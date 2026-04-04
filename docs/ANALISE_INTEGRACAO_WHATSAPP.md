# Análise Completa — Integração WhatsApp / Evolution Bot / N8N

**Data:** 30/03/2026
**Versão:** 1.0
**Escopo:** Fluxo de mensagens, dependências, problemas identificados e sugestões de melhoria

---

## 1. Mapa Completo do Fluxo de Mensagens

### 1.1 Fluxo Principal: WhatsApp → N8N → Mercury (DP Chat)

```
WhatsApp (celular do colaborador)
    │
    ▼ mensagem enviada
Evolution API v2.3.7 (container Docker)
    │ webhook global: http://n8n:5678/webhook/whatsapp
    ▼
N8N Workflow: "WhatsApp DP → Chat → IA → Mercury"
    │
    ├─ 1. Webhook WhatsApp (recebe evento)
    │
    ├─ 2. Responder 200 (evita timeout do Evolution)
    │
    ├─ 3. Filtrar e Extrair (JavaScript)
    │     ├─ Rejeita: fromMe=true, grupos (@g.us), status ACK, texto vazio
    │     └─ Extrai: phone_number, message_text, message_id, push_name
    │
    ├─ 4. Mercury Chat API
    │     POST /api/v1/dp-chat/message
    │     Auth: Bearer {DP_CHAT_API_KEY}
    │     Body: { whatsapp_number, message_text, push_name, message_id }
    │
    ├─ 5. Tipo de Ação (switch por action da resposta)
    │     │
    │     ├─ action = "ask_cpf"
    │     │   └─ Responde: "Informe seu CPF"
    │     │
    │     ├─ action = "ask_demand"
    │     │   └─ Responde: "Descreva sua solicitação"
    │     │
    │     ├─ action = "message_appended"
    │     │   └─ Silencia (mensagem anexada a ticket existente)  ← FALTA ROTA NO N8N
    │     │
    │     ├─ action = "process_demand"
    │     │   ├─ Montar Prompt (contexto do funcionário + demanda)
    │     │   ├─ Groq LLM (llama-3.3-70b-versatile, temp=0.1)
    │     │   ├─ Normalizar Resposta IA (request_type, urgency, confidence)
    │     │   ├─ Criar Ticket (POST /api/v1/personnel-requests)
    │     │   └─ Montar Confirmação
    │     │
    │     └─ Todos convergem para:
    │         └─ Enviar WhatsApp (POST /message/sendText/mercury-dp)
    │
    └─ FIM
```

### 1.2 Fluxo Alternativo: Evolution Bot (FAQ direto)

```
WhatsApp (mensagem corresponde a trigger do bot)
    │
    ▼
Evolution Bot (configurado via aba Chatbots do painel)
    │ POST /api/v1/evolution-bot/handle
    │ Auth: apiKey no body (EVOLUTION_BOT_HANDLER_KEY)
    ▼
EvolutionBotController
    │ Extrai: inputs.remoteJid, inputs.pushName, query (mensagem)
    ▼
EvolutionBotHandlerService
    │
    ├─ "menu", "oi", "olá" → Menu com 6 opções
    ├─ "1" ou "dp" → "Informe seu CPF para atendimento DP"
    ├─ "2" ou "horario" → Horário de funcionamento
    ├─ "3" ou "holerite" → "Digite dp para solicitar"
    ├─ "4" ou "ferias" → "Digite dp para informações"
    ├─ "5" ou "atestado" → "Digite dp para enviar"
    ├─ "6" ou "contato" → Canais de contato
    ├─ Sinônimos (salario→holerite, medico→atestado, etc.)
    └─ Fallback → "Não entendi, digite menu"
    │
    ▼
Resposta: { "message": "texto" }
Evolution Bot envia de volta ao WhatsApp
```

### 1.3 Máquina de Estados do DpChatController

