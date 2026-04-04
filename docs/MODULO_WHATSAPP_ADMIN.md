# Módulo WhatsApp Admin — Documentação Técnica e Manual de Usuário

**Data:** 30/03/2026
**Versão:** 2.0
**Acesso:** Super Admin (nível 1)
**Rota:** `/whatsapp-admin/index`

---

## 1. Visão Geral

O módulo WhatsApp Admin é um painel administrativo integrado ao Mercury que fornece uma interface visual para gerenciar a instância WhatsApp (Evolution API v2) e a automação de workflows (N8N), eliminando a necessidade de executar comandos curl no terminal ou acessar múltiplas ferramentas externas separadamente.

### Problema Resolvido

Antes deste módulo, qualquer operação na Evolution API ou N8N exigia:
- Acesso SSH ao servidor VPS
- Execução manual de comandos curl com headers de autenticação
- Consulta à documentação da Evolution API para lembrar endpoints e payloads
- Acesso separado ao painel N8N em outra URL

Agora, tudo é feito por uma interface visual dentro do próprio Mercury.

---

## 2. Arquitetura

```
┌─────────────────────────────────────────────┐
│  Browser (Mercury)                          │
│  ┌───────────────────────────────────────┐  │
│  │  whatsapp-admin.js                    │  │
│  │  waApiCall(endpoint, method, payload) │  │
│  └──────────────┬────────────────────────┘  │
│                 │ AJAX (X-Requested-With)    │
└─────────────────┼───────────────────────────┘
                  ▼
┌─────────────────────────────────────────────┐
│  WhatsappAdmin Controller (proxy)           │
│  POST /whatsapp-admin/api                   │
│                                             │
│  - Injeta apikey do .env                    │
│  - Substitui {instance} pelo nome real      │
│  - Repassa request via cURL                 │
│  - Retorna JSON da resposta                 │
└──────────────┬──────────────────────────────┘
               │ cURL (server-side)
               ▼
┌──────────────────────┐    ┌─────────────────┐
│  Evolution API v2    │    │  N8N             │
│  (VPS ou localhost)  │    │  (link direto)   │
│  :8085               │    │  :5678           │
└──────────────────────┘    └─────────────────┘
```

### Infraestrutura de Produção (VPS)

```
┌─────────────────────────────────────────────────────┐
│                VPS (Docker)                          │
│  Domínio: ws.portalmercury.com.br                   │
│                                                     │
│  Nginx (reverse proxy + SSL Let's Encrypt)          │
│    ├── /evolution/ ──► Evolution API :8080           │
│    ├── /n8n/       ──► N8N Editor :5678             │
│    └── /webhook/   ──► N8N Webhooks :5678           │
│                                                     │
│  Fluxo de mensagens recebidas:                      │
│  WhatsApp ──► Evolution API ──► webhook global      │
│    ──► N8N ──► Mercury API (POST /api/v1/dp-chat)   │
│                                                     │
│  PostgreSQL (dados Evolution)                       │
│  Redis (cache Evolution)                            │
└─────────────────────────────────────────────────────┘
```

**Segurança:** A API key da Evolution API nunca é exposta no frontend. Todas as chamadas passam pelo proxy backend que injeta a autenticação no servidor.

---

## 3. Estrutura de Arquivos

```
mercury/
├── app/adms/Controllers/
│   └── WhatsappAdmin.php              # Controller com proxy API
├── app/adms/Views/whatsAppAdmin/
│   └── loadWhatsAppAdmin.php          # View principal (6 abas)
├── app/adms/Services/
│   └── WhatsAppService.php            # Envio programático de mensagens
├── assets/js/
│   └── whatsapp-admin.js              # Handlers JS + proxy client
├── docker/
│   ├── docker-compose.prod.yml        # Stack de produção
│   ├── nginx/nginx.conf               # Reverse proxy + SSL
│   └── .env.prod                      # Variáveis de produção
└── database/migrations/
    └── 2026_03_29_add_whatsapp_admin_routes.sql
```

---

## 4. Configuração

### 4.1 Variáveis de Ambiente (`.env`)

#### Obrigatórias

| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `EVOLUTION_API_URL` | URL base da Evolution API | `http://localhost:8085` |
| `EVOLUTION_API_KEY` | Chave de autenticação da API | `meia-sola-evo-2026` |
| `EVOLUTION_INSTANCE` | Nome da instância WhatsApp | `mercury-dp` |

#### Opcionais

| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `N8N_URL` | URL do painel N8N | `http://localhost:5678` |
| `VPS_DOMAIN` | Domínio da VPS (fallback para N8N URL) | `ws.portalmercury.com.br` |
| `WEBHOOK_GLOBAL_ENABLED` | Webhook global ativo | `true` |
| `WEBHOOK_GLOBAL_URL` | URL do webhook global | `http://n8n:5678/webhook/whatsapp` |
| `APP_URL` | URL pública do Mercury (para mídia) | `https://portalmercury.com.br` |

#### Prioridade da URL N8N

1. Se `N8N_URL` está definido, usa diretamente
2. Se não, e `VPS_DOMAIN` está definido, constrói `https://{VPS_DOMAIN}/n8n/`
3. Se nenhum está definido, mostra mensagem de configuração

### 4.2 Ambiente Local

```env
EVOLUTION_API_URL=http://localhost:8085
EVOLUTION_API_KEY=meia-sola-evo-2026
EVOLUTION_INSTANCE=mercury-dp
N8N_URL=http://localhost:5678
```

### 4.3 Ambiente de Produção (VPS)

**Domínio:** `ws.portalmercury.com.br`

```env
# docker/.env.prod
VPS_DOMAIN=ws.portalmercury.com.br
SERVER_URL=https://ws.portalmercury.com.br/evolution
WEBHOOK_GLOBAL_ENABLED=true
WEBHOOK_GLOBAL_URL=http://n8n:5678/webhook/whatsapp
```

**Variáveis N8N no container:**

| Variável | Valor | Função |
|----------|-------|--------|
| `N8N_PATH_PREFIX` | `/n8n` | Subpath do editor atrás do Nginx |
| `N8N_EDITOR_BASE_URL` | `https://ws.portalmercury.com.br/n8n/` | URL base para assets do editor |
| `WEBHOOK_URL` | `https://ws.portalmercury.com.br/webhook/` | URL pública dos webhooks N8N |

### 4.4 Rotas no Banco

A migration `2026_03_29_add_whatsapp_admin_routes.sql` registra:
- `WhatsappAdmin/index` - página principal
- `WhatsappAdmin/api` - proxy API

**Permissões:** Apenas nível de acesso 1 (Super Admin).

---

## 5. Controller

### `WhatsappAdmin.php`

| Método | Rota | Descrição |
|--------|------|-----------|
| `index()` | `GET /whatsapp-admin/index` | Renderiza o painel com 6 abas |
| `api()` | `GET/POST /whatsapp-admin/api` | Proxy para Evolution API |

### Proxy API — Como Funciona

O método `api()` aceita dois modos:

**GET** (leitura):
```
GET /whatsapp-admin/api?endpoint=/instance/connectionState/{instance}
```

**POST** (escrita):
```json
POST /whatsapp-admin/api
Content-Type: application/json

{
    "endpoint": "/message/sendText/{instance}",
    "method": "POST",
    "payload": {
        "number": "5511999999999",
        "text": "Mensagem de teste"
    }
}
```

O placeholder `{instance}` é substituído automaticamente pelo valor de `EVOLUTION_INSTANCE` do `.env`.

**Resposta padrão:**
```json
{
    "error": false,
    "http_code": 200,
    "data": { ... }
}
```

**Tratamento de `null`:** Quando a Evolution API retorna `null` (ex: webhook não configurado por instância), o proxy usa `json_last_error()` para distinguir JSON válido de erro de parsing, garantindo que `null` chegue como `null` no frontend (não como a string `"null"`).

---

## 6. Abas do Painel

### 6.1 Status

Verifica o estado da conexão WhatsApp em tempo real.

**Layout:** Dois cards na mesma linha (Conexão + QR Code) e um card abaixo (Instância).

#### Card Conexão

| Estado | Badge | Descrição |
|--------|-------|-----------|
| `open` | Verde - Conectado | Instância conectada e pronta |
| `close` | Vermelho - Desconectado | Necessita reconexão via QR Code |
| `connecting` | Amarelo - Conectando | Aguardando leitura do QR Code |

