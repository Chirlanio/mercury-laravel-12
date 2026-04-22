# 04 — Glossário Orçamentos

> Termos do módulo Budgets. Para termos da DRE, veja
> [DRE — 04 Glossário](../dre/04-glossario.md).
> 🟢 = básico · 🟡 = intermediário · 🔴 = técnico

---

## A

### 🟡 `area_department_id`

FK opcional em `BudgetUpload` que aponta para uma classe gerencial sintética
no formato `8.1.DD`. Adicionado na **Fase 5** do projeto. Identifica a área
organizacional do orçamento (Marketing, TI, Operações…). Para uploads
legados é nullable; novos uploads passam pela validação no controller.

---

## B

### 🟢 BudgetItem (Item de Orçamento)

**Uma linha do orçamento** — uma combinação de conta + CC + (opcionalmente)
loja, com 12 valores mensais e total ano. Tabela `budget_items`.

### 🟢 BudgetUpload (Versão de Orçamento)

**Cabeçalho de uma versão.** Agrupa N items, tem ano, escopo, versão
(1.0/1.01/2.0…), tipo (NOVO/AJUSTE) e flag `is_active`. Tabela
`budget_uploads`.

### 🔴 BudgetToDreProjector

Service que **projeta um BudgetUpload ativo em `dre_budgets`**. Idempotente
(delete-then-insert). Disparado pelo `BudgetUploadDreObserver` quando
`is_active=true`. Faz superseding (apaga linhas de uploads anteriores no
mesmo `(year, scope_label)`).

### 🔴 BudgetVersionService

Service que calcula a próxima versão (`major`, `minor`, `label`) baseada em
`(year, scope_label, upload_type)`. Lógica:
- Primeiro do `(year, scope)` → `1.0`
- Tipo `NOVO` → incrementa major, zera minor (`1.05` → `2.0`)
- Tipo `AJUSTE` → incrementa minor (`1.05` → `1.06`)
- Reset por ano novo
- Ignora versões deletadas

### 🔴 BulkPaste / Wizard

Componente React `BudgetUploadWizard` com 3 steps (`upload`, `reconcile`,
`confirm`). Usa fuzzy match (Levenshtein) para sugerir códigos quando o
XLSX traz códigos parciais.

---

## C

### 🟢 Committed (Comprometido)

Métrica do dashboard de consumo: **OPs (Order Payments) lançadas** que
apontam para items deste BudgetUpload, com qualquer status exceto
`cancelled`. Soma o que está "no pipeline" (lançado, mesmo que ainda não
pago).

---

## D

### 🟢 Dashboard de consumo

Tela em `/budgets/{id}/dashboard`. Mostra **forecast vs committed vs
realized** com 4 visões (por item, por CC, por categoria contábil, por mês).
Service: `BudgetConsumptionService::getConsumption()`.

### 🟢 Diff (Comparação)

Tela em `/budgets/compare?v1=X&v2=Y`. Mostra adicionados/removidos/alterados
entre 2 versões. Service: `BudgetDiffService::diff()`.

---

## E

### 🟢 Editar inline

Capacidade de **editar células diretamente na tabela** sem subir XLSX
novo. Endpoint `PATCH /budget-items/{id}`. Audit log (`Auditable` trait)
registra cada mudança. **Não dispara reprojeção em DRE imediatamente** —
só quando o upload muda `is_active`.

### 🟢 Escopo (`scope_label`)

Identificador lógico do orçamento — string até 100 chars. Granularidades
comuns:
- `"Geral"` — orçamento único da empresa
- `"Administrativo"`, `"TI"`, `"Comercial"` — por área
- `"Z421"`, `"Z425"` — por loja

**Uma versão ativa por (year, scope_label).**

### 🟢 Exceeded (Estourado)

Estado em que `realized ≥ forecast` para um CC. Trigger de alerta crítico
em `budgets:alert`. Aparece em vermelho no dashboard.

### 🟢 Export consolidado

