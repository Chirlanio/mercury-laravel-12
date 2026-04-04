# Análise Completa do Projeto Mercury — Março 2026

**Data:** 22 de Março de 2026
**Versão:** 4.0
**Escopo:** Análise completa de padrões, dívidas técnicas, gaps e melhorias

---

## 1. Visão Geral do Projeto

| Métrica | Contagem | Evolução (Fev→Mar) |
|---------|----------|---------------------|
| **Controllers** | 727 | 678 → 727 (+49) |
| **Models** | 636 | 617 → 636 (+19) |
| **Views** | 865 | 782 → 865 (+83) |
| **JavaScript** | 128 | 91 → 128 (+37) |
| **Services** | 55 | 39 → 55 (+16) |
| **Helpers** | 42 | 44 → 42 (consolidados) |
| **Search Models (cpadms)** | 71 | 72 → 71 |
| **Testes** | 309 arquivos / 3.899 testes | Mantido |
| **Migrações** | 91 | Crescendo |
| **Documentação** | 95 arquivos | Crescendo |

---

## 2. Classificação de Controllers (727 total)

### 2.1 Por Padrão de Implementação

| Padrão | Quantidade | % | Exemplos |
|--------|-----------|---|----------|
| **Moderno** (match expressions, type hints, services) | 207 | 65% | Sales, StoreGoals, HolidayPayment, VacationPeriods |
| **AbstractConfigController** (subclasses) | 46 | 14% | Holidays, CostCenters, HdCategories, ProdColors |
| **Parcial** (mix old/new) | 60 | 18% | Transfers (parcialmente refatorado) |
| **Legacy** (if/elseif, português) | 51 | 16% | Ajuste, Transferencia, Bandeira, Cfop |

### 2.2 Controllers de Ação

| Tipo | Moderno (AJAX) | Legacy (page-reload) | Total |
|------|---------------|---------------------|-------|
| Add* | 89 | — | 89 |
| Edit* | ~89 | — | ~89 |
| Delete* | 54 (58%) | 39 Apagar* (42%) | 93 |
| View* | ~139 | — | ~139 |

### 2.3 API Controllers (8 total)

Todos em `Api/V1/`: AuthController, SalesController, TicketsController, TransfersController, EmployeesController, AdjustmentsController, InteractionsController, OrderPaymentsController.

**Todos:** type hints completos, BaseApiController, JWT auth, LoggerService.

### 2.4 CRUD Sets Incompletos (16 módulos)

| Módulo | Faltando |
|--------|----------|
| Banks, Brands, Chat, ChatBroadcast, ChatGroup | View controller |
| CertificateTemplate, TrainingSubject | View controller |
| CountFixedAssets, FixedAssets, Returns | Edit, Delete, View |
| DeliveryRoutes | Add, Delete, View |
| Sales | Delete |
| StoreGoals, TravelExpenses | Edit, Delete |

---

## 3. Classificação de Models (636 total)

### 3.1 Padrões de Implementação

| Padrão | Quantidade | % |
|--------|-----------|---|
| Type hints completos | 391 | 66% |
| LoggerService integrado | 118 | 20% |
| AdmsCampoVazio (anti-padrão) | 134 | 23% |
| Nomenclatura portuguesa | 83 | 13% |
| Traits utilizados | ~20 | 3% |

### 3.2 SessionContext Adoption

- **Controllers:** 0 acessos diretos a `$_SESSION` ✅
- **Models:** 0 acessos diretos a `$_SESSION` ✅
- **Services:** 20 referências (esperadas — PermissionService, SessionContext, CsrfService)
- **Views:** 294 arquivos usam `$_SESSION['msg']` (flash messages — aceitável)

### 3.3 Modelos Complexos (>800 linhas)

| Arquivo | Linhas | Risco |
|---------|--------|-------|
| AdmsSynchronizeProducts.php | 2.428 | CRÍTICO — 52 métodos, sync Cigam |
| AdmsStockAuditReconciliation.php | 1.428 | ALTO — 17 métodos públicos |
| AdmsImportOrderControl.php | 1.383 | ALTO — 28 métodos, CSV parsing |
| AdmsStockAuditStoreJustification.php | 1.209 | ALTO — 15 métodos |
| AdmsStockAuditCount.php | 1.088 | ALTO — 9 métodos |
| AdmsImportStockAuditCount.php | 989 | ALTO — 11 métodos |

