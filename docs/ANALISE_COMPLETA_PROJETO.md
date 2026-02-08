# Analise Completa do Projeto Mercury

**Data:** 2026-02-06
**Ultima Atualizacao:** 2026-02-07
**Versao:** 2.1
**Analista:** Claude Code (Opus 4.6)

---

## Sumario Executivo

O projeto Mercury e um portal administrativo corporativo do Grupo Meia Sola, construido em PHP 8.0+ com arquitetura MVC customizada. O sistema conta com **110 modulos**, **572 controllers**, **641 models**, **683 views**, **90 arquivos JavaScript** e **25 services**. A analise revelou que **77% dos modulos estao modernizados**, com padroes solidos de seguranca na camada de infraestrutura (SQL injection prevention, CSRF global), mas com **vulnerabilidades criticas pontuais** e um acumulo significativo de **codigo legado nos modulos de configuracao**.

### Numeros do Projeto

| Componente | Quantidade |
|-----------|-----------|
| Modulos (View directories) | 110 |
| Controllers | 574 |
| Models | 649 |
| Views | 683 |
| JavaScript files | 91 |
| Services | 25 |
| Model Helpers | 44 |
| Core files | 4 |
| Testes unitarios | 181 |

---

## 1. Arquitetura Geral

### 1.1 Stack Tecnologico

| Camada | Tecnologia |
|--------|-----------|
| Backend | PHP 8.0+ com type hints |
| Frontend | Bootstrap 4.6.1 + Vanilla JavaScript (ES6+) |
| Icons | Font Awesome 6.6.0 |
| Database | MySQL (PDO) + PostgreSQL (ERP Cigam) |
| Email | PHPMailer 6.2+ |
| PDF | DomPDF 3.0 |
| Spreadsheet | PhpSpreadsheet 5.3 |
| QR Code | Endroid QR Code 5.0 |
| UUID | Ramsey UUID 4.7 |
| Testes | PHPUnit 12.4 |
| Env | vlucas/phpdotenv 5.4 (dev) + EnvLoader customizado |

### 1.2 Estrutura MVC

```
mercury/
+-- core/                    # Framework Core (4 arquivos)
|   +-- Config.php           # Constantes e configuracao
|   +-- ConfigController.php # Routing + CSRF middleware + Session validation
|   +-- ConfigView.php       # View rendering com path traversal protection
|   +-- EnvLoader.php        # Carregador de variaveis .env
|
+-- app/adms/                # Modulo administrativo principal
|   +-- Controllers/         # 574 controllers (PascalCase)
|   +-- Models/              # 649 models (Adms prefix)
|   |   +-- helper/          # 44 database helpers e utilities
|   +-- Views/               # 683 views em 110 diretorios
|   +-- Services/            # 25 business services
|
+-- app/cpadms/              # Modulo de busca/query
+-- assets/                  # CSS, JS, imagens, fontes
+-- tests/                   # 181 testes unitarios
+-- vendor/                  # Composer dependencies
```

### 1.3 Fluxo de Requisicao

```
index.php -> Config.php (constantes, sessao, CSRF token)
          -> ConfigController (routing, CSRF validation, session check)
          -> Controller (logica de negocios)
          -> Model (acesso a dados via helpers)
          -> ConfigView (renderizacao de view)
```

### 1.4 Sistema de Rotas

O roteamento e baseado em banco de dados (tabela `adms_paginas`). A URL e parseada em `controller/metodo/parametro` e validada contra registros no banco. Permissoes sao controladas pela tabela `adms_nivacs_pgs`.

---

## 2. Dependencias

### 2.1 Dependencias Externas (Composer)

| Pacote | Versao | Proposito | Status |
|--------|--------|----------|--------|
| phpmailer/phpmailer | ^6.2 | Envio de emails | Estavel |
| ckeditor/ckeditor | 4.* | Editor WYSIWYG | **LEGADO** - CKEditor 4 EOL |
| dompdf/dompdf | ^3.0 | Geracao de PDF | Atual |
| ramsey/uuid | ^4.7 | Geracao de UUID v7 | Atual |
| phpoffice/phpspreadsheet | ^5.3 | Excel import/export | Atual |
| endroid/qr-code | ^5.0 | Geracao de QR Codes | Atual |
| phpunit/phpunit (dev) | ^12.4 | Testes unitarios | Atual |
| vlucas/phpdotenv (dev) | ^5.4 | Variaveis de ambiente | Atual |

### 2.2 Dependencias Internas (Services)

| Service | Usado por | Proposito |
|---------|----------|----------|
| CsrfService | ConfigController (global) | Protecao CSRF |
| LoggerService | Controllers modernos | Auditoria e logging |
| NotificationService | Controllers modernos | Notificacoes email + flash messages + rate limiting |
| FormSelectRepository | Controllers modernos | Dados de selects centralizados |
| SelectCacheService | FormSelectRepository | Cache de selects |
| PermissionService | Controllers | Verificacao de permissoes |
| AuthenticationService | Login | Autenticacao (credenciais teste removidas) |
| FileUploadService | Upload controllers | Upload de arquivos |
| ExportService | Reports | Exportacao de dados |
| ImportService | Import controllers | Importacao de dados |
| StatisticsService | Dashboard/Reports | Calculos estatisticos |
| ChatService | Chat | Mensagens internas |
| ChecklistService | Checklist | Gerenciamento de checklists |
| BudgetService | Budgets | Gerenciamento de orcamentos |
| TrainingEmailService | Training | Emails de treinamento |
| TrainingQRCodeService | Training | QR Codes para treinamento |
| StoreGoalEmailService | StoreGoals | Emails de metas |
| StoreGoalsRedistributionService | StoreGoals | Redistribuicao de metas |
| TravelExpenseService | Expenses | Despesas de viagem |
| GoogleOAuthService | Login | OAuth Google |

