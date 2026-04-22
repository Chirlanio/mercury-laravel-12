# Descoberta — Módulo DRE Financeira

**Data:** 2026-04-21
**Escopo:** levantamento do estado atual do projeto antes de escrever qualquer linha do módulo DRE.
**Fonte primária do plano de contas real:** `docs/Plano de Contas.xlsx` (86 KB, modificado 2026-04-20 16:27).

Este documento é apenas um mapeamento. Nenhuma decisão de arquitetura do DRE é tomada aqui — as questões abertas estão consolidadas no final.

---

## 1. Stack e convenções

### 1.1 Versões exatas

**Backend (`composer.json`):**

| Pacote | Versão requerida |
|---|---|
| `php` | `^8.2` |
| `laravel/framework` | `^12.0` |
| `inertiajs/inertia-laravel` | `^2.0` |
| `laravel/sanctum` | `^4.0` |
| `laravel/reverb` | `^1.10` |
| `laravel/socialite` | `^5.26` |
| `stancl/tenancy` | `^3.10` |
| `maatwebsite/excel` | `^3.1` |
| `barryvdh/laravel-dompdf` | `^3.1` |
| `intervention/image` | `^3.11` |
| `tightenco/ziggy` | `^2.0` |
| `webklex/php-imap` | `^6.2` |
| `picqer/php-barcode-generator` | `^3.2` |

Dev: `laravel/breeze ^2.3`, `laravel/pint ^1.24`, `phpunit ^11.5.3`, `mockery ^1.6`, `fakerphp/faker ^1.23`, `lucascudo/laravel-pt-br-localization ^3.0`.

**Frontend (`package.json`):**

| Pacote | Versão |
|---|---|
| `react` | `^18.2.0` |
| `react-dom` | `^18.2.0` |
| `@inertiajs/react` | `^2.0.0` |
| `@headlessui/react` | `^2.0.0` |
| `@heroicons/react` | `^2.2.0` |
| `tailwindcss` | `^3.2.1` |
| `@tailwindcss/forms` | `^0.5.3` |
| `vite` | `^7.0.4` |
| `laravel-vite-plugin` | `^2.0.0` |
| `@vitejs/plugin-react` | `^4.2.0` |
| `recharts` | `^3.8.1` |
| `react-toastify` | `^11.0.5` |
| `laravel-echo` | `^2.3.4` |
| `pusher-js` | `^8.5.0` |
| `leaflet` | `^1.9.4` |
| `react-leaflet` | `^4.2.1` |
| `axios` | `^1.11.0` |
| `qrcode.react` | `^4.2.0` |

**Não tem:** TypeScript, shadcn/ui, Radix, Chakra, MUI, TanStack Table, react-hook-form, zod/yup, date-fns/dayjs/luxon, i18next, PropTypes.

### 1.2 Estrutura de `app/`

Diretórios de primeiro nível encontrados:

```
app/
  Casts/        Console/    Contracts/  Enums/      Events/
  Exports/      Helpers/    Http/       Imports/    Jobs/
  Listeners/    Models/     Notifications/ Observers/ Providers/
  Rules/        Services/   Traits/
```

**Não existe:** `Actions/`, `Repositories/`, `DTOs/`, `Modules/`.

Organização é **por camada** (Controllers, Services, Models, etc.), **não** por domínio. A única subdivisão por módulo está dentro de `app/Http/Controllers/`:

- `app/Http/Controllers/` — raiz com a maioria (incluindo `BudgetController.php`, `CostCenterController.php`, `AccountingClassController.php`, `ManagementClassController.php`, `SaleController.php`, `MovementController.php`).
- `app/Http/Controllers/Admin/`, `Api/`, `Auth/`, `Central/`, `Config/` — subpastas específicas.
- Controllers dos módulos novos (Budgets, Returns, Reversals, PurchaseOrders) ficam **na raiz de `Controllers/`**, não em subpasta por módulo.

`app/Services/` tem **87+ classes** organizadas por responsabilidade (não por domínio em subpasta). Exemplos do módulo Budgets:

- `BudgetService` — CRUD de uploads, versionamento, persistência de items.
- `BudgetImportService` — parse de planilha → resolve FKs → produz items.
- `BudgetConsumptionService` — roll-up de consumo real × previsto por CC/AC/MC/mês.

### 1.3 Traits em `app/Traits/`