```
                    ┌─────────────────┐
                    │  Nova mensagem   │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │ Tem solicitação  │──SIM──► Anexa mensagem
                    │ aberta (status   │         action: "message_appended"
                    │ 1,2,3,4)?       │         Notifica DP via WebSocket
                    └────────┬────────┘
                             │ NÃO
                    ┌────────▼────────┐
                    │ Tem sessão ativa │──NÃO──► Cria sessão
                    │ (< 30 min)?     │          action: "ask_cpf"
                    └────────┬────────┘
                             │ SIM
                    ┌────────▼────────┐
                    │  step = ?        │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
     ┌────────▼───┐  ┌──────▼──────┐  ┌───▼────────┐
     │awaiting_cpf│  │awaiting_    │  │ completed  │
     │            │  │demand       │  │            │
     │ Valida CPF │  │ Coleta      │  │ Reinicia   │
     │ 11 dígitos │  │ descrição   │  │ sessão     │
     │ Busca em   │  │ da demanda  │  │            │
     │ adms_      │  │             │  │            │
     │ employees  │  │ action:     │  │ action:    │
     │            │  │ "process_   │  │ "ask_cpf"  │
     │ action:    │  │ demand"     │  │            │
     │ "ask_      │  │             │  │            │
     │ demand"    │  │ N8N faz IA  │  │            │
     │            │  │ + cria      │  │            │
     └────────────┘  │ ticket      │  └────────────┘
                     └─────────────┘
```

---

## 2. Componentes e Dependências

### 2.1 Mapa de Arquivos

| Componente | Arquivo | Depende de |
|------------|---------|-----------|
| **Painel Admin** | `Controllers/WhatsappAdmin.php` | EnvLoader, ConfigView, Evolution API |
| **View** | `Views/whatsAppAdmin/loadWhatsAppAdmin.php` | WhatsappAdmin controller |
| **JS** | `assets/js/whatsapp-admin.js` | View (data attributes) |
| **DP Chat** | `Controllers/Api/V1/DpChatController.php` | AdmsPersonnelRequest, SessionContext, EnvLoader |
| **Evolution Bot** | `Controllers/Api/V1/EvolutionBotController.php` | EvolutionBotHandlerService, EnvLoader |
| **Bot Service** | `Services/EvolutionBotHandlerService.php` | AdmsRead (employee lookup) |
| **WhatsApp Service** | `Services/WhatsAppService.php` | EnvLoader, LoggerService, cURL |
| **Personnel Model** | `Models/AdmsPersonnelRequest.php` | AdmsRead, AdmsCreate, AdmsUpdate, AdmsDelete |
| **Auth** | `Controllers/Api/V1/AuthController.php` | JwtService |
| **JWT** | `core/Api/JwtService.php` | Firebase\JWT, AdmsRead, AdmsCreate |
| **Router** | `core/Api/ApiRouter.php` | ApiAuthMiddleware, ApiRateLimiter |
| **N8N Workflow** | `docker/n8n-workflow-whatsapp-dp-prod.json` | DpChatController, PersonnelRequestsController, Groq API, Evolution API |

### 2.2 Variáveis de Ambiente Necessárias

| Variável | Usado por | Obrigatória |
|----------|----------|-------------|
| `EVOLUTION_API_URL` | WhatsappAdmin, WhatsAppService | Sim |
| `EVOLUTION_API_KEY` | WhatsappAdmin, WhatsAppService | Sim |
| `EVOLUTION_INSTANCE` | WhatsappAdmin, WhatsAppService | Sim |
| `EVOLUTION_BOT_HANDLER_KEY` | EvolutionBotController | Sim |
| `DP_CHAT_API_KEY` | DpChatController | Sim |
| `N8N_URL` | WhatsappAdmin (view) | Não |
| `VPS_DOMAIN` | WhatsappAdmin (fallback N8N URL) | Não |
| `WEBHOOK_GLOBAL_ENABLED` | WhatsappAdmin (view) | Não |
| `WEBHOOK_GLOBAL_URL` | WhatsappAdmin (view) | Não |
| `JWT_SECRET` | JwtService | Não (usa HASH_KEY) |
| `JWT_ACCESS_TTL` | JwtService | Não (default 3600s) |
| `GROC_API_KEY` | N8N (Groq LLM node) | Sim (para IA) |

### 2.3 Tabelas do Banco de Dados

| Tabela | Função |
|--------|--------|
| `adms_personnel_requests` | Solicitações DP (tickets) |
| `adms_personnel_request_messages` | Thread de mensagens por solicitação |
| `adms_status_personnel_requests` | Lookup: 6 status (Novo→Cancelado) |
| `adms_dp_chat_sessions` | Sessões do fluxo conversacional (TTL 30min) |
| `adms_employees` | Diretório de funcionários (busca por CPF) |
| `api_tokens` | Refresh tokens JWT |
| `adms_logger` | Logs de auditoria |

