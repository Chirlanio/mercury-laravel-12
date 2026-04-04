# Plano de Ação: Integração WhatsApp → Solicitações DP

**Projeto:** Mercury - Grupo Meia Sola
**Versão:** 2.0
**Data de Início:** 27 de Março de 2026
**Última Atualização:** 28 de Março de 2026
**Status Geral:** Em Produção (VPS + Hospedagem Compartilhada)

---

## 1. Resumo

Implementação de um fluxo automatizado que transforma mensagens de WhatsApp em cards de solicitações para o Departamento Pessoal (DP), utilizando IA (Groq/Llama 3.3 70B) para classificação e extração de dados, N8N como orquestrador de fluxos, e Evolution API como gateway WhatsApp.

**Proposta original:** `docs/PROPOSTA_INTEGRACAO_WHATSAPP_DP.md`
**Guia de deploy:** `docs/DEPLOY_VPS_CENTRAL_DP.md`

---

## 2. Arquitetura de Produção

```
┌─────────────┐     WhatsApp    ┌──────────────────────────────────┐
│ Colaborador  │◄──────────────►│  VPS (Ubuntu 24 + Docker)        │
│ WhatsApp     │                │  ws.portalmercury.com.br          │
└─────────────┘                 │  ├─ Nginx (SSL nativo)           │
                                │  ├─ Evolution API v2.3.7 :8085   │
                                │  ├─ N8N v1.79.3 :5678            │
                                │  ├─ PostgreSQL 15 :5432           │
                                │  └─ Redis 7 :6379                │
                                └──────────────┬───────────────────┘
                                               │ HTTPS
                                               ▼
                                ┌──────────────────────────────────┐
                                │  Hospedagem Compartilhada         │
                                │  www.portalmercury.com.br         │
                                │  (Hostinger)                      │
                                │  └─ Mercury (PHP 8.3 + MySQL)    │
                                └──────────────────────────────────┘
```

### Stack Tecnológico

| Componente | Tecnologia | Versão | Status |
|---|---|---|---|
| WhatsApp Gateway | Evolution API | v2.3.7 | ✅ Produção (VPS) |
| Orquestrador | N8N | v1.79.3 | ✅ Produção (VPS) |
| IA (principal) | Groq + Llama | 3.3-70b-versatile | ✅ Operacional |
| Database IA | PostgreSQL | 15-alpine | ✅ Produção (VPS) |
| Cache | Redis | 7-alpine | ✅ Produção (VPS) |
| Backend | Mercury (PHP 8.3) | — | ✅ Produção (Hostinger) |
| Database | MySQL | — | ✅ Produção (Hostinger) |
| Reverse Proxy | Nginx | Nativo VPS | ✅ SSL via Hostinger |

### URLs de Produção

| Serviço | URL |
|---|---|
| Mercury (Kanban DP) | `https://www.portalmercury.com.br/personnel-requests/list` |
| N8N (workflows) | `https://ws.portalmercury.com.br/` |
| Evolution API | `https://ws.portalmercury.com.br/evolution/` |
| N8N Webhooks | `https://ws.portalmercury.com.br/webhook/` |
| Mercury API | `https://www.portalmercury.com.br/api/v1/` |

---

## 3. Fases de Implementação

### Fase 1 — Infraestrutura ✅ Concluída

| Tarefa | Status | Observações |
|---|---|---|
| Instalar Docker Desktop (local) | ✅ | Windows 11 |
| Criar `docker/docker-compose.yml` | ✅ | 4 serviços: Evolution, N8N, PostgreSQL, Redis |
| Configurar Evolution API | ✅ | Imagem `evoapicloud/evolution-api:v2.3.7` |
| Conectar WhatsApp | ✅ | Via **Pairing Code** |
| Aplicar patches Baileys | ✅ | 4 patches: MACOS, passive, lidDbMigrated, finishInit |
| Configurar N8N | ✅ | Workflow importado via JSON |
| Obter API Key Groq | ✅ | Tier gratuito (30 req/min) |

### Fase 2 — Módulo Mercury ✅ Concluída

