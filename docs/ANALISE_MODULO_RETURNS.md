# Análise do Módulo — Devoluções / Trocas (Returns)

**Data de conclusão:** 19/04/2026
**Versão:** 1.0 (v2 completo — migração adaptada de `adms_returns` v1 para contexto e-commerce)
**Testes:** 51 testes / 155 assertions / 6 suites
**Rotas:** 14 principais + 4 config-reasons
**Permissions:** 11
**Commands agendados:** 1

---

## Visão Geral

Módulo de **solicitações de devolução/troca/estorno originadas no e-commerce**, registradas pelo time de atendimento online (chat, WhatsApp, email). Distinto e independente do módulo **Reversals** (que cobre estornos no PDV físico autorizados por gerente).

Cobre o ciclo completo da logística reversa:

1. **Registro** — atendimento localiza a NF/cupom em `movements` (`movement_code=2`, loja default `Z441`) e abre a solicitação com motivo categorizado.
2. **Aprovação** — responsável avalia e aprova (ou cancela) a solicitação.
3. **Aguardando produto** — cliente recebe instruções, posta o item pelos Correios; o atendimento registra o código de rastreio.
4. **Processamento** — produto recebido é conferido e o reembolso/crédito/troca é executado.
5. **Conclusão** — registro terminal com histórico completo.

### Escopo vs Reversals

| Dimensão                | Returns (e-commerce)                | Reversals (PDV físico)          |
|-------------------------|-------------------------------------|---------------------------------|
| Canal                   | Online (atendimento registra)       | Venda física (gerente autoriza) |
| Loja                    | Z441 (default, e-commerce)          | Qualquer                        |
| Tipos                   | troca, estorno, credito (3)         | total, partial (2)              |
| Fluxo                   | Logística reversa (cliente posta)   | Autorização imediata + execução |
| Integração Helpdesk     | Não (standalone)                    | Sim (hook fail-safe)            |
| CIGAM push              | Não (NF emitida pela contabilidade) | Sim (stub + command every15min) |
| Estados                 | 6                                   | 6                               |

Os dois módulos **não compartilham dados**. Um cliente com devolução via site abre `ReturnOrder`; uma vendedora no PDV com problema de cobrança usa `Reversal`.

---

## Arquitetura

### Nomenclatura

`Return` é palavra reservada do PHP — classe de domínio se chama **`ReturnOrder`** (espelha `PurchaseOrder`). Tabelas: `return_orders`, `return_order_items`, `return_order_status_histories`, `return_order_files`, `return_reasons`. URL pública: `/returns`. Slug do módulo: `returns`. Permissions: `returns.*`.

### Enums (3)

| Arquivo                       | Propósito                                                                                     |
|-------------------------------|-----------------------------------------------------------------------------------------------|
| `ReturnStatus.php`            | State machine — 6 estados + `allowedTransitions()`, `isTerminal()`, `active()`, `transitionMap()` |
| `ReturnType.php`              | `troca` / `estorno` / `credito` — label, color, `requiresRefundAmount()` (estorno+credito)    |
| `ReturnReasonCategory.php`    | 6 categorias fixas (`ARREPENDIMENTO`, `DEFEITO`, `DIVERGENCIA`, `TAMANHO_COR`, `NAO_RECEBIDO`, `OUTRO`) |

### State machine

```
pending
   ├─► approved
   │      ├─► pending (volta)
   │      ├─► awaiting_product
   │      │       ├─► approved (volta)
   │      │       ├─► processing
   │      │       │       ├─► awaiting_product (volta)
   │      │       │       ├─► completed (terminal)
   │      │       │       └─► cancelled (terminal)
   │      │       └─► cancelled
   │      └─► cancelled
   └─► cancelled
```

- `awaiting_product` = cliente deve postar o produto (logística reversa dos Correios).
- Permissões por transição: `APPROVE_RETURNS` para aprovar/cancelar/voltar; `PROCESS_RETURNS` para movimentações operacionais (`awaiting_product` → `processing` → `completed`); `CANCEL_RETURNS` alias granular de APPROVE.

