# Analise do Modulo Order Control (Ordens de Compra)

**Data:** 26 de Janeiro de 2026
**Versao:** 1.0
**Status:** Legado - Candidato a Modernizacao

---

## 1. Visao Geral

O modulo **Order Control** gerencia ordens de compra no sistema Mercury. Atualmente utiliza padroes legados e esta candidato a modernizacao seguindo os padroes definidos em `REGRAS_DESENVOLVIMENTO.md`.

### 1.1. Arquivos do Modulo

#### Controllers Existentes

| Arquivo | Descricao |
|---------|-----------|
| `OrderControl.php` | Listagem principal |
| `AddOrderControl.php` | Adicionar ordem |
| `EditOrderControl.php` | Editar ordem |
| `ViewOrderControl.php` | Visualizar ordem |
| `DeleteOrderControl.php` | Deletar ordem |
| `AddOrderControlItems.php` | Adicionar itens da ordem |
| `EditOrderControlItem.php` | Editar item da ordem |
| `ViewOrderControlItem.php` | Visualizar item |
| `DeleteOrderControlItem.php` | Deletar item individual |
| `DeleteOrderControlItems.php` | Deletar todos itens da ordem |

#### Controllers a Criar (Modernizacao)

| Arquivo | Descricao | Service |
|---------|-----------|---------|
| `ExportOrderControl.php` | Exportar ordens para Excel | ExportService |
| `ImportOrderControl.php` | Importar itens de planilha | ImportService |
| `UploadOrderControlInvoice.php` | Upload de notas fiscais | FileUploadService |

#### Models - Ordem Principal

| Arquivo | Descricao |
|---------|-----------|
| `AdmsListOrderControl.php` | Listagem com paginacao |
| `AdmsAddOrderControl.php` | Criar ordem |
| `AdmsEditOrderControl.php` | Editar ordem |
| `AdmsViewOrderControl.php` | Visualizar ordem + itens |
| `AdmsDeleteOrderControl.php` | Deletar ordem |
| `CpAdmsSearchOrderControl.php` | Busca com filtros |

#### Models - Itens da Ordem

| Arquivo | Descricao |
|---------|-----------|
| `AdmsAddOrderControlItems.php` | Criar item |
| `AdmsEditOrderControlItem.php` | Editar item |
| `AdmsViewOrderControlItem.php` | Visualizar item |
| `AdmsDeleteOrderControlItem.php` | Deletar item individual |
| `AdmsDeleteOrderControlItems.php` | Deletar todos itens |

#### Views

| Arquivo | Descricao |
|---------|-----------|
| `listOrderControl.php` | View unica (load + list) |
| `addOrderControl.php` | Formulario de adicao |
| `editOrderControl.php` | Formulario de edicao |
| `viewOrderControl.php` | Visualizacao de detalhes |
| `listOrderControlItems.php` | Listagem de itens |
| `editOrderControlItem.php` | Formulario de edicao de item |
| `viewOrderControlItem.php` | Visualizacao de item |
| `priceProduct.php` | Precificacao de produto |

#### JavaScript

| Arquivo | Status |
|---------|--------|
| `order-control.js` | **Nao existe** - Sem arquivo JS dedicado |

---

## 2. Comparacao com Padroes de Desenvolvimento

### 2.1. Controller Principal

| Aspecto | Padrao Moderno | OrderControl Atual | Status |
|---------|---------------|-------------------|--------|
| Match expression | `match($type) { 1 => ..., 2 => ... }` | Nao utiliza | :x: |
| Type hints | `public function list(int\|string\|null $pageId): void` | `public function list(int\|null $PageId)` | :warning: Parcial |
| Return type | `: void` em todos os metodos | Ausente | :x: |
| Camel case | `$this->data`, `$pageId` | `$this->Dados`, `$PageId` | :x: |
| Imports com `use` | `use App\adms\Models\...` | `new \App\...` inline | :x: |
| Metodos privados | `loadButtons()`, `loadStats()` | Inline no metodo `list()` | :x: |
| AJAX routing | `typeentity` parameter | Nao utiliza | :x: |

**Codigo Atual:**
```php
class OrderControl {
    private array|null $Dados;
    private int|null $PageId;

    public function list(int|null $PageId = null) {
        $this->PageId = $PageId ? $PageId : 1;
        // ... codigo inline
    }
}
```

