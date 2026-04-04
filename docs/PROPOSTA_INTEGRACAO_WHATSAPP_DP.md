# Proposta de Integração: WhatsApp → Solicitações DP

**Projeto:** Mercury - Grupo Meia Sola
**Versão:** 1.0
**Data:** 20 de Março de 2026
**Status:** Aguardando Aprovação

---

## 1. Resumo Executivo

Esta proposta apresenta a implementação de um fluxo automatizado que transforma **mensagens de WhatsApp** em **cards de solicitações** para o **Departamento Pessoal (DP)**, utilizando **Inteligência Artificial** para classificação e extração de dados.

### Objetivo

Permitir que colaboradores enviem solicitações ao DP via WhatsApp (canal já familiar) e que estas sejam automaticamente processadas, categorizadas e registradas no sistema Mercury como cards de acompanhamento — eliminando processos manuais, reduzindo tempo de resposta e garantindo rastreabilidade.

### Benefícios Esperados

| Benefício | Descrição |
|---|---|
| **Agilidade** | Solicitações registradas em segundos, sem formulários manuais |
| **Acessibilidade** | Colaboradores usam WhatsApp, canal que já conhecem |
| **Rastreabilidade** | Toda solicitação vira um card com histórico completo |
| **Redução de erros** | IA extrai dados estruturados, minimizando preenchimento incorreto |
| **Visibilidade** | Equipe DP recebe notificações em tempo real no Mercury |
| **Métricas** | Dashboard com volume, tipos, tempo de atendimento |

---

## 2. Visão Geral da Solução

### 2.1 Fluxo Simplificado

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  Colaborador │     │   WhatsApp   │     │  N8N + IA    │     │   Mercury    │
│              │────>│  (Evolution  │────>│  (Processa   │────>│  (Card DP +  │
│  Envia msg   │     │   API)       │     │   mensagem)  │     │  Notifica)   │
└──────────────┘     └──────────────┘     └──────────────┘     └──────────────┘
```

### 2.2 Fluxo Detalhado

```
1. Colaborador envia mensagem no WhatsApp
   Ex: "Oi, preciso do meu holerite de fevereiro. Sou João da loja Centro."

2. Evolution API (WhatsApp) recebe a mensagem
   └─> Dispara webhook para o N8N

3. N8N recebe o webhook e envia para a IA
   └─> IA (Groq/DeepSeek) extrai dados estruturados:
       {
         "employee_name": "João",
         "request_type": "holerite",
         "description": "Holerite de fevereiro",
         "store_name": "Centro",
         "urgency": "low"
       }

4. N8N envia os dados para a API do Mercury
   └─> POST /api/v1/personnel-requests
       (autenticado via JWT)

5. Mercury cria o card de solicitação
   └─> Registra no banco de dados
   └─> Notifica equipe DP via WebSocket (tempo real)
   └─> Resposta automática no WhatsApp: "Solicitação #1234 registrada!"

6. Equipe DP visualiza, atende e atualiza o card no Mercury
   └─> Colaborador recebe atualização de status via WhatsApp
```

---

## 3. Arquitetura Técnica

### 3.1 Componentes da Solução

```
                              VPS Hostinger (já existente)
                    ┌───────────────────────────────────────┐
                    │                                       │
  WhatsApp ────────>│  Evolution API (porta 8085)           │
  (mensagens)       │    │                                  │
                    │    ↓ webhook                          │
                    │  N8N (porta 5678)                     │
                    │    │                                  │
                    │    ↓ HTTP Request                     │
                    │  Groq API / DeepSeek API ◄────────────│───> Nuvem (IA)
                    │    │                                  │
                    │    ↓ resposta JSON                    │
                    │  N8N envia para Mercury ──────────────│───> Hospedagem
                    │                                       │     Compartilhada
                    │  WebSocket Server (porta 8080) ◄──────│─── Mercury notifica
                    │  (já existente)                       │    equipe DP
                    └───────────────────────────────────────┘