### Models (5)

| Arquivo                         | Propósito                                                                                     |
|---------------------------------|-----------------------------------------------------------------------------------------------|
| `ReturnOrder.php`               | Tabela principal, Auditable trait, casts para 3 enums, 9 scopes, 11 relações                  |
| `ReturnOrderItem.php`           | Itens devolvidos com quantidade parcial. FK nullable para `movements.id` + snapshot           |
| `ReturnOrderStatusHistory.php`  | Audit trail de transições com usuário, from/to, note                                          |
| `ReturnOrderFile.php`           | Anexos múltiplos (fotos do defeito, print do chat, comprovante de envio)                      |
| `ReturnReason.php`              | Catálogo de motivos (config module), FK para `ReturnReasonCategory`                           |

### Services (5)

| Arquivo                              | Propósito                                                                                     |
|--------------------------------------|-----------------------------------------------------------------------------------------------|
| `ReturnOrderService.php`             | CRUD + snapshot de movements na criação + `persistItems()` com quantidade parcial + `ensureNoDuplicate()` + upload de anexos + soft delete |
| `ReturnOrderLookupService.php`       | `lookupInvoice($invoice, $storeCode=null, $movementDate=null)` — store default Z441, retorna `available_dates[]` para desambiguação |
| `ReturnOrderTransitionService.php`   | Ponto único de mutação de status. Valida transições + permissões por transição + exige note em cancelled. Dispatch do event `ReturnOrderStatusChanged` |
| `ReturnOrderExportService.php`       | Export XLSX (listagem com filtros) + Export PDF individual (comprovante A4 via dompdf)        |
| `ReturnOrderImportService.php`       | Import XLSX/CSV em 2 passos (`preview` + `import`). Upsert por `(invoice_number, store_code, type)` |

### Controller

**`ReturnOrderController.php`** — 14 métodos públicos:

| Método           | Rota                                    | Propósito                                        |
|------------------|-----------------------------------------|--------------------------------------------------|
| `index`          | `GET /returns`                          | Lista paginada + StatisticsGrid + filtros        |
| `store`          | `POST /returns`                         | Criação com lookup de NF + anexos                |
| `show`           | `GET /returns/{id}`                     | JSON detalhado (AJAX modal)                      |
| `update`         | `PUT /returns/{id}`                     | Atualização limitada (só em estados iniciais)    |
| `destroy`        | `DELETE /returns/{id}`                  | Soft delete com motivo obrigatório               |
| `transition`     | `POST /returns/{id}/transition`         | Mudança de status via `ReturnOrderTransitionService` |
| `lookupInvoice`  | `GET /returns/lookup-invoice`           | AJAX — resolve NF em `movements`                 |
| `statistics`     | `GET /returns/statistics`               | KPIs para refresh sem page reload                |
| `dashboard`      | `GET /returns/dashboard`                | Página analítica separada                        |
| `export`         | `GET /returns/export`                   | XLSX com filtros aplicados                       |
| `exportPdf`      | `GET /returns/{id}/pdf`                 | Comprovante PDF                                  |
| `importPreview`  | `POST /returns/import/preview`          | Validação sem persistir                          |
| `importStore`    | `POST /returns/import`                  | Persistência do import                           |
| `destroyFile`    | `DELETE /returns/{id}/files/{file}`     | Remove anexo                                     |

### Eventos e Listeners

| Arquivo                              | Propósito                                                                                      |
|--------------------------------------|------------------------------------------------------------------------------------------------|
| `ReturnOrderStatusChanged.php`       | Event disparado post-commit pelo `ReturnOrderTransitionService`                                |
| `NotifyReturnOrderStakeholders.php`  | Listener — envia database notification (sino do frontend) com matriz de destinatários por transição |

### Notifications

