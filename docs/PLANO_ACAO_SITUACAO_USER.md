# Plano de Ação: Modernizar Módulo SituacaoUser

**Data:** 2026-03-04
**Baseado em:** `docs/ANALISE_MODULO_SITUACAO_USER.md`
**Referência:** HdCategories (implementação de referência AJAX modals)
**Complexidade:** Baixa (módulo simples, 2 campos + FK cor)

---

## Resumo

Migrar o módulo SituacaoUser de page-reload para AJAX modals, seguindo o padrão HdCategories/CostCenters. Renomear controllers para nomenclatura inglesa. Criar views modernas com cards, filtros e JavaScript dedicado.

---

## Arquivos a Criar/Reescrever (12 arquivos)

| # | Arquivo | Baseado em | Ação |
|---|---------|------------|------|
| 1 | `Controllers/SituacaoUser.php` | `HdCategories.php` | Reescrever MODULE + match expression |
| 2 | `Controllers/AddSituacaoUser.php` | `AddHdCategory.php` | Criar novo |
| 3 | `Controllers/EditSituacaoUser.php` | `EditHdCategory.php` | Criar novo |
| 4 | `Controllers/DeleteSituacaoUser.php` | `DeleteHdCategory.php` | Criar novo |
| 5 | `Controllers/ViewSituacaoUser.php` | `ViewHdCategory.php` | Criar novo |
| 6 | `Views/situacaoUser/loadSituacaoUser.php` | `loadHdCategory.php` | Criar novo |
| 7 | `Views/situacaoUser/listSituacaoUser.php` | `listHdCategory.php` | Criar novo |
| 8 | `Views/situacaoUser/partials/_add_situacao_user_modal.php` | `_add_hd_category_modal.php` | Criar novo |
| 9 | `Views/situacaoUser/partials/_edit_situacao_user_form.php` | `_edit_hd_category_form.php` | Criar novo |
| 10 | `Views/situacaoUser/partials/_view_situacao_user_details.php` | `_view_hd_category_details.php` | Criar novo |
| 11 | `Views/situacaoUser/partials/_delete_situacao_user_modal.php` | Inline no load | Criar novo |
| 12 | `assets/js/situacao-user.js` | `hd-categories.js` | Criar novo |

## Arquivos Legacy a Remover (8 arquivos, após validação)

| # | Arquivo |
|---|---------|
| 1 | `Controllers/CadastrarSitUser.php` |
| 2 | `Controllers/EditarSitUser.php` |
| 3 | `Controllers/ApagarSitUser.php` |
| 4 | `Controllers/VerSitUser.php` |
| 5 | `Views/situacaoUser/listarSitUser.php` |
| 6 | `Views/situacaoUser/cadSitUser.php` |
| 7 | `Views/situacaoUser/editarSitUser.php` |
| 8 | `Views/situacaoUser/verSitUser.php` |

---

## Fase 1: Controllers (5 arquivos)

### 1.1 `Controllers/SituacaoUser.php` — Controller Principal + MODULE