```

### 3.2 Tecnologias Utilizadas

| Componente | Tecnologia | Tipo | Custo |
|---|---|---|---|
| **WhatsApp Gateway** | Evolution API v2 | Open-source, self-hosted | Gratuito |
| **Orquestrador de Fluxos** | N8N | Open-source, self-hosted | Gratuito |
| **Inteligência Artificial** | Groq (Llama 3.1 70B) | API na nuvem | Gratuito (tier free) |
| **IA Alternativa** | DeepSeek V3 API | API na nuvem | ~R$1-5/mês |
| **Backend** | Mercury (PHP 8.0+) | Já existente | — |
| **Banco de Dados** | MySQL | Já existente | — |
| **Notificações** | WebSocket (Ratchet) | Já existente | — |
| **Hospedagem** | Hostinger (compartilhada + VPS) | Já existente | — |

### 3.3 Onde Cada Componente Roda

| Componente | Infraestrutura | Justificativa |
|---|---|---|
| Evolution API | **VPS** | Requer processo persistente (Node.js) |
| N8N | **VPS** | Requer processo persistente + Docker ou Node.js |
| IA (Llama/DeepSeek) | **Nuvem (API externa)** | VPS sem GPU = inviável rodar modelos localmente |
| API Mercury (endpoint) | **Hospedagem compartilhada** | Já hospeda o Mercury |
| Módulo CRUD (cards DP) | **Hospedagem compartilhada** | Parte do sistema Mercury |
| WebSocket | **VPS** | Já roda na VPS atual |

---

## 4. Inteligência Artificial

### 4.1 Função da IA

A IA recebe a mensagem bruta do WhatsApp e extrai dados estruturados em JSON:

**Entrada (mensagem do colaborador):**
> "Bom dia, aqui é a Maria Silva da loja Shopping. Preciso pedir férias para o mês que vem, é urgente porque já marquei viagem."

**Saída (JSON estruturado pela IA):**
```json
{
  "employee_name": "Maria Silva",
  "request_type": "ferias",
  "description": "Solicitação de férias para o próximo mês, viagem já marcada",
  "store_name": "Shopping",
  "urgency": "high",
  "confidence": 0.95
}
```

### 4.2 Tipos de Solicitação Reconhecidos

| Tipo | Palavras-chave detectadas pela IA |
|---|---|
| `ferias` | férias, descanso, folga programada |
| `atestado` | atestado, médico, doença, afastamento |
| `holerite` | holerite, contracheque, comprovante de renda |
| `adiantamento` | adiantamento, vale, antecipação |
| `desligamento` | demissão, desligamento, sair da empresa |
| `declaracao` | declaração, comprovante de vínculo |
| `alteracao_dados` | alteração cadastral, mudança de endereço, conta bancária |
| `duvida` | dúvida, pergunta, informação |
| `outros` | não classificável nos tipos acima |

### 4.3 Modelos de IA Disponíveis

| Modelo | Provedor | Qualidade PT-BR | Latência | Custo |
|---|---|---|---|---|
| **Llama 3.1 70B** | Groq | Excelente | ~200ms | Gratuito (tier free) |
| **Llama 3.1 8B** | Groq | Boa | ~100ms | Gratuito (tier free) |
| **DeepSeek V3** | DeepSeek | Excelente | ~500ms | ~$0.27/1M tokens |
| **Llama 3.1 8B** | Ollama (local) | Boa | ~2-5s (CPU) | Gratuito (não recomendado sem GPU) |

**Recomendação:** Groq + Llama 3.1 70B como principal, DeepSeek V3 como fallback.

### 4.4 Prompt de Extração

```
Você é um assistente de Departamento Pessoal do Grupo Meia Sola.
Analise a mensagem de WhatsApp abaixo e extraia as informações em JSON.

Tipos válidos: ferias, atestado, holerite, adiantamento, desligamento,
declaracao, alteracao_dados, duvida, outros.

Urgência: low (pode esperar), medium (precisa em alguns dias), high (urgente).

Se algum campo não estiver claro na mensagem, use null.
Responda APENAS o JSON, sem explicações.

Campos:
- employee_name (string|null)
- request_type (string)
- description (string)
- store_name (string|null)
- urgency (string)
- confidence (float 0-1)

