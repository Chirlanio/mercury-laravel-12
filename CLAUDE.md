# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Mercury Laravel is a business management system for Grupo Meia Sola, migrated from a custom PHP MVC framework to **Laravel 12 + React 18 + Inertia.js 2**. It uses MySQL/MariaDB for the primary database and optionally connects to a PostgreSQL (CIGAM) database for sales data synchronization.

## Commands

### Development

```bash
composer dev          # Starts server, queue, reverb, schedule, and Vite concurrently (uses WampServer PHP)
                      # Note: Pail is not included — it requires pcntl, which is not available on Windows PHP
php artisan serve     # Laravel server only
npm run dev           # Vite dev server only
npm run build         # Production build
```

### Testing

```bash
composer test                              # Clear config cache + run all tests
php artisan test                           # Run all tests
php artisan test --filter=SaleControllerTest  # Run a specific test class
php artisan test --filter=test_method_name    # Run a single test method
```

Tests use in-memory SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). The `TestHelpers` trait (`tests/Traits/TestHelpers.php`) bootstraps test users (admin, support, regular) and reference data.

### PHP Environment Note

Three PHP installations exist on this machine:
- **Herd Lite** (`C:\Users\MSDEV\.config\herd-lite\bin\php.exe`): PHP 8.4, lacks `pdo_pgsql`/`pdo_sqlite` — **do NOT use for dev server**
- **Full PHP 8.4** (`C:\Users\MSDEV\php84\php.exe`): Has `pdo_pgsql`, `pdo_sqlite` — for tests
- **WampServer PHP 8.4** (`C:\wamp64\bin\php\php8.4.0\php.exe`): Has `pdo_pgsql`, `pdo_sqlite`, `pdo_mysql` — **recommended for dev server** (`composer dev` uses this)

For tests, use:
```bash
C:\Users\MSDEV\php84\php.exe artisan test
```

The `composer dev` command automátically uses WampServer PHP with all required extensions.

### Linting

```bash
vendor/bin/pint        # Laravel Pint (PHP code style fixer)
```

## Architecture

### Stack

- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** React 18 with Inertia.js 2 (no separate API — Inertia bridges Laravel controllers to React pages)
- **Styling:** Tailwind CSS 3 with `@tailwindcss/forms`
- **Icons:** Heroicons React (`@heroicons/react`)
- **Build:** Vite 7 via `laravel-vite-plugin`
- **Routes:** Ziggy generates Laravel routes for frontend use

### Backend Structure

**Auth & RBAC (centralized, database-driven):**
- 4 roles: SUPER_ADMIN > ADMIN > SUPPORT > USER — defined as enum in `app/Enums/Role.php` but **permissions are managed via central DB** (`central_roles` + `central_permissions` tables)
- `CentralRoleResolver` (`app/Services/CentralRoleResolver.php`) resolves role→permissions from central DB with enum fallback, cached 5 min
- `PermissionMiddleware` checks `$user->hasPermissionTo($permission)` which delegates to `CentralRoleResolver`
- Routes protect endpoints with `middleware('permission:PERMISSION_NAME')` and `middleware('tenant.module:MODULE_SLUG')`
- Module access per plan: `CheckTenantModule` middleware blocks routes if tenant's plan doesn't include the module
- SaaS Admin manages roles/permissions at `/admin/roles-permissions` — changes propagate to all tenants without code deployment
- Tenant admins can only manage users (assign roles); they cannot manage menus, pages, or access levels

**Config Module Pattern:**
- `app/Http/Controllers/ConfigController.php` is an abstract base for CRUD modules with minimal boilerplate
- 13 config controllers in `app/Http/Controllers/Config/` extend it (Position, Sector, Gender, etc.)
- Subclasses define: `modelClass()`, `viewTitle()`, `columns()`, `formFields()`, `validationRules()`
- All render to a single generic page: `resources/js/Pages/Config/Index.jsx`
- Routes: `/config/{module}` protected by `MANAGE_SETTINGS` permission

