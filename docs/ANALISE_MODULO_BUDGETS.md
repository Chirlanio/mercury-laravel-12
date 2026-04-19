# Análise do Módulo — Orçamentos (Budgets) · Fases 1 + 2 + 3

**Data de conclusão da Fase 3:** 20/04/2026
**Commits:** 13 (Fase 1: 5 + Fase 2: 4 + Fase 3: 4)
**Testes:** 53 feature tests / 244 assertions
**Regressão fundação + Budgets:** 103 tests / 429 assertions

---

## Escopo entregue (Fase 1 MVP)

Estrutura completa de orçamento com versionamento automático e armazenamento de arquivo original — **funcionando via API**. UI de upload com parse Excel + preview + fuzzy matching **virá na Fase 2**.

### O que funciona agora

| Feature | Disponível |
|---|---|
| Criar orçamento via API (POST multipart: file + items[] com FKs resolvidas) | ✅ |
| **Upload via UI com parse xlsx + preview + fuzzy + reconciliação** | ✅ Fase 2 |
| **Template xlsx para download com exemplos** | ✅ Fase 2 |
| Versionamento automático v1 (1.0 / 1.01 / 2.0 / reset por ano) | ✅ |
| Uma versão ativa por `(year, scope_label)` — upload novo desativa anterior | ✅ |
| Armazenamento do xlsx original em `storage/tenantX/budgets/{year}/` | ✅ |
| Listagem filtrada (year, scope, upload_type, include_inactive) | ✅ |
| Detalhe com 12 meses por linha + histórico de status | ✅ |
| Download do xlsx original | ✅ |
| Soft delete de versões **não-ativas** (ativa nunca é deletada) | ✅ |
| Statistics (ativos, total previsto, escopos, anos) | ✅ |
| Audit trail completo (created_by, updated_by, deleted_by + histories) | ✅ |

### O que ainda NÃO está disponível (próximas fases)

- **Fase 4**: Command diário de alerta ≥ 80% consumo + notifications
- **Fase 5**: Export xlsx consolidado (previsto + realizado) + PDF resumo
- **Follow-up pós-Fase 3**: Integração do dropdown "Vincular a linha de orçamento" no form de OrderPayment (backend já disponível via `GET /budgets/items-for-cost-center/{cc}?year=YYYY`)

---

## Arquitetura

### Schema (3 tabelas)

**`budget_uploads`** — header por `(year, scope_label)` com versão ativa única:

| Coluna | Propósito |
|---|---|
| `year`, `scope_label` | Chave lógica de agrupamento |
| `version_label`, `major_version`, `minor_version` | Versionamento paridade v1 |
| `upload_type` | `novo` \| `ajuste` |
| `original_filename`, `stored_path`, `file_size_bytes` | xlsx armazenado |
| `is_active` | Uma por (year, scope) |
| `total_year`, `items_count` | Cache para listagem |
| audit + soft delete manual | Padrão do projeto |

**`budget_items`** — linhas do orçamento com FKs obrigatórias:

| Coluna | Observação |
|---|---|
| `accounting_class_id` | FK NOT NULL (folha analítica) |
| `management_class_id` | FK NOT NULL (folha analítica) |
| `cost_center_id` | FK NOT NULL (decisão da Fase 0: toda linha tem CC) |
| `store_id` | FK nullable (linhas "gerais" podem não ter loja) |
| `supplier`, `justification`, `account_description`, `class_description` | Snapshots do xlsx original |
| `month_01_value` … `month_12_value` | 12 `decimal(15,2)` |
| `year_total` | `decimal(17,2)` calculado no service |

**`budget_status_histories`** — audit trail de transições `is_active`:

- `event` string: `created` / `activated` / `deactivated` / `deleted`
- `from_active`, `to_active` bool
- `note` texto (ex: "Substituída por v2.0")
- Registrado automaticamente pelo `BudgetService`

### Enums

- **`BudgetUploadType`** — `novo` | `ajuste`. Controla a regra de incremento de versão.

### Services (3)

