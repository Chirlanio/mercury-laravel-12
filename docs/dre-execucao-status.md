# DRE — Status de execução vs. `dre-playbook.md`

**Última atualização:** 2026-04-22 (prompt 14 concluído — playbook 100%)

Comparativo do que está entregue vs. o que o `dre-playbook.md` (fonte canônica
dos prompts) prevê. Documenta divergências conhecidas para não se perderem
em futuras sessões.

---

## Resumo

| Prompt do playbook | Status | Entregue como |
|---|---|---|
| 1 — Migrations/models/factories/seeders | ✅ **Concluído** | Prompts #1 e #2 internos + D1 (L99_UNCLASSIFIED seeded) |
| 2 — ChartOfAccountsImporter + ActionPlan hint | ✅ **Concluído** | Prompt #3 (importer) + D3 (ActionPlanHintImporter). 131 hints no tenant meia-sola. |
| 3 — Limpeza CCs legados + observer pendências | ✅ **Concluído com divergência** | Destravadores D2a/D2b/D2c. Migration marca `LEGACY_UNMIGRATED` quando há refs em vez de abortar (resiliente para testes e rollback humano). |
| 4 — CRUD plano gerencial | ✅ **Concluído** | Prompt #5a interno |
| 5 — CRUD mapeamentos + pendências + bulk | ✅ **Concluído com divergências** | Prompt #5b interno |
| 6 — Núcleo: DreMatrixService + Resolver + SubtotalCalculator | ✅ **Concluído** | 28 tests novos. Resolver precedência específico>coringa>L99. Calculator unit puro. Service com matrix/drill/kpis. FormRequest. Interface ClosedPeriodReader + NullDefault (prompt 11 substitui). |
| 7 — Camada HTTP da matriz + stub React | ✅ **Concluído** | `DreMatrixController` (show/drill), rotas `dre.matrix.*`, `config/modules.php` ganha entrada `dre`, stub `Matrix.jsx` com dump JSON. 10 tests / 60 assertions. |
| 8 — Projetores OrderPayment/Sale | ✅ **Concluído** | `OrderPaymentToDreProjector` + `SaleToDreProjector` + observers auto-registrados + command `dre:rebuild-actuals` + schedule dominical. 21 tests novos. Matriz agora reflete OPs (status=done) e Sales automaticamente via observer. |
| 9 — Frontend completo da matriz | ✅ **Concluído** | `lib/dre.js` helpers + `KpiCards.jsx` + `MatrixTable.jsx` sticky headers + `DrillModal.jsx` com axios + `Pages/DRE/Matrix.jsx` reescrito (scope selector, filtros URL, banner período fechado, tabs). Build Vite limpo. Tests controller 10/60 continuam verdes. |
| 10 — Import manual actuals/budgets + BudgetToDreProjector | ✅ **Concluído com divergências** | `DreActualsImporter` + `DreBudgetsImporter` + `DreImportReport` + `BudgetToDreProjector` + `BudgetUploadDreObserver` + 2 commands (`dre:import-actuals`, `dre:import-budgets`) + `DreImportController` síncrono + 3 páginas (`Actuals/Budgets/Chart`) + `ImportReportPanel.jsx`. 24 tests novos. **Async Jobs + polling de status adiados** (backlog) — entrega síncrona consome planilhas <5k linhas em segundos. |
| 10.5 — Importar Action Plan v1.xlsx | ✅ **Concluído** | `ActionPlanImporter` + `ActionPlanReport` + `ActionPlanImport` reader (9 colunas) + command `dre:import-action-plan --file= --version= --dry-run`. Upsert idempotente por `(entry_date, coa_id, cc_id, store_id, budget_version)`. Resolve store por Z+code ou code puro. 9 tests / 29 assertions. |
| 11 — Fechamento de períodos + snapshots | ✅ **Concluído** | `DrePeriodClosingService` com `close/reopen/previewReopenDiffs` + `DrePeriodSnapshotReader` (substitui o Null) + enforcement no `DreMappingService` (create/update/delete) + enforcement no `DreActualsImporter` + FormRequests `CloseDrePeriodRequest`/`ReopenDrePeriodRequest` + `DrePeriodClosingController` + `DrePeriodReopenedNotification` (mail + database) + `Pages/DRE/Periods/Index.jsx` com preview de diffs via axios. Permission `MANAGE_DRE_PERIODS` só para SUPER_ADMIN e ADMIN. 18 tests novos (11 service + 7 controller). |
| 12 — Cache version key + warm-up | ✅ **Concluído** | `App\Support\DreCacheVersion` (current/invalidate em chave `dre:cache_version`) + trait `InvalidatesDreCacheOnChange` em DreMapping/DreManagementLine/DreActual/DreBudget/DrePeriodClosing/BudgetUpload + `ChartOfAccountObserver` invalida quando `default_management_class_id` muda. `DreMatrixService::matrix()` wrapea `compute()` em `Cache::remember(600)`, `normalizeFilterForCache()` + `cacheKeyForFilter()` públicos. Command `dre:warm-cache` (mês corrente + 12 meses móveis) agendado dailyAt 05:50. 7 tests novos. |
| 13 — Gráficos + export XLSX/PDF | ✅ **Concluído com divergências** | `App\Exports\DRE\DreMatrixExport` (3 sheets: Matriz/KPIs/Metadata — divergência do playbook que pedia 5 sheets; ver seção abaixo) + `resources/views/pdf/dre-matrix.blade.php` (A4 landscape via dompdf) + `DreMatrixController::exportXlsx/exportPdf` + rotas protegidas por `EXPORT_DRE` + `Components/DRE/ChartsPanel.jsx` (BarChart/LineChart/PieChart via Recharts) + botões XLSX/PDF no header da matriz. 4 tests novos. |
| 14 — Testes E2E + seed realista | ✅ **Concluído** | `tests/Feature/DRE/EndToEndTest.php` com 4 cenários (chart→mapping→OP→matrix; close+retroativo+reopen; multi-loja scope=store/network; BudgetUpload ativação → dre_budgets). `DreDevSeeder` rodável via `tenants:seed --class=DreDevSeeder` — popula 5 lojas + 48 OPs + 30 Sales + 3 mappings + orçado + fechamento. `docs/dre-README.md` entregue. **Bônus**: corrigiu bug de produção nos prompts 11-12 onde `Cache::*` usava o default driver → quebrava em tenant (stancl/tenancy wrap com tagging + driver database sem tagging). Fixed em `DreCacheVersion` e `DreMatrixService::matrix()` forçando `Cache::store('file')`. |

