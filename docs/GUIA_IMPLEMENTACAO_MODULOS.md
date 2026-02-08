# Guia de Implementação para Novos Módulos

**Última Atualização:** 21/01/2026
**Versão:** 2.0

## 1. Introdução

Este documento estabelece o padrão de arquitetura e o fluxo de trabalho para a criação de novos módulos no sistema Mercury.

### 1.1 Módulo de Referência: Sales

O módulo **Sales** (Vendas), refatorado em Janeiro/2026, é a **implementação de referência principal** para módulos complexos. Consulte os arquivos em:

```
app/adms/Controllers/Sales.php              # Controller principal
app/adms/Models/AdmsStatisticsSales.php     # Estatísticas
assets/js/sales.js                          # JavaScript moderno
tests/Sales/                                # Suite de testes (113 testes)
```

### 1.2 Objetivo

Garantir que novos módulos mantenham a consistência, a separação de responsabilidades e a qualidade do código existente, seguindo os padrões estabelecidos.

## 2. Estrutura de Diretórios

Para um novo módulo chamado `MeuModulo`, a seguinte estrutura de arquivos e diretórios deve ser criada dentro de `app/adms/`:

```
app/adms/
├───Controllers/
│   ├───MeuModulo.php             # Controller principal para listagem e busca
│   ├───AddMeuModulo.php          # Controller para a ação de ADICIONAR
│   ├───EditMeuModulo.php         # Controller para a ação de EDITAR
│   ├───DeleteMeuModulo.php       # Controller para a ação de DELETAR
│   ├───ViewMeuModulo.php         # Controller para a ação de VISUALIZAR
│   └───StatisticsMeuModulo.php   # Controller para a ação de ESTATÍSTICAS (se aplicável)
│
├───Models/
│   ├───AdmsListMeuModulo.php     # Model para listar e buscar os dados
│   ├───AdmsAddMeuModulo.php      # Model com a lógica de negócio para ADICIONAR
│   ├───AdmsEditMeuModulo.php     # Model com a lógica de negócio para EDITAR
│   ├───AdmsDeleteMeuModulo.php   # Model com a lógica de negócio para DELETAR
│   ├───AdmsViewMeuModulo.php     # Model para buscar os dados de um item específico
│   └───AdmsStatisticsMeuModulo.php # Model com a lógica para ESTATÍSTICAS (se aplicável)
│
└───Views/
    └───meuModulo/
        ├───listMeuModulo.php         # View principal que exibe a lista/tabela
        ├───_addMeuModuloModal.php    # View parcial (modal) para o formulário de adição
        ├───_editMeuModuloModal.php   # View parcial (modal) para o formulário de edição
        ├───_viewMeuModuloModal.php   # View parcial (modal) para visualização de detalhes
        ├───_statisticsMeuModuloModal.php # View parcial (modal) para estatísticas (se aplicável)
        └───_formSelectOptions.php  # Exemplo de parcial para opções de SELECT (se necessário)
```

## 3. Fluxo de Dados (Padrão AJAX)

A maioria das interações CRUD e de visualização segue um fluxo assíncrono:

1.  **View Principal (`listMeuModulo.php`)**: O usuário clica em um botão (ex: "Adicionar", "Editar", "Visualizar", "Estatísticas").
2.  **JavaScript**: Um evento de clique aciona uma função JavaScript que:
    *   Determina o modal a ser carregado (ex: `#addMeuModuloModal`).
    *   Pode fazer uma requisição AJAX para um controller de ação (ex: `view-meu-modulo/view`) para obter dados específicos antes de abrir o modal de visualização/edição.
    *   Carrega e exibe o modal correspondente.
3.  **View de Modal (`_addMeuModuloModal.php`, etc.)**:
    *   Para Adicionar/Editar: O usuário preenche/edita o formulário e clica em "Salvar".
    *   Para Visualizar/Estatísticas: O modal exibe os dados ou gráficos.