Mensagem: "{mensagem}"
```

### 4.5 Tratamento de Baixa Confiança

| Confiança | Ação |
|---|---|
| ≥ 0.8 | Card criado automaticamente |
| 0.5 – 0.79 | Card criado com flag "Revisão Necessária" |
| < 0.5 | Card criado como "Não Classificado", equipe DP classifica manualmente |

---

## 5. Módulo Mercury — Solicitações DP

### 5.1 Estrutura de Dados

#### Tabela: `adms_personnel_requests`

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Chave primária |
| `request_number` | VARCHAR(20) | Número único (ex: DP-2026-0001) |
| `employee_name` | VARCHAR(255) | Nome informado na mensagem |
| `employee_id` | INT NULL | FK `adms_employees.id` (vinculado posteriormente) |
| `store_name` | VARCHAR(100) NULL | Loja informada na mensagem |
| `store_id` | VARCHAR(10) NULL | FK `tb_lojas.id` (vinculado posteriormente) |
| `request_type` | ENUM(...) | Tipo da solicitação |
| `urgency` | ENUM('low','medium','high') | Urgência detectada pela IA |
| `status_id` | INT | FK `adms_status_personnel_requests.id` |
| `description` | TEXT | Descrição extraída pela IA |
| `original_message` | TEXT | Mensagem original do WhatsApp |
| `whatsapp_number` | VARCHAR(20) | Número do remetente |
| `whatsapp_message_id` | VARCHAR(100) NULL | ID da mensagem no WhatsApp |
| `ai_confidence` | DECIMAL(3,2) | Confiança da classificação (0.00-1.00) |
| `ai_model` | VARCHAR(50) | Modelo utilizado (ex: llama-3.1-70b) |
| `assigned_to_user_id` | INT NULL | FK `adms_usuarios.id` (responsável DP) |
| `internal_notes` | TEXT NULL | Observações internas da equipe DP |
| `resolution` | TEXT NULL | Resolução/resposta ao colaborador |
| `resolved_at` | DATETIME NULL | Data/hora da resolução |
| `created_at` | DATETIME | Data de criação |
| `updated_at` | DATETIME | Última atualização |
| `created_by_user_id` | INT NULL | Usuário que criou (NULL = via WhatsApp) |
| `updated_by_user_id` | INT NULL | Último usuário que atualizou |

#### Tabela: `adms_status_personnel_requests`

| ID | Nome | Cor | Descrição |
|---|---|---|---|
| 1 | Novo | `#17a2b8` (info) | Recém-criado, aguardando triagem |
| 2 | Em Análise | `#ffc107` (warning) | Equipe DP está analisando |
| 3 | Aguardando Informação | `#6c757d` (secondary) | Falta informação do colaborador |
| 4 | Em Atendimento | `#007bff` (primary) | Sendo resolvido |
| 5 | Resolvido | `#28a745` (success) | Concluído |
| 6 | Cancelado | `#dc3545` (danger) | Cancelado |

#### Tabela: `adms_personnel_request_messages`

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Chave primária |
| `request_id` | INT | FK `adms_personnel_requests.id` |
| `direction` | ENUM('incoming','outgoing') | Recebida ou enviada |
| `message` | TEXT | Conteúdo da mensagem |
| `whatsapp_message_id` | VARCHAR(100) NULL | ID no WhatsApp |
| `sent_by_user_id` | INT NULL | Quem enviou (NULL = colaborador) |
| `created_at` | DATETIME | Data/hora |

### 5.2 Funcionalidades do Módulo

#### Listagem (Tela Principal)

- **Cards de estatísticas:** Total, Novos, Em Análise, Em Atendimento, Resolvidos (hoje/semana/mês)
- **Filtros:** Status, Tipo, Urgência, Loja, Período, Responsável
- **Tabela:** Número, Colaborador, Tipo, Loja, Urgência, Status, Responsável, Data
- **Indicadores visuais:** Badge de urgência, ícone de baixa confiança IA, badge "WhatsApp"

#### Card de Solicitação (Visualização)

- Dados extraídos pela IA
- Mensagem original do WhatsApp
- Histórico de mensagens (conversa)
- Histórico de status
- Campo para resposta (envia via WhatsApp)
- Notas internas
- Atribuição de responsável

#### Ações Disponíveis

| Ação | Descrição |
|---|---|
| **Visualizar** | Ver detalhes completos do card |
| **Atribuir** | Designar responsável da equipe DP |
| **Alterar Status** | Mover entre os status |
| **Responder** | Enviar resposta via WhatsApp |
| **Editar** | Corrigir dados extraídos pela IA |
| **Vincular Funcionário** | Associar ao cadastro `adms_employees` |
| **Exportar** | Relatório CSV/PDF |

### 5.3 Notificações

| Evento | Quem recebe | Canal |
|---|---|---|
| Nova solicitação | Equipe DP | WebSocket (Mercury) |
| Solicitação urgente | Gestor DP | WebSocket + destaque visual |
| Status alterado | Colaborador | WhatsApp (automático) |
| Resposta da equipe | Colaborador | WhatsApp (automático) |
| Nova mensagem do colaborador | Responsável atribuído | WebSocket (Mercury) |

---

## 6. Evolution API — WhatsApp

### 6.1 O que é

Evolution API é uma solução **open-source** e **gratuita** que permite integrar WhatsApp via API REST. Funciona como uma ponte entre o WhatsApp e qualquer sistema.

### 6.2 Características

| Recurso | Detalhe |
|---|---|
| Licença | Open-source (Apache 2.0) |
| Custo | Gratuito |
| Hospedagem | Self-hosted (VPS) |
| Conexão | Via QR Code (WhatsApp Web) |
| API | REST completa (enviar, receber, grupos, mídia) |
| Webhooks | Eventos configuráveis por instância |
| Multi-device | Suporte nativo |

### 6.3 Requisitos

- Node.js 18+ ou Docker
- ~512MB RAM
- Número de telefone dedicado (chip exclusivo para o DP)
- Conexão com internet estável

### 6.4 Considerações Importantes

> **Nota:** A Evolution API utiliza o protocolo WhatsApp Web (não oficial).
> Para uso empresarial em larga escala, a alternativa oficial é a
> **WhatsApp Business API (Meta Cloud API)**, que possui custo por conversa
> mas oferece garantias de estabilidade e compliance.