```php
class SituacaoUser extends AbstractConfigController
{
    public const MODULE = [
        'table'         => 'adms_sits_usuarios',
        'entityName'    => 'Situação de Usuário',
        'searchAlias'   => 'sit',
        'searchConfig'  => [
            'textFields'   => ['nome'],
            'exactFilters' => [],
        ],
        'listQuery'     => "SELECT sit.id, sit.nome, sit.adms_cor_id,
                            cr.cor AS cor_cr, cr.nome AS cor_nome
                            FROM adms_sits_usuarios sit
                            INNER JOIN adms_cors cr ON cr.id = sit.adms_cor_id
                            ORDER BY sit.nome ASC",
        'countQuery'    => "SELECT COUNT(id) AS num_result FROM adms_sits_usuarios",
        'viewQuery'     => "SELECT sit.*, cr.cor AS cor_cr, cr.nome AS cor_nome
                            FROM adms_sits_usuarios sit
                            INNER JOIN adms_cors cr ON cr.id = sit.adms_cor_id
                            WHERE sit.id = :id LIMIT :limit",
        'editQuery'     => "SELECT * FROM adms_sits_usuarios WHERE id = :id LIMIT :limit",
        'listDataKey'   => 'listSituacaoUser',
        'viewDataKey'   => 'viewSituacaoUser',
        'selectQueries' => [
            'cor' => "SELECT id AS id_cor, nome AS nome_cor, cor AS cor_class FROM adms_cors ORDER BY nome ASC",
        ],
        'deleteCheck' => [
            'query'    => "SELECT COUNT(*) AS cnt FROM adms_usuarios WHERE adms_sits_usuario_id = :id",
            'paramKey' => 'id',
            'message'  => 'há usuários cadastrados com essa situação',
        ],
        'routes' => [
            'list'   => ['menu_controller' => 'situacao-user',         'menu_metodo' => 'list'],
            'create' => ['menu_controller' => 'add-situacao-user',     'menu_metodo' => 'create'],
            'edit'   => ['menu_controller' => 'edit-situacao-user',    'menu_metodo' => 'edit'],
            'view'   => ['menu_controller' => 'view-situacao-user',    'menu_metodo' => 'view'],
            'delete' => ['menu_controller' => 'delete-situacao-user',  'menu_metodo' => 'delete'],
        ],
        'buttonKeys' => [
            'create' => 'add_situacao_user',
            'view'   => 'view_situacao_user',
            'edit'   => 'edit_situacao_user',
            'delete' => 'delete_situacao_user',
            'list'   => 'list_situacao_user',
        ],
        'views' => [
            'load'        => 'adms/Views/situacaoUser/loadSituacaoUser',
            'list'        => 'adms/Views/situacaoUser/listSituacaoUser',
            'editForm'    => 'adms/Views/situacaoUser/partials/_edit_situacao_user_form',
            'viewDetails' => 'adms/Views/situacaoUser/partials/_view_situacao_user_details',
        ],
        'submitFields'     => ['create' => 'CreateSituacaoUser', 'edit' => 'EditSituacaoUser'],
        'timestampColumns' => ['created' => 'created', 'modified' => 'modified'],
        'displayConfig' => [
            'entityLabel'       => 'Situação de Usuário',
            'entityLabelPlural' => 'Situações de Usuários',
            'icon'              => 'fas fa-user-tag',
        ],
    ];

    protected function getConfig(): array
    {
        return self::MODULE;
    }

    public function list(?int $PageId = null): void
    {
        $type = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT);
        match ($type) {
            1 => $this->executeListAjax($PageId),
            default => $this->executeLoadPage(),
        };
    }
}
```

**Mudanças-chave vs atual:**
- Método `listar()` → `list()` com match expression
- `executeList()` → `executeListAjax()` / `executeLoadPage()`
- Adicionado: `searchAlias`, `searchConfig`, `editQuery`, `timestampColumns`, `displayConfig`
- Queries melhoradas: aliases explícitos, `COUNT(*)` com `cnt`
- `deleteCheck` usa `id` como `paramKey` (padrão moderno) em vez de `adms_sits_usuario_id`
- `listDataKey` e `viewDataKey` renomeados para padrão
- `buttonKeys` renomeados para padrão inglês

### 1.2 `Controllers/AddSituacaoUser.php`

```php
class AddSituacaoUser extends AbstractConfigController
{
    protected function getConfig(): array
    {
        return SituacaoUser::MODULE;
    }

    public function create(): void
    {
        $this->executeCreateAjax();
    }
}
```

### 1.3 `Controllers/EditSituacaoUser.php`

```php
class EditSituacaoUser extends AbstractConfigController
{
    protected function getConfig(): array
    {
        return SituacaoUser::MODULE;
    }

    public function edit(?int $DadosId = null): void
    {
        $this->executeEditFormAjax($DadosId);
    }

    public function update(): void
    {
        $this->executeUpdateAjax();
    }
}
```

### 1.4 `Controllers/DeleteSituacaoUser.php`

```php
class DeleteSituacaoUser extends AbstractConfigController
{
    protected function getConfig(): array
    {
        return SituacaoUser::MODULE;
    }

    public function delete(?int $DadosId = null): void
    {
        $this->executeDeleteAjax($DadosId);
    }
}
```

### 1.5 `Controllers/ViewSituacaoUser.php`

```php
class ViewSituacaoUser extends AbstractConfigController
{
    protected function getConfig(): array
    {
        return SituacaoUser::MODULE;
    }

    public function view(?int $DadosId = null): void
    {
        $this->executeViewAjax($DadosId);
    }
}
```

---

## Fase 2: Views (6 arquivos)

### 2.1 `Views/situacaoUser/loadSituacaoUser.php` — SPA Shell

Estrutura (seguindo `loadHdCategory.php`):
- Config extraction: `$cfg`, `$display`, `$routes`, `$btnKeys`, `$buttons`
- Route building: `$listRoute`, `$addRoute`, etc.
- Select data: `$cores` (da selectQueries 'cor')
- **Header** com título "Situações de Usuários" + icon `fas fa-user-tag`
- **Desktop button** "Nova Situação" (btn-success) + **Mobile dropdown**
- **Filtros**: Pesquisa texto (col-12) — módulo simples, apenas busca por nome
- **Messages div** `id="messages"`
- **Content div** `id="content_situacao_user"` (com spinner inicial)
- **Hidden config div** `id="situacao-user-config"` com data-* attributes
- **Modal includes**: add modal, edit shell, view shell, delete modal
- **Script tag**: `situacao-user.js`

