# Arquitetura do Projeto Mercury

**Versao:** 1.0
**Data:** 22 de Marco de 2026

> **ATENCAO: Este documento descreve a arquitetura do Mercury v1 (PHP MVC + Bootstrap).**
> O projeto foi migrado para **Laravel 12 + React 18 + Inertia.js 2**.
> Para a arquitetura atual, consulte:
> - [`CLAUDE.md`](../CLAUDE.md) — Arquitetura completa do Mercury Laravel
> - [`docs/PADRONIZACAO.md`](PADRONIZACAO.md) — Padroes de codificacao v3
> - [`docs/GUIA_IMPLEMENTACAO_MODULOS.md`](GUIA_IMPLEMENTACAO_MODULOS.md) — Guia de implementacao v3

---

## 1. Visao Geral (v1 — LEGADO)

O Mercury e um sistema MVC em PHP 8.0+ para gestao empresarial do Grupo Meia Sola.

| Componente | Tecnologia |
|------------|-----------|
| **Backend** | PHP 8.0+ com type hints |
| **Banco Principal** | MySQL com PDO (prepared statements) |
| **Banco ERP** | PostgreSQL (Cigam ERP) |
| **Frontend** | Bootstrap 4.6.1 + Vanilla JavaScript (ES6+) |
| **API REST** | JWT (firebase/php-jwt) com rate limiting |
| **Real-time** | Ratchet 0.4 WebSocket + ReactPHP |
| **Testes** | PHPUnit 12.4 (~3.900 testes) |

### Numeros do Projeto

- 678 controllers, 617 models, 782 views
- 91 arquivos JavaScript, 54 services, 44 helpers
- 72 models de busca, 40+ endpoints REST

---

## 2. Ciclo de Vida da Requisicao (Web)

```
HTTP Request
    |
    v
index.php
    |
    v
Config.php
    |  - session_start() com hardening (HTTPOnly, SameSite, strict)
    |  - Carrega constantes (URLADM, CONTROLER, METODO)
    |  - Inicializa CSRF token
    |
    v
ConfigController::__construct()
    |  - Extrai URL: ?url=controller/method/param
    |  - Converte slug: kebab-case -> PascalCase (controller)
    |  - Converte slug: kebab-case -> camelCase (metodo)
    |
    v
ConfigController::carregar()
    |  - Consulta adms_paginas (rota + permissao)
    |  - Resolve namespace: \App\{tipo_tpg}\Controllers\{Controller}
    |
    v
Middleware Chain (carregarMetodo)
    |  1. validateCsrf()           - Valida token CSRF (POST/PUT/DELETE)
    |  2. validateUserSession()    - Verifica logout forcado
    |  3. checkForcePasswordChange() - Redireciona se senha expirada
    |  4. trackCurrentPage()       - Monitora pagina atual (real-time)
    |
    v
Controller::{metodo}($parametro)
    |  - Logica de negocios
    |  - Interage com Models/Services
    |
    v
ConfigView::carregar()
    |  - Protecao contra path traversal
    |  - Renderiza: header + sidebar + content + footer
    |
    v
HTTP Response (HTML)
```

### Formato de URL

```
?url=store-goals/edit/42
      |            |    |
      |            |    +-- Parametro: 42
      |            +------- Metodo: edit -> camelCase
      +-------------------- Controller: StoreGoals (PascalCase)
```

---

## 3. Ciclo de Vida da Requisicao (API)

```
HTTP Request (api.php)
    |
    v
ApiRouter::__construct()
    |  - Registra rotas com regex patterns
    |  - Extrai parametros nomeados ({id})
    |
    v
ApiRateLimiter
    |  - 60 requisicoes / 60 segundos por IP
    |  - Retorna 429 Too Many Requests se exceder
    |
    v
ApiAuthMiddleware (rotas protegidas)
    |  - Valida JWT (HS256)
    |  - Extrai user_id do payload
    |  - Popula ApiRequest com dados do usuario
    |
    v
Controller::{action}(ApiRequest $request)
    |
    v
ApiResponse (JSON)
    |  - Formato padrao: { success, data, message, errors }
    |  - HTTP status codes semanticos
```