`GET /budgets/{id}/export` — XLSX com 6 sheets: resumo, por CC, por
categoria, por mês, dashboard, comparações. Pronto para apresentação.
Permission: `budgets.export`.

---

## F

### 🟢 Forecast (Previsto)

Métrica do dashboard: **soma `year_total` dos items** do BudgetUpload.
É o valor planejado. Equivale ao "orçado" da DRE.

### 🟡 Force delete

Hard delete (apaga fisicamente). Apenas SUPER_ADMIN. Endpoint
`DELETE /budgets/{id}/force`. Use para dados sensíveis vazados ou lixo
crítico. **Não é reversível.**

### 🔴 Fuzzy match

Algoritmo no `BudgetImportService::preview()` para sugerir códigos quando
XLSX traz código parcial. Usa Levenshtein distance ≤ min(3, 30% do
tamanho do código). Exibido como dropdown no Step 2 do wizard.

---

## H

### 🔴 HEADER_MAP

Mapa de aliases de colunas do XLSX → campos canônicos. Em
`BudgetImportService`. 35+ aliases reconhecidos:
- `"codigo_contabil"`, `"código contábil"`, `"conta contabil"` → `accounting_class_code`
- `"codigo_centro_custo"`, `"cc"`, `"centro custo"` → `cost_center_code`
- (etc.)

---

## I

### 🟡 `is_active`

Flag em `BudgetUpload`. Quando `true`, o upload alimenta `dre_budgets`.
**Apenas uma versão ativa por `(year, scope_label)`** — ativar
desativa as demais (superseding).

---

## L

### 🟢 Lixeira

`/budgets/trash` — lista de uploads soft-deleted (com `deleted_at` preenchido).
Operações: **restore** (zera `deleted_at`, mas não reativa) e **force delete**
(SUPER_ADMIN, hard delete).

---

## M

### 🔴 `major_version` / `minor_version`

Inteiros que compõem o `version_label`. Convenção semântica:
- NOVO incrementa major, zera minor
- AJUSTE incrementa minor

Exemplo: `1.0` → AJUSTE → `1.01` → NOVO → `2.0`.

---

## N

### 🟢 NOVO × AJUSTE (`upload_type`)

Tipo do upload, declarado pelo usuário no Step 3 do wizard.

| Tipo | Quando usar | Resultado |
|---|---|---|
| `NOVO` | Substituição completa, mudança radical | Incrementa major (`1.x` → `2.0`) |
| `AJUSTE` | Refinamento incremental, mesma estrutura | Incrementa minor (`1.0` → `1.01`) |

**Diferença é semântica/audit**, não técnica. Ambos disparam superseding.

---

## O

### 🟢 OP (Order Payment)

Despesa lançada no módulo `/order-payments`. Cada OP pode apontar para um
BudgetItem específico — quando o usuário escolhe `cost_center` no formulário,
o sistema mostra items disponíveis (`/budgets/items-for-cost-center/{cc}`).
Essa associação alimenta as métricas committed/realized.

### 🟢 Orçado

