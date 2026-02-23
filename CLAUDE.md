# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Mercury Laravel is a business management system for Grupo Meia Sola, migrated from a custom PHP MVC framework to **Laravel 12 + React 18 + Inertia.js 2**. It uses MySQL/MariaDB for the primary database and optionally connects to a PostgreSQL (CIGAM) database for sales data synchronization.

## Commands

### Development

```bash
composer dev          # Starts Laravel server, queue worker, Pail logs, and Vite dev server concurrently
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

Two PHP installations exist on this machine:
- **Herd Lite** (`C:\Users\MSDEV\.config\herd-lite\bin\php.exe`): PHP 8.4, lacks `pdo_pgsql`/`pdo_sqlite`
- **Full PHP 8.4** (`C:\Users\MSDEV\php84\php.exe`): Has `pdo_pgsql`, `pdo_sqlite` — required for CIGAM sync and tests

For tests or CIGAM features, prefix with the full PHP path:
```bash
C:\Users\MSDEV\php84\php.exe artisan test
C:\Users\MSDEV\php84\php.exe artisan serve
```

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

**Auth & RBAC:**
- 4 roles defined in `app/Enums/Role.php`: SUPER_ADMIN > ADMIN > SUPPORT > USER (hierarchical)
- 23 permissions in `app/Enums/Permission.php` (e.g., `VIEW_SALES`, `MANAGE_SETTINGS`)
- `PermissionMiddleware` checks `$user->role->hasPermissionTo($permission)` — permission logic lives in the Role enum, not a pivot table
- Routes protect endpoints with `middleware('permission:PERMISSION_NAME')`

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
- `MenuService` — dynamic sidebar menu with permission filtering

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
│   ├── DataTable.jsx            # Reusable table with sort/filter/paginate
│   ├── Sidebar.jsx              # Permission-aware navigation
│   ├── Shared/                  # Modal, Button variants, Input, Checkbox, etc.
│   └── Modals/                  # GenericFormModal, GenericDetailModal, module-specific modals
└── Hooks/
    ├── usePermissions.js        # Frontend permission/role checking (mirrors backend Role enum)
    └── useConfirm.jsx           # Confirmation dialog hook
```

**Key frontend patterns:**
- `usePermissions()` hook mirrors the backend RBAC — check with `hasPermission()`, `isAdmin()`, `canViewUsers()`, etc.
- `GenericFormModal` renders forms from a field definition array (used by all Config modules)
- `DataTable` is the standard list component with sorting, search, and pagination
- Flash messages from Laravel are displayed as toasts via `react-toastify`
- Inertia `router.visit()` / `router.post()` for navigation and form submissions (no Axios for page data)

### Database

- **Primary:** MySQL/MariaDB (configured via standard `DB_*` env vars)
- **CIGAM:** Optional PostgreSQL connection (`CIGAM_DB_*` env vars in `.env`) for sales sync
- **Tests:** In-memory SQLite

### Key Libraries

- `maatwebsite/excel` — Excel export
- `barryvdh/laravel-dompdf` — PDF generation
- `intervention/image` — Image manipulation
- `laravel/breeze` — Auth scaffolding (dev dependency)