- `Auditable` — registra mudanças de atributos em `activity_logs` (before/after). Usado de forma genérica em todos os models que precisam de auditoria.

Não há trait de soft delete customizado — o projeto usa **soft delete manual** (colunas `deleted_at`, `deleted_by_user_id`, `deleted_reason`) em vários models (Reversal, ReturnOrder, CostCenter, AccountingClass, ManagementClass, BudgetUpload). Isto é uma convenção consolidada — não é o `SoftDeletes` do Eloquent.

### 1.4 Padrão de nomes no DB

- **Tabelas:** plural + snake_case. Ex: `budget_uploads`, `budget_items`, `accounting_classes`, `management_classes`, `cost_centers`.
- **Sem prefixo por módulo** nas tabelas de negócio, exceto:
  - Módulos centrais: `central_*` (central_menus, central_pages, central_roles, central_permissions, central_users).
  - Helpdesk: `hd_*` (hd_tickets, hd_articles, hd_categories, etc.).
  - Chat: `chat_*`.
- **FK:** sempre `{model_singular}_id` (ex: `accounting_class_id`, `cost_center_id`, `budget_upload_id`, `store_id`).
- **Timestamps:** `created_at` / `updated_at` sempre presentes.
- **Soft delete:** 3 colunas quando presente (`deleted_at`, `deleted_by_user_id`, `deleted_reason`) — manual.
- **Audit user:** quando relevante, `created_by_user_id` e `updated_by_user_id` como FK para `users`.

---

## 2. Integrações necessárias

### 2.1 Loja / Unidade / Filial

- **Model:** `app/Models/Store.php`
- **Tabela:** `stores`
- **Identificador externo (CIGAM):** coluna `code` — é string. Exemplos reais: `Z421`–`Z443`, `Z457` (`Z441` = e-commerce). Não se chama `cigam_code`; é simplesmente `code`.
- **Colunas principais:** `code`, `name`, `cnpj`, `company_name`, `state_registration`, `address`, `network_id`, `manager_id`, `supervisor_id`, `store_order`, `network_order`, `status_id`.
- **Relações:** `belongsTo(Network)`, `belongsTo(Manager)` (Employee), `belongsTo(Supervisor)` (Employee), `belongsTo(Status)`, `hasMany(Employee)`.
- **Scopes úteis:** `scopeActive()`, `scopeByNetwork()`, `scopeOrderedByStore()`, `scopeOrderedByNetwork()`.

### 2.2 Rede / Bandeira

- **Model:** `app/Models/Network.php` — existe.
- Hierarquia: `Network 1 : N Store`. `Store.network_id` é FK.
- Não há camada acima de Network (não existe "Marca" como entidade separada no v2).

### 2.3 Vendas / Faturamento / Movimento

- **`app/Models/Sale.php`** — existe. Tabela `sales`.
  - Colunas: `store_id` (int FK), `employee_id` (int FK), `date_sales` (date), `total_sales` (decimal), `qtde_total` (int), `source`, `user_hash`, `created_by_user_id`, `updated_by_user_id`.
  - **Sem `network_id`** — rede é acessada via `store.network`.
  - **Agregado**, não é fonte de verdade — é derivado de `movements` via `refreshSalesSummary()`.
- **`app/Models/Movement.php`** — existe. Fonte de verdade do CIGAM.
  - `movement_code`: 1=Compra, 2=Vendas, 6=Devoluções (entry_exit='E'). Código 17 é documental, nunca aparece em dados reais.
  - Reversals, Returns, PurchaseOrderReceiptItems consomem via FK `movement_id`/`matched_movement_id`.
  - Sync: `movements:sync today` a cada 5 min; `movements:sync auto` diário às 06:00.
- **Confirmação:** a DRE pode operar em tabelas próprias de lançamentos contábeis — compatível com o projeto. A relação com `movements`/`sales` seria **agregativa** (DRE consome/reclassifica, não substitui).

### 2.4 Calendário fiscal

**Não existe** entidade de calendário fiscal (períodos de competência, fechamento mensal, etc.) no projeto. Budgets usa ano de referência (`budget_uploads.year`) + 12 colunas mensais (`month_01_value` … `month_12_value`) — não há conceito de "período aberto/fechado" modelado.

### 2.5 Plano de contas / centros de custo / lançamentos já existentes

