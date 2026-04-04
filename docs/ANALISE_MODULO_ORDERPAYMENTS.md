# Análise do Módulo OrderPayments (Ordens de Pagamento)

**Versão:** 1.0  
**Data:** 04/04/2026  
**Status:** Módulo de Referência para Módulos Financeiros/Workflow  

---

## 1. Visão Geral

O módulo **OrderPayments** é o sistema de gestão de ordens de pagamento do Mercury, implementando um workflow Kanban completo com 4 estágios. É o módulo mais completo do projeto em termos de arquitetura, combinando:

- **Workflow Kanban** com drag-and-drop (SortableJS)
- **State Machine** para transições de status com validação
- **Sistema de permissões** multi-nível (3 níveis de exclusão)
- **Rateio de custos** (allocations) com validação percentual
- **8 tipos de relatórios** financeiros
- **Dashboard** com 4 gráficos (Chart.js)
- **API REST** completa (10 endpoints)
- **Parcelas** (installments) com controle de pagamento
- **Soft-delete** com restauração (Super Admin)
- **Acessibilidade** (ARIA, screen reader, keyboard navigation)
- **Notificações WebSocket** em tempo real
- **~90 testes** unitários e de integração

### Por que é Módulo de Referência?

| Aspecto | Sales (Jan/2026) | OrderPayments (Mar/2026) |
|---------|-------------------|--------------------------|
| UI Pattern | Tabela paginada | Kanban + Tabela |
| Status Flow | Simples (badge) | State Machine com validação |
| Exclusão | Simples | 3 níveis com soft-delete |
| Relatórios | Estatísticas cards | 8 relatórios + Dashboard |
| Parcelas | N/A | Sistema completo |
| Rateio | N/A | Alocação por centro de custo |
| API REST | Sim | Sim (10 endpoints) |
| Drag-and-drop | Não | SortableJS |
| Acessibilidade | Básica | Completa (ARIA + SR) |

---

## 2. Arquitetura de Arquivos

### 2.1. Controllers (11 arquivos, ~3.514 linhas)

```
app/adms/Controllers/
├── OrderPayments.php              # 918 linhas — Controller principal (Kanban + AJAX endpoints)
├── AddOrderPayments.php           # 172 linhas — Criação de ordem
├── EditOrderPayments.php          # 270 linhas — Edição de ordem
├── DeleteOrderPayments.php        # 129 linhas — Soft-delete com 3 níveis
├── ViewOrderPayments.php          # 113 linhas — Visualização detalhada
├── ReportOrderPayments.php        # 613 linhas — 8 tipos de relatórios + Excel export
├── DownloadOrderPaymentFiles.php  # 214 linhas — Download de arquivos (single + ZIP)
├── SitOrderPayments.php           # 108 linhas — Config status (AbstractConfigController)
└── Api/V1/
    └── OrderPaymentsController.php # 944 linhas — API REST completa

app/cpadms/Controllers/
├── SearchOrderPayments.php                    # 87 linhas — Busca multi-status
└── CreateSpreadsheetOrderPayments.php         # 46 linhas — Exportação planilha
```

### 2.2. Models (8 arquivos, ~3.544 linhas)

```
app/adms/Models/
├── AdmsAddOrderPayment.php            # 875 linhas — Criação com transação, parcelas, upload
├── AdmsEditOrderPayment.php           # 860 linhas — Edição com sync de parcelas
├── AdmsDeleteOrderPayments.php        # 201 linhas — Soft-delete + restauração
├── AdmsListOrderPayments.php          # 284 linhas — Listagem Kanban por status
├── AdmsViewOrderPayment.php           # 125 linhas — View com 11 LEFT JOINs
├── AdmsReportOrderPayments.php        # 697 linhas — 8 relatórios financeiros
└── constants/
    └── OrderPaymentStatus.php         # 40 linhas — Constantes de status

app/cpadms/Models/
└── CpAdmsSearchOrderPayments.php      # 462 linhas — Busca com filtros complexos
```

