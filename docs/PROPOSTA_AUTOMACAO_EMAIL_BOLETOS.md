# Proposta Técnica: Automação de Triagem de Boletos por E-mail

**Status:** Planejado
**Versão:** 1.0
**Projeto:** Mercury
**Última Atualização:** 2026-03-30

---

## 1. Objetivo

Automatizar a identificação de boletos bancários recebidos por e-mail, extraindo dados financeiros dos PDFs anexados e criando pré-ordens de pagamento no módulo OrderPayments existente. O sistema deve:

1. Conectar a uma caixa de e-mail via IMAP e monitorar novos e-mails
2. Identificar e-mails que contêm boletos (anexos PDF)
3. Extrair dados estruturados dos boletos (valor, vencimento, beneficiário, linha digitável)
4. Criar pré-ordens de pagamento com status **Backlog (1)** automaticamente
5. Notificar a equipe financeira para revisão e aprovação
6. Manter rastreabilidade completa (e-mail → boleto → ordem de pagamento)

### Premissas

- A caixa de e-mail será configurada exclusivamente para recebimento de boletos (ex: `boletos@empresa.com.br`)
- A maioria dos boletos são PDFs baseados em texto (não imagens escaneadas)
- A criação automática gera uma **pré-ordem** que requer validação humana antes de avançar no fluxo
- O sistema opera de forma conservadora: em caso de dúvida, marca como "pendente de revisão"

---

## 2. Análise de Complexidade

| Componente | Complexidade | Esforço |
|---|---|---|
| Infraestrutura IMAP | Média | Configuração + Service |
| Classificador de Boletos | Alta | Regex + heurísticas multi-banco |
| Parser de Dados | Alta | Extração estruturada de PDFs variados |
| Integração OrderPayments | Baixa | Modelo existente, apenas criar registro |
| Cron/Scheduler | Baixa | Padrão existente em `bin/` |
| Dashboard de Triagem | Média | Listagem + filtros + ações em lote |
| Detecção de Duplicatas | Média | Hash + linha digitável |
| Notificações | Baixa | WebSocket + email existentes |
| Testes | Média | Mocks de IMAP + fixtures de PDFs |

**Grau Geral:** Complexidade Alta
**Estimativa de Arquivos:** ~25-30 arquivos novos

---

## 3. Modelagem de Dados

### 3.1 Diagrama de Relacionamentos

```
adms_email_accounts                    adms_email_messages
┌──────────────────────┐              ┌──────────────────────────┐
│ id (PK)              │──────1:N────▶│ id (PK)                  │
│ name                 │              │ adms_email_account_id(FK) │
│ imap_host            │              │ message_uid              │
│ imap_port            │              │ from_email               │
│ imap_user            │              │ from_name                │
│ imap_password        │              │ subject                  │
│ imap_encryption      │              │ received_at              │
│ imap_folder          │              │ status                   │
│ polling_interval_min │              │ processed_at             │
│ is_active            │              │ error_message            │
│ last_polled_at       │              │ raw_headers              │
│ created_at           │              │ created_at               │
│ updated_at           │              └────────────┬─────────────┘
└──────────────────────┘                           │
                                                 1:N
                                                   │
                              adms_email_attachments▼
                              ┌──────────────────────────────┐
                              │ id (PK)                      │
                              │ adms_email_message_id (FK)   │
                              │ file_name                    │
                              │ file_path                    │
                              │ mime_type                    │
                              │ file_size                    │
                              │ file_hash (SHA-256)          │
                              │ is_boleto                    │
                              │ confidence_score             │
                              │ classification_reason        │
                              │ created_at                   │
                              └────────────┬─────────────────┘
                                           │
                                         1:N
                                           │
                              adms_boleto_extractions▼
                              ┌──────────────────────────────┐
                              │ id (PK)                      │
                              │ adms_email_attachment_id (FK) │
                              │ barcode_line                 │
                              │ due_date                     │
                              │ total_value                  │
                              │ beneficiary_name             │
                              │ beneficiary_document         │
                              │ payer_name                   │
                              │ payer_document               │
                              │ bank_name                    │
                              │ bank_code                    │
                              │ document_number              │
                              │ extracted_text (TEXT)         │
                              │ extraction_status            │
                              │ extraction_errors            │
                              │ adms_order_payment_id (FK)   │◀── Link para ordem criada
                              │ created_by_user_id           │
                              │ reviewed_by_user_id          │
                              │ reviewed_at                  │
                              │ review_status                │
                              │ review_notes                 │
                              │ created_at                   │
                              │ updated_at                   │
                              └──────────────────────────────┘

                              ┌──────────────────────────────┐
                              │ adms_boleto_processing_logs  │
                              │──────────────────────────────│
                              │ id (PK)                      │
                              │ batch_id (UUID)              │
                              │ action                       │
                              │ details (JSON/TEXT)          │
                              │ emails_fetched               │
                              │ boletos_found                │
                              │ orders_created               │
                              │ errors_count                 │
                              │ execution_time_ms            │
                              │ created_at                   │
                              └──────────────────────────────┘
```

