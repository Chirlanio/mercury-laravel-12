# Analise do Modulo de Paginas (Pages/Routing/Permissions)

**Versao:** 1.0
**Data:** 04 de Abril de 2026
**Autor:** Chirlanio Silva - Grupo Meia Sola
**Finalidade:** Documentacao completa para referencia na implementacao da v2

---

## 1. Visao Geral

O modulo de Paginas e o **nucleo central do sistema Mercury**. Ele gerencia o roteamento de URLs, o controle de acesso baseado em niveis de permissao, a estrutura do menu sidebar, e o CRUD de todas as rotas registradas no sistema. Cada URL acessada no sistema e validada contra a tabela `adms_paginas` e suas permissoes em `adms_nivacs_pgs`.

### Classificacao

| Aspecto | Valor |
|---------|-------|
| **Tipo** | Sistema de roteamento + ACL (Access Control List) database-driven |
| **Criticidade** | **MAXIMA** — falha neste modulo impede acesso a todo o sistema |
| **Maturidade** | Moderno (match expressions, type hints, AJAX, CSRF global) |
| **Complexidade** | Alta (3 sub-modulos interconectados) |
| **Testes** | 89 testes (5 arquivos PageGroups, 1.767 linhas) |
| **Arquivos** | ~137 arquivos diretos |

### Sub-modulos

| Sub-modulo | Responsabilidade |
|------------|------------------|
| **Pages (Paginas)** | CRUD de rotas, registro de controllers/metodos |
| **PageGroups (Grupos)** | Agrupamento logico de paginas para organizacao |
| **Menus** | Itens de menu sidebar, ordem, visibilidade |
| **Permissions (Permissoes)** | Nivel de acesso por pagina, toggle allow/block |
| **AccessLevels (Niveis de Acesso)** | Hierarquia de permissoes (Super Admin ate Candidato) |

---

## 2. Banco de Dados — Modelo de Dados

### 2.1 Diagrama ER

```
  adms_niveis_acessos        adms_paginas           adms_menus
  ┌──────────────────┐      ┌──────────────────┐    ┌──────────────────┐
  │ id (PK)          │      │ id (PK)          │    │ id (PK)          │
  │ nome             │      │ nome_pagina      │    │ nome             │
  │ ordem            │      │ controller       │    │ icone            │
  │ created          │      │ metodo           │    │ ordem            │
  │ modified         │      │ menu_controller  │    │ adms_sit_id (FK) │
  └────────┬─────────┘      │ menu_metodo      │    │ created          │
           │                │ icone            │    │ modified         │
           │                │ lib_pub          │    └────────┬─────────┘
           │                │ obs              │             │
           │                │ adms_grps_pg_id  │──→ adms_grps_pgs
           │                │ adms_tps_pg_id   │──→ adms_tps_pgs
           │                │ adms_sits_pg_id  │──→ adms_sits_pgs
           │                │ created          │             │
           │                │ modified         │             │
           │                └────────┬─────────┘             │
           │                         │                       │
           └──────────┬──────────────┘                       │
                      │                                      │
              adms_nivacs_pgs (tabela pivot)                  │
              ┌──────────────────────────┐                   │
              │ id (PK)                  │                   │
              │ adms_niveis_acesso_id (FK)│                  │
              │ adms_pagina_id (FK)      │                   │
              │ permissao (1=allow, 2=deny)                  │
              │ ordem                    │                   │
              │ lib_menu (1=show)        │                   │
              │ dropdown (1=yes, 2=no)   │                   │
              │ adms_menu_id (FK)        │───────────────────┘
              │ created                  │
              │ modified                 │
              └──────────────────────────┘
```

### 2.2 Tabela `adms_paginas` (Paginas/Rotas)

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT (PK, AI) | Identificador unico |
| `nome_pagina` | VARCHAR | Nome amigavel da pagina |
| `controller` | VARCHAR | Nome do controller PHP (PascalCase) |
| `metodo` | VARCHAR | Nome do metodo (camelCase) |
| `menu_controller` | VARCHAR | Slug do controller para URL (kebab-case) |
| `menu_metodo` | VARCHAR | Slug do metodo para URL (kebab-case) |
| `icone` | VARCHAR | Classe Font Awesome (nullable) |
| `lib_pub` | TINYINT | 1 = pagina publica (sem auth), 0/null = privada |
| `obs` | TEXT | Observacoes/descricao |
| `adms_grps_pg_id` | INT (FK) | Grupo de paginas |
| `adms_tps_pg_id` | INT (FK) | Tipo de pagina (namespace do controller) |
| `adms_sits_pg_id` | INT (FK) | Status da pagina (1=Ativa) |
| `created` | DATETIME | Data de criacao |
| `modified` | DATETIME | Data de alteracao |

**Regra critica:** O campo `adms_tps_pg_id` determina o **namespace** do controller via `adms_tps_pgs.tipo`. O valor `tipo` (ex: "adms", "cpadms") e usado para montar o caminho: `\App\{tipo}\Controllers\{Controller}`.

### 2.3 Tabela `adms_nivacs_pgs` (Permissoes)

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT (PK, AI) | Identificador unico |
| `adms_niveis_acesso_id` | INT (FK) | Nivel de acesso |
| `adms_pagina_id` | INT (FK) | Pagina referenciada |
| `permissao` | TINYINT | **1 = permitido**, **2 = bloqueado** |
| `ordem` | INT | Posicao no menu para este nivel |
| `lib_menu` | TINYINT | 1 = visivel no menu sidebar |
| `dropdown` | TINYINT | 1 = sub-item de dropdown, 2 = item direto |
| `adms_menu_id` | INT (FK) | Menu pai (agrupador sidebar) |
| `created` | DATETIME | Data de criacao |
| `modified` | DATETIME | Data de alteracao |

**Cardinalidade:** Cada pagina tem **uma entrada por nivel de acesso**. Para N paginas e M niveis, existem N*M registros.

### 2.4 Tabela `adms_menus` (Itens do Menu Sidebar)

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT (PK, AI) | Identificador unico |
| `nome` | VARCHAR | Nome exibido no menu |
| `icone` | VARCHAR | Classe Font Awesome |
| `ordem` | INT | Posicao no sidebar |
| `adms_sit_id` | INT (FK) | Status (1=Ativo) |
| `created` | DATETIME | Data de criacao |
| `modified` | DATETIME | Data de alteracao |

### 2.5 Tabelas Auxiliares

| Tabela | Descricao | Campos Principais |
|--------|-----------|-------------------|
| `adms_niveis_acessos` | Niveis de acesso | id, nome, ordem |
| `adms_grps_pgs` | Grupos de paginas | id, nome, obs, created, modified |
| `adms_tps_pgs` | Tipos de pagina | id, tipo (namespace), nome |
| `adms_sits_pgs` | Status de paginas | id, nome, cor |
| `adms_sits` | Status gerais | id, nome, adms_cor_id |

---

## 3. Fluxo Principal — Roteamento (ConfigController)

### 3.1 Pipeline de Requisicao

```
HTTP Request
    │
    ▼
ConfigController::__construct()
    │
    ├─ 1. Parsing da URL
    │     GET 'url' → strip_tags → trim → rtrim('/')
    │     Explode por '/' → [controller, metodo, parametro]
    │     slugController('add-page') → 'AddPage'    (kebab → PascalCase)
    │     slugMetodo('create')       → 'create'     (kebab → camelCase)
    │
    ▼
ConfigController::carregar()
    │
    ├─ 2. Validacao de Rota (AdmsPages::listarPaginas)
    │     SELECT adms_paginas
    │     INNER JOIN adms_tps_pgs (namespace)
    │     LEFT JOIN adms_nivacs_pgs (permissao do nivel do usuario)
    │     WHERE controller = ? AND metodo = ?
    │       AND (lib_pub = 1 OR (permissao = 1 AND sits_pg = 1))
    │
    │     Se nao encontrada → handleRouteNotFound()
    │       ├─ AJAX → JSON 404
    │       └─ Normal → Redirect para Login::acesso
    │
    ▼
ConfigController::carregarMetodo()
    │
    ├─ 3. CSRF Middleware (validateCsrf)
    │     Skip se: GET/HEAD/OPTIONS, lib_pub=1, Login::acesso, UsersOnline::ping
    │     Valida token de: POST body, JSON body, ou HTTP header
    │     Se invalido → Log CSRF_VALIDATION_FAILED + 403 (JSON ou redirect)
    │
    ├─ 4. Session Validation (validateUserSession)
    │     Skip se: lib_pub=1, Login controller, nao logado
    │     Cache de 5 segundos em sessao (evita query repetida)
    │     Verifica: adms_users_online.adms_sit_access_id = 1
    │             E adms_usuarios.adms_sits_usuario_id = 1
    │     Se invalido → Destroi sessao + redirect/JSON 401
    │
    ├─ 5. Force Password Change (checkForcePasswordChange)
    │     Skip se: lib_pub=1, Login, ChangePassword, ForceChangePassword
    │     Se SessionContext::mustChangePassword() → redirect 403
    │
    ├─ 6. Page Tracking (trackCurrentPage)
    │     Skip se: lib_pub=1, nao logado, AJAX, controllers skip-list
    │     Atualiza adms_users_online
    │     Registra em adms_page_visits
    │     Verifica alertas de monitoramento
    │     Push via WebSocket para subscribers de monitoramento
    │
    ├─ 7. Instanciacao do Controller
    │     $classe = "\App\{tipo_tpg}\Controllers\{UrlController}"
    │     Se class_exists → chama metodo com parametro (se houver)
    │     Se nao → fallback para controller/metodo default
    │
    ▼
Controller::metodo($parametro)
```

### 3.2 Conversao de URL → Controller

| URL | slugController | slugMetodo | Classe Resolvida |
|-----|---------------|------------|------------------|
| `add-page/create` | `AddPage` | `create` | `\App\adms\Controllers\AddPage::create()` |
| `edit-supplier/edit/5` | `EditSupplier` | `edit` | `\App\adms\Controllers\EditSupplier::edit(5)` |
| `stock-movements/list/3` | `StockMovements` | `list` | `\App\adms\Controllers\StockMovements::list(3)` |
| `home/index` | `Home` | `index` | `\App\adms\Controllers\Home::index()` |

**Algoritmo `slugController`:** `kebab-case` → `split('-')` → `ucwords()` → `join('')` = `PascalCase`
**Algoritmo `slugMetodo`:** Igual ao controller + `lcfirst()` = `camelCase`

### 3.3 Query de Validacao de Rota (AdmsPages::listarPaginas)

```sql
SELECT pg.id, tpg.tipo AS tipo_tpg, pg.lib_pub, pg.nome_pagina, pg.obs
FROM adms_paginas pg
INNER JOIN adms_tps_pgs tpg ON tpg.id = pg.adms_tps_pg_id
LEFT JOIN adms_nivacs_pgs nivpg
    ON nivpg.adms_pagina_id = pg.id
    AND nivpg.adms_niveis_acesso_id = :user_level_id
WHERE (pg.controller = :controller AND pg.metodo = :metodo)
    AND ((pg.lib_pub = 1)       -- Pagina publica OU
     OR (nivpg.permissao = 1    -- Permissao concedida
    AND pg.adms_sits_pg_id = 1)) -- E pagina ativa
LIMIT 1
```

**Regras:**
- Paginas publicas (`lib_pub = 1`) sao acessiveis sem autenticacao
- Paginas privadas exigem: login + `permissao = 1` na `adms_nivacs_pgs` + status ativo
- Se usuario nao logado, `adms_niveis_acesso_id` e `null` — so paginas publicas passam

---

## 4. Sistema de Permissoes (AdmsBotao)

### 4.1 Validacao de Botoes

O `AdmsBotao::valBotao()` e usado por **todos os controllers** do sistema para determinar quais botoes de acao o usuario pode ver (adicionar, editar, excluir, visualizar).

```php
// Registro no Controller
$buttons = [
    'cad_pagina' => ['menu_controller' => 'add-page', 'menu_metodo' => 'create'],
    'edit_pagina' => ['menu_controller' => 'edit-page', 'menu_metodo' => 'edit'],
    'del_pagina' => ['menu_controller' => 'delete-page', 'menu_metodo' => 'delete'],
    'vis_pagina' => ['menu_controller' => 'view-page', 'menu_metodo' => 'view'],
];
$listButton = new AdmsBotao();
$this->data['buttons'] = $listButton->valBotao($buttons);
```

### 4.2 Query de Validacao por Botao

```sql
SELECT pg.id AS id_pg
FROM adms_paginas pg
LEFT JOIN adms_nivacs_pgs nivpg ON nivpg.adms_pagina_id = pg.id
WHERE pg.menu_controller = :menu_controller     -- slug do controller
    AND pg.menu_metodo = :menu_metodo            -- slug do metodo
    AND pg.adms_sits_pg_id = 1                   -- pagina ativa
    AND nivpg.adms_niveis_acesso_id = :user_level -- nivel do usuario
    AND nivpg.permissao = 1                       -- permissao concedida
LIMIT 1
```

**Retorno:** `['cad_pagina' => true, 'edit_pagina' => false, ...]`

### 4.3 Uso nas Views

```php
<?php if (!empty($buttons['cad_pagina'])): ?>
    <button class="btn btn-success">Nova Pagina</button>
<?php endif; ?>
```

---

## 5. Hierarquia de Niveis de Acesso (PermissionService)

### 5.1 Tabela de Niveis

| Nivel (ordem) | Constante | Nome | Escopo |
|:-------------:|-----------|------|--------|
| 1 | `SUPADMPERMITION` | Super Admin | Acesso total, todas as lojas |
| 2 | `ADMPERMITION` | Admin | Acesso administrativo |
| 3 | `SUPPORT` | Suporte | Acesso de suporte |
| 7 | `DP` | Depto Pessoal | RH e pessoal |
| 9 | `FINANCIALPERMITION` | Financeiro | Modulos financeiros |
| 10 | `FINANCIALPERMITIONONE` | Financeiro N1 | Financeiro restrito |
| 14 | `OPERATION` | Operacoes | Operacoes logisticas |
| 18 | `STOREPERMITION` | Loja | Restrito a sua loja |
| 22 | `DRIVER` | Motorista | Acesso minimo |
| 23 | `CANDIDATE` | Candidato | Acesso de candidato |

### 5.2 Logica de Verificacao

A hierarquia e **numerica e invertida**: quanto **menor** o numero, **maior** o poder.

```php
// Verifica se e admin ou superior (nivel <= 2)
PermissionService::isAdmin()    → SessionContext::getAccessLevel() <= 2

// Verifica se e restrito a loja (nivel >= 18)
PermissionService::isStoreLevel() → SessionContext::getAccessLevel() >= 18

// Filtro SQL: retorna store_id se restrito, null se admin
PermissionService::getStoreFilter() → isStoreLevel() ? getUserStore() : null
```

### 5.3 Metodos do PermissionService

| Metodo | Verifica | Operador |
|--------|----------|----------|
| `isSuperAdmin()` | nivel === 1 | igualdade |
| `isAdmin()` | nivel <= 2 | inclusivo |
| `isSupport()` | nivel <= 3 | inclusivo |
| `isDp()` | nivel <= 7 | inclusivo |
| `isFinancial()` | nivel <= 9 | inclusivo |
| `isFinancialRestricted()` | nivel > 9 | estrito |
| `isOperation()` | nivel <= 14 | inclusivo |
| `isStoreLevel()` | nivel >= 18 | inclusivo |
| `isDriver()` | nivel === 22 | igualdade |
| `isCandidate()` | nivel === 23 | igualdade |

### 5.4 SQL Filter Builders

```php
// Filtro de loja generico
PermissionService::buildStoreFilter('ev', 'store_id')
→ ['condition' => ' AND ev.store_id = :storeId', 'paramString' => 'storeId=Z424']

// Filtro financeiro (dual store)
PermissionService::buildFinancialStoreFilter('ts', 'emp', 'adms_store_id')
→ ['condition' => ' AND (ts.adms_store_id = :storeId OR emp.adms_store_id = :storeId)', ...]
```

---

## 6. Menu Sidebar (AdmsMenu)

### 6.1 Fluxo de Montagem

```
AdmsMenu::itemMenu($forceRefresh)
    │
    ├─ 1. Verifica cache em sessao (menu_cache_{levelId})
    │     Se existe e !forceRefresh → retorna cache
    │
    ├─ 2. fetchMenuFromDatabase()
    │     SELECT adms_nivacs_pgs + adms_menus + adms_paginas
    │     WHERE nivel = :user_level
    │       AND permissao = 1 AND lib_menu = 1
    │       AND menu.adms_sit_id = 1 AND pg.adms_sits_pg_id = 1
    │     ORDER BY menu.ordem, nivpg.ordem
    │
    ├─ 3. buildHierarchicalMenu(flatMenu)
    │     Converte lista plana → estrutura hierarquica
    │     Detecta itens dropdown (dropdown = 1)
    │     Agrupa sub-itens por menu pai
    │
    └─ 4. Armazena cache em sessao
```

### 6.2 Estrutura Hierarquica do Menu

```php
[
    [
        'id' => 1,
        'name' => 'Dashboard',
        'icon' => 'fas fa-home',
        'type' => 'link',           // Link direto
        'controller' => 'home',
        'method' => 'index',
        'url' => '/adm/home/index',
        'order' => 1
    ],
    [
        'id' => 5,
        'name' => 'Cadastros',
        'icon' => 'fas fa-cog',
        'type' => 'dropdown',       // Dropdown com sub-itens
        'order' => 3,
        'items' => [
            [
                'id' => 12,
                'name' => 'Paginas',
                'icon' => 'fas fa-file-alt',
                'controller' => 'page',
                'method' => 'list',
                'url' => '/adm/page/list',
                'order' => 1
            ],
            // ... mais sub-itens
        ]
    ]
]
```

### 6.3 Logica de dropdown vs link

- Se **qualquer item** do menu tem `dropdown = 1` → o menu inteiro vira dropdown
- Itens com `dropdown = 2` e menu sem nenhum dropdown → link direto
- O campo `dropdown` e definido na `adms_nivacs_pgs`, nao na `adms_menus`

---

## 7. CRUD de Paginas

### 7.1 Criacao (AdmsAddPage::createPage)

**Fluxo:**
1. Valida campos obrigatorios: `nome_pagina`, `controller`, `metodo`, `adms_grps_pg_id`, `adms_tps_pg_id`
2. Insere registro em `adms_paginas`
3. **Auto-provisioning de permissoes:** Para CADA nivel de acesso existente:
   - Super Admin (id=1) recebe `permissao = 1` (permitido)
   - Demais recebem `permissao = 2` (bloqueado)
   - Se `allowedLevelIds` informados, esses niveis recebem `permissao = 1`
   - `ordem` = MAX(ordem) + 1 para o nivel especifico

**Regra de negocio critica:** Ao criar uma pagina, TODOS os niveis de acesso recebem um registro em `adms_nivacs_pgs`. Isso garante que o sistema de permissoes funciona corretamente.

### 7.2 Edicao (AdmsEditPage::updatePage)

**Fluxo:**
1. Carrega dados existentes: `viewPage($id)` → SELECT * FROM adms_paginas
2. Valida campos obrigatorios: `id`, `nome_pagina`, `controller`, `metodo`, `adms_grps_pg_id`, `adms_tps_pg_id`
3. Atualiza via `AdmsUpdate::exeUpdate('adms_paginas')`
4. LoggerService + flash message

### 7.3 Exclusao (AdmsDeletePage::deletePage)

**Fluxo otimizado (3 queries):**
1. **Bulk Reorder:** UPDATE `adms_nivacs_pgs` SET ordem = ordem - 1 WHERE ordem > (ordem da pagina sendo deletada) — por nivel de acesso
2. **Bulk Delete Permissions:** DELETE FROM `adms_nivacs_pgs` WHERE adms_pagina_id = :id — remove de TODOS os niveis
3. **Delete Page:** DELETE FROM `adms_paginas` WHERE id = :id

### 7.4 Visualizacao (AdmsViewPage::viewPage)

```sql
SELECT pg.*, grpg.nome AS nome_grpg, tpgs.tipo AS tipo_tpgs, tpgs.nome AS nome_tpgs,
       sitpg.nome AS nome_sitpg, sitpg.cor AS cor_sitpg
FROM adms_paginas pg
INNER JOIN adms_grps_pgs grpg ON grpg.id = pg.adms_grps_pg_id
INNER JOIN adms_tps_pgs tpgs ON tpgs.id = pg.adms_tps_pg_id
INNER JOIN adms_sits_pgs sitpg ON sitpg.id = pg.adms_sits_pg_id
WHERE pg.id = :id LIMIT 1
```

Adicionalmente, carrega permissoes ativas: quais niveis de acesso tem `permissao = 1`.

### 7.5 Sincronizacao (SyncPagesWithLevels)

Ferramenta administrativa que garante que **todas as paginas** tenham registros de permissao para **todos os niveis de acesso**. Util apos criacao manual de paginas ou niveis.

---

## 8. Gestao de Permissoes por Nivel

### 8.1 Listagem (Permissions::list)

A tela de permissoes mostra todas as paginas para um nivel de acesso especifico, com controles para:
- **Toggle permissao** (permitir/bloquear)
- **Toggle menu** (visivel/invisivel no sidebar)
- **Toggle dropdown** (sub-item/link direto)
- **Editar menu pai** (vincular a um agrupador)
- **Reordenar** (posicao no menu)

**Filtro de seguranca:** Super Admin ve todas as paginas. Outros niveis so veem paginas que eles proprios tem permissao (evita escalacao de privilegio).

### 8.2 Toggle de Permissao (AdmsTogglePermission)

```php
// Alterna entre permitido (1) e bloqueado (2)
if ($levelPageData['permissao'] == 1) {
    $newPermission = 2; // bloquear
} else {
    $newPermission = 1; // liberar
}
UPDATE adms_nivacs_pgs SET permissao = :new WHERE id = :id
```

**Regra de seguranca:** So pode alterar permissoes de niveis com `ordem >= sua propria ordem`. Super Admin pode alterar qualquer nivel.

### 8.3 Edicao de Menu (AdmsEditLevelPermission)

Permite vincular uma pagina a um item de menu especifico na `adms_nivacs_pgs`, alterando o campo `adms_menu_id`. Isso controla sob qual dropdown a pagina aparece no sidebar.

---

## 9. Protecao CSRF (Deploy 5 — Global)

### 9.1 Historico de Deploy

| Deploy | Escopo | Descricao |
|:------:|--------|-----------|
| 1 | Core | CsrfService, helpers, middleware |
| 2 | Critico | Transfers, AbsenceControl, Usuario |
| 3 | Financeiro | HolidayPayment, OrderPayments, OrderControl |
| 4 | Admin | Lojas, Employees, Cargo, NivelAcesso |
| **5** | **Global** | **~300+ controllers protegidos** |

### 9.2 Pontos de Validacao

O token CSRF e validado em 3 fontes, nesta ordem:
1. **Form data** (`$_POST['_csrf_token']`)
2. **JSON body** (`application/json` → `_csrf_token`)
3. **HTTP header** (`csrf-token`)

### 9.3 Excecoes

| Excecao | Motivo |
|---------|--------|
| GET/HEAD/OPTIONS | Metodos seguros |
| `lib_pub = 1` | Paginas publicas |
| `Login::acesso` | Login inicial |
| `UsersOnline::ping` | Heartbeat (nao modifica dados) |

---

## 10. Middlewares de Seguranca (ConfigController)

### 10.1 Session Validation

Verifica a cada request se o usuario:
- Tem sessao ativa em `adms_users_online` (status = 1)
- Continua ativo em `adms_usuarios` (status = 1)

Cache de 5 segundos evita queries repetitivas em navegacao rapida.

### 10.2 Force Password Change

Se `must_change_password` ativo na sessao, redireciona para `force-change-password/change`. Excecoes: Login, ChangePassword, ForceChangePassword.

### 10.3 Page Tracking

Registra cada navegacao (full page load, nao AJAX) em:
- `adms_users_online` (pagina atual)
- `adms_page_visits` (historico de navegacao)
- WebSocket push para monitores em tempo real
- Alertas de monitoramento

---

## 11. Estrutura de Arquivos

### 11.1 Pages (CRUD de Rotas)

```
Controllers/
    Page.php                   # Listagem principal (match expression)
    AddPage.php                # Criacao via AJAX/JSON
    EditPage.php               # Edicao (load form + update)
    DeletePage.php             # Exclusao via AJAX/JSON
    ViewPage.php               # Visualizacao em modal
    SyncPagesWithLevels.php    # Sincronizacao paginas ↔ niveis

Models/
    AdmsPages.php              # Validacao de rota (usado pelo ConfigController)
    AdmsListPages.php          # Listagem paginada
    AdmsAddPage.php            # Criacao + auto-provisioning de permissoes
    AdmsEditPage.php           # Edicao com validacao
    AdmsDeletePage.php         # Exclusao otimizada (3 queries)
    AdmsViewPage.php           # Consulta detalhada com JOINs
    AdmsSyncPagesWithLevels.php # Sincronizacao

Views/page/
    loadPages.php              # Container principal + filtros
    listPages.php              # Tabela paginada (AJAX)
    partials/
        _add_page_modal.php    # Modal de criacao
        _edit_page_modal.php   # Modal de edicao (wrapper)
        _edit_page_form.php    # Formulario de edicao (carregado via AJAX)
        _delete_page_modal.php # Modal de exclusao
        _view_page_modal.php   # Modal de visualizacao (wrapper)
        _view_page_details.php # Detalhes da pagina (carregado via AJAX)
```

### 11.2 PageGroups (Grupos de Paginas)

```
Controllers/
    PageGroups.php             # Listagem principal
    AddPageGroup.php           # Criacao
    EditPageGroup.php          # Edicao
    DeletePageGroup.php        # Exclusao
    ViewPageGroup.php          # Visualizacao
    ReorderPageGroup.php       # Reordenacao

Models/
    AdmsListPageGroups.php     # Listagem paginada
    AdmsAddPageGroup.php       # Criacao
    AdmsEditPageGroup.php      # Edicao
    AdmsDeletePageGroup.php    # Exclusao
    AdmsViewPageGroup.php      # Visualizacao
    AdmsReorderPageGroup.php   # Reordenacao
    AdmsStatisticsPageGroups.php # Estatisticas (cards)

Views/pageGroups/
    loadPageGroups.php         # Container + filtros + stats cards
    listPageGroups.php         # Tabela paginada
    partials/                  # Modais CRUD
```

### 11.3 Menus (Sidebar)

```
Controllers/
    Menu.php                   # Listagem principal
    AddMenu.php                # Criacao
    EditMenu.php               # Edicao
    DeleteMenu.php             # Exclusao
    ViewMenu.php               # Visualizacao
    ReorderMenu.php            # Reordenacao
    ToggleMenu.php             # Toggle visibilidade
    ClearMenuCache.php         # Limpar cache de sessao
    ForceRebuildMenu.php       # Forcar reconstrucao

Models/
    AdmsMenu.php               # Model principal (cache, hierarquia, CRUD)
    AdmsListMenus.php          # Listagem paginada
    AdmsViewMenu.php           # Visualizacao
    AdmsReorderMenu.php        # Reordenacao
    AdmsToggleMenu.php         # Toggle
    AdmsStatisticsMenus.php    # Estatisticas

Views/menu/
    loadMenu.php               # Container
    listMenu.php               # Tabela paginada
    partials/                  # Modais CRUD + reorder
```

### 11.4 Permissoes e Niveis de Acesso

```
Controllers/
    Permissions.php            # Listagem de permissoes por nivel
    EditLevelPermission.php    # Editar permissao (menu, dropdown)
    AccessLevel.php            # CRUD de niveis de acesso
    AddAccessLevel.php
    EditAccessLevel.php
    DeleteAccessLevel.php
    ViewAccessLevel.php
    ReorderAccessLevel.php
    HomePermissions.php        # Permissoes da home

Models/
    AdmsListPermissions.php    # Lista permissoes com filtro de seguranca
    AdmsEditLevelPermission.php # Editar vinculacao pagina-menu
    AdmsTogglePermission.php   # Toggle allow/block
    AdmsListAccessLevels.php   # Lista niveis
    AdmsAddAccessLevel.php     # Criar nivel
    AdmsEditAccessLevel.php    # Editar nivel
    AdmsDeleteAccessLevel.php  # Excluir nivel
    AdmsViewAccessLevel.php    # Visualizar nivel
    AdmsReorderAccessLevel.php # Reordenar niveis
    AdmsHomePermissions.php    # Permissoes da home
    AdmsBotao.php              # Validacao de botoes (usado globalmente)

Views/
    permissions/               # Views de permissoes
    accessLevel/               # Views de niveis de acesso
```

### 11.5 Core e Services

```
core/
    ConfigController.php       # Roteamento central + middlewares
    ConfigView.php             # Renderizacao de views
    Config.php                 # Constantes + sessao

Services/
    PermissionService.php      # Verificacao de niveis (static methods)
    CsrfService.php            # Geracao e validacao de tokens CSRF
    SessionContext.php          # Wrapper de $_SESSION

JavaScript/
    assets/js/pages.js         # 699 linhas (CRUD de paginas)
    assets/js/page-groups.js   # 1.043 linhas (CRUD de grupos)
    assets/js/menu.js          # 926 linhas (CRUD de menus)
```

---

## 12. JavaScript

### 12.1 Resumo

| Arquivo | Linhas | Funcionalidades |
|---------|:------:|-----------------|
| `pages.js` | 699 | Listagem, busca com filtros, add/edit/delete via modais AJAX |
| `page-groups.js` | 1.043 | CRUD completo, reordenacao, estatisticas |
| `menu.js` | 926 | CRUD, toggle visibilidade, reordenacao |
| **Total** | **2.668** | |

### 12.2 Padroes Comuns

- **Async/await** para todas as operacoes AJAX
- **CSRF token** incluido em todas as requisicoes POST (via meta tag)
- **X-Requested-With: XMLHttpRequest** para deteccao AJAX no backend
- **Loading states** com spinners em botoes
- **Debounce** (500ms) no campo de busca
- **Event delegation** para paginacao dinamica
- **Delete confirmation modal** padronizado (`DeleteConfirmationModal`)
- **Server-side notifications** via `NotificationService`

---

## 13. Testes

### 13.1 Resumo

| Arquivo | Testes | Foco |
|---------|:------:|------|
| `PageGroupsControllerTest.php` | 36 | Controller principal de grupos |
| `AdmsStatisticsPageGroupsTest.php` | 17 | Estatisticas de grupos |
| `AdmsListPageGroupsTest.php` | 13 | Listagem paginada de grupos |
| `AdmsReorderPageGroupTest.php` | 13 | Reordenacao de grupos |
| `AdmsAddPageGroupTest.php` | 10 | Criacao de grupos |
| **Total** | **89** | |

**Nota:** Nao ha testes para o sub-modulo de Pages (CRUD), Menus, Permissoes, AdmsBotao, ou ConfigController. A cobertura esta limitada ao sub-modulo PageGroups.

---

## 14. Regras de Negocio — Resumo para v2

### 14.1 Roteamento

