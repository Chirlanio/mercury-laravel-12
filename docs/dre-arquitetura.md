# Arquitetura — Módulo DRE Financeira

**Data:** 2026-04-21 (revisado após respostas em `dre-descoberta.md` §7)
**Pré-requisito:** leitura de `docs/dre-descoberta.md`.
**Escopo:** design do módulo e justificativas. Sem código de produção.

> **Linguagem:** nomes de tabela/coluna em **snake_case + inglês**, plural para tabelas — é a convenção real do projeto (`accounting_classes`, `cost_centers`, `budget_items`, `stores`, `movements`, `sales`, `order_payments`). O prompt do usuário propõe nomes em inglês; respeitamos tal qual.

### Decisões já fechadas (respostas do usuário em `dre-descoberta.md §7`)

| # | Pergunta | Resposta |
|---|---|---|
| 1 | Plano de contas: substituir seed ou incrementar? | **Substituir** o seed atual pelo XLSX oficial (1.129 contas). |
| 2 | Fonte do realizado | Não é `movements` (só estoque). Usar `order_payments` (parte das despesas), `sales` (receitas) + import manual (balancete do ERP pro que faltar). |
| 4 | Linhas da DRE | Entidade nova (`dre_management_lines`). |
| 5 | Versionamento gerencial | **NÃO versionar.** Estrutura única vigente, auditada quando muda. |
| 6 | Congelar períodos fechados | **Sim.** Modelar `dre_period_closings`. |
| 7 | Versão MySQL | MySQL 8+ (CTE e window functions disponíveis). |
| 8 | Granularidade DRE | **3 visões:** Geral, Rede, Loja. Filtros na UI. |
| 9 | CC no mapping | Opcional (mantido). Precedência: específico ganha do coringa. |
| 10 | Tela de pendências | No MVP. |
| 17 | Conta analítica para receita de Sale | **Por loja** — coluna `stores.sale_chart_of_account_id` + fallback global. |
| — | 19 linhas da DRE executiva | **Opção C:** seed #1 entrega 16 linhas DRE-BR padrão do enum `DreGroup`; o CFO insere as 3–4 linhas executivas restantes via UI após o prompt #4. |
| — | `Action Plan v1.xlsx` | **3 usos:** (1) primeiro `dre_budgets` 2026 via prompt #10.5; (2) pré-popula `chart_of_accounts.default_management_class_id`; (3) alimenta botão "Usar sugestão" na tela de Pendências. |

**Estratégia para as 19 linhas (resposta do usuário em 2026-04-21):**

Inferir do banco é possível **parcialmente**. Temos:

- **Enum `App\Enums\DreGroup`** com 11 grupos contábeis e ordem canônica em `dreOrder()` (1..11).
- **Comentário docblock do enum** (linhas 12–27 de `DreGroup.php`) já lista uma DRE BR padrão completa com 16 linhas = 11 grupos não-subtotal + 5 subtotais calculados (Receita Líquida, Lucro Bruto, Lucro Operacional/EBIT, Resultado Antes Impostos, Lucro Líquido).
- **`ManagementClass`** com 11 departamentos (`8.1.01..11`) + ~170 nós — mas isso é plano gerencial operacional, **não** é linha de DRE executiva.

**O que não dá pra inferir:**

- A DRE executiva do Power BI tem **19 linhas**, não 16. As 3–5 linhas "a mais" são conceitos executivos que não existem no enum nem no `management_classes`:
  - **Headcount** (provável linha 13) — bloco próprio de pessoal, sai de Despesas Comerciais/Admin.
  - **Marketing e Corporativo** (provável linha 12) — idem, agrupamento executivo.
  - **EBITDA** como subtotal formal — o enum só tem "Lucro Operacional" (não idêntico a EBITDA, que exclui D&A).
  - **Lucro Líquido s/ Cedro** (linha 19) — consolidação sem subsidiária específica.

**Decisão: seed em duas etapas (opção C escolhida pelo usuário).**