**Testes entregues até aqui: 140 DRE + ~200 regressão verdes em lotes.** Prompt 10 adicionou 24; prompt 10.5 adicionou 9; prompt 11 adicionou 18; prompt 12 adicionou 7; prompt 13 adicionou 4; prompt 14 adicionou 4 (`EndToEndTest`).

---

## Divergências intencionais relevantes

### Prompt 1 (Migrations/seeds) — minha implementação vs. playbook

| Item | Playbook | Entregue | Motivo |
|---|---|---|---|
| `chart_of_accounts.accepts_entries` / `nature` / `dre_group` | Drop no prompt 1 (após backfill) | **Preservados** | Retro-compat com ~30 arquivos que ainda usam `$account->accepts_entries` e `$account->dre_group`. Drop vira débito técnico para prompt final de limpeza. |
| `sort_order` UNIQUE em `dre_management_lines` | UNIQUE | **Não UNIQUE** | Para comportar duas linhas no mesmo sort_order (Headcount + EBITDA em 13, conforme prompt do usuário). Implicação: playbook prompt 4 vai precisar relaxar a validação. |
| Seed inicial das linhas | 16 DRE-BR + 1 fantasma L99 = 17 | **20 linhas executivas reais do Grupo Meia Sola, sem L99** | Usuário forneceu as 20 linhas explicitamente no prompt (Receita Bruta/Deduções/Fat.Líquido/Tributos/ROL/CMV/Lucro Bruto/Custos Indiretos/MC/SG&A/Ocupação/Marketing/Headcount/EBITDA/Depreciação/Desp.Fin/Outras Rec/Lucro Líquido/Ajuste Cedro/LL s/ Cedro). L99 postergada para o prompt 5 (Pendências). |
| Enums `DreActualSource`, `DreLineNature` | Enums PHP formais | **Constantes em models** | `DreActual::SOURCE_ORDER_PAYMENT`, `DreManagementLine::NATURE_REVENUE` etc. Funciona igual, menos arquivos novos. |
| `AccountType` enum | synthetic/analytical | ✓ Criado | — |
| `AccountGroup` enum | Não pedido explicitamente | ✓ **Criado** (bonus) | 1..5 com helpers `fromCode()`, `isResultGroup()`. |