### Rotas Disponiveis

| Recurso | Endpoints | Autenticacao |
|---------|-----------|-------------|
| Auth | `POST login`, `POST refresh` | Nao |
| Tickets | CRUD completo + interactions | JWT |
| Adjustments | CRUD + statistics + items | JWT |
| Transfers | CRUD + pickup/delivery/receipt/history | JWT |
| Sales | Listagem + statistics + by-store/consultant | JWT |
| Employees | CRUD completo | JWT |

---

## 4. Camada de Roteamento

### Roteamento Web (DB-driven)

O roteamento e controlado pela tabela `adms_paginas`:

```sql
-- Cada rota e um registro no banco
SELECT * FROM adms_paginas
WHERE controller_pg = 'StoreGoals'
  AND metodo_pg = 'index';
```

| Coluna | Funcao |
|--------|--------|
| `controller_pg` | Nome do controller (PascalCase) |
| `metodo_pg` | Nome do metodo |
| `tipo_tpg` | Namespace do modulo (`adms`, `cpadms`) |
| `lib_pub` | 1 = pagina publica (sem login) |

### Verificacao de Permissao

```sql
-- Permissoes por nivel de acesso
SELECT * FROM adms_nivacs_pgs
WHERE adms_pagina_id = :pagina_id
  AND adms_niveis_acesso_id = :nivel_acesso;
```

Os botoes de acao (adicionar, editar, deletar) sao controlados por `AdmsBotao`, que verifica se o nivel de acesso do usuario tem permissao para cada acao via `adms_nivacs_pgs`.

---

## 5. Camada de Banco de Dados

### Helpers MySQL (Principal)

Todas as operacoes usam prepared statements via PDO:

| Helper | Funcao | Caracteristica |
|--------|--------|---------------|
| `AdmsConn` | Factory de conexao PDO | Singleton, charset utf8mb4 |
| `AdmsRead` | SELECT | `fullRead()` com parametros no formato `"key=value"` |
| `AdmsCreate` | INSERT | Validacao automatica de colunas |
| `AdmsUpdate` | UPDATE | Validacao obrigatoria de WHERE clause |
| `AdmsDelete` | DELETE | WHERE obrigatorio (previne DELETE sem filtro) |
| `AdmsPaginacao` | Paginacao | Calculo automatico de offset/limit |

### Formato de Parametros

```php
// Formato string: "chave1=valor1&chave2=valor2"
$read = new AdmsRead();
$read->fullRead(
    "SELECT * FROM adms_usuarios WHERE id = :id AND loja_id = :loja",
    "id={$id}&loja={$lojaId}"
);
$resultado = $read->getResultado();
```

### Helpers PostgreSQL (Cigam ERP)

| Helper | Funcao |
|--------|--------|
| `AdmsConnCigam` | Conexao PDO com PostgreSQL |
| `AdmsReadCigam` | Leitura de dados do ERP Cigam |

### Traits Disponiveis

| Trait | Funcao |
|-------|--------|
| `JsonResponseTrait` | Respostas JSON padronizadas para AJAX |
| `MoneyConverterTrait` | Converte "1.234,56" para 1234.56 |
| `FinancialPermissionTrait` | Filtro de permissao financeira |
| `StorePermissionTrait` | Filtro de dados por loja do usuario |

---

## 6. Autenticacao e Autorizacao

### 6.1 Web: Sessao

```
Login -> session_start() com hardening
    |
    +-- HTTPOnly cookies (sem acesso JS)
    +-- SameSite=Strict
    +-- session.use_strict_mode
    +-- Regeneracao de session_id no login
```

O acesso a sessao e abstraido pela camada de servicos:

| Servico | Responsabilidade |
|---------|-----------------|
| `SessionContext` | Leitura/escrita tipada de `$_SESSION` |
| `PermissionService` | Verificacoes de nivel (isSuperAdmin, isAdmin, etc.) |
| `AuthenticationService` | Login, logout, verificacao de autenticacao |

### 6.2 API: JWT

| Token | Algoritmo | TTL | Armazenamento |
|-------|-----------|-----|--------------|
| Access Token | HS256 | 1 hora | Cliente (header Authorization) |
| Refresh Token | Opaco | 7 dias | Hash SHA-256 no banco |

### 6.3 CSRF: Deploy 5 (Global)

Protecao ativa para **todos** os controllers do sistema:

```
Requisicao POST/PUT/DELETE
    |
    v
Excecoes automaticas?
    |  - GET/HEAD/OPTIONS -> Skip
    |  - Pagina publica (lib_pub=1) -> Skip
    |  - Login inicial -> Skip
    |  - Heartbeat (ping) -> Skip
    |
    v (nao e excecao)
Validar token CSRF
    |  1. POST body (_csrf_token)
    |  2. JSON body (_csrf_token)
    |  3. Header X-CSRF-Token
    |
    +-- Valido -> Prossegue
    +-- Invalido -> 403 (AJAX: JSON / Form: redirect)
```

Caracteristicas do token:
- Vinculado ao `session_id`
- Expiracao de 60 minutos
- Gerado por `CsrfService`

---

## 7. Padroes de Controllers

### 7.1 Moderno (match expression)

Padrao recomendado para novos modulos. Referencia: `Sales.php`.

```php
class Sales
{
    public function index(?string $param = null): void
    {
        $action = match ($param) {
            null        => 'list',
            'create'    => 'create',
            'edit'      => 'edit',
            'delete'    => 'delete',
            'view'      => 'view',
            default     => 'list',
        };

        $this->{$action}();
    }
}
```

### 7.2 AbstractConfigController

Base para modulos de configuracao/lookup (13 modulos migrados). O subcontroller define um array `MODULE` com toda a configuracao:

```php
class Holidays extends AbstractConfigController
{
    protected const MODULE = [
        'table'          => 'adms_holidays',
        'singular'       => 'Feriado',
        'plural'         => 'Feriados',
        'id_field'       => 'id',
        'name_field'     => 'name',
        'list_query'     => 'SELECT ...',
        'routes'         => ['list' => 'holidays/index', ...],
        'views'          => ['load' => 'holidays/loadHolidays', ...],
    ];
}
```

### 7.3 Legacy (if/elseif)

Modulos antigos com metodos em portugues. ~31% dos controllers:

```php
// Padrao legado - NAO usar em novos modulos
public function listar() { ... }
public function editar($id) { ... }
public function apagar($id) { ... }
```

### Distribuicao de Maturidade

| Nivel | Percentual | Caracteristicas |
|-------|-----------|----------------|
| Moderno | 43% | match expressions, type hints, services |
| Parcial | 26% | Alguns padroes modernos, precisa refatoracao |
| Legacy | 31% | if/elseif, nomes em portugues, page-reload |

---

## 8. Padroes de Models

### Nomenclatura

| Prefixo | Funcao | Exemplo |
|---------|--------|---------|
| `Adms{Entity}` | CRUD principal | `AdmsSale.php` |
| `AdmsList{Entities}` | Listagem com paginacao | `AdmsListSales.php` |
| `AdmsStatistics{Entities}` | Cards de estatisticas | `AdmsStatisticsSales.php` |
| `AdmsView{Entity}` | Visualizacao detalhada | `AdmsViewSale.php` |

### Validacao

```php
// CORRETO: Validacao explicita de campos obrigatorios
if (empty($data['name'])) {
    $this->errors[] = 'O campo Nome e obrigatorio.';
}

// EVITAR: AdmsCampoVazio (verifica apenas se vazio, nao quais campos)
```

### Integracao com LoggerService