| Aspecto | Evolution API | Meta Cloud API |
|---|---|---|
| Custo | Gratuito | ~R$0,25-0,80 por conversa |
| Estabilidade | Boa (pode ter desconexões) | Alta (SLA Meta) |
| Aprovação Meta | Não requer | Requer verificação business |
| Setup | Simples (QR Code) | Burocrático (1-4 semanas) |
| Volume | Moderado | Alto |
| **Recomendação** | MVP / Piloto | Produção definitiva |

**Sugestão:** Iniciar com Evolution API para validar o fluxo, migrar para Meta Cloud API após aprovação e validação do projeto.

---

## 7. N8N — Orquestrador de Fluxos

### 7.1 O que é

N8N é uma plataforma de automação de workflows **open-source**, similar ao Zapier/Make, porém **self-hosted e gratuita**.

### 7.2 Fluxo Visual no N8N

```
┌─────────────┐    ┌─────────────┐    ┌──────────────┐    ┌─────────────┐
│  Webhook     │───>│  Validação   │───>│  IA (Groq)   │───>│  Mercury    │
│  (Evolution) │    │  (mensagem)  │    │  Extrai JSON │    │  API POST   │
└─────────────┘    └─────────────┘    └──────────────┘    └─────────────┘
                                                                │
                                           ┌────────────────────┘
                                           ↓
                                    ┌─────────────┐    ┌─────────────┐
                                    │  Resposta    │───>│  Evolution   │
                                    │  automática  │    │  (WhatsApp)  │
                                    └─────────────┘    └─────────────┘
```

### 7.3 Nodes Utilizados

1. **Webhook Trigger** — Recebe evento da Evolution API
2. **IF** — Valida se é mensagem de texto (ignora mídia, status, etc.)
3. **HTTP Request (Groq)** — Envia mensagem para IA processar
4. **Code** — Trata resposta JSON da IA
5. **HTTP Request (Mercury)** — POST para API do Mercury com JWT
6. **HTTP Request (Evolution)** — Envia confirmação no WhatsApp

### 7.4 Requisitos

- Node.js 18+ ou Docker
- ~512MB RAM
- Porta 5678 (interface web de administração)
- Porta 5679 (webhooks)

---

## 8. Infraestrutura e Requisitos

### 8.1 Requisitos da VPS

| Recurso | Atual (WebSocket) | Necessário (com integração) | Observação |
|---|---|---|---|
| **RAM** | ~512MB | **2GB mínimo / 4GB recomendado** | N8N + Evolution + WebSocket |
| **CPU** | 1 vCPU | **2 vCPU recomendado** | Processamento de webhooks |
| **Disco** | ~5GB | **20GB+ recomendado** | Logs, sessão WhatsApp, backups N8N |
| **Docker** | Não utilizado | **Recomendado** | Simplifica instalação e atualização |
| **Portas** | 8080, 8081 | **+ 5678, 5679, 8085** | N8N + Evolution |
| **SO** | Linux | Linux (mesmo) | — |

### 8.2 Verificar no Plano Atual

- [ ] Plano VPS permite Docker? (KVM = sim, OpenVZ = não)
- [ ] RAM disponível é suficiente? (mínimo 2GB)
- [ ] Há portas disponíveis para os novos serviços?
- [ ] Largura de banda suporta webhooks frequentes?

### 8.3 Possível Upgrade de Plano

Se a VPS atual for insuficiente:

| Plano Hostinger VPS | RAM | vCPU | Preço aprox. |
|---|---|---|---|
| KVM 1 | 4GB | 2 | ~R$30-50/mês |
| KVM 2 | 8GB | 4 | ~R$50-80/mês |

---

## 9. Segurança

### 9.1 Autenticação e Autorização

| Ponto | Proteção |
|---|---|
| N8N → Mercury API | JWT com credenciais de serviço (service account) |
| Evolution API | API Key própria + IP whitelist (apenas VPS) |
| N8N interface web | Senha + acesso restrito por IP ou VPN |
| WhatsApp → Evolution | Criptografia E2E nativa do WhatsApp |
| Dados no banco | Prepared statements (padrão Mercury) |

### 9.2 Dados Sensíveis

| Dado | Tratamento |
|---|---|
| Número WhatsApp | Armazenado com máscara na interface |
| Mensagens originais | Armazenadas para auditoria, acesso restrito à equipe DP |
| Dados pessoais (LGPD) | Apenas nome e loja — não solicitar CPF ou dados bancários via WhatsApp |
| Credenciais de API | Armazenadas em variáveis de ambiente (.env), nunca no código |

### 9.3 LGPD — Considerações

