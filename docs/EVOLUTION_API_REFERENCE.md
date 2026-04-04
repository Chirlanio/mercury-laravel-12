# Evolution API v2 — Guia de Referência

**Versão da API:** v2.1.1
**Documentação oficial:** https://doc.evolution-api.com/v2/pt/get-started/introduction
**Repositório:** https://github.com/EvolutionAPI/evolution-api
**Postman Collection:** https://www.postman.com/agenciadgcode/evolution-api/collection/gqr041s/evolution-api-v2-0
**Última atualização deste documento:** 27/03/2026

---

## 1. Visão Geral

A Evolution API v2 é uma plataforma **open-source e gratuita** para integração com WhatsApp e outras plataformas de mensageria. Baseada na biblioteca **Baileys**, também suporta a **API oficial do WhatsApp Business (Cloud API)**.

### Integrações Nativas
- **Mensageria:** WhatsApp (Baileys + Cloud API), Instagram (planejado), Messenger (planejado)
- **Chatbots:** Typebot, Flowise, Evolution Bot
- **Atendimento:** Chatwoot
- **IA:** OpenAI, Dify, EvoAI
- **Automação:** n8n
- **Filas:** RabbitMQ, Amazon SQS
- **Armazenamento:** Amazon S3, MinIO
- **Real-time:** WebSocket

---

## 2. Instalação

### 2.1 Docker Compose (Recomendado)

```yaml
version: '3.9'
services:
  evolution-api:
    container_name: evolution_api
    image: atendai/evolution-api:v2.1.1
    restart: always
    ports:
      - "8080:8080"
    env_file:
      - .env
    volumes:
      - evolution_instances:/evolution/instances

volumes:
  evolution_instances:
```

**.env mínimo:**
```bash
AUTHENTICATION_API_KEY=sua-chave-secreta-aqui
```

**Comandos:**
```bash
docker compose up -d          # Iniciar
docker logs evolution_api     # Ver logs
docker compose down           # Parar
```

**Acesso:** http://localhost:8080

### 2.2 Docker Swarm (Produção)

Para ambiente de produção com Traefik (SSL automático via Let's Encrypt):

```bash
# 1. Configurar hostname
hostnamectl set-hostname manager1

# 2. Instalar Docker
curl -fsSL https://get.docker.com | bash

# 3. Iniciar Swarm
docker swarm init --advertise-addr IP_SERVER

# 4. Criar rede overlay
docker network create --driver=overlay network_public

# 5. Deploy Traefik
docker stack deploy --prune --resolve-image always -c traefik.yaml traefik

# 6. Deploy Evolution API
docker stack deploy --prune --resolve-image always -c evolution_api_v2.yaml evolution_v2
```

### 2.3 NVM (Desenvolvimento)

Consultar: https://doc.evolution-api.com/v2/pt/install/nvm

---

## 3. Variáveis de Ambiente

### 3.1 Servidor

| Variável | Descrição | Tipo | Padrão |
|----------|-----------|------|--------|
| `SERVER_TYPE` | Protocolo (http/https) | string | http |
| `SERVER_PORT` | Porta de execução | number | 8080 |
| `SERVER_URL` | URL pública do servidor | string | — |

### 3.2 Autenticação

| Variável | Descrição | Tipo |
|----------|-----------|------|
| `AUTHENTICATION_API_KEY` | Chave global da API | string |
| `AUTHENTICATION_EXPOSE_IN_FETCH_INSTANCES` | Exibir instâncias sem auth | boolean |

### 3.3 Banco de Dados

| Variável | Descrição | Tipo |
|----------|-----------|------|
| `DATABASE_ENABLED` | Ativa persistência | boolean |
| `DATABASE_PROVIDER` | `postgresql` ou `mysql` | string |
| `DATABASE_CONNECTION_URI` | URI de conexão | string |
| `DATABASE_CONNECTION_CLIENT_NAME` | Identificador da instalação | string |
| `DATABASE_SAVE_DATA_INSTANCE` | Salvar dados de instâncias | boolean |
| `DATABASE_SAVE_DATA_NEW_MESSAGE` | Salvar novas mensagens | boolean |
| `DATABASE_SAVE_MESSAGE_UPDATE` | Salvar atualizações | boolean |
| `DATABASE_SAVE_DATA_CONTACTS` | Salvar contatos | boolean |
| `DATABASE_SAVE_DATA_CHATS` | Salvar conversas | boolean |
| `DATABASE_SAVE_DATA_LABELS` | Salvar etiquetas | boolean |
| `DATABASE_SAVE_DATA_HISTORIC` | Salvar histórico de eventos | boolean |

### 3.4 Cache (Redis)

| Variável | Descrição | Tipo |
|----------|-----------|------|
| `CACHE_REDIS_ENABLED` | Ativa Redis | boolean |
| `CACHE_REDIS_URI` | URI de conexão | string |
| `CACHE_REDIS_PREFIX_KEY` | Prefixo de diferenciação | string |
| `CACHE_REDIS_SAVE_INSTANCES` | Salvar credenciais no Redis | boolean |
| `CACHE_LOCAL_ENABLED` | Cache local em memória | boolean |

### 3.5 Webhook Global

| Variável | Descrição | Tipo |
|----------|-----------|------|
| `WEBHOOK_GLOBAL_ENABLED` | Ativa webhooks globais | boolean |
| `WEBHOOK_GLOBAL_URL` | URL de recebimento | string |
| `WEBHOOK_GLOBAL_WEBHOOK_BY_EVENTS` | URL por evento | boolean |
| `WEBHOOK_EVENTS_APPLICATION_STARTUP` | Evento: startup | boolean |
| `WEBHOOK_EVENTS_QRCODE_UPDATED` | Evento: QR code | boolean |
| `WEBHOOK_EVENTS_MESSAGES_UPSERT` | Evento: msg recebida | boolean |
| `WEBHOOK_EVENTS_MESSAGES_UPDATE` | Evento: msg atualizada | boolean |
| `WEBHOOK_EVENTS_SEND_MESSAGE` | Evento: msg enviada | boolean |
| `WEBHOOK_EVENTS_CONNECTION_UPDATE` | Evento: conexão | boolean |
| `WEBHOOK_EVENTS_CONTACTS_UPDATE` | Evento: contatos | boolean |
| `WEBHOOK_EVENTS_PRESENCE_UPDATE` | Evento: presença | boolean |
| `WEBHOOK_EVENTS_ERRORS` | Evento: erros | boolean |
| `WEBHOOK_EVENTS_ERRORS_WEBHOOK` | URL específica para erros | string |

### 3.6 WhatsApp Business API

| Variável | Descrição | Tipo |
|----------|-----------|------|
| `WA_BUSINESS_TOKEN_WEBHOOK` | Token de validação | string |
| `WA_BUSINESS_URL` | URL da API Meta | string |
| `WA_BUSINESS_VERSION` | Versão da API | string |
| `WA_BUSINESS_LANGUAGE` | Idioma padrão | string |

### 3.7 Sessão e QR Code

| Variável | Descrição | Tipo |
|----------|-----------|------|
| `CONFIG_SESSION_PHONE_CLIENT` | Nome exibido na conexão | string |
| `CONFIG_SESSION_PHONE_NAME` | Nome do navegador | string |
| `QRCODE_LIMIT` | Duração do QR code (minutos) | number |
| `QRCODE_COLOR` | Cor do QR code (hex) | string |

### 3.8 Integrações

| Variável | Descrição | Tipo |
|----------|-----------|------|
| `CHATWOOT_ENABLED` | Integração Chatwoot | boolean |
| `CHATWOOT_MESSAGE_READ` | Marcar como lida | boolean |
| `CHATWOOT_MESSAGE_DELETE` | Deletar mensagens | boolean |
| `CHATWOOT_IMPORT_DATABASE_CONNECTION_URI` | Importar do Chatwoot | string |
| `OPENAI_ENABLED` | Integração OpenAI | boolean |
| `DIFY_ENABLED` | Integração Dify | boolean |
| `TYPEBOT_API_VERSION` | Versão API Typebot | string |

### 3.9 Amazon S3 / MinIO

| Variável | Descrição | Tipo |
|----------|-----------|------|
| `S3_ENABLED` | Ativa armazenamento S3 | boolean |
| `S3_ACCESS_KEY` | Chave de acesso | string |
| `S3_SECRET_KEY` | Chave secreta | string |
| `S3_BUCKET` | Nome do bucket | string |
| `S3_PORT` | Porta de conexão | number |
| `S3_ENDPOINT` | Endpoint S3/MinIO | string |
| `S3_USE_SSL` | Usar SSL | boolean |

### 3.10 RabbitMQ / SQS / WebSocket / Logs

| Variável | Descrição | Tipo |
|----------|-----------|------|
| `RABBITMQ_ENABLED` | Ativa RabbitMQ | boolean |
| `RABBITMQ_URI` | URI de conexão | string |
| `SQS_ENABLED` | Ativa Amazon SQS | boolean |
| `SQS_ACCESS_KEY_ID` / `SQS_SECRET_ACCESS_KEY` | Credenciais AWS | string |
| `WEBSOCKET_ENABLED` | Ativa WebSocket | boolean |
| `LOG_LEVEL` | Níveis de log | string |
| `LOG_COLOR` | Colorir logs | boolean |
| `LOG_BAILEYS` | Logs do Baileys | string |
| `DEL_INSTANCE` | Min. para deletar instância inativa | number |
| `CORS_ORIGIN` | Origens CORS permitidas | string |
| `TELEMETRY` | Habilita telemetria | boolean |
| `LANGUAGE` | Idioma da API | string |

### 3.11 Exemplo .env Completo (Produção)

```bash
# Servidor
SERVER_TYPE=https
SERVER_PORT=8080
SERVER_URL=https://evo2.meudominio.com

# Autenticação
AUTHENTICATION_API_KEY=429683C4C977415CAAFCCE10F7D57E11

# Banco de Dados
DATABASE_ENABLED=true
DATABASE_PROVIDER=postgresql
DATABASE_CONNECTION_URI=postgresql://postgres:SENHA@postgres:5432/evolution
DATABASE_SAVE_DATA_INSTANCE=true
DATABASE_SAVE_DATA_NEW_MESSAGE=true
DATABASE_SAVE_MESSAGE_UPDATE=true
DATABASE_SAVE_DATA_CONTACTS=true
DATABASE_SAVE_DATA_CHATS=true

# Redis
CACHE_REDIS_ENABLED=true
CACHE_REDIS_URI=redis://evo_redis:6379/1

# S3/MinIO
S3_ENABLED=true
S3_ACCESS_KEY=minha-chave
S3_SECRET_KEY=minha-secret
S3_BUCKET=evolution
S3_ENDPOINT=s3.meudominio.com
S3_USE_SSL=true

# Webhook
WEBHOOK_GLOBAL_ENABLED=false
```

---

## 4. Autenticação da API

Todas as requisições exigem o header `apikey`:

```
apikey: sua-chave-global-ou-da-instancia
```

- A **chave global** é definida em `AUTHENTICATION_API_KEY`
- Cada **instância** pode ter sua própria `token` definida na criação
- Ambas as chaves são aceitas para autenticação

---

## 5. Gerenciamento de Instâncias

### 5.1 Criar Instância

```
POST /instance/create
```

**Headers:**
```
apikey: sua-chave
Content-Type: application/json
```

**Body (completo):**
```json
{
  "instanceName": "minha-instancia",
  "integration": "WHATSAPP-BAILEYS",
  "token": "chave-customizada-opcional",
  "qrcode": true,
  "number": "5511999999999",
  "rejectCall": false,
  "msgCall": "Não posso atender agora",
  "groupsIgnore": true,
  "alwaysOnline": false,
  "readMessages": false,
  "readStatus": false,
  "syncFullHistory": false,
  "webhook": {
    "url": "https://meu-server.com/webhook",
    "byEvents": false,
    "base64": false,
    "headers": {
      "authorization": "Bearer meu-token",
      "Content-Type": "application/json"
    },
    "events": [
      "MESSAGES_UPSERT",
      "MESSAGES_UPDATE",
      "SEND_MESSAGE",
      "CONNECTION_UPDATE"
    ]
  },
  "rabbitmq": {
    "enabled": false,
    "events": []
  },
  "sqs": {
    "enabled": false,
    "events": []
  },
  "chatwootAccountId": 1,
  "chatwootToken": "token-chatwoot",
  "chatwootUrl": "https://chatwoot.exemplo.com",
  "chatwootSignMsg": true,
  "chatwootReopenConversation": true,
  "chatwootConversationPending": false,
  "chatwootImportContacts": true,
  "chatwootImportMessages": true,
  "chatwootDaysLimitImportMessages": 30,
  "chatwootNameInbox": "WhatsApp",
  "proxyHost": "",
  "proxyPort": "",
  "proxyProtocol": "",
  "proxyUsername": "",
  "proxyPassword": ""
}
```

**`integration` aceita:** `WHATSAPP-BAILEYS` ou `WHATSAPP-BUSINESS`

**Resposta (201):**
```json
{
  "instance": {
    "instanceName": "minha-instancia",
    "instanceId": "af6c5b7c-ee27-4f94-9ea8-192393746ddd",
    "status": "created"
  },
  "hash": {
    "apikey": "123456"
  },
  "settings": {
    "reject_call": false,
    "msg_call": "",
    "groups_ignore": true,
    "always_online": false,
    "read_messages": false,
    "read_status": false,
    "sync_full_history": false
  }
}
```

**Erro (403) — nome duplicado:**
```json
{
  "status": 403,
  "error": "Forbidden",
  "response": {
    "message": ["This name \"minha-instancia\" is already in use."]
  }
}
```

### 5.2 Conectar Instância (QR Code / Pairing Code)

```
GET /instance/connect/{instance}
```

**Query params opcionais:**
- `number` — número com código de país (para pairing code)

**Resposta (200):**
```json
{
  "pairingCode": "WZYEH1YY",
  "code": "2@y8eK+bjtEjUWy9/FOM...",
  "count": 1
}
```

### 5.3 Estado da Conexão

```
GET /instance/connectionState/{instance}
```

### 5.4 Listar Instâncias

```
GET /instance/fetchInstances
```

### 5.5 Reiniciar Instância

```
PUT /instance/restart/{instance}
```

### 5.6 Desconectar (Logout)

```
DELETE /instance/logout/{instance}
```

### 5.7 Deletar Instância

```
DELETE /instance/delete/{instance}
```

### 5.8 Definir Presença

```
POST /instance/setPresence/{instance}
```

---

## 6. Envio de Mensagens

> **Header obrigatório em todos os endpoints:**
> ```
> apikey: sua-chave
> Content-Type: application/json
> ```

### 6.1 Texto Simples

```
POST /message/sendText/{instance}
```

```json
{
  "number": "5511999999999",
  "text": "Olá, tudo bem?",
  "delay": 1000,
  "linkPreview": true
}
```

**Resposta (201):**
```json
{
  "key": {
    "remoteJid": "5511999999999@s.whatsapp.net",
    "fromMe": true,
    "id": "BAE594145F4C59B4"
  },
  "message": {
    "extendedTextMessage": {
      "text": "Olá, tudo bem?"
    }
  },
  "messageTimestamp": "1717689097",
  "status": "PENDING"
}
```

### 6.2 Mídia (Imagem, Vídeo, Documento)

```
POST /message/sendMedia/{instance}
```

```json
{
  "number": "5511999999999",
  "mediatype": "image",
  "mimetype": "image/png",
  "caption": "Veja esta imagem",
  "media": "https://exemplo.com/imagem.png",
  "fileName": "imagem.png",
  "delay": 1000
}
```

**Valores de `mediatype`:** `image`, `video`, `document`

**`media` aceita:** URL pública ou string base64

### 6.3 Áudio (PTT — Push-to-Talk)

```
POST /message/sendWhatsAppAudio/{instance}
```

```json
{
  "number": "5511999999999",
  "audio": "https://exemplo.com/audio.mp4",
  "delay": 1000
}
```

**`audio` aceita:** URL pública ou string base64

### 6.4 Contato

```
POST /message/sendContact/{instance}
```

### 6.5 Localização

```
POST /message/sendLocation/{instance}
```

### 6.6 Reação (Emoji)

```
POST /message/sendReaction/{instance}
```

### 6.7 Enquete (Poll)

```
POST /message/sendPoll/{instance}
```

### 6.8 Lista

```
POST /message/sendList/{instance}
```

### 6.9 Sticker

```
POST /message/sendSticker/{instance}
```

### 6.10 Status/Story

```
POST /message/sendStatus/{instance}
```

### 6.11 Botões (Somente Cloud API)

```
POST /message/sendButton/{instance}
```

> **Nota:** Botões funcionam apenas com `integration: "WHATSAPP-BUSINESS"` (Cloud API). No Baileys, botões foram descontinuados pelo WhatsApp.

### 6.12 Opções Comuns a Todas as Mensagens

```json
{
  "delay": 1500,
  "linkPreview": true,
  "mentionsEveryOne": false,
  "mentioned": ["5511888888888", "5511777777777"],
  "quoted": {
    "key": {
      "id": "BAE5EFED2AB0BB9F"
    },
    "message": {
      "conversation": "Mensagem original sendo respondida"
    }
  }
}
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `delay` | integer | Milissegundos de "digitando..." antes do envio |
| `linkPreview` | boolean | Preview de links na mensagem |
| `mentionsEveryOne` | boolean | Mencionar todos no grupo |
| `mentioned` | string[] | Números específicos para mencionar |
| `quoted` | object | Responder mensagem específica (com `key.id`) |

---

## 7. Chat Controller

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/chat/findChats/{instance}` | Buscar conversas |
| POST | `/chat/findMessages/{instance}` | Buscar mensagens |
| POST | `/chat/findContacts/{instance}` | Buscar contatos |
| POST | `/chat/checkIsWhatsApp/{instance}` | Verificar se número tem WhatsApp |
| GET | `/chat/fetchProfilePictureUrl/{instance}` | Foto de perfil |
| GET | `/chat/findStatusMessage/{instance}` | Status/Story |
| GET | `/chat/getBase64/{instance}` | Mídia em base64 |
| PUT | `/chat/markMessageAsRead/{instance}` | Marcar como lida |
| PUT | `/chat/markMessageAsUnread/{instance}` | Marcar como não lida |
| PUT | `/chat/archiveChat/{instance}` | Arquivar/desarquivar chat |
| PUT | `/chat/updateMessage/{instance}` | Editar mensagem enviada |
| PUT | `/chat/updateBlockStatus/{instance}` | Bloquear/desbloquear contato |
| PUT | `/chat/sendPresence/{instance}` | Enviar presença (digitando, gravando) |
| DELETE | `/chat/deleteMessageForEveryone/{instance}` | Apagar para todos |

---

## 8. Grupos

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/group/create/{instance}` | Criar grupo |
| GET | `/group/fetchAllGroups/{instance}` | Listar todos os grupos |
| GET | `/group/findGroupInfos/{instance}?groupJid=` | Informações do grupo |
| GET | `/group/participants/{instance}?groupJid=` | Listar membros |
| GET | `/group/inviteCode/{instance}?groupJid=` | Código de convite |
| GET | `/group/findByInviteCode/{instance}?inviteCode=` | Buscar por código de convite |
| PUT | `/group/updateGroupSubject/{instance}` | Alterar nome do grupo |
| PUT | `/group/updateGroupDescription/{instance}` | Alterar descrição |
| PUT | `/group/updateGroupPicture/{instance}` | Alterar foto |
| PUT | `/group/updateParticipant/{instance}` | Add/remove/promote/demote membros |
| PUT | `/group/updateSetting/{instance}` | Configurações do grupo |
| PUT | `/group/toggleEphemeral/{instance}` | Mensagens temporárias |
| POST | `/group/sendInviteUrl/{instance}` | Enviar link de convite |
| PUT | `/group/revokeInviteCode/{instance}` | Revogar código de convite |
| DELETE | `/group/leaveGroup/{instance}` | Sair do grupo |

---

## 9. Webhooks

### 9.1 Configurar Webhook por Instância

```
POST /webhook/set/{instance}
```

```json
{
  "enabled": true,
  "url": "https://meu-server.com/webhook",
  "webhookByEvents": false,
  "webhookBase64": false,
  "events": [
    "MESSAGES_UPSERT",
    "MESSAGES_UPDATE",
    "MESSAGES_DELETE",
    "SEND_MESSAGE",
    "CONNECTION_UPDATE",
    "QRCODE_UPDATED",
    "PRESENCE_UPDATE",
    "CALL"
  ]
}
```

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `enabled` | boolean | Ativa/desativa o webhook |
| `url` | string | URL que receberá os eventos |
| `webhookByEvents` | boolean | Cria URL por evento (sufixo automático) |
| `webhookBase64` | boolean | Envia arquivos em base64 no payload |
| `events` | string[] | Lista de eventos a receber |

**Resposta (201):**
```json
{
  "webhook": {
    "instanceName": "minha-instancia",
    "webhook": {
      "url": "https://meu-server.com/webhook",
      "events": ["MESSAGES_UPSERT"],
      "enabled": true
    }
  }
}
```

### 9.2 Consultar Webhook

```
GET /webhook/find/{instance}
```

**Resposta:**
```json
{
  "enabled": true,
  "url": "https://meu-server.com/webhook",
  "webhookByEvents": false,
  "events": ["MESSAGES_UPSERT", "CONNECTION_UPDATE"]
}
```

### 9.3 Todos os Eventos Disponíveis (19)

| # | Evento | Sufixo URL | Descrição |
|---|--------|------------|-----------|
| 1 | `APPLICATION_STARTUP` | `/application-startup` | API iniciou |
| 2 | `QRCODE_UPDATED` | `/qrcode-updated` | QR Code gerado/atualizado (base64) |
| 3 | `CONNECTION_UPDATE` | `/connection-update` | Conexão mudou (online/offline/connecting) |
| 4 | `MESSAGES_SET` | `/messages-set` | Lista inicial de mensagens (dispara 1x) |
| 5 | `MESSAGES_UPSERT` | `/messages-upsert` | **Mensagem recebida** |
| 6 | `MESSAGES_UPDATE` | `/messages-update` | Mensagem atualizada (lida, entregue, editada) |
| 7 | `MESSAGES_DELETE` | `/messages-delete` | Mensagem excluída |
| 8 | `SEND_MESSAGE` | `/send-message` | **Mensagem enviada pela API** |
| 9 | `CONTACTS_SET` | `/contacts-set` | Carregamento inicial de contatos (1x) |
| 10 | `CONTACTS_UPSERT` | `/contacts-upsert` | Contatos recarregados (1x) |
| 11 | `CONTACTS_UPDATE` | `/contacts-update` | Contato atualizado |
| 12 | `PRESENCE_UPDATE` | `/presence-update` | Digitando/online/gravando áudio |
| 13 | `CHATS_SET` | `/chats-set` | Lista de chats carregados |
| 14 | `CHATS_UPDATE` | `/chats-update` | Chat atualizado |
| 15 | `CHATS_UPSERT` | `/chats-upsert` | Nova informação de chat |
| 16 | `CHATS_DELETE` | `/chats-delete` | Chat excluído |
| 17 | `GROUPS_UPSERT` | `/groups-upsert` | Grupo criado |
| 18 | `GROUP_UPDATE` | `/groups-update` | Informações do grupo atualizadas |
| 19 | `GROUP_PARTICIPANTS_UPDATE` | `/group-participants-update` | Participante adicionado/removido/promovido/rebaixado |
| 20 | `CONNECTION_UPDATE` | `/connection-update` | Estado da conexão |
| 21 | `CALL` | `/call` | Chamada recebida |
| 22 | `NEW_JWT_TOKEN` | `/new-jwt` | Token JWT atualizado |
| 23 | `TYPEBOT_START` | `/typebot-start` | Typebot iniciou sessão |
| 24 | `TYPEBOT_CHANGE_STATUS` | `/typebot-change-status` | Typebot mudou status |

### 9.4 Webhook por Eventos

Quando `webhookByEvents: true`, a URL recebe sufixo automático:

```
Base URL: https://meu-server.com/webhook

Mensagem recebida → https://meu-server.com/webhook/messages-upsert
Conexão mudou    → https://meu-server.com/webhook/connection-update
QR Code          → https://meu-server.com/webhook/qrcode-updated
```

### 9.5 Configuração Global via .env

```bash
WEBHOOK_GLOBAL_ENABLED=true
WEBHOOK_GLOBAL_URL=https://meu-server.com/webhook
WEBHOOK_GLOBAL_WEBHOOK_BY_EVENTS=false

# Ativar eventos específicos
WEBHOOK_EVENTS_QRCODE_UPDATED=true
WEBHOOK_EVENTS_MESSAGES_UPSERT=true
WEBHOOK_EVENTS_MESSAGES_UPDATE=true
WEBHOOK_EVENTS_SEND_MESSAGE=true
WEBHOOK_EVENTS_CONNECTION_UPDATE=true
WEBHOOK_EVENTS_APPLICATION_STARTUP=false
WEBHOOK_EVENTS_ERRORS=false
```

---

## 10. Perfil

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/profile/fetchProfile/{instance}` | Buscar perfil |
| GET | `/profile/fetchBusinessProfile/{instance}` | Perfil business |
| GET | `/profile/fetchPrivacySettings/{instance}` | Configurações de privacidade |
| PUT | `/profile/updateProfileName/{instance}` | Alterar nome |
| PUT | `/profile/updateProfilePicture/{instance}` | Alterar foto |
| PUT | `/profile/updateProfileStatus/{instance}` | Alterar status/bio |
| PUT | `/profile/updatePrivacySettings/{instance}` | Configurações de privacidade |
| DELETE | `/profile/removeProfilePicture/{instance}` | Remover foto |

---

## 11. Configurações da Instância

### Consultar

```
GET /settings/find/{instance}
```

### Definir

```
POST /settings/set/{instance}
```

---

## 12. Recursos Disponíveis

### Mensagens (Individual e Grupo)

| Recurso | Status |
|---------|--------|
| Texto (negrito, itálico, riscado, código, emoji) | Disponível |
| Imagem, Vídeo, Documento | Disponível |
| Áudio narrado (PTT) | Disponível (Android + iOS) |
| Localização com nome e descrição | Disponível |
| Contatos (nome, empresa, telefone, email, URL) | Disponível |
| Reações (qualquer emoji) | Disponível |
| Preview de links (SEO) | Disponível |
| Respostas com marcação | Disponível |
| Menções (individual, parcial, massa) | Disponível |
| Enquetes com votação | Disponível |
| Status/Story (texto, link, vídeo, imagem) | Disponível |
| Stickers estáticos | Disponível |
| Lista | Em homologação |
| Botões | Somente Cloud API |

### Perfil
- Atualizar nome, foto e status do perfil conectado

### Grupos
- Criar, atualizar foto/nome/descrição, listar com participantes

---

## 13. Integrações Detalhadas

### 13.1 Chatwoot

Configurável na criação da instância ou via endpoint:

```
POST /chatwoot/set/{instance}
GET /chatwoot/find/{instance}
```

Parâmetros: `chatwootAccountId`, `chatwootToken`, `chatwootUrl`, `chatwootSignMsg`, `chatwootReopenConversation`, `chatwootConversationPending`, `chatwootImportContacts`, `chatwootImportMessages`, `chatwootDaysLimitImportMessages`, `chatwootNameInbox`

### 13.2 Typebot

```
POST /typebot/set/{instance}        # Criar
PUT /typebot/update/{instance}       # Atualizar
GET /typebot/find/{instance}         # Buscar
GET /typebot/fetch/{instance}        # Listar
DELETE /typebot/delete/{instance}    # Deletar
POST /typebot/start/{instance}       # Iniciar sessão
POST /typebot/changeStatus/{instance} # Mudar status sessão
GET /typebot/fetchSession/{instance}  # Buscar sessão
GET /typebot/findSettings/{instance}  # Configurações
POST /typebot/settings/{instance}     # Definir configurações
```

### 13.3 OpenAI

```
POST /openai/create/{instance}       # Criar bot
PUT /openai/update/{instance}        # Atualizar
GET /openai/find/{instance}          # Buscar bot
GET /openai/findBots/{instance}      # Listar bots
DELETE /openai/delete/{instance}     # Deletar
POST /openai/setCreds/{instance}     # Configurar credenciais
GET /openai/findCreds/{instance}     # Buscar credenciais
DELETE /openai/deleteCreds/{instance} # Deletar credenciais
POST /openai/settings/{instance}     # Configurações
GET /openai/findSettings/{instance}  # Buscar configurações
POST /openai/changeStatus/{instance} # Mudar status
GET /openai/findSession/{instance}   # Buscar sessão
```

### 13.4 Dify

```
POST /dify/create/{instance}
PUT /dify/update/{instance}
GET /dify/find/{instance}
GET /dify/findBot/{instance}
POST /dify/settings/{instance}
GET /dify/findSettings/{instance}
POST /dify/changeStatus/{instance}
GET /dify/findStatus/{instance}
```

### 13.5 n8n

```
POST /n8n/create/{instance}
PUT /n8n/update/{instance}
GET /n8n/find/{instance}
POST /n8n/settings/{instance}
GET /n8n/findSettings/{instance}
POST /n8n/changeStatus/{instance}
GET /n8n/findStatus/{instance}
```

### 13.6 Flowise

```
POST /flowise/create/{instance}
PUT /flowise/update/{instance}
GET /flowise/find/{instance}
GET /flowise/findBots/{instance}
DELETE /flowise/delete/{instance}
POST /flowise/settings/{instance}
GET /flowise/findSettings/{instance}
POST /flowise/changeStatus/{instance}
GET /flowise/findSessions/{instance}
```

### 13.7 EvoAI

```
POST /evoai/create/{instance}
PUT /evoai/update/{instance}
GET /evoai/find/{instance}
POST /evoai/settings/{instance}
GET /evoai/findSettings/{instance}
POST /evoai/changeStatus/{instance}
GET /evoai/findStatus/{instance}
```

### 13.8 Evolution Bot

```
POST /evolutionBot/create/{instance}
PUT /evolutionBot/update/{instance}
GET /evolutionBot/find/{instance}
GET /evolutionBot/fetch/{instance}
DELETE /evolutionBot/delete/{instance}
POST /evolutionBot/settings/{instance}
GET /evolutionBot/findSettings/{instance}
POST /evolutionBot/changeStatus/{instance}
GET /evolutionBot/fetchSession/{instance}
```

### 13.9 RabbitMQ

```
POST /rabbitmq/set/{instance}
GET /rabbitmq/find/{instance}
```

### 13.10 Amazon SQS

```
POST /sqs/set/{instance}
GET /sqs/find/{instance}
```

### 13.11 WebSocket

```
POST /websocket/set/{instance}
GET /websocket/find/{instance}
```

---

## 14. Formato de Números

| Formato | Exemplo | Uso |
|---------|---------|-----|
| Individual | `5511999999999` | Código país + DDD + número |
| JID WhatsApp | `5511999999999@s.whatsapp.net` | Retornado nas respostas |
| Grupo JID | `120363025486748123@g.us` | Para mensagens em grupo |

> **Importante:** Sempre enviar o número **sem** `+`, `-`, `()` ou espaços. Incluir código do país (55 para Brasil).

---

## 15. Exemplos Práticos com PHP (cURL)

### 15.1 Criar Instância

```php
$baseUrl = 'https://evo2.meudominio.com';
$apiKey = 'minha-chave-global';

$ch = curl_init("{$baseUrl}/instance/create");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "apikey: {$apiKey}",
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'instanceName' => 'mercury-whatsapp',
        'integration' => 'WHATSAPP-BAILEYS',
        'qrcode' => true,
        'rejectCall' => true,
        'msgCall' => 'Não posso atender agora. Envie uma mensagem.',
        'groupsIgnore' => false,
        'alwaysOnline' => true,
        'readMessages' => false,
        'webhook' => [
            'url' => 'https://mercury.meudominio.com/api/v1/webhook/whatsapp',
            'byEvents' => false,
            'base64' => true,
            'events' => [
                'MESSAGES_UPSERT',
                'MESSAGES_UPDATE',
                'SEND_MESSAGE',
                'CONNECTION_UPDATE',
            ],
        ],
    ]),
    CURLOPT_RETURNTRANSFER => true,
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);
```

### 15.2 Enviar Mensagem de Texto

```php
$instanceName = 'mercury-whatsapp';

$ch = curl_init("{$baseUrl}/message/sendText/{$instanceName}");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "apikey: {$apiKey}",
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'number' => '5511999999999',
        'text' => 'Olá! Esta é uma mensagem automática do Mercury.',
        'delay' => 1200,
        'linkPreview' => true,
    ]),
    CURLOPT_RETURNTRANSFER => true,
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);
```

### 15.3 Enviar Imagem

```php
$ch = curl_init("{$baseUrl}/message/sendMedia/{$instanceName}");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "apikey: {$apiKey}",
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'number' => '5511999999999',
        'mediatype' => 'image',
        'mimetype' => 'image/jpeg',
        'caption' => 'Relatório de vendas - Março 2026',
        'media' => 'https://mercury.meudominio.com/uploads/relatorio.jpg',
        'fileName' => 'relatorio.jpg',
    ]),
    CURLOPT_RETURNTRANSFER => true,
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);
```

### 15.4 Enviar Documento (PDF)

```php
$ch = curl_init("{$baseUrl}/message/sendMedia/{$instanceName}");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "apikey: {$apiKey}",
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'number' => '5511999999999',
        'mediatype' => 'document',
        'mimetype' => 'application/pdf',
        'caption' => 'Segue o relatório em anexo',
        'media' => 'https://mercury.meudominio.com/uploads/relatorio.pdf',
        'fileName' => 'Relatorio_Marco_2026.pdf',
    ]),
    CURLOPT_RETURNTRANSFER => true,
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);
```

### 15.5 Enviar Áudio (PTT)

```php
$ch = curl_init("{$baseUrl}/message/sendWhatsAppAudio/{$instanceName}");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "apikey: {$apiKey}",
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'number' => '5511999999999',
        'audio' => 'https://mercury.meudominio.com/uploads/audio.mp4',
        'delay' => 1000,
    ]),
    CURLOPT_RETURNTRANSFER => true,
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);
```

### 15.6 Verificar se Número tem WhatsApp

```php
$ch = curl_init("{$baseUrl}/chat/checkIsWhatsApp/{$instanceName}");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "apikey: {$apiKey}",
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'numbers' => ['5511999999999', '5511888888888'],
    ]),
    CURLOPT_RETURNTRANSFER => true,
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);
```

### 15.7 Receber Webhook (Endpoint PHP)

```php
// Endpoint: POST /api/v1/webhook/whatsapp
$payload = json_decode(file_get_contents('php://input'), true);

$event = $payload['event'] ?? '';
$instance = $payload['instance'] ?? '';
$data = $payload['data'] ?? [];

switch ($event) {
    case 'messages.upsert':
        $message = $data['message'] ?? [];
        $from = $data['key']['remoteJid'] ?? '';
        $text = $message['conversation']
            ?? $message['extendedTextMessage']['text']
            ?? '';

        // Processar mensagem recebida
        processIncomingMessage($from, $text, $instance);
        break;

    case 'connection.update':
        $state = $data['state'] ?? '';
        // Monitorar estado da conexão
        logConnectionState($instance, $state);
        break;

    case 'messages.update':
        // Mensagem lida, entregue, etc.
        $status = $data['status'] ?? '';
        updateMessageStatus($data['key']['id'], $status);
        break;
}

http_response_code(200);
echo json_encode(['status' => 'received']);
```

---

## 16. OpenAPI / Swagger

Especificações OpenAPI disponíveis para importação:

- **v2:** https://doc.evolution-api.com/openapi/openapi-v2.json
- **v1:** https://doc.evolution-api.com/openapi/openapi-v1.json

---

## 17. Requisitos de Infraestrutura

| Componente | Obrigatório | Recomendado |
|------------|-------------|-------------|
| Docker | Sim | v24+ |
| PostgreSQL | Sim (produção) | v14+ |
| Redis | Não | Sim (cache + performance) |
| Nginx | Não | Sim (reverse proxy + SSL) |
| S3/MinIO | Não | Sim (armazenamento de mídia) |

---

## 18. Links Úteis

- **Documentação oficial:** https://doc.evolution-api.com/v2/pt/get-started/introduction
- **Índice completo (LLMs):** https://doc.evolution-api.com/llms.txt
- **GitHub:** https://github.com/EvolutionAPI/evolution-api
- **Postman Collection:** https://www.postman.com/agenciadgcode/evolution-api/collection/gqr041s/evolution-api-v2-0
- **Comunidade:** https://evolution-api.com
- **Docker:** https://doc.evolution-api.com/v2/pt/install/docker
- **NVM:** https://doc.evolution-api.com/v2/pt/install/nvm
- **Webhooks:** https://doc.evolution-api.com/v2/pt/configuration/webhooks
- **Recursos:** https://doc.evolution-api.com/v2/pt/configuration/available-resources
- **Variáveis .env:** https://doc.evolution-api.com/v2/pt/env

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
**Criado em:** 27/03/2026
