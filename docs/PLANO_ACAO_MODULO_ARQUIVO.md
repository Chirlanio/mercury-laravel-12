# Plano de Ação — Refatoração Módulo Arquivo → Files

**Data:** 26/03/2026
**Versão:** 1.0
**Status:** Planejado
**Baseado em:** Análise completa do módulo Arquivo (26/03/2026)

---

## Índice

1. [Fase 1 — Correções Críticas de Segurança](#fase-1)
2. [Fase 2 — Banco de Dados (Migração de Schema)](#fase-2)
3. [Fase 3 — Refatoração Backend (Controllers + Models)](#fase-3)
4. [Fase 4 — Refatoração Frontend (Views + JavaScript)](#fase-4)
5. [Fase 5 — Integrações (Logging, Notificações, Permissões)](#fase-5)
6. [Fase 6 — Melhorias UX/UI](#fase-6)
7. [Fase 7 — Testes Unitários](#fase-7)
8. [Fase 8 — Limpeza e Documentação](#fase-8)
9. [Dependências entre Fases](#dependencias)
10. [Estimativa de Arquivos por Fase](#arquivos)
11. [Resumo Geral](#resumo)

---

## Visão Geral

O módulo Arquivo é um dos **51 controllers legados** do projeto (classificação 3/10). Este plano de ação refatora o módulo inteiro para o padrão moderno do Mercury, usando o módulo **Sales** como referência principal.

### Estado Atual vs Estado Desejado

| Aspecto | Atual | Desejado |
|---------|-------|----------|
| Nomenclatura | Português (`Arquivo`, `Cadastrar`, `Apagar`) | Inglês (`Files`, `AddFile`, `DeleteFile`) |
| Tabela DB | `adms_up_down` (7 colunas) | `adms_files` (13 colunas) |
| Controllers | 5 (1 duplicado), sem type hints | 5 com match/type hints |
| Models | 4 (1 duplicado), verbos no nome | 4 padrão `Adms` + Statistics |
| Views | 3 (page-reload, dir `upload/`) | load + list + 4 modais (dir `files/`) |
| JavaScript | Nenhum | `files.js` completo |
| Testes | 0 | ~40 testes unitários |
| Segurança | XSS, GET delete, downloads públicos | XSS-safe, CSRF, downloads protegidos |
| Logging | Nenhum | LoggerService completo |
| Filtros | Nenhum | Loja, status, busca por nome |
| Estatísticas | Nenhum | Cards KPI (total, por loja, por status) |

### Diagrama de Arquivos (Estado Final)

```
app/adms/Controllers/
├── Files.php                          # Controller principal (listagem)
├── AddFile.php                        # Criar arquivo
├── EditFile.php                       # Editar arquivo
├── DeleteFile.php                     # Excluir arquivo
└── DownloadFile.php                   # Download seguro (NOVO)

app/adms/Models/
├── AdmsFile.php                       # CRUD principal
├── AdmsListFiles.php                  # Listagem + busca
├── AdmsStatisticsFiles.php            # Estatísticas (NOVO)
└── AdmsViewFile.php                   # Visualização (NOVO)

app/adms/Views/files/
├── loadFiles.php                      # Página principal
├── listFiles.php                      # Listagem AJAX
└── partials/
    ├── _add_file_modal.php            # Modal criar
    ├── _edit_file_modal.php           # Modal editar
    ├── _view_file_modal.php           # Modal visualizar (NOVO)
    └── _delete_file_modal.php         # Modal excluir

app/cpadms/Models/
└── CpAdmsSearchFile.php              # Busca avançada (NOVO)

assets/js/
└── files.js                           # JavaScript dedicado (NOVO)

tests/Files/
├── AdmsFileTest.php                   # Testes CRUD
├── AdmsListFilesTest.php              # Testes listagem
└── AdmsStatisticsFilesTest.php        # Testes estatísticas

database/migrations/
└── migrate_adms_up_down_to_adms_files.sql  # Migração de schema
```

---

<a name="fase-1"></a>
## Fase 1 — Correções Críticas de Segurança

> **Prioridade:** URGENTE — aplicar independente do restante do plano
> **Pode ser aplicada no código legado atual sem aguardar as demais fases**

### 1A. Corrigir XSS em Formulários

**Problema:** Os campos `nome`, `id`, `arq_antigo` e `slug` nos formulários de cadastro e edição não usam `htmlspecialchars()`, permitindo injeção de HTML/JavaScript.

**Arquivos a modificar:**
- `app/adms/Views/upload/cadArquivo.php`
- `app/adms/Views/upload/editArquivo.php`

**Implementação (cadArquivo.php):**
```php
<!-- ANTES (linha 39-43) -->
value="<?php if (isset($valorForm['nome'])) { echo $valorForm['nome']; } ?>"

<!-- DEPOIS -->
value="<?= htmlspecialchars($valorForm['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
```

**Implementação (editArquivo.php):**
```php
<!-- Campo id (linha 33-37) -->
<!-- ANTES -->
value="<?php if (isset($valorForm['id'])) { echo $valorForm['id']; } ?>"
<!-- DEPOIS -->
value="<?= htmlspecialchars($valorForm['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"

<!-- Campo arq_antigo (linha 75-81) -->
<!-- ANTES -->
value="<?php if (isset($valorForm['slug'])) { echo $valorForm['slug'];
    } elseif (isset($valorForm['arquivo'])) { echo $valorForm['arquivo']; } ?>"
<!-- DEPOIS -->
value="<?= htmlspecialchars($valorForm['slug'] ?? $valorForm['arquivo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"

<!-- Campo nome (linha 41-45) -->
<!-- ANTES -->
value="<?php if (isset($valorForm['nome'])) { echo $valorForm['nome']; } ?>"
<!-- DEPOIS -->
value="<?= htmlspecialchars($valorForm['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
```

**Critério de aceite:**
- [ ] Todos os campos `value=""` usam `htmlspecialchars()` com `ENT_QUOTES` e `UTF-8`
- [ ] Testar com payload `"><script>alert('xss')</script>` no campo nome — deve ser renderizado como texto
- [ ] Formulários continuam funcionando normalmente com acentos (é, ã, ç)

---

### 1B. Migrar Delete para POST com CSRF

**Problema:** A exclusão de arquivos ocorre via GET request (link direto), sem token CSRF. Um atacante pode forçar exclusão via link em email ou imagem.

**Solução:** Criar modal de confirmação com formulário POST + CSRF token. Até a refatoração completa (Fase 4), implementar no código legado.

**Arquivos a modificar:**
- `app/adms/Views/upload/arquivo.php` — substituir link GET por botão que abre modal
- `app/adms/Controllers/ApagarArquivo.php` — aceitar apenas POST

**Implementação (arquivo.php) — Adicionar modal no final do arquivo:**
```php
<!-- Modal de confirmação de exclusão -->
<div class="modal fade" id="deleteFileModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirmar Exclusão</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza de que deseja excluir o arquivo <strong id="delete-file-name"></strong>?</p>
                <div class="alert alert-warning"><i class="fas fa-info-circle"></i> Esta ação não pode ser desfeita.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteFileForm" method="POST" action="" style="display:inline">
                    <input type="hidden" name="_csrf_token" value="<?= $_SESSION['_csrf_token'] ?? '' ?>">
                    <button type="submit" name="ConfirmDelete" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Excluir
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-delete-file]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const id = this.dataset.deleteFile;
        const name = this.dataset.fileName;
        document.getElementById('delete-file-name').textContent = name;
        document.getElementById('deleteFileForm').action =
            '<?= URLADM ?>apagar-arquivo/apagar-arquivo/' + id;
        new bootstrap.Modal(document.getElementById('deleteFileModal')).show();
    });
});
</script>
```

**Substituir links de exclusão na listagem:**
```php
<!-- ANTES -->
<a href="<?= URLADM ?>apagar-arquivo/apagar-arquivo/<?= $arquivo['id'] ?>"
   data-confirm="Tem certeza...?">

<!-- DEPOIS -->
<button class="btn btn-outline-danger btn-sm" title="Apagar"
    data-delete-file="<?= htmlspecialchars($arquivo['id'], ENT_QUOTES, 'UTF-8') ?>"
    data-file-name="<?= htmlspecialchars($arquivo['nome'], ENT_QUOTES, 'UTF-8') ?>">
    <i class="fa-solid fa-eraser"></i>
</button>
```

**Implementação (ApagarArquivo.php):**
```php
public function apagarArquivo($DadosId = null)
{
    // Aceitar apenas POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        SessionContext::setFlashMessage("<div class='alert alert-danger'>Erro: Método não permitido.</div>");
        header("Location: " . URLADM . "arquivo/listar");
        return;
    }

    $this->DadosId = (int) $DadosId;
    if (!empty($this->DadosId)) {
        $apagarArq = new \App\adms\Models\AdmsApagarArquivo();
        $apagarArq->apagarArquivo($this->DadosId);
    } else {
        SessionContext::setFlashMessage("<div class='alert alert-danger'>Erro: Necessário selecionar um arquivo!</div>");
    }
    header("Location: " . URLADM . "arquivo/listar");
}
```

**Critério de aceite:**
- [ ] Acesso direto via GET a `/apagar-arquivo/apagar-arquivo/1` retorna erro
- [ ] Modal de confirmação exibe nome do arquivo
- [ ] Formulário POST envia token CSRF
- [ ] Exclusão funciona normalmente via modal

---

### 1C. Proteger Downloads com Controller Dedicado

**Problema:** Arquivos em `assets/files/downloads/{id}/{slug}` são acessíveis diretamente por URL sem autenticação. Qualquer pessoa com o link pode baixar qualquer arquivo.

**Solução:** Criar controller `DownloadFile.php` que verifica sessão e permissão antes de servir o arquivo.

**Arquivos a criar:**
- `app/adms/Controllers/DownloadFile.php`

**Arquivos a modificar:**
- `app/adms/Views/upload/arquivo.php` — apontar links de download para o novo controller
- `.htaccess` na pasta `assets/files/downloads/` — bloquear acesso direto

**Implementação (DownloadFile.php):**
```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\helper\AdmsRead;
use App\adms\Services\SessionContext;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller para download seguro de arquivos
 *
 * Verifica autenticação e permissão antes de servir o arquivo
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class DownloadFile
{
    private ?int $fileId = null;

    public function download(int|string|null $fileId = null): void
    {
        $this->fileId = (int) $fileId;

        if (empty($this->fileId)) {
            SessionContext::setFlashMessage("<div class='alert alert-danger'>Erro: Arquivo não especificado.</div>");
            header("Location: " . URLADM . "arquivo/listar");
            return;
        }

        $read = new AdmsRead();
        $read->fullRead(
            "SELECT id, nome, slug, loja_id FROM adms_up_down WHERE id = :id LIMIT :limit",
            "id={$this->fileId}&limit=1"
        );
        $file = $read->getResult();

        if (empty($file)) {
            SessionContext::setFlashMessage("<div class='alert alert-danger'>Erro: Arquivo não encontrado.</div>");
            header("Location: " . URLADM . "arquivo/listar");
            return;
        }

        $filePath = 'assets/files/downloads/' . $file[0]['id'] . '/' . $file[0]['slug'];

        if (!file_exists($filePath)) {
            SessionContext::setFlashMessage("<div class='alert alert-danger'>Erro: Arquivo físico não encontrado no servidor.</div>");
            header("Location: " . URLADM . "arquivo/listar");
            return;
        }

        // Servir arquivo
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($file[0]['slug']) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($filePath);
        exit();
    }
}
```

**Implementação (.htaccess em `assets/files/downloads/`):**
```apache
# Bloquear acesso direto a arquivos
Order deny,allow
Deny from all
```

**Registrar rota no banco:**
```sql
INSERT INTO adms_paginas (nome_pagina, descricao, controller, metodo, adms_sit_id, icone)
VALUES ('Download de Arquivo', 'Download seguro de arquivos', 'download-file', 'download', 1, 'fa-solid fa-download');

-- Permissão para todos os níveis de acesso que já têm acesso ao módulo Arquivo
INSERT INTO adms_nivacs_pgs (adms_niveis_acesso_id, adms_pagina_id, permissao, ordem)
SELECT anp.adms_niveis_acesso_id, (SELECT id FROM adms_paginas WHERE controller = 'download-file'), 1, MAX(anp.ordem) + 1
FROM adms_nivacs_pgs anp
WHERE anp.adms_pagina_id = (SELECT id FROM adms_paginas WHERE controller = 'arquivo')
GROUP BY anp.adms_niveis_acesso_id;
```

**Critério de aceite:**
- [ ] URL direta `assets/files/downloads/1/file.pdf` retorna 403
- [ ] `/download-file/download/1` funciona para usuários autenticados
- [ ] Usuário não autenticado é redirecionado para login
- [ ] Arquivo inexistente exibe mensagem de erro
- [ ] Download funciona para todos os tipos de arquivo (PDF, Excel, Word, imagens)

---

<a name="fase-2"></a>
## Fase 2 — Banco de Dados (Migração de Schema)

### 2A. Criar Migração da Tabela

**Problema:** A tabela `adms_up_down` tem nome genérico, colunas sem padrão (`nome` em vez de `title`, `slug` como filename, sem auditoria), e falta colunas essenciais (tamanho, MIME type, usuário).

**Solução:** Renomear tabela para `adms_files` e adicionar colunas de auditoria, metadados de arquivo e padronizar nomenclatura.

**Arquivo a criar:**
- `database/migrations/migrate_adms_up_down_to_adms_files.sql`

**Implementação:**
```sql
-- =====================================================
-- Migração: adms_up_down → adms_files
-- Data: 2026-03-26
-- Descrição: Renomear tabela e adicionar colunas modernas
-- =====================================================

-- Passo 1: Adicionar novas colunas à tabela existente
ALTER TABLE adms_up_down
    ADD COLUMN title VARCHAR(255) NULL AFTER nome,
    ADD COLUMN filename VARCHAR(255) NULL AFTER slug,
    ADD COLUMN original_filename VARCHAR(255) NULL AFTER filename,
    ADD COLUMN file_size BIGINT UNSIGNED NULL AFTER original_filename,
    ADD COLUMN mime_type VARCHAR(100) NULL AFTER file_size,
    ADD COLUMN created_by_user_id INT UNSIGNED NULL AFTER modified,
    ADD COLUMN updated_by_user_id INT UNSIGNED NULL AFTER created_by_user_id,
    ADD COLUMN created_at DATETIME NULL AFTER updated_by_user_id,
    ADD COLUMN updated_at DATETIME NULL AFTER created_at;

-- Passo 2: Migrar dados para novas colunas
UPDATE adms_up_down SET
    title = nome,
    filename = slug,
    original_filename = slug,
    created_at = created,
    updated_at = modified;

-- Passo 3: Preencher file_size e mime_type dos registros existentes
-- (executar via script PHP que lê cada arquivo do disco)

-- Passo 4: Renomear tabela
RENAME TABLE adms_up_down TO adms_files;

-- Passo 5: Adicionar índices
ALTER TABLE adms_files
    ADD INDEX idx_files_store (loja_id),
    ADD INDEX idx_files_status (status_id),
    ADD INDEX idx_files_created_by (created_by_user_id),
    ADD INDEX idx_files_created_at (created_at);

-- Passo 6: Atualizar rotas no banco
UPDATE adms_paginas SET
    nome_pagina = 'Arquivos',
    controller = 'files',
    metodo = 'list'
WHERE controller = 'arquivo' AND metodo = 'listar';

UPDATE adms_paginas SET
    nome_pagina = 'Adicionar Arquivo',
    controller = 'add-file',
    metodo = 'create'
WHERE controller = 'cadastrar-arquivo';

UPDATE adms_paginas SET
    nome_pagina = 'Editar Arquivo',
    controller = 'edit-file',
    metodo = 'edit'
WHERE controller = 'editar-arquivo';

UPDATE adms_paginas SET
    nome_pagina = 'Excluir Arquivo',
    controller = 'delete-file',
    metodo = 'delete'
WHERE controller = 'apagar-arquivo';

-- Passo 7: Atualizar controller do download (Fase 1C)
UPDATE adms_paginas SET
    nome_pagina = 'Download de Arquivo',
    controller = 'download-file',
    metodo = 'download'
WHERE controller = 'download-file';
```

**Critério de aceite:**
- [ ] Tabela renomeada para `adms_files` sem perda de dados
- [ ] Colunas `title`, `filename`, `original_filename`, `file_size`, `mime_type` populadas
- [ ] Colunas de auditoria `created_by_user_id`, `updated_by_user_id`, `created_at`, `updated_at` presentes
- [ ] Índices criados em `loja_id`, `status_id`, `created_by_user_id`, `created_at`
- [ ] Rotas atualizadas para nomenclatura em inglês
- [ ] Dados existentes migrados corretamente (verificar `title = nome`, `filename = slug`)

---

### 2B. Script PHP para Preencher Metadados de Arquivos Existentes

**Problema:** Os registros existentes não têm `file_size` e `mime_type`. Esses dados só podem ser obtidos lendo os arquivos físicos no disco.

**Arquivo a criar:**
- `database/migrations/fill_file_metadata.php`

**Implementação:**
```php
<?php
/**
 * Script para preencher metadados de arquivos existentes
 * Executar UMA VEZ após a migração 2A
 */

require_once __DIR__ . '/../../core/Config.php';

use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsUpdate;

$read = new AdmsRead();
$read->fullRead("SELECT id, slug FROM adms_files WHERE file_size IS NULL");
$files = $read->getResult();

if (empty($files)) {
    echo "Nenhum arquivo para atualizar.\n";
    exit;
}

$updated = 0;
$errors = 0;

foreach ($files as $file) {
    $filePath = 'assets/files/downloads/' . $file['id'] . '/' . $file['slug'];

    if (file_exists($filePath)) {
        $data = [
            'id' => $file['id'],
            'file_size' => filesize($filePath),
            'mime_type' => mime_content_type($filePath) ?: 'application/octet-stream',
            'original_filename' => $file['slug'],
        ];

        $update = new AdmsUpdate();
        $update->exeUpdate("adms_files", $data, "WHERE id = :id", "id={$file['id']}");
        $updated++;
        echo "OK: ID {$file['id']} — {$data['mime_type']} ({$data['file_size']} bytes)\n";
    } else {
        $errors++;
        echo "ERRO: ID {$file['id']} — arquivo não encontrado: {$filePath}\n";
    }
}

echo "\nFinalizado: {$updated} atualizados, {$errors} erros.\n";
```

**Critério de aceite:**
- [ ] Todos os registros com arquivo físico existente têm `file_size` e `mime_type` preenchidos
- [ ] Registros sem arquivo físico são reportados como erro (para limpeza manual)
- [ ] Script é idempotente (executar duas vezes não causa problemas)

---

<a name="fase-3"></a>
## Fase 3 — Refatoração Backend (Controllers + Models)

### 3A. Controller Principal — Files.php

**Problema:** `Arquivo.php` usa nomenclatura PT, sem type hints, sem match expression, sem statistics, sem busca.

**Arquivo a criar:**
- `app/adms/Controllers/Files.php`

**Arquivo a remover (após migração completa):**
- `app/adms/Controllers/Arquivo.php`
- `app/adms/Controllers/ListarArquivo.php` (duplicado)

**Implementação:**
```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsBotao;
use App\adms\Models\AdmsListFiles;
use App\adms\Models\AdmsMenu;
use App\adms\Models\AdmsStatisticsFiles;
use App\adms\Services\FormSelectRepository;
use Core\ConfigView;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller de Arquivos
 *
 * Gerencia listagem, busca e estatísticas de arquivos do sistema
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class Files
{
    private ?array $data = [];
    private int $pageId;
    private ?int $requestType;

    /**
     * Método principal de listagem
     */
    public function list(int|string|null $pageId = null): void
    {
        $this->pageId = (int) ($pageId ?: 1);
        $this->requestType = filter_input(INPUT_GET, 'typefiles', FILTER_VALIDATE_INT);

        $this->loadButtons();
        $this->loadMenu();

        match ($this->requestType) {
            1 => $this->listAllFiles(),
            2 => $this->searchFiles(),
            default => $this->loadInitialPage(),
        };
    }

    private function loadButtons(): void
    {
        $buttons = [
            'add_file'    => ['menu_controller' => 'add-file', 'menu_metodo' => 'create'],
            'edit_file'   => ['menu_controller' => 'edit-file', 'menu_metodo' => 'edit'],
            'delete_file' => ['menu_controller' => 'delete-file', 'menu_metodo' => 'delete'],
            'view_file'   => ['menu_controller' => 'view-file', 'menu_metodo' => 'view'],
        ];
        $listButtons = new AdmsBotao();
        $this->data['buttons'] = $listButtons->valBotao($buttons);
    }

    private function loadMenu(): void
    {
        $menu = new AdmsMenu();
        $this->data['menu'] = $menu->itemMenu();
    }

    private function loadInitialPage(): void
    {
        $this->loadStatistics();
        $this->loadFilterOptions();
        $this->listAllFiles();

        $loadView = new ConfigView("adms/Views/files/loadFiles", $this->data);
        $loadView->renderizar();
    }

    private function listAllFiles(): void
    {
        $listFiles = new AdmsListFiles();
        $this->data['listFiles'] = $listFiles->list($this->pageId);
        $this->data['pagination'] = $listFiles->getPagination();

        if ($this->requestType === 1) {
            $loadView = new ConfigView("adms/Views/files/listFiles", $this->data);
            $loadView->renderList();
        }
    }

    private function searchFiles(): void
    {
        $filters = filter_input_array(INPUT_POST, FILTER_DEFAULT) ?? [];
        unset($filters['_csrf_token']);

        $listFiles = new AdmsListFiles();
        $this->data['listFiles'] = $listFiles->search($this->pageId, $filters);
        $this->data['pagination'] = $listFiles->getPagination();
        $this->data['activeFilters'] = $filters;

        $loadView = new ConfigView("adms/Views/files/listFiles", $this->data);
        $loadView->renderList();
    }

    private function loadStatistics(): void
    {
        $statistics = new AdmsStatisticsFiles();
        $this->data['statistics'] = $statistics->getStatistics();
    }

    private function loadFilterOptions(): void
    {
        $selects = new FormSelectRepository();
        $this->data['filterStores'] = $selects->getActiveStores();
        $this->data['filterStatuses'] = $selects->getStatuses();
    }
}
```

**Critério de aceite:**
- [ ] Match expression para routing de tipos de requisição
- [ ] Type hints em todas as propriedades e métodos
- [ ] Statistics cards carregados na página inicial
- [ ] Filtros de busca por loja e status
- [ ] Listagem AJAX via `listFiles.php`
- [ ] Paginação funcional
- [ ] Botões de permissão via `AdmsBotao`

---

### 3B. Controller de Criação — AddFile.php

**Arquivo a criar:**
- `app/adms/Controllers/AddFile.php`

**Arquivo a remover:**
- `app/adms/Controllers/CadastrarArquivo.php`

**Implementação:**
```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsFile;
use App\adms\Models\helper\traits\JsonResponseTrait;
use App\adms\Services\LoggerService;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller para adicionar novos arquivos
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class AddFile
{
    use JsonResponseTrait;

    /**
     * Processar criação de arquivo via AJAX
     */
    public function create(): void
    {
        $postData = filter_input_array(INPUT_POST, FILTER_DEFAULT);
        unset($postData['_csrf_token']);

        if (empty($postData)) {
            $this->jsonResponse(['error' => true, 'message' => 'Dados não recebidos.'], 400);
            return;
        }

        $postData['file'] = $_FILES['file'] ?? null;

        $model = new AdmsFile();
        $result = $model->create($postData);

        if ($result) {
            $this->jsonResponse([
                'error' => false,
                'success' => true,
                'message' => 'Arquivo cadastrado com sucesso!',
            ]);
        } else {
            $this->jsonResponse([
                'error' => true,
                'message' => $model->getMessage(),
            ], 400);
        }
    }
}
```

**Critério de aceite:**
- [ ] Resposta JSON padrão via `JsonResponseTrait`
- [ ] Validação de dados POST
- [ ] Upload via model (delegação limpa)
- [ ] Mensagens de erro descritivas
- [ ] CSRF token removido antes do processamento

---

### 3C. Controller de Edição — EditFile.php

**Arquivo a criar:**
- `app/adms/Controllers/EditFile.php`

**Arquivo a remover:**
- `app/adms/Controllers/EditarArquivo.php`

**Implementação:**
```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsFile;
use App\adms\Models\AdmsViewFile;
use App\adms\Models\helper\traits\JsonResponseTrait;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller para editar arquivos existentes
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class EditFile
{
    use JsonResponseTrait;

    /**
     * Carregar dados do arquivo para o modal de edição
     */
    public function edit(int|string|null $fileId = null): void
    {
        $fileId = (int) $fileId;

        if (empty($fileId)) {
            $this->jsonResponse(['error' => true, 'message' => 'ID não informado.'], 400);
            return;
        }

        $viewModel = new AdmsViewFile();
        $file = $viewModel->view($fileId);

        if (empty($file)) {
            $this->jsonResponse(['error' => true, 'message' => 'Arquivo não encontrado.'], 404);
            return;
        }

        $this->jsonResponse([
            'error' => false,
            'file' => $file[0],
        ]);
    }

    /**
     * Processar atualização do arquivo
     */
    public function update(): void
    {
        $postData = filter_input_array(INPUT_POST, FILTER_DEFAULT);
        unset($postData['_csrf_token']);

        if (empty($postData['id'])) {
            $this->jsonResponse(['error' => true, 'message' => 'ID não informado.'], 400);
            return;
        }

        $postData['file'] = $_FILES['file'] ?? null;

        $model = new AdmsFile();
        $result = $model->update($postData);

        if ($result) {
            $this->jsonResponse([
                'error' => false,
                'success' => true,
                'message' => 'Arquivo atualizado com sucesso!',
            ]);
        } else {
            $this->jsonResponse([
                'error' => true,
                'message' => $model->getMessage(),
            ], 400);
        }
    }
}
```

**Critério de aceite:**
- [ ] Método `edit()` retorna dados para popular o modal
- [ ] Método `update()` processa o formulário via AJAX
- [ ] Upload de novo arquivo substitui o anterior
- [ ] Tratar `AdmsUpdate::getResult() === false` com 0 rows affected como sucesso

---

### 3D. Controller de Exclusão — DeleteFile.php

**Arquivo a criar:**
- `app/adms/Controllers/DeleteFile.php`

**Arquivo a remover:**
- `app/adms/Controllers/ApagarArquivo.php`

**Implementação:**
```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsFile;
use App\adms\Models\helper\traits\JsonResponseTrait;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller para exclusão de arquivos
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class DeleteFile
{
    use JsonResponseTrait;

    /**
     * Excluir arquivo via AJAX POST
     */
    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => true, 'message' => 'Método não permitido.'], 405);
            return;
        }

        $fileId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (empty($fileId)) {
            $this->jsonResponse(['error' => true, 'message' => 'ID não informado.'], 400);
            return;
        }

        $model = new AdmsFile();
        $result = $model->delete($fileId);

        if ($result) {
            $this->jsonResponse([
                'error' => false,
                'success' => true,
                'message' => 'Arquivo excluído com sucesso!',
            ]);
        } else {
            $this->jsonResponse([
                'error' => true,
                'message' => $model->getMessage(),
            ], 400);
        }
    }
}
```

**Critério de aceite:**
- [ ] Apenas POST aceito (GET retorna 405)
- [ ] Validação de ID
- [ ] Exclusão do banco + arquivo físico + diretório
- [ ] Resposta JSON padrão
- [ ] LoggerService registra a exclusão

---

### 3E. Model Principal — AdmsFile.php

**Arquivo a criar:**
- `app/adms/Models/AdmsFile.php`

**Arquivos a remover:**
- `app/adms/Models/AdmsCadastrarArquivo.php`
- `app/adms/Models/AdmsEditarArquivo.php`
- `app/adms/Models/AdmsApagarArquivo.php`

**Implementação:**
```php
<?php

namespace App\adms\Models;

use App\adms\Models\helper\AdmsCreate;
use App\adms\Models\helper\AdmsDelete;
use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsSlug;
use App\adms\Models\helper\AdmsUpdate;
use App\adms\Models\helper\AdmsApagarArq;
use App\adms\Services\FileUploadService;
use App\adms\Services\LoggerService;
use App\adms\Services\SessionContext;
use App\adms\Services\UploadConfig;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Model CRUD para arquivos
 *
 * Gerencia criação, edição e exclusão de registros na tabela adms_files
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class AdmsFile
{
    private bool $result = false;
    private string $message = '';
    private ?array $data = null;

    public function getResult(): bool
    {
        return $this->result;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Criar novo arquivo
     */
    public function create(array $postData): bool
    {
        $file = $postData['file'] ?? null;
        unset($postData['file']);

        // Validação explícita de campos obrigatórios
        $requiredFields = ['title', 'loja_id', 'status_id'];
        foreach ($requiredFields as $field) {
            if (empty($postData[$field])) {
                $this->message = "Campo obrigatório não preenchido: {$field}";
                return false;
            }
        }

        if (empty($file) || empty($file['name'])) {
            $this->message = 'Selecione um arquivo para upload.';
            return false;
        }

        // Gerar slug para o nome do arquivo
        $slugService = new AdmsSlug();
        $filename = $slugService->nomeSlug($file['name']);

        $postData['filename'] = $filename;
        $postData['original_filename'] = $file['name'];
        $postData['file_size'] = $file['size'];
        $postData['mime_type'] = $file['type'];
        $postData['created_at'] = date('Y-m-d H:i:s');
        $postData['created_by_user_id'] = SessionContext::getUserId();

        // Manter compatibilidade com colunas legadas durante transição
        $postData['nome'] = $postData['title'];
        $postData['slug'] = $filename;
        $postData['created'] = $postData['created_at'];

        $create = new AdmsCreate();
        $create->exeCreate("adms_files", $postData);

        if (!$create->getResult()) {
            $this->message = 'Erro ao cadastrar o arquivo no banco de dados.';
            LoggerService::error('FILE_CREATE_FAILED', 'Falha ao inserir registro', ['data' => $postData]);
            return false;
        }

        $fileId = $create->getResult();

        // Upload do arquivo
        $uploadService = new FileUploadService();
        $config = UploadConfig::documents('assets/files/downloads/' . $fileId . '/')
            ->setCustomFilename($filename)
            ->setNotify(false);

        try {
            $uploadResult = $uploadService->uploadSingle($file, $config);

            if (!$uploadResult->isSuccess()) {
                $this->message = 'Registro criado, mas o upload falhou. Tente editar e reenviar o arquivo.';
                LoggerService::warning('FILE_UPLOAD_FAILED', 'Upload falhou após insert', ['file_id' => $fileId]);
                return true; // Registro foi criado, upload pode ser feito depois
            }
        } catch (\Exception $e) {
            $this->message = 'Erro no upload: ' . $e->getMessage();
            LoggerService::error('FILE_UPLOAD_EXCEPTION', $e->getMessage(), ['file_id' => $fileId]);
            return true;
        }

        LoggerService::info('FILE_CREATED', 'Arquivo criado com sucesso', [
            'file_id' => $fileId,
            'filename' => $filename,
            'store_id' => $postData['loja_id'],
        ]);

        $this->result = true;
        return true;
    }

    /**
     * Atualizar arquivo existente
     */
    public function update(array $postData): bool
    {
        $fileId = (int) $postData['id'];
        $file = $postData['file'] ?? null;
        unset($postData['file']);

        // Buscar registro atual
        $read = new AdmsRead();
        $read->fullRead("SELECT * FROM adms_files WHERE id = :id LIMIT :limit", "id={$fileId}&limit=1");
        $currentFile = $read->getResult();

        if (empty($currentFile)) {
            $this->message = 'Arquivo não encontrado.';
            return false;
        }

        $currentFile = $currentFile[0];

        // Validação explícita
        $requiredFields = ['title', 'loja_id', 'status_id'];
        foreach ($requiredFields as $field) {
            if (empty($postData[$field])) {
                $this->message = "Campo obrigatório não preenchido: {$field}";
                return false;
            }
        }

        $postData['updated_at'] = date('Y-m-d H:i:s');
        $postData['updated_by_user_id'] = SessionContext::getUserId();
        $postData['modified'] = $postData['updated_at'];
        $postData['nome'] = $postData['title'];

        // Se há novo arquivo, fazer upload e excluir o anterior
        if (!empty($file) && !empty($file['name'])) {
            $slugService = new AdmsSlug();
            $newFilename = $slugService->nomeSlug($file['name']);

            $uploadService = new FileUploadService();
            $config = UploadConfig::documents('assets/files/downloads/' . $fileId . '/')
                ->setCustomFilename($newFilename)
                ->setNotify(false);

            try {
                $uploadResult = $uploadService->uploadSingle($file, $config);

                if ($uploadResult->isSuccess()) {
                    // Excluir arquivo anterior
                    $oldPath = 'assets/files/downloads/' . $fileId . '/' . $currentFile['filename'];
                    if (file_exists($oldPath) && $currentFile['filename'] !== $newFilename) {
                        unlink($oldPath);
                    }

                    $postData['filename'] = $newFilename;
                    $postData['original_filename'] = $file['name'];
                    $postData['file_size'] = $file['size'];
                    $postData['mime_type'] = $file['type'];
                    $postData['slug'] = $newFilename;
                } else {
                    $this->message = 'Erro no upload do novo arquivo. Dados atualizados sem substituir o arquivo.';
                }
            } catch (\Exception $e) {
                $this->message = 'Erro no upload: ' . $e->getMessage();
            }
        }

        $update = new AdmsUpdate();
        $update->exeUpdate("adms_files", $postData, "WHERE id = :id", "id={$fileId}");

        // Tratar getResult() === false com 0 rows affected como sucesso
        // (dados idênticos aos existentes não é erro)
        LoggerService::info('FILE_UPDATED', 'Arquivo atualizado', [
            'file_id' => $fileId,
            'store_id' => $postData['loja_id'],
        ]);

        $this->result = true;
        return true;
    }

    /**
     * Excluir arquivo
     */
    public function delete(int $fileId): bool
    {
        $read = new AdmsRead();
        $read->fullRead("SELECT * FROM adms_files WHERE id = :id LIMIT :limit", "id={$fileId}&limit=1");
        $file = $read->getResult();

        if (empty($file)) {
            $this->message = 'Arquivo não encontrado.';
            return false;
        }

        $fileData = $file[0];

        $delete = new AdmsDelete();
        $delete->exeDelete("adms_files", "WHERE id = :id", "id={$fileId}");

        if (!$delete->getResult()) {
            $this->message = 'Erro ao excluir o registro do banco de dados.';
            LoggerService::error('FILE_DELETE_FAILED', 'Falha ao excluir registro', ['file_id' => $fileId]);
            return false;
        }

        // Excluir arquivo físico e diretório
        $filePath = 'assets/files/downloads/' . $fileId . '/' . ($fileData['filename'] ?? $fileData['slug']);
        $dirPath = 'assets/files/downloads/' . $fileId;

        if (file_exists($filePath)) {
            unlink($filePath);
        }
        if (is_dir($dirPath)) {
            @rmdir($dirPath);
        }

        LoggerService::info('FILE_DELETED', 'Arquivo excluído', [
            'file_id' => $fileId,
            'filename' => $fileData['filename'] ?? $fileData['slug'],
            'store_id' => $fileData['loja_id'],
            'deleted_data' => $fileData,
        ]);

        $this->result = true;
        return true;
    }
}
```

**Critério de aceite:**
- [ ] CRUD completo em um único model
- [ ] Validação explícita de campos obrigatórios (não usar `AdmsCampoVazio`)
- [ ] LoggerService em todas as operações (CREATE, UPDATE, DELETE)
- [ ] Campos de auditoria (`created_by_user_id`, `updated_by_user_id`) preenchidos
- [ ] Upload atômico: registro + arquivo no mesmo fluxo
- [ ] Exclusão limpa: banco + arquivo + diretório
- [ ] Tratar `AdmsUpdate::getResult() === false` como sucesso quando 0 rows affected

---

### 3F. Model de Listagem — AdmsListFiles.php

**Arquivo a criar:**
- `app/adms/Models/AdmsListFiles.php`

**Arquivos a remover:**
- `app/adms/Models/AdmsListarArquivo.php`
- `app/adms/Models/AdmsListarArq.php`

**Implementação:**
```php
<?php

namespace App\adms\Models;

use App\adms\Models\helper\AdmsPaginacao;
use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\traits\StorePermissionTrait;
use App\adms\Services\SessionContext;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Model de listagem e busca de arquivos
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class AdmsListFiles
{
    use StorePermissionTrait;

    private ?array $result = null;
    private ?string $pagination = null;
    private int $limitPerPage = 20;

    public function getPagination(): ?string
    {
        return $this->pagination;
    }

    /**
     * Listar todos os arquivos com paginação e filtro por loja
     */
    public function list(?int $pageId = null): ?array
    {
        $pageId = $pageId ?: 1;

        $storeFilter = $this->buildStoreFilter('f', 'loja_id');
        $storeCondition = $storeFilter['condition'];
        $storeParam = $storeFilter['paramPart'];

        $paginacao = new AdmsPaginacao(URLADM . 'files/list');
        $paginacao->condicao($pageId, $this->limitPerPage);
        $paginacao->paginacao(
            "SELECT COUNT(f.id) AS num_result FROM adms_files f WHERE 1=1{$storeCondition}",
            ltrim($storeParam, '&')
        );
        $this->pagination = $paginacao->getResultado();

        $read = new AdmsRead();
        $read->fullRead(
            "SELECT f.id, f.title, f.filename, f.original_filename, f.file_size,
                    f.mime_type, f.loja_id, f.status_id, f.created_at,
                    f.updated_at, f.created_by_user_id,
                    st.nome AS status, lj.nome AS loja,
                    u.nome AS created_by_name
             FROM adms_files f
             INNER JOIN tb_status st ON st.id = f.status_id
             INNER JOIN tb_lojas lj ON lj.id = f.loja_id
             LEFT JOIN adms_usuarios u ON u.id = f.created_by_user_id
             WHERE 1=1{$storeCondition}
             ORDER BY f.id DESC
             LIMIT :limit OFFSET :offset",
            "limit={$this->limitPerPage}&offset={$paginacao->getOffset()}{$storeParam}"
        );

        $this->result = $read->getResult();
        return $this->result;
    }

    /**
     * Buscar arquivos com filtros
     */
    public function search(?int $pageId = null, array $filters = []): ?array
    {
        $pageId = $pageId ?: 1;

        $storeFilter = $this->buildStoreFilter('f', 'loja_id');
        $conditions = "1=1" . $storeFilter['condition'];
        $params = ltrim($storeFilter['paramPart'], '&');

        // Filtro por nome/título
        if (!empty($filters['search_title'])) {
            $conditions .= " AND f.title LIKE :search_title";
            $params .= "&search_title=%" . $filters['search_title'] . "%";
        }

        // Filtro por loja
        if (!empty($filters['filter_store'])) {
            $conditions .= " AND f.loja_id = :filter_store";
            $params .= "&filter_store=" . $filters['filter_store'];
        }

        // Filtro por status
        if (!empty($filters['filter_status'])) {
            $conditions .= " AND f.status_id = :filter_status";
            $params .= "&filter_status=" . $filters['filter_status'];
        }

        $paginacao = new AdmsPaginacao(URLADM . 'files/list');
        $paginacao->condicao($pageId, $this->limitPerPage);
        $paginacao->paginacao(
            "SELECT COUNT(f.id) AS num_result FROM adms_files f WHERE {$conditions}",
            $params
        );
        $this->pagination = $paginacao->getResultado();

        $read = new AdmsRead();
        $read->fullRead(
            "SELECT f.id, f.title, f.filename, f.original_filename, f.file_size,
                    f.mime_type, f.loja_id, f.status_id, f.created_at,
                    f.updated_at, f.created_by_user_id,
                    st.nome AS status, lj.nome AS loja,
                    u.nome AS created_by_name
             FROM adms_files f
             INNER JOIN tb_status st ON st.id = f.status_id
             INNER JOIN tb_lojas lj ON lj.id = f.loja_id
             LEFT JOIN adms_usuarios u ON u.id = f.created_by_user_id
             WHERE {$conditions}
             ORDER BY f.id DESC
             LIMIT :limit OFFSET :offset",
            "limit={$this->limitPerPage}&offset={$paginacao->getOffset()}&{$params}"
        );

        $this->result = $read->getResult();
        return $this->result;
    }
}
```

**Critério de aceite:**
- [ ] `StorePermissionTrait` filtra por loja conforme nível de acesso
- [ ] Paginação alinhada com query (mesmas condições no COUNT e no SELECT)
- [ ] Busca por título, loja e status
- [ ] JOIN com `adms_usuarios` para exibir nome do criador
- [ ] Exibe metadados (tamanho, tipo MIME)

---

### 3G. Model de Estatísticas — AdmsStatisticsFiles.php

**Arquivo a criar:**
- `app/adms/Models/AdmsStatisticsFiles.php`

**Implementação:**
```php
<?php

namespace App\adms\Models;

use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\traits\StorePermissionTrait;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Model de estatísticas de arquivos
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class AdmsStatisticsFiles
{
    use StorePermissionTrait;

    /**
     * Retorna estatísticas do módulo de arquivos
     */
    public function getStatistics(): array
    {
        $storeFilter = $this->buildStoreFilter('f', 'loja_id');
        $storeCondition = $storeFilter['condition'];
        $storeParam = ltrim($storeFilter['paramPart'], '&');

        $read = new AdmsRead();

        // Total de arquivos
        $read->fullRead(
            "SELECT COUNT(f.id) AS total FROM adms_files f WHERE 1=1{$storeCondition}",
            $storeParam ?: null
        );
        $total = (int) ($read->getResult()[0]['total'] ?? 0);

        // Ativos
        $read->fullRead(
            "SELECT COUNT(f.id) AS total FROM adms_files f WHERE f.status_id = :status{$storeCondition}",
            "status=1" . ($storeParam ? "&{$storeParam}" : "")
        );
        $active = (int) ($read->getResult()[0]['total'] ?? 0);

        // Inativos
        $inactive = $total - $active;

        // Tamanho total
        $read->fullRead(
            "SELECT COALESCE(SUM(f.file_size), 0) AS total_size FROM adms_files f WHERE 1=1{$storeCondition}",
            $storeParam ?: null
        );
        $totalSize = (int) ($read->getResult()[0]['total_size'] ?? 0);

        return [
            'total'      => $total,
            'active'     => $active,
            'inactive'   => $inactive,
            'total_size' => $totalSize,
        ];
    }
}
```

**Critério de aceite:**
- [ ] 4 KPIs: total, ativos, inativos, tamanho total em disco
- [ ] StorePermissionTrait aplicado
- [ ] Valores corretos comparados com contagem manual no banco

---

### 3H. Model de Visualização — AdmsViewFile.php

**Arquivo a criar:**
- `app/adms/Models/AdmsViewFile.php`

**Implementação:**
```php
<?php

namespace App\adms\Models;

use App\adms\Models\helper\AdmsRead;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Model de visualização detalhada de arquivo
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2026, Grupo Meia Sola
 */
class AdmsViewFile
{
    /**
     * Buscar dados completos de um arquivo
     */
    public function view(int $fileId): ?array
    {
        $read = new AdmsRead();
        $read->fullRead(
            "SELECT f.*, st.nome AS status, lj.nome AS loja,
                    u1.nome AS created_by_name,
                    u2.nome AS updated_by_name
             FROM adms_files f
             INNER JOIN tb_status st ON st.id = f.status_id
             INNER JOIN tb_lojas lj ON lj.id = f.loja_id
             LEFT JOIN adms_usuarios u1 ON u1.id = f.created_by_user_id
             LEFT JOIN adms_usuarios u2 ON u2.id = f.updated_by_user_id
             WHERE f.id = :id LIMIT :limit",
            "id={$fileId}&limit=1"
        );

        return $read->getResult();
    }
}
```

**Critério de aceite:**
- [ ] Retorna todos os campos incluindo JOINs para nomes de usuário criador/atualizador
- [ ] Retorna `null` para IDs inexistentes

---

<a name="fase-4"></a>
## Fase 4 — Refatoração Frontend (Views + JavaScript)

### 4A. View Principal — loadFiles.php

**Arquivo a criar:**
- `app/adms/Views/files/loadFiles.php`

**Implementação:**
```php
<?php
if (!defined('URLADM')) {
    header("Location: /");
    exit();
}
$stats = $this->Dados['statistics'] ?? [];
?>

<div class="page-content p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="display-4 titulo d-none d-lg-block">Arquivos</h2>
        <h4 class="d-lg-none">Arquivos</h4>
        <?php if (!empty($this->Dados['buttons']['add_file'])): ?>
            <button class="btn btn-success btn-sm" id="btn-add-file">
                <i class="fas fa-plus d-block d-md-none fa-2x"></i>
                <span class="d-none d-md-inline"><i class="fas fa-plus"></i> Novo Arquivo</span>
            </button>
        <?php endif; ?>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="row mb-3">
        <div class="col-6 col-sm-4 col-md-6 col-lg-3 mb-3">
            <div class="card border-left-primary shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase">Total</div>
                    <div class="h5 mb-0 font-weight-bold"><?= $stats['total'] ?? 0 ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-md-6 col-lg-3 mb-3">
            <div class="card border-left-success shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase">Ativos</div>
                    <div class="h5 mb-0 font-weight-bold"><?= $stats['active'] ?? 0 ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-md-6 col-lg-3 mb-3">
            <div class="card border-left-warning shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase">Inativos</div>
                    <div class="h5 mb-0 font-weight-bold"><?= $stats['inactive'] ?? 0 ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-md-6 col-lg-3 mb-3">
            <div class="card border-left-info shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase">Tamanho Total</div>
                    <div class="h5 mb-0 font-weight-bold" id="stats-total-size"
                         data-bytes="<?= $stats['total_size'] ?? 0 ?>">
                        <?= number_format(($stats['total_size'] ?? 0) / 1048576, 1, ',', '.') ?> MB
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form id="search-form" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-0">Buscar por título</label>
                    <input type="text" name="search_title" class="form-control form-control-sm"
                           placeholder="Nome do arquivo..." id="search-title">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Loja</label>
                    <select name="filter_store" class="form-control form-control-sm" id="filter-store">
                        <option value="">Todas</option>
                        <?php foreach ($this->Dados['filterStores'] ?? [] as $store): ?>
                            <option value="<?= htmlspecialchars($store['l_id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($store['store_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Status</label>
                    <select name="filter_status" class="form-control form-control-sm" id="filter-status">
                        <option value="">Todos</option>
                        <?php foreach ($this->Dados['filterStatuses'] ?? [] as $status): ?>
                            <option value="<?= htmlspecialchars($status['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($status['nome'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Container da listagem (AJAX) -->
    <div id="content_files">
        <?php include __DIR__ . '/listFiles.php'; ?>
    </div>
</div>

<!-- Modais -->
<?php include __DIR__ . '/partials/_add_file_modal.php'; ?>
<?php include __DIR__ . '/partials/_edit_file_modal.php'; ?>
<?php include __DIR__ . '/partials/_view_file_modal.php'; ?>
<?php include __DIR__ . '/partials/_delete_file_modal.php'; ?>

<!-- JavaScript -->
<script src="<?= URLADM ?>assets/js/files.js"></script>
```

**Critério de aceite:**
- [ ] 4 cards de estatísticas com grid responsivo (2 mobile / 3 tablet / 4 desktop)
- [ ] Filtros de busca (título, loja, status)
- [ ] Container AJAX `#content_files` para listagem dinâmica
- [ ] Inclusão de modais e JavaScript
- [ ] Título responsivo (h2 desktop / h4 mobile)

---

### 4B. View de Listagem AJAX — listFiles.php

**Arquivo a criar:**
- `app/adms/Views/files/listFiles.php`

**Implementação:**
```php
<?php
if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

$mimeIcons = [
    'application/pdf' => 'fa-file-pdf text-danger',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'fa-file-excel text-success',
    'application/vnd.ms-excel' => 'fa-file-excel text-success',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fa-file-word text-primary',
    'application/msword' => 'fa-file-word text-primary',
    'image/jpeg' => 'fa-file-image text-info',
    'image/png' => 'fa-file-image text-info',
    'text/csv' => 'fa-file-csv text-success',
];
?>

<?php if (isset($_SESSION['msg'])): ?>
    <?= $_SESSION['msg'] ?>
    <?php unset($_SESSION['msg']); ?>
<?php endif; ?>

<?php if (empty($this->Dados['listFiles'])): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Nenhum arquivo encontrado.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover table-bordered table-sm">
            <thead class="thead-light">
                <tr>
                    <th class="text-center" style="width:50px">ID</th>
                    <th>Arquivo</th>
                    <th class="d-none d-md-table-cell">Loja</th>
                    <th class="d-none d-lg-table-cell">Tamanho</th>
                    <th class="d-none d-lg-table-cell">Cadastrado</th>
                    <th class="d-none d-md-table-cell text-center">Status</th>
                    <th class="text-center" style="width:160px">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->Dados['listFiles'] as $file): ?>
                    <?php
                    $mime = $file['mime_type'] ?? '';
                    $icon = $mimeIcons[$mime] ?? 'fa-file text-secondary';
                    $sizeFormatted = $file['file_size']
                        ? ($file['file_size'] >= 1048576
                            ? number_format($file['file_size'] / 1048576, 1, ',', '.') . ' MB'
                            : number_format($file['file_size'] / 1024, 0, ',', '.') . ' KB')
                        : '—';
                    ?>
                    <tr>
                        <td class="text-center"><?= htmlspecialchars($file['id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <i class="fas <?= $icon ?> me-1"></i>
                            <?= htmlspecialchars($file['title'] ?? $file['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            <small class="d-block d-md-none text-muted">
                                <?= htmlspecialchars($file['loja'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </small>
                        </td>
                        <td class="d-none d-md-table-cell"><?= htmlspecialchars($file['loja'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="d-none d-lg-table-cell"><?= $sizeFormatted ?></td>
                        <td class="d-none d-lg-table-cell">
                            <?php if (!empty($file['created_at'])): ?>
                                <?= htmlspecialchars(date('d/m/Y', strtotime($file['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell text-center">
                            <?php
                            $statusClass = match ((int)($file['status_id'] ?? 0)) {
                                1 => 'badge-success',
                                2 => 'badge-danger',
                                default => 'badge-secondary',
                            };
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($file['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td class="text-center">
                            <!-- Desktop -->
                            <span class="d-none d-md-inline">
                                <a href="<?= URLADM ?>download-file/download/<?= htmlspecialchars($file['id'], ENT_QUOTES, 'UTF-8') ?>"
                                   class="btn btn-outline-success btn-sm" title="Baixar">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                                <?php if (!empty($this->Dados['buttons']['view_file'])): ?>
                                    <button class="btn btn-outline-info btn-sm btn-view-file"
                                            data-id="<?= htmlspecialchars($file['id'], ENT_QUOTES, 'UTF-8') ?>"
                                            title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if (!empty($this->Dados['buttons']['edit_file'])): ?>
                                    <button class="btn btn-outline-warning btn-sm btn-edit-file"
                                            data-id="<?= htmlspecialchars($file['id'], ENT_QUOTES, 'UTF-8') ?>"
                                            title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if (!empty($this->Dados['buttons']['delete_file'])): ?>
                                    <button class="btn btn-outline-danger btn-sm btn-delete-file"
                                            data-id="<?= htmlspecialchars($file['id'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-name="<?= htmlspecialchars($file['title'] ?? $file['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </span>

                            <!-- Mobile -->
                            <div class="dropdown d-inline d-md-none">
                                <button class="btn btn-primary dropdown-toggle btn-sm" type="button"
                                        data-bs-toggle="dropdown">Ações</button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="<?= URLADM ?>download-file/download/<?= htmlspecialchars($file['id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-download me-1"></i> Baixar
                                    </a>
                                    <?php if (!empty($this->Dados['buttons']['view_file'])): ?>
                                        <button class="dropdown-item btn-view-file" data-id="<?= htmlspecialchars($file['id'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fas fa-eye me-1"></i> Visualizar
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!empty($this->Dados['buttons']['edit_file'])): ?>
                                        <button class="dropdown-item btn-edit-file" data-id="<?= htmlspecialchars($file['id'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fas fa-edit me-1"></i> Editar
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!empty($this->Dados['buttons']['delete_file'])): ?>
                                        <button class="dropdown-item btn-delete-file"
                                                data-id="<?= htmlspecialchars($file['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-name="<?= htmlspecialchars($file['title'] ?? $file['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fas fa-trash me-1"></i> Excluir
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $this->Dados['pagination'] ?? '' ?>
<?php endif; ?>
```

**Critério de aceite:**
- [ ] Ícones por tipo MIME (PDF, Excel, Word, imagem)
- [ ] Tamanho formatado (KB/MB)
- [ ] Badges coloridos por status
- [ ] Botões de ação via `data-id` (sem links diretos para delete)
- [ ] Layout responsivo (desktop: tabela completa, mobile: dropdown + nome da loja inline)
- [ ] htmlspecialchars em todos os outputs

---

### 4C. Modais CRUD

**Arquivos a criar:**
- `app/adms/Views/files/partials/_add_file_modal.php`
- `app/adms/Views/files/partials/_edit_file_modal.php`
- `app/adms/Views/files/partials/_view_file_modal.php`
- `app/adms/Views/files/partials/_delete_file_modal.php`

Os modais seguem o padrão documentado em `docs/DELETE_MODAL_IMPLEMENTATION_GUIDE.md` e o include genérico de `Views/include/_delete_confirmation_modal.php`.

**Critério de aceite:**
- [ ] Modal Add: form com título, loja (select), status (select), upload de arquivo
- [ ] Modal Edit: pré-populado via AJAX, upload opcional de novo arquivo
- [ ] Modal View: exibe todos os dados em modo leitura (título, loja, status, tamanho, tipo, datas, criador)
- [ ] Modal Delete: confirmação com nome do arquivo, botão de exclusão via POST
- [ ] Todos com `data-bs-backdrop="static"` para evitar fechamento acidental
- [ ] Indicadores de campo obrigatório (asterisco vermelho)

---

### 4D. JavaScript — files.js

**Arquivo a criar:**
- `assets/js/files.js`

**Implementação (estrutura):**
```javascript
/**
 * Files Module - JavaScript
 *
 * Gerencia CRUD de arquivos via AJAX com modais
 *
 * @prefix fl (files)
 */
'use strict';

const FilesModule = (() => {
    const CONTAINER = 'content_files';
    const BASE_URL = typeof URLADM !== 'undefined' ? URLADM : '/';

    // Cache de elementos DOM
    const elements = {
        container: () => document.getElementById(CONTAINER),
        addModal: () => document.getElementById('addFileModal'),
        editModal: () => document.getElementById('editFileModal'),
        viewModal: () => document.getElementById('viewFileModal'),
        deleteModal: () => document.getElementById('deleteFileModal'),
        searchForm: () => document.getElementById('search-form'),
    };

    /**
     * Inicializar módulo
     */
    function init() {
        bindEvents();
        bindPaginationEvents();
    }

    /**
     * Bind de eventos via event delegation
     */
    function bindEvents() {
        // Botão adicionar
        const btnAdd = document.getElementById('btn-add-file');
        if (btnAdd) {
            btnAdd.addEventListener('click', () => openAddModal());
        }

        // Busca/filtros
        const searchForm = elements.searchForm();
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                searchFiles(1);
            });
        }

        // Event delegation para botões dinâmicos na listagem
        document.addEventListener('click', (e) => {
            const viewBtn = e.target.closest('.btn-view-file');
            const editBtn = e.target.closest('.btn-edit-file');
            const deleteBtn = e.target.closest('.btn-delete-file');

            if (viewBtn) openViewModal(viewBtn.dataset.id);
            if (editBtn) openEditModal(editBtn.dataset.id);
            if (deleteBtn) openDeleteModal(deleteBtn.dataset.id, deleteBtn.dataset.name);
        });

        // Submit dos forms de modais
        const addForm = document.getElementById('add-file-form');
        if (addForm) {
            addForm.addEventListener('submit', (e) => {
                e.preventDefault();
                submitAddFile();
            });
        }

        const editForm = document.getElementById('edit-file-form');
        if (editForm) {
            editForm.addEventListener('submit', (e) => {
                e.preventDefault();
                submitEditFile();
            });
        }

        const deleteConfirm = document.getElementById('btn-confirm-delete-file');
        if (deleteConfirm) {
            deleteConfirm.addEventListener('click', () => submitDeleteFile());
        }
    }

    /**
     * Bind de eventos de paginação (re-executado após AJAX)
     */
    function bindPaginationEvents() {
        const container = elements.container();
        if (!container) return;

        container.querySelectorAll('.pagination a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const url = new URL(link.href);
                const page = url.pathname.split('/').pop() || 1;
                loadList(page);
            });
        });
    }

    /**
     * Carregar listagem via AJAX
     */
    async function loadList(page = 1) {
        try {
            const response = await fetch(`${BASE_URL}files/list/${page}?typefiles=1`);
            const html = await response.text();
            elements.container().innerHTML = html;
            bindPaginationEvents();
        } catch (error) {
            console.error('Erro ao carregar listagem:', error);
        }
    }

    /**
     * Buscar com filtros
     */
    async function searchFiles(page = 1) {
        const form = elements.searchForm();
        const formData = new FormData(form);

        try {
            const response = await fetch(`${BASE_URL}files/list/${page}?typefiles=2`, {
                method: 'POST',
                body: formData,
            });
            const html = await response.text();
            elements.container().innerHTML = html;
            bindPaginationEvents();
        } catch (error) {
            console.error('Erro na busca:', error);
        }
    }

    /**
     * Abrir modal de adicionar
     */
    function openAddModal() {
        const modal = elements.addModal();
        if (modal) {
            modal.querySelector('form')?.reset();
            new bootstrap.Modal(modal).show();
        }
    }

    /**
     * Submeter formulário de adicionar
     */
    async function submitAddFile() {
        const form = document.getElementById('add-file-form');
        const formData = new FormData(form);
        const btn = form.querySelector('[type="submit"]');
        btn.disabled = true;

        try {
            const response = await fetch(`${BASE_URL}add-file/create`, {
                method: 'POST',
                body: formData,
            });
            const data = await response.json();

            if (data.success) {
                bootstrap.Modal.getInstance(elements.addModal())?.hide();
                loadList(1);
                showToast('success', data.message);
            } else {
                showToast('danger', data.message);
            }
        } catch (error) {
            showToast('danger', 'Erro ao cadastrar arquivo.');
        } finally {
            btn.disabled = false;
        }
    }

    /**
     * Abrir modal de editar (carrega dados via AJAX)
     */
    async function openEditModal(fileId) {
        try {
            const response = await fetch(`${BASE_URL}edit-file/edit/${fileId}`);
            const data = await response.json();

            if (data.error) {
                showToast('danger', data.message);
                return;
            }

            const file = data.file;
            const modal = elements.editModal();
            modal.querySelector('#edit-file-id').value = file.id;
            modal.querySelector('#edit-file-title').value = file.title || file.nome || '';
            modal.querySelector('#edit-file-store').value = file.loja_id;
            modal.querySelector('#edit-file-status').value = file.status_id;

            const currentFile = modal.querySelector('#edit-current-file');
            if (currentFile) {
                currentFile.textContent = file.filename || file.slug || '';
            }

            new bootstrap.Modal(modal).show();
        } catch (error) {
            showToast('danger', 'Erro ao carregar dados do arquivo.');
        }
    }

    /**
     * Submeter formulário de editar
     */
    async function submitEditFile() {
        const form = document.getElementById('edit-file-form');
        const formData = new FormData(form);
        const btn = form.querySelector('[type="submit"]');
        btn.disabled = true;

        try {
            const response = await fetch(`${BASE_URL}edit-file/update`, {
                method: 'POST',
                body: formData,
            });
            const data = await response.json();

            if (data.success) {
                bootstrap.Modal.getInstance(elements.editModal())?.hide();
                loadList(1);
                showToast('success', data.message);
            } else {
                showToast('danger', data.message);
            }
        } catch (error) {
            showToast('danger', 'Erro ao atualizar arquivo.');
        } finally {
            btn.disabled = false;
        }
    }

    /**
     * Abrir modal de visualizar
     */
    async function openViewModal(fileId) {
        try {
            const response = await fetch(`${BASE_URL}edit-file/edit/${fileId}`);
            const data = await response.json();

            if (data.error) {
                showToast('danger', data.message);
                return;
            }

            const file = data.file;
            const modal = elements.viewModal();

            modal.querySelector('#view-file-title').textContent = file.title || file.nome || '';
            modal.querySelector('#view-file-store').textContent = file.loja || '';
            modal.querySelector('#view-file-status').textContent = file.status || '';
            modal.querySelector('#view-file-filename').textContent = file.original_filename || file.filename || '';
            modal.querySelector('#view-file-size').textContent = formatFileSize(file.file_size);
            modal.querySelector('#view-file-type').textContent = file.mime_type || '—';
            modal.querySelector('#view-file-created').textContent = formatDate(file.created_at || file.created);
            modal.querySelector('#view-file-updated').textContent = formatDate(file.updated_at || file.modified);
            modal.querySelector('#view-file-created-by').textContent = file.created_by_name || '—';

            const downloadLink = modal.querySelector('#view-file-download');
            if (downloadLink) {
                downloadLink.href = `${BASE_URL}download-file/download/${file.id}`;
            }

            new bootstrap.Modal(modal).show();
        } catch (error) {
            showToast('danger', 'Erro ao carregar detalhes do arquivo.');
        }
    }

    /**
     * Abrir modal de exclusão
     */
    function openDeleteModal(fileId, fileName) {
        const modal = elements.deleteModal();
        modal.querySelector('#delete-file-id').value = fileId;
        modal.querySelector('#delete-file-name').textContent = fileName;
        new bootstrap.Modal(modal).show();
    }

    /**
     * Submeter exclusão
     */
    async function submitDeleteFile() {
        const fileId = document.getElementById('delete-file-id').value;
        const btn = document.getElementById('btn-confirm-delete-file');
        btn.disabled = true;

        const formData = new FormData();
        formData.append('id', fileId);
        formData.append('_csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

        try {
            const response = await fetch(`${BASE_URL}delete-file/delete`, {
                method: 'POST',
                body: formData,
            });
            const data = await response.json();

            if (data.success) {
                bootstrap.Modal.getInstance(elements.deleteModal())?.hide();
                loadList(1);
                showToast('success', data.message);
            } else {
                showToast('danger', data.message);
            }
        } catch (error) {
            showToast('danger', 'Erro ao excluir arquivo.');
        } finally {
            btn.disabled = false;
        }
    }

    // Utilidades
    function formatFileSize(bytes) {
        if (!bytes) return '—';
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
        return Math.round(bytes / 1024).toLocaleString('pt-BR') + ' KB';
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return d.toLocaleDateString('pt-BR');
    }

    function showToast(type, message) {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show`;
        toast.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        container.prepend(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;top:70px;right:20px;z-index:9999;max-width:400px';
        document.body.appendChild(container);
        return container;
    }

    // Inicializar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { loadList, searchFiles };
})();
```

**Critério de aceite:**
- [ ] Módulo IIFE com prefixo `fl` (files)
- [ ] CRUD completo via AJAX com modais
- [ ] Event delegation para botões dinâmicos
- [ ] Paginação AJAX (sem page reload)
- [ ] Busca com filtros
- [ ] Toast notifications para feedback
- [ ] Formatação de tamanho de arquivo e datas
- [ ] Botão submit desabilitado durante request (previne duplo clique)

---

<a name="fase-5"></a>
## Fase 5 — Integrações (Logging, Notificações, Permissões)

### 5A. LoggerService em Todas as Operações

**Já implementado no model `AdmsFile.php` (Fase 3E):**
- `FILE_CREATED` — ao criar arquivo com sucesso
- `FILE_UPDATED` — ao atualizar arquivo
- `FILE_DELETED` — ao excluir arquivo (inclui dados completos para auditoria)
- `FILE_CREATE_FAILED`, `FILE_UPLOAD_FAILED`, `FILE_DELETE_FAILED` — em erros

**Critério de aceite:**
- [ ] Toda operação CRUD gera log no `adms_logs`
- [ ] Logs incluem `file_id`, `store_id`, `filename`
- [ ] DELETE inclui snapshot completo do registro antes da exclusão

---

### 5B. StorePermissionTrait em Listagem e Estatísticas

**Já implementado nos models `AdmsListFiles.php` e `AdmsStatisticsFiles.php` (Fases 3F e 3G).**

**Critério de aceite:**
- [ ] Nível 1 (Super Admin): vê arquivos de todas as lojas
- [ ] Nível 2-3 (Admin/RH): vê arquivos de todas as lojas
- [ ] Nível 4+ (Operacional): vê apenas arquivos da sua loja
- [ ] Estatísticas refletem o mesmo filtro da listagem

---

### 5C. WebSocket Notifications (Opcional — Fase Futura)

**Problema:** Outros módulos notificam em tempo real via WebSocket quando há criação/exclusão. O módulo de arquivos não tem essa integração.

**Solução:** Adicionar `SystemNotificationService::notify()` no model `AdmsFile.php` após operações de criação e exclusão.

**Implementação (adicionar em `AdmsFile::create()` após sucesso):**
```php
// Notificar gestores da loja sobre novo arquivo
try {
    SystemNotificationService::notifyStoreManagers(
        $postData['loja_id'],
        'files',
        "Novo arquivo disponível: {$postData['title']}",
        SessionContext::getUserId()
    );
} catch (\Exception $e) {
    // Fire-and-forget: não bloquear operação principal
}
```

**Critério de aceite:**
- [ ] Notificação enviada ao criar arquivo (para gestores da loja)
- [ ] Self-notification prevention (exclui o próprio usuário)
- [ ] Fire-and-forget (try/catch, não bloqueia)

---

<a name="fase-6"></a>
## Fase 6 — Melhorias UX/UI

### 6A. Ícones por Tipo de Arquivo

**Já implementado em `listFiles.php` (Fase 4B)** com mapa de MIME types para Font Awesome icons.

---

### 6B. Drag-and-Drop no Upload (Opcional)

**Problema:** Upload atual é apenas via `<input type="file">`, pouco intuitivo.

**Solução:** Adicionar área de drag-and-drop no modal de cadastro.

**Implementação (adicionar em `_add_file_modal.php`):**
```html
<div id="drop-zone" class="border border-2 border-dashed rounded p-4 text-center mb-3"
     style="border-color: #dee2e6 !important; cursor: pointer; transition: all 0.3s;">
    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
    <p class="mb-1">Arraste e solte o arquivo aqui</p>
    <p class="text-muted small mb-0">ou clique para selecionar</p>
    <input type="file" name="file" id="add-file-input" class="d-none">
    <div id="file-preview" class="mt-2 d-none">
        <span class="badge bg-primary" id="file-preview-name"></span>
        <button type="button" class="btn btn-sm btn-link text-danger" id="file-preview-remove">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
```

**JavaScript (adicionar em `files.js`):**
```javascript
function initDropZone() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('add-file-input');
    if (!dropZone || !fileInput) return;

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#007bff';
        dropZone.style.backgroundColor = '#f0f7ff';
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.style.borderColor = '#dee2e6';
        dropZone.style.backgroundColor = '';
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#dee2e6';
        dropZone.style.backgroundColor = '';
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            showFilePreview(e.dataTransfer.files[0].name);
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            showFilePreview(fileInput.files[0].name);
        }
    });
}

function showFilePreview(name) {
    const preview = document.getElementById('file-preview');
    const previewName = document.getElementById('file-preview-name');
    previewName.textContent = name;
    preview.classList.remove('d-none');
}
```

**Critério de aceite:**
- [ ] Área visual de drag-and-drop com ícone de upload
- [ ] Hover effect ao arrastar arquivo sobre a área
- [ ] Preview do nome do arquivo selecionado
- [ ] Funciona também via clique (fallback para input file)
- [ ] Máximo 4MB exibido como informação

---

### 6C. Exibir Nome do Arquivo Atual na Edição

**Implementado no modal de edição** — campo `#edit-current-file` mostra o nome do arquivo atual antes de selecionar um novo.

**Critério de aceite:**
- [ ] Nome do arquivo atual exibido abaixo do input file
- [ ] Texto explicativo: "Arquivo atual: nome.pdf — Selecione um novo arquivo para substituir"

---

<a name="fase-7"></a>
## Fase 7 — Testes Unitários

### 7A. Testes do Model AdmsFile

**Arquivo a criar:**
- `tests/Files/AdmsFileTest.php`

**Escopo:**
```php
class AdmsFileTest extends TestCase
{
    // Create
    public function testCreateWithValidData(): void
    public function testCreateFailsWithoutTitle(): void
    public function testCreateFailsWithoutStore(): void
    public function testCreateFailsWithoutFile(): void
    public function testCreateSetsAuditFields(): void

    // Update
    public function testUpdateWithValidData(): void
    public function testUpdateWithNewFile(): void
    public function testUpdateWithoutNewFile(): void
    public function testUpdateFailsWithInvalidId(): void

    // Delete
    public function testDeleteExistingFile(): void
    public function testDeleteFailsWithInvalidId(): void
    public function testDeleteRemovesPhysicalFile(): void
}
```

**Critério de aceite:**
- [ ] ~12 testes cobrindo create, update, delete
- [ ] Usa `SessionContext::setTestData()` para mock de sessão
- [ ] Testes passam com `vendor/bin/phpunit tests/Files/`

---

### 7B. Testes do Model AdmsListFiles

**Arquivo a criar:**
- `tests/Files/AdmsListFilesTest.php`

**Escopo:**
```php
class AdmsListFilesTest extends TestCase
{
    public function testListReturnsArray(): void
    public function testListWithPagination(): void
    public function testSearchByTitle(): void
    public function testSearchByStore(): void
    public function testSearchByStatus(): void
    public function testStorePermissionFilter(): void
}
```

---

### 7C. Testes do Model AdmsStatisticsFiles

**Arquivo a criar:**
- `tests/Files/AdmsStatisticsFilesTest.php`

**Escopo:**
```php
class AdmsStatisticsFilesTest extends TestCase
{
    public function testGetStatisticsReturnsExpectedKeys(): void
    public function testTotalCountMatchesDatabase(): void
    public function testActiveCountIsSubsetOfTotal(): void
    public function testTotalSizeIsNonNegative(): void
}
```

**Critério de aceite total (Fase 7):**
- [ ] ~25-30 testes unitários
- [ ] Cobertura de todos os métodos públicos dos 3 models
- [ ] Todos passando no CI

---

<a name="fase-8"></a>
## Fase 8 — Limpeza e Documentação

### 8A. Remover Arquivos Legados

**Arquivos a remover após migração completa e verificação:**
```
app/adms/Controllers/Arquivo.php
app/adms/Controllers/CadastrarArquivo.php
app/adms/Controllers/EditarArquivo.php
app/adms/Controllers/ApagarArquivo.php
app/adms/Controllers/ListarArquivo.php          (duplicado)

app/adms/Models/AdmsListarArquivo.php
app/adms/Models/AdmsListarArq.php               (duplicado)
app/adms/Models/AdmsCadastrarArquivo.php
app/adms/Models/AdmsEditarArquivo.php
app/adms/Models/AdmsApagarArquivo.php

app/adms/Views/upload/arquivo.php
app/adms/Views/upload/cadArquivo.php
app/adms/Views/upload/editArquivo.php
```

**Critério de aceite:**
- [ ] Todos os 13 arquivos legados removidos
- [ ] Nenhuma referência restante a `AdmsListarArq`, `AdmsCadastrarArquivo`, etc.
- [ ] Diretório `Views/upload/` vazio (ou removido se não usado por outro módulo)

---

### 8B. Verificar Referências Residuais

**Comando de verificação:**
```bash
grep -rn "AdmsListarArquivo\|AdmsCadastrarArquivo\|AdmsEditarArquivo\|AdmsApagarArquivo\|AdmsListarArq\|adms_up_down" app/ core/ --include="*.php"
```

**Critério de aceite:**
- [ ] Zero referências a classes e tabela legadas em todo o codebase
- [ ] Todas as referências apontam para `adms_files` e classes novas

---

<a name="dependencias"></a>
## Dependências entre Fases

```
Fase 1 (Segurança)     ──→ Pode ser aplicada IMEDIATAMENTE no código legado
                             Independente das demais fases

Fase 2 (Banco)          ──→ PRÉ-REQUISITO para Fase 3, 4, 5, 6, 7
                             Deve ser executada em ambiente de staging primeiro

Fase 3 (Backend)        ──→ Depende de Fase 2
                             PRÉ-REQUISITO para Fase 4 (views consomem novos controllers)

Fase 4 (Frontend)       ──→ Depende de Fase 3
                             Pode ser desenvolvida em paralelo parcial com Fase 5

Fase 5 (Integrações)    ──→ LoggerService e StorePermission já estão na Fase 3
                             WebSocket notifications pode ser feito em paralelo com Fase 4

Fase 6 (UX/UI)          ──→ Depende de Fase 4 (views base devem existir)
                             Incrementos opcionais, podem ser feitos gradualmente

Fase 7 (Testes)         ──→ Depende de Fase 3 (models devem existir)
                             Pode ser desenvolvida em paralelo com Fase 4

Fase 8 (Limpeza)        ──→ ÚLTIMA FASE — só após todas as anteriores concluídas e validadas
```

### Diagrama de Dependências

```
  ┌─────────┐
  │ Fase 1  │ ← Aplicar AGORA (independente)
  │Segurança│
  └─────────┘

  ┌─────────┐
  │ Fase 2  │ ← Primeiro passo da refatoração
  │  Banco  │
  └────┬────┘
       │
  ┌────▼────┐
  │ Fase 3  │
  │ Backend │
  └────┬────┘
       │
  ┌────▼────┐     ┌─────────┐     ┌─────────┐
  │ Fase 4  │────►│ Fase 6  │     │ Fase 7  │
  │Frontend │     │  UX/UI  │     │ Testes  │
  └────┬────┘     └─────────┘     └────┬────┘
       │                               │
  ┌────▼────┐                          │
  │ Fase 5  │                          │
  │Integraç.│                          │
  └────┬────┘                          │
       │          ┌─────────┐          │
       └─────────►│ Fase 8  │◄─────────┘
                  │ Limpeza │
                  └─────────┘
```

---

<a name="arquivos"></a>
## Estimativa de Arquivos por Fase

| Fase | Novos | Modificados | Removidos | Total Impacto |
|------|-------|-------------|-----------|---------------|
| 1 - Segurança | 2 | 3 | 0 | 5 |
| 2 - Banco | 2 | 0 | 0 | 2 |
| 3 - Backend | 5 | 0 | 0 | 5 |
| 4 - Frontend | 7 | 0 | 0 | 7 |
| 5 - Integrações | 0 | 1 | 0 | 1 |
| 6 - UX/UI | 0 | 2 | 0 | 2 |
| 7 - Testes | 3 | 0 | 0 | 3 |
| 8 - Limpeza | 0 | 0 | 13 | 13 |
| **TOTAL** | **19** | **6** | **13** | **38** |

---

<a name="resumo"></a>
## Resumo Geral

| Fase | Descrição | Prioridade | Dependência |
|------|-----------|------------|-------------|
| 1 | Correções críticas de segurança (XSS, CSRF, downloads) | **URGENTE** | Nenhuma |
| 2 | Migração de schema (tabela + colunas + rotas) | Alta | Nenhuma |
| 3 | Controllers + Models modernos (match, type hints, CRUD unificado) | Alta | Fase 2 |
| 4 | Views AJAX + Modais + JavaScript dedicado | Alta | Fase 3 |
| 5 | LoggerService + StorePermission + WebSocket | Média | Fase 3 |
| 6 | Drag-and-drop, melhorias visuais | Baixa | Fase 4 |
| 7 | Testes unitários (~30 testes) | Média | Fase 3 |
| 8 | Remoção de código legado + verificação de referências | Final | Todas |

### Impacto Final Esperado

| Critério | Antes | Depois |
|----------|-------|--------|
| Nota geral | 3/10 | 9/10 |
| Nomenclatura | 2/10 | 10/10 |
| Segurança | 4/10 | 9/10 |
| Testes | 0/10 | 8/10 |
| UX/UI | 3/10 | 9/10 |
| Logging | 0/10 | 10/10 |

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
**Versão:** 1.0
**Última Atualização:** 26/03/2026
