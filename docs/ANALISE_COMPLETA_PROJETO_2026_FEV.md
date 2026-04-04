# Analise Completa do Projeto Mercury

**Data:** 26 de Fevereiro de 2026
**Versao:** 3.0
**Escopo:** Analise abrangente pos-implementacoes WebSocket, Produtos, Movimentacao de Estoque

---

## 1. Visao Geral do Projeto

### 1.1 Numeros Atuais

| Metrica | Quantidade | Observacao |
|---------|-----------|------------|
| Controllers | 678 | 43% modernos, 26% parciais, 31% legados |
| Models | 617 | ~60% com type hints modernos |
| Services | 39 | 100% modernos (PHP 8.0+) |
| Views | 782 arquivos / 191 diretorios | 68 modernos, 69 legados, 54 parciais |
| JavaScript | 91 arquivos (~74K linhas) | 57% async/await, 20% jQuery legado |
| Search Models | 72 | 50% modernos, 50% legados |
| Helper Classes | 44 | Core moderno, validacao mista |
| Migrations | 56 arquivos / 5.704 linhas | Dez/2025 - Fev/2026 |
| Testes | 3.899 (todos passando) | PHPUnit 12.4 |
| Dependencias | 14 prod + 2 dev | 1 critica (Ratchet) |

### 1.2 Stack Tecnologico

- **Backend:** PHP 8.0+ com type hints, MVC customizado
- **Frontend:** Bootstrap 4.6.1 + Vanilla JavaScript (ES6+)
- **Database:** MySQL (principal) + PostgreSQL (ERP Cigam)
- **Real-time:** Ratchet 0.4 WebSocket + ReactPHP HTTP
- **Auth:** Sessions + JWT (WebSocket/API)
- **Email:** PHPMailer com rate limiting (30/15min)
- **PDF:** DomPDF 3.0
- **Excel:** PhpSpreadsheet 5.3

---

## 2. Implementacoes Recentes (Dez/2025 - Fev/2026)

### 2.1 Chat v2.0 com WebSocket

**Arquitetura Three-Tier:**
1. **Cliente (Browser)** - JavaScript WebSocket client com auto-reconnect
2. **Server Publico (porta 8080)** - Ratchet 0.4 aceitando conexoes browser
3. **API Interna (porta 8081)** - ReactPHP HTTP para IPC de controllers PHP

**Componentes:**
- `WebSocketService.php` - Server Ratchet com autenticacao JWT
- `WebSocketTokenService.php` - JWT HS256, TTL 5 minutos
- `WebSocketNotifier.php` - Fire-and-forget curl para IPC
- `ChatService.php` - Permissoes, busca, contagem unread (query otimizada)
- `GroupChatService.php` - CRUD grupos, membros, typing indicators
- `BroadcastService.php` - Notificacoes sistema com rastreio de leitura
- `chat.js` - Cliente com reconnect exponencial (max 30s)
- `navbar-chat-badge.js` - Badge com polling adaptativo (30s visivel, 2min hidden)

**Pontos Fortes:**
- JWT com TTL curto (5min) para autenticacao
- Fire-and-forget nao bloqueia requests PHP
- Suporte a multiplas conexoes por usuario (tabs)
- Fallback automatico para polling quando WS indisponivel
- `document.hasFocus()` + `visibilitychange` para read receipts

**Pontos de Atencao:**
- Ratchet 0.4 descontinuado (2016) - warnings de deprecacao suprimidos
- Typing indicator para grupos faz broadcast para TODOS (filtragem client-side)
- Token JWT no query string da URL (limitacao do protocolo WebSocket)

### 2.2 Modulo de Produtos (Sincronizacao Cigam)

**Arquitetura:**
- `AdmsSynchronizeProducts.php` - Sync em 4 fases com chunks de 1000 registros
- `ProductLookupService.php` - Busca/lookup integrado ao Cigam (PostgreSQL)
- `StockMovementSyncService.php` - Sync de movimentacoes (batch 500)

**Fases de Sincronizacao:**
1. **Lookups** - Colecoes, subcolecoes, tabelas auxiliares
2. **Products** - Produtos em chunks de 1000
3. **Prices** - Precos com historico de alteracoes
4. **Finalize** - Consolidacao e log

**Tabelas de Historico:**
- `adms_product_price_history` - Historico de precos (sale/cost)
- `adms_product_collection_history` - Historico de colecoes (collection/subcollection)

