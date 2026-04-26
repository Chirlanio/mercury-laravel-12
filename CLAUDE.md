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
- 9 roles: SUPER_ADMIN (10) > ADMIN (9) > FINANCE / ACCOUNTING / FISCAL / MARKETING (8) > SUPPORT (2) > USER / DRIVER (1) — defined as enum in `app/Enums/Role.php` but **permissions are managed via central DB** (`central_roles` + `central_permissions` tables). Hierarchy levels têm gaps intencionais (1, 2, 8, 9, 10) pra permitir adicionar roles intermediárias sem shift em cascata.
- `CentralRoleResolver` (`app/Services/CentralRoleResolver.php`) resolves role→permissions from central DB with enum fallback, cached 5 min
- `PermissionMiddleware` checks `$user->hasPermissionTo($permission)` which delegates to `CentralRoleResolver`
- Routes protect endpoints with `middleware('permission:PERMISSION_NAME')` and `middleware('tenant.module:MODULE_SLUG')`
- Module access per plan: `CheckTenantModule` middleware blocks routes if tenant's plan doesn't include the module
- SaaS Admin manages roles/permissions at `/admin/roles-permissions` — changes propagate to all tenants without code deployment
- Tenant admins can only manage users (assign roles); they cannot manage menus, pages, or access levels

**Config Module Pattern:**
- `app/Http/Controllers/ConfigController.php` is an abstract base for CRUD modules with minimal boilerplate
- 40 config controllers in `app/Http/Controllers/Config/` extend it (Position, Sector, Gender, ReversalReason, ReturnReason, etc.)
- Subclasses define: `modelClass()`, `viewTitle()`, `columns()`, `formFields()`, `validationRules()`
- All render to a single generic page: `resources/js/Pages/Config/Index.jsx`
- Routes: `/config/{module}` protected by `MANAGE_SETTINGS` permission

**Services** (`app/Services/` — 106 services):
- `AuditLogService` — activity tracking (used via `Auditable` trait on models)
- `CigamSyncService` — syncs sales from CIGAM PostgreSQL (`msl_fmovimentodiario_` table)
- `ImageUploadService` — avatar/image handling with `intervention/image`
- `CentralMenuResolver` — sidebar menu from central DB tables, filtered by user role + tenant's active modules
- `CentralRoleResolver` — resolves role permissions from central DB with enum fallback and caching
- `TenantRoleService` — filters allowed roles per tenant settings

**Module Services Pattern** (PurchaseOrders, Reversals, Returns, Vacancies, Coupons, etc.):
- `{Module}Service` — CRUD + business rules (validation, snapshot, dedup)
- `{Module}TransitionService` — single point of state mutation, enforces permissions per transition, records `{module}_status_histories`
- `{Module}LookupService` (where applicable) — AJAX lookups, external data resolution
- `{Module}ExportService` + `{Module}ImportService` — XLSX/PDF export, XLSX/CSV import with upsert
- Events dispatched post-commit; listeners handle notifications and integrations (e.g. Helpdesk hooks)
- **Laravel 12 event auto-discovery is enabled by default** (`$shouldDiscoverEvents = true`). Listeners in `app/Listeners/` with typed `handle(Event $e)` are auto-registered — **do NOT also call `Event::listen()` manually** or handlers fire twice. Return/Reversal listeners have this bug; Coupons avoids it.

**Movements (fonte de verdade CIGAM):**
- Tabela `movements` é source of truth — outros módulos leem, nunca escrevem. Dependentes: Reversals (FK `movement_id`), Returns (FK `movement_id`), PurchaseOrderReceiptItems (FK `matched_movement_id` unique), `sales` (derivada via `refreshSalesSummary()`)
- Movement codes: **1**=Compra (200k+ registros reais), **2**=Vendas, **6**=Devoluções (entry_exit='E'). Code 17 é referência documental e **nunca aparece** em dados reais — use code=1 para OCs
- Scheduled: `movements:sync today` every 5min + `movements:sync auto` daily 06:00 (veja `routes/console.php`)
- `MovementController::buildFilteredQuery()` centraliza filtros da listagem + exports — paridade garantida
- NF é chave composta (`store_code` + `invoice_number` + `movement_date`), não entidade — número reseta por ano/loja, sempre passe data. `MovementInvoiceService::find()` agrega os items
- Detalhes em `C:\Users\MSDEV\.claude\projects\C--xampp-htdocs-mercury-laravel\memory\movements_module.md`

