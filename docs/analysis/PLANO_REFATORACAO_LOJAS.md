# Plano de Refatoração: Módulo de Lojas (Stores)

**Data:** 16 de Janeiro de 2026
**Versão:** 1.0
**Baseado em:** ANALISE_MODULO_LOJAS.md

---

## 1. Visão Geral

Refatorar o módulo de Lojas para seguir os padrões do projeto Mercury, incluindo:
- Nomenclatura correta de Controllers, Models e Views
- Type hints e PHPDoc em todos os métodos
- Arquitetura modal-based (AJAX) para CRUD
- LoggerService para auditoria
- NotificationService para feedback
- JavaScript dedicado para interatividade

**Requisitos:** CRUD completo + Pesquisa

---

## 2. Estrutura Proposta

### 2.1. Nova Estrutura de Arquivos

```
app/adms/Controllers/
├── Store.php                    # RENOMEAR de Lojas.php
├── AddStore.php                 # RENOMEAR de CadastrarLoja.php
├── EditStore.php                # RENOMEAR de EditarLoja.php
├── ViewStore.php                # RENOMEAR de VerLoja.php
└── DeleteStore.php              # RENOMEAR de ApagarLoja.php

app/adms/Models/
├── AdmsListStores.php           # RENOMEAR de AdmsListarLojas.php
├── AdmsAddStore.php             # RENOMEAR de AdmsCadastrarLoja.php
├── AdmsEditStore.php            # RENOMEAR de AdmsEditarLoja.php
├── AdmsViewStore.php            # RENOMEAR de AdmsVerLoja.php
└── AdmsDeleteStore.php          # RENOMEAR de AdmsApagarLoja.php

app/adms/Views/store/            # RENOMEAR diretório de loja/
├── loadStore.php                # NOVO: Página principal
├── listStore.php                # RENOMEAR de listarLojas.php
└── partials/
    ├── _add_store_modal.php     # NOVO (conteúdo de cadLoja.php)
    ├── _edit_store_modal.php    # NOVO (conteúdo de editarLojas.php)
    ├── _view_store_modal.php    # NOVO (conteúdo de verLoja.php)
    └── _delete_store_modal.php  # NOVO

assets/js/
└── store.js                     # NOVO

tests/Store/
├── AdmsAddStoreTest.php         # NOVO
├── AdmsEditStoreTest.php        # NOVO
├── AdmsDeleteStoreTest.php      # NOVO
└── AdmsListStoresTest.php       # NOVO
```

### 2.2. Arquivos a Remover (após refatoração)

```
app/adms/Controllers/
├── Lojas.php
├── CadastrarLoja.php
├── EditarLoja.php
├── VerLoja.php
└── ApagarLoja.php

app/adms/Models/
├── AdmsListarLojas.php
├── AdmsCadastrarLoja.php
├── AdmsEditarLoja.php
├── AdmsVerLoja.php
└── AdmsApagarLoja.php

app/adms/Views/loja/
├── listarLojas.php
├── cadLoja.php
├── editarLojas.php
└── verLoja.php
```

---

## 3. Mapeamento de Nomenclatura

### 3.1. Controllers

| Arquivo Atual | Novo Arquivo | Classe Atual | Nova Classe |
|---------------|--------------|--------------|-------------|
| `Lojas.php` | `Store.php` | `Lojas` | `Store` |
| `CadastrarLoja.php` | `AddStore.php` | `CadastrarLoja` | `AddStore` |
| `EditarLoja.php` | `EditStore.php` | `EditarLoja` | `EditStore` |
| `VerLoja.php` | `ViewStore.php` | `VerLoja` | `ViewStore` |
| `ApagarLoja.php` | `DeleteStore.php` | `ApagarLoja` | `DeleteStore` |

### 3.2. Models

| Arquivo Atual | Novo Arquivo | Classe Atual | Nova Classe |
|---------------|--------------|--------------|-------------|
| `AdmsListarLojas.php` | `AdmsListStores.php` | `AdmsListarLojas` | `AdmsListStores` |
| `AdmsCadastrarLoja.php` | `AdmsAddStore.php` | `AdmsCadastrarLoja` | `AdmsAddStore` |
| `AdmsEditarLoja.php` | `AdmsEditStore.php` | `AdmsEditarLoja` | `AdmsEditStore` |
| `AdmsVerLoja.php` | `AdmsViewStore.php` | `AdmsVerLoja` | `AdmsViewStore` |
| `AdmsApagarLoja.php` | `AdmsDeleteStore.php` | `AdmsApagarLoja` | `AdmsDeleteStore` |

