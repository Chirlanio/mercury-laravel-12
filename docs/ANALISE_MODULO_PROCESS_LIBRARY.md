# Modulo: Biblioteca de Processos (Process Library)

**Versao:** 1.0
**Data:** 18/02/2026
**Status:** Moderno (refatorado em Fev/2026)

---

## 1. Visao Geral

A Biblioteca de Processos e o repositorio centralizado de documentos normativos do Grupo Meia Sola. Gerencia politicas, processos operacionais e manuais de procedimentos com controle de versao, niveis de acesso, upload de arquivos, extracao automatica de texto e notificacoes de vencimento.

### Funcionalidades Principais

| Funcionalidade | Descricao |
|---|---|
| CRUD completo | Cadastro, visualizacao, edicao e exclusao de processos/politicas |
| Interface AJAX + Modais | Operacoes sem reload de pagina via Bootstrap 4 modals |
| Busca avancada | 6 filtros: titulo, area, setor, status, data inicio, data fim |
| Dashboard estatistico | 5 cards + alertas colapsaveis de vencimento |
| Upload de arquivos | Multi-file upload com gerenciamento individual |
| Extracao de texto | PDF, DOCX, TXT, MD → conteudo HTML via CKEditor |
| Controle de acesso M:N | Niveis de acesso configurados por processo |
| Indicadores de vencimento | Badges e bordas visuais nos cards da listagem |
| Notificacao por email (cron) | Alertas automaticos para gestores sobre vencimentos |
| Auditoria | LoggerService em todas as operacoes CRUD |

---

## 2. Arquitetura

### 2.1 Padrao MVC

```
[Browser] → [ConfigController (routing)] → [Controller] → [Model] → [MySQL]
                                               ↓
                                          [ConfigView] → [View (.php)] → HTML
```

### 2.2 Dual Mode (AJAX + Fallback)

Todos os controllers suportam dois modos de operacao:

- **AJAX** (padrao): Detectado via `HTTP_X_REQUESTED_WITH`. Retorna JSON ou HTML parcial para modais.
- **Fallback**: Pagina completa com menu lateral para acesso direto via URL.

### 2.3 Fluxo de Requisicao (Match Expression)

```php
// ProcessLibrary::list()
match ($requestType) {
    1 => $this->ajaxList(),       // Listagem paginada
    2 => $this->ajaxSearch(),     // Busca com filtros
    default => $this->fullPage(), // Pagina completa (shell)
};
```

---

## 3. Estrutura de Arquivos

### 3.1 Controllers (6 arquivos)

| Arquivo | Classe | Metodo Principal | Rota |
|---|---|---|---|
| `ProcessLibrary.php` | `ProcessLibrary` | `list(?int $pageId)` | `process-library/list` |
| `AddProcessLibrary.php` | `AddProcessLibrary` | `create()` | `add-process-library/create` |
| `EditProcessLibrary.php` | `EditProcessLibrary` | `edit(?int $dadosId)` | `edit-process-library/edit` |
| `ViewProcessLibrary.php` | `ViewProcessLibrary` | `view(?int $dadosId)` | `view-process-library/view` |
| `DeleteProcessLibrary.php` | `DeleteProcessLibrary` | `delete(?int $dadosId)` | `delete-process-library/delete` |
| `ExtractProcessText.php` | `ExtractProcessText` | `extract()` | `extract-process-text/extract` |

**Diretorio:** `app/adms/Controllers/`

### 3.2 Models (7 arquivos)

| Arquivo | Classe | Responsabilidade |
|---|---|---|
| `AdmsListProcessLibrary.php` | `AdmsListProcessLibrary` | Listagem, busca com filtros, paginacao, areas |
| `AdmsAddProcessLibrary.php` | `AdmsAddProcessLibrary` | Insercao de processo + arquivos + niveis de acesso |
| `AdmsEditProcessLibrary.php` | `AdmsEditProcessLibrary` | Atualizacao + sync access levels + gerenciamento de arquivos |
| `AdmsViewProcessLibrary.php` | `AdmsViewProcessLibrary` | Leitura detalhada + verificacao de acesso do usuario |
| `AdmsDelProcessLibrary.php` | `AdmsDelProcessLibrary` | Exclusao cascata (processo + arquivos fisicos + DB) |
| `AdmsStatisticsProcessLibrary.php` | `AdmsStatisticsProcessLibrary` | Estatisticas do dashboard (7 metricas) |
| `AdmsCheckProcessLibraryExpiration.php` | `AdmsCheckProcessLibraryExpiration` | Cron: verificacao de vencimentos + envio de emails |

**Diretorio:** `app/adms/Models/`

### 3.3 Views (12 arquivos)

**Diretorio:** `app/adms/Views/processLibrary/`