| Arquivo | Propósito |
|---|---|
| `BudgetVersionService` | `resolveNextVersion(year, scope, type)` implementa as 4 regras v1: primeiro=1.0, ano-novo=reset 1.0, mesmo-ano+novo=major+1, mesmo-ano+ajuste=minor+1. Ignora versões soft-deleted. |
| `BudgetFileStorageService` | Armazena xlsx em `budgets/{year}/{timestamp}_{uniqid}.xlsx` no disk `local` do tenant. `downloadResponse()` preserva o nome original. |
| `BudgetService` | Orquestra: valida header, resolve versão, armazena arquivo ANTES do DB (arquivo órfão aceitável vs. linhas sem arquivo), desativa versão anterior, persiste items com `year_total` calculado, atualiza cache `items_count`/`total_year` do header, grava histórico. |

### Controller + routes (6 endpoints)

| Método | Rota | Permission |
|---|---|---|
| `GET` | `/budgets` | `VIEW_BUDGETS` |
| `GET` | `/budgets/statistics` | `VIEW_BUDGETS` |
| `GET` | `/budgets/{id}` | `VIEW_BUDGETS` |
| `GET` | `/budgets/{id}/download` | `DOWNLOAD_BUDGETS` |
| `POST` | `/budgets` | `UPLOAD_BUDGETS` |
| `PUT` | `/budgets/{id}` | `UPLOAD_BUDGETS` (só edita `notes`) |
| `DELETE` | `/budgets/{id}` | `DELETE_BUDGETS` |

### Frontend

- `Pages/Budgets/Index.jsx` com StatisticsGrid + filtros + DataTable + modal detail (5xl) + modal delete
- Alerta **amarelo explícito** no topo informando que upload completo com parse xlsx entra na Fase 2 — botão "Novo Upload" desabilitado com tooltip
- Modal detail renderiza tabela scrollável com **12 colunas mensais** + total destacado em índigo
- Timeline de history no modal com events Criado / Ativado / Desativado / Excluído

---

## Decisões arquiteturais não-óbvias

### 1. `scope_label` VARCHAR livre (não FK)

A v1 tinha `adms_areas` como tabela. V2 não tem. Duas alternativas avaliadas:
- **FK para ManagementClass raiz** — força modelagem via MC, adiciona rigidez
- **Nova tabela `budget_scopes`** — mais pesado, duplica conceito de MC
- **String livre** ✅ — máxima flexibilidade, compat com v1

Decisão: string. Cliente organiza como quiser ("Administrativo", "TI 2026", "Geral"). Agregação DRE futura não depende disso — usa `dre_group` do `AccountingClass` via `budget_items`.

### 2. FKs obrigatórias em `budget_items` (não nullable)

Decisão herdada da Fase 0: "toda linha de orçamento tem CC". FKs para `accounting_classes`, `management_classes`, `cost_centers` são NOT NULL. O parser da Fase 2 (fuzzy matching + reconciliação) é quem vai resolver FKs ausentes **antes** do insert. Store fica nullable (algumas linhas são genéricas, sem loja específica).

### 3. Fase 1 via JSON payload, não via parse xlsx

Decisão consciente para não duplicar escopo com Fase 2. Na Fase 1, quem quer criar um budget faz POST multipart com:
- `file` = xlsx (armazenado para auditoria, **não parseado**)
- `items[]` = array JSON com FKs já resolvidas

Isso permite testar o core (versionamento, storage, audit) sem depender do parser complexo. Fase 2 entrega a UI de upload que, internamente, gera o mesmo `items[]` via preview+reconciliação e chama `BudgetService::create()` sem mudança no service.

### 4. `year_total` calculado no service, não GENERATED

MySQL/Postgres suportam `decimal GENERATED AS (sum)`. SQLite (usado nos testes) não. Para manter compatibilidade de testes em memória, o total é calculado no PHP (`BudgetService::persistItems` + `BudgetItem::computeYearTotal`). Trade-off aceito: risco de inconsistência se alguém editar direto no DB — mas nada no projeto edita items fora do service.

### 5. Storage ANTES de DB na transaction

`BudgetService::create()` salva o arquivo **antes** de abrir a transaction de DB. Racional:
- Arquivo órfão (storage OK, DB rollback) = limpeza manual aceitável
- DB OK + arquivo ausente = inaceitável (header sem arquivo para download)

### 6. Versão ativa nunca é deletada diretamente

`BudgetService::delete()` bloqueia com erro explícito se `is_active=true`. Para substituir uma versão ativa, faça **novo upload** — o service desativa a anterior automaticamente. Evita janelas de "sem orçamento ativo" para o scope.