4.  **JavaScript (AJAX)**:
    *   Para Adicionar/Editar: O formulário é enviado via uma requisição `POST` assíncrona (AJAX) para o controller de ação correspondente (ex: `add-meu-modulo/create`).
    *   Para Visualizar/Estatísticas: Pode haver requisições AJAX adicionais para carregar dados dinâmicos dentro do modal.
5.  **Controller de Ação (`AddMeuModulo.php`, etc.)**:
    *   Recebe e sanitiza os dados (se `POST`).
    *   Instancia o `Model` correspondente.
    *   Chama o método principal do Model, passando os dados.
    *   Recebe o resultado (`true` ou `false` para operações, ou dados para visualização) do Model.
    *   Utiliza serviços auxiliares (como `LoggerService`, `NotificationService`).
    *   Retorna uma resposta `JSON` padronizada para o frontend (ex: `{'status': 'success', 'message': '...', 'data': {...}}`).
6.  **JavaScript (Callback do AJAX)**: A função de callback do AJAX recebe a resposta JSON, exibe uma notificação (toast), fecha o modal (para operações CRUD), e atualiza a tabela de dados na view principal, se necessário.

## 4. Implementação Detalhada

### 4.1. Controller de Ação (Ex: `AddMeuModulo.php`)

Este tipo de controller orquestra uma única ação. Ele é "magro", contendo o mínimo de lógica possível.

**Exemplo:** `app/adms/Controllers/AddMeuModulo.php`

```php
<?php

namespace Adms\\Controllers;

use Adms\\Models\\AdmsAddMeuModulo;
use Core\\Logs\\LoggerService;
use Core\\Notifications\\NotificationService;

class AddMeuModulo
{
    private array|null $data = [];

    public function create(): void
    {
        // 1. Receber e sanitizar dados do formulário
        $this->data = filter_input_array(INPUT_POST, FILTER_DEFAULT);

        // 2. Verificar se o formulário foi enviado
        if (empty($this->data['SendAddMeuModulo'])) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Erro: Requisição inválida.']);
            return;
        }
        
        // 3. Instanciar o Model
        $addMeuModulo = new AdmsAddMeuModulo();

        // 4. Chamar o método de negócio no Model
        $result = $addMeuModulo->create($this->data);

        // 5. Processar o resultado e retornar JSON
        if ($result) {
            LoggerService::log('create', $this->getLogDetails());
            NotificationService::add('success', 'Novo item cadastrado com sucesso!');
            $this->jsonResponse(['status' => 'success', 'message' => 'Item cadastrado com sucesso.']);
        } else {
            // Em caso de erro, a mensagem já é definida no Model
            $this->jsonResponse(['status' => 'error', 'message' => $addMeuModulo->getErro()]);
        }
    }

    private function jsonResponse(array $response): void
    {
        header('Content-Type: application/json');
        echo json_encode($response);
    }
    
    private function getLogDetails(): array
    {
        // Retorna detalhes para o log, incluindo ID do usuário logado (se disponível)
        return [
            'module' => 'MeuModulo',
            'action' => 'create',
            'user_id' => $_SESSION['user_id'] ?? null,
            'data_summary' => ['id' => $this->data['id'] ?? 'novo'] // Adicione um resumo dos dados relevantes
        ];
    }
}
```

### 4.2. Model de Ação (Ex: `AdmsAddMeuModulo.php`)

O Model contém a essência da lógica de negócio. Ele valida os dados, prepara-os para o banco de dados e realiza a operação de persistência, geralmente através de classes auxiliares (`AdmsCreate`).

**Exemplo:** `app/adms/Models/AdmsAddMeuModulo.php`