**Endpoint:** `GET /instance/connectionState/{instance}`

#### Card QR Code

Quando desconectada, o botão "Gerar QR Code" chama `GET /instance/connect/{instance}` e exibe a imagem base64. Também mostra o código de pareamento quando disponível.

#### Card Instância

Exibe nome da instância e API URL. A URL usa `word-break: break-all` para não estourar o card.

---

### 6.2 Enviar Mensagem

Dois cards lado a lado para teste de envio:

**Texto:**
- Campo: número (com DDD, ex: `11999999999` — o JS adiciona `55` automaticamente)
- Campo: mensagem
- Endpoint: `POST /message/sendText/{instance}`

**Mídia:**
- Campo: número
- Campo: tipo (image / video / document)
- Campo: URL ou base64 da mídia
- Campo: legenda (opcional)
- Endpoint: `POST /message/sendMedia/{instance}`

---

### 6.3 Instância

Três cards em linhas separadas, cada um com largura total:

#### Card Instâncias Registradas

Tabela com todas as instâncias registradas na Evolution API:

| Coluna | Campo da API | Descrição |
|--------|-------------|-----------|
| Nome | `name` | Nome da instância |
| Estado | `connectionStatus` | `open` (Conectado) ou `close` (Desconectado) |
| Telefone | `ownerJid` | Número do chip (sem `@s.whatsapp.net`) |
| Perfil | `profileName` | Nome do perfil WhatsApp |
| Mensagens | `_count.Message` | Total de mensagens armazenadas |
| Contatos | `_count.Contact` | Total de contatos |

**Endpoint:** `GET /instance/fetchInstances`

**Uso principal:** Verificar qual chip (número de telefone) está associado à instância.

#### Card Configurações da Instância

Exibe as configurações atuais em JSON formatado.

**Endpoint:** `GET /settings/find/{instance}` (Evolution API v2)

> **Nota:** O endpoint antigo `/instance/settings/{instance}` não existe na v2. Usar `/settings/find/{instance}`.

#### Card Ações da Instância

| Ação | Método | Endpoint | Uso |
|------|--------|----------|-----|
| Conectar | GET | `/instance/connect/{instance}` | Gerar QR Code / reconectar |
| Reiniciar | PUT | `/instance/restart/{instance}` | Reiniciar sem desconectar |
| Desconectar | DELETE | `/instance/logout/{instance}` | Logout (exige confirmação) |

---

### 6.4 Webhooks

#### Webhook Global vs. Por Instância

| Tipo | Configuração | Consulta via API |
|------|-------------|-----------------|
| **Global** | Variáveis `WEBHOOK_GLOBAL_*` no `.env` | Não consultável |
| **Por instância** | Via `/webhook/set/{instance}` | `/webhook/find/{instance}` |

O webhook global envia eventos de **todas** as instâncias para uma única URL. O webhook por instância é opcional e complementar.

A aba exibe um alerta informativo no topo:
- **Verde:** Webhook global ativo, mostrando a URL configurada
- **Amarelo:** Webhook global desativado, orientando configuração

#### Carregar Webhook Atual

- Endpoint: `GET /webhook/find/{instance}`
- Se nenhum webhook por instância estiver configurado, exibe "Nenhum webhook configurado para esta instância" (normal quando se usa webhook global)
- Se configurado, preenche automaticamente o formulário abaixo

#### Configurar Webhook

| Campo | Descrição |
|-------|-----------|
| URL do Webhook | Ex: `http://n8n:5678/webhook/whatsapp` |
| Webhook ativo | Checkbox para ativar/desativar |
| Eventos | Seleção múltipla dos tipos de evento |

**Eventos disponíveis:**

| Evento | Descrição |
|--------|-----------|
| `MESSAGES_UPSERT` | Mensagens recebidas |
| `MESSAGES_UPDATE` | Mensagens atualizadas (lido, entregue) |
| `SEND_MESSAGE` | Mensagens enviadas |
| `CONNECTION_UPDATE` | Mudanças no status da conexão |
| `QRCODE_UPDATED` | QR Code gerado/atualizado |

**Endpoint de salvamento:** `POST /webhook/set/{instance}`

---

### 6.5 N8N

