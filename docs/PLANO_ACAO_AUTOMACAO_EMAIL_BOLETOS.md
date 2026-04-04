# Plano de Ação: Automação de Triagem de Boletos por E-mail

**Referência:** `docs/PROPOSTA_AUTOMACAO_EMAIL_BOLETOS.md` v1.0
**Data:** 2026-03-30
**Última Atualização:** 2026-03-30
**Projeto:** Mercury

---

## Visão Geral

Sistema de automação que monitora caixas de e-mail via IMAP, identifica boletos bancários em anexos PDF, extrai dados financeiros e cria pré-ordens de pagamento no módulo OrderPayments existente. O fluxo completo é: **E-mail → PDF → Classificação → Extração → Pré-Ordem → Revisão Humana → Fluxo Normal de Pagamento**.

### Integrações Principais

| Módulo | Integração | Impacto |
|---|---|---|
| **OrderPayments** | Criação automática de pré-ordens (status Backlog) | Reutiliza modelo existente |
| **TextExtractionService** | Extração de texto de PDFs | Sem alterações |
| **NotificationService** | Alertas para equipe financeira | Sem alterações |
| **SystemNotificationService** | Notificações real-time WebSocket | Sem alterações |
| **LoggerService** | Auditoria completa | Sem alterações |

---

### Status das Fases

| Fase | Descrição | Status | Data Conclusão |
|---|---|---|---|
| **Fase 1** | Infraestrutura — Banco de dados + IMAP + Cron | Planejada | — |
| **Fase 2** | Classificação e Extração de Boletos | Planejada | — |
| **Fase 3** | Integração OrderPayments + Deduplicação | Planejada | — |
| **Fase 4** | Dashboard de Triagem + Notificações | Planejada | — |
| **Fase 5** | Refinamentos + Relatórios + Testes | Planejada | — |

---

## Arquitetura de Dados

### Diagrama de Relacionamentos

```
adms_email_accounts ──1:N──▶ adms_email_messages ──1:N──▶ adms_email_attachments
                                                                    │
                                                                  1:N
                                                                    ▼
                                                          adms_boleto_extractions
                                                                    │
                                                                  N:1
                                                                    ▼
                                                          adms_order_payments [EXISTENTE]

adms_boleto_processing_logs (independente — log de execuções do cron)
```

---

## Fase 1: Infraestrutura — Banco de Dados, IMAP e Cron

### 1.1 Dependência Composer

Adicionar biblioteca IMAP ao projeto:

```bash
composer require php-imap/php-imap:^5.0
```

**Alternativa:** Usar extensão nativa `imap_*` do PHP (já disponível no WAMP). A biblioteca `php-imap/php-imap` é preferível por oferecer API orientada a objetos, tratamento de encoding automático e compatibilidade com PHP 8.0+.

### 1.2 Migration — Tabelas Base

```sql
-- ============================================================
-- Migration: 2026_03_30_01_create_email_boleto_tables.sql
-- Descrição: Tabelas para automação de triagem de boletos
-- ============================================================

-- 1. Contas de e-mail monitoradas
CREATE TABLE IF NOT EXISTS `adms_email_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT 'Nome descritivo da conta',
    `imap_host` VARCHAR(255) NOT NULL COMMENT 'Host IMAP (ex: imap.gmail.com)',
    `imap_port` INT NOT NULL DEFAULT 993 COMMENT 'Porta IMAP',
    `imap_user` VARCHAR(255) NOT NULL COMMENT 'Usuário IMAP',
    `imap_password` VARCHAR(500) NOT NULL COMMENT 'Senha IMAP (criptografada)',
    `imap_encryption` ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl',
    `imap_folder` VARCHAR(100) NOT NULL DEFAULT 'INBOX',
    `polling_interval_min` INT NOT NULL DEFAULT 10 COMMENT 'Intervalo de polling em minutos',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_polled_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. E-mails processados
CREATE TABLE IF NOT EXISTS `adms_email_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `adms_email_account_id` INT NOT NULL,
    `message_uid` VARCHAR(255) NOT NULL COMMENT 'UID IMAP único',
    `from_email` VARCHAR(255) NOT NULL,
    `from_name` VARCHAR(255) NULL,
    `subject` VARCHAR(500) NULL,
    `received_at` DATETIME NOT NULL,
    `status` ENUM('new','processing','processed','error','ignored') NOT NULL DEFAULT 'new',
    `processed_at` DATETIME NULL,
    `error_message` TEXT NULL,
    `raw_headers` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_account_uid` (`adms_email_account_id`, `message_uid`),
    INDEX `idx_status` (`status`),
    INDEX `idx_received` (`received_at`),
    CONSTRAINT `fk_email_msg_account` FOREIGN KEY (`adms_email_account_id`)
        REFERENCES `adms_email_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Anexos dos e-mails
CREATE TABLE IF NOT EXISTS `adms_email_attachments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `adms_email_message_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` INT NOT NULL DEFAULT 0 COMMENT 'Tamanho em bytes',
    `file_hash` VARCHAR(64) NULL COMMENT 'SHA-256 do conteúdo',
    `is_boleto` TINYINT(1) NOT NULL DEFAULT 0,
    `confidence_score` DECIMAL(5,2) NULL COMMENT 'Score 0-100',
    `classification_reason` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_hash` (`file_hash`),
    INDEX `idx_is_boleto` (`is_boleto`),
    CONSTRAINT `fk_attach_message` FOREIGN KEY (`adms_email_message_id`)
        REFERENCES `adms_email_messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Dados extraídos dos boletos