```php
<?php

namespace Adms\\Models;

use Adms\\Models\\helpers\\AdmsCreate;
use Core\\Logs\\LoggerService; // Pode ser utilizado para logar erros mais detalhados
use Core\\Notifications\\NotificationService; // Para notificar erros específicos de validação

class AdmsAddMeuModulo
{
    private array|null $data;
    private bool $result;
    private string $erro;

    public function getResult(): bool
    {
        return $this->result;
    }

    public function getErro(): string
    {
        return $this->erro;
    }

    public function create(array $data): bool
    {
        $this->data = $data;

        // 1. Validar os dados
        if (!$this->validate()) {
            NotificationService::add('warning', $this->getErro()); // Notifica o erro de validação
            LoggerService::log('validation_error', ['module' => 'MeuModulo', 'action' => 'create', 'error' => $this->getErro(), 'data' => $this->data]);
            return $this->result = false;
        }
        
        // 2. Preparar os dados para inserção no banco
        $this->prepareData();

        // 3. Usar a classe auxiliar para criar o registro
        $create = new AdmsCreate();
        $create->exeCreate('tabela_meu_modulo', $this->data);

        if ($create->getResult()) {
            $this->result = true;
        } else {
            $this->erro = "Erro: Não foi possível cadastrar o item. Tente novamente.";
            LoggerService::log('db_error', ['module' => 'MeuModulo', 'action' => 'create', 'error' => $create->getErro(), 'data' => $this->data]);
            $this->result = false;
        }
        
        return $this->result;
    }

    private function validate(): bool
    {
        // Exemplo: Validação de campo obrigatório
        if (empty($this->data['nome_campo_obrigatorio'])) {
            $this->erro = "Erro: O campo 'Nome' é obrigatório.";
            return false;
        }

        // Exemplo: Validação de unicidade (se aplicável)
        // $read = new AdmsRead();
        // $read->exeRead('tabela_meu_modulo', 'WHERE nome_campo = :nome', 'nome={$this->data['nome_campo']}');
        // if ($read->getResult()) {
        //     $this->erro = "Erro: Nome já cadastrado.";
        //     return false;
        // }

        // ... outras validações de negócio ...

        return true;
    }

    private function prepareData(): void
    {
        // Mapeia os dados do formulário para as colunas do banco
        $preparedData = [
            'nome_coluna' => $this->data['nome_campo_formulario'],
            'status_id' => $this->data['status_id'] ?? 1, // Valor padrão
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null // Exemplo de uso de dados da sessão
        ];
        
        // Remove campos desnecessários do array original para evitar inserção incorreta
        unset($this->data['SendAddMeuModulo']);

        $this->data = $preparedData;
    }
}
```

### 4.3. Controller Principal (Ex: `MeuModulo.php`)

Este controller gerencia a página de listagem, tratando de buscas, paginação e carregamento da view principal.

**Exemplo:** `app/adms/Controllers/MeuModulo.php`

```php
<?php

namespace Adms\\Controllers;

use Core\\ConfigController;
use Adms\\Models\\AdmsListMeuModulo;
use Core\\ConfigView;
use Core\\Repositories\\FormSelectRepository; // Para popular dropdowns

class MeuModulo extends ConfigController
{
    public function list(): void
    {
        // 1. Carregar dados para a view principal (listagem)
        $listMeuModulo = new AdmsListMeuModulo();
        $this->data['list'] = $listMeuModulo->findAll(); // Exemplo de busca de todos os itens
        
        // 2. Popular dropdowns/selects usando FormSelectRepository (se necessário na view principal)
        $formSelect = new FormSelectRepository();
        $this->data['statusOptions'] = $formSelect->getOptions('status_table'); // Exemplo
        
        // 3. Carregar a view principal
        $loadView = new ConfigView("adms/Views/meuModulo/listMeuModulo", $this->data);
        $loadView->loadView();
    }
    
    public function search(): void
    {
        // Lógica para busca, geralmente chamada via AJAX, retorna JSON
        // ...
        $searchData = filter_input_array(INPUT_GET, FILTER_DEFAULT); // Ou POST
        $listMeuModulo = new AdmsListMeuModulo();
        $results = $listMeuModulo->search($searchData); // Exemplo
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'data' => $results]);
    }

    public function export(): void
    {
        // Lógica para exportação de dados, geralmente acionada por um botão na lista
        // Geralmente utiliza o ExportService
        // ...
        // $exportService = new ExportService();
        // $dataToExport = $this->data['list']; // Ou buscar novamente
        // $exportService->exportToExcel($dataToExport, 'meus_modulos');
    }
}
```

