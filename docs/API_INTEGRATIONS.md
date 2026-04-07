# Mercury SaaS - API de Integrações

**Versão:** 1.0
**Data:** Abril 2026
**Base URL:** `https://{subdomain}.mercury.com.br/api/v1`

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Autenticação](#2-autenticação)
3. [Rate Limiting](#3-rate-limiting)
4. [Endpoints](#4-endpoints)
   - 4.1 [Status da Integração](#41-status-da-integração)
   - 4.2 [Disparar Sincronização](#42-disparar-sincronização)
   - 4.3 [Consultar Dados](#43-consultar-dados)
   - 4.4 [Enviar Dados](#44-enviar-dados)
   - 4.5 [Receber Webhook](#45-receber-webhook)
5. [Drivers Disponíveis](#5-drivers-disponíveis)
   - 5.1 [Database](#51-database)
   - 5.2 [REST API](#52-rest-api)
   - 5.3 [Webhook](#53-webhook)
   - 5.4 [CIGAM Sales](#54-cigam-sales)
   - 5.5 [CIGAM Products](#55-cigam-products)
6. [Provedores (Presets)](#6-provedores-presets)
7. [Formato de Resposta](#7-formato-de-resposta)
8. [Códigos de Erro](#8-códigos-de-erro)
9. [Exemplos de Uso](#9-exemplos-de-uso)

---

## 1. Visão Geral

A API de Integrações permite que sistemas externos se conectem ao Mercury para sincronizar dados (vendas, produtos, funcionários, lojas). A arquitetura usa um **Driver Pattern** onde cada tipo de conexão (database, REST API, webhook) implementa uma interface comum (`IntegrationDriver`).

**Fluxo:**
1. Administrador cria uma integração em `/integrations` (UI do tenant)
2. O sistema gera uma API Key para autenticação externa
3. Sistemas externos usam a API Key para consultar/enviar dados
4. Logs de sincronização são registrados automaticamente

---

## 2. Autenticação

Todas as requisições autenticadas exigem uma **API Key** enviada via header ou query parameter.

### Via Header (recomendado)

```
X-Integration-Key: SUA_API_KEY_AQUI
```

### Via Query Parameter

```
GET /api/v1/integrations/{id}/status?api_key=SUA_API_KEY_AQUI
```

### Respostas de Erro de Autenticação

| Status | Descrição |
|--------|-----------|
| 401 | API Key ausente ou inválida |
| 404 | Integração não encontrada ou inativa |

---

## 3. Rate Limiting

| Endpoint | Limite |
|----------|--------|
| API (autenticada) | 60 req/minuto |
| Webhooks | 30 req/minuto |
| Login central | 5 req/minuto |

Ao exceder o limite, a API retorna `429 Too Many Requests`.

---

## 4. Endpoints

### 4.1 Status da Integração

Consulta o status atual da integração e informações da última sincronização.

```
GET /api/v1/integrations/{integration}/status
```

**Headers:** `X-Integration-Key: {api_key}`

**Resposta 200:**

```json
{
  "id": 1,
  "name": "CIGAM ERP",
  "is_active": true,
  "last_sync_at": "2026-04-05T14:30:00-03:00",
  "last_sync_status": "success",
  "last_sync": {
    "status": "success",
    "records_processed": 150,
    "records_created": 12,
    "started_at": "2026-04-05T14:29:55-03:00",
    "finished_at": "2026-04-05T14:30:00-03:00"
  }
}
```

---

### 4.2 Disparar Sincronização

Dispara uma sincronização pull (extrai dados do sistema externo para o Mercury).

```
POST /api/v1/integrations/{integration}/sync
```

**Headers:** `X-Integration-Key: {api_key}`

**Body (opcional):**

```json
{
  "date_from": "2026-04-01",
  "date_to": "2026-04-05",
  "resource": "sales"
}
```

**Resposta 200:**

```json
{
  "status": "success",
  "result": {
    "processed": 150,
    "created": 12,
    "updated": 138,
    "failed": 0,
    "errors": []
  }
}
```

**Resposta parcial (com erros):**

```json
{
  "status": "partial",
  "result": {
    "processed": 150,
    "created": 10,
    "updated": 135,
    "failed": 5,
    "errors": ["Registro duplicado: ID 4521", "Campo obrigatório ausente: cpf"]
  }
}
```

---

### 4.3 Consultar Dados

Recupera dados de um recurso específico via driver configurado.

```
GET /api/v1/integrations/{integration}/data/{resource}
```

**Headers:** `X-Integration-Key: {api_key}`

**Parâmetros de Query:**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| date_from | string | Data início (YYYY-MM-DD) |
| date_to | string | Data fim (YYYY-MM-DD) |
| page | int | Página (para paginação) |
| per_page | int | Itens por página |

**Recursos disponíveis:** Depende do driver configurado. Use o status da integração para obter a lista.

**Resposta 200:**

```json
{
  "processed": 50,
  "created": 0,
  "updated": 0,
  "failed": 0,
  "errors": []
}
```

**Resposta 404 (recurso inválido):**

```json
{
  "error": "Resource 'invalid' not available"
}
```

---

### 4.4 Enviar Dados

Envia dados para o sistema externo via driver configurado.

```
POST /api/v1/integrations/{integration}/data/{resource}
```

**Headers:** `X-Integration-Key: {api_key}`

**Body:**

```json
{
  "data": [
    {"name": "Produto A", "price": 29.90},
    {"name": "Produto B", "price": 49.90}
  ]
}
```

**Resposta 200:**

```json
{
  "processed": 2,
  "success": 2,
  "failed": 0,
  "errors": []
}
```

---

### 4.5 Receber Webhook

Endpoint para sistemas externos enviarem dados ao Mercury via push.

```
POST /api/v1/webhooks/{integration}
```

**Autenticação:** Via header `X-Webhook-Secret` ou campo `secret` no body.

**Headers:**

```
X-Webhook-Secret: SEU_WEBHOOK_SECRET
Content-Type: application/json
```

**Body:**

```json
{
  "resource": "sales",
  "data": [
    {"date": "2026-04-05", "store": "Z421", "value": 1500.00}
  ]
}
```

**Resposta 200:**

```json
{
  "status": "accepted",
  "records": 1
}
```

**Respostas de Erro:**

| Status | Descrição |
|--------|-----------|
| 401 | Webhook secret inválido |
| 403 | IP não permitido (se `allowed_ips` configurado) |
| 500 | Erro no processamento |

---

## 5. Drivers Disponíveis

### 5.1 Database

Conexão direta com banco de dados externo.

**Drivers suportados:** PostgreSQL, MySQL, SQL Server

**Campos de configuração:**

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| db_driver | select | Sim | pgsql, mysql, sqlsrv |
| db_host | text | Sim | Endereço do servidor |
| db_port | number | Sim | Porta |
| db_database | text | Sim | Nome do banco |
| db_username | text | Sim | Usuário |
| db_password | password | Sim | Senha |
| db_schema | text | Não | Schema (default: public) |
| db_timeout | number | Não | Timeout em segundos (default: 10) |
| default_table | text | Não | Tabela padrão para consultas |
| default_query | textarea | Não | Query SQL personalizada |

**Recursos:** Lista dinâmica de tabelas/views do banco conectado.

---

### 5.2 REST API

Integração via API HTTP/REST.

**Campos de configuração:**

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| base_url | url | Sim | URL base da API |
| auth_type | select | Sim | none, bearer, basic, api_key |
| auth_token | password | Condicional | Token Bearer |
| auth_username | text | Condicional | Usuário (Basic Auth) |
| auth_password | password | Condicional | Senha (Basic Auth) |
| auth_header | text | Não | Nome do header para API Key (default: X-API-Key) |
| health_endpoint | text | Não | Endpoint de health check (default: /) |
| pull_endpoint | text | Não | Endpoint para pull de dados |
| push_endpoint | text | Não | Endpoint para push de dados |
| timeout | number | Não | Timeout em segundos (default: 30) |

**Recursos padrão:** sales, products, employees, stores

---

### 5.3 Webhook

Recebe dados via POST de sistemas externos.

**Campos de configuração:**

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| webhook_secret | password | Sim | Secret para validação (auto-gerado se vazio) |
| api_key | text | Não | API Key (auto-gerada) |
| allowed_ips | text | Não | IPs permitidos (separados por vírgula) |

**Endpoint de recebimento:** `POST /api/v1/webhooks/{integration_id}`

---

### 5.4 CIGAM Sales

Driver especializado para sincronização de vendas do ERP CIGAM.

**Tabela CIGAM:** `msl_fmovimentodiario_`

**Campos sincronizados:** data, cod_lojas, cpf_consultora, valor_realizado, qtde, controle, ent_sai

**Lógica de negócio:**
- `controle=2` → Vendas
- `controle=6` + `ent_sai='E'` → Devoluções (valor negativo)

---

### 5.5 CIGAM Products

Driver especializado para sincronização de produtos do ERP CIGAM.

**Views CIGAM utilizadas:** `msl_produtos_`, `msl_prod_valor_`, `msl_dfornecedor_`, 8 views de lookup

**Funcionalidades:**
- Sincronização chunked (por lotes)
- Sync de preços separado
- Produtos editados manualmente (`sync_locked=true`) não são sobrescritos

---

## 6. Provedores (Presets)

Configurações pré-definidas para sistemas comuns:

| Provedor | Descrição | Drivers disponíveis |
|----------|-----------|---------------------|
| `cigam` | CIGAM ERP | cigam_sales, cigam_products, database |
| `sap` | SAP | rest_api, database |
| `totvs` | TOTVS Protheus | rest_api, database |
| `custom` | Personalizado | database, rest_api, webhook |

---

## 7. Formato de Resposta

Todas as respostas da API usam JSON. Respostas de sincronização seguem o formato padrão:

```json
{
  "processed": 100,
  "created": 10,
  "updated": 85,
  "failed": 5,
  "errors": ["mensagem de erro 1", "mensagem de erro 2"]
}
```

---

## 8. Códigos de Erro

| Código | Descrição |
|--------|-----------|
| 200 | Sucesso |
| 401 | Não autenticado (API Key inválida) |
| 403 | Acesso negado (IP bloqueado) |
| 404 | Recurso não encontrado |
| 422 | Dados inválidos |
| 429 | Rate limit excedido |
| 500 | Erro interno do servidor |

---

## 9. Exemplos de Uso

### cURL - Consultar status

```bash
curl -H "X-Integration-Key: SUA_API_KEY" \
  https://meia-sola.mercury.com.br/api/v1/integrations/1/status
```

### cURL - Disparar sincronização

```bash
curl -X POST \
  -H "X-Integration-Key: SUA_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"date_from":"2026-04-01","date_to":"2026-04-05"}' \
  https://meia-sola.mercury.com.br/api/v1/integrations/1/sync
```

### cURL - Enviar webhook

```bash
curl -X POST \
  -H "X-Webhook-Secret: SEU_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"resource":"sales","data":[{"date":"2026-04-05","store":"Z421","value":1500}]}' \
  https://meia-sola.mercury.com.br/api/v1/webhooks/1
```

### PHP - Enviar dados via REST

```php
$response = Http::withHeaders([
    'X-Integration-Key' => $apiKey,
])->post('https://meia-sola.mercury.com.br/api/v1/integrations/1/data/sales', [
    'data' => $salesData,
]);
```

### JavaScript - Consultar dados

```javascript
const response = await fetch(
  'https://meia-sola.mercury.com.br/api/v1/integrations/1/data/products?page=1&per_page=50',
  {
    headers: { 'X-Integration-Key': apiKey }
  }
);
const data = await response.json();
```