### 2.3. Services (3 arquivos, ~522 linhas)

```
app/adms/Services/
├── OrderPaymentAllocationService.php   # 216 linhas — CRUD + validação de rateio
├── OrderPaymentDeleteService.php       # 80 linhas — Lógica de permissão de exclusão
└── OrderPaymentTransitionService.php   # 227 linhas — State machine de transições
```

### 2.4. Views (18 arquivos, ~9.392 linhas)

```
app/adms/Views/orderPayment/
├── loadOrderPayments.php              # 284 linhas — Página principal (layout + filtros)
├── listOrderPayment.php               # 287 linhas — Kanban board com KPIs
├── addOrderPayments.php               # 407 linhas — Formulário full-page
├── editOrderPayment.php               # 1.400+ linhas — Edição full-page
├── viewOrderPayment.php               # 492 linhas — Visualização full-page
└── partials/
    ├── _add_order_payment_modal.php          # 313 linhas — Modal de criação
    ├── _edit_order_payment_modal.php         # 35 linhas — Shell do modal de edição
    ├── _edit_order_payment_content.php       # ~500 linhas — Conteúdo AJAX do edit
    ├── _view_order_payment_modal.php         # 38 linhas — Shell do modal de view
    ├── _view_order_payment_content.php       # ~600 linhas — Conteúdo AJAX do view
    ├── _delete_order_payment_modal.php       # 40 linhas — Modal de exclusão
    ├── _kanban_card.php                      # 143 linhas — Card reutilizável do Kanban
    ├── _allocation_interface.php             # 105 linhas — Interface de rateio
    ├── _transition_modal.php                 # 43 linhas — Modal de transição
    ├── _report_modal.php                     # 79 linhas — Modal de relatórios
    ├── _dashboard_modal.php                  # 132 linhas — Dashboard com gráficos
    ├── _status_history_modal.php             # 70 linhas — Histórico de transições
    └── _delete_file_confirmation_modal.php   # ~30 linhas — Confirmação de exclusão de arquivo
```

### 2.5. JavaScript (1 arquivo, 4.867 linhas)

```
assets/js/order-payments.js   # 4.867 linhas — Controller AJAX completo
```

### 2.6. Testes (~90 testes, 6 arquivos)

```
tests/OrderPayments/
├── OrderPaymentAllocationServiceTest.php       # 319 linhas, 14 testes
├── OrderPaymentDeleteServiceTest.php           # 223 linhas, 15 testes
├── OrderPaymentTransitionServiceTest.php       # 411 linhas, 27 testes
├── OrderPaymentAccessibilityTest.php           # 1.505 linhas, 16+ testes
├── OrderPaymentWorkflowIntegrationTest.php     # 404 linhas, multi-teste
└── OrderPaymentsControllerTest.php             # 272 linhas, 7+ testes
```

---

## 3. Workflow Kanban (State Machine)

### 3.1. Status e Transições

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  BACKLOG(1)  │────►│  DOING(2)   │────►│ WAITING(3)  │────►│   DONE(4)   │
│ Solicitação  │◄────│ Reg. Fiscal │◄────│  Lançado    │◄────│    Pago     │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
```

**Regras:**
- Apenas transições adjacentes são permitidas (1↔2, 2↔3, 3↔4)
- Transições "para frente" exigem campos obrigatórios
- Transições "para trás" exigem motivo/notas

### 3.2. Campos Obrigatórios por Transição

| Transição | Campos Obrigatórios | Campos Condicionais |
|-----------|---------------------|---------------------|
| 1→2 (Backlog→Doing) | `number_nf`, `launch_number` | — |
| 2→3 (Doing→Waiting) | `launch_number` | PIX: `type_key_pix_id`, `key_pix`; Banco: `bank_id`, `agency`, `checking_account`; Boleto: nenhum |
| 3→4 (Waiting→Done) | `date_paid` | — |
| Retorno (qualquer) | Nenhum obrigatório | `notes` (motivo) |

### 3.3. Implementação (OrderPaymentTransitionService)

```php
// Mapa de transições permitidas
private const TRANSITIONS = [
    1 => [2],       // Backlog → Doing
    2 => [1, 3],    // Doing → Backlog ou Waiting
    3 => [2, 4],    // Waiting → Doing ou Done
    4 => [3],       // Done → Waiting
];