### 2.3 Conexoes de Banco de Dados

| Conexao | Banco | Proposito |
|---------|-------|----------|
| AdmsConn | MySQL | Banco principal do sistema |
| AdmsConnCigam | PostgreSQL | Integracao ERP Cigam (leitura) |

---

## 3. Categorizacao dos Modulos

### 3.1 Resumo por Categoria

| Categoria | Total | Modernos | Legados | % Moderno |
|-----------|-------|----------|---------|-----------|
| Core/Sistema | 13 | 8 | 5 | 62% |
| Financeiro/Pagamentos | 12 | 10 | 2 | 83% |
| RH/Pessoas | 24 | 18 | 6 | 75% |
| Operacoes/Logistica | 18 | 16 | 2 | 89% |
| Inventario/Produtos | 15 | 13 | 2 | 87% |
| Configuracao/Settings | 25 | 16 | 9 | 64% |
| Transferencias/Ajustes | 8 | 6 | 2 | 75% |
| Auditoria/Compliance | 4 | 2 | 2 | 50% |
| Conteudo/Conhecimento | 6 | 5 | 1 | 83% |
| Especializado | 6 | 6 | 0 | 100% |
| Monitoramento | 2 | 1 | 1 | 50% |
| **TOTAL** | **110** | **~85** | **~25** | **~77%** |

### 3.2 Modulos de Referencia (Gold Standard)

| Modulo | Destaque |
|--------|---------|
| **Sales** | Melhor implementacao geral: match expressions, statistics, async/await, testes |
| **StoreGoals** | Melhor para modulos complexos com notifications e import |
| **HolidayPayment** | Melhor para validacao complexa e permissoes por loja |
| **Transfers** | Melhor para modulos financeiros com estatisticas |

### 3.3 Modulos Duplicados (Legacy + Moderno)

| Legado | Moderno | Status |
|--------|---------|--------|
| `funcionarios` | `employee` | Coexistem |
| `transferencia` | `transfers` | Coexistem |
| `treinamento` | `training` | Coexistem |
| `rota` | `route` | Coexistem |

---

## 4. Padroes Legados vs Modernos

### 4.1 Comparacao de Controllers

| Aspecto | Legado | Moderno |
|---------|--------|---------|
| **Type hints** | Nenhum | Union types (`int\|string\|null`) |
| **Propriedades** | `$Dados`, `$PageId` (PT, PascalCase) | `$data`, `$pageId` (EN, camelCase) |
| **Metodos** | `listar()`, `editar()`, `apagar()` | `list()`, `create()`, `edit()`, `delete()` |
| **Roteamento** | if/else linear | `match()` expressions |
| **DI** | Instanciacao direta | Constructor injection |
| **Sessao** | `$_SESSION['key']` direto | `$_SESSION['key'] ?? fallback` |
| **Notificacoes** | HTML em `$_SESSION['msg']` | NotificationService |
| **Logging** | Nenhum | LoggerService completo |
| **CSRF** | Nenhum (confia no middleware) | `unset($this->data['_csrf_token'])` explicito |
| **AJAX** | Nenhum suporte | Deteccao + JSON responses |
| **Linhas/arquivo** | 43-45 | 150-300+ |
| **Metodos privados** | 0 | 3-8 |
| **Testabilidade** | Impossivel | Possivel |

### 4.2 Comparacao de JavaScript

| Aspecto | Legado | Moderno |
|---------|--------|---------|
| **HTTP** | jQuery `$.ajax()` | `fetch()` + async/await |
| **DOM** | jQuery selectors | Vanilla JS + `closest()` |
| **Eventos** | `$(document).on()` | `addEventListener()` + delegation |
| **Erros** | `.fail()` callbacks | `try/catch` com status HTTP |
| **Modulos** | jQuery IIFE | Funcoes ES6+ |
| **CSRF** | Manual | `csrf-setup.js` global interceptor |

### 4.3 Metricas de Qualidade

| Metrica | Legado | Moderno |
|---------|--------|---------|
| Type declarations | 0% | 100% |
| PHPDoc | 0% | 100% |
| Input validation | 1 (cast) | 3-5+ (filter + validate) |
| Logging calls | 0 | 2-5+ por operacao |
| Testes unitarios | 0 | Sim (181 arquivos) |

---

## 5. Auditoria de Seguranca

### 5.1 Vulnerabilidades Criticas

| # | Vulnerabilidade | Localizacao | Impacto | Status |
|---|----------------|-------------|---------|--------|
| 1 | **Credenciais de teste hardcoded** | `AuthenticationService.php` | Bypass de autenticacao | ✅ CORRIGIDO |
| 2 | **Path traversal em anexos de email** | `NotificationService.php` | Leitura arbitraria de arquivos | ✅ CORRIGIDO (realpath + allowedBasePath + MIME + size) |
| 3 | **Credenciais SMTP em texto plano** | tabela `adms_confs_emails` | Exposicao se BD comprometido | ⚠️ PENDENTE (BD) |
| 4 | **Credenciais Mailtrap hardcoded** | `AdmsPhpMailer.php` | Credenciais expostas em codigo | ✅ CORRIGIDO (movido para .env) |
| 5 | **XSS em paginacao** | `AdmsPaginacao.php` | Injecao JS via `$_SESSION['pgid']` | ✅ CORRIGIDO (htmlspecialchars) |

