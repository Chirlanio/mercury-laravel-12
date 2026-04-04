# Central DP — MVP: Módulo de Atendimento ao Departamento Pessoal

**Status:** Proposta / Em Análise
**Versão:** 1.0
**Data:** 27/03/2026
**Última Atualização:** 27/03/2026
**Projeto:** Mercury — Grupo Meia Sola
**Protótipo:** `docs/examples/preview_v6.html`
**Módulo de Referência:** Helpdesk (arquitetura), Sales (padrões modernos)

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Problema e Justificativa](#2-problema-e-justificativa)
3. [Escopo do MVP](#3-escopo-do-mvp)
4. [Arquitetura de Dados](#4-arquitetura-de-dados)
5. [Estrutura de Diretórios](#5-estrutura-de-diretórios)
6. [Fluxo do Atendimento](#6-fluxo-do-atendimento)
7. [Implementação — Fase 1: Fundação](#7-implementação--fase-1-fundação)
8. [Implementação — Fase 2: Chat e Criação de Tickets](#8-implementação--fase-2-chat-e-criação-de-tickets)
9. [Implementação — Fase 3: Kanban e Gestão](#9-implementação--fase-3-kanban-e-gestão)
10. [Implementação — Fase 4: Interações e Templates](#10-implementação--fase-4-interações-e-templates)
11. [Implementação — Fase 5: Dashboard e Relatórios](#11-implementação--fase-5-dashboard-e-relatórios)
12. [Implementação — Fase 6: Funcionários e Integrações](#12-implementação--fase-6-funcionários-e-integrações)
13. [Validações e Regras de Negócio](#13-validações-e-regras-de-negócio)
14. [Permissões e Controle de Acesso](#14-permissões-e-controle-de-acesso)
15. [Notificações](#15-notificações)
16. [Testes](#16-testes)
17. [Análise de Complexidade](#17-análise-de-complexidade)
18. [Roadmap Pós-MVP](#18-roadmap-pós-mvp)

---

## 1. Visão Geral

A **Central DP** é um módulo de atendimento do Departamento Pessoal que centraliza solicitações dos colaboradores (férias, contracheques, benefícios, ponto, atestados, documentação) em um fluxo único com:

- **Chat conversacional** — O colaborador informa CPF, descreve a demanda e responde 2 perguntas de triagem. Um ticket é criado automaticamente.
- **Kanban de gestão** — Operadores do DP visualizam, atribuem e movem tickets entre colunas (Novo → Em Análise → Em Validação → Finalizado) com drag-and-drop.
- **Sistema de interações** — Operadores enviam mensagens, solicitam documentos ou formulários estruturados (férias, benefícios, ponto) diretamente pelo chat.
- **Dashboard analítico** — KPIs e gráficos de motivos, unidades, cargos e distribuição por coluna.
- **Base de funcionários** — Cadastro, busca e importação CSV/Excel para validação de CPF no chat.

### Relação com Módulos Existentes

| Aspecto | Central DP | Helpdesk |
|---------|-----------|----------|
| **Público-alvo** | Colaboradores → Equipe DP | Usuários → Equipe TI/Suporte |
| **Entrada** | Chat conversacional (CPF + triagem) | Formulário modal |
| **Gestão** | Kanban visual + drag-and-drop | Tabela com filtros |
| **Interação** | Templates estruturados (formulários inline) | Comentários livres |
| **Validação** | Aprovação do colaborador no chat | Status interno |

A Central DP **não substitui** o Helpdesk — são módulos complementares. A arquitetura do Helpdesk (tabelas, services, patterns) serve como **base técnica**, mas a UX e o fluxo são distintos.

---

## 2. Problema e Justificativa

### Situação Atual

- Solicitações de DP chegam por **múltiplos canais** (WhatsApp, email, presencial, chat interno)
- **Sem rastreabilidade**: não há protocolo, SLA ou histórico centralizado
- **Sem métricas**: impossível medir tempo de atendimento, volume por categoria ou unidade
- Documentos e formulários trafegam em **formatos inconsistentes**
- Retrabalho por **falta de triagem**: informações incompletas exigem múltiplos contatos

### Solução Proposta

- Canal único via chat com **triagem automática** (categoria, prioridade, perguntas direcionadas)
- Protocolo numérico sequencial (`DP-XXXX`) com **rastreabilidade completa**
- Formulários estruturados por tipo de demanda (campos específicos de férias, ponto, etc.)
- Dashboard com **métricas em tempo real** para gestão do DP
- Integração com a base de colaboradores existente (`adms_employees` / `adms_usuarios`)

---

## 3. Escopo do MVP

### Incluído no MVP

| Funcionalidade | Prioridade | Fase |
|---------------|-----------|------|
| Tabelas e lookups (categorias, status, prioridades) | Crítica | 1 |
| Chat conversacional com triagem automática | Crítica | 2 |
| Criação automática de ticket via chat | Crítica | 2 |
| Kanban com 4 colunas e drag-and-drop | Crítica | 3 |
| Atribuição de operador | Crítica | 3 |
| Envio para validação + aprovação no chat | Alta | 3 |
| Sistema de interações com 5 templates | Alta | 4 |
| Formulários inline no chat (férias, benefício, ponto) | Alta | 4 |
| Solicitação de documentos pelo operador | Alta | 4 |
| Dashboard com 4 KPIs e 4 gráficos | Média | 5 |
| Notificações WebSocket em tempo real | Média | 6 |
| Notificações por email | Média | 6 |
| **Avaliação de atendimento via WhatsApp (1-5)** | **Alta** | **Implementado** |
| **SLA configurável com alertas (cron)** | **Alta** | **Implementado** |
| **Templates de interação estruturados (4 formulários)** | **Alta** | **Implementado** |
| **Fluxo de validação (sim/não via WhatsApp)** | **Média** | **Implementado** |
| **Dashboard: satisfação geral + por atendente** | **Média** | **Implementado** |

> **Nota:** "Gestão de funcionários (CRUD + import CSV)" foi removida deste módulo pois já existe módulo dedicado (`Employees`).

### Excluído do MVP (Roadmap Futuro)

- Integração com WhatsApp Business API
- Relatórios PDF exportáveis (DomPDF)
- IA para sugestão de resposta
- Integração com folha de pagamento (Cigam ERP)
- App mobile nativo
- Pesquisa de satisfação (NPS)

---

## 4. Arquitetura de Dados

### Diagrama de Relacionamentos

```
dp_categories ──┐
                 ├──→ dp_tickets ──→ dp_interactions
dp_priorities ──┘        │              │
                         │              └──→ dp_attachments
dp_statuses ─────────────┘
                         │
dp_operators ────────────┘
                         │
adms_usuarios ───────────┘ (requester_id, assigned_operator_id)
                         │
dp_interaction_templates ─┘ (template_id em dp_interactions)
```

### Tabelas

#### `dp_categories` — Categorias de Demanda

```sql
CREATE TABLE dp_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(30) DEFAULT NULL COMMENT 'Classe Font Awesome ou emoji',
    questions JSON DEFAULT NULL COMMENT '["Pergunta 1?","Pergunta 2?"]',
    detection_keywords JSON DEFAULT NULL COMMENT '["férias","descanso","recesso"]',
    sort_order TINYINT UNSIGNED DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO dp_categories (name, slug, icon, questions, detection_keywords, sort_order) VALUES
('Férias', 'ferias', '🏖️', '["Para qual período deseja agendar?","Prefere 30 dias ou dividir?"]', '["férias","ferias","descanso","recesso"]', 1),
('Contracheque / Salário', 'salario', '💰', '["Qual mês de referência?","Notou diferença no valor?"]', '["contracheque","holerite","salário","salario","pagamento","folha"]', 2),
('Benefícios', 'beneficio', '🎁', '["Qual benefício? (VR, VA, Saúde, Dental, VT)","Inclusão, alteração ou problema?"]', '["vale","benefício","beneficio","plano","saúde","saude","dental"]', 3),
('Ponto / Jornada', 'ponto', '⏰', '["Qual data/horário precisa correção?","Tem comprovante?"]', '["ponto","jornada","hora extra","banco de horas","atraso","falta"]', 4),
('Atestado Médico', 'atestado', '🏥', '["Período de afastamento?","Enviar digital ou presencial?"]', '["atestado","médico","medico","doença","doenca","afastamento"]', 5),
('Documentação', 'documento', '📄', '["Qual documento?","Para qual finalidade?"]', '["documento","certidão","certidao","declaração","declaracao","comprovante","informe"]', 6),
('Outros', 'outros', '📌', '["Pode detalhar mais?","Existe urgência?"]', '[]', 99);
```

#### `dp_statuses` — Status do Ticket

```sql
CREATE TABLE dp_statuses (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(20) NOT NULL UNIQUE,
    badge_class VARCHAR(30) DEFAULT 'badge-secondary',
    kanban_column VARCHAR(20) DEFAULT NULL COMMENT 'novo|analise|validacao|final',
    sort_order TINYINT UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO dp_statuses (name, slug, badge_class, kanban_column, sort_order) VALUES
('Novo', 'novo', 'badge-primary', 'novo', 1),
('Em Análise', 'analise', 'badge-warning', 'analise', 2),
('Aguardando Validação', 'validacao', 'badge-info', 'validacao', 3),
('Validado pelo Colaborador', 'validado', 'badge-success', 'validacao', 4),
('Finalizado', 'finalizado', 'badge-success', 'final', 5),
('Cancelado', 'cancelado', 'badge-danger', NULL, 6);
```

#### `dp_priorities` — Prioridades

```sql
CREATE TABLE dp_priorities (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL,
    slug VARCHAR(20) NOT NULL UNIQUE,
    badge_class VARCHAR(30) DEFAULT 'badge-secondary',
    sla_hours INT UNSIGNED DEFAULT 48,
    detection_keywords JSON DEFAULT NULL COMMENT '["urgente","imediato"]',
    sort_order TINYINT UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO dp_priorities (name, slug, badge_class, sla_hours, detection_keywords, sort_order) VALUES
('Normal', 'normal', 'badge-primary', 48, '[]', 1),
('Alta', 'alta', 'badge-warning', 24, '["rápido","logo","breve"]', 2),
('Urgente', 'urgente', 'badge-danger', 8, '["urgente","imediato","hoje","agora"]', 3);
```

#### `dp_tickets` — Tickets (tabela principal)

```sql
CREATE TABLE dp_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(10) NOT NULL UNIQUE COMMENT 'DP-XXXX sequencial',
    requester_id INT UNSIGNED NOT NULL COMMENT 'FK adms_usuarios.id',
    requester_name VARCHAR(200) NOT NULL,
    requester_cpf VARCHAR(14) DEFAULT NULL,
    requester_position VARCHAR(100) DEFAULT NULL,
    store_id VARCHAR(4) DEFAULT NULL COMMENT 'FK tb_lojas.id',

    category_id INT UNSIGNED NOT NULL,
    priority_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status_id TINYINT UNSIGNED NOT NULL DEFAULT 1,

    demand_text TEXT NOT NULL COMMENT 'Descrição livre do colaborador',
    question_1 VARCHAR(255) DEFAULT NULL,
    answer_1 TEXT DEFAULT NULL,
    question_2 VARCHAR(255) DEFAULT NULL,
    answer_2 TEXT DEFAULT NULL,

    assigned_operator_id INT UNSIGNED DEFAULT NULL COMMENT 'FK adms_usuarios.id',
    assigned_at DATETIME DEFAULT NULL,

    validation_message TEXT DEFAULT NULL,
    validation_attachment VARCHAR(255) DEFAULT NULL,
    validated_by_requester TINYINT(1) DEFAULT 0,
    validated_at DATETIME DEFAULT NULL,

    chat_session_id VARCHAR(36) DEFAULT NULL COMMENT 'UUID para vincular ao chat ativo',

    sla_due_at DATETIME DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    closed_at DATETIME DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED DEFAULT NULL,
    updated_by_user_id INT UNSIGNED DEFAULT NULL,

    CONSTRAINT fk_dp_tickets_category FOREIGN KEY (category_id) REFERENCES dp_categories(id),
    CONSTRAINT fk_dp_tickets_priority FOREIGN KEY (priority_id) REFERENCES dp_priorities(id),
    CONSTRAINT fk_dp_tickets_status FOREIGN KEY (status_id) REFERENCES dp_statuses(id),

    INDEX idx_status (status_id),
    INDEX idx_store (store_id),
    INDEX idx_operator (assigned_operator_id),
    INDEX idx_requester (requester_id),
    INDEX idx_number (ticket_number),
    INDEX idx_created (created_at),
    INDEX idx_sla (sla_due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `dp_interaction_templates` — Templates de Interação

```sql
CREATE TABLE dp_interaction_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('mensagem', 'solicitacao', 'formulario') NOT NULL,
    icon VARCHAR(30) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    fields JSON DEFAULT NULL COMMENT 'Array de campos do formulário',
    is_active TINYINT(1) DEFAULT 1,
    sort_order TINYINT UNSIGNED DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO dp_interaction_templates (name, slug, type, icon, description, fields, sort_order) VALUES
('Mensagem Livre', 'msg_livre', 'mensagem', '💬', 'Mensagem de texto livre para o colaborador.', NULL, 1),
('Solicitar Documento', 'doc_adicional', 'solicitacao', '📄', 'O colaborador receberá a solicitação e poderá enviar o documento pelo chat.', NULL, 2),
('Formulário de Férias', 'form_ferias', 'formulario', '🏖️', 'Formulário de férias enviado ao colaborador para preencher.', '[{"nome":"periodo_inicio","label":"Data de início","tipo":"text","placeholder":"dd/mm/aaaa"},{"nome":"periodo_fim","label":"Data de retorno","tipo":"text","placeholder":"dd/mm/aaaa"},{"nome":"tipo","label":"Tipo","tipo":"select","opcoes":["30 dias corridos","15 + 15 dias","20 + 10 dias","10 + 10 + 10 dias"]},{"nome":"abono","label":"Vender 10 dias (abono)?","tipo":"select","opcoes":["Sim","Não"]}]', 3),
('Formulário de Benefícios', 'form_beneficio', 'formulario', '🎁', 'Formulário de benefícios para preenchimento do colaborador.', '[{"nome":"beneficio","label":"Benefício","tipo":"select","opcoes":["Vale Refeição","Vale Alimentação","Plano de Saúde","Plano Dental","Vale Transporte"]},{"nome":"operacao","label":"Operação","tipo":"select","opcoes":["Inclusão","Exclusão","Alteração","Inclusão de dependente"]},{"nome":"dependente","label":"Dependente (se aplicável)","tipo":"text","placeholder":"Nome"}]', 4),
('Correção de Ponto', 'form_ponto', 'formulario', '⏰', 'Formulário de correção de ponto para o colaborador preencher.', '[{"nome":"data","label":"Data","tipo":"text","placeholder":"dd/mm/aaaa"},{"nome":"entrada","label":"Entrada real","tipo":"text","placeholder":"08:00"},{"nome":"saida","label":"Saída real","tipo":"text","placeholder":"17:30"},{"nome":"motivo","label":"Motivo","tipo":"text","placeholder":"Ex: Sistema não registrou"}]', 5);
```

#### `dp_interactions` — Interações do Ticket

```sql
CREATE TABLE dp_interactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    template_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL = comentário livre',
    user_id INT UNSIGNED NOT NULL,
    type ENUM('mensagem', 'solicitacao', 'formulario', 'sistema', 'validacao') NOT NULL DEFAULT 'mensagem',
    title VARCHAR(200) DEFAULT NULL,
    message TEXT DEFAULT NULL,

    is_responded TINYINT(1) DEFAULT 0,
    response JSON DEFAULT NULL COMMENT 'Resposta do colaborador (campos do form ou texto)',
    responded_at DATETIME DEFAULT NULL,

    is_internal TINYINT(1) DEFAULT 0 COMMENT 'Nota interna (não visível ao colaborador)',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_dp_interactions_ticket FOREIGN KEY (ticket_id) REFERENCES dp_tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_dp_interactions_template FOREIGN KEY (template_id) REFERENCES dp_interaction_templates(id),

    INDEX idx_ticket (ticket_id),
    INDEX idx_responded (is_responded),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `dp_attachments` — Anexos

```sql
CREATE TABLE dp_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    interaction_id INT UNSIGNED DEFAULT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED DEFAULT 0,
    mime_type VARCHAR(100) DEFAULT NULL,
    uploaded_by ENUM('colaborador', 'operador') NOT NULL,
    uploaded_by_user_id INT UNSIGNED NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_dp_attachments_ticket FOREIGN KEY (ticket_id) REFERENCES dp_tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_dp_attachments_interaction FOREIGN KEY (interaction_id) REFERENCES dp_interactions(id) ON DELETE SET NULL,

    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `dp_operators` — Operadores do DP

```sql
CREATE TABLE dp_operators (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE COMMENT 'FK adms_usuarios.id',
    display_name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    max_tickets INT UNSIGNED DEFAULT 20 COMMENT 'Capacidade máxima simultânea',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `dp_employees` — Base de Funcionários (complementar à `adms_usuarios`)

```sql
CREATE TABLE dp_employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL COMMENT 'FK adms_usuarios.id (nullable — pode não ter login)',
    nome VARCHAR(200) NOT NULL,
    cpf VARCHAR(14) NOT NULL UNIQUE,
    loja VARCHAR(100) DEFAULT NULL,
    store_id VARCHAR(4) DEFAULT NULL,
    cargo VARCHAR(100) DEFAULT NULL,
    data_admissao DATE DEFAULT NULL,
    situacao ENUM('Efetivo', 'Experiência', 'PJ', 'Demitido', 'Afastado') DEFAULT 'Efetivo',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED DEFAULT NULL,

    INDEX idx_cpf (cpf),
    INDEX idx_store (store_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Numeração Sequencial

```sql
CREATE TABLE dp_sequence (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    current_number INT UNSIGNED NOT NULL DEFAULT 1000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO dp_sequence (current_number) VALUES (1000);

-- Uso: UPDATE dp_sequence SET current_number = current_number + 1;
-- SELECT current_number FROM dp_sequence; → "DP-1001"
```

---

## 5. Estrutura de Diretórios

```
app/adms/
├── Controllers/
│   ├── DpCentral.php                    # Página principal (chat + kanban + dash)
│   ├── DpTickets.php                    # CRUD de tickets (list, search, create via chat)
│   ├── AddDpTicket.php                  # Criar ticket (via chat ou manual)
│   ├── EditDpTicket.php                 # Editar ticket (mover coluna, atribuir)
│   ├── ViewDpTicket.php                 # Visualizar detalhes do ticket
│   ├── DeleteDpTicket.php               # Soft delete
│   ├── DpInteractions.php               # Enviar/responder interações
│   ├── DpEmployees.php                  # CRUD funcionários + import CSV
│   ├── DpDashboard.php                  # KPIs e dados dos gráficos
│   └── Api/V1/
│       └── DpChatController.php         # API para o chat (lookup CPF, criar ticket)
│
├── Models/
│   ├── AdmsDpTicket.php                 # CRUD principal do ticket
│   ├── AdmsListDpTickets.php            # Listagem com filtros e paginação
│   ├── AdmsViewDpTicket.php             # Detalhes + interações + anexos
│   ├── AdmsStatisticsDpTickets.php      # KPIs e dados de gráficos
│   ├── AdmsDpInteraction.php            # Criar/responder interações
│   ├── AdmsDpEmployee.php               # CRUD funcionários
│   ├── AdmsListDpEmployees.php          # Listagem funcionários
│   ├── AdmsImportDpEmployees.php        # Importação CSV/Excel
│   └── AdmsDpChatSession.php            # Lógica do chat (lookup CPF, triagem)
│
├── Views/
│   └── dpCentral/
│       ├── loadDpCentral.php            # Layout principal (chat + painel direito)
│       ├── listDpTickets.php            # Listagem tabela (fallback sem kanban)
│       └── partials/
│           ├── _chat_panel.php          # Painel de chat (esquerda)
│           ├── _kanban_board.php        # Quadro kanban
│           ├── _dashboard.php           # Dashboard com KPIs e gráficos
│           ├── _employees_tab.php       # Aba de funcionários
│           ├── _view_ticket_modal.php   # Modal de detalhe do ticket
│           ├── _interact_modal.php      # Modal de interação (templates)
│           ├── _validate_modal.php      # Modal de validação
│           ├── _add_employee_modal.php  # Modal novo funcionário
│           └── _statistics_dp.php       # Cards de estatísticas
│
├── Services/
│   ├── DpChatService.php                # Lógica do chat (CPF lookup, triagem, detecção)
│   ├── DpTicketService.php              # Criação de ticket, numeração, SLA
│   ├── DpInteractionService.php         # Templates, formulários, respostas
│   ├── DpNotificationService.php        # Email + WebSocket para DP
│   └── DpImportService.php              # Import CSV/Excel de funcionários

assets/js/
├── dp-central.js                        # JS principal (chat, kanban, tabs, modais)
├── dp-chat.js                           # Lógica do chat conversacional
├── dp-kanban.js                         # Kanban com drag-and-drop
├── dp-interactions.js                   # Templates e formulários inline
└── dp-dashboard.js                      # Gráficos e KPIs

database/migrations/
└── 2026_03_27_create_dp_central_tables.sql
```

---

## 6. Fluxo do Atendimento

### Fluxo Principal (Colaborador → Operador → Resolução)

```
┌─────────────────────────────────────────────────────────────┐
│                     COLABORADOR (Chat)                       │
├─────────────────────────────────────────────────────────────┤
│  1. Informa CPF                                             │
│  2. Sistema valida na base dp_employees                     │
│  3. Colaborador descreve a demanda                          │
│  4. Sistema detecta categoria e prioridade                  │
│  5. Sistema faz 2 perguntas de triagem (da categoria)       │
│  6. Ticket criado automaticamente (status: Novo)            │
│  7. Colaborador recebe protocolo (DP-XXXX)                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                     OPERADOR DP (Kanban)                     │
├─────────────────────────────────────────────────────────────┤
│  8. Ticket aparece na coluna "Novo"                         │
│  9. Operador abre o detalhe e se atribui                    │
│ 10. Ticket move para "Em Análise"                           │
│ 11. Chat notifica: "Sou [Operador], vou conduzir..."        │
│                                                              │
│  OPÇÃO A — Interagir (Fase 4):                              │
│  12a. Operador envia interação (template)                   │
│  13a. Formulário/doc/msg aparece no chat do colaborador     │
│  14a. Colaborador responde (form inline / upload / texto)   │
│  15a. Resposta salva em dp_interactions                     │
│                                                              │
│  OPÇÃO B — Validação (Resolução):                           │
│  12b. Operador envia para validação (msg + anexo)           │
│  13b. Ticket move para "Em Validação"                       │
│  14b. Chat notifica colaborador com botão "Aprovar"         │
│  15b. Colaborador aprova → ticket.validated_by_requester=1  │
│  16b. Operador finaliza → status "Finalizado"               │
└─────────────────────────────────────────────────────────────┘
```

### Máquina de Estados do Ticket

```
                    ┌──────────┐
                    │   Novo   │ (1)
                    └────┬─────┘
                         │ Atribuir operador
                         ▼
                    ┌──────────┐
              ┌─────│ Em Análise│ (2) ◄──── Interagir (loop)
              │     └────┬─────┘
              │          │ Enviar para validação
              │          ▼
              │     ┌────────────────┐
              │     │ Ag. Validação  │ (3)
              │     └────┬───────┬──┘
              │          │       │
              │  Aprovado│       │ (voltar para análise)
              │          ▼       │
              │     ┌──────────┐ │
              │     │ Validado │ (4)
              │     └────┬─────┘ │
              │          │       │
              │ Finalizar│       │
              │          ▼       │
              │     ┌──────────┐ │
              │     │Finalizado│ (5)
              │     └──────────┘ │
              │                  │
              │     ┌──────────┐ │
              └────►│Cancelado │ (6) ◄──┘
                    └──────────┘
```

### Transições Permitidas

| De | Para | Quem | Ação |
|----|------|------|------|
| Novo (1) | Em Análise (2) | Operador | Atribuir |
| Novo (1) | Cancelado (6) | Operador/Admin | Cancelar |
| Em Análise (2) | Ag. Validação (3) | Operador | Enviar validação |
| Em Análise (2) | Cancelado (6) | Operador | Cancelar |
| Ag. Validação (3) | Validado (4) | Colaborador | Aprovar no chat |
| Ag. Validação (3) | Em Análise (2) | Operador | Reabrir |
| Validado (4) | Finalizado (5) | Operador | Finalizar |
| Finalizado (5) | Em Análise (2) | Admin | Reabrir |

---

## 7. Implementação — Fase 1: Fundação

**Objetivo:** Criar tabelas, lookups, rotas e estrutura base.

### 7.1. Migration SQL

Arquivo: `database/migrations/2026_03_27_create_dp_central_tables.sql`

Contém todas as tabelas definidas na [Seção 4](#4-arquitetura-de-dados), mais os INSERTs de dados iniciais.

### 7.2. Rotas (`adms_paginas`)

```sql
-- Página principal
INSERT INTO adms_paginas (controller, metodo, nome_pagina, publicar, obs)
VALUES
('dp-central', 'list', 'Central DP', 1, 'Página principal da Central DP'),
('dp-central', 'kanban', 'Central DP - Kanban', 1, NULL),
('dp-central', 'dashboard', 'Central DP - Dashboard', 1, NULL),

-- Tickets
('dp-tickets', 'list', 'DP Tickets - Listar', 1, NULL),
('add-dp-ticket', 'create', 'DP Tickets - Criar', 1, NULL),
('edit-dp-ticket', 'edit', 'DP Tickets - Editar', 1, NULL),
('view-dp-ticket', 'view', 'DP Tickets - Visualizar', 1, NULL),
('delete-dp-ticket', 'delete', 'DP Tickets - Excluir', 1, NULL),

-- Interações
('dp-interactions', 'send', 'DP Interações - Enviar', 1, NULL),
('dp-interactions', 'respond', 'DP Interações - Responder', 1, NULL),

-- Funcionários
('dp-employees', 'list', 'DP Funcionários - Listar', 1, NULL),
('dp-employees', 'create', 'DP Funcionários - Criar', 1, NULL),
('dp-employees', 'import', 'DP Funcionários - Importar', 1, NULL),

-- Chat API
('dp-chat', 'lookup', 'DP Chat - Buscar CPF', 1, NULL),
('dp-chat', 'create-ticket', 'DP Chat - Criar Ticket', 1, NULL);
```

### 7.3. Services Base

**`DpTicketService.php`** — Numeração sequencial:

```php
<?php

namespace App\adms\Services;

use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsUpdate;

class DpTicketService
{
    /**
     * Gera o próximo número de ticket (DP-XXXX)
     */
    public static function generateTicketNumber(): string
    {
        $update = new AdmsUpdate();
        $update->exeUpdate(
            'dp_sequence',
            ['current_number' => null], // placeholder
            'id = :id',
            'id=1'
        );

        // UPDATE direto para atomicidade
        $read = new AdmsRead();
        $read->fullRead(
            "UPDATE dp_sequence SET current_number = current_number + 1 WHERE id = 1"
        );

        $read->fullRead("SELECT current_number FROM dp_sequence WHERE id = 1");
        $result = $read->getResult();

        $number = $result[0]['current_number'] ?? 1001;

        return 'DP-' . $number;
    }

    /**
     * Calcula SLA baseado na prioridade
     */
    public static function calculateSla(int $priorityId): string
    {
        $read = new AdmsRead();
        $read->fullRead(
            "SELECT sla_hours FROM dp_priorities WHERE id = :id",
            "id={$priorityId}"
        );
        $result = $read->getResult();
        $hours = $result[0]['sla_hours'] ?? 48;

        return date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
    }
}
```

### 7.4. Estimativa Fase 1

| Item | Esforço |
|------|---------|
| Migration SQL (9 tabelas + seeds) | 2h |
| Rotas e permissões (adms_paginas + adms_nivacs_pgs) | 1h |
| DpTicketService (numeração + SLA) | 1h |
| **Total** | **4h** |

---

## 8. Implementação — Fase 2: Chat e Criação de Tickets

**Objetivo:** Chat conversacional funcional com criação automática de tickets.

### 8.1. DpChatService

```php
<?php

namespace App\adms\Services;

use App\adms\Models\helper\AdmsRead;

class DpChatService
{
    /**
     * Busca colaborador por CPF na base dp_employees
     */
    public function lookupByCpf(string $cpf): ?array
    {
        $cpfClean = preg_replace('/[.\-\s]/', '', $cpf);

        $read = new AdmsRead();
        $read->fullRead(
            "SELECT id, nome, cpf, cargo, loja, store_id, situacao
             FROM dp_employees
             WHERE cpf = :cpf AND is_active = 1",
            "cpf={$cpfClean}"
        );

        $result = $read->getResult();
        return $result[0] ?? null;
    }

    /**
     * Detecta categoria baseado em palavras-chave do texto
     */
    public function detectCategory(string $text): ?array
    {
        $read = new AdmsRead();
        $read->fullRead(
            "SELECT id, name, slug, questions, detection_keywords
             FROM dp_categories
             WHERE is_active = 1
             ORDER BY sort_order"
        );

        $categories = $read->getResult();
        if (!$categories) {
            return null;
        }

        $textLower = mb_strtolower($text, 'UTF-8');

        foreach ($categories as $cat) {
            $keywords = json_decode($cat['detection_keywords'] ?? '[]', true);
            foreach ($keywords as $keyword) {
                if (mb_strpos($textLower, mb_strtolower($keyword)) !== false) {
                    return [
                        'id' => (int) $cat['id'],
                        'name' => $cat['name'],
                        'slug' => $cat['slug'],
                        'questions' => json_decode($cat['questions'] ?? '[]', true),
                    ];
                }
            }
        }

        // Fallback: "Outros"
        foreach ($categories as $cat) {
            if ($cat['slug'] === 'outros') {
                return [
                    'id' => (int) $cat['id'],
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'questions' => json_decode($cat['questions'] ?? '[]', true),
                ];
            }
        }

        return null;
    }

    /**
     * Detecta prioridade baseado em palavras-chave
     */
    public function detectPriority(string $text): array
    {
        $read = new AdmsRead();
        $read->fullRead(
            "SELECT id, name, slug, detection_keywords
             FROM dp_priorities
             WHERE detection_keywords IS NOT NULL
             ORDER BY sort_order DESC"
        );

        $priorities = $read->getResult();
        $textLower = mb_strtolower($text, 'UTF-8');

        foreach ($priorities as $prio) {
            $keywords = json_decode($prio['detection_keywords'] ?? '[]', true);
            foreach ($keywords as $keyword) {
                if (mb_strpos($textLower, mb_strtolower($keyword)) !== false) {
                    return ['id' => (int) $prio['id'], 'name' => $prio['name']];
                }
            }
        }

        return ['id' => 1, 'name' => 'Normal'];
    }
}
```

### 8.2. Controller do Chat (API JSON)

```php
<?php

namespace App\adms\Controllers;

use App\adms\Services\DpChatService;
use App\adms\Services\DpTicketService;
use App\adms\Models\AdmsDpTicket;
use App\adms\Models\helper\traits\JsonResponseTrait;

class DpCentral
{
    use JsonResponseTrait;

    private DpChatService $chatService;

    public function __construct()
    {
        $this->chatService = new DpChatService();
    }

    /**
     * Página principal — renderiza layout com chat + painel
     */
    public function list(): void
    {
        $loadView = new \Core\ConfigView('adms/Views/dpCentral/loadDpCentral');
        $loadView->render($this->data);
    }

    /**
     * API: Buscar colaborador por CPF
     * POST dp-central/lookup-cpf
     */
    public function lookupCpf(): void
    {
        header('Content-Type: application/json');

        $cpf = filter_input(INPUT_POST, 'cpf', FILTER_DEFAULT);
        if (empty($cpf)) {
            echo json_encode(['error' => true, 'msg' => 'CPF é obrigatório']);
            return;
        }

        $employee = $this->chatService->lookupByCpf($cpf);
        if (!$employee) {
            echo json_encode(['error' => true, 'msg' => 'CPF não encontrado']);
            return;
        }

        echo json_encode(['error' => false, 'data' => $employee]);
    }

    /**
     * API: Detectar categoria e prioridade
     * POST dp-central/detect-category
     */
    public function detectCategory(): void
    {
        header('Content-Type: application/json');

        $text = filter_input(INPUT_POST, 'text', FILTER_DEFAULT);
        $category = $this->chatService->detectCategory($text);
        $priority = $this->chatService->detectPriority($text);

        echo json_encode([
            'error' => false,
            'category' => $category,
            'priority' => $priority,
        ]);
    }

    /**
     * API: Criar ticket via chat
     * POST dp-central/create-ticket
     */
    public function createTicket(): void
    {
        header('Content-Type: application/json');

        $data = filter_input_array(INPUT_POST, FILTER_DEFAULT);

        $model = new AdmsDpTicket();
        $model->createFromChat($data);

        if ($model->getResult()) {
            echo json_encode([
                'error' => false,
                'ticket' => $model->getResult(),
            ]);
        } else {
            echo json_encode([
                'error' => true,
                'msg' => $model->getError() ?? 'Erro ao criar ticket',
            ]);
        }
    }
}
```

### 8.3. JavaScript do Chat (`dp-chat.js`)

O chat opera inteiramente no frontend com chamadas AJAX ao backend. O estado da conversa é gerenciado por uma máquina de estados local:

```javascript
/**
 * Central DP — Chat Module
 *
 * Estados: IDLE → CPF → DEMANDA → P1 → P2 → AGUARDANDO → (interações do operador)
 */
const DpChat = (() => {
    let state = 'IDLE';
    let employee = null;
    let category = null;
    let priority = null;
    let answers = { q1: '', q2: '' };
    let demandText = '';
    let currentTicketId = null;
    let chatFiles = [];

    const URL_BASE = document.getElementById('dp-container')?.dataset.urlBase || '';

    function init() {
        document.getElementById('dp-chat-input')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') send();
        });
        document.getElementById('dp-chat-send')?.addEventListener('click', send);
        document.getElementById('dp-chat-new')?.addEventListener('click', startChat);

        startChat();
    }

    function startChat() {
        state = 'CPF';
        employee = null;
        category = null;
        priority = null;
        answers = { q1: '', q2: '' };
        demandText = '';
        currentTicketId = null;
        chatFiles = [];

        clearMessages();
        showControls(true);
        addMessage('bot', 'Olá! 👋 Bem-vindo ao atendimento do <strong>Departamento Pessoal</strong>.\n\nInforme seu <strong>CPF</strong> (somente números).');
    }

    async function send() {
        const input = document.getElementById('dp-chat-input');
        const msg = input.value.trim();
        if (!msg) return;

        addMessage('user', msg);
        input.value = '';
        input.disabled = true;
        showTyping(true);

        await delay(500 + Math.random() * 400);
        showTyping(false);
        input.disabled = false;
        input.focus();

        processMessage(msg);
    }

    async function processMessage(msg) {
        switch (state) {
            case 'CPF':
                await handleCpf(msg);
                break;
            case 'DEMANDA':
                await handleDemand(msg);
                break;
            case 'P1':
                handleAnswer1(msg);
                break;
            case 'P2':
                await handleAnswer2(msg);
                break;
            default:
                addMessage('bot', 'Atendimento em andamento. Aguarde a resposta do RH. ✅');
        }
    }

    async function handleCpf(msg) {
        try {
            const response = await fetch(`${URL_BASE}dp-central/lookup-cpf`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `cpf=${encodeURIComponent(msg)}`
            });
            const result = await response.json();

            if (result.error) {
                addMessage('bot', '❌ CPF não encontrado.\n\n💡 Verifique o número e tente novamente.');
                return;
            }

            employee = result.data;
            state = 'DEMANDA';
            addMessage('bot',
                `Cadastro encontrado! ✅\n\n` +
                `👤 <strong>${employee.nome}</strong>\n` +
                `📌 ${employee.cargo} — ${employee.loja}\n\n` +
                `Descreva sua solicitação.`
            );
        } catch (error) {
            console.error('Erro ao buscar CPF:', error);
            addMessage('bot', '❌ Erro ao consultar. Tente novamente.');
        }
    }

    async function handleDemand(msg) {
        demandText = msg;

        try {
            const response = await fetch(`${URL_BASE}dp-central/detect-category`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `text=${encodeURIComponent(msg)}`
            });
            const result = await response.json();

            category = result.category;
            priority = result.priority;
            state = 'P1';

            const questions = category?.questions || ['Pode detalhar mais?', 'Existe urgência?'];
            addMessage('bot',
                `Entendido! Categoria: <strong>${category?.name || 'Outros'}</strong>\n\n` +
                `📋 <strong>Pergunta 1 de 2:</strong>\n${questions[0]}`
            );
        } catch (error) {
            console.error('Erro ao detectar categoria:', error);
            state = 'P1';
            category = { id: 7, name: 'Outros', questions: ['Pode detalhar mais?', 'Existe urgência?'] };
            addMessage('bot', '📋 <strong>Pergunta 1 de 2:</strong>\nPode detalhar mais?');
        }
    }

    function handleAnswer1(msg) {
        answers.q1 = msg;
        state = 'P2';
        const questions = category?.questions || ['', 'Existe urgência?'];
        addMessage('bot', `Anotado! ✍️\n\n📋 <strong>Pergunta 2 de 2:</strong>\n${questions[1]}`);
    }

    async function handleAnswer2(msg) {
        answers.q2 = msg;
        state = 'AGUARDANDO';

        try {
            const formData = new URLSearchParams({
                employee_id: employee.id,
                employee_name: employee.nome,
                employee_cpf: employee.cpf,
                employee_position: employee.cargo,
                store_id: employee.store_id || '',
                category_id: category?.id || 7,
                priority_id: priority?.id || 1,
                demand_text: demandText,
                question_1: (category?.questions || [])[0] || '',
                answer_1: answers.q1,
                question_2: (category?.questions || [])[1] || '',
                answer_2: answers.q2,
            });

            const response = await fetch(`${URL_BASE}dp-central/create-ticket`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            const result = await response.json();

            if (result.error) {
                addMessage('bot', '❌ Erro ao criar ticket: ' + (result.msg || 'Tente novamente.'));
                return;
            }

            currentTicketId = result.ticket.id;
            const t = result.ticket;

            addMessage('bot',
                `Ticket aberto! 🎉\n\n` +
                `━━━━━━━━━━━━━━━━━━━━\n` +
                `📌 <strong>Protocolo:</strong> ${t.ticket_number}\n` +
                `📂 <strong>Categoria:</strong> ${category?.name}\n` +
                `🔴 <strong>Prioridade:</strong> ${priority?.name}\n` +
                `━━━━━━━━━━━━━━━━━━━━\n\n` +
                `Aguarde, o RH responderá por aqui.`
            );

            // Atualizar kanban se visível
            if (typeof DpKanban !== 'undefined') DpKanban.refresh();
            if (typeof DpDashboard !== 'undefined') DpDashboard.refresh();

        } catch (error) {
            console.error('Erro ao criar ticket:', error);
            addMessage('bot', '❌ Erro ao criar ticket. Tente novamente.');
        }
    }

    // --- Helpers de UI ---
    function addMessage(type, text) { /* renderiza bolha no chat */ }
    function clearMessages() { /* limpa container */ }
    function showTyping(show) { /* mostra/oculta typing indicator */ }
    function showControls(show) { /* mostra/oculta input+botões */ }
    function delay(ms) { return new Promise(r => setTimeout(r, ms)); }

    return { init, startChat, addMessage, getCurrentTicketId: () => currentTicketId };
})();

document.addEventListener('DOMContentLoaded', DpChat.init);
```

### 8.4. Estimativa Fase 2

| Item | Esforço |
|------|---------|
| DpChatService (CPF lookup, detecção) | 3h |
| DpCentral controller (endpoints JSON) | 3h |
| AdmsDpTicket model (createFromChat) | 3h |
| View loadDpCentral.php + _chat_panel.php | 3h |
| dp-chat.js (máquina de estados completa) | 5h |
| **Total** | **17h** |

---

## 9. Implementação — Fase 3: Kanban e Gestão

**Objetivo:** Quadro kanban funcional com drag-and-drop, atribuição e validação.

### 9.1. Kanban Backend

**`DpCentral::kanban()`** — retorna tickets agrupados por coluna:

```php
public function kanban(): void
{
    header('Content-Type: application/json');

    $read = new AdmsRead();
    $read->fullRead(
        "SELECT t.id, t.ticket_number, t.requester_name, t.demand_text,
                t.category_id, c.name AS category_name, c.icon AS category_icon,
                t.priority_id, p.name AS priority_name,
                t.status_id, s.kanban_column,
                t.assigned_operator_id, o.display_name AS operator_name,
                t.validated_by_requester, t.created_at,
                (SELECT COUNT(*) FROM dp_interactions i
                 WHERE i.ticket_id = t.id AND i.is_responded = 0) AS pending_interactions,
                (SELECT COUNT(*) FROM dp_interactions i
                 WHERE i.ticket_id = t.id AND i.is_responded = 1) AS responded_interactions
         FROM dp_tickets t
         LEFT JOIN dp_categories c ON t.category_id = c.id
         LEFT JOIN dp_priorities p ON t.priority_id = p.id
         LEFT JOIN dp_statuses s ON t.status_id = s.id
         LEFT JOIN dp_operators o ON t.assigned_operator_id = o.user_id
         WHERE t.deleted_at IS NULL
         ORDER BY t.priority_id DESC, t.created_at ASC"
    );

    $tickets = $read->getResult() ?: [];
    $columns = ['novo' => [], 'analise' => [], 'validacao' => [], 'final' => []];

    foreach ($tickets as $ticket) {
        $col = $ticket['kanban_column'] ?? 'novo';
        $columns[$col][] = $ticket;
    }

    echo json_encode(['error' => false, 'columns' => $columns]);
}
```

### 9.2. Drag-and-Drop (`dp-kanban.js`)

```javascript
/**
 * Central DP — Kanban Module
 *
 * Drag-and-drop nativo (HTML5 Drag API)
 * Atualiza status via AJAX ao soltar card em outra coluna
 */
const DpKanban = (() => {
    const URL_BASE = document.getElementById('dp-container')?.dataset.urlBase || '';
    let dragId = null;

    function init() {
        refresh();
    }

    async function refresh() {
        try {
            const response = await fetch(`${URL_BASE}dp-central/kanban`);
            const result = await response.json();
            if (!result.error) renderColumns(result.columns);
        } catch (error) {
            console.error('Erro ao carregar kanban:', error);
        }
    }

    function renderColumns(columns) {
        const config = [
            { key: 'novo', el: 'col-novo', counter: 'cn1' },
            { key: 'analise', el: 'col-analise', counter: 'cn2' },
            { key: 'validacao', el: 'col-validacao', counter: 'cn3' },
            { key: 'final', el: 'col-final', counter: 'cn4' },
        ];

        config.forEach(col => {
            const el = document.getElementById(col.el);
            const counter = document.getElementById(col.counter);
            const tickets = columns[col.key] || [];

            counter.textContent = tickets.length;
            el.innerHTML = tickets.length
                ? tickets.map(t => renderCard(t)).join('')
                : '<div class="emp">Nenhum ticket</div>';
        });

        // Atualizar badge
        const badge = document.getElementById('kbBadge');
        if (badge) badge.textContent = (columns.novo || []).length;
    }

    function renderCard(ticket) {
        const pendBadge = ticket.pending_interactions > 0
            ? `<div class="text-warning small">💬 ${ticket.pending_interactions} pendente(s)</div>`
            : '';
        const respBadge = ticket.responded_interactions > 0
            ? `<div class="text-success small">✅ ${ticket.responded_interactions} respondida(s)</div>`
            : '';

        return `
            <div class="tc" draggable="true"
                 ondragstart="DpKanban.dragStart(event, '${ticket.id}')"
                 ondragend="DpKanban.dragEnd(event)"
                 onclick="DpKanban.openDetail('${ticket.id}')">
                <div class="tc-row">
                    <span class="tc-num">${ticket.ticket_number}</span>
                    <span class="tc-pri ${ticket.priority_name}">${ticket.priority_name}</span>
                </div>
                <div class="tc-nm">${escapeHtml(ticket.requester_name)}</div>
                <div class="tc-cat">${ticket.category_icon || ''} ${escapeHtml(ticket.category_name)}</div>
                ${ticket.operator_name ? `<div class="tc-resp">👤 ${escapeHtml(ticket.operator_name)}</div>` : ''}
                ${pendBadge}${respBadge}
            </div>`;
    }

    // --- Drag API ---
    function dragStart(e, id) { dragId = id; e.target.classList.add('drg'); e.dataTransfer.effectAllowed = 'move'; }
    function dragEnd(e) { e.target.classList.remove('drg'); document.querySelectorAll('.kc-body').forEach(c => c.classList.remove('dov')); }
    function dragOver(e) { e.preventDefault(); e.currentTarget.classList.add('dov'); }
    function dragLeave(e) { e.currentTarget.classList.remove('dov'); }

    async function drop(e, targetColumn) {
        e.preventDefault();
        e.currentTarget.classList.remove('dov');
        if (!dragId) return;

        if (targetColumn === 'validacao') {
            // Abrir modal de validação em vez de mover direto
            openValidateModal(dragId);
        } else {
            await moveTicket(dragId, targetColumn);
        }
        dragId = null;
    }

    async function moveTicket(ticketId, targetColumn) {
        try {
            const response = await fetch(`${URL_BASE}edit-dp-ticket/move`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ticket_id=${ticketId}&target_column=${targetColumn}`
            });
            const result = await response.json();

            if (!result.error) {
                refresh();
                DpDashboard?.refresh();
            }
        } catch (error) {
            console.error('Erro ao mover ticket:', error);
        }
    }

    return { init, refresh, dragStart, dragEnd, dragOver, dragLeave, drop, openDetail, moveTicket };
})();
```

### 9.3. Estimativa Fase 3

| Item | Esforço |
|------|---------|
| DpCentral::kanban() endpoint | 2h |
| EditDpTicket (move, assign, validate) | 4h |
| _kanban_board.php (HTML colunas + drop zones) | 2h |
| _view_ticket_modal.php (detalhe completo) | 3h |
| _validate_modal.php | 1h |
| dp-kanban.js (drag-drop + AJAX) | 4h |
| Integração chat ↔ kanban (atribuição notifica chat) | 2h |
| **Total** | **18h** |

---

## 10. Implementação — Fase 4: Interações e Templates

**Objetivo:** Sistema de interações estruturadas com formulários inline no chat.

### 10.1. DpInteractionService

```php
<?php

namespace App\adms\Services;

use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsCreate;
use App\adms\Models\helper\AdmsUpdate;

class DpInteractionService
{
    /**
     * Carrega templates ativos para o modal de interação
     */
    public function getTemplates(): array
    {
        $read = new AdmsRead();
        $read->fullRead(
            "SELECT id, name, slug, type, icon, description, fields
             FROM dp_interaction_templates
             WHERE is_active = 1
             ORDER BY sort_order"
        );

        return $read->getResult() ?: [];
    }

    /**
     * Cria uma nova interação no ticket
     */
    public function createInteraction(array $data): ?int
    {
        $create = new AdmsCreate();
        $create->exeCreate('dp_interactions', [
            'ticket_id'   => (int) $data['ticket_id'],
            'template_id' => !empty($data['template_id']) ? (int) $data['template_id'] : null,
            'user_id'     => SessionContext::getUserId(),
            'type'        => $data['type'] ?? 'mensagem',
            'title'       => $data['title'] ?? null,
            'message'     => $data['message'] ?? '',
            'is_responded' => 0,
            'is_internal' => (int) ($data['is_internal'] ?? 0),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return $create->getResult() ? (int) $create->getResult() : null;
    }

    /**
     * Registra a resposta do colaborador (form, documento ou texto)
     */
    public function respondInteraction(int $interactionId, mixed $response): bool
    {
        $update = new AdmsUpdate();
        $update->exeUpdate('dp_interactions', [
            'is_responded' => 1,
            'response'     => is_array($response) ? json_encode($response) : $response,
            'responded_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', "id={$interactionId}");

        return $update->getResult();
    }
}
```

### 10.2. Renderização de Templates no Chat

Quando o operador envia uma interação, o backend retorna o template renderizado. O `dp-interactions.js` insere o formulário inline no chat:

- **`formulario`** → Renderiza inputs/selects baseados no JSON `fields` do template. Ao submeter, coleta valores e envia via `POST dp-interactions/respond`.
- **`solicitacao`** → Renderiza zona de upload. Ao selecionar arquivo, faz upload via `POST dp-interactions/upload-response`.
- **`mensagem`** → Renderiza input de texto simples. Ao submeter, envia resposta livre.

### 10.3. Estimativa Fase 4

| Item | Esforço |
|------|---------|
| DpInteractionService | 3h |
| DpInteractions controller (send, respond, upload) | 4h |
| AdmsDpInteraction model | 2h |
| _interact_modal.php (select template + info + textarea) | 2h |
| dp-interactions.js (render inline forms, submit, upload) | 5h |
| Integração: interação no detalhe do ticket (histórico) | 2h |
| **Total** | **18h** |

---

## 11. Implementação — Fase 5: Dashboard e Relatórios

**Objetivo:** KPIs em tempo real e gráficos analíticos.

### 11.1. KPIs

| KPI | Query |
|-----|-------|
| **Total Demandas** | `COUNT(*) FROM dp_tickets WHERE deleted_at IS NULL` |
| **Tempo Médio** | `AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at))` dos finalizados |
| **% Aprovação** | `validados / (validados + aguardando) * 100` |
| **Urgentes Abertos** | `COUNT(*) WHERE priority_id = 3 AND status_id NOT IN (5,6)` |

### 11.2. Gráficos (barras horizontais)

| Gráfico | Dados |
|---------|-------|
| **Por Motivo** | `GROUP BY category_id` com contagem |
| **Por Unidade** | `GROUP BY store_id` com JOIN `tb_lojas` |
| **Por Cargo** | `GROUP BY requester_position` |
| **Por Coluna** | `GROUP BY status_id` (kanban_column) |

### 11.3. AdmsStatisticsDpTickets

```php
<?php

namespace App\adms\Models;

use App\adms\Models\helper\AdmsRead;

class AdmsStatisticsDpTickets
{
    private ?array $kpis = null;
    private ?array $charts = null;

    public function getKpis(): ?array { return $this->kpis; }
    public function getCharts(): ?array { return $this->charts; }

    public function loadStatistics(?string $storeFilter = null, ?string $dateFrom = null, ?string $dateTo = null): void
    {
        $this->loadKpis($storeFilter, $dateFrom, $dateTo);
        $this->loadCharts($storeFilter, $dateFrom, $dateTo);
    }

    private function loadKpis(?string $store, ?string $from, ?string $to): void
    {
        $where = "WHERE t.deleted_at IS NULL";
        $params = "";

        if ($store) { $where .= " AND t.store_id = :store"; $params .= "store={$store}"; }
        if ($from) { $where .= " AND t.created_at >= :from"; $params .= "&from={$from} 00:00:00"; }
        if ($to) { $where .= " AND t.created_at <= :to"; $params .= "&to={$to} 23:59:59"; }

        $read = new AdmsRead();

        // Total
        $read->fullRead("SELECT COUNT(*) AS total FROM dp_tickets t {$where}", $params ?: null);
        $total = $read->getResult()[0]['total'] ?? 0;

        // Tempo médio (horas)
        $read->fullRead(
            "SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at)),1) AS avg_hours
             FROM dp_tickets t {$where} AND t.resolved_at IS NOT NULL",
            $params ?: null
        );
        $avgHours = $read->getResult()[0]['avg_hours'] ?? 0;

        // % Aprovação
        $read->fullRead(
            "SELECT
                SUM(CASE WHEN t.validated_by_requester = 1 THEN 1 ELSE 0 END) AS validated,
                SUM(CASE WHEN s.kanban_column = 'validacao' AND t.validated_by_requester = 0 THEN 1 ELSE 0 END) AS pending
             FROM dp_tickets t
             LEFT JOIN dp_statuses s ON t.status_id = s.id
             {$where}",
            $params ?: null
        );
        $row = $read->getResult()[0] ?? [];
        $val = (int) ($row['validated'] ?? 0);
        $pend = (int) ($row['pending'] ?? 0);
        $approvalPct = ($val + $pend) > 0 ? round($val / ($val + $pend) * 100) : 0;

        // Urgentes abertos
        $read->fullRead(
            "SELECT COUNT(*) AS urgents FROM dp_tickets t
             {$where} AND t.priority_id = 3 AND t.status_id NOT IN (5, 6)",
            $params ?: null
        );
        $urgents = $read->getResult()[0]['urgents'] ?? 0;

        $this->kpis = [
            'total' => $total,
            'avg_time' => $avgHours > 0 ? $this->formatHours($avgHours) : '—',
            'approval_pct' => $approvalPct . '%',
            'urgents' => $urgents,
        ];
    }

    private function formatHours(float $hours): string
    {
        if ($hours < 1) return round($hours * 60) . 'min';
        $h = floor($hours);
        $m = round(($hours - $h) * 60);
        return $h . 'h' . ($m > 0 ? ' ' . $m . 'min' : '');
    }

    private function loadCharts(?string $store, ?string $from, ?string $to): void
    {
        // ... queries GROUP BY para cada gráfico
    }
}
```

### 11.4. Estimativa Fase 5

| Item | Esforço |
|------|---------|
| AdmsStatisticsDpTickets (KPIs + charts) | 4h |
| DpDashboard controller | 1h |
| _dashboard.php (HTML cards + containers gráficos) | 2h |
| dp-dashboard.js (fetch + render barras) | 3h |
| Filtros (loja, período) | 2h |
| **Total** | **12h** |

---

## 12. Implementação — Fase 6: Funcionários e Integrações

**Objetivo:** CRUD de funcionários, importação CSV/Excel e notificações.

### 12.1. Funcionários

- **DpEmployees** controller com `list`, `create`, `import`
- **AdmsDpEmployee** model com validação de CPF único
- **AdmsImportDpEmployees** model seguindo o padrão de `AdmsImportProductPrices`
  - Upload CSV (`session_write_close` + JSON progress)
  - Validação por linha (CPF, campos obrigatórios)
  - CSV de rejeitados (`uploads/import_errors/dp_rejeitados_*.csv`)
- **_employees_tab.php** com tabela, busca, botões add/import
- **_add_employee_modal.php** com formulário (nome, CPF, loja, cargo, admissão, situação)

### 12.2. Notificações WebSocket

Seguindo o padrão de `SystemNotificationService`:

```php
use App\adms\Services\SystemNotificationService;

// Quando ticket é criado → notificar operadores do DP
SystemNotificationService::notifyUsers(
    $operatorUserIds,
    "Nova solicitação DP: {$ticketNumber} — {$categoryName}",
    'dp-central/list',
    'dp_ticket'
);

// Quando operador envia interação → notificar colaborador
SystemNotificationService::notify(
    $requesterId,
    "Sua solicitação {$ticketNumber} recebeu uma interação do RH",
    'dp-central/list',
    'dp_interaction'
);
```

### 12.3. Notificações Email

Seguindo o padrão de `HelpdeskEmailService`:

```php
class DpNotificationService
{
    public static function notifyNewTicket(array $ticket): void { /* ... */ }
    public static function notifyAssignment(array $ticket, string $operatorName): void { /* ... */ }
    public static function notifyInteraction(array $ticket, array $interaction): void { /* ... */ }
    public static function notifyValidation(array $ticket): void { /* ... */ }
}
```

### 12.4. Estimativa Fase 6

| Item | Esforço |
|------|---------|
| DpEmployees controller (list, create, import) | 3h |
| AdmsDpEmployee + AdmsListDpEmployees models | 3h |
| AdmsImportDpEmployees (CSV com progress) | 4h |
| Views (tab + modais de funcionários) | 2h |
| DpNotificationService (email + WebSocket) | 3h |
| Integração WebSocket nos controllers | 2h |
| **Total** | **17h** |

---

## 13. Validações e Regras de Negócio

### Validação de CPF

```php
// No DpChatService::lookupByCpf()
// 1. Limpar caracteres não-numéricos
// 2. Verificar 11 dígitos
// 3. Buscar na tabela dp_employees (is_active = 1)
// 4. Fallback: buscar em adms_usuarios (se dp_employees não tiver o registro)
```

### Validações de Ticket

| Campo | Regra | Mensagem |
|-------|-------|----------|
| `requester_id` | Obrigatório, existir em dp_employees | "Colaborador não encontrado" |
| `category_id` | Obrigatório, existir em dp_categories | "Categoria inválida" |
| `demand_text` | Obrigatório, min 10 caracteres | "Descreva a demanda com mais detalhes" |
| `answer_1`, `answer_2` | Obrigatório se perguntas definidas | "Responda as perguntas de triagem" |

### Regras de Transição

| Regra | Descrição |
|-------|-----------|
| Atribuir | Apenas tickets com `status_id = 1` (Novo) |
| Interagir | Apenas tickets com `status_id = 2` (Em Análise) |
| Enviar validação | Apenas tickets com `status_id = 2` e `assigned_operator_id` preenchido |
| Aprovar | Apenas pelo colaborador (`requester_id = user logado`) |
| Finalizar | Apenas tickets com `validated_by_requester = 1` |
| Cancelar | Qualquer status exceto Finalizado (5) |

### Regras de Arquivo

| Regra | Valor |
|-------|-------|
| Extensões permitidas | pdf, doc, docx, png, jpg, jpeg, gif, webp |
| Tamanho máximo | 10 MB |
| Diretório | `uploads/dp/{ticket_id}/` |
| Nomeação | `{timestamp}_{original_name}` |
| Validação MIME | Obrigatória (não confiar apenas na extensão) |

---

## 14. Permissões e Controle de Acesso

### Níveis de Acesso

| Nível | Permissões |
|-------|-----------|
| **Super Admin (1)** | Tudo: gerenciar operadores, ver todas as lojas, cancelar/reabrir qualquer ticket |
| **Admin (2)** | Gerenciar operadores da própria loja, ver tickets da loja |
| **Gestor (3-5)** | Visualizar tickets da loja, atribuir operadores |
| **Operador DP** | Atribuir-se, interagir, enviar validação, finalizar |
| **Colaborador (6+)** | Abrir ticket via chat, responder interações, aprovar validação |

### Filtro por Loja

Seguindo o padrão `StorePermissionTrait`:

```php
// Super Admin: vê todas as lojas
// Demais: filtrado por SessionContext::getUserStore()
if (PermissionService::isSuperAdmin()) {
    // Sem filtro de loja
} else {
    $where .= " AND t.store_id = :storeId";
    $params .= "storeId=" . SessionContext::getUserStore();
}
```

### Botões (AdmsBotao)

```php
private function loadButtons(): void
{
    $buttons = [
        'add_dp_ticket'    => ['menu_controller' => 'add-dp-ticket', 'menu_metodo' => 'create'],
        'edit_dp_ticket'   => ['menu_controller' => 'edit-dp-ticket', 'menu_metodo' => 'edit'],
        'view_dp_ticket'   => ['menu_controller' => 'view-dp-ticket', 'menu_metodo' => 'view'],
        'delete_dp_ticket' => ['menu_controller' => 'delete-dp-ticket', 'menu_metodo' => 'delete'],
        'dp_employees'     => ['menu_controller' => 'dp-employees', 'menu_metodo' => 'list'],
    ];
    $listButtons = new AdmsBotao();
    $this->data['buttons'] = $listButtons->valBotao($buttons);
}
```

---

## 15. Notificações

### Mapa de Notificações

| Evento | Email | WebSocket | Chat |
|--------|-------|-----------|------|
| Ticket criado | Operadores DP | Operadores DP | — |
| Operador atribuído | Colaborador | Colaborador | Msg no chat |
| Interação enviada | Colaborador | Colaborador | Form/doc/msg inline |
| Interação respondida | Operador | Operador | — |
| Enviado para validação | Colaborador | Colaborador | Msg + botão aprovar |
| Validação aprovada | Operador | Operador | Confirmação no chat |
| Ticket finalizado | Colaborador | Colaborador | Msg de encerramento |
| Ticket cancelado | Colaborador | — | — |

---

## 16. Testes

### Estrutura de Testes

```
tests/DpCentral/
├── DpChatServiceTest.php              # Lookup CPF, detecção categoria/prioridade
├── DpTicketServiceTest.php            # Numeração sequencial, cálculo SLA
├── DpInteractionServiceTest.php       # Criar, responder, templates
├── AdmsDpTicketTest.php               # CRUD, validações, transições
├── AdmsStatisticsDpTicketsTest.php     # KPIs, queries de gráficos
├── AdmsDpEmployeeTest.php             # CRUD, validação CPF
└── AdmsImportDpEmployeesTest.php      # Import CSV, rejeição
```

### Cenários Prioritários

| Teste | Tipo | Descrição |
|-------|------|-----------|
| `testLookupValidCpf` | Unit | CPF existente retorna dados corretos |
| `testLookupInvalidCpf` | Unit | CPF inexistente retorna null |
| `testDetectCategoryFerias` | Unit | Texto com "férias" retorna categoria correta |
| `testDetectCategoryFallback` | Unit | Texto sem match retorna "Outros" |
| `testDetectPriorityUrgente` | Unit | Texto com "urgente" retorna prioridade 3 |
| `testCreateTicketFromChat` | Unit | Dados completos criam ticket com número sequencial |
| `testCreateTicketMissingData` | Unit | Dados incompletos retornam erro |
| `testTicketNumberSequence` | Unit | Números são sequenciais (DP-1001, DP-1002...) |
| `testSlaCalculation` | Unit | Prioridade 3 (Urgente) = +8h |
| `testMoveTicketValidTransition` | Unit | Novo → Em Análise (válido) |
| `testMoveTicketInvalidTransition` | Unit | Novo → Finalizado (inválido) |
| `testCreateInteraction` | Unit | Interação criada com template |
| `testRespondInteraction` | Unit | Resposta registrada com JSON |
| `testImportCsvValid` | Unit | CSV válido importa funcionários |
| `testImportCsvDuplicateCpf` | Unit | CPF duplicado vai para rejeitados |

**Meta:** 50+ testes cobrindo services, models e validações.

---

## 17. Análise de Complexidade

### Resumo por Fase

| Fase | Descrição | Controllers | Models | Views | JS | Services | Esforço |
|------|-----------|-------------|--------|-------|----|----------|---------|
| 1 | Fundação | — | — | — | — | 1 | 4h |
| 2 | Chat + Tickets | 1 | 2 | 2 | 1 | 1 | 17h |
| 3 | Kanban + Gestão | 2 | 1 | 3 | 1 | — | 18h |
| 4 | Interações | 1 | 1 | 2 | 1 | 1 | 18h |
| 5 | Dashboard | 1 | 1 | 1 | 1 | — | 12h |
| 6 | Funcionários + Notif. | 1 | 3 | 2 | — | 2 | 17h |
| **Total** | | **6** | **8** | **10** | **4** | **5** | **86h** |

### Comparação com Módulos Existentes

| Módulo | Controllers | Models | Tests | Complexidade |
|--------|-------------|--------|-------|-------------|
| Sales (referência) | 5 | 5 | 162 | Alta |
| Helpdesk | 7 | 6 | — | Alta |
| StockAudit | 8 | 8 | 120+ | Muito Alta |
| VacationPeriods | 4 | 4 | 41 | Média |
| **Central DP (MVP)** | **6** | **8** | **50+** | **Alta** |

---

## 18. Roadmap Pós-MVP

### v1.1 — Melhorias de UX
- [ ] Pesquisa de satisfação (NPS) ao finalizar ticket
- [ ] Histórico de tickets anteriores do colaborador no detalhe
- [ ] Indicador de SLA com cores (verde/amarelo/vermelho)
- [ ] Notificação sonora no chat ao receber resposta

### v1.2 — Relatórios Avançados
- [ ] Exportação PDF dos tickets (DomPDF)
- [ ] Relatório de produtividade por operador
- [ ] Relatório de SLA (cumprido vs. estourado)
- [ ] Exportação Excel do dashboard

### v1.3 — Automações
- [ ] SLA automático com alertas de vencimento (cron)
- [ ] Auto-atribuição por categoria (round-robin)
- [ ] Respostas automáticas para perguntas frequentes
- [ ] Escalação automática após SLA estourado

### v2.0 — Integrações Externas
- [ ] WhatsApp Business API (receber demandas por WhatsApp)
- [ ] Integração com folha de pagamento (Cigam ERP)
- [ ] IA para sugestão de resposta e classificação
- [ ] App mobile (PWA ou React Native)

---

## Referências

| Documento | Descrição |
|-----------|-----------|
| `docs/examples/preview_v6.html` | Protótipo visual interativo (HTML self-contained) |
| `docs/GUIA_IMPLEMENTACAO_MODULOS.md` | Guia de implementação de módulos Mercury |
| `docs/PADRONIZACAO.md` | Templates de código e padrões |
| `docs/ANALISE_MODULO_SALES.md` | Análise do módulo de referência |
| `.claude/REGRAS_DESENVOLVIMENTO.md` | Regras obrigatórias de desenvolvimento |
| `app/adms/Controllers/Helpdesk.php` | Controller de referência (arquitetura similar) |
| `app/adms/Services/HelpdeskEmailService.php` | Service de email de referência |

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
**Versão:** 1.0
**Última Atualização:** 27/03/2026