## 5. Utilização de Views Parciais (Modals)

As views parciais são utilizadas para modularizar o código HTML, especialmente para modais, formulários reusáveis ou componentes dinâmicos.

*   **Convenção de Nomenclatura**: Parciais geralmente começam com um underscore (`_`), por exemplo, `_addMeuModuloModal.php`.
*   **Carregamento**: No contexto de interações AJAX, o conteúdo de um modal parcial pode ser carregado dinamicamente para dentro de um elemento `div` no DOM, ou a lógica JavaScript pode acionar a exibição de um modal que já existe no HTML da página principal, mas com seu conteúdo preenchido via AJAX.

**Exemplo de carregamento de modal (HTML/JavaScript na `listMeuModulo.php`):**

```html
<!-- Botão para abrir o modal de adição -->
<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addMeuModuloModal">
    Adicionar Novo Item
</button>

<!-- Modal de Adição (pode ser incluído diretamente ou carregado via JS) -->
<div class="modal fade" id="addMeuModuloModal" tabindex="-1" role="dialog" aria-labelledby="addMeuModuloModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <!-- O conteúdo do modal viria de _addMeuModuloModal.php -->
      <?php 
          // Se o modal for incluído diretamente na view principal
          // include __DIR__ . "/_addMeuModuloModal.php"; 
          // Alternativamente, o JS pode buscar e injetar o conteúdo
      ?>
      <div class="modal-header">
        <h5 class="modal-title" id="addMeuModuloModalLabel">Adicionar Item</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <!-- Conteúdo do formulário de adição aqui -->
        <!-- Exemplo: Formulário para _addMeuModuloModal.php -->
        <form id="formAddMeuModulo" method="POST" action="add-meu-modulo/create">
            <!-- Campos do formulário -->
            <div class="form-group">
                <label for="campo1">Campo 1</label>
                <input type="text" class="form-control" id="campo1" name="campo1" required>
            </div>
            <button type="submit" class="btn btn-success" name="SendAddMeuModulo" value="1">Salvar</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
    $(document).ready(function() {
        $('#formAddMeuModulo').submit(function(event) {
            event.preventDefault();
            var formData = $(this).serialize();
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message); // Substituir por NotificationService no frontend
                        $('#addMeuModuloModal').modal('hide');
                        // Recarregar tabela ou adicionar item à lista
                        // window.location.reload(); 
                    } else {
                        alert(response.message); // Substituir por NotificationService no frontend
                    }
                },
                error: function() {
                    alert('Erro na requisição.');
                }
            });
        });
    });
</script>
```

## 6. Utilização de Services Comuns

O projeto utiliza diversas classes de serviço para funcionalidades transversais, que devem ser integradas aos novos módulos.

### 6.1. `LoggerService` (`Core\Logs\LoggerService`)

Utilizado para registrar eventos importantes do sistema, como operações CRUD, erros de validação, tentativas de acesso não autorizado, etc. Ajuda na depuração e auditoria do sistema.

*   **Uso Comum**: No Controller após uma operação bem-sucedida ou no Model para registrar validações ou erros de banco de dados.
*   **Exemplo (no Controller):**
    ```php
    use Core\\Logs\\LoggerService;

    // ...
    LoggerService::log('create', [
        'module' => 'MeuModulo',
        'action' => 'create',
        'user_id' => $_SESSION['user_id'] ?? null,
        'data_summary' => ['id' => $newlyCreatedId]
    ]);
    ```

### 6.2. `NotificationService` (`Core\Notifications\NotificationService`)

Responsável por gerenciar e exibir mensagens de notificação (sucesso, erro, alerta, informação) para o usuário. Essas mensagens são geralmente armazenadas em `$_SESSION` e exibidas na próxima requisição, ou retornadas via JSON para serem tratadas no frontend.