**Padrao Moderno:**
```php
class OrderControl
{
    private ?array $data = [];
    private int $pageId = 1;

    public function list(int|string|null $pageId = null): void
    {
        $this->pageId = (int) ($pageId ?: 1);
        $requestType = filter_input(INPUT_GET, 'typeorder', FILTER_VALIDATE_INT);

        match ($requestType) {
            1 => $this->listAll(),
            2 => $this->searchOrders(),
            default => $this->loadInitialPage(),
        };
    }
}
```

### 2.2. Models de Listagem e CRUD

#### AdmsListOrderControl

| Aspecto | Padrao Moderno | Atual | Status |
|---------|---------------|-------|--------|
| Return type hints | `: ?array`, `: ?string` | Ausente | :x: |
| Property types | `private ?array $result = null` | `private array\|int\|null $Result` | :warning: |
| Camel case | `$result`, `$pageId` | `$Result`, `$PageId` | :x: |
| Metodo getter | `getResultPg(): ?string` | `function getResult()` | :x: |
| Query formatting | Multiline, legivel | Single line | :x: |
| Error handling | try/catch | Ausente | :x: |

#### AdmsAddOrderControl / AdmsEditOrderControl

| Aspecto | Padrao Moderno | Atual | Status |
|---------|---------------|-------|--------|
| NotificationService | `$this->notification->success()` | `$_SESSION['msg'] = "<div>..."` | :x: |
| LoggerService | `LoggerService::info('CREATED')` | Ausente | :x: |
| FormSelectRepository | `$selects->getBrands()` | Query manual em `listAdd()` | :x: |
| Return type hints | `: bool`, `: ?array` | Ausente | :x: |
| Imports com use | `use App\adms\Models\helper\...` | `new \App\adms\...` inline | :x: |
| Validacao | ValidatorService | AdmsCampoVazio | :warning: |

#### AdmsDeleteOrderControl / AdmsDeleteOrderControlItem(s)

| Aspecto | Padrao Moderno | Atual | Status |
|---------|---------------|-------|--------|
| Confirmacao | Modal AJAX com confirmacao | Redirect direto | :x: |
| NotificationService | `$this->notification->success()` | `$_SESSION['msg']` | :x: |
| LoggerService | `LoggerService::info('DELETED', data)` | Ausente | :x: |
| Soft delete | `deleted_at` timestamp | Hard delete | :warning: |

#### AdmsViewOrderControl / AdmsViewOrderControlItem

| Aspecto | Padrao Moderno | Atual | Status |
|---------|---------------|-------|--------|
| Return type | `: ?array` | `?array` (parcial) | :warning: |
| Query formatting | Multiline | Single line (dificil leitura) | :x: |
| PHPDoc | Documentacao completa | Parcial | :warning: |

**Query Atual (dificil leitura):**
```php
$listOrder->fullRead("SELECT oc.id oc_id, oc.short_description, oc.seasons, oc.colletions, b.nome brand, st.description_name sits, c.cor FROM adms_purchase_order_controls oc LEFT JOIN adms_marcas b ON b.id = oc.adms_brand_id LEFT JOIN adms_sits_orders st ON st.id = oc.adms_sits_order_id LEFT JOIN adms_cors c ON c.id = st.adms_cor_id ORDER BY oc.id DESC LIMIT :limit OFFSET :offset", "limit={$this->LimitResult}&offset={$pagination->getOffset()}");
```

**Padrao Moderno (formatado):**
```php
$query = "SELECT
        oc.id AS oc_id,
        oc.short_description,
        oc.seasons,
        oc.colletions,
        b.nome AS brand,
        st.description_name AS sits,
        c.cor
    FROM adms_purchase_order_controls oc
    LEFT JOIN adms_marcas b ON b.id = oc.adms_brand_id
    LEFT JOIN adms_sits_orders st ON st.id = oc.adms_sits_order_id
    LEFT JOIN adms_cors c ON c.id = st.adms_cor_id
    ORDER BY oc.id DESC
    LIMIT :limit OFFSET :offset";

$listOrder->fullRead($query, "limit={$this->limitResult}&offset={$pagination->getOffset()}");
```

### 2.3. Views

| Aspecto | Padrao Moderno | OrderControl Atual | Status |
|---------|---------------|-------------------|--------|
| Separacao load/list | `loadEntityName.php` + `listEntityName.php` | Arquivo unico | :x: |
| Modais em partials | `partials/_view_entity_modal.php` | Paginas separadas (full reload) | :x: |
| Container AJAX | `<div id="content_entity">` | Nao utiliza | :x: |
| Formulario busca | AJAX POST com JavaScript | Form tradicional com action URL | :x: |
| Responsividade | Classes Bootstrap 4 completas | Parcial | :warning: |