**Services** (`app/Services/`):
- `AuditLogService` — activity tracking (used via `Auditable` trait on models)
- `CigamSyncService` — syncs sales from CIGAM PostgreSQL (`msl_fmovimentodiario_` table)
- `ImageUploadService` — avatar/image handling with `intervention/image`
- `CentralMenuResolver` — sidebar menu from central DB tables, filtered by user role + tenant's active modules
- `CentralRoleResolver` — resolves role permissions from central DB with enum fallback and caching
- `TenantRoleService` — filters allowed roles per tenant settings

### Frontend Structure

```
resources/js/
├── app.jsx                    # Inertia app bootstrap, CSRF handling, 419 session expiry modal
├── Layouts/
│   ├── AuthenticatedLayout.jsx  # Main layout: sidebar + topnav + flash messages (react-toastify)
│   └── GuestLayout.jsx          # Auth pages layout
├── Pages/                     # One directory per module, maps to Inertia::render() calls
│   ├── Config/Index.jsx         # Generic data-driven page for all 13 config modules
│   ├── Sales/Index.jsx
│   ├── Employees/Index.jsx
│   └── ...
├── Components/
│   ├── Button.jsx               # botão padrão (variants, sizes, icons, loading)
│   ├── DataTable.jsx            # Tabela padrão com sort/filter/paginate
│   ├── ActionButtons.jsx        # Botoes de ação para colunas de tabela (view/edit/delete/custom)
│   ├── StandardModal.jsx         # Modal padrão com header, sections, footer, timeline (USAR ESTE)
│   ├── Modal.jsx                # Modal base (uso interno do StandardModal apenas)
│   ├── ConfirmDialog.jsx        # Dialogo de confirmação (usar via useConfirm)
│   ├── ImageUpload.jsx          # Upload de imagem com drag-and-drop e preview
│   ├── TextInput.jsx            # Input de texto padrão
│   ├── InputLabel.jsx           # Label de formulário
│   ├── InputError.jsx           # Mensagem de erro de campo
│   ├── Checkbox.jsx             # Checkbox padrão
│   ├── EmployeeAvatar.jsx       # Avatar com fallback de iniciais
│   ├── Sidebar.jsx              # Navegação com menu do CentralMenuResolver
│   └── Shared/                  # Componentes compartilhados obrigatórios
│       ├── StatisticsGrid.jsx   # Grid de cards de KPIs (label, value, format, icon, onClick, active)
│       ├── StatusBadge.jsx      # Badge de status com variantes de cor e ícone
│       ├── FormSection.jsx      # Seção de formulário com titulo e grid responsivo
│       ├── DeleteConfirmModal.jsx # Modal de confirmação de exclusão reutilizável
│       ├── EmptyState.jsx       # Estado vazio com ícone, titulo e ação
│       ├── LoadingSpinner.jsx   # Spinner de carregamento com tamanhos e cores
│       └── SkeletonCard.jsx     # Skeleton de carregamento para cards
└── Hooks/
    ├── usePermissions.js        # Verificação de permissões (PERMISSIONS, hasPermission, hasRole)
    ├── useTenant.js             # Contexto tenant: hasModule(), plan, settings
    ├── useModalManager.js       # Gerenciamento de multiplos modais (openModal, closeModal, switchModal)
    ├── useConfirm.jsx           # Confirmação com Promise (confirm, ConfirmDialogComponent)
    └── useMasks.js              # Mascaras BR: maskMoney, maskCpf, maskCnpj, maskPhone, parseMoney
```

**Key frontend patterns:**
- `usePermissions()` reads permissions from `props.auth.permissions` (provided by `CentralRoleResolver` via Inertia) — no hardcoded permission maps
- `useTenant()` provides `hasModule(slug)` to conditionally render UI based on tenant's active modules
- `GenericFormModal` renders forms from a field definition array (used by all Config modules)
- `DataTable` is the standard list component with sorting, search, and pagination
- Flash messages from Laravel are displayed as toasts via `react-toastify`
- Inertia `router.visit()` / `router.post()` for navigation and form submissions (no Axios for page data)

### Frontend Component Standards (OBRIGATÓRIO)