O N8N bloqueia embedding em iframe por segurança (`X-Frame-Options: SAMEORIGIN`). Por isso, a aba exibe um botão "Abrir N8N" que abre o painel em uma nova aba do navegador.

**Informações exibidas:**
- URL configurada do N8N
- Botão para abrir em nova aba

**Se a URL não estiver configurada:** Mensagem orientando a definir `N8N_URL` no `.env` ou `VPS_DOMAIN`.

---

### 6.6 Console API

Terminal interativo para chamadas diretas à Evolution API (tipo Postman embutido).

**Campos:**
- Método: GET / POST / PUT / DELETE
- Endpoint: com placeholder `{instance}` (substituído automaticamente)
- Body: JSON para POST/PUT (validado antes do envio)

**Atalhos rápidos:**

| Botão | Método | Endpoint |
|-------|--------|----------|
| Status | GET | `/instance/connectionState/{instance}` |
| Instâncias | GET | `/instance/fetchInstances` |
| QR Code | GET | `/instance/connect/{instance}` |
| Chats | POST | `/chat/findChats/{instance}` |
| Enviar Texto | POST | `/message/sendText/{instance}` |
| Settings | GET | `/settings/find/{instance}` |
| Webhook | GET | `/webhook/find/{instance}` |

**Resposta:** Exibida em `<pre>` com JSON formatado (`white-space: pre-wrap` para controlar overflow), badge de HTTP code colorido (verde 2xx, vermelho 4xx/5xx, amarelo outros).

---

## 7. WhatsAppService — Envio Programático

Serviço reutilizável para envio de mensagens WhatsApp em qualquer parte do Mercury.

### Uso

```php
use App\adms\Services\WhatsAppService;

$wa = new WhatsAppService();

// Verificar se está configurado
if (!$wa->isConfigured()) {
    return;
}

// Enviar texto
$wa->sendMessage('5585999999999', 'Mensagem de teste');

// Enviar mídia
$wa->sendMedia(
    '5585999999999',
    '/caminho/para/arquivo.pdf',
    'document',
    'relatorio.pdf',
    'Segue o relatório'
);

// Normalizar telefone (método estático)
$phone = WhatsAppService::normalizePhone('85999999999');
// Resultado: '5585999999999'
```

### Características

- **Nunca lança exceções** — retorna `false` em caso de erro
- **Logging automático** — todas as operações registradas via `LoggerService`
- **Normalização de telefone** — adiciona prefixo `55` (Brasil) automaticamente
- **Suporte a mídia** — imagens, vídeos e documentos via URL pública ou base64
- **Timeouts** — 10s para texto, 60s para mídia

### Tipos de mídia

| Tipo | Extensões | Parâmetro `mediaType` |
|------|-----------|----------------------|
| Imagem | jpg, png, gif | `image` |
| Vídeo | mp4, avi | `video` |
| Documento | pdf, docx, xlsx | `document` |

---

## 8. Infraestrutura Docker (Produção)

### Stack de Serviços

| Serviço | Imagem | Porta Interna | Acesso Externo | Memória |
|---------|--------|--------------|----------------|---------|
| Nginx | nginx:alpine | 80, 443 | Sim (reverse proxy) | 128M |
| Evolution API | evoapicloud/evolution-api:v2.3.7 | 8080 | Via `/evolution/` | 512M |
| N8N | n8nio/n8n:1.79.3 | 5678 | Via `/n8n/` | 512M |
| PostgreSQL | postgres:15-alpine | 5432 | Não (rede interna) | 256M |
| Redis | redis:7-alpine | 6379 | Não (rede interna) | 96M |
| Certbot | certbot/certbot | — | — | — |

### Nginx — Rotas

| Path | Destino | Rate Limit | WebSocket |
|------|---------|------------|-----------|
| `/evolution/` | `evolution-api:8080` | 30 req/min (burst 10) | Sim |
| `/n8n/` | `n8n:5678` | — | Sim |
| `/webhook/` | `n8n:5678/webhook/` | 60 req/min (burst 20) | Não |
| `/health` | Resposta direta | — | Não |

### Security Headers

| Header | Valor | Função |
|--------|-------|--------|
| `X-Frame-Options` | `SAMEORIGIN` | Permite iframe do mesmo domínio |
| `X-Content-Type-Options` | `nosniff` | Previne MIME sniffing |
| `X-XSS-Protection` | `1; mode=block` | Proteção XSS do browser |

