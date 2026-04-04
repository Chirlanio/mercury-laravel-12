# Plano de Ação: Envio de Arquivos e Imagens via WhatsApp — Central DP

**Módulo:** Personnel Requests (Solicitações DP)
**Data:** 29/03/2026
**Status:** Planejamento

---

## 1. Contexto

O módulo Central DP integra com WhatsApp via **Evolution API** (self-hosted) para comunicação bidirecional com colaboradores. Atualmente **apenas mensagens de texto** são suportadas — tanto no envio (equipe DP → colaborador) quanto no recebimento (colaborador → equipe DP).

### Estado Atual

| Componente | Arquivo | Capacidade |
|---|---|---|
| WhatsAppService | `Services/WhatsAppService.php` | Apenas `sendMessage()` → `/message/sendText/{instance}` |
| Controller | `Controllers/PersonnelRequests.php:398-476` | `sendMessage()` + `sendWhatsAppMessage()` texto-only |
| Webhook API | `Controllers/Api/V1/DpChatController.php:36-123` | Recebe `message_text` do N8N, sem campo de mídia |
| Model | `Models/AdmsPersonnelRequest.php:100-115` | `createMessage()` com 5 params texto-only |
| DB | `adms_personnel_request_messages` | Colunas: `id, request_id, direction, message, whatsapp_message_id, sent_by_user_id, created_at` |
| View | `Views/personnelRequests/partials/_view_personnel_request.php:149-186` | Chat com textarea simples |
| JavaScript | `assets/js/personnel-requests.js:194-358` | Polling + renderização de texto + envio texto |

### Referência Existente

O módulo **Chat v2.0** (`Controllers/SendFileChat.php`) já implementa upload de arquivos com:
- Whitelist de MIME types (imagens, vídeos, documentos, arquivos compactados)
- UUID v7 para nomes de arquivo
- Validação de tamanho (50MB max)
- Cleanup automático em caso de erro
- Storage em `assets/imagens/chat/`

---

## 2. Fases de Implementação

### Fase 1 — Database (Migration)

**Objetivo:** Adicionar suporte a metadados de mídia na tabela de mensagens.

**Arquivo:** `database/migrations/2026_03_29_add_media_to_personnel_request_messages.sql`

```sql
ALTER TABLE adms_personnel_request_messages
    MODIFY COLUMN message TEXT NULL,
    ADD COLUMN message_type ENUM('text','image','video','file') NOT NULL DEFAULT 'text' AFTER direction,
    ADD COLUMN file_path VARCHAR(500) NULL AFTER message,
    ADD COLUMN file_name VARCHAR(255) NULL AFTER file_path,
    ADD COLUMN file_size BIGINT UNSIGNED NULL AFTER file_name,
    ADD COLUMN file_mime_type VARCHAR(100) NULL AFTER file_size;

ALTER TABLE adms_personnel_request_messages
    ADD INDEX idx_prm_message_type (message_type);
```

**Notas:**
- `message` passa a ser `NULL` — mensagens de mídia podem ter apenas arquivo sem texto (ou com caption)
- `file_path` armazena caminho relativo (`assets/imagens/personnel-requests/uuid.ext`)
- Sem `COLLATE` explícito no ALTER — tabela já usa `utf8mb4_unicode_ci`
- Sem coluna `evolution_media_id` por agora — pode ser adicionada depois se necessário para tracking

**Dependências:** Nenhuma
**Impacto:** Zero — colunas novas com DEFAULT/NULL não quebram código existente

---

### Fase 2 — WhatsAppService: Métodos de Mídia

**Objetivo:** Estender `WhatsAppService` com capacidade de enviar mídia via Evolution API.

**Arquivo:** `app/adms/Services/WhatsAppService.php`

**Novo método:** `sendMedia(string $phone, string $mediaUrl, string $mediaType, ?string $fileName, ?string $caption): bool`

**Endpoints Evolution API:**
- Imagens/Vídeos: `POST /message/sendMedia/{instance}`
- Documentos: `POST /message/sendMedia/{instance}` com `mediatype: 'document'`

**Payload esperado:**
```json
{
    "number": "5511999999999",
    "mediatype": "image",
    "media": "https://dominio.com/assets/imagens/personnel-requests/uuid.jpg",
    "caption": "Legenda opcional",
    "fileName": "documento.pdf"
}
```