### 2.2 `Views/situacaoUser/listSituacaoUser.php` — List Fragment (AJAX)

Tabela com colunas:
| #ID | Nome | Cor (badge preview) | Ações |

- Nome: sempre visível
- Cor: `d-none d-sm-table-cell`, com badge preview `<span class="badge badge-{cor_cr}">{nome}</span>`
- Ações: desktop btn-group + mobile dropdown (btn-view-item, btn-edit-item, btn-delete-item)
- Paginação: `$pagination`
- Todos outputs com `htmlspecialchars()`

### 2.3 `Views/situacaoUser/partials/_add_situacao_user_modal.php`

Modal `#addSituacaoUserModal`, bg-success header.
Form `#addSituacaoUserForm`:
- **Card Dados**: Nome (col-6, required) + Cor (col-6, select required com badge preview)
- Campos obrigatórios note + botões Cancelar/Salvar

**Diferencial:** Select de cor com `data-cor` nos options para preview dinâmico da cor selecionada.

### 2.4 `Views/situacaoUser/partials/_edit_situacao_user_form.php`

Fragment loaded via AJAX em `#editSituacaoUserContent`.
Form `#editSituacaoUserForm` com hidden `id` input:
- Mesma estrutura do add, campos pré-preenchidos
- Select options com `selected` condicional
- Botões Cancelar/Salvar Alterações (btn-warning)

### 2.5 `Views/situacaoUser/partials/_view_situacao_user_details.php`

Fragment com `<dl>` cards:
- **Card Identificação**: ID (badge), Nome, Cor (badge com cor associada)
- **Card Registro**: Criado em, Modificado em (formatados dd/mm/yyyy HH:mm)

### 2.6 `Views/situacaoUser/partials/_delete_situacao_user_modal.php`

Modal `#deleteSituacaoUserModal`, bg-danger header.
- Alert warning "Esta ação não pode ser desfeita"
- dl: Nome da situação (`#delete_sit_name`)
- Hidden input `#delete_sit_id`
- Botões: Cancelar + Excluir (`#confirmDeleteSituacaoUser`)

---

## Fase 3: JavaScript — `assets/js/situacao-user.js`

Seguir estrutura de `hd-categories.js`, com prefixo `sitUser`:

```
// Config from #situacao-user-config div
const sitUserConfig, SU_URL_BASE, SU_LIST_URL, SU_ADD_URL, SU_EDIT_URL, SU_VIEW_URL, SU_DELETE_URL
const SU_SUBMIT_CREATE, SU_SUBMIT_EDIT, SU_ENTITY_LABEL

// SEARCH FILTERS
sitUserBuildFilterParams()   — searchName only
sitUserPerformSearch()       — window.listSituacaoUser(1)
sitUserClearAllFilters()     — reset input + search

// LISTING AND PAGINATION
window.listSituacaoUser(page) — fetch list URL with ?type=1
sitUserAdjustPaginationLinks()
sitUserRefreshList()
Pagination click handler      — delegation on #content_situacao_user .pagination
CRUD button delegation        — .btn-view-item, .btn-edit-item, .btn-delete-item

// ADD ITEM
sitUserSetupAddForm() — form submit via fetch POST → JSON → notification + refresh

// EDIT ITEM
sitUserEditItem(id)    — fetch edit form HTML → inject in #editSituacaoUserContent
sitUserSetupEditForm() — form submit via fetch POST → JSON → notification + refresh

// VIEW ITEM
sitUserViewItem(id) — fetch view HTML → inject in #viewSituacaoUserContent

// DELETE ITEM
sitUserDeleteItem(id, name) — populate delete modal + show
sitUserPerformDelete()      — fetch delete URL → JSON → notification + refresh

// INITIALIZE
sitUserInit() — wire search input (debounce 500ms), modal resets, load initial data

// NOTIFICATION HELPER
sitUserRenderNotification(html) — inject + auto-remove after 5.8s
```

---

## Fase 4: Rotas no Banco

### SQL de Migração