| Arquivo | Tipo | Descricao |
|---|---|---|
| `loadProcessLibrary.php` | Shell | Pagina principal: header, filtros, estatisticas, container AJAX, modais |
| `listProcessLibrary.php` | Parcial AJAX | Accordion por area com cards de processos + paginacao |
| `addProcessLibrary.php` | Fallback | Formulario de cadastro standalone |
| `editProcessLibrary.php` | Fallback | Formulario de edicao standalone |
| `viewProcessLibrary.php` | Fallback | Pagina de visualizacao standalone |

**Diretorio:** `app/adms/Views/processLibrary/partials/`

| Arquivo | Tipo | Descricao |
|---|---|---|
| `_statistics_dashboard.php` | Dashboard | 5 cards estatisticos + alertas colapsaveis de vencimento |
| `_add_process_library_modal.php` | Modal | Formulario de cadastro com CKEditor |
| `_edit_process_library_modal.php` | Modal | Container para formulario de edicao (AJAX-loaded) |
| `_edit_process_library_form.php` | Parcial | Formulario completo de edicao renderizado via AJAX |
| `_view_process_library_content.php` | Parcial | Conteudo de visualizacao renderizado via AJAX |
| `_delete_process_library_modal.php` | Modal | Confirmacao de exclusao com detalhes do processo |
| `_delete_file_confirmation_modal.php` | Modal | Confirmacao de exclusao de arquivo individual |
| `_replace_content_modal.php` | Modal | Opcoes: substituir ou adicionar texto extraido |

### 3.4 JavaScript (1 arquivo, 951 linhas)

| Arquivo | Descricao |
|---|---|
| `assets/js/process-library.js` | Modulo ES6+ com AJAX, modais, CKEditor, extracao de texto, notificacoes |

### 3.5 CSS

| Arquivo | Classes Adicionadas |
|---|---|
| `assets/css/personalizado.css` | `.process-expired`, `.process-expiring`, secao "Content Tables" |

### 3.6 Cron

| Arquivo | Descricao |
|---|---|
| `check_process_library_cron.php` | Entry point do cron de verificacao de vencimentos (raiz do projeto) |

### 3.7 Migrations SQL

| Arquivo | Descricao |
|---|---|
| `docs/migrations/2026_02_18_process_library_access_levels.sql` | Tabela M:N de niveis de acesso + seed |
| `docs/sql/migration_process_library_2026_02_18.sql` | Coluna `content`, rota de extracao, metodos modernizados |

---

## 4. Tabelas do Banco de Dados

### 4.1 Tabela Principal: `adms_process_librarys`

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK, AI) | Identificador unico |
| `title` | VARCHAR(255) | Titulo do processo/politica |
| `content` | LONGTEXT | Conteudo HTML (editado via CKEditor ou extraido de arquivo) |
| `adms_cats_process_id` | INT (FK) | Categoria → `adms_cats_process_librarys` |
| `version_number` | VARCHAR(50) | Numero da versao (ex: "1.0", "2.1") |
| `adms_area_id` | INT (FK) | Area → `adms_areas` |
| `adms_manager_area_id` | INT (FK) | Gestor de area → `adms_managers` |
| `adms_sector_id` | INT (FK) | Setor → `adms_sectors` |
| `adms_manager_sector_id` | INT (FK) | Gestor de setor → `adms_employees` |
| `date_validation_start` | DATE | Inicio da vigencia |
| `date_validation_end` | DATE | Fim da vigencia |
| `adms_sit_id` | INT (FK) | Status → `adms_sits` (1=Ativo) |
| `created` | DATETIME | Data de criacao |
| `modified` | DATETIME | Data da ultima alteracao |

### 4.2 Tabela de Arquivos: `adms_process_library_files`

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK, AI) | Identificador unico |
| `adms_process_library_id` | INT (FK) | Processo associado (CASCADE on DELETE) |
| `exibition_name` | VARCHAR(255) | Nome original do arquivo |
| `file_name_slug` | VARCHAR(255) | Nome slugificado (armazenamento) |
| `status_id` | INT | Status do arquivo (1=Ativo) |
| `created` | DATETIME | Data de upload |

**Armazenamento fisico:** `assets/files/processLibrary/{process_id}/`

### 4.3 Tabela de Niveis de Acesso: `adms_process_library_access_levels`

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK, AI) | Identificador unico |
| `adms_process_library_id` | INT (FK) | Processo associado (CASCADE on DELETE) |
| `adms_niveis_acesso_id` | INT (FK) | Nivel de acesso (CASCADE on DELETE) |
| `created` | DATETIME | Data de associacao |

**Constraint:** UNIQUE KEY `uk_process_level` (process_library_id, niveis_acesso_id)