### 3.4 Statistics Models (39 total)

Todos seguem padrão consistente: `getStats(?string $storeId = null): array`. Boa padronização.

---

## 4. Views e JavaScript

### 4.1 Views (865 arquivos em 219 diretórios)

| Aspecto | Quantidade | % |
|---------|-----------|---|
| Com `htmlspecialchars` (XSS protection) | 343 | 40% |
| Sem escaping explícito | 522 | 60% |
| Modal partials (`_*_modal.php`) | 290 | — |
| Bootstrap 4.6 responsivo | 200+ | — |
| Bootstrap 3 legacy (`col-xs-`, `panel-default`) | 30 | — |
| JavaScript inline (`<script>` tags) | 50+ | — |

### 4.2 JavaScript (128 arquivos)

| Padrão | Quantidade | % |
|--------|-----------|---|
| async/await | 99 | 77% |
| Fetch API | 107 | 83% |
| try/catch error handling | 120 | 94% |
| Event delegation | 40+ | 31% |
| jQuery/callbacks (legacy) | 20+ | 16% |

### 4.3 Arquivos JS Grandes (candidatos a refatoração)

| Arquivo | Linhas |
|---------|--------|
| chat.js | 5.874 |
| order-payments.js | 4.828 |
| order-control.js | 2.624 |
| holiday-payment.js | 2.470 |
| reversals.js | 2.400 |
| overtime-control.js | 2.280 |
| employees.js | 2.243 |

### 4.4 Arquivos JS Obsoletos

- `customCreate.js` — marcado como obsoleto no header, funções movidas para módulos específicos
- `kanban.js` — uso incerto
- `ionicons.js` — pequeno, possivelmente não utilizado

---

## 5. Services (55 total)

### 5.1 Distribuição por Categoria

| Categoria | Qtd | Services |
|-----------|-----|----------|
| Core/Auth | 4 | SessionContext, AuthenticationService, PermissionService, CsrfService |
| Chat/Real-time | 6 | ChatService, GroupChatService, WebSocketService, WebSocketTokenService, WebSocketNotifier, BroadcastService |
| Notificações | 5 | NotificationService, NotificationRecipientService, SystemNotificationService, HelpdeskChatNotifier, HelpdeskEmailService |
| Business Logic | 9 | VacationStatusTransition, VacationPeriodGenerator, VacationValidator, VacationCalculation, ReversalTransition, OrderPaymentAllocation/Delete/Transition, BudgetService |
| Data/Sync | 4 | StockMovementSync, StockAuditCigam, ProductLookup, StockMovementAlert |
| Reports/Analytics | 3 | StockAuditReport, StockAuditRandomSelection, StatisticsService |
| Email | 5 | TrainingEmail, StoreGoalEmail, ChecklistEmail, HelpdeskEmail, NotificationService |
| File Operations | 4 | FileUpload, ImageUploadConfig, ExportService, ImportService |
| Lookups/Data | 3 | FormSelectRepository, SelectCacheService, Ean13Generator |
| Specialized | 4 | RecordLock, GoogleOAuth, StoreGoalsRedistribution, AuditStateMachine |

### 5.2 Padrões

- **62% usam métodos estáticos** (SessionContext, PermissionService, LoggerService, etc.)
- **38% usam dependency injection** (serviços mais novos: Vacation, StockAudit, OrderPayment)
- **47% integram LoggerService**
- **78 blocos try/catch** no total

### 5.3 Oportunidades de Consolidação

| Redundância | Estado Atual | Recomendação |
|-------------|-------------|--------------|
| Email Services (5) | Cada módulo tem seu email service | Consolidar via NotificationService + templates |
| Upload Services (4) | FileUpload + ImageConfig + UploadConfig + UploadResult | Merge ImageConfig em FileUpload |
| Notification Stack (4) | Notification + Recipient + System + HelpdeskChat | SystemNotification deveria encapsular NotificationService |
| Checklist Services (2) | ChecklistService + ChecklistServiceBusiness | Merge ou renomear para clarificar |

---

## 6. Core Framework

### 6.1 Roteamento (ConfigController.php)

