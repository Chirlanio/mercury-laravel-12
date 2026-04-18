# Mercury Laravel

Sistema de gestão empresarial multi-tenant (SaaS) desenvolvido com **Laravel 12 + React 18 + Inertia.js 2**. Reescrita completa do sistema legado Mercury v1 para o Grupo Meia Sola.

## Stack

| Camada | Tecnologia |
|--------|------------|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | React 18, Tailwind CSS 3, Heroicons |
| Bridge | Inertia.js 2 |
| Build | Vite 7 |
| Multi-tenancy | stancl/tenancy (subdomínios) |
| Database | MySQL/MariaDB + PostgreSQL (CIGAM, opcional) |
| PDF | barryvdh/laravel-dompdf |
| Excel | maatwebsite/excel |

## Instalacao

```bash
# 1. Clonar e instalar dependencias
git clone https://github.com/your-username/mercury-laravel.git
cd mercury-laravel
composer install
npm install

# 2. Configurar ambiente
cp .env.example .env
php artisan key:generate
# Configurar DB_* no .env

# 3. Banco de dados (central)
php artisan migrate --seed

# 4. Build frontend
npm run build
```

## Desenvolvimento

```bash
composer dev          # Inicia server + queue + logs + vite
php artisan serve     # Apenas servidor Laravel
npm run dev           # Apenas Vite dev server
npm run build         # Build de producao
vendor/bin/pint       # Linting (code style)
```

### Testes

Testes usam SQLite in-memory. Na maquina de dev, usar o PHP com pdo_sqlite:

```bash
C:\Users\MSDEV\php84\php.exe artisan test
C:\Users\MSDEV\php84\php.exe artisan test --filter=SaleControllerTest
```

## Acessando o Sistema

O Mercury opera em dois contextos: **Central** (admin SaaS) e **Tenant** (aplicacao por empresa).

### Painel Central (Admin SaaS)

Gerenciamento de tenants, planos e billing.

| Item | Valor |
|------|-------|
| URL | `http://127.0.0.1:8000` |
| Login | `http://127.0.0.1:8000/login` |
| Dashboard | `http://127.0.0.1:8000/admin` |

**Credenciais (seed):**

| Email | Senha | Role |
|-------|-------|------|
| `admin@mercury.com.br` | `password` | super_admin |

### Tenants (Aplicacao por Empresa)

Cada tenant e acessado via subdominio. Os tenants sao criados pelo painel central.

**Tenant Meia Sola:**

| Item | Valor |
|------|-------|
| URL | `http://meia-sola.localhost:8000` |
| Login | `http://meia-sola.localhost:8000/login` |

| Email | Senha | Role |
|-------|-------|------|
| `superadmin@mercury.com` | `password` | super_admin |
| `admin@mercury.com` | `password` | admin |
| `support@mercury.com` | `password` | support |
| `user@mercury.com` | `password` | user |

**Tenant InMyStock:**

| Item | Valor |
|------|-------|
| URL | `http://inmystock.localhost:8000` |
| Login | `http://inmystock.localhost:8000/login` |

### Criando um Novo Tenant

1. Acesse o painel central (`http://127.0.0.1:8000/admin`)
2. Va em **Tenants** e clique em **Criar Tenant**
3. Defina o slug (ex: `minha-empresa`) — o subdominio sera `minha-empresa.localhost:8000`
4. O banco de dados do tenant e criado automaticamente com dados de referencia (seeders)
5. Crie o primeiro usuario do tenant pelo painel central

### Importante: Central vs Tenant

O sistema usa **guards de autenticacao separados**:

- **Central** (`/login` em `127.0.0.1`): tabela `central_users`, guard `central`
- **Tenant** (`/login` em `*.localhost`): tabela `users` (por tenant), guard `web`

Os usuarios sao completamente independentes. Um usuario do tenant nao consegue acessar o painel central e vice-versa.

## Arquitetura

```
app/
├── Http/Controllers/
│   ├── Central/         # 9 admin SaaS controllers (tenants, plans)
│   ├── Config/          # 39 config modules (extend ConfigController)
│   ├── Admin/           # Email settings
│   ├── Api/             # Integration API + Webhooks
│   └── *.php            # 61 module controllers
├── Models/              # 198 Eloquent models
├── Services/            # 77 service classes
├── Enums/               # Role.php, Permission.php (70+ permissions)
└── Http/Middleware/      # Tenant, auth, RBAC, permission, etc.

resources/js/
├── Pages/               # 45 page directories (~100 JSX pages)
│   ├── Central/         # Admin SaaS pages
│   ├── Config/Index.jsx # Generic page for 39 config modules
│   └── ...              # Module pages (PurchaseOrders, Reversals, Vacancies, etc.)
├── Components/          # 68 reusable components + Shared/
├── Layouts/             # 3 layouts (Authenticated, Guest, Central)
└── Hooks/               # usePermissions, useConfirm, useModalManager, useMasks, useTenant

database/
├── migrations/          # 10 central + 227 tenant migrations
└── seeders/             # TenantPlanSeeder, CentralNavigationSeeder, etc.

routes/
├── web.php              # Central routes (tenant management)
├── tenant-routes.php    # Tenant routes (full application)
├── tenant.php           # Tenant middleware + resolution
├── api.php              # API routes
├── auth.php             # Auth routes
└── console.php          # Scheduled commands (cigam-sync, reversals push, late alerts, etc.)
```