| Tarefa | Status | Arquivos |
|---|---|---|
| Criar tabelas MySQL (3+1) | ✅ | `2026_03_27_create_personnel_requests_tables.sql` + `adms_dp_chat_sessions` |
| Seed de status (6) | ✅ | Novo, Em Análise, Aguard. Info, Em Atendimento, Resolvido, Cancelado |
| Model CRUD | ✅ | `AdmsPersonnelRequest.php` |
| Model Lista (Kanban) | ✅ | `AdmsListPersonnelRequests.php` |
| Model Estatísticas + Dashboard | ✅ | `AdmsStatisticsPersonnelRequests.php` |
| Model Exportação | ✅ | `AdmsExportPersonnelRequests.php` |
| API Controller (4 endpoints) | ✅ | `PersonnelRequestsController.php` |
| DP Chat Controller (conversacional) | ✅ | `DpChatController.php` |
| Rotas API registradas | ✅ | `ApiRouter.php` (5 rotas) |
| Deduplicação por número | ✅ | Mesmo número com solicitação aberta → append |
| Web Controller principal | ✅ | `PersonnelRequests.php` |
| View Controller (modal) | ✅ | `ViewPersonnelRequest.php` |
| Edit Controller (modal) | ✅ | `EditPersonnelRequest.php` |
| Export Controller (CSV/PDF) | ✅ | `ExportPersonnelRequests.php` |
| View Kanban board (abas) | ✅ | Aba Kanban + Aba Dashboard |
| View modal detalhes + chat | ✅ | `_view_personnel_request.php` |
| View modal edição | ✅ | `_edit_personnel_request.php` |
| Kanban card partial | ✅ | `_kanban_card.php` |
| Cards de estatísticas | ✅ | No `loadPersonnelRequests.php` |
| Dashboard analítico (Chart.js) | ✅ | 4 gráficos + KPIs expandidos |
| Filtros de busca | ✅ | Status, tipo, urgência, período, texto livre |
| JavaScript completo | ✅ | `personnel-requests.js` + `personnel-requests-dashboard.js` |
| Drag-and-drop (SortableJS) | ✅ | Mover cards entre colunas altera status |
| Auto-atribuição no drag | ✅ | Card sem responsável → atribuído ao usuário que moveu |
| Exportação CSV/PDF | ✅ | Botão no cabeçalho, DomPDF landscape |
| Notificação WhatsApp ao colaborador | ✅ | Mudança de status, resolução via Evolution API |
| Chat WhatsApp bidirecional | ✅ | Operador envia mensagem pelo modal → colaborador recebe no WhatsApp |
| Polling de mensagens no modal | ✅ | A cada 5s busca novas mensagens via `get-messages` |
| Notificações WebSocket (equipe DP) | ✅ | Toast + Kanban refresh via `SystemNotificationService` |
| Rotas web registradas | ✅ | `adms_paginas` + `adms_nivacs_pgs` (10 rotas) |
| Testes unitários (PHPUnit) | ✅ | 48 testes, 133 assertions |
| CPF do colaborador no modal | ✅ | JOIN com `adms_employees`, máscara 000.000.000-00 |

### Fase 3 — Fluxo N8N ✅ Concluída

| Tarefa | Status | Observações |
|---|---|---|
| Webhook recebendo mensagens | ✅ | `POST /webhook/whatsapp` |
| Filtro de mensagens (só texto individual) | ✅ | Ignora grupos, mídia, mensagens próprias |
| Fluxo conversacional (CPF → demanda) | ✅ | Mercury Chat API gerencia sessão por número |
| Groq classificando mensagens | ✅ | ~90% confiança, ~200ms latência |
| Normalização de resposta | ✅ | Code node compatível com todos os provedores |
| Mercury API integrada | ✅ | `POST /api/v1/dp-chat/message` + `POST /api/v1/personnel-requests` |
| Resposta automática WhatsApp | ✅ | Confirmação de ticket + pedido de CPF + identificação |
| Deduplicação (message_appended) | ✅ | Mensagens de follow-up vão ao thread |

### Fase 4 — Testes e Validação ✅ Concluída

| Tarefa | Status | Observações |
|---|---|---|
| Testes da API via curl | ✅ | CRUD completo validado |
| Testes do fluxo N8N simulado | ✅ | Webhook → Groq → Mercury funcionando |
| Teste com WhatsApp real | ✅ | Fluxo completo validado em produção |
| Testes unitários (PHPUnit) | ✅ | 48 testes, 133 assertions (3 arquivos) |
| Ajuste de filtro N8N | ✅ | Removido filtro `DELIVERY_ACK` (Evolution v2.3.7 inclui em mensagens válidas) |

### Fase 5 — Go-live ✅ Concluída