- URL slug → PascalCase controller + camelCase method
- Lookup em `adms_paginas` para validação de rota
- CSRF Deploy 5 (global enforcement) — protege POST/PUT/DELETE automaticamente
- Validação de sessão via `adms_users_online` a cada request (**preocupação de performance**)
- Force password change middleware

### 6.2 API Framework (core/Api/)

| Componente | Status |
|-----------|--------|
| Router (regex-based) | Funcional, manual registration |
| JWT (HS256, TTL 1h) | ⚠️ Inconsistente com WebSocket TTL (5min) |
| Rate Limiting (DB-backed) | Funcional, 60 req/60s default |
| CORS | Configurável, `*` default |
| Response padronizado | ApiResponse::success/error |

### 6.3 Segurança do Core

| Aspecto | Score | Notas |
|---------|-------|-------|
| SQL Injection | 9.5/10 | PDO prepared statements consistentes |
| CSRF | 9/10 | Deploy 5 global |
| XSS | 7/10 | 40% views com escaping; 60% sem |
| Session | 9/10 | Validação robusta, SessionContext |
| File Upload | 5/10 | 8 pontos sem validação MIME/extensão |
| Config | 4/10 | Credenciais no .env expostas |

---

## 7. Testes

### 7.1 Métricas

- **309 arquivos de teste** em 73 diretórios
- **3.899 testes passando** (PHPUnit 12.4)
- **~94k linhas** de código de teste
- Framework: PHPUnit com Attributes (PHP 8.0+)

### 7.2 Cobertura por Módulo

**Bem testados (5+ arquivos):**
- Chat (10), CertificateTemplate (5), StockAudit (4), AbsenceControl (4), OrderPayments (3)

**Sem testes dedicados:**
- MaterialRequest, ServiceOrder, OvertimeControl, MedicalCertificate, VacancyOpening, WorkSchedule, FixedAssets, InternalTransfer, Relocation, Returns

### 7.3 Cobertura Estimada

- **42% dos módulos** têm testes dedicados
- **58% dos módulos** sem testes (gap de ~30 módulos)

### 7.4 Padrão de Teste

- Todos integration tests (DB real, não mocks)
- `SessionContext::setTestData()` para mock de sessão
- setUp/tearDown com cleanup via DELETE
- Sem unit tests com mocks/stubs
- Sem testes parametrizados
- Sem teste de performance/benchmark

---

## 8. Banco de Dados e Migrações

### 8.1 Migrações (91 arquivos)

- **77 timestamped** (YYYY_MM_DD) — bem versionadas
- **14 não timestamped** — naming inconsistente (`.sql`, `.php`, `.md` misturados)
- **Sem framework de migração** (execução manual/ad-hoc)
- **Sem rollback scripts** (down migrations)
- Seed/test files misturados com migrações de produção

### 8.2 Schema

- **MySQL** principal + **PostgreSQL** (Cigam ERP)
- **Collation:** Deve usar `utf8mb4_unicode_ci` (MySQL 8 default `0900_ai_ci` quebra UNION)
- **Sem documentação unificada de schema** (apenas migrações incrementais)
- **Sem diagrama ER**

---

## 9. Dependências

### 9.1 Composer (14 pacotes)

| Pacote | Versão | Status |
|--------|--------|--------|
| phpmailer/phpmailer | ^6.2 | ✅ Atual |
| dompdf/dompdf | ^3.0 | ✅ Atual |
| ramsey/uuid | ^4.7 | ✅ Atual |
| phpoffice/phpspreadsheet | ^5.3 | ✅ Atual |
| firebase/php-jwt | ^7.0 | ✅ Atual |
| endroid/qr-code | ^5.0 | ✅ Atual |
| picqer/php-barcode-generator | ^3.2 | ✅ Atual |
| phpunit/phpunit | ^12.4 | ✅ Atual |
| **cboden/ratchet** | ^0.4 | ⚠️ Deprecated — warnings de dynamic properties |
| **ckeditor/ckeditor** | 4.* | ⚠️ CKEditor 4 é legacy (v5 disponível) |
| react/http | ^1.9 | ⚠️ Verificar compatibilidade PHP 8.2+ |

---

## 10. Documentação

### 10.1 Estado Atual (95 arquivos)