| Arquivo                                         | Tipo                | Uso                                             |
|-------------------------------------------------|---------------------|-------------------------------------------------|
| `ReturnOrderStatusChangedNotification.php`      | `database`          | Sino do frontend a cada transição               |
| `ReturnOrderStaleAlertNotification.php`         | `mail` + `database` | Alerta diário de devoluções paradas em `awaiting_product` |

### Commands agendados

| Command                     | Frequência   | Propósito                                                                                   |
|-----------------------------|--------------|---------------------------------------------------------------------------------------------|
| `returns:stale-alert`       | daily 09:00  | Notifica processadores (PROCESS_RETURNS) de devoluções em `awaiting_product` há mais de N dias (threshold 7 dias default via `--days=N`) |

**Decisão de referência temporal**: o command mede dias desde `approved_at` até hoje, com fallback para `created_at` quando `approved_at` é nulo. Isso reflete o SLA real — o cliente só fica responsável pela logística reversa depois da aprovação.

### Migrations (7)

| Migration                                                                          | Tabela            | Destaques                                                                                             |
|------------------------------------------------------------------------------------|-------------------|-------------------------------------------------------------------------------------------------------|
| `2026_04_19_500001_seed_returns_module_and_permissions.php`                        | central           | Seed `central_modules` + `central_permissions` + `central_pages` + menu + `tenant_modules` (Professional + Enterprise) |
| `2026_04_19_400001_create_return_reasons_table.php`                                | tenant            | Config module com 15 motivos pré-carregados em 6 categorias                                           |
| `2026_04_19_400002_create_return_orders_table.php`                                 | tenant            | Tabela principal + soft delete + índice composto `(invoice_number, store_code, type)` para dedup      |
| `2026_04_19_400003_create_return_order_items_table.php`                            | tenant            | Itens devolvidos com `quantity` e `subtotal` por linha                                                |
| `2026_04_19_400004_create_return_order_status_histories_table.php`                 | tenant            | Audit trail de transições                                                                             |
| `2026_04_19_400005_create_return_order_files_table.php`                            | tenant            | Anexos múltiplos                                                                                      |
| `2026_04_19_400006_add_valor_divergente_return_reason.php`                         | tenant            | Migration idempotente adicionando motivo `DIV_VALOR` à categoria `DIVERGENCIA` (caso comum pós-MVP)   |

---

## Frontend

### Páginas (2)

- **`resources/js/Pages/Returns/Index.jsx`** — lista paginada + `StatisticsGrid` (cards clicáveis para filtro rápido) + filtros (busca, loja, tipo, status, categoria) + 5 modais inline (`StandardModal`):
  - **Create** — lookup de NF (com seletor de data quando há múltiplas) → tabela de seleção de itens com **quantidade editável por linha** → seletor de motivo em cascata (categoria → motivo específico) → valor a reembolsar com máscara BR (condicional a estorno/credito) → anexos
  - **Detail** — Timeline de status + botão "Baixar comprovante" (PDF) + badges no header (status, tipo, categoria)
  - **Edit** — só campos editáveis (nome, categoria, motivo, notes, tracking)
  - **Transition** — select filtrado pelas transições válidas do backend, com note obrigatório em cancelled
  - **Delete** — motivo obrigatório mínimo 3 chars

- **`resources/js/Pages/Returns/Dashboard.jsx`** — página analítica separada com cards KPI + pizzas de distribuição (status, categoria, tipo) + linha temporal dos últimos 12 meses + métricas de performance.

### Sub-componentes (3)

Em `resources/js/Pages/Returns/components/`:

| Arquivo                               | Propósito                                                                                      |
|---------------------------------------|------------------------------------------------------------------------------------------------|
| `InvoiceLookupSection.jsx`            | Input com debounce 500ms, cache por `(storeCode, invoice, movementDate)`, indicador visual     |
| `ItemSelectionWithQuantityTable.jsx`  | Tabela com checkbox + **input de quantidade editável** por linha (qty ≤ qty comprada); recalcula subtotal em tempo real |
| `ReasonCategorySelector.jsx`          | Seletor em cascata — primeiro categoria (obrigatório), depois motivo específico (opcional, filtrado pela categoria) |

