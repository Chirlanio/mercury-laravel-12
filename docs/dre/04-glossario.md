# 04 — Glossário DRE

> Termos usados na documentação e no produto, em ordem alfabética.
> 🟢 = básico (qualquer usuário) · 🟡 = intermediário (admin) · 🔴 = técnico (dev)

Atalhos por categoria:

- [Conceitos contábeis](#conceitos-contábeis)
- [Estrutura DRE](#estrutura-da-dre)
- [Origens de dados](#origens-de-dados)
- [Operação](#operação)
- [Técnico](#técnico)

---

## A

### 🔴 `account_group`

Inteiro de 1 a 5 que identifica a natureza contábil de uma conta:
**1=Ativo, 2=Passivo, 3=Receita, 4=Despesa, 5=Custo**. Determina o
[sinal](#sinal) aplicado pelos projetores. Contas de grupo 1 e 2 não
podem aparecer na DRE — projetores rejeitam com `DomainException`.

### 🟡 Analítica

Conta contábil que **recebe lançamentos diretamente**. Folha da árvore.
Contraposto a [sintética](#sintética). Apenas analíticas podem ter
[mapping](#mapping) e aparecer em `dre_actuals` / `dre_budgets`.

### 🟡 Audit log

Registro automático de quem mudou o quê e quando. Habilitado nos models
DRE relevantes (`DreManagementLine`, `DreMapping`, `BudgetUpload`,
`BudgetItem`) via trait `Auditable`. Visível em **Configurações → Logs de
atividade** (`/activity-logs`). Filtre por `model_type` para ver só DRE.

---

## B

### 🟡 BulkAssign

Ferramenta no `/dre/mappings` que permite **atribuir várias contas a uma
mesma linha DRE de uma vez**. Selecione N contas, escolha 1 linha, aplica.
Útil no setup inicial (mapear todas as `4.2.*` para "Despesas
Administrativas" em um clique).

### 🟢 Budget Item

Cada linha do orçamento — **uma conta + centro de custo + 12 valores
mensais**. Várias linhas formam um Budget Upload.

### 🟢 Budget Upload

**Cabeçalho de uma versão de orçamento**. Tem ano, escopo, versão (1.0,
1.01, 2.0…), tipo (NOVO ou AJUSTE) e flag `is_active`. Uma única versão
ativa por (ano + escopo). Detalhes em
[Manual de Orçamentos](../budgets/02-administrador.md).

---

## C

### 🔴 Cache (DRE)

Implementação `Cache::store('file')` (driver `file`) com TTL de 600 segundos
(10 minutos). Chave: `dre:matrix:v{version}:{md5(filter)}`. Invalidação
automática via trait `InvalidatesDreCacheOnChange` em models que afetam a
matriz. Warm-up diário às 05:50 via `dre:warm-cache`.

### 🟢 Centro de Custo (CC)

Unidade operacional que recebe gasto: **uma loja, um departamento, uma
área**. No Mercury, tabela `cost_centers`. Para Meia Sola: 24 CCs com
código numérico de 3 dígitos (`421` a `457`). Convenção: cada loja tem CC
de mesmo nome (sem prefixo `Z`).

### 🟢 CIGAM

ERP da empresa que alimenta o Mercury com **vendas** (tabela
`movements`, source de `Sale`) e o **plano de contas oficial**. Sincronização
configurada via `CIGAM_DB_*` em `.env`. Para detalhes do schema CIGAM, veja
`docs/movements_module.md` e a memória `accounting_real_chart.md`.

### 🟢 CMV — Custo da Mercadoria Vendida

Linha da DRE que representa o **custo das mercadorias** que geraram a
receita do período. Sai negativo. No Mercury, vem geralmente do módulo
Order Payments (compra de produto) e do CIGAM (movimento de saída por
venda).

### 🟡 Coringa (mapping)

[Mapping](#mapping) com `cost_center_id = NULL` — vale para **qualquer
centro de custo**. Caso comum (uma conta vai sempre para a mesma linha,
independente do CC). Contraposto a [específico](#específico).

---

## D

### 🟢 De-para DRE

Veja [Mapping](#mapping). Termo informal para a tabela `dre_mappings`,
usado no menu da aplicação.

### 🔴 DomainException (DRE)

Exceção PHP lançada por projetores quando regra de negócio é violada:

- Conta de [account_group](#account_group) 1 ou 2 sendo projetada
- Conta sintética sendo lançada
- (etc.)

Aparece em `storage/logs/laravel-YYYY-MM-DD.log`. Veja
[02 — Troubleshooting](02-administrador.md#8-troubleshooting).

### 🟢 DRE Contábil × DRE Gerencial

| | **Contábil** | **Gerencial** |
|---|---|---|
| Para quem? | Fisco, contador externo | Diretoria, sócios, gerentes |
| Linhas | Definidas pelo CFC (Conselho Federal de Contabilidade) | Definidas pela empresa (no Mercury: 20 linhas executivas) |
| Frequência | Mensal/anual oficial | Mensal/diária para gestão |
| Onde? | Sistema contábil (escritório) | Mercury (`/dre/matrix`) |

A DRE Gerencial **usa o mesmo plano de contas** da contábil, mas reorganiza
em linhas diferentes via [mappings](#mapping).

### 🟢 Drill

Recurso de **clicar numa célula da matriz** e ver os lançamentos individuais
que formaram aquele número. Mostra data, loja, conta, CC, documento,
descrição. Não disponível em linhas de [subtotal](#subtotal).

---

## E

### 🟢 EBITDA

*Earnings Before Interest, Taxes, Depreciation and Amortization* — Lucro
**antes** de juros, impostos, depreciação e amortização. Subtotal típico
da DRE executiva. Mede a **rentabilidade operacional pura**.

### 🔴 effective_from / effective_to

Campos de data em `dre_mappings` que definem a **vigência** do mapping.
`effective_from` é obrigatório (data de início). `effective_to` é opcional
(NULL = vigente sem fim). Permite reorganizar a DRE no meio do ano sem
reescrever histórico.

### 🟡 Específico (mapping)

[Mapping](#mapping) com `chart_of_account` **e** `cost_center` definidos —
vale só para essa combinação. Tem precedência sobre o [coringa](#coringa).
Use quando uma conta se comporta diferente em CCs diferentes.

### 🟢 Escopo

Granularidade de leitura da DRE: **Geral** (consolidado), **Rede**
(agrupamento de lojas), **Loja** (uma loja específica). Filtro no topo da
matriz.

### 🟡 external_id

Campo opcional em imports manuais de [realizado](#realizado). Quando
presente, faz **dedup** — re-importar o mesmo `external_id` substitui a
linha anterior. Use para imports recorrentes (depreciação mensal:
`DEPREC-2026-04-LOJA-Z421`).

---

## F

### 🟢 Fechamento (de período)

Ato de **declarar que tudo até a data X está auditado e congelado**.
Cria um [snapshot](#snapshot) imutável. Lançamentos retroativos continuam
sendo gravados, mas a matriz fechada não muda visualmente. Veja
[02 — Rotina mensal](02-administrador.md#3-rotina-mensal-de-fechamento).

---

## L

### 🟢 L01, L02… L99

Códigos das **20 linhas executivas** da DRE Gerencial. `L01` = primeira
linha (Receita Bruta), `L99_UNCLASSIFIED` = linha-fantasma para fallback.
Ver [Linha executiva](#linha-executiva).

### 🟢 L99 — Não Classificado

Linha-fantasma da DRE. Recebe lançamentos cujo
[mapping](#mapping) não foi encontrado. Aparece em **vermelho** na matriz.
Idealmente fica vazia. Ver `/dre/mappings/unmapped` para listar contas que
estão caindo aqui.

### 🟢 Lançamento

Termo genérico para "uma linha em `dre_actuals` ou `dre_budgets`". Cada
lançamento tem data, loja, conta, CC, valor e origem.

### 🟢 Linha Executiva

Cada uma das **20 linhas fixas da DRE Gerencial**: Receita Bruta, (-)
Devoluções, Receita Líquida, (-) CMV, Lucro Bruto, etc. Tabela
`dre_management_lines`. Imutáveis durante operação (renomear OK; mudar
`code` quebra histórico).

### 🟢 Lucro Bruto

Subtotal da DRE: Receita Líquida menos CMV. Mede **margem de produto**
antes de despesas operacionais.

### 🟢 Lucro Líquido

Subtotal final da DRE: o que sobra **depois de tudo** (receita líquida,
custos, despesas operacionais, financeiras, IR/CSLL). É o "resultado" do
período.

### 🟢 Lucro Operacional

Subtotal: Lucro Bruto menos despesas operacionais (administrativas,
comerciais, pessoal, ocupação) menos depreciação. Mede a **eficiência
operacional**.

---

## M

### 🟡 Management Class (Plano Gerencial)

Vocabulário **interno operacional** ("Salário PJ", "Aluguel Loja",
"Propaganda Mídia Digital"). É um **bridge opcional** entre
[Centro de Custo](#centro-de-custo-cc) e [Plano de Contas](#plano-de-contas).
**Não é consumido pelo motor da DRE** — quem classifica a linha é o
[mapping](#mapping). Para Meia Sola: 169 classes no formato `8.1.DD.UU`.

### 🟢 Mapping

Vínculo `(conta contábil + CC opcional) → linha DRE`, com vigência
(`effective_from/to`). Tabela `dre_mappings`. **Sem mapping, conta cai em
L99.** Pode ser [específico](#específico) ou [coringa](#coringa).

### 🔴 MANUAL_IMPORT

Valor do enum `dre_actuals.source`. Identifica lançamentos que vieram do
**importador manual** (XLSX via `/dre/imports/actuals` ou
`dre:import-actuals`). Não tem `source_id` (origem é arquivo externo).

### 🟢 Margem (Bruta / EBITDA / Líquida)

Percentual = subtotal ÷ Receita Líquida × 100. Apresentado na aba
**Gráficos** da matriz. Mede eficiência:

- Margem Bruta → eficiência de produto
- Margem EBITDA → eficiência operacional
- Margem Líquida → eficiência total

### 🟢 Matriz DRE

A tela principal do módulo. Linhas (categorias) × colunas (períodos), com
realizado, orçado e variação em cada célula. Tela em `/dre/matrix`.

### 🟡 Movement

Tabela `movements` — **registros brutos do CIGAM** (compras, vendas,
devoluções, transferências). É a **fonte** das vendas que viram `Sale`,
que por sua vez viram lançamentos `dre_actuals` source `SALE`. **A DRE
não lê movements diretamente.**

---

## O

### 🔴 ORDER_PAYMENT

Valor do enum `dre_actuals.source`. Identifica lançamentos que vieram do
módulo **Order Payments** (despesas registradas no sistema). Tem
`source_id = order_payment.id` para rastreabilidade.

### 🟢 Order Payment (OP)

Módulo do Mercury para registro de **despesas e contas a pagar**. Cada OP
salva dispara o `OrderPaymentDreObserver`, que projeta a despesa em
`dre_actuals` via `OrderPaymentToDreProjector`. Detalhes em
`/order-payments`.

### 🟢 Orçado

A coluna da matriz que mostra o **valor planejado**. Vem de `dre_budgets`,
populada pelo módulo [Orçamentos](#orçamentos-budgets).

### 🟢 Orçamentos (Budgets)

Módulo Mercury para gestão de **orçamento anual** por escopo. Cada upload
gera uma versão (1.0, 1.01, 2.0…). Quando `is_active=true`, alimenta
`dre_budgets` automaticamente. Detalhes em
[Manual de Orçamentos](../budgets/README.md).

---

## P

### 🟢 Período aberto / fechado

- **Aberto**: aceita novos lançamentos, matriz é computada live.
- **Fechado**: tem [snapshot](#snapshot) ativo; matriz mostra valores
  congelados; novos lançamentos manuais são rejeitados na data desse
  período.

### 🟢 Plano de Contas

O cadastro contábil oficial: cada conta tem código (`X.X.X.XX.XXXXX`),
nome, [account_group](#account_group), tipo ([analítica](#analítica) ou
[sintética](#sintética)), hierarquia. Para Meia Sola: 839 contas reais
importadas do CIGAM. Tabela `chart_of_accounts`.

### 🟢 Plano Gerencial

Veja [Management Class](#management-class-plano-gerencial).

### 🟢 Plano Gerencial DRE

Termo do menu para o cadastro das **20 linhas executivas** (`dre_management_lines`).
Não confundir com [Management Class](#management-class-plano-gerencial)
(que tem nome parecido mas papel diferente).

### 🔴 Projetor (Projector)

Classe service que **lê uma entidade origem** (Sale, OrderPayment,
BudgetUpload) e **grava lançamentos** em `dre_actuals` ou `dre_budgets`.
Idempotente (delete-then-insert por `source_id` ou `budget_upload_id`).
Disparados por observers no save da entidade origem.

---

## R

### 🟢 Realizado

A coluna da matriz que mostra o **valor que aconteceu**. Vem de
`dre_actuals` somando lançamentos das 4 sources (SALE, ORDER_PAYMENT,
MANUAL_IMPORT, CIGAM_BALANCE).

### 🟡 Rebuild

Operação de **reprojetar todos os lançamentos** de uma source. Roda via
`dre:rebuild-actuals [--source=...]`. Idempotente. Use após corrigir bug em
projetor ou mudar mapping massivamente.

### 🟢 Reabertura (Reopen)

Reverter um [fechamento](#fechamento-de-período). Rara e auditada — exige
justificativa de no mínimo 10 caracteres. Notifica todos com
`dre.manage_periods`. Apaga snapshots e volta a computar live. Veja
[02 — Como reabrir](02-administrador.md#34-como-reabrir).

### 🟢 Rede

Agrupamento de lojas (`networks` no schema). Para Meia Sola, redes
incluem "Comercial", "E-commerce" (Z441), "Outlet" etc. Filtro de
[escopo](#escopo) na matriz.

---

## S

### 🔴 SALE

Valor do enum `dre_actuals.source`. Identifica lançamentos que vieram da
projeção da entidade `Sale` (vendas sincronizadas do CIGAM). Tem
`source_id = sale.id` para rastreabilidade.

### 🟢 Sale

Entidade que representa **uma venda agregada** (já consolidada do
CIGAM). Cada `Sale` salva dispara `SaleDreObserver` que projeta em
`dre_actuals`. Lê de `movements` (CIGAM raw).

### 🟡 Scope label

Identificador lógico do escopo de um Budget Upload — "Administrativo",
"TI", "Geral". Usado para [superseding](#superseding) (uma versão ativa
por `(year, scope_label)`).

### 🟢 Sinal

Convenção: lançamentos são gravados em `dre_actuals` e `dre_budgets`
**com sinal aplicado** baseado em [account_group](#account_group):

- Grupo 3 (Receita) → mantém positivo
- Grupo 4/5 (Despesa/Custo) → inverte para negativo
- Grupo 1/2 (Ativo/Passivo) → rejeita com [DomainException](#domainexception-dre)

Quando você importa via XLSX, envie sempre **valor absoluto positivo**;
sistema aplica o sinal.

### 🟡 Sintética

Conta contábil **agrupadora** — não recebe lançamentos. Existe só para
totalização visual. Contraposto a [analítica](#analítica). Mappings
**rejeitam** sintéticas.

### 🟡 Snapshot

Valores **imutáveis** gravados em `dre_period_closing_snapshots` no
momento do [fechamento](#fechamento-de-período). Granularidade:
`(closing_id, year_month, dre_management_line_id, scope)`. O reader
sobrepõe esses valores no compute live → matriz fechada não muda
visualmente.

### 🔴 source

Coluna em `dre_actuals` que identifica de onde veio o lançamento.
Quatro valores: `SALE`, `ORDER_PAYMENT`, `MANUAL_IMPORT`, `CIGAM_BALANCE`.
Determina qual projetor o gerou (e se pode ser reprojetado).

### 🟢 Subtotal

Linha da DRE que **soma** linhas anteriores até um marco
(`accumulate_until_sort_order`). Exemplos: Receita Líquida, Lucro Bruto,
EBITDA, Lucro Líquido. Aparece em **negrito** na matriz. Sem [drill](#drill).

### 🟡 Superseding (Budgets)

Regra do módulo Orçamentos: ao ativar uma versão (`is_active=true`),
o sistema **desativa automaticamente** qualquer versão anterior do mesmo
`(year, scope_label)`. Em `dre_budgets`, apaga as linhas da versão
anterior e insere as novas. Garante que **só uma versão alimenta a DRE
por vez**.

---

## T

### 🟡 Tenant

Cliente do SaaS. Cada tenant tem seu próprio banco de dados (multi-tenancy
via `stancl/tenancy`). Para Meia Sola: tenant `meia-sola` com banco
`mercury_meia-sola`. Acessado via subdomínio.

### 🔴 Trait `Auditable`

Trait Laravel aplicado em models para registrar mudanças automaticamente
no audit log. Models DRE com Auditable: `DreManagementLine`, `DreMapping`,
`BudgetUpload`, `BudgetItem`.

### 🔴 Trait `InvalidatesDreCacheOnChange`

Trait que **invalida o cache da matriz DRE** sempre que o model muda.
Aplicado em: `DreActual`, `DreBudget`, `DreManagementLine`, `DreMapping`,
`BudgetUpload`. Garante que mudanças em qualquer um desses recalculam a
matriz no próximo acesso.

---

## V

### 🟢 Variação

Diferença entre **realizado** e **orçado**. Apresentada em R$ e %. Cor:
verde se favorável (receita acima ou despesa abaixo do orçado), vermelho
se desfavorável.

### 🟢 Versão (Budget)

Identificador da versão de orçamento: "1.0" (NOVO), "1.01" (AJUSTE
incremental), "2.0" (NOVO seguinte), etc. Determinada por
`BudgetVersionService.resolveNextVersion()` — NOVO incrementa major,
AJUSTE incrementa minor.

---

## W

### 🟡 Warm-up cache

Pré-computação da matriz DRE feita pelo command `dre:warm-cache`,
agendado para diariamente às **05:50**. Garante que o primeiro acesso da
manhã abra a matriz instantaneamente. Pode ser rodado manualmente quando
necessário.

---

## Z

### 🟢 Z421, Z425, Z441…

Códigos das **lojas Meia Sola**. Convenção: prefixo `Z` + 3 dígitos.
`Z441` é a loja de **e-commerce**. Cada loja tem seu próprio
[Centro de Custo](#centro-de-custo-cc) (sem o prefixo `Z`).

---

## Categorias

### Conceitos contábeis

[account_group](#account_group), [Analítica](#analítica),
[CMV](#cmv--custo-da-mercadoria-vendida), [DRE Contábil × Gerencial](#dre-contábil--dre-gerencial),
[EBITDA](#ebitda), [Lucro Bruto](#lucro-bruto), [Lucro Líquido](#lucro-líquido),
[Lucro Operacional](#lucro-operacional), [Margem](#margem-bruta--ebitda--líquida),
[Plano de Contas](#plano-de-contas), [Sinal](#sinal),
[Sintética](#sintética).

### Estrutura DRE

[Centro de Custo (CC)](#centro-de-custo-cc), [Coringa](#coringa-mapping),
[De-para DRE](#de-para-dre), [Específico](#específico-mapping),
[L01..L99](#l01-l02-l99), [L99](#l99--não-classificado),
[Linha Executiva](#linha-executiva), [Management Class](#management-class-plano-gerencial),
[Mapping](#mapping), [Matriz DRE](#matriz-dre),
[Plano Gerencial DRE](#plano-gerencial-dre), [Subtotal](#subtotal).

### Origens de dados

[CIGAM](#cigam), [MANUAL_IMPORT](#manual_import), [Movement](#movement),
[ORDER_PAYMENT](#order_payment), [Order Payment (OP)](#order-payment-op),
[Orçamentos](#orçamentos-budgets), [Projetor](#projetor-projector),
[SALE](#sale-1), [Sale](#sale), [source](#source), [Realizado](#realizado),
[Orçado](#orçado).

### Operação

[BulkAssign](#bulkassign), [Drill](#drill),
[effective_from / effective_to](#effective_from--effective_to),
[Escopo](#escopo), [external_id](#external_id), [Fechamento](#fechamento-de-período),
[Período aberto / fechado](#período-aberto--fechado), [Reabertura](#reabertura-reopen),
[Rebuild](#rebuild), [Snapshot](#snapshot), [Superseding](#superseding-budgets),
[Variação](#variação).

### Técnico

[Audit log](#audit-log), [Cache](#cache-dre), [DomainException](#domainexception-dre),
[Tenant](#tenant), [Trait Auditable](#trait-auditable),
[Trait InvalidatesDreCacheOnChange](#trait-invalidatesdrecacheonchange),
[Warm-up cache](#warm-up-cache).

---

> **Última atualização:** 2026-04-22
