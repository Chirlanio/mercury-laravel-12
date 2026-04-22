# DRE — Formatos de importação XLSX

Este documento padroniza as três planilhas aceitas pelos importadores
manuais da DRE:

- `dre-actuals.xlsx` → `App\Services\DRE\DreActualsImporter`
- `dre-budgets.xlsx` → `App\Services\DRE\DreBudgetsImporter`
- `plano-de-contas.xlsx` → `App\Services\DRE\ChartOfAccountsImporter` (ver `dre-plano-contas-formato.md`)

Todos os três são acionáveis via:

- CLI: `php artisan dre:import-{actuals|budgets|chart}`
- HTTP: `POST /dre/imports/{actuals|budgets|chart}` (auth + permissions)

---

## Convenção de sinal (actuals e budgets)

**Sempre positivo na planilha.** O importador converte o sinal conforme
o `account_group` da conta:

| Grupo | Descrição              | Sinal final | Aceito? |
|-------|------------------------|-------------|---------|
| 1     | Ativo                  | —           | ❌ rejeita (não pertence à DRE) |
| 2     | Passivo                | —           | ❌ rejeita |
| 3     | Receitas               | +abs(valor) | ✅ |
| 4     | Custos/Despesas        | -abs(valor) | ✅ |
| 5     | Resultado              | -abs(valor) | ✅ |

Se o valor vier negativo na planilha, o `abs()` é aplicado antes da
conversão — não há "valor estornado" nesse fluxo.

---

## `dre-actuals.xlsx` — realizado manual

Cabeçalho na linha 1. Dados a partir da linha 2.

| Coluna             | Obrigatório | Formato / observações |
|--------------------|-------------|-----------------------|
| `entry_date`       | **sim**     | `YYYY-MM-DD` (ex: `2026-03-15`) ou data do Excel. Tem de ser **estritamente posterior** ao último fechamento ativo em `dre_period_closings` — senão a linha é pulada. |
| `store_code`       | **sim**     | Código da loja (ex: `Z421`). |
| `account_code`     | **sim**     | Código analítico da conta contábil (ex: `4.2.1.04.00032`). Conta sintética → linha pulada. |
| `cost_center_code` | opcional    | Código do CC. Se preenchido e inexistente, linha é pulada. |
| `amount`           | **sim**     | Numérico positivo. Conversão de sinal automática. |
| `document`         | opcional    | Nº do documento (máx 60 chars — truncado silenciosamente). |
| `description`      | opcional    | Texto livre (máx 500 chars). |
| `external_id`      | opcional    | Identificador estável do sistema externo. Quando presente, dedup por `(source=MANUAL_IMPORT, external_id)` — re-importar com mesmo `external_id` **atualiza** a linha. Sem `external_id`, sempre cria nova linha. |

### Exemplo (5 linhas)

```
entry_date,store_code,account_code,cost_center_code,amount,document,description,external_id
2026-03-01,Z421,4.2.1.04.00032,421,1250.00,NF-89321,Telefonia março,CASH-2026-03-Z421-001
2026-03-05,Z421,4.2.1.04.00032,,890.50,NF-89340,Telefonia (sem CC),
2026-03-12,Z441,3.1.1.01.00001,,2500.00,VD-1001,Receita extra,CASH-2026-03-Z441-002
2026-03-20,Z443,4.2.1.04.00032,443,350.00,REC-201,,CASH-2026-03-Z443-003
2026-03-28,Z421,5.1.01.00015,421,80.00,AJ-05,Ajuste contábil,CASH-2026-03-Z421-004
```

### Regras de rejeição (linha pulada, erro acumulado em PT-BR)

- `entry_date` ausente/inválida → "entry_date inválida ou ausente."
- `entry_date` ≤ `lastClosedUpTo` → "entry_date ({date}) dentro de período fechado ({lastClosed})."
- `store_code` ausente → "store_code obrigatório."
- Loja inexistente → "loja 'X' não encontrada."
- `account_code` ausente → "account_code obrigatório."
- Conta inexistente → "conta 'X' não encontrada no plano."
- Conta sintética → "conta 'X' é sintética — só analíticas aceitam lançamento."
- `cost_center_code` preenchido e inexistente → "centro de custo 'X' não encontrado."
- `amount` não-numérico → "amount inválido ou ausente."
- Conta no grupo 1/2 → "conta 'X' pertence ao grupo {N} (Ativo/Passivo) e não pode entrar na DRE."

O import continua após cada erro — erros são acumulados, linhas válidas
são persistidas na mesma transação.