### 4.4 Tabela de Notificacoes: `adms_process_library_notifications`

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK, AI) | Identificador unico |
| `process_library_id` | INT (FK) | Processo associado (CASCADE on DELETE) |
| `notification_type` | VARCHAR(30) | `expiring_30d` ou `expired` |
| `recipient_type` | VARCHAR(30) | `area_manager` ou `sector_manager` |
| `recipient_email` | VARCHAR(255) | Email do destinatario |
| `sent_at` | DATETIME | Data/hora do envio |

**Indice:** `idx_process_notif` (process_library_id, notification_type, recipient_type)

### 4.5 Tabelas Relacionadas (externas ao modulo)

| Tabela | Relacao | Campos Utilizados |
|---|---|---|
| `adms_areas` | Area do processo | `id`, `name`, `status_id` |
| `adms_sectors` | Setor do processo | `id`, `sector_name`, `adms_sit_id` |
| `adms_managers` | Gestor de area | `id`, `name`, `email`, `status_id` |
| `adms_employees` | Gestor de setor | `id`, `name_employee`, `email`, `position_id` |
| `adms_cats_process_librarys` | Categoria | `id`, `name_category`, `adms_sits_id` |
| `adms_sits` | Status geral | `id`, `nome` |
| `adms_cors` | Cores dos status | `id`, `color` |
| `adms_niveis_acessos` | Niveis de acesso | `id`, `nome`, `ordem` |
| `adms_paginas` | Rotas do sistema | `controller`, `metodo`, `menu_controller` |
| `adms_nivacs_pgs` | Permissoes por nivel | `adms_niveis_acesso_id`, `adms_pagina_id` |

---

## 5. Rotas e Permissoes

### 5.1 Rotas Registradas em `adms_paginas`

| menu_controller | menu_metodo | Controller | Metodo | Descricao |
|---|---|---|---|---|
| `process-library` | `list` | `ProcessLibrary` | `list` | Listagem principal |
| `add-process-library` | `create` | `AddProcessLibrary` | `create` | Cadastrar processo |
| `edit-process-library` | `edit` | `EditProcessLibrary` | `edit` | Editar processo |
| `view-process-library` | `view` | `ViewProcessLibrary` | `view` | Visualizar processo |
| `delete-process-library` | `delete` | `DeleteProcessLibrary` | `delete` | Excluir processo |
| `extract-process-text` | `extract` | `ExtractProcessText` | `extract` | API de extracao de texto |

### 5.2 Botoes de Permissao (AdmsBotao)

| Chave | Rota | Controla |
|---|---|---|
| `add_process` | `add-process-library/create` | Botao "Novo" + modal de cadastro |
| `view_process` | `view-process-library/view` | Botao olho (visualizar) nos cards |
| `edit_process` | `edit-process-library/edit` | Botao caneta (editar) nos cards |
| `del_process` | `delete-process-library/delete` | Botao borracha (excluir) nos cards |

### 5.3 Controle de Acesso ao Conteudo

```
Super Admin (ordem_nivac == SUPADMPERMITION)
    → Ve todos os processos sem restricao

Demais usuarios
    → Filtro via adms_process_library_access_levels
    → So ve processos onde seu nivel de acesso esta associado
    → Verificacao em: listagem, visualizacao, edicao, exclusao
```

---

## 6. Fluxos Detalhados

### 6.1 Listagem (Pagina Principal)

```
GET /process-library/list
    |
    v
ProcessLibrary::list() → fullPage()
    |-- loadButtons()       → Permissoes via AdmsBotao
    |-- loadStatistics()    → 7 metricas (ativos, categoria, vencidos, vencendo, arquivos, listas)
    |-- loadSelectData()    → Areas, setores, status para filtros
    |-- loadAddFormSelects() → Selects do modal de cadastro
    |
    v
loadProcessLibrary.php (shell)
    |-- _statistics_dashboard.php (cards + alertas)
    |-- Filtros de busca (6 campos)
    |-- Container AJAX (#content_process_library)
    |-- 6 modais incluidos
    |-- process-library.js carregado
    |
    v
JS: ProcessLibrary.init() → loadList()
    |
    v
AJAX GET /process-library/list/1?request_type=1
    |
    v
ProcessLibrary::list() → ajaxList()
    |-- AdmsListProcessLibrary::list()
    |-- listAreas()
    |
    v
listProcessLibrary.php (parcial)
    |-- Accordion por area
    |-- Cards com indicadores de vencimento
    |-- Paginacao
```

### 6.2 Busca com Filtros

```
Usuario digita/seleciona filtro
    |
    v
JS: debounce 400ms → search()
    |
    v
AJAX POST /process-library/list/1?request_type=2
    Body: searchTerm, searchArea, searchSector, searchStatus, searchDateFrom, searchDateTo
    |
    v
ProcessLibrary::list() → ajaxSearch()
    |-- AdmsListProcessLibrary::listWithFilters(page, filters)
    |-- WHERE dinamico montado conforme filtros preenchidos
    |
    v
listProcessLibrary.php (parcial) atualizado no container
```