---

## Decisões arquiteturais não-óbvias

### 1. Nome da classe `ReturnOrder` (não `Return`)

`return` é palavra reservada do PHP — a classe não pode se chamar `Return`. Padrão: `ReturnOrder` (espelha `PurchaseOrder`). Tabelas seguem o mesmo prefixo (`return_orders`, etc.). Rotas e slugs ficam com `returns`.

### 2. Store default Z441 no lookup

No modelo v2, todas as vendas e-commerce são registradas no CIGAM na loja `Z441` (reservada ao online). O `ReturnOrderLookupService::lookupInvoice()` assume `Z441` como default quando `storeCode` não é informado — 99% dos casos. O parâmetro fica aberto para registrar uma devolução de loja física que veio parar no canal online (raro, mas possível).

Futuro: `ECOMMERCE_STORE_CODE` em `.env` para tornar configurável sem code change.

### 3. Múltiplas datas (ano cruzado)

Sequências de cupom **reiniciam entre anos** — é comum o mesmo `invoice_number` aparecer em 2021 e em 2026 dentro da mesma loja. O `ReturnOrderLookupService` retorna `available_dates[]` ordenadas DESC; quando há mais de uma, o frontend mostra um seletor de data para o usuário desambiguar. Por default, seleciona a mais recente. Mesma lógica do Reversals.

### 4. Quantidade parcial por item

Cliente pode devolver **N de M unidades compradas** — compra 3 pares de meia, devolve 1. O frontend (`ItemSelectionWithQuantityTable`) permite editar a quantidade por linha. O service calcula `subtotal = unit_price * requested_qty` e recalcula `amount_items = SUM(subtotals)` após persistir. Backend valida `requested_qty ≤ original_qty` via snapshot de movements.

### 5. Motivos categorizados — enum + catálogo

Dupla estrutura:
- **`ReturnReasonCategory`**: enum fixo de 6 categorias — **obrigatório** no backend, padroniza agregações do dashboard
- **`ReturnReason`**: config module com ~15 motivos específicos, cada um com FK para uma categoria — **opcional**, usado pelo atendimento para registrar o motivo exato

No frontend o seletor é em cascata: primeiro a categoria (obrigatória), depois filtra os motivos daquela categoria. Isso mantém as estatísticas do dashboard consistentes independentemente de como o catálogo evolui.

**DIV_VALOR (2026-04-19)** — motivo `Valor cobrado incorreto` adicionado pós-MVP via migration idempotente após observação de uso real: cliente cobrado errado, cupom não aplicado, frete incorreto. Fica na categoria `DIVERGENCIA`.

### 6. Dedup via service (não via constraint)

MySQL não permite unique composite com soft delete (múltiplos `NULL` em `deleted_at` são tratados como distintos). Além disso, a regra de dedup aqui permite **mesma NF com tipos diferentes** — ex: troca do item A + estorno do item B da mesma NF.

**Solução**: `ReturnOrderService::ensureNoDuplicate()` faz a checagem explícita antes do insert — bloqueia `(invoice_number, store_code, type)` já ativo. O índice composto na migration serve apenas para performance da query.

### 7. `refund_amount` condicional + máscara BR

`ReturnType::requiresRefundAmount()` retorna `true` apenas para `estorno` e `credito`. O frontend condiciona o campo à seleção do tipo. Input usa `maskMoney` do `useMasks` hook (formato `1.234,56`, prefixo `R$`), convertido via `parseMoney` para float antes do submit. Para edit, hidratação via `maskMoney(Math.round(Number(r.refund_amount) * 100).toString())` para re-formatar o valor vindo da API.

### 8. Stale-alert usa `approved_at`, não `created_at`

O SLA real da devolução começa no **momento da aprovação** — antes disso o cliente pode estar apenas discutindo via chat. Medir desde `created_at` inflaria falsos positivos. Por isso o command `returns:stale-alert` filtra `status = awaiting_product` e mede `today - approved_at` (fallback `created_at` quando nulo, para registros legacy).

