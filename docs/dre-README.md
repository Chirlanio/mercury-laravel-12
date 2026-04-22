# DRE — Demonstração do Resultado do Exercício

Módulo de relatório executivo do Mercury Laravel. Consolida lançamentos
realizados (OrderPayment, Sale, import manual) contra orçado (BudgetUpload,
import manual, Action Plan) em uma matriz mensal por linha gerencial, com
subtotais, KPIs, drill-through, fechamento imutável e export XLSX/PDF.

Playbook completo em [`dre-playbook.md`](dre-playbook.md). Arquitetura e
decisões em [`dre-arquitetura.md`](dre-arquitetura.md). Status real de
execução vs. playbook em [`dre-execucao-status.md`](dre-execucao-status.md).

---

## Cenário de uso

1. **CFO importa plano de contas** — `php artisan dre:import-chart <file>`
   ou tela `/dre/imports/chart`. Popula `chart_of_accounts` + `cost_centers`.
2. **Equipe financeira mapeia contas para linhas executivas** — tela
   `/dre/mappings`. Cria `dre_mappings` com precedência específico (conta+CC)
   > coringa (conta+NULL) > fallback L99_UNCLASSIFIED.
3. **Sistema projeta automaticamente** via observers:
   - `OrderPaymentDreObserver` — status=done → `dre_actuals`.
   - `SaleDreObserver` — criação de Sale → `dre_actuals` com conta da loja.
4. **Orçamento:** equipe gerencial cria `BudgetUpload` via módulo Budgets;
   ativação projeta em `dre_budgets`. Ou import manual via
   `/dre/imports/budgets` / `dre:import-budgets --version=...`.
5. **Matriz DRE** exibe em `/dre/matrix` — realizado × orçado × ano
   anterior, com drill-through por célula e gráficos.
6. **Fechamento mensal** em `/dre/periods` — gera snapshots imutáveis por
   Geral/Rede/Loja × linha × mês. Enforcement bloqueia edição de mappings
   e import de actuals dentro do período fechado.

---

## Entry points

### Rotas HTTP (sob `tenant.module:dre`)

| Rota | Permission | Descrição |
|---|---|---|
| `GET /dre/matrix` | `dre.view` | Matriz principal (Inertia). |
| `GET /dre/matrix/drill/{line}` | `dre.view` | JSON com contas contribuintes da linha. |
| `GET /dre/matrix/export/xlsx` | `dre.export` | Export XLSX multi-sheet (Matriz/KPIs/Metadata). |
| `GET /dre/matrix/export/pdf` | `dre.export` | Export PDF A4 landscape. |
| `GET /dre/management-lines` | `dre.view` | CRUD do plano gerencial. |
| `GET /dre/mappings` | `dre.view` | CRUD de de-para conta→linha. |
| `GET /dre/mappings/unmapped` | `dre.view` | Fila de contas pendentes. |
| `GET /dre/periods` | `dre.manage_periods` | Fechamentos + reabertura com diff. |
| `GET /dre/imports/chart` | `dre.manage_mappings` | Upload do plano. |
| `GET /dre/imports/actuals` | `dre.import_actuals` | Import manual de realizado. |
| `GET /dre/imports/budgets` | `dre.import_budgets` | Import manual de orçado. |

### Commands CLI

| Command | Descrição |
|---|---|
| `dre:import-chart <file> [--source= --dry-run]` | Importa plano de contas. |
| `dre:import-actuals <file> [--dry-run]` | Importa realizado (source=MANUAL_IMPORT). |
| `dre:import-budgets <file> --version=<label> [--dry-run]` | Importa orçado. |
| `dre:import-action-plan [--file= --version= --dry-run]` | Importa Action Plan v1.xlsx (upsert). |
| `dre:import-action-plan-hints <file>` | Pré-popula `default_management_class_id` nas contas. |
| `dre:rebuild-actuals [--source=all\|ORDER_PAYMENT\|SALE] [--force]` | Reconciliação defensiva dos projetores. |
| `dre:warm-cache` | Aquece cache da matriz (mês + 12 meses móveis). Scheduled 05:50. |
| `dre:check-legacy-cc-refs` | Lista CCs legados ainda referenciados. |

### Scheduled tasks (`routes/console.php`)

| Frequência | Command | Propósito |
|---|---|---|
| Domingo 03:00 | `dre:rebuild-actuals --source=ORDER_PAYMENT --force` | Reconciliação semanal. |
| Diário 05:50 | `dre:warm-cache` | Pré-aquece cache antes do horário comercial. |

### Services-chave (`app/Services/DRE/`)

- `DreMatrixService` — núcleo: `matrix()` (cacheada 600s) + `compute()` + `drill()` + `kpis()`.
- `DreMappingResolver` — precedência específico > coringa > L99.
- `DreSubtotalCalculator` — unit-pure; aplica `accumulate_until_sort_order`.
- `DrePeriodClosingService` — close/reopen com snapshot + diff.
- `DrePeriodSnapshotReader` — implementa `ClosedPeriodReader` (bind default).
- `OrderPaymentToDreProjector` / `SaleToDreProjector` — auto via observers.
- `BudgetToDreProjector` — ponte `BudgetUpload.is_active=true` → `dre_budgets`.
- `ChartOfAccountsImporter` — leitura do ERP CIGAM.
- `DreActualsImporter` / `DreBudgetsImporter` / `ActionPlanImporter` — imports XLSX manuais.
- `DreMappingService` / `DreManagementLineService` — CRUD com validações.