- **Prompt #1 — seed inicial (16 linhas DRE-BR padrão):** derivadas automaticamente do enum `DreGroup`. Destrava o cronograma imediatamente; a matriz já funciona end-to-end com a estrutura contábil padrão.
- **Refinamento via UI (pós-prompt #4):** após o CRUD das linhas estar pronto, o CFO insere diretamente pela tela `/dre/management-lines` as linhas executivas adicionais (Headcount, Marketing e Corporativo, EBITDA formal, Lucro Líquido s/ Cedro) e reorganiza `sort_order`. Como `sort_order` tem gaps de 10, inserção não requer renumeração em massa. Não depende de planilha interna, nem de PBIX — é trabalho de 10 min na UI.

### Arquivo `Action Plan v1.xlsx` — destino

O usuário forneceu `C:/Users/MSDEV/Downloads/Action Plan v1.xlsx` (3861 linhas × 9 colunas). **Não** é a estrutura das 19 linhas da DRE executiva, mas é **orçamento real 2026** detalhado por loja × conta contábil × classe gerencial × mês. Uso acordado:

1. **Primeiro `dre_budgets` populado.** Import direto do XLSX para `dre_budgets`, sem passar por `budget_items`. Arquivo vira a fonte canônica do orçado 2026 até que um novo upload via módulo Budgets substitua.
2. **Pré-popular `chart_of_accounts.default_management_class_id`.** O XLSX traz ~132 pares únicos `(conta contábil, classe gerencial)`. Deduplica → popula um FK opcional na conta contábil. Ajuda o time na tela de Pendências: ao classificar uma conta, a UI já sugere a classe gerencial.
3. **UI de Pendências destaca contas pré-mapeadas.** Quando uma conta analítica já tem `default_management_class_id` sugerida pelo Action Plan, a tela `/dre/mappings/pending` mostra ícone verde + botão "Usar sugestão" — pulando autocomplete manual.

Impacto no cronograma: 1 prompt novo (#10.5) + enhancement em #2 (ChartOfAccountsImporter) + enhancement em #5 (Pendências UI).

---

## 1. Decisão estruturante: reconciliação com o que já existe

O v2 já tem 3 entidades que se sobrepõem parcialmente às tabelas propostas. Antes de qualquer modelagem nova, precisamos resolver:

| Entidade pedida (prompt) | Entidade atual (v2) | Decisão recomendada |
|---|---|---|
| `chart_of_accounts` | `accounting_classes` (80 contas) | **Renomear + estender + reimportar.** `accounting_classes` vira `chart_of_accounts`. Seed atual descartado — o XLSX oficial (1.129 linhas) vira fonte. |
| `cost_centers` | `cost_centers` (24 linhas atuais — 421–457) | **Estender + reimportar.** CCs atuais (421–457) na verdade são códigos de loja (`stores.code`) — vão sumir. Entram os 11 CCs do Excel (`8.1.01..8.1.11`). |
| `dre_management_lines` | `management_classes` | **Coexistem — conceitos distintos.** `management_classes` é plano gerencial operacional interno (169 classes hoje, vinculadas a AC+CC). `dre_management_lines` é a DRE executiva (19 linhas com subtotais). São coisas diferentes. |
| `dre_mappings` | parcialmente no `management_classes.accounting_class_id + cost_center_id` | **Cria nova.** O link embutido em `management_classes` é só um hint default, não um de-para auditável. A tabela nova é fonte de verdade do DRE. |
| `dre_actuals` | nenhuma | **Cria nova.** Espelho canônico unificado. Populado por projetores (OrderPayment, Sale) e import manual. Ver §2.5. |
| `dre_budgets` | `budget_items` (pivot 12 meses) | **Coexistem.** `budget_items` fica como storage versionado da planilha (Budgets permanece operando). `dre_budgets` é a forma normalizada (1 linha por mês) consumida pela matriz DRE; populada via `BudgetToDreProjector` quando um `budget_upload` é ativado. |
| `dre_period_closings` | nenhuma | **Cria nova.** Congela períodos (resposta #6). |

### 1.1 Por que renomear `accounting_classes` → `chart_of_accounts` (e não manter os dois)

- A tabela atual já tem exatamente o papel de "plano de contas contábil". O nome novo é mais preciso e alinhado ao domínio contábil real (chart of accounts).
- Manter as duas geraria duplicação imediata de 80+ registros reais e de FKs em `budget_items`, `management_classes.accounting_class_id`, `cost_centers.default_accounting_class_id`.
- O custo do rename é localizado e faz sentido antes do módulo DRE crescer a base de ~80 para ~1.129 contas:
  - 1 migration renomeia a tabela + renomeia FKs nos 3 lugares acima.
  - Renomear Model + Service + Controller + rotas + React Pages.
  - Renomear permissions no enum (`VIEW_ACCOUNTING_CLASSES` → `VIEW_CHART_OF_ACCOUNTS`, etc.).
  - Criar alias 301 da rota antiga para a nova (já temos padrão 301 em uso em Cost Centers — memória `budgets_foundation.md`).

### 1.2 Por que `dre_management_lines` **não** substitui `management_classes`

| | `management_classes` | `dre_management_lines` |
|---|---|---|
| Cardinalidade | ~169 nós hierárquicos | 19 linhas planas numeradas |
| Uso | Plano gerencial interno; recebe lançamentos via Budgets (`budget_items.management_class_id`) | Apresentação da DRE executiva; NÃO recebe lançamento direto |
| Hierarquia | `parent_id` self | `sort_order` linear + `level_1..level_4` (drill) |
| Subtotais | Não (é classificação) | Sim (`is_subtotal`, acumula linhas anteriores) |
| Versionamento | Não (plano único vigente) | Sim (`version`, pode coexistir v2026 e v2027) |

Confundir os dois quebraria Budgets. Decisão: mantemos as duas e **o `dre_mappings` aponta sempre para `dre_management_lines`**, não para `management_classes`.

### 1.3 Por que `dre_budgets` é separado de `budget_items`

- `budget_items` é pivot (12 colunas `month_XX_value`) — otimizado para a UX do wizard e para o export multi-sheet já existente.
- `dre_budgets` é normalizado (1 linha por mês) — formato que a matriz DRE consome naturalmente com `GROUP BY entry_date`.
- Evita refatorar Budgets (módulo recém-entregue, 72+ tests passando). `BudgetToDreProjector` converte um `budget_upload` ativo em ~N×12 linhas em `dre_budgets` quando o upload é marcado ativo.

---

## 2. Modelagem das tabelas

### 2.1 `chart_of_accounts` (renomeado de `accounting_classes`)

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `code` | `varchar(40)` UNIQUE | Ex: `1.1.1.01.00016`. Formato do ERP. |
| `reduced_code` | `varchar(20)` UNIQUE | Ex: `1191`. Código interno curto do ERP. |
| `name` | `varchar(200)` | Ex: `CAIXA TESOURARIA`. |
| `type` | `enum('synthetic','analytical')` | S/A do ERP. Substitui `accepts_entries` (analytical = accepts entries). |
| `account_group` | `tinyInteger` | 1=Ativo, 2=Passivo, 3=Receitas, 4=Custos/Despesas, 5=Resultado. Derivado do primeiro segmento do code. Grupo 8 (CCs) **não** entra aqui. |
| `classification_level` | `tinyInteger` | 0..4 — número de pontos no code. |
| `parent_id` | `bigInteger NULL` FK self | Derivado do `code` truncado no último `.`. |
| `dre_group` | `enum DreGroup NULL` | Preservado de `accounting_classes`. Dica do grupo DRE, usada como _seed_ do `dre_mappings` para contas analíticas. |
| `balance_nature` | `char(1) NULL` | `D` ou `C`. Mapeia do antigo `nature` enum. No Excel vem `A` (ambos) — reservamos o campo mas aceitamos null. |
| `is_result_account` | `boolean` | true para grupos 3, 4, 5. Derivado. |
| `is_active` | `boolean default true` | |
| `default_management_class_id` | `foreignId NULL` | Sugestão de classe gerencial pré-populada (origem: `Action Plan v1.xlsx` ou edição manual). Não é de-para formal do DRE — é hint para a UI de Pendências "usar sugestão". |
| `external_source` | `varchar(20) NULL` | `CIGAM`, `TAYLOR`, `ZZNET` — qual ERP é a origem. |
| `imported_at` | `timestamp NULL` | Última importação que tocou esta linha. |
| `sort_order` | `integer default 0` | Preservado para UI tree. |
| `description` | `text NULL` | Preservado. |
| `created_by_user_id` / `updated_by_user_id` | `foreignId NULL` | Preservado. |
| `deleted_at` / `deleted_by_user_id` / `deleted_reason` | soft delete manual | Convenção do projeto. |
| `created_at` / `updated_at` | timestamps | |

**Índices:**
- UNIQUE `code`, UNIQUE `reduced_code`.
- `(account_group, classification_level)` composto.
- `parent_id`, `is_active`, `deleted_at`.
- `(type, is_active)` composto — queries de listagem só-analíticas.

**Constraint de integridade (via app — não via DB):**
- Ao gravar `type='synthetic'`, não pode existir lançamento (`dre_actuals` / `dre_budgets` / `budget_items`) apontando para este id.
- Ao gravar `parent_id`, o pai tem que ter `type='synthetic'`.

**Migration path (rename vs novo):**
1. Rename tabela + colunas + FKs. Laravel `Schema::rename` suportado.
2. Backfill `reduced_code`, `classification_level`, `account_group`, `is_result_account` a partir do `code` existente (seeder de migração).
3. FKs renomeadas nos 3 lugares: `budget_items.accounting_class_id` → `chart_of_account_id`, `management_classes.accounting_class_id` → idem, `cost_centers.default_accounting_class_id` → `default_chart_of_account_id`.
4. Permissions renomeadas no enum. Role.php reflete.
5. Rotas antigas (`/config/accounting-classes`) devolvem 301 para `/dre/chart-of-accounts`.

### 2.2 `cost_centers` (extensão)

Já existe. Apenas adicionar:

| Coluna | Tipo | Notas |
|---|---|---|
| `reduced_code` | `varchar(20) NULL UNIQUE` | Opcional. Alguns ERPs usam. |
| `external_source` | `varchar(20) NULL` | `CIGAM`, `TAYLOR`, `ZZNET`. |
| `imported_at` | `timestamp NULL` | |

Não mexer em: `code` (8.1.01), `name`, `parent_id`, `default_accounting_class_id` (→ `default_chart_of_account_id`), `is_active`, `manager_id`, `area_id`, audit fields, soft delete manual.

**Observação importante:** o grupo 8 do Excel (11 CCs tipo "Marketing", "Operações", etc.) tem códigos `8.1.01..8.1.11`. Vão alimentar `cost_centers.code`. Os CCs já existentes no projeto (24 linhas, códigos `421..457`) seguem convenção diferente. **Isto é uma decisão a tomar com o negócio** (ver seção 12): unificamos sob um único `code`, ou mantemos 2 padrões de código (`421..457` para lojas-como-CC + `8.1.01..8.1.11` para CCs departamentais)? Recomendação: a estrutura do Excel (8.1.XX) é a canônica vinda do contador — importamos essas e **os códigos atuais (421..457) são na verdade o `stores.code`, não CCs de verdade**.

### 2.3 `dre_management_lines` (nova)

A DRE executiva. Cardinalidade: ~20 linhas (19 + 1 fantasma). **Estrutura única vigente** (resposta #5 — sem versionamento). Mudanças são auditadas via `Auditable` + soft delete manual.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | `bigIncrements` | |
| `code` | `varchar(20)` UNIQUE | Ex: `L01`, `L02`, `L99_UNCLASSIFIED`. Estável; a ordem pode mudar, o code não. |
| `sort_order` | `smallInteger` UNIQUE | 10, 20, 30... (cresce por 10 para permitir inserção futura). |
| `is_subtotal` | `boolean default false` | true para linhas 3, 5, 11, 13, 15, 17, 19 (exemplo). |
| `accumulate_until_sort_order` | `smallInteger NULL` | Quando `is_subtotal=true`, somatório vai de 1 até esse sort_order inclusive. Permite EBITDA acumular 1..13 e Lucro Líquido acumular 1..17 sem ambiguidade. |
| `level_1` | `varchar(150)` | Ex: `(+) Faturamento Bruto`, `(=) EBITDA`. |
| `level_2`, `level_3`, `level_4` | `varchar(150) NULL` | Drill interno — usado só em não-subtotais. Subtotais ficam null. |
| `nature` | `enum('revenue','expense','subtotal')` | Usado pela UI para colorir variação (verde favorável / vermelho desfavorável). |
| `is_active` | `boolean default true` | |
| `notes` | `text NULL` | |
| `created_by_user_id`, `updated_by_user_id` | `foreignId NULL` | |
| soft delete manual + timestamps | | |

**Índices:** UNIQUE `code`, UNIQUE `sort_order` (ambos com filtro `WHERE deleted_at IS NULL` via partial index em MySQL 8+), `is_active`.

**Semente inicial:** seeder carrega as 19 linhas da DRE executiva do Power BI + 1 linha-fantasma `(!) Não classificado`. O usuário forneceu 8 labels no prompt; as 11 restantes precisam ser confirmadas antes do prompt #1 (ver §12.1).

**Auditoria de mudanças:** trait `Auditable` registra `before`/`after` em `activity_logs`. Qualquer mudança em `sort_order`, `accumulate_until_sort_order`, `nature` fica rastreável. Se o CFO precisar comparar "como era antes", consulta `activity_logs`.

### 2.4 `dre_mappings` (nova) — a ponte

Sem `version` (resposta #5). Mudanças retroativas em período fechado são bloqueadas via `dre_period_closings` (§2.8).

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | `bigIncrements` | |
| `chart_of_account_id` | `foreignId` NOT NULL | Precisa ser analítica. Validado via FormRequest. |
| `cost_center_id` | `foreignId NULL` | NULL = mapping coringa (vale para qualquer CC). |
| `dre_management_line_id` | `foreignId` NOT NULL | |
| `effective_from` | `date` NOT NULL | Só pode ser ≥ `last_closed_month + 1 dia`. |
| `effective_to` | `date NULL` | NULL = vigente. |
| `notes` | `text NULL` | Justificativa do mapeamento (auditoria). |
| `created_by_user_id` | `foreignId` NOT NULL | |
| `updated_by_user_id` | `foreignId NULL` | |
| soft delete manual + timestamps | | |

**Índices:**
- UNIQUE `(chart_of_account_id, cost_center_id, effective_from)` com filtro `WHERE deleted_at IS NULL` — evita duplicados ativos no mesmo período.
- `(chart_of_account_id, effective_from, effective_to)` — query de resolução.
- `(deleted_at)` — listagens.

**Gotcha MySQL:** `UNIQUE` com coluna nullable permite múltiplos nulos (`cost_center_id IS NULL`) — padrão SQL. Para um par `(conta, CC=null)`, precisamos garantir unicidade no app (Service faz busca + upsert atômico), como já fazemos em Reversals (memória `reversals_module.md` documenta o mesmo problema).

**Linha-fantasma "(!) Não classificado":**

Decisão: sim, criar. Faz parte do seeder de `dre_management_lines` — uma linha extra com `code='L99_UNCLASSIFIED'`, `sort_order=999`, `is_subtotal=false`, `nature='expense'`, rotulada `(!) Não classificado`. Lançamentos cuja conta analítica não tem mapping vigente caem aqui. A matriz DRE **sempre** renderiza essa linha, e ela aparece em vermelho se houver valor > 0. O CFO pode bater o olho e saber imediatamente que há coisa a classificar.

### 2.5 `dre_actuals` (nova) — espelho canônico unificado

Realizado vindo de **3 fontes** (resposta #2):

1. **`OrderPayment`** (status `done`, com `accounting_class_id` + `cost_center_id` + `store_id` + `competence_date` + `total_value`) — fonte principal das despesas contabilizadas. Já tem **todos os campos que o DRE precisa** (confirmado na leitura do model). Projetado via observer: quando `OrderPayment` transita para `done`, uma linha em `dre_actuals` é criada/atualizada.
2. **`Sale`** (agregado por `store_id` + `date_sales` + `total_sales`) — faturamento bruto. Projetado via observer ou command diário. A conta analítica alvo é fixa (ex: `3.1.1.01.00001` — VENDAS A VISTA) ou configurável por loja. Ver §12.4 dúvida sobre múltiplas contas de receita.
3. **Import manual** de balancete do ERP (para despesas contábeis que **não** passam pelo OrderPayment — ex: depreciação, provisões, impostos diferidos).

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | `bigIncrements` | |
| `entry_date` | `date` | Data de competência (`competence_date` quando vier do OrderPayment, `date_sales` quando vier de Sale). |
| `chart_of_account_id` | `foreignId` NOT NULL | Precisa ser analítica. |
| `cost_center_id` | `foreignId NULL` | Nem todo lançamento tem CC (Sales geralmente não tem). |
| `store_id` | `foreignId NULL` | FK para `stores.id`. **Nullable** — há despesas corporativas sem loja (ver §12.3 risco 17). |
| `amount` | `decimal(15,2)` | **Sinalizado.** Receita positiva, despesa negativa. Ver 2.5.1. |
| `source` | `enum('ORDER_PAYMENT','SALE','MANUAL_IMPORT','CIGAM_BALANCE')` | |
| `source_type` | `varchar(100) NULL` | Nome fqcn do model origem (`App\Models\OrderPayment`, `App\Models\Sale`). Polimórfico. |
| `source_id` | `bigInteger NULL` | Id da linha origem. Permite drill-through da matriz até o lançamento original. |
| `document` | `varchar(60) NULL` | NF, duplicata, etc. (vem de `OrderPayment.number_nf`). |
| `description` | `varchar(500) NULL` | Histórico do lançamento (vem de `OrderPayment.description`). |
| `external_id` | `varchar(100) NULL` | ID estável do lançamento no ERP (se `source=MANUAL_IMPORT`/`CIGAM_BALANCE`). |
| `imported_at` | `timestamp NULL` | |
| `created_by_user_id` / `updated_by_user_id` | `foreignId NULL` | Preenchido em lançamentos manuais. |
| `created_at` / `updated_at` | timestamps | |

**Sem soft delete** — lançamentos são imutáveis. Cancelamento de `OrderPayment` (volta de `done` para estado anterior) → remove a linha em `dre_actuals` (via observer). Isto é diferente de estorno contábil: é reflexo do estado canônico da fonte.

**Índices (críticos):**
- `(entry_date, store_id, chart_of_account_id, cost_center_id)` — filtro mais comum na matriz.
- `(chart_of_account_id, entry_date)` — drill por conta.
- `(source_type, source_id)` — busca reversa (qual `dre_actual` veio deste `OrderPayment`). UNIQUE quando ambos não nulos (1 projeção por origem).
- UNIQUE parcial de `(source, external_id)` onde `external_id` não null — dedup de imports manuais.

**Projetores (observers):**

| Fonte | Evento gatilho | Ação |
|---|---|---|
| `OrderPayment` | Observer `saving` quando `status` vira `done` | Cria/atualiza `dre_actuals` com `source='ORDER_PAYMENT'`, `source_id=order_payment.id`. |
| `OrderPayment` | Observer quando `status` sai de `done` | Deleta linha correspondente. |
| `OrderPayment` | Observer `updating` em campos que afetam DRE (`accounting_class_id`, `cost_center_id`, `store_id`, `competence_date`, `total_value`) com status=done | Atualiza linha projetada. |
| `Sale` | Observer `created` | Cria linha em `dre_actuals` com conta de receita padrão + `source='SALE'`. |
| Import manual | `DreActualsImporter` | INSERT em lote com `source='MANUAL_IMPORT'` / `CIGAM_BALANCE`. |

**Rebuild command:** `dre:rebuild-actuals --source=ORDER_PAYMENT` — trunca linhas de uma fonte e reprojeta tudo. Útil quando um bug no observer deixa estado dessincronizado, ou quando mudamos a regra de conta de receita default do Sale.

#### 2.5.1 Justificativa da convenção de sinal

**Decisão: `amount` é sinalizado.** Receita = positivo. Despesa = negativo. Motivos:

1. **Matriz DRE fica trivial:** `SUM(amount)` já devolve o resultado correto por linha gerencial. Não precisamos consultar `dre_management_lines.nature` na query agregadora.
2. **Subtotais acumulam por soma simples:** EBITDA = `SUM(amount)` de todas as linhas anteriores, sem lógica condicional.
3. **Alinhado com Power BI existente** (que tratava `(-) Deduções` como valor negativo na fonte, não invertia na apresentação).
4. **Orçado × Realizado fica 1:1:** `dre_budgets.amount` segue mesma convenção.

**Importador (`DreActualsImporter`) faz a conversão quando o ERP entrega positivo + indicador D/C:**

```
se source = 'CIGAM' e balance_nature do ERP = 'D' e account_group ∈ {4,5}:
    amount = -abs(valor_erp)
senão se account_group ∈ {3}:
    amount = +abs(valor_erp)
...
```

As regras completas ficam documentadas no `DreActualsImporter` com comentário indicando a convenção. Faz parte do contrato da importação.

### 2.6 `dre_budgets` (nova)

Orçado normalizado.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | `bigIncrements` | |
| `entry_date` | `date` | Sempre dia 1 do mês (`2026-01-01`, `2026-02-01`...). Convenção: o valor do mês todo é amarrado ao dia 1. |
| `chart_of_account_id` | `foreignId` NOT NULL | Analítica. |
| `cost_center_id` | `foreignId NULL` | |
| `store_id` | `foreignId NULL` | Orçado pode ser por loja ou agregado. |
| `amount` | `decimal(15,2)` | Mesma convenção de sinal. |
| `budget_version` | `varchar(30)` | `v1`, `revisado_jun`, etc. Espelha `budget_uploads.version_label`. |
| `budget_upload_id` | `foreignId NULL` | Quando vem de um upload do módulo Budgets, guarda a rastreabilidade. Null = orçado inserido manualmente pelo DRE. |
| `notes` | `varchar(500) NULL` | |
| `created_by_user_id` / `updated_by_user_id` | `foreignId NULL` | |
| timestamps | | |

**Sem soft delete.** Mudar versão = nova linha, não UPDATE.

**Índices:**
- `(entry_date, store_id, chart_of_account_id, cost_center_id, budget_version)` — query principal.
- `(budget_upload_id)` — para re-projeção quando um upload é reativado.
- `(chart_of_account_id, entry_date, budget_version)` — drill.

**Projetor:** `BudgetToDreProjector`:
- Escuta observer em `BudgetUpload` ao flipar `is_active=true`.
- Desativa versão anterior do mesmo `(year, scope_label)`: `DELETE FROM dre_budgets WHERE budget_upload_id = <anterior_ativo>`.
- Explode cada `budget_item` em 12 linhas (uma por mês_XX_value não-zero).
- `chart_of_account_id`, `cost_center_id`, `store_id` vêm do `budget_item`.
- `amount` = `month_XX_value`, convertido para sinalizado segundo `chart_of_accounts.account_group` (despesa negativa; receita positiva).
- `budget_version` = `budget_upload.version_label`.

Isto também resolve um débito atual do Budgets: hoje `budget_items` só tem valores mensais em pivot, sem linha temporal. `dre_budgets` é a forma canônica por período.

### 2.7 Linha-fantasma "(!) Não classificado"

Seed de `dre_management_lines` inclui uma linha `code='L99_UNCLASSIFIED'`, `sort_order=9990`, `is_subtotal=false`, `nature='expense'`, `level_1='(!) Não classificado'`. Lançamentos cuja conta analítica não tem `dre_mapping` vigente caem aqui. A matriz sempre a renderiza; se valor > 0, UI mostra em vermelho destacado.

**Fila de trabalho (resposta #24):** contas não mapeadas **precisam** ser identificadas — não há opção "ignorar" no MVP. A tela `/dre/mappings/pending` lista todas as analíticas ativas sem mapping vigente e é tratada como fila de trabalho do time financeiro. Um contador persistente aparece no sidebar (badge numérico ao lado de "DRE"), pressionando o time a zerar a fila. Classificação em lote por grupo contábil (ex: "todas as contas do grupo 3.1.1 → Linha DRE 1") é incluída no MVP para viabilizar o backlog inicial de centenas de contas do XLSX oficial.

### 2.8 `dre_period_closings` (nova) — fechamento de períodos (resposta #6)

Permite ao time financeiro "fechar" um período (ex: Janeiro/2026) para evitar mudanças retroativas acidentais em mapping ou em `dre_actuals` manuais.

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | `bigIncrements` | |
| `closed_up_to_date` | `date` UNIQUE | Última data inclusiva fechada. Ex: `2026-01-31` fecha todo janeiro. |
| `closed_by_user_id` | `foreignId` NOT NULL | |
| `closed_at` | `timestamp` | |
| `reopened_by_user_id` | `foreignId NULL` | Se o período for reaberto. |
| `reopened_at` | `timestamp NULL` | |
| `reopen_reason` | `text NULL` | Justificativa obrigatória quando reabre. |
| `notes` | `text NULL` | |
| timestamps | | |

**Regra de negócio (enforcement em PHP via FormRequest + Service):**
- `dre_mappings`: `effective_from` não pode ser ≤ `MAX(closed_up_to_date)`.
- `dre_mappings`: `deleted_at` (soft delete) é proibido se `effective_from` ≤ `MAX(closed_up_to_date)` e `effective_to` é null ou > esse valor.
- `dre_actuals` com `source=MANUAL_IMPORT`: não pode ter `entry_date` ≤ `MAX(closed_up_to_date)`.
- **Exceção:** `dre_actuals` com `source=ORDER_PAYMENT` ou `SALE` continuam projetando mesmo em período fechado — a fonte é canônica. Se o time fechar janeiro e um `OrderPayment` com `competence_date='2026-01-15'` transitar pra `done` depois, ele **vai** entrar. Alternativa: avisar no frontend ("há lançamento nova em período fechado — reabrir ou aceitar?"). Esta decisão precisa validação com o CFO (dúvida #25 em §12.4).

**UI:**
- Tela `/dre/periods` lista fechamentos passados, com botões "Fechar mês atual" e "Reabrir último fechamento".

**Snapshot de imutabilidade (resposta #25 — entra no MVP):**

A resposta do usuário pediu "valores imutáveis com possibilidade de atualizar" — interpretação: no fechamento, snapshotear a matriz para garantir leitura idêntica no futuro; reabertura permite recomputar e snapshotear de novo.

Tabela adicional **`dre_period_closing_snapshots`**:

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | `bigIncrements` | |
| `dre_period_closing_id` | `foreignId` NOT NULL | |
| `scope` | `enum('GENERAL','NETWORK','STORE')` | Para cada escopo da matriz. |
| `scope_id` | `bigInteger NULL` | `network_id` ou `store_id` conforme scope (null em GENERAL). |
| `dre_management_line_id` | `foreignId` NOT NULL | |
| `year_month` | `char(7)` | `YYYY-MM`. |
| `actual_amount` | `decimal(15,2)` | |
| `budget_amount` | `decimal(15,2)` | |
| `created_at` | timestamp | |

**Índices:** `(dre_period_closing_id, scope, scope_id, year_month)`.

**Fluxo:**
- `DrePeriodClosingService::close()` computa matriz live via `DreMatrixService` para os 3 escopos × todas as redes × todas as lojas × todos os meses dentro do período fechado, e insere linhas em `dre_period_closing_snapshots`.
- `DreMatrixService`, quando o filtro cai em período fechado, lê do snapshot (não computa live). Se período meio-fechado meio-aberto, híbrido: meses fechados do snapshot + abertos computados.
- `DrePeriodClosingService::reopen()` apaga snapshots do período reaberto. Ao fechar de novo, snapshota novamente.

**Notificação de projeção em período fechado (resposta #23 — default aceito):**

Projetores (`OrderPaymentToDreProjector`, `SaleToDreProjector`) registram a linha em `dre_actuals` mesmo quando `entry_date` cai em período fechado — a fonte é canônica. Como o snapshot é imutável, isso **não afeta** a leitura histórica da matriz. Para evitar ruído, **não disparamos notificação por projeção individual**. Em vez disso:

- Tabela auxiliar `dre_actuals.reported_in_closed_period` (boolean, default false) é marcada `true` quando o projetor detecta `entry_date ≤ closed_up_to_date`.
- `DrePeriodClosingService::reopen()` executa um **relatório consolidado** antes de apagar o snapshot: compara valor agregado atual (live) × snapshot existente, lista as diferenças por linha DRE/mês e anexa no payload da resposta + notifica por e-mail o `MANAGE_DRE_PERIODS`. O CFO vê "há 17 lançamentos novos em Janeiro somando R$ 45k na linha Headcount" antes de refechar.
- Badge sutil na matriz indica "Há N lançamentos pós-fechamento em Janeiro" (link leva à tela de reabertura), sem toast por evento.

Isto resolve: (a) valores de período fechado ficam imutáveis mesmo com mudança de mapping/lançamento manual retroativo por super-admin; (b) possibilidade de atualizar via reabrir → revisar relatório consolidado → refechar; (c) performance melhor em leituras históricas (uma query no snapshot em vez de matriz live); (d) zero ruído operacional — o time fiscal/DP segue aprovando OrderPayments sem receber toast a cada clique.

**Permission:** `MANAGE_DRE_PERIODS` (só ADMIN financeiro). SUPPORT e USER só visualizam.

---

## 3. Camada de serviço

Pasta: `App\Services\DRE\` (**primeira vez que o projeto usa subpasta em `Services/`** — hoje tudo fica flat; justifica-se porque são 7+ serviços relacionados e o módulo é isolável).

Confirmar com o projeto se adota. Alternativa (mais aderente ao padrão atual): prefixo em classes flat (`DreMatrixService`, `DreMappingService`, ...) sem subpasta. **Recomendação: subpasta `DRE/`**, porque o módulo é rico e a flat list em `Services/` já passa de 87 classes e está confusa.

### Services planejados

| Classe | Responsabilidade |
|---|---|
| `ChartOfAccountsImporter` | Lê XLSX do ERP. Upsert por `reduced_code` (chave estável entre reimportações). Deriva `parent_id`, `classification_level`, `account_group`, `is_result_account` a partir do `code`. Detecta contas novas (`type='analytical'` sem mapping) e dispara evento `ChartOfAccountsImported` para a fila de pendências. |
| `CostCentersImporter` | Idem para grupo 8 do XLSX. |
| `DreMappingService` | CRUD do de-para. Valida: (a) conta é analítica, (b) CC opcional, (c) sem overlap temporal para o mesmo par, (d) `effective_to >= effective_from`, (e) `effective_from` > último período fechado. Emite `DreMappingChanged` para invalidar cache. |
| `DreMappingResolver` | Dado `(account_id, cost_center_id, date)`, devolve o `dre_management_line_id` aplicando precedência (CC específico > CC null > linha-fantasma). **Isolado** para ser unit-testável sem tocar DB em todos os cenários. |
| `DreMatrixService` | Núcleo. Dado `DreMatrixFilter`, monta a matriz gerencial com 3 colunas de valor (Realizado / Orçado / Ano Anterior), por mês × linha gerencial. Usa `DreMappingResolver` + agregação em SQL. Aplica agregação por `scope` (Geral / Rede / Loja — resposta #8). |
| `DreSubtotalCalculator` | Aplica a lógica de subtotais sobre a matriz já agregada. Isolado para unit test. |
| `DreActualsImporter` | Importa lançamentos realizados de balancete ERP via XLSX. Dedup por `(source, external_id)`. Converte sinal conforme `account_group`. Bloqueia import em período fechado. |
| `DreBudgetsImporter` | Importa orçamento manual do XLSX quando não vem de `budget_items`. |
| `BudgetToDreProjector` | Converte `budget_upload` ativo → linhas em `dre_budgets`. Rodado por observer ou manualmente via command. |
| `OrderPaymentToDreProjector` | Observer em `OrderPayment`. Quando `status` vira `done` ou campos relevantes mudam com `status=done`, sincroniza linha em `dre_actuals` com `source='ORDER_PAYMENT'`. Quando sai de `done`, remove. |
| `SaleToDreProjector` | Observer em `Sale`. Cria linha em `dre_actuals` com conta de receita padrão (ver §12.4 dúvida) e `source='SALE'`. |
| `UnmappedAccountsFinder` | Query auxiliar: lista contas analíticas ativas sem mapping vigente. Base da tela "Pendências" (resposta #10). |
| `DrePeriodClosingService` | Fecha/reabre períodos. Valida que não há pendências bloqueantes. Cria log em `activity_logs`. |

### DTOs

Pasta: `App\DTOs\DRE\` (idem — primeira vez que o projeto usa DTO formal; hoje o padrão é array associativo). Aqui a justificativa é mais fraca — **recomendação: continuar com array associativo + validação via `FormRequest`**. DTO formal introduz uma convenção nova sem benefício claro.

Se mesmo assim quisermos DTOs, os 2 essenciais:

- `DreMatrixFilter { startDate, endDate, storeIds[], networkIds[], budgetVersion, managementVersion, includeUnclassified }`
- `DreMatrixRow { lineId, code, level1..4, isSubtotal, nature, monthValues[], totalActual, totalBudget, totalLastYear }`

---

## 4. Estratégia SQL do `DreMatrixService`

### 4.1 As duas opções

#### Opção A — Query única com JOIN precedente via window function

```sql
-- pseudocódigo MySQL 8+
WITH resolved_mapping AS (
  SELECT a.id AS actual_id,
         a.entry_date,
         a.chart_of_account_id,
         a.cost_center_id,
         a.amount,
         m.dre_management_line_id,
         ROW_NUMBER() OVER (
            PARTITION BY a.id
            ORDER BY CASE WHEN m.cost_center_id IS NULL THEN 2 ELSE 1 END
         ) rn
  FROM dre_actuals a
  JOIN dre_mappings m
    ON m.chart_of_account_id = a.chart_of_account_id
   AND (m.cost_center_id = a.cost_center_id OR m.cost_center_id IS NULL)
   AND m.version = :mgmt_version
   AND a.entry_date BETWEEN m.effective_from AND COALESCE(m.effective_to, '9999-12-31')
   AND m.deleted_at IS NULL
  WHERE a.entry_date BETWEEN :from AND :to
    AND a.store_id IN (:stores)
)
SELECT dre_management_line_id,
       DATE_FORMAT(entry_date, '%Y-%m') AS ym,
       SUM(amount) AS total
FROM resolved_mapping
WHERE rn = 1
GROUP BY dre_management_line_id, ym
```

**Prós:** uma viagem ao DB; deixa o MySQL fazer o trabalho pesado. MySQL 8+ confirmado (resposta #7), logo window function é viável.
**Contras:**
- Precedência embutida na query dificulta testar.
- Contas analíticas sem mapping **não entram no SELECT** (o JOIN as elimina); precisamos LEFT JOIN do lado dos actuals para pegar a linha-fantasma "Não classificado". Isso complica a query.
- Requer duplicação da query para `dre_budgets` e `dre_actuals` do ano anterior → 3 queries estruturalmente iguais.

#### Opção B — Resolução em duas etapas (**recomendada**)

**Passo 1 — Resolve mapping em memória:**

```
mapping_table = SELECT * FROM dre_mappings
  WHERE deleted_at IS NULL
  AND effective_from <= :to
  AND (effective_to IS NULL OR effective_to >= :from)
```

(poucas centenas de linhas — cabe em RAM facilmente)

`DreMappingResolver` transforma isso em `array[chart_of_account_id][cost_center_id|'*'] = line_id`, já aplicando precedência em PHP. Resultado: índice direto `O(1)`.

**Passo 2 — Agrega actuals/budgets já resolvendo linha:**

```sql
SELECT chart_of_account_id,
       cost_center_id,
       YEAR(entry_date) AS y,
       MONTH(entry_date) AS m,
       SUM(amount) AS total
FROM dre_actuals
WHERE entry_date BETWEEN :from AND :to
  AND store_id IN (:stores)
GROUP BY chart_of_account_id, cost_center_id, y, m
```

Em PHP, itera o resultset e resolve `(account, cc) → line_id` via `DreMappingResolver`. Contas sem match caem na linha-fantasma automaticamente.

Mesma query roda 3 vezes (realizado período, orçado período, realizado ano anterior) — trivial paralelizar via `Bus::batch` ou rodar sequencial (latência do MySQL é baixa quando os índices estão ok).

**Prós:**
- `DreMappingResolver` é unit-testado com 100% de cobertura de cenários de precedência, sem DB.
- Contas sem mapping são tratadas naturalmente (fallback para linha-fantasma em PHP).
- Fácil acrescentar novas dimensões (moeda, consolidação com/sem Cedro — linha 19) sem mexer em SQL.
- Independente de recurso SQL específico (funciona inclusive em SQLite nos testes).

**Contras:**
- 2 viagens ao DB (mas a primeira é pequena e cacheável em RAM — poucas centenas de linhas).
- Cardinalidade da segunda query: agrupa por `(account, cc, year, month)` — pode devolver dezenas de milhares de linhas antes do PHP reduzir. Ainda assim é ordem de grandeza bem menor que `dre_actuals` completo, e o PHP só faz somas.

### 4.2 Recomendação: Opção B

Mesmo com MySQL 8+ confirmado (resposta #7) e window functions disponíveis, mantemos a recomendação da Opção B. Justificativa: o bloco mais difícil do DRE é a lógica de precedência do mapping. Qualquer bug aqui materializa em número errado em relatório de CFO. Testar isoladamente em PHP vale o pequeno custo da 2ª query — e os testes do projeto rodam em SQLite in-memory, onde garantir paridade com MySQL 8+ seria trabalho extra.

### 4.3 Diferença NULL vs específico — algoritmo exato

```
resolve(account_id, cost_center_id, date):
    candidatos = mapping_index[account_id] ?? []
    # 1. Tenta match específico (CC = cost_center_id passado)
    específico = candidatos.primeiro onde cc == cost_center_id
                                 e effective_from <= date
                                 e (effective_to is null ou effective_to >= date)
    se específico: return específico.line_id
    # 2. Fallback coringa (CC = null)
    coringa = candidatos.primeiro onde cc is null
                              e effective_from <= date
                              e (effective_to is null ou effective_to >= date)
    se coringa: return coringa.line_id
    # 3. Não classificado
    return LINE_UNCLASSIFIED_ID
```

Testes unitários cobrem:
- Só específico existe → usa específico.
- Só coringa existe → usa coringa.
- Ambos existem → específico ganha.
- Nenhum existe → linha-fantasma.
- Específico expirou em `effective_to` → cai em coringa.
- 2 específicos para mesmo `(account, cc)` em períodos adjacentes → cada data pega o certo.
- Overlap temporal inválido (2 mappings ativos simultaneamente) → erro de validação, nunca chega ao resolver.

---

## 5. Subtotais

Aplicados **sobre a matriz já agregada**, não sobre os lançamentos.

```
Input: matriz { line_id → { month → valor_realizado, valor_orcado, valor_ano_anterior } }
       ordenada por dre_management_lines.sort_order

Output: matriz igual, mas com subtotais preenchidos

para cada linha L ordenada por sort_order:
    se L.is_subtotal:
        # soma todas as linhas não-subtotal cujo sort_order <= L.accumulate_until_sort_order
        para cada mês M:
            soma_realizado[L][M] = SUM(matriz[X][M] onde X.is_subtotal=false e X.sort_order <= L.accumulate_until_sort_order)
            idem para orçado e ano anterior
```

Isolado em `DreSubtotalCalculator` — 100% unit-testável.

**Equivalência com o DAX original:** `FILTER(ALL(D_Contabil), D_Contabil[Ordem] <= ordem_atual)` do Power BI fica literalmente como comentário em PT-BR em cima do método que implementa isso — para auditoria futura rastrear a intenção.

---

## 6. Cache

**Proposta:** `Cache::remember('dre:matrix:' . md5(serialize($filter->toArray())), 600, fn() => ...)` — TTL 10 min.

**Problema:** o driver de cache default do projeto é `database` (ver descoberta §6.2), que **não** suporta `Cache::tags()`.

**Solução — cache version key:**

1. Uma chave global `dre:cache_version` guarda um inteiro (começa em 1).
2. Chave da matriz inclui a version: `dre:matrix:v{N}:md5(...)`.
3. Observers em `DreMapping`, `DreManagementLine`, `DreActual`, `DreBudget`, `BudgetUpload` fazem `Cache::increment('dre:cache_version')` no `saved`/`deleted`.
4. Qualquer mudança invalida _tudo_ de DRE de uma só vez (sem tag).

Prós: funciona em `database`/`file`/`redis`. Simples. Zero acoplamento a driver específico.
Contras: invalidação é bruta (invalida a cache inteira mesmo que a mudança seja local) — aceitável porque DRE é lida grossa.

**Chave exata:**
```
dre:matrix:v{cache_version}:{md5(json_encode(filter_array_normalized))}
```

Normalizar o filter antes de hash: arrays ordenados, datas `Y-m-d`, chaves em ordem alfabética — previne hit/miss inconsistente com mesmo filtro em ordens diferentes.

**Warm-up:** command `dre:warm-cache` rodando diariamente 05:50 (antes do sync do CIGAM às 06:00) para o período mês atual + 11 meses anteriores + lojas ativas — leitura manhã fica instantânea. Scheduled em `routes/console.php`.

---

## 7. Camada HTTP

### 7.1 Rotas

Agrupadas sob middleware `tenant`, `auth`, `tenant.module:dre`. Todas em `routes/web.php` (ou arquivo dedicado `routes/dre.php` incluído — a decidir).

```
GET    /dre/matrix                           matrix.show
GET    /dre/matrix/drill                     matrix.drill             (JSON)

GET    /dre/chart-of-accounts                chart-of-accounts.index  (read-only)
GET    /dre/chart-of-accounts/tree           chart-of-accounts.tree   (JSON)
GET    /dre/chart-of-accounts/{id}           chart-of-accounts.show

GET    /dre/cost-centers                     cost-centers.index       (read-only — CRUD continua em /config/cost-centers)

GET    /dre/management-lines                 management-lines.index
POST   /dre/management-lines                 management-lines.store
PUT    /dre/management-lines/{id}            management-lines.update
DELETE /dre/management-lines/{id}            management-lines.destroy
POST   /dre/management-lines/reorder         management-lines.reorder

GET    /dre/mappings                         mappings.index
POST   /dre/mappings                         mappings.store
PUT    /dre/mappings/{id}                    mappings.update
DELETE /dre/mappings/{id}                    mappings.destroy
GET    /dre/mappings/pending                 mappings.pending         (contas sem mapping)
POST   /dre/mappings/bulk                    mappings.bulkStore       (classificar várias em lote)

GET    /dre/periods                          periods.index            (lista fechamentos)
POST   /dre/periods                          periods.store            (fechar novo período)
PUT    /dre/periods/{id}/reopen              periods.reopen           (reabre fechamento — exige reason)

POST   /dre/imports/chart                    imports.chart            (XLSX plano de contas)
POST   /dre/imports/cost-centers             imports.costCenters
POST   /dre/imports/actuals                  imports.actuals          (XLSX lançamentos)
POST   /dre/imports/budgets                  imports.budgets          (XLSX orçado manual)

GET    /dre/export/matrix                    export.matrix            (XLSX + PDF)
```

### 7.2 Controllers

Pasta: **raiz de `app/Http/Controllers/`** (convenção do projeto — ver descoberta §1.2). Nada de subpasta.

- `DreMatrixController` — `show`, `drill`, `export`.
- `ChartOfAccountsController` — `index`, `show`, `tree`.
- `CostCenterController` — já existe; não mexemos. Criar CRUD paralelo dentro de DRE é duplicação — usamos o existente e, se necessário, uma _view_ read-only em `/dre/cost-centers` que reaproveita o mesmo Controller via rota nova.
- `DreManagementLineController` — CRUD 19 linhas.
- `DreMappingController` — CRUD + `pending` + `bulkStore`.
- `DrePeriodClosingController` — `index`, `store`, `reopen`.
- `DreImportController` — 4 endpoints de upload.

Form Requests: um por endpoint não-trivial (`StoreDreMappingRequest`, `UpdateDreManagementLineRequest`, etc.).

---

## 8. Autorização

### 8.1 Novas permissions em `App\Enums\Permission`

Suite inicial (12 permissions):

- `VIEW_DRE` — ver a matriz.
- `EXPORT_DRE` — exportar XLSX/PDF.
- `MANAGE_DRE_STRUCTURE` — CRUD de `dre_management_lines`.
- `MANAGE_DRE_MAPPINGS` — CRUD de `dre_mappings`.
- `MANAGE_DRE_PERIODS` — fechar/reabrir períodos em `dre_period_closings`.
- `VIEW_DRE_PENDING_ACCOUNTS` — ler a tela de pendências.
- `IMPORT_DRE_CHART` — upload do plano de contas.
- `IMPORT_DRE_ACTUALS` — upload de realizados.
- `IMPORT_DRE_BUDGETS` — upload de orçado manual.
- **Renomeadas do que já existe** (rename `ACCOUNTING_CLASSES` → `CHART_OF_ACCOUNTS`): `VIEW_CHART_OF_ACCOUNTS`, `MANAGE_CHART_OF_ACCOUNTS`, `IMPORT_CHART_OF_ACCOUNTS`, `EXPORT_CHART_OF_ACCOUNTS`.

### 8.2 Atribuição aos roles (via `App\Enums\Role::permissions()`)

| Permission | SUPER_ADMIN | ADMIN (financeiro) | SUPPORT | USER |
|---|:-:|:-:|:-:|:-:|
| `VIEW_DRE` | ✓ | ✓ | ✓ | escopo limitado |
| `EXPORT_DRE` | ✓ | ✓ | ✓ | ✗ |
| `MANAGE_DRE_STRUCTURE` | ✓ | ✓ | ✗ | ✗ |
| `MANAGE_DRE_MAPPINGS` | ✓ | ✓ | ✗ | ✗ |
| `MANAGE_DRE_PERIODS` | ✓ | ✓ | ✗ | ✗ |
| `VIEW_DRE_PENDING_ACCOUNTS` | ✓ | ✓ | ✓ | ✗ |
| `IMPORT_DRE_*` | ✓ | ✓ | ✗ | ✗ |

### 8.3 Policies

Consistente com o projeto, **não** criamos Policies (o projeto não usa — o padrão é middleware `permission:PERM1,PERM2`). Se precisarmos de lógica condicional (ex: gerente vê só suas lojas), colocamos um **scope** no `DreMatrixService` lendo `auth()->user()->employee->managed_stores` — mesmo padrão de `Sale::scopeForStoreWithEcommerce`.

Se o prompt original insistir em Policies, cria-se `DrePolicy` com `viewMatrix`, `manageMappings`, `manageLines` — mas é convenção nova no projeto e requer Gate::registerPolicies. Recomendação: **ficar com middleware permission**, consistente com o resto do sistema.

### 8.4 Escopo por loja (relevante quando houver USER com DRE)

- Gerente vê DRE só das lojas onde é `stores.manager_id` + lojas onde é `stores.supervisor_id` (consulta via `Employee → managed_stores`).
- O filtro não é mascarado: a tela de DRE renderiza o seletor de lojas **apenas** com as lojas permitidas.
- Tentativa de forçar `store_id` via query param que o user não pode → 403 no controller.

### 8.5 Registro do módulo

`config/modules.php` ganha entrada nova:

```
'dre' => [
    'slug' => 'dre',
    'name' => 'DRE Financeira',
    'description' => 'Demonstrativo de Resultado do Exercício gerencial.',
    'icon' => 'ChartBarIcon',
    'plan_tiers' => ['pro', 'enterprise'], // ou conforme business
]
```

E entry em `tenant_modules` por tenant ativado (ver memória `module_registration_gotchas.md` — não basta adicionar em `central_modules` sozinho; precisa seed por plano).

Menu sidebar: entrada em `central_menus` via migration (ordem após Budgets).

---

## 9. Frontend

### 9.1 Estrutura de pastas

```
resources/js/Pages/DRE/
  Matrix.jsx                           (principal)
  ChartOfAccounts/
    Index.jsx                          (read-only, tree view)
    Show.jsx                           (detalhe de uma conta)
  ManagementLines/
    Index.jsx
    Edit.jsx                           (modal ou página — ver abaixo)
  Mappings/
    Index.jsx                          (lista + filtros)
    Pending.jsx                        (fila de contas sem mapping)
  Imports/
    Chart.jsx
    Actuals.jsx
    Budgets.jsx
```

### 9.2 Convenções (seguindo descoberta)

- `.jsx`, sem TypeScript, sem PropTypes, sem JSDoc — shape vem do Inertia.
- Filtros via query string (`router.get` com `preserveState: true`).
- `useForm` do Inertia para formulários — sem react-hook-form.
- `DataTable` para listas, `StandardModal` para modais, `StatisticsGrid` para KPIs.
- Ícones Heroicons (`@heroicons/react/24/outline`).
- `recharts` para gráficos.
- Strings PT-BR inline **com acentuação completa** (ver memória `feedback_frontend_accents.md`).
- `useMasks` para formatação BR.

### 9.3 UI da matriz (`DRE/Matrix.jsx`) — tela-núcleo

Layout proposto (resposta #8 — 3 níveis de agregação Geral/Rede/Loja):

```
┌─────────────────────────────────────────────────────────────────────┐
│ Header: "DRE Gerencial" + botão Exportar XLSX + Exportar PDF        │
├─────────────────────────────────────────────────────────────────────┤
│ Nível: ( ) Geral  ( ) Rede  ( ) Loja   ← seletor principal         │
│   > Se "Rede" selecionado, dropdown de redes (multi)               │
│   > Se "Loja" selecionado, dropdown de lojas (multi, respeitando   │
│     permissão do usuário — USER vê só suas lojas)                  │
├─────────────────────────────────────────────────────────────────────┤
│ Filtros (inline, preserveState):                                    │
│   Período [12/2025 → 12/2026]  Orçado [v1 ativo]                   │
│   Comparar com [Ano anterior]                                       │
├─────────────────────────────────────────────────────────────────────┤
│ Banner amarelo: "Janeiro/2026 está fechado" (quando aplicável)      │
├─────────────────────────────────────────────────────────────────────┤
│ StatisticsGrid:                                                     │
│   [Faturamento] [EBITDA %] [Margem Líq.] [Não classificado]         │
├─────────────────────────────────────────────────────────────────────┤
│ Tabs: [Matriz Mensal] [Consolidado Ano] [Gráficos]                  │
├─────────────────────────────────────────────────────────────────────┤
│ Matriz (tabela sticky headers):                                     │
│   Linha             | Jan  | Fev  | ... | Dez  | Total | Orç.  | %  │
│   (+) Fat. Bruto    | ...  | ...  | ... | ...  | ...   | ...   | %  │
│   (-) Deduções      | ...  | ...  | ... | ...  | ...   | ...   | %  │
│   (=) Fat. Líquido  | BOLD | BOLD | ... | ...  | ...   | ...   | %  │  ← subtotal
│   ...                                                                │
│   (!) Não classif.  | 🔴   | 🔴   | ... | ...  | ...   | ...   | %  │  ← destaque quando > 0
└─────────────────────────────────────────────────────────────────────┘
```

**Granularidade × escopo:**
- **Geral:** agrega todas as lojas. Uma única coluna de valor por mês.
- **Rede:** agrega as lojas das redes selecionadas. Uma coluna por rede ou soma única.
- **Loja:** por loja individual. Uma coluna de valor por loja selecionada, ou soma única.

A implementação no backend é um `GROUP BY` extra no `DreMatrixService` conforme o `scope` do filter. A UI alterna o visual mas a query é a mesma estrutura.

**Interações:**
- Click numa célula mensal de linha não-subtotal → abre `StandardModal` com drill async (`GET /dre/matrix/drill?line=...&ym=...`): lista contas analíticas + CC + soma.
- Click no código da linha → drill cascata (se tiver filhos em `level_2..4`).
- Hover mostra variação vs orçado em tooltip.
- Subtotais são visualmente distintos (bold, fundo cinza claro).
- Linha "(!) Não classificado" fica em vermelho quando tem valor.

**Estado:**
- Filtros na URL (query string).
- Expansão de drill em state local.
- Nada de localStorage/sessionStorage (ver prompt).

### 9.4 UI de mapeamento (`DRE/Mappings/Index.jsx`) — ponto mais delicado

**Contexto:** o time financeiro vai mexer aqui **muito**. Ergonomia importa.

**Proposta recomendada: tabela com inline edit**

Razões:
- Consistente com o padrão do projeto (`AccountingClasses/Index.jsx`, `ManagementClasses/Index.jsx` usam CRUD via StandardModal sem drag-drop).
- Com 1.129 contas, drag-and-drop é impraticável.
- Wizard por conta é lento (1.129 cliques para classificar tudo).

**Fluxo:**

1. Lista em `DataTable` com colunas: Conta (code + name), CC (badge ou "—"), Linha DRE (badge), Vigência, Ações.
2. Filtros no topo: busca por conta, busca por linha DRE, toggle "só pendentes", toggle "incluir expirados".
3. Botão primário **"Nova Classificação"** abre `StandardModal` com:
   - Autocomplete de conta (busca por `code` / `reduced_code` / `name`). Só analíticas.
   - Autocomplete de CC opcional. Marca claramente "(deixar vazio = vale para qualquer CC)".
   - Select de Linha DRE (dropdown com as 19 linhas da versão vigente).
   - Datas `effective_from` / `effective_to`.
   - Campo `notes`.
4. Em cada linha da tabela, ações:
   - Editar (abre modal pré-preenchido).
   - Expirar (seta `effective_to = hoje`).
   - Duplicar (copia campos para nova classificação).
5. **Tela "Pendentes" (`/dre/mappings/pending`)** — lista contas analíticas ativas **sem** mapping vigente na versão atual. Cada linha tem botão inline rápido "Classificar agora" que abre o mesmo modal pré-preenchido com a conta selecionada.

**Classificação em lote:** na tela de Pendentes, checkboxes na tabela + botão "Classificar selecionadas" → modal único pedindo linha DRE comum para todas. Útil quando entram 40 contas novas de um import ERP e metade vai na mesma linha. Endpoint `POST /dre/mappings/bulk`.

### 9.5 CRUD das 19 linhas (`DRE/ManagementLines/Index.jsx`)

- `DataTable` ordenada por `sort_order`.
- Colunas: Ordem, Code, Level 1, Nature badge, Is Subtotal badge, Ativa, Ações.
- Reordenar via setas ↑↓ (POST `/dre/management-lines/reorder` com array de ids).
- Criar/editar em `StandardModal`.
- Versão selecionável no topo (dropdown `v2026 | v2027 | ...`). "Clonar versão" cria cópia completa com novo label (ex: clonar `v2026` para começar `v2027`).

### 9.6 Gráficos (`DRE/Matrix.jsx` → tab "Gráficos")

Usando `recharts`:
- BarChart empilhada: receita × despesa × EBITDA por mês (12 barras).
- LineChart: evolução da margem líquida (realizado × orçado × ano anterior).
- PieChart: distribuição de despesas por linha DRE no período.

---

## 10. Versionamento & imutabilidade de períodos (resposta #5 + #6)

**Decisão:** estrutura gerencial única vigente. **Sem** versionamento paralelo de `dre_management_lines` ou `dre_mappings` (resposta #5).

**Garantia de consistência histórica** via `dre_period_closings`:

- Ao fechar um período (ex: Janeiro/2026), `DrePeriodClosingService` cria linha com `closed_up_to_date='2026-01-31'`.
- A partir daí, `DreMappingService` rejeita:
  - `INSERT` de mapping com `effective_from <= 2026-01-31`.
  - `UPDATE` de mapping que altere `effective_from`/`effective_to` para atravessar essa barreira.
  - Soft delete de mapping cujo `effective_from <= 2026-01-31` e está vigente nele.
- `DreActualsImporter` (MANUAL_IMPORT / CIGAM_BALANCE) rejeita `entry_date <= 2026-01-31`.
- Projetores de `OrderPayment` e `Sale` **continuam** funcionando mesmo em período fechado (a fonte é canônica) — mas o valor **não afeta** a leitura histórica porque a matriz lê do snapshot. A projeção em `dre_actuals` fica registrada para auditoria; quando o período for reaberto/refechado, o snapshot é recomputado e a mudança aparece (ver dúvida #23 pendente sobre notificação).
- Reabertura via `POST /dre/periods/{id}/reopen` exige `reopen_reason`, registra no `activity_logs` via `Auditable`, **e apaga os snapshots do período**. Refechar recomputa.

**Evolução natural da estrutura:**
- Precisa reclassificar uma conta? Crie novo `dre_mapping` com `effective_from = hoje + 1` (ou amanhã), e o antigo ganha `effective_to = hoje`. Mapping resolver aplica o correto por data.
- Precisa inserir linha nova na DRE? `INSERT INTO dre_management_lines` com `sort_order` apropriado (gaps de 10 permitem intercalar).
- Precisa inativar linha? `is_active=false` + soft delete. Linha antiga permanece no histórico mas não aparece mais em novos mappings.

**Se eventualmente precisar versionamento de verdade:** migration futura adiciona `version` — mas fora do MVP.

---

## 11. Cronograma de entrega (ordem dos prompts)

| # | Escopo | Dependências | Entregáveis | Nota |
|---|---|---|---|---|
| 1 | Migrations + Models + Factories + Seeders esqueleto | — | 7 tabelas (`chart_of_accounts`, `cost_centers` estendida, `dre_management_lines`, `dre_mappings`, `dre_actuals`, `dre_budgets`, `dre_period_closings`); rename `accounting_classes` → `chart_of_accounts` + backfill de `reduced_code`, `classification_level`, `account_group`, `is_result_account`; seed das 19 linhas + linha-fantasma; FKs em budget_items / management_classes renomeadas. | Fundação. |
| 2 | `ChartOfAccountsImporter` + upload + pré-população de `default_management_class_id` a partir de `Action Plan v1.xlsx` | #1 | `docs/Plano de Contas.xlsx` importa 1.129 contas; `Action Plan v1.xlsx` é lido secundariamente pra popular `default_management_class_id` em ~132 contas; log de contas novas dispara observer | Leitura do Action Plan é defensiva: se o arquivo não existir no caminho esperado, skip sem erro. |
| 3 | `CostCentersImporter` + limpeza dos CCs atuais (421–457) | #1 | Grupo 8 do Excel (8.1.01..8.1.11) vira `cost_centers`; migration remove CCs legados que não são departamentais | Decisão a tomar: manter `code=421..457` como histórico soft-deleted ou deletar de vez. |
| 4 | CRUD linhas DRE (`DRE/ManagementLines`) | #1 | Página + Controller + FormRequest + tests. **CFO completa as 19 linhas via UI aqui** — o seed entrega 16 linhas DRE-BR, o CFO insere Headcount/Marketing/EBITDA/Lucro s/ Cedro. | Destrava opção C do seed. |
| 5 | CRUD mapeamentos + tela de Pendências + bulk + botão "Usar sugestão" quando `default_management_class_id` existe | #1, #4 | `DreMappingService`, `UnmappedAccountsFinder`, `/dre/mappings/*`, 3 páginas React | UI do cadastro. "Usar sugestão" pula autocomplete para contas que vieram pré-mapeadas do Action Plan. |
| 6 | `DreMatrixService` + `DreMappingResolver` + `DreSubtotalCalculator` + testes unitários | #1, #5 | Núcleo. Testes de precedência, subtotais, fantasma, scope Geral/Rede/Loja. | Coração do módulo. |
| 7 | Controller HTTP da matriz + stub da página React | #6 | `/dre/matrix` responde JSON com dados mock; página renderiza tabela vazia com seletor Geral/Rede/Loja | |
| 8 | `OrderPaymentToDreProjector` + `SaleToDreProjector` + observers + rebuild command | #1 | Observers ativos; `dre:rebuild-actuals` funcionando; tests verificam projeção 1:1 | **Nova etapa** — integração com OrderPayment. |
| 9 | Frontend completo da matriz + drill-through para `OrderPayment`/`Sale` + filtros + exportação | #7, #8 | `Matrix.jsx` funcional com dados reais; click em célula abre drill com link pra fonte | |
| 10 | `DreActualsImporter` (balancete manual) + `DreBudgetsImporter` + `BudgetToDreProjector` | #6 | Carga de realizados/orçados manuais; observer em `BudgetUpload` ativo | |
| 10.5 | Importar `Action Plan v1.xlsx` como primeiro `dre_budgets` 2026 | #10 | Command `dre:import-action-plan --file=...` lê 3861 linhas × loja × conta × mês, popula `dre_budgets` com `budget_version='action_plan_v1'`; idempotente (upsert por conjunto `entry_date+chart_of_account_id+cost_center_id+store_id+budget_version`). | Arquivo é input único; quando chegar Action Plan v2, é só rodar de novo com nova version label. |
| 11 | `dre_period_closings` + `dre_period_closing_snapshots` + `DrePeriodClosingService` (close/reopen com snapshot) + enforcement em `DreMappingService` / `DreActualsImporter` + `DreMatrixService` lendo snapshot em período fechado | #5, #10 | Tela `/dre/periods`; testes de bloqueio retroativo + imutabilidade via snapshot | **Nova etapa** — fechamento de períodos com snapshot (resposta #25). |
| 12 | Cache (`dre:cache_version` + warm-up command) + schedule | #9 | `dre:warm-cache` agendado às 05:50 | |
| 13 | StatisticsGrid de KPIs + gráficos + polimento + export XLSX/PDF | #9 | Tab "Gráficos", StatisticsGrid com Fat/EBITDA/Margem/Não Classif. | |
| 14 | Tests de integração ponta-a-ponta + seed realista para ambiente local | todos | | |

Cada prompt entrega algo rodando + tests verdes.

---

## 12. Riscos, decisões em aberto e dúvidas para o negócio

### 12.1 Bloqueantes antes do prompt #1

1. **As 11 linhas ausentes da DRE executiva** — o prompt lista 1, 2, 3, 4, 5, 13, 17, 19. Faltam 6, 7, 8, 9, 10, 11, 12, 14, 15, 16, 18. Precisamos da lista completa com: ordem, label PT-BR exato, is_subtotal, nature (revenue/expense/subtotal), e qual `accumulate_until_sort_order` cada subtotal usa. Fonte sugerida: PBIX original ou planilha da DRE publicada internamente.
Vamos usar planilha da DRE publicada internamente.

### 12.2 Decisões técnicas assumidas (precisam de ok explícito)

2. **Rename `accounting_classes` → `chart_of_accounts`** — impacto em 3 FKs (`budget_items`, `management_classes`, `cost_centers.default_accounting_class_id`) + permissions + rotas. Breaking change interno. Quem aprova: líder técnico.
Se as mudanças forem essenciais, pode renomear. 

3. **Limpeza dos CCs legados (421–457)** — esses códigos são na verdade `stores.code`, não CCs departamentais. Plano: soft delete das 24 linhas atuais e import dos 11 CCs do grupo 8 do Excel. Precisa conferir se algum `budget_item` ou `management_class` aponta para esses `cost_center_id` atuais — se sim, precisa migração antes.
Sim, vamos seguir o arquivo.

4. **Convenção de sinal `amount` sinalizado** — toda a app vai tratar despesa como negativa. Se o importador/projetor errar, subtotais ficam invertidos. Documentar no `OrderPaymentToDreProjector` + `DreActualsImporter` + testes cobrindo os 5 `account_group`.
Sim

5. **Cache invalidation bruta via `dre:cache_version++`** — qualquer mudança em mapping/linha/entry invalida a cache inteira do DRE. Simples, funciona em qualquer driver, mas pode causar pico de miss após grande import. Aceitável porque warm-up cobre o caso principal.
Sim

6. **Linha-fantasma "Não classificado"** — seed + UI em vermelho. Confirmar que é comportamento aceito (em vez de silenciar).
Aceitável

7. **Subpasta `App\Services\DRE\`** — primeira vez no projeto. Se líder técnico preferir manter flat (consistência), aceita-se; só fica classes prefixadas `Dre*`.
Podemos usar subpastas

8. **DTOs** — recomendação: **não criar** formalmente. Filter como `FormRequest` + array associativo tipado. Se líder quiser DTOs, precisamos abrir precedente documentado.
Vamos seguir a recomendação.

9. **Policies vs middleware `permission:`** — recomendação: seguir o padrão do projeto (middleware). O prompt pede Policies; aqui argumentamos contra por consistência.
Vamos seguir o padrão.

### 12.3 Riscos de implementação

10. **Volume de `dre_actuals`** — projeção realista: ~20k OrderPayments/ano projetados + Sales diárias × 24 lojas × 2 anos ≈ 60k–200k linhas. Muito menor que o pior caso teórico. Índices compostos listados em §2.5 cobrem. Monitorar ao longo do tempo.

11. **Import do XLSX de 1.129 contas** — Maatwebsite faz parse chunked via `WithChunkReading`. Tempo estimado < 30 s. Upsert por `reduced_code`. Precisa transação + rollback em erro. Idempotência: reimportar o mesmo arquivo não cria duplicatas.

12. **Observer de `OrderPayment` × performance** — quando DP/Fiscal aprova 200 OPs em lote, o observer dispara 200× a projeção. Risco de degradação. Mitigação: observer usa `DB::transaction` e faz upsert em 1 statement por OP. Se ficar lento, migrar pra `ShouldQueue` (job assíncrono).

13. **Drift entre `OrderPayment` e `dre_actuals`** — se o observer falhar silenciosamente (exception capturada + log), a matriz fica inconsistente. Mitigação: `dre:rebuild-actuals --source=ORDER_PAYMENT` roda agendado semanal (ex: domingos 03:00) como reconciliação defensiva.

14. **Resolver de mapping em memória** — projeção realista de 500–1500 mappings ativos em DRE. Cabe em RAM; caminho `O(1)` após hash. Sem risco.

15. **Evolução futura: consolidação multi-empresa** — CNPJ Cedro (linha 19 "Lucro Líquido s/ Cedro") sugere consolidação. Se a empresa tem 2+ CNPJs, `dre_actuals` precisa de `legal_entity_id`. **Não no MVP**, mas é a primeira extensão a planejar.

16. **Ano anterior automático** — a coluna "Ano Anterior" da matriz precisa de `dre_actuals` populado para o mesmo período — 1 ano atrás. Se não houver, a UI mostra "—" (não fingir zero). Validar UX com o CFO.

### 12.4 Dúvidas de produto

17. **Conta analítica padrão para `Sale`** — ✅ **Resolvido: opção (b) — configurável por loja.**

    Implementação:
    - Coluna nova em `stores`: `sale_chart_of_account_id` (foreignId NULL) — conta analítica de receita de venda para a loja.
    - `SaleToDreProjector` lê `sale->store->sale_chart_of_account_id`. Se null, cai num fallback configurado (ex: `3.1.1.01.00001` VENDAS A VISTA) — evita quebra se o cadastro da loja estiver incompleto.
    - UI: campo no formulário de Store (provavelmente em `/stores/{id}/edit`), com autocomplete do plano de contas filtrado por `account_group=3` (Receitas).
    - Validação: só permite apontar para conta analítica (`type='analytical'`) e do grupo Receitas.
    - Seed inicial: todas as lojas existentes apontam pro fallback, até o CFO configurar individualmente.
    - Command `dre:rebuild-actuals --source=SALE` reprojeta tudo se a configuração mudar.

18. **Despesas corporativas sem loja** — `OrderPayment.store_id` é nullable? Rapid check: é fillable, e há registros sem loja? Se sim, `dre_actuals.store_id` também nullable (já proposto assim). Confirmar padrão de dados atual com o time DP/Fiscal.
Sim, existem algumas despesas corporativas sem loja.

19. **Ciclo de aprovação contábil** — há aprovação formal de diretoria antes de fechar período? O `DrePeriodClosingService` precisa workflow multi-step (solicitar → aprovar → fechar), ou é um toggle direto do ADMIN financeiro? **MVP:** toggle direto. Workflow fica pra v2.
Atualmente não tem, mas é possível que futuramente tenha.

20. **Moeda** — tudo em BRL. Alguma operação em outra moeda? Se sim, `currency` entra em `dre_actuals`/`dre_budgets` + taxa de conversão. MVP **assume BRL** apenas.
Todos os valores são BRL.

21. **Audit log** — mudanças em `dre_mappings`, `dre_management_lines` e `dre_period_closings` são sensíveis. Aplicar trait `Auditable` (já existe no projeto, registra diff em `activity_logs`) nesses 3 models + `ChartOfAccount` quando editado manualmente. Não em `DreActual` (imutável) nem `DreBudget` (imutável por design — editar = nova versão).
Manter o padrão atual

22. **Export do XLSX/PDF** — formato alinhado ao que o CFO hoje usa (PBIX ou planilha manual). Precisamos de amostra do formato esperado antes de implementar.
Planilha

23. **Projeção de `Sale` em período fechado** — se uma venda em `2026-01-15` for criada hoje (após fechamento de janeiro), o observer projeta em `dre_actuals` em período fechado? Duas opções:
    - (a) Sim, sempre projeta — a fonte é canônica. Relatório fica atualizado, mas fechamento "quebra".
    - (b) Não, projeta com `entry_date = hoje` (primeiro dia útil após reabertura) — preserva fechamento mas distorce competência.
    - (c) Bloqueia a criação do Sale retroativo.
    
    Recomendação MVP: **(a) — sempre projeta**, com notificação (toast/email) ao ADMIN financeiro informando que houve movimento em período fechado, pedindo revisão. Validar com CFO.

24. **Pendências de mapping — workflow** — a tela de Pendências lista contas analíticas sem mapping vigente. O XLSX traz 1.129 contas, mas nem todas são analíticas (só as que aparecem na apresentação contábil — ~80% tipicamente). A fila de pendências inicial pode ter várias centenas. Precisamos: (a) opção "ignorar esta conta" (marca `chart_of_accounts.is_active=false` — some da fila sem virar mapping fantasma); (b) atribuição bulk por grupo (ex: "todas as contas do grupo 3.1.1 vão pra linha DRE 1"). Confirmar com o time financeiro.
Contas não mapeadas precisam ser identificadas, inicialmente mapping fantasma, mas com necessidade de identificação.

25. **Snapshot de fechamento** — §2.8 propõe que o MVP **não** snapshotee valores no fechamento (computa live sempre). Implica: se alguém mexer no mapping retroativo via bypass (super admin, correção manual via DB), o valor de período fechado muda. Aceitável para MVP, mas validar com auditoria/compliance.
É preciso manter os valores de períodos fechados imutáveis, mas ter a possibilidade de atualizar.

---

## 13. Resumo executivo

**Revisões após respostas do usuário:**
- Removido versionamento (`version` em `dre_management_lines` e `dre_mappings`) — resposta #5.
- Adicionado `dre_period_closings` — resposta #6.
- `dre_actuals` agora é espelho canônico alimentado por projetores de `OrderPayment` (fonte principal) + `Sale` + import manual — resposta #2.
- Filtros da matriz cobrem 3 escopos: Geral / Rede / Loja — resposta #8.
- MySQL 8+ confirmado — resposta #7 (mas Opção B do SQL mantém-se como recomendada).

**Tabelas:**
- **8 tabelas novas/alteradas**: `chart_of_accounts` (rename+extend de `accounting_classes`), `cost_centers` (extend + limpeza dos 421–457 legados), `dre_management_lines`, `dre_mappings`, `dre_actuals`, `dre_budgets`, `dre_period_closings`, `dre_period_closing_snapshots`.
- Ponte `budget_items` → `dre_budgets` via `BudgetToDreProjector`.
- Ponte `OrderPayment` → `dre_actuals` via `OrderPaymentToDreProjector` (observer).
- Ponte `Sale` → `dre_actuals` via `SaleToDreProjector` (observer).

**Código:**
- **12 Services** em `App\Services\DRE\` (subpasta nova — decisão #7 de §12.2).
- **7 controllers** na raiz de `Http/Controllers/` (padrão do projeto).
- **12 permissions novas** (inclui `MANAGE_DRE_PERIODS`) + 4 renomeadas.
- **9+ páginas React** em `Pages/DRE/` — `Matrix.jsx` é a estrela, mais `/periods` para fechamento.
- **Estratégia SQL: Opção B** (resolver em PHP com mapping em RAM + 2 queries simples) — testável, paridade SQLite-in-memory nos tests.
- **Cache por version key** (`dre:cache_version`) — compatível com driver database.
- **14 prompts** de entrega sequencial, cada um com tests verdes.

**Status das pendências após respostas do usuário (§12): 25 de 25 fechadas.** ✅

Decisões-chave:
- **19 linhas DRE** — seed #1 entrega 16 linhas DRE-BR (enum `DreGroup`); CFO insere Headcount/Marketing/EBITDA/Lucro s/ Cedro via UI após prompt #4 (opção C).
- **Rename** `accounting_classes` → `chart_of_accounts` — aprovado.
- **CCs legados 421–457** — soft delete + import dos 11 departamentais do Excel.
- **Conta de venda** — por loja (`stores.sale_chart_of_account_id`) + fallback global.
- **Snapshot de fechamento** — entra no MVP (`dre_period_closing_snapshots`).
- **Notificação em projeção em período fechado (#23)** — **relatório consolidado na reabertura** (flag `dre_actuals.reported_in_closed_period` + comparação snapshot × live + e-mail ao MANAGE_DRE_PERIODS + badge sutil na matriz). Zero ruído operacional.
- **`Action Plan v1.xlsx`** — 3 usos: (1) primeiro `dre_budgets` 2026 via prompt #10.5; (2) pré-popular `chart_of_accounts.default_management_class_id`; (3) alimentar botão "Usar sugestão" na tela de Pendências.

**Nada foi codado. O prompt #1 pode começar.**
