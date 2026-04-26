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

## Fine-Tuning (TaneIA)

A TaneIA armazena conversas tenant-scoped e permite exportar o dataset de treino em formato JSONL (compatível com Llama 3.1 / Unsloth / OpenAI conversations). O rating dado pelos usuários (+1 / -1) alimenta o filtro de qualidade — apenas conversas com pelo menos um `assistant` aprovado e nenhuma reprovação entram por padrão.

### Comando principal

```bash
# Export default (apenas conversas aprovadas, rating=1 sem nenhum -1)
php artisan taneia:export-training --tenant=meia-sola

# Export completo, sem filtro de rating (debug / baseline)
php artisan taneia:export-training --tenant=meia-sola --all

# Limitar quantidade e exigir minimo de turnos (pares user/assistant)
php artisan taneia:export-training --tenant=meia-sola --limit=500 --min-turns=2

# Saida customizada
php artisan taneia:export-training --tenant=meia-sola --out=taneia-training/custom.jsonl
```

**Opcoes:**

| Flag | Descricao |
|------|-----------|
| `--tenant=ID` | ID do tenant (obrigatorio no contexto central; dispensavel em `tenants:run`) |
| `--all` | Ignora o filtro de rating e exporta todas as conversas |
| `--min-turns=N` | Numero minimo de pares user/assistant por conversa (default: `1`) |
| `--limit=N` | Maximo de conversas exportadas, `0` = sem limite (default: `0`) |
| `--out=PATH` | Caminho do arquivo `.jsonl` (default: `storage/app/taneia-training/{tenant}-{timestamp}.jsonl`) |

O arquivo gerado fica em `storage/app/taneia-training/` dentro do disco local do tenant.

### Data augmentation (dataset pequeno)

Quando o historico de conversas reais e curto, use `taneia:augment-training` para multiplicar o dataset via paraphrase das mensagens do usuario (resposta do assistente preservada). Requer `GROQ_API_KEY` no `.env` — reusa a mesma configuracao do classifier do Helpdesk.

```bash
# Estimar quantos exemplos seriam gerados (sem chamar a API)
php artisan taneia:augment-training --tenant=meia-sola --dry-run --variations=5

# Gerar 3 variacoes por par user/assistant (apenas conversas aprovadas)
php artisan taneia:augment-training --tenant=meia-sola --variations=3

# Augmentar tudo (sem filtro de rating), 5 variacoes, limitando 50 conversas
php artisan taneia:augment-training --tenant=meia-sola --all --variations=5 --limit=50
```

**Opcoes:**

| Flag | Descricao |
|------|-----------|
| `--tenant=ID` | Contexto tenant (ou use via `tenants:run`) |
| `--variations=N` | Numero de reformulacoes por mensagem de usuario (default: `3`) |
| `--all` | Ignora rating e augmenta todas as conversas |
| `--min-turns=N` | Minimo de pares user/assistant por conversa (default: `1`) |
| `--limit=N` | Maximo de conversas, `0` = sem limite (default: `0`) |
| `--out=PATH` | Caminho do `.jsonl` (default: `taneia-training/augmented-{tenant}-{timestamp}.jsonl`) |
| `--dry-run` | Nao chama a API — apenas imprime a estimativa |

O arquivo gerado contem o par **original + N variacoes** no mesmo formato chat (system/user/assistant). Cada variacao varia estilo (formal/informal), ordem de informacoes, nivel de detalhe e erros leves de digitacao — tudo em pt-BR. Concatene com o arquivo do `taneia:export-training` antes de subir para o Colab:

```bash
cat storage/app/taneia-training/meia-sola-*.jsonl \
    storage/app/taneia-training/augmented-meia-sola-*.jsonl \
    > dataset-final.jsonl
```

### Comandos auxiliares

```bash
# Rodar export ja no contexto tenant (dispensa --tenant)
php artisan tenants:run taneia:export-training --tenants=meia-sola

# Exportar para todos os tenants de uma vez
php artisan tenants:run taneia:export-training

# Gerenciamento de tenants
php artisan tenants:list                # Lista tenants ativos
php artisan tenant:create               # Cria tenant + banco + seed
php artisan tenant:backup {tenant}      # Backup SQL do tenant
php artisan tenant:restore {tenant}     # Restore a partir de dump
php artisan tenant:export-data {tenant} # Export LGPD (JSON consolidado)
```

### Proximos passos (treino)

1. Colete o `.jsonl` gerado do diretorio `storage/app/taneia-training/`.
2. Siga o guia em `taneia-backend/finetune/README.md` para treinar via Unsloth no Colab.
3. Cada linha do arquivo segue o formato chat da OpenAI (`{"messages":[{"role":"system"},{"role":"user"},{"role":"assistant"}]}`), aceito nativamente pelo `standardize_sharegpt` + `apply_chat_template` do Llama 3.1.

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

### Tenants (Aplicação por Empresa)

Cada tenant e acessado via subdomínio. Os tenants sao criados pelo painel central.

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
│   ├── Config/          # 40 config modules (extend ConfigController)
│   ├── Admin/           # Email settings
│   ├── Api/             # Integration API + Webhooks
│   └── *.php            # 62 module controllers
├── Models/              # 200+ Eloquent models
├── Services/            # 87 service classes
├── Enums/               # Role.php, Permission.php (80+ permissions)
└── Http/Middleware/      # Tenant, auth, RBAC, permission, etc.

resources/js/
├── Pages/               # 46 page directories (~100 JSX pages)
│   ├── Central/         # Admin SaaS pages
│   ├── Config/Index.jsx # Generic page for 40 config modules
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

### Principais (38)
Auth · Users · Employees · Stores · Sales · Products · WorkShifts · WorkSchedules ·
Transfers · StockAdjustments · OrderPayments · Suppliers · **PurchaseOrders** ·
**Reversals** · **Returns** · Checklists · MedicalCertificates · Absences ·
OvertimeRecords · StoreGoals · Movements · Vacations · StockAudits ·
PersonnelMovements · **Vacancies** · Training (LMS completo) · ExperienceTracker ·
Deliveries · DeliveryRoutes · Chat · Helpdesk · TaneIA · ActivityLogs ·
EmailSettings · Profile · Dashboard · LGPD · UserSessions · Integrations

### Config (40)
Position, PositionLevel, Sector, Gender, EducationLevel, EmployeeStatus,
EmployeeEventType, TypeMoviment, EmploymentRelationship, Manager, Network,
Status, PageStatus, Bank, CostCenter, Driver, PaymentType, OrderPaymentStatus,
StockAdjustmentStatus, StockAdjustmentReason, TransferStatus, ManagementReason,
ProductBrand, ProductCategory, ProductCollection, ProductSubcollection,
ProductColor, ProductMaterial, ProductSize, ProductArticleComplement,
ProductLookupConfig, ProductLookupGroup, StockAuditCycle, StockAuditVendor,
DismissalReason, DeliveryReturnReason, PercentageAward, **ReversalReason**,
**ReturnReason**

### Destaques recentes

- **Returns** (abr/2026) — Devoluções/trocas do e-commerce (loja Z441),
  state machine 6 estados com logística reversa, quantidade parcial por item,
  motivos categorizados (6 categorias + 15 motivos pré-seeded), máscara BR
  no refund, stale-alert diário. Distinto e independente do Reversals.
  51 tests / 155 assertions.
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
| `docs/ANALISE_MODULO_RETURNS.md` | Modulo de Devoluções/Trocas e-commerce (v2) — 7 fases, 51 testes |

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
