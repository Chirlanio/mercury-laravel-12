# Análise do Módulo — Estornos (Reversals)

**Data de conclusão:** 17/04/2026
**Versão:** 1.0 (v2 completo — paridade v1 `adms_estornos` adaptada ao modelo de dados Laravel)
**Testes:** 62 testes / 184 assertions / 6 suites
**Rotas:** 18 (14 principais + 4 config-reasons)
**Permissions:** 10
**Commands agendados:** 2

---

## Visão Geral

Módulo de solicitações de estorno de vendas. Migração do subsistema v1 (`adms_estornos` do Mercury PHP MVC) para Laravel 12 + React 18 + Inertia.js 2, **adaptado ao modelo de dados v2** onde a fonte de verdade das vendas é a tabela `movements` (`movement_code=2`) e não uma tabela `sales` granular (que na v2 é daily-aggregated por vendedor/loja).

Cobre o ciclo completo:

1. **Registro** — usuário seleciona a loja e informa a NF/cupom; o sistema busca em `movements` e agrega sale_total/itens.
2. **Workflow** — state machine de 6 estados com permissões por transição.
3. **Execução** — registro da devolução efetiva (PIX, cartão, etc.) com snapshot auditável.
4. **Integrações** — notificações database + hook opcional que abre ticket no Helpdesk Financeiro; command de push ao CIGAM (stub até ter credenciais de escrita).

---

## Arquitetura

### Enums (3)

| Arquivo | Propósito |
|---------|-----------|
| `ReversalStatus.php` | State machine — 6 estados + `allowedTransitions()`, `isTerminal()`, `active()`, `transitionMap()` |
| `ReversalType.php` | `total` / `partial` — label, color, options |
| `ReversalPartialMode.php` | `by_value` / `by_item` — só aplicável a `partial` |

### State machine

```
pending_reversal
    ├─► pending_authorization
    │       ├─► pending_reversal (volta)
    │       ├─► authorized
    │       │       ├─► pending_authorization (volta)
    │       │       ├─► pending_finance
    │       │       │       ├─► authorized (volta)
    │       │       │       ├─► reversed (terminal)
    │       │       │       └─► cancelled (terminal)
    │       │       ├─► reversed
    │       │       └─► cancelled
    │       ├─► reversed
    │       └─► cancelled
    ├─► reversed
    └─► cancelled
```

### Models (5)

| Arquivo | Propósito |
|---------|-----------|
| `Reversal.php` | Tabela principal (32 colunas + soft delete manual), Auditable trait, casts para os 3 enums, 6 scopes (`byStore`, `byStatus`, `pendingApproval`, `forMonth`, `notDeleted`, `pendingCigamSync`), 11 relações |
| `ReversalItem.php` | Itens de estorno parcial `by_item`. FK nullable para `movements.id` (resiliente a re-sync) + snapshot de barcode/ref_size/product_name/quantity/unit_price |
| `ReversalStatusHistory.php` | Audit trail de transições (substitui triggers MySQL da v1) |
| `ReversalFile.php` | Anexos múltiplos (NF digitalizada, prints, comprovantes) |
| `ReversalReason.php` | Catálogo de motivos (config module) |

### Services (5)

| Arquivo | Propósito |
|---------|-----------|
| `ReversalService.php` | CRUD + snapshot de movements na criação + cálculo de `amount_reversal` por type/partial_mode + `ensureNoDuplicate()` + upload de anexos + soft delete |
| `ReversalLookupService.php` | `lookupInvoice($invoice, $storeCode, $movementDate?)` — busca em `Movement::sales()`, agrupa por data, retorna `available_dates[]` para desambiguação (cupom repete entre anos) |
| `ReversalTransitionService.php` | Ponto único de mutação de status. Valida transições + permissões por transição + exige note em `cancelled`. Dispatch do event `ReversalStatusChanged`. |
| `ReversalExportService.php` | Export XLSX (listagem com filtros — 33 colunas) + Export PDF individual (comprovante A4 via dompdf) |
| `ReversalImportService.php` | Import XLSX/CSV em 2 passos (`preview` + `import`). Upsert por `(invoice_number, store_code, amount_original)`. Aceita ~40 variações de header PT-BR, parses decimal BR (`1.234,56`) e data `d/m/Y` |