**Confirmado:** o v2 **não tem** model de lançamento contábil (`ledger`, `journal`, `accounting_entries`, `accounting_transactions`) — grep por esses termos retorna vazio. A estrutura já existe só até **plano de contas + centros de custo + classe gerencial**, todos como entidades de cadastro, sem tabela de lançamento individual.

Modelos já presentes relevantes para DRE:

| Model | Arquivo | Papel |
|---|---|---|
| `AccountingClass` | `app/Models/AccountingClass.php` | Plano de contas contábil. Hierarquia via `parent_id`. Tem `dre_group` (enum `DreGroup`, 11 valores) e `nature` (enum `AccountingNature` = DEBIT/CREDIT). `accepts_entries` distingue sintética de analítica. |
| `CostCenter` | `app/Models/CostCenter.php` | Centro de custo. Hierarquia via `parent_id`. Tem `default_accounting_class_id` opcional. |
| `ManagementClass` | `app/Models/ManagementClass.php` | Plano gerencial (visão interna). Hierarquia. Vínculo **opcional** a `accounting_class_id` e `cost_center_id`. |
| `BudgetUpload` | implícito nas migrations | Cabeçalho de orçamento: `year`, `scope_label`, `version_label`, `is_active`, `total_year`, `items_count`. Versionamento (1 versão ativa por year+scope). |
| `BudgetItem` | `app/Models/BudgetItem.php` | Linha de orçamento: FKs para (budget_upload, accounting_class, management_class, cost_center) + `store_id` opcional + 12 colunas `month_XX_value` + `year_total` + `supplier`, `justification`, `account_description`, `class_description`. |

Enums já existentes:

- `App\Enums\AccountingNature` — `DEBIT`, `CREDIT` com `label()`, `shortLabel()`, `color()`.
- `App\Enums\DreGroup` — 11 grupos: `RECEITA_BRUTA`, `DEDUCOES`, `CMV`, 4× grupos de despesas, 2× `OUTRAS_*`, 3× financeiras, `IMPOSTOS`. Cada grupo tem `label()`, `dreOrder()`, `naturalNature()`, `color()`, `increasesResult()`.

**Seeds reais:** os seeds de produção já foram aplicados (ver memória `accounting_real_chart.md`) — 80 contas analíticas no formato `X.X.X.XX.XXXXX` (ex: `4.2.1.04.00032` = Telefonia), 24 centros de custo (421–457), 169 classes gerenciais no formato `8.1.DD.UU`.

**`docs/Plano de Contas.xlsx` (86 KB)** — é a fonte primária enviada pelo contador. Ainda não foi parseado neste mapeamento (será feito na próxima etapa, quando decidirmos se o import atualiza `accounting_classes` via upsert por `code`).

---

## 3. Autenticação e autorização

### 3.1 Driver

- **Sessão web (Breeze)** no guard `web` (provider `users`).
- **Guard adicional `central`** (provider `central_users`) para o painel SaaS central em subdomínio próprio.
- **Sanctum** está instalado (`laravel/sanctum ^4.0`), mas o fluxo principal é session-cookie via Breeze + Inertia. Não há API stateless pública relevante.
- **Socialite** presente para Google OAuth.

### 3.2 Sistema de permissões

- **Não** usa Spatie Laravel-Permission.
- **RBAC centralizado, database-driven**:
  - `App\Enums\Role` define os roles: `SUPER_ADMIN`, `ADMIN`, `SUPPORT`, `USER` (+ `DRIVER` em alguns contextos de entrega).
  - `App\Enums\Permission` define **todas** as permission strings (enum com ~100+ valores).
  - `App\Services\CentralRoleResolver` resolve role → permissions lendo de `central_roles` + `central_permissions` (DB central SaaS), com fallback para o enum e cache de 5 min.
  - `App\Http\Middleware\PermissionMiddleware` aceita `permission:PERM1,PERM2,...` e valida se o user tem pelo menos uma.
  - Middleware complementar `tenant.module:{slug}` bloqueia rotas se o plano do tenant não inclui o módulo.

Permissões existentes relevantes para o DRE (todas em `App\Enums\Permission`):