### 3.3. Views

| Arquivo Atual | Novo Arquivo |
|---------------|--------------|
| `loja/listarLojas.php` | `store/listStore.php` |
| `loja/cadLoja.php` | `store/partials/_add_store_modal.php` |
| `loja/editarLojas.php` | `store/partials/_edit_store_modal.php` |
| `loja/verLoja.php` | `store/partials/_view_store_modal.php` |
| - | `store/loadStore.php` (NOVO) |
| - | `store/partials/_delete_store_modal.php` (NOVO) |

### 3.4. URLs

| URL Atual | Nova URL |
|-----------|----------|
| `/lojas/listar-lojas` | `/store/list` |
| `/cadastrar-loja/cad-loja` | `/add-store/create` |
| `/editar-loja/edit-loja/{id}` | `/edit-store/update/{id}` |
| `/ver-loja/ver-loja/{id}` | `/view-store/view/{id}` |
| `/apagar-loja/apagar-loja/{id}` | `/delete-store/delete/{id}` |

---

## 4. Implementação Detalhada

### 4.1. Controller Principal: `Store.php`

```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsMenu;
use App\adms\Models\AdmsBotao;
use App\adms\Models\AdmsListStores;
use App\adms\Services\FormSelectRepository;
use Core\ConfigView;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller de Lojas
 *
 * Gerencia listagem e busca de lojas
 *
 * @author Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class Store
{
    /** @var array|null Dados para a view */
    private ?array $data = [];

    /** @var int Página atual */
    private int $pageId;

    /**
     * Método principal de listagem
     *
     * @param int|string|null $pageId Número da página
     * @return void
     */
    public function list(int|string|null $pageId = null): void
    {
        $this->pageId = (int) ($pageId ?: 1);

        $requestType = filter_input(INPUT_GET, 'typestore', FILTER_VALIDATE_INT);

        match ($requestType) {
            1 => $this->listAllItems(),
            2 => $this->searchItems(),
            default => $this->loadInitialPage(),
        };
    }

    /**
     * Carrega página inicial completa
     */
    private function loadInitialPage(): void
    {
        $this->loadCommonData();
        $this->loadListData();
        $this->loadSelectData();

        $loadView = new ConfigView("adms/Views/store/loadStore", $this->data);
        $loadView->renderizar();
    }

    /**
     * Lista todos os itens (AJAX)
     */
    private function listAllItems(): void
    {
        $this->loadListData();

        $loadView = new ConfigView("adms/Views/store/listStore", $this->data);
        $loadView->renderList();
    }

    /**
     * Busca com filtros (AJAX)
     */
    private function searchItems(): void
    {
        $filters = [
            'name' => filter_input(INPUT_POST, 'search_name', FILTER_SANITIZE_SPECIAL_CHARS),
            'network_id' => filter_input(INPUT_POST, 'search_network', FILTER_VALIDATE_INT),
            'status_id' => filter_input(INPUT_POST, 'search_status', FILTER_VALIDATE_INT),
        ];

        $listModel = new AdmsListStores();
        $this->data['listStore'] = $listModel->search($filters, $this->pageId);
        $this->data['pagination'] = $listModel->getResultPg();

        $loadView = new ConfigView("adms/Views/store/listStore", $this->data);
        $loadView->renderList();
    }

    /**
     * Carrega dados comuns (menu, botões)
     */
    private function loadCommonData(): void
    {
        $buttons = [
            'add_store' => ['menu_controller' => 'add-store', 'menu_metodo' => 'create'],
            'view_store' => ['menu_controller' => 'view-store', 'menu_metodo' => 'view'],
            'edit_store' => ['menu_controller' => 'edit-store', 'menu_metodo' => 'update'],
            'delete_store' => ['menu_controller' => 'delete-store', 'menu_metodo' => 'delete']
        ];

        $listButtons = new AdmsBotao();
        $this->data['button'] = $listButtons->valBotao($buttons);

        $listMenu = new AdmsMenu();
        $this->data['menu'] = $listMenu->itemMenu();
    }

    /**
     * Carrega dados de listagem
     */
    private function loadListData(): void
    {
        $listModel = new AdmsListStores();
        $this->data['listStore'] = $listModel->list($this->pageId);
        $this->data['pagination'] = $listModel->getResultPg();
    }

    /**
     * Carrega dados de select para filtros
     */
    private function loadSelectData(): void
    {
        $this->data['select'] = [
            'networks' => FormSelectRepository::getNetworks(),
            'status' => FormSelectRepository::getStoreStatus(),
        ];
    }
}
```

