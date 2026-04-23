# Análise do Módulo Consignações (v1 → v2)

**Projeto:** Mercury Laravel — Grupo Meia Sola
**Data:** 2026-04-23
**Autor:** Chirlanio + Claude (Opus 4.7)
**Status:** Análise de escopo (pré-implementação)

> Base v1: `C:\wamp64\www\mercury\app\adms\**` + dump `u401878354_meiaso26_bd_me.sql`
> Base v2: `C:\xampp\htdocs\mercury-laravel\**` (Laravel 12 + React 18 + Inertia 2)

---

## 1. Conceito de negócio (regra definida pelo usuário)

Consignação é o processo em que a **loja envia produtos para um terceiro** (cliente, influenciador, estúdio de fotos, e-commerce) com um **prazo de retorno**. Durante a vigência, o receptor pode **vender** parte dos itens; ao final do prazo, o que **não foi vendido deve retornar fisicamente** à loja.

O fluxo exige duas notas fiscais:

1. **Nota de saída (remessa)** — emitida quando os produtos saem da loja. Lista obrigatoriamente os itens consignados (referência, tamanho, quantidade, valor).
2. **Nota de retorno** — emitida quando os produtos retornam. **Regra dura:** a nota de retorno precisa conter **os mesmos itens da nota de saída** (o que saiu precisa voltar — seja na forma de produto físico devolvido, seja abatido por venda registrada separadamente).

No sistema CIGAM (ERP de origem) essas notas existem como movimentos:
- `movement_code = 20` → **"Remessa"** — saída de produtos consignados (fonte de verdade da NF de saída)
- `movement_code = 21` → **"Retorno"** — entrada de produtos consignados que voltaram (fonte de verdade da NF de retorno)

> O código `4` ("Consignada") é **semântica diferente** — sinaliza o estado do item em estoque/controle, não o fluxo da nota fiscal. Para a integração do módulo, **somente 20 (saída) e 21 (retorno) são relevantes**.

A v1 atual **não valida** essa regra — grava os números das notas como texto livre.

### 1.1 Tipos de consignação (novo — não existe na v1)

| Tipo | Destinatário | Contexto | Observações |
|---|---|---|---|
| **Cliente** | Pessoa física (CPF) | Cliente VIP/Black leva peças para experimentar em casa | Padrão atual da v1 |
| **Influencer / Fotos** | Pessoa física ou estúdio | Produção de conteúdo (shooting, reels, campanhas) | Retorno alto, venda zero típica |
| **E-commerce** | Loja virtual (Z441) | Abastecimento de loja virtual operada por terceiro ou centro de distribuição | Venda esperada; volumes maiores |

**Decisão de escopo (2026-04-23):** prazo padrão **único de 7 dias** para todos os tipos. Não há diferenciação de prazo por tipo — simplifica operação e relatórios. O tipo continua relevante para filtros, gráficos de dashboard (taxa de retorno por tipo) e segregação de relatórios, mas o SLA de retorno é o mesmo.

---

## 2. Mapeamento da v1 (como está hoje)

### 2.1 Arquivos

**Controllers (ações únicas, estilo legado):**
- `AddConsignment.php` — POST de criação (AJAX/JSON)
- `EditConsignment.php` — POST de edição
- `DeleteConsignment.php` + `DeleteProductConsignment.php` — exclusões
- `ViewConsignment.php` — modal de detalhes
- `ExportConsignment.php` — export XLSX/CSV
- `PrintConsignment.php` — impressão/recibo
- `Consignments.php` — listagem + estatísticas + dashboard (match expression sobre `typeconsignment` = 1..4)

**Models:**
- `AdmsAddConsignment`, `AdmsEditConsignment`, `AdmsDeleteConsignment`, `AdmsDeleteProductConsignment`, `AdmsViewConsignment`, `AdmsListConsignments`, `AdmsStatisticsConsignments`, `AdmsExportConsignments`

**Views (blade-like PHP puro + Bootstrap 5):**
- `listConsignments.php`, `loadConsignments.php`, `printConsignment.php`
- `partials/` → `_add_consignment_form.php`, `_edit_consignment_form.php`, `_view_consignment_content.php`, `_statistics_dashboard.php`, `_dashboard_charts.php` + modais

### 2.2 Schema (MySQL v1)

```sql
adms_consignments
├── id INT AUTO_INCREMENT
├── hash_id VARCHAR(36)                  -- UUID v7 (Ramsey)
├── adms_store_id VARCHAR(4)             -- código da loja (Z421..Z443, Z457)
├── adms_emploeey_id INT                 -- (sic — typo preservado) consultor(a)
├── client_name VARCHAR(200)
├── documents VARCHAR(11)                -- CPF/CNPJ *** BUG: CNPJ tem 14 dígitos ***
├── total_products INT
├── total_product_value DECIMAL(10,4)
├── consignment_note VARCHAR(11)         -- Nº NF de saída (texto livre)
├── return_note VARCHAR(11) NULL         -- Nº NF de retorno (texto livre)
├── observations TEXT
├── date_consignments DATE
├── date_return_consignments DATE NULL
├── consignment_time INT NULL            -- dias entre saída e retorno (calculado)
├── return_period INT DEFAULT 7          -- prazo permitido (dias)
├── adms_sit_consignment_id INT DEFAULT 1
├── created_at DATETIME
└── updated_at DATETIME NULL

adms_consignment_products
├── id INT AUTO_INCREMENT
├── adms_consignment_id INT (FK lógica, sem constraint)
├── product_value DECIMAL(10,4)
├── reference VARCHAR(30)                -- código interno do produto
├── size INT                             -- FK lógica para tb_tam
├── adms_sit_consigment_prod_id INT DEFAULT 1  -- (sic — typo preservado)
├── created_at DATETIME
└── updated_at DATETIME NULL

adms_sit_consignments          -- 3 estados: Pendente, Finalizada, Cancelada
adms_sit_consigment_products   -- 3 estados: Não devolvido, Devolvido, Vendido
```