### 7. Cache `items_count` + `total_year` no header

Duas colunas redundantes atualizadas a cada insert de items pelo service. Trade-off:
- Prós: listagem mostra total sem JOIN/SUM
- Contras: qualquer edição de items precisa chamar `refreshTotals()`

Como items são imutáveis pós-criação (via API pública), essa manutenção fica centralizada no service — baixo risco de drift.

### 8. `updated_by_user_id` no header mas não nos items

Items não têm audit individual (seriam thousands de linhas por tenant). O tracking fica no header + nas transitions via `budget_status_histories`. Se alguma linha virar editável no futuro, adiciona audit específico.

### 9. Padrão Option A (frontend MVP mínimo) ao invés de Option B (UI manual de linhas)

Na Fase 1, o frontend só **consome** dados criados via API — não oferece formulário pra digitar 50 linhas manualmente. Decisão: mais honesto com o escopo, evita UX ruim que seria descartada na Fase 2 (quando entra upload xlsx). Alerta amarelo explícito na UI comunica a limitação.

---

## Permissões (7)

| Slug | Label | Default |
|---|---|---|
| `budgets.view` | Visualizar orçamentos | SUPER_ADMIN, ADMIN, SUPPORT |
| `budgets.upload` | Enviar planilhas | SUPER_ADMIN, ADMIN |
| `budgets.download` | Baixar xlsx original | SUPER_ADMIN, ADMIN, SUPPORT |
| `budgets.delete` | Excluir versões não-ativas | SUPER_ADMIN, ADMIN |
| `budgets.manage` | Gerenciar todos os escopos | SUPER_ADMIN, ADMIN |
| `budgets.export` | Exportar consolidado (Fase 5) | SUPER_ADMIN, ADMIN, SUPPORT |
| `budgets.view_consumption` | Dashboard de consumo (Fase 3) | SUPER_ADMIN, ADMIN, SUPPORT |

Habilitado nos planos **Professional** e **Enterprise**. Starter não tem (é módulo de valor agregado).

---

## Testes

**39 tests / 162 assertions / ~10s**

| Suite | Tests | Foco |
|---|---|---|
| `BudgetControllerTest` | 20 | CRUD API + versionamento completo + delete rules + statistics |
| `BudgetImportServiceTest` | 10 | Parser xlsx + fuzzy matching + headers alternativos + agrupamento por freq + agregação by_month |
| `BudgetImportEndpointTest` | 9 | Preview + import HTTP + mapping + validations + template download |

Regressão cruzada: **89 tests** na pilha completa (CC + AC + MC + Budgets).

---

## Dependências de módulos

**Obrigatórias** (declaradas em `config/modules.php`):
- `accounting_classes` — FK em cada item
- `management_classes` — FK em cada item
- `cost_centers` — FK em cada item

**Opcional**:
- `stores` — FK nullable em items "geográficos"

Middleware `tenant.module:budgets` bloqueia acesso quando os 3 pré-requisitos não estão habilitados no plano do tenant.

---

## Fase 2 — Upload completo com parser xlsx (CONCLUÍDA 2026-04-20)

Transforma o módulo em ferramenta real de uso pelo financeiro — antes da Fase 2 o upload era possível só via API/Postman.

### Entregas

**Backend (2 commits):**
- `BudgetImportService` (~500 linhas) com:
  - HEADER_MAP com ~50 aliases de cabeçalho (PT-BR abrev/full/numérico + EN bonus + separadores normalizados)
  - `parseMoney()` aceitando formato BR `1.234,56`, US `1,234.56`, `1234,56`, numérico direto
  - Fuzzy matching Levenshtein com threshold `min(3, 30% * len)` + top-3 candidatos
  - Agrupamento de ausentes por frequência (códigos repetidos não rodam fuzzy duplicado)
  - Limite de universe=500 por tipo (perf para cadastros grandes)
  - `preview()` retorna diagnóstico estruturado (valid/pending/rejected + unresolved_summary + totals)
  - `resolveItems(path, mapping)` produz items[] final para o BudgetService

- 2 endpoints novos:
  - `POST /budgets/preview` — análise sem persistir
  - `POST /budgets/import` — confirma com mapping + delega ao BudgetService