### 4.2. Model de Listagem: `AdmsListStores.php`

```php
<?php

namespace App\adms\Models;

use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsPaginacao;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Model para listagem de lojas
 *
 * @author Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class AdmsListStores
{
    /** @var array|null Resultado da query */
    private ?array $result = null;

    /** @var int Página atual */
    private int $pageId;

    /** @var int Limite por página */
    private int $limitResult = LIMIT;

    /** @var string|null HTML da paginação */
    private ?string $resultPg = null;

    /**
     * Retorna HTML da paginação
     *
     * @return string|null
     */
    public function getResultPg(): ?string
    {
        return $this->resultPg;
    }

    /**
     * Lista lojas paginadas
     *
     * @param int $pageId Número da página
     * @return array|null
     */
    public function list(int $pageId = 1): ?array
    {
        $this->pageId = $pageId;

        $pagination = new AdmsPaginacao(URLADM . 'store/list');
        $pagination->condicao($this->pageId, $this->limitResult);
        $pagination->paginacao("SELECT COUNT(id_loja) AS num_result FROM tb_lojas");
        $this->resultPg = $pagination->getResultado();

        $read = new AdmsRead();
        $read->fullRead(
            "SELECT lj.*, r.nome AS rede, st.nome AS status
             FROM tb_lojas lj
             INNER JOIN tb_status_loja st ON st.id = lj.status_id
             INNER JOIN tb_redes r ON r.id = lj.rede_id
             ORDER BY lj.nome ASC
             LIMIT :limit OFFSET :offset",
            "limit={$this->limitResult}&offset={$pagination->getOffset()}"
        );

        $this->result = $read->getResult();
        return $this->result;
    }

    /**
     * Busca lojas com filtros
     *
     * @param array $filters Filtros de busca
     * @param int $pageId Número da página
     * @return array|null
     */
    public function search(array $filters, int $pageId = 1): ?array
    {
        $this->pageId = $pageId;

        $whereConditions = [];
        $params = [];

        if (!empty($filters['name'])) {
            $whereConditions[] = "lj.nome LIKE :name";
            $params[] = "name=%" . $filters['name'] . "%";
        }

        if (!empty($filters['network_id'])) {
            $whereConditions[] = "lj.rede_id = :network_id";
            $params[] = "network_id=" . $filters['network_id'];
        }

        if (!empty($filters['status_id'])) {
            $whereConditions[] = "lj.status_id = :status_id";
            $params[] = "status_id=" . $filters['status_id'];
        }

        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
        $paramString = !empty($params) ? implode("&", $params) : "";

        // Paginação
        $pagination = new AdmsPaginacao(URLADM . 'store/list');
        $pagination->condicao($this->pageId, $this->limitResult);

        $countQuery = "SELECT COUNT(lj.id_loja) AS num_result FROM tb_lojas lj {$whereClause}";
        $pagination->paginacao($countQuery, $paramString ?: null);
        $this->resultPg = $pagination->getResultado();

        // Query principal
        $read = new AdmsRead();
        $query = "SELECT lj.*, r.nome AS rede, st.nome AS status
                  FROM tb_lojas lj
                  INNER JOIN tb_status_loja st ON st.id = lj.status_id
                  INNER JOIN tb_redes r ON r.id = lj.rede_id
                  {$whereClause}
                  ORDER BY lj.nome ASC
                  LIMIT :limit OFFSET :offset";

        $fullParams = $paramString ? $paramString . "&" : "";
        $fullParams .= "limit={$this->limitResult}&offset={$pagination->getOffset()}";

        $read->fullRead($query, $fullParams);
        $this->result = $read->getResult();

        return $this->result;
    }
}
```

### 4.3. Model de Adição: `AdmsAddStore.php`