### 5.2 Vulnerabilidades de Alta Severidade

| # | Vulnerabilidade | Localizacao | Impacto | Status |
|---|----------------|-------------|---------|--------|
| 6 | **CSRF tokens sem binding** | `CsrfService.php` | Tokens nao vinculados a usuario/IP | ✅ CORRIGIDO (session binding via user_id + IP) |
| 7 | **Headers de seguranca ausentes** | `.htaccess` | Sem CSP, HSTS, X-Frame-Options | ✅ CORRIGIDO (CSP, HSTS, X-Frame-Options, etc.) |
| 8 | **Sem enforcement HTTPS** | `.htaccess` | MITM attacks possiveis | ✅ CORRIGIDO (redirect condicional) |
| 9 | **Politica de senha fraca** | `AdmsValSenha.php` | Minimo 6 caracteres | ✅ CORRIGIDO (12 chars + complexidade) |
| 10 | **Validacao IP de sessao** | `AdmsLogin.php` | Bloqueia usuarios legitimos | ⚠️ PENDENTE (by design) |

### 5.3 Vulnerabilidades de Media Severidade

| # | Vulnerabilidade | Localizacao | Impacto | Status |
|---|----------------|-------------|---------|--------|
| 11 | **Filtragem incompleta de dados sensiveis em logs** | `LoggerService.php` | CPF, CNPJ nos logs | ✅ CORRIGIDO (filtragem LGPD adicionada) |
| 12 | **IP spoofing via X-Forwarded-For** | `LoggerService.php` | IPs falsificados nos logs | ✅ CORRIGIDO (prevencao de IP spoofing) |
| 13 | **Sem hardening de sessao** | `Config.php` | Sem httponly, samesite | ✅ CORRIGIDO (httponly, samesite, strict_mode, gc_maxlifetime) |
| 14 | **Sem rate limiting de email** | `NotificationService.php` | Email bombing possivel | ✅ CORRIGIDO (30 emails/15min) |
| 15 | **Excecoes CSRF hardcoded** | `ConfigController.php` | Rotas podem perder protecao | ✅ CORRIGIDO (excecoes removidas, csrf-setup.js cobre todas as rotas) |
| 16 | **Sessao de 8 horas** | `Config.php` | Muito longa para dados financeiros | ✅ CORRIGIDO (2 horas - gc_maxlifetime=7200) |
| 17 | **Validacao de email quebrada** | `AdmsEmail.php` | Regex rejeita emails validos | ✅ CORRIGIDO (filter_var) |
| 18 | **Sem autenticacao em criptografia** | `AdmsEncrypt/Decrypt.php` | Sem HMAC | ✅ CORRIGIDO (HMAC adicionado) |
| 19 | **Credenciais Cigam publicas** | `Config.php` | Fallbacks hardcoded | ✅ CORRIGIDO (movido para .env) |
| 20 | **Email admin em mensagem de erro** | `AdmsConn.php` | Leak de informacao | ✅ CORRIGIDO (email oculto) |

### 5.4 Praticas de Seguranca Bem Implementadas

| Pratica | Implementacao | Status |
|---------|--------------|--------|
| SQL Injection Prevention | PDO prepared statements em todos os helpers | EXCELENTE |
| CSRF Global | CsrfService + ConfigController middleware | BOM |
| Password Hashing | `password_verify()` com bcrypt | BOM |
| Password Policy | 12 chars + maiuscula + minuscula + numero + especial | BOM |
| Random Token Generation | `random_bytes(32)` - 256-bit entropy | EXCELENTE |
| Timing-Safe Comparison | `hash_equals()` para tokens | EXCELENTE |
| Output Escaping | `htmlspecialchars()` no NotificationService + Views | BOM |
| Path Traversal Protection | ConfigView + NotificationService com `realpath()` + whitelist | BOM |
| Session Hardening | httponly, samesite=Lax, strict_mode, gc_maxlifetime=7200 | BOM |
| Session Tracking | `adms_users_online` com force logout | BOM |
| Secure Cookies | HttpOnly + Secure + SameSite flags | BOM |
| Security Headers | CSP, HSTS, X-Frame-Options, X-Content-Type-Options | BOM |
| HTTPS Enforcement | Redirect condicional (bypass localhost) | BOM |
| Input Sanitization | `strip_tags()` + `trim()` + `filter_var()` | BOM |
| File Upload Validation | FileUploadService com tipo/tamanho | BOM |
| Email Rate Limiting | 30 emails/15min por sessao | BOM |
| Login Rate Limiting | 5 tentativas/15min por IP | BOM |
| Email Attachment Validation | realpath + MIME whitelist + 10MB max | BOM |
| Authenticated Encryption | HMAC em AdmsEncrypt/Decrypt | BOM |

### 5.5 Score de Seguranca por Area