**View Atual (form tradicional):**
```php
<form method="POST" action="<?= URLADM ?>search-order-control/search">
    <!-- ... -->
    <input type="submit" value="Pesquisar">
</form>
```

**Padrao Moderno (AJAX):**
```php
<form id="searchOrderForm">
    <!-- ... -->
    <button type="button" onclick="performSearch()">Pesquisar</button>
</form>
<div id="content_order">
    <!-- Conteudo carregado via AJAX -->
</div>
```

### 2.4. JavaScript

| Aspecto | Padrao Moderno | OrderControl Atual | Status |
|---------|---------------|-------------------|--------|
| Arquivo dedicado | `assets/js/order-control.js` | **Nao existe** | :x: |
| AJAX/Fetch API | async/await com fetch | Full page reload | :x: |
| Event delegation | `container.addEventListener('click', ...)` | Inline onclick | :x: |
| Paginacao AJAX | `data-page` attributes | Links tradicionais | :x: |
| Modais | Bootstrap modal + AJAX content | Paginas separadas | :x: |

### 2.5. Model de Busca

| Aspecto | Padrao Moderno | CpAdmsSearchOrderControl | Status |
|---------|---------------|--------------------------|--------|
| Uso de SESSION | Evitar, usar parametros | Salva em $_SESSION | :x: |
| Mensagens de erro | Via NotificationService | HTML inline em $_SESSION['msg'] | :x: |
| Metodos organizados | Responsabilidades claras | Bom (buildWhereAndParams) | :white_check_mark: |

**Problema 1: Uso de SESSION para persistir busca:**
```php
// Atual - problematico
$_SESSION['searchOrderControl'] = trim($this->Dados['searchOrderControl'] ?? '');
$_SESSION['msg'] = "<div class='alert alert-danger'>...</div>";

// Moderno - usar parametros e NotificationService
$this->notification->error('Nenhuma ordem encontrada');
```

**Problema 2: Codigo de debug em producao (AdmsAddOrderControlItems.php:27):**
```php
// ❌ ERRO: var_dump em producao
public function addItems(array $Data) {
    $this->Data = $Data;
    var_dump($this->Data);  // REMOVER!
    // ...
}
```

**Problema 3: Mensagens HTML hardcoded em todos os models:**
```php
// ❌ Atual - HTML inline em todos os models
$_SESSION['msg'] = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
    <strong>Ordem de compra</strong> cadastrada com sucesso!
    <button type='button' class='close' data-dismiss='alert' aria-label='Close'>
        <span aria-hidden='true'>&times;</span>
    </button>
</div>";

// ✅ Moderno - NotificationService
$this->notification->success('Ordem de compra cadastrada com sucesso!');
```

---

## 3. Score de Modernizacao

| Criterio | Peso | Score (0-10) | Pontos |
|----------|------|-------------|--------|
| Type hints completos | 10% | 4 | 0.40 |
| Match expression | 7% | 0 | 0.00 |
| AJAX/Modais | 12% | 0 | 0.00 |
| Separacao Views | 7% | 0 | 0.00 |
| JavaScript dedicado | 10% | 0 | 0.00 |
| Naming conventions | 5% | 4 | 0.20 |
| NotificationService | 8% | 0 | 0.00 |
| LoggerService | 8% | 0 | 0.00 |
| FormSelectRepository | 8% | 0 | 0.00 |
| ExportService | 5% | 0 | 0.00 |
| ImportService | 5% | 0 | 0.00 |
| FileUploadService | 5% | 0 | 0.00 |
| Error handling | 5% | 3 | 0.15 |
| Codigo limpo (sem debug) | 5% | 0 | 0.00 |
| **TOTAL** | 100% | - | **0.75/10** |

**Classificacao:** Legado Critico - Prioridade Alta para Modernizacao

### 3.1. Problemas Criticos Encontrados

1. **`var_dump()` em producao** - `AdmsAddOrderControlItems.php` linha 27
2. **HTML hardcoded em `$_SESSION['msg']`** - Todos os models CRUD
3. **Sem LoggerService** - Nenhuma operacao e auditada
4. **Sem NotificationService** - Mensagens via SESSION
5. **Full page reload** - Todas as operacoes causam reload
6. **Queries em linha unica** - Dificil manutencao e leitura

---

## 4. Sugestoes de Melhoria

### 4.1. Alta Prioridade