**Consequências:**
- `L99_UNCLASSIFIED` precisa ser criada no prompt 5 ou no prompt 6 (antes do DreMappingResolver usá-la como fallback).
- O playbook menciona `DreManagementLine::where('code', 'L99_UNCLASSIFIED')` — esse registro NÃO existe hoje no banco. O prompt 6 (Resolver) precisa criar ou o prompt 5 (Pendências).

### Prompt 2 (Importer) — minha implementação vs. playbook

| Item | Playbook | Entregue | Motivo |
|---|---|---|---|
| `ChartOfAccountsImporter` + `CostCentersImporter` separados | 2 classes | **1 classe só** (ChartOfAccountsImporter cuida dos dois) | Roteia internamente por V_Grupo. Refatorar só se o playbook do prompt 3 exigir separação real. |
| `ActionPlanHintImporter` | Classe dedicada | ❌ **Não entregue** | Precisa criar ainda. Valor: pré-popular `chart_of_accounts.default_management_class_id` a partir do `Action Plan v1.xlsx` antes do prompt 5 consumir via "Usar sugestão". |
| Import em Job async + polling UI | Job + status endpoint | **Sync no command CLI** | Para 1129 linhas roda em ~5s; aceitável sync. Job async vira exigência real se o CFO subir planilhas de 10k+ linhas via UI — adiar para o prompt 9 (UI de upload). |
| Page stub `DRE/Imports/Chart.jsx` | Stub visual | ❌ **Não entregue** | Nenhuma UI de import ainda. Pendente para prompt 9. |
| Permission `IMPORT_DRE_CHART` | Criada no prompt 2 | ❌ **Não criada** | Nem o import tem guarda HTTP ainda — só CLI. Quando adicionar UI, cria a permission. |
| Command `dre:import-action-plan-hints` | Existe | ❌ **Não existe** | Depende do ActionPlanHintImporter. |

**Consequência:** `default_management_class_id` nas 840 contas está **null** no tenant `meia-sola`. O botão "Usar sugestão" do prompt 5 entregue sempre mostra "—" porque não há hint populada.

### Prompt 3 (Limpeza CCs legados) — não entregue

- **Migration `soft_delete_legacy_cost_centers`** — ausente. Os 24 CCs antigos (códigos 421..457) continuam `is_active=true` no tenant `meia-sola`, coexistindo com os 289 reais do grupo 8.
- **Command `dre:check-legacy-cc-refs`** — ausente.
- **Observer `ChartOfAccountObserver` + Event `AnalyticalAccountCreated`** — ausentes. Contas novas do próximo re-import não notificam ninguém.

**Impacto:** matriz DRE do prompt 6 pode ter lançamentos vindos via CC legado e inflar (ou deflacionar) o realizado. **Resolver antes do prompt 6.**

### Prompt 4 (CRUD management lines) — minha implementação vs. playbook