- **Budgets:** `VIEW_BUDGETS`, `UPLOAD_BUDGETS`, `DOWNLOAD_BUDGETS`, `DELETE_BUDGETS`, `MANAGE_BUDGETS`, `EXPORT_BUDGETS`, `VIEW_BUDGET_CONSUMPTION`.
- **CostCenter:** `VIEW_COST_CENTERS`, `CREATE_COST_CENTERS`, `EDIT_COST_CENTERS`, `DELETE_COST_CENTERS`, `MANAGE_COST_CENTERS`, `IMPORT_COST_CENTERS`, `EXPORT_COST_CENTERS`.
- **AccountingClass:** `VIEW_ACCOUNTING_CLASSES`, `CREATE_ACCOUNTING_CLASSES`, `EDIT_ACCOUNTING_CLASSES`, `DELETE_ACCOUNTING_CLASSES`, `MANAGE_ACCOUNTING_CLASSES`, `IMPORT_ACCOUNTING_CLASSES`, `EXPORT_ACCOUNTING_CLASSES`.
- **ManagementClass:** set equivalente.
- **DRE:** **não existem** ainda (`VIEW_DRE`, `MANAGE_DRE`, `EXPORT_DRE`, `MANAGE_DRE_MAPPING`, etc. precisam ser criadas).

### 3.3 Acesso limitado a lojas

- **Não há** pivot `user_store` / `store_user`. O acesso a lojas é indireto via `Employee`:
  - Um user tem `employee_id` (quando aplicável).
  - `Employee` aponta para `store_id` e tem contratos em `employment_contracts.store_id`.
  - `Sale::scopeForStoreWithEcommerce()` usa `employment_contracts.store_id` para filtrar.
- **Hierarquia de perfis:**
  - `SUPER_ADMIN`/`ADMIN` → tudo.
  - `SUPPORT` → algumas áreas, só visualização em vários módulos (Budgets: view + export).
  - `USER` → escopo restringido pela role/contrato (ex: gerente vê as lojas sob supervisão via `employees.supervisor_id`).
- **Não há middleware pronto** para restringir Stores visíveis; filtros são feitos manualmente nas queries por controller.

---

## 4. Frontend (React sem TypeScript)

### 4.1 Layout padrão

- `resources/js/Layouts/AuthenticatedLayout.jsx` — layout principal (sidebar + topnav + flash/toast). Extensão `.jsx`.
- `resources/js/Layouts/GuestLayout.jsx` — páginas públicas/auth.
- Pages ficam em `resources/js/Pages/{Modulo}/{Action}.jsx`. Inertia `Inertia::render('Modulo/Action', [...])` mapeia diretamente.

### 4.2 Biblioteca de componentes

- **Base:** Headless UI (`@headlessui/react ^2.0.0`) + Tailwind 3 + Heroicons.
- **Não** usa shadcn/ui, Radix, Chakra, MUI — tudo é componente próprio ou Headless UI + classes Tailwind.
- Biblioteca própria em `resources/js/Components/` (core) e `resources/js/Components/Shared/` (padronização).

Core (obrigatórios pelo padrão do projeto):

- `Button`, `TextInput`, `InputLabel`, `InputError`, `Checkbox`.
- `DataTable` (tabela padrão com sort/filter/paginate — custom, **não** TanStack).
- `ActionButtons` (view/edit/delete em colunas).
- `StandardModal` + sub-componentes (`.Section`, `.Field`, `.InfoCard`, `.MiniField`, `.Footer`, `.Highlight`, `.Timeline`).
- `ConfirmDialog` (via hook `useConfirm`).
- `ImageUpload`, `EmployeeAvatar`, `UserAvatar`.

Shared: `StatisticsGrid`, `StatusBadge`, `FormSection`, `DeleteConfirmModal`, `EmptyState`, `LoadingSpinner`, `SkeletonCard`.

### 4.3 Padrão de tabela, formulário, datas, gráficos