#### 1. Implementar Match Expression no Controller
```php
public function list(int|string|null $pageId = null): void
{
    $this->pageId = (int) ($pageId ?: 1);
    $requestType = filter_input(INPUT_GET, 'typeorder', FILTER_VALIDATE_INT);

    match ($requestType) {
        1 => $this->listAll(),
        2 => $this->searchOrders(),
        default => $this->loadInitialPage(),
    };
}
```

#### 2. Separar Views (load + list)
- Criar `loadOrderControl.php` (pagina principal com filtros)
- Manter `listOrderControl.php` (tabela AJAX)
- Criar `partials/_view_order_control_modal.php` (modal de visualizacao)
- Criar `partials/_delete_order_control_modal.php` (modal de exclusao)

#### 3. Criar JavaScript Dedicado
```javascript
// assets/js/order-control.js
(function() {
    'use strict';

    const container = document.getElementById('content_order');
    const config = document.getElementById('order-config');
    const URL_BASE = config?.dataset?.urlBase || '';

    // Auto-load on init
    listOrders(1);

    async function listOrders(page = 1) {
        const response = await fetch(`${URL_BASE}order-control/list/${page}?typeorder=1`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        container.innerHTML = await response.text();
    }

    async function searchOrders(page = 1) {
        const formData = new FormData(document.getElementById('searchOrderForm'));
        const response = await fetch(`${URL_BASE}order-control/list/${page}?typeorder=2`, {
            method: 'POST',
            body: formData
        });
        container.innerHTML = await response.text();
    }

    window.listOrders = listOrders;
    window.searchOrders = searchOrders;
})();
```

#### 4. Remover Uso de SESSION para Busca
```php
// Em vez de salvar em SESSION
private function searchOrders(array $searchData): void
{
    $searchModel = new CpAdmsSearchOrderControl();
    $this->data['list_order_control'] = $searchModel->search($searchData, $this->pageId);
    // ...
}
```

### 4.2. Media Prioridade

#### 5. Remover var_dump de Producao
```php
// AdmsAddOrderControlItems.php - REMOVER linha 27
public function addItems(array $Data) {
    $this->Data = $Data;
    // var_dump($this->Data);  // REMOVER ESTA LINHA!
    // ...
}
```

#### 6. Implementar NotificationService em Todos os Models
```php
// Antes (todos os models CRUD)
$_SESSION['msg'] = "<div class='alert alert-success'>...</div>";

// Depois
use App\adms\Services\NotificationService;

private NotificationService $notification;

public function __construct()
{
    $this->notification = new NotificationService();
}

// No metodo
$this->notification->success('Ordem de compra cadastrada com sucesso!');
```

#### 7. Implementar LoggerService para Auditoria
```php
use App\adms\Services\LoggerService;

// Em AdmsAddOrderControl
LoggerService::info('ORDER_CONTROL_CREATED', 'Nova ordem criada', [
    'order_id' => $orderId,
    'user_id' => $_SESSION['usuario_id']
]);

// Em AdmsDeleteOrderControl
LoggerService::info('ORDER_CONTROL_DELETED', 'Ordem excluida', [
    'order_id' => $this->DadosId,
    'user_id' => $_SESSION['usuario_id']
]);
```

#### 8. Adicionar Type Hints e Return Types
```php
public function list(int|null $pageId = null): ?array
{
    // ...
}

public function getResultPg(): ?string
{
    return $this->resultPg;
}
```

#### 9. Formatar Queries SQL
```php
$query = "SELECT
        oc.id AS oc_id,
        oc.short_description,
        oc.seasons,
        oc.colletions,
        b.nome AS brand
    FROM adms_purchase_order_controls oc
    LEFT JOIN adms_marcas b ON b.id = oc.adms_brand_id
    ORDER BY oc.id DESC
    LIMIT :limit OFFSET :offset";
```

#### 10. Usar Imports com `use`
```php
use App\adms\Models\AdmsBotao;
use App\adms\Models\AdmsMenu;
use App\adms\Models\AdmsListOrderControl;
use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsCreate;
use App\adms\Models\helper\AdmsUpdate;
use App\adms\Models\helper\AdmsDelete;
use App\adms\Services\FormSelectRepository;
use App\adms\Services\NotificationService;
use App\adms\Services\LoggerService;
use App\adms\Services\ExportService;
use App\adms\Services\ImportService;
use App\adms\Services\FileUploadService;
use Core\ConfigView;
```