---

## `dre-budgets.xlsx` — orçado manual

Diferenças principais em relação a actuals:

- `store_code` é **opcional** (budget pode ser consolidado por rede).
- `entry_date` é **normalizada para o dia 1 do mês** (convenção de
  `dre_budgets`). Aceita `YYYY-MM` sem dia.
- Sem `external_id` — uma `budget_version` é imutável; re-importação com
  mesma version duplica linhas. Use versions incrementais (`2026.v1`,
  `2026.v2`).
- Não checa fechamento de período — orçado é prospectivo por natureza.
- `budget_version` **não** vem da planilha — vem do form/CLI (`--version=...`).

| Coluna             | Obrigatório | Formato / observações |
|--------------------|-------------|-----------------------|
| `entry_date`       | **sim**     | `YYYY-MM-DD` ou `YYYY-MM`. Normalizado para dia 1 do mês. |
| `store_code`       | opcional    | Código da loja. Null → budget consolidado. |
| `account_code`     | **sim**     | Analítica. Conta sintética → linha pulada. |
| `cost_center_code` | opcional    | Código do CC. |
| `amount`           | **sim**     | Numérico positivo. |
| `notes`            | opcional    | Texto livre (máx 500 chars). |

### Exemplo (5 linhas)

```
entry_date,store_code,account_code,cost_center_code,amount,notes
2026-01,,4.2.1.04.00032,,1200.00,Telefonia jan consolidada
2026-02,,4.2.1.04.00032,,1200.00,Telefonia fev consolidada
2026-03,Z421,4.2.1.04.00032,421,800.00,Meta específica Z421
2026-01,,3.1.1.01.00001,,280000.00,Receita prevista jan
2026-02,,3.1.1.01.00001,,310000.00,Receita prevista fev
```

### Regras específicas

- Re-importação com mesma `budget_version` **não** deduplica — as linhas
  novas coexistem com as antigas. Para substituir uma versão, use uma
  versão diferente (`2026.v2`) ou delete manualmente as linhas antigas
  antes de reimportar.
- Diferente do `BudgetToDreProjector` (módulo Budgets), este fluxo não
  cria `BudgetUpload` — grava direto em `dre_budgets`. Os dois fluxos
  coexistem: `budget_upload_id` fica `NULL` nas linhas importadas
  manualmente, populado nas vindas do Budgets.

---

## Ponte automática `BudgetUpload` → `dre_budgets`

Orçamentos gerenciados pelo módulo Budgets (UI padrão) são projetados
automaticamente para `dre_budgets` quando o `BudgetUpload.is_active` vira
`true`. O mapeamento:

- Cada `BudgetItem` vira até 12 linhas em `dre_budgets` (uma por mês com
  valor ≠ 0).
- `budget_version = BudgetUpload.version_label` (ex: "1.0", "2.03").
- `budget_upload_id` referencia o upload — permite reprojetar.
- Ativar um novo upload no mesmo `(year, scope_label)` remove as linhas
  do upload anterior (semântica "1 ativa por escopo" da v1).

Para detalhes da ponte, ver `docs/dre-arquitetura.md §2.6` e
`App\Services\DRE\BudgetToDreProjector`.

---

## Permissões

| Rota                             | Permission          |
|----------------------------------|---------------------|
| `GET /dre/imports/chart`         | `dre.manage_mappings` (estender quando houver CHART dedicado) |
| `POST /dre/imports/chart`        | idem |
| `GET /dre/imports/actuals`       | `dre.import_actuals` |
| `POST /dre/imports/actuals`      | idem |
| `GET /dre/imports/budgets`       | `dre.import_budgets` |
| `POST /dre/imports/budgets`      | idem |

Todas sob `tenant.module:dre` + `permission:dre.view` (acesso ao módulo).

---

## Limitações conhecidas

- **Execução síncrona.** Planilhas até ~5k linhas rodam em poucos segundos.
  Para volumes maiores, rodar via CLI (`php artisan dre:import-actuals
  path.xlsx`) em vez da UI HTTP — jobs async com polling ficaram em backlog
  (playbook prompt 10, item "Jobs").
- **Sem preview.** O dry-run valida mas não devolve pré-visualização das
  linhas — apenas contadores e erros. Preview rico (tabela das 10 primeiras
  linhas) é iteração futura.
- **Sem drag-and-drop.** O input é `<input type="file">` padrão. Componente
  dedicado (como o de Purchase Orders) é iteração futura.