### 2.4 Autenticação por Endpoint

| Endpoint | Método | Chamado por |
|----------|--------|-------------|
| `/api/v1/dp-chat/message` | API Key (`DP_CHAT_API_KEY`) | N8N |
| `/api/v1/evolution-bot/handle` | API Key (`EVOLUTION_BOT_HANDLER_KEY`) | Evolution Bot |
| `/api/v1/personnel-requests` | JWT (Bearer token) | N8N |
| `/api/v1/auth/login` | Usuário + Senha | N8N (para obter JWT) |
| `/whatsapp-admin/api` | Sessão web (ConfigController) | Painel Admin |

---

## 3. Problemas Identificados

### 3.1 CRÍTICOS (corrigir imediatamente)

#### P1: JWT hardcoded no workflow N8N

**Problema:** O workflow `n8n-workflow-whatsapp-dp-prod.json` contém `"Bearer YOUR_JWT_TOKEN"` como placeholder. Se em produção foi colocado um JWT real, ele expira em 1 hora e o fluxo para de funcionar.

**Impacto:** O endpoint `/api/v1/personnel-requests` (criar ticket) requer JWT. Quando expira, tickets não são criados, mas mensagens continuam sendo processadas pelo `dp-chat/message` (que usa API Key).

**Solução:** Trocar autenticação de `personnel-requests` para API Key (como já feito com `dp-chat`), ou adicionar um node de login no N8N antes da chamada.

#### P2: Race condition na geração de request_number

**Problema:** `AdmsPersonnelRequest::create()` faz `SELECT MAX(id) + 1` para gerar o número `DP-2026-XXXX`. Duas requisições simultâneas podem gerar o mesmo número.

**Impacto:** Constraint UNIQUE violada → erro 500 → ticket não criado.

**Solução:** Usar `SELECT ... FOR UPDATE` ou gerar UUID.

#### P3: Rota `message_appended` não tratada no N8N

**Problema:** Quando o colaborador tem solicitação aberta, o Mercury retorna `action: "message_appended"` com `reply: null`. O node "Tipo de Ação" no N8N não tem saída para este caso, causando erro silencioso.

**Impacto:** Mensagem é salva no Mercury mas N8N não responde ao colaborador (pode ser intencional, mas precisa de rota explícita).

**Solução:** Adicionar rota `message_appended` no node Switch do N8N — pode silenciar ou confirmar recebimento.

### 3.2 ALTOS (corrigir antes do próximo release)

#### P4: employee_id não salvo na criação do ticket

**Problema:** `DpChatController` identifica o funcionário (id, nome, CPF, loja) e retorna no response. O N8N repassa para `PersonnelRequestsController.store()`, mas o `$createData` não inclui `employee_id`.

**Impacto:** Todos os tickets têm `employee_id = NULL`. Não é possível consultar "todas as solicitações deste funcionário".

**Solução:** Adicionar `'employee_id' => isset($data['employee_id']) ? (int)$data['employee_id'] : null` no array `$createData` do `PersonnelRequestsController`.

#### P5: Evolution API key hardcoded no workflow N8N

**Problema:** O node "Enviar WhatsApp" do workflow contém a API key da Evolution em texto plano no JSON.

**Impacto:** Key exposta no versionamento e logs do N8N.

**Solução:** Usar N8N Credentials para armazenar a key de forma criptografada.

### 3.3 MÉDIOS (próximo sprint)

#### P6: FAQ hardcoded no EvolutionBotHandlerService

**Problema:** Respostas de FAQ estão em `const` no código PHP. Qualquer alteração exige deploy.

**Solução:** Criar tabela `adms_evolution_bot_faqs` e carregar do banco.

#### P7: Mensagens não reconhecidas pelo bot não são logadas

**Problema:** Quando o fallback é acionado, não há registro de qual mensagem não foi entendida. Impossível identificar padrões de melhoria.

**Solução:** `LoggerService::warning('EVOLUTION_BOT_FALLBACK', ...)` com a mensagem original.

#### P8: Tokens expirados permanecem no banco