### 6.3 Cadastro (Modal)

```
Clique em "Novo"
    |
    v
Modal #addProcessLibraryModal abre
    |-- Formulario com 5 secoes:
    |   1. Dados Basicos (titulo, categoria, versao)
    |   2. Area e Gestores (area, gestor area, setor, gestor setor)
    |   3. Vigencia e Situacao (data inicio, data fim, status)
    |   4. Niveis de Acesso (checkboxes com Selecionar/Desmarcar Todos)
    |   5. Arquivo e Conteudo (file upload + CKEditor)
    |
    v
Submit AJAX POST /add-process-library/create
    |-- Sanitizacao do conteudo CKEditor (strip_tags com ALLOWED_TAGS)
    |-- Validacao de campos obrigatorios
    |-- Upload de arquivo(s) → assets/files/processLibrary/{id}/
    |-- Extracao automatica de texto do arquivo
    |-- Insercao na tabela principal
    |-- Salvamento dos niveis de acesso (M:N)
    |-- LoggerService::info('PROCESS_LIBRARY_CREATED')
    |-- NotificationService::staticSuccess()
    |
    v
JSON response → JS atualiza listagem + notificacao
```

### 6.4 Edicao (Modal)

```
Clique no botao editar (caneta)
    |
    v
JS: editProcess(id)
    |
    v
AJAX GET /edit-process-library/edit/{id}
    |-- Verifica userCanAccess()
    |-- Carrega dados do processo + niveis de acesso atuais
    |-- Carrega selects + lista de arquivos
    |
    v
_edit_process_library_form.php renderizado no modal
    |-- CKEditor inicializado com conteudo existente
    |-- Arquivos listados com botoes download/excluir
    |
    v
Submit AJAX POST /edit-process-library/edit/{id}
    |-- Sanitizacao CKEditor
    |-- UPDATE tabela principal
    |-- syncAccessLevels() (delete old + insert new)
    |-- Upload de novos arquivos (se enviados)
    |-- Limpeza de notificacoes antigas (adms_process_library_notifications)
    |-- LoggerService::info('PROCESS_LIBRARY_UPDATED')
    |
    v
JSON response → JS atualiza listagem + notificacao
```

### 6.5 Visualizacao (Modal)

```
Clique no botao visualizar (olho)
    |
    v
JS: viewProcess(id)
    |
    v
AJAX GET /view-process-library/view/{id}
    |-- Verifica userCanAccess()
    |-- Carrega dados completos + niveis de acesso + arquivos
    |
    v
_view_process_library_content.php renderizado no modal
    |-- Cards com metadados (titulo, area, setor, gestores, versao, vigencia)
    |-- Badges de niveis de acesso
    |-- Conteudo do documento em div scrollable
    |-- Links de download de arquivos
    |-- Botao "Editar" no footer do modal (se tiver permissao)
```

### 6.6 Exclusao (Modal)

```
Clique no botao excluir (borracha)
    |
    v
JS: openDeleteModal(data)
    |-- Popula modal com titulo, area, setor, status
    |
    v
Confirmacao no modal
    |
    v
AJAX POST /delete-process-library/delete/{id}
    |-- Verifica userCanAccess()
    |-- Busca arquivos associados
    |-- Exclui arquivos fisicos (AdmsApagarImg)
    |-- Exclui registros de arquivos (DB)
    |-- Exclui processo principal
    |-- LoggerService::info('PROCESS_LIBRARY_DELETED')
    |
    v
JSON response → JS atualiza listagem + notificacao
```

### 6.7 Extracao de Texto

```
Upload de arquivo no formulario (add ou edit)
    |
    v
JS: extractText(fileInput, mode)
    |-- Valida extensao (pdf, doc, docx, txt, md)
    |-- Mostra progress indicator
    |
    v
AJAX POST /extract-process-text/extract
    |-- TextExtractionService::extract(tmpName, mimeType)
    |-- Retorna HTML formatado
    |
    v
JS recebe conteudo
    |-- Se CKEditor vazio: insere direto
    |-- Se CKEditor tem conteudo: abre modal _replace_content_modal
    |       |-- "Substituir": substitui todo conteudo
    |       |-- "Adicionar ao Final": append ao final
    |       |-- "Cancelar": descarta texto extraido
```

### 6.8 Exclusao de Arquivo Individual