// Campos obrigatórios por transição
private const REQUIRED_FIELDS = [
    '1_2' => ['number_nf', 'launch_number'],
    '2_3' => ['launch_number'],
    '3_4' => ['date_paid'],
];

// Campos condicionais por tipo de pagamento
private const CONDITIONAL_FIELDS = [
    '2_3' => [
        'default' => ['bank_id', 'agency', 'checking_account'],
        'pix'     => ['adms_type_key_pix_id', 'key_pix'],
        'boleto'  => [],
    ],
];
```

### 3.4. Histórico de Transições

Toda transição é registrada em `adms_order_payment_status_history`:

```sql
INSERT INTO adms_order_payment_status_history 
    (adms_order_payment_id, old_status_id, new_status_id, changed_by_user_id, notes, created_at)
VALUES (:orderId, :from, :to, :userId, :notes, NOW())
```

---

## 4. Sistema de Permissões

### 4.1. Níveis de Exclusão (3 níveis)

| Nível | Critério | Requer Motivo | Requer Confirmação Dupla |
|-------|----------|---------------|--------------------------|
| 1 | Backlog + criador + nunca editado | Não | Não |
| 2 | Backlog/Doing + financeiro (≤5) | Sim | Não |
| 3 | Waiting/Done + Super Admin (=1) | Sim | Sim |

```php
// OrderPaymentDeleteService::canDelete()
return [
    'allowed' => true|false,
    'requireReason' => true|false,
    'requireConfirmation' => true|false,
    'message' => 'Mensagem de erro',
    'level' => 1|2|3,
];
```

### 4.2. Permissão de Edição por Campo

```php
// Na view de edição, campos são desabilitados por nível de acesso:
$userLevel = SessionContext::getAccessLevel();
$canEditAll = in_array($userLevel, [1, 2, 9, 15]); // Super Admin, Admin, Financial, Special

// Se NÃO pode editar tudo: área, centro de custo, marca, gestor, fornecedor, descrição = disabled
```

### 4.3. Filtro por Loja (StorePermissionTrait)

```php
// Em AdmsListOrderPayments
use StorePermissionTrait;