## Modulos Implementados

### Principais (37)
Auth · Users · Employees · Stores · Sales · Products · WorkShifts · WorkSchedules ·
Transfers · StockAdjustments · OrderPayments · Suppliers · **PurchaseOrders** ·
**Reversals** · Checklists · MedicalCertificates · Absences · OvertimeRecords ·
StoreGoals · Movements · Vacations · StockAudits · PersonnelMovements ·
**Vacancies** · Training (LMS completo) · ExperienceTracker · Deliveries ·
DeliveryRoutes · Chat · Helpdesk · TaneIA · ActivityLogs · EmailSettings ·
Profile · Dashboard · LGPD · UserSessions · Integrations

### Config (39)
Position, PositionLevel, Sector, Gender, EducationLevel, EmployeeStatus,
EmployeeEventType, TypeMoviment, EmploymentRelationship, Manager, Network,
Status, PageStatus, Bank, CostCenter, Driver, PaymentType, OrderPaymentStatus,
StockAdjustmentStatus, StockAdjustmentReason, TransferStatus, ManagementReason,
ProductBrand, ProductCategory, ProductCollection, ProductSubcollection,
ProductColor, ProductMaterial, ProductSize, ProductArticleComplement,
ProductLookupConfig, ProductLookupGroup, StockAuditCycle, StockAuditVendor,
DismissalReason, DeliveryReturnReason, PercentageAward, **ReversalReason**

### Destaques recentes

- **Reversals** (abr/2026) — Estornos de vendas com state machine 6 estados,
  lookup direto em `movements`, dedup via service, hook Helpdesk fail-safe,
  dashboard recharts, import XLSX + export PDF. 62 tests / 184 assertions.
- **PurchaseOrders** (abr/2026) — Ordens de compra de coleção com size matrix,
  matcher CIGAM, auto-geração de parcelas em order_payments, EAN-13 interno.
  81 tests / 293 assertions.
- **Vacancies + PersonnelRequests** (abr/2026) — Abertura de vagas com SLA
  automático por nível de cargo + integração bidirecional com PersonnelMovement
  via events. PersonnelRequests acopla ao Helpdesk (não é módulo separado).
- **Helpdesk** (abr/2026) — Sistema de chamados com SLA business-hours,
  WhatsApp inbound/outbound (Evolution API), AI classifier (Groq), KB,
  CSAT, email intake (IMAP + Postmark).

## RBAC

4 roles hierarquicos com 70+ permissions:

| Role | Level | Descricao |
|------|-------|-----------|
| SUPER_ADMIN | 4 | Acesso total |
| ADMIN | 3 | Gerenciamento sem controle total |
| SUPPORT | 2 | Suporte com visualizacao ampla |
| USER | 1 | Apenas proprio perfil |

## Documentacao

| Documento | Descricao |
|-----------|-----------|
| `CLAUDE.md` | Instrucoes para Claude Code |
| `GUIA_DESENVOLVIMENTO.md` | Guia completo de desenvolvimento |
| `docs/BLUEPRINT_LARAVEL_V2.md` | Blueprint de migracao v1 → v2 |
| `docs/ARCHITECTURE.md` | Arquitetura do sistema |
| `docs/GUIA_IMPLEMENTACAO_MODULOS.md` | Passo-a-passo para novos modulos |
| `docs/PADRONIZACAO.md` | Padroes de codigo |
| `docs/TESTING_STRATEGY.md` | Estrategia de testes |
| `docs/DEPLOYMENT.md` | Guia de deploy |
| `docs/CONTRIBUTING.md` | Guia de contribuicao |
| `docs/ANALISE_MODULO_REVERSALS.md` | Modulo de Estornos (v2) — 6 fases, 62 testes |

## Deploy (VPS)

```bash
# Instalar dependencias
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Configurar ambiente
cp .env.example .env
php artisan key:generate
# Editar .env com credenciais de producao

# Banco de dados
php artisan migrate --seed

# Permissoes
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Scheduler (cron)
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Licenca

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