### 3.2 Definição das Tabelas

#### `adms_email_accounts` — Contas de e-mail monitoradas

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | Identificador |
| `name` | VARCHAR(100) | Nome descritivo (ex: "Boletos Financeiro") |
| `imap_host` | VARCHAR(255) | Host IMAP (ex: imap.gmail.com) |
| `imap_port` | INT | Porta (993 para SSL, 143 para STARTTLS) |
| `imap_user` | VARCHAR(255) | Usuário IMAP |
| `imap_password` | VARCHAR(500) | Senha IMAP (criptografada) |
| `imap_encryption` | ENUM('ssl','tls','none') | Tipo de criptografia |
| `imap_folder` | VARCHAR(100) DEFAULT 'INBOX' | Pasta monitorada |
| `polling_interval_min` | INT DEFAULT 10 | Intervalo de polling em minutos |
| `is_active` | TINYINT(1) DEFAULT 1 | Conta ativa/inativa |
| `last_polled_at` | DATETIME NULL | Último polling realizado |
| `created_at` | DATETIME DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME ON UPDATE CURRENT_TIMESTAMP | |

#### `adms_email_messages` — E-mails processados

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | Identificador |
| `adms_email_account_id` | INT FK | Conta de origem |
| `message_uid` | VARCHAR(255) | UID IMAP (único por mailbox) |
| `from_email` | VARCHAR(255) | Remetente |
| `from_name` | VARCHAR(255) NULL | Nome do remetente |
| `subject` | VARCHAR(500) | Assunto |
| `received_at` | DATETIME | Data de recebimento |
| `status` | ENUM('new','processing','processed','error','ignored') DEFAULT 'new' | |
| `processed_at` | DATETIME NULL | Data de processamento |
| `error_message` | TEXT NULL | Mensagem de erro (se houver) |
| `raw_headers` | TEXT NULL | Headers completos para debug |
| `created_at` | DATETIME DEFAULT CURRENT_TIMESTAMP | |

**Índices:** UNIQUE(`adms_email_account_id`, `message_uid`)

#### `adms_email_attachments` — Anexos dos e-mails

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | Identificador |
| `adms_email_message_id` | INT FK | E-mail de origem |
| `file_name` | VARCHAR(255) | Nome original do arquivo |
| `file_path` | VARCHAR(500) | Caminho no servidor |
| `mime_type` | VARCHAR(100) | Tipo MIME |
| `file_size` | INT | Tamanho em bytes |
| `file_hash` | VARCHAR(64) | SHA-256 do conteúdo (detecção de duplicatas) |
| `is_boleto` | TINYINT(1) DEFAULT 0 | Classificado como boleto |
| `confidence_score` | DECIMAL(5,2) NULL | Score de confiança (0-100) |
| `classification_reason` | VARCHAR(500) NULL | Motivo da classificação |
| `created_at` | DATETIME DEFAULT CURRENT_TIMESTAMP | |