*   **Uso Comum**: No Controller para definir mensagens após uma operação, ou no Model para erros de validação.
*   **Exemplo (no Controller):**
    ```php
    use Core\\Notifications\\NotificationService;

    // ...
    NotificationService::add('success', 'Item atualizado com sucesso!');
    // Ou para erros retornados pelo Model
    // NotificationService::add('error', $meuModulo->getErro());
    ```
    No frontend, uma função JavaScript deve ler essas notificações (se passadas via sessão) ou tratar as mensagens do JSON retornado via AJAX para exibi-las em um toast/alert.

### 6.3. `FormSelectRepository` (`Core\Repositories\FormSelectRepository`)

Uma classe utilitária para buscar e formatar dados de tabelas comuns (ex: status, tipos, categorias) para popular elementos `select` (dropdowns) em formulários.

*   **Uso Comum**: No Controller principal (`MeuModulo.php`) para carregar dados de dropdowns que serão exibidos na view principal ou em modais, antes de renderizar a view.
*   **Exemplo (no Controller):**
    ```php
    use Core\\Repositories\\FormSelectRepository;

    // ...
    $formSelect = new FormSelectRepository();
    $this->data['statusOptions'] = $formSelect->getOptions('status_table'); // Retorna array formatado para <option>
    $this->data['categoryOptions'] = $formSelect->getOptions('categories_table', 'id', 'name_field'); // Exemplo com campos específicos
    ```
    A view então itera sobre `$this->data['statusOptions']` para criar as tags `<option>`.

### 6.4. `ExportService` (Assumindo `Core\Services\ExportService`)

Serviço para lidar com a exportação de dados para diferentes formatos, como Excel, CSV ou PDF.

*   **Uso Comum**: Em um método específico dentro do Controller principal (`MeuModulo.php`) ou em um controller dedicado para exportação, acionado por um botão na interface do usuário.
*   **Exemplo (no Controller):**
    ```php
    // Assume que a classe ExportService está disponível
    // use Core\\Services\\ExportService; 

    // ... em um método export() no Controller MeuModulo.php
    public function exportToExcel(): void
    {
        // 1. Obter os dados a serem exportados (pode ser do Model)
        $listMeuModulo = new AdmsListMeuModulo();
        $dataToExport = $listMeuModulo->getAllForExport(); // Método específico no Model

        // 2. Instanciar e usar o serviço de exportação
        $exportService = new ExportService();
        $exportService->exportToExcel($dataToExport, 'relatorio_meus_modulos'); // Nome do arquivo
        // O método exportToExcel já deve enviar os headers e o conteúdo do arquivo
        exit; // Termina a execução após o download
    }
    ```

## 7. Padrões Modernos (Referência: Sales)

### 7.1 Controller Principal com Match Expression

```php
// Exemplo de Sales.php - Roteamento moderno
// NOTA: Permissões de acesso são controladas automaticamente pelo sistema
// através da tabela adms_nivacs_pgs. NÃO use hasPermission() hardcoded.
public function list(?int $pageId = null): void
{
    $this->pageId = (int) ($pageId ?: 1);
    $requestType = filter_input(INPUT_GET, 'typesales', FILTER_VALIDATE_INT);

    // Carregar botões de permissão (via banco de dados)
    $this->loadButtons();

    // Roteamento via match expression
    match ($requestType) {
        1 => $this->listAllItems(),
        2 => $this->searchItems($this->getSearchData()),
        default => $this->loadInitialPage(),
    };
}

// Permissões de botões carregadas do banco via AdmsBotao
private function loadButtons(): void
{
    $buttons = [
        'add_entity' => ['menu_controller' => 'add-entity', 'menu_metodo' => 'create'],
        'view_entity' => ['menu_controller' => 'view-entity', 'menu_metodo' => 'view'],
        'edit_entity' => ['menu_controller' => 'edit-entity', 'menu_metodo' => 'edit'],
        'delete_entity' => ['menu_controller' => 'delete-entity', 'menu_metodo' => 'delete'],
    ];
    $listButtons = new AdmsBotao();
    $this->data['buttons'] = $listButtons->valBotao($buttons);
}
```

