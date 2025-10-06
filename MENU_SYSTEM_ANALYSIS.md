# Análise Comparativa: Sistema de Menu - Projeto Original vs Atual

## 📊 Resumo Executivo

### ✅ **BOA NOTÍCIA:** A estrutura do banco de dados está CORRETA!

A tabela `access_level_pages` já possui TODOS os campos necessários para replicar a lógica do projeto original. O problema é que algumas implementações estão usando uma abordagem diferente (`parent_id` em menus) ao invés da abordagem correta (páginas como submenus).

---

## 🏗️ Estrutura do Banco de Dados

### Projeto Original (PHP/MVC)

```
adms_menus
├── id
├── name
├── icon
├── order
└── is_active

adms_paginas
├── id
├── controller
├── method
├── page_name
└── ...

adms_nivacs_pgs (TABELA DE MAPEAMENTO - CORE DO SISTEMA)
├── id
├── adms_niveis_acesso_id  (nível de acesso)
├── adms_pagina_id          (página)
├── adms_menu_id            (menu pai)
├── permission              (se tem permissão)
├── order                   (ordem de exibição)
├── dropdown                (se é dropdown/submenu)
└── lib_menu                (menu liberado)
```

### Projeto Atual (Laravel)

```
menus
├── id
├── name
├── icon
├── order
├── is_active
├── parent_id ⚠️ (ADICIONADO - NÃO DEVERIA SER USADO)
├── created_at
└── updated_at

pages
├── id
├── controller
├── method
├── menu_controller
├── menu_method
├── page_name
├── notes
├── is_public
├── icon
├── page_group_id
├── is_active
├── created_at
└── updated_at

access_level_pages ✅ (TABELA DE MAPEAMENTO - ESTRUTURA CORRETA!)
├── id
├── access_level_id     ✅ (nível de acesso)
├── page_id             ✅ (página)
├── menu_id             ✅ (menu pai)
├── permission          ✅ (se tem permissão)
├── order               ✅ (ordem de exibição)
├── dropdown            ✅ (se é dropdown/submenu)
├── lib_menu            ✅ (menu liberado)
├── created_at
└── updated_at
```

**📌 CONCLUSÃO:** A estrutura está 100% correta! O problema é na implementação.

---

## ⚠️ Divergências Críticas Identificadas

### 1. Campo `parent_id` na Tabela `menus`

**Status:** ❌ **NÃO DEVERIA EXISTIR**

**Motivo:**
- No projeto original, menus **NÃO** têm hierarquia própria
- A hierarquia vem da tabela `access_level_pages` através do campo `menu_id`
- Páginas são os submenus, não outros menus

**Impacto:**
- Criado formulário de menu com campo "Menu Pai" desnecessário
- Lógica de filtro para evitar menu ser seu próprio pai (desnecessária)
- Confusão conceitual sobre o que é menu vs submenu

**Ação Recomendada:**
- ⚠️ **REMOVER** o campo `parent_id` dos formulários de menu (ou deixar deprecated)
- ⚠️ **NÃO USAR** `parent_id` na renderização do menu

---

### 2. Lógica de Renderização do Menu

**Status:** ❌ **NÃO IMPLEMENTADA**

**Projeto Original:**
```php
// AdmsMenu::itemMenu()
// Query complexa unindo 3 tabelas:
SELECT m.*, p.*, alp.*
FROM adms_nivacs_pgs alp
INNER JOIN adms_menus m ON alp.adms_menu_id = m.id
INNER JOIN adms_paginas p ON alp.adms_pagina_id = p.id
WHERE alp.adms_niveis_acesso_id = :user_level
  AND alp.permission = 1
  AND alp.lib_menu = 1
ORDER BY alp.order
```

**Projeto Atual:**
- ❌ Não existe método equivalente
- ❌ Sidebar renderiza menus estáticos do seeder
- ❌ Não considera `access_level_pages`
- ❌ Não filtra por nível de acesso do usuário

**O que deveria fazer:**
```php
// MenuService::getMenuForUser($userId)
// ou
// Menu::forAccessLevel($accessLevelId)

// Retornar estrutura:
[
    [
        'menu' => 'Configurações',
        'icon' => 'fas fa-cog',
        'items' => [
            ['page_name' => 'Gerenciar Níveis', 'route' => '/access-levels'],
            ['page_name' => 'Gerenciar Menus', 'route' => '/menus'],
            ['page_name' => 'Logs', 'route' => '/activity-logs'],
        ]
    ]
]
```

---

### 3. Interface de Gerenciamento

**Status:** ⚠️ **PARCIALMENTE IMPLEMENTADO**

#### ✅ O que JÁ existe:
- CRUD completo de Menus (Create, Read, Update - falta Delete)
- CRUD de Páginas (existe)
- CRUD de Níveis de Acesso (access_levels)
- Tabela `access_level_pages` com estrutura correta

#### ❌ O que FALTA:
- **Interface para vincular Páginas aos Menus**
- **Gestão de permissões menu/página por nível de acesso**
- **Definir quais páginas aparecem em dropdown**
- **Definir ordem de exibição das páginas dentro dos menus**
- **Marcar se página aparece no menu (lib_menu)**

**Tela que deveria existir:**
```
Gerenciar Acesso: Nível "Administrador"
┌─────────────────────────────────────────────┐
│ Menu: Configurações                         │
├─────────────────────────────────────────────┤
│ ☑ Gerenciar Níveis       Ordem: [1]  ▼     │
│ ☑ Gerenciar Menus        Ordem: [2]  ▼     │
│ ☑ Gerenciar Páginas      Ordem: [3]  ▼     │
│ ☑ Logs de Atividade      Ordem: [4]  ▼     │
│ ☐ Config. de Email       Ordem: [5]  ▼     │
└─────────────────────────────────────────────┘
                ▼ = Aparece como dropdown
```

---

## 📋 Checklist de Implementação

### Backend

#### ✅ Estrutura do Banco de Dados
- [x] Tabela `menus` criada
- [x] Tabela `pages` criada
- [x] Tabela `access_levels` criada
- [x] Tabela `access_level_pages` criada com estrutura correta
- [x] Relacionamentos definidos nos Models

#### ✅ Models
- [x] Model `Menu` criado
- [x] Model `Page` criado com relação `accessLevels()`
- [x] Model `AccessLevel` criado
- [x] Model `AccessLevelPage` (pivot model)

#### ⚠️ Controllers - Menu
- [x] MenuController::index (listar)
- [x] MenuController::store (criar)
- [x] MenuController::show (visualizar)
- [x] MenuController::update (editar)
- [ ] MenuController::destroy (deletar) ❌
- [ ] MenuController::getMenuForUser($userId) ❌

#### ⚠️ Controllers - Page
- [x] PageController::index (listar)
- [x] PageController::store (criar)
- [x] PageController::show (visualizar)
- [x] PageController::update (editar)
- [ ] PageController::destroy (deletar) ❌

#### ❌ Controllers - Access Level Pages (NÃO EXISTE)
- [ ] AccessLevelPageController::index ❌
- [ ] AccessLevelPageController::managePermissions ❌
- [ ] AccessLevelPageController::updatePermissions ❌
- [ ] AccessLevelPageController::assignPageToMenu ❌

#### ❌ Services (NÃO EXISTE)
- [ ] MenuService::getMenuStructureForUser($userId) ❌
- [ ] MenuService::getMenuForAccessLevel($accessLevelId) ❌
- [ ] PermissionService::canAccessPage($userId, $pageId) ❌

### Frontend

#### ✅ Components Genéricos
- [x] GenericFormModal (criar/editar)
- [x] GenericDetailModal (visualizar)
- [x] DataTable (listagem)
- [x] Button (padronizado)

#### ⚠️ Páginas - Menus
- [x] Menu/Index.jsx (listagem)
- [x] Modal de criação (GenericFormModal)
- [x] Modal de visualização (GenericDetailModal)
- [x] Modal de edição (GenericFormModal)
- [ ] Função de deletar ❌

#### ⚠️ Páginas - Pages
- [x] Pages/Index.jsx (listagem)
- [x] Modais de visualização
- [x] Modais de criação
- [x] Modais de edição
- [ ] Função de deletar ❌

#### ❌ Páginas - Gerenciamento de Permissões (NÃO EXISTE)
- [ ] AccessLevelPages/Manage.jsx ❌
  - [ ] Selecionar nível de acesso ❌
  - [ ] Selecionar menu ❌
  - [ ] Listar páginas disponíveis ❌
  - [ ] Checkboxes para permissão ❌
  - [ ] Inputs para ordem ❌
  - [ ] Toggle para dropdown ❌
  - [ ] Toggle para lib_menu ❌
  - [ ] Botão salvar permissões ❌

#### ⚠️ Layout - Sidebar
- [x] Sidebar.jsx existe
- [x] Renderiza menus estáticos (seeder)
- [ ] Busca menus dinâmicos por nível de acesso ❌
- [ ] Renderiza páginas como submenus ❌
- [ ] Respeita `dropdown`, `order`, `lib_menu` ❌
- [ ] Filtra por `permission` ❌

---

## 🔧 Ações Corretivas Necessárias

### 1. **ALTA PRIORIDADE**

#### A) Remover uso de `parent_id` nos formulários de Menu

**Arquivos a modificar:**
- `resources/js/Pages/Menu/Index.jsx`
  - Remover seção "Hierarquia" do `getFormSections()`
  - Remover lógica de filtro de parent_id

**Código a remover:**
```javascript
{
    title: 'Hierarquia',
    fields: [
        {
            name: 'parent_id',
            label: 'Menu Pai (Submenu)',
            type: 'select',
            // ... REMOVER TODA ESTA SEÇÃO
        },
    ],
},
```

#### B) Criar Service para Gerenciar Menu Dinâmico

**Novo arquivo:** `app/Services/MenuService.php`

```php
<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Page;
use App\Models\AccessLevelPage;
use Illuminate\Support\Collection;

class MenuService
{
    /**
     * Retorna estrutura de menu baseada no nível de acesso do usuário
     */
    public static function getMenuForUser(int $userId): array
    {
        $user = User::with('accessLevel')->find($userId);

        if (!$user || !$user->accessLevel) {
            return [];
        }

        return self::getMenuForAccessLevel($user->accessLevel->id);
    }

    /**
     * Retorna estrutura de menu para um nível de acesso específico
     */
    public static function getMenuForAccessLevel(int $accessLevelId): array
    {
        // Query equivalente ao projeto original
        $menuItems = AccessLevelPage::query()
            ->where('access_level_id', $accessLevelId)
            ->where('permission', true)
            ->where('lib_menu', true)
            ->with(['menu', 'page'])
            ->orderBy('order')
            ->get()
            ->groupBy('menu_id');

        $menuStructure = [];

        foreach ($menuItems as $menuId => $items) {
            $menu = $items->first()->menu;

            if (!$menu) continue;

            $menuStructure[] = [
                'id' => $menu->id,
                'name' => $menu->name,
                'icon' => $menu->icon,
                'order' => $menu->order,
                'is_dropdown' => $items->where('dropdown', true)->count() > 0,
                'items' => $items->map(function ($item) {
                    return [
                        'id' => $item->page->id,
                        'name' => $item->page->page_name,
                        'route' => route($item->page->menu_controller . '.' . $item->page->menu_method),
                        'icon' => $item->page->icon,
                        'order' => $item->order,
                    ];
                })->sortBy('order')->values()->toArray(),
            ];
        }

        return collect($menuStructure)->sortBy('order')->values()->toArray();
    }
}
```

#### C) Criar Controller para Gerenciamento de Permissões

**Novo arquivo:** `app/Http/Controllers/AccessLevelPageController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\AccessLevel;
use App\Models\Menu;
use App\Models\Page;
use App\Models\AccessLevelPage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccessLevelPageController extends Controller
{
    public function manage(AccessLevel $accessLevel, Menu $menu = null)
    {
        $menus = Menu::active()->ordered()->get();

        $selectedMenu = $menu ?? $menus->first();

        // Buscar páginas e suas permissões para este menu e nível
        $pages = Page::active()->get();

        $permissions = AccessLevelPage::where('access_level_id', $accessLevel->id)
            ->where('menu_id', $selectedMenu->id)
            ->get()
            ->keyBy('page_id');

        return Inertia::render('AccessLevelPages/Manage', [
            'accessLevel' => $accessLevel,
            'menus' => $menus,
            'selectedMenu' => $selectedMenu,
            'pages' => $pages->map(function ($page) use ($permissions) {
                $permission = $permissions->get($page->id);

                return [
                    'id' => $page->id,
                    'page_name' => $page->page_name,
                    'controller' => $page->controller,
                    'method' => $page->method,
                    'permission' => $permission ? $permission->permission : false,
                    'order' => $permission ? $permission->order : 999,
                    'dropdown' => $permission ? $permission->dropdown : false,
                    'lib_menu' => $permission ? $permission->lib_menu : false,
                ];
            }),
        ]);
    }

    public function updatePermissions(Request $request, AccessLevel $accessLevel, Menu $menu)
    {
        $validated = $request->validate([
            'pages' => 'required|array',
            'pages.*.page_id' => 'required|exists:pages,id',
            'pages.*.permission' => 'boolean',
            'pages.*.order' => 'integer|min:0',
            'pages.*.dropdown' => 'boolean',
            'pages.*.lib_menu' => 'boolean',
        ]);

        foreach ($validated['pages'] as $pageData) {
            AccessLevelPage::updateOrCreate(
                [
                    'access_level_id' => $accessLevel->id,
                    'menu_id' => $menu->id,
                    'page_id' => $pageData['page_id'],
                ],
                [
                    'permission' => $pageData['permission'],
                    'order' => $pageData['order'],
                    'dropdown' => $pageData['dropdown'],
                    'lib_menu' => $pageData['lib_menu'],
                ]
            );
        }

        return back()->with('success', 'Permissões atualizadas com sucesso!');
    }
}
```

#### D) Criar Tela de Gerenciamento de Permissões

**Novo arquivo:** `resources/js/Pages/AccessLevelPages/Manage.jsx`

(Interface para gerenciar quais páginas aparecem em cada menu para cada nível de acesso)

---

### 2. **MÉDIA PRIORIDADE**

#### E) Atualizar Sidebar para Buscar Menu Dinâmico

**Arquivo:** `resources/js/Components/Sidebar.jsx`

Modificar para buscar menu do backend ao invés de usar dados estáticos.

#### F) Implementar Funções de Delete

- MenuController::destroy
- PageController::destroy

---

### 3. **BAIXA PRIORIDADE**

#### G) Documentação

- Criar guia de uso do sistema de permissões
- Documentar fluxo de criação de novo menu com páginas

---

## 📝 Conclusão

### Resumo do Status:

| Componente | Status | Observação |
|------------|--------|------------|
| **Banco de Dados** | ✅ 100% | Estrutura perfeita! |
| **Models** | ✅ 100% | Relacionamentos corretos |
| **CRUD Menus** | ⚠️ 85% | Falta delete + remover parent_id |
| **CRUD Páginas** | ⚠️ 85% | Falta delete |
| **CRUD Access Levels** | ✅ 100% | OK |
| **Gerenciar Permissões** | ❌ 0% | NÃO IMPLEMENTADO |
| **Menu Dinâmico** | ❌ 0% | NÃO IMPLEMENTADO |
| **Sidebar** | ⚠️ 30% | Usa dados estáticos |

### Próximos Passos Recomendados:

1. ✅ **Ler esta análise completa**
2. 🔧 **Remover `parent_id` dos formulários de menu**
3. 🚀 **Implementar MenuService**
4. 🎨 **Criar tela de gerenciamento de permissões**
5. 🔄 **Atualizar Sidebar para usar menu dinâmico**
6. ✨ **Testar fluxo completo**

---

**Data:** 2025-01-05
**Autor:** Claude (Assistente IA)
**Versão:** 1.0