### 2.3 Fluxo atual (v1)

```
┌─ Criação ─────────────────────────────────────────────────────────┐
│  1. Usuário preenche: loja, consultor(a), cliente + CPF,          │
│     nota de remessa (só número), data, lista de produtos          │
│     (referência, tamanho, valor)                                  │
│  2. Validação: campos obrigatórios + CPF/CNPJ (11 ou 14 dígitos)  │
│  3. Dedup: bloqueia se já existe consignação PENDENTE para        │
│     (CPF + loja). Uma consignação pendente por cliente/loja.      │
│  4. Insert + items (com status default = 1 "Não devolvido")       │
│  5. Notifica usuários do workflow via SystemNotificationService   │
└───────────────────────────────────────────────────────────────────┘

┌─ Edição ──────────────────────────────────────────────────────────┐
│  1. Carrega consignação + produtos                                │
│  2. Permite: alterar cabeçalho, adicionar/remover produtos,       │
│     marcar cada item como Devolvido/Vendido, informar nota de     │
│     retorno + data de retorno                                     │
│  3. Se situação = "Finalizada" e data_return vazia → usa hoje     │
│  4. Calcula consignment_time = date_return - date_consignments    │
│  5. Update + re-insert de novos produtos                          │
└───────────────────────────────────────────────────────────────────┘

┌─ Exclusão ────────────────────────────────────────────────────────┐
│  Apenas status "Pendente" pode ser excluído,                      │
│  exceto nível de acesso <= 3 (admin/super) que força.             │
└───────────────────────────────────────────────────────────────────┘

┌─ Visualização ────────────────────────────────────────────────────┐
│  Cards: Produtos, Valor Total, Dias decorridos, Status do Prazo,  │
│  Situação. Lista de produtos com foto (por reference) + situação. │
└───────────────────────────────────────────────────────────────────┘
```

### 2.4 Permissões por nível (v1)

| Ação | Super/Admin/RH (≤3) | Loja (STOREPERMITION) |
|---|---|---|
| Ver todas as consignações | ✅ | ❌ só da sua loja |
| Criar em qualquer loja | ✅ | ❌ só na própria loja |
| Alterar situação dos produtos | ✅ | ❌ herda "Consignado" |
| Editar Finalizada/Cancelada | ✅ | ❌ |
| Excluir qualquer status | ✅ | ❌ só Pendente |

### 2.5 Estatísticas (v1)

`AdmsStatisticsConsignments`:
- Total / Pendente / Finalizada / Cancelada
- Total de produtos consignados
- Resumo financeiro (total, pending, completed, cancelled, average)
- Por mês (últimos 12 meses) — group by `DATE_FORMAT(created_at, '%Y-%m')`
- Por loja (top N com contagem e pendências)
- 5 consignações mais recentes

### 2.6 Notificações (v1)

`NotificationRecipientService::resolveRecipients('consignments', $storeId, [$excludeUserId])` resolve destinatários; `SystemNotificationService::notifyUsers(...)` dispara com ícone `fa-handshake` e link para `consignments/list`. Disparado apenas na **criação**.

---

## 3. Gaps e problemas encontrados na v1