| Area | Score Anterior | Score Atual | Classificacao |
|------|---------------|-------------|--------------|
| SQL Injection | 9/10 | 9/10 | Excelente |
| XSS Prevention | 6/10 | 8/10 | Bom (CSP + htmlspecialchars + paginacao corrigida) |
| CSRF Protection | 7/10 | 8/10 | Bom (binding adicionado, excecoes removidas, falta rotacao) |
| Autenticacao | 4/10 | 7/10 | Bom (creds removidas, senha forte, rate limiting) |
| Autorizacao | 7/10 | 7/10 | Bom (DB-driven + middleware) |
| Protecao de Dados | 4/10 | 7/10 | Bom (HTTPS, env vars, HMAC) |
| Seguranca de Sessao | 5/10 | 8/10 | Bom (httponly, samesite, strict, 2h timeout) |
| Seguranca de Email | 3/10 | 8/10 | Bom (path traversal fix, rate limit, MIME validation) |
| Logging/Auditoria | 7/10 | 8/10 | Bom (coleta boa, filtragem LGPD adicionada, IP spoofing prevenido) |
| **GERAL** | **5.8/10** | **7.8/10** | **Bom** |

---

## 6. Analise Modulo por Modulo

### 6.1 CORE/SISTEMA

#### 6.1.1 Login

**Arquivos:** `Controllers/Login.php`, `Models/AdmsLogin.php`, `Views/login/`
**Status:** Semi-Moderno

**Pontos Fortes:**
- Bcrypt password hashing
- Session tracking em `adms_users_online`
- Cookie auth_token com HttpOnly + Secure
- CSRF excecao justificada (login inicial)

**Pontos Fracos:**
- Validacao IP de sessao muito restritiva (bloqueia proxy/mobile)

**Melhorias Aplicadas:**
- [x] Remover credenciais de teste do AuthenticationService
- [x] Implementar rate limiting de login (max 5 tentativas/15min)
- [x] Reduzir timeout de sessao para 2 horas (gc_maxlifetime=7200)

**Melhorias Pendentes:**
- [ ] Adicionar suporte a 2FA/MFA
- [ ] Flexibilizar validacao IP (whitelist de proxies)
- [ ] Implementar `session_regenerate_id()` apos login

#### 6.1.2 Dashboard

**Arquivos:** `Controllers/VerDashboard.php`, `Views/dashboard/`
**Status:** Legado

**Pontos Fracos:**
- Metodo portugues `listar()`
- Sem type hints
- Sem match expressions
- Sem NotificationService

**Melhorias Sugeridas:**
- [ ] Refatorar para padrao moderno (usar Sales como referencia)
- [ ] Adicionar type hints
- [ ] Renomear metodos para ingles

#### 6.1.3 User Management

**Arquivos:** `Controllers/User*.php`, `Models/AdmsUser*.php`, `Views/user/`
**Status:** Moderno

**Pontos Fortes:**
- Type hints completos
- Service layer
- Testes unitarios (diretorio `tests/Users/`)

**Melhorias Aplicadas:**
- [x] Validacao de forca de senha rigorosa (12 chars + complexidade)

**Melhorias Pendentes:**
- [ ] Implementar historico de senhas

#### 6.1.4 Permissions / Access Level

**Arquivos:** `Controllers/Permissions*.php`, `Services/PermissionService.php`
**Status:** Semi-Moderno

**Melhorias Sugeridas:**
- [ ] Cache de permissoes (verificacao em cada request e custosa)
- [ ] Escapar mensagem no PermissionService (XSS potencial)

#### 6.1.5 Chat

**Arquivos:** `Controllers/Chat*.php`, `Services/ChatService.php`, `Views/chat/`
**Status:** Moderno

**Melhorias Sugeridas:**
- [ ] Considerar WebSocket para real-time (polling e ineficiente)
- [ ] Sanitizar conteudo de mensagens contra XSS

#### 6.1.6 Activity Log

**Arquivos:** `Controllers/ActivityLog*.php`, `Views/activityLog/`
**Status:** Moderno

**Melhorias Sugeridas:**
- [ ] Implementar politica de retencao de logs
- [ ] Adicionar filtros avancados (por usuario, tipo, data)

---

### 6.2 FINANCEIRO/PAGAMENTOS

#### 6.2.1 Sales (Modulo de Referencia)

**Arquivos:** `Controllers/Sales.php`, `Controllers/AddSales.php`, `Controllers/EditSales.php`, `Controllers/DeleteSales*.php`, `Models/AdmsStatisticsSales.php`, `Models/AdmsListSales.php`, `assets/js/sales.js`, `tests/Sales/`
**Status:** Moderno (Gold Standard)
**Testes:** 7 arquivos de teste

**Pontos Fortes:**
- Match expressions para roteamento
- Union types completos
- LoggerService em todas operacoes
- NotificationService
- FormSelectRepository
- Async/await no JavaScript
- Statistics endpoint dedicado
- 113 testes unitarios

**Melhorias Sugeridas:**
- [ ] Extrair base controller abstrato para reutilizar padrao
- [ ] Adicionar paginacao no endpoint de statistics

#### 6.2.2 Transfers

**Arquivos:** `Controllers/Transfers*.php`, `Models/AdmsListTransfers.php`, `assets/js/transfers.js`, `tests/Transfers/`
**Status:** Moderno

**Melhorias Sugeridas:**
- [ ] Adicionar LoggerService (parcialmente ausente)
- [ ] Remover `Transferencia.php` legado (duplicata)

#### 6.2.3 Holiday Payment