Threshold default 7 dias, configurável via `--days=N`. Alerta vai apenas para usuários com `PROCESS_RETURNS`.

### 9. Default listing esconde apenas `cancelled`

Diferente do Reversals (que esconde `completed + cancelled` por default), aqui só `cancelled` é escondido. Motivo: **o atendimento consulta devoluções concluídas com frequência** — cliente pergunta "minha devolução foi aprovada?" dias depois de concluída. Esconder `completed` adicionaria um clique a cada consulta.

Flag `include_cancelled=1` traz canceladas quando necessário (auditoria, análise).

### 10. Store scoping por ausência de `MANAGE_RETURNS`

Mesmo padrão de Vacancies, Reversals, PurchaseOrders: usuário sem `MANAGE_RETURNS` fica restrito à própria loja via `user.store_id`. Na prática e-commerce, **todas as devoluções têm `store_code=Z441`**, então o scoping raramente filtra algo na prática — mas é mantido por consistência e para o caso de uma devolução de loja física excepcional.

### 11. Commands com `scanTenant()` extraído

Mesmo padrão dos Reversals: em SQLite in-memory `Tenant::all()` retorna vazio. O método `scanTenant()` foi extraído do `handle()` para permitir invocação direta nos testes sem depender do loop de tenants.

### 12. Escopo explícito rejeitado na discussão

Features deliberadamente deixadas fora do MVP após decisão com o stakeholder:

| Rejeitado                               | Motivo                                                                       |
|-----------------------------------------|------------------------------------------------------------------------------|
| Controle de prazo CDC (D7)              | Cliente optou por não automatizar — política tratada fora do sistema         |
| Endereço de coleta estruturado          | Correios são padrão — endereço texto livre é suficiente                      |
| Tracking automático Correios            | `reverse_tracking_code` fica como texto livre; sem polling de API            |
| Hook Helpdesk (D8)                      | Standalone — fluxo e-commerce não passa pelo Helpdesk                        |
| NF-e de devolução (D9)                  | Contabilidade externa emite via ERP — fora do escopo do módulo               |
| Integração adquirente (D10)             | Estorno manual via painel do banco — sem push ao Cielo/Stone                 |

---

## Permissões (11)

| Slug                           | Label                                                 | Default                                 |
|--------------------------------|-------------------------------------------------------|-----------------------------------------|
| `returns.view`                 | Visualizar devoluções                                 | SUPER_ADMIN, ADMIN, SUPPORT             |
| `returns.create`               | Criar solicitações de devolução                       | SUPER_ADMIN, ADMIN, SUPPORT             |
| `returns.edit`                 | Editar devoluções                                     | SUPER_ADMIN, ADMIN, SUPPORT             |
| `returns.approve`              | Aprovar/cancelar/voltar (state machine não-terminal)  | SUPER_ADMIN, ADMIN                      |
| `returns.process`              | Processar (awaiting_product → processing → completed) | SUPER_ADMIN, ADMIN, SUPPORT             |
| `returns.cancel`               | Cancelar (alias granular de APPROVE)                  | SUPER_ADMIN, ADMIN, SUPPORT             |
| `returns.delete`               | Excluir (soft delete)                                 | SUPER_ADMIN, ADMIN                      |
| `returns.manage`               | Gerenciar todas as lojas (sem scoping)                | SUPER_ADMIN, ADMIN                      |
| `returns.import`               | Importar planilha                                     | SUPER_ADMIN, ADMIN                      |
| `returns.export`               | Exportar XLSX/PDF                                     | SUPER_ADMIN, ADMIN, SUPPORT             |
| `returns.manage_reasons`       | CRUD de motivos                                       | SUPER_ADMIN, ADMIN                      |

---

## Testes

**Total: 51 tests / 155 assertions / 6 suites**