- **Tabela:** `DataTable.jsx` custom. Props: `data` (paginador Laravel), `columns [{key, label, render?, sortable?}]`, `searchable`, `perPageOptions`, `onRowClick`, `onNavigate`, `selectable`, `selectedIds`, `onSelectionChange`. Não é virtualizada.
- **Formulário:** `useForm` do `@inertiajs/react`. Sem react-hook-form, sem Zod/Yup — validação server-side via `validationRules()` em Controllers; frontend mostra erros de `errors[field]` vindos do Inertia.
- **Datas:** sem lib — apenas `new Date()` + `Intl.DateTimeFormat`. O `DataTable` formata campos automaticamente se a chave terminar com `_at` ou `_date`.
- **Máscaras BR:** hook `useMasks` (em `resources/js/Hooks/useMasks.js`) — `maskMoney`, `maskCpf`, `maskCnpj`, `maskPhone`, `parseMoney`.
- **Gráficos:** `recharts ^3.8.1`. Exemplos em uso: `Pages/Budgets/Dashboard.jsx`, `Pages/OrderPayments/Dashboard.jsx`, `Pages/PurchaseOrders/Dashboard.jsx`, `Pages/Returns/Dashboard.jsx`, `Pages/Reversals/Dashboard.jsx`, `Pages/Central/Dashboard.jsx`, `Pages/Dashboard.jsx`, `Pages/Trainings/Reports.jsx`, `Pages/DeliveryRoutes/DriverDashboard.jsx`.
- **Toasts:** `react-toastify ^11.0.5`. `AuthenticatedLayout` lê `flash` das shared props e dispara toast automaticamente.
- **i18n:** **nenhum**. Strings PT-BR ficam inline no JSX. **Importante:** convenção do projeto é manter acentuação completa (ç, á, ã, etc.) — ver memória `feedback_frontend_accents.md`.

### 4.4 Validação de props nos componentes

- **Sem PropTypes** (pacote não instalado).
- **Sem JSDoc `@param`** sistemático.
- Validação é implícita: o backend define o shape via Inertia::render + Ziggy; frontend confia.

Exemplo observado (amostra de `resources/js/Pages/Budgets/Index.jsx`):

```jsx
import { Head, router } from '@inertiajs/react';
import { BanknotesIcon, CheckCircleIcon, ArchiveBoxIcon } from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';

export default function Index({ budgets, filters, stats }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'detail']);
    // ... sem validação formal de props ...
}
```

### 4.5 Hooks customizados

- Pasta: `resources/js/Hooks/`.
- Naming: camelCase + `use` prefix. Arquivos `.js` (não `.jsx` quando não retorna JSX).
- Lista:
  - `usePermissions.js` — lê `props.auth.permissions` (array de strings fornecido pelo backend via Inertia shared props). Exporta `hasPermission`, `hasAnyPermission`, `hasAllPermissions`, `hasRole`, `hasRoleLevel`, `canEditUser`, `isSuperAdmin`, `isAdmin`, etc., e as **constantes** `PERMISSIONS` e `ROLES`.
  - `useTenant.js` — `hasModule(slug)`, plan, settings.
  - `useModalManager.js` — `openModal`, `closeModal`, `switchModal` para páginas com múltiplos modais.
  - `useConfirm.jsx` — confirmação Promise-based (substitui `window.confirm`).
  - `useMasks.js` — máscaras BR.

### 4.6 Vite / aliases

- `vite.config.js` (21 linhas): plugins `laravel-vite-plugin` + `@vitejs/plugin-react`. Server força IPv4 (`host: '127.0.0.1'`).
- **Aliases não estão no `vite.config.js`** — estão em `jsconfig.json`:

```json
{
    "compilerOptions": {
        "baseUrl": "/",
        "paths": {
            "@/*": ["resources/js/*"],
            "ziggy-js": ["./vendor/tightenco/ziggy"]
        }
    }
}
```

O `@vitejs/plugin-react` + `laravel-vite-plugin` consegue resolver `@/` via esse `jsconfig` no Vite. Imports `@/Components/...`, `@/Hooks/...`, `@/Pages/...` funcionam.

---

## 5. Padrões úteis para espelhar

### 5.1 Dashboard / relatório já implementado

- `resources/js/Pages/Budgets/Dashboard.jsx` — **referência mais próxima do DRE**.
  - 3+ vistas (by_cost_center, by_accounting_class, by_item) com tabs.
  - `recharts` BarChart empilhado para orçado × consumido.
  - Máscaras BR via `useMasks`.
- `resources/js/Pages/Dashboard.jsx` — dashboard geral (cards KPI + gráficos).
- `resources/js/Pages/Reversals/Dashboard.jsx`, `Returns/Dashboard.jsx`, `PurchaseOrders/Dashboard.jsx` — todos com estrutura similar: `StatisticsGrid` no topo + gráficos recharts + tabela de detalhamento.

