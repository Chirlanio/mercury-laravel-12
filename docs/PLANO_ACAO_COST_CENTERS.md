# Plano de Ação — Modernização do Módulo Centros de Custos

**Data:** 04/03/2026
**Estratégia:** Migração para AbstractConfigController (Opção A)
**Referência:** `docs/ANALISE_MODULO_COST_CENTERS.md`

---

## Visão Geral

Substituir os 15 arquivos legacy (5 controllers + 6 models + 4 views) por uma implementação baseada em `AbstractConfigController`, que fornece CRUD AJAX com modais, logging, validação, notificações e paginação automaticamente.

---

## Pré-requisito: Investigação da Tabela

Antes de iniciar, é necessário:

1. **Verificar estrutura da tabela `adms_cost_centers`** — Colunas, tipos, constraints
2. **Decidir tabela de managers** — `adms_managers` vs `adms_employees` (o módulo atual usa ambas inconsistentemente)
3. **Verificar FKs** — Quais tabelas referenciam `adms_cost_centers` (para delete check)
4. **Verificar rotas** — Registros atuais em `adms_paginas` para os controllers existentes

---

## Fase 1: Preparação e Correções Urgentes

**Objetivo:** Corrigir vulnerabilidades antes da migração.

### 1.1 Corrigir XSS nas Views Atuais
Enquanto a migração não é feita, corrigir os 3 pontos vulneráveis:

- `addCostCenter.php`: Escapar `$valorForm['cost_center_id']` e `$valorForm['name']`
- `editCostCenter.php`: Escapar `$valorForm['cost_center_id']` e `$valorForm['costCenter']`
- `listCostCenter.php`: Escapar `$_SESSION['search']`

### 1.2 Verificar Tabela e Dependências
```sql
-- Estrutura da tabela
DESCRIBE adms_cost_centers;

-- Verificar FKs que referenciam cost_centers
SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME = 'adms_cost_centers';

-- Verificar rotas existentes
SELECT id, controller, metodo, menu_controller FROM adms_paginas
WHERE controller LIKE '%CostCenter%' OR controller LIKE '%cost-center%';
```

---

## Fase 2: Criar Controller Principal + MODULE

### 2.1 Criar `Controllers/CostCenters.php` (novo)

Substituir o controller atual por um que estende `AbstractConfigController`:

```php
class CostCenters extends AbstractConfigController
{
    public const MODULE = [
        'table'         => 'adms_cost_centers',
        'entityName'    => 'Centro de Custo',
        'searchAlias'   => 'cc',

        'listQuery'     => "SELECT cc.id, cc.cost_center_id, cc.name,
                            COALESCE(m.name, '---') AS manager_name,
                            COALESCE(a.name, '---') AS area_name,
                            s.nome AS status
                            FROM adms_cost_centers cc
                            LEFT JOIN adms_managers m ON m.id = cc.manager_id
                            LEFT JOIN adms_areas a ON a.id = cc.adms_area_id
                            INNER JOIN adms_sits s ON s.id = cc.status_id
                            ORDER BY cc.name ASC",

        'countQuery'    => "SELECT COUNT(id) AS num_result FROM adms_cost_centers",

        'viewQuery'     => "SELECT cc.*,
                            COALESCE(m.name, '---') AS manager_name,
                            COALESCE(a.name, '---') AS area_name,
                            s.nome AS status
                            FROM adms_cost_centers cc
                            LEFT JOIN adms_managers m ON m.id = cc.manager_id
                            LEFT JOIN adms_areas a ON a.id = cc.adms_area_id
                            INNER JOIN adms_sits s ON s.id = cc.status_id
                            WHERE cc.id = :id LIMIT :limit",

        'editQuery'     => "SELECT * FROM adms_cost_centers WHERE id = :id LIMIT :limit",

        'listDataKey'   => 'listCostCenters',
        'viewDataKey'   => 'viewCostCenter',
        'optionalFields' => ['manager_id'],

        'selectQueries' => [
            'areas' => "SELECT id AS a_id, name AS area_name
                        FROM adms_areas ORDER BY name ASC",
            'managers' => "SELECT id AS m_id, name AS manager_name
                          FROM adms_managers WHERE status_id = 1 ORDER BY name ASC",
            'statuses' => "SELECT id AS s_id, nome AS status
                          FROM adms_sits ORDER BY id ASC",
        ],

        'searchConfig' => [
            'textFields' => ['name', 'cost_center_id'],
            'exactFilters' => [
                'searchStatus' => 'status_id',
                'searchArea' => 'adms_area_id',
            ],
        ],

        'deleteCheck' => [
            'query'    => "SELECT COUNT(*) AS cnt FROM adms_order_payments
                          WHERE adms_cost_center_id = :id",
            'paramKey' => 'id',
            'message'  => 'existem ordens de pagamento vinculadas a este centro de custo',
        ],

        'timestampColumns' => [
            'created' => 'created',
            'modified' => 'modified',
        ],

        'submitFields' => [
            'create' => 'CreateCostCenter',
            'edit' => 'EditCostCenter',
        ],

        'routes' => [
            'list'   => ['menu_controller' => 'cost-centers',        'menu_metodo' => 'list'],
            'create' => ['menu_controller' => 'add-cost-center',     'menu_metodo' => 'create'],
            'edit'   => ['menu_controller' => 'edit-cost-center',    'menu_metodo' => 'edit'],
            'view'   => ['menu_controller' => 'view-cost-center',    'menu_metodo' => 'view'],
            'delete' => ['menu_controller' => 'delete-cost-center',  'menu_metodo' => 'delete'],
        ],

        'buttonKeys' => [
            'create' => 'add_cost_center',
            'view'   => 'view_cost_center',
            'edit'   => 'edit_cost_center',
            'delete' => 'delete_cost_center',
            'list'   => 'list_cost_centers',
        ],

        'views' => [
            'load'        => 'adms/Views/costCenter/loadCostCenters',
            'list'        => 'adms/Views/costCenter/listCostCenters',
            'editForm'    => 'adms/Views/costCenter/partials/_edit_cost_center_form',
            'viewDetails' => 'adms/Views/costCenter/partials/_view_cost_center_details',
        ],

        'displayConfig' => [
            'entityLabel'       => 'Centro de Custo',
            'entityLabelPlural' => 'Centros de Custos',
            'icon'              => 'fas fa-sitemap',
        ],
    ];

    protected function getConfig(): array
    {
        return self::MODULE;
    }

    public function list(?int $PageId = null): void
    {
        $type = filter_input(INPUT_GET, 'typecostcenter', FILTER_VALIDATE_INT);
        match ($type) {
            1 => $this->executeListAjax($PageId),
            default => $this->executeLoadPage(),
        };
    }
}
```

### 2.2 Criar Action Controllers

**`Controllers/AddCostCenter.php`:**
```php
class AddCostCenter extends AbstractConfigController
{
    protected function getConfig(): array { return CostCenters::MODULE; }

    protected function beforeCreate(array &$data): void
    {
        // Remover pontos do ID do centro de custo
        if (isset($data['cost_center_id'])) {
            $data['cost_center_id'] = str_replace('.', '', $data['cost_center_id']);
        }
    }

    public function create(): void { $this->executeCreateAjax(); }
}
```

**`Controllers/EditCostCenter.php`:**
```php
class EditCostCenter extends AbstractConfigController
{
    protected function getConfig(): array { return CostCenters::MODULE; }

    protected function beforeEdit(array &$data): void
    {
        if (isset($data['cost_center_id'])) {
            $data['cost_center_id'] = str_replace('.', '', $data['cost_center_id']);
        }
    }

    public function edit(?int $id = null): void { $this->executeEditFormAjax($id); }
    public function update(): void { $this->executeUpdateAjax(); }
}
```

**`Controllers/DeleteCostCenter.php`:**
```php
class DeleteCostCenter extends AbstractConfigController
{
    protected function getConfig(): array { return CostCenters::MODULE; }
    public function delete(?int $id = null): void { $this->executeDeleteAjax($id); }
}
```

**`Controllers/ViewCostCenter.php`:**
```php
class ViewCostCenter extends AbstractConfigController
{
    protected function getConfig(): array { return CostCenters::MODULE; }
    public function view(?int $id = null): void { $this->executeViewAjax($id); }
}
```

---