| # | Problema | Severidade | Impacto |
|---|---|---|---|
| **G1** | **Nota de saída e retorno são texto livre** (varchar 11). Sem vínculo com `movements` do CIGAM, sem validação que a NF existe | 🔴 Crítico | Pode cadastrar NF inventada; relatórios fiscais ficam órfãos |
| **G2** | **Regra "itens da nota de retorno = itens da nota de saída" NÃO É VALIDADA** em lugar algum do código | 🔴 Crítico | Shrinkage silencioso; peças somem sem rastreio |
| **G3** | `documents` é `VARCHAR(11)` — CNPJ (14 dígitos) é truncado silenciosamente | 🔴 Crítico | Dedup+lookup de cliente quebra para pessoa jurídica |
| **G4** | Typo de schema preservado: `adms_emploeey_id`, `adms_sit_consigment_prod_id` | 🟡 Médio | Legibilidade; migração de dados precisa mapear |
| **G5** | Sem tipo de consignação (fotos/e-commerce/cliente). Tudo é tratado igual | 🟡 Médio | Relatórios não distinguem consumo interno de consignação comercial |
| **G6** | Dedup "1 pendente por CPF+loja" impede casos legítimos (ex: cliente que deixa 2 consignações em datas diferentes com NFs diferentes) | 🟡 Médio | UX ruim; atendente precisa cancelar consignação antiga que ainda tem prazo |
| **G7** | Sem state machine formal — mudança de situação é livre via dropdown | 🟡 Médio | Atendente pode pular de "Pendente" direto para "Finalizada" sem retorno efetivo |
| **G8** | Sem histórico de transições (quem finalizou, quando, por quê) | 🟡 Médio | Auditoria impossível |
| **G9** | `total_products` e `total_product_value` **denormalizados e não recalculados** em sync com itens | 🟡 Médio | Drift silencioso; relatórios ficam errados após editar itens |
| **G10** | Sem soft delete — exclusão permanente | 🟡 Médio | Erros de clique são irreversíveis |
| **G11** | `return_period` default 7 dias hardcoded por linha; sem política global ou por tipo | 🟢 Baixo | Operação precisa ajustar manualmente |
| **G12** | Retorno é "tudo ou nada" — não trata bem o caso "5 de 10 peças voltaram hoje, as outras 5 ainda estão com o cliente" | 🟢 Baixo | Pequenas consignações fracionadas são registradas como múltiplos cadastros |
| **G13** | Peça vendida não linka para a venda em `movements` — só marca o status do item | 🟡 Médio | Impossível conciliar venda-consignação automaticamente |
| **G14** | Sem alertas de prazo vencido (batch/cron) — só cor vermelha na listagem | 🟢 Baixo | Consignações esquecidas até alguém olhar a lista |
| **G15** | Sem PDF formal de remessa/retorno com assinatura do cliente | 🟢 Baixo | Prova de entrega precisa ser feita fora do sistema |
| **G16** | Export XLSX básico, sem filtros avançados por tipo/cliente | 🟢 Baixo | - |
| **G17** | **Produto é texto livre** (reference varchar 30) — permite digitar referência inexistente no catálogo | 🔴 Crítico | Consignação "fantasma" de produto que não existe; relatórios por produto ficam inválidos |
| **G18** | Layout Bootstrap 5 com breakpoints clássicos — mas não é **mobile-first**. Formulário de criação é desktop-first com muitos campos em linha que quebram em telas pequenas | 🟡 Médio | Consultor(a) não consegue usar no celular na hora de entregar peças ao cliente |

---

## 4. Proposta v2 (paridade + melhorias)

A proposta segue os padrões já consolidados em **Reversals, Returns, PurchaseOrders e Coupons**: state machine explícita + services + events + listeners + matcher CIGAM + dashboard + exports.

### 4.1 Domínio e nomenclatura

- Módulo: `consignments`
- Permissão base: `MANAGE_CONSIGNMENTS` + `VIEW_CONSIGNMENTS`
- Namespace: `App\Models\Consignment`, `App\Services\Consignment*`, `App\Http\Controllers\ConsignmentController`

### 4.2 Tipos (enum PHP)

```php
enum ConsignmentType: string {
    case Cliente      = 'cliente';      // Pessoa física — VIP/Black
    case Influencer   = 'influencer';   // Fotos, reels, campanhas
    case Ecommerce    = 'ecommerce';    // Loja virtual (Z441)
}
```

Cada tipo define: **prazo padrão**, **taxa de retorno esperada**, **destinatários de notificação** e **lojas permitidas como origem** (e-commerce geralmente sai de CD; influencer de matriz).

### 4.3 State machine

```
         ┌────────────────────────────────────────┐
         │                                        │
  draft ─┼─→ pending ──→ partially_returned ──→ completed
         │     │                                  ↑
         │     │                                  │
         │     └───→ overdue ────────────────────┘
         │           (prazo vencido com pendência)
         │
         └──→ cancelled
```

| Estado | Descrição | Transições permitidas |
|---|---|---|
| `draft` | Rascunho; criado mas NF de saída ainda não informada | → pending, cancelled |
| `pending` | NF de saída emitida; produtos com destinatário | → partially_returned, completed, cancelled, overdue |
| `partially_returned` | Parte retornou (NF de retorno parcial) ou parte foi vendida | → completed, cancelled, overdue |
| `overdue` | Prazo venceu com itens ainda pendentes | → partially_returned, completed (com atraso) |
| `completed` | Todos os itens resolvidos (retornados OU vendidos) | (terminal) |
| `cancelled` | Cancelada (erro de cadastro, desistência) | (terminal) |

Transição dispara: `ConsignmentStateChanged` event → listeners (notificações, audit, webhooks).

### 4.4 Schema proposto (Laravel migrations)