#### 11. Usar FormSelectRepository para Selects
```php
// Antes - queries manuais em listAdd()
$list->fullRead("SELECT id b_id, nome brand_name FROM adms_marcas WHERE status_id =:sits", "sits=1");
$list->fullRead("SELECT id s_id, nome store_name FROM tb_lojas WHERE status_id =:sits", "sits=1");

// Depois - FormSelectRepository centralizado
use App\adms\Services\FormSelectRepository;

private function loadSelects(): array
{
    $selects = new FormSelectRepository();
    return [
        'brands' => $selects->getBrands(),
        'stores' => $selects->getStores(),
        'order_status' => $selects->getOrderStatus(),
    ];
}
```

#### 12. Usar ExportService para Exportacao
```php
// ExportOrderControl.php
use App\adms\Services\ExportService;

public function export(): void
{
    $data = $this->getOrdersData();

    $export = new ExportService();
    $export->setFilename('ordens_compra_' . date('Y-m-d'));
    $export->setHeaders(['ID', 'Descricao', 'Marca', 'Loja', 'Status']);
    $export->exportToExcel($data);
}
```

#### 13. Usar ImportService para Importacao
```php
// ImportOrderControl.php
use App\adms\Services\ImportService;

public function import(): void
{
    $import = new ImportService();
    $import->setRequiredColumns(['referencia', 'tamanho', 'quantidade', 'custo']);

    $result = $import->processUpload($_FILES['file']);

    if ($result->hasErrors()) {
        $this->notification->error($result->getErrorMessage());
        return;
    }

    $this->processImportedData($result->getData());
}
```

#### 14. Usar FileUploadService para Anexos
```php
// Upload de nota fiscal ou documentos
use App\adms\Services\FileUploadService;
use App\adms\Services\UploadConfig;

public function uploadInvoice(): void
{
    $config = new UploadConfig();
    $config->setAllowedExtensions(['pdf', 'jpg', 'png']);
    $config->setMaxSize(5 * 1024 * 1024); // 5MB
    $config->setUploadPath('uploads/orders/invoices/');

    $upload = new FileUploadService($config);
    $result = $upload->upload($_FILES['invoice']);

    if ($result->isSuccess()) {
        $this->saveInvoicePath($orderId, $result->getFilePath());
        $this->notification->success('Nota fiscal anexada com sucesso!');
    } else {
        $this->notification->error($result->getErrorMessage());
    }
}
```

### 4.3. Baixa Prioridade

#### 11. Padronizar Naming (camelCase)
- `$this->Dados` -> `$this->data`
- `$PageId` -> `$pageId`
- `$Result` -> `$result`
- `$ResultPg` -> `$resultPg`
- `$DadosId` -> `$dataId`
- `$Empty` -> `$emptyFields`

#### 12. Adicionar Error Handling com try/catch
```php
try {
    $read->fullRead($query, $params);
    $this->result = $read->getResult();
} catch (\PDOException $e) {
    LoggerService::error('ORDER_LIST_FAILED', $e->getMessage());
    $this->result = null;
}
```

#### 13. Corrigir URL typo em CpAdmsSearchOrderControl
```php
// Linha 42 - "constrol" -> "control"
$paginacao = new AdmsPaginacao(URLADM . 'search-order-control/search', $urlParams);
//                                              ^^^^^^^^ corrigir typo
```

#### 14. Corrigir listAdd() em AdmsAddOrderControlItems
```php
// O metodo listAdd() retorna dados de usuarios/niveis de acesso
// em vez de dados relevantes para itens de ordem de compra
// Isso parece ser codigo copiado de outro modelo
```

---

## 5. Plano de Modernizacao Sugerido

### Fase 1: Estrutura Basica (Referencia: Sales)
1. Criar `loadOrderControl.php` separando da listagem (ver `loadSales.php`)
2. Criar `assets/js/order-control.js` (ver `sales.js`)
3. Adicionar container AJAX `#content_order`

### Fase 2: Controller (Referencia: Sales.php)
1. Implementar match expression
2. Adicionar type hints e return types
3. Separar metodos (loadButtons, loadInitialPage, listAll, searchOrders)
4. Usar imports com `use`

### Fase 3: Models e Services
1. Adicionar type hints completos
2. Formatar queries SQL
3. Remover uso de SESSION em CpAdmsSearchOrderControl
4. Adicionar error handling com try/catch
5. Substituir `listAdd()` por FormSelectRepository
6. Implementar NotificationService em todos os models
7. Implementar LoggerService para auditoria