**Metodos Publicos:**
- `initializeSync()`, `processProductChunk()`, `processPrices()`
- `finalizeSync()`, `cancelSync()`, `syncLookupsOnly()`, `syncPricesOnly()`
- `runFullSyncBackground()` - Sync completo em background

### 2.3 Movimentacao de Estoque

**Componentes:**
- `StockMovements.php` (Controller) - Listagem com sort server-side
- `AdmsListStockMovements.php` (Model) - Listagem com ORDER BY dinamico
- `CpAdmsSearchStockMovements.php` (Search) - Busca com filtros combinados
- `StockMovementSyncService.php` - Sync PostgreSQL -> MySQL (batch 500)
- `StockMovementAlertService.php` - Alertas de threshold de estoque
- `stock-movements.js` - JS moderno com async/await e sort delegacao

**Features:**
- Ordenacao server-side com whitelist de colunas
- Indicadores visuais de direcao (setas FontAwesome)
- Filtros combinados (data, loja, tipo, NF)
- Sync automatico com CIGAM/PostgreSQL
- Relatorios com todos os tipos de movimentacao

### 2.4 REST API

**Infraestrutura:**
- `core/Api/` - Endpoint handlers
- `2026_02_17_create_api_tables.sql` - 342 linhas de schema
- JWT tokens via `firebase/php-jwt ^7.0`
- ~40+ endpoints

### 2.5 Outras Implementacoes Recentes

- **Experience Tracker** - Avaliacao de experiencia do usuario
- **System Notifications** - Notificacoes sistema com tracking
- **Helpdesk** - Sistema de service requests
- **Work Schedule** - Gestao de escalas de trabalho (380 linhas de migration)
- **Turn List** - Lista de turnos/presenca (481 linhas de migration)
- **AbstractConfigController** - Base class para 13+ modulos de configuracao
- **ReversalReason** - Migrado para padrao AJAX/modal moderno

---

## 3. Arquitetura e Padroes

### 3.1 Controllers (678 total)

**Distribuicao por Maturidade:**

| Categoria | % | Quantidade | Descricao |
|-----------|---|-----------|-----------|
| Moderno | 43% | ~292 | match expressions, type hints, services |
| Parcial | 26% | ~176 | Alguns padroes modernos, precisa refactor |
| Legado | 31% | ~210 | if/elseif, portugues, sem services |

**Padroes Modernos:**
- `AbstractConfigController` - 13+ modulos migrados
- Match expressions para routing
- Type hints completos (parametros + retorno)
- LoggerService + NotificationService
- AdmsBotao para permissoes dinamicas

**Controllers de Referencia:**
- `Sales.php` - Referencia para modulos complexos
- `StockMovements.php` - Sort server-side, filtros combinados
- `AbstractConfigController.php` - Base para config modules

### 3.2 Services (39 total - 100% modernos)

**Categorias:**

| Categoria | Quantidade | Exemplos |
|-----------|-----------|----------|
| Core/Infra | 6 | Auth, Logger, Notification, CSRF, Permission, Password |
| Chat/Real-time | 6 | Chat, GroupChat, WebSocket, Token, Notifier, Broadcast |
| Business Logic | 11 | Checklist(3), Budget, StockMovement(2), Travel, StoreGoals(2), Training(2) |
| Data/File | 9 | FormSelect, Cache, Export, Upload(4), Text, Import, ProductLookup |
| Infraestrutura | 7 | SystemNotification, RecordLock, Statistics, Ean13, Google, Helpdesk(2) |

**Cobertura de LoggerService:**
- Auth, Chat, GroupChat, Budget, StockMovements, TravelExpense, Checklists, Password
- **Faltando:** Upload, Export, Import, Training, Store, Notification (own service)

### 3.3 Models (617 total)

**Distribuicao:**
- 569 models principais
- 44 helper classes
- 72 search models (em `cpadms/Models/`)

**Problema Critico: AdmsCampoVazio**
- Valida "algum campo nao vazio" mas NAO valida QUAIS campos sao obrigatorios
- **Corrigido em 2 models:** AdmsAddAbsenceControl, AdmsAddInternalTransferSystem
- **Ainda em risco: 50+ models** usando o padrao antigo

### 3.4 Views (782 arquivos)