**Arquivos:** `Controllers/HolidayPayment*.php`, `Models/AdmsHolidayPayment*.php`, `assets/js/holiday-payment.js`, `tests/HolidayPayment/`
**Status:** Moderno

**Pontos Fortes:**
- Validacao de dados mais robusta do projeto (47+ linhas)
- Filtragem por loja baseada em nivel de acesso
- JSON responses com metodo `jsonResponse()` dedicado

**Melhorias Sugeridas:**
- [ ] Extrair metodo `jsonResponse()` para trait reutilizavel (ja existe `JsonResponseTrait.php`)

#### 6.2.4 Order Payment

**Arquivos:** `Controllers/OrderPayment*.php`, `Views/orderPayment/`, `assets/js/order-payment.js`
**Status:** Moderno

**Melhorias Sugeridas:**
- [ ] Adicionar testes unitarios
- [ ] Validar valores monetarios (format/range)

#### 6.2.5 Reversals (Estornos)

**Arquivos:** `Controllers/Reversals*.php`, `Models/AdmsReversals*.php`, `assets/js/reversals.js`, `tests/Reversals/`
**Status:** Moderno

**Melhorias Sugeridas:**
- [ ] Adicionar aprovacao em dois niveis para estornos acima de valor X
- [ ] Log de tentativas de estorno rejeitadas

#### 6.2.6 Budgets

**Arquivos:** `Controllers/Budgets*.php`, `Services/BudgetService.php`, `assets/js/budgets.js`, `tests/Budgets/`
**Status:** Moderno

**Melhorias Sugeridas:**
- [ ] Adicionar versionamento de orcamentos (historico de alteracoes)

#### 6.2.7 Coupons

**Arquivos:** `Controllers/Coupons*.php`, `Views/coupon/`, `assets/js/coupons.js`, `tests/Coupons/`
**Status:** Moderno

#### 6.2.8 Expenses / Travel Expenses

**Arquivos:** `Controllers/Expenses*.php`, `Services/TravelExpenseService.php`, `Views/expenses/`
**Status:** Moderno

**Melhorias Sugeridas:**
- [ ] Adicionar limites de aprovacao por nivel hierarquico
- [ ] Upload de comprovantes com validacao de tipo

#### 6.2.9 SituacaoPg / TipoPagamento (Legacy)

**Status:** Legado
**Melhorias:** Refatorar para padrao TypePayment moderno

---

### 6.3 RH/PESSOAS

#### 6.3.1 Employee

**Arquivos:** `Controllers/Employee*.php`, `Models/AdmsEmployee*.php`, `Views/employee/`, `assets/js/employee.js`, `tests/Employees/`
**Status:** Moderno

**Pontos Fortes:**
- CRUD completo com match expressions
- Testes unitarios
- Integracao com NotificationService

**Melhorias Sugeridas:**
- [ ] Remover `Funcionarios.php` legado (duplicata)
- [ ] Adicionar historico de alteracoes de dados do funcionario

#### 6.3.2 Training

**Arquivos:** `Controllers/Training*.php`, `Services/TrainingEmailService.php`, `Services/TrainingQRCodeService.php`, `Views/training/`, `assets/js/training.js`, `tests/Training/`
**Status:** Moderno

**Pontos Fortes:**
- Certificados com QR Code
- Email service dedicado
- Testes unitarios

**Melhorias Sugeridas:**
- [ ] Remover `Treinamento.php` e `HomeTreinamento.php` legados
- [ ] Adicionar tracking de progresso do treinamento

#### 6.3.3 Absence Control

**Arquivos:** `Controllers/Absence*.php`, `Views/absence/`, `assets/js/absence.js`, `tests/AbsenceControl/`
**Status:** Moderno

#### 6.3.4 Overtime Control

**Arquivos:** `Controllers/OvertimeControl*.php`, `Views/overtimeControl/`, `assets/js/overtime-control.js`, `tests/OvertimeControl/`
**Status:** Moderno

#### 6.3.5 Medical Certificate

**Arquivos:** `Controllers/MedicalCertificate*.php`, `Views/medicalCertificate/`
**Status:** Moderno

**Melhorias Sugeridas:**
- [ ] Validacao de CID (codigo de doenca)
- [ ] Controle de confidencialidade (dados medicos sensiveis)

#### 6.3.6 Vacancy Opening

**Arquivos:** `Controllers/VacancyOpening*.php`, `Views/vacancyOpening/`, `tests/VacancyOpening/`
**Status:** Moderno

#### 6.3.7 Personnel Movements

**Arquivos:** `Controllers/PersonnelMoviments*.php`, `Views/personnelMoviments/`, `tests/PersonnelMoviments/`
**Status:** Moderno

**Melhorias Sugeridas:**
- [ ] Corrigir nome do modulo: `Moviments` -> `Movements` (typo)

#### 6.3.8 Work Schedule

**Arquivos:** `Controllers/WorkSchedule*.php`, `Views/workSchedule/`, `tests/WorkSchedule/`
**Status:** Moderno

#### 6.3.9 Turn List

**Arquivos:** `Controllers/TurnList*.php`, `Views/turnList/`, `tests/TurnList/`
**Status:** Moderno

#### 6.3.10 Cargo (Cargos/Posicoes)

**Arquivos:** `Controllers/Cargo*.php`, `Views/cargo/`, `assets/js/cargo.js`
**Status:** Moderno