**Recentes (ativos):**
- PROPOSTA_INTEGRACAO_WHATSAPP_DP.md (Mar 20)
- ANALISE_MODULO_CONSIGNMENTS.md (Mar 20)
- PLANO_ACAO_GESTAO_FERIAS.md (Mar 17)
- PLANO_ACAO_AUDITORIA_ESTOQUE.md (Mar 15)

**Desatualizados (3+ meses):**
- ANALISE_SEGURANCA.md (Jan 3) — precisa atualização
- SETUP_ENVIRONMENT.md (Nov 18) — 4 meses desatualizado
- DELETE_MODAL_IMPLEMENTATION_GUIDE.md (Nov 26)
- 20+ docs em `docs/modules/MODULO_*.md` (Jan 3) — superados por `ANALISE_MODULO_*.md`

### 10.2 Gaps de Documentação

1. **Sem schema de banco unificado** — apenas 91 migrations incrementais
2. **Sem guia de deployment** — diretório `/deploy/` vazio
3. **Sem estratégia de testes** — 3.899 testes mas sem documentação
4. **Sem CONTRIBUTING.md** — sem guia de contribuição
5. **Sem overview de arquitetura** — ANALISE_COMPLETA é de Fev 26
6. **Sem guia de performance** — crítico para WebSocket + Products sync
7. **Setup desatualizado** — SETUP_ENVIRONMENT.md de Nov 2025

---

## 11. Dívidas Técnicas — Priorização

### 🔴 CRÍTICO (ação imediata)

| # | Dívida | Impacto | Arquivos |
|---|--------|---------|----------|
| 1 | **Credenciais expostas no .env** (Google API, Grok API, OAuth secret) | Segurança | 1 |
| 2 | **AdmsCampoVazio em 134 models** — não valida campos obrigatórios | Integridade de dados | 134 |
| 3 | **60% das views sem htmlspecialchars** | XSS potencial | 522 |
| 4 | **File upload sem validação MIME/extensão** | Segurança | 8 |
| 5 | **Debug controllers em produção** (DebugMenu, DebugMenuDetailed, DebugViewCoupon) | Exposição de dados | 3 |

### 🟡 ALTO (próximo sprint)

| # | Dívida | Impacto | Arquivos |
|---|--------|---------|----------|
| 6 | **80% dos models sem LoggerService** | Auditoria incompleta | 474 |
| 7 | **58% dos módulos sem testes** | Regressões | ~30 módulos |
| 8 | **51 controllers legacy** (português, sem type hints) | Manutenibilidade | 51 |
| 9 | **39 Delete controllers legacy** (page-reload, sem AJAX) | UX inconsistente | 39 |
| 10 | **Ratchet 0.4 deprecated** | Stability | 1 dependência |

### 🟠 MÉDIO (próximo mês)

| # | Dívida | Impacto | Arquivos |
|---|--------|---------|----------|
| 11 | **1.150+ echo statements em controllers** | Code quality | ~100 |
| 12 | **83 models com nomenclatura portuguesa** | Consistência | 83 |
| 13 | **30 views com Bootstrap 3 patterns** | Compatibilidade | 30 |
| 14 | **JS arquivos grandes** (chat.js 5.8K, order-payments.js 4.8K) | Manutenibilidade | 7 |
| 15 | **16 CRUD sets incompletos** | Funcionalidade | 16 módulos |
| 16 | **Email services fragmentados** (5 services) | Duplicação | 5 |
| 17 | **Sem migration framework** (execução manual) | DevOps | 91 |

### 🟢 BAIXO (backlog)

| # | Dívida | Impacto | Arquivos |
|---|--------|---------|----------|
| 18 | CKEditor 4 legacy | UX | 1 dependência |
| 19 | Documentação desatualizada | Onboarding | 20+ docs |
| 20 | `/nbproject/` no repositório | Limpeza | 1 diretório |
| 21 | Backup file `.bak` em Models | Limpeza | 1 |
| 22 | `customCreate.js` obsoleto | Limpeza | 1 |
| 23 | Diretórios `/migrations/` e `/database/migrations/` duplicados | Organização | 2 |

---

## 12. Métricas de Qualidade — Score Card