**Distribuicao:**

| Padrao | Quantidade | Estrutura |
|--------|-----------|-----------|
| Moderno | 68 diretorios | load + list + partials/_modal |
| Legado | 69 diretorios | listar + cad + editar + ver (page reload) |
| Parcial | 54 diretorios | load/list sem partials |

**Responsividade:**
- 346 arquivos (44%) com classes responsivas
- 436 arquivos (56%) sem design responsivo

### 3.5 JavaScript (91 arquivos, ~74K linhas)

**Distribuicao:**

| Padrao | % | Arquivos | Descricao |
|--------|---|----------|-----------|
| Moderno | 57% | ~52 | async/await, fetch, ES6+, event delegation |
| Misto | 23% | ~21 | Transicao (alguns patterns modernos, alguns $.ajax) |
| Legado | 20% | ~18 | jQuery, $.ajax, callbacks, funcoes globais |

**Arquivos de Infraestrutura:**
- `mercury-ws.js` - WebSocket client base
- `csrf-setup.js` - CSRF token management
- `heartbeat.js` - Keep-alive do session
- `navbar-chat-badge.js` - Badge de mensagens

### 3.6 CSS (1.916 linhas)

**Metricas:**
- 251 declaracoes `!important` (ALTO RISCO)
- 24 media queries responsivos
- 41 animacoes/transicoes definidas
- 28+ secoes organizadas com labels

**Problemas:**
- Excesso de `!important` (recomendado max 20-30)
- 95 regras customizadas de margin/padding duplicando Bootstrap
- Falta abordagem mobile-first consistente

---

## 4. Seguranca

### 4.1 Score Geral: 8.2/10

| Area | Score | Detalhes |
|------|-------|----------|
| SQL Injection | 9.5/10 | PDO prepared statements em tudo, validacao WHERE |
| CSRF | 9/10 | Deploy 5 (global), token binding + expiracao 60min |
| XSS | 8/10 | htmlspecialchars nos outputs, path traversal protection |
| Autenticacao | 8/10 | Session hardening, JWT TTL curto, password policy |
| Controle Acesso | 8/10 | DB-driven permissions, store-level filtering |
| Protecao Dados | 7/10 | LoggerService mascara sensiveis, falta encryption at rest |
| Dependencias | 7/10 | Ratchet 0.4 descontinuado, PHPWord desatualizado |

### 4.2 Melhorias Recentes de Seguranca
- Credenciais de teste removidas
- Path traversal corrigido em ConfigView
- CSRF binding com sessao
- Security headers adicionados
- Session timeout 2h com strict_mode
- Password policy: 12 chars + complexidade
- WHERE clause validation nos helpers

### 4.3 Pendencias
- SMTP credentials em plaintext no banco
- Rotacao de CSRF token (atualmente reutiliza)
- Rate limiting em API endpoints
- Field-level encryption para dados sensiveis

---

## 5. Modulos Legados (Prioridade de Migracao)

### 5.1 Modulos 100% Legados (31% = ~210 controllers)

**Config Modules (page-reload, nomes em portugues):**

| Modulo | Controller | Status |
|--------|-----------|--------|
| Situacao | situacao/ | Legado completo |
| Tipo Artigo | tipoArt/ | Legado completo |
| Bandeira | bandeira/ | Legado completo |
| Ciclo | ciclo/ | Legado completo |
| Categoria Artigo | catArt/ | Legado completo |
| Cor | cor/ | Legado completo |
| Defects | defects/ | Legado completo |
| Situacao Ajuste | situacaoAj/ | Legado |
| Situacao Balanco | situacaoBalanco/ | Legado |
| Tipo Estorno | tipoEstorno/ | Legado |

**Modulos de Negocio Legados:**