### Form Requests (3)

| Arquivo | Propósito |
|---------|-----------|
| `StoreReversalRequest.php` | Regras condicionais: `partial_mode` obrigatório quando `type=partial`, `amount_correct` obrigatório em `by_value`, `items[]` em `by_item` |
| `UpdateReversalRequest.php` | Só campos editáveis (snapshots da venda são imutáveis) |
| `TransitionReversalRequest.php` | `note` obrigatório quando `to_status=cancelled` |

### Controller

**`ReversalController.php`** — 14 métodos públicos:

| Método | Rota | Propósito |
|--------|------|-----------|
| `index` | `GET /reversals` | Lista paginada + StatisticsGrid + filtros |
| `store` | `POST /reversals` | Criação com lookup de NF + anexos |
| `show` | `GET /reversals/{id}` | JSON detalhado (AJAX modal) |
| `update` | `PUT /reversals/{id}` | Atualização limitada (só em estados iniciais) |
| `destroy` | `DELETE /reversals/{id}` | Soft delete com motivo obrigatório |
| `transition` | `POST /reversals/{id}/transition` | Mudança de status via `ReversalTransitionService` |
| `lookupInvoice` | `GET /reversals/lookup-invoice` | AJAX — resolve NF em `movements` |
| `statistics` | `GET /reversals/statistics` | KPIs para refresh sem page reload |
| `dashboard` | `GET /reversals/dashboard` | Página analítica separada |
| `export` | `GET /reversals/export` | XLSX com filtros aplicados |
| `exportPdf` | `GET /reversals/{id}/pdf` | Comprovante PDF |
| `importPreview` | `POST /reversals/import/preview` | Validação sem persistir |
| `importStore` | `POST /reversals/import` | Persistência do import |
| `destroyFile` | `DELETE /reversals/{id}/files/{file}` | Remove anexo |

### Eventos e Listeners

| Arquivo | Propósito |
|---------|-----------|
| `ReversalStatusChanged.php` | Event disparado post-commit pelo `ReversalTransitionService` |
| `NotifyReversalStakeholders.php` | Listener — envia database notification (sino do frontend) com matriz de destinatários por transição |
| `OpenHelpdeskTicketForReversal.php` | Listener — hook opcional que abre ticket no departamento "Financeiro" quando transita para `pending_authorization` (idempotente + fail-safe) |

### Notifications

| Arquivo | Tipo | Uso |
|---------|------|-----|
| `ReversalStatusChangedNotification.php` | `database` | Sino do frontend a cada transição |
| `ReversalStaleAlertNotification.php` | `mail` + `database` | Alerta diário consolidado de estornos atrasados em aprovação |

### Commands agendados

| Command | Frequência | Propósito |
|---------|------------|-----------|
| `reversals:cigam-push` | every 15 min | Marca estornos executados como sincronizados com o CIGAM (stub — `pushToCigam()` é no-op até ter credenciais de escrita no ERP) |
| `reversals:stale-alert` | daily 09:00 | Notifica aprovadores de estornos em `pending_authorization` há mais de 3 dias (threshold configurável via `--days=N`) |

### Migrations (5)

| Migration | Tabela | Destaques |
|-----------|--------|-----------|
| `2026_04_19_200001_seed_reversals_module_and_permissions.php` | central | Seed central_modules + central_permissions + central_pages + menu + tenant_modules |
| `2026_04_19_300001_create_reversal_reasons_table.php` | tenant | Config module com 8 motivos pré-carregados (FURO_ESTOQUE, DESISTENCIA, TAMANHO_ERRADO, VALOR_INCORRETO, DUPLICIDADE, QUALIDADE, TROCA, OUTROS) |
| `2026_04_19_300002_create_reversals_table.php` | tenant | Tabela principal com 32 colunas + soft delete manual + índice `idx_reversal_dedup_lookup` para performance do `ensureNoDuplicate` |
| `2026_04_19_300003_create_reversal_items_table.php` | tenant | Itens para `partial by_item` |
| `2026_04_19_300004_create_reversal_status_histories_table.php` | tenant | Audit trail de transições |
| `2026_04_19_300005_create_reversal_files_table.php` | tenant | Anexos múltiplos |