**Mapeamento de tipos:**
```php
$mediatype = match ($mediaType) {
    'image' => 'image',
    'video' => 'video',
    'file'  => 'document',
    default => 'document',
};
```

**Padrão:** Mesmo padrão fire-and-forget do `sendMessage()` existente — nunca lança exceção, loga e retorna bool.

**Notas importantes:**
- A Evolution API aceita **URLs HTTP** no campo `media` — o arquivo precisa ser acessível via web
- Para arquivos locais no WAMP, a URL será `http://dominio/mercury/assets/imagens/personnel-requests/uuid.ext`
- Em produção (VPS com Nginx), será `https://dominio/assets/imagens/personnel-requests/uuid.ext`
- Necessário verificar/confirmar o payload exato testando a instância Evolution API instalada (endpoints podem variar entre versões)
- `fileName` é obrigatório para documentos (PDF, DOCX) — sem ele, o WhatsApp mostra "document" genérico
- Timeout aumentado para 30s (upload de vídeos pode demorar)

**Dependências:** Nenhuma
**Riscos:** Payload da Evolution API pode variar conforme versão — testar endpoints antes de implementar

---

### Fase 3 — Model: Suporte a Mídia no `createMessage()`

**Objetivo:** Estender `AdmsPersonnelRequest::createMessage()` para aceitar metadados de arquivo.

**Arquivo:** `app/adms/Models/AdmsPersonnelRequest.php:100-115`

**Assinatura atual:**
```php
public function createMessage(
    int $requestId,
    string $message,
    string $direction = 'incoming',
    ?string $whatsappMessageId = null,
    ?int $sentByUserId = null
): bool
```

**Nova assinatura:**
```php
public function createMessage(
    int $requestId,
    ?string $message,
    string $direction = 'incoming',
    ?string $whatsappMessageId = null,
    ?int $sentByUserId = null,
    string $messageType = 'text',
    ?string $filePath = null,
    ?string $fileName = null,
    ?int $fileSize = null,
    ?string $fileMimeType = null
): bool
```

**Compatibilidade:** 100% retrocompatível — novos parâmetros têm defaults. Chamadas existentes não precisam ser alteradas:
- `PersonnelRequests.php:421` → continua funcionando
- `DpChatController.php:51-56` → continua funcionando

**Dependências:** Fase 1 (migration executada)

---

### Fase 4 — Controller: Endpoint de Upload e Envio

**Objetivo:** Criar endpoint para upload de arquivo + envio via WhatsApp.

**Abordagem:** Adicionar método `sendFile()` diretamente no `PersonnelRequests` controller (não criar controller separado — mantém coerência com `sendMessage()` no mesmo controller).

**Arquivo:** `app/adms/Controllers/PersonnelRequests.php`

**Novo método:** `sendFile(): void`

**Rota:** `POST /personnel-requests/send-file`

**Fluxo:**
1. Receber `id`, `whatsapp_number`, `file` (multipart), `caption` (opcional)
2. Validar: request existe, arquivo dentro dos limites, MIME type permitido
3. Upload: salvar em `assets/imagens/personnel-requests/` com UUID v7
4. Enviar via `WhatsAppService::sendMedia()` com URL pública do arquivo
5. Salvar mensagem no banco com metadados de mídia
6. Retornar JSON com dados para atualização visual

**Validação de arquivo (reutilizar padrão do SendFileChat):**
```php
private const UPLOAD_DIR = 'assets/imagens/personnel-requests/';
private const MAX_FILE_SIZE = 16777216; // 16MB (limite WhatsApp)
private const ALLOWED_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'video/mp4'  => 'mp4',
    'application/pdf' => 'pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
];
```

**Registro de rota:**
```sql
INSERT INTO adms_paginas (controller, metodo, nome_pagina, obs)
VALUES ('personnel-requests', 'send-file', 'Enviar Arquivo DP', 'Endpoint AJAX para envio de arquivo via WhatsApp');

INSERT INTO adms_nivacs_pgs (adms_niveis_acesso_id, adms_pagina_id, permissao, ordem)
SELECT anp.adms_niveis_acesso_id, (SELECT id FROM adms_paginas WHERE controller='personnel-requests' AND metodo='send-file'), anp.permissao, anp.ordem
FROM adms_nivacs_pgs anp
WHERE anp.adms_pagina_id = (SELECT id FROM adms_paginas WHERE controller='personnel-requests' AND metodo='send-message');
```