```
Clique no botao excluir arquivo (no modal de edicao)
    |
    v
JS: abre _delete_file_confirmation_modal com nome do arquivo
    |
    v
Confirmacao
    |
    v
AJAX POST /edit-process-library/edit/{id}
    Body: delete_file={filename}
    |
    v
EditProcessLibrary::deleteFileAjax()
    |-- AdmsEditProcessLibrary::deleteFile(processId, filename)
    |   |-- Remove registro do DB (adms_process_library_files)
    |   |-- Remove arquivo fisico (AdmsApagarArq)
    |   |-- LoggerService::info('PROCESS_FILE_DELETED')
    |
    v
JSON response → JS remove linha do arquivo da lista
```

---

## 7. Dashboard Estatistico

### 7.1 Cards (5 colunas)

| # | Card | Cor | Icone | Metrica | Query |
|---|---|---|---|---|---|
| 1 | Ativos | Azul (`primary`) | `fa-book` | Total processos ativos | `COUNT WHERE adms_sit_id=1` |
| 2 | [Categoria] | Verde (`success`) | `fa-handshake` | Total da 1a categoria | `GROUP BY categoria` |
| 3 | Vencidos | Vermelho (`danger`) | `fa-exclamation-triangle` | Processos expirados | `WHERE date_validation_end < CURDATE()` |
| 4 | Vencendo | Amarelo (`warning`) | `fa-clock` | Vencendo em 30 dias | `WHERE date_validation_end BETWEEN hoje AND +30d` |
| 5 | Arquivos | Ciano (`info`) | `fa-file-alt` | Total arquivos vinculados | `COUNT adms_process_library_files` |

**Grid responsivo:** `col-6 col-sm-4 col-md-6 col-lg` (auto-equal em desktop, 2 colunas mobile)

### 7.2 Alertas Colapsaveis

| Alerta | Condicao | Cor | Tabela de Detalhes |
|---|---|---|---|
| Processos Vencidos | `expired_count > 0` | `alert-danger` | Processo, Area*, Setor*, Vencido em, Dias |
| Processos Vencendo | `expiring_soon > 0` | `alert-warning` | Processo, Area*, Setor*, Vence em, Faltam |

*Colunas Area e Setor ocultas em telas < 768px (`d-none d-md-table-cell`)

Ambos os alertas sao `alert-dismissible` com botao "Detalhes" que expande/recolhe a tabela via Bootstrap `collapse`.

---

## 8. Indicadores de Vencimento na Listagem

### 8.1 Logica nos Cards

| Condicao | Borda | Badge Extra | Background | Data Fim |
|---|---|---|---|---|
| `date_validation_end < hoje` | `border-danger` | `badge-danger "Vencido"` | `rgba(220,53,69,0.05)` | Vermelho negrito |
| `date_validation_end <= hoje+30d` | `border-warning` | `badge-warning "Xd"` | `rgba(255,193,7,0.05)` | Amarelo negrito |
| Normal | Cor do status original | Apenas badge de status | Sem alteracao | Normal |

### 8.2 CSS

```css
.process-expired  { background-color: rgba(220, 53, 69, 0.05) !important; }
.process-expiring { background-color: rgba(255, 193, 7, 0.05) !important; }
```

---

## 9. Sistema de Notificacao por Email (Cron)

### 9.1 Visao Geral

O cron verifica diariamente processos ativos com datas de vencimento proximas ou ultrapassadas e envia emails para os gestores responsaveis.

### 9.2 Destinatarios

| Destinatario | Tabela Origem | Campo Email | Vinculo no Processo |
|---|---|---|---|
| Gestor de Area | `adms_managers` | `email` | `adms_manager_area_id` |
| Gestor de Setor | `adms_employees` | `email` | `adms_manager_sector_id` |

### 9.3 Tipos de Notificacao

| Tipo | Condicao | Assunto do Email | Template |
|---|---|---|---|
| `expiring_30d` | `date_validation_end BETWEEN hoje AND +30d` | "Processo vencendo em X dias: [titulo]" | Warning (amarelo) |
| `expired` | `date_validation_end < hoje` | "VENCIDO: [titulo] (X dias)" | Error (vermelho com banner de atencao) |

### 9.4 Prevencao de Duplicatas

- Cada envio e registrado em `adms_process_library_notifications` com `(process_library_id, notification_type, recipient_type)`
- Antes de enviar, o cron verifica se ja existe registro → se sim, pula
- Ao editar um processo (renovar data), todos os registros sao deletados → permite renotificacao

### 9.5 Fluxo do Cron

```
check_process_library_cron.php
    |
    v
AdmsCheckProcessLibraryExpiration::checkAndNotify()
    |
    |-- [1/2] notifyExpiringSoon()
    |       |-- SELECT processos ativos com date_validation_end entre hoje e +30d
    |       |-- JOIN adms_managers (email gestor area)
    |       |-- JOIN adms_employees (email gestor setor)
    |       |-- Para cada processo + destinatario:
    |       |     |-- notificationAlreadySent()? → pula
    |       |     |-- sendNotificationEmail(email, subject, message, 'warning')
    |       |     |-- registerNotification() → insere rastreamento
    |
    |-- [2/2] notifyExpired()
    |       |-- SELECT processos ativos com date_validation_end < hoje
    |       |-- Mesmo fluxo, porem email tipo 'error'
    |
    |-- LoggerService::info('PROCESS_LIBRARY_EXPIRATION_CRON')
```