### 5.2 Filtros de período + lojas

- `resources/js/Pages/Sales/Index.jsx` é a referência mais direta:
  - Filtros: `month` (select 1–12), `store_id` (select), `year`, `network_id`.
  - Passa por **query string** (GET): `router.get(route('sales.index'), { month, store_id, year }, { preserveState: true, preserveScroll: true })`.
  - Backend lê via `$request->query('month')` etc. no `SalesController@index`.
- `MovementController::buildFilteredQuery()` centraliza filtros da listagem + exports — paridade garantida (tanto a grid quanto os exports lêem os mesmos filtros). Esse é o padrão recomendado quando uma tela tem lista + exports; seguir isso no DRE.

### 5.3 Import / Export de planilha

- Biblioteca: `maatwebsite/excel ^3.1`.
- **Imports** (`app/Imports/`): convenção `implements ToCollection, WithHeadingRow`. Método `collection(Collection $rows)` → itera, valida, persiste (upsert). Exemplos: `StoreGoalsImport`, `BudgetImport`, `AccountingClassImport`, `CostCenterImport`, `ManagementClassImport`.
- **Exports** (`app/Exports/`): multi-sheet via `implements WithMultipleSheets` + `sheets(): array`. Cada sheet pode implementar `FromArray`, `WithHeadings`, `ShouldAutoSize`, `WithStyles`. `BudgetExport` é multi-sheet (6 abas).
- Fluxo típico (Budgets): upload do arquivo → `BudgetImportService` faz parse + preview + upsert resolvendo FKs por `code` (não por id).

### 5.4 Matriz pivotada / subtotais

- **Não** há componente genérico de matriz pivotada. A lógica fica em Services que retornam estruturas agregadas já pré-somadas, e o React renderiza.
- `BudgetConsumptionService` agrega orçamento × consumo por dimensão (CC, AC, MC, mês) — é a referência mais próxima do que o DRE precisa. Retorna estrutura tipo `{ rows: [...], totals: {...} }`.

### 5.5 CRUD hierárquico (tree)

- `resources/js/Pages/AccountingClasses/Index.jsx` — exemplo perfeito:
  - 2 view modes: `list` e `tree`.
  - Tree fetch via `axios.get(route('accounting-classes.tree'))` (lado cliente, não via Inertia).
  - Backend: método `tree()` retorna `{ tree: [...hierarchical] }` com `children` aninhados.
- Mesmo padrão em `CostCenters/Index.jsx` e `ManagementClasses/Index.jsx`.
- `children` vêm do Eloquent via recursive eager load (cuidado com fan-out — o número de nós está controlado porque são cadastros).

---

## 6. Ambiente

### 6.1 Banco de dados

- **Primary:** MySQL/MariaDB (`env('DB_CONNECTION', 'sqlite')` — na prática MySQL nos ambientes reais).
- **Connection CIGAM** existe em `config/database.php:101–108`: driver `pgsql`, host/port/database/user/pass via `CIGAM_DB_*` env vars. Portanto, **CIGAM PostgreSQL** está configurado, não é integração futura.
- **Tests:** in-memory SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).
- **Versão MySQL/MariaDB:** **não confirmada aqui**. Se o DRE precisar de CTE recursiva / window functions (típico para roll-up hierárquico), precisamos de MySQL 8.0+ ou MariaDB 10.2.2+. Abrir como dúvida.
- **`stancl/tenancy`** usa migrations em 2 pastas:
  - `database/migrations/` — central (10 migrations hoje).
  - `database/migrations/tenant/` — por tenant (156 migrations hoje).

### 6.2 Cache

- Default: `database` (`env('CACHE_STORE', 'database')`).
- Redis **não está** no `.env.example` nem em config ativo.

### 6.3 Fila

- Default: `database` (`env('QUEUE_CONNECTION', 'database')`).
- Drivers configurados: `sync`, `database`, `beanstalkd`. **Sem Redis.**
- O `composer dev` inicia um `queue:listen --tries=1` automaticamente.

### 6.4 Scheduler (`routes/console.php`)

Schedules atuais relevantes:

- `movements:sync today` — a cada 5 min (log `movements-sync.log`).
- `movements:sync auto` — diariamente 06:00.
- `store-goals:midmonth-alert` — dia 15 às 09:00.
- `experience:notify` — diariamente 08:00.
- `helpdesk:sla-monitor` — a cada 10 min.
- `helpdesk:imap-fetch` — a cada minuto.
- `purchase-orders:cigam-match` — a cada 15 min.
- `purchase-orders:late-alert` — diariamente 09:00.
- `reversals:cigam-push` + `reversals:stale-alert`.
- `returns:stale-alert`.
- Budgets: `budgets:consumption-alert` (conforme memória).

---

## 7. Convenções inferidas mas sem certeza (perguntas objetivas)

1. **Parse do `docs/Plano de Contas.xlsx`:** o arquivo oficial tem 1.129 contas. O seed atual (`accounting_real_chart.md`) tem **80 analíticas + 21 sintéticas**. A intenção é **substituir o seed completo** pelo plano oficial via import na primeira rodada, ou é para **incrementar** preservando o seed atual? A tabela `accounting_classes` deve receber o XLSX inteiro (1.129 linhas) ou apenas as folhas que recebem lançamento?
Substituir o seed atual

2. **Onde vivem os lançamentos:** o plano será calcular DRE a partir de:
   - (a) `BudgetItems` (orçado) × movimentos CIGAM em `movements`/`sales` (realizado)?
   - (b) Criar nova tabela de lançamentos contábeis sintética (ex: `accounting_entries` com `accounting_class_id` + `cost_center_id` + `value` + `date` + `source`) populada via ETL a partir do ERP?
   - (c) Ambos — DRE consome de uma view materializada que une Budgets + Entries?
A tabela movements só contém as movimentações de estoque, não contém as despesas contabeís, mas temos o módulo de ordens de pagamento que possui partes dessas despesas.

3. **Tabela de de-para (contábil → gerencial):** o MVP trata isso como uma tabela **nova** `dre_mappings` (ou nome equivalente) com `accounting_class_id`, `cost_center_id` (nullable), `dre_line_id`, ou reaproveita o link já existente em `management_classes` (`accounting_class_id` + `cost_center_id`)? A diferença é que o de-para da DRE aponta para uma **linha da DRE gerencial** (1..19, com subtotais), não para `ManagementClass`.
Não entendi a pergunta.

4. **Estrutura "linhas da DRE gerencial":** é nova tabela (`dre_lines` com `order`, `label`, `operator` +/-/=, `accumulate_until_order`) ou evolução do `ManagementClass` adicionando flags de subtotal? Pela descrição do prompt, parece uma entidade independente — confirmar.
Sim, vamos usar as melhores prática.

5. **Versionamento da estrutura gerencial:** precisa ser versionada por ano (como Budgets)? Ou é "uma única estrutura vigente, auditada quando muda"?
Vamos usar a estrutura vigente.

6. **Períodos fechados / immutabilidade:** há requisito de congelar a DRE de um período fechado (reclassificações afetam só períodos futuros)? Não vi nada no código. Se sim, precisamos modelar `period_closed_at` por ano/mês.
Sim, vamos criar a possibilidade de congelar os períodos.

7. **CTE e window functions no MySQL de produção:** qual é a versão exata do MySQL/MariaDB usada? Se for < 8.0 / < 10.2.2, a computação de subtotais e hierarquia precisa rodar em PHP (sem CTE).
MySql 8 ou superior

8. **Granularidade do DRE por loja / rede:** a DRE é única para a empresa toda, ou há DRE por loja / por rede / por centro de custo? O enunciado sugere "DRE gerencial executiva" (única, 19 linhas). Mas `BudgetItem` tem `store_id` opcional — isso sugere que o realizado tem granularidade por loja. Confirmar se a **tela** de DRE terá filtro por loja/rede ou é sempre agregado.
Há DRE Geral, Rede e Loja.

9. **Mapeamento CC + AC para linha DRE:** o CC é opcional no mapeamento (`Conta X + qualquer CC → Linha Y`) ou é obrigatoriamente composto? Pelos exemplos (`VENDAS A VISTA` vai pra linha 1 sem CC; `SALARIOS + GENTE E GESTAO` vai pra linha 13 com CC), parece ser condicional. Vamos precisar de regra de priorização: `(AC + CC)` bate antes de `(AC sozinho)`.
Vamos seguir o mapeamento e deixar como opcional, por enquanto.