- Mensagens de WhatsApp contêm dados pessoais
- Necessário: termo de consentimento ou aviso no primeiro contato
- Sugestão: mensagem automática de boas-vindas informando sobre coleta e tratamento
- Retenção: definir prazo de armazenamento das mensagens (ex: 12 meses)
- Exclusão: mecanismo para atender solicitações de exclusão de dados

---

## 10. Cronograma de Implementação

### Fase 1 — Infraestrutura (Semana 1-2)

| Tarefa | Responsável | Prioridade |
|---|---|---|
| Verificar/atualizar plano VPS | Infra/TI | Alta |
| Instalar Docker na VPS | Infra/TI | Alta |
| Instalar e configurar Evolution API | Desenvolvedor | Alta |
| Instalar e configurar N8N | Desenvolvedor | Alta |
| Conectar número WhatsApp (QR Code) | DP + Desenvolvedor | Alta |
| Configurar API key da IA (Groq) | Desenvolvedor | Alta |

### Fase 2 — Módulo Mercury (Semana 2-3)

| Tarefa | Responsável | Prioridade |
|---|---|---|
| Criar tabelas no banco de dados | Desenvolvedor | Alta |
| Criar endpoint API (receber solicitações) | Desenvolvedor | Alta |
| Criar módulo CRUD (listagem, visualização, edição) | Desenvolvedor | Alta |
| Integrar notificações WebSocket | Desenvolvedor | Média |
| Criar cards de estatísticas e filtros | Desenvolvedor | Média |

### Fase 3 — Fluxo N8N (Semana 3-4)

| Tarefa | Responsável | Prioridade |
|---|---|---|
| Criar fluxo webhook → IA → Mercury | Desenvolvedor | Alta |
| Configurar resposta automática WhatsApp | Desenvolvedor | Alta |
| Configurar fluxo de atualização de status → WhatsApp | Desenvolvedor | Média |
| Testar e ajustar prompt da IA | Desenvolvedor + DP | Alta |

### Fase 4 — Testes e Validação (Semana 4-5)

| Tarefa | Responsável | Prioridade |
|---|---|---|
| Testes internos (equipe DP como piloto) | DP + Desenvolvedor | Alta |
| Ajustar classificações da IA | Desenvolvedor | Média |
| Testar notificações e respostas automáticas | Desenvolvedor | Alta |
| Documentar procedimentos para equipe DP | DP | Média |

### Fase 5 — Go-live (Semana 5-6)

| Tarefa | Responsável | Prioridade |
|---|---|---|
| Divulgar número WhatsApp para colaboradores | DP / Comunicação | Alta |
| Monitorar volume e ajustar | Desenvolvedor | Alta |
| Coletar feedback da equipe DP | DP | Média |
| Ajustes finais | Desenvolvedor | Média |

**Prazo total estimado: 5 a 6 semanas**

---

## 11. Custos

### 11.1 Custos de Implementação (Únicos)

| Item | Custo |
|---|---|
| Evolution API | Gratuito (open-source) |
| N8N | Gratuito (open-source) |
| Desenvolvimento do módulo Mercury | Equipe interna |
| Chip/número WhatsApp dedicado | ~R$15-30 (compra única) |
| **Total implementação** | **~R$15-30** |

### 11.2 Custos Recorrentes (Mensais)

| Item | Custo mensal |
|---|---|
| Hospedagem compartilhada | Já existente (sem custo adicional) |
| VPS (pode necessitar upgrade) | R$0 a R$50 (dependendo do plano atual) |
| IA — Groq API (Llama 3.1) | Gratuito (tier free: 30 req/min) |
| IA — DeepSeek API (backup) | ~R$1-5 |
| Plano de dados do chip | ~R$20-30 (plano básico) |
| **Total mensal** | **~R$20 a R$85** |

### 11.3 Comparativo com Alternativas

| Solução | Custo mensal | Observação |
|---|---|---|
| **Proposta atual (N8N + Evolution)** | **R$20-85** | Self-hosted, controle total |
| Pipefy (plano business) | R$500-2.000+ | SaaS, dados fora do Mercury |
| Zendesk + WhatsApp | R$300-1.500+ | SaaS, complexo de integrar |
| Solução 100% customizada | R$0 (após dev) | Alto custo de desenvolvimento e manutenção |

---

## 12. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Evolution API desconectar | Média | Médio | Monitoramento + reconexão automática; migrar para Meta Cloud API se recorrente |
| IA classificar incorretamente | Baixa | Baixo | Flag de "Revisão Necessária" para confiança < 80%; ajuste contínuo do prompt |
| VPS sem recursos suficientes | Baixa | Alto | Verificar antes da implementação; upgrade de plano se necessário |
| Alto volume de mensagens | Baixa | Médio | Groq free tier suporta 30 req/min (~43.000/dia); DeepSeek como fallback |
| Bloqueio do número WhatsApp | Baixa | Alto | Usar número dedicado (não pessoal); seguir políticas do WhatsApp; migrar para API oficial se necessário |
| Colaboradores enviarem dados sensíveis | Média | Alto | Mensagem automática orientando a NÃO enviar CPF, dados bancários; LGPD compliance |