- 2 FormRequests: `PreviewBudgetRequest`, `ImportBudgetRequest` (validação de mapping IDs exists)

**Frontend (1 commit):**
- `BudgetUploadWizard.jsx` — modal 6xl com state machine 3-steps:
  - **Step 1 (upload)**: file input + card de headers aceitos + **botão "Baixar template xlsx"**
  - **Step 2 (reconcile)**: SummaryCards + seção por tipo de FK com dropdown de reconciliação (top-3 fuzzy, melhor pré-selecionado) + tabela de rejeitadas
  - **Step 3 (confirm)**: year/scope/type + totais previstos + aviso de regras de versionamento
- Contador em tempo real "X de Y linhas importáveis" atualiza conforme usuário mapeia códigos
- Remove alerta amarelo "MVP Fase 1" e desabilita do botão "Novo Upload"

**Template xlsx (1 commit):**
- `GET /budgets/template` retorna xlsx com 20 colunas pré-formatadas + 2 linhas de exemplo

### Decisões arquiteturais adicionais da Fase 2

#### 1. Template gerado on-the-fly (não estático)
Alternativa considerada: servir um arquivo estático em `public/templates/`. Escolhida: gerar via Maatwebsite on-the-fly. Vantagem: exemplo sempre tem AC/MC/CC reais do tenant (pode evoluir futuramente para popular com códigos ativos do cadastro).

#### 2. `Excel::raw()` + `tempnam()` nos tests
`Storage::fake('local')` estava interferindo com `Excel::store()` + `storage_path()`. Workaround: usar `Excel::raw()` que gera os bytes em memória e escrever via `file_put_contents()` em temp dir. Mais robusto para integração com `UploadedFile`.

#### 3. Fuzzy threshold: `min(3, 30% * len)`
Valor absoluto 3 pra códigos curtos (ex: "CC-1" nunca dá sugestão pra "CC-999" — distância 2), 30% pra códigos longos (ex: "ACCOUNTING-SALARIOS-TI" aceita até 6 chars de diferença). Balance conservador que evita falsos positivos.

#### 4. Sugestão top-1 pré-selecionada automaticamente
UX crítica: se usuário não interagir com um dropdown, a sugestão com menor distância Levenshtein fica selecionada. Opção "Ignorar" é explícita (value=""), forçando o usuário a pensar antes de descartar uma sugestão razoável.

#### 5. Mapping serializado como array aninhado no FormData
`mapping[accounting_class][CODE-X]=42` no multipart. Laravel parsa automaticamente como array PHP. Alternativa avaliada (JSON em campo único) daria menos erros de validação por campo mas complicaria o `mapping.accounting_class.*` do FormRequest.

### Fase 2 — Commits

| Hash | Descrição |
|---|---|
| `cbb4695` | BudgetImportService + 10 unit tests |
| `e0a087b` | Controller endpoints + FormRequests + 8 endpoint tests |
| `67fa688` | BudgetUploadWizard.jsx + integração no Index |
| `(este)` | Template xlsx + docs |

---

## Fase 3 — Consumo previsto × realizado (CONCLUÍDA 2026-04-20)

Conecta o Budgets ao módulo financeiro. OrderPayments passam a poder ser vinculadas a linhas específicas do orçamento via `budget_item_id`, e um dashboard agregado mostra previsto × realizado em 4 dimensões (item, CC, AC, mês).

### Entregas

**Backend (2 commits):**
- Migration `order_payments.budget_item_id` (FK nullable, onDelete=null, index)
- Relations: `OrderPayment::budgetItem()` + `BudgetItem::orderPayments()`
- `BudgetConsumptionService::getConsumption(BudgetUpload)` retorna:
  - `totals` (forecast/realized/available/utilization_pct)
  - `by_item` com semáforo status (ok/warning/exceeded)
  - `by_cost_center` + `by_accounting_class` agregados ordenados por % DESC
  - `by_month` 12 pontos (forecast = soma das colunas mensais, realized = OPs do ano agrupadas por mês)
- Endpoints:
  - `GET /budgets/{id}/dashboard` (Inertia page)
  - `GET /budgets/{id}/consumption` (JSON)
  - `GET /budgets/items-for-cost-center/{cc}?year=YYYY` (dropdown helper para futura integração com form de OP)