### 9.6 Limpeza ao Editar Processo

```
AdmsEditProcessLibrary::updateEditProcessLibrary()
    |-- UPDATE adms_process_librarys
    |-- DELETE FROM adms_process_library_notifications WHERE process_library_id = X
    |-- Proxima execucao do cron pode reenviar se necessario
```

---

## 10. JavaScript (process-library.js)

### 10.1 Estrutura

```javascript
const ProcessLibrary = {
    // Configuracao
    containerId: 'content_process_library',
    urlBase: '',           // URLADM do PHP
    page: '',              // 'add', 'edit' ou '' (listagem)

    // Instancias CKEditor
    addContentEditor: null,
    editContentEditor: null,

    // Estado
    searchDebounceTimer: null,
    pendingDeleteFile: null,
    pendingExtractedContent: null,
};
```

### 10.2 Funcoes Principais

| Funcao | Descricao |
|---|---|
| `init()` | Entry point: detecta pagina e inicializa |
| `loadList(page)` | AJAX GET listagem paginada (request_type=1) |
| `search(page)` | AJAX POST busca com filtros (request_type=2) |
| `viewProcess(id)` | AJAX GET conteudo para modal de visualizacao |
| `editProcess(id)` | AJAX GET formulario para modal de edicao + init CKEditor |
| `openDeleteModal(data)` | Popula e abre modal de exclusao |
| `initEditor(elementId, type)` | Cria instancia CKEditor (async) |
| `extractText(fileInput, mode)` | Extracao de texto via AJAX |
| `refreshList()` | Recarrega listagem (respeita filtros ativos) |
| `generateNotification(type, msg)` | Cria HTML de notificacao Bootstrap |
| `bindModalCleanup()` | Reset forms e destroy editors ao fechar modais |
| `clearFilters()` | Limpa todos os filtros e recarrega (global) |

### 10.3 CKEditor

- **Versao:** CKEditor 5 (ClassicEditor)
- **Idioma:** pt-br
- **Toolbar:** heading, bold, italic, underline, bulletedList, numberedList, insertTable, link, undo, redo
- **Integracao:** Conteudo sincronizado com campo hidden no submit do formulario

### 10.4 Responsividade

| Componente | Mobile (<768px) | Desktop (>=768px) |
|---|---|---|
| Cards de processo | 1 coluna | 2-3 colunas |
| Botoes de acao | Dropdown com ellipsis | Button group inline |
| Titulo da pagina | `<h4>` | `<h2 class="display-4">` |
| Colunas das tabelas de alerta | Processo, Data, Dias | + Area, Setor |

---

## 11. Services e Helpers Utilizados

### 11.1 Services

| Service | Uso no Modulo |
|---|---|
| `LoggerService` | Auditoria de CRUD + execucao do cron |
| `NotificationService` | Notificacoes visuais (staticSuccess/Error) + envio de emails |
| `TextExtractionService` | Extracao de texto de PDF, DOCX, TXT, MD |

### 11.2 Database Helpers

| Helper | Uso |
|---|---|
| `AdmsRead` | Todas as queries SELECT |
| `AdmsCreate` | INSERT de processos, arquivos, niveis de acesso, notificacoes |
| `AdmsUpdate` | UPDATE do processo principal |
| `AdmsDelete` | DELETE de arquivos, niveis de acesso, notificacoes |
| `AdmsPaginacao` | Paginacao da listagem (100 itens/pagina) |

### 11.3 Helpers de Arquivo

| Helper | Uso |
|---|---|
| `AdmsUpload` | Upload de arquivo unico |
| `AdmsUploadMultFiles` | Upload de multiplos arquivos |
| `AdmsSlug` | Slugificacao de nomes de arquivo |
| `AdmsApagarArq` | Exclusao de arquivo fisico |

### 11.4 Outros Helpers

| Helper | Uso |
|---|---|
| `AdmsBotao` | Validacao de permissoes de botoes |
| `AdmsCampoVazioComTag` | Validacao de campos obrigatorios (preserva HTML) |

---

## 12. Seguranca