#### `adms_boleto_extractions` — Dados extraídos dos boletos

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | Identificador |
| `adms_email_attachment_id` | INT FK | Anexo de origem |
| `barcode_line` | VARCHAR(60) NULL | Linha digitável (47 ou 48 dígitos) |
| `due_date` | DATE NULL | Data de vencimento |
| `total_value` | DECIMAL(10,2) NULL | Valor do boleto |
| `beneficiary_name` | VARCHAR(255) NULL | Nome do beneficiário/cedente |
| `beneficiary_document` | VARCHAR(18) NULL | CNPJ/CPF do beneficiário |
| `payer_name` | VARCHAR(255) NULL | Nome do pagador/sacado |
| `payer_document` | VARCHAR(18) NULL | CNPJ/CPF do pagador |
| `bank_name` | VARCHAR(100) NULL | Nome do banco emissor |
| `bank_code` | VARCHAR(3) NULL | Código do banco (3 dígitos) |
| `document_number` | VARCHAR(25) NULL | Número do documento/NF |
| `extracted_text` | TEXT NULL | Texto completo extraído do PDF |
| `extraction_status` | ENUM('success','partial','failed') DEFAULT 'success' | |
| `extraction_errors` | TEXT NULL | Erros durante extração |
| `adms_order_payment_id` | INT FK NULL | Ordem de pagamento criada |
| `created_by_user_id` | INT NULL | Usuário do sistema (cron) |
| `reviewed_by_user_id` | INT FK NULL | Usuário que revisou |
| `reviewed_at` | DATETIME NULL | Data da revisão |
| `review_status` | ENUM('pending','approved','rejected','duplicate') DEFAULT 'pending' | |
| `review_notes` | VARCHAR(500) NULL | Observações da revisão |
| `created_at` | DATETIME DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME ON UPDATE CURRENT_TIMESTAMP | |

**Índices:** INDEX(`barcode_line`), INDEX(`review_status`), INDEX(`adms_order_payment_id`)

#### `adms_boleto_processing_logs` — Log de execuções do cron

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | Identificador |
| `batch_id` | CHAR(36) | UUID v7 do lote |
| `action` | VARCHAR(50) | Ação executada |
| `details` | TEXT NULL | Detalhes em JSON |
| `emails_fetched` | INT DEFAULT 0 | E-mails baixados |
| `boletos_found` | INT DEFAULT 0 | Boletos identificados |
| `orders_created` | INT DEFAULT 0 | Ordens criadas |
| `errors_count` | INT DEFAULT 0 | Erros ocorridos |
| `execution_time_ms` | INT NULL | Tempo de execução |
| `created_at` | DATETIME DEFAULT CURRENT_TIMESTAMP | |

---

## 4. Funcionalidades

### 4.1 Polling de E-mails (IMAP)

- Conexão via IMAP com SSL/TLS
- Busca apenas e-mails não lidos (`UNSEEN`)
- Download de anexos PDF para `uploads/boletos/`
- Marca e-mails processados como lidos no servidor
- Suporte a múltiplas contas de e-mail
- Tolerância a falhas (reconexão automática)

### 4.2 Classificação de Boletos

O classificador analisa o texto extraído do PDF e atribui um **score de confiança (0-100)**:

| Critério | Peso | Descrição |
|---|---|---|
| Linha digitável | +40 | Regex: `\d{5}\.\d{5}\s\d{5}\.\d{6}\s\d{5}\.\d{6}\s\d\s\d{14}` |
| Código de barras numérico | +30 | 44-47 dígitos consecutivos |
| Palavras-chave primárias | +15 | "vencimento", "beneficiário", "cedente", "sacado", "pagador" |
| Palavras-chave secundárias | +10 | "boleto", "cobrança", "banco", "agência", "parcela" |
| Valor monetário (R$) | +5 | Padrão `R$\s?\d{1,3}(\.\d{3})*,\d{2}` |

