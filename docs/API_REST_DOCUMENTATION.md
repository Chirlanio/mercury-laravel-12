# Mercury REST API — Documentacao Completa

**Versao:** 2.0
**Data:** 18 de Fevereiro de 2026
**Base URL:** `http://localhost/mercury/api/v1`

---

## Indice

1. [Visao Geral](#1-visao-geral)
2. [Arquitetura](#2-arquitetura)
3. [Configuracao e Instalacao](#3-configuracao-e-instalacao)
4. [Autenticacao (JWT)](#4-autenticacao-jwt)
5. [Formato de Resposta](#5-formato-de-resposta)
6. [Codigos de Erro](#6-codigos-de-erro)
7. [Rate Limiting](#7-rate-limiting)
8. [CORS](#8-cors)
9. [Endpoints — Autenticacao](#9-endpoints--autenticacao)
   - 9.1 [Login](#91-login)
   - 9.2 [Refresh Token](#92-refresh-token)
10. [Endpoints — Tickets](#10-endpoints--tickets)
    - 10.1 [Listar Tickets](#101-listar-tickets)
    - 10.2 [Criar Ticket](#102-criar-ticket)
    - 10.3 [Visualizar Ticket](#103-visualizar-ticket)
    - 10.4 [Atualizar Ticket](#104-atualizar-ticket)
11. [Endpoints — Interacoes](#11-endpoints--interacoes)
    - 11.1 [Listar Interacoes](#111-listar-interacoes)
    - 11.2 [Adicionar Comentario](#112-adicionar-comentario)
12. [Endpoints — Ajustes de Estoque](#12-endpoints--ajustes-de-estoque)
13. [Endpoints — Transferencias](#13-endpoints--transferencias)
14. [Endpoints — Vendas](#14-endpoints--vendas)
15. [Endpoints — Funcionarios](#15-endpoints--funcionarios)
16. [Controle de Acesso por Loja](#16-controle-de-acesso-por-loja)
17. [Guia Rapido (Quick Start)](#17-guia-rapido-quick-start)
18. [Exemplos de Integracao](#18-exemplos-de-integracao)
19. [Estrutura de Arquivos](#19-estrutura-de-arquivos)
20. [Guia para Novos Endpoints](#20-guia-para-novos-endpoints)
21. [Troubleshooting](#21-troubleshooting)

---

## 1. Visao Geral

A Mercury REST API fornece acesso programatico aos modulos do portal Mercury: Helpdesk, Ajustes de Estoque, Transferencias, Vendas e Funcionarios. Projetada para integracao com aplicativos mobile, sistemas de terceiros e automacoes.

### Caracteristicas

- **Autenticacao JWT** com access token (1h) e refresh token (7 dias)
- **Rotacao de tokens** automatica (refresh invalida o token anterior)
- **Rate limiting** por IP (60 req/min configuravel)
- **CORS** configuravel para permitir chamadas cross-origin
- **Controle de acesso por loja** — mesma logica do portal web
- **Logging** completo de todas as operacoes via LoggerService
- **Formato JSON** padronizado em todas as respostas

### Tabela de Endpoints

| Metodo | Endpoint | Auth | Descricao |
|--------|----------|------|-----------|
| `POST` | `/api/v1/auth/login` | Nao | Autenticar e obter tokens |
| `POST` | `/api/v1/auth/refresh` | Nao | Renovar par de tokens |
| `GET` | `/api/v1/tickets` | JWT | Listar tickets (paginado) |
| `POST` | `/api/v1/tickets` | JWT | Criar ticket |
| `GET` | `/api/v1/tickets/{id}` | JWT | Visualizar ticket completo |
| `PUT` | `/api/v1/tickets/{id}` | JWT | Atualizar ticket |
| `GET` | `/api/v1/tickets/{id}/interactions` | JWT | Listar interacoes do ticket |
| `POST` | `/api/v1/tickets/{id}/interactions` | JWT | Adicionar comentario |
| **Ajustes de Estoque** | | | |
| `GET` | `/api/v1/adjustments` | JWT | Listar ajustes (paginado) |
| `GET` | `/api/v1/adjustments/statistics` | JWT | Estatisticas de ajustes |
| `POST` | `/api/v1/adjustments` | JWT | Criar ajuste |
| `GET` | `/api/v1/adjustments/{id}` | JWT | Ver ajuste (header + items + summary) |
| `PUT` | `/api/v1/adjustments/{id}` | JWT | Atualizar ajuste |
| `DELETE` | `/api/v1/adjustments/{id}` | JWT | Deletar ajuste (so Pendente) |
| `GET` | `/api/v1/adjustments/{id}/items` | JWT | Listar itens do ajuste |
| **Transferencias** | | | |
| `GET` | `/api/v1/transfers` | JWT | Listar transferencias (paginado) |
| `GET` | `/api/v1/transfers/statistics` | JWT | Estatisticas de transferencias |
| `POST` | `/api/v1/transfers` | JWT | Criar transferencia |
| `GET` | `/api/v1/transfers/{id}` | JWT | Ver transferencia |
| `PUT` | `/api/v1/transfers/{id}` | JWT | Atualizar transferencia |
| `DELETE` | `/api/v1/transfers/{id}` | JWT | Deletar transferencia (so Pendente) |
| `POST` | `/api/v1/transfers/{id}/pickup` | JWT | Confirmar coleta |
| `POST` | `/api/v1/transfers/{id}/delivery` | JWT | Confirmar entrega |
| `POST` | `/api/v1/transfers/{id}/receipt` | JWT | Confirmar recebimento |
| `GET` | `/api/v1/transfers/{id}/history` | JWT | Historico de status |
| **Vendas** | | | |
| `GET` | `/api/v1/sales` | JWT | Listar vendas (paginado) |
| `GET` | `/api/v1/sales/statistics` | JWT | Estatisticas do mes |
| `GET` | `/api/v1/sales/by-store` | JWT | Relatorio por loja |
| `GET` | `/api/v1/sales/by-consultant` | JWT | Relatorio por consultor |
| `GET` | `/api/v1/sales/consultants` | JWT | Listar consultores do mes |
| **Funcionarios** | | | |
| `GET` | `/api/v1/employees` | JWT | Listar funcionarios (paginado) |
| `POST` | `/api/v1/employees` | JWT | Criar funcionario |
| `GET` | `/api/v1/employees/{id}` | JWT | Ver funcionario completo |
| `PUT` | `/api/v1/employees/{id}` | JWT | Atualizar funcionario |
| `DELETE` | `/api/v1/employees/{id}` | JWT | Deletar funcionario |
| `GET` | `/api/v1/employees/{id}/contracts` | JWT | Listar contratos |
| `GET` | `/api/v1/employees/{id}/schedule` | JWT | Escala de trabalho atual |

---

## 2. Arquitetura

A API usa um entry point separado (`api.php`) do portal web (`index.php`), garantindo que sessoes PHP e CSRF nao interfiram nas chamadas REST.

```
Requisicao HTTP
    |
    v
.htaccess (RewriteRule ^api/ -> api.php)
    |
    v
api.php (constantes, autoloader, sem session_start)
    |
    v
CorsHandler::handle() -> headers CORS + OPTIONS 204
    |
    v
ApiRouter::dispatch()
    |-- LoggerService::info() .............. log da requisicao
    |-- ApiRateLimiter::check() ............ verifica limite por IP
    |-- ApiAuthMiddleware::authenticate() .. valida JWT, popula $_SESSION
    |-- Controller::action() ............... executa logica de negocios
    v
ApiResponse::success() ou ::error() -> JSON
```

### Componentes (`core/Api/`)

| Arquivo | Responsabilidade |
|---------|------------------|
| `CorsHandler.php` | Headers CORS e preflight OPTIONS |
| `ApiResponse.php` | Respostas JSON padronizadas |
| `ApiRequest.php` | Parser de body JSON, query params, Bearer token |
| `JwtService.php` | Geracao/validacao JWT + refresh tokens |
| `ApiAuthMiddleware.php` | Valida Bearer token, popula `$_SESSION` in-memory |
| `ApiRateLimiter.php` | Rate limiting por IP (tabela `api_rate_limits`) |
| `BaseApiController.php` | Classe base com `canViewAll()`, `canViewFinancial()`, `validateRequired()` |
| `ApiRouter.php` | Roteador com regex, dispatch de rotas |

---

## 3. Configuracao e Instalacao

### 3.1 Dependencia

```bash
composer require firebase/php-jwt
```

### 3.2 Variaveis de Ambiente (`.env`)

```ini
# JWT / API REST
JWT_SECRET=chave_secreta_com_pelo_menos_32_caracteres
JWT_ACCESS_TTL=3600        # Access token: 1 hora (segundos)
JWT_REFRESH_TTL=604800     # Refresh token: 7 dias (segundos)
JWT_ISSUER=mercury-api     # Emissor do token
JWT_ALGORITHM=HS256        # Algoritmo de assinatura
API_RATE_LIMIT=60          # Requisicoes por janela
API_RATE_WINDOW=60         # Janela em segundos
API_CORS_ORIGINS=*         # Origens permitidas (* = todas)
```

> **IMPORTANTE:** Em producao, `JWT_SECRET` deve ser uma string aleatoria de pelo menos 32 caracteres. Gere com: `openssl rand -hex 32`

### 3.3 Tabelas no Banco de Dados

Execute a migration:

```bash
mysql -u root nome_do_banco < database/migrations/2026_02_17_create_api_tables.sql
```

Tabelas criadas:

- **`api_tokens`** — Armazena hash SHA-256 dos refresh tokens
- **`api_rate_limits`** — Contadores de rate limiting por IP/endpoint

### 3.4 Apache (.htaccess)

As regras ja estao configuradas no `.htaccess` do projeto:

```apache
# Preservar header Authorization
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

# API Routes -> api.php
RewriteRule ^api/(.*)$ api.php?route=$1 [QSA,L]
```

---

## 4. Autenticacao (JWT)

A API utiliza autenticacao baseada em **JSON Web Tokens (JWT)**. O fluxo completo:

```
1. POST /api/v1/auth/login  ->  access_token (JWT, 1h) + refresh_token (opaco, 7d)
2. Usar access_token no header Authorization: Bearer <token>
3. Quando expirar, POST /api/v1/auth/refresh com refresh_token
4. Novo par de tokens emitido, refresh_token anterior revogado
```

### Access Token (JWT)

- **Tipo:** JWT assinado com HS256
- **TTL:** 1 hora (configuravel via `JWT_ACCESS_TTL`)
- **Envio:** Header `Authorization: Bearer <access_token>`

**Payload decodificado:**

```json
{
  "iss": "mercury-api",
  "sub": 123,
  "iat": 1739999000,
  "exp": 1740002600,
  "type": "access",
  "user": {
    "id": 123,
    "nome": "Joao Silva",
    "email": "joao@email.com",
    "loja_id": 5,
    "niveis_acesso_id": 2,
    "ordem_nivac": 2
  }
}
```

### Refresh Token

- **Tipo:** String opaca de 64 caracteres hexadecimais
- **TTL:** 7 dias (configuravel via `JWT_REFRESH_TTL`)
- **Armazenamento:** Hash SHA-256 salvo na tabela `api_tokens`
- **Rotacao:** Ao usar o refresh, o token anterior e automaticamente revogado

### Fluxo de Renovacao

```
access_token expira (1h)
         |
         v
POST /api/v1/auth/refresh  { "refresh_token": "abc..." }
         |
         v
Token antigo revogado -> novo access_token + novo refresh_token
```

---

## 5. Formato de Resposta

Todas as respostas seguem o formato JSON padronizado:

### Sucesso

```json
{
  "success": true,
  "data": { ... },
  "error": null
}
```

### Sucesso com Paginacao

```json
{
  "success": true,
  "data": [ ... ],
  "error": null,
  "meta": {
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 45,
      "total_pages": 3,
      "has_next": true,
      "has_prev": false
    }
  }
}
```

### Erro

```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "Descricao do erro",
    "code": "ERROR_CODE",
    "details": { ... }
  }
}
```

---

## 6. Codigos de Erro

| HTTP | Codigo | Descricao |
|------|--------|-----------|
| 400 | `BAD_REQUEST` | Requisicao mal formatada |
| 400 | `INVALID_ID` | ID invalido na URL |
| 401 | `AUTH_REQUIRED` | Token ausente no header |
| 401 | `AUTH_INVALID_TOKEN` | Token expirado ou invalido |
| 401 | `AUTH_INVALID_CREDENTIALS` | Usuario ou senha incorretos |
| 401 | `AUTH_INVALID_REFRESH` | Refresh token invalido ou revogado |
| 403 | `FORBIDDEN` | Sem permissao para acessar o recurso |
| 404 | `NOT_FOUND` | Recurso ou endpoint nao encontrado |
| 422 | `VALIDATION_ERROR` | Campos obrigatorios ausentes |
| 422 | `CREATE_FAILED` | Falha ao criar recurso |
| 422 | `UPDATE_FAILED` | Falha ao atualizar recurso |
| 422 | `DELETE_FAILED` | Falha ao deletar recurso |
| 422 | `PICKUP_FAILED` | Falha ao confirmar coleta |
| 422 | `DELIVERY_FAILED` | Falha ao confirmar entrega |
| 422 | `RECEIPT_FAILED` | Falha ao confirmar recebimento |
| 500 | `STATS_ERROR` | Falha ao calcular estatisticas |
| 429 | `RATE_LIMIT_EXCEEDED` | Limite de requisicoes excedido |
| 500 | `INTERNAL_ERROR` | Erro interno do servidor |

---

## 7. Rate Limiting

Toda requisicao a API e contabilizada por IP. Os limites sao configurados via `.env`:

- **Limite:** 60 requisicoes por janela (padrao)
- **Janela:** 60 segundos (padrao)

### Headers de Rate Limit

Toda resposta inclui:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 58
```

Quando excedido (HTTP 429):

```
Retry-After: 60
```

```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "Too many requests",
    "code": "RATE_LIMIT_EXCEEDED",
    "details": { "retry_after": 60 }
  }
}
```

### Limpeza Automatica

Registros expirados sao removidos automaticamente com probabilidade de 1% por requisicao (limpeza probabilistica).

---

## 8. CORS

A API suporta Cross-Origin Resource Sharing (CORS) para chamadas de frontends em outros dominios.

**Headers enviados em todas as respostas:**

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With
Access-Control-Max-Age: 86400
```

**Preflight (OPTIONS):** Retorna `204 No Content` automaticamente.

**Configuracao:** Altere `API_CORS_ORIGINS` no `.env` para restringir origens:

```ini
# Apenas um dominio
API_CORS_ORIGINS=https://app.meiasola.com.br

# Todas as origens (desenvolvimento)
API_CORS_ORIGINS=*
```

---

## 9. Endpoints — Autenticacao

### 9.1 Login

Autentica o usuario e retorna um par de tokens (access + refresh).

```
POST /api/v1/auth/login
Content-Type: application/json
```

**Request Body:**

| Campo | Tipo | Obrigatorio | Descricao |
|-------|------|-------------|-----------|
| `usuario` | string | Sim | Nome de usuario (login do Mercury) |
| `senha` | string | Sim | Senha do usuario |

**Exemplo:**

```bash
curl -X POST http://localhost/mercury/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"usuario": "joao@meiasola.com.br", "senha": "minhasenha123"}'
```

**Resposta 200 (sucesso):**

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 42,
      "nome": "Joao Silva",
      "email": "joao@meiasola.com.br",
      "loja_id": 5,
      "loja": "Arezzo Centro"
    },
    "tokens": {
      "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
      "refresh_token": "a1b2c3d4e5f6...",
      "token_type": "Bearer",
      "expires_in": 3600
    }
  },
  "error": null
}
```

**Resposta 401 (credenciais invalidas):**

```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "Invalid credentials",
    "code": "AUTH_INVALID_CREDENTIALS"
  }
}
```

**Resposta 422 (campos ausentes):**

```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "Missing required fields",
    "code": "VALIDATION_ERROR",
    "details": {
      "missing_fields": ["usuario", "senha"]
    }
  }
}
```

---

### 9.2 Refresh Token

Troca um refresh token valido por um novo par de tokens. O refresh token anterior e automaticamente revogado (token rotation).

```
POST /api/v1/auth/refresh
Content-Type: application/json
```

**Request Body:**

| Campo | Tipo | Obrigatorio | Descricao |
|-------|------|-------------|-----------|
| `refresh_token` | string | Sim | Refresh token recebido no login |

**Exemplo:**

```bash
curl -X POST http://localhost/mercury/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token": "a1b2c3d4e5f6..."}'
```

**Resposta 200 (sucesso):**

```json
{
  "success": true,
  "data": {
    "tokens": {
      "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
      "refresh_token": "f6e5d4c3b2a1...",
      "token_type": "Bearer",
      "expires_in": 3600
    }
  },
  "error": null
}
```

**Resposta 401 (token invalido/expirado/revogado):**

```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "Invalid or expired refresh token",
    "code": "AUTH_INVALID_REFRESH"
  }
}
```

> **Nota:** O refresh token so pode ser usado **uma unica vez**. Apos o uso, um novo refresh token e emitido e o anterior e invalidado.

---

## 10. Endpoints — Tickets

Todos os endpoints de tickets requerem autenticacao JWT.

**Header obrigatorio:**

```
Authorization: Bearer <access_token>
```

---

### 10.1 Listar Tickets

Retorna tickets paginados com filtros opcionais. Usuarios de nível loja veem apenas tickets da sua loja.

```
GET /api/v1/tickets
Authorization: Bearer <token>
```

**Query Parameters:**

| Parametro | Tipo | Padrao | Descricao |
|-----------|------|--------|-----------|
| `page` | int | 1 | Pagina atual |
| `per_page` | int | 20 | Itens por pagina (max: 100) |
| `status_id` | int | — | Filtrar por status |
| `department_id` | int | — | Filtrar por departamento |
| `priority_id` | int | — | Filtrar por prioridade |
| `store_id` | string | — | Filtrar por loja (somente admins) |

**IDs de Status:**

| ID | Nome |
|----|------|
| 1 | Aberto |
| 2 | Em Andamento |
| 3 | Pendente |
| 4 | Resolvido |
| 5 | Fechado |
| 6 | Cancelado |

**Exemplos:**

```bash
# Listar todos (pagina 1, 20 por pagina)
curl http://localhost/mercury/api/v1/tickets \
  -H "Authorization: Bearer <token>"

# Pagina 2 com 10 por pagina
curl "http://localhost/mercury/api/v1/tickets?page=2&per_page=10" \
  -H "Authorization: Bearer <token>"

# Filtrar por status "Aberto" e departamento TI
curl "http://localhost/mercury/api/v1/tickets?status_id=1&department_id=1" \
  -H "Authorization: Bearer <token>"

# Filtrar por loja (somente admins)
curl "http://localhost/mercury/api/v1/tickets?store_id=Z427" \
  -H "Authorization: Bearer <token>"
```

**Resposta 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 2,
      "title": "Cancelar plano",
      "status_id": 1,
      "priority_id": 2,
      "department_id": 2,
      "category_id": 11,
      "store_id": "Z427",
      "requester_id": 1,
      "assigned_technician_id": null,
      "created_at": "2026-02-17 23:08:28",
      "updated_at": "2026-02-17 23:08:28",
      "sla_due_at": "2026-02-19 23:08:28",
      "resolved_at": null,
      "closed_at": null,
      "status_name": "Aberto",
      "status_badge": "badge-info",
      "priority_name": "Media",
      "priority_badge": "badge-info",
      "department_name": "DP",
      "category_name": "Plano de Saude - Cancelamento",
      "requester_name": "Administrador",
      "technician_name": null,
      "store_name": "Arezzo Cariri",
      "hours_open": 2
    }
  ],
  "error": null,
  "meta": {
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 2,
      "total_pages": 1,
      "has_next": false,
      "has_prev": false
    }
  }
}
```

---

### 10.2 Criar Ticket

Cria um novo ticket no helpdesk. O `requester_id` e automaticamente definido como o usuario autenticado. O SLA e calculado com base na prioridade.

```
POST /api/v1/tickets
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**

| Campo | Tipo | Obrigatorio | Descricao |
|-------|------|-------------|-----------|
| `title` | string | Sim | Titulo do ticket |
| `description` | string | Sim | Descricao detalhada |
| `department_id` | int | Sim | ID do departamento |
| `category_id` | int | Sim | ID da categoria |
| `priority_id` | int | Nao | ID da prioridade (padrao: 2 = Media) |
| `store_id` | string | Nao | ID da loja (auto-preenchido para usuarios de loja) |

**Exemplo:**

```bash
curl -X POST http://localhost/mercury/api/v1/tickets \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Impressora nao funciona",
    "description": "A impressora do caixa 2 parou de funcionar apos queda de energia",
    "department_id": 1,
    "category_id": 1,
    "priority_id": 3
  }'
```

**Resposta 201 (criado):**

```json
{
  "success": true,
  "data": {
    "id": 5,
    "requester_id": 42,
    "department_id": 1,
    "category_id": 1,
    "title": "Impressora nao funciona",
    "description": "A impressora do caixa 2 parou...",
    "status_id": 1,
    "priority_id": 3,
    "created_at": "2026-02-18 10:30:00",
    "sla_due_at": "2026-02-19 10:30:00",
    "status_name": "Aberto",
    "priority_name": "Alta",
    "requester_name": "Joao Silva",
    "interactions": [
      {
        "id": 10,
        "comment": "Solicitacao criada.",
        "type": "comment",
        "created_at": "2026-02-18 10:30:00",
        "user_name": "Joao Silva"
      }
    ],
    "attachments": []
  },
  "error": null
}
```

---

### 10.3 Visualizar Ticket

Retorna dados completos do ticket, incluindo interacoes (timeline) e anexos.

```
GET /api/v1/tickets/{id}
Authorization: Bearer <token>
```

**Exemplo:**

```bash
curl http://localhost/mercury/api/v1/tickets/1 \
  -H "Authorization: Bearer <token>"
```

**Resposta 200:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "requester_id": 1,
    "assigned_technician_id": null,
    "department_id": 3,
    "category_id": 18,
    "store_id": "Z439",
    "title": "Luz queimada",
    "description": "TROCA DE LUZ DO PAINEL LATERAL",
    "status_id": 1,
    "priority_id": 3,
    "created_at": "2026-02-17 22:42:16",
    "updated_at": "2026-02-17 22:43:04",
    "sla_due_at": "2026-02-18 22:42:16",
    "resolved_at": null,
    "closed_at": null,
    "deleted_at": null,
    "created_by_user_id": 1,
    "updated_by_user_id": 1,
    "department_name": "Facilities",
    "department_icon": "fas fa-wrench",
    "category_name": "Eletrica",
    "status_name": "Aberto",
    "status_badge": "badge-info",
    "priority_name": "Alta",
    "priority_badge": "badge-warning",
    "sla_hours": 24,
    "requester_name": "Administrador",
    "technician_name": null,
    "store_name": "Schutz Riomar",
    "created_by_name": "Administrador",
    "updated_by_name": "Administrador",
    "hours_open": 12,
    "interactions": [
      {
        "id": 2,
        "ticket_id": 1,
        "user_id": 1,
        "comment": "Aguardando visita tecnica.",
        "type": "comment",
        "old_value": null,
        "new_value": null,
        "is_internal": 0,
        "created_at": "2026-02-17 22:43:03",
        "user_name": "Administrador"
      },
      {
        "id": 1,
        "ticket_id": 1,
        "user_id": 1,
        "comment": "Solicitacao criada.",
        "type": "comment",
        "old_value": null,
        "new_value": null,
        "is_internal": 0,
        "created_at": "2026-02-17 22:42:16",
        "user_name": "Administrador"
      }
    ],
    "attachments": [
      {
        "id": 1,
        "ticket_id": 1,
        "original_filename": "Fatura15-02-2026.pdf",
        "stored_filename": "Fatura15-02-2026_1771378996.pdf",
        "file_path": "assets/imagens/helpdesk/1/Fatura15-02-2026_1771378996.pdf",
        "mime_type": "application/pdf",
        "size_bytes": 90657,
        "uploaded_by_user_id": 1,
        "created_at": "2026-02-17 22:43:16",
        "uploaded_by_name": "Administrador"
      }
    ]
  },
  "error": null
}
```

**Resposta 404:**

```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "Ticket not found",
    "code": "NOT_FOUND"
  }
}
```

---

### 10.4 Atualizar Ticket

Atualiza campos de um ticket existente. Mudancas de status, prioridade e tecnico geram interacoes automaticas na timeline.

```
PUT /api/v1/tickets/{id}
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body (todos opcionais):**

| Campo | Tipo | Descricao |
|-------|------|-----------|
| `title` | string | Novo titulo |
| `description` | string | Nova descricao |
| `status_id` | int | Novo status (1-6) |
| `priority_id` | int | Nova prioridade |
| `department_id` | int | Novo departamento |
| `category_id` | int | Nova categoria |
| `assigned_technician_id` | int\|null | ID do tecnico (null para remover) |

**Exemplo — alterar status e atribuir tecnico:**

```bash
curl -X PUT http://localhost/mercury/api/v1/tickets/1 \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "status_id": 2,
    "assigned_technician_id": 15
  }'
```

**Resposta 200:**

Retorna o ticket completo atualizado (mesmo formato do endpoint de visualizacao). A timeline incluira interacoes automaticas:

```json
{
  "interactions": [
    {
      "comment": "Tecnico alterado de \"Ninguem\" para \"Carlos\"",
      "type": "assignment",
      "old_value": "Ninguem",
      "new_value": "Carlos"
    },
    {
      "comment": "Status alterado de \"Aberto\" para \"Em Andamento\"",
      "type": "status_change",
      "old_value": "Aberto",
      "new_value": "Em Andamento"
    }
  ]
}
```

**Interacoes automaticas geradas:**

| Mudanca | Tipo da Interacao | Exemplo |
|---------|-------------------|---------|
| Status | `status_change` | "Status alterado de X para Y" |
| Prioridade | `priority_change` | "Prioridade alterada de X para Y" |
| Tecnico | `assignment` | "Tecnico alterado de X para Y" |

**Comportamentos especiais:**

- Status **Resolvido** (4) ou **Fechado** (5): define `resolved_at`
- Status **Fechado** (5): tambem define `closed_at`
- Mudanca de prioridade: recalcula `sla_due_at`

---

## 11. Endpoints — Interacoes

### 11.1 Listar Interacoes

Retorna todas as interacoes (timeline) de um ticket, ordenadas por data (mais recente primeiro).

```
GET /api/v1/tickets/{id}/interactions
Authorization: Bearer <token>
```

**Exemplo:**

```bash
curl http://localhost/mercury/api/v1/tickets/1/interactions \
  -H "Authorization: Bearer <token>"
```

**Resposta 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "ticket_id": 1,
      "user_id": 15,
      "comment": "Problema resolvido, lampada substituida.",
      "type": "comment",
      "old_value": null,
      "new_value": null,
      "is_internal": 0,
      "created_at": "2026-02-18 14:30:00",
      "user_name": "Carlos Tecnico"
    },
    {
      "id": 4,
      "ticket_id": 1,
      "user_id": 1,
      "comment": "Status alterado de \"Aberto\" para \"Em Andamento\"",
      "type": "status_change",
      "old_value": "Aberto",
      "new_value": "Em Andamento",
      "is_internal": 0,
      "created_at": "2026-02-18 10:00:00",
      "user_name": "Administrador"
    }
  ],
  "error": null
}
```

**Tipos de interacao:**

| Tipo | Descricao |
|------|-----------|
| `comment` | Comentario manual ou criacao do ticket |
| `status_change` | Mudanca de status (automatica) |
| `priority_change` | Mudanca de prioridade (automatica) |
| `assignment` | Atribuicao/mudanca de tecnico (automatica) |

---

### 11.2 Adicionar Comentario

Adiciona um comentario publico ou nota interna ao ticket.

```
POST /api/v1/tickets/{id}/interactions
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**

| Campo | Tipo | Obrigatorio | Descricao |
|-------|------|-------------|-----------|
| `comment` | string | Sim | Texto do comentario |
| `is_internal` | bool | Nao | `true` para nota interna (padrao: `false`) |

**Exemplo:**

```bash
curl -X POST http://localhost/mercury/api/v1/tickets/1/interactions \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "comment": "Peca encomendada, previsao de chegada em 3 dias.",
    "is_internal": false
  }'
```

**Resposta 201:**

```json
{
  "success": true,
  "data": {
    "id": 8,
    "ticket_id": 1,
    "user_id": 15,
    "comment": "Peca encomendada, previsao de chegada em 3 dias.",
    "type": "comment",
    "old_value": null,
    "new_value": null,
    "is_internal": 0,
    "created_at": "2026-02-18 15:00:00",
    "user_name": "Carlos Tecnico"
  },
  "error": null
}
```

---

## 12. Endpoints — Ajustes de Estoque

Todos os endpoints de ajustes requerem autenticacao JWT. Acesso controlado por `FINANCIALPERMITION`.

### 12.1 Listar Ajustes

```
GET /api/v1/adjustments
Authorization: Bearer <token>
```

**Query Parameters:**

| Parametro | Tipo | Padrao | Descricao |
|-----------|------|--------|-----------|
| `page` | int | 1 | Pagina atual |
| `per_page` | int | 20 | Itens por pagina (max: 100) |
| `status_id` | int | — | Filtrar por status |
| `store_id` | string | — | Filtrar por loja (somente admins financeiros) |
| `search` | string | — | Busca por ID, nome do cliente ou funcionario |

```bash
curl http://localhost/mercury/api/v1/adjustments \
  -H "Authorization: Bearer <token>"

# Com filtros
curl "http://localhost/mercury/api/v1/adjustments?status_id=1&store_id=Z427" \
  -H "Authorization: Bearer <token>"
```

### 12.2 Estatisticas de Ajustes

```
GET /api/v1/adjustments/statistics
Authorization: Bearer <token>
```

**Query Parameters:** `search`, `store_id`, `status_id`

```bash
curl http://localhost/mercury/api/v1/adjustments/statistics \
  -H "Authorization: Bearer <token>"
```

**Resposta 200:**

```json
{
  "success": true,
  "data": {
    "total_adjustments": 45,
    "adjustments_by_situation": [...],
    "adjustments_by_store": [...],
    "total_products": 320,
    "total_adjusted": 280,
    "total_pending_or_analysis": 15
  }
}
```

### 12.3 Criar Ajuste

```
POST /api/v1/adjustments
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**

| Campo | Tipo | Obrigatorio | Descricao |
|-------|------|-------------|-----------|
| `store_id` | string | Sim | ID da loja |
| `employee_id` | int | Sim | ID do funcionario |
| `products` | array | Sim | Lista de produtos/tamanhos |
| `client_name` | string | Nao | Nome do cliente |
| `status_id` | int | Nao | ID do status (padrao: 1 = Pendente) |
| `observations` | string | Nao | Observacoes |

```bash
curl -X POST http://localhost/mercury/api/v1/adjustments \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "store_id": "Z427",
    "employee_id": 10,
    "client_name": "Maria Silva",
    "products": [{"product_id": 1, "sizes": {"38": 2, "40": 1}}]
  }'
```

### 12.4 Visualizar Ajuste

```
GET /api/v1/adjustments/{id}
Authorization: Bearer <token>
```

Retorna ajuste com itens e resumo.

```bash
curl http://localhost/mercury/api/v1/adjustments/1 \
  -H "Authorization: Bearer <token>"
```

**Resposta 200:**

```json
{
  "success": true,
  "data": {
    "adjustment": { "id": 1, "adms_store_id": "Z427", ... },
    "items": [...],
    "summary": { "total_products": 5, "total_adjusted": 3 }
  }
}
```

### 12.5 Atualizar Ajuste

```
PUT /api/v1/adjustments/{id}
Authorization: Bearer <token>
Content-Type: application/json
```

### 12.6 Deletar Ajuste

Somente ajustes com status **Pendente** (id=1) podem ser deletados.

```
DELETE /api/v1/adjustments/{id}
Authorization: Bearer <token>
```

### 12.7 Listar Itens do Ajuste

```
GET /api/v1/adjustments/{id}/items
Authorization: Bearer <token>
```

---

## 13. Endpoints — Transferencias

Todos os endpoints de transferencias requerem autenticacao JWT. Acesso controlado por `STOREPERMITION` — usuarios de loja veem transferencias onde sua loja e origem OU destino.

### 13.1 Listar Transferencias

```
GET /api/v1/transfers
Authorization: Bearer <token>
```

**Query Parameters:**

| Parametro | Tipo | Padrao | Descricao |
|-----------|------|--------|-----------|
| `page` | int | 1 | Pagina atual |
| `per_page` | int | 20 | Itens por pagina (max: 100) |
| `status_id` | int | — | Filtrar por status |
| `store_origin_id` | string | — | Filtrar por loja origem (somente admins) |
| `store_destiny_id` | string | — | Filtrar por loja destino (somente admins) |
| `type_id` | int | — | Filtrar por tipo |
| `search` | string | — | Busca por ID, nota fiscal ou criador |

```bash
curl http://localhost/mercury/api/v1/transfers \
  -H "Authorization: Bearer <token>"
```

### 13.2 Estatisticas de Transferencias

```
GET /api/v1/transfers/statistics
Authorization: Bearer <token>
```

**Query Parameters:** `search`, `store_origin_id`, `store_destiny_id`, `status_id`

### 13.3 Criar Transferencia

```
POST /api/v1/transfers
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**

| Campo | Tipo | Obrigatorio | Descricao |
|-------|------|-------------|-----------|
| `store_origin_id` | string | Sim | ID da loja origem |
| `store_destiny_id` | string | Sim | ID da loja destino |
| `invoice_number` | string | Nao | Numero da nota fiscal |
| `volumes_qty` | int | Nao | Quantidade de volumes |
| `products_qty` | int | Nao | Quantidade de produtos |
| `type_id` | int | Nao | Tipo de transferencia |
| `observations` | string | Nao | Observacoes |

```bash
curl -X POST http://localhost/mercury/api/v1/transfers \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "store_origin_id": "Z427",
    "store_destiny_id": "Z439",
    "invoice_number": "NF-12345",
    "volumes_qty": 3,
    "products_qty": 15,
    "type_id": 1
  }'
```

### 13.4 Visualizar Transferencia

```
GET /api/v1/transfers/{id}
Authorization: Bearer <token>
```

### 13.5 Atualizar Transferencia

```
PUT /api/v1/transfers/{id}
Authorization: Bearer <token>
Content-Type: application/json
```

### 13.6 Deletar Transferencia

Somente transferencias com status **Pendente** (id=1) podem ser deletadas.

```
DELETE /api/v1/transfers/{id}
Authorization: Bearer <token>
```

### 13.7 Confirmar Coleta (Pickup)

Transicao: **Pendente (1) → Em Rota (2)**

```
POST /api/v1/transfers/{id}/pickup
Authorization: Bearer <token>
```

```bash
curl -X POST http://localhost/mercury/api/v1/transfers/1/pickup \
  -H "Authorization: Bearer <token>"
```

### 13.8 Confirmar Entrega (Delivery)

Transicao: **Em Rota (2) → Entregue (3)**

```
POST /api/v1/transfers/{id}/delivery
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**

| Campo | Tipo | Obrigatorio | Descricao |
|-------|------|-------------|-----------|
| `receiver_name` | string | Sim | Nome do recebedor |

```bash
curl -X POST http://localhost/mercury/api/v1/transfers/1/delivery \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"receiver_name": "Joao Silva"}'
```

### 13.9 Confirmar Recebimento (Receipt)

Transicao: **Entregue (3) → Confirmado (4)**

```
POST /api/v1/transfers/{id}/receipt
Authorization: Bearer <token>
```

### 13.10 Historico de Status

Retorna o historico de transicoes de status derivado dos campos da transferencia.

```
GET /api/v1/transfers/{id}/history
Authorization: Bearer <token>
```

**Resposta 200:**

```json
{
  "success": true,
  "data": [
    { "status": "Criado", "date": "2026-02-15 10:00:00", "user": "Admin" },
    { "status": "Coletado (Em Rota)", "date": "2026-02-15 14:30:00", "user": "Motorista" },
    { "status": "Entregue", "date": "2026-02-16 09:00:00", "user": "Joao Silva" },
    { "status": "Confirmado", "date": "2026-02-16 10:30:00", "user": "Maria" }
  ]
}
```

---

## 14. Endpoints — Vendas

Endpoints somente leitura para dados de vendas. Acesso controlado por `FINANCIALPERMITION`.

### 14.1 Listar Vendas

```
GET /api/v1/sales
Authorization: Bearer <token>
```

**Query Parameters:**

| Parametro | Tipo | Padrao | Descricao |
|-----------|------|--------|-----------|
| `page` | int | 1 | Pagina atual |
| `per_page` | int | 20 | Itens por pagina (max: 100) |
| `month` | int | mes atual | Mes de referencia (1-12) |
| `year` | int | ano atual | Ano de referencia |
| `store_id` | string | — | Filtrar por loja (somente admins financeiros) |
| `search` | string | — | Busca por nome do funcionario ou loja |
| `date_start` | string | — | Data inicio (YYYY-MM-DD) |
| `date_end` | string | — | Data fim (YYYY-MM-DD) |

```bash
curl "http://localhost/mercury/api/v1/sales?month=2&year=2026" \
  -H "Authorization: Bearer <token>"
```

### 14.2 Estatisticas de Vendas

```
GET /api/v1/sales/statistics
Authorization: Bearer <token>
```

**Query Parameters:** `month`, `year`, `search`, `store_id`, `date_start`, `date_end`

```bash
curl "http://localhost/mercury/api/v1/sales/statistics?month=2&year=2026" \
  -H "Authorization: Bearer <token>"
```

**Resposta 200:**

```json
{
  "success": true,
  "data": {
    "current_month_total": 150000.00,
    "last_month_total": 140000.00,
    "variation": 7.14,
    "same_month_last_year_total": 130000.00,
    "year_over_year_variation": 15.38,
    "active_stores": 12,
    "active_consultants": 45,
    "total_records": 320,
    "avg_per_store": 12500.00,
    "avg_per_consultant": 3333.33,
    "last_sync": "2026-02-18 08:00:00",
    "target_month": 2,
    "target_year": 2026,
    "month_name": "Fev"
  }
}
```

### 14.3 Relatorio por Loja

```
GET /api/v1/sales/by-store
Authorization: Bearer <token>
```

**Query Parameters:** `month`, `year`, `search`, `store_id`, `date_start`, `date_end`

```bash
curl "http://localhost/mercury/api/v1/sales/by-store?month=2&year=2026" \
  -H "Authorization: Bearer <token>"
```

### 14.4 Relatorio por Consultor

```
GET /api/v1/sales/by-consultant
Authorization: Bearer <token>
```

**Query Parameters:** `month`, `year`, `search`, `store_id`, `date_start`, `date_end`

### 14.5 Listar Consultores

Lista consultores com vendas no mes atual.

```
GET /api/v1/sales/consultants
Authorization: Bearer <token>
```

---

## 15. Endpoints — Funcionarios

CRUD completo de funcionarios com endpoints de contratos e escala. Acesso controlado por `STOREPERMITION`.

> **Nota:** Upload de imagem nao e suportado via API. Use a interface web para fotos.

### 15.1 Listar Funcionarios

```
GET /api/v1/employees
Authorization: Bearer <token>
```

**Query Parameters:**

| Parametro | Tipo | Padrao | Descricao |
|-----------|------|--------|-----------|
| `page` | int | 1 | Pagina atual |
| `per_page` | int | 20 | Itens por pagina (max: 100) |
| `status_id` | int | — | Filtrar por status |
| `store_id` | string | — | Filtrar por loja (somente admins) |
| `position_id` | int | — | Filtrar por cargo |
| `search` | string | — | Busca por nome ou loja |

```bash
curl http://localhost/mercury/api/v1/employees \
  -H "Authorization: Bearer <token>"
```

### 15.2 Criar Funcionario

```
POST /api/v1/employees
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body:**

| Campo | Tipo | Obrigatorio | Descricao |
|-------|------|-------------|-----------|
| `name_employee` | string | Sim | Nome completo |
| `short_name` | string | Sim | Nome abreviado |
| `doc_cpf` | string | Sim | CPF do funcionario |
| `adms_store_id` | string | Sim | ID da loja |
| `position_id` | int | Sim | ID do cargo |
| `date_admission` | string | Sim | Data de admissao (YYYY-MM-DD) |
| `adms_status_employee_id` | int | Sim | ID do status |
| `adms_area_id` | int | Nao | ID da area |
| `adms_level_education_id` | int | Nao | ID do nivel de educacao |
| `adms_sex_id` | int | Nao | ID do sexo |
| `date_birth` | string | Nao | Data de nascimento |
| `email` | string | Nao | Email |
| `telephone` | string | Nao | Telefone |

```bash
curl -X POST http://localhost/mercury/api/v1/employees \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name_employee": "Maria da Silva",
    "short_name": "Maria",
    "doc_cpf": "12345678901",
    "adms_store_id": "Z427",
    "position_id": 1,
    "date_admission": "2026-02-18",
    "adms_status_employee_id": 1
  }'
```

### 15.3 Visualizar Funcionario

Retorna dados completos do funcionario com contratos e escala atual.

```
GET /api/v1/employees/{id}
Authorization: Bearer <token>
```

```bash
curl http://localhost/mercury/api/v1/employees/1 \
  -H "Authorization: Bearer <token>"
```

**Resposta 200:**

```json
{
  "success": true,
  "data": {
    "employee": { "id": 1, "name_employee": "Maria da Silva", ... },
    "contracts": [...],
    "schedule": { "schedule_name": "Comercial", "weekly_hours": 44, "days": [...] }
  }
}
```

### 15.4 Atualizar Funcionario

```
PUT /api/v1/employees/{id}
Authorization: Bearer <token>
Content-Type: application/json
```

### 15.5 Deletar Funcionario

Deleta o funcionario e todos os contratos associados (cascade).

```
DELETE /api/v1/employees/{id}
Authorization: Bearer <token>
```

### 15.6 Listar Contratos

```
GET /api/v1/employees/{id}/contracts
Authorization: Bearer <token>
```

### 15.7 Escala de Trabalho

Retorna a escala de trabalho atual do funcionario com dias da semana e overrides.

```
GET /api/v1/employees/{id}/schedule
Authorization: Bearer <token>
```

---

## 16. Controle de Acesso por Loja

A API implementa controle de acesso por loja usando duas constantes diferentes conforme o modulo:

### Constantes de Permissao

| Constante | Valor Padrao | Modulos |
|-----------|-------------|---------|
| `STOREPERMITION` | 18 | Tickets, Transferencias, Funcionarios |
| `FINANCIALPERMITION` | 2 | Ajustes de Estoque, Vendas |

### Regras

- **STOREPERMITION:** `ordem_nivac < STOREPERMITION` = admin; `>= STOREPERMITION` = usuario de loja
- **FINANCIALPERMITION:** `ordem_nivac < FINANCIALPERMITION` = admin financeiro; `> FINANCIALPERMITION` = restrito

### Comportamento por Modulo

| Modulo | Admin | Usuario de Loja |
|--------|-------|-----------------|
| **Tickets** | Ve todos | Ve apenas da sua loja |
| **Ajustes** | Ve todos | Ve apenas da sua loja (filtro financeiro) |
| **Transferencias** | Ve todos | Ve transferencias com origem OU destino = sua loja |
| **Vendas** | Ve todos | Ve apenas da sua loja (filtro dual: venda + funcionario) |
| **Funcionarios** | Ve todos | Ve apenas da sua loja |

### Filtros de Loja na Listagem

- `store_id` em Tickets, Ajustes, Vendas e Funcionarios: so aceito para admins
- `store_origin_id` / `store_destiny_id` em Transferencias: so aceito para admins

---

## 17. Guia Rapido (Quick Start)

### Passo 1 — Login

```bash
curl -X POST http://localhost/mercury/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"usuario": "seu.usuario@meiasola.com.br", "senha": "suasenha"}'
```

Salve os valores `access_token` e `refresh_token` da resposta.

### Passo 2 — Usar o Token

```bash
# Substitua <TOKEN> pelo access_token recebido
export TOKEN="eyJ0eXAiOiJKV1Qi..."
```

### Passo 3 — Listar Tickets

```bash
curl http://localhost/mercury/api/v1/tickets \
  -H "Authorization: Bearer $TOKEN"
```

### Passo 4 — Criar Ticket

```bash
curl -X POST http://localhost/mercury/api/v1/tickets \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Problema no sistema",
    "description": "O sistema trava ao abrir relatorio de vendas",
    "department_id": 1,
    "category_id": 1
  }'
```

### Passo 5 — Adicionar Comentario

```bash
curl -X POST http://localhost/mercury/api/v1/tickets/5/interactions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"comment": "Ja verifiquei e o problema persiste."}'
```

### Passo 6 — Renovar Token (quando expirar)

```bash
curl -X POST http://localhost/mercury/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token": "seu_refresh_token_aqui"}'
```

---

## 18. Exemplos de Integracao

### 18.1 JavaScript (Fetch API)

```javascript
const BASE_URL = 'http://localhost/mercury/api/v1';
let accessToken = null;
let refreshToken = null;

// Login
async function login(usuario, senha) {
  const res = await fetch(`${BASE_URL}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ usuario, senha }),
  });
  const data = await res.json();

  if (data.success) {
    accessToken = data.data.tokens.access_token;
    refreshToken = data.data.tokens.refresh_token;
    console.log('Login OK:', data.data.user.nome);
  }
  return data;
}

// Request autenticada com refresh automatico
async function apiRequest(endpoint, options = {}) {
  options.headers = {
    ...options.headers,
    'Authorization': `Bearer ${accessToken}`,
    'Content-Type': 'application/json',
  };

  let res = await fetch(`${BASE_URL}${endpoint}`, options);

  // Se token expirou, tenta refresh
  if (res.status === 401 && refreshToken) {
    const refreshRes = await fetch(`${BASE_URL}/auth/refresh`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: refreshToken }),
    });
    const refreshData = await refreshRes.json();

    if (refreshData.success) {
      accessToken = refreshData.data.tokens.access_token;
      refreshToken = refreshData.data.tokens.refresh_token;

      // Retry original request
      options.headers['Authorization'] = `Bearer ${accessToken}`;
      res = await fetch(`${BASE_URL}${endpoint}`, options);
    }
  }

  return res.json();
}

// Uso
await login('joao@meiasola.com.br', 'senha123');

// Listar tickets
const tickets = await apiRequest('/tickets?page=1&per_page=10');

// Criar ticket
const newTicket = await apiRequest('/tickets', {
  method: 'POST',
  body: JSON.stringify({
    title: 'Problema no sistema',
    description: 'Detalhes do problema',
    department_id: 1,
    category_id: 1,
  }),
});

// Adicionar comentario
const comment = await apiRequest('/tickets/5/interactions', {
  method: 'POST',
  body: JSON.stringify({ comment: 'Verificando o problema...' }),
});
```

### 18.2 Python (requests)

```python
import requests

BASE_URL = 'http://localhost/mercury/api/v1'

# Login
r = requests.post(f'{BASE_URL}/auth/login', json={
    'usuario': 'joao@meiasola.com.br',
    'senha': 'senha123'
})
tokens = r.json()['data']['tokens']
access_token = tokens['access_token']
refresh_token = tokens['refresh_token']

headers = {
    'Authorization': f'Bearer {access_token}',
    'Content-Type': 'application/json'
}

# Listar tickets
r = requests.get(f'{BASE_URL}/tickets', headers=headers)
tickets = r.json()
print(f"Total: {tickets['meta']['pagination']['total']} tickets")

for ticket in tickets['data']:
    print(f"  #{ticket['id']} [{ticket['status_name']}] {ticket['title']}")

# Criar ticket
r = requests.post(f'{BASE_URL}/tickets', headers=headers, json={
    'title': 'Problema na rede',
    'description': 'Internet caiu na loja',
    'department_id': 1,
    'category_id': 1,
    'priority_id': 3  # Alta
})
print(f"Ticket criado: #{r.json()['data']['id']}")

# Adicionar comentario
r = requests.post(f'{BASE_URL}/tickets/5/interactions', headers=headers, json={
    'comment': 'Problema identificado, tecnico a caminho.'
})
```

### 18.3 PHP (cURL)

```php
<?php

$baseUrl = 'http://localhost/mercury/api/v1';

// Login
$ch = curl_init("{$baseUrl}/auth/login");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'usuario' => 'joao@meiasola.com.br',
        'senha' => 'senha123',
    ]),
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

$accessToken = $response['data']['tokens']['access_token'];

// Listar tickets
$ch = curl_init("{$baseUrl}/tickets?page=1&per_page=10");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "Authorization: Bearer {$accessToken}",
    ],
]);
$tickets = json_decode(curl_exec($ch), true);
curl_close($ch);

foreach ($tickets['data'] as $ticket) {
    echo "#{$ticket['id']} [{$ticket['status_name']}] {$ticket['title']}\n";
}
```

---

## 19. Estrutura de Arquivos

```
mercury/
├── api.php                                          # Entry point da API
├── .htaccess                                        # Rewrite ^api/ -> api.php
├── .env                                             # Variaveis JWT/API
│
├── core/Api/                                        # Framework da API
│   ├── ApiRouter.php                                # Roteador com regex
│   ├── ApiResponse.php                              # Respostas JSON padrao
│   ├── ApiRequest.php                               # Parser de request
│   ├── JwtService.php                               # JWT + refresh tokens
│   ├── ApiAuthMiddleware.php                         # Validacao Bearer token
│   ├── ApiRateLimiter.php                            # Rate limiting por IP
│   ├── BaseApiController.php                         # Classe base controllers
│   └── CorsHandler.php                              # Headers CORS + preflight
│
├── app/adms/Controllers/Api/V1/                     # Controllers API v1
│   ├── AuthController.php                            # Login + refresh
│   ├── TicketsController.php                         # CRUD tickets
│   ├── InteractionsController.php                    # Interacoes tickets
│   ├── AdjustmentsController.php                     # CRUD ajustes + stats
│   ├── TransfersController.php                       # CRUD transferencias + status
│   ├── SalesController.php                           # Listagem + relatorios vendas
│   └── EmployeesController.php                       # CRUD funcionarios + contratos
│
├── app/adms/Models/                                 # Models reutilizados
│   ├── AdmsServiceRequest.php                        # Tickets: create(), update()
│   ├── AdmsViewServiceRequest.php                    # Tickets: view()
│   ├── AdmsServiceRequestInteraction.php             # Tickets: addComment()
│   ├── AdmsViewAdjustment.php                        # Ajustes: view + items
│   ├── AdmsAddAdjustments.php                        # Ajustes: create
│   ├── AdmsEditAdjustment.php                        # Ajustes: update
│   ├── AdmsDeleteAdjustment.php                      # Ajustes: delete
│   ├── AdmsStatisticsAdjustments.php                 # Ajustes: stats
│   ├── AdmsViewTransfer.php                          # Transferencias: view
│   ├── AdmsAddTransfer.php                           # Transferencias: create
│   ├── AdmsEditTransfer.php                          # Transferencias: update
│   ├── AdmsDeleteTransfer.php                        # Transferencias: delete
│   ├── AdmsConfirmTransfer.php                       # Transferencias: pickup/delivery/receipt
│   ├── AdmsStatisticsTransfers.php                   # Transferencias: stats
│   ├── AdmsStatisticsSales.php                       # Vendas: stats + relatorios
│   ├── AdmsListSales.php                             # Vendas: list + consultores
│   ├── AdmsViewEmployee.php                          # Funcionarios: view + contratos
│   ├── AdmsAddEmployee.php                           # Funcionarios: create
│   ├── AdmsEditEmployee.php                          # Funcionarios: update
│   └── AdmsDeleteEmployee.php                        # Funcionarios: delete
│
└── database/migrations/
    └── 2026_02_17_create_api_tables.sql              # api_tokens + api_rate_limits
```

---

## 20. Guia para Novos Endpoints

Para adicionar novos endpoints a API (ex: modulo de Vendas, Funcionarios, etc.):

### Passo 1 — Criar Controller

Crie um novo controller em `app/adms/Controllers/Api/V1/`:

```php
<?php

namespace App\adms\Controllers\Api\V1;

use Core\Api\ApiRequest;
use Core\Api\ApiResponse;
use Core\Api\BaseApiController;

class NomeController extends BaseApiController
{
    public function index(): void
    {
        // Logica de listagem
        ApiResponse::paginated($items, $page, $perPage, $total);
    }

    public function show(): void
    {
        $id = (int) $this->request->getRouteParam('id');
        // Logica de visualizacao
        ApiResponse::success($data);
    }

    public function store(): void
    {
        $data = $this->request->input();
        $this->validateRequired($data, ['campo1', 'campo2']);
        // Logica de criacao
        ApiResponse::success($created, 201);
    }

    public function update(): void
    {
        $id = (int) $this->request->getRouteParam('id');
        $data = $this->request->input();
        // Logica de atualizacao
        ApiResponse::success($updated);
    }
}
```

### Passo 2 — Registrar Rotas

Adicione as rotas em `core/Api/ApiRouter.php` no metodo `registerRoutes()`:

```php
// Novo modulo (JWT required)
$this->addRoute('GET',  'v1/nome',      'NomeController', 'index',  true);
$this->addRoute('POST', 'v1/nome',      'NomeController', 'store',  true);
$this->addRoute('GET',  'v1/nome/{id}', 'NomeController', 'show',   true);
$this->addRoute('PUT',  'v1/nome/{id}', 'NomeController', 'update', true);
```

### Passo 3 — Metodos Uteis da BaseApiController

```php
// Verificar se usuario pode ver todas as lojas (STOREPERMITION)
$this->canViewAll();          // bool

// Verificar se usuario tem acesso financeiro (FINANCIALPERMITION)
$this->canViewFinancial();    // bool

// Obter dados do usuario autenticado
$this->getUserId();           // int
$this->getUserStoreId();      // int|null

// Validar campos obrigatorios (retorna 422 se faltante)
$this->validateRequired($data, ['campo1', 'campo2']);

// Acessar dados da request
$this->request->input('campo');           // valor do body JSON
$this->request->query('parametro');       // valor do query string
$this->request->getRouteParam('id');      // parametro da URL
$this->request->getPage();               // pagina atual (min 1)
$this->request->getPerPage();            // itens por pagina (1-100)
```

---

## 21. Troubleshooting

### Token retorna 401 mesmo sendo valido

1. Verifique se o header esta correto: `Authorization: Bearer <token>` (com espaco apos "Bearer")
2. Verifique se o token nao expirou (`expires_in: 3600` = 1 hora)
3. Use o endpoint de refresh para obter novo token

### Header Authorization nao chega ao PHP

O Apache pode remover o header. Verifique se o `.htaccess` tem:

```apache
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

### Erro 404 para rotas da API

1. Verifique se `mod_rewrite` esta habilitado no Apache
2. Verifique se o `.htaccess` esta sendo processado (`AllowOverride All`)
3. Verifique se o `api.php` existe na raiz do projeto

### CORS bloqueado no navegador

1. Verifique `API_CORS_ORIGINS` no `.env`
2. Para desenvolvimento, use `API_CORS_ORIGINS=*`
3. Para producao, especifique o dominio exato

### Rate limit excedido durante testes

O rate limit padrao e 60 req/min. Para testes intensivos, aumente temporariamente no `.env`:

```ini
API_RATE_LIMIT=1000
API_RATE_WINDOW=60
```

### Body JSON nao e recebido

1. Verifique o header `Content-Type: application/json`
2. Verifique se o JSON e valido (use um validador)
3. `php://input` so e lido uma vez — nao use `$_POST` simultaneamente

### Refresh token invalido apos unico uso

Comportamento esperado. O sistema usa **token rotation**: cada refresh token so pode ser usado uma unica vez. Apos o uso, um novo par de tokens e emitido.

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
**Versao:** 2.0
**Ultima Atualizacao:** 18/02/2026