### SSL

- **Provedor:** Let's Encrypt via Certbot
- **Renovação:** Automática a cada 12h
- **Protocolos:** TLS 1.2 e 1.3

### Fluxo de Mensagens Recebidas

```
WhatsApp (celular)
    │
    ▼
Evolution API (recebe mensagem via Baileys)
    │
    ▼ webhook global (http://n8n:5678/webhook/whatsapp)
N8N (filtra: apenas texto, exclui grupos, status, ACKs)
    │
    ▼ HTTP POST
Mercury API (POST /api/v1/dp-chat/message)
    │
    ▼
DpChatController
    ├── Identifica funcionário (CPF)
    ├── Gerencia fluxo conversacional
    ├── Cria/atualiza solicitação DP
    └── Notifica equipe via WebSocket
```

---

## 9. Procedimentos Operacionais

### 9.1 Verificar Chip Conectado

1. **Aba Instância** - clicar **Listar Instâncias**
2. Verificar a coluna **Telefone** — exibe o número do chip ativo
3. Confirmar que o nome do **Perfil** corresponde ao esperado

### 9.2 Troca de Chip

1. **Aba Instância** - clicar **Desconectar (Logout)** - confirmar
2. Trocar o chip SIM no celular
3. Abrir o WhatsApp no celular com o novo número
4. **Aba Status** - clicar **Gerar QR Code**
5. Escanear o QR Code exibido na tela com o celular
6. Aguardar o badge mudar para "Conectado" (verde)
7. **Aba Instância** - clicar **Listar Instâncias** - confirmar telefone correto
8. **Aba Enviar Mensagem** - enviar mensagem de teste para confirmar

### 9.3 Reconexão após Queda

1. **Aba Status** - verificar estado
2. Se `Desconectado`: clicar **Gerar QR Code** e escanear
3. Se `Conectando`: aguardar ou **Aba Instância** - **Reiniciar**
4. Se "Evolution API inacessível": verificar VPS/Docker via SSH

### 9.4 Configurar Webhook para N8N

1. **Aba Webhooks** - clicar **Carregar Webhook Atual**
2. Preencher URL: `http://n8n:5678/webhook/whatsapp` (Docker interno)
3. Marcar eventos necessários (mínimo: `MESSAGES_UPSERT`)
4. Clicar **Salvar Webhook**
5. Testar: enviar mensagem do WhatsApp e verificar se o N8N recebe

### 9.5 Debug de Integração

1. **Aba Console** - atalho **Status** - verificar se instância está `open`
2. **Aba Console** - atalho **Enviar Texto** - preencher número e enviar
3. Verificar resposta: HTTP 201 = sucesso, HTTP 400 = payload inválido, HTTP 401 = API key errada
4. **Aba Console** - atalho **Webhook** - verificar se URL está correta
5. **N8N** - abrir e verificar execuções do workflow

### 9.6 Verificar Estado dos Containers (VPS)

```bash
# Listar containers
docker compose -f docker-compose.prod.yml ps

# Verificar variáveis do N8N
docker inspect n8n --format '{{range .Config.Env}}{{println .}}{{end}}' | grep -E "N8N_PATH|EDITOR_BASE|WEBHOOK_URL"

# Logs recentes
docker compose -f docker-compose.prod.yml logs --tail 20

# Reiniciar um serviço específico
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d n8n
```

---

## 10. Endpoints da Evolution API v2

Referência rápida dos endpoints utilizados:

### Instância

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/instance/connectionState/{instance}` | Estado da conexão |
| GET | `/instance/fetchInstances` | Listar todas as instâncias |
| GET | `/instance/connect/{instance}` | Gerar QR Code |
| GET | `/settings/find/{instance}` | Configurações da instância |
| PUT | `/instance/restart/{instance}` | Reiniciar |
| DELETE | `/instance/logout/{instance}` | Desconectar |

### Mensagens

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/message/sendText/{instance}` | Enviar texto |
| POST | `/message/sendMedia/{instance}` | Enviar imagem/vídeo/documento |

### Webhook

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/webhook/find/{instance}` | Webhook por instância |
| POST | `/webhook/set/{instance}` | Configurar webhook |

### Chats

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/chat/findChats/{instance}` | Listar conversas |
| POST | `/chat/findContacts/{instance}` | Buscar contatos (requer filtro) |