**Frontend (1 commit):**
- `Pages/Budgets/Dashboard.jsx` (~370 linhas)
- StatisticsGrid com 5 KPIs (previsto/realizado/disponível/utilização/linhas em alerta)
- Gráfico recharts BarChart previsto × realizado por mês (ResponsiveContainer, tooltip BR, tickFormatter em k)
- 3 tabs navegáveis: por CC, por AC, detalhe por linha
- UtilizationBar horizontal com cores por status (verde < 70%, amber 70–99%, vermelho ≥ 100%)
- Ícone ChartBarIcon em cada linha da listagem abre o dashboard

### Regras de agregação do service

- **Ignora OPs com `status='backlog'`** (ainda não comprometidas)
- **Ignora OPs soft-deleted** (deleted_at IS NOT NULL)
- **Semáforo**: ok `<70%`, warning `70-99.99%`, exceeded `≥100%`
- **`forecast=0` + `realized>0`** → `exceeded` (overrun implícito)
- **by_month** extrai mês no PHP (driver-agnostic — funciona SQLite/MySQL/Postgres). Trade-off: transporta rows, aceitável para volumes de 1 ano de OPs

### Performance

- 1 query agregada `GROUP BY budget_item_id` para realized por item (N itens → 1 query)
- Agregações by_cc / by_ac feitas em memória a partir do resultado acima (evita duplicar SQL)
- by_month: 1 query busca todas OPs do ano do budget, agrega no PHP (compromisso driver-agnostic)

### Decisões arquiteturais adicionais

#### 1. `budget_item_id` com onDelete=null (não cascade)
Razão: OP é registro financeiro legal. Deletar um item de orçamento não deve apagar OPs vinculadas — só perder o vínculo. Rastreabilidade financeira preservada independentemente.

#### 2. Ignora backlog mas conta doing/waiting/done
OPs em `backlog` ainda estão em planejamento (podem ser descartadas). Uma vez que saem de backlog, são comprometimentos de orçamento reais.

#### 3. Dashboard page separada (não modal)
Volume de dados (5 cards + gráfico + 3 tabelas tab'áveis) justifica navegação. Breadcrumb "voltar para orçamentos" mantém contexto.

#### 4. Semáforo em 3 níveis (não 2)
Alternativa considerada: só verde/vermelho em 100%. Escolhido 3 níveis (amber em 70%) para dar sinal antecipado de risco — equipe financeira pode agir antes de estourar.

### Fase 3 — Commits

| Hash | Descrição |
|---|---|
| `01baf36` | Migration + relations `OrderPayment ↔ BudgetItem` |
| `950ca4a` | BudgetConsumptionService + endpoints + 11 tests |
| `44a0a7c` | Frontend Dashboard.jsx + recharts + Index link |
| `(este)` | Endpoint items-for-cost-center + docs |

---

## Próximos passos

### Fase 4 — Alertas automatizados
- Migration `order_payments.budget_item_id` (FK nullable)
- Controller do dashboard com queries agregadas por CC/AC/MC/mês
- Comparativo previsto (do budget ativo do mesmo year+scope) vs realizado (sum de order_payments vinculadas)
- Alertas visuais em amarelo (≥ 80%) e vermelho (≥ 100%)

### Fase 4 — Alertas automatizados
- Command `budgets:alert` (daily 09:00) usa `BudgetConsumptionService` para scanear budgets ativos
- Notification database + mail quando CC/item atinge threshold (70% warning, 100% exceeded)
- Thresholds configuráveis por tenant (settings)

### Fase 5 — Export consolidado
- Export XLSX com previsto + realizado lado a lado por item (12 colunas meses × 2 valores cada)
- PDF resumo por scope/year
- Documentação final do módulo

---

## Referências

- **Código**: `app/{Enums,Models,Services,Http/Controllers,Http/Requests}/Budget*`, `resources/js/Pages/Budgets/`
- **V1 origem**: `C:\wamp64\www\mercury\app\adms\Controllers\Budgets.php` + `adms_budgets_uploads` + `adms_budgets_items`
- **Memory interna**: `memory/budgets_module.md`
- **Fundação**: `docs/ANALISE_FUNDACAO_BUDGETS.md` (Fases 0.1 + 0.2 + 0.3)