| # | Regra | Implementacao Atual |
|---|-------|---------------------|
| R1 | Toda URL do sistema deve ter registro em `adms_paginas` | ConfigController consulta DB a cada request |
| R2 | URL e convertida de kebab-case para PascalCase/camelCase | `slugController()` e `slugMetodo()` |
| R3 | URLs suportam ate 3 segmentos: `controller/metodo/parametro` | Explode por '/' limitado a 3 posicoes |
| R4 | Se rota nao encontrada e AJAX → JSON 404 | `handleRouteNotFound()` |
| R5 | Se rota nao encontrada e normal → redirect Login | `handleRouteNotFound()` |
| R6 | Namespace do controller determinado pelo tipo de pagina | `adms_tps_pgs.tipo` → `\App\{tipo}\Controllers\` |

### 14.2 Permissoes

| # | Regra | Implementacao Atual |
|---|-------|---------------------|
| P1 | Permissao e a combinacao (nivel_acesso + pagina) | `adms_nivacs_pgs` com permissao 1 ou 2 |
| P2 | Paginas publicas (`lib_pub=1`) ignoram verificacao de permissao | WHERE clause no AdmsPages |
| P3 | Ao criar pagina, auto-provisionar permissoes para TODOS os niveis | `insertPermissionsForAllAccessLevels()` |
| P4 | Super Admin (id=1) recebe permissao automatica | `permissao = 1` na criacao |
| P5 | So pode alterar permissoes de niveis com ordem >= sua propria | Filtro `nivac.ordem >= :session_level` |
| P6 | Botoes de acao sao condicionados a permissao do nivel na rota de destino | `AdmsBotao::valBotao()` |
| P7 | Toggle de permissao alterna entre 1 (allow) e 2 (deny) | `AdmsTogglePermission` |

### 14.3 Menu

| # | Regra | Implementacao Atual |
|---|-------|---------------------|
| M1 | Menu e construido por nivel de acesso | Query filtrada por `adms_niveis_acesso_id` |
| M2 | So paginas com `lib_menu=1` e `permissao=1` aparecem | WHERE na query |
| M3 | Menus e paginas devem estar ativos | `adms_sit_id=1` e `adms_sits_pg_id=1` |
| M4 | Itens com `dropdown=1` sao agrupados sob o menu pai | `buildHierarchicalMenu()` |
| M5 | Menu e cacheado em sessao por nivel | `menu_cache_{levelId}` |
| M6 | Cache invalidado ao alterar permissoes ou menu | `AdmsMenu::clearCache()` |
| M7 | Ordenacao: `menu.ordem` (grupo) depois `nivpg.ordem` (item) | ORDER BY no SQL |

### 14.4 CSRF

| # | Regra | Implementacao Atual |
|---|-------|---------------------|
| C1 | Todo POST/PUT/DELETE requer token CSRF valido | Enforcement global (Deploy 5) |
| C2 | Token aceito via form, JSON body, ou header | 3 pontos de validacao |
| C3 | Falha CSRF → log + 403 (JSON ou redirect) | `validateCsrf()` |
| C4 | Excecoes: GET, paginas publicas, login, heartbeat | Skip conditions |

### 14.5 Sessao

| # | Regra | Implementacao Atual |
|---|-------|---------------------|
| S1 | Sessao validada contra DB a cada request (cache 5s) | `validateUserSession()` |
| S2 | Admin pode forcar logout (desativar sessao online) | `adms_users_online.adms_sit_access_id` |
| S3 | Usuario desativado perde acesso imediatamente | `adms_usuarios.adms_sits_usuario_id` |
| S4 | Senha obrigatoria pode forcar troca antes de usar sistema | `checkForcePasswordChange()` |

---

## 15. Pontos de Melhoria para v2

### 15.1 Prioridade Alta

| # | Item | Descricao |
|---|------|-----------|
| 1 | **Roteamento em arquivo, nao DB** | Cada request faz query ao banco. Para v2, considerar roteamento em cache/arquivo (compilar rotas) com invalidacao ao alterar. Reduz latencia e carga no DB. |
| 2 | **N+1 queries no AdmsBotao** | Cada botao faz uma query separada. Para 4 botoes = 4 queries por pagina. Otimizar com query unica usando IN clause. |
| 3 | **Sem testes para modulos criticos** | Faltam testes para: ConfigController, AdmsPages, AdmsBotao, AdmsMenu, Permissions. Sao os modulos mais criticos do sistema. |
| 4 | **Permissao hardcoded (1 e 2)** | Os valores de permissao sao magic numbers. Para v2, usar enum ou constantes. |
| 5 | **3 segmentos de URL fixos** | Limite de `controller/metodo/parametro`. Nao suporta parametros multiplos nem nested routes. |

### 15.2 Prioridade Media

| # | Item | Descricao |
|---|------|-----------|
| 6 | **Cache de menu baseado em sessao** | Se permissoes mudam, o cache so atualiza no proximo login. Para v2, usar cache centralizado (Redis/Memcached) com invalidacao por evento. |
| 7 | **Codigo misto (portugues + ingles)** | Variaveis, metodos e tabelas misturam idiomas (`listarPaginas`, `carregarMetodo`, `Resultado`). Padronizar para ingles na v2. |
| 8 | **URL sanitization via transliteracao** | `limparUrl()` usa conversao ISO-8859-1 com mapa de caracteres manual. Para v2, usar `Transliterator::transliterate()` do ICU. |
| 9 | **Validacao de campos em flash messages HTML** | Models usam `SessionContext::setFlashMessage()` com HTML inline. Para v2, separar logica de apresentacao. |
| 10 | **Double logging** | Controller e Model logam a mesma operacao (ex: PAGE_CREATED). Centralizar em um unico ponto. |

### 15.3 Prioridade Baixa

| # | Item | Descricao |
|---|------|-----------|
| 11 | **Delete via GET** | `deletePage()` no JS usa `method: 'GET'` — deveria ser POST/DELETE. |
| 12 | **Campos `controller` vs `menu_controller`** | A tabela tem tanto `controller` (PascalCase) quanto `menu_controller` (kebab-case). Redundancia que pode dessincronizar. |
| 13 | **error_log() residual** | `AdmsEditPage::viewPage()` usa `error_log()` em vez de `LoggerService`. |
| 14 | **Falta soft-delete** | Exclusao de paginas e fisica. Para v2, considerar soft-delete para auditoria. |
| 15 | **Nao ha versionamento de permissoes** | Alteracoes de permissao nao sao logadas com before/after. Para v2, adicionar audit trail. |

---

## 16. Metricas

| Metrica | Valor |
|---------|-------|
| **Controllers** | 24 (Pages: 6, PageGroups: 6, Menus: 9, Permissions: 3) |
| **Models** | 28 (Pages: 7, PageGroups: 7, Menus: 6, Permissions: 4, Core: 4) |
| **Views** | ~22 (Pages: 8, PageGroups: 6, Menus: 8) |
| **Services** | 3 (PermissionService, CsrfService, SessionContext) |
| **JavaScript** | 3 arquivos, 2.668 linhas |
| **Testes** | 89 (PageGroups apenas) |
| **Tabelas** | 6 (paginas, nivacs_pgs, menus, niveis_acessos, grps_pgs, tps_pgs) |
| **Rotas** | ~30 rotas do proprio modulo |
| **Impacto** | Toda requisicao HTTP do sistema passa por este modulo |

---

## 17. Glossario para v2

| Termo Atual (v1) | Tabela | Sugestao v2 | Descricao |
|-------------------|--------|-------------|-----------|
| `adms_paginas` | Rotas | `routes` | Tabela de rotas/endpoints |
| `adms_nivacs_pgs` | Permissoes | `role_permissions` | Tabela pivot role-route |
| `adms_niveis_acessos` | Niveis | `roles` | Papeis/niveis de acesso |
| `adms_menus` | Menu | `menu_items` | Itens do menu sidebar |
| `adms_grps_pgs` | Grupos | `route_groups` | Agrupamento logico de rotas |
| `adms_tps_pgs` | Tipos | `route_namespaces` | Namespace do controller |
| `adms_sits_pgs` | Status | `route_statuses` | Status da rota (ativo/inativo) |
| `permissao` | 1/2 | `is_allowed` | Boolean em vez de 1=allow/2=deny |
| `lib_pub` | 1/null | `is_public` | Boolean |
| `lib_menu` | 1/null | `show_in_menu` | Boolean |
| `dropdown` | 1/2 | `is_submenu` | Boolean |
| `ordem` | INT | `sort_order` | Posicao |
| `metodo` | VARCHAR | `action` | Nome do metodo |
| `menu_controller` | VARCHAR | `route_slug` | Slug kebab-case |
| `controller` | VARCHAR | `controller_class` | Nome da classe |

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
**Versao:** 1.0
**Data:** 04/04/2026