```php
LoggerService::info('SALE_CREATED', 'Venda criada', ['sale_id' => $id]);
LoggerService::error('SALE_FAILED', 'Falha ao criar venda', ['data' => $data]);
```

---

## 9. Padroes de Views

### Estrutura de Arquivos

```
Views/entityName/
    |-- loadEntityName.php          # Pagina principal (carrega JS, stats, container)
    |-- listEntityName.php          # Tabela AJAX (recarregavel via fetch)
    |-- partials/
        |-- _add_entity_name_modal.php
        |-- _edit_entity_name_modal.php
        |-- _view_entity_name_modal.php
        |-- _delete_entity_name_modal.php
```

### Convencoes

- Diretorios: **camelCase** (`entityName/`)
- Arquivos load/list: **camelCase** (`loadEntityName.php`)
- Partials (modals): **_snake_case** (`_add_entity_name_modal.php`)

### Protecao XSS

```php
<!-- SEMPRE escapar dados do usuario -->
<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>
```

### Renderizacao

`ConfigView` monta a pagina completa:

```
header.php -> sidebar.php -> {content view} -> footer.php
```

Com protecao contra path traversal no caminho da view.

---

## 10. Services (54 arquivos)

### Core

| Servico | Funcao |
|---------|--------|
| `SessionContext` | Abstracao tipada de `$_SESSION` |
| `AuthenticationService` | Login, logout, verificacao de sessao |
| `PermissionService` | Verificacoes de nivel de acesso |
| `CsrfService` | Geracao e validacao de tokens CSRF |
| `LoggerService` | Log estruturado de operacoes |
| `PasswordService` | Hashing, validacao de politica de senhas |

### Chat e Real-time

| Servico | Funcao |
|---------|--------|
| `ChatService` | Mensagens diretas, contagem de nao lidas |
| `GroupChatService` | Chat em grupo |
| `BroadcastService` | Mensagens de broadcast |
| `WebSocketService` | Gerenciamento de conexoes WebSocket |
| `WebSocketTokenService` | JWT para autenticacao WebSocket (TTL 5min) |
| `WebSocketNotifier` | Fire-and-forget para IPC interno (porta 8081) |

### Notificacoes

| Servico | Funcao |
|---------|--------|
| `NotificationService` | Notificacoes in-app |
| `NotificationRecipientService` | Resolucao de destinatarios |
| `SystemNotificationService` | Notificacoes via WebSocket |

### Logica de Negocios

| Servico | Funcao |
|---------|--------|
| `BudgetService` | Orcamentos |
| `ChecklistService` / `ChecklistServiceBusiness` | Checklists de loja |
| `StockMovementSyncService` | Sincronizacao de movimentacao com Cigam |
| `StockMovementAlertService` | Alertas de estoque |
| `TravelExpenseService` | Despesas de viagem |
| `StoreGoalsRedistributionService` | Redistribuicao de metas |
| `OrderPaymentAllocationService` | Alocacao de pagamentos |
| `OrderPaymentTransitionService` | Maquina de estados de pagamentos |
| `VacationPeriodGeneratorService` | Geracao de periodos aquisitivos (CLT) |
| `VacationStatusTransitionService` | Fluxo de aprovacao de ferias |
| `VacationValidatorService` | Validacoes de ferias (CLT) |
| `VacationCalculationService` | Calculos de dias e proporcionalidades |
| `AuditStateMachineService` | Maquina de estados de auditoria |
| `ReversalTransitionService` | Transicoes de estorno |

### Dados e Arquivos

| Servico | Funcao |
|---------|--------|
| `FormSelectRepository` | Populacao de selects (dropdowns) |
| `SelectCacheService` | Cache de dados de selects |
| `ExportService` | Exportacao (Excel via PhpSpreadsheet) |
| `ImportService` | Importacao de dados (CSV/Excel) |
| `FileUploadService` | Upload generico de arquivos |
| `ImageUploadConfig` / `UploadConfig` | Configuracao de uploads |
| `TextExtractionService` | Extracao de texto de documentos |
| `ProductLookupService` | Busca de produtos no Cigam |