| Dimensão | Score | Meta | Gap |
|----------|-------|------|-----|
| **Segurança** | 7.8/10 | 9.0 | -1.2 |
| **Consistência de código** | 6.5/10 | 8.0 | -1.5 |
| **Cobertura de testes** | 4.2/10 | 7.0 | -2.8 |
| **Documentação** | 6.0/10 | 8.0 | -2.0 |
| **Modernização** | 6.5/10 | 8.0 | -1.5 |
| **Logging/Auditoria** | 4.0/10 | 8.0 | -4.0 |
| **Performance** | 7.0/10 | 8.0 | -1.0 |
| **DevOps/Deploy** | 3.0/10 | 7.0 | -4.0 |
| **MÉDIA** | **5.6/10** | **7.9** | **-2.3** |

---

## 13. Recomendações Estratégicas

### Fase 1 — Segurança e Estabilidade (1-2 semanas)

1. **Rotacionar credenciais** expostas no `.env` (Google, Grok, OAuth)
2. **Remover debug controllers** (DebugMenu, DebugMenuDetailed, DebugViewCoupon)
3. **Implementar validação de upload** (MIME type + extension whitelist) nos 8 pontos
4. **Auditar views críticas** — adicionar `htmlspecialchars` nos módulos financeiros e de dados sensíveis

### Fase 2 — Qualidade e Consistência (2-4 semanas)

5. **Substituir AdmsCampoVazio** por validação explícita (priorizar módulos financeiros: Sales, Adjustments, Transfers)
6. **Expandir LoggerService** para módulos CRUD sem logging (começar por operações de write: create, update, delete)
7. **Adicionar testes** para os 10 módulos mais críticos sem cobertura
8. **Migrar 39 Apagar* controllers** para padrão Delete* AJAX

### Fase 3 — Modernização (1-2 meses)

9. **Refatorar 51 controllers legacy** para match expressions + type hints
10. **Consolidar email services** (5 → 1 core + templates)
11. **Implementar migration framework** (Phinx ou similar)
12. **Atualizar Ratchet** 0.4 para alternativa mantida (ou avaliar Swoole/OpenSwoole)
13. **Criar documentação de arquitetura** e deployment

### Fase 4 — Excelência (2-3 meses)

14. **Adicionar unit tests** com mocks (além dos integration tests atuais)
15. **Implementar CSP headers** em ConfigView
16. **Migrar CKEditor 4 → 5**
17. **Refatorar JS monolíticos** (chat.js, order-payments.js)
18. **Criar schema de banco unificado** com diagrama ER

---

## 14. Evolução desde Fevereiro 2026

### Progresso Positivo

| Aspecto | Fev 2026 | Mar 2026 | Δ |
|---------|----------|----------|---|
| Controllers | 678 | 727 | +49 |
| Controllers modernos | 43% | 65% | +22% |
| SessionContext migration | Em progresso | Completa | ✅ |
| Stock Audit | Fase 3 | Todas completas (4F) | ✅ |
| Vacation Management | — | Fase 3 completa | ✅ |
| WebSocket Notifications | — | 53 controllers, 28 módulos | ✅ |
| AbstractConfigController | 13 módulos | 46 controllers | +33 |
| Services | 39 | 55 | +16 |

### Áreas Estagnadas

- **Testes:** Mantido em 3.899 (precisa crescer)
- **Legacy controllers:** 51 ainda não refatorados
- **AdmsCampoVazio:** 134 models (apenas 2 corrigidos)
- **Documentação:** Gaps persistentes

---

## 15. Conclusão

O Mercury demonstrou **progresso significativo de modernização** entre Fevereiro e Março 2026, com a migração para SessionContext completada, módulos complexos finalizados (Stock Audit 4F, Vacation Phase 3), e expansão de 16 novos services. A base de controllers modernos cresceu de 43% para 65%.

No entanto, **dívidas técnicas críticas persistem**: credenciais expostas, validação inadequada (AdmsCampoVazio em 134 models), e gaps de cobertura de testes (58% dos módulos). A prioridade imediata deve ser segurança (Fase 1), seguida de qualidade (Fase 2) antes de prosseguir com modernizações adicionais.

O **score geral do projeto é 5.6/10**, com maior gap em DevOps/Deploy (3.0) e Logging/Auditoria (4.0). As recomendações da Fase 1-2 podem elevar o score para ~7.5/10 em 4-6 semanas.

---

**Elaborado por:** Análise automatizada do codebase
**Base de dados:** 727 controllers, 636 models, 865 views, 128 JS, 55 services, 309 test files
**Próxima revisão recomendada:** Abril 2026