| Modulo | Controllers | Problema Principal |
|--------|------------|-------------------|
| Funcionarios | funcionarios/* | Duplicado por `employee/` |
| Transferencia | transferencia/* | Duplicado por `transfers/` |
| Treinamento | treinamento/* | Duplicado por `training/` |
| Pedidos | pesqPed/* | Nomenclatura portuguesa |
| Delivery | delivery/* | Misto legado/parcial |
| Ordem Servico | orderService/* | Parcialmente moderno |

### 5.2 Modulos Duplicados (Tech Debt)

| Moderno | Legado | Acao Recomendada |
|---------|--------|-----------------|
| `employee/` | `funcionarios/` | Remover funcionarios, migrar rotas |
| `transfers/` | `transferencia/` | Remover transferencia, migrar rotas |
| `training/` | `treinamento/` | Remover treinamento, migrar rotas |

### 5.3 Search Models Legados (25 de 72)

Nomenclatura `CpAdmsPesq*` (portugues) vs `CpAdmsSearch*` (moderno):

| Legado | Modernizar Para |
|--------|----------------|
| CpAdmsPesqFunc | CpAdmsSearchEmployee |
| CpAdmsPesqPed | CpAdmsSearchOrders |
| CpAdmsPesqProdutos | CpAdmsSearchProducts |
| CpAdmsPesqAjuste | CpAdmsSearchAdjustments (ja existe) |
| CpAdmsPesqTransf | CpAdmsSearchTransfers (ja existe) |
| CpAdmsPesqTroca | CpAdmsSearchExchanges |
| CpAdmsPesqRemanejo | CpAdmsSearchRedeployments |
| CpAdmsPesqDelivery | CpAdmsSearchDelivery |

### 5.4 JavaScript Legado (20% = ~18 arquivos)

Arquivos usando jQuery/$.ajax que precisam migracao:

- Scripts de modulos legados (page-reload CRUD)
- Event handlers com binding direto (nao delegation)
- Callbacks em vez de async/await
- Funcoes globais em vez de modulos

---

## 6. Documentacao

### 6.1 Score Geral: 6.2/10

| Documento | Status | Problema |
|-----------|--------|----------|
| CLAUDE.md | Desatualizado | Falta Chat v2.0, API REST, Produtos, Stock Movements |
| REGRAS_DESENVOLVIMENTO.md | Parcial | Faltam padroes WebSocket, API |
| CHECKLIST_MODULOS.md | Inacurado | Informacoes significativamente incorretas |
| ANALISE_COMPLETA_PROJETO.md | Obsoleta | Versao anterior a implementacoes recentes |
| MODULE_LOGGER_DOCUMENTATION.md | Bom | Atualizado |
| DELETE_MODAL_IMPLEMENTATION_GUIDE.md | Bom | Atualizado |
| PADRONIZACAO.md | Parcial | Secao 20 (AbstractConfigController) ok, resto desatualizado |

### 6.2 Documentacao Ausente

- Documentacao do Chat v2.0 com WebSocket (existe ref v1.0, v2.0 e o que esta live)
- Documentacao da REST API (40+ endpoints)
- Guia de sincronizacao de Produtos (Cigam)
- Documentacao do StockMovements (sync, alertas, sort)
- Guia do AbstractConfigController para novos modulos
- Documentacao dos Traits (Financial, Store, Json, Money)
- Guia de deployment do WebSocket server

---

## 7. Dependencias

### 7.1 Status das Dependencias

| Pacote | Versao | Status | Risco |
|--------|--------|--------|-------|
| cboden/ratchet | ^0.4 | CRITICO | Descontinuado desde 2020, warnings PHP 8.2 |
| phpoffice/phpword | ^1.4 | ATENCAO | Ultima release 2019 |
| phpmailer/phpmailer | ^6.2 | OK | Ativo |
| firebase/php-jwt | ^7.0 | OK | Atual |
| ramsey/uuid | ^4.7 | OK | Atual |
| dompdf/dompdf | ^3.0 | OK | Atual |
| phpoffice/phpspreadsheet | ^5.3 | OK | Atual |
| endroid/qr-code | ^5.0 | OK | Atual |
| phpunit/phpunit | ^12.4 | OK | Atual |

### 7.2 Dependencias Ausentes (Recomendadas)

| Tipo | Recomendacao | Justificativa |
|------|-------------|---------------|
| Cache | Redis/APCu | Queries DB em cada request (rotas, selects) |
| Logging | Monolog | LoggerService customizado funciona mas sem rotacao/canais |
| HTTP Client | Guzzle/Symfony HTTP | curl manual em WebSocketNotifier |
| Rate Limiting | - | API sem throttling |
| Validation | - | AdmsCampoVazio insuficiente |

---

## 8. Recomendacoes Priorizadas

### 8.1 ALTA PRIORIDADE (Seguranca/Estabilidade)

| # | Acao | Impacto | Esforco |
|---|------|---------|---------|
| 1 | Substituir AdmsCampoVazio por validacao explicita em 50+ models | Bug prevention | Medio |
| 2 | Adicionar LoggerService a todos os CRUD models | Audit trail | Medio |
| 3 | Migrar $_SESSION['msg'] para NotificationService | Consistencia | Baixo |
| 4 | Atualizar documentacao (CLAUDE.md, Chat v2.0, API) | Manutencao | Baixo |
| 5 | Planejar substituicao do Ratchet | Seguranca | Alto (pesquisa) |

### 8.2 MEDIA PRIORIDADE (Modernizacao)

| # | Acao | Impacto | Esforco |
|---|------|---------|---------|
| 6 | Migrar 69 modulos legados para AJAX/modal | UX, manutencao | Alto |
| 7 | Migrar 25 search models legados para nomenclatura moderna | Consistencia | Medio |
| 8 | Eliminar modulos duplicados (funcionarios/employee, etc.) | Tech debt | Medio |
| 9 | Adicionar type hints aos ~40% de models sem | Code quality | Medio |
| 10 | Reduzir 251 !important no CSS para max 30 | Manutenibilidade | Medio |

### 8.3 BAIXA PRIORIDADE (Evolucao)

| # | Acao | Impacto | Esforco |
|---|------|---------|---------|
| 11 | Implementar cache layer (Redis/APCu) | Performance | Alto |
| 12 | Adicionar responsividade aos 56% de views sem | Mobile UX | Alto |
| 13 | Implementar rate limiting na API | Seguranca | Medio |
| 14 | Criar AbstractModel base class | Padronizacao | Medio |
| 15 | Implementar message queue para WebSocket | Confiabilidade | Alto |

---

## 9. Roadmap Sugerido

### Q1 2026 (Marco)
- [ ] Atualizar documentacao (CLAUDE.md, Chat v2.0, API REST)
- [ ] Corrigir AdmsCampoVazio nos 10 models mais criticos
- [ ] Adicionar LoggerService aos CRUD models de modulos ativos

### Q2 2026 (Abril-Junho)
- [ ] Pesquisar substituicao do Ratchet (Amphp/Swoole/Workerman)
- [ ] Migrar 20 modulos legados mais usados para padrao moderno
- [ ] Eliminar modulos duplicados (funcionarios, transferencia, treinamento)
- [ ] Migrar search models legados para nomenclatura moderna

### Q3 2026 (Julho-Setembro)
- [ ] Implementar cache layer (Redis/APCu)
- [ ] Migrar restante dos modulos legados
- [ ] Refatorar CSS (reduzir !important, mobile-first)
- [ ] Adicionar rate limiting na API

### Q4 2026 (Outubro-Dezembro)
- [ ] Executar migracao WebSocket (Ratchet -> nova lib)
- [ ] Implementar responsividade completa
- [ ] Audit de seguranca completa
- [ ] Cobertura de testes para modulos novos

---

## 10. Metricas de Progresso

### Modernizacao por Area

```
Controllers:  [=========>---------]  43% moderno
Models:       [===========>-------]  60% moderno
Services:     [====================] 100% moderno
Views:        [========>----------]  44% moderno (responsivo)
JavaScript:   [===========>-------]  57% moderno
Search:       [=========>---------]  50% moderno
CSS:          [======>------------]  30% ideal (!important)
Docs:         [===========>-------]  62% atualizado
Testes:       [====================] 100% passando (3899)
Seguranca:    [=================>-]  82% score
```

### Evolucao (vs Analise Anterior)

| Metrica | Antes (Out/2025) | Agora (Fev/2026) | Delta |
|---------|-----------------|-------------------|-------|
| Score Seguranca | 5.8/10 | 8.2/10 | +2.4 |
| Controllers Modernos | ~30% | 43% | +13% |
| Services | ~20 | 39 | +19 |
| Testes | ~2000 | 3899 | +1899 |
| WebSocket | Nenhum | Implementado | Novo |
| API REST | Nenhum | 40+ endpoints | Novo |
| Sync Produtos | Nenhum | 4 fases | Novo |

---

**Gerado por:** Claude Code - Analise Automatizada
**Baseado em:** 5 agentes de analise paralela (Controllers, JS, Services/Models, Views/CSS/Core, Docs)
**Proxima revisao:** Marco 2026