**Problema:** Tabela `api_tokens` acumula registros revogados/expirados indefinidamente.

**Solução:** Cron job diário: `DELETE FROM api_tokens WHERE revoked_at < DATE_SUB(NOW(), INTERVAL 30 DAY) OR expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)`.

### 3.4 BAIXOS (nice to have)

#### P9: EvolutionBotController não documentado no MODULO_WHATSAPP_ADMIN.md

**Problema:** O fluxo do Evolution Bot (FAQ) não está descrito na documentação do módulo.

#### P10: Dois workflows N8N (dev/prod) com URLs hardcoded

**Problema:** Manutenção duplicada. Alteração em um precisa ser replicada no outro.

**Solução:** Usar variáveis de ambiente do N8N para URLs, mantendo um único workflow.

---

## 4. Sugestões de Melhoria

### 4.1 Curto Prazo (próximas 2 semanas)

| # | Melhoria | Esforço | Impacto |
|---|----------|---------|---------|
| 1 | Trocar auth de `personnel-requests` para API Key (como `dp-chat`) | Baixo | Elimina P1 (JWT expirando) |
| 2 | Adicionar rota `message_appended` no N8N (silenciar ou confirmar) | Baixo | Elimina P3 |
| 3 | Incluir `employee_id` no `$createData` do PersonnelRequestsController | Baixo | Elimina P4 |
| 4 | Adicionar `SELECT FOR UPDATE` na geração de request_number | Baixo | Elimina P2 |
| 5 | Log de mensagens não reconhecidas no bot | Baixo | Elimina P7 |

### 4.2 Médio Prazo (próximo mês)

| # | Melhoria | Esforço | Impacto |
|---|----------|---------|---------|
| 6 | Mover FAQ do bot para tabela no banco | Médio | Elimina P6, permite edição via painel |
| 7 | Tela de gestão de FAQ no painel WhatsApp Admin | Médio | UX para DP gerenciar respostas |
| 8 | Unificar workflows dev/prod com variáveis de ambiente N8N | Médio | Elimina P10, reduz manutenção |
| 9 | Cron para limpar tokens expirados | Baixo | Elimina P8 |
| 10 | Dashboard de métricas: msgs/dia, tempo de resposta, tipos de demanda | Alto | Visibilidade operacional |

### 4.3 Longo Prazo (próximo trimestre)

| # | Melhoria | Esforço | Impacto |
|---|----------|---------|---------|
| 11 | Migrar N8N credentials para secrets criptografados | Médio | Elimina P5, segurança |
| 12 | Testes automatizados do fluxo completo (WhatsApp→N8N→Mercury) | Alto | Confiabilidade |
| 13 | Suporte a múltiplas instâncias WhatsApp no painel | Alto | Escalabilidade |
| 14 | Fila de mensagens (Redis/RabbitMQ) entre N8N e Mercury | Alto | Resiliência |
| 15 | Painel de conversas WhatsApp em tempo real no Mercury | Alto | DP responde direto do sistema |

---

## 5. Status Atual vs Esperado

### Fluxo N8N → DpChatController

| Ação | Status | Observação |
|------|--------|-----------|
| ask_cpf | OK | Pede CPF ao colaborador |
| invalid_cpf | OK | Re-pede CPF |
| cpf_not_found | OK | Informa que CPF não existe |
| ask_demand | OK | Pede descrição da demanda |
| process_demand | OK | Envia para IA + cria ticket |
| message_appended | FALTA ROTA | N8N não trata esta ação |
| already_identified | OK | Colaborador enviou CPF de novo |
| new_session | OK | Reinicia após ticket criado |

### Fluxo Evolution Bot → EvolutionBotController

| Trigger | Status | Observação |
|---------|--------|-----------|
| menu/oi/olá | OK | Exibe menu com opções |
| dp (ou 1) | OK | Pede CPF para DP |
| horario (ou 2) | OK | Mostra horários |
| holerite (ou 3) | OK | Redireciona para DP |
| ferias (ou 4) | OK | Redireciona para DP |
| atestado (ou 5) | OK | Redireciona para DP |
| contato (ou 6) | OK | Mostra canais |
| sinônimos | OK | Mapeia para FAQ |
| não reconhecido | OK mas sem log | Fallback sem rastreamento |