**Dependências:** Fases 1, 2, 3
**Segurança:**
- CSRF token obrigatório (já validado pelo ConfigController)
- Verificação de permissão via `adms_nivacs_pgs` (automático)
- MIME type checado contra whitelist
- Nome do arquivo substituído por UUID v7 (prevenção de path traversal)

---

### Fase 5 — Webhook: Recebimento de Mídia Incoming

**Objetivo:** Processar mensagens de mídia enviadas pelo colaborador via WhatsApp.

**Arquivo:** `app/adms/Controllers/Api/V1/DpChatController.php:46-84`

**Campos adicionais esperados do N8N/Evolution webhook:**
```json
{
    "whatsapp_number": "5511999999999",
    "message_text": "caption ou vazio",
    "message_id": "whatsapp-msg-id",
    "push_name": "João",
    "message_type": "image",
    "media_url": "https://mmg.whatsapp.net/...",
    "media_mime_type": "image/jpeg",
    "media_file_name": "photo.jpg"
}
```

**Fluxo de recebimento:**
1. Detectar `message_type !== 'text'` no payload
2. Fazer download da `media_url` (URLs do WhatsApp expiram em ~15 minutos)
3. Salvar localmente em `assets/imagens/personnel-requests/incoming/`
4. Registrar mensagem no banco com metadados de mídia
5. Notificar equipe DP via WebSocket

**Download de mídia:**
```php
private function downloadMedia(string $mediaUrl, string $mimeType): ?array
{
    $extension = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'application/pdf' => 'pdf',
        default => 'bin',
    };

    $fileName = Uuid::uuid7()->toString() . '.' . $extension;
    $dir = 'assets/imagens/personnel-requests/incoming/';

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $ch = curl_init($mediaUrl);
    $fp = fopen($dir . $fileName, 'wb');
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($httpCode < 200 || $httpCode >= 300) {
        @unlink($dir . $fileName);
        return null;
    }

    return [
        'path' => $dir . $fileName,
        'name' => $fileName,
        'size' => filesize($dir . $fileName),
        'mime' => $mimeType,
    ];
}
```

**Notas importantes:**
- URLs de mídia do WhatsApp **expiram rapidamente** (~15 min) — download deve ser imediato
- O N8N precisa ser atualizado para enviar os campos de mídia no payload (configuração externa)
- Se o N8N não enviar `media_url`, a mensagem será salva apenas como texto (graceful fallback)
- Subdirectory `incoming/` separa uploads da equipe DP dos recebidos via WhatsApp

**Dependências:** Fases 1, 3 + configuração N8N (externa)
**Riscos:** Requer alteração no workflow N8N para incluir campos de mídia

---

### Fase 6 — Frontend: Interface de Upload e Renderização

**Objetivo:** Adicionar UI para envio de arquivos e exibição de mensagens com mídia.

#### 6A — View: Botão de Anexo no Chat

**Arquivo:** `Views/personnelRequests/partials/_view_personnel_request.php:172-185`

**Alteração:** Substituir o `card-footer` atual por versão com botão de anexo.

```html
<div class="card-footer py-2">
    <div class="d-flex align-items-end">
        <!-- Botão anexo -->
        <div class="me-2">
            <input type="file" class="d-none" id="pr-file-input"
                   data-request-id="<?= $r['id'] ?>"
                   data-whatsapp-number="<?= htmlspecialchars($r['whatsapp_number']) ?>"
                   accept="image/jpeg,image/png,image/webp,video/mp4,.pdf,.docx,.xlsx">
            <button class="btn btn-outline-secondary btn-sm" id="btn-attach-file"
                    title="Anexar arquivo" type="button">
                <i class="fas fa-paperclip"></i>
            </button>
        </div>
        <!-- Textarea -->
        <textarea class="form-control me-2" id="pr-reply-message" rows="1" ...></textarea>
        <!-- Enviar -->
        <button class="btn btn-success btn-sm" id="btn-send-whatsapp" ...>
            <i class="fab fa-whatsapp me-1"></i>Enviar
        </button>
    </div>
    <!-- Preview de arquivo selecionado -->
    <div id="pr-file-preview" class="mt-2 d-none">
        <div class="d-flex align-items-center bg-light rounded p-2">
            <div id="pr-file-preview-thumb" class="me-2"></div>
            <div class="flex-grow-1">
                <div id="pr-file-preview-name" class="small fw-bold"></div>
                <div id="pr-file-preview-size" class="small text-muted"></div>
            </div>
            <button class="btn btn-sm btn-outline-danger" id="btn-cancel-file" type="button">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</div>
```