// Nível ≤ FINANCIALPERMITION (5): vê todas as lojas (rede_id ≤ 6)
// Nível > 5: vê apenas sua loja
```

### 4.4. Soft-Delete e Restauração

```php
// Soft-delete: marca deleted_at, deleted_by_user_id, delete_reason
// Restauração: apenas Super Admin (nível 1)
// Registros deletados: visíveis apenas para Super Admin com show_deleted=1
// Visual: opacity 0.6, borda vermelha tracejada
```

---

## 5. Rateio de Custos (Allocations)

### 5.1. Interface

O rateio permite distribuir o valor total de uma ordem entre múltiplos centros de custo:

```
┌───────────────────────────────────────────────────────────┐
│ ☐ Habilitar Rateio                                       │
├───────────────┬──────────┬──────────────┬────────────────┤
│ Centro Custo  │ % Rateio │ Valor        │ Ações          │
├───────────────┼──────────┼──────────────┼────────────────┤
│ [Select CC]   │ 60%      │ R$ 600,00    │ [Remover]      │
│ [Select CC]   │ 40%      │ R$ 400,00    │ [Remover]      │
├───────────────┼──────────┼──────────────┼────────────────┤
│ Total:        │ 100%     │ R$ 1.000,00  │ [+ Adicionar]  │
└───────────────┴──────────┴──────────────┴────────────────┘
```

### 5.2. Validação

```php
// OrderPaymentAllocationService::validate()
// 1. Nenhuma alocação vazia
// 2. Cada linha: cost_center_id preenchido, % > 0, valor > 0
// 3. Soma das % = 100% (tolerância ±0.01)
// 4. Soma dos valores = valor total da ordem (tolerância ±0.01)
```

### 5.3. Recálculo Automático

Quando o valor total da ordem muda, as alocações são recalculadas mantendo as porcentagens:

```php
// OrderPaymentAllocationService::recalculate()
$newValue = round($newTotal * ($percentage / 100), 2);
```

---

## 6. Relatórios (8 tipos)

| Código | Nome | Descrição |
|--------|------|-----------|
| R1 | Parcelas Vencidas | Parcelas não pagas com vencimento passado |
| R2 | Próximas Parcelas | Parcelas a vencer nos próximos N dias |
| R3 | Fluxo Mensal | Fluxo de pagamentos por mês/status |
| R4 | SLA de Resolução | Tempo médio entre transições de status |
| R5 | Gasto por Área | Gasto por área/centro de custo |
| R6 | Top Fornecedores | Top N fornecedores por valor |
| R7 | Por Tipo Pagamento | Gasto por forma de pagamento |
| R8 | Orçamento vs Real | Comparação orçamento planejado vs executado |

### 6.1. Exportação

- **Excel**: PhpSpreadsheet com headers bold, colunas auto-sized
- **Impressão**: Layout dedicado `d-print-block` com tabela simplificada

---

## 7. Dashboard

4 gráficos Chart.js em modal:

1. **Distribuição por Status** (Doughnut) — % de ordens por status
2. **Top Áreas** (Horizontal Bar) — Áreas com maior gasto
3. **Fluxo Mensal** (Line) — Evolução mensal de valores
4. **Top Fornecedores** (Horizontal Bar) — Fornecedores com maior volume

Filtros: data início/fim aplicados a todos os gráficos.

---

## 8. Padrões de Código

### 8.1. Controller Principal (Match Expression Router)

```php
public function list(int|string|null $PageId = null): void
{
    $type = filter_input(INPUT_GET, 'typeorderpayment', FILTER_VALIDATE_INT);

    match ($type) {
        1 => $this->listAllOrderPayments(),      // Lista completa
        2 => $this->searchOrderPayments(),        // Busca com filtros
        3 => $this->loadMoreColumn(),             // Paginação Kanban
        4 => $this->dashboardData(),              // Dados do dashboard
        default => $this->loadInitialPage(),      // Página inicial
    };
}
```

### 8.2. Controller de Ação com Detecção AJAX

```php
public function create(): void
{
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // ... processamento ...

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => false, 'msg' => 'Sucesso', 'data' => [...]]);
        return;
    }

    // Fallback: redirect com flash message
    $url = URLADMS . 'order-payments/list';
    header("Location: $url");
}
```

### 8.3. Model com Transação (PDO)

```php
private function insertOrder(): void
{
    $conn = AdmsConn::getConn();

    try {
        $conn->beginTransaction();

        // 1. Inserir ordem
        $create = new AdmsCreate();
        $create->exeCreate('adms_order_payments', $this->Datas);

        // 2. Inserir parcelas
        $this->insertInstallments();

        // 3. Inserir alocações
        $this->insertAllocations();

        // 4. Registrar status inicial
        $this->recordInitialStatus();

        // 5. Upload de arquivo
        $this->valArquivo();

        $conn->commit();
        $this->Result = true;

    } catch (\Exception $e) {
        $conn->rollBack();
        LoggerService::error('ORDER_PAYMENT_CREATE_TRANSACTION_FAILED', $e->getMessage());
        $this->Result = false;
    }
}
```

### 8.4. Constantes de Status (Class Constants)

```php
class OrderPaymentStatus
{
    public const BACKLOG = 1;    // Solicitação
    public const DOING   = 2;    // Reg. Fiscal
    public const WAITING = 3;    // Lançado
    public const DONE    = 4;    // Pago