## Fase 3: Criar Views

### 3.1 `Views/costCenter/loadCostCenters.php`
Página principal (SPA shell) com:
- Header com título e botão "Novo"
- Cards de estatísticas (total, ativos, inativos, por área)
- Filtros inline: busca por texto, filtro por status, filtro por área
- Container `<div id="content_cost_centers">` para lista AJAX
- Modais include (add, view, delete)
- Config element com `data-url-base`, `data-user-level`
- Script tag apontando para `cost-centers.js`

### 3.2 `Views/costCenter/listCostCenters.php`
Fragmento de lista (carregado via AJAX) com:
- Tabela responsiva com colunas: ID CC, Nome, Responsável, Área, Status, Ações
- Badges coloridos para status (Ativo=success, Inativo=danger)
- Botões desktop + dropdown mobile
- Paginação centralizada
- Todos outputs com `htmlspecialchars()`

### 3.3 `Views/costCenter/partials/_add_cost_center_modal.php`
Modal Bootstrap de cadastro com:
- Campos: ID Centro de Custo, Nome, Área (select), Responsável (select), Situação (select)
- Máscara no campo ID CC (formato 0.0.00.00)
- CSRF token
- Botões: Cancelar, Limpar, Salvar

### 3.4 `Views/costCenter/partials/_edit_cost_center_form.php`
Fragmento carregado via AJAX no modal de edição com:
- Mesmos campos do add, pré-preenchidos
- Disabled condicional baseado em nível de acesso

### 3.5 `Views/costCenter/partials/_view_cost_center_details.php`
Fragmento de visualização com:
- Definition list (`<dl>`) com todos os campos
- Timestamps formatados (dd/mm/yyyy HH:mm)
- Botões de ação (editar, deletar)

### 3.6 `Views/costCenter/partials/_delete_cost_center_modal.php`
Modal de confirmação de exclusão com:
- Mensagem de confirmação
- Nome do centro de custo a ser excluído
- Botões: Cancelar, Confirmar Exclusão

---

## Fase 4: Criar JavaScript

### 4.1 `assets/js/cost-centers.js`

Arquivo JS completo com as seções padrão:

```
// ==================== GLOBAL STATE ====================
// ==================== INITIALIZATION ====================
// ==================== EVENT LISTENERS ====================
// ==================== DATA LOADING (list AJAX) ====================
// ==================== MODAL OPERATIONS (add, edit, view, delete) ====================
// ==================== SEARCH & FILTERS ====================
// ==================== HELPER FUNCTIONS ====================
```

Funcionalidades:
- Carregamento AJAX da lista com loading state
- Abertura de modais (add, edit, view, delete) via event delegation
- Submit de formulários via `fetch()` com `FormData`
- Busca com debounce (400ms) + filtros por status e área
- Paginação AJAX
- Máscara de input para campo ID CC (formato 0.0.00.00)
- Refresh automático da lista após CRUD
- Notificações do server (HTML injection)

---

## Fase 5: Atualizar Rotas no Banco

### 5.1 Atualizar `adms_paginas`

Verificar e atualizar os registros de rotas para os novos métodos:

```sql
-- Verificar métodos atuais
SELECT id, controller, metodo FROM adms_paginas
WHERE controller IN ('CostCenters', 'AddCostCenter', 'EditCostCenter',
                     'DeleteCostCenter', 'ViewCostCenter');

-- Atualizar métodos se necessário (de 'costCenter' para 'create', 'edit', etc.)
-- Os nomes dos controllers se mantêm, apenas os métodos mudam
```

### 5.2 Atualizar `adms_nivacs_pgs`

Garantir que as permissões estão mapeadas para os novos métodos.

---

## Fase 6: Limpeza

### 6.1 Remover Arquivos Legacy

Após validar que o novo módulo funciona:

- `Models/AdmsAddCostCenter.php`
- `Models/AdmsEditCostCenter.php`
- `Models/AdmsDelCostCenter.php`
- `Models/AdmsViewCostCenter.php`
- `Models/AdmsListCostCenter.php`
- `cpadms/Models/CpAdmsPesqCostCenter.php`
- `Views/costCenter/addCostCenter.php` (substituída por modal)
- `Views/costCenter/editCostCenter.php` (substituída por modal)
- `Views/costCenter/viewCostCenter.php` (substituída por partial)
- `Views/costCenter/listCostCenter.php` (substituída por nova list)