#### 6.3.11 Funcionarios (Legacy)

**Status:** Legado - substituido por Employee
**Acao:** Migrar referencias e remover

---

### 6.4 OPERACOES/LOGISTICA

#### 6.4.1 Delivery

**Arquivos:** `Controllers/Delivery*.php`, `Views/delivery/`, `assets/js/delivery.js`
**Status:** Moderno

#### 6.4.2 Delivery Routing

**Arquivos:** `Controllers/DeliveryRouting*.php`, `Views/delivery-routing/`, `assets/js/delivery-routing.js`, `tests/DeliveryRouting/`
**Status:** Moderno

#### 6.4.3 Order Control

**Arquivos:** `Controllers/OrderControl*.php`, `Views/orderControl/`, `assets/js/order-control.js`, `tests/OrderControl/`
**Status:** Moderno

**Melhorias Sugeridas:**
- [x] ~~Mover excecao CSRF do ConfigController.php para configuracao~~ ✅ (excecoes removidas)

#### 6.4.4 Service Order

**Arquivos:** `Controllers/ServiceOrder*.php`, `Views/serviceOrder/`, `assets/js/service-order.js`, `tests/ServiceOrders/`
**Status:** Moderno

#### 6.4.5 Checklist / Checklist Service

**Arquivos:** `Controllers/Checklist*.php`, `Services/ChecklistService.php`, `Services/ChecklistServiceBusiness.php`, `Views/checklist/`, `tests/ChecklistService/`
**Status:** Moderno

#### 6.4.6 Store Goals

**Arquivos:** `Controllers/StoreGoals*.php`, `Services/StoreGoalEmailService.php`, `Services/StoreGoalsRedistributionService.php`, `Views/goals/`, `assets/js/store-goals.js`, `tests/StoreGoals/`
**Status:** Moderno (JS legado)

**Melhorias Sugeridas:**
- [ ] Refatorar `store-goals.js` (1.124 linhas jQuery) para Fetch API

#### 6.4.7 Consignments

**Arquivos:** `Controllers/Consignments*.php`, `Views/consignments/`, `tests/Consignments/`
**Status:** Moderno

#### 6.4.8 Returns

**Arquivos:** `Controllers/Returns*.php`, `Views/returns/`
**Status:** Moderno

#### 6.4.9 Defects / Defect Location

**Arquivos:** `Controllers/Defects*.php`, `Controllers/DefectLocation*.php`
**Status:** Moderno

---

### 6.5 INVENTARIO/PRODUTOS

#### 6.5.1 Stock

**Arquivos:** `Controllers/Stock*.php`, `Views/stock/`, `assets/js/stock.js`
**Status:** Moderno

**Pontos Fortes:**
- Integracao PostgreSQL ERP Cigam
- Consultas cross-database

#### 6.5.2 Supplier

**Arquivos:** `Controllers/Supplier*.php`, `Views/supplier/`, `tests/Suppliers/`
**Status:** Moderno

#### 6.5.3 Store (Lojas)

**Arquivos:** `Controllers/Store*.php`, `Views/store/`, `tests/Store/`
**Status:** Moderno

#### 6.5.4 Materials / Material Request

**Arquivos:** `Controllers/Materials*.php`, `Views/materials/`, `tests/MaterialRequest/`
**Status:** Moderno

#### 6.5.5 Fixed Assets

**Arquivos:** `Controllers/FixedAssets*.php`, `Views/fixedAssets/`
**Status:** Moderno

#### 6.5.6 Brand

**Arquivos:** `Controllers/Brand*.php`, `Views/brand/`
**Status:** Moderno

#### 6.5.7 Produtos (Legacy)

**Status:** Legado
**Melhorias:** Refatorar para padrao moderno ou integrar com Stock

---

### 6.6 CONFIGURACAO/SETTINGS

#### 6.6.1 Modulos Migrados para AbstractConfigController (13 modulos) ✅ CONCLUIDO

Em Fevereiro de 2026, 13 modulos de configuracao foram migrados para o padrao `AbstractConfigController`, eliminando 50 models legados e reduzindo ~819 linhas de codigo duplicado.

**Padrao AbstractConfigController:**
- Classe base abstrata com CRUD completo (list, create, edit, delete, view)
- Cada modulo define apenas um array `MODULE` com configuracao (tabela, queries, rotas, views)
- LoggerService integrado para auditoria
- Validacao de campos vazios via AdmsCampoVazio
- Suporte a paginacao, foreign key checks, hooks pre-criacao

**Modulos Migrados:**
`cor` ✅, `bandeira` ✅, `situacao` ✅, `cfop` ✅, `tipoPagamento` ✅, `tipoPg` ✅, `situacaoPg` ✅, `rota` ✅, `situacaoTransf` ✅, `situacaoTroca` ✅, `situacaoUser` ✅, `situacaoDelivery` ✅, `responsavelAuditoria` ✅

#### 6.6.2 MotivoEstorno/ReversalReason ✅ CONCLUIDO

Migrado para padrao moderno AJAX/modal em Fevereiro de 2026. Usa NotificationService, LoggerService, async/await JavaScript.

#### 6.6.3 Modulos Config Ainda Legados (~12 modulos)

**Modulos Legados Restantes:**
`situacaoAj`, `situacaoBalanco`, `situacaoOrderPayment`, `tipoArt`, `tipoRemanejo`, `motivo`, `ciclo`, `catArt`, `auditoria`, `confEmail`, entre outros