---

## Frontend

### Páginas (2)

- **`resources/js/Pages/Reversals/Index.jsx`** — lista paginada + `StatisticsGrid` (5 cards clicáveis para filtro rápido) + filtros (busca, loja, tipo, status, data) + 5 modais inline (`StandardModal`):
  - **Create** — seção Venda (lookup com auto-detect de múltiplas datas) + Tipo (total/partial by_value/by_item) + Pagamento (com PIX condicional) + Anexos
  - **Detail** — Timeline de status + botão "Baixar comprovante" (PDF)
  - **Edit** — só campos editáveis
  - **Transition** — select filtrado pelas transições válidas do backend
  - **Delete** — motivo obrigatório mínimo 3 chars

- **`resources/js/Pages/Reversals/Dashboard.jsx`** — página analítica separada com:
  - 5 cards de KPI (Total, Aguardando aprovação, Aguardando financeira, Estornado no mês, Taxa de autorização)
  - Pizza de distribuição por status
  - Pizza dos top motivos
  - Linha temporal dos últimos 12 meses (eixo duplo: quantidade + valor)
  - Barra horizontal dos top 15 lojas (ocultado quando scoped)
  - Métricas de performance (tempo médio criação → estorno, taxa de autorização)

### Sub-componentes (4)

Em `resources/js/Pages/Reversals/components/`:

| Arquivo | Propósito |
|---------|-----------|
| `InvoiceLookupSection.jsx` | Input com debounce 500ms, cache por `(storeCode, invoice, movementDate)`, indicador visual (searching/found/error) |
| `ItemSelectionTable.jsx` | Tabela com checkbox para seleção múltipla + total selecionado em tempo real |
| `PixFieldsSection.jsx` | Bloco condicional (aparece quando `payment_type` é PIX) com tipo de chave + chave + beneficiário + banco |
| `ReversalFilesUpload.jsx` | Drag-and-drop múltiplo (até 10 arquivos, 10MB cada), preview com remoção |

---

## Decisões arquiteturais não-óbvias

### 1. Lookup em `movements`, não em `sales`

A tabela `sales` v2 é **agregada** por dia/vendedor (unique `store_id + employee_id + date_sales`). Não tem NF, cupom nem itens. Portanto o lookup precisa acontecer em `movements` (`movement_code=2`), onde cada linha é um item de uma venda e o agregador é o `invoice_number`.

**Consequência**: `ReversalLookupService::lookupInvoice()` faz `Movement::where('movement_code', 2)->where('invoice_number', X)->where('store_code', Y)`. Cada linha = um item; `sale_total = SUM(realized_value)`.

### 2. Store code obrigatório no lookup

Número de cupom fiscal **não é único entre lojas** — cada loja emite sequências independentes. Sem `store_code` o lookup retornaria vendas erradas. O frontend força a seleção da loja antes de habilitar o input de NF.

### 3. Múltiplas datas (ano cruzado)

Sequências de cupom **reiniciam entre anos** — é comum o mesmo `invoice_number` aparecer em 2021 e em 2026 dentro da mesma loja. O `ReversalLookupService` retorna `available_dates[]` ordenadas DESC; quando há mais de uma, o frontend mostra um seletor de data para o usuário desambiguar. Por default, seleciona a mais recente.

### 4. Dedup via service (não via constraint)

Tentativa inicial: unique composto `(invoice_number, store_code, amount_original, deleted_at)`. Isso **não funciona no MySQL** — múltiplos `NULL` em unique composite são tratados como distintos, então a constraint não bloqueia duplicatas de registros ativos (`deleted_at IS NULL`).