**Coupons (paridade v1 `adms_coupons` + melhorias):**
- 3 tipos com regras condicionais: Consultor/MS Indica exigem `store_code + employee_id`; Influencer exige `city + social_media_id`; MS Indica **restrito a lojas administrativas** (`Store.network_id IN [6, 7]` — Z441/Z442/Z443/Z999)
- **CPF com encryption manual + `cpf_hash` HMAC-SHA256 determinístico** — permite busca/unicidade sem expor CPF em claro. NÃO usa o cast `encrypted` nativo (conflita com mutator que recalcula o hash)
- Unicidade varia por tipo (via `CouponService::ensureUnique()`, não via DB constraint — MySQL não trata `NULL=NULL`): Consultor/MsIndica por `(cpf_hash, type, store_code)` permite cupons em lojas diferentes; Influencer por `(cpf_hash, type)`
- Config module auxiliar `SocialMedia` com `link_type` (`url`|`username`) + `link_placeholder` — valida link do Influencer contextualmente (YouTube exige URL, Instagram aceita @user ou URL)
- Análise completa em `docs/ANALISE_MODULO_COUPONS.md`

**Customers VIP / Programa MS Life (curadoria anual Black/Gold da Marketing):**
- Sub-módulo de Customers (mesmo `tenant.module:customers`), 6 permissions `customer_vips.*` + Role nova `MARKETING` (hierarchy 8). 3 tabelas tenant isoladas do sync CIGAM — nada toca `customers`, apenas FKs por `customer_id`.
- **Regra N-1**: lista do ano N usa faturamento de N-1 (Lista 2026 ↔ vendas 2025). Encapsulado em `CustomerVipClassificationService::revenueYearFor($listYear)`.
- **Escopo MS Life**: SÓ vendas em lojas da rede `MEIA SOLA` (`networks.nome`) contam — Arezzo/Schutz/MS Off/E-Commerce/Administrativo são excluídas. Resolvido via `msLifeStoreCodes()` com cache `array` por request, comparação `UPPER()` case-insensitive (seeder produção usa MAIÚSCULA, TestHelpers usa CamelCase).
- **Fluxo híbrido**: auto sugere via `generateSuggestions(year)` (movements code=2 soma, code=6+E subtrai), Marketing cura manualmente via `curate()`. Curadoria com `curated_at != null` SEMPRE preservada — rodadas auto subsequentes só atualizam snapshots, nunca sobrescrevem `final_tier`. `cleanupObsoleteAutoTiers` deleta registros auto que não qualificam mais.
- **Régua incompleta = pré-falha**: `runSuggestions` valida que Black E Gold estão cadastrados ANTES de processar. Sem ambos, retorna warning específico e nada é persistido.
- **Loja preferida**: snapshot por cliente/ano com tie-break hierárquico revenue → tickets → items → menor store_code (estabilidade).
- UI: cadastro do par Black+Gold em uma operação via endpoint `POST /customers/vip/config/year` (validação Black>=Gold). Campo Ano é `<select>` dinâmico populado pelo backend (`availableYears` = anos cadastrados ∪ ano corrente ∪ próximo). Layout `lg:flex-row` (não estoura iPad mini).
- Detalhes em `C:\Users\MSDEV\.claude\projects\C--xampp-htdocs-mercury-laravel\memory\customers_vip_module.md`

