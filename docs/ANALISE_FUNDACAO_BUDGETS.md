# Fundação Budgets/DRE — Análise das Fases 0.1 e 0.2

**Data de conclusão:** 20/04/2026
**Commits:** 10 (5 por fase)
**Testes:** 48 feature tests / 182 assertions

---

## Por que uma "Fase 0"?

O módulo Budgets (Orçamentos) é ponto de partida para o futuro módulo DRE. Se importássemos planilhas de orçamento guardando `accounting_class` e `management_class` como `VARCHAR` (como a v1 faz), o DRE viria com 200 variações de "Salários" escritas de 15 maneiras. Migrar histórico depois vira projeto inteiro.

Solução: **estruturar as entidades referenciáveis antes** do módulo principal.

---

## Fase 0.1 — CostCenter standalone

Migração de config module → módulo standalone.

### Ponto de partida

- `Config/CostCenterController extends ConfigController` com apenas 5 colunas úteis
- UI compartilhada via `Pages/Config/Index.jsx`
- Controle de acesso genérico via `MANAGE_SETTINGS`
- Consumido apenas por `OrderPayment` via FK nullable

### Entregas

- **Tabela `cost_centers` expandida**: `description`, `parent_id` (self FK para hierarquia), `default_accounting_class_id` (FK adicionada só na Fase 0.2 para evitar dependência circular de migrations), audit fields, soft delete manual, unique em `code` (corrige gap original)
- **Model `CostCenter`**: `Auditable`, parent/children, scopes, helper `ancestorsIds()` para validação de ciclo
- **Service `CostCenterService`**: cycle validation em parent, dedup por code, delete bloqueado quando há filhos ativos
- **7 permissions granulares** `cost_centers.{view, create, edit, delete, manage, import, export}`
- **Rotas `/cost-centers`** standalone; `/config/cost-centers` agora é `Route::redirect(301)` (6 meses até remover)
- **Frontend `Pages/CostCenters/Index.jsx`**: StatisticsGrid (5 cards), filtros, DataTable, 5 modais (create/edit/detail/delete/import) — padrão `StandardModal`
- **Export XLSX + Import XLSX** com preview+upsert por code
- **24 feature tests** (17 controller + 7 import/export)
- **Cleanup**: deletado `Config/CostCenterController.php`, removido de seeders, menu migrado de "Configurações" para "Financeiro"

### Commits
| Hash | Descrição |
|------|-----------|
| `428a658` | Migration + seed central + permissions |
| `9b50a63` | Model + Service + FormRequests |
| `cb0d87a` | Controller + routes + 17 backend tests |
| `d36b22a` | Frontend + export/import (+7 tests) |
| `93eae0f` | Cleanup config + redirect 301 |

---

## Fase 0.2 — AccountingClass (Plano de Contas)

Plano de Contas Contábil — esqueleto do DRE.

### Novos enums

**`AccountingNature`** — natureza contábil (db `nature` VARCHAR 10):
- `DEBIT` — saldo natural devedor (despesas, custos, ativos, impostos)
- `CREDIT` — saldo natural credor (receitas, passivos, PL)

**`DreGroup`** — 11 grupos agregadores do DRE (db `dre_group` VARCHAR 40):

| Ordem | Grupo | Natureza natural |
|-------|-------|------------------|
| 1 | Receita Bruta | credit |
| 2 | Deduções da Receita | debit |
| 3 | CMV | debit |
| 4 | Despesas Comerciais | debit |
| 5 | Despesas Administrativas | debit |
| 6 | Despesas Gerais | debit |
| 7 | Outras Receitas Operacionais | credit |
| 8 | Outras Despesas Operacionais | debit |
| 9 | Receitas Financeiras | credit |
| 10 | Despesas Financeiras | debit |
| 11 | Impostos sobre o Lucro | debit |

Cada enum tem `label()`, `dreOrder()`, `naturalNature()`, `color()`, `increasesResult()`.

### Schema

```
accounting_classes (
    id, code (unique 30), name, description,
    parent_id (FK self nullable, onDelete=null),
    nature ('debit'|'credit'),
    dre_group (1 de 11 valores),
    accepts_entries bool,
    sort_order int,
    is_active bool,
    created_by_user_id, updated_by_user_id,
    timestamps,
    deleted_at, deleted_by_user_id, deleted_reason (500)
)
```

Mais a **FK pendente** resolvida aqui: `cost_centers.default_accounting_class_id → accounting_classes.id` (coluna foi criada na Fase 0.1 sem constraint para evitar dependência circular de migrations).

### Regras de negócio

- **Folha não pode ser pai**: se `parent.accepts_entries = true`, rejeita. Agrupadores devem ser sintéticos.
- **Ciclo em parent_id**: rejeita se o novo pai é descendente da própria conta.
- **Virar folha com filhos**: bloqueia `accepts_entries=true` quando há filhos ativos.
- **Delete com filhos ativos**: bloqueia com mensagem.
- **Natureza divergente do grupo**: só sinaliza (warning ⚠ na UI) — não bloqueia. Divergências são legítimas (ex: "Desconto Obtido" lançado como redutor de despesa financeira).

### Seed BR simplificado

~50 contas cobrindo:
- 3.x Receitas (Vendas Loja/E-commerce, Serviços)
- 4.x CMV (Produtos, Serviços)
- 5.x Despesas (Comerciais, Administrativas, Gerais, Outras Op)
- 6.x Financeiras (Receitas e Despesas + IOF + Taxas de Cartão)
- 7.x Impostos sobre Lucro (IRPJ + CSLL)