```php
<?php

namespace App\adms\Models;

use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsCreate;
use App\adms\Models\helper\AdmsCampoVazio;
use App\adms\Services\LoggerService;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Model para adicionar lojas
 *
 * @author Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class AdmsAddStore
{
    /** @var bool Resultado da operação */
    private bool $result = false;

    /** @var array|null Dados do formulário */
    private ?array $data = null;

    /** @var string|null Mensagem de erro */
    private ?string $error = null;

    /** @var int|null ID do registro inserido */
    private ?int $lastInsertId = null;

    /**
     * Retorna resultado da operação
     *
     * @return bool
     */
    public function getResult(): bool
    {
        return $this->result;
    }

    /**
     * Retorna mensagem de erro
     *
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Retorna ID do registro inserido
     *
     * @return int|null
     */
    public function getLastInsertId(): ?int
    {
        return $this->lastInsertId;
    }

    /**
     * Adiciona nova loja
     *
     * @param array $data Dados da loja
     * @return bool
     */
    public function create(array $data): bool
    {
        $this->data = $data;

        // Remove formatação do CNPJ
        $this->data['cnpj'] = preg_replace('/\D/', '', $this->data['cnpj'] ?? '');
        $this->data['ins_estadual'] = preg_replace('/\D/', '', $this->data['ins_estadual'] ?? '');

        // Validação de campos vazios
        $validator = new AdmsCampoVazio();
        $validator->validarDados($this->data);

        if (!$validator->getResultado()) {
            $this->error = 'Preencha todos os campos obrigatórios.';
            $this->result = false;
            return false;
        }

        // Verifica duplicidade de CNPJ
        if ($this->cnpjExists($this->data['cnpj'])) {
            $this->error = 'CNPJ já cadastrado no sistema.';
            $this->result = false;
            return false;
        }

        return $this->insertStore();
    }

    /**
     * Verifica se CNPJ já existe
     *
     * @param string $cnpj CNPJ a verificar
     * @return bool
     */
    private function cnpjExists(string $cnpj): bool
    {
        $read = new AdmsRead();
        $read->fullRead(
            "SELECT id_loja FROM tb_lojas WHERE cnpj = :cnpj LIMIT 1",
            "cnpj={$cnpj}"
        );
        return !empty($read->getResult());
    }

    /**
     * Insere loja no banco
     *
     * @return bool
     */
    private function insertStore(): bool
    {
        $this->data['created'] = date('Y-m-d H:i:s');

        $create = new AdmsCreate();
        $create->exeCreate('tb_lojas', $this->data);

        if ($create->getResult()) {
            $this->lastInsertId = (int) $create->getResult();
            $this->result = true;

            LoggerService::info('STORE_CREATED', 'Loja criada com sucesso', [
                'store_id' => $this->lastInsertId,
                'name' => $this->data['nome'] ?? null,
                'cnpj' => $this->data['cnpj'] ?? null,
                'created_by' => $_SESSION['usuario_id'] ?? null
            ]);

            return true;
        }

        $this->error = 'Erro ao cadastrar loja.';
        $this->result = false;

        LoggerService::error('STORE_CREATE_FAILED', 'Erro ao criar loja', [
            'data' => $this->data,
            'error' => $create->getError() ?? 'Unknown error'
        ]);

        return false;
    }

    /**
     * Lista dados para selects do formulário
     *
     * @return array
     */
    public function listFormData(): array
    {
        $read = new AdmsRead();

        // Status
        $read->fullRead("SELECT id AS sit_id, nome AS sit FROM tb_status_loja ORDER BY id ASC");
        $status = $read->getResult();

        // Redes
        $read->fullRead("SELECT id AS rede_id, nome AS rede FROM tb_redes ORDER BY nome ASC");
        $networks = $read->getResult();

        // Gerentes
        $read->fullRead(
            "SELECT id AS func_id, name_employee AS func
             FROM adms_employees
             WHERE position_id = :position_id AND adms_status_employee_id = :status_id
             ORDER BY name_employee ASC",
            "position_id=2&status_id=2"
        );
        $managers = $read->getResult();

        return [
            'status' => $status,
            'networks' => $networks,
            'managers' => $managers
        ];
    }
}
```

### 4.4. JavaScript: `store.js`