**Todas as novas paginas e módulos DEVEM usar os componentes compartilhados listados abaixo.** Nao crie componentes específico para um único módulo quando ja existe um componente genérico reutilizável. O objetivo e manter o layout e a experiencia do usuário padronizados em toda a aplicação, não remova acentuação das palavras, usaremos português brasileiro como padrão.

#### Componentes Compartilhados (`Components/Shared/`)

| Componente | Quando usar | Nunca fazer |
|---|---|---|
| `StatisticsGrid` | Cards de estatísticas/KPIs no topo de qualquer pagina de listagem. Aceita `cards[]` com `label`, `value`, `format` (currency/number/percentage), `icon` (Heroicon), `color`, `sub`, `variation`, `onClick`, `active`. Suporta estado de `loading` com skeleton automático. | Criar cards de estatísticas inline com HTML/Tailwind avulso ou componentes específico por módulo (ex: `SaleStatisticsCards`, `XyzStats`). |
| `StatusBadge` | Exibir status, tipo ou qualquer label categorizado com cor. Variantes: success, warning, danger, info, purple, indigo, teal, orange, gray. Aceita `icon` e `dot`. | Criar badges manuais com `<span className="bg-green-100 text-green-800...">`. |
| `FormSection` | Agrupar campos de formulário com titulo e grid responsivo. Props: `title`, `cols` (1-4). | Criar `<div>` com titulo e grid manual para agrupar campos. |
| `EmptyState` | Tela vazia quando nao ha dados. Props: `title`, `description`, `icon` (Heroicon), `action` (botão), `compact` (para uso dentro de tabelas). | Criar mensagens "Nenhum registro" com HTML avulso. |
| `LoadingSpinner` | Estado de carregamento. Props: `size` (sm/md/lg/xl), `color`, `label`, `fullPage`. | Criar spinners manuais com animações CSS. |
| `SkeletonCard` | Placeholder de carregamento para cards. Props: `lines`, `hasHeader`. | Criar skeletons com divs e `animate-pulse` avulsos. |
| `DeleteConfirmModal` | Confirmação de exclusão com detalhes do item. Props: `show`, `onClose`, `onConfirm`, `itemType`, `itemName`, `details[]` ({label,value}), `warningMessage`, `confirmLabel`, `processing`. padrão: `const [deleteTarget, setDeleteTarget] = useState(null)` + `<DeleteConfirmModal show={deleteTarget !== null} .../>`. | Criar modais de delete manuais, usar `window.confirm()`, ou duplicar `DeleteConfirm` inline nos módulos. |

#### Componentes Core (`Components/`)

| Componente | Quando usar | Nunca fazer |
|---|---|---|
| `Button` | Todo botão da aplicação. Variantes: primary, secondary, success, warning, danger, info, light, dark, outline. Tamanhos: xs, sm, md, lg, xl. Suporta `icon`, `loading`, `iconOnly`. | Criar `<button className="bg-indigo-600...">` com estilos manuais. |
| `DataTable` | Toda listagem de dados com busca, ordenação e paginação. | Criar tabelas HTML manuais com `<table>`. |
| `ActionButtons` | Coluna de acoes em tabelas. Aceita `onView`, `onEdit`, `onDelete` e `ActionButtons.Custom` para acoes extras. | Criar grupos de botoes de ação com HTML avulso nas colunas de tabela. |
| `StandardModal` | Modal padrão para todos os módulos. Header colorido, body scrollavel, footer fixo, loading/error states. Sub-componentes: `StandardModal.Section` (card com titulo), `StandardModal.Field` (label+valor), `StandardModal.InfoCard` (métrica em destaque), `StandardModal.MiniField` (campo compacto), `StandardModal.Footer` (botoes padrão com processing), `StandardModal.Highlight` (bloco de destaque), `StandardModal.Timeline` (histórico de status). Props: `show`, `onClose`, `title`, `subtitle`, `headerColor`, `headerIcon`, `headerBadges`, `headerActions`, `maxWidth`, `loading`, `errorMessage`, `footer`, `onSubmit` (transforma body em form). | Usar o `Modal` base diretamente ou criar modais com HTML/CSS manual. O `Modal` base so deve ser usado internamente pelo `StandardModal`. |
| `ConfirmDialog` | Confirmação de acoes destrutivas. Tipos: warning, danger, info, success. **Usar via hook `useConfirm()`**. | Usar `window.confirm()` ou criar diálogos manuais. |
| `TextInput` / `InputLabel` / `InputError` / `Checkbox` | Campos de formulário. | Criar `<input>` com estilos manuais. |
| `ImageUpload` | Upload de imagem com drag-and-drop, preview, validação. | Criar inputs de upload com `<input type="file">` manual. |
| `EmployeeAvatar` / `UserAvatar` | Exibição de avatar com fallback de iniciais e cor automática. Tamanhos: xs a 3xl. | Criar avatars com `<img>` e fallbacks manuais. |