```php
// consignments
$table->id();
$table->uuid('uuid')->unique();
$table->enum('type', ['cliente', 'influencer', 'ecommerce']);
$table->foreignId('store_id')->constrained('stores');   // origem
$table->foreignId('employee_id')->nullable()->constrained('employees'); // consultor
$table->foreignId('customer_id')->nullable()->constrained('customers'); // cliente cadastrado (opcional)

// dados do destinatário (snapshot — funciona mesmo sem customer_id)
$table->string('recipient_name');
$table->string('recipient_document', 18)->nullable();   // CPF ou CNPJ (mask aplicada na view)
$table->string('recipient_document_clean', 14)->nullable()->index(); // só dígitos
$table->string('recipient_phone', 20)->nullable();
$table->string('recipient_email')->nullable();

// nota fiscal de saída (remessa) — FK composta semântica para movements
$table->string('outbound_invoice_number', 20);
$table->date('outbound_invoice_date');
$table->string('outbound_store_code', 4);               // redundante com store->code, mas simplifica lookup
$table->decimal('outbound_total_value', 12, 2);
$table->unsignedInteger('outbound_items_count');

// nota fiscal de retorno (pode ser null até retorno; várias NFs via `consignment_returns` N:1)
$table->decimal('returned_total_value', 12, 2)->default(0);
$table->unsignedInteger('returned_items_count')->default(0);
$table->decimal('sold_total_value', 12, 2)->default(0);
$table->unsignedInteger('sold_items_count')->default(0);

// prazo
$table->date('expected_return_date');
$table->unsignedSmallInteger('return_period_days');

// controle
$table->enum('status', ['draft', 'pending', 'partially_returned', 'overdue', 'completed', 'cancelled']);
$table->text('notes')->nullable();
$table->foreignId('created_by')->constrained('users');
$table->foreignId('completed_by')->nullable()->constrained('users');
$table->timestamp('completed_at')->nullable();
$table->timestamps();
$table->softDeletes();

$table->index(['type', 'status']);
$table->index(['store_id', 'status']);
$table->index(['expected_return_date', 'status']);
$table->index(['outbound_store_code', 'outbound_invoice_number']);
```

```php
// consignment_items — snapshot dos itens da NF de saída
$table->id();
$table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
$table->foreignId('movement_id')->nullable()->constrained('movements'); // link pro movement code=20 (Remessa/saída)

// INTEGRAÇÃO OBRIGATÓRIA COM CATÁLOGO (ver §4.6):
$table->foreignId('product_id')->constrained('products');          // exige produto existente
$table->foreignId('product_variant_id')->nullable()->constrained('product_variants'); // variante tamanho/EAN (null = produto sem variante cadastrada)

// snapshot (congelados no momento do cadastro — não sofrem impacto se catálogo mudar depois)
$table->string('reference', 30);          // products.reference no momento do cadastro
$table->string('ean', 14)->nullable();    // product_variants.barcode no momento do cadastro
$table->string('size_label', 20)->nullable();
$table->foreignId('size_id')->nullable()->constrained('product_sizes');
$table->string('description')->nullable(); // products.description snapshot (para PDF)

$table->unsignedInteger('quantity');
$table->decimal('unit_value', 10, 2);
$table->decimal('total_value', 10, 2);   // quantity * unit_value

// quantidades resolvidas (soma deve ser <= quantity)
$table->unsignedInteger('returned_quantity')->default(0);
$table->unsignedInteger('sold_quantity')->default(0);
$table->unsignedInteger('lost_quantity')->default(0);   // não voltou nem foi vendido (shrinkage)

$table->enum('status', ['pending', 'partially_returned', 'returned', 'sold', 'partial', 'lost'])->default('pending');
$table->timestamps();

$table->index(['product_id', 'status']);
```

```php
// consignment_returns — registros de retorno parcial (N:1 com consignments)
$table->id();
$table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
$table->string('return_invoice_number', 20)->nullable();
$table->date('return_date');
$table->string('return_store_code', 4);
$table->unsignedInteger('returned_quantity');
$table->decimal('returned_value', 12, 2);
$table->text('notes')->nullable();
$table->foreignId('registered_by')->constrained('users');
$table->timestamps();
```

```php
// consignment_return_items — pivô de retorno (qual item voltou em qual NF de retorno)
$table->id();
$table->foreignId('consignment_return_id')->constrained()->cascadeOnDelete();
$table->foreignId('consignment_item_id')->constrained()->cascadeOnDelete();
$table->unsignedInteger('quantity');
```

```php
// consignment_status_histories — audit da state machine
$table->id();
$table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
$table->string('from_status', 30)->nullable();
$table->string('to_status', 30);
$table->foreignId('user_id')->constrained('users');
$table->text('notes')->nullable();
$table->timestamp('changed_at');
```

### 4.5 Regra central: "nota de retorno = itens da nota de saída"

Implementação em `ConsignmentReturnService::register(Consignment $c, array $returnItems, ...)`:

```php
// Pseudocódigo da validação
foreach ($returnItems as $returnItem) {
    $consignmentItem = $c->items()->find($returnItem['consignment_item_id']);

    if (!$consignmentItem) {
        throw new ConsignmentReturnException(
            "Item {$returnItem['reference']} não pertence à consignação {$c->uuid}"
        );
    }

    $alreadyResolved = $consignmentItem->returned_quantity + $consignmentItem->sold_quantity;
    $remainingQuantity = $consignmentItem->quantity - $alreadyResolved;

    if ($returnItem['quantity'] > $remainingQuantity) {
        throw new ConsignmentReturnException(
            "Item {$consignmentItem->reference} só tem {$remainingQuantity} pendente(s); tentou devolver {$returnItem['quantity']}"
        );
    }
}

// Após validar tudo, aplica de fato em uma transaction:
DB::transaction(function () use ($c, $returnItems) {
    $return = $c->returns()->create([...]);
    foreach ($returnItems as $ri) {
        $return->items()->create([...]);
        $consignmentItem->increment('returned_quantity', $ri['quantity']);
        $consignmentItem->refreshDerivedStatus();  // pending → partial → returned
    }
    $c->refreshTotals();   // recalcula returned_total_value, ..., status
});
```