```javascript
/**
 * Store Module JavaScript
 *
 * @author Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialize module
    initStoreModule();
});

/**
 * Initialize store module
 */
function initStoreModule() {
    // Load initial list
    if (document.getElementById('content_store')) {
        listStores(1);
    }

    // Setup event listeners
    setupEventListeners();
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    const container = document.getElementById('store-container');
    if (!container) return;

    // Pagination clicks
    container.addEventListener('click', function (e) {
        const paginationLink = e.target.closest('.pagination a');
        if (paginationLink) {
            e.preventDefault();
            const page = paginationLink.dataset.page || extractPageFromUrl(paginationLink.href);
            listStores(page);
        }
    });

    // Search form
    const searchForm = document.getElementById('search-store-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            performSearch();
        });
    }

    // Clear filters button
    const clearBtn = document.getElementById('btn-clear-filters');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearFilters);
    }
}

/**
 * List stores with pagination
 *
 * @param {number} page Page number
 */
function listStores(page = 1) {
    const container = document.getElementById('content_store');
    if (!container) return;

    showLoading(container);

    fetch(`${URLADM}store/list/${page}?typestore=1`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading stores:', error);
            container.innerHTML = '<div class="alert alert-danger">Erro ao carregar lojas.</div>';
        });
}

/**
 * Perform search with filters
 */
function performSearch() {
    const container = document.getElementById('content_store');
    if (!container) return;

    showLoading(container);

    const formData = new FormData(document.getElementById('search-store-form'));

    fetch(`${URLADM}store/list/1?typestore=2`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error searching stores:', error);
            container.innerHTML = '<div class="alert alert-danger">Erro ao pesquisar lojas.</div>';
        });
}

/**
 * Clear all filters
 */
function clearFilters() {
    const form = document.getElementById('search-store-form');
    if (form) {
        form.reset();
    }
    listStores(1);
}

/**
 * Show loading indicator
 *
 * @param {HTMLElement} container Container element
 */
function showLoading(container) {
    container.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Carregando...</span>
            </div>
        </div>
    `;
}

/**
 * Extract page number from URL
 *
 * @param {string} url URL string
 * @returns {number} Page number
 */
function extractPageFromUrl(url) {
    const match = url.match(/\/list\/(\d+)/);
    return match ? parseInt(match[1]) : 1;
}

/**
 * Open add store modal
 */