    public const ALL = [1, 2, 3, 4];
    public const MIN = 1;
    public const MAX = 4;

    public static function getName(int $statusId): string
    {
        return match ($statusId) {
            self::BACKLOG => 'Solicitação',
            self::DOING   => 'Reg. Fiscal',
            self::WAITING => 'Lançado',
            self::DONE    => 'Pago',
            default       => 'Desconhecido',
        };
    }
}
```

### 8.5. Service de Transição (State Machine)

```php
public function validateTransition(int $from, int $to, array $data, int $paymentTypeId): array
{
    // 1. Verifica se transição é permitida
    $allowed = self::TRANSITIONS[$from] ?? [];
    if (!in_array($to, $allowed)) {
        return ['valid' => false, 'errors' => ["Transição $from → $to não é permitida"]];
    }

    // 2. Valida campos obrigatórios
    $key = "{$from}_{$to}";
    $required = self::REQUIRED_FIELDS[$key] ?? [];
    $errors = [];

    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = "Campo '{$field}' é obrigatório para esta transição";
        }
    }

    // 3. Valida campos condicionais (PIX, banco, boleto)
    if (isset(self::CONDITIONAL_FIELDS[$key])) {
        $conditionalFields = $this->resolveConditionalFields(
            self::CONDITIONAL_FIELDS[$key], $paymentTypeId
        );
        foreach ($conditionalFields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Campo '{$field}' é obrigatório";
            }
        }
    }

    return ['valid' => empty($errors), 'errors' => $errors];
}
```

### 8.6. JavaScript: Kanban Drag-and-Drop

```javascript
function initKanbanDragDrop() {
    document.querySelectorAll('.kanban-column-body').forEach(column => {
        new Sortable(column, {
            group: 'kanban-order-payments',
            animation: 150,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            filter: '.dropdown-menu, .btn',
            onEnd: function(evt) {
                const orderId = evt.item.dataset.orderId;
                const fromStatus = parseInt(evt.from.dataset.statusId);
                const toStatus = parseInt(evt.to.dataset.statusId);

                if (fromStatus === toStatus) return;

                // Apenas transições adjacentes
                if (Math.abs(toStatus - fromStatus) !== 1) {
                    showFormError('Apenas transições adjacentes são permitidas');
                    return;
                }

                if (toStatus > fromStatus) {
                    openTransitionModal(orderId, fromStatus, toStatus);
                } else {
                    openReturnModal(orderId, fromStatus, toStatus);
                }
            }
        });
    });
}
```

### 8.7. JavaScript: CSRF Token Refresh

```javascript
async function refreshCsrfToken() {
    const response = await fetch(window.location.href, { method: 'GET' });
    const html = await response.text();
    const match = html.match(/name="_csrf_token"\s+value="([^"]+)"/);
    if (match) {
        document.querySelectorAll('input[name="_csrf_token"]').forEach(
            input => input.value = match[1]
        );
        return match[1];
    }
    return null;
}

// Uso: retry automático em 403
if (response.status === 403 && !isRetry) {
    const newToken = await refreshCsrfToken();
    if (newToken) return performSearchWithPage(page, true);
}
```

### 8.8. Acessibilidade (ARIA)

```php
<!-- Kanban card com atributos ARIA -->
<div class="card mb-2 kanban-card"
     role="listitem"
     tabindex="0"
     aria-label="Ordem #<?= $id ?>, <?= htmlspecialchars($area) ?>, 
                 <?= htmlspecialchars($supplier) ?>, 
                 R$ <?= number_format($value, 2, ',', '.') ?>
                 <?= $isOverdue ? ', vencida' : '' ?>
                 <?= $isDeleted ? ', excluída' : '' ?>"
     data-order-id="<?= $id ?>"
     data-status-id="<?= $statusId ?>">