**Melhoria Sugerida:**
- [ ] Migrar modulos restantes para AbstractConfigController
- [ ] Cada modulo de configuracao herda do base e define apenas tabela/campos

---

### 6.7 TREINAMENTO/EDUCACAO

#### 6.7.1 Escola Digital / E-Learning

**Arquivos:** `Controllers/EscolaDigital.php`, `Controllers/HomeTreinamento.php`, `Controllers/UsuariosTreinamento.php`
**Status:** Legado

**Melhorias Sugeridas:**
- [ ] Migrar para o modulo Training moderno
- [ ] Unificar com publicTraining

#### 6.7.2 Certificate / Certificate Template

**Arquivos:** `Controllers/Certificate*.php`, `Controllers/CertificateTemplate*.php`, `Views/certificate/`, `Views/certificateTemplate/`, `tests/CertificateTemplate/`
**Status:** Moderno

---

### 6.8 ECOMMERCE

**Arquivos:** `Controllers/Ecommerce*.php`, `Views/ecommerce/`
**Status:** Moderno

**Melhorias Sugeridas:**
- [ ] Adicionar testes unitarios
- [ ] Implementar webhooks para integracao com plataformas

---

## 7. Database Helpers - Analise Detalhada

### 7.1 CRUD Helpers (EXCELENTE)

| Helper | Funcao | Seguranca SQL | Score |
|--------|--------|--------------|-------|
| AdmsRead | SELECT | PDO prepared statements | 9/10 |
| AdmsCreate | INSERT | PDO + parameterized | 9/10 |
| AdmsUpdate | UPDATE | PDO + 16 pattern blocks | 10/10 |
| AdmsDelete | DELETE | PDO + 16 pattern blocks | 10/10 |
| AdmsPaginacao | Pagination | Via AdmsRead (safe) | 7/10 (XSS) |

### 7.2 Helpers Especializados

| Helper | Funcao | Status Anterior | Status Atual |
|--------|--------|----------------|--------------|
| AdmsEncrypt/Decrypt | Criptografia | Sem HMAC | ✅ HMAC adicionado |
| AdmsValSenha | Validacao senha | 6 chars, sem complexidade | ✅ 12 chars + complexidade |
| AdmsEmail | Validacao email | Regex quebrada | ✅ filter_var() |
| AdmsPhpMailer | Envio email | Creds Mailtrap hardcoded | ✅ Movido para .env |
| AdmsConn | Conexao MySQL | Admin email em erro | ✅ Email oculto |
| AdmsConnCigam | Conexao PostgreSQL | Credenciais publicas | ✅ Movido para .env |
| AdmsPaginacao | Paginacao | XSS em pgid | ✅ htmlspecialchars + validacao |

### 7.3 Traits Modernos

| Trait | Funcao | Status |
|-------|--------|--------|
| JsonResponseTrait | Respostas JSON | Bom |
| MoneyConverterTrait | Conversao monetaria | Bom |

---

## 8. Cobertura de Testes

### 8.1 Modulos com Testes

| Modulo | Arquivos de Teste | Status |
|--------|-------------------|--------|
| Sales | 7 | Completo |
| Transfers | 1+ | Parcial |
| HolidayPayment | Sim | Parcial |
| StoreGoals | Sim | Parcial |
| Users | Sim | Parcial |
| Employees | Sim | Parcial |
| Training | Sim | Parcial |
| OrderControl | Sim | Parcial |
| ServiceOrders | Sim | Parcial |
| Reversals | Sim | Parcial |
| Budgets | Sim | Parcial |
| Consignments | Sim | Parcial |
| Store | Sim | Parcial |
| Suppliers | Sim | Parcial |
| AccessLevels | Sim | Parcial |
| ActivityLog | Sim | Parcial |
| Auth | Sim | Parcial |
| PageGroups | Sim | Parcial |
| VacancyOpening | Sim | Parcial |
| WorkSchedule | Sim | Parcial |
| OvertimeControl | Sim | Parcial |
| TurnList | Sim | Parcial |
| ChecklistService | Sim | Parcial |
| CertificateTemplate | Sim | Parcial |
| DeliveryRouting | Sim | Parcial |
| InternalTransfers | Sim | Parcial |
| PersonnelMoviments | Sim | Parcial |
| Details | Sim | Parcial |
| MaterialMarketing | Sim | Parcial |
| MaterialRequest | Sim | Parcial |

### 8.2 Modulos sem Testes

Todos os 25 modulos de configuracao + modulos legados nao possuem testes.

**Total:** 181 arquivos de teste em 30+ modulos (de 110 totais)
**Cobertura estimada:** ~30% dos modulos

---

## 9. Recomendacoes de Melhoria (Prioridade)

### 9.1 CRITICAS (Semana 1-2) - ✅ CONCLUIDO

1. ~~**Remover credenciais de teste** do `AuthenticationService.php`~~ ✅
2. ~~**Corrigir path traversal** em anexos do `NotificationService.php`~~ ✅ (realpath + MIME + size)
3. **Criptografar credenciais SMTP** na tabela `adms_confs_emails` ⚠️ PENDENTE (tarefa de BD)
4. ~~**Remover credenciais Mailtrap** do `AdmsPhpMailer.php`~~ ✅ (movido para .env)
5. ~~**Corrigir XSS** na paginacao (`AdmsPaginacao.php`)~~ ✅ (htmlspecialchars)