### 7.2 Controller de Ação com Services

```php
// Exemplo de AddSales.php - Padrão moderno
private NotificationService $notification;

public function __construct()
{
    $this->notification = new NotificationService();
}

public function create(): void
{
    $data = filter_input_array(INPUT_POST, FILTER_DEFAULT);

    $model = new AdmsAddSales();
    $model->addSale($data);

    if ($model->getResult()) {
        LoggerService::info('SALE_CREATED', 'Nova venda cadastrada', [
            'sale_id' => $model->getInsertId(),
            'user_id' => $_SESSION['usuario_id']
        ]);

        $this->notification->success('Venda cadastrada com sucesso!');
        $notificationHtml = $this->notification->getFlashMessage();

        $response = [
            'error' => false,
            'msg' => 'Venda cadastrada com sucesso!',
            'notification_html' => $notificationHtml
        ];
    } else {
        $errorMessage = $model->getError() ?? 'Erro ao cadastrar venda';

        $this->notification->error($errorMessage);
        $notificationHtml = $this->notification->getFlashMessage();

        $response = [
            'error' => true,
            'msg' => $errorMessage,
            'notification_html' => $notificationHtml
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}
```

### 7.3 Model de Estatísticas

```php
// Exemplo de AdmsStatisticsSales.php
public function getStatistics(?string $storeId = null): array
{
    return [
        'total_sales' => $this->getTotalSales($storeId),
        'sales_by_store' => $this->getSalesByStore($storeId),
        'top_consultants' => $this->getTopConsultants($storeId),
        'monthly_comparison' => $this->getMonthlyComparison($storeId)
    ];
}
```

### 7.4 JavaScript Moderno

```javascript
// Exemplo de sales.js - Async/await com debounce
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('content_sales');
    if (!container) return;

    const baseUrl = container.dataset.urlBase;
    let debounceTimer;

    // Event delegation
    container.addEventListener('click', async (e) => {
        if (e.target.matches('.btn-edit')) {
            await loadEditModal(e.target.dataset.id);
        }
    });

    // Busca com debounce
    document.getElementById('searchInput')?.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => searchItems(e.target.value), 500);
    });

    async function loadEditModal(id) {
        try {
            const response = await fetch(`${baseUrl}view-sale/view/${id}`);
            const data = await response.json();
            // ... populate modal
        } catch (error) {
            console.error('Erro:', error);
        }
    }
});
```

### 7.5 Testes Unitários

```php
// Exemplo de SalesControllerTest.php
class SalesControllerTest extends TestCase
{
    public function testListReturnsValidData(): void
    {
        $_SESSION['usuario_id'] = 1;
        $_SESSION['ordem_nivac'] = 1;

        $controller = new Sales();
        // ... test implementation
    }

    public function testPermissionCheckForStoreUser(): void
    {
        $_SESSION['ordem_nivac'] = STOREPERMITION;
        // ... verify store filtering
    }
}
```

---

## 8. Conclusão

A adoção deste padrão promove um desenvolvimento mais rápido, organizado e de fácil manutenção. Ao criar um novo módulo:

1. **Consulte o módulo Sales** como referência de implementação
2. **Use match expression** para roteamento no controller principal
3. **Implemente NotificationService e LoggerService** em todos os controllers
4. **Crie testes unitários** para Models e Controllers
5. **Siga a estrutura de partials** para modais
6. **Use async/await** no JavaScript

A utilização consistente dos Services e das Views parciais é fundamental para a modularidade e reusabilidade do código.

---

**Histórico de Versões:**

| Versão | Data | Alterações |
|--------|------|------------|
| 2.0 | 21/01/2026 | Adicionado Sales como referência, padrões modernos |
| 1.0 | - | Versão inicial baseada em Transfers e Reversals |