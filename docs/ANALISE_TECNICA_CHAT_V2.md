# Analise Tecnica - Chat Module v2.0

**Versao:** 2.0
**Data:** 20 de Fevereiro de 2026
**Autor:** Equipe Mercury - Grupo Meia Sola
**Status:** Implementado (todas as fases concluidas)

---

## Sumario

1. [Resumo Executivo](#1-resumo-executivo)
2. [Arquitetura v1.0 (Anterior)](#2-arquitetura-v10-anterior)
3. [Features Implementadas (v2.0)](#3-features-implementadas-v20)
4. [Arquitetura WebSocket](#4-arquitetura-websocket)
5. [Grupos de Conversa](#5-grupos-de-conversa)
6. [Notificacoes em Massa (Broadcast)](#6-notificacoes-em-massa-broadcast)
7. [Responder Mensagem (Reply-To)](#7-responder-mensagem-reply-to)
8. [Busca em Mensagens](#8-busca-em-mensagens)
9. [Indicador de Digitacao (Typing)](#9-indicador-de-digitacao-typing)
10. [Paginacao de Mensagens](#10-paginacao-de-mensagens)
11. [UX Extras: Separadores de Data e Scroll para Nao-Lidas](#11-ux-extras-separadores-de-data-e-scroll-para-nao-lidas)
12. [Schema do Banco de Dados](#12-schema-do-banco-de-dados)
13. [Correcoes de Seguranca](#13-correcoes-de-seguranca)
14. [Historico de Implementacao](#14-historico-de-implementacao)
15. [Metricas Finais](#15-metricas-finais)
16. [Riscos e Mitigacoes](#16-riscos-e-mitigacoes)
17. [Compatibilidade e Migracao](#17-compatibilidade-e-migracao)

---

## 1. Resumo Executivo

### Estado Anterior (v1.0)

O modulo de Chat do Mercury era um sistema de mensagens internas estilo WhatsApp com as seguintes caracteristicas:

| Metrica | v1.0 |
|---|---|
| Controllers | 8 |
| Models | 3 |
| Services | 1 (ChatService) |
| Views | 4 |
| JavaScript | 1.880 linhas (2 arquivos) |
| Tabelas DB | 2 |
| Endpoints | 13 |
| Comunicacao | Polling adaptativo (2s/10s/30s) |
| Tipo de Conversa | Somente 1-para-1 |

### Estado Atual (v2.0) - Implementado

| Metrica | v1.0 | v2.0 | Diferenca |
|---|---|---|---|
| Controllers | 8 | 19 | +11 |
| Models | 3 | 5 | +2 |
| Services | 1 | 6 | +5 |
| Views | 4 | 9 (2 main + 7 partials) | +5 |
| JavaScript | 1.880 LOC | 6.014 LOC (2 arquivos) | +4.134 LOC |
| Tabelas DB | 2 | 9 | +7 |
| Endpoints | 13 | 41 | +28 |
| Comunicacao | Polling | WebSocket + fallback polling | Tempo real |
| Tipos de Conversa | 1-para-1 | 1-para-1 + Grupos + Broadcasts | +2 tipos |

### Features Implementadas

- **WebSocket** (Ratchet) - comunicacao em tempo real com fallback polling
- **Grupos de conversa** - multiplos participantes com roles admin/member
- **Notificacoes em massa (Broadcast)** - comunicados para todos/loja/nivel
- **Responder mensagem (Reply-to)** - quote com preview
- **Busca em mensagens** - full-text search global e na conversa
- **Indicador de digitacao** - typing indicators via WebSocket
- **Paginacao de mensagens** - scroll infinito com cursor-based pagination
- **Separadores de data** - "Hoje", "Ontem", data formatada entre mensagens
- **Scroll para nao-lidas** - divisor visual + auto-scroll para primeira mensagem nao lida
- **Notificacoes do navegador** - Browser Notification API
- **Envio de arquivos** - imagens, documentos, videos
- **Edicao/exclusao de mensagens** - com UI otimista
- **Correcoes de seguranca** - SQL injection, prepared statements, JWT auth

---

## 2. Arquitetura v1.0 (Anterior)

### Fluxo de Comunicacao (v1.0)

```
[Browser] --HTTP Poll (2s)--> [Apache/PHP] --> [MySQL]
[Browser] <--JSON Response--- [Apache/PHP] <-- [MySQL]
```

### Problemas Identificados na v1.0

| # | Problema | Severidade | Status v2.0 |
|---|---|---|---|
| 1 | SQL Injection em `addslashes()` no search | **Alta** | **CORRIGIDO** |
| 2 | Polling consome recursos do servidor | Media | **CORRIGIDO** (WebSocket) |
| 3 | Sem paginacao de mensagens (max 100) | Media | **CORRIGIDO** (cursor pagination) |
| 4 | Sem grupos de conversa | Alta | **CORRIGIDO** |
| 5 | Sem notificacoes push | Media | **CORRIGIDO** (Browser Notification API) |
| 6 | Sem busca em mensagens | Baixa | **CORRIGIDO** (FULLTEXT search) |
| 7 | Race condition na criacao de conversas | Baixa | **CORRIGIDO** (UNIQUE constraints) |

---

## 3. Features Implementadas (v2.0)

### Matriz de Features

| Feature | Prioridade Original | Complexidade | Status |
|---|---|---|---|
| Correcoes de seguranca | P0 - Critica | Baixa | **Implementado** |
| WebSocket | P1 - Alta | Alta | **Implementado** |
| Grupos de conversa | P1 - Alta | Alta | **Implementado** |
| Paginacao de mensagens | P1 - Alta | Baixa | **Implementado** |
| Responder mensagem | P2 - Media | Media | **Implementado** |
| Notificacoes em massa | P2 - Media | Media | **Implementado** |
| Busca em mensagens | P3 - Baixa | Media | **Implementado** |
| Indicador de digitacao | P3 - Baixa | Baixa | **Implementado** |
| Separadores de data | Extra | Baixa | **Implementado** |
| Scroll para nao-lidas | Extra | Media | **Implementado** |
| Notificacoes do navegador | Extra | Baixa | **Implementado** |
| Envio de arquivos | Extra | Media | **Implementado** |

---

## 4. Arquitetura WebSocket

### Tecnologia Escolhida: Ratchet + ReactPHP HTTP

**Decisao:** Ratchet 0.4 como WebSocket server + ReactPHP HTTP para IPC interno (em vez de Redis PubSub, simplificando a infraestrutura).

**Justificativa:**
- Stack PHP mantida, integracao direta com Models/Services
- IPC via HTTP interno (porta 8081) em vez de Redis, sem dependencia externa
- Polling existente como fallback automatico
- Suficiente para o volume de usuarios do Mercury

### Arquitetura Implementada

```
┌──────────────────────────────────────────────────────────┐
│                      FRONTEND                             │
│  ┌──────────────────────────────────────────────────────┐│
│  │              chat.js (WebSocket Client)              ││
│  │  ┌─────────┐  ┌──────────┐  ┌───────────────────┐  ││
│  │  │ WS Conn │  │ Fallback │  │ Message Handlers  │  ││
│  │  │ Manager │  │ Polling  │  │ (send/recv/typing)│  ││
│  │  └────┬────┘  └────┬─────┘  └───────────────────┘  ││
│  │       │            │                                 ││
│  │       └──────┬─────┘                                 ││
│  │              │ Auto-switch (WS primary, Poll backup) ││
│  └──────────────┼───────────────────────────────────────┘│
└─────────────────┼────────────────────────────────────────┘
                  │
    ┌─────────────┼──────────────────────────┐
    │  WS (wss://)│         HTTP (https://)  │
    ▼             │              ▼            │
┌─────────┐      │     ┌────────────────┐    │
│Ratchet  │      │     │  Apache/PHP    │    │
│WebSocket│◄─────┼────►│  Controllers   │    │
│ Server  │ IPC  │     │  (REST API)    │    │
│ :8080   │(HTTP │     │  :443          │    │
│(public) │ 8081)│     └───────┬────────┘    │
└────┬────┘      │             │             │
     │           │     ┌───────▼────────┐    │
     │           │     │  ChatService   │    │
     │           │     │  GroupChatSvc  │    │
     │           │     │  BroadcastSvc  │    │
     │           │     │  WsNotifier   │    │
     │           │     └───────┬────────┘    │
     │           │             │             │
     └───────────┼─────────────┼─────────────┘
                 │             │
          ┌──────▼─────────────▼──────┐
          │         MySQL             │
          └───────────────────────────┘
```

### Fluxo de Mensagem (WebSocket)

```
1. User A envia mensagem via HTTP POST
   Browser A --> Apache/PHP --> Salva no MySQL via AdmsChat
                            --> WebSocketNotifier envia HTTP para :8081 (fire-and-forget)
                            --> JSON response para Browser A (confirmacao)

2. WebSocket Server distribui
   HTTP :8081 --> WebSocketService --> Broadcast para User B (se conectado)

3. User B recebe mensagem
   WebSocket Server --> Browser B --> Renderiza mensagem (sem polling!)
```

### Componentes WebSocket

| Arquivo | Descricao |
|---|---|
| `bin/websocket-server.php` | Servidor principal (Ratchet :8080 + ReactPHP HTTP :8081) |
| `app/adms/Services/WebSocketService.php` | Handler de conexoes e eventos WebSocket |
| `app/adms/Services/WebSocketNotifier.php` | Fire-and-forget HTTP para IPC com WebSocket |
| `app/adms/Services/WebSocketTokenService.php` | Geracao/validacao de JWT tokens para auth WS |

### Eventos WebSocket Suportados

| Evento | Direcao | Descricao |
|---|---|---|
| `new_message` | Server → Client | Nova mensagem direta |
| `message_edited` | Server → Client | Mensagem editada |
| `message_deleted` | Server → Client | Mensagem deletada |
| `group_message` | Server → Client | Nova mensagem em grupo |
| `group_message_edited` | Server → Client | Mensagem de grupo editada |
| `group_message_deleted` | Server → Client | Mensagem de grupo deletada |
| `broadcast` | Server → Client | Novo comunicado |
| `broadcast_edited` | Server → Client | Comunicado editado |
| `broadcast_deleted` | Server → Client | Comunicado deletado |
| `typing` | Client → Server → Client | Indicador de digitacao |
| `read_receipt` | Server → Client | Confirmacao de leitura |

### Autenticacao WebSocket

- JWT gerado via `WebSocketTokenService` no login/carregamento da pagina
- Token passado como query parameter na conexao WS: `wss://host:8080?token=xxx`
- Token validado no `onOpen` do WebSocketService
- Expiracao configuravel (default: 24h)

### Fallback Automatico

O JavaScript detecta automaticamente se WebSocket esta disponivel:

```javascript
// Em chat.js - connectWebSocket()
try {
    ws = new WebSocket(`wss://${location.hostname}:8080?token=${wsToken}`);
    ws.onopen = () => { /* Desabilita polling */ };
    ws.onclose = () => { /* Reativa polling com reconnect */ };
    ws.onerror = () => { /* Fallback para polling adaptativo */ };
} catch (e) {
    startPolling(); // Polling como fallback
}
```

---

## 5. Grupos de Conversa

### Modelo de Dados

```
┌─────────────────────────┐     ┌──────────────────────────┐
│ adms_chat_groups         │     │ adms_chat_group_members   │
├─────────────────────────┤     ├──────────────────────────┤
│ id (UUID PK)            │◄───┐│ id (INT AUTO PK)         │
│ name (VARCHAR 100)      │    ││ group_id (UUID FK)       │──►groups.id
│ description (TEXT)      │    ││ user_id (INT FK)         │──►adms_usuarios.id
│ avatar_path (VARCHAR)   │    ││ role (ENUM)              │   admin|member
│ created_by_user_id      │    ││ joined_at (TIMESTAMP)    │
│ max_members (INT)       │    ││ left_at (TIMESTAMP NULL) │
│ send_permission (ENUM)  │    ││ is_muted (TINYINT)       │
│ is_active (TINYINT)     │    ││ can_send (TINYINT)       │
│ created_at              │    │└──────────────────────────┘
│ updated_at              │    │
└─────────────────────────┘    │  ┌──────────────────────────┐
                               │  │ adms_chat_group_messages   │
┌──────────────────────────┘  ├──────────────────────────┤
│ adms_chat_group_unread       │  │ id (UUID PK)              │
├──────────────────────────┐  │ group_id (UUID FK)        │
│ id (INT AUTO PK)         │  │ sender_user_id (INT FK)   │
│ group_id (UUID FK)       │  │ message_text (LONGTEXT)   │
│ user_id (INT FK)         │  │ message_type (ENUM)       │
│ unread_count (INT)       │  │ reply_to_message_id (UUID)│
│ last_read_at (TIMESTAMP) │  │ file_path, file_name      │
└──────────────────────────┘  │ edited_at, deleted_at     │
                               │ created_at                 │
                               └──────────────────────────┘
```

### Regras de Negocio Implementadas

1. **Criacao de grupo**: Qualquer usuario com permissao de chat
2. **Limite de membros**: Configuravel por grupo (padrao: 50)
3. **Roles**: `admin` (criador + promovidos) e `member`
4. **Admin pode**: adicionar/remover membros, editar grupo, mutar membros, controlar permissao de envio, deletar grupo
5. **Member pode**: enviar mensagens (se permitido), sair do grupo
6. **Send permissions**: `all` (todos enviam), `admins_only` (so admins), com controle individual `can_send` por membro
7. **Mensagens separadas**: Grupo usa tabela propria `adms_chat_group_messages` (nao altera `adms_chat_messages`)
8. **Sair do grupo**: Soft-leave (historico preservado via `left_at`)
9. **Notificacoes de sistema**: Mensagens automaticas ao adicionar/remover membros

### Controllers Implementados

| Controller | Metodos | Descricao |
|---|---|---|
| `ChatGroup` | list, view, loadMessages, loadNewMessages, loadOlderMessages | Listagem e mensagens |
| `AddChatGroup` | create | Criar grupo |
| `EditChatGroup` | edit | Editar nome/descricao do grupo |
| `DeleteChatGroup` | delete | Excluir grupo (admin only) |
| `AddChatGroupMessage` | create | Enviar mensagem no grupo |
| `ChatGroupMember` | addMember, removeMember, leave, promoteToAdmin, demoteToMember, toggleCanSend | Gerenciar membros |

### Interface do Usuario

- Sidebar separada por abas: Conversas | Grupos | Comunicados
- Icone de grupo (`fa-users`) com badge de membros
- Header mostra nome do grupo + botao de membros
- Painel lateral deslizante para gerenciar membros (admin only)
- Modal de criacao com busca e selecao de membros
- Nome do remetente exibido em cada mensagem do grupo
- Indicador de permissao de envio restrita

---

## 6. Notificacoes em Massa (Broadcast)

### Modelo de Dados

```
┌──────────────────────────┐     ┌───────────────────────────┐
│ adms_chat_broadcasts     │     │ adms_chat_broadcast_reads  │
├──────────────────────────┤     ├───────────────────────────┤
│ id (UUID PK)             │◄───┐│ id (INT AUTO PK)          │
│ sender_user_id (INT FK)  │    ││ broadcast_id (UUID FK)    │
│ title (VARCHAR 200)      │    ││ user_id (INT FK)          │
│ message_text (LONGTEXT)  │    ││ read_at (TIMESTAMP NULL)  │
│ message_type (ENUM)      │    ││ created_at (TIMESTAMP)    │
│   text|image|file|video  │    │└───────────────────────────┘
│ priority (ENUM)          │    │
│   normal|important|urgent│    │
│ target_type (ENUM)       │    │
│   all|level|store        │    │
│ target_ids (JSON)        │    │
│ file_path (VARCHAR NULL) │    │
│ file_name, file_size     │    │
│ is_active (TINYINT)      │    │
│ created_at, updated_at   │    │
└──────────────────────────┘    │
```

### Regras de Negocio Implementadas

1. **Quem pode enviar**: Usuarios com nivel de acesso configurado (via `adms_nivacs_pgs`)
2. **Tipos de alvo**: `all` (todos), `level` (por nivel de acesso), `store` (por loja)
3. **Prioridade visual**:
   - `normal` - Badge azul, notificacao padrao
   - `important` - Badge laranja, destaque na lista
   - `urgent` - Badge vermelho, modal de alerta automatico
4. **Rastreamento de leitura**: Registro de quem leu e quando
5. **Envio de arquivos**: Imagens, documentos e videos como anexo
6. **Edicao/exclusao**: Admins podem editar ou remover broadcasts
7. **Notificacao em tempo real**: Via WebSocket para todos os destinatarios

### Controllers Implementados

| Controller | Metodos | Descricao |
|---|---|---|
| `ChatBroadcast` | list, view, loadBroadcast, markRead | Listagem e visualizacao |
| `AddChatBroadcast` | create, sendFile | Criar broadcast (com arquivo opcional) |
| `EditChatBroadcast` | edit | Editar broadcast |
| `DeleteChatBroadcast` | delete | Excluir broadcast |
| `MarkBroadcastRead` | markRead | Marcar como lido |

### Interface

- Aba "Comunicados" na sidebar com badge de nao lidos
- Modal de criacao com selecao de alvo (todos/loja/nivel)
- Selecao de prioridade (normal/importante/urgente)
- Upload de arquivo opcional
- Modal de alerta automatico para broadcasts urgentes
- Contagem de leituras visivel para o remetente

---

## 7. Responder Mensagem (Reply-To)

### Implementacao

**Banco:** Coluna `reply_to_message_id` em `adms_chat_messages` e `adms_chat_group_messages`

### Fluxo Implementado

1. Usuario clica no botao "Responder" (visivel no hover da mensagem)
2. Barra de preview aparece acima do input com nome do remetente + texto truncado
3. Ao enviar, `reply_to_message_id` e incluido no payload
4. Mensagem renderiza com quote da mensagem original (clicavel para scroll)
5. Funciona em conversas diretas e grupos

### Interface

```
┌─────────────────────────────────────┐
│  Joao (10:30):                      │
│  ┌────────────────────────────────┐ │
│  │ ↩ Maria:                       │ │  ← Quote clicavel
│  │ Voce ja enviou o relatorio?   │ │
│  └────────────────────────────────┘ │
│  Sim, acabei de enviar por email    │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ ↩ Respondendo a Maria              │  ← Barra de preview
│ Voce ja enviou o relatorio?     [X]│
├─────────────────────────────────────┤
│ [     Digite sua mensagem...      ] │
└─────────────────────────────────────┘
```

---

## 8. Busca em Mensagens

### Implementacao

**Indice:** FULLTEXT em `adms_chat_messages.message_text` e `adms_chat_group_messages.message_text`

### Tipos de Busca Implementados

1. **Busca global**: Busca em todas as conversas e grupos do usuario
2. **Busca na conversa**: Filtra mensagens dentro da conversa/grupo aberto

### Endpoints

| Controller | Metodo | URL | Descricao |
|---|---|---|---|
| Chat | searchMessages | /chat/search-messages?q=... | Busca global |
| Chat | searchInConversation | /chat/search-in-conversation/{id}?q=... | Busca na conversa |
| Chat | searchInGroup | /chat/search-in-group/{id}?q=... | Busca no grupo |

### Interface

- Icone de busca na sidebar e no header da conversa
- Resultados mostram snippet com highlight do termo
- Click no resultado abre a conversa e navega ate a mensagem exata
- Navegacao usa `getMessagesAroundId()` para carregar contexto ao redor da mensagem

---

## 9. Indicador de Digitacao (Typing)

### Fluxo via WebSocket

```
User A digita --> WS: {type: 'typing', conversationId, isGroup, recipientId}
                  --> Server broadcast para User B
                  --> User B mostra "Digitando..."

User A para (3s) --> Timeout no frontend
                     --> User B remove indicador
```

### Implementacao

- Debounce de 3 segundos no input
- Evento `typing` enviado via WebSocket
- Sem fallback para polling (typing nao funciona sem WS)
- Indicador visual com animacao de 3 pontinhos no header da conversa
- Funciona em conversas diretas e grupos

---

## 10. Paginacao de Mensagens

### Implementacao

- **Cursor-based pagination**: Usa `created_at` da mensagem mais antiga como cursor
- **Limite**: 50 mensagens por carga (reduzido de 100)
- **Scroll infinito**: Detecta scroll no topo para carregar mensagens anteriores
- **Preservacao de scroll**: Mantem posicao do scroll ao prepend de mensagens antigas
- **Around-load**: `getMessagesAroundId()` carrega 25 antes + 25 depois de uma mensagem alvo

### Endpoints

| Tipo | Endpoint | Descricao |
|---|---|---|
| Direto | `/chat/load-older-messages/{id}?before={messageId}` | Mensagens anteriores |
| Grupo | `/chat-group/load-older-messages/{id}?before={messageId}` | Mensagens anteriores (grupo) |
| Around | `/chat/load-messages/{id}?focus={messageId}` | Mensagens ao redor de um ID |

---

## 11. UX Extras: Separadores de Data e Scroll para Nao-Lidas

### Separadores de Data

Divisores visuais entre mensagens de dias diferentes:
- **"Hoje"** - mensagens do dia atual
- **"Ontem"** - mensagens do dia anterior
- **Data formatada** - ex: "15 de fevereiro de 2026" para datas mais antigas

Inseridos automaticamente em:
- `renderMessages()` - carregamento inicial
- `appendNewMessage()` - novas mensagens recebidas
- `loadOlderMessages()` - mensagens antigas (scroll infinito)

### Scroll para Primeira Mensagem Nao Lida

Quando o usuario abre uma conversa com mensagens nao lidas:

1. Backend retorna `unread_count` e `first_unread_id` no JSON
2. Frontend insere divisor visual: "X mensagens nao lidas"
3. Auto-scroll posiciona o divisor no topo da area visivel
4. Divisor desaparece com fade-out apos 8 segundos
5. Se `unread_count > 50`: usa around-load para garantir que a primeira nao lida esteja no batch

### Implementacao Backend

- `AdmsViewChat::getFirstUnreadMessageId()` - primeira msg nao lida em conversa direta
- `GroupChatService::getFirstUnreadGroupMessageId()` - primeira msg nao lida em grupo
- `GroupChatService::getSingleGroupUnreadCount()` - contagem de nao lidas por grupo
- Dados calculados ANTES de `markAsRead()` para precisao

---

## 12. Schema do Banco de Dados

### Tabelas do Chat v2.0

| Tabela | Tipo | Descricao |
|---|---|---|
| `adms_chat_conversations` | Existente | Conversas 1-para-1 |
| `adms_chat_messages` | Existente (alterada) | Mensagens diretas + reply_to |
| `adms_chat_groups` | Nova | Grupos de conversa |
| `adms_chat_group_members` | Nova | Membros dos grupos |
| `adms_chat_group_messages` | Nova | Mensagens de grupo (tabela separada) |
| `adms_chat_group_unread` | Nova | Contagem de nao lidas por grupo/usuario |
| `adms_chat_broadcasts` | Nova | Comunicados em massa |
| `adms_chat_broadcast_reads` | Nova | Rastreamento de leitura |
| `adms_chat_ws_sessions` | Nova | Sessoes WebSocket |

### Diagrama ER Completo (v2.0)

```
adms_usuarios
    │
    ├──< adms_chat_conversations (user1_id, user2_id)
    │       │
    │       └──< adms_chat_messages (conversation_id)
    │               │
    │               └── reply_to_message_id ──> adms_chat_messages (self-ref)
    │
    ├──< adms_chat_groups (created_by_user_id)
    │       │
    │       ├──< adms_chat_group_members (group_id, user_id)
    │       │
    │       ├──< adms_chat_group_messages (group_id)
    │       │       │
    │       │       └── reply_to_message_id ──> adms_chat_group_messages (self-ref)
    │       │
    │       └──< adms_chat_group_unread (group_id, user_id)
    │
    ├──< adms_chat_broadcasts (sender_user_id)
    │       │
    │       └──< adms_chat_broadcast_reads (broadcast_id, user_id)
    │
    └──< adms_chat_ws_sessions (user_id)
```

### Nota sobre Decisao de Arquitetura

A proposta original previa alterar `adms_chat_messages` para suportar grupos (adicionando `group_id` e `message_scope`). Na implementacao, optou-se por uma **tabela separada** `adms_chat_group_messages`, que:
- Evita queries complexas com filtro de scope
- Permite schema otimizado para cada tipo de mensagem
- Simplifica indices e queries de contagem de nao-lidas
- Isola mensagens de grupo sem impactar performance de conversas diretas

---

## 13. Correcoes de Seguranca

### P0 - SQL Injection no Search - CORRIGIDO

**Arquivo:** `app/adms/Services/ChatService.php`
**Correcao:** Todas as queries agora usam prepared statements via `AdmsRead::fullRead()`

```php
// ANTES (vulneravel)
$searchTerm = addslashes($searchTerm);
$read->fullRead("SELECT ... WHERE nome LIKE '%{$searchTerm}%' ...");

// DEPOIS (corrigido)
$read->fullRead(
    "SELECT ... WHERE nome LIKE :search_term ...",
    "search_term=%{$searchTerm}%"
);
```

### P0 - Parametros nao preparados - CORRIGIDO

**Arquivo:** `app/adms/Services/ChatService.php`
**Correcao:** Todas as interpolacoes diretas substituidas por parametros nomeados

```php
// ANTES (interpolacao direta)
"WHERE user1_id = {$userId} OR user2_id = {$userId}"

// DEPOIS (prepared statements)
$read->fullRead(
    "SELECT ... WHERE user1_id = :uid1 OR user2_id = :uid2",
    "uid1={$userId}&uid2={$userId}"
);
```

### Autenticacao WebSocket - IMPLEMENTADA

- JWT tokens via `WebSocketTokenService`
- Tokens passados como query parameter (nao headers, por limitacao do WebSocket API)
- Validacao no `onOpen()` do servidor WebSocket
- Tokens expiram em 24h

---

## 14. Historico de Implementacao

### Fase 1 - Fundacao (Concluida)

**Commit:** `73960917` + `5cf5d662`

| Tarefa | Status |
|---|---|
| Corrigir SQL injection no search | **Concluido** |
| Corrigir interpolacao direta de parametros | **Concluido** |
| Implementar paginacao de mensagens (cursor) | **Concluido** |
| Setup Ratchet WebSocket + bin/server.php | **Concluido** |
| IPC via ReactPHP HTTP (substituiu Redis PubSub) | **Concluido** |
| Token JWT de autenticacao WebSocket | **Concluido** |
| Frontend: WebSocket com fallback polling | **Concluido** |

**Entrega:** WebSocket funcional + seguranca corrigida + paginacao

### Fase 2 - Grupos (Concluida)

**Commits:** `205a7625` + `e7f5b8e7`

| Tarefa | Status |
|---|---|
| Criar tabelas DB (groups, members, messages, unread) | **Concluido** |
| Model: AdmsChatGroup | **Concluido** |
| Service: GroupChatService | **Concluido** |
| Controllers: ChatGroup, AddChatGroup, EditChatGroup, DeleteChatGroup | **Concluido** |
| Controller: ChatGroupMember (add, remove, leave, promote, demote, mute) | **Concluido** |
| Controller: AddChatGroupMessage | **Concluido** |
| Views: modals de criacao, painel de membros, configuracoes | **Concluido** |
| JS: Gerenciamento de grupo no chat.js | **Concluido** |
| Envio de arquivos em grupo | **Concluido** |
| Edicao/exclusao de mensagens de grupo | **Concluido** |
| Registrar rotas em adms_paginas | **Concluido** |

**Entrega:** Grupos de conversa com gerenciamento completo de membros e permissoes

### Fase 3 - Broadcast + Reply-to (Concluida)

**Commits:** `6354bb69` + `6b10b790` + `2db111ca` + `fae208fc`

| Tarefa | Status |
|---|---|
| Criar tabelas DB (broadcasts, reads) | **Concluido** |
| Model: AdmsChatBroadcast | **Concluido** |
| Service: BroadcastService | **Concluido** |
| Controllers: ChatBroadcast, Add, Edit, Delete, MarkRead | **Concluido** |
| Views: modal de criacao, modal urgente | **Concluido** |
| JS: Broadcast UI + prioridades + modal urgente | **Concluido** |
| Envio de arquivos em broadcasts | **Concluido** |
| Backend reply-to (AddChat + AddChatGroupMessage) | **Concluido** |
| Frontend reply-to (quote UI, preview, scroll-to-quote) | **Concluido** |

**Entrega:** Broadcasts com prioridades + reply-to com quote visual

### Fase 4 - Busca + Typing + Polish (Concluida)

**Commits:** `cd9d6fb6` + `33641e15` + `1447c3b2`

| Tarefa | Status |
|---|---|
| Indice FULLTEXT + endpoints de busca | **Concluido** |
| UI de busca global + na conversa/grupo | **Concluido** |
| Navegacao para mensagem exata no resultado de busca | **Concluido** |
| Typing indicator via WebSocket | **Concluido** |
| Notificacoes push (Browser Notification API) | **Concluido** |
| Separadores de data (Hoje/Ontem/data) | **Concluido** |
| Scroll para primeira mensagem nao lida | **Concluido** |
| Divisor visual de mensagens nao lidas | **Concluido** |
| Fix: notificacoes do navegador quando tab sem foco | **Concluido** |
| Fix: auto-dismiss de notificacoes globais | **Concluido** |

**Entrega:** Todas as features + UX polish completo

---

## 15. Metricas Finais

### Componentes Implementados

| Componente | Quantidade | Arquivos |
|---|---|---|
| Controllers | 19 | AbstractChatController, Chat, AddChat, EditChat, DeleteChat, MarkChatRead, SearchChatUsers, SendFileChat, ChatGroup, AddChatGroup, EditChatGroup, DeleteChatGroup, ChatGroupMember, AddChatGroupMessage, ChatBroadcast, AddChatBroadcast, EditChatBroadcast, DeleteChatBroadcast, MarkBroadcastRead |
| Models | 5 | AdmsChat, AdmsListChats, AdmsViewChat, AdmsChatGroup, AdmsChatBroadcast |
| Services | 6 | ChatService, GroupChatService, BroadcastService, WebSocketService, WebSocketNotifier, WebSocketTokenService |
| Views | 9 | loadChat, listChat, + 7 partials (modals e paineis) |
| JavaScript | 6.014 LOC | chat.js (5.839L) + navbar-chat-badge.js (175L) |
| CSS | ~580 linhas | chat.css (dedicado ao modulo) |
| Tabelas DB | 9 | Ver secao 12 |
| Endpoints | 41 | Metodos publicos nos controllers |

### Evolucao v1.0 → v2.0

| Metrica | v1.0 | v2.0 | Crescimento |
|---|---|---|---|
| Controllers | 8 | 19 | +137% |
| Models | 3 | 5 | +67% |
| Services | 1 | 6 | +500% |
| Views | 4 | 9 | +125% |
| JS (LOC) | 1.880 | 6.014 | +220% |
| DB Tables | 2 | 9 | +350% |
| Endpoints | 13 | 41 | +215% |

### Infraestrutura

| Item | Custo | Detalhes |
|---|---|---|
| Ratchet WebSocket Server | R$ 0 | Mesmo servidor, porta 8080 |
| ReactPHP HTTP (IPC) | R$ 0 | Porta 8081 (interno) |
| Supervisor (process manager) | R$ 0 | Open source |
| Redis | **Nao necessario** | IPC via HTTP substituiu PubSub |
| SSL para wss:// | R$ 0 | Reutiliza certificado existente |
| **Total adicional** | **R$ 0** | Mesma infraestrutura |

---

## 16. Riscos e Mitigacoes

| # | Risco | Probabilidade | Impacto | Mitigacao | Status |
|---|---|---|---|---|---|
| 1 | WebSocket server instavel (crash) | Media | Alto | Supervisor com auto-restart + fallback polling | **Mitigado** (fallback implementado) |
| 2 | Escalabilidade de grupos grandes | Baixa | Medio | Limite de membros configuravel + lazy loading | **Mitigado** (max_members + pagination) |
| 3 | Migracao de schema quebra dados | Baixa | Alto | Tabelas separadas para grupo (nao altera existentes) | **Mitigado** (schema nao-destrutivo) |
| 4 | Conflito de porta WebSocket | Media | Medio | Proxy reverso Apache | **Documentado** (ver abaixo) |
| 5 | Performance FULLTEXT em tabela grande | Baixa | Medio | Monitorar + particionar se necessario | **Monitorando** |
| 6 | Broadcasts spam | Media | Baixo | Permissao por nivel de acesso | **Mitigado** |
| 7 | Race conditions em grupos | Baixa | Baixo | UUIDs + timestamps + indices | **Mitigado** |

### Proxy Reverso Apache (WebSocket)

```apache
# Adicionar ao VirtualHost
<Location /ws>
    ProxyPass ws://localhost:8080/
    ProxyPassReverse ws://localhost:8080/
</Location>

# Habilitar modulos necessarios
# a2enmod proxy proxy_wstunnel proxy_http
```

---

## 17. Compatibilidade e Migracao

### Retrocompatibilidade

| Aspecto | Compativel? | Detalhes |
|---|---|---|
| URLs existentes | Sim | Endpoints atuais permanecem iguais |
| Conversas 1:1 | Sim | Funcionam exatamente como antes |
| Mensagens existentes | Sim | Nenhuma alteracao destrutiva |
| Permissoes | Sim | Novas rotas registradas em adms_paginas |
| JavaScript | Sim | chat.js evoluiu (mesmo arquivo, nao substituido) |
| Polling | Sim | Mantido como fallback automatico |

### Checklist de Deploy

- [x] Backup completo do banco de dados
- [x] Executar migrations SQL (novas tabelas)
- [x] Instalar dependencias Composer (ratchet)
- [x] Configurar proxy reverso Apache para WebSocket
- [x] Instalar Supervisor para processo WebSocket
- [x] Deploy codigo PHP (Controllers, Models, Services, Views)
- [x] Deploy JavaScript (chat.js + navbar-chat-badge.js)
- [x] Deploy CSS (chat.css)
- [x] Registrar rotas em adms_paginas
- [x] Conceder permissoes em adms_nivacs_pgs
- [x] Iniciar servidor WebSocket
- [x] Testar fallback polling
- [x] Monitorar logs

### Commits de Implementacao (em ordem cronologica)

| Commit | Descricao |
|---|---|
| `73960917` | Fase 0-2: Security fixes, refactoring, new features |
| `205a7625` | Fase 3: Group conversations with member management |
| `e7f5b8e7` | Fix: Group message delete/edit, video support, UX |
| `09dc1cb9` | Fix: MySQL session timezone |
| `6354bb69` | Fase 4: Broadcast (comunicados) system |
| `2db111ca` | Add file attachment support to broadcasts |
| `3d401a71` | Fix: Broadcast sender always sees own broadcasts |
| `6b10b790` | Add broadcast edit/delete UI, fix duplicate notifications |
| `5cf5d662` | WebSocket real-time messaging + refactor unread badge |
| `cd9d6fb6` | Message search, typing indicators, browser notifications |
| `33641e15` | Fix: Navigate to exact message on search result click |
| `d600c427` | Fix: Use getResult() in MarkChatRead |
| `fae208fc` | Fix: Accents and min-height in broadcast modal |
| `1447c3b2` | Date separators, unread scroll, fix notifications |

---

**Documento preparado por:** Equipe Mercury - Grupo Meia Sola
**Implementacao concluida:** 20 de Fevereiro de 2026
**Versao do documento:** 2.0 (atualizado para refletir implementacao completa)