Seed é idempotente: `if (DB::table('accounting_classes')->exists()) return;`. Cliente personaliza depois via UI ou import de CSV próprio.

### Entregas

- **Controller standalone** com CRUD + statistics + endpoint `/tree` (retorna árvore hierárquica em O(n) via mapa id→node)
- **7 permissions granulares** `accounting_classes.*`
- **Frontend com toggle Lista/Árvore**:
  - **Lista**: DataTable com cores por grupo DRE (11 cores codificadas), badge circular D/C para natureza, ícone ⚠ quando conta diverge da natureza natural do grupo
  - **Árvore**: recursiva com expand/collapse, ordenação natural por code (3.1 < 3.1.01 < 3.1.01.001), hover-to-reveal actions
- **5 modais**: create, edit, detail (com filhas + aviso de divergência), delete (motivo obrigatório min 3 chars), import
- **Export XLSX** ordenado por code + **Import XLSX** com aliases PT-BR:
  - `nature`: `debit/credit`, `d/c`, `devedora/credora`, `débito/crédito`
  - `accepts_entries`: `sim/não`, `1/0`, `folha/analitica` (true), `sintetica` (false)
- **24 feature tests** (17 controller + 7 import/export)

### Commits
| Hash | Descrição |
|------|-----------|
| `972268d` | Migrations + enums + permissions |
| `66b4b82` | Model + Service + BR chart seed + FormRequests |
| `6d428e2` | Controller + routes + 17 backend tests |
| `4266821` | Frontend (toggle lista/árvore) + import/export + 7 tests |
| `(este)` | Documentação consolidada |

---

## Decisões arquiteturais não-óbvias

### 1. FK `cost_centers.default_accounting_class_id` em duas etapas

Para evitar dependência circular, a coluna foi criada na Fase 0.1 **sem FK constraint** — só coluna indexada. A FK real foi adicionada na Fase 0.2 na mesma migration que cria `accounting_classes`. Migrations rodam em ordem alfabética: 0.2 roda depois de 0.1, então a coluna já existe.

### 2. `accepts_entries` bool em vez de enum `type: synthetic | analytic`

Bool responde à pergunta real do sistema ("posso lançar valor aqui?"). Folha = `true`, grupo = `false`. Mais direto que abstração desnecessária.

### 3. "Folha não pode ser pai" enforce em 3 camadas

- **UI**: select `parents` filtra apenas grupos sintéticos
- **Service**: `ensureParentIsValid()` rejeita `parent.accepts_entries = true`
- **Import**: `resolveParentId()` retorna erro explícito e rejeita a linha

### 4. `DreGroup::naturalNature()` não é enforcement

Só sinaliza na UI (⚠ amarelo no detalhe). Divergências são legítimas — não bloquear.

### 5. Todos os planos têm os dois módulos

Cadastros de fundação. Habilitados em todos os tenants sem gate de plano.

### 6. Tree view sem lazy loading

Dataset pequeno (~50-200 contas). `/tree` retorna tudo de uma vez. Depth 0 começa expandido, depth > 0 começa fechado.

### 7. Aliases PT-BR no import

Equipe financeira raramente digita `debit` — prefere PT-BR. O service normaliza via `self::NATURE_MAP`.

---

## Permissões totais (14)

`cost_centers.{view, create, edit, delete, manage, import, export}` (7)
`accounting_classes.{view, create, edit, delete, manage, import, export}` (7)

Distribuição:
| Role | Cost Centers | Accounting Classes |
|------|---|---|
| SUPER_ADMIN | todas | todas |
| ADMIN | todas | todas |
| SUPPORT | todas | todas |
| USER | view + export | view + export |

---

## Testes (consolidado)

**Total: 48 tests / 182 assertions / ~6s**

| Suite | Tests |
|-------|-------|
| `CostCenterControllerTest` | 17 |
| `CostCenterImportExportTest` | 7 |
| `AccountingClassControllerTest` | 17 |
| `AccountingClassImportExportTest` | 7 |

Cobertura: CRUD, permissões, ciclo em parent, folha-como-pai rejeitada, delete com filhas bloqueado, soft delete manual com motivo obrigatório, tree hierarchy, statistics, import upsert idempotente, rejeição de arquivos inválidos, aliases PT-BR, parent ausente silencioso vs erro duro.

---

## Próximos passos

- **Fase 0.3** — `ManagementClass` (Plano de Contas Gerencial) com FK opcional para `AccountingClass`. Começa vazio, tenant popula conforme uso.
- **Fase 1+** — Módulo Budgets propriamente dito (5 fases previstas), usando as 3 fundações (CC/AC/MC) para resolver FKs reais no import do Excel de orçamento via preview + fuzzy matching + reconciliação.

---

## Referências

- **Memory interna**: `memory/budgets_foundation.md`
- **Documentos irmãos**: `ANALISE_MODULO_REVERSALS.md`, `ANALISE_MODULO_RETURNS.md`
- **Código CostCenter**: `app/Models/CostCenter.php`, `app/Services/CostCenter*.php`, `app/Http/Controllers/CostCenterController.php`, `resources/js/Pages/CostCenters/`
- **Código AccountingClass**: `app/Models/AccountingClass.php`, `app/Enums/{AccountingNature,DreGroup}.php`, `app/Services/AccountingClass*.php`, `app/Http/Controllers/AccountingClassController.php`, `resources/js/Pages/AccountingClasses/`