**Classificação:**
- Score ≥ 70: **Boleto confirmado** → criação automática de pré-ordem
- Score 40-69: **Possível boleto** → marcado para revisão manual
- Score < 40: **Não é boleto** → ignorado (registrado no log)

### 4.3 Extração de Dados (Parser)

Dados extraídos via regex e heurísticas:

| Campo | Método de Extração |
|---|---|
| **Linha digitável** | Regex para formatos com/sem pontuação |
| **Valor** | Regex `R\$` + normalização monetária (`MoneyConverterTrait`) |
| **Vencimento** | Regex para `dd/mm/yyyy`, `dd.mm.yyyy` + campos rotulados |
| **Beneficiário** | Texto após "Beneficiário:", "Cedente:", "Razão Social:" |
| **CNPJ/CPF** | Regex para formatos `XX.XXX.XXX/XXXX-XX` e `XXX.XXX.XXX-XX` |
| **Banco** | Código de 3 dígitos da linha digitável + tabela de bancos |
| **Nº Documento** | Texto após "Nosso Número:", "Nº Documento:" |

### 4.4 Criação de Pré-Ordem de Pagamento

Campos mapeados automaticamente para `adms_order_payments`:

| Campo OrderPayment | Origem | Valor |
|---|---|---|
| `total_value` | Parser | Valor extraído do boleto |
| `date_payment` | Parser | Data de vencimento |
| `description` | Template | "Boleto - {beneficiário} - {nº documento}" |
| `adms_type_payment_id` | Fixo | 5 (Boleto) |
| `adms_sits_order_pay_id` | Fixo | 1 (Backlog) |
| `obs` | Template | Dados completos extraídos |
| `file_name` | Anexo | PDF original do boleto |
| `source_module` | Fixo | "email_boleto" |
| `source_id` | FK | `adms_boleto_extractions.id` |
| `adms_user_id` | Config | Usuário do sistema (cron) |

**Campos que requerem preenchimento manual:**
- `adms_area_id` — Área/departamento
- `adms_supplier_id` — Fornecedor (pode ser sugerido via CNPJ)
- `manager_id` — Gestor aprovador
- `adms_cost_center_id` — Centro de custo

### 4.5 Detecção de Duplicatas

Três níveis de verificação:

1. **Hash SHA-256 do PDF** — Arquivo idêntico já processado
2. **Linha digitável** — Mesmo boleto (diferentes remetentes)
3. **Valor + Vencimento + CNPJ** — Possível duplicata (flagged para revisão)

### 4.6 Dashboard de Triagem

Tela para a equipe financeira revisar boletos processados:

- **Listagem** com filtros por status (pendente/aprovado/rejeitado/duplicata)
- **Visualização** com dados extraídos lado a lado com preview do PDF
- **Ações:** Aprovar (cria ordem), Rejeitar, Marcar como duplicata, Editar dados extraídos
- **Estatísticas:** Total processado, taxa de acerto, pendentes, criados hoje

---

## 5. Arquitetura de Services

```
EmailPollingService          — Conexão IMAP + download de e-mails
    ├── ImapConnectionService    — Wrapper IMAP (connect, fetch, mark read)
    └── EmailAttachmentService   — Download e armazenamento de anexos

BoletoClassifierService      — Classificação de PDFs como boleto
    └── TextExtractionService    — [EXISTENTE] Extração de texto de PDF

BoletoParserService          — Extração de dados estruturados do boleto
    └── BoletoValidatorService   — Validação de linha digitável, CNPJ, datas

BoletoOrderService           — Criação de pré-ordens no OrderPayments
    ├── AdmsAddOrderPayment      — [EXISTENTE] Criação de ordem
    └── BoletoDeduplicationService — Detecção de duplicatas

BoletoProcessingService      — Orquestrador principal (chamado pelo cron)
    ├── EmailPollingService
    ├── BoletoClassifierService
    ├── BoletoParserService
    ├── BoletoOrderService
    ├── NotificationService      — [EXISTENTE] Envio de notificações
    └── LoggerService            — [EXISTENTE] Logging
```