| Tarefa | Status | Observações |
|---|---|---|
| Deploy VPS (Docker) | ✅ | `ws.portalmercury.com.br` — Evolution API + N8N |
| Nginx reverse proxy | ✅ | Locations `/evolution/`, `/webhook/`, `/` no Nginx nativo da VPS |
| Conectar WhatsApp (produção) | ✅ | Via Pairing Code |
| Importar workflow N8N (produção) | ✅ | URLs atualizadas para `www.portalmercury.com.br` |
| Tabela `api_rate_limits` em produção | ✅ | Criada na hospedagem |
| Tabela `adms_dp_chat_sessions` em produção | ✅ | Criada na hospedagem |
| Atualizar `.env` Mercury (hospedagem) | ✅ | `EVOLUTION_API_URL` → VPS |
| Divulgar número WhatsApp | ❌ | Pendente — aguardando validação final |
| Monitorar volume e ajustar | ❌ | Pendente |
| Coletar feedback da equipe DP | ❌ | Pendente |

---

## 4. Estrutura de Arquivos

### Arquivos Criados

```
mercury/
├── database/migrations/
│   ├── 2026_03_27_create_personnel_requests_tables.sql    # Migration SQL (3 tabelas)
│   └── 2026_03_28_add_edit_personnel_request_routes.sql   # Rotas Edit + Export + Dashboard
│
├── app/adms/Controllers/
│   ├── PersonnelRequests.php                               # Web controller (Kanban + Dashboard + AJAX)
│   ├── ViewPersonnelRequest.php                            # View controller (modal)
│   ├── EditPersonnelRequest.php                            # Edit controller (modal)
│   ├── ExportPersonnelRequests.php                         # Export CSV/PDF
│   └── Api/V1/
│       ├── PersonnelRequestsController.php                 # API REST (N8N → Mercury)
│       └── DpChatController.php                            # API conversacional (CPF → demanda)
│
├── app/adms/Models/
│   ├── AdmsPersonnelRequest.php                            # CRUD + deduplicação + CPF join
│   ├── AdmsListPersonnelRequests.php                       # Listagem Kanban por status
│   ├── AdmsStatisticsPersonnelRequests.php                 # Estatísticas + Dashboard (Chart.js)
│   └── AdmsExportPersonnelRequests.php                     # Formatação para exportação
│
├── app/adms/Views/personnelRequests/
│   ├── loadPersonnelRequests.php                           # Página principal (stats + filtros + abas)
│   ├── listPersonnelRequests.php                           # Kanban board (AJAX)
│   └── partials/
│       ├── _kanban_card.php                                # Card individual do Kanban
│       ├── _view_personnel_request.php                     # Conteúdo AJAX modal visualização
│       ├── _view_personnel_request_modal.php               # Shell do modal visualização
│       ├── _edit_personnel_request.php                     # Conteúdo AJAX modal edição
│       └── _edit_personnel_request_modal.php               # Shell do modal edição
│
├── assets/js/
│   ├── personnel-requests.js                               # JS (Kanban, drag-drop, modais, export)
│   └── personnel-requests-dashboard.js                     # JS (Chart.js dashboard)
│
├── tests/PersonnelRequests/
│   ├── AdmsPersonnelRequestTest.php                        # 23 testes (CRUD, messages, search)
│   ├── AdmsListPersonnelRequestsTest.php                   # 11 testes (listagem, filtros)
│   └── AdmsStatisticsPersonnelRequestsTest.php             # 14 testes (stats, dashboard)
│
├── docker/
│   ├── docker-compose.yml                                  # Compose local (dev)
│   ├── docker-compose.prod.yml                             # Compose produção (VPS)
│   ├── .env                                                # Variáveis ambiente (dev)
│   ├── .env.prod                                           # Template variáveis produção
│   ├── deploy-vps.sh                                       # Script deploy automatizado
│   ├── nginx/nginx.conf                                    # Nginx reverse proxy (template)
│   ├── n8n-workflow-whatsapp-dp.json                       # Workflow N8N (dev)
│   └── n8n-workflow-whatsapp-dp-prod.json                  # Workflow N8N (produção)
│
└── docs/
    ├── PROPOSTA_INTEGRACAO_WHATSAPP_DP.md                  # Proposta original
    ├── DEPLOY_VPS_CENTRAL_DP.md                            # Guia completo de deploy VPS
    └── PLANO_ACAO_WHATSAPP_DP.md                           # Este arquivo
```

### Tabelas MySQL

| Tabela | Registros | Descrição |
|---|---|---|
| `adms_status_personnel_requests` | 6 (seed) | Status lookup (Novo → Cancelado) |
| `adms_personnel_requests` | Dinâmico | Solicitações do DP |
| `adms_personnel_request_messages` | Dinâmico | Thread de conversa WhatsApp |
| `adms_dp_chat_sessions` | Dinâmico (TTL 30min) | Sessões do fluxo conversacional |
| `api_rate_limits` | Dinâmico | Rate limiting da API REST |