#### 6B — View: Renderização de Mídia nas Mensagens

**Arquivo:** `Views/personnelRequests/partials/_view_personnel_request.php:159-169`

**Alteração:** No loop de mensagens, renderizar conforme `message_type`.

```php
<?php foreach ($messages as $msg): ?>
    <?php $isIncoming = $msg['direction'] === 'incoming'; ?>
    <div class="mb-2 p-2 rounded ..." style="max-width: 85%; ...">
        <div class="small ...">
            <!-- Header: ícone + nome + hora (igual ao atual) -->
        </div>

        <?php if (($msg['message_type'] ?? 'text') === 'image' && !empty($msg['file_path'])): ?>
            <a href="<?= htmlspecialchars($msg['file_path']) ?>" target="_blank">
                <img src="<?= htmlspecialchars($msg['file_path']) ?>"
                     alt="Imagem" class="img-fluid rounded mt-1" style="max-height: 200px;">
            </a>
            <?php if (!empty($msg['message'])): ?>
                <div class="mt-1"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
            <?php endif; ?>

        <?php elseif (($msg['message_type'] ?? 'text') === 'video' && !empty($msg['file_path'])): ?>
            <video class="rounded mt-1" style="max-width: 100%; max-height: 200px;" controls>
                <source src="<?= htmlspecialchars($msg['file_path']) ?>">
            </video>
            <?php if (!empty($msg['message'])): ?>
                <div class="mt-1"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
            <?php endif; ?>

        <?php elseif (($msg['message_type'] ?? 'text') === 'file' && !empty($msg['file_path'])): ?>
            <a href="<?= htmlspecialchars($msg['file_path']) ?>" target="_blank"
               class="btn btn-sm btn-outline-<?= $isIncoming ? 'primary' : 'light' ?> mt-1">
                <i class="fas fa-file-download me-1"></i>
                <?= htmlspecialchars($msg['file_name'] ?? 'Arquivo') ?>
                <small>(<?= number_format(($msg['file_size'] ?? 0) / 1024, 0) ?> KB)</small>
            </a>
            <?php if (!empty($msg['message'])): ?>
                <div class="mt-1"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
            <?php endif; ?>

        <?php else: ?>
            <?= nl2br(htmlspecialchars($msg['message'] ?? '')) ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
```

#### 6C — JavaScript: Handlers de Upload e Renderização Dinâmica

**Arquivo:** `assets/js/personnel-requests.js`

**Alterações:**

1. **`prRefreshMessages()`** (linhas 214-228): Atualizar renderização para suportar tipos de mídia
2. **`prBindModalEvents()`**: Adicionar handlers para `btn-attach-file`, `pr-file-input`, `btn-cancel-file`
3. **Novo `prSendFileMessage()`**: Envia FormData multipart para `/personnel-requests/send-file`
4. **Preview de arquivo**: Mostrar thumbnail para imagens, ícone para docs
5. **Atualização visual**: Após envio de arquivo, appendar bubble com preview

**Novo handler de envio (botão Send detecta se há arquivo):**
```javascript
btnSend.addEventListener('click', async () => {
    const textarea = document.getElementById('pr-reply-message');
    const fileInput = document.getElementById('pr-file-input');
    const message = textarea.value.trim();
    const hasFile = fileInput && fileInput.files.length > 0;

    if (!message && !hasFile) return;

    if (hasFile) {
        await prSendFileMessage(fileInput.files[0], message);
    } else {
        await prSendTextMessage(message);
    }
});
```

**Dependências:** Fases 1-5

---

## 3. Sugestões de Melhoria

### 3.1 Refatorar `sendWhatsAppMessage()` Duplicado