**Solução**: `ReversalService::ensureNoDuplicate()` faz a checagem explícita antes do insert. O índice composto serve apenas para performance da query.

### 5. Snapshot dos movements na criação

Todos os dados do cabeçalho da venda (store_code, movement_date, cpf_consultant, sale_total) são gravados em `reversals` no momento da criação. Isso protege contra:
- Re-sync de `movements` que apague/altere linhas antigas
- Auditoria histórica — o estorno mantém os valores originais mesmo se o CIGAM for reimportado

Para `partial by_item`, os itens também recebem snapshot (barcode, ref_size, quantity, unit_price). O `movement_id` é FK nullable — se a linha em `movements` sumir, mantemos o snapshot.

### 6. Store scoping por ausência de permissão

Usuário sem `MANAGE_REVERSALS` fica automaticamente restrito à própria loja (via `user.store_id`). Mesmo padrão usado em `Vacancies` e `PurchaseOrders`.

**Consequência**: SUPPORT role **não tem** `MANAGE_REVERSALS` por design — vê apenas a própria loja. SUPER_ADMIN e ADMIN têm e veem tudo.

### 7. Hook Helpdesk fail-safe

`OpenHelpdeskTicketForReversal` listener abre um ticket no departamento "Financeiro" do Helpdesk ao transitar para `pending_authorization`. Três características:

- **Idempotente**: verifica `reversal.helpdesk_ticket_id` antes de criar (não cria duas vezes mesmo se a transição for revertida e reaplicada)
- **Fail-safe**: se o módulo Helpdesk não estiver instalado, se o departamento "Financeiro" não existir (`HdDepartment::where('name', 'financeiro')`), ou se qualquer erro ocorrer, apenas loga e segue — **nunca quebra o fluxo de transição**
- **Opt-in por tenant**: basta (não) cadastrar o departamento "Financeiro" para ligar/desligar o hook

### 8. CIGAM push é stub

`ReversalsCigamPushCommand::pushToCigam()` é um no-op. Apenas grava `synced_to_cigam_at = now()` localmente. A gravação real no `msl_fmovimentodiario_` depende de credenciais de escrita no PostgreSQL do ERP, que não estão configuradas nesta instalação.

O command é idempotente (scope `pendingCigamSync` só seleciona registros com `synced_to_cigam_at IS NULL`). Quando a integração for ativada, basta implementar o método — sem mudanças no resto do módulo.

### 9. Commands com `scanTenant()` extraído

Commands `reversals:cigam-push` e `reversals:stale-alert` iteram sobre `Tenant::all()->run()`. Em testing (SQLite in-memory), `Tenant::all()` vem vazio e o command sai sem processar nada. Por isso o método `scanTenant()` foi extraído do `handle()` — testes invocam diretamente sem depender do loop de tenants.

### 10. Dashboard com alias para evitar cast do Eloquent

O método `buildAnalytics` usa `selectRaw('status as status_value, ...')` em vez de `selectRaw('status, ...')`. Motivo: o model `Reversal` tem cast `'status' => ReversalStatus::class`, e o Eloquent aplica o cast mesmo quando a coluna vem de um `selectRaw`. Acessar `$r->status` retornava o enum em vez da string, quebrando `ReversalStatus::tryFrom($enum)`. O alias contorna o cast.

---

## Permissões (10)

| Slug | Label | Default |
|------|-------|---------|
| `reversals.view` | Visualizar estornos | SUPER_ADMIN, ADMIN, SUPPORT |
| `reversals.create` | Criar solicitações de estorno | SUPER_ADMIN, ADMIN, SUPPORT |
| `reversals.edit` | Editar solicitações | SUPER_ADMIN, ADMIN, SUPPORT |
| `reversals.delete` | Excluir estornos (soft delete) | SUPER_ADMIN, ADMIN |
| `reversals.approve` | Autorizar e cancelar | SUPER_ADMIN, ADMIN, SUPPORT |
| `reversals.process` | Executar (aguardando financeira → estornado) | SUPER_ADMIN, ADMIN, SUPPORT |
| `reversals.manage` | Gerenciar todas as lojas (sem scoping) | SUPER_ADMIN, ADMIN |
| `reversals.import` | Importar planilha | SUPER_ADMIN, ADMIN |
| `reversals.export` | Exportar XLSX/PDF | SUPER_ADMIN, ADMIN, SUPPORT |
| `reversals.manage_reasons` | CRUD de motivos | SUPER_ADMIN, ADMIN |