### Relatorios e Integracao

| Servico | Funcao |
|---------|--------|
| `StockAuditReportService` | Relatorios PDF de auditoria (DomPDF) |
| `StockAuditCigamService` | Integracao auditoria com Cigam |
| `StockAuditRandomSelectionService` | Selecao aleatoria para auditoria |
| `StatisticsService` | Estatisticas genericas |
| `Ean13Generator` | Geracao de codigos de barras EAN-13 |
| `RecordLockService` | Lock otimista de registros |
| `GoogleOAuthService` | Autenticacao OAuth com Google |

### Email

| Servico | Funcao |
|---------|--------|
| `HelpdeskEmailService` | Emails do helpdesk |
| `HelpdeskChatNotifier` | Notificacoes de chat do helpdesk |
| `ChecklistEmailService` | Emails de checklist |
| `StoreGoalEmailService` | Emails de metas de loja |
| `TrainingEmailService` | Emails de treinamento |
| `TrainingQRCodeService` | QR codes para treinamento |

---

## 11. Seguranca

| Camada | Implementacao | Cobertura |
|--------|--------------|-----------|
| **SQL Injection** | Prepared statements (PDO) em todos os helpers | 9.5/10 |
| **CSRF** | Deploy 5 global, token vinculado a sessao, 60min TTL | 9/10 |
| **XSS** | `htmlspecialchars()` obrigatorio em views | Alto |
| **Sessao** | HTTPOnly, SameSite=Strict, strict mode, regeneracao | 8/10 |
| **Upload** | Validacao de tipo MIME, extensao, tamanho maximo | Alto |
| **Rate Limiting** | 60 req/60s por IP na API REST | API |
| **JWT** | HS256, TTL 1h (access), 7d (refresh), hash SHA-256 | API |
| **Senhas** | bcrypt, politica de 12+ caracteres | 8/10 |
| **WHERE obrigatorio** | `AdmsUpdate` e `AdmsDelete` rejeitam queries sem WHERE | Banco |

### Score Geral de Seguranca: 8.2/10

---

## 12. Estrutura de Diretorios

```
mercury/
|-- .claude/                        # Configuracao Claude Code
|   |-- CLAUDE.md                   # Indice de documentacao
|   +-- REGRAS_DESENVOLVIMENTO.md   # Regras obrigatorias
|
|-- app/
|   |-- adms/                       # Modulo administrativo principal
|   |   |-- Controllers/            # 678 controllers (PascalCase)
|   |   |   |-- Api/V1/            # Controllers da API REST
|   |   |   +-- AbstractConfigController.php
|   |   |-- Models/                 # 617 models (prefixo Adms)
|   |   |   +-- helper/            # 44 helpers + traits
|   |   |-- Views/                  # 782 views (camelCase)
|   |   +-- Services/              # 54 services
|   +-- cpadms/                     # Modulo de busca
|       +-- Models/                 # 72 models de busca
|
|-- core/                           # Framework core
|   |-- ConfigController.php        # Roteamento + middleware
|   |-- ConfigView.php              # Renderizacao de views
|   |-- Config.php                  # Constantes + sessao
|   |-- EnvLoader.php               # Variaveis de ambiente
|   +-- Api/                        # Framework REST API
|       |-- ApiRouter.php           # Roteador de API
|       |-- BaseApiController.php   # Controller base da API
|       |-- JwtService.php          # Gerenciamento JWT
|       |-- ApiRateLimiter.php      # Rate limiting
|       |-- ApiAuthMiddleware.php   # Middleware de autenticacao
|       |-- ApiRequest.php          # Objeto de requisicao
|       +-- ApiResponse.php         # Objeto de resposta
|
|-- bin/
|   +-- websocket-server.php        # Entry point WebSocket
|
|-- assets/
|   |-- css/personalizado.css       # CSS customizado
|   |-- js/                         # 91 arquivos (kebab-case)
|   |-- imagens/
|   +-- fonts/
|
|-- database/
|   +-- migrations/                 # Migracoes SQL
|
|-- tests/                          # ~3.900 testes (PHPUnit 12.4)
|
|-- docs/                           # Documentacao
|
+-- vendor/                         # Dependencias Composer
```