---

## 6. Integrações

| Módulo | Integração | Impacto |
|---|---|---|
| **OrderPayments** | Criação de pré-ordens com status Backlog | Reutiliza modelo e fluxo existente |
| **TextExtractionService** | Extração de texto de PDFs | Já disponível, sem alterações |
| **NotificationService** | Alertas por e-mail para equipe financeira | Já disponível |
| **SystemNotificationService** | Notificações real-time via WebSocket | Já disponível |
| **LoggerService** | Auditoria de todas as operações | Já disponível |
| **Fornecedores** | Match automático por CNPJ | Leitura da tabela existente |
| **Bancos** | Identificação pelo código do banco | Leitura da tabela existente |

---

## 7. Notificações

| Evento | Canal | Destinatários |
|---|---|---|
| Novos boletos processados | WebSocket + E-mail | Equipe financeira (nível ≤ 2) |
| Boleto pendente de revisão | WebSocket | Equipe financeira |
| Erro no processamento | E-mail | Administrador do sistema |
| Duplicata detectada | WebSocket | Equipe financeira |
| Resumo diário | E-mail | Gestor financeiro |

---

## 8. Permissões e Níveis de Acesso

| Ação | Nível 1 (Super Admin) | Nível 2 (Admin) | Nível 3-5 | Nível 6+ |
|---|---|---|---|---|
| Configurar contas IMAP | ✓ | ✗ | ✗ | ✗ |
| Visualizar boletos triados | ✓ | ✓ | ✗ | ✗ |
| Aprovar/Rejeitar boletos | ✓ | ✓ | ✗ | ✗ |
| Editar dados extraídos | ✓ | ✓ | ✗ | ✗ |
| Visualizar logs de processamento | ✓ | ✓ | ✗ | ✗ |
| Reprocessar e-mail | ✓ | ✗ | ✗ | ✗ |

---

## 9. Limitações e Riscos

### Limitações Técnicas

1. **PDFs baseados em imagem** — O `smalot/pdfparser` não faz OCR. Boletos escaneados não serão processados automaticamente (marcados como "falha na extração")
2. **Variação entre bancos** — Cada banco tem layout diferente de boleto. O parser será otimizado progressivamente
3. **E-mails HTML complexos** — Boletos embutidos no corpo do e-mail (não como anexo) não são suportados na v1

### Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Falsos positivos (não-boleto classificado como boleto) | Média | Baixo | Score conservador + revisão manual obrigatória |
| Dados extraídos incorretos | Alta | Médio | Pré-ordem sempre requer validação humana |
| Duplicatas de boletos | Média | Baixo | Triple-check (hash + linha digitável + valor+data+CNPJ) |
| Credenciais IMAP expostas | Baixa | Alto | Criptografia AES-256 no banco de dados |
| Volume alto de e-mails | Baixa | Médio | Rate limiting + processamento em lotes |
| PDF malicioso | Baixa | Alto | Validação MIME + tamanho máximo + sandboxing |

---

## 10. Evolução Futura (v2.0)

1. **OCR para PDFs escaneados** — Integração com Tesseract ou serviço cloud
2. **Machine Learning** — Classificador treinado com histórico de aprovações/rejeições
3. **Match automático de fornecedor** — Sugestão baseada em CNPJ do beneficiário
4. **Preenchimento automático de área/centro de custo** — Baseado em histórico de pagamentos ao mesmo fornecedor
5. **Suporte a NF-e (XML)** — Processar XMLs de notas fiscais eletrônicas anexados
6. **API de entrada** — Webhook para receber boletos de outros sistemas
7. **Relatórios analíticos** — Dashboard com métricas de processamento, taxa de acerto, tempo médio de aprovação