> **Nota:** `/chat/findContacts` é POST e exige body com filtro, ex: `{"where": {"pushName": "João"}}`. Não aceita filtro vazio.

### Formato da resposta de `fetchInstances`

```json
[
  {
    "id": "f3113723-...",
    "name": "mercury-dp",
    "connectionStatus": "open",
    "ownerJid": "558587460451@s.whatsapp.net",
    "profileName": "Nome do Perfil",
    "integration": "WHATSAPP-BAILEYS",
    "_count": {
      "Message": 1567,
      "Contact": 540,
      "Chat": 208
    }
  }
]
```

---

## 11. Segurança

| Aspecto | Implementação |
|---------|---------------|
| **Autenticação** | Verificada automaticamente pelo `ConfigController` (sessão) |
| **Autorização** | Restrito a nível 1 via `adms_nivacs_pgs` |
| **API Key** | Armazenada no `.env`, injetada server-side — nunca exposta no JS |
| **CSRF** | Token validado automaticamente pelo `ConfigController` |
| **Proxy** | Todas as chamadas passam por `/whatsapp-admin/api` |
| **Input** | Endpoints sanitizados, `{instance}` substituído no servidor |
| **Rate Limit** | Nginx limita requisições por IP (API: 30/min, Webhooks: 60/min) |

---

## 12. Troubleshooting

| Sintoma | Causa Provável | Solução |
|---------|---------------|---------|
| "Evolution API não configurada" | `.env` sem variáveis | Definir `EVOLUTION_API_URL`, `EVOLUTION_API_KEY`, `EVOLUTION_INSTANCE` |
| Badge "Offline" | Evolution API inacessível | Verificar VPS, Docker, firewall |
| HTTP 401 no Console | API key errada | Verificar `EVOLUTION_API_KEY` no `.env` |
| QR Code não aparece | Instância já conectada | Se estado é `open`, já está OK |
| Tabela de instâncias vazia | Nenhuma instância criada | Criar instância via Console API |
| Settings "Cannot GET" | Endpoint errado | Usar `/settings/find/{instance}` (v2) |
| Webhook mostra "null" | Webhook é global, não por instância | Normal — alerta no topo da aba explica |
| N8N "não configurada" | Variáveis ausentes | Definir `N8N_URL` ou `VPS_DOMAIN` no `.env` |
| N8N não carrega | Bloqueio X-Frame-Options | Usar botão "Abrir N8N" (nova aba) |
| Console resposta cortada | Overflow horizontal | O `<pre>` usa `pre-wrap` — scroll automático |
| "Erro de conexão" | cURL timeout | Verificar rede entre Mercury e Evolution API |
| Contatos retorna `[]` | Filtro muito restritivo | Ajustar `pushName` no body do request |

---

## 13. Relação com Outros Módulos

```
WhatsApp Admin (gerenciamento)
    │
    ├── Evolution API ←→ WhatsAppService.php (envio de mensagens)
    │                       │
    │                       ├── PersonnelRequests (Solicitações DP)
    │                       └── DpChatController (webhook incoming)
    │
    └── N8N ←→ Workflows de automação
                    │
                    ├── Classificação de demandas
                    ├── Criação automática de tickets
                    └── Respostas conversacionais (CPF → demanda)
```

O WhatsApp Admin **não interfere** no fluxo operacional dos módulos — é uma ferramenta de gerenciamento e debug. As mensagens continuam sendo enviadas/recebidas pelo `WhatsAppService` e `DpChatController` normalmente.

---

## 14. Manutenção

### Atualizar Evolution API

```bash
# Alterar versão em docker-compose.prod.yml
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d evolution-api
```

### Atualizar N8N

```bash
# Alterar versão em docker-compose.prod.yml
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d n8n
```

### Renovar certificado SSL

```bash
docker compose -f docker-compose.prod.yml exec certbot certbot renew --webroot -w /var/www/certbot
docker compose -f docker-compose.prod.yml exec nginx nginx -s reload
```

### Recarregar Nginx (após alteração no nginx.conf)

```bash
docker compose -f docker-compose.prod.yml exec nginx nginx -s reload
```