10. **Contas pendentes de mapeamento:** o prompt fala "quando entra conta nova do ERP, fica pendente de mapeamento". Isso implica uma tela de fila de pendências. É expectativa do MVP ou pode entrar depois?
É expectativa do MVP.

11. **Aliases Vite:** confirmado que estão em `jsconfig.json` via `"paths"`. Mas o Vite em si pode ou não estar resolvendo — o projeto funciona hoje, então está ok. Só sinalizando para evitar tentativa de adicionar no `vite.config.js` sem necessidade.
Ok

---

## 8. Ausências / pontos a resolver antes do próximo prompt

1. **Parse real do `docs/Plano de Contas.xlsx`** — ler as 1.129 linhas, conferir formato de código (`X.X.X.XX.XXXXX`?), tipos S/A, estrutura de pais/filhos. Necessário para decidir o plano de migração (substitui seed atual ou incrementa).
2. **Versão do MySQL em produção** — confirmar se temos CTE recursiva disponível.
3. **Decisão de modelo dos lançamentos** — (a) agregação em cima de entidades já existentes vs (b) nova tabela `accounting_entries` alimentada por ETL. Esta é a decisão estruturante do módulo.
4. **Fonte de dados do realizado** — vai vir de `movements` + `sales` (já no sistema) ou de import manual de balancete do ERP? O enunciado diz "lançamentos apontam para conta analítica + centro de custo (não para linha gerencial)" — isso sugere fonte externa (balancete contábil, não movimentações de estoque).
5. **Definição de "linha da DRE"** — lista das 19 linhas com ordem, operador (+/-/=), fórmula de acumulação. O prompt lista 1, 2, 3, 4, 5, 13, 17, 19 — faltam 6–12, 14–16, 18.
6. **Permissions novas** — lista definitiva antes de começar. Sugestão mínima: `VIEW_DRE`, `MANAGE_DRE_STRUCTURE`, `MANAGE_DRE_MAPPING`, `EXPORT_DRE`, `VIEW_DRE_PENDING_ACCOUNTS`.
7. **Nomenclatura das tabelas novas** — propor e validar antes de migration: `dre_lines`, `dre_mappings`, `accounting_entries` (se aplicável). Seguindo o padrão do projeto: plural, snake_case, sem prefixo.
8. **Se DRE é um módulo separado no `config/modules.php`** — precisa de entrada com `slug`, `name`, `description`, plano e rota pra entrar no sidebar via `CentralMenuResolver`. Ver memória `module_registration_gotchas.md`.

---

## 9. Resumo executivo

**Pronto para reaproveitar:**

- Plano de contas (AccountingClass) + Centros de custo (CostCenter) + Classe gerencial (ManagementClass) já existem como cadastros completos, com hierarquia, enums, seeds reais, CRUD, import/export, tree view.
- Budgets (Fases 0–10) entregou toda a infraestrutura de agregação mensal: `BudgetItem` com 12 colunas mensais, `BudgetConsumptionService` com roll-up por CC/AC/MC, Dashboard com recharts, wizard de upload XLSX.
- Padrões de frontend (DataTable, StandardModal, StatisticsGrid, recharts, máscaras BR, hooks), de backend (Controllers na raiz, Services por domínio, Maatwebsite Excel com multi-sheet, Auditable trait, soft delete manual) e de segurança (CentralRoleResolver + middleware `permission:...`) estão consolidados e devem ser seguidos tal qual.
- CIGAM PostgreSQL já está configurado. Fila e cache estão em `database`, não Redis.

**O que o DRE precisa adicionar (escopo inferido):**

- Fonte de dados de **lançamentos contábeis** (nova tabela ou ETL sobre `movements`) — decisão em aberto.
- Estrutura de **linhas da DRE gerencial** (1..19 com operadores +/-/= e subtotais) — entidade nova.
- **De-para** conta contábil + centro de custo → linha DRE — entidade nova (separada de `ManagementClass`).
- **Tela de pendências** (contas novas sem classificação) — fluxo novo.
- **Relatório DRE** em React (tabela hierárquica 19 linhas × meses × ano, com filtros período/loja/rede, export XLSX/PDF).
- **Import** do plano oficial (`docs/Plano de Contas.xlsx`) — reconciliação com `accounting_classes`.
- Novas permissions + entrada no `config/modules.php` + item de sidebar no `CentralMenuResolver`.

Nenhuma linha de produção foi escrita neste passo.