**Problema:** O controller `PersonnelRequests.php:452-476` tem um método `sendWhatsAppMessage()` que **duplica** a lógica do `WhatsAppService::sendMessage()` — lê `.env` diretamente e faz curl manual.

**Solução:** Substituir por chamada ao service:
```php
// Antes (duplicação):
private function sendWhatsAppMessage(string $number, string $text): void
{
    $evolutionUrl = env('EVOLUTION_API_URL', '...');
    // ...curl manual...
}

// Depois (centralizado):
private function sendWhatsAppMessage(string $number, string $text): void
{
    $whatsApp = new WhatsAppService();
    $whatsApp->sendMessage($number, $text);
}
```

**Benefício:** Centraliza configuração, logs, e normalização de telefone em um único lugar. Quando adicionarmos `sendMedia()`, toda a lógica de conexão com a Evolution API fica no service.

### 3.2 Unificar `sendMessage()` e `sendFile()` no Controller

**Problema:** Ter dois endpoints separados para texto e arquivo adiciona complexidade no frontend (decidir qual chamar).

**Solução:** O endpoint `send-message` aceita tanto texto puro quanto FormData com arquivo. O JS sempre envia FormData — quando há arquivo inclui o campo `file`, quando não há, envia só `message`.

```php
public function sendMessage(): void
{
    $hasFile = !empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;

    if ($hasFile) {
        $this->processFileMessage($id, $existing, ...);
    } else {
        $this->processTextMessage($id, $existing, ...);
    }
}
```

**Benefício:** Um único endpoint, lógica de decisão no backend, frontend mais simples.

### 3.3 Adicionar Validação Real de MIME Type

**Problema:** A validação do `SendFileChat` usa `$_FILES['type']` que vem do navegador (pode ser manipulado).

**Solução:** Usar `finfo_file()` para detectar MIME real:
```php
$finfo = new finfo(FILEINFO_MIME_TYPE);
$realMime = $finfo->file($file['tmp_name']);

if ($realMime !== $file['type'] || !isset(self::ALLOWED_TYPES[$realMime])) {
    return ['valid' => false, 'error' => 'Tipo de arquivo inválido'];
}
```

### 3.4 Configurar `APP_URL` no `.env`

**Problema:** Para enviar mídia via Evolution API, é necessário uma URL pública do arquivo. O controller precisa converter `assets/imagens/...` em `https://dominio/...`.

**Solução:** Adicionar `APP_URL` no `.env`:
```env
APP_URL=https://mercury.dominio.com.br
```

E no controller:
```php
$mediaUrl = rtrim(EnvLoader::get('APP_URL', ''), '/') . '/' . $filePath;
```

### 3.5 Drag-and-Drop na Área de Chat

**Melhoria UX:** Permitir arrastar arquivos diretamente sobre a área de mensagens.

```javascript
const container = document.getElementById('pr-messages-container');
container.addEventListener('dragover', (e) => {
    e.preventDefault();
    container.classList.add('border-primary');
});
container.addEventListener('drop', (e) => {
    e.preventDefault();
    container.classList.remove('border-primary');
    const fileInput = document.getElementById('pr-file-input');
    fileInput.files = e.dataTransfer.files;
    fileInput.dispatchEvent(new Event('change'));
});
```

### 3.6 Compressão de Imagens Antes do Upload

**Melhoria de Performance:** Redimensionar imagens grandes no frontend antes do upload usando Canvas API:
```javascript
async function compressImage(file, maxWidth = 1920, quality = 0.85) {
    if (!file.type.startsWith('image/')) return file;
    // Canvas resize + toBlob...
}
```

**Benefício:** Reduz tempo de upload e consumo de storage para fotos de celular (que podem ter 5-10MB cada).

### 3.7 Cleanup de Arquivos Órfãos (Futuro)

Para evitar acúmulo de arquivos no servidor:
- Cron job semanal que verifica `assets/imagens/personnel-requests/`
- Remove arquivos cujo path não existe em `adms_personnel_request_messages.file_path`
- Ou: definir política de retenção (ex: apagar arquivos de solicitações canceladas após 90 dias)

---

## 4. Checklist de Implementação