Complemento: se a NF de retorno existe no CIGAM (`movements` com code=21), o service puxa os itens reais e compara com o `consignment_items` — **diff automático** mostra qualquer discrepância antes de salvar. Se não existe (ainda), registra manualmente mas marca `return_invoice_number` como não conciliado.

### 4.6 Integração com o módulo de Produtos (obrigatória)

**Regra dura:** `consignment_items.product_id` é FK **NOT NULL** para `products`. Impossível cadastrar um item cuja referência não exista no catálogo.

**Fluxo de seleção de item no formulário:**

1. Usuário começa a digitar em um campo de busca (autocomplete com debounce 300ms)
2. Frontend chama `GET /api/products/lookup?q={termo}` — o backend busca por:
   - `products.reference` (prefix match — ex: "A123")
   - `products.description` (contains match — ex: "sandália")
   - `product_variants.barcode` (exact match — EAN-13 escaneado ou digitado)
   - `product_variants.aux_reference` (contains match — código auxiliar)
   Filtro: `products.is_active = true` + scope do tenant.
3. Dropdown mostra até 20 resultados com: miniatura, referência, descrição, marca, preço (cost_price)
4. Ao selecionar o produto, um segundo select (ou chips clicáveis) mostra as **variantes de tamanho disponíveis** (`product_variants` ativas) — usuário escolhe o tamanho
5. Campo "Valor" pré-preenche com `products.sale_price` (editável)
6. `product_id` + `product_variant_id` gravam no item; `reference`, `ean`, `size_label`, `description` são **snapshots** tomados naquele momento (não sofrem sync reverso se o catálogo mudar)

**Suporte a leitor de código de barras / EAN:**

- Input textual aceita colar/digitar um EAN-13
- Detecta automaticamente (13 dígitos) e faz lookup direto em `product_variants.barcode`
- Se match único, seleciona produto+variante em um clique (ideal para operação mobile com leitor ou câmera)

**Quando a integração CIGAM tiver matchou a NF de saída:**

- `ConsignmentLookupService::findOutboundInvoice(...)` já retorna `product_id`/`product_variant_id` resolvidos pelo `reference` + `ean` do movement
- Se algum item do movement não encontrou produto no catálogo, o service **bloqueia o cadastro** e lista os referências órfãs — isso sinaliza produto faltando no CIGAM-sync ou erro de digitação da NF

**Integridade referencial:**

- `product_id` usa `restrictOnDelete()` — produto não pode ser excluído se tem consignações ativas
- Soft-delete de produto (`is_active=false`) **não** impede listagem/edição de consignações existentes, mas bloqueia adição de novos itens daquela referência

### 4.7 Integração CIGAM (movements)

- **Lookup de saída**: `ConsignmentLookupService::findOutboundInvoice($storeCode, $invoiceNumber, $date)` busca em `movements` WHERE `movement_code = 20` (Remessa) e popula os itens automaticamente. Filtro por data porque o número de NF reseta por ano/loja. **Antes de retornar, resolve cada item via `reference` + `ean` para `products` + `product_variants`** — se algum não resolver, inclui na lista de "órfãos" e bloqueia o cadastro.
- **Lookup de retorno**: `ConsignmentLookupService::findReturnInvoice($storeCode, $invoiceNumber, $date)` busca em `movements` WHERE `movement_code = 21` (Retorno). Antes de salvar, o `ConsignmentReturnService` faz **diff automático**: itens que estão no retorno mas não estavam na saída → erro; itens com quantidade de retorno > pendente no saída → erro.
- **Matcher automático**: command agendado `consignments:cigam-match` a cada 15min (mesmo padrão `purchase-orders:cigam-match`). Varre consignações em `pending`/`partially_returned`/`overdue` e tenta casar qualquer novo `movement_code=21` com saída existente — idempotente (não reprocessa retornos já registrados).
- **Saída sem NF prévia (draft)**: permite cadastrar antes da NF ser emitida; o matcher reconcilia quando o `movement_code=20` aparece via `CigamSyncService`.

### 4.8 Bloqueio de novo cadastro por inadimplência (Fase 1)

**Decisão (2026-04-23):** implementar na Fase 1 — não é nice-to-have.

Regra: ao tentar criar uma nova consignação, o `ConsignmentService::ensureRecipientEligibility()` verifica se o destinatário (mesmo `recipient_document_clean`) possui alguma consignação em status `overdue`. Se sim, bloqueia o cadastro com mensagem clara:

> "Este destinatário possui {N} consignação(ões) em atraso. Finalize ou cancele as pendentes antes de criar uma nova."

Listagem das consignações em atraso é mostrada no modal (link para cada uma). Override requer a permissão `OVERRIDE_CONSIGNMENT_LOCK` — usuários com essa permissão veem um checkbox "Ignorar bloqueio (justificar)" que exige texto de justificativa e é gravado em `consignment_status_histories`.

Aplica-se aos 3 tipos (Cliente, Influencer, E-commerce) com o mesmo critério.

### 4.9 Commands agendados