| Aspecto | Implementacao |
|---|---|
| SQL Injection | PDO prepared statements via AdmsRead/AdmsCreate/etc. |
| XSS | `htmlspecialchars(ENT_QUOTES, UTF-8)` em todas as saidas |
| CSRF | Token `_csrf_token` em todos os formularios, validado pelo ConfigController |
| Controle de Acesso | `userCanAccess()` verifica nivel do usuario via junction table |
| Sanitizacao HTML | `strip_tags()` com whitelist (ALLOWED_TAGS) para conteudo CKEditor |
| Upload de Arquivos | Validacao de tipo MIME + tamanho maximo (10MB) |
| Input Validation | `filter_input()` / `filter_input_array()` em todos os controllers |

---

## 13. Auditoria (LoggerService)

| Evento | Action | Dados Registrados |
|---|---|---|
| Processo criado | `PROCESS_LIBRARY_CREATED` | process_id, title |
| Processo atualizado | `PROCESS_LIBRARY_UPDATED` | process_id, title |
| Processo excluido | `PROCESS_LIBRARY_DELETED` | process_id, title, dados completos |
| Arquivo excluido | `PROCESS_FILE_DELETED` | process_id, filename |
| Cron executado | `PROCESS_LIBRARY_EXPIRATION_CRON` | notifications (contagem de emails) |

---

## 14. Deploy em Producao

### 14.1 Pre-requisitos SQL

Executar na ordem:

**1. Migration de estrutura (se primeiro deploy):**
```sql
-- Coluna content
ALTER TABLE adms_process_librarys ADD COLUMN content LONGTEXT NULL AFTER title;

-- Rota de extracao de texto
INSERT INTO adms_paginas (controller, metodo, menu_controller, menu_metodo, nome_pagina, obs, lib_pub, adms_grps_pg_id, adms_tps_pg_id, adms_sits_pg_id, created)
VALUES ('ExtractProcessText', 'extract', 'extract-process-text', 'extract', 'Extração de Texto', 'API extração de texto de arquivo para ProcessLibrary', 2, 1, 1, 1, NOW());

-- Permissoes da rota de extracao (ajustar IDs conforme necessidade)
INSERT INTO adms_nivacs_pgs (adms_niveis_acesso_id, adms_pagina_id)
SELECT 1, id FROM adms_paginas WHERE menu_controller = 'extract-process-text' AND menu_metodo = 'extract';

INSERT INTO adms_nivacs_pgs (adms_niveis_acesso_id, adms_pagina_id)
SELECT 2, id FROM adms_paginas WHERE menu_controller = 'extract-process-text' AND menu_metodo = 'extract';

-- Modernizacao dos metodos das rotas
UPDATE adms_paginas SET metodo = 'create', menu_metodo = 'create' WHERE menu_controller = 'add-process-library';
UPDATE adms_paginas SET metodo = 'edit', menu_metodo = 'edit' WHERE menu_controller = 'edit-process-library';
UPDATE adms_paginas SET metodo = 'view', menu_metodo = 'view' WHERE menu_controller = 'view-process-library';
UPDATE adms_paginas SET metodo = 'delete', menu_metodo = 'delete' WHERE menu_controller = 'delete-process-library';
```
**Arquivo:** `docs/sql/migration_process_library_2026_02_18.sql`

**2. Tabela M:N de niveis de acesso:**
```sql
CREATE TABLE IF NOT EXISTS adms_process_library_access_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adms_process_library_id INT NOT NULL,
    adms_niveis_acesso_id INT NOT NULL,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_process_level (adms_process_library_id, adms_niveis_acesso_id),
    INDEX idx_process_id (adms_process_library_id),
    INDEX idx_level_id (adms_niveis_acesso_id),
    CONSTRAINT fk_pla_process FOREIGN KEY (adms_process_library_id)
        REFERENCES adms_process_librarys(id) ON DELETE CASCADE,
    CONSTRAINT fk_pla_level FOREIGN KEY (adms_niveis_acesso_id)
        REFERENCES adms_niveis_acessos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: todos os processos existentes com todos os niveis (backward compatible)
INSERT INTO adms_process_library_access_levels (adms_process_library_id, adms_niveis_acesso_id, created)
SELECT p.id, n.id, NOW()
FROM adms_process_librarys p
CROSS JOIN adms_niveis_acessos n;
```
**Arquivo:** `docs/migrations/2026_02_18_process_library_access_levels.sql`

**3. Tabela de notificacoes (cron de vencimento):**
```sql
CREATE TABLE `adms_process_library_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `process_library_id` INT NOT NULL,
    `notification_type` VARCHAR(30) NOT NULL,
    `recipient_type` VARCHAR(30) NOT NULL,
    `recipient_email` VARCHAR(255) DEFAULT NULL,
    `sent_at` DATETIME NOT NULL,
    INDEX idx_process_notif (process_library_id, notification_type, recipient_type),
    CONSTRAINT fk_pln_process FOREIGN KEY (process_library_id)
        REFERENCES adms_process_librarys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 14.2 Configuracao do Cron Job

