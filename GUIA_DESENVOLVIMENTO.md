# GUIA DE DESENVOLVIMENTO - MERCURY LARAVEL

**Data de Atualização:** Abril 2026
**Versão do Documento:** 2.0
**Projeto:** Mercury Laravel - Sistema de Gestão Empresarial (Multi-Tenant SaaS)

---

## SUMÁRIO

1. [Visão Geral do Projeto](#1-visão-geral-do-projeto)
2. [Estado Atual de Desenvolvimento](#2-estado-atual-de-desenvolvimento)
3. [Módulos Implementados](#3-módulos-implementados)
4. [Módulos Pendentes de Implementação](#4-módulos-pendentes-de-implementação)
5. [Placeholders (Coming Soon)](#5-placeholders-coming-soon)
6. [Débitos Técnicos](#6-débitos-técnicos)
7. [Padrões e Convenções](#8-padrões-e-convenções)
8. [Referência Técnica](#9-referência-técnica)

---

## 1. VISÃO GERAL DO PROJETO

### 1.1 Descrição
O **Mercury Laravel** é um sistema de gestão empresarial multi-tenant (SaaS) desenvolvido com Laravel 12 e React 18, utilizando Inertia.js 2 para comunicação frontend-backend. O projeto é uma reescrita completa do sistema legado PHP (Mercury v1) do Grupo Meia Sola.

### 1.2 Stack Tecnológica

| Camada | Tecnologia | Versão |
|--------|------------|--------|
| Backend | Laravel | 12.0 |
| Frontend | React | 18.2 |
| Bridge | Inertia.js | 2.0 |
| CSS | Tailwind CSS | 3.x |
| Icons | Heroicons React | @heroicons/react |
| Build Tool | Vite | 7.0 |
| Database Principal | MySQL/MariaDB | - |
| Database ERP | PostgreSQL (CIGAM) | Opcional |
| Multi-tenancy | stancl/tenancy | - |
| Auth | Laravel Sanctum | 4.0 |
| PDF | barryvdh/laravel-dompdf | - |
| Excel | maatwebsite/excel | - |
| Imagens | intervention/image | - |

### 1.3 Arquitetura
```
┌──────────────────────────────────────────────────────────────┐
│                         FRONTEND                              │
│  React 18 + Tailwind CSS + Heroicons + react-toastify        │
└───────────────────────────┬──────────────────────────────────┘
                            │ Inertia.js 2
┌───────────────────────────▼──────────────────────────────────┐
│                         BACKEND                               │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────┐  │
│  │ Controllers  │──│  Services    │──│     Models         │  │
│  │ (56 classes) │  │ (18 classes) │  │   (81 classes)     │  │
│  └──────────────┘  └──────────────┘  └────────────────────┘  │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────┐  │
│  │ Middleware   │  │   Traits     │  │  Enums (Role,      │  │
│  │ (12 classes) │  │  (Auditable) │  │  Permission)       │  │
│  └──────────────┘  └──────────────┘  └────────────────────┘  │
└───────────────────────────┬──────────────────────────────────┘
                            │
┌───────────────────────────▼──────────────────────────────────┐
│                        DATABASE                               │
│         MySQL (88 migrations) + PostgreSQL (CIGAM)           │
│         Central (10 migrations) + Tenant (78 migrations)     │
└──────────────────────────────────────────────────────────────┘
```

### 1.4 Arquitetura Multi-Tenant
O sistema opera em dois contextos:
- **Central:** Gerenciamento de tenants, planos e billing (`web.php`)
- **Tenant:** Aplicação completa por empresa (`tenant-routes.php`)

---

## 2. ESTADO ATUAL DE DESENVOLVIMENTO

### 2.1 Estatísticas do Projeto

| Métrica | Quantidade |
|---------|------------|
| Controllers | 56 |
| Models | 81 |
| Services | 18 |
| Migrations | 88 (10 central + 78 tenant) |
| Middleware | 12 |
| Enums | 2 (Role + Permission com 60+ permissions) |
| Componentes React | 73 |
| Páginas React | 54 |
| Layouts React | 3 |
| Hooks React | 4 |
| Testes | 51 arquivos |
| Total arquivos PHP | 202 |

### 2.2 Progresso Geral

| Status | Quantidade |
|--------|------------|
| Módulos totalmente implementados | 48 (27 módulos + 21 config) |
| Módulos parcialmente implementados | 7 |
| Módulos pendentes | ~40 |
| **Progresso estimado** | **~40% do Blueprint v1** |

---

## 3. MÓDULOS IMPLEMENTADOS

### 3.1 Core e Autenticação (✅ Completo)

| Módulo | Controller | Status |
|--------|-----------|--------|
| Autenticação | Auth Controllers | Login, Registro, Reset Senha, Verificação Email |
| Dashboard | DashboardController + DashboardService | Estatísticas, gráficos |
| Perfil | ProfileController | Edição de perfil, avatar |
| LGPD | LgpdController | Termos, privacidade, export, exclusão |

### 3.2 Administração (✅ Completo)

| Módulo | Controller | Funcionalidades |
|--------|-----------|-----------------|
| Usuários | UserManagementController | CRUD, roles, avatares |
| Menus | MenuController | CRUD, reorder, activate/deactivate |
| Páginas | PageController | CRUD, visibilidade, sync permissions |
| Grupos de Páginas | PageGroupController | CRUD |
| Níveis de Acesso | AccessLevelController + AccessLevelPageController | Permissões por página/menu |
| Logs de Atividade | ActivityLogController | Rastreamento, exportação, cleanup |
| Temas de Cor | ColorThemeController | CRUD |
| Config Email | EmailSettingsController | Gerenciamento SMTP |
| Sessões Online | UserSessionController | Monitoramento, heartbeat |

### 3.3 RH e Pessoal (✅ Completo)

| Módulo | Controller | Funcionalidades |
|--------|-----------|-----------------|
| Funcionários | EmployeeController | CRUD, contratos, eventos, histórico, relatório, export |
| Jornadas | WorkShiftController | CRUD, export, impressão |
| Escalas | WorkScheduleController | 12 métodos, 4 models, assign/unassign, overrides |
| Atestados Médicos | MedicalCertificateController | CRUD |
| Faltas | AbsenceController | CRUD |
| Horas Extras | OvertimeRecordController | CRUD |

### 3.4 Vendas e Financeiro (✅ Completo)

| Módulo | Controller | Funcionalidades |
|--------|-----------|-----------------|
| Vendas | SaleController | 12 métodos, sync CIGAM, estatísticas, bulk delete |
| Ordens de Pagamento | OrderPaymentController | Kanban, transitions, allocations, bulk, installments |
| Fornecedores | SupplierController | CRUD completo |

### 3.5 Estoque (✅ Completo)

| Módulo | Controller | Funcionalidades |
|--------|-----------|-----------------|
| Transferências | TransferController | CRUD, confirm pickup/delivery/receipt, cancel |
| Ajustes de Estoque | StockAdjustmentController | CRUD, state transitions |

### 3.6 Produtos (✅ Completo)

| Módulo | Controller | Funcionalidades |
|--------|-----------|-----------------|
| Produtos | ProductController | 15 métodos, sync CIGAM chunked, EAN-13 |
| Lojas | StoreController | CRUD, reorder, activate/deactivate |

### 3.7 Qualidade (✅ Completo)

| Módulo | Controller | Funcionalidades |
|--------|-----------|-----------------|
| Checklists | ChecklistController | CRUD, answers, statistics, employees |

### 3.8 Integrações (✅ Completo)

| Módulo | Controller | Funcionalidades |
|--------|-----------|-----------------|
| Integrações | IntegrationController | CRUD, test connection, trigger sync, logs |
| API | IntegrationApiController + WebhookController | API externa + webhooks |

### 3.9 Config Modules (21 módulos ✅)

Todos seguem o padrão `ConfigController` base com CRUD genérico:

| Módulo | Rota |
|--------|------|
| Position | `/positions` |
| PositionLevel | `/position-levels` |
| Sector | `/sectors` |
| Gender | `/genders` |
| EducationLevel | `/education-levels` |
| EmployeeStatus | `/employee-statuses` |
| EmployeeEventType | `/employee-event-types` |
| TypeMoviment | `/type-moviments` |
| EmploymentRelationship | `/employment-relationships` |
| Manager | `/managers` |
| Network | `/networks` |
| Status | `/statuses` |
| PageStatus | `/page-statuses` |
| Bank | `/banks` |
| CostCenter | `/cost-centers` |
| Driver | `/drivers` |
| PaymentType | `/payment-types` |
| OrderPaymentStatus | `/order-payment-statuses` |
| StockAdjustmentStatus | `/stock-adjustment-statuses` |
| TransferStatus | `/transfer-statuses` |
| ManagementReason | `/management-reasons` |

### 3.10 Multi-Tenant SaaS (✅ Completo)

| Componente | Implementação |
|-----------|---------------|
| Tenant Management | Central/TenantController |
| Plans | Central/PlanController |
| Domain Routing | stancl/tenancy |
| Tenant Provisioning | TenantProvisioningService |
| Plan Limits | PlanLimitService |
| Middleware | CheckTenantActive, CheckTenantModule, CheckPlanLimit |

---

## 4. MÓDULOS PENDENTES DE IMPLEMENTAÇÃO

Referência completa: `docs/BLUEPRINT_LARAVEL_V2.md` (Seção 15.3)

### 4.1 Alta Prioridade — Financeiro

| Módulo | Complexidade | Descrição |
|--------|-------------|-----------|
| OrderControl | Alta | Pedidos de compra (state machine + items) |
| Reversals | Média | Estornos financeiros |
| Returns | Média | Devoluções |
| StoreGoals | Média | Metas de loja e consultores |
| Coupons | Média | Cupons de desconto |
| TravelExpenses | Média | Despesas de viagem |
| Budgets | Média | Orçamentos |

### 4.2 Alta Prioridade — RH Avançado

| Módulo | Complexidade | Descrição |
|--------|-------------|-----------|
| Vacations | Muito Alta | Férias CLT (state machine, 11 regras CLT) |
| VacationPeriods | Alta | Períodos aquisitivos |
| Holidays | Baixa | Feriados (config) |
| PersonnelMoviments | Alta | Movimentações de pessoal (state machine) |
| VacancyOpening | Alta | Vagas abertas + recrutamento |
| PersonnelRequests | Alta | Requisições de pessoal (WhatsApp DP) |
| ExperienceTracker | Média | Avaliações de experiência |

### 4.3 Média Prioridade — Estoque Avançado

| Módulo | Complexidade | Descrição |
|--------|-------------|-----------|
| StockAudit | Muito Alta | Auditoria de estoque 6 fases (15+ tabelas) |
| Consignments | Alta | Consignações de produtos |
| DamagedProducts | Média | Produtos avariados + matching |
| Relocation | Média | Remanejos entre lojas |
| FixedAssets | Média | Controle de ativos fixos |
| StockMovements | Média | Movimentações + alertas |
| ProductPromotions | Média | Promoções de produtos |

### 4.4 Média Prioridade — Comunicação

| Módulo | Complexidade | Descrição |
|--------|-------------|-----------|
| Chat | Muito Alta | Chat real-time (WebSocket via Reverb) |
| Helpdesk | Alta | Tickets + SLA |
| SystemNotifications | Média | Notificações do sistema |

### 4.5 Baixa Prioridade — Especializados

| Módulo | Complexidade | Descrição |
|--------|-------------|-----------|
| Training | Alta | Treinamentos + certificados |
| Delivery | Alta | Entregas + roteamento |
| TurnList (LDV) | Alta | Lista da vez (fila de atendimento) |
| ServiceOrder | Alta | Ordens de serviço (qualidade) |
| Ecommerce | Média | Pedidos e-commerce |
| ProcessLibrary | Média | Biblioteca de processos |
| Policies | Baixa | Políticas da empresa |
| MaterialRequest | Média | Requisições de material marketing |
| Brand | Média | Gestão de marcas (CRUD dedicado) |

### 4.6 Infraestrutura Pendente

| Item | Descrição |
|------|-----------|
| WebSocket/Reverb | Real-time para chat, notificações, monitoramento |
| Laravel Queue | Background jobs para syncs, imports, emails |
| Laravel Notifications | Email + DB + Broadcast unificado |
| Record Locks | Lock pessimista de registros |
| Page Visit Tracking | Analytics de páginas |
| Reports/Exports | Relatórios PDF e exports Excel generalizados |
| API REST completa | Endpoints Sanctum para Sales, Employees, etc. |

---

## 5. PLACEHOLDERS (COMING SOON)

Rotas que exibem página "Coming Soon":

| Rota | Módulo |
|------|--------|
| `/planejamento` | Planejamento |
| `/financeiro` | Financeiro |
| `/ativo-fixo` | Ativo Fixo |
| `/delivery` | Delivery |
| `/rotas` | Rotas |
| `/ecommerce` | E-commerce |
| `/pessoas-cultura` | Pessoas & Cultura |
| `/departamento-pessoal` | Departamento Pessoal |
| `/escola-digital` | Escola Digital |
| `/movidesk` | Movidesk |
| `/biblioteca-processos` | Biblioteca de Processos |

---

## 6. DÉBITOS TÉCNICOS

### 6.1 Médios

#### DT-001: Validações Inline em Controllers
**Problema:** Alguns controllers usam `$request->validate()` inline em vez de Form Requests.
**Solução:** Criar FormRequest classes dedicadas para controllers com validação complexa.

#### DT-002: Cobertura de Testes
**Situação Atual:** 51 arquivos de teste para 202 classes PHP.
**Meta:** Aumentar cobertura, especialmente nos módulos novos (OrderPayments, Transfers, StockAdjustments).

#### DT-003: TypeScript no Frontend
**Situação:** React sem tipagem.
**Benefício:** Maior segurança de tipos nos componentes.

### 6.2 Baixos (Backlog)

#### DT-004: Testes E2E
**Situação:** Inexistentes.
**Ferramenta sugerida:** Laravel Dusk ou Cypress.

#### DT-005: CI/CD Pipeline
**Situação:** Sem pipeline automatizado.
**Recomendação:** GitHub Actions para testes + lint + build.

---

## 7. PADRÕES E CONVENÇÕES

### 7.1 Nomenclatura

```
Controllers:   PascalCase + Controller   → EmployeeController
Models:        PascalCase (singular)     → Employee
Migrations:    snake_case + timestamp    → 2026_01_04_create_employees_table
Tables:        snake_case (plural)       → employees
Columns:       snake_case                → created_at
Routes:        kebab-case                → /employees/{employee}/contracts
React Pages:   PascalCase/               → Pages/Employees/Index.jsx
React Comps:   PascalCase.jsx            → Components/DataTable.jsx
Enums:         PascalCase                → Permission.php, Role.php
Config Ctrl:   Config/{Name}Controller   → Config/BankController.php
```

### 7.2 Estrutura de Diretórios

```
app/
├── Enums/               # Role.php, Permission.php
├── Http/
│   ├── Controllers/
│   │   ├── Admin/       # EmailSettingsController
│   │   ├── Api/         # IntegrationApiController, WebhookController
│   │   ├── Auth/        # LoginController, etc.
│   │   ├── Central/     # TenantController, PlanController, DashboardController
│   │   ├── Config/      # 21 config controllers (extend ConfigController)
│   │   └── *.php        # Controllers de módulo (EmployeeController, SaleController, etc.)
│   ├── Middleware/       # 12 middlewares (Permission, Role, LGPD, Tenant, etc.)
│   └── Requests/        # Form Requests (quando usado)
├── Models/              # 81 Eloquent Models
├── Services/            # 18 service classes
│   └── Integrations/    # Services de integração externa
└── Traits/              # Auditable, etc.

resources/js/
├── app.jsx              # Bootstrap Inertia, CSRF, session expiry
├── Components/          # 73 componentes reutilizáveis
│   ├── DataTable.jsx    # Tabela com sort/filter/paginate
│   ├── Sidebar.jsx      # Navegação com permissões
│   ├── Shared/          # Modal, Button, Input, Checkbox, etc.
│   └── Modals/          # GenericFormModal, GenericDetailModal, etc.
├── Hooks/               # 4 hooks (usePermissions, useConfirm, etc.)
├── Layouts/             # 3 layouts (Authenticated, Guest, Central)
└── Pages/               # 54 páginas por módulo
    ├── Config/Index.jsx # Página genérica para 21 config modules
    ├── Sales/           # Index + 6 componentes
    ├── Products/        # Index + 5 componentes
    ├── Employees/       # Index + modais
    └── ...

database/migrations/
├── *.php                # 10 migrations centrais (tenants, plans, domains)
└── tenant/              # 78 migrations tenant (users, employees, sales, etc.)

routes/
├── web.php              # Rotas centrais (tenant management)
├── tenant-routes.php    # Rotas tenant (aplicação completa)
├── api.php              # API routes
├── auth.php             # Auth routes
└── tenant.php           # Tenant resolution
```

### 7.3 Padrão de Controller

```php
class ExampleController extends Controller
{
    public function index(Request $request): Response
    {
        $items = Example::query()
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->paginate(15);

        return Inertia::render('Examples/Index', [
            'items' => $items,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([...]);
        Example::create($validated);

        return redirect()->route('examples.index')
            ->with('success', 'Item criado com sucesso.');
    }
}
```

### 7.4 Padrão Config Module

Para módulos CRUD simples (lookup tables), usar o padrão ConfigController:

```php
// app/Http/Controllers/Config/ExampleController.php
class ExampleController extends ConfigController
{
    protected function modelClass(): string { return Example::class; }
    protected function viewTitle(): string { return 'Exemplos'; }
    protected function columns(): array { return [...]; }
    protected function formFields(): array { return [...]; }
    protected function validationRules(): array { return [...]; }
}
```

Renderiza automaticamente em `Pages/Config/Index.jsx`.

### 7.5 Padrão de Permissões

```php
// Backend: middleware em rotas
Route::get('/examples', [ExampleController::class, 'index'])
    ->middleware('permission:VIEW_EXAMPLES');

// Frontend: hook usePermissions
const { hasPermission, isAdmin } = usePermissions();
if (hasPermission('VIEW_EXAMPLES')) { ... }
```

---

## 8. REFERÊNCIA TÉCNICA

### 8.1 Comandos de Desenvolvimento

```bash
# Desenvolvimento
composer dev                    # Inicia server + queue + logs + vite
php artisan serve               # Apenas servidor Laravel
npm run dev                     # Apenas Vite dev server
npm run build                   # Build de produção

# Banco de Dados
php artisan migrate             # Executar migrations
php artisan migrate:fresh       # Reset + migrate
php artisan db:seed             # Executar seeders

# Testes (usar PHP com pdo_sqlite)
C:\Users\MSDEV\php84\php.exe artisan test
C:\Users\MSDEV\php84\php.exe artisan test --filter=SaleControllerTest

# Linting
vendor/bin/pint                 # Laravel Pint (code style)

# Cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache

# Geração de Código
php artisan make:model Example -mfsc
php artisan make:request StoreExampleRequest
php artisan make:test ExampleTest
```

### 8.2 PHP Environment

Duas instalações PHP existem na máquina:

| Instalação | Path | Uso |
|-----------|------|-----|
| Herd Lite | `C:\Users\MSDEV\.config\herd-lite\bin\php.exe` | Dev geral (sem pgsql/sqlite) |
| Full PHP 8.4 | `C:\Users\MSDEV\php84\php.exe` | Testes + CIGAM sync |

Para testes e funcionalidades CIGAM, sempre prefixar com `C:\Users\MSDEV\php84\php.exe`.

### 8.3 Variáveis de Ambiente

```env
# App
APP_NAME=Mercury
APP_ENV=local|production
APP_URL=http://localhost:8000

# Database Principal
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=mercury
DB_USERNAME=root
DB_PASSWORD=

# Database CIGAM (opcional)
CIGAM_DB_HOST=
CIGAM_DB_PORT=5432
CIGAM_DB_DATABASE=
CIGAM_DB_USERNAME=
CIGAM_DB_PASSWORD=

# Filesystem
FILESYSTEM_DISK=public
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

### 8.4 Permissões (60+ cases em Permission.php)

```php
// Gestão de Usuários
VIEW_USERS, CREATE_USERS, EDIT_USERS, DELETE_USERS, MANAGE_USER_ROLES

// Perfil
VIEW_OWN_PROFILE, EDIT_OWN_PROFILE, VIEW_ANY_PROFILE, EDIT_ANY_PROFILE

// Sistema
ACCESS_DASHBOARD, ACCESS_ADMIN_PANEL, ACCESS_SUPPORT_PANEL
MANAGE_SETTINGS, VIEW_LOGS, MANAGE_SYSTEM
VIEW_ACTIVITY_LOGS, EXPORT_ACTIVITY_LOGS, MANAGE_SYSTEM_SETTINGS

// Vendas
VIEW_SALES, CREATE_SALES, EDIT_SALES, DELETE_SALES

// Produtos
VIEW_PRODUCTS, EDIT_PRODUCTS, SYNC_PRODUCTS

// Sessões
VIEW_USER_SESSIONS, MANAGE_USER_SESSIONS

// Transferências
VIEW_TRANSFERS, CREATE_TRANSFERS, EDIT_TRANSFERS, DELETE_TRANSFERS

// Ajustes de Estoque
VIEW_ADJUSTMENTS, CREATE_ADJUSTMENTS, EDIT_ADJUSTMENTS, DELETE_ADJUSTMENTS

// Ordens de Pagamento
VIEW_ORDER_PAYMENTS, CREATE_ORDER_PAYMENTS, EDIT_ORDER_PAYMENTS, DELETE_ORDER_PAYMENTS

// Fornecedores
VIEW_SUPPLIERS, CREATE_SUPPLIERS, EDIT_SUPPLIERS, DELETE_SUPPLIERS

// Checklists
VIEW_CHECKLISTS, CREATE_CHECKLISTS, EDIT_CHECKLISTS, DELETE_CHECKLISTS

// Atestados Médicos
VIEW_MEDICAL_CERTIFICATES, CREATE_MEDICAL_CERTIFICATES, EDIT_MEDICAL_CERTIFICATES, DELETE_MEDICAL_CERTIFICATES

// Faltas
VIEW_ABSENCES, CREATE_ABSENCES, EDIT_ABSENCES, DELETE_ABSENCES

// Horas Extras
VIEW_OVERTIME, CREATE_OVERTIME, EDIT_OVERTIME, DELETE_OVERTIME
```

### 8.5 Roles e Hierarquia

```php
Role::SUPER_ADMIN  // Level 4 - Todas as permissões
Role::ADMIN        // Level 3 - Gerenciamento sem controle total
Role::SUPPORT      // Level 2 - Suporte com visualização ampla
Role::USER         // Level 1 - Apenas próprio perfil
```

### 8.6 Documentação de Referência

| Documento | Localização | Descrição |
|-----------|------------|-----------|
| Blueprint Completo | `docs/BLUEPRINT_LARAVEL_V2.md` | Documento master de referência (v1 → v2) |
| Arquitetura | `docs/ARCHITECTURE.md` | Arquitetura do sistema |
| Padrões de Código | `docs/PADRONIZACAO.md` | Templates e padrões |
| Guia de Implementação | `docs/GUIA_IMPLEMENTACAO_MODULOS.md` | Passo-a-passo para novos módulos |
| Estratégia de Testes | `docs/TESTING_STRATEGY.md` | Abordagem de testes |
| Deploy | `docs/DEPLOYMENT.md` | Guia de deploy |
| Contributing | `docs/CONTRIBUTING.md` | Guia de contribuição |
| CLAUDE.md | `CLAUDE.md` | Instruções para Claude Code |

---

*Documento atualizado em Abril 2026 — Mercury Laravel v2.0*
*Referência: BLUEPRINT_LARAVEL_V2.md para mapeamento completo v1 → v2*