### Pré-requisitos
- [ ] Confirmar versão da Evolution API instalada e endpoints de mídia disponíveis
- [ ] Confirmar formato de payload do webhook N8N para mensagens de mídia
- [ ] Definir `APP_URL` no `.env` (necessário para URL pública dos arquivos)

### Fase 1 — Database
- [ ] Criar migration `2026_03_29_add_media_to_personnel_request_messages.sql`
- [ ] Executar migration em desenvolvimento
- [ ] Verificar que queries existentes continuam funcionando

### Fase 2 — WhatsAppService
- [ ] Adicionar método `sendMedia()` ao `WhatsAppService`
- [ ] Testar envio de imagem via Evolution API (endpoint + payload)
- [ ] Testar envio de documento PDF via Evolution API
- [ ] Verificar logs de sucesso/falha

### Fase 3 — Model
- [ ] Atualizar `createMessage()` com novos parâmetros
- [ ] Verificar que chamadas existentes não quebram
- [ ] Adicionar novos campos no `SELECT` de `getById()` (já usa `m.*`, OK)

### Fase 4 — Controller (Envio)
- [ ] Refatorar `sendWhatsAppMessage()` para usar `WhatsAppService` (melhoria 3.1)
- [ ] Implementar `sendFile()` ou unificar em `sendMessage()` (melhoria 3.2)
- [ ] Criar diretório `assets/imagens/personnel-requests/`
- [ ] Registrar rota em `adms_paginas` + `adms_nivacs_pgs`
- [ ] Testar upload + envio WhatsApp end-to-end

### Fase 5 — Webhook (Recebimento)
- [ ] Atualizar `DpChatController::message()` para detectar mídia
- [ ] Implementar `downloadMedia()` para salvar arquivos incoming
- [ ] Criar diretório `assets/imagens/personnel-requests/incoming/`
- [ ] Atualizar workflow N8N para enviar campos de mídia (configuração externa)
- [ ] Testar recebimento de imagem do WhatsApp

### Fase 6 — Frontend
- [ ] Atualizar view: botão de anexo + preview + renderização de mídia
- [ ] Atualizar JS: handlers de upload, renderização dinâmica, drag-and-drop
- [ ] Testar envio de imagem pela UI
- [ ] Testar envio de PDF pela UI
- [ ] Testar visualização de mídia incoming no chat
- [ ] Testar responsividade (mobile)

### Melhorias Opcionais
- [ ] Validação real de MIME com `finfo_file()` (melhoria 3.3)
- [ ] Drag-and-drop na área de chat (melhoria 3.5)
- [ ] Compressão de imagens no frontend (melhoria 3.6)
- [ ] Cron de cleanup de arquivos órfãos (melhoria 3.7)

---

## 5. Ordem de Execução Recomendada

```
Fase 1 (DB) → Fase 3 (Model) → Fase 2 (Service) → Fase 4 (Controller) → Fase 6 (Frontend) → Fase 5 (Webhook)
```

**Justificativa:**
- Fases 1+3 são quick wins sem risco — adicionam colunas e parâmetros opcionais
- Fase 2 (Service) pode ser desenvolvida e testada isoladamente
- Fase 4 (Controller) integra tudo e é o primeiro ponto de teste end-to-end
- Fase 6 (Frontend) pode ser desenvolvida em paralelo com as fases 2-4
- Fase 5 (Webhook) depende de configuração externa (N8N) — deixar por último

**Estimativa de arquivos alterados:**
- 1 arquivo SQL novo (migration)
- 4 arquivos PHP alterados (Service, Model, Controller, DpChatController)
- 1 arquivo PHP view alterado
- 1 arquivo JS alterado

---

## 6. Riscos e Mitigações

| Risco | Impacto | Mitigação |
|---|---|---|
| Evolution API endpoint de mídia diferente do documentado | Alto | Testar endpoints na instância real antes de implementar |
| URLs de mídia incoming expiram antes do download | Médio | Download imediato no webhook; fallback texto se falhar |
| Arquivos grandes sobrecarregam upload | Baixo | Limite 16MB (WhatsApp), compressão frontend opcional |
| N8N não envia campos de mídia | Médio | Graceful fallback — salva como texto se sem `media_url` |
| Storage acumula arquivos indefinidamente | Baixo | Cron cleanup futuro; monitorar uso de disco |