| Command | Frequência | Função |
|---|---|---|
| `consignments:cigam-match` | every 15min | Tenta conciliar notas de retorno com `movements` (code=21) |
| `consignments:mark-overdue` | dailyAt 06:00 | Muda `pending` → `overdue` quando `expected_return_date < now()` e há itens pendentes |
| `consignments:remind-upcoming` | dailyAt 09:00 | Notifica consultor(a) + gerente quando faltam ≤ 2 dias para vencer |
| `consignments:overdue-alert` | dailyAt 09:00 | Notifica RH/supervisão quando `overdue` há ≥ 7 dias |

### 4.10 Permissões (8 novas)

```
MANAGE_CONSIGNMENTS          # criar, editar, cancelar
VIEW_CONSIGNMENTS            # listar, ver detalhe
REGISTER_CONSIGNMENT_RETURN  # lançar nota de retorno
REGISTER_CONSIGNMENT_SALE    # marcar item como vendido (vincular venda)
COMPLETE_CONSIGNMENT         # finalizar (encerrar ciclo)
CANCEL_CONSIGNMENT           # cancelar
EXPORT_CONSIGNMENTS          # XLSX / PDF
OVERRIDE_CONSIGNMENT_LOCK    # editar consignações finalizadas E ignorar bloqueio de overdue
```

### 4.11 Frontend — Mobile-first e responsividade total

**Requisito obrigatório (2026-04-23):** todas as telas do módulo devem ser **mobile-first**. Consultor(a) em loja precisa conseguir cadastrar/consultar/finalizar uma consignação direto do celular no momento da entrega ao cliente.

**Diretrizes de design:**