#### Hooks obrigatórios (`Hooks/`)

| Hook | Quando usar | Nunca fazer |
|---|---|---|
| `usePermissions()` | Verificar permissões do usuário. Métodos: `hasPermission()`, `hasAnyPermission()`, `hasRole()`. Constante `PERMISSIONS` com 50+ slugs. | Hardcodar verificações de role ou ler `auth.user.role` diretamente. |
| `useTenant()` | Verificar módulos ativos do tenant. Método: `hasModule(slug)`. | Verificar módulos com logica manual ou condições hardcoded. |
| `useModalManager(names[])` | Gerenciar multiplos modais numa pagina. Retorna `modals`, `selected`, `openModal()`, `closeModal()`, `switchModal()`. | Criar multiplos `useState` para controlar visibilidade de modais. |
| `useConfirm()` | Confirmação com Promise. Retorna `confirm(config)` + `ConfirmDialogComponent`. | Usar `window.confirm()`. |
| `useMasks` | Mascaras brasileiras: `maskMoney`, `maskCpf`, `maskCnpj`, `maskPhone`, `parseMoney`. | Criar funções de formatação avulsas ou usar libs externas de mascara. |

#### Estrutura padrão de uma Pagina de Listagem

Toda nova pagina de módulo **deve** seguir esta estrutura:

```jsx
import { Head, router } from '@inertiajs/react';
import { PlusIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';

export default function Index({ items, filters, stats }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'detail', 'edit']);

    return (
        <>
            <Head title="módulo" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* 1. Header com titulo + botoes de ação */}
                    {/* 2. StatisticsGrid com cards de KPIs */}
                    {/* 3. Filtros em bg-white shadow-sm rounded-lg p-4 mb-6 */}
                    {/* 4. DataTable com colunas, ActionButtons, StatusBadge */}
                </div>
            </div>

            {/* 5. Modais — SEMPRE usar StandardModal */}
            <StandardModal show={modals.create} onClose={() => closeModal('create')}
                title="Novo Item" headerColor="bg-indigo-600"
                onSubmit={handleSubmit}
                footer={<StandardModal.Footer onCancel={() => closeModal('create')}
                    onSubmit="submit" submitLabel="Salvar" processing={processing} />}>
                <StandardModal.Section title="Dados Gerais">
                    {/* campos do formulário */}
                </StandardModal.Section>
            </StandardModal>

            <StandardModal show={modals.detail} onClose={() => closeModal('detail')}
                title="Detalhes" headerColor="bg-gray-700">
                <StandardModal.Section title="Informações">
                    <div className="grid grid-cols-2 gap-4">
                        <StandardModal.Field label="Campo" value={selected?.campo} />
                    </div>
                </StandardModal.Section>
            </StandardModal>
        </>
    );
}
```

### Database

- **Primary:** MySQL/MariaDB (configured via standard `DB_*` env vars)
- **CIGAM:** Optional PostgreSQL connection (`CIGAM_DB_*` env vars in `.env`) for sales sync
- **Tests:** In-memory SQLite

### Key Libraries

- `maatwebsite/excel` — Excel export
- `barryvdh/laravel-dompdf` — PDF generation
- `intervention/image` — Image manipulation
- `laravel/breeze` — Auth scaffolding (dev dependency)