```

```html
<!-- Região de notificação para screen readers -->
<div id="sr-notification" class="visually-hidden" aria-live="assertive"></div>
```

### 8.9. Skeleton Loading Pattern

O módulo usa skeleton loading para feedback visual imediato durante carregamento AJAX do conteúdo Kanban.

**CSS (`personalizado.css`):**

```css
.skeleton-line {
    background: linear-gradient(90deg, #e0e0e0 25%, #f0f0f0 50%, #e0e0e0 75%);
    background-size: 200% 100%;
    animation: skeleton-pulse 1.5s ease-in-out infinite;
    border-radius: 4px;
    height: 12px;
}
.skeleton-card { pointer-events: none; }

@keyframes skeleton-pulse {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

@media (prefers-reduced-motion: reduce) {
    .skeleton-line { animation: none; background: #e0e0e0; }
}
```

**JavaScript (`order-payments.js` — `getSkeletonHtml()`):**

```javascript
function getSkeletonHtml() {
    const skeletonCard = `
        <div class="card bg-light m-1 skeleton-card" style="min-height:120px;">
            <div class="card-header p-2">
                <div class="skeleton-line" style="width:40%;height:14px;"></div>
            </div>
            <div class="card-body p-2">
                <div class="skeleton-line mb-2" style="width:70%;height:13px;"></div>
                <div class="skeleton-line mb-1" style="width:90%;height:11px;"></div>
                <div class="skeleton-line mb-1" style="width:60%;height:11px;"></div>
                <div class="skeleton-line" style="width:50%;height:11px;"></div>
            </div>
        </div>`;
    // 4 colunas com 3 cards cada, headers com cores dos status
    // bg-dark (Backlog), bg-secondary (Doing), bg-info (Waiting), bg-success (Done)
}
```

**Fluxo de uso (`listOrderPayments()`):**

```javascript
// 1. Mostrar skeleton IMEDIATAMENTE
contentContainer.innerHTML = getSkeletonHtml();

// 2. Fetch AJAX
const response = await fetchWithTimeout(url);
const htmlContent = await response.text();

// 3. Substituir skeleton pelo conteúdo real
contentContainer.innerHTML = htmlContent;

// 4. Reinicializar handlers (innerHTML destrói event listeners)
initKanbanDragDrop();
adjustPaginationLinks();
```

**Hierarquia de indicadores no módulo:**

| Componente | Indicador | Implementação |
|------------|-----------|---------------|
| Kanban board completo | **Skeleton** | `getSkeletonHtml()` em `listOrderPayments()` |
| Modais (view, edit) | **Spinner centralizado** | `loadModalContent()` com `spinner-border` |
| Botão "Carregar mais" | **Spinner inline** | `loadMoreCards()` no botão |
| KPI cards | **Fade animation** | `updateKpiValue()` com `text-muted` toggle 150ms |
| Busca com filtros | **Spinner + texto** | `performSearchWithPage()` |

### 8.10. Notificações WebSocket

```php
// Padrão de notificação em todos os controllers CRUD
private function notifyOrderPaymentCreated(int $orderId, string $storeId): void
{
    try {
        $userIds = NotificationRecipientService::resolveRecipients(
            'order_payments',
            $storeId,
            [SessionContext::getUserId()] // Excluir self
        );

        SystemNotificationService::notifyUsers(
            $userIds,
            'workflow',
            'order_payments',
            'Nova Ordem de Pagamento',
            "Ordem #{$orderId} criada por " . SessionContext::getUserName(),
            [
                'icon' => 'fa-file-invoice-dollar',
                'color' => 'success',
                'action_url' => URLADMS . "order-payments/list"
            ]
        );
    } catch (\Exception $e) {
        // Fire-and-forget: nunca bloquear a operação principal
        LoggerService::warning('NOTIFICATION_FAILED', $e->getMessage());
    }
}
```

---

## 9. Banco de Dados

### 9.1. Tabelas

| Tabela | Propósito |
|--------|-----------|
| `adms_order_payments` | Tabela principal (30+ colunas) |
| `adms_installments` | Parcelas de pagamento |
| `adms_order_payment_allocations` | Rateio por centro de custo |
| `adms_order_payment_status_history` | Histórico de transições |
| `adms_sits_order_payments` | Lookup de status |

### 9.2. Colunas Principais (adms_order_payments)

```sql
-- Core
id, adms_area_id, adms_cost_center_id, adms_brand_id, adms_supplier_id,
adms_type_payment_id, total_value, description, date_payment, manager_id,
adms_sits_order_pay_id (default 1)

-- Bancário
bank_id, agency, checking_account, adms_type_key_pix_id, key_pix

-- Controle
number_nf, launch_number, proof, payment_prepared, installments,
advance, advance_amount, diff_payment_advance, date_paid

-- Auditoria
adms_user_id, update_user_id, created, modified, created_date, obs

-- Soft-delete
deleted_at, deleted_by_user_id, delete_reason

-- Integração
adms_store_id, management_reason_id, has_allocation, source_module, source_id
```

### 9.3. Índices de Performance

```sql
-- Compostos para queries Kanban
idx_op_status_deleted_date (adms_sits_order_pay_id, deleted_at, date_payment)
idx_op_status_deleted_total (adms_sits_order_pay_id, deleted_at, total_value)

-- Individuais
idx_store (adms_store_id)
idx_management_reason (management_reason_id)
idx_date_paid (date_paid)
idx_deleted_at (deleted_at)
```

---

## 10. Testes

### 10.1. Cobertura

| Arquivo de Teste | Testes | Tipo |
|------------------|--------|------|
| AllocationServiceTest | 14 | Validação + DB CRUD |
| DeleteServiceTest | 15 | Lógica pura (sem DB) |
| TransitionServiceTest | 27 | Validação + DB |
| AccessibilityTest | 16+ | ARIA + HTML output |
| WorkflowIntegrationTest | ~5 | Fluxo completo (DB) |
| ControllerTest | 7+ | Estrutura/reflexão |

### 10.2. Padrão de Teste (Lógica Pura)

```php
#[Test]
public function level2FinancialBacklog(): void
{
    $order = ['adms_sits_order_pay_id' => 1, 'adms_user_id' => 10, 'modified' => '2026-01-01'];
    $result = $this->service->canDelete($order, userId: 99, userLevel: 5);

    $this->assertTrue($result['allowed']);
    $this->assertTrue($result['requireReason']);
    $this->assertFalse($result['requireConfirmation']);
    $this->assertEquals(2, $result['level']);
}
```

### 10.3. Padrão de Teste (Integração DB)

```php
#[Test]
public function executeTransitionUpdatesStatusInDb(): void
{
    $orderId = $this->createTestOrder();

    $result = $this->service->executeTransition(
        $orderId, fromStatusId: 1, newStatusId: 2,
        fields: ['number_nf' => '123', 'launch_number' => '456'],
        userId: 1, notes: 'Test transition'
    );

    $this->assertTrue($result);
    $this->assertEquals(2, $this->getOrderStatus($orderId));
}
```

### 10.4. Padrão de Teste (Acessibilidade)

```php
#[Test]
public function kanbanCardHasAriaLabelWithOrderId(): void
{
    $html = $this->renderKanbanCard($this->buildOrderData('bk', [
        'bk_id' => 42,
    ]), 'bk', 'bg-dark', 1, ['view_payment' => true]);

    $this->assertMatchesRegularExpression('/aria-label="[^"]*Ordem #42/', $html);
}
```

---

## 11. Padrões Responsivos

### 11.1. Kanban

```
Mobile (<768px):   Colunas empilhadas (col-12) + Tab bar de seleção
Tablet (≥768px):   Grid 2×2 (col-md-6)
Desktop (≥992px):  4 colunas em linha (col-lg-3)
```

### 11.2. Botões

```
Desktop (≥768px):  Botões individuais (d-none d-md-inline)
Mobile (<768px):   Dropdown menu compacto (d-block d-md-none)
```

### 11.3. KPI Cards

```html
<div class="col-6 col-sm-3 mb-2">
    <!-- Mobile: 2 cards por linha | Desktop: 4 cards por linha -->
</div>
```

---

## 12. Validações

### 12.1. PIX

| Tipo Chave | Validação |
|------------|-----------|
| CPF (1) | 11 dígitos + algoritmo mod 11 |
| CNPJ (1) | 14 dígitos + algoritmo mod 11 |
| Email (2) | FILTER_VALIDATE_EMAIL |
| Telefone (3) | 11 dígitos (DDD + 9 dígitos) |
| Aleatória (4) | Qualquer valor não vazio |

### 12.2. Parcelas (Boleto Parcelado)

- Quantidade: 1-12 parcelas
- Cada parcela: valor obrigatório + data de vencimento obrigatória
- Soma dos valores deve corresponder ao valor total

### 12.3. Datas de Pagamento (Regras por Nível)

| Nível | Restrição |
|-------|-----------|
| Super Admin (1) | Sem restrição |
| Financial/Admin (2, 9, 15) | Até 60 dias no passado |
| Usuário comum | 7 dias no futuro + regra de quarta-feira 12h |

---

## 13. API REST (V1)

### 13.1. Endpoints

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/v1/order-payments` | Listar com paginação e filtros |
| GET | `/api/v1/order-payments/{id}` | Detalhe da ordem |
| GET | `/api/v1/order-payments/statistics` | Estatísticas/KPIs |
| GET | `/api/v1/order-payments/{id}/history` | Histórico de transições |
| GET | `/api/v1/order-payments/{id}/installments` | Parcelas |
| POST | `/api/v1/order-payments` | Criar ordem |
| PUT | `/api/v1/order-payments/{id}` | Atualizar ordem |
| DELETE | `/api/v1/order-payments/{id}` | Soft-delete |
| POST | `/api/v1/order-payments/{id}/transition` | Transição de status |
| POST | `/api/v1/order-payments/{id}/installments/{instId}/mark-paid` | Marcar parcela paga |

### 13.2. Formato de Resposta

```json
// Sucesso
{ "success": true, "data": {...}, "message": "..." }

// Erro
{ "success": false, "error": "mensagem", "code": 422, "error_code": "VALIDATION_ERROR" }

// Paginado
{ "success": true, "data": [...], "pagination": { "page": 1, "per_page": 20, "total": 150 } }
```

---

## 14. Quando Usar Como Referência

Use o módulo **OrderPayments** como referência quando o novo módulo precisar de:

| Necessidade | Arquivo de Referência |
|-------------|----------------------|
| Workflow Kanban com drag-and-drop | `OrderPayments.php` + `order-payments.js` |
| State machine de status | `OrderPaymentTransitionService.php` |
| Soft-delete com níveis | `OrderPaymentDeleteService.php` |
| Rateio/alocação de custos | `OrderPaymentAllocationService.php` |
| Relatórios financeiros | `ReportOrderPayments.php` + `AdmsReportOrderPayments.php` |
| Dashboard com gráficos | `_dashboard_modal.php` + `order-payments.js` |
| API REST completa | `Api/V1/OrderPaymentsController.php` |
| Parcelas/installments | `AdmsAddOrderPayment.php` (seção installments) |
| Upload de arquivos | `DownloadOrderPaymentFiles.php` |
| Acessibilidade completa | `_kanban_card.php` + `OrderPaymentAccessibilityTest.php` |
| Testes de integração | `OrderPaymentWorkflowIntegrationTest.php` |

Para módulos **mais simples** (CRUD básico), use **Sales** como referência.
Para módulos de **configuração/lookup**, use **AbstractConfigController**.

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola  
**Versão:** 1.0  
**Última Atualização:** 04/04/2026