**Travel Expenses (Verbas de Viagem — paridade v1 `adms_travel_expenses` + melhorias):**
- 2 state machines paralelas e independentes: `TravelExpenseStatus` (6 estados: draft → submitted → approved → finalized | cancelled, com rejected terminal) e `AccountabilityStatus` (5 estados: pending → in_progress → submitted → approved | rejected) — campos `status` e `accountability_status` separados (v1 tinha as 2 colunas mas só usava uma).
- **Daily rate persistido** (`daily_rate` decimal + `days_count` int + `value` calculado), não hardcoded como na v1 onde era `R$ 100` fixo no Service. Permite mudança de política sem recalcular registros antigos.
- **CPF + cpf_hash HMAC-SHA256** (mesmo padrão de Coupons): `cpf_encrypted` armazena valor encriptado via `Crypt::encryptString`, `cpf_hash` permite busca/dedup sem expor claro. **Mutator setCpfAttribute recalcula hash automaticamente** — não usar cast `encrypted` nativo. PIX key também encriptada via mutator próprio.
- **Pagamento dual XOR** (bank_id+branch+account ou pix_type_id+pix_key) — validação no `TransitionService` antes de DRAFT → SUBMITTED via `ensurePaymentInfo()`. Pelo menos um esperado, ambos aceitos.
- **History com kind discriminator**: tabela `travel_expense_status_histories` tem coluna `kind` (`expense` | `accountability`) — uma única tabela atende as 2 state machines, identificável via scope `expenseKind()` / `accountabilityKind()`.
- **Auto-transição da prestação**: ao adicionar primeiro item (qualquer source), `accountability_status` vai de PENDING → IN_PROGRESS automaticamente. Ao remover último item, volta para PENDING. Operações silenciosas (sem evento), apenas history gravado.
- **FINALIZED requer accountability APPROVED** — pré-condição validada no `TransitionService::validateExpenseTransitionPreconditions()`. Erro de UX comum: tentar finalizar antes de aprovar a prestação.
- **Hook Helpdesk fail-safe**: ao rejeitar verba ou prestação, abre ticket no depto "Financeiro" via `OpenHelpdeskTicketForTravelExpense` (auto-discovered). 3 camadas de fail-safe: módulo desinstalado / depto inexistente / erro inesperado → log + skip silencioso. Idempotente via `helpdesk_ticket_id` na verba.
- **Cancel bloqueado pós-prestação**: depois que `accountability_status` sai de PENDING (entrou em in_progress/submitted/rejected/approved), não é mais possível cancelar a verba — o fluxo correto passa a ser aprovar/rejeitar a prestação. Validado em `validateExpenseTransitionPreconditions` + espelhado no `canCancelRow` do front.
- **CPF do beneficiado obrigatório + auto-fill**: select de Beneficiado é populado com `selects.employees` filtrado por loja selecionada (client-side, sem AJAX) e por `status_id = 2` (apenas ativos, ~345 dos ~1544 colaboradores). Ao selecionar, o CPF do colaborador é auto-preenchido via mutator (employee.cpf é `$hidden` → `makeVisible('cpf')` no controller só para esse endpoint). Edição manual continua possível para correção pontual.
- **Chave PIX com máscara/validação dinâmica por tipo**: 4 tipos mapeados pelo nome do TypeKeyPix (case-insensitive): CPF/CNPJ → maskCpfCnpj 11/14 dígitos; E-mail → regex; Celular → maskPhone 10/11 dígitos com DDD; Aleatória → maskRandomKey UUID 8-4-4-4-12 hex. Validação espelhada em `TravelExpenseService::validatePixKey()` (defesa em profundidade). Input fica desabilitado até o tipo ser escolhido.
- **Item da prestação restrito ao período da viagem**: `expense_date` no form ganha `min={initial_date}` e `max={end_date}` (calendário do browser bloqueia). Backend usa `after_or_equal:{initial_date}` + `before_or_equal:{end_date}` no validate (defesa em profundidade). Valor monetário usa maskMoney + parseMoney (decimal puro vai pro backend).
- **Filtros aplicam no onChange**: selects/datas/checkbox disparam request imediato; campo de busca tem debounce de 400ms via useRef. `applyFilters({overrides})` aceita argumentos para evitar lag do useState dentro do mesmo ciclo. `replace: true` no router.get evita acumular histórico. Paginação preserva filtros via `paginate()->withQueryString()` no backend + merge com `window.location.search` no DataTable.
- **Commands agendados**: `travel-expenses:accountability-overdue` (daily 09:00) substitui o cron v1 — alerta solicitante + financeiro sobre verbas APROVADAS com prestação ≥3 dias atrasada após end_date. `travel-expenses:auto-cancel-stale` (daily 02:00) cancela DRAFTs >30d abandonados (silencioso, sem evento).
- **Vínculo opcional `accounting_class_id` em TypeExpense** — preparação para futura projeção DRE (cada item da prestação herdará a classe contábil do tipo). Hook em si fica para iteração futura.
- **Scoping por loja automático**: usuário sem `MANAGE_TRAVEL_EXPENSES` nem `APPROVE_TRAVEL_EXPENSES` só vê verbas da própria loja (via `user.store_id`). Approver (Financeiro) vê todas.
- **Upload disk='public'**, dir `travel-expenses/{ulid}/`, max 5MB, aceita PDF/JPG/PNG/WebP. Mime+size validados em `TravelExpenseAccountabilityService::validateUpload()`.
- ULID público (substitui hash_id UUID v7 da v1) — `getRouteKeyName()` resolve por `ulid` em todas as rotas com `{travelExpense}`.
- Detalhes em `C:\Users\MSDEV\.claude\projects\C--xampp-htdocs-mercury-laravel\memory\travel_expenses_module.md`

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
│       ├── PageHeader.jsx      # Header padronizado de página (título + botões de ação responsivos)
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
| `PageHeader` | **Header padronizado de TODA página de listagem/detalhe.** Container mobile-first (stack em mobile, linha em `sm:` ou `lg:` conforme quantidade de ações). API declarativa via `actions[]` — cada ação aceita **`type`** (preset com icon+variant+label default — ver tabela abaixo), `label`, `icon`, `variant`, `compact` (`'auto'`\|`true`\|`false`), `visible`, **uma** de `onClick`/`href`/`download`/`items[]` (dropdown). Props do header: `title`, `subtitle` (string ou JSX), `icon` (Heroicon), `scopeBadge`, `breakpoint` (`auto`\|`sm`\|`lg`). Touch target `min-h-[44px]` automático. **Compact `'auto'` (default)**: ações com variant ≠ `primary` viram icon-only sempre, label vira tooltip — só a ação primária mostra label completo (padrão visual do OrderPayments). Aceita `children` para casos custom. | Criar headers com `<div className="flex justify-between...">` inline, duplicar classes Tailwind, usar `<a>` puro com classes inline para export, aninhar `<Button>` dentro de `<Link>` (gera `<button>` dentro de `<a>`, HTML inválido). |
| `StatisticsGrid` | Cards de estatísticas/KPIs no topo de qualquer pagina de listagem. Aceita `cards[]` com `label`, `value`, `format` (currency/number/percentage), `icon` (Heroicon), `color`, `sub`, `variation`, `onClick`, `active`. Suporta estado de `loading` com skeleton automático. **Layout responsivo**: cols 1-6 escalam progressivamente (cols=4 vira `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`); valor usa `text-lg sm:text-xl xl:text-2xl` + `tabular-nums` + `truncate` + `title=valor` (tooltip hover) — não estoura em iPad mini para valores monetários longos. | Criar cards de estatísticas inline com HTML/Tailwind avulso ou componentes específico por módulo (ex: `SaleStatisticsCards`, `XyzStats`). |
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
| `useMasks` | Mascaras brasileiras: `maskMoney`, `maskCpf`, `maskCnpj`, `maskPhone`, `parseMoney`. **Importar como funções soltas** (`import { maskMoney, parseMoney } from '@/Hooks/useMasks'`), NUNCA como hook (`useMasks()` não existe e quebra build do Vite). | Criar funções de formatação avulsas ou usar libs externas de mascara. |