---

## Testes

**Total: 62 tests / 184 assertions / 5.89s**

| Suite | Tests | Cobertura |
|-------|-------|-----------|
| `ReversalControllerTest` | 18 | Index + filtros + store scoping + CRUD + validações + dedup + statistics |
| `ReversalTransitionTest` | 12 | State machine completa + permissões + events + history |
| `ReversalLookupTest` | 8 | Lookup em movements + múltiplas datas + filtro de loja + endpoint |
| `ReversalIntegrationTest` | 7 | Matriz de notifications + hook Helpdesk idempotente + fail-safe |
| `ReversalCommandsTest` | 8 | `cigam-push` (idempotente, dry-run, skipa soft-deleted) + `stale-alert` (threshold, recipients) |
| `ReversalImportExportTest` | 9 | Excel + PDF + Import preview/persist + BR decimal + upsert |

Todos em-memory SQLite. Não dependem de movements reais — cada teste semeia seus próprios `movements` quando necessário.

---

## Dependências de módulos

- **`movements`** — fonte de verdade do lookup de NF (obrigatório)
- **`stores`** — scoping e referência de loja (obrigatório)
- **`employees`** — snapshot opcional do consultor via CPF
- **`payment_types`** + **`banks`** — catálogos opcionais para forma de pagamento + PIX
- **`helpdesk`** — opcional (hook fail-safe)
- **`order_payments`** — **não** há FK (design decision: estornos são independentes de OPs na v1 e na v2)

Configurado em `config/modules.php`:
```php
'reversals' => [
    'name' => 'Estornos',
    'routes' => ['reversals.*'],
    'icon' => 'ArrowUturnLeftIcon',
    'dependencies' => ['movements', 'stores'],
],
```

Habilitado nos planos **Professional** e **Enterprise**.

---

## Backlog pós-MVP

Não bloqueante. Features conhecidas que ficaram fora do MVP mas têm infraestrutura pronta.

| # | Feature | Esforço | Valor | Nota |
|---|---------|---------|-------|------|
| 1 | UI de import XLSX/CSV | Pequeno | Médio | Rotas `reversals.import.*` funcionam; falta `Pages/Reversals/Import.jsx` seguindo padrão do `PurchaseOrders/Import.jsx` |
| 2 | Push real ao CIGAM | Médio | Alto | `pushToCigam()` é stub; depende de credenciais de escrita no ERP |
| 3 | Classificação AI de motivo | Médio-grande | Médio | Sugerir motivo baseado em `notes` via Groq (similar ao classifier do Helpdesk) |
| 4 | Relatório gerencial mensal | Médio | Médio | PDF consolidado por loja/motivo/status enviado via email |
| 5 | Integração com adquirentes (Cielo/Stone) | Grande | Alto | Push direto via API para processamento automático |

---

## Bug fix colateral

Durante a Fase 3, detectamos que o `DataTable` em `Pages/Vacancies/Index.jsx` recebia `data={vacancies.data}` (só o array) mas o componente espera o objeto paginate completo (`data.data`, `data.links`, etc.). Corrigido em commit separado.

---

## Referências

- **Código**: `app/{Enums,Events,Http,Listeners,Models,Notifications,Services}/**Reversal*`, `resources/js/Pages/Reversals/`, `database/migrations/tenant/2026_04_19_3*`, `tests/Feature/Reversal*`
- **V1 origem**: `C:\wamp64\www\mercury\app\adms\Controllers\*Reversal*.php` + `AdmsModelsReversal*.php`
- **Memory interna**: `memory/reversals_module.md`