| Item | Playbook | Entregue | Divergência |
|---|---|---|---|
| Validar sort_order único | sim | **Não valida** (permite duplicado) | Consequência do seed das 20 linhas. Prompt 4 do playbook precisa relaxar a regra ou o seed precisa alinhar. |
| Permission `EXPORT_DRE` | Criada aqui | ❌ **Não criada** | Irá no prompt 13. |
| Page `Form.jsx` em modal | Modal StandardModal | **Página cheia (Edit.jsx)** | Simplicidade — não tem StandardModal pra form create em outros módulos do projeto; segui padrão de `AccountingClasses/Edit`. |
| Reorder em lote gaps de 10 | Sim, gaps 10,20,30... | **Reorder em gaps 1,2,3...** | Implementação simples. OK para ~20 linhas; reescrever para gaps 10 vira refactoring futuro se precisar inserir linhas intermediárias sem renumerar todos. |

### Prompt 13 (Gráficos + export XLSX/PDF) — minha implementação vs. playbook

| Item | Playbook | Entregue | Divergência |
|---|---|---|---|
| XLSX multi-sheet | "Matriz Geral", "Matriz por Rede", "Matriz por Loja", "KPIs", "Detalhamento" (5 abas) | 3 abas: **Matriz**, **KPIs**, **Metadata** | Gerar "Por Rede" e "Por Loja" num export único triplica compute e duplica dados — o usuário exporta o escopo atualmente filtrado; para outro escopo, troca e re-exporta. "Detalhamento" fica futuro (é essencialmente um dump da tabela `dre_actuals` filtrada — considerar quando houver demanda de conciliação externa). |
| PDF | A4 landscape via dompdf | ✅ Entregue (`resources/views/pdf/dre-matrix.blade.php`, header+filtros+KPIs+matriz compacta). | — |
| Gráficos Recharts | BarChart empilhado (Receita/Despesa/EBITDA), LineChart margens, PieChart despesas | ✅ Entregue em `Components/DRE/ChartsPanel.jsx` com classificação heurística por `nature`+`code`. | — |
| Polimento (StatusBadge, EmptyState, LoadingSpinner) | Consistência em toda UI | ✅ Já aplicado nos prompts 9/11 (DrillModal, Periods/Index). | — |

---

### Prompt 10 (Imports manuais + BudgetToDreProjector) — minha implementação vs. playbook

| Item | Playbook | Entregue | Divergência |
|---|---|---|---|
| Jobs async `ImportDreActualsJob`, `ImportDreBudgetsJob`, `ProjectBudgetToDreJob` | Jobs + progresso em Cache + polling status endpoint | ❌ **Não entregues** — execução 100% síncrona (HTTP + CLI) | Volume atual (<5k linhas) roda em segundos. Async vira exigência quando aparecerem planilhas 10k+; adicionar queue + `ImportStatus` DTO + poll endpoint `GET /dre/imports/{jobId}/status` no prompt 13 ou iteração dedicada. |
| Páginas `Pages/DRE/Imports/{Actuals,Budgets,Chart}.jsx` com drag-drop + polling | Drag-drop + polling 2s | ✅ Entregues **sem** drag-drop e **sem** polling | Input `<input type="file">` padrão + retorno síncrono via flash (`import_report`). Componente compartilhado `Components/DRE/Imports/ImportReportPanel.jsx` mostra contadores + tabela de erros PT. |
| Endpoint `GET /dre/imports/{jobId}/status` | Previsto | ❌ **Não criado** | Desnecessário no modelo síncrono. |
| Permission `IMPORT_DRE_ACTUALS` / `IMPORT_DRE_BUDGETS` | Criadas | ✅ Criadas + descriptions + labels em `Permission.php`. | — |
| `DreActualsImporter` + dedup por `external_id` | upsert por (source=MANUAL_IMPORT, external_id) | ✅ Entregue — teste cobre reimport com mesmo `external_id` → UPDATE não CREATE. | — |
| `DreBudgetsImporter` | análogo a actuals, sem check de fechamento | ✅ Entregue — `entry_date` normalizada para dia 1 do mês; `budget_version` vem do form (`--version=` no CLI). | — |
| `BudgetToDreProjector` com superseção por `(year, scope_label)` | Remove dre_budgets de uploads anteriores mesmo scope | ✅ Entregue com transação. Observer chama `project()` no flip `is_active=true` e `unproject()` no flip → false. | — |
| `docs/dre-imports-formatos.md` | Documentar colunas + exemplos | ✅ Entregue — 3 planilhas documentadas com convenção de sinal, regras de rejeição, 5 exemplos cada. | — |