---

## 13. Métricas de Sucesso

| Métrica | Meta | Como medir |
|---|---|---|
| Taxa de classificação correta da IA | ≥ 85% | Cards editados manualmente / total |
| Tempo médio de resposta (primeiro contato) | ≤ 2 horas | Timestamp criação → primeiro status "Em Análise" |
| Tempo médio de resolução | ≤ 48 horas | Timestamp criação → status "Resolvido" |
| Satisfação dos colaboradores | ≥ 4/5 | Pesquisa após resolução |
| Volume de solicitações processadas | Crescimento mensal | Dashboard Mercury |
| Disponibilidade do sistema | ≥ 99% | Monitoramento VPS |

---

## 14. Evolução Futura

Funcionalidades que podem ser implementadas após a versão inicial:

| Fase | Funcionalidade | Descrição |
|---|---|---|
| v1.1 | **Chatbot interativo** | IA faz perguntas de follow-up antes de criar o card |
| v1.2 | **Auto-vinculação de funcionário** | IA identifica colaborador pelo número de WhatsApp cadastrado |
| v1.3 | **Respostas automáticas simples** | Holerite, declarações — gerados e enviados sem intervenção humana |
| v2.0 | **Migração para Meta Cloud API** | API oficial do WhatsApp para maior estabilidade |
| v2.1 | **Atendimento multicanal** | Incluir Telegram, e-mail, formulário web |
| v2.2 | **Dashboard analítico avançado** | BI com tendências, previsão de demanda, SLA |
| v3.0 | **Auto-atendimento com IA** | IA resolve solicitações simples automaticamente |

---

## 15. Análise Comparativa: Ferramentas Externas vs. Desenvolvimento Interno

Esta seção apresenta a análise de viabilidade caso a opção seja desenvolver toda a integração internamente, sem utilizar N8N e Evolution API.

### 15.1 Nível de Complexidade

| Abordagem | Complexidade | Justificativa |
|---|---|---|
| **Com N8N + Evolution** | **Média** | Ferramentas prontas para orquestração e WhatsApp; foco apenas no módulo Mercury |
| **100% Interno** | **Alta** | Necessário desenvolver gateway WhatsApp, fila de mensagens, worker, cliente IA, monitoramento |

### 15.2 Arquitetura do Desenvolvimento Interno

Sem ferramentas externas, o Mercury precisaria absorver toda a responsabilidade de orquestração:

```
         100% INTERNO — Tudo no Mercury + VPS

  Meta Cloud API ◄──────────────────────────────────────────┐
       │                                                     │
       ↓ webhook                                             │ resposta
  ┌──────────────────────── VPS ──────────────────────┐      │
  │                                                    │     │
  │  WhatsApp Webhook Receiver (PHP)                   │     │
  │    └─> Valida assinatura Meta (X-Hub-Signature)    │     │
  │    └─> Responde 200 em < 5 segundos                │     │
  │    └─> Enfileira mensagem no MySQL/Redis           │     │
  │                                                    │     │
  │  Queue Worker (PHP daemon)                         │     │
  │    └─> Consome fila de mensagens                   │     │
  │    └─> Chama API da IA (Groq/DeepSeek via cURL)    │     │
  │    └─> Processa resposta JSON                      │     │
  │    └─> Insere card no banco Mercury                │     │
  │    └─> Notifica equipe DP via WebSocket            │     │
  │    └─> Envia confirmação via Meta Cloud API ───────│─────┘
  │                                                    │
  │  WebSocket Server (já existente)                   │
  └────────────────────────────────────────────────────┘
```

**Diferença fundamental:** Sem a Evolution API, a única forma estável e oficial de conectar ao WhatsApp por código próprio é a **Meta Cloud API (WhatsApp Business API)**, que exige aprovação comercial, templates pré-aprovados e cobra por conversa.

### 15.3 Componentes a Desenvolver

No desenvolvimento interno, além do módulo CRUD (que é igual em ambas abordagens), seria necessário criar **4 camadas adicionais**:

#### Camada 1 — WhatsApp Gateway Service

```
app/adms/Services/WhatsApp/
├── WhatsAppService.php             # Enviar/receber mensagens via Meta Cloud API
├── WhatsAppWebhookHandler.php      # Processar e validar webhooks da Meta
├── WhatsAppMessageQueue.php        # Fila de processamento de mensagens
└── WhatsAppTemplateService.php     # Gerenciar templates (exigência Meta)
```