Veja [DRE — Glossário, Orçado](../dre/04-glossario.md#orçado). Sinônimo de
**Forecast** no dashboard. É a coluna na DRE alimentada por `dre_budgets`.

---

## P

### 🟡 Plano gerencial sintético

Classe `8.1.DD` (departamento) usada como `area_department_id` no
BudgetUpload. Identificação organizacional. Não é a mesma coisa que a
linha DRE — é só metadado de quem "dono" do orçamento.

### 🟢 Preview

Step 1 do wizard. `POST /budgets/preview`. Lê o XLSX, normaliza headers,
roda fuzzy match, retorna diagnóstico (válidas/pendentes/rejeitadas).
**Não persiste nada.**

---

## R

### 🟢 Realized (Realizado)

Métrica do dashboard: **OPs com status `done`** que apontam para items deste
BudgetUpload. Soma o que efetivamente saiu do caixa.

### 🟡 Reconcile

Step 2 do wizard. Usuário resolve FKs ausentes via dropdowns
pré-preenchidos com sugestões fuzzy. Não persiste — apenas prepara o
mapping para o Step 3.

### 🟢 Restore

Endpoint `POST /budgets/{id}/restore`. Zera `deleted_at`,
`deleted_by_user_id`, `deleted_reason`. **Não reativa** (`is_active`
permanece `false`). Para reativar após restore, abrir e clicar "Ativar".

---

## S

### 🟡 Scope label

Veja [Escopo](#escopo-scope_label).

### 🟡 Soft delete

Ato de marcar `deleted_at = now()` sem apagar fisicamente. Endpoint
`DELETE /budgets/{id}` exige `deleted_reason` (mín 10 chars). Se o upload
estava ativo, também desativa (apaga `dre_budgets` correspondente).
Reversível via [Restore](#restore).

### 🟢 Status (Badge)

Label visual na lista de versões:
- **ATIVO** (verde) — `is_active=true`
- **INATIVO** (cinza) — `is_active=false`
- **EXCLUÍDO** (vermelho, na lixeira) — `deleted_at != NULL`

### 🟡 Superseding

Regra: ativar uma versão **desativa qualquer outra ativa do mesmo
`(year, scope_label)`**. Implementada em `BudgetService::create()` e
`BudgetUpload::activate()`. Em `dre_budgets`, apaga linhas da versão
anterior antes de inserir as novas.

---

## T

### 🟢 Template

XLSX modelo pré-formatado com colunas + 2 linhas de exemplo. Endpoint
`GET /budgets/template`. Use sempre como ponto de partida para evitar erros
de formato.

### 🔴 `total_year`

Cache de soma dos 12 meses do BudgetUpload. Recalculado quando:
- BudgetItem é criado/atualizado/deletado
- Edição inline de `month_*_value`

---

## U

### 🟢 Upload

Veja [BudgetUpload](#budgetupload-versão-de-orçamento).

### 🟢 `upload_type`

Veja [NOVO × AJUSTE](#novo--ajuste-upload_type).

### 🟢 Utilização

Percentual = `realized / forecast × 100`. Trigger de alertas:
- ≥ 70% → warning
- ≥ 100% → exceeded

---

## V

### 🟢 Versão (`version_label`)

String compilada de `major_version` + `minor_version`. Exemplo: `"1.0"`,
`"1.01"`, `"2.0"`, `"2.05"`. Calculada por
`BudgetVersionService::resolveNextVersion()`.

---

## W

### 🟢 Wizard

`BudgetUploadWizard.jsx` — componente React com 3 steps (upload, reconcile,
confirm) que guia o usuário no fluxo de subir nova versão. 33 KB de
componente.

---

## Categorias

### Conceitos básicos
[BudgetItem](#budgetitem-item-de-orçamento), [BudgetUpload](#budgetupload-versão-de-orçamento),
[Escopo](#escopo-scope_label), [Forecast](#forecast-previsto),
[NOVO × AJUSTE](#novo--ajuste-upload_type), [Versão](#versão-version_label),
[Wizard](#wizard).

### Métricas
[Committed](#committed-comprometido), [Exceeded](#exceeded-estourado),
[Forecast](#forecast-previsto), [Realized](#realized-realizado),
[Utilização](#utilização).

### Operações
[Diff](#diff-comparação), [Editar inline](#editar-inline),
[Force delete](#force-delete), [Lixeira](#lixeira), [Preview](#preview),
[Reconcile](#reconcile), [Restore](#restore), [Soft delete](#soft-delete),
[Superseding](#superseding), [Template](#template).

### Técnico
[`area_department_id`](#area_department_id), [BudgetToDreProjector](#budgettodreprojector),
[BudgetVersionService](#budgetversionservice), [Fuzzy match](#fuzzy-match),
[HEADER_MAP](#header_map), [`is_active`](#is_active),
[`major_version` / `minor_version`](#major_version--minor_version),
[`total_year`](#total_year).

---

> **Última atualização:** 2026-04-22