- **Breakpoints Tailwind**: desenhar primeiro para `<640px` (mobile), depois adicionar `sm:`, `md:`, `lg:`, `xl:` progressivamente. Nunca usar `max-sm:` (desktop-first) — sempre `sm:` (mobile-first).
- **Touch targets ≥ 44×44px** em botões, checkboxes e itens de lista.
- **Leitor de código de barras nativo**: campo EAN aceita input numérico com `inputmode="numeric"` + botão dedicado "Escanear" que, em navegadores mobile, aciona a câmera via [BarcodeDetector API](https://developer.mozilla.org/en-US/docs/Web/API/Barcode_Detection_API) quando suportada; fallback para input manual. Biblioteca sugerida: `@zxing/browser` (leve, funciona no iOS/Safari).
- **Evitar `overflow-x`** em tabelas — usar `DataTable` com `mobileView="cards"` (já padrão no módulo recentes) que colapsa colunas em cards no mobile.
- **Modais (`StandardModal`)**: usar `maxWidth="2xl"` e garantir que `body` é scrollável em altura; footer sticky com botões full-width em `<640px`.
- **Wizard de criação**: 1 passo por tela no mobile (não 3 passos em um modal único), com progress indicator no topo. No desktop (`lg:`), mostrar os 3 passos lado a lado.
- **Datepickers**: usar `<input type="date">` nativo (teclado nativo no mobile vence qualquer biblioteca custom).
- **Testar com DevTools em 360×640 (mobile pequeno) + 768×1024 (tablet) + 1440×900 (desktop)** antes de marcar task como completa.

**Páginas:**
- `Pages/Consignments/Index.jsx` — listagem com `StatisticsGrid` (6 cards: Total, Pendentes, Atrasadas, Parciais, Finalizadas, Canceladas) + `DataTable` + filtros (tipo, loja, consultor, status, período, busca). Filtros colapsam em accordion no mobile.
- `Pages/Consignments/Dashboard.jsx` — 4 gráficos recharts: evolução mensal, distribuição por tipo, top 10 clientes, taxa de retorno por consultor. Gráficos usam `ResponsiveContainer` com altura fixa (320px mobile, 400px desktop).

**Modais (StandardModal):**
- `CreateConsignmentModal` — wizard 3 passos: (1) Tipo + loja + destinatário, (2) NF de saída (busca em movements → popula itens) OU busca manual no catálogo de produtos + variante, (3) confirmação. No mobile, cada passo ocupa a tela cheia.
- `ConsignmentDetailModal` — tabs: Dados, Itens, Retornos, Histórico. Tabs viram accordion no mobile.
- `RegisterReturnModal` — NF de retorno + lista de itens (só os pendentes), validando quantidade por item. Cada item é um card com stepper +/− para a quantidade.
- `CompleteConsignmentModal` — marca itens não-devolvidos como `lost` (shrinkage) antes de finalizar.
- `CancelConsignmentModal` — motivo obrigatório.
- `ProductLookupInline` (componente compartilhado novo) — dropdown com autocomplete + seletor de variante + campo EAN com botão escanear; reutilizável em outros módulos que precisam selecionar produtos.

### 4.12 Exports

- **XLSX (listagem)**: 2 abas — "Consignações" (1 linha por consignação) + "Itens" (1 linha por item com ref, tamanho, status)
- **PDF individual**: recibo de consignação com itens, valor total, assinatura do cliente, prazo e instruções de retorno — serve como comprovante físico que viaja com a remessa

### 4.13 Import (opcional — fase 2)

XLSX com upsert para migrar histórico v1: usa `recipient_document_clean + outbound_invoice_number` como chave natural. Converte `adms_sit_consignment_id` → novo enum; mapeia `adms_emploeey_id` → `employee_id` via `cigam_code` intermediário. **Importante:** o import precisa resolver `reference` → `product_id` para cada item; referências órfãs vão para uma aba "errors" no retorno do import com sugestão de importar no catálogo de produtos antes de reprocessar.

---

## 5. Melhorias sugeridas (além da paridade)

### 5.1 🏆 Alta prioridade (todas entram na Fase 1 ou 2)

| # | Melhoria | Por quê |
|---|---|---|
| **M1** | **Validação de itens retorno = saída** (regra principal do usuário) | Fecha shrinkage; materializa a regra de negócio |
| **M2** | **Vínculo com `movements` do CIGAM** em saída (code 20) e retorno (code 21) | Elimina NF inventada; conciliação automática |
| **M3** | **Tipo de consignação** (Cliente/Influencer/E-commerce) | Permite filtros/relatórios diferenciados (prazo é unificado em 7d) |
| **M4** | **Retorno parcial** — itens podem voltar em múltiplas NFs | Reflete operação real (cliente devolve em 2-3 visitas) |
| **M5** | **Shrinkage tracking** — quando finaliza com itens "perdidos", marca como `lost_quantity` e gera alerta de compliance/estoque | Expõe problema que hoje é silencioso |
| **M6** | **State machine + histórico** com audit completo | Base para compliance LGPD/auditoria + relatórios de tempo médio por transição |
| **M7** | **Commands de alerta preventivo** (≤2 dias para vencer) | Evita prazo vencido — hoje só mostra cor vermelha quando já venceu |
| **M8** | **Integração com módulo Produtos** (FK NOT NULL em `consignment_items.product_id` + lookup com autocomplete/EAN) | Elimina produto "fantasma" (gap G17); habilita relatórios por produto/coleção |
| **M9** | **Bloqueio de cadastro por inadimplência** (`overdue` em aberto para o destinatário) com override permissionado | Freio de arrumação: consultor(a) precisa resolver pendências antes de criar novas |
| **M10** | **Mobile-first e responsividade total** (celular → tablet → desktop) + leitor de código de barras via câmera | Consultor(a) cadastra direto no celular na hora da entrega ao cliente (gap G18) |

### 5.2 ⚡ Média prioridade

| # | Melhoria | Por quê |
|---|---|---|
| **M11** | **Vínculo item→venda** quando marca como vendido (FK para `movements` code=2) | Concilia financeiramente consignação com venda registrada |
| **M12** | **Cliente cadastrado (FK opcional para `customers`)** ao invés de só snapshot de nome+CPF | Permite ver "todas as consignações da Maria"; reuso de contato |
| **M13** | **Dashboard com 4 gráficos recharts** (padrão atual) + **taxa de conversão por consultor** | Indicador de performance comercial da consignação |
| **M14** | **Wizard de criação em 3 passos** com busca automática da NF de saída no CIGAM | Reduz erro de digitação de NF e de itens |
| **M15** | **PDF comprovante com QR Code** apontando para detalhe da consignação | Cliente escaneia na devolução e consultor abre direto a tela certa |

### 5.3 🌱 Baixa prioridade / nice-to-have

| # | Melhoria | Por quê |
|---|---|---|
| **M16** | **Integração com WhatsApp** (já existe no Helpdesk) para lembretes automáticos ao cliente | Reduz prazo vencido sem ligação manual |
| **M17** | **Import XLSX** migrando histórico v1 com upsert por (CPF_limpo + outbound_invoice_number) | Preserva operação e estatísticas históricas |
| **M18** | **Fotos de assinatura de retorno** (upload pelo app mobile) como prova de entrega | Contestações futuras ficam resolvidas no próprio sistema |
| **M19** | **Relatório de "peças mais consignadas / menos vendidas"** (produto X vai mil vezes mas vende 5%) | Insight de planejamento de coleção |
| **M20** | **Limite configurável por cliente** (ex: máx 5 peças ou R$ X em consignação simultânea) | Controle de crédito/exposição |

### 5.4 🚫 Não-objetivos (fora de escopo nesta fase)

- Pedidos de venda efetivos (isso já é Sale/Movement)
- Gestão de estoque físico (Stock módulos cuidam)
- Nota fiscal eletrônica emitida pelo Mercury (CIGAM continua fonte de NF)

---

## 6. Fases sugeridas de implementação

Seguindo o padrão dos módulos recentes (Returns, Reversals, Coupons). **Fase 1 concentra as regras críticas** (M1, M2, M8, M9, M10) para garantir que o módulo nasça com as validações corretas e responsivo desde o primeiro sprint.

| Fase | Entregas | Testes esperados |
|---|---|---|
| **0** | Enums (`ConsignmentType`, `ConsignmentStatus`, `ConsignmentItemStatus`) + 8 Permissions | 5 unit |
| **1** | **Migrations (5 tabelas) com FK NOT NULL para `products` + `product_variants` (M8)** + Models + Factories + Seeders + **Services core**: `ConsignmentService` (CRUD + `ensureRecipientEligibility` para M9 + regras por tipo), `ConsignmentLookupService` (lookup produtos + CIGAM), `ConsignmentReturnService` (regra M1 — itens retorno = saída), `ConsignmentTransitionService` (state machine) | 25 feature |
| **2** | Controller + rotas + **Pages/Index mobile-first (M10)** + `ProductLookupInline` (componente compartilhado) + modais Create/Detail/Return responsivos | 20 feature |
| **3** | Wizard 3 passos (tela cheia no mobile), Dashboard com 4 gráficos recharts responsivos, exports XLSX + PDF comprovante com QR Code | 10 feature |
| **4** | Events + Listeners + Notifications + Commands agendados (`cigam-match` every 15min, `mark-overdue` daily 06:00, `remind-upcoming` daily 09:00, `overdue-alert` daily 09:00) | 10 feature |
| **5** | Vínculo item→venda (M11), Cliente cadastrado (M12), taxa de conversão por consultor (M13) | 8 feature |
| **6** | Import XLSX (migração v1) com resolução de `reference` → `product_id` e aba de órfãos — opcional | 5 feature |

**Estimativa total:** ~83 tests / ~270 assertions / 18 rotas / 8 permissions / 4 commands / 1 módulo pronto para produção.

**Entregas críticas da Fase 1 (não negociáveis):**

- ✅ Regra M1 (NF retorno = NF saída) validada em service + testes
- ✅ Regra M8 (produto obrigatório no catálogo) via FK NOT NULL + lookup de produtos
- ✅ Regra M9 (bloqueio de overdue) em `ConsignmentService::ensureRecipientEligibility`
- ✅ Layout mobile-first (M10) — **toda tela criada na Fase 2 em diante já nasce responsiva**; verificação em 360×640 / 768×1024 / 1440×900 faz parte do Definition of Done

---

## 7. Pontos de atenção técnicos (gotchas)

- **Laravel 12 auto-discovery**: Em Coupons descobrimos que `Event::listen()` manual causa duplicação porque o auto-discovery está ativo. Em Consignments, registrar listeners **somente** via tipagem do método `handle(EventName $e)` — NÃO chamar `Event::listen` em AppServiceProvider.
- **Cache do CentralMenuResolver é file-based** — ao adicionar a página, limpar `storage/framework/cache` explicitamente, não apenas `artisan cache:clear`.
- **Tenant modules**: criar linha em `tenant_modules` para cada plano além de `central_modules` (gotcha documentado em `module_registration_gotchas.md`).
- **MySQL dedup com soft-delete**: partial unique index não funciona; usar `ConsignmentService::ensureUnique()` em código (padrão Coupons).
- **`recipient_document`**: máscara CPF/CNPJ é cosmética; dedup e lookup usam `recipient_document_clean` (só dígitos) com índice. Validar 11 (CPF) ou 14 (CNPJ).
- **Movements como fonte de verdade**: nunca escrever em `movements`; sempre ler. FK para `movement_id` é link de auditoria, não controle.

---

## 8. Resumo executivo (1 parágrafo)

A v1 implementa o cadastro de consignações com estrutura simples (header + items, 3 estados, dedup por CPF+loja), mas tem **4 gaps críticos**: (1) notas fiscais são texto livre sem vínculo com o CIGAM, (2) a regra "itens da NF de retorno = itens da NF de saída" **não é validada em lugar algum**, (3) não existe tipificação (cliente vs. influencer vs. e-commerce), e (4) produto é texto livre sem vínculo com o catálogo — permite cadastrar referência inexistente. A v2 propõe paridade completa com o padrão dos módulos recentes (state machine + services + events + commands agendados), vinculando saída e retorno aos `movements` do CIGAM (**code 20 na saída / "Remessa"**, **code 21 no retorno / "Retorno"**), introduzindo 3 tipos (prazo unificado 7d), **FK NOT NULL para `products` + `product_variants`** com lookup por referência/EAN (escaneamento via câmera no mobile), **bloqueio de novo cadastro para destinatários com consignação em atraso** (override permissionado), design **mobile-first** em todas as telas (consultor cadastra no celular), suportando retornos parciais em múltiplas NFs, rastreando shrinkage explicitamente e gerando alertas preventivos de prazo. Estimativa: ~83 tests em 7 fases, sem dependências novas relevantes (apenas `@zxing/browser` para leitura de EAN via câmera).

---

## 9. Changelog do plano

| Data | Decisões incorporadas | Motivo |
|---|---|---|
| 2026-04-23 (inicial) | Escopo base, 19 melhorias classificadas, 7 fases | Análise inicial v1 → v2 |
| 2026-04-23 (revisão 1) | (a) Prazo unificado 7d para todos os tipos; (b) Bloqueio por overdue promovido para Fase 1 (M9); (c) Integração obrigatória com módulo Produtos via FK NOT NULL (M8, gap G17); (d) Mobile-first + responsividade em todas as telas (M10, gap G18); (e) Renumeração M8..M20 | Alinhamento com o usuário sobre escopo e prioridades |

---

**Próximo passo proposto:** validar este plano revisado. Se estiver OK, abro a **Fase 0** (enums + 8 permissions) e **Fase 1** (migrations com FK de produtos + services core incluindo M1/M8/M9), em commits separados. As telas mobile-first entram na Fase 2.