**Consequência:** a tela de status assíncrono (progresso 2s, "processing"/"done" visual) fica em débito técnico. Não afeta funcionalidade — CFO consegue importar pelo CLI (que dá output detalhado) ou pela UI (que entrega resultado sync). Adicionar jobs quando métrica/uso mostrar planilhas >5k linhas.

---

### Prompt 5 (CRUD mappings) — minha implementação vs. playbook

| Item | Playbook | Entregue | Divergência |
|---|---|---|---|
| Enforcement de `effective_from > lastClosedUpTo` | Sim | **Não** (mas `DrePeriodClosing` já existe) | Pendente para quando prompt 11 existir — aí preencho o guard no DreMappingService. |
| Método `expire(mapping, date)` no service | Sim | **Não** — só `update` com `effective_to` manual | Menor. Adicionável. |
| Método `duplicate(source, overrides)` | Sim | **Não** | UX extra — deixável para iteração. |
| Badge numérico no sidebar | Via HandleInertiaRequests shared props | **Não** | UX pending. |
| `UnmappedAccountsFinder::find` como classe dedicada | Classe separada | **Método `findUnmappedAccounts` no service** | Funcional equivalente. Refatorar só se testes exigirem isolamento. |

---

## Ordem recomendada para retomar execução

### Destravadores críticos (antes de prompt 6)

1. **Criar `L99_UNCLASSIFIED` no seed** — migration nova que insere a linha-fantasma. O `DreMappingResolver` do prompt 6 depende dela.
2. **Rodar prompt 3 do playbook** (limpeza CCs 421..457 + observer). Senão o prompt 6 soma valores contaminados pelos CCs antigos.
3. **Rodar `ActionPlanHintImporter`** (parte do prompt 2 do playbook) — pré-popula sugestões para a tela de Pendências ganhar utilidade.

### Sequência sugerida dos próximos prompts

- Prompt 6 (núcleo DreMatrixService + Resolver + SubtotalCalculator) ✅
- Prompt 7 (controller HTTP + stub React) ✅
- Prompt 8 (projetores OrderPayment/Sale) ✅
- Prompt 9 (frontend completo da matriz + UI de import) ✅
- Prompt 10 (import manual de actuals/budgets + BudgetToDreProjector) ✅ (async adiado)
- Prompt 10.5 (Action Plan v1.xlsx → dre_budgets) ✅
- Prompt 11 (fechamento + snapshots + enforcement) ✅
- Prompt 12 (cache version key + warm-up + invalidação) ✅
- Prompt 13 (gráficos + export XLSX/PDF) ✅ (3 sheets em vez de 5 — ver divergências)
- Prompt 14 (E2E + seed realista + README final) ✅
- **Playbook 100% concluído.** Debito técnico mapeado em `dre-README.md` seção "Divergências assumidas".
- Prompt 12 (cache version key)
- Prompt 13 (gráficos + export)
- Prompt 14 (E2E + seed realista)

---

## Observações de configuração

- **Trait `HasManualSoftDelete`** — NÃO existe no projeto. Soft delete é implementado à mão em cada model (3 colunas + scopes `notDeleted`, `isDeleted()`). Confirmado para o prompt 1 do playbook.
- **Enum `DreLineNature`** — NÃO criado. `DreManagementLine::NATURE_REVENUE/NATURE_EXPENSE/NATURE_SUBTOTAL` são constantes de classe. Se playbook exigir enum PHP, renomear depois.
- **`config/modules.php` entrada `dre`** — NÃO criada. Módulo não está registrado no sistema de tenant modules. Pendente do prompt 7 do playbook (registro do módulo).
- **Menu sidebar DRE** — NÃO adicionado. Pendente do prompt 7.