### Fase 4: Views e Modais (Referencia: StoreGoals)
1. Converter para AJAX
2. Criar `partials/_view_order_control_modal.php`
3. Criar `partials/_delete_order_control_modal.php`
4. Implementar paginacao AJAX com `data-page`

### Fase 5: Export/Import/Upload
1. Criar `ExportOrderControl.php` com ExportService
2. Criar `ImportOrderControl.php` com ImportService
3. Criar `UploadOrderControlInvoice.php` com FileUploadService
4. Adicionar botoes na view para export/import

### Fase 6: Finalizacao
1. Testes completos do fluxo CRUD
2. Testes de export/import
3. Remover arquivos legados (se aplicavel)
4. Atualizar documentacao

---

## 6. Arquivos de Referencia

Para modernizacao, utilize como referencia os modulos com CRUD completo:

### 6.1. Modulo Sales (Vendas) - Referencia Principal

| Tipo | Arquivo | Descricao |
|------|---------|-----------|
| Controller | `app/adms/Controllers/Sales.php` | Controller com match expression e estatisticas |
| Controller | `app/adms/Controllers/AddSales.php` | Adicionar venda |
| Controller | `app/adms/Controllers/EditSales.php` | Editar venda |
| Controller | `app/adms/Controllers/ConfirmSales.php` | Confirmar venda |
| View | `app/adms/Views/sales/loadSales.php` | Pagina principal (header, filtros, container AJAX) |
| View | `app/adms/Views/sales/listSales.php` | Listagem AJAX |
| JavaScript | `assets/js/sales.js` | AJAX completo (list, search, CRUD) |

**Documentacao:** `docs/ANALISE_MODULO_SALES.md`

### 6.2. Modulo StoreGoals (Metas) - Referencia Secundaria

| Tipo | Arquivo | Descricao |
|------|---------|-----------|
| Controller | `app/adms/Controllers/StoreGoals.php` | Controller principal |
| Controller | `app/adms/Controllers/AddStoreGoals.php` | Adicionar meta |
| Controller | `app/adms/Controllers/EditStoreGoal.php` | Editar meta |
| Controller | `app/adms/Controllers/DeleteStoreGoal.php` | Deletar meta |
| Controller | `app/adms/Controllers/ViewStoreGoals.php` | Visualizar meta |
| View | `app/adms/Views/goals/loadStoreGoals.php` | Pagina principal |
| View | `app/adms/Views/goals/listStoreGoals.php` | Listagem AJAX |
| JavaScript | `assets/js/store-goals.js` | AJAX com operacoes CRUD |

### 6.3. Services Obrigatorios

| Service | Uso | Arquivo |
|---------|-----|---------|
| `FormSelectRepository` | Popular selects (marcas, lojas, status) | `app/adms/Services/FormSelectRepository.php` |
| `NotificationService` | Feedback ao usuario (sucesso/erro) | `app/adms/Services/NotificationService.php` |
| `LoggerService` | Auditoria de operacoes CRUD | `app/adms/Services/LoggerService.php` |
| `ExportService` | Exportar dados para Excel/CSV | `app/adms/Services/ExportService.php` |
| `ImportService` | Importar dados de planilhas | `app/adms/Services/ImportService.php` |
| `FileUploadService` | Upload de arquivos anexos | `app/adms/Services/FileUploadService.php` |

### 6.4. Padroes a Seguir

1. **Controller com match expression** - Ver `Sales.php` linhas 30-50
2. **Views separadas** - `loadSales.php` (estrutura) + `listSales.php` (tabela AJAX)
3. **JavaScript async/await** - `sales.js` com fetch API
4. **Modais em partials/** - Para visualizacao, edicao e exclusao
5. **FormSelectRepository** - Para popular campos select
6. **NotificationService** - Para feedback ao usuario
7. **LoggerService** - Para auditoria de operacoes
8. **ExportService** - Para exportacao de dados
9. **ImportService** - Para importacao de dados
10. **FileUploadService** - Para upload de arquivos

---

## 7. Conclusao

O modulo Order Control utiliza padroes legados que impactam:
- **Experiencia do Usuario:** Full page reload em todas operacoes
- **Manutencao:** Codigo menos legivel e organizado
- **Seguranca:** Uso de SESSION para dados de busca
- **Performance:** Recarregamento completo da pagina

A modernizacao seguindo os padroes do projeto trara beneficios significativos em todas essas areas.

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
**Referencia:** `REGRAS_DESENVOLVIMENTO.md` v2.1