### Rotas Web

| Controller | Método | URL | Descrição |
|---|---|---|---|
| PersonnelRequests | list | `/personnel-requests/list` | Kanban board |
| PersonnelRequests | statistics | `/personnel-requests/statistics` | AJAX stats |
| PersonnelRequests | changeStatus | `/personnel-requests/change-status` | AJAX status |
| PersonnelRequests | assign | `/personnel-requests/assign` | AJAX atribuir |
| PersonnelRequests | sendMessage | `/personnel-requests/send-message` | AJAX enviar WhatsApp |
| PersonnelRequests | getMessages | `/personnel-requests/get-messages` | AJAX buscar mensagens |
| PersonnelRequests | dashboard | `/personnel-requests/dashboard` | AJAX dados dashboard |
| ViewPersonnelRequest | view | `/view-personnel-request/view` | AJAX modal visualizar |
| EditPersonnelRequest | edit | `/edit-personnel-request/edit` | AJAX modal editar |
| EditPersonnelRequest | update | `/edit-personnel-request/update` | AJAX salvar edição |
| ExportPersonnelRequests | csv | `/export-personnel-requests/csv` | Download CSV |
| ExportPersonnelRequests | pdf | `/export-personnel-requests/pdf` | Download PDF |

### Rotas API REST

| Método | Endpoint | Descrição |
|---|---|---|
| POST | `/api/v1/auth/login` | Autenticação (JWT) |
| POST | `/api/v1/dp-chat/message` | Fluxo conversacional WhatsApp |
| POST | `/api/v1/personnel-requests` | Criar solicitação (N8N → Mercury) |
| GET | `/api/v1/personnel-requests` | Listar com paginação e filtros |
| GET | `/api/v1/personnel-requests/{id}` | Detalhes com mensagens |
| PUT | `/api/v1/personnel-requests/{id}/status` | Alterar status |

---

## 5. Configuração de Produção

### VPS (ws.portalmercury.com.br)

Nginx nativo da VPS com locations adicionados em `/etc/nginx/sites-enabled/ws.portalmercury.com.br.conf`:
- `/evolution/` → `http://127.0.0.1:8085/` (Evolution API)
- `/webhook/` → `http://127.0.0.1:5678/webhook/` (N8N webhooks)
- `/` → `http://127.0.0.1:5678` (N8N editor)

Containers Docker (docker-compose v1):
- `evolution-api` → porta `127.0.0.1:8085:8080`
- `n8n` → porta `127.0.0.1:5678:5678`
- `evolution-postgres` → sem porta externa
- `evolution-redis` → sem porta externa

### Hospedagem (www.portalmercury.com.br)

Variáveis no `.env`:
```
EVOLUTION_API_URL=https://ws.portalmercury.com.br/evolution
EVOLUTION_API_KEY=<chave da Evolution API>
EVOLUTION_INSTANCE=mercury-dp
```

### N8N — Workflow de Produção

URLs nos nodes HTTP:
- Mercury Chat API: `https://www.portalmercury.com.br/api/v1/dp-chat/message`
- Criar Ticket: `https://www.portalmercury.com.br/api/v1/personnel-requests`
- Enviar WhatsApp: `http://evolution-api:8080/message/sendText/mercury-dp` (rede Docker interna)

Filtro ajustado: removida verificação de `DELIVERY_ACK` — Evolution API v2.3.7 inclui este status em mensagens recebidas válidas.

---

## 6. Pendências

### Concluído (v2.0)

| # | Tarefa | Status |
|---|---|---|
| ~~1~~ | ~~Teste ponta a ponta com WhatsApp real~~ | ✅ |
| ~~2~~ | ~~Resposta automática WhatsApp~~ | ✅ |
| ~~3~~ | ~~Filtro de grupo no N8N~~ | ✅ |
| ~~4~~ | ~~Edit Controller (corrigir dados da IA)~~ | ✅ |
| ~~5~~ | ~~Responder via WhatsApp pelo modal~~ | ✅ |
| ~~6~~ | ~~Notificação real-time (WebSocket → Kanban)~~ | ✅ |
| ~~7~~ | ~~Menu lateral~~ | ✅ |
| ~~8~~ | ~~Testes unitários (PHPUnit)~~ | ✅ 48 testes |
| ~~9~~ | ~~Exportação CSV/PDF~~ | ✅ |
| ~~10~~ | ~~Dashboard analítico~~ | ✅ Chart.js |
| ~~11~~ | ~~Migrar para VPS em produção~~ | ✅ |
| ~~12~~ | ~~Fluxo conversacional (CPF → identificação → demanda)~~ | ✅ |
| ~~13~~ | ~~Notificação WhatsApp ao colaborador~~ | ✅ |
| ~~14~~ | ~~Scroll automático no modal~~ | ✅ |