### 9.2 ALTA PRIORIDADE (Semana 2-4) - ✅ CONCLUIDO

6. ~~**Adicionar HTTPS enforcement** no `.htaccess`~~ ✅ (redirect condicional)
7. ~~**Adicionar security headers** (CSP, X-Frame-Options, HSTS, X-Content-Type-Options)~~ ✅
8. ~~**Hardening de sessao** (`session.cookie_httponly`, `samesite`, `use_strict_mode`)~~ ✅
9. ~~**Fortalecer politica de senha** (minimo 12 chars, complexidade)~~ ✅
10. ~~**Corrigir validacao de email** (usar `filter_var()`)~~ ✅
11. ~~**Reduzir timeout de sessao** de 8h para 2h~~ ✅ (gc_maxlifetime=7200)
12. ~~**Rate limiting de login** (max 5 tentativas/15min)~~ ✅
13. ~~**Tornar credenciais Cigam privadas**~~ ✅ (movido para .env)

### 9.3 MEDIA PRIORIDADE (Mes 1-2) - PARCIALMENTE CONCLUIDO

14. ~~**Criar `AbstractConfigController`** para os 25 modulos de configuracao~~ ✅ CONCLUIDO (13 modulos migrados, -819 linhas)
15. **Remover modulos legados duplicados** (funcionarios, transferencia, treinamento, rota) ⚠️ PENDENTE
16. **Refatorar `store-goals.js`** (1.124 linhas jQuery -> Fetch API) ⚠️ PENDENTE
17. ~~**Implementar autenticacao em criptografia** (HMAC)~~ ✅
18. ~~**Adicionar binding de token CSRF** a usuario/sessao~~ ✅ CONCLUIDO (session binding via user_id + IP)
19. **Implementar rotacao de token CSRF** apos operacoes sensiveis ⚠️ PENDENTE
20. **Adicionar politica de retencao de logs** ⚠️ PENDENTE
21. ~~**Ampliar filtragem de dados sensiveis** (CPF, CNPJ, dados bancarios)~~ ✅ CONCLUIDO (filtragem LGPD)
22. ~~**Rate limiting de email**~~ ✅ (30 emails/15min)
23. **Corrigir typo `PersonnelMoviments`** -> `PersonnelMovements` ⚠️ PENDENTE

### 9.4 BAIXA PRIORIDADE (Mes 2-3)

24. **Substituir CKEditor 4** (EOL) por CKEditor 5 ou TinyMCE
25. **Migrar modulos de configuracao legados** para ingles
26. **Implementar 2FA/MFA**
27. **Adicionar WebSocket** para chat (substituir polling)
28. **Cache de permissoes** (atualmente verifica BD em cada request)
29. **Implementar session_regenerate_id()** apos login
30. **Aumentar cobertura de testes** para 60%+

---

## 10. Metricas de Saude do Projeto

| Metrica | Valor Anterior | Valor Atual | Classificacao |
|---------|---------------|-------------|--------------|
| Modernizacao de codigo | 77% | 77% | BOM |
| Seguranca geral | 5.8/10 | 7.8/10 | BOM |
| SQL Injection prevention | 9/10 | 9/10 | EXCELENTE |
| Cobertura de testes | ~30% | ~30% | BAIXO |
| Documentacao | Desatualizada | Atualizada | BOM |
| Consistencia de nomenclatura | 70% | 70% | MEDIO |
| Duplicacao de codigo | Alta (config modules) | Reduzida (AbstractConfigController) | MEDIO |
| Dependencias externas | Atualizadas | Atualizadas | BOM |
| Performance (DB queries) | Sem cache | Sem cache | MEDIO |

---

## 11. Roadmap de Modernizacao Sugerido

### Fase 1: Seguranca (2-4 semanas) - ✅ CONCLUIDA
- ~~Corrigir todas as vulnerabilidades criticas e altas~~ ✅
- ~~Adicionar security headers e HTTPS~~ ✅
- ~~Hardening de sessao~~ ✅
- ~~Rate limiting (login + email)~~ ✅
- ~~Politica de senha forte~~ ✅
- ~~Validacao de email~~ ✅
- ~~HMAC em criptografia~~ ✅
- ~~Refatoracao NotificationService (DRY, dead code, CSS-only auto-dismiss)~~ ✅

### Fase 2: Consolidacao (4-8 semanas) - PARCIALMENTE CONCLUIDA
- ~~Criar AbstractConfigController~~ ✅ (13 modulos migrados)
- ~~Migrar MotivoEstorno para padrao moderno~~ ✅ (ReversalReason)
- Remover modulos legados duplicados
- Refatorar JavaScript legado
- Migrar `$_SESSION['msg']` legado para NotificationService

### Fase 3: Qualidade (8-12 semanas) - PENDENTE
- Aumentar cobertura de testes para 60%
- Implementar cache de permissoes
- Atualizar CKEditor
- Remover `extract()` das views

### Fase 4: Evolucao (12+ semanas) - PENDENTE
- Implementar 2FA/MFA
- WebSocket para real-time
- API RESTful para integracao externa

---

**Documento gerado em:** 2026-02-06
**Ultima atualizacao:** 2026-02-07
**Baseado em:** Analise completa do codigo-fonte
**Ferramenta:** Claude Code (Opus 4.6)