### 6.2 Verificar Referências

Buscar no codebase por referências aos arquivos removidos:
```
AdmsAddCostCenter, AdmsEditCostCenter, AdmsDelCostCenter,
AdmsViewCostCenter, AdmsListCostCenter, CpAdmsPesqCostCenter
```

---

## Fase 7: Testes

### 7.1 Testes Manuais
- [ ] Abrir página de listagem — dados carregam via AJAX
- [ ] Buscar por nome — filtro funciona com debounce
- [ ] Filtrar por status — atualiza lista
- [ ] Filtrar por área — atualiza lista
- [ ] Abrir modal de cadastro — selects carregam
- [ ] Cadastrar centro de custo — sucesso + lista atualiza
- [ ] Cadastrar sem campos obrigatórios — validação funciona
- [ ] Abrir modal de edição — dados pré-preenchidos
- [ ] Editar centro de custo — sucesso + lista atualiza
- [ ] Abrir modal de visualização — dados exibidos corretamente
- [ ] Abrir modal de exclusão — confirmação exibida
- [ ] Excluir centro de custo com dependências — mensagem de erro
- [ ] Excluir centro de custo sem dependências — sucesso + lista atualiza
- [ ] Verificar responsividade (mobile, tablet, desktop)
- [ ] Verificar permissões (admin, gerente, operador)
- [ ] Verificar logs no banco (`adms_logs`) após cada operação CRUD

### 7.2 Testes Unitários (Opcional)
- Testar MODULE array configuration
- Testar beforeCreate (strip dots)
- Testar beforeEdit (strip dots)
- Testar deleteCheck query

---

## Resumo de Arquivos

### Novos (a criar)
| # | Arquivo | Tipo |
|---|---------|------|
| 1 | `Controllers/CostCenters.php` | Controller principal (reescrita) |
| 2 | `Controllers/AddCostCenter.php` | Action controller (reescrita) |
| 3 | `Controllers/EditCostCenter.php` | Action controller (reescrita) |
| 4 | `Controllers/DeleteCostCenter.php` | Action controller (reescrita) |
| 5 | `Controllers/ViewCostCenter.php` | Action controller (reescrita) |
| 6 | `Views/costCenter/loadCostCenters.php` | Load page (novo) |
| 7 | `Views/costCenter/listCostCenters.php` | List fragment (reescrita) |
| 8 | `Views/costCenter/partials/_add_cost_center_modal.php` | Add modal (novo) |
| 9 | `Views/costCenter/partials/_edit_cost_center_form.php` | Edit form (novo) |
| 10 | `Views/costCenter/partials/_view_cost_center_details.php` | View details (novo) |
| 11 | `Views/costCenter/partials/_delete_cost_center_modal.php` | Delete modal (novo) |
| 12 | `assets/js/cost-centers.js` | JavaScript (novo) |

### A remover (após validação)
| # | Arquivo |
|---|---------|
| 1 | `Models/AdmsAddCostCenter.php` |
| 2 | `Models/AdmsEditCostCenter.php` |
| 3 | `Models/AdmsDelCostCenter.php` |
| 4 | `Models/AdmsViewCostCenter.php` |
| 5 | `Models/AdmsListCostCenter.php` |
| 6 | `cpadms/Models/CpAdmsPesqCostCenter.php` |
| 7 | `Views/costCenter/addCostCenter.php` |
| 8 | `Views/costCenter/editCostCenter.php` |
| 9 | `Views/costCenter/viewCostCenter.php` |
| 10 | `Views/costCenter/listCostCenter.php` |

---

## Ordem de Execução

```
Fase 1 → Correções XSS + investigação de tabela
Fase 2 → Controllers (MODULE + actions)
Fase 3 → Views (load + list + partials)
Fase 4 → JavaScript
Fase 5 → Rotas no banco
Fase 6 → Limpeza de legacy
Fase 7 → Testes
```

**Nota:** As fases 2, 3 e 4 podem ser feitas em paralelo, mas devem ser testadas juntas na fase 7.