| Desafio | Detalhe |
|---|---|
| Aprovação Meta Business | Processo burocrático de 1-4 semanas |
| Templates pré-aprovados | Toda mensagem proativa precisa de template aprovado pela Meta |
| Validação de assinatura | Verificar X-Hub-Signature-256 em cada webhook |
| Resposta em < 5 segundos | Meta reenvia webhook se não receber 200 a tempo |
| Token de acesso | Renovação periódica obrigatória |

#### Camada 2 — AI Processing Service

```
app/adms/Services/AI/
├── AIClassifierService.php         # Chamada à API da IA (Groq/DeepSeek)
├── AIPromptBuilder.php             # Construção e versionamento de prompts
└── AIResponseParser.php            # Parse, validação e fallback do JSON
```

| Desafio | Detalhe |
|---|---|
| Timeout handling | APIs de IA podem demorar 1-10 segundos |
| Fallback entre provedores | Se Groq falhar, chamar DeepSeek automaticamente |
| Retry com backoff | Exponencial: 1s, 2s, 4s, 8s... |
| Validação rigorosa do JSON | IA pode retornar formato inesperado |
| Rate limiting | Respeitar limites de cada provedor |

#### Camada 3 — Message Queue e Worker

```
app/adms/Services/Queue/
├── MessageQueueService.php         # Enfileirar/desenfileirar mensagens
└── QueueWorkerService.php          # Processar fila (lógica de negócio)

bin/
└── whatsapp-worker.php             # Daemon PHP (roda na VPS via systemd)

database/migrations/
└── create_message_queue_table.sql  # Tabela de fila
```

| Desafio | Detalhe |
|---|---|
| PHP long-running process | Risco de memory leaks; necessário restart periódico |
| Supervisor/systemd | Configurar para manter o worker sempre ativo |
| Concorrência | Evitar processar a mesma mensagem duas vezes |
| Dead letter queue | Mensagens que falharam N vezes vão para fila de erro |
| Monitoramento | Alertas se o worker parar ou a fila crescer |

**Tabela de fila (MySQL):**

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | INT AUTO_INCREMENT | PK |
| `payload` | JSON | Dados do webhook |
| `status` | ENUM('pending','processing','completed','failed') | Estado |
| `attempts` | INT DEFAULT 0 | Tentativas de processamento |
| `max_attempts` | INT DEFAULT 3 | Máximo de tentativas |
| `error_message` | TEXT NULL | Último erro |
| `locked_at` | DATETIME NULL | Lock para concorrência |
| `processed_at` | DATETIME NULL | Quando foi processado |
| `created_at` | DATETIME | Criação |

#### Camada 4 — Webhook Controller

```
app/adms/Controllers/Api/V1/
└── WhatsAppWebhookController.php   # Endpoint público para webhooks Meta
```

| Desafio | Detalhe |
|---|---|
| Challenge verification | Meta envia GET com `hub.verify_token` na configuração |
| Assinatura HMAC-SHA256 | Validar cada POST com app secret |
| Tipos de evento | Filtrar: message, status, read_receipt, errors |
| Idempotência | Mesmo webhook pode chegar mais de uma vez |

### 15.4 Comparativo Detalhado

#### Volume de Trabalho

| Aspecto | Com N8N + Evolution | 100% Interno |
|---|---|---|
| **Arquivos novos** | ~15-20 (módulo CRUD) | **~30-40** (CRUD + services + worker + gateway) |
| **Linhas de código estimadas** | ~2.000-3.000 | **~5.000-8.000** |
| **Tabelas no banco** | 3 (cards + status + mensagens) | **4** (+ fila de mensagens) |
| **Configurações externas** | N8N (visual) + Evolution (env) | Meta Business Manager + systemd + cron |
| **Testes unitários** | ~50-80 | **~120-180** |

#### Prazo e Cronograma

| Fase | Com N8N + Evolution | 100% Interno |
|---|---|---|
| Infraestrutura | 1-2 semanas | **3-5 semanas** (inclui aprovação Meta) |
| Módulo Mercury CRUD | 1-2 semanas | 1-2 semanas (igual) |
| Gateway WhatsApp | Não aplicável (Evolution) | **2-3 semanas** |
| Cliente IA + Queue | Não aplicável (N8N) | **2-3 semanas** |
| Fluxo e orquestração | 1 semana (N8N visual) | **1-2 semanas** (código PHP) |
| Testes e validação | 1 semana | **1-2 semanas** |
| **Total** | **5-6 semanas** | **10-14 semanas** |

#### Custos Mensais

| Item | Com N8N + Evolution | 100% Interno |
|---|---|---|
| VPS (upgrade se necessário) | R$0-50 | R$0-50 |
| IA (Groq/DeepSeek) | R$0-5 | R$0-5 |
| WhatsApp | Grátis (Evolution) | **R$50-200** (Meta Cloud API por conversa) |
| N8N | Grátis (self-hosted) | R$0 (não usa) |
| Chip/plano de dados | R$20-30 | R$20-30 |
| **Total mensal** | **R$20-85** | **R$70-285** |

