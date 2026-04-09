# Modulo 4A: Chat + Helpdesk

**Status:** Pendente
**Fase:** 4A
**Prioridade:** MENOR — Comunicacao (requer WebSocket)
**Estimativa:** ~40 arquivos novos
**Referencia v1:** `C:\wamp64\www\mercury\app\adms\Controllers\Chat.php`, `Helpdesk.php`

---

## 1. Pre-requisitos

```bash
composer require laravel/reverb
npm install laravel-echo pusher-js
```

Configurar broadcasting.php para Laravel Reverb.

## 2. Chat

### Features
- Conversas 1-to-1 e em grupo
- Mensagens de texto + anexos (imagens, arquivos)
- Indicador de digitacao (typing)
- Presenca online/offline
- Read receipts
- Broadcast (mensagem para todos)

### Tabelas
conversations, conversation_participants, messages, message_attachments, user_presence

### Events (Broadcasting)
MessageSent, UserTyping, PresenceUpdated

## 3. Helpdesk

### Features
- Tickets com SLA por prioridade (Baixa 72h, Media 48h, Alta 24h, Urgente 8h)
- Departamentos: TI, DP, Facilities
- Categorias cascateando por departamento
- Interacoes (comentarios, mudancas de status, atribuicoes)
- Anexos
- Notas internas (nao visiveis ao solicitante)

### State Machine (Tickets)
```
Aberto → Em Andamento ↔ Pendente
              ↓
          Resolvido → Fechado
              ↓
          Cancelado
```

### Tabelas
hd_departments, hd_categories, hd_tickets, hd_interactions, hd_attachments

## 4. Permissions (6)
VIEW_CHAT, CREATE_CONVERSATIONS, VIEW_HELPDESK, CREATE_TICKETS, MANAGE_TICKETS, MANAGE_HELPDESK_CONFIG

## 5. Arquivos: 10 migrations, 10 models, 4 services, 4 events, 2 controllers, 8 frontend, 2 tests

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