| Suite                              | Tests | Cobertura                                                                     |
|------------------------------------|-------|-------------------------------------------------------------------------------|
| `ReturnOrderControllerTest`        | 15    | Index + filtros + store scoping + CRUD + validações + dedup + statistics      |
| `ReturnOrderTransitionTest`        | 11    | State machine completa + permissões + events + history                        |
| `ReturnOrderLookupTest`            | 7     | Lookup em movements + store default Z441 + múltiplas datas                    |
| `ReturnOrderIntegrationTest`       | 5     | Matriz de notifications por transição                                         |
| `ReturnOrderCommandsTest`          | 5     | `stale-alert` (threshold, recipients, approved_at vs created_at)              |
| `ReturnOrderImportExportTest`      | 8     | Excel + PDF + Import preview/persist + BR decimal + upsert                    |

Todos em-memory SQLite. Não dependem de movements reais — cada teste semeia seus próprios `movements` quando necessário.

---

## Dependências de módulos

- **`movements`** — fonte de verdade do lookup de NF (obrigatório)
- **`stores`** — scoping e referência de loja (obrigatório)
- **`employees`** — snapshot opcional do consultor via CPF

Configurado em `config/modules.php`:
```php
'returns' => [
    'name' => 'Devoluções',
    'routes' => ['returns.*'],
    'icon' => 'ArrowUturnLeftIcon',
    'dependencies' => ['movements', 'stores'],
],
```

Habilitado nos planos **Professional** e **Enterprise**. Não habilitado em **Starter**.

---

## Bugs corrigidos durante desenvolvimento

| Bug                                                                                   | Correção                                                                                           |
|---------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------|
| 3 badges vazios no cabeçalho do modal detalhe                                         | `StandardModal` espera `{text, className}`, não `{label, variant}`. Afetava Returns e Reversals    |
| Input `refund_amount` sem máscara monetária                                           | Migrado para `<input type="text">` com `maskMoney`/`parseMoney` do hook `useMasks`                 |
| Default listing escondia registros completed (cliente consulta com frequência)        | Ajustado para esconder só cancelled; flag `include_cancelled=1` substitui `include_terminal`       |
| Faltava motivo para divergência de valor (cobrança errada, cupom não aplicado)        | Migration idempotente `2026_04_19_400006` adicionando `DIV_VALOR` à categoria DIVERGENCIA          |

---

## Backlog pós-MVP

Não bloqueante. Features conhecidas que ficaram fora do MVP mas têm infraestrutura pronta.

| # | Feature                                 | Esforço       | Valor  | Nota                                                                  |
|---|-----------------------------------------|---------------|--------|-----------------------------------------------------------------------|
| 1 | UI de import XLSX/CSV                   | Pequeno       | Médio  | Rotas `returns.import.*` funcionam; falta `Pages/Returns/Import.jsx`  |
| 2 | Tracking automático Correios            | Médio         | Alto   | Polling do `reverse_tracking_code` via API SRO dos Correios           |
| 3 | NF-e de devolução                       | Grande        | Alto   | Emissão automática via integração com emissor (atualmente manual)     |
| 4 | Hook Helpdesk (opt-in)                  | Pequeno       | Médio  | Abrir ticket no departamento Atendimento em transições críticas       |
| 5 | Classificação AI de motivo              | Médio-grande  | Médio  | Sugerir motivo baseado em `notes` via Groq (similar ao classifier do Helpdesk) |
| 6 | Push direto a adquirente                | Grande        | Alto   | API Cielo/Stone para estorno automático em cartão                     |

---

## Referências

- **Código**: `app/{Enums,Events,Http,Listeners,Models,Notifications,Services}/**Return*`, `resources/js/Pages/Returns/`, `database/migrations/tenant/2026_04_19_4*`, `tests/Feature/ReturnOrder*`
- **V1 origem**: `C:\wamp64\www\mercury\app\adms\Controllers\*Return*.php` + `AdmsModels*Return*.php`
- **Memory interna**: `memory/returns_module.md`
- **Documento irmão**: `ANALISE_MODULO_REVERSALS.md` (módulo distinto para PDV físico)
