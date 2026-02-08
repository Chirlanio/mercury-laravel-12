# Mercury Project - Padrões de Codificação e Templates

Este documento contém os padrões de codificação, templates e boas práticas para o desenvolvimento no projeto Mercury.

## Índice

1. [Nomenclatura](#1-nomenclatura)
2. [Template: Controller de Listagem](#2-template-controller-de-listagem)
3. [Template: Controller de Adição](#3-template-controller-de-adição)
4. [Template: Controller de Edição](#4-template-controller-de-edição)
5. [Template: Controller de Exclusão](#5-template-controller-de-exclusão)
6. [Template: Controller de Visualização](#6-template-controller-de-visualização)
7. [Template: Model de Adição](#7-template-model-de-adição)
8. [Template: Model de Edição](#8-template-model-de-edição)
9. [Template: Model de Listagem](#9-template-model-de-listagem)
10. [Template: Service](#10-template-service)
11. [Template: Helper](#11-template-helper)
12. [Template: View de Listagem](#12-template-view-de-listagem)
13. [Template: Modal de Cadastro](#13-template-modal-de-cadastro)
14. [Template: Modal de Edição](#14-template-modal-de-edição)
15. [Template: Modal de Visualização](#15-template-modal-de-visualização)
16. [Template: Modal de Confirmação de Exclusão](#16-template-modal-de-confirmação-de-exclusão)
17. [Template: JavaScript (AJAX)](#17-template-javascript-ajax)
18. [Padrões de Database Helpers](#18-padrões-de-database-helpers)
19. [Boas Práticas](#19-boas-práticas)
20. [Padrão AbstractConfigController](#20-padrão-abstractconfigcontroller-módulos-de-configuração)
21. [Módulos de Referência](#21-módulos-de-referência)

---

## 1. Nomenclatura

### 1.1. Controllers

**Padrão**: PascalCase, nome descritivo da ação

```
Listagem:    Products, Coupons, Sales
Adição:      AddProduct, AddCoupon, AddSale
Edição:      EditProduct, EditCoupon, EditSale
Exclusão:    DeleteProduct, DeleteCoupon, DeleteSale
Visualização: ViewProduct, ViewCoupon, ViewSale
```

**URL Slug**: kebab-case (conversão automática)

```
Products       → /products/list
AddProduct     → /add-product/create
EditProduct    → /edit-product/edit
DeleteProduct  → /delete-product/delete
ViewProduct    → /view-product/view
```

### 1.2. Models

**Padrão**: `Adms` + ação + entidade

```
AdmsAddCoupon
AdmsEditCoupon
AdmsListCoupons
AdmsDeleteCoupon
AdmsViewCoupon
AdmsStatisticsCoupons
```

### 1.3. Views

**Padrão**: Organização por módulo

```
app/adms/Views/
├── coupon/
│   ├── loadCoupons.php       # View principal
│   ├── listCoupons.php       # Lista (AJAX)
│   └── partials/
│       ├── _add_coupon_modal.php
│       ├── _edit_coupon.php
│       ├── _edit_coupon_modal.php
│       └── _statistics_dashboard.php
│       └── _view_coupon_details.php
│       └── _view_coupon_modal.php
```

### 1.4. JavaScript

**Padrão**: Nome do módulo em camelCase

```
assets/js/
├── coupons.js
├── products.js
├── sales.js
```

### 1.5. Services e Helpers

**Services**: `XxxService.php`
```
NotificationService.php
LoggerService.php
AuthenticationService.php
```

**Helpers**: `XxxHelper.php`
```
FormatHelper.php
UiHelper.php
CalculationHelper.php
```

---

## 2. Template: Controller de Listagem

**Arquivo**: `app/adms/Controllers/Products.php`

```php
<?php

namespace App\adms\Controllers;

use App\adms\Services\FormSelectRepository;
use App\adms\Models\AdmsBotao;
use App\adms\Models\AdmsMenu;
use App\adms\Models\AdmsListProducts;
use App\adms\Models\AdmsStatisticsProducts;
use Core\ConfigView;
use App\cpadms\Models\CpAdmsSearchProducts;
use App\adms\Models\helper\AdmsRead;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller de Produtos
 *
 * Gerencia a listagem, busca e estatísticas de produtos
 *
 * @author Seu Nome - Grupo Meia Sola
 * @copyright (c) 2025, Grupo Meia Sola
 */
class Products {

    private ?array $data = [];
    private int $pageId;

    /**
     * Método principal de listagem
     *
     * @param int|string|null $pageId Número da página para paginação
     * @return void
     */
    public function list(int|string|null $pageId = null): void {
        $this->pageId = (int) ($pageId ?: 1);

        // Configuração de botões
        $this->loadButtons();

        // Carrega estatísticas
        $this->loadStats();

        // Obtém o tipo de requisição (listagem normal, busca, ou página inicial)
        $requestType = filter_input(INPUT_GET, 'typeproduct', FILTER_VALIDATE_INT);
        $searchData = $this->getSearchData();

        // Roteamento usando match (PHP 8+)
        match ($requestType) {
            1 => $this->listAllProducts(),
            2 => $this->searchProducts($searchData),
            default => $this->loadInitialPage(),
        };
    }

    /**
     * Carrega as estatísticas
     *
     * @return void
     */
    private function loadStats(): void {
        $stats = new AdmsStatisticsProducts();
        $this->data['stats'] = $stats->getStats();
    }

    /**
     * Carrega a página inicial com menu e formulário
     *
     * @return void
     */
    private function loadInitialPage(): void {
        $menu = new AdmsMenu();
        $this->data['menu'] = $menu->itemMenu();

        // Usando FormSelectRepository para dados dos selects
        $selects = new FormSelectRepository();
        $this->data['select'] = $selects->getProductFormData();

        $loadView = new ConfigView("adms/Views/product/loadProducts", $this->data);
        $loadView->renderizar();
    }

    /**
     * Lista todos os produtos com paginação
     *
     * @return void
     */
    private function listAllProducts(): void {
        $listProducts = new AdmsListProducts();
        $this->data['list_products'] = $listProducts->list($this->pageId);
        $this->data['pagination'] = $listProducts->getResult();

        $loadView = new ConfigView("adms/Views/product/listProducts", $this->data);
        $loadView->renderList();
    }

    /**
     * Busca produtos com filtros
     *
     * @param array $searchData Dados de busca
     * @return void
     */
    private function searchProducts(array $searchData): void {
        if (empty(array_filter($searchData))) {
            $this->listAllProducts();
            return;
        }

        $search = new CpAdmsSearchProducts();
        $this->data['list_products'] = $search->search($searchData);
        $this->data['pagination'] = $search->getResult();

        $loadView = new ConfigView("adms/Views/product/listProducts", $this->data);
        $loadView->renderList();
    }

    /**
     * Retorna estatísticas filtradas via AJAX
     *
     * @return void
     */
    public function getStatistics(): void {
        $searchData = $this->getSearchData();

        $stats = new AdmsStatisticsProducts();
        $this->data['stats'] = $stats->getStats($searchData);

        $loadView = new ConfigView("adms/Views/product/partials/_statistics_dashboard", $this->data);
        $loadView->renderList();
    }

    /**
     * Coleta os dados de busca do formulário
     *
     * @return array Dados de busca
     */
    private function getSearchData(): array {
        return [
            'searchProducts' => filter_input(INPUT_POST, 'searchProducts', FILTER_DEFAULT),
            'searchCategory' => filter_input(INPUT_POST, 'searchCategory', FILTER_DEFAULT),
            'searchStatus' => filter_input(INPUT_POST, 'searchStatus', FILTER_DEFAULT),
        ];
    }

    /**
     * Carrega a configuração de botões de ação
     *
     * @return void
     */
    private function loadButtons(): void {
        $buttonsConfig = [
            'add_product' => ['menu_controller' => 'add-product', 'menu_metodo' => 'create'],
            'view_product' => ['menu_controller' => 'view-product', 'menu_metodo' => 'view'],
            'edit_product' => ['menu_controller' => 'edit-product', 'menu_metodo' => 'edit'],
            'del_product' => ['menu_controller' => 'delete-product', 'menu_metodo' => 'delete']
        ];

        $buttonLoader = new AdmsBotao();
        $this->data['buttons'] = $buttonLoader->valBotao($buttonsConfig);
    }
}
```

---

## 3. Template: Controller de Adição

**Arquivo**: `app/adms/Controllers/AddProduct.php`

```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsAddProduct;
use App\adms\Services\NotificationService;
use App\adms\Services\LoggerService;
use App\adms\Services\FormSelectRepository;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller para Adicionar Produtos
 *
 * @copyright (c) 2025, Seu Nome - Grupo Meia Sola
 */
class AddProduct {

    private ?array $data = [];
    private NotificationService $notification;

    public function __construct()
    {
        $this->notification = new NotificationService();
    }

    /**
     * Processa a criação do produto
     *
     * @return void
     */
    public function create(): void {
        $this->data = filter_input_array(INPUT_POST, FILTER_DEFAULT);

        if (empty($this->data)) {
            $this->notification->error('Requisição inválida!');
            $this->jsonResponse([
                'erro' => false,
                'msg' => 'Erro: Requisição inválida!',
                'notification' => $this->notification->getFlashMessage()
            ], 400);
            return;
        }

        $this->createProduct();
    }

    /**
     * Cria o produto no banco de dados
     *
     * @return void
     */
    private function createProduct(): void {
        $addProduct = new AdmsAddProduct();
        $result = $addProduct->addProduct($this->data);

        if ($result) {
            // Log de sucesso
            LoggerService::info(
                'PRODUCT_CREATE',
                "Produto cadastrado com sucesso",
                [
                    'product_name' => $this->data['product_name'] ?? null,
                    'category_id' => $this->data['category_id'] ?? null,
                ]
            );

            $this->notification->success('Produto cadastrado com sucesso!');

            $this->jsonResponse([
                'erro' => true, // mantido para compatibilidade com JS
                'msg' => 'Produto cadastrado com sucesso!',
                'notification' => $this->notification->getFlashMessage()
            ]);
        } else {
            // Log de erro
            LoggerService::error(
                'PRODUCT_CREATE_FAILED',
                "Falha ao criar produto: " . ($addProduct->getError() ?? 'Erro desconhecido'),
                [
                    'error' => $addProduct->getError() ?? 'Erro desconhecido',
                    'form_data' => [
                        'product_name' => $this->data['product_name'] ?? null,
                    ]
                ]
            );

            $errorMsg = $addProduct->getError() ?? 'Erro ao cadastrar produto. Tente novamente.';
            $this->notification->error($errorMsg);
            $this->jsonResponse([
                'erro' => false,
                'msg' => $errorMsg,
                'notification' => $this->notification->getFlashMessage()
            ], 400);
        }
    }

    /**
     * Endpoint AJAX para buscar dados relacionados
     * Exemplo: buscar categorias por tipo
     */
    public function getCategoriesByType(): void {
        $typeId = filter_input(INPUT_GET, 'type_id', FILTER_VALIDATE_INT);

        if (!$typeId) {
            $this->jsonResponse([
                'error' => true,
                'msg' => 'ID do tipo não informado',
                'categories' => []
            ]);
            return;
        }

        $repository = new FormSelectRepository();
        $categories = $repository->getCategoriesByType($typeId);

        $this->jsonResponse([
            'error' => false,
            'success' => true,
            'categories' => $categories ?: []
        ]);
    }

    /**
     * Retorna resposta JSON padronizada
     *
     * @param array $data Dados para retornar
     * @param int $statusCode Código HTTP de status
     * @return void
     */
    private function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
```

---

## 4. Template: Controller de Edição

**Arquivo**: `app/adms/Controllers/EditProduct.php`

```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsEditProduct;
use App\adms\Services\NotificationService;
use App\adms\Services\LoggerService;
use Core\ConfigView;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller para Editar Produtos
 *
 * @copyright (c) 2025, Seu Nome - Grupo Meia Sola
 */
class EditProduct {

    private ?array $data = [];
    private NotificationService $notification;

    public function __construct()
    {
        $this->notification = new NotificationService();
    }

    /**
     * Exibe o formulário de edição
     *
     * @param string $hashId Hash ID do produto
     * @return void
     */
    public function edit(string $hashId): void {
        if (empty($hashId)) {
            $this->notification->error('Produto não identificado!');
            echo json_encode([
                'error' => true,
                'msg' => 'Produto não identificado!'
            ]);
            return;
        }

        $viewProduct = new AdmsEditProduct();
        $this->data['form'] = $viewProduct->getProductData($hashId);

        if ($this->data['form']) {
            $loadView = new ConfigView("adms/Views/product/editProduct", $this->data);
            $loadView->renderList();
        } else {
            echo '<div class="alert alert-danger">Produto não encontrado.</div>';
        }
    }

    /**
     * Processa a atualização do produto
     *
     * @return void
     */
    public function update(): void {
        $this->data = filter_input_array(INPUT_POST, FILTER_DEFAULT);

        if (empty($this->data) || empty($this->data['hash_id'])) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Dados inválidos!'
            ], 400);
            return;
        }

        $editProduct = new AdmsEditProduct();
        $result = $editProduct->updateProduct($this->data);

        if ($result) {
            LoggerService::info(
                'PRODUCT_UPDATE',
                "Produto atualizado",
                [
                    'product_id' => $this->data['hash_id'],
                    'changes' => $this->data
                ]
            );

            $this->notification->success('Produto atualizado com sucesso!');

            $this->jsonResponse([
                'success' => true,
                'message' => 'Produto atualizado com sucesso!',
                'notification' => $this->notification->getFlashMessage()
            ]);
        } else {
            $errorMsg = $editProduct->getError() ?? 'Erro ao atualizar produto.';

            LoggerService::error(
                'PRODUCT_UPDATE_FAILED',
                $errorMsg,
                ['product_id' => $this->data['hash_id']]
            );

            $this->notification->error($errorMsg);

            $this->jsonResponse([
                'success' => false,
                'error' => $errorMsg,
                'notification' => $this->notification->getFlashMessage()
            ], 400);
        }
    }

    private function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
```

---

## 5. Template: Controller de Exclusão

**Arquivo**: `app/adms/Controllers/DeleteProduct.php`

```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsDeleteProduct;
use App\adms\Services\NotificationService;
use App\adms\Services\LoggerService;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller para Excluir Produtos
 *
 * @copyright (c) 2025, Seu Nome - Grupo Meia Sola
 */
class DeleteProduct {

    private NotificationService $notification;

    public function __construct()
    {
        $this->notification = new NotificationService();
    }

    /**
     * Exclui um produto
     *
     * @param string $hashId Hash ID do produto
     * @return void
     */
    public function delete(string $hashId): void {
        if (empty($hashId)) {
            $this->notification->error('Produto não identificado!');
            header("Location: " . URLADM . "products/list");
            exit();
        }

        $deleteProduct = new AdmsDeleteProduct();
        $result = $deleteProduct->deleteProduct($hashId);

        if ($result) {
            LoggerService::info(
                'PRODUCT_DELETE',
                "Produto excluído",
                ['product_id' => $hashId]
            );

            $this->notification->success('Produto excluído com sucesso!');
        } else {
            $errorMsg = $deleteProduct->getError() ?? 'Erro ao excluir produto.';

            LoggerService::error(
                'PRODUCT_DELETE_FAILED',
                $errorMsg,
                ['product_id' => $hashId]
            );

            $this->notification->error($errorMsg);
        }

        header("Location: " . URLADM . "products/list");
        exit();
    }
}
```

---

## 6. Template: Controller de Visualização

**Arquivo**: `app/adms/Controllers/ViewProduct.php`

```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsViewProduct;
use Core\ConfigView;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller para Visualizar Produtos
 *
 * @copyright (c) 2025, Seu Nome - Grupo Meia Sola
 */
class ViewProduct {

    private ?array $data = [];

    /**
     * Exibe os detalhes do produto
     *
     * @param string $hashId Hash ID do produto
     * @return void
     */
    public function view(string $hashId): void {
        if (empty($hashId)) {
            echo '<div class="alert alert-danger">Produto não identificado!</div>';
            return;
        }

        $viewProduct = new AdmsViewProduct();
        $this->data['product'] = $viewProduct->getProductDetails($hashId);

        if ($this->data['product']) {
            $loadView = new ConfigView("adms/Views/product/viewProduct", $this->data);
            $loadView->renderList();
        } else {
            echo '<div class="alert alert-danger">Produto não encontrado.</div>';
        }
    }
}
```

---

## 7. Template: Model de Adição

**Arquivo**: `app/adms/Models/AdmsAddProduct.php`

```php
<?php

namespace App\adms\Models;

use Ramsey\Uuid\Uuid;
use App\adms\Models\helper\AdmsCampoVazio;
use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsCreate;
use App\adms\Services\NotificationService;

/**
 * Model para adicionar produtos
 *
 * @copyright (c) 2025, Seu Nome - Grupo Meia Sola
 */
class AdmsAddProduct {

    private mixed $Result;
    private array|null $Datas;
    private ?string $Error = null;
    private NotificationService $notification;

    public function __construct() {
        $this->notification = new NotificationService();
    }

    public function getResult(): mixed {
        return $this->Result;
    }

    public function getError(): ?string {
        return $this->Error;
    }

    /**
     * Adiciona produto no banco
     *
     * @param array $Datas Dados do produto
     * @return bool
     */
    public function addProduct(array $Datas): bool {
        $this->Datas = $Datas;

        // Processar dados antes de validar
        $this->Datas['slug'] = $this->generateSlug($this->Datas['product_name']);

        // Validar campos vazios
        $valCampoVazio = new AdmsCampoVazio();
        $valCampoVazio->validarDados($this->Datas);

        if ($valCampoVazio->getResultado()) {
            $this->checkDuplicate();
        } else {
            $this->Result = false;
        }

        return (bool) $this->Result;
    }

    /**
     * Verifica duplicidade antes de inserir
     *
     * @return void
     */
    private function checkDuplicate(): void {
        $viewProduct = new AdmsRead();
        $viewProduct->fullRead(
            "SELECT COUNT(id) AS num_result FROM adms_products WHERE product_name = :name",
            "name={$this->Datas['product_name']}"
        );

        $result = $viewProduct->getResult();

        if ($result[0]['num_result'] === 0) {
            $this->insertProduct();
        } else {
            $this->Error = "Já existe um produto com este nome!";
            $this->notification->error($this->Error);
            $this->Result = false;
        }
    }

    /**
     * Insere no banco
     *
     * @return void
     */
    private function insertProduct(): void {
        $this->Datas['hash_id'] = Uuid::uuid7()->toString();
        $this->Datas['created_at'] = date("Y-m-d H:i:s");
        $this->Datas['adms_user_created_id'] = $_SESSION['usuario_id'];

        $addProduct = new AdmsCreate();
        $addProduct->exeCreate("adms_products", $this->Datas);

        if ($addProduct->getResult()) {
            $this->notification->success('Produto cadastrado com sucesso!');
            $this->Result = true;
        } else {
            $this->Error = 'Erro ao inserir no banco de dados.';
            $this->notification->error($this->Error);
            $this->Result = false;
        }
    }

    /**
     * Gera slug a partir do nome
     *
     * @param string $text Texto para gerar slug
     * @return string
     */
    private function generateSlug(string $text): string {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        $text = trim($text, '-');
        return $text;
    }
}
```

---

## 8. Template: Model de Edição

**Arquivo**: `app/adms/Models/AdmsEditProduct.php`

```php
<?php

namespace App\adms\Models;

use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsUpdate;
use App\adms\Services\NotificationService;

/**
 * Model para editar produtos
 *
 * @copyright (c) 2025, Seu Nome - Grupo Meia Sola
 */
class AdmsEditProduct {

    private mixed $Result;
    private ?string $Error = null;
    private NotificationService $notification;

    public function __construct() {
        $this->notification = new NotificationService();
    }

    public function getResult(): mixed {
        return $this->Result;
    }

    public function getError(): ?string {
        return $this->Error;
    }

    /**
     * Busca os dados do produto para edição
     *
     * @param string $hashId Hash ID do produto
     * @return array|null
     */
    public function getProductData(string $hashId): ?array {
        $viewProduct = new AdmsRead();
        $viewProduct->fullRead(
            "SELECT
                p.*,
                c.category_name,
                u.nome AS user_created_name
            FROM adms_products p
            LEFT JOIN adms_categories c ON p.category_id = c.id
            LEFT JOIN adms_usuarios u ON p.adms_user_created_id = u.id
            WHERE p.hash_id = :hash
            LIMIT 1",
            "hash={$hashId}"
        );

        $this->Result = $viewProduct->getResult();
        return $this->Result[0] ?? null;
    }

    /**
     * Atualiza o produto
     *
     * @param array $data Dados do produto
     * @return bool
     */
    public function updateProduct(array $data): bool {
        $hashId = $data['hash_id'];
        unset($data['hash_id']); // Remove hash_id dos dados de atualização

        // Adiciona dados de auditoria
        $data['updated_at'] = date("Y-m-d H:i:s");
        $data['adms_user_updated_id'] = $_SESSION['usuario_id'];

        $updateProduct = new AdmsUpdate();
        $updateProduct->exeUpdate(
            "adms_products",
            $data,
            "WHERE hash_id = :hash",
            "hash={$hashId}"
        );

        if ($updateProduct->getResult()) {
            $this->notification->success('Produto atualizado com sucesso!');
            $this->Result = true;
            return true;
        } else {
            $this->Error = 'Erro ao atualizar produto no banco de dados.';
            $this->notification->error($this->Error);
            $this->Result = false;
            return false;
        }
    }
}
```

---

## 9. Template: Model de Listagem

**Arquivo**: `app/adms/Models/AdmsListProducts.php`

```php
<?php

namespace App\adms\Models;

use App\adms\Models\helper\AdmsRead;
use App\adms\Models\AdmsPagination;

/**
 * Model para listar produtos
 *
 * @copyright (c) 2025, Seu Nome - Grupo Meia Sola
 */
class AdmsListProducts {

    private mixed $Result;
    private int $Page;
    private int $LimitResult = LIMIT;
    private ?array $Dados = null;

    public function getResult(): mixed {
        return $this->Result;
    }

    /**
     * Lista produtos com paginação
     *
     * @param int $page Número da página
     * @return array|null
     */
    public function list(int $page = 1): ?array {
        $this->Page = (int) $page ?: 1;

        $pagination = new AdmsPagination(URLADM . 'products/list', 'typecoupon=1');
        $pagination->condition($this->Page, $this->LimitResult);
        $pagination->pagination("SELECT COUNT(id) AS num_result FROM adms_products");

        $this->Result = $pagination->getResult();

        if ($this->Result) {
            $listProducts = new AdmsRead();
            $listProducts->fullRead(
                "SELECT
                    p.*,
                    c.category_name,
                    s.status_name,
                    u.nome AS user_created_name
                FROM adms_products p
                LEFT JOIN adms_categories c ON p.category_id = c.id
                LEFT JOIN adms_status s ON p.status_id = s.id
                LEFT JOIN adms_usuarios u ON p.adms_user_created_id = u.id
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset",
                "limit={$this->LimitResult}&offset=" . $pagination->getOffset()
            );

            $this->Dados = $listProducts->getResult();
            return $this->Dados;
        } else {
            return null;
        }
    }
}
```

---

## 10. Template: Service

**Arquivo**: `app/adms/Services/ProductService.php`

```php
<?php

namespace App\adms\Services;

use App\adms\Models\helper\AdmsRead;

/**
 * ProductService - Serviço para operações de produtos
 *
 * Centraliza lógica de negócios relacionada a produtos
 *
 * @copyright (c) 2025, Seu Nome - Grupo Meia Sola
 */
class ProductService {

    /**
     * Calcula estatísticas de produtos
     *
     * @param array $filters Filtros opcionais
     * @return array
     */
    public function getProductStatistics(array $filters = []): array {
        $read = new AdmsRead();

        // Total de produtos
        $read->fullRead("SELECT COUNT(id) AS total FROM adms_products");
        $result = $read->getResult();
        $total = $result[0]['total'] ?? 0;

        // Produtos ativos
        $read->fullRead("SELECT COUNT(id) AS active FROM adms_products WHERE status_id = 1");
        $result = $read->getResult();
        $active = $result[0]['active'] ?? 0;

        // Produtos inativos
        $read->fullRead("SELECT COUNT(id) AS inactive FROM adms_products WHERE status_id = 2");
        $result = $read->getResult();
        $inactive = $result[0]['inactive'] ?? 0;

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'percentage_active' => $total > 0 ? round(($active / $total) * 100, 2) : 0
        ];
    }

    /**
     * Busca produtos por categoria
     *
     * @param int $categoryId ID da categoria
     * @return array
     */
    public function getProductsByCategory(int $categoryId): array {
        $read = new AdmsRead();
        $read->fullRead(
            "SELECT * FROM adms_products WHERE category_id = :cat_id AND status_id = 1 ORDER BY product_name ASC",
            "cat_id={$categoryId}"
        );
        return $read->getResult() ?? [];
    }

    /**
     * Verifica se um produto existe pelo nome
     *
     * @param string $productName Nome do produto
     * @param string|null $excludeHashId Hash ID para excluir da busca (útil na edição)
     * @return bool
     */
    public function productExists(string $productName, ?string $excludeHashId = null): bool {
        $read = new AdmsRead();

        if ($excludeHashId) {
            $read->fullRead(
                "SELECT COUNT(id) AS num FROM adms_products WHERE product_name = :name AND hash_id != :hash",
                "name={$productName}&hash={$excludeHashId}"
            );
        } else {
            $read->fullRead(
                "SELECT COUNT(id) AS num FROM adms_products WHERE product_name = :name",
                "name={$productName}"
            );
        }

        $result = $read->getResult();
        return ($result[0]['num'] ?? 0) > 0;
    }
}
```

---

## 11. Template: Helper

**Arquivo**: `app/adms/Helpers/ValidationHelper.php`

```php
<?php

namespace App\adms\Helpers;

/**
 * ValidationHelper - Helper para validações
 *
 * Contém métodos estáticos para validações comuns
 *
 * @copyright (c) 2025, Seu Nome - Grupo Meia Sola
 */
class ValidationHelper {

    /**
     * Valida CPF
     *
     * @param string $cpf CPF para validar
     * @return bool
     */
    public static function validateCpf(string $cpf): bool {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpf) != 11) {
            return false;
        }

        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida CNPJ
     *
     * @param string $cnpj CNPJ para validar
     * @return bool
     */
    public static function validateCnpj(string $cnpj): bool {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        if (strlen($cnpj) != 14) {
            return false;
        }

        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        $length = strlen($cnpj) - 2;
        $numbers = substr($cnpj, 0, $length);
        $digits = substr($cnpj, $length);
        $sum = 0;
        $pos = $length - 7;

        for ($i = $length; $i >= 1; $i--) {
            $sum += $numbers[$length - $i] * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }

        $result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;

        if ($result != $digits[0]) {
            return false;
        }

        $length++;
        $numbers = substr($cnpj, 0, $length);
        $sum = 0;
        $pos = $length - 7;

        for ($i = $length; $i >= 1; $i--) {
            $sum += $numbers[$length - $i] * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }

        $result = $sum % 11 < 2 ? 0 : 11 - $sum % 11;

        return $result == $digits[1];
    }

    /**
     * Valida e-mail
     *
     * @param string $email E-mail para validar
     * @return bool
     */
    public static function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valida URL
     *
     * @param string $url URL para validar
     * @return bool
     */
    public static function validateUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Valida se a string tem comprimento mínimo
     *
     * @param string $value Valor para validar
     * @param int $minLength Comprimento mínimo
     * @return bool
     */
    public static function minLength(string $value, int $minLength): bool {
        return strlen($value) >= $minLength;
    }

    /**
     * Valida se a string tem comprimento máximo
     *
     * @param string $value Valor para validar
     * @param int $maxLength Comprimento máximo
     * @return bool
     */
    public static function maxLength(string $value, int $maxLength): bool {
        return strlen($value) <= $maxLength;
    }
}
```

---

## 12. Template: View de Listagem

**Arquivo**: `app/adms/Views/product/loadProducts.php`

```php
<?php
if (!defined('URLADM')) {
    header("Location: /");
    exit();
}
?>

<!-- Dados de Configuração para JavaScript -->
<span class="path" data-path="<?php echo URLADM; ?>"></span>
<span class="pathProducts" data-pathproducts="<?php echo URLADM; ?>products/list/"></span>
<span class="pathStatistics" data-pathstatistics="<?php echo URLADM; ?>products/get-statistics"></span>

<div class="content p-3">
    <div class="list-group-item">

        <!-- Cabeçalho -->
        <div class="d-flex align-items-center bg-light pr-2 pl-2 mb-4 border rounded shadow-sm">
            <div class="mr-auto p-2">
                <h2 class="display-4 titulo">
                    <i class="fas fa-box text-primary mr-2"></i>
                    Produtos
                </h2>
            </div>
            <div class="btn-toolbar">
                <?php if ($this->Dados['buttons']['add_product']): ?>
                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#addProduct">
                        <i class="fas fa-plus mr-1"></i> Novo Produto
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Estatísticas -->
        <div id="statistics_container">
            <?php include_once 'partials/_statistics_dashboard.php'; ?>
        </div>

        <!-- Formulário de Busca -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">
                    <i class="fas fa-filter mr-2"></i> Filtros de Busca
                </h6>
            </div>
            <div class="card-body">
                <form id="searchForm" method="POST">
                    <div class="form-row">
                        <div class="form-group col-md-5">
                            <label for="searchProducts">Buscar</label>
                            <input type="text" class="form-control" id="searchProducts" name="searchProducts"
                                   placeholder="Nome do produto, SKU, descrição...">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="searchCategory">Categoria</label>
                            <select class="custom-select" id="searchCategory" name="searchCategory">
                                <option value="">Todas</option>
                                <?php foreach ($this->Dados['select']['categories'] as $cat): ?>
                                    <option value="<?= $cat['cat_id'] ?>"><?= $cat['category_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="searchStatus">Status</label>
                            <select class="custom-select" id="searchStatus" name="searchStatus">
                                <option value="">Todos</option>
                                <option value="1">Ativo</option>
                                <option value="2">Inativo</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button type="button" id="resetSearchBtn" class="btn btn-secondary btn-block">
                                <i class="fas fa-redo mr-1"></i> Limpar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Conteúdo (carregado via AJAX) -->
        <div id="content_products"></div>

    </div>
</div>

<!-- Modals -->
<?php include_once 'partials/_add_product_modal.php'; ?>
<?php include_once 'partials/_view_product_modal.php'; ?>
<?php include_once 'partials/_edit_product_modal.php'; ?>

<script src="<?php echo URLADM . 'assets/js/products.js'; ?>"></script>
```

---

## 13. Template: Modal de Cadastro

**Arquivo**: `app/adms/Views/product/partials/_add_product_modal.php`

```php
<?php
if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

$valorForm = $this->Dados['form'][0] ?? $this->Dados['form'] ?? [];
?>
<span class="add_path_product" data-addpath="<?php echo URLADM; ?>add-product/create"></span>

<div class="modal fade" id="addProduct" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-box mr-2"></i>Cadastrar Produto
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div id="modal-messages"></div>

                <form method="POST" id="insert_form_product" enctype="multipart/form-data">

                    <!-- Informações Básicas -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Informações Básicas</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group col-md-8">
                                    <label for="product_name">
                                        <span class="text-danger">*</span> Nome do Produto
                                    </label>
                                    <input type="text" class="form-control" name="product_name" id="product_name"
                                           placeholder="Ex: Sapato Social Masculino" required>
                                    <small class="form-text text-muted">Nome completo do produto</small>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="sku">
                                        <span class="text-danger">*</span> SKU
                                    </label>
                                    <input type="text" class="form-control" name="sku" id="sku"
                                           placeholder="Ex: PROD-001" required>
                                    <small class="form-text text-muted">Código único do produto</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="category_id">
                                        <span class="text-danger">*</span> Categoria
                                    </label>
                                    <select class="custom-select" name="category_id" id="category_id" required>
                                        <option value="">Selecione</option>
                                        <?php foreach ($this->Dados['select']['categories'] as $cat): ?>
                                            <option value="<?= $cat['cat_id'] ?>"><?= $cat['category_name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="status_id">
                                        <span class="text-danger">*</span> Status
                                    </label>
                                    <select class="custom-select" name="status_id" id="status_id" required>
                                        <option value="1">Ativo</option>
                                        <option value="2">Inativo</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description">Descrição</label>
                                <textarea class="form-control" name="description" id="description" rows="3"
                                          placeholder="Descrição detalhada do produto"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Preços -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-dollar-sign mr-2"></i>Preços</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="cost_price">Preço de Custo</label>
                                    <input type="text" class="form-control money" name="cost_price" id="cost_price"
                                           placeholder="R$ 0,00">
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="sale_price">
                                        <span class="text-danger">*</span> Preço de Venda
                                    </label>
                                    <input type="text" class="form-control money" name="sale_price" id="sale_price"
                                           placeholder="R$ 0,00" required>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="promotional_price">Preço Promocional</label>
                                    <input type="text" class="form-control money" name="promotional_price" id="promotional_price"
                                           placeholder="R$ 0,00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-muted small">
                        <span class="text-danger">* </span>Campos obrigatórios
                    </p>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('insert_form_product').reset();">
                    <i class="fas fa-eraser mr-1"></i>Limpar
                </button>
                <button type="submit" form="insert_form_product" class="btn btn-success">
                    <i class="fas fa-save mr-1"></i>Salvar
                </button>
            </div>
        </div>
    </div>
</div>
```

---

## 14. Template: Modal de Edição

**Arquivo**: `app/adms/Views/product/partials/_edit_product_modal.php`

```php
<?php
if (!defined('URLADM')) {
    header("Location: /");
    exit();
}
?>

<div class="modal fade" id="editProductModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-edit mr-2"></i>Editar Produto
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div id="edit-product-message"></div>
                <div id="edit_data_product">
                    <!-- Conteúdo carregado via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>
```

---

## 15. Template: Modal de Visualização

**Arquivo**: `app/adms/Views/product/partials/_view_product_modal.php`

```php
<?php
if (!defined('URLADM')) {
    header("Location: /");
    exit();
}
?>

<div class="modal fade" id="viewProductModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye mr-2"></i>Visualizar Produto
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="view_data_product">
                    <!-- Conteúdo carregado via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>
```

---

## 16. Template: Modal de Confirmação de Exclusão

### 16.1. Arquitetura do Sistema

O sistema de confirmação de exclusão utiliza um padrão reutilizável com modal personalizado Bootstrap.

**Componentes**:
- **Modal Base**: `app/adms/Views/include/_delete_confirmation_modal.php`
- **Biblioteca JS**: `assets/js/delete-confirmation.js`
- **Modal Específico**: `app/adms/Views/[module]/partials/_delete_[entity]_modal.php`

### 16.2. Controller de Exclusão (Padrão Atualizado)

**Arquivo**: `app/adms/Controllers/DeleteProduct.php`

```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsDeleteProduct;
use App\adms\Services\NotificationService;
use App\adms\Services\LoggerService;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller para Excluir Produtos
 *
 * Suporta requisições AJAX e tradicionais
 *
 * @copyright (c) 2025, Seu Nome - Grupo Meia Sola
 */
class DeleteProduct {

    private NotificationService $notification;

    public function __construct()
    {
        $this->notification = new NotificationService();
    }

    /**
     * Exclui um produto
     *
     * @param string $hashId Hash ID do produto
     * @return void
     */
    public function delete(string $hashId): void {
        // Detecta se é requisição AJAX
        $isAjax = $this->isAjaxRequest();

        if (empty($hashId)) {
            $this->notification->error('Produto não identificado!');

            if ($isAjax) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Produto não identificado!'
                ], 400);
            } else {
                header("Location: " . URLADM . "products/list");
                exit();
            }
        }

        $deleteProduct = new AdmsDeleteProduct();
        $result = $deleteProduct->deleteProduct($hashId);

        if ($result) {
            LoggerService::info(
                'PRODUCT_DELETE',
                "Produto excluído",
                ['product_id' => $hashId]
            );

            $this->notification->success('Produto excluído com sucesso!');

            if ($isAjax) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Produto excluído com sucesso!'
                ]);
            }
        } else {
            $errorMsg = $deleteProduct->getError() ?? 'Erro ao excluir produto.';

            LoggerService::error(
                'PRODUCT_DELETE_FAILED',
                $errorMsg,
                ['product_id' => $hashId]
            );

            $this->notification->error($errorMsg);

            if ($isAjax) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $errorMsg
                ], 400);
            }
        }

        if (!$isAjax) {
            header("Location: " . URLADM . "products/list");
            exit();
        }
    }

    /**
     * Verifica se é requisição AJAX
     *
     * @return bool
     */
    private function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Retorna resposta JSON
     *
     * @param array $data Dados
     * @param int $statusCode Código HTTP
     * @return void
     */
    private function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
```

### 16.3. Modal Específico do Módulo

**Arquivo**: `app/adms/Views/product/partials/_delete_product_modal.php`

```php
<?php
if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

$modalId = 'deleteProductModal';
$modalTitle = 'Confirmar Exclusão de Produto';
$warningMessage = 'O produto será removido permanentemente do sistema.';

include __DIR__ . '/../../include/_delete_confirmation_modal.php';
?>
```

### 16.4. Inclusão na View Principal

**Arquivo**: `app/adms/Views/product/loadProducts.php`

```php
<!-- Modals -->
<?php include_once 'partials/_add_product_modal.php'; ?>
<?php include_once 'partials/_view_product_modal.php'; ?>
<?php include_once 'partials/_edit_product_modal.php'; ?>
<?php include_once 'partials/_delete_product_modal.php'; ?>

<!-- JavaScript -->
<script src="<?php echo URLADM . 'assets/js/delete-confirmation.js?v=' . time(); ?>"></script>
<script src="<?php echo URLADM . 'assets/js/products.js?v=' . time(); ?>"></script>
```

### 16.5. Função JavaScript

**Arquivo**: `assets/js/products.js`

```javascript
/**
 * Abre modal de confirmação de exclusão
 *
 * @param {number} productId - ID do produto
 * @param {string} productName - Nome do produto
 * @param {string} sku - SKU (opcional)
 * @param {string} category - Categoria (opcional)
 */
function deleteProduct(productId, productName, sku = '', category = '') {
    if (!productId) return;

    // Cria instância do modal
    const deleteModal = new DeleteConfirmationModal('deleteProductModal');

    // Prepara dados para exibir
    const data = {
        'ID': productId,
        'Nome': productName || 'N/A'
    };

    if (sku) data['SKU'] = sku;
    if (category) data['Categoria'] = category;

    // Callback de exclusão
    const onConfirm = async (id) => {
        const URL_BASE = document.querySelector('.path')?.dataset.path || '';

        try {
            const response = await fetch(`${URL_BASE}delete-product/delete/${id}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error('Erro ao excluir');

            const result = await response.json();

            // Fecha modal
            deleteModal.close();

            // Exibe notificação
            if (result.success) {
                showNotification('success', result.message || 'Produto excluído com sucesso!');
                // Recarrega listagem
                listProducts(1);
            } else {
                showNotification('error', result.message || 'Erro ao excluir produto.');
            }

        } catch (error) {
            console.error('Erro:', error);
            deleteModal.showError('Erro ao processar exclusão. Tente novamente.');
        }
    };

    // Abre modal
    deleteModal.show(productId, data, onConfirm, {
        description: 'Você está prestes a excluir o seguinte produto:',
        extraWarning: 'Todos os dados do produto serão removidos permanentemente.'
    });
}
```

### 16.6. Botão de Exclusão na Listagem

**Arquivo**: `app/adms/Views/product/listProducts.php`

```php
<?php if (!empty($this->Dados['buttons']['del_product'])): ?>
    <button type="button" class="btn btn-outline-danger btn-sm"
            onclick="deleteProduct(<?= $id ?>, '<?= htmlspecialchars($productName, ENT_QUOTES) ?>', '<?= htmlspecialchars($sku, ENT_QUOTES) ?>', '<?= htmlspecialchars($category, ENT_QUOTES) ?>')"
            title="Excluir">
        <i class="fas fa-trash"></i>
    </button>
<?php endif; ?>
```

### 16.7. API da Classe DeleteConfirmationModal

**Métodos Disponíveis**:

```javascript
// Constructor
const modal = new DeleteConfirmationModal('modalId');

// Exibir modal
modal.show(itemId, dataObject, callbackFunction, optionsObject);

// Parâmetros de show():
// - itemId: ID do item a ser excluído
// - dataObject: { 'Label': 'Valor' } - dados exibidos no card
// - callbackFunction: async (id) => { ... } - função chamada ao confirmar
// - optionsObject: {
//     description: 'Texto personalizado',
//     extraWarning: 'Aviso adicional'
//   }

// Exibir erro no modal
modal.showError('Mensagem de erro');

// Limpar mensagens
modal.clearMessages();

// Fechar modal
modal.close();
```

### 16.8. Exemplo Completo de Implementação

**1. Criar modal específico**:
```php
// app/adms/Views/coupon/partials/_delete_coupon_modal.php
<?php
$modalId = 'deleteCouponModal';
$modalTitle = 'Confirmar Exclusão de Cupom';
$warningMessage = 'O cupom será removido permanentemente.';
include __DIR__ . '/../../include/_delete_confirmation_modal.php';
?>
```

**2. Incluir na view principal**:
```php
<?php include_once 'partials/_delete_coupon_modal.php'; ?>
<script src="<?php echo URLADM . 'assets/js/delete-confirmation.js'; ?>"></script>
```

**3. Implementar função JavaScript**:
```javascript
function deleteCoupon(couponId, couponCode) {
    const modal = new DeleteConfirmationModal('deleteCouponModal');

    modal.show(
        couponId,
        { 'ID': couponId, 'Código': couponCode },
        async (id) => {
            const response = await fetch(`${URL_BASE}delete-coupon/delete/${id}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();

            modal.close();
            if (result.success) {
                showNotification('success', result.message);
                listCoupons(1);
            } else {
                showNotification('error', result.message);
            }
        }
    );
}
```

**4. Atualizar controller para suportar AJAX** (ver seção 16.2)

### 16.9. Módulos Implementados

✅ **Movimentações de Pessoal** - `personnelMoviments`
✅ **Funcionários** - `employee`
✅ **Ajustes de Estoque** - `adjustments`
✅ **Transferências** - `transfers`
✅ **Remanejos** - `relocation`
✅ **Cupons** - `coupon`
✅ **Páginas** - `pagina`

**Referência Completa**: `docs/DELETE_MODAL_IMPLEMENTATION_GUIDE.md`

---

## 17. Template: JavaScript (AJAX)

**Arquivo**: `assets/js/products.js`

```javascript
// ==================== FUNÇÕES GLOBAIS ====================

/**
 * Lista produtos com paginação
 */
async function listProducts(page = 1) {
    const contentProducts = document.getElementById('content_products');
    if (!contentProducts) return;

    const pathElement = document.querySelector('.pathProducts');
    const URL_BASE = pathElement ? pathElement.dataset.pathproducts : '';

    page = parseInt(page);
    if (isNaN(page) || page < 1) page = 1;

    contentProducts.innerHTML = '<p class="text-center mt-3"><span class="spinner-border spinner-border-sm"></span> Carregando produtos...</p>';

    const url = `${URL_BASE}${page}?typeproduct=1`;

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`Falha ao carregar (Status: ${response.status})`);

        const htmlContent = await response.text();
        contentProducts.innerHTML = htmlContent;

        adjustPaginationLinks();
    } catch (error) {
        console.error("Erro em listProducts:", error);
        contentProducts.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados.</div>';
    }
}

/**
 * Ajusta links de paginação para usar AJAX
 */
function adjustPaginationLinks() {
    const paginationLinks = document.querySelectorAll('#content_products .pagination .page-link');

    paginationLinks.forEach(link => {
        if (link.hasAttribute('data-ajax-processed') || link.parentElement.classList.contains('active')) {
            return;
        }

        let page = null;

        if (link.hasAttribute('data-page')) {
            page = link.getAttribute('data-page');
        } else {
            const href = link.getAttribute('href');
            if (href && href !== '#') {
                const match = href.match(/\/(\d+)(?:\?|\/|$)/);
                if (match) page = match[1];
            }
        }

        if (page) {
            link.setAttribute('href', '#');
            link.setAttribute('data-ajax-processed', 'true');
            link.setAttribute('onclick', `listProducts(${page}); return false;`);
        }
    });
}

/**
 * Carrega estatísticas
 */
async function loadStatistics() {
    const statisticsContainer = document.getElementById('statistics_container');
    if (!statisticsContainer) return;

    const pathStatistics = document.querySelector('.pathStatistics');
    const STATS_URL = pathStatistics ? pathStatistics.dataset.pathstatistics : '';

    statisticsContainer.style.opacity = '0.5';

    const searchForm = document.getElementById('searchForm');
    const formData = searchForm ? new FormData(searchForm) : new FormData();

    try {
        const response = await fetch(STATS_URL, { method: 'POST', body: formData });
        if (!response.ok) throw new Error('Falha ao carregar estatísticas');

        const htmlContent = await response.text();
        statisticsContainer.innerHTML = htmlContent;
        statisticsContainer.style.opacity = '1';
    } catch (error) {
        console.error("Erro ao carregar estatísticas:", error);
        statisticsContainer.style.opacity = '1';
    }
}

// ==================== DOCUMENT READY ====================

document.addEventListener('DOMContentLoaded', () => {
    const pathElement = document.querySelector('.pathProducts');
    const URL_BASE = pathElement ? pathElement.dataset.pathproducts : '';

    // ==================== BUSCA ====================

    const searchForm = document.getElementById('searchForm');
    let searchTimeout = null;

    function hasActiveFilters() {
        const searchInput = document.getElementById('searchProducts');
        const searchCategory = document.getElementById('searchCategory');
        const searchStatus = document.getElementById('searchStatus');

        return (searchInput && searchInput.value.trim() !== '') ||
               (searchCategory && searchCategory.value !== '') ||
               (searchStatus && searchStatus.value !== '');
    }

    async function performSearch() {
        if (!hasActiveFilters()) {
            listProducts(1);
            loadStatistics();
            return;
        }

        const contentProducts = document.getElementById('content_products');
        if (!contentProducts) return;

        contentProducts.innerHTML = '<p class="text-center mt-3"><span class="spinner-border spinner-border-sm"></span> Buscando...</p>';

        const formData = new FormData(searchForm);
        const url = `${URL_BASE}1?typeproduct=2`;

        try {
            const response = await fetch(url, { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Falha na busca');

            const htmlContent = await response.text();
            contentProducts.innerHTML = htmlContent;

            adjustPaginationLinks();
            loadStatistics();
        } catch (error) {
            console.error("Erro na busca:", error);
            contentProducts.innerHTML = '<div class="alert alert-danger">Erro ao buscar.</div>';
        }
    }

    if (searchForm) {
        searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            performSearch();
        });

        const searchInput = document.getElementById('searchProducts');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                if (searchTimeout) clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => performSearch(), 500);
            });
        }

        const searchCategory = document.getElementById('searchCategory');
        if (searchCategory) {
            searchCategory.addEventListener('change', () => performSearch());
        }

        const searchStatus = document.getElementById('searchStatus');
        if (searchStatus) {
            searchStatus.addEventListener('change', () => performSearch());
        }

        const resetBtn = document.getElementById('resetSearchBtn');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                searchForm.reset();
                performSearch();
            });
        }
    }

    // ==================== MODAL DE VISUALIZAÇÃO ====================

    $(document).on('click', '.view_data_product', async function () {
        const productId = $(this).attr('data-product-id');
        if (!productId) return;

        const path = $('.path').attr('data-path');
        const modalContent = $('#view_data_product');

        modalContent.html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
                <p class="mt-3 text-muted">Carregando...</p>
            </div>
        `);

        $('#viewProductModal').modal('show');

        try {
            const response = await fetch(`${path}view-product/view/${productId}`);
            if (!response.ok) throw new Error('Erro ao carregar');

            const htmlContent = await response.text();
            modalContent.html(htmlContent);
        } catch (error) {
            console.error('Erro:', error);
            modalContent.html('<div class="alert alert-danger">Erro ao carregar dados.</div>');
        }
    });

    // ==================== MODAL DE EDIÇÃO ====================

    $(document).on('click', '.edit_data_product', async function () {
        const productId = $(this).attr('data-product-id');
        if (!productId) return;

        const path = $('.path').attr('data-path');
        const modalContent = $('#edit_data_product');

        modalContent.html(`
            <div class="text-center py-5">
                <div class="spinner-border text-warning" style="width: 3rem; height: 3rem;"></div>
                <p class="mt-3 text-muted">Carregando...</p>
            </div>
        `);

        $('#editProductModal').modal('show');

        try {
            const response = await fetch(`${path}edit-product/edit/${productId}`);
            if (!response.ok) throw new Error('Erro ao carregar');

            const htmlContent = await response.text();
            modalContent.html(htmlContent);
        } catch (error) {
            console.error('Erro:', error);
            modalContent.html('<div class="alert alert-danger">Erro ao carregar formulário.</div>');
        }
    });

    // ==================== SUBMIT DE ADIÇÃO ====================

    const insertForm = document.getElementById('insert_form_product');
    if (insertForm) {
        insertForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const addPathElement = document.querySelector('.add_path_product');
            if (!addPathElement) return;

            const URL_ADD = addPathElement.dataset.addpath;
            const submitBtn = insertForm.querySelector('button[type="submit"]');

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Salvando...';
            }

            try {
                const formData = new FormData(insertForm);
                const response = await fetch(URL_ADD, { method: 'POST', body: formData });

                if (!response.ok) throw new Error(`Erro HTTP: ${response.status}`);

                const result = await response.json();

                if (result.erro) {
                    // Sucesso
                    if (result.notification) {
                        const container = document.createElement('div');
                        container.innerHTML = result.notification;
                        document.body.appendChild(container.firstElementChild);
                    }

                    $('#addProduct').modal('hide');
                    insertForm.reset();
                    listProducts(1);
                    loadStatistics();
                } else {
                    // Erro
                    if (result.notification) {
                        const container = document.createElement('div');
                        container.innerHTML = result.notification;
                        document.body.appendChild(container.firstElementChild);
                    }
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao processar solicitação.');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save mr-1"></i>Salvar';
                }
            }
        });
    }

    // ==================== CHAMADA INICIAL ====================

    listProducts(1);
});

// ==================== DELETE HANDLER ====================

document.addEventListener('click', function (e) {
    const deleteBtn = e.target.closest('.btn-delete-product');

    if (deleteBtn) {
        e.preventDefault();
        const deleteUrl = deleteBtn.getAttribute('data-delete-url') || deleteBtn.getAttribute('href');

        if (typeof confirmDelete === 'function') {
            confirmDelete(
                'Excluir Produto',
                'Tem certeza que deseja excluir este produto?\n\nEsta ação não poderá ser desfeita.',
                function () {
                    window.location.href = deleteUrl;
                }
            );
        } else {
            if (confirm('Tem certeza que deseja excluir este produto?')) {
                window.location.href = deleteUrl;
            }
        }
    }
});
```

---

## 18. Padrões de Database Helpers

### 18.1. AdmsRead - Leitura

```php
use App\adms\Models\helper\AdmsRead;

// Query simples com tabela e condição
$read = new AdmsRead();
$read->exeRead("adms_products", "WHERE id = :id LIMIT :limit", "id=1&limit=1");
$result = $read->getResult();

// Query completa (SQL customizado)
$read = new AdmsRead();
$read->fullRead(
    "SELECT p.*, c.category_name
     FROM adms_products p
     LEFT JOIN adms_categories c ON p.category_id = c.id
     WHERE p.status_id = :status
     ORDER BY p.created_at DESC",
    "status=1"
);
$result = $read->getResult();
```

### 18.2. AdmsCreate - Inserção

```php
use App\adms\Models\helper\AdmsCreate;

$create = new AdmsCreate();
$create->exeCreate("adms_products", [
    'hash_id' => $hashId,
    'product_name' => 'Produto Teste',
    'category_id' => 1,
    'status_id' => 1,
    'created_at' => date("Y-m-d H:i:s")
]);

$lastInsertId = $create->getResult(); // Retorna ID inserido
```

### 18.3. AdmsUpdate - Atualização

```php
use App\adms\Models\helper\AdmsUpdate;

$update = new AdmsUpdate();
$update->exeUpdate(
    "adms_products",                    // Tabela
    [                                    // Dados a atualizar
        'product_name' => 'Novo Nome',
        'updated_at' => date("Y-m-d H:i:s")
    ],
    "WHERE hash_id = :hash",            // Condição
    "hash={$hashId}"                    // Valores
);

$success = $update->getResult(); // true/false
```

### 18.4. AdmsDelete - Exclusão

```php
use App\adms\Models\helper\AdmsDelete;

$delete = new AdmsDelete();
$delete->exeDelete(
    "adms_products",
    "WHERE hash_id = :hash",
    "hash={$hashId}"
);

$success = $delete->getResult(); // true/false
```

---

## 19. Boas Práticas

### 19.1. PHP

✅ **FAÇA:**
- Use type hints em parâmetros e retornos
- Use `match` expression (PHP 8+) em vez de `switch`
- Valide entradas com `filter_input` e `filter_var`
- Use prepared statements sempre
- Documente classes e métodos com PHPDoc
- Use constantes para valores mágicos
- Trate exceções adequadamente

❌ **NÃO FAÇA:**
- Não use concatenação direta de SQL
- Não exponha dados sensíveis em logs
- Não use `extract()` sem validação
- Não confie em dados do usuário sem validação
- Não use `eval()`

**Exemplo:**
```php
// ✅ BOM
public function getProduct(string $hashId): ?array {
    $read = new AdmsRead();
    $read->fullRead(
        "SELECT * FROM adms_products WHERE hash_id = :hash LIMIT 1",
        "hash={$hashId}"
    );
    return $read->getResult()[0] ?? null;
}

// ❌ RUIM
public function getProduct($hashId) {
    $read = new AdmsRead();
    $read->fullRead("SELECT * FROM adms_products WHERE hash_id = '{$hashId}'");
    return $read->getResult()[0];
}
```

### 19.2. JavaScript

✅ **FAÇA:**
- Use `async/await` para operações assíncronas
- Use `const` e `let`, nunca `var`
- Use template literals para strings
- Valide respostas de API
- Trate erros com try-catch
- Use debounce para eventos de input
- Documente funções complexas

❌ **NÃO FAÇA:**
- Não use callbacks quando possível usar async/await
- Não ignore erros de fetch
- Não manipule DOM sem verificar se o elemento existe

**Exemplo:**
```javascript
// ✅ BOM
async function loadData(id) {
    const element = document.getElementById('content');
    if (!element) return;

    try {
        const response = await fetch(`/api/data/${id}`);
        if (!response.ok) throw new Error('Falha ao carregar');

        const data = await response.json();
        element.innerHTML = data.html;
    } catch (error) {
        console.error('Erro:', error);
        element.innerHTML = '<div class="alert alert-danger">Erro ao carregar.</div>';
    }
}

// ❌ RUIM
function loadData(id) {
    $.get('/api/data/' + id, function(data) {
        $('#content').html(data.html);
    });
}
```

### 19.3. SQL

✅ **FAÇA:**
- Use prepared statements
- Use índices em colunas de busca
- Use JOINs explícitos
- Limite resultados com LIMIT
- Use transações para operações múltiplas

❌ **NÃO FAÇA:**
- Não concatene valores em queries
- Não use `SELECT *` em produção
- Não faça queries dentro de loops

**Exemplo:**
```php
// ✅ BOM
$read->fullRead(
    "SELECT
        p.id, p.product_name, p.sku,
        c.category_name,
        s.status_name
    FROM adms_products p
    LEFT JOIN adms_categories c ON p.category_id = c.id
    LEFT JOIN adms_status s ON p.status_id = s.id
    WHERE p.category_id = :cat_id
    ORDER BY p.created_at DESC
    LIMIT :limit",
    "cat_id={$categoryId}&limit=10"
);

// ❌ RUIM
$read->fullRead("SELECT * FROM adms_products WHERE category_id = {$categoryId}");
```

### 19.4. Segurança

✅ **SEMPRE:**
- Valide e sanitize todas as entradas
- Use `htmlspecialchars()` para output
- Use HTTPS em produção
- Implemente rate limiting
- Registre ações sensíveis no log
- Use senhas fortes (hash com `password_hash()`)
- Valide permissões em cada ação

❌ **NUNCA:**
- Nunca confie em dados do cliente
- Nunca exponha stack traces em produção
- Nunca armazene senhas em plain text
- Nunca desabilite validação CSRF

### 19.5. Performance

✅ **OTIMIZE:**
- Use cache quando apropriado
- Minimize queries no banco
- Use índices em tabelas
- Comprima assets (CSS, JS)
- Use paginação em listagens
- Carregue dados via AJAX quando possível
- Use lazy loading para imagens

### 19.6. Manutenibilidade

✅ **MANTENHA:**
- Código DRY (Don't Repeat Yourself)
- Funções pequenas e focadas
- Nomenclatura clara e consistente
- Comentários em código complexo
- Versionamento semântico
- Documentação atualizada
- Testes automatizados

---

## 20. Padrão AbstractConfigController (Módulos de Configuração)

Para módulos de configuração simples (CRUD básico de tabelas lookup como cores, bandeiras, situações, etc.), o projeto utiliza o padrão **AbstractConfigController** que elimina código duplicado.

### 20.1. Quando Usar

- Módulos de cadastro simples (tabelas de lookup/configuração)
- CRUD básico: listar, cadastrar, editar, excluir, visualizar
- Sem lógica de negócio complexa
- Sem JavaScript personalizado (usa views existentes do módulo)

**NÃO use** para módulos complexos com estatísticas, buscas avançadas, integrações ou fluxos de aprovação. Para esses, use controllers individuais (veja seção 21 - Módulos de Referência).

### 20.2. Estrutura da Classe Base

**Arquivo:** `app/adms/Controllers/AbstractConfigController.php`

A classe abstrata fornece:
- `executeList($pageId)` - Listagem com paginação
- `executeCreate()` - GET (formulário) + POST (inserção)
- `executeEdit($id)` - GET (formulário) + POST (atualização)
- `executeDelete($id)` - Exclusão com verificação de FK opcional
- `executeView($id)` - Visualização de registro
- `loadButtons()` - Carregamento de permissões via AdmsBotao
- `loadMenu()` - Menu lateral
- `loadSelects()` - Popula selects de foreign keys
- `beforeCreate()` - Hook para lógica pré-inserção (override nas subclasses)

Inclui LoggerService para auditoria e AdmsCampoVazio para validação.

### 20.3. Exemplo de Implementação Concreta

**Arquivo:** `app/adms/Controllers/Cor.php`

```php
<?php

namespace App\adms\Controllers;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

class Cor extends AbstractConfigController
{
    public const MODULE = [
        'table'         => 'adms_cors',
        'entityName'    => 'Cor',
        'listQuery'     => "SELECT id, nome, cor FROM adms_cors ORDER BY id ASC",
        'countQuery'    => "SELECT COUNT(id) AS num_result FROM adms_cors",
        'viewQuery'     => "SELECT * FROM adms_cors WHERE id =:id LIMIT :limit",
        'listDataKey'   => 'listCor',
        'viewDataKey'   => 'dados_cor',
        'selectQueries' => [],
        'routes' => [
            'list'   => ['menu_controller' => 'cor',           'menu_metodo' => 'listar'],
            'create' => ['menu_controller' => 'cadastrar-cor', 'menu_metodo' => 'cad-cor'],
            'edit'   => ['menu_controller' => 'editar-cor',    'menu_metodo' => 'edit-cor'],
            'view'   => ['menu_controller' => 'ver-cor',       'menu_metodo' => 'ver-cor'],
            'delete' => ['menu_controller' => 'apagar-cor',    'menu_metodo' => 'apagar-cor'],
        ],
        'buttonKeys' => [
            'create' => 'cad_cor',
            'view'   => 'vis_cor',
            'edit'   => 'edit_cor',
            'delete' => 'del_cor',
            'list'   => 'list_cor',
        ],
        'views' => [
            'list'   => 'adms/Views/cor/listarCor',
            'create' => 'adms/Views/cor/cadCor',
            'edit'   => 'adms/Views/cor/editarCor',
            'view'   => 'adms/Views/cor/verCor',
        ],
        'submitFields' => ['create' => 'CadCor', 'edit' => 'EditCor'],
    ];

    protected function getConfig(): array
    {
        return self::MODULE;
    }

    public function listar($PageId = null): void
    {
        $this->executeList($PageId);
    }
}
```

### 20.4. Chaves do Array MODULE

| Chave | Obrigatória | Descrição |
|-------|-------------|-----------|
| `table` | Sim | Nome da tabela no banco |
| `entityName` | Sim | Nome da entidade para logs |
| `listQuery` | Sim | SQL para listagem |
| `countQuery` | Sim | SQL para contagem (paginação) |
| `viewQuery` | Sim | SQL para visualizar/editar registro |
| `listDataKey` | Sim | Chave no array `$data` para a listagem |
| `viewDataKey` | Sim | Chave no array `$data` para visualização |
| `selectQueries` | Sim | Array de queries para popular selects (pode ser vazio `[]`) |
| `routes` | Sim | Array com rotas para list, create, edit, view, delete |
| `buttonKeys` | Sim | Array com chaves de botões para permissões |
| `views` | Sim | Array com caminhos das views |
| `submitFields` | Sim | Array com nomes dos campos submit (create, edit) |
| `limit` | Não | Limite de registros por página (default: LIMIT constante) |
| `editQuery` | Não | SQL separado para formulário de edição |
| `deleteCheck` | Não | Array com query e mensagem para verificar FK antes de exclusão |
| `extraListButtons` | Não | Botões adicionais na listagem |

### 20.5. Módulos Migrados

Os seguintes 13 módulos usam AbstractConfigController:
`Cor`, `Bandeira`, `Situacao`, `Cfop`, `TipoPagamento`, `TipoPg`, `SituacaoPg`, `Rota`, `SituacaoTransf`, `SituacaoTroca`, `SituacaoUser`, `SituacaoDelivery`, `ResponsavelAuditoria`

---

## 21. Módulos de Referência

Os módulos abaixo são considerados **implementações de referência** e devem ser consultados ao criar ou refatorar outros módulos.

### 21.1. Sales (Vendas) - Módulo Complexo

**Status:** ✅ MODERNO (Refatorado em 21/01/2026)

O módulo Sales é a **referência principal** para implementação de módulos complexos com múltiplas funcionalidades.

**Características:**
- CRUD completo via AJAX
- Sincronização com sistema externo (CIGAM)
- Exclusão em lote por período
- Cards de estatísticas
- 113 testes unitários

**Arquivos de Referência:**

| Tipo | Arquivo | Descrição |
|------|---------|-----------|
| Controller Principal | `Controllers/Sales.php` | Match expression, estatísticas, permissões |
| Controller AJAX | `Controllers/AddSales.php` | NotificationService, LoggerService, JSON response |
| Model Estatísticas | `Models/AdmsStatisticsSales.php` | Cards, cálculos, filtros por permissão |
| Model CRUD | `Models/AdmsAddSales.php` | Validação, auditoria, tratamento de erros |
| View Principal | `Views/sales/loadSales.php` | Layout com cards, filtros, container AJAX |
| Modal Partial | `Views/sales/partials/_add_sale_modal.php` | Modal Bootstrap padrão |
| JavaScript | `assets/js/sales.js` | Async/await, debounce, event delegation |
| Testes | `tests/Sales/SalesControllerTest.php` | Cobertura completa |

**Padrões Demonstrados:**
```php
// Controller com match expression
match ($requestType) {
    'listAll' => $this->listAllItems($userStoreId),
    'search' => $this->searchItems($searchData, $userStoreId),
    default => $this->loadInitialPage($userStoreId),
};

// Uso de NotificationService
$this->notification->success('Venda adicionada com sucesso!');

// Uso de LoggerService
LoggerService::info('SALE_CREATED', 'Nova venda cadastrada', [
    'sale_id' => $saleId,
    'user_id' => $_SESSION['usuario_id']
]);
```

### 21.2. Coupons (Cupons) - Módulo Padrão

**Status:** ✅ MODERNO

Referência para CRUD básico com validação e auditoria.

### 21.3. HolidayPayment - Módulo com Aprovação

**Status:** ✅ MODERNO

Referência para fluxos de aprovação multinível.

---

**Última Atualização**: 2026-02-07
**Mantenedor**: Chirlanio Silva - Grupo Meia Sola