```bash
# Verificacao diaria de processos vencidos - 07:00 (America/Sao_Paulo)
0 7 * * * cd /caminho/para/mercury && /usr/bin/php check_process_library_cron.php >> /var/log/mercury/process_library_cron.log 2>&1
```

### 14.3 Teste Manual do Cron

```bash
cd /caminho/para/mercury
php check_process_library_cron.php
```

### 14.4 Checklist de Deploy

**Pre-deploy:**
- [ ] Executar SQL 1 (migration de estrutura) se primeiro deploy
- [ ] Executar SQL 2 (tabela M:N de niveis de acesso) se primeiro deploy
- [ ] Executar SQL 3 (tabela de notificacoes)
- [ ] Verificar credenciais SMTP configuradas no banco
- [ ] Verificar que `adms_managers` tem coluna `email` preenchida

**Deploy:**
- [ ] Deploy dos arquivos (6 controllers, 7 models, 12 views, 1 JS, 1 CSS, 1 cron)
- [ ] Verificar permissoes do arquivo `check_process_library_cron.php`
- [ ] Verificar diretorio `assets/files/processLibrary/` existe com permissao de escrita

**Pos-deploy:**
- [ ] Acessar `/process-library/list` e verificar dashboard com 5 cards
- [ ] Verificar indicadores de vencimento na listagem
- [ ] Testar CRUD completo (cadastrar, visualizar, editar, excluir)
- [ ] Testar upload e extracao de texto de arquivo
- [ ] Executar `php check_process_library_cron.php` e verificar emails
- [ ] Configurar cron job no servidor (`crontab -e`)

**Monitoramento:**
- [ ] Verificar logs em `adms_logs` com actions `PROCESS_LIBRARY_*`
- [ ] Consultar `adms_process_library_notifications` para historico de envios

---

## 15. Consultas SQL Uteis

### Ver todos os processos vencidos
```sql
SELECT p.id, p.title, a.name AS area, s.sector_name AS setor,
       p.date_validation_end, DATEDIFF(CURDATE(), p.date_validation_end) AS dias_vencido
FROM adms_process_librarys p
LEFT JOIN adms_areas a ON a.id = p.adms_area_id
LEFT JOIN adms_sectors s ON s.id = p.adms_sector_id
WHERE p.adms_sit_id = 1 AND p.date_validation_end < CURDATE()
ORDER BY p.date_validation_end ASC;
```

### Ver notificacoes enviadas
```sql
SELECT pln.*, pl.title
FROM adms_process_library_notifications pln
JOIN adms_process_librarys pl ON pl.id = pln.process_library_id
ORDER BY pln.sent_at DESC;
```

### Ver processos sem notificacao de vencimento
```sql
SELECT p.id, p.title, p.date_validation_end,
       DATEDIFF(CURDATE(), p.date_validation_end) AS dias_vencido
FROM adms_process_librarys p
LEFT JOIN adms_process_library_notifications n
    ON n.process_library_id = p.id AND n.notification_type = 'expired'
WHERE p.adms_sit_id = 1 AND p.date_validation_end < CURDATE() AND n.id IS NULL;
```

### Limpar notificacoes (forcar reenvio)
```sql
DELETE FROM adms_process_library_notifications WHERE process_library_id = ?;
```

### Ver niveis de acesso de um processo
```sql
SELECT na.nome, na.ordem
FROM adms_process_library_access_levels pla
JOIN adms_niveis_acessos na ON na.id = pla.adms_niveis_acesso_id
WHERE pla.adms_process_library_id = ?
ORDER BY na.ordem;
```

---

## 16. Troubleshooting

| Problema | Causa Provavel | Solucao |
|---|---|---|
| Pagina 404 ao acessar | Rota nao registrada em `adms_paginas` | Executar SQL de migracao |
| Modal nao abre | JS nao carregado ou erro de sintaxe | Verificar console do navegador |
| CKEditor nao aparece | CDN indisponivel ou conflito de versao | Verificar rede e console |
| Arquivo nao faz upload | Diretorio sem permissao de escrita | `chmod 775 assets/files/processLibrary/` |
| Extracao de texto falha | Extensao nao suportada ou arquivo > 10MB | Verificar formato e tamanho |
| Cron nao envia emails | SMTP mal configurado ou email vazio | Verificar credenciais no DB e campo email dos gestores |
| Cron reenvia emails | Tabela de notificacoes nao criada | Executar SQL de criacao da tabela |
| Processos nao aparecem | Nivel de acesso nao associado | Verificar `adms_process_library_access_levels` |
| Filtros nao funcionam | JavaScript desabilitado | Verificar console e JS carregado |
| Cards sem indicador de vencimento | `date_validation_end` nulo | Preencher data de vencimento no processo |

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
**Ultima Atualizacao:** 18/02/2026