```sql
-- 1. Atualizar o controller principal (SituacaoUser) — mesmo nome, novo método
UPDATE adms_paginas
SET metodo = 'list', menu_metodo = 'list'
WHERE controller = 'SituacaoUser' AND metodo = 'listar';

-- 2. Renomear controllers de ação + atualizar métodos e rotas
-- CadastrarSitUser → AddSituacaoUser
UPDATE adms_paginas
SET controller = 'AddSituacaoUser',
    metodo = 'create',
    menu_controller = 'add-situacao-user',
    menu_metodo = 'create'
WHERE controller = 'CadastrarSitUser';

-- EditarSitUser → EditSituacaoUser (edit + update)
UPDATE adms_paginas
SET controller = 'EditSituacaoUser',
    metodo = 'edit',
    menu_controller = 'edit-situacao-user',
    menu_metodo = 'edit'
WHERE controller = 'EditarSitUser';

-- ApagarSitUser → DeleteSituacaoUser
UPDATE adms_paginas
SET controller = 'DeleteSituacaoUser',
    metodo = 'delete',
    menu_controller = 'delete-situacao-user',
    menu_metodo = 'delete'
WHERE controller = 'ApagarSitUser';

-- VerSitUser → ViewSituacaoUser
UPDATE adms_paginas
SET controller = 'ViewSituacaoUser',
    metodo = 'view',
    menu_controller = 'view-situacao-user',
    menu_metodo = 'view'
WHERE controller = 'VerSitUser';

-- 3. Inserir rota para EditSituacaoUser.update() (POST do formulário AJAX)
-- Copiar permissões da rota de edição existente
INSERT INTO adms_paginas (nome_pagina, controller, metodo, menu_controller, menu_metodo, obs)
SELECT 'Atualizar Situação de Usuário', 'EditSituacaoUser', 'update', 'edit-situacao-user', 'update', 'AJAX update endpoint'
FROM adms_paginas
WHERE controller = 'EditSituacaoUser' AND metodo = 'edit'
AND NOT EXISTS (
    SELECT 1 FROM adms_paginas WHERE controller = 'EditSituacaoUser' AND metodo = 'update'
);

-- 4. Copiar permissões para a nova rota de update
INSERT INTO adms_nivacs_pgs (adms_niveis_acesso_id, adms_pagina_id)
SELECT np.adms_niveis_acesso_id,
       (SELECT id FROM adms_paginas WHERE controller = 'EditSituacaoUser' AND metodo = 'update')
FROM adms_nivacs_pgs np
INNER JOIN adms_paginas p ON p.id = np.adms_pagina_id
WHERE p.controller = 'EditSituacaoUser' AND p.metodo = 'edit'
AND NOT EXISTS (
    SELECT 1 FROM adms_nivacs_pgs
    WHERE adms_pagina_id = (SELECT id FROM adms_paginas WHERE controller = 'EditSituacaoUser' AND metodo = 'update')
);
```

---

## Fase 5: Limpeza

1. Remover os 8 arquivos legacy listados acima
2. Verificar se não há referências no codebase a:
   - `CadastrarSitUser`
   - `EditarSitUser`
   - `ApagarSitUser`
   - `VerSitUser`
   - `cadSitUser`
   - `editSitUser`
   - `verSitUser`
   - `apagarSitUser`
   - `listarSitUser`
   - `cadSitUser.php`
   - `editarSitUser.php`
   - `verSitUser.php`
   - `listSitUser`

---

## Verificação End-to-End

1. Abrir `/situacao-user/list` → página carrega, lista via AJAX
2. Filtrar por texto → debounce funciona, lista atualiza
3. Limpar filtros → reseta
4. Abrir modal de cadastro → select de cores carregado
5. Cadastrar → sucesso, notificação, lista atualiza
6. Cadastrar sem campos obrigatórios → validação bloqueia
7. Abrir modal de edição → dados pré-preenchidos
8. Editar → sucesso, notificação, lista atualiza
9. Abrir modal de visualização → dados exibidos com badge colorido
10. Abrir modal de exclusão → nome exibido, confirmação
11. Excluir situação com usuários vinculados → mensagem de erro (deleteCheck)
12. Excluir situação sem dependências → sucesso
13. Responsividade: mobile (dropdown ações), tablet, desktop (btn-group)
14. Verificar logs em `adms_logs` após cada operação CRUD

---

## Estimativa de Impacto

| Métrica | Antes | Depois |
|---------|-------|--------|
| Nota geral | 5/10 | 9/10 |
| Page reloads por operação | 1-2 | 0 |
| Vulnerabilidades XSS | 2 | 0 |
| Arquivos | 9 | 12 (+JS, +partials) |
| Linhas de JavaScript | 0 | ~400 |
| Filtros disponíveis | 0 | 1 (texto) |
| Delete confirmation | JS confirm() | Modal customizado |
| UX | Formulários full-page | Modals AJAX inline |

---

## Dependências

- `AbstractConfigController.php` — Já possui todos os métodos AJAX necessários
- `NotificationService.php` — Já disponível
- `LoggerService.php` — Já disponível
- `AdmsBotao` — Já disponível
- `adms_cors` — Tabela já existente com dados