### Baixa Prioridade (Pós-MVP)

| # | Tarefa | Estimativa |
|---|---|---|
| 15 | Templates de interação (formulários de férias, ponto, etc.) | 6h |
| 16 | Gestão de funcionários (CRUD + import CSV) | 4h |
| 17 | JWT de longa duração para N8N (90 dias) | 1h |
| 18 | SLA automático com alertas de vencimento | 4h |
| 19 | Fluxo de validação (colaborador aprova resolução) | 4h |

---

## 7. Problemas Conhecidos e Soluções

### Evolution API — Erro 405 (Baileys bloqueado)

**Problema:** WhatsApp bloqueia o protocolo Baileys com HTTP 405.

**Solução aplicada:** 4 patches no Baileys dentro do container. Reaplicar após cada rebuild:
```bash
docker exec evolution-api sh -c "BAILEYS=\$(find /evolution/node_modules -path '*/baileys/lib' -type d | head -1) && sed -i 's/Platform\.WEB/Platform.MACOS/g' \$BAILEYS/Utils/validate-connection.js"
docker exec evolution-api sh -c "BAILEYS=\$(find /evolution/node_modules -path '*/baileys/lib' -type d | head -1) && sed -i 's/passive: true,/passive: false,/g' \$BAILEYS/Utils/validate-connection.js"
docker exec evolution-api sh -c "BAILEYS=\$(find /evolution/node_modules -path '*/baileys/lib' -type d | head -1) && sed -i '/lidDbMigrated: false/d' \$BAILEYS/Utils/validate-connection.js"
docker exec evolution-api sh -c "BAILEYS=\$(find /evolution/node_modules -path '*/baileys/lib' -type d | head -1) && sed -i 's/await noise\.finishInit();/noise.finishInit();/g' \$BAILEYS/Socket/socket.js"
docker restart evolution-api
```

### N8N — Filtro DELIVERY_ACK

**Problema:** Evolution API v2.3.7 inclui `status: DELIVERY_ACK` em mensagens recebidas válidas.

**Solução:** Removida verificação de status no filtro. Presença de texto + `fromMe: false` é suficiente.

### API em produção — Tabela `api_rate_limits` não existia

**Problema:** POST na API retornava 500 (Internal Server Error) com body vazio.

**Solução:** Criada tabela `api_rate_limits` manualmente na hospedagem.

### API em produção — Tabela `adms_dp_chat_sessions` não existia

**Problema:** Fluxo conversacional falhava ao tentar buscar/criar sessão.

**Solução:** Criada tabela manualmente na hospedagem (não havia migration SQL).

### VPS — DNS/Rede de saída bloqueada

**Problema:** VPS não resolve DNS nem faz conexões de saída (porta 53, 443).

**Solução:** Docker instalado via painel do provedor. Containers que precisam de saída (Evolution API → WhatsApp) funcionam via rede Docker interna. N8N chama Mercury via HTTPS público.

### PowerShell — JSON body vazio

**Problema:** `curl` no PowerShell envia body vazio (aspas removidas).

**Solução:** Usar variável separada: `$body = '{"key":"value"}'` + `curl.exe -d $body`

---

## 8. Como Subir o Ambiente

### Local (Desenvolvimento)

```bash
cd C:/wamp64/www/mercury/docker
docker compose up -d
# Aplicar patches Baileys + conectar WhatsApp
# Importar workflow: n8n-workflow-whatsapp-dp.json
# Acessar: http://localhost/mercury/personnel-requests/list
```

### Produção (VPS + Hostinger)

Consultar `docs/DEPLOY_VPS_CENTRAL_DP.md` para guia completo.

Resumo:
1. Copiar `docker/` para `/opt/mercury-dp/` na VPS
2. Configurar `.env.prod`
3. `docker-compose -f docker-compose.prod.yml --env-file .env.prod up -d`
4. Aplicar patches Baileys
5. Conectar WhatsApp via Pairing Code
6. Importar workflow N8N com URLs de produção
7. Atualizar `.env` do Mercury na Hostinger

---

**Elaborado por:** Equipe de Desenvolvimento — Grupo Meia Sola
**Data:** 27 de Março de 2026
**Versão:** 2.0