function openAddStoreModal() {
    fetch(`${URLADM}add-store/form`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(response => response.text())
        .then(html => {
            document.getElementById('modal-container').innerHTML = html;
            $('#addStoreModal').modal('show');
        });
}

/**
 * Open edit store modal
 *
 * @param {number} id Store ID
 */
function openEditStoreModal(id) {
    fetch(`${URLADM}edit-store/form/${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(response => response.text())
        .then(html => {
            document.getElementById('modal-container').innerHTML = html;
            $('#editStoreModal').modal('show');
        });
}

/**
 * Open view store modal
 *
 * @param {number} id Store ID
 */
function openViewStoreModal(id) {
    fetch(`${URLADM}view-store/view/${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(response => response.text())
        .then(html => {
            document.getElementById('modal-container').innerHTML = html;
            $('#viewStoreModal').modal('show');
        });
}

/**
 * Confirm delete store
 *
 * @param {number} id Store ID
 * @param {string} name Store name
 */
function confirmDeleteStore(id, name) {
    document.getElementById('delete-store-id').value = id;
    document.getElementById('delete-store-name').textContent = name;
    $('#deleteStoreModal').modal('show');
}

/**
 * Submit add store form
 *
 * @param {Event} event Form submit event
 */
function submitAddStore(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('[type="submit"]');

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';

    fetch(`${URLADM}add-store/create`, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#addStoreModal').modal('hide');
                showNotification('success', data.message || 'Loja cadastrada com sucesso!');
                listStores(1);
            } else {
                showNotification('error', data.message || 'Erro ao cadastrar loja.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'Erro ao processar requisição.');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Cadastrar';
        });
}

/**
 * Show notification toast
 *
 * @param {string} type Notification type (success, error, warning)
 * @param {string} message Message to display
 */
function showNotification(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

    const html = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="fas ${icon} mr-2"></i>${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;

    const container = document.getElementById('notification-area');
    if (container) {
        container.innerHTML = html;
        setTimeout(() => {
            const alert = container.querySelector('.alert');
            if (alert) alert.remove();
        }, 5000);
    }
}

// Export functions for global access
window.listStores = listStores;
window.performSearch = performSearch;
window.clearFilters = clearFilters;
window.openAddStoreModal = openAddStoreModal;
window.openEditStoreModal = openEditStoreModal;
window.openViewStoreModal = openViewStoreModal;
window.confirmDeleteStore = confirmDeleteStore;
window.submitAddStore = submitAddStore;
```

---

## 5. FormSelectRepository

### 5.1. Métodos Adicionados

Os seguintes métodos foram adicionados ao `FormSelectRepository` para o módulo de Lojas:

```php
// ========================================================================
// METODOS ESPECIFICOS - STORES (LOJAS)
// ========================================================================

/**
 * Busca dados para formularios de lojas
 * @return array
 */
public function getStoreFormData(): array {
    return [
        'networks' => $this->getNetworks(),
        'statuses' => $this->getStoreStatus(),
        'managers' => $this->getStoreManagers(),
        'supervisors' => $this->getStoreSupervisors(),
    ];
}

/**
 * Busca redes/marcas
 * Keys: network_id, network_name
 * @return array
 */
public function getNetworks(): array;

/**
 * Busca status de lojas
 * Keys: status_id, status_name
 * @return array
 */
public function getStoreStatus(): array;

/**
 * Busca gerentes para lojas (cargo nível gestor + status ativo)
 * Keys: manager_id, manager_name
 * @return array
 */
public function getStoreManagers(): array;

/**
 * Busca supervisores para lojas (cargo nível gestor + status ativo)
 * Keys: supervisor_id, supervisor_name
 * @return array
 */
public function getStoreSupervisors(): array;
```

### 5.2. Padrão de Keys

| Método | Keys |
|--------|------|
| `getNetworks()` | `network_id`, `network_name` |
| `getStoreStatus()` | `status_id`, `status_name` |
| `getStoreManagers()` | `manager_id`, `manager_name` |
| `getStoreSupervisors()` | `supervisor_id`, `supervisor_name` |

### 5.3. Uso nos Controllers

```php
use App\adms\Services\FormSelectRepository;

// No controller
$formSelect = new FormSelectRepository();
$this->data['select'] = $formSelect->getStoreFormData();

// Ou individualmente
$this->data['select']['networks'] = $formSelect->getNetworks();
$this->data['select']['statuses'] = $formSelect->getStoreStatus();
```

### 5.4. Uso nas Views

```php
<!-- Select de Redes -->
<select name="rede_id" class="form-control" required>
    <option value="">Selecione</option>
    <?php foreach ($this->Dados['select']['networks'] ?? [] as $network): ?>
        <option value="<?= $network['network_id'] ?>"
            <?= ($valorForm['rede_id'] ?? '') == $network['network_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($network['network_name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach; ?>
</select>

<!-- Select de Status -->
<select name="status_id" class="form-control" required>
    <option value="">Selecione</option>
    <?php foreach ($this->Dados['select']['statuses'] ?? [] as $status): ?>
        <option value="<?= $status['status_id'] ?>"
            <?= ($valorForm['status_id'] ?? '') == $status['status_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($status['status_name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach; ?>
</select>

<!-- Select de Gerentes -->
<select name="func_id" class="form-control" required>
    <option value="">Selecione</option>
    <?php foreach ($this->Dados['select']['managers'] ?? [] as $manager): ?>
        <option value="<?= $manager['manager_id'] ?>"
            <?= ($valorForm['func_id'] ?? '') == $manager['manager_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($manager['manager_name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach; ?>
</select>

<!-- Select de Supervisores -->
<select name="super_id" class="form-control">
    <option value="">Selecione</option>
    <?php foreach ($this->Dados['select']['supervisors'] ?? [] as $supervisor): ?>
        <option value="<?= $supervisor['supervisor_id'] ?>"
            <?= ($valorForm['super_id'] ?? '') == $supervisor['supervisor_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($supervisor['supervisor_name'], ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach; ?>
</select>
```

---

## 6. Ordem de Implementação

### Fase 1: Preparação
1. ✅ Criar documentação de análise
2. ✅ Criar plano de refatoração
3. ✅ Adicionar métodos ao FormSelectRepository
4. Criar estrutura de diretórios

### Fase 2: Models (mantendo separados)
4. Criar `AdmsListStores.php` (renomear e refatorar)
5. Criar `AdmsAddStore.php` (renomear e refatorar)
6. Criar `AdmsEditStore.php` (renomear e refatorar)
7. Criar `AdmsViewStore.php` (renomear e refatorar)
8. Criar `AdmsDeleteStore.php` (renomear e refatorar)

### Fase 3: Controllers
9. Criar `Store.php` (controller principal)
10. Criar `AddStore.php` (JSON response)
11. Criar `EditStore.php` (JSON response)
12. Criar `ViewStore.php` (HTML partial)
13. Criar `DeleteStore.php` (JSON response)

### Fase 4: Views
14. Criar `store/loadStore.php`
15. Criar `store/listStore.php`
16. Criar `store/partials/_add_store_modal.php`
17. Criar `store/partials/_edit_store_modal.php`
18. Criar `store/partials/_view_store_modal.php`
19. Criar `store/partials/_delete_store_modal.php`

### Fase 5: JavaScript
20. Criar `assets/js/store.js`

### Fase 6: Testes
21. Criar `tests/Store/AdmsAddStoreTest.php`
22. Criar `tests/Store/AdmsEditStoreTest.php`
23. Criar `tests/Store/AdmsDeleteStoreTest.php`
24. Criar `tests/Store/AdmsListStoresTest.php`

### Fase 7: Banco de Dados
25. Atualizar `adms_paginas` com novas rotas
26. Atualizar `adms_nivacs_pgs` com permissões
27. Atualizar `adms_menus` se necessário

### Fase 8: Limpeza
28. Remover arquivos antigos
29. Testar fluxo completo
30. Atualizar documentação

---

## 7. Scripts SQL para Atualização de Rotas

```sql
-- Backup das rotas antigas
SELECT * FROM adms_paginas WHERE menu_controller LIKE '%loja%';

-- Atualizar rotas existentes (exemplo)
UPDATE adms_paginas SET
    menu_controller = 'store',
    menu_metodo = 'list',
    nome_pagina = 'Listar Lojas'
WHERE menu_controller = 'lojas' AND menu_metodo = 'listar-lojas';

UPDATE adms_paginas SET
    menu_controller = 'add-store',
    menu_metodo = 'create',
    nome_pagina = 'Adicionar Loja'
WHERE menu_controller = 'cadastrar-loja' AND menu_metodo = 'cad-loja';

UPDATE adms_paginas SET
    menu_controller = 'edit-store',
    menu_metodo = 'update',
    nome_pagina = 'Editar Loja'
WHERE menu_controller = 'editar-loja' AND menu_metodo = 'edit-loja';

UPDATE adms_paginas SET
    menu_controller = 'view-store',
    menu_metodo = 'view',
    nome_pagina = 'Visualizar Loja'
WHERE menu_controller = 'ver-loja' AND menu_metodo = 'ver-loja';

UPDATE adms_paginas SET
    menu_controller = 'delete-store',
    menu_metodo = 'delete',
    nome_pagina = 'Excluir Loja'
WHERE menu_controller = 'apagar-loja' AND menu_metodo = 'apagar-loja';
```

---

## 8. Checklist de Verificação

### 7.1. Funcional

- [ ] Listagem com paginação funciona
- [ ] Pesquisa por nome funciona
- [ ] Filtro por rede funciona
- [ ] Filtro por status funciona
- [ ] Cadastro de nova loja funciona
- [ ] Edição de loja funciona
- [ ] Visualização de loja funciona
- [ ] Exclusão de loja funciona
- [ ] Validação de CNPJ duplicado funciona

### 7.2. Técnico

- [ ] Type hints em todos os métodos
- [ ] PHPDoc em métodos públicos
- [ ] LoggerService em operações CRUD
- [ ] Prepared statements (SQL Injection)
- [ ] htmlspecialchars em outputs (XSS)
- [ ] CSRF protection em formulários
- [ ] Responsivo (mobile + desktop)
- [ ] Console sem erros JavaScript

### 7.3. Testes

- [ ] Testes unitários passando
- [ ] Testes de integração passando
- [ ] Cobertura mínima de 80%

---

## 9. Estimativa de Esforço

| Fase | Arquivos | Complexidade |
|------|----------|--------------|
| Models | 5 | Média |
| Controllers | 5 | Média |
| Views | 6 | Média |
| JavaScript | 1 | Média |
| Testes | 4 | Média |
| SQL/Config | - | Baixa |
| **Total** | **21** | **Média** |

---

## 10. Riscos e Mitigações

| Risco | Impacto | Mitigação |
|-------|---------|-----------|
| Quebra de dependências | Alto | Manter URLs antigas temporariamente |
| Perda de dados | Alto | Backup antes da migração |
| Regressão de funcionalidades | Médio | Testes automatizados |

---

*Documento criado em: 16/01/2026*
*Referência: ANALISE_MODULO_LOJAS.md*