#### Estrutura padrão de uma Pagina de Listagem

Toda nova pagina de módulo **deve** seguir esta estrutura:

```jsx
import { Head, router } from '@inertiajs/react';
import { PlusIcon, ChartBarIcon, DocumentArrowDownIcon, ArrowUpTrayIcon } from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import PageHeader from '@/Components/Shared/PageHeader';
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
                    {/* 1. PageHeader — SEMPRE usar. Type presets aplicam icon+variant+label automaticamente. */}
                    <PageHeader
                        title="Meu módulo"
                        subtitle="Descrição do módulo"
                        scopeBadge={isStoreScoped ? 'escopo: sua loja' : null}
                        actions={[
                            { type: 'dashboard', href: route('modulo.dashboard') },
                            { type: 'download', download: route('modulo.export', filters), visible: canExport },
                            { type: 'import', href: route('modulo.import'), visible: canImport },
                            { type: 'create', label: 'Novo', onClick: () => openModal('create'), visible: canCreate },
                        ]}
                    />
                    {/* 2. StatisticsGrid com cards de KPIs (responsivo automaticamente) */}
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

**Ações do PageHeader — 3 formas mutuamente exclusivas por ação:**

| Prop | Renderiza | Usar para |
|---|---|---|
| `onClick: () => fn()` | `<Button>` | Abrir modal, rodar função da página (sync, gerar sugestões, etc.) |
| `href: route('...')` | `<Link>` (Inertia) | Navegar para outra página dentro do app (Dashboard, Histórico, sub-rotas) |
| `download: route('...')` | `<a>` nativo | Download direto (XLSX/PDF/CSV) preservando query params de filtros |

**Type presets disponíveis** (aplicam icon + variant + label default; props explícitas sobrescrevem):

| `type:` | Ícone | Variant | Label default | Quando usar |
|---|---|---|---|---|
| `'create'` | `PlusIcon` | `primary` (preenchido) | `'Novo'` | Ação principal de criação — único type que mostra label sempre |
| `'back'` | `ArrowLeftIcon` | `outline` | `'Voltar'` | Botão de retorno em dashboards/imports/show |
| `'download'` | `ArrowDownTrayIcon` | `success-soft` (verde) | `'Exportar'` | Export XLSX/PDF/CSV — passar `download: route(...)` |
| `'print'` | `PrinterIcon` | `info-soft` (azul) | `'Imprimir'` | `onClick: () => window.print()` ou rota de impressão |
| `'import'` | `ArrowUpTrayIcon` | `info-soft` | `'Importar'` | Upload de planilha — modal ou página dedicada |
| `'dashboard'` | `ChartBarIcon` | `primary-soft` (indigo) | `'Dashboard'` | Link para dashboard analítico do módulo |
| `'reports'` | `ChartBarIcon` | `primary-soft` | `'Relatórios'` | Geralmente com `items[]` (dropdown de relatórios) |
| `'history'` | `ClockIcon` | `outline` | `'Histórico'` | Histórico/log/auditoria |
| `'sync'` | `ArrowPathIcon` | `warning-soft` (âmbar) | `'Sincronizar'` | Sync com sistema externo (CIGAM etc) |
| `'refresh'` | `ArrowPathIcon` | `warning-soft` | `'Atualizar'` | Recálculo de dados internos |
| `'filter'` | `FunnelIcon` | `outline` | `'Filtros'` | Toggle de painel de filtros |
| `'settings'` | `Cog6ToothIcon` | `outline` | `'Configurações'` | Sub-página de configuração do módulo |
| `'edit'` | `PencilSquareIcon` | `warning-soft` | `'Editar'` | Edição global (raro no header — geralmente está em ActionButtons da linha) |
| `'delete'` | `TrashIcon` | `danger-soft` (vermelho) | `'Excluir'` | Ação destrutiva |
| `'view'` | `EyeIcon` | `info-soft` | `'Visualizar'` | Visualização |

**Variants soft** (`primary-soft`, `info-soft`, `success-soft`, `warning-soft`, `danger-soft`): borda + texto coloridos com fundo branco. Identificação visual por cor sem peso de variant preenchida — perfeito para botões compactos no header.

**Comportamento `compact` (default `'auto'`):**
- `'auto'`: variant ≠ `primary` E tem ícone → icon-only sempre, label vira tooltip via `title`. Padrão visual do OrderPayments — secundárias compactas, primária com label.
- `true`: força icon-only mesmo sendo `primary`.
- `false`: força label sempre visível.

Touch target `min-h-[44px]` automático. Container responsivo: stack em mobile → linha em `sm:` (1-2 ações) ou `lg:` (3+ ações, iPad-mini safe).

**Dropdown via `items[]`:**

```jsx
{
    type: 'reports',  // herda ChartBarIcon + primary-soft + label "Relatórios"
    items: [
        { label: 'Vendas', icon: CurrencyDollarIcon, href: route('reports.sales') },
        { label: 'Estoque', icon: ArchiveBoxIcon, href: route('reports.stock') },
        { divider: true },
        { label: 'Personalizado', icon: Cog6ToothIcon, onClick: () => openModal('reportConfig') },
    ],
}
```

Cada item suporta `href` (Link Inertia), `download` (`<a>` nativo), `onClick`, e `divider: true` (separador). Click outside e tecla Esc fecham.

### Database

- **Primary:** MySQL/MariaDB (configured via standard `DB_*` env vars)
- **CIGAM:** Optional PostgreSQL connection (`CIGAM_DB_*` env vars in `.env`) for sales sync
- **Tests:** In-memory SQLite

### Key Libraries

- `maatwebsite/excel` — Excel export
- `barryvdh/laravel-dompdf` — PDF generation
- `intervention/image` — Image manipulation
- `laravel/breeze` — Auth scaffolding (dev dependency)