### Fluxo de Criação de Ticket

| Etapa | Status | Observação |
|-------|--------|-----------|
| IA classifica demanda | OK | Groq llama-3.3-70b |
| Normaliza resposta IA | OK | Fallback se JSON inválido |
| Cria ticket no Mercury | JWT EXPIRA | PersonnelRequestsController usa JWT |
| Salva employee_id | FALTA | Campo não incluído no createData |
| Gera request_number | RACE CONDITION | SELECT MAX(id) não é atômico |
| Notifica DP via WebSocket | OK | SystemNotificationService |
| Envia confirmação WhatsApp | OK | Evolution API sendText |

---

## 6. Diagrama de Dependências

```
┌─────────────────────────────────────────────────────────────────┐
│                    Sistemas Externos                             │
│  ┌──────────┐  ┌──────────────┐  ┌────────┐  ┌─────────────┐  │
│  │ WhatsApp │  │ Evolution API│  │  N8N   │  │  Groq LLM   │  │
│  │ (Baileys)│  │    v2.3.7    │  │ 1.79.3 │  │llama-3.3-70b│  │
│  └────┬─────┘  └──────┬───────┘  └───┬────┘  └──────┬──────┘  │
└───────┼───────────────┼──────────────┼───────────────┼─────────┘
        │               │              │               │
        │    webhook     │   webhook    │    HTTP POST  │
        └──────►────────►└─────►───────►└──────────────►│
                                       │               │
┌──────────────────────────────────────┼───────────────┼─────────┐
│              Mercury API             │               │         │
│  ┌───────────────────────┐           │               │         │
│  │ DpChatController      │◄──────────┘               │         │
│  │ (API Key auth)        │                           │         │
│  │  └─ Session state     │                           │         │
│  │  └─ Employee lookup   │                           │         │
│  │  └─ Media download    │                           │         │
│  └───────────┬───────────┘                           │         │
│              │                                       │         │
│  ┌───────────▼───────────┐    ┌──────────────────┐   │         │
│  │ PersonnelRequests     │    │ EvolutionBot     │   │         │
│  │ Controller (JWT auth) │    │ Controller       │   │         │
│  │  └─ Create ticket     │    │ (API Key auth)   │   │         │
│  │  └─ Append message    │    │  └─ FAQ handler  │   │         │
│  │  └─ WebSocket notify  │    │  └─ Menu routing │   │         │
│  └───────────┬───────────┘    └──────────────────┘   │         │
│              │                                       │         │
│  ┌───────────▼───────────┐    ┌──────────────────┐   │         │
│  │ AdmsPersonnelRequest  │    │ WhatsAppService  │   │         │
│  │ (Model)               │    │ (envio de msgs)  │   │         │
│  └───────────┬───────────┘    └──────────────────┘   │         │
└──────────────┼───────────────────────────────────────┘         │
               │                                                  │
┌──────────────▼──────────────────────────────────────────────────┘
│  Banco de Dados (MySQL)
│  ├─ adms_personnel_requests      (tickets)
│  ├─ adms_personnel_request_messages (thread de mensagens)
│  ├─ adms_dp_chat_sessions        (estado da conversa)
│  ├─ adms_employees               (diretório de funcionários)
│  ├─ api_tokens                   (refresh tokens JWT)
│  └─ adms_logger                  (auditoria)
└─────────────────────────────────────────────────────────────────
```

---

## 7. Resumo Executivo

| Aspecto | Avaliação |
|---------|-----------|
| Arquitetura | Sólida — separação clara de responsabilidades |
| Fluxo de mensagens | Funcional, mas N8N não trata `message_appended` |
| Segurança (API Keys) | Boa — proxy protege keys no painel |
| Segurança (JWT no N8N) | Crítica — token expira e workflow para |
| Concorrência | Race condition na geração de números |
| Observabilidade | Boa (LoggerService), falta log de fallback do bot |
| Documentação | Atualizada para painel, falta documentar fluxo do bot |
| Testes | Inexistentes para este módulo |
| Escalabilidade | Adequada para volume atual |

**Prioridade imediata:** Resolver P1 (JWT → API Key para personnel-requests), P2 (race condition), P3 (rota message_appended no N8N) e P4 (employee_id).