### Cache (playbook prompt #12)

- `App\Support\DreCacheVersion` — version key no store `file` (evita tagging
  do stancl/tenancy sobre o driver `database`).
- Chave: `dre:matrix:v{version}:{md5(normalizedFilter)}`, TTL 600s.
- Invalidação: trait `InvalidatesDreCacheOnChange` em `DreMapping`,
  `DreManagementLine`, `DreActual`, `DreBudget`, `DrePeriodClosing`,
  `BudgetUpload` + `ChartOfAccountObserver` (quando
  `default_management_class_id` muda).
- Warm-up: `dre:warm-cache` dailyAt 05:50.

### Convenção de sinal (arquitetura §2.5.1)

XLSX sempre com **valor positivo**. Service converte por `account_group`:

- Grupo 3 (Receitas) → `+abs(valor)`
- Grupos 4 e 5 (Custos/Despesas/Resultado) → `-abs(valor)`
- Grupos 1 e 2 (Ativo/Passivo) → **rejeita** (não pertence à DRE)

---

## Permissions

| Slug | Função |
|---|---|
| `dre.view` | Ver matriz, mappings, pendências, exports. |
| `dre.manage_structure` | Criar/editar/remover linhas do plano gerencial. |
| `dre.manage_mappings` | CRUD de de-para e bulk assign. |
| `dre.view_pending_accounts` | Fila de contas sem mapping vigente. |
| `dre.import_actuals` | Upload XLSX de realizado manual. |
| `dre.import_budgets` | Upload XLSX de orçado manual. |
| `dre.manage_periods` | Fechar/reabrir períodos (MANAGE_DRE_PERIODS). |
| `dre.export` | Download XLSX/PDF da matriz. |

Atribuição default (enum fallback): todas para SUPER_ADMIN e ADMIN.

---

## Seed realista (para ambiente local)

```bash
php artisan tenants:seed --class=DreDevSeeder --tenants=meia-sola
```

Cria 5 lojas em 2 redes + 50 OrderPayments (últimos 6 meses) + 30 Sales
+ 3 mappings (específico/coringa/expirado) + 1 fechamento do mês anterior
+ orçamento anual em `dre_budgets`. Abra `/dre/matrix` para conferir.

Não roda automaticamente — chame explicitamente.

---

## Testes

- **Suíte DRE** (214 tests / ~680 assertions após prompt 14):
  - `tests/Feature/DRE/` — Controller + Service + Cache + Periods + E2E.
  - `tests/Feature/Imports/DRE/` — importers.
  - `tests/Feature/Projectors/` — OrderPayment + Sale + BudgetToDre.
- Rodar em batches por memória do Windows:
  ```bash
  C:/Users/MSDEV/php84/php.exe -d memory_limit=2G artisan test --filter=DreMatrix
  ```

---

## Documentação relacionada

- [`dre-arquitetura.md`](dre-arquitetura.md) — decisões arquiteturais (25/25 fechadas).
- [`dre-playbook.md`](dre-playbook.md) — execução em 14 prompts (fonte canônica).
- [`dre-execucao-status.md`](dre-execucao-status.md) — status real vs. playbook + divergências.
- [`dre-descoberta.md`](dre-descoberta.md) — mapeamento inicial do projeto.
- [`dre-plano-contas-formato.md`](dre-plano-contas-formato.md) — formato do XLSX do ERP.
- [`dre-imports-formatos.md`](dre-imports-formatos.md) — formatos de actuals/budgets manuais.

---

## Divergências assumidas (em produção)

1. **Imports síncronos** — `DreActualsImporter`/`DreBudgetsImporter` rodam
   no próprio request (até 5k linhas). Jobs async + polling de status
   adiados ao backlog. _Prompt 10 do playbook._
2. **Export XLSX em 3 sheets** (Matriz + KPIs + Metadata) — playbook pedia
   5 (multi-scope). Multi-scope adiado; usuário troca scope e re-exporta.
   _Prompt 13._
3. **`default_management_class_id` null no tenant `meia-sola`** — o
   `ActionPlanHintImporter` não foi rodado contra dados reais (apenas testes).
   Rodar antes da UI de "Usar sugestão" ser útil em produção.
4. **`budget_item_id` NOT NULL** (C3c do backlog de budgets) — ainda
   pendente; afeta integridade de OrderPayment × BudgetItem mas não afeta
   DRE diretamente.

---

## Caminho feliz em 30 segundos

```bash
# 1. Importa plano + CCs (839 contas + 289 CCs no seed real).
php artisan dre:import-chart "storage/app/imports/Plano de Contas.xlsx"

# 2. Opcional — popula sugestões de mapping do Action Plan.
php artisan dre:import-action-plan-hints "storage/app/imports/Action Plan v1.xlsx"

# 3. Opcional — carrega orçado 2026.
php artisan dre:import-action-plan --version=action_plan_v1

# 4. Equipe financeira acessa /dre/mappings e classifica as pendências.
#    OrderPayments com status=done já estão sendo projetados (observer).

# 5. Aquece cache e abre /dre/matrix.
php artisan dre:warm-cache
```

Fim do ciclo — a partir daqui é operação contínua: cada OP que vira `done`
projeta em `dre_actuals`, cada mapping criado invalida o cache, o warm-up
roda diariamente 05:50, e o fechamento mensal em `/dre/periods` congela
o histórico.