#### Manutenção e Operação

| Aspecto | Com N8N + Evolution | 100% Interno |
|---|---|---|
| Atualizar WhatsApp gateway | `docker pull` (Evolution) | Acompanhar changelog Meta Cloud API |
| Alterar fluxo de processamento | Arrastar nodes no N8N (visual) | Alterar código PHP + deploy |
| Monitorar filas | N8N dashboard nativo | Criar dashboard próprio ou usar logs |
| Retry automático | Nativo no N8N | Implementado manualmente (queue worker) |
| Fallback de IA | Node de switch no N8N | Implementado manualmente (try/catch) |
| Adicionar novo tipo de solicitação | Editar prompt no N8N | Editar prompt no código + deploy |
| Tempo de manutenção mensal | ~2-4 horas | **~8-16 horas** |

### 15.5 Vantagens do Desenvolvimento Interno

Apesar da maior complexidade, existem cenários onde o desenvolvimento interno pode ser justificável:

| Vantagem | Quando faz sentido |
|---|---|
| **Controle total do código** | Requisitos de compliance/auditoria muito rígidos |
| **Sem dependência de terceiros** | Política corporativa contra ferramentas externas |
| **Customização profunda** | Fluxos muito complexos que ultrapassam capacidade do N8N |
| **Performance** | Volume extremamente alto (>10.000 mensagens/dia) |
| **Propriedade intelectual** | Todo código pertence à empresa |

### 15.6 Desvantagens do Desenvolvimento Interno

| Desvantagem | Impacto |
|---|---|
| **Prazo dobrado** | 10-14 semanas vs. 5-6 semanas |
| **Custo mensal 2-3x maior** | Meta Cloud API cobra por conversa |
| **Burocracia Meta** | Aprovação de business account + templates |
| **Manutenção contínua** | Worker PHP, queue, gateway — tudo sob responsabilidade interna |
| **PHP não é ideal para workers** | Memory leaks em processos long-running |
| **Risco técnico maior** | Mais código = mais pontos de falha |
| **Menor agilidade** | Qualquer mudança no fluxo requer deploy |

### 15.7 Recomendação Final

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│   RECOMENDAÇÃO: N8N + Evolution API (Abordagem com ferramentas) │
│                                                                 │
│   • Metade do prazo (5-6 semanas vs. 10-14)                    │
│   • 1/3 do custo mensal (R$20-85 vs. R$70-285)                 │
│   • Menor risco técnico                                         │
│   • Maior agilidade para ajustes                                │
│   • Mesmo resultado funcional para o usuário final              │
│                                                                 │
│   Estratégia: Iniciar com ferramentas externas (MVP),           │
│   internalizar componentes específicos apenas se surgir         │
│   necessidade concreta no futuro.                               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 16. Conclusão

A integração WhatsApp → Mercury para solicitações ao Departamento Pessoal é **tecnicamente viável** com a infraestrutura atual (hospedagem compartilhada + VPS Hostinger), podendo ser implementada por duas abordagens:

| | Com N8N + Evolution | 100% Interno |
|---|---|---|
| **Complexidade** | Média | Alta |
| **Prazo** | 5-6 semanas | 10-14 semanas |
| **Custo mensal** | R$20-85 | R$70-285 |
| **Manutenção** | ~2-4h/mês | ~8-16h/mês |
| **Resultado funcional** | Idêntico | Idêntico |

A **abordagem recomendada** (N8N + Evolution API) utiliza ferramentas **open-source e gratuitas**, aproveita recursos já existentes no Mercury (API REST, WebSocket, notificações) e entrega o mesmo resultado com metade do prazo e um terço do custo.

A alternativa de desenvolvimento 100% interno é viável, porém justificável apenas em cenários com requisitos rígidos de compliance ou política corporativa contra ferramentas externas.

**Estratégia sugerida:** iniciar com ferramentas externas para validação rápida do conceito, e internalizar componentes específicos apenas se surgir necessidade concreta após o projeto em operação.

### Aprovações Necessárias

| Item | Aprovador | Status |
|---|---|---|
| Abordagem técnica (ferramentas externas vs. interno) | Gestão / TI | ⬜ Pendente |
| Orçamento (upgrade VPS se necessário) | Gestão / TI | ⬜ Pendente |
| Número WhatsApp dedicado para DP | DP / Gestão | ⬜ Pendente |
| Uso de IA para classificação | Gestão / Jurídico (LGPD) | ⬜ Pendente |
| Prazo de implementação | Gestão / TI | ⬜ Pendente |
| Início do desenvolvimento | Gestão | ⬜ Pendente |

---

**Elaborado por:** Equipe de Desenvolvimento — Grupo Meia Sola
**Data:** 20 de Março de 2026
**Próxima revisão:** Após aprovação