CREATE TABLE IF NOT EXISTS `adms_boleto_extractions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `adms_email_attachment_id` INT NOT NULL,
    `barcode_line` VARCHAR(60) NULL COMMENT 'Linha digitável (47-48 dígitos)',
    `due_date` DATE NULL COMMENT 'Data de vencimento',
    `total_value` DECIMAL(10,2) NULL COMMENT 'Valor do boleto',
    `beneficiary_name` VARCHAR(255) NULL COMMENT 'Nome do beneficiário/cedente',
    `beneficiary_document` VARCHAR(18) NULL COMMENT 'CNPJ/CPF do beneficiário',
    `payer_name` VARCHAR(255) NULL COMMENT 'Nome do pagador/sacado',
    `payer_document` VARCHAR(18) NULL COMMENT 'CNPJ/CPF do pagador',
    `bank_name` VARCHAR(100) NULL COMMENT 'Nome do banco emissor',
    `bank_code` VARCHAR(3) NULL COMMENT 'Código do banco (3 dígitos)',
    `document_number` VARCHAR(25) NULL COMMENT 'Nº do documento/nosso número',
    `extracted_text` TEXT NULL COMMENT 'Texto completo extraído do PDF',
    `extraction_status` ENUM('success','partial','failed') NOT NULL DEFAULT 'success',
    `extraction_errors` TEXT NULL,
    `adms_order_payment_id` INT NULL COMMENT 'FK ordem de pagamento criada',
    `created_by_user_id` INT NULL COMMENT 'Usuário do sistema (cron)',
    `reviewed_by_user_id` INT NULL COMMENT 'Usuário que revisou',
    `reviewed_at` DATETIME NULL,
    `review_status` ENUM('pending','approved','rejected','duplicate') NOT NULL DEFAULT 'pending',
    `review_notes` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_barcode` (`barcode_line`),
    INDEX `idx_review_status` (`review_status`),
    INDEX `idx_order_payment` (`adms_order_payment_id`),
    INDEX `idx_due_date` (`due_date`),
    INDEX `idx_beneficiary_doc` (`beneficiary_document`),
    CONSTRAINT `fk_extract_attachment` FOREIGN KEY (`adms_email_attachment_id`)
        REFERENCES `adms_email_attachments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Log de execuções do cron
CREATE TABLE IF NOT EXISTS `adms_boleto_processing_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_id` CHAR(36) NOT NULL COMMENT 'UUID v7 do lote',
    `action` VARCHAR(50) NOT NULL,
    `details` TEXT NULL COMMENT 'Detalhes em JSON',
    `emails_fetched` INT NOT NULL DEFAULT 0,
    `boletos_found` INT NOT NULL DEFAULT 0,
    `orders_created` INT NOT NULL DEFAULT 0,
    `errors_count` INT NOT NULL DEFAULT 0,
    `execution_time_ms` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_batch` (`batch_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.3 Constantes de Configuração

Adicionar ao `.env`:

```env
# Email Boleto Automation
BOLETO_CRON_USER_ID=1
BOLETO_UPLOAD_DIR=uploads/boletos
BOLETO_MAX_FILE_SIZE=10485760
BOLETO_MIN_CONFIDENCE=70
BOLETO_ENCRYPTION_KEY=<chave-AES-256>
```

Adicionar ao `core/Config.php`:

```php
define('BOLETO_CRON_USER_ID', $_ENV['BOLETO_CRON_USER_ID'] ?? 1);
define('BOLETO_UPLOAD_DIR', $_ENV['BOLETO_UPLOAD_DIR'] ?? 'uploads/boletos');
define('BOLETO_MAX_FILE_SIZE', $_ENV['BOLETO_MAX_FILE_SIZE'] ?? 10485760);
define('BOLETO_MIN_CONFIDENCE', $_ENV['BOLETO_MIN_CONFIDENCE'] ?? 70);
```

### 1.4 ImapConnectionService

Service para abstração da conexão IMAP.

| Arquivo | Descrição |
|---|---|
| `app/adms/Services/ImapConnectionService.php` | Wrapper IMAP: connect, fetch unseen, download attachments, mark read |

**Métodos principais:**

```php
class ImapConnectionService
{
    public function connect(array $accountConfig): bool
    public function fetchUnseenMessages(int $limit = 50): array
    public function getAttachments(int $messageUid): array
    public function downloadAttachment(object $attachment, string $savePath): string
    public function markAsRead(int $messageUid): bool
    public function disconnect(): void
}
```

**Responsabilidades:**
- Conectar ao servidor IMAP com SSL/TLS
- Buscar e-mails não lidos (flag `\Unseen`)
- Baixar anexos PDF para diretório configurado
- Marcar e-mails como lidos após processamento
- Tratamento de erros de conexão e timeout
- Descriptografia da senha IMAP armazenada no banco

### 1.5 EmailAttachmentService

| Arquivo | Descrição |
|---|---|
| `app/adms/Services/EmailAttachmentService.php` | Gerenciamento de anexos: download, validação, hash |

**Responsabilidades:**
- Validar MIME type (apenas `application/pdf`)
- Validar tamanho do arquivo (max 10MB)
- Gerar nome único para arquivo (UUID + extensão original)
- Calcular hash SHA-256 do conteúdo
- Salvar em `uploads/boletos/YYYY-MM/`
- Limpeza de arquivos antigos (retenção configurável)

### 1.6 Cron Job

| Arquivo | Descrição |
|---|---|
| `bin/cron-boleto-email.php` | Script cron para polling de e-mails |

**Estrutura do cron:**

```php
#!/usr/bin/env php
<?php
/**
 * Cron: Polling de e-mails para triagem de boletos
 * Uso: php bin/cron-boleto-email.php
 * Crontab: */10 * * * * php /path/to/mercury/bin/cron-boleto-email.php >> /var/log/mercury-boleto-cron.log 2>&1
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap (mesmo padrão de vacation-cron.php)
$dotenv = new EnvLoader(__DIR__ . '/../');
// ... setup de constantes e timezone

$processor = new BoletoProcessingService();
$processor->execute();
```

**Crontab sugerido:**
```bash
# Polling de e-mails a cada 10 minutos (horário comercial)
*/10 8-18 * * 1-5 php /path/to/mercury/bin/cron-boleto-email.php >> /var/log/mercury-boleto-cron.log 2>&1
```

### 1.7 Entregável da Fase 1

- [x] Tabelas criadas no banco de dados (5 tabelas)
- [x] Dependência `php-imap/php-imap` instalada
- [x] `ImapConnectionService` funcional (connect + fetch + download)
- [x] `EmailAttachmentService` funcional (validação + hash + storage)
- [x] Cron job configurado e testável manualmente
- [x] Constantes no `.env` e `Config.php`
- [x] Diretório `uploads/boletos/` criado

---

## Fase 2: Classificação e Extração de Boletos

### 2.1 BoletoClassifierService

| Arquivo | Descrição |
|---|---|
| `app/adms/Services/BoletoClassifierService.php` | Classificação de PDFs como boleto via scoring |

**Algoritmo de classificação:**

```php
class BoletoClassifierService
{
    private const THRESHOLD_CONFIRMED = 70;
    private const THRESHOLD_POSSIBLE  = 40;

    public function classify(string $extractedText): array
    {
        // Retorna: ['is_boleto' => bool, 'score' => float, 'reason' => string]
    }

    private function checkBarcodePattern(string $text): int    // +40 pontos
    private function checkDigitableLine(string $text): int     // +30 pontos
    private function checkPrimaryKeywords(string $text): int   // +15 pontos
    private function checkSecondaryKeywords(string $text): int // +10 pontos
    private function checkMonetaryValue(string $text): int     // +5 pontos
}
```

**Padrões Regex principais:**

```php
// Linha digitável (com pontuação)
'/\d{5}[.\s]?\d{5}\s?\d{5}[.\s]?\d{6}\s?\d{5}[.\s]?\d{6}\s?\d\s?\d{14}/'

// Linha digitável (sem pontuação — 47 dígitos)
'/\b\d{47}\b/'

// Código de barras numérico (44 dígitos)
'/\b\d{44}\b/'

// Valor monetário brasileiro
'/R\$\s?\d{1,3}(?:\.\d{3})*,\d{2}/'

// Palavras-chave primárias (case-insensitive)
'/\b(?:vencimento|benefici[aá]rio|cedente|sacado|pagador|linha\s+digit[aá]vel)\b/i'

// Palavras-chave secundárias
'/\b(?:boleto|cobran[cç]a|banco|ag[eê]ncia|parcela|nosso\s+n[uú]mero)\b/i'
```

### 2.2 BoletoParserService

| Arquivo | Descrição |
|---|---|
| `app/adms/Services/BoletoParserService.php` | Extração de dados estruturados do texto do boleto |

**Métodos de extração:**

```php
class BoletoParserService
{
    public function parse(string $extractedText): array
    {
        // Retorna array com todos os campos extraídos
        return [
            'barcode_line'         => $this->extractBarcodeLine($text),
            'due_date'             => $this->extractDueDate($text),
            'total_value'          => $this->extractValue($text),
            'beneficiary_name'     => $this->extractBeneficiary($text),
            'beneficiary_document' => $this->extractBeneficiaryDocument($text),
            'payer_name'           => $this->extractPayer($text),
            'payer_document'       => $this->extractPayerDocument($text),
            'bank_code'            => $this->extractBankCode($text),
            'bank_name'            => $this->resolveBankName($bankCode),
            'document_number'      => $this->extractDocumentNumber($text),
        ];
    }
}
```

**Estratégias de extração por campo:**

| Campo | Estratégia | Fallback |
|---|---|---|
| `barcode_line` | Regex padrão FEBRABAN | Busca 47 dígitos consecutivos |
| `due_date` | Label "Vencimento:" + data | Qualquer data futura no texto |
| `total_value` | Label "Valor:" ou "Valor do Documento:" + R$ | Maior valor R$ no texto |
| `beneficiary_name` | Label "Beneficiário:" ou "Cedente:" | Texto após CNPJ do cedente |
| `beneficiary_document` | Regex CNPJ após "Beneficiário" | Primeiro CNPJ encontrado |
| `bank_code` | 3 primeiros dígitos da linha digitável | Label "Banco:" |
| `document_number` | Label "Nosso Número:" | Label "Nº Documento:" |

### 2.3 BoletoValidatorService

| Arquivo | Descrição |
|---|---|
| `app/adms/Services/BoletoValidatorService.php` | Validação de dados extraídos |

**Validações:**

```php
class BoletoValidatorService
{
    public function validateBarcodeLine(string $line): bool     // Módulo 10/11 FEBRABAN
    public function validateCnpj(string $cnpj): bool           // Dígito verificador
    public function validateCpf(string $cpf): bool             // Dígito verificador
    public function validateDueDate(string $date): bool        // Data válida e não muito antiga
    public function validateValue(float $value): bool          // Valor > 0 e < limite razoável
    public function validateExtraction(array $data): array     // Validação completa, retorna erros
}
```

### 2.4 Testes da Fase 2

| Arquivo | Testes |
|---|---|
| `tests/BoletoEmail/BoletoClassifierServiceTest.php` | ~25 testes (scores, thresholds, edge cases) |
| `tests/BoletoEmail/BoletoParserServiceTest.php` | ~30 testes (extração por campo, formatos variados) |
| `tests/BoletoEmail/BoletoValidatorServiceTest.php` | ~20 testes (validação de linha digitável, CNPJ, CPF) |

**Fixtures de teste:**
```
tests/BoletoEmail/fixtures/
├── boleto_itau.txt          # Texto extraído de boleto Itaú
├── boleto_bradesco.txt      # Texto extraído de boleto Bradesco
├── boleto_bb.txt            # Texto extraído de boleto Banco do Brasil
├── boleto_santander.txt     # Texto extraído de boleto Santander
├── boleto_caixa.txt         # Texto extraído de boleto Caixa
├── nao_boleto_nf.txt        # Nota fiscal (falso positivo)
├── nao_boleto_contrato.txt  # Contrato (falso positivo)
└── boleto_parcial.txt       # Boleto com dados incompletos
```

### 2.5 Entregável da Fase 2

- [x] `BoletoClassifierService` com scoring multi-critério
- [x] `BoletoParserService` com extração de todos os campos
- [x] `BoletoValidatorService` com validação FEBRABAN
- [x] Testes unitários com fixtures de múltiplos bancos
- [x] Documentação dos padrões regex utilizados

---

## Fase 3: Integração OrderPayments + Deduplicação

### 3.1 BoletoDeduplicationService

| Arquivo | Descrição |
|---|---|
| `app/adms/Services/BoletoDeduplicationService.php` | Detecção de boletos duplicados em 3 níveis |

**Algoritmo de deduplicação:**

```php
class BoletoDeduplicationService
{
    /**
     * Verifica se o boleto já foi processado
     * @return array ['is_duplicate' => bool, 'level' => string, 'existing_id' => ?int]
     */
    public function check(string $fileHash, ?string $barcodeLine, ?float $value, ?string $dueDate, ?string $beneficiaryDoc): array
    {
        // Nível 1: Hash SHA-256 idêntico (arquivo exato)
        if ($match = $this->checkFileHash($fileHash)) {
            return ['is_duplicate' => true, 'level' => 'exact_file', 'existing_id' => $match];
        }

        // Nível 2: Linha digitável idêntica (mesmo boleto, PDF diferente)
        if ($barcodeLine && $match = $this->checkBarcodeLine($barcodeLine)) {
            return ['is_duplicate' => true, 'level' => 'barcode', 'existing_id' => $match];
        }

        // Nível 3: Valor + Vencimento + CNPJ (possível duplicata)
        if ($value && $dueDate && $beneficiaryDoc) {
            if ($match = $this->checkValueDateDocument($value, $dueDate, $beneficiaryDoc)) {
                return ['is_duplicate' => true, 'level' => 'possible', 'existing_id' => $match];
            }
        }

        return ['is_duplicate' => false, 'level' => null, 'existing_id' => null];
    }
}
```

### 3.2 BoletoOrderService

| Arquivo | Descrição |
|---|---|
| `app/adms/Services/BoletoOrderService.php` | Criação de pré-ordens de pagamento a partir de dados extraídos |

**Fluxo de criação:**

```php
class BoletoOrderService
{
    public function createPreOrder(array $boletoData, int $attachmentId): ?int
    {
        // 1. Verificar duplicata
        $dupCheck = $this->deduplicationService->check(...);
        if ($dupCheck['is_duplicate'] && $dupCheck['level'] !== 'possible') {
            // Duplicata exata → não criar ordem
            return null;
        }

        // 2. Tentar match de fornecedor por CNPJ
        $supplierId = $this->matchSupplier($boletoData['beneficiary_document']);

        // 3. Montar dados da ordem
        $orderData = [
            'total_value'            => $boletoData['total_value'],
            'date_payment'           => $boletoData['due_date'],
            'description'            => $this->buildDescription($boletoData),
            'adms_type_payment_id'   => 5, // Boleto
            'adms_sits_order_pay_id' => 1, // Backlog
            'adms_supplier_id'       => $supplierId,
            'adms_user_id'           => BOLETO_CRON_USER_ID,
            'obs'                    => $this->buildObservations($boletoData),
            'source_module'          => 'email_boleto',
            'source_id'             => $attachmentId,
            'created_date'           => date('Y-m-d'),
            'advance'                => 2, // Não
            'diff_payment_advance'   => $boletoData['total_value'],
        ];

        // 4. Criar ordem via modelo existente
        // 5. Copiar PDF do boleto como anexo da ordem
        // 6. Registrar link na tabela adms_boleto_extractions

        return $orderId;
    }

    private function matchSupplier(?string $cnpj): ?int
    {
        // Busca fornecedor na tabela adms_suppliers pelo CNPJ
        // Retorna null se não encontrado (preenchimento manual)
    }

    private function buildDescription(array $data): string
    {
        // "Boleto - {beneficiary_name} - Doc: {document_number}"
    }

    private function buildObservations(array $data): string
    {
        // Texto formatado com todos os dados extraídos
        // Inclui: banco, linha digitável, CNPJ, remetente do e-mail
    }
}
```

### 3.3 BoletoProcessingService (Orquestrador)

| Arquivo | Descrição |
|---|---|
| `app/adms/Services/BoletoProcessingService.php` | Orquestrador principal — chamado pelo cron |

**Fluxo completo:**

```php
class BoletoProcessingService
{
    public function execute(): void
    {
        $batchId = Uuid::uuid7()->toString();
        $startTime = microtime(true);
        $stats = ['emails_fetched' => 0, 'boletos_found' => 0, 'orders_created' => 0, 'errors' => 0];

        try {
            // 1. Buscar contas ativas
            $accounts = $this->getActiveAccounts();

            foreach ($accounts as $account) {
                // 2. Conectar via IMAP
                $this->imapService->connect($account);

                // 3. Buscar e-mails não lidos
                $messages = $this->imapService->fetchUnseenMessages(50);
                $stats['emails_fetched'] += count($messages);

                foreach ($messages as $message) {
                    // 4. Registrar e-mail no banco
                    $messageId = $this->saveMessage($account['id'], $message);

                    // 5. Processar anexos PDF
                    $attachments = $this->imapService->getAttachments($message['uid']);

                    foreach ($attachments as $attachment) {
                        if ($attachment['mime_type'] !== 'application/pdf') continue;

                        // 6. Download e hash
                        $filePath = $this->attachmentService->save($attachment);
                        $fileHash = hash_file('sha256', $filePath);

                        $attachId = $this->saveAttachment($messageId, $attachment, $filePath, $fileHash);

                        // 7. Extrair texto do PDF
                        $text = TextExtractionService::extract($filePath, 'application/pdf');

                        // 8. Classificar
                        $classification = $this->classifier->classify($text);

                        // 9. Se é boleto (score >= threshold)
                        if ($classification['score'] >= BOLETO_MIN_CONFIDENCE) {
                            $stats['boletos_found']++;

                            // 10. Parse dos dados
                            $boletoData = $this->parser->parse($text);

                            // 11. Validar dados
                            $errors = $this->validator->validateExtraction($boletoData);

                            // 12. Salvar extração
                            $extractionId = $this->saveExtraction($attachId, $boletoData, $text, $errors);

                            // 13. Criar pré-ordem (se dados mínimos presentes)
                            if ($boletoData['total_value'] && $boletoData['due_date']) {
                                $orderId = $this->orderService->createPreOrder($boletoData, $extractionId);
                                if ($orderId) $stats['orders_created']++;
                            }
                        }

                        $this->updateAttachmentClassification($attachId, $classification);
                    }

                    // 14. Marcar e-mail como processado
                    $this->imapService->markAsRead($message['uid']);
                    $this->updateMessageStatus($messageId, 'processed');
                }

                // 15. Atualizar last_polled_at da conta
                $this->updateLastPolled($account['id']);
                $this->imapService->disconnect();
            }

            // 16. Notificar equipe financeira (se houve boletos)
            if ($stats['boletos_found'] > 0) {
                $this->notifyFinancialTeam($stats);
            }

        } catch (\Throwable $e) {
            $stats['errors']++;
            LoggerService::error('BOLETO_PROCESSING_FAILED', $e->getMessage(), [
                'batch_id' => $batchId,
                'trace' => $e->getTraceAsString()
            ]);
        }

        // 17. Log de execução
        $this->logExecution($batchId, $stats, $startTime);
    }
}
```

### 3.4 Testes da Fase 3

| Arquivo | Testes |
|---|---|
| `tests/BoletoEmail/BoletoDeduplicationServiceTest.php` | ~15 testes |
| `tests/BoletoEmail/BoletoOrderServiceTest.php` | ~20 testes |
| `tests/BoletoEmail/BoletoProcessingServiceTest.php` | ~15 testes (integração) |

### 3.5 Entregável da Fase 3

- [x] `BoletoDeduplicationService` com 3 níveis de verificação
- [x] `BoletoOrderService` com criação automática de pré-ordens
- [x] `BoletoProcessingService` orquestrador completo
- [x] Match automático de fornecedor por CNPJ
- [x] Link bidirecional: extração ↔ ordem de pagamento
- [x] Testes unitários e de integração

---

## Fase 4: Dashboard de Triagem + Notificações

### 4.1 Controllers

| Arquivo | Tipo | Descrição |
|---|---|---|
| `app/adms/Controllers/BoletoTriage.php` | Controller | Listagem principal de boletos triados |
| `app/adms/Controllers/ViewBoletoTriage.php` | Controller | Visualização detalhada + preview PDF |
| `app/adms/Controllers/ApproveBoletoTriage.php` | Controller | Ações: aprovar, rejeitar, marcar duplicata |
| `app/adms/Controllers/EmailAccounts.php` | Controller | CRUD de contas de e-mail (AbstractConfigController) |
| `app/adms/Controllers/BoletoProcessingLogs.php` | Controller | Visualização de logs de processamento |

### 4.2 Models

| Arquivo | Tipo | Descrição |
|---|---|---|
| `app/adms/Models/AdmsListBoletoTriages.php` | Model | Listagem com filtros e paginação |
| `app/adms/Models/AdmsViewBoletoTriage.php` | Model | Detalhes do boleto + dados extraídos |
| `app/adms/Models/AdmsBoletoTriage.php` | Model | Ações de aprovação/rejeição |
| `app/adms/Models/AdmsStatisticsBoletoTriages.php` | Model | Cards de estatísticas |
| `app/adms/Models/AdmsEmailAccount.php` | Model | CRUD de contas IMAP |
| `app/adms/Models/AdmsListBoletoProcessingLogs.php` | Model | Listagem de logs |

### 4.3 Views

| Arquivo | Descrição |
|---|---|
| `app/adms/Views/boletoTriage/loadBoletoTriage.php` | Página principal com stats + listagem |
| `app/adms/Views/boletoTriage/listBoletoTriage.php` | Tabela AJAX com filtros |
| `app/adms/Views/boletoTriage/partials/_view_boleto_triage_modal.php` | Modal com dados extraídos + preview PDF |
| `app/adms/Views/boletoTriage/partials/_approve_boleto_triage_modal.php` | Modal de aprovação com campos editáveis |
| `app/adms/Views/boletoTriage/partials/_reject_boleto_triage_modal.php` | Modal de rejeição |
| `app/adms/Views/emailAccounts/loadEmailAccounts.php` | Configuração de contas IMAP |
| `app/adms/Views/boletoProcessingLogs/loadBoletoProcessingLogs.php` | Logs de execução |

### 4.4 JavaScript

| Arquivo | Descrição |
|---|---|
| `assets/js/boleto-triage.js` | Listagem, filtros, ações em lote, preview PDF |
| `assets/js/email-accounts.js` | CRUD de contas IMAP, teste de conexão |

### 4.5 Funcionalidades do Dashboard

#### Cards de Estatísticas

```
┌─────────────┐ ┌──────────────┐ ┌─────────────┐ ┌──────────────┐
│  Pendentes   │ │  Aprovados   │ │  Rejeitados  │ │  Duplicatas  │
│     12       │ │     45       │ │      3       │ │      5       │
│   R$ 8.540   │ │  R$ 32.100   │ │   R$ 1.200   │ │   R$ 3.400   │
└─────────────┘ └──────────────┘ └─────────────┘ └──────────────┘
```

#### Filtros

- **Status:** Pendente / Aprovado / Rejeitado / Duplicata
- **Período:** Data de recebimento do e-mail
- **Banco:** Código do banco emissor
- **Valor:** Faixa de valores (mín-máx)
- **Confiança:** Score de classificação (baixa/média/alta)
- **Conta de e-mail:** Filtro por conta monitorada

#### Modal de Visualização

Layout lado a lado:
- **Esquerda:** Dados extraídos (editáveis) — valor, vencimento, beneficiário, CNPJ, linha digitável
- **Direita:** Preview do PDF em iframe
- **Inferior:** Metadados — remetente, assunto, data, score de confiança

#### Modal de Aprovação

- Campos pré-preenchidos dos dados extraídos (editáveis)
- **Campos obrigatórios para completar a ordem:**
  - Área/Departamento (select)
  - Centro de Custo (select)
  - Gestor Aprovador (select)
  - Fornecedor (auto-sugerido por CNPJ, editável)
- Botão "Aprovar e Criar Ordem" → cria/atualiza a ordem em OrderPayments

#### Ações em Lote

- Selecionar múltiplos boletos pendentes
- "Aprovar Selecionados" (com campos comuns pré-preenchidos)
- "Rejeitar Selecionados" (com motivo)

### 4.6 Notificações

#### WebSocket (Real-time)

```php
// Ao processar novos boletos
SystemNotificationService::notifyUsers(
    $financialUserIds,
    'boleto_triage',
    "Novos boletos para triagem: {$count} encontrados",
    ['count' => $count, 'total_value' => $totalValue]
);
```

**Evento no frontend:**
```javascript
MercuryWS.on('notification.new', function(data) {
    if (data.category === 'boleto_triage') {
        // Refresh listagem e estatísticas
        loadBoletoTriageList();
        loadBoletoTriageStats();
    }
});
```

#### E-mail (Resumo)

- Notificação imediata quando novos boletos são identificados
- Resumo diário (opcional) com total processado, pendentes, valor acumulado

### 4.7 Rotas (adms_paginas)

```sql
-- Triagem de Boletos
INSERT INTO adms_paginas (controller, metodo, nome_pagina, tipo) VALUES
('boleto-triage', 'index', 'Triagem de Boletos', 'menu'),
('view-boleto-triage', 'view', 'Visualizar Boleto Triado', 'botao'),
('approve-boleto-triage', 'approve', 'Aprovar Boleto Triado', 'botao'),
('approve-boleto-triage', 'reject', 'Rejeitar Boleto Triado', 'botao'),
('approve-boleto-triage', 'mark-duplicate', 'Marcar Boleto Duplicado', 'botao'),
('approve-boleto-triage', 'bulk-approve', 'Aprovar Boletos em Lote', 'botao'),

-- Contas de E-mail
('email-accounts', 'index', 'Contas de E-mail', 'menu'),
('email-accounts', 'create', 'Adicionar Conta de E-mail', 'botao'),
('email-accounts', 'edit', 'Editar Conta de E-mail', 'botao'),
('email-accounts', 'delete', 'Excluir Conta de E-mail', 'botao'),
('email-accounts', 'test-connection', 'Testar Conexão IMAP', 'botao'),

-- Logs de Processamento
('boleto-processing-logs', 'index', 'Logs de Processamento de Boletos', 'menu');
```

### 4.8 Entregável da Fase 4

- [x] Dashboard de triagem com listagem, filtros e estatísticas
- [x] Modal de visualização com preview do PDF
- [x] Modal de aprovação com campos complementares
- [x] Ações em lote (aprovar/rejeitar múltiplos)
- [x] CRUD de contas de e-mail IMAP
- [x] Botão de teste de conexão IMAP
- [x] Tela de logs de processamento
- [x] Notificações WebSocket + e-mail
- [x] Rotas e permissões cadastradas

---

## Fase 5: Refinamentos, Relatórios e Testes

### 5.1 Refinamentos

#### Match Automático de Fornecedor

```php
// Busca na tabela de fornecedores por CNPJ
// Se encontrado: preenche automaticamente na pré-ordem
// Se não encontrado: sugere criação de novo fornecedor
private function matchSupplier(?string $cnpj): ?array
{
    if (!$cnpj) return null;

    $read = new AdmsRead();
    $read->fullRead(
        "SELECT id, nome FROM adms_suppliers WHERE cnpj = :cnpj AND deleted_at IS NULL LIMIT 1",
        "cnpj=" . preg_replace('/\D/', '', $cnpj)
    );

    return $read->getResult() ? $read->getResult()[0] : null;
}
```

#### Sugestão de Área/Centro de Custo por Histórico

```php
// Busca ordens anteriores do mesmo fornecedor/CNPJ
// Sugere área e centro de custo mais frequentes
private function suggestAllocation(int $supplierId): ?array
{
    $read = new AdmsRead();
    $read->fullRead(
        "SELECT adms_area_id, adms_cost_center_id, COUNT(*) as freq
         FROM adms_order_payments
         WHERE adms_supplier_id = :suppId AND deleted_at IS NULL
         GROUP BY adms_area_id, adms_cost_center_id
         ORDER BY freq DESC LIMIT 1",
        "suppId={$supplierId}"
    );

    return $read->getResult() ? $read->getResult()[0] : null;
}
```

#### Reprocessamento Manual

- Botão na UI para reprocessar um e-mail específico
- Útil quando o parser é atualizado e queremos re-extrair dados
- Apenas Super Admin (nível 1)

### 5.2 Relatórios

#### Relatório de Processamento (PDF)

Gerado via DomPDF, contendo:
- Período de análise
- Total de e-mails processados
- Taxa de identificação (boletos / total PDFs)
- Taxa de aprovação (aprovados / total boletos)
- Valor total processado
- Top fornecedores por volume
- Erros e falhas

#### Métricas no Dashboard

| Métrica | Descrição |
|---|---|
| Taxa de acerto | % de classificações corretas (aprovados / (aprovados + rejeitados)) |
| Tempo médio de revisão | Tempo entre criação e aprovação/rejeição |
| Volume diário | Gráfico de boletos processados por dia |
| Top remetentes | E-mails que mais enviam boletos |
| Distribuição por banco | Pizza chart com bancos mais frequentes |

### 5.3 Testes Completos

| Diretório | Arquivo | Testes |
|---|---|---|
| `tests/BoletoEmail/` | `BoletoClassifierServiceTest.php` | ~25 |
| `tests/BoletoEmail/` | `BoletoParserServiceTest.php` | ~30 |
| `tests/BoletoEmail/` | `BoletoValidatorServiceTest.php` | ~20 |
| `tests/BoletoEmail/` | `BoletoDeduplicationServiceTest.php` | ~15 |
| `tests/BoletoEmail/` | `BoletoOrderServiceTest.php` | ~20 |
| `tests/BoletoEmail/` | `BoletoProcessingServiceTest.php` | ~15 |
| `tests/BoletoEmail/` | `ImapConnectionServiceTest.php` | ~10 |
| `tests/BoletoEmail/` | `AdmsListBoletoTriagesTest.php` | ~10 |
| `tests/BoletoEmail/` | `AdmsBoletoTriageTest.php` | ~15 |
| **Total** | | **~160 testes** |

### 5.4 Entregável da Fase 5

- [x] Match automático de fornecedor por CNPJ
- [x] Sugestão de área/centro de custo por histórico
- [x] Reprocessamento manual de e-mails
- [x] Relatório PDF de processamento
- [x] Métricas e gráficos no dashboard
- [x] ~160 testes unitários cobrindo todos os services
- [x] Documentação técnica atualizada

---

## Resumo de Arquivos por Fase

### Fase 1 — Infraestrutura (7 arquivos)

| Arquivo | Tipo |
|---|---|
| `database/migrations/2026_03_30_01_create_email_boleto_tables.sql` | Migration |
| `app/adms/Services/ImapConnectionService.php` | Service |
| `app/adms/Services/EmailAttachmentService.php` | Service |
| `bin/cron-boleto-email.php` | Cron |
| `.env` (alteração) | Config |
| `core/Config.php` (alteração) | Config |
| `composer.json` (alteração) | Dependência |

### Fase 2 — Classificação e Extração (3 arquivos + fixtures)

| Arquivo | Tipo |
|---|---|
| `app/adms/Services/BoletoClassifierService.php` | Service |
| `app/adms/Services/BoletoParserService.php` | Service |
| `app/adms/Services/BoletoValidatorService.php` | Service |
| `tests/BoletoEmail/fixtures/` | Fixtures (8 arquivos) |

### Fase 3 — Integração OrderPayments (3 arquivos)

| Arquivo | Tipo |
|---|---|
| `app/adms/Services/BoletoDeduplicationService.php` | Service |
| `app/adms/Services/BoletoOrderService.php` | Service |
| `app/adms/Services/BoletoProcessingService.php` | Service |

### Fase 4 — Dashboard e UI (15+ arquivos)

| Arquivo | Tipo |
|---|---|
| `app/adms/Controllers/BoletoTriage.php` | Controller |
| `app/adms/Controllers/ViewBoletoTriage.php` | Controller |
| `app/adms/Controllers/ApproveBoletoTriage.php` | Controller |
| `app/adms/Controllers/EmailAccounts.php` | Controller |
| `app/adms/Controllers/BoletoProcessingLogs.php` | Controller |
| `app/adms/Models/AdmsListBoletoTriages.php` | Model |
| `app/adms/Models/AdmsViewBoletoTriage.php` | Model |
| `app/adms/Models/AdmsBoletoTriage.php` | Model |
| `app/adms/Models/AdmsStatisticsBoletoTriages.php` | Model |
| `app/adms/Models/AdmsEmailAccount.php` | Model |
| `app/adms/Models/AdmsListBoletoProcessingLogs.php` | Model |
| `app/adms/Views/boletoTriage/*.php` | Views (5 arquivos) |
| `app/adms/Views/emailAccounts/*.php` | Views (2 arquivos) |
| `assets/js/boleto-triage.js` | JavaScript |
| `assets/js/email-accounts.js` | JavaScript |

### Fase 5 — Refinamentos e Testes (10 arquivos)

| Arquivo | Tipo |
|---|---|
| `tests/BoletoEmail/*.php` | Testes (9 arquivos) |
| `docs/ANALISE_MODULO_BOLETO_EMAIL.md` | Documentação |

**Total estimado: ~45 arquivos novos + 3 alterações em existentes**

---

## Dependências Externas

| Pacote | Versão | Uso |
|---|---|---|
| `php-imap/php-imap` | ^5.0 | Conexão IMAP para leitura de e-mails |
| `smalot/pdfparser` | ^2.12 | **[JÁ INSTALADO]** Extração de texto de PDF |
| `dompdf/dompdf` | ^3.0 | **[JÁ INSTALADO]** Geração de relatórios PDF |
| `ramsey/uuid` | ^4.7 | **[JÁ INSTALADO]** UUIDs para batch_id |

**Nova dependência:** apenas `php-imap/php-imap`

---

## Configuração de Produção

### Crontab

```bash
# Polling de e-mails para boletos (a cada 10 min, horário comercial)
*/10 8-18 * * 1-5 php /var/www/mercury/bin/cron-boleto-email.php >> /var/log/mercury-boleto-cron.log 2>&1

# Limpeza de PDFs antigos (mensal, 1º dia às 3h)
0 3 1 * * php /var/www/mercury/bin/cleanup-boleto-files.php >> /var/log/mercury-cleanup.log 2>&1
```

### Diretório de Upload

```bash
mkdir -p uploads/boletos
chmod 750 uploads/boletos
```

### Segurança

- Senhas IMAP armazenadas com criptografia AES-256 no banco
- Diretório `uploads/boletos/` fora do document root público (ou protegido por `.htaccess`)
- Rate limiting no cron: máximo 50 e-mails por execução
- Validação MIME type antes de processar qualquer anexo
- Tamanho máximo de arquivo: 10MB