---

## 13. WebSocket (Chat v2.0)

### Arquitetura

```
Cliente (Browser)
    |
    | wss:// (porta 8080)
    |
    v
Ratchet WebSocket Server
    |  - Autenticacao via JWT (query param)
    |  - TTL do token: 5 minutos
    |  - Auto-reconnect exponencial no cliente
    |
    v
WebSocketService
    |  - Gerencia conexoes ativas
    |  - Roteamento de mensagens
    |
    +-----> ChatService (mensagens diretas)
    +-----> GroupChatService (grupos)
    +-----> BroadcastService (broadcasts)

Controller PHP (operacao CRUD)
    |
    | HTTP POST (porta 8081, interno)
    |
    v
ReactPHP HTTP Server (IPC)
    |
    v
Push para clientes conectados
```

### Fluxo de Notificacao

1. Controller executa operacao (ex: criar venda)
2. `WebSocketNotifier::send()` envia curl fire-and-forget para porta 8081
3. ReactPHP recebe e faz push para clientes conectados
4. Cliente recebe via `MercuryWS.on('notification.new')`

---

## 14. Integracao Cigam (ERP)

### Sincronizacao de Produtos

```
Fase 1: Lookups (categorias, marcas, colecoes)
    |
Fase 2: Produtos (chunks de 1000 registros)
    |
Fase 3: Precos (com historico)
    |
Fase 4: Finalizacao (logs, CSV de rejeitados)
```

- Conexao via `AdmsConnCigam` (PostgreSQL)
- Leitura via `AdmsReadCigam`
- `ProductLookupService` para busca de produtos
- CSV de rejeitados salvo em `uploads/import_errors/`

---

## 15. Dependencias Externas

| Pacote | Versao | Uso |
|--------|--------|-----|
| `firebase/php-jwt` | - | Autenticacao JWT (API + WebSocket) |
| `cboden/ratchet` | 0.4 | Servidor WebSocket |
| `react/http` | - | Servidor HTTP interno (IPC) |
| `phpmailer/phpmailer` | - | Envio de emails |
| `ramsey/uuid` | - | Geracao de UUID v7 |
| `dompdf/dompdf` | 3.0 | Geracao de PDF |
| `phpoffice/phpspreadsheet` | 5.3 | Importacao/exportacao Excel |
| `phpunit/phpunit` | 12.4 | Testes unitarios |

---

## 16. Observacoes Importantes

### Gotchas Conhecidas

1. **MySQL 8 Collation:** Sempre usar `COLLATE=utf8mb4_unicode_ci` em `CREATE TABLE`. O default `utf8mb4_0900_ai_ci` quebra UNION com tabelas existentes.

2. **AdmsUpdate::getResult():** Retorna `false` quando 0 rows sao afetadas. Isso nao e erro - significa que os dados enviados sao iguais aos existentes.

3. **AdmsRead::exeInstruction():** Engole `PDOException` silenciosamente. Testar queries complexas (UNION, etc.) diretamente ao debugar.

4. **AdmsCampoVazio:** Verifica apenas se campo esta vazio, NAO quais campos sao obrigatorios. Usar validacao explicita no model.

5. **DomPDF com tabelas grandes:** Tabelas com 500+ linhas esgotam memoria (Cellmap O(n^2)). Dividir em chunks de ~200 linhas.

6. **Formato de parametros DB:** Usar formato string `"key1=value1&key2=value2"`, nao array.

7. **Store selects:** Usar chaves `l_id` e `store_name` (nao `id` e `name`).

8. **STOREPERMITION:** Constante = 18 no bootstrap.php (nao 2 como testes antigos assumem).
