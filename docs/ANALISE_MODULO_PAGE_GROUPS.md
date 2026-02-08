# Análise Técnica - Módulo de Grupos de Páginas (Page Groups)

**Data:** 30 de Janeiro de 2026
**Versão:** 2.1
**Autor:** Equipe Mercury - Grupo Meia Sola
**Status:** CONCLUÍDO

---

## Status da Refatoração

| Fase | Status | Data de Conclusão |
|------|--------|-------------------|
| Fase 1 - Preparação | Concluído | 29/01/2026 |
| Fase 2 - Renomeação | Concluído | 29/01/2026 |
| Fase 3 - Controllers | Concluído | 29/01/2026 |
| Fase 4 - Models | Concluído | 29/01/2026 |
| Fase 5 - Views | Concluído | 29/01/2026 |
| Fase 6 - JavaScript | Concluído | 29/01/2026 |
| Fase 7 - Banco de Dados | Concluído | 29/01/2026 |
| Fase 8 - Testes | Concluído | 29/01/2026 |
| Fase 9 - Limpeza | Concluído | 29/01/2026 |
| Fase 10 - Melhorias UX | Concluído | 30/01/2026 |
| Fase 11 - Testes Automatizados | Concluído | 30/01/2026 |

### Arquivos Criados
- **Controllers:** PageGroups.php, AddPageGroup.php, EditPageGroup.php, ViewPageGroup.php, DeletePageGroup.php, ReorderPageGroup.php
- **Models:** AdmsListPageGroups.php, AdmsAddPageGroup.php, AdmsEditPageGroup.php, AdmsViewPageGroup.php, AdmsDeletePageGroup.php, AdmsReorderPageGroup.php, AdmsStatisticsPageGroups.php
- **Views:** loadPageGroups.php, listPageGroups.php, partials/_add_page_group_modal.php, partials/_edit_page_group_modal.php, partials/_view_page_group_modal.php, partials/_delete_page_group_modal.php
- **JavaScript:** page-groups.js
- **SQL:** page_groups_routes_migration.sql
- **Testes:** AdmsListPageGroupsTest.php, AdmsStatisticsPageGroupsTest.php, AdmsAddPageGroupTest.php, AdmsReorderPageGroupTest.php, PageGroupsControllerTest.php

### Melhorias UX Implementadas (30/01/2026)

#### Drag & Drop para Reordenação
- Implementação usando biblioteca **SortableJS**
- Permite arrastar e soltar grupos para reordenar
- Atualização em lote via endpoint `reorder-page-group/save-order`
- Feedback visual com animação de destaque

#### Reordenação por Botões
- Botões de seta para cima/baixo em cada linha
- Atualização otimista (UI atualiza antes da resposta do servidor)
- Endpoints: `reorder-page-group/move-up` e `reorder-page-group/move-down`

#### Preview de Páginas no Hover
- Popover Bootstrap ao passar o mouse sobre contador de páginas
- Carregamento AJAX das páginas do grupo
- Exibe até 10 páginas com nome e controller
- Endpoint: `page-groups/pages-preview/{id}`

#### Padrão de Notificações Server-Side
- Notificações geradas pelo servidor via `NotificationService`
- JSON responses incluem campo `notification_html`
- Container `#messages` na view para injeção de notificações
- Auto-hide após 5 segundos
- Sem criação de notificações via JavaScript

### Arquivos Removidos
- **Controllers:** GrupoPg.php, CadastrarGrupoPg.php, EditarGrupoPg.php, VerGrupoPg.php, ApagarGrupoPg.php, AltOrdemGrupoPg.php
- **Models:** AdmsListarGrupoPg.php, AdmsCadastrarGrupoPg.php, AdmsEditarGrupoPg.php, AdmsVerGrupoPg.php, AdmsApagarGrupoPg.php, AdmsAltOrdemGrupoPg.php
- **Views:** grupoPg/ (diretório completo)

---

## 1. Visão Geral

### 1.1. Objetivo do Módulo
O módulo de Grupos de Páginas gerencia a organização hierárquica das páginas do sistema, permitindo agrupar páginas relacionadas para melhor organização do menu e controle de acesso.

### 1.2. Funcionalidades Atuais
- Listar grupos de páginas com paginação
- Cadastrar novo grupo
- Editar grupo existente
- Visualizar detalhes do grupo
- Excluir grupo (com validação de dependências)
- Alterar ordem dos grupos

### 1.3. Tabela do Banco de Dados
- **Tabela Principal:** `adms_grps_pgs`
- **Colunas:** `id`, `nome`, `ordem`, `created`, `modified`
- **Tabela Relacionada:** `adms_paginas` (FK: `adms_grps_pg_id`)

---

## 2. Estado Atual dos Arquivos

### 2.1. Controllers (6 arquivos)

| Arquivo Atual | Localização |
|---------------|-------------|
| `GrupoPg.php` | `app/adms/Controllers/` |
| `CadastrarGrupoPg.php` | `app/adms/Controllers/` |
| `EditarGrupoPg.php` | `app/adms/Controllers/` |
| `VerGrupoPg.php` | `app/adms/Controllers/` |
| `ApagarGrupoPg.php` | `app/adms/Controllers/` |
| `AltOrdemGrupoPg.php` | `app/adms/Controllers/` |

### 2.2. Models (6 arquivos)

| Arquivo Atual | Localização |
|---------------|-------------|
| `AdmsListarGrupoPg.php` | `app/adms/Models/` |
| `AdmsCadastrarGrupoPg.php` | `app/adms/Models/` |
| `AdmsEditarGrupoPg.php` | `app/adms/Models/` |
| `AdmsVerGrupoPg.php` | `app/adms/Models/` |
| `AdmsApagarGrupoPg.php` | `app/adms/Models/` |
| `AdmsAltOrdemGrupoPg.php` | `app/adms/Models/` |

### 2.3. Views (4 arquivos)

| Arquivo Atual | Localização |
|---------------|-------------|
| `listarGrupoPg.php` | `app/adms/Views/grupoPg/` |
| `cadGrupoPg.php` | `app/adms/Views/grupoPg/` |
| `editarGrupoPg.php` | `app/adms/Views/grupoPg/` |
| `verGrupoPg.php` | `app/adms/Views/grupoPg/` |

### 2.4. JavaScript
- **Não existe** arquivo JavaScript dedicado ao módulo

---

## 3. Problemas Identificados

### 3.1. Nomenclatura

#### Controllers
| Problema | Atual | Padrão Esperado |
|----------|-------|-----------------|
| Classe principal | `GrupoPg` | `PageGroups` |
| Método listar | `listar()` | `list()` |
| Ação cadastrar | `CadastrarGrupoPg` | `AddPageGroup` |
| Ação editar | `EditarGrupoPg` | `EditPageGroup` |
| Ação visualizar | `VerGrupoPg` | `ViewPageGroup` |
| Ação deletar | `ApagarGrupoPg` | `DeletePageGroup` |
| Ação ordem | `AltOrdemGrupoPg` | `ReorderPageGroup` |

#### Models
| Problema | Atual | Padrão Esperado |
|----------|-------|-----------------|
| Listagem | `AdmsListarGrupoPg` | `AdmsListPageGroups` (plural) |
| Cadastro | `AdmsCadastrarGrupoPg` | `AdmsAddPageGroup` |
| Edição | `AdmsEditarGrupoPg` | `AdmsEditPageGroup` |
| Visualização | `AdmsVerGrupoPg` | `AdmsViewPageGroup` |
| Exclusão | `AdmsApagarGrupoPg` | `AdmsDeletePageGroup` |
| Ordem | `AdmsAltOrdemGrupoPg` | `AdmsReorderPageGroup` |

#### Views
| Problema | Atual | Padrão Esperado |
|----------|-------|-----------------|
| Diretório | `grupoPg/` | `pageGroups/` |
| Listagem | `listarGrupoPg.php` | `loadPageGroups.php` |
| Cadastro | `cadGrupoPg.php` | `partials/_add_page_group_modal.php` |
| Edição | `editarGrupoPg.php` | `partials/_edit_page_group_modal.php` |
| Visualização | `verGrupoPg.php` | `partials/_view_page_group_modal.php` |

### 3.2. Código

#### Problemas em Controllers
1. **Sem type hints** - Variáveis e métodos sem tipagem
2. **Sem PHPDoc completo** - Documentação incompleta
3. **Imports não declarados** - Uso de `\App\adms\Models\...` inline
4. **Variáveis em português** - `$Dados`, `$PageId`, `$botao`
5. **Métodos em português** - `listar()`, `cadGrupoPg()`
6. **Sem uso de match expression** - Switch/if tradicional
7. **Sem padrão de resposta AJAX** - Redirecionamentos diretos

#### Problemas em Models
1. **Múltiplos models para CRUD** - Deveria ser um único model
2. **Sem type hints** - Parâmetros e retornos sem tipagem
3. **Variáveis em português** - `$Resultado`, `$LimiteResultado`
4. **Sem getters/setters padronizados**
5. **Sem uso de LoggerService** - Operações não são logadas

#### Problemas em Views
1. **Sem estrutura de partials** - Views completas em vez de modais
2. **PHP misturado com HTML** - Falta de separação
3. **Sem listagem AJAX** - Carregamento de página completa
4. **Botões inline** - Código repetitivo

### 3.3. Funcionalidades Ausentes
1. **Busca/Filtro** - Não há funcionalidade de pesquisa
2. **Estatísticas** - Não há cards de estatísticas
3. **Operações AJAX** - Tudo via refresh de página
4. **Modal de confirmação** - Delete sem modal
5. **Logging** - Sem registro de operações
6. **Notificações** - Sem NotificationService

---

## 4. Plano de Refatoração

### 4.1. Fase 1 - Preparação (Pré-requisitos)

#### 4.1.1. Backup
- [ ] Fazer backup de todos os arquivos atuais
- [ ] Documentar rotas atuais no banco de dados

#### 4.1.2. Rotas no Banco de Dados
Atualizar tabela `adms_paginas` com novas rotas:

```sql
-- Rotas principais do módulo (CRUD)
-- page-groups/list              -- Listagem principal
-- page-groups/index             -- Alias para list
-- add-page-group/create         -- Adicionar grupo
-- edit-page-group/edit          -- Editar grupo
-- view-page-group/view          -- Visualizar grupo
-- delete-page-group/delete      -- Excluir grupo

-- Rotas de reordenação
-- reorder-page-group/index      -- Permissão base
-- reorder-page-group/reorder    -- Reordenar (alias)
-- reorder-page-group/move-up    -- Mover para cima (botão)
-- reorder-page-group/move-down  -- Mover para baixo (botão)
-- reorder-page-group/save-order -- Salvar ordem (drag & drop)

-- Rotas AJAX
-- page-groups/statistics        -- Estatísticas (AJAX)
-- page-groups/pages-preview     -- Preview páginas no hover (AJAX)
```

### 4.2. Fase 2 - Renomeação de Arquivos

#### 4.2.1. Controllers

| De | Para |
|----|------|
| `GrupoPg.php` | `PageGroups.php` |
| `CadastrarGrupoPg.php` | `AddPageGroup.php` |
| `EditarGrupoPg.php` | `EditPageGroup.php` |
| `VerGrupoPg.php` | `ViewPageGroup.php` |
| `ApagarGrupoPg.php` | `DeletePageGroup.php` |
| `AltOrdemGrupoPg.php` | `ReorderPageGroup.php` |

#### 4.2.2. Models

| De | Para |
|----|------|
| `AdmsListarGrupoPg.php` | `AdmsListPageGroups.php` |
| `AdmsCadastrarGrupoPg.php` | `AdmsAddPageGroup.php` |
| `AdmsEditarGrupoPg.php` | `AdmsEditPageGroup.php` |
| `AdmsVerGrupoPg.php` | `AdmsViewPageGroup.php` |
| `AdmsApagarGrupoPg.php` | (`AdmsDeletePageGroup.php` |
| `AdmsAltOrdemGrupoPg.php` | `AdmsReorderPageGroup.php` |
| (criar novo) | `AdmsStatisticsPageGroups.php` |

#### 4.2.3. Views

| De | Para |
|----|------|
| `grupoPg/` | `pageGroups/` |
| `listarGrupoPg.php` | `loadPageGroups.php` |
| (criar) | `listPageGroups.php` |
| `cadGrupoPg.php` | `partials/_add_page_group_modal.php` |
| `editarGrupoPg.php` | `partials/_edit_page_group_modal.php` |
| `verGrupoPg.php` | `partials/_view_page_group_modal.php` |
| (criar) | `partials/_delete_page_group_modal.php` |

#### 4.2.4. JavaScript
| Criar |
|-------|
| `assets/js/page-groups.js` |

### 4.3. Fase 3 - Refatoração de Controllers

#### 4.3.1. PageGroups.php (Controller Principal)
```php
<?php
namespace App\adms\Controllers;

use App\adms\Models\AdmsMenu;
use App\adms\Models\AdmsBotao;
use App\adms\Models\AdmsListPageGroups;
use App\adms\Models\AdmsStatisticsPageGroups;
use Core\ConfigView;

class PageGroups
{
    private ?array $data = [];
    private int $pageId;

    public function list(int|string|null $pageId = null): void
    {
        $this->pageId = (int) ($pageId ?: 1);

        $this->loadButtons();
        $this->loadStats();

        $requestType = filter_input(INPUT_GET, 'typepg', FILTER_VALIDATE_INT);

        match ($requestType) {
            1 => $this->listAllGroups(),
            2 => $this->searchGroups(),
            default => $this->loadInitialPage(),
        };
    }

    public function index(): void
    {
        $this->list();
    }

    private function loadButtons(): void { /* ... */ }
    private function loadStats(): void { /* ... */ }
    private function loadInitialPage(): void { /* ... */ }
    private function listAllGroups(): void { /* ... */ }
    private function searchGroups(): void { /* ... */ }
}
```

### 4.4. Fase 4 - Refatoração de Models

#### 4.4.1. AdmsAddPageGroup.php
```php
<?php
namespace App\adms\Models;

use App\adms\Models\helper\AdmsCreate;
use App\adms\Models\helper\FormSelectRepository;
use App\adms\Services\LoggerService;

class AdmsAddPageGroup
{
    private bool $result = false;
    private ?string $error = null;
    private ?array $data = null;

    public function getResult(): bool { return $this->result; }
    public function getError(): ?string { return $this->error; }
    public function getData(): ?array { return $this->data; }

    public function create(array $data): bool { /* ... */ }
    public function update(array $data): bool { /* ... */ }
    public function delete(int $id): bool { /* ... */ }
}
```

#### 4.4.2. AdmsEditPageGroup.php
```php
<?php
namespace App\adms\Models;

use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsUpdate;
use App\adms\Models\helper\FormSelectRepository;
use App\adms\Services\LoggerService;

class AdmsEditPageGroup
{
    private bool $result = false;
    private ?string $error = null;
    private ?array $data = null;

    public function getResult(): bool { return $this->result; }
    public function getError(): ?string { return $this->error; }
    public function getData(): ?array { return $this->data; }

    public function view(int $id): bool { /* ... */ }
    public function update(array $data): bool { /* ... */ }
}
```

#### 4.4.3. AdmsDeletePageGroup.php
```php
<?php
namespace App\adms\Models;

use App\adms\Models\helper\AdmsDelete;
use App\adms\Services\LoggerService;

class AdmsDeletePageGroup
{
    private bool $result = false;
    private ?string $error = null;
    private ?array $data = null;

    public function getResult(): bool { return $this->result; }
    public function getError(): ?string { return $this->error; }
    public function getData(): ?array { return $this->data; }

    public function delete(array $data): bool { /* ... */ }
}
```

### 4.5. Fase 5 - Refatoração de Views

#### 4.5.1. Estrutura Final
```
app/adms/Views/pageGroups/
├── loadPageGroups.php          # Página principal
├── listPageGroups.php          # Lista AJAX
└── partials/
    ├── _add_page_group_modal.php
    ├── _edit_page_group_modal.php
    ├── _view_page_group_modal.php
    └── _delete_page_group_modal.php
```

### 4.6. Fase 6 - JavaScript

#### 4.6.1. page-groups.js
```javascript
/**
 * Page Groups Module
 * @author Grupo Meia Sola
 */
(function() {
    'use strict';

    const CONFIG = {
        container: 'content_page_groups',
        addModal: 'addPageGroupModal',
        editModal: 'editPageGroupModal',
        viewModal: 'viewPageGroupModal',
        deleteModal: 'deletePageGroupModal'
    };

    // Inicialização, handlers AJAX, etc.
})();
```

### 4.7. Fase 7 - Banco de Dados

#### 4.7.1. Atualizar Rotas
```sql
-- Atualizar rotas antigas para novas
UPDATE adms_paginas SET
    nome_pagina = 'page-groups',
    menu_controller = 'page-groups',
    menu_metodo = 'list'
WHERE nome_pagina = 'grupo-pg';

-- Adicionar novas permissões se necessário
```

---

## 5. Mapeamento Completo de Renomeação

### 5.1. Classes

| Tipo | Atual | Novo |
|------|-------|------|
| Controller | `GrupoPg` | `PageGroups` |
| Controller | `CadastrarGrupoPg` | `AddPageGroup` |
| Controller | `EditarGrupoPg` | `EditPageGroup` |
| Controller | `VerGrupoPg` | `ViewPageGroup` |
| Controller | `ApagarGrupoPg` | `DeletePageGroup` |
| Controller | `AltOrdemGrupoPg` | `ReorderPageGroup` |
| Model | `AdmsListarGrupoPg` | `AdmsListPageGroups` |
| Model | `AdmsCadastrarGrupoPg` | `AdmsAddPageGroup` |
| Model | `AdmsEditarGrupoPg` | `AdmsEditPageGroup` |
| Model | `AdmsVerGrupoPg` | `AdmsViewPageGroup` |
| Model | `AdmsApagarGrupoPg` | `AdmsDeletePageGroup` |
| Model | `AdmsAltOrdemGrupoPg` | `AdmsReorderPageGroup` |

### 5.2. Métodos

| Classe | Atual | Novo |
|--------|-------|------|
| PageGroups | `listar()` | `list()` |
| AddPageGroup | `cadGrupoPg()` | `create()` |
| EditPageGroup | `editGrupoPg()` | `edit()` |
| ViewPageGroup | `verGrupoPg()` | `view()` |
| DeletePageGroup | `apagarGrupoPg()` | `delete()` |
| ReorderPageGroup | `altOrdemGrupoPg()` | `reorder()` |
| AdmsListPageGroups | `listarGrupoPg()` | `listAll()` |
| AdmsPageGroup | `cadGrupoPg()` | `create()` |
| AdmsPageGroup | `altGrupoPg()` | `update()` |
| AdmsPageGroup | N/A | `delete()` |

### 5.3. Variáveis

| Atual | Novo |
|-------|------|
| `$Dados` | `$data` |
| `$PageId` | `$pageId` |
| `$Resultado` | `$result` |
| `$ResultadoPg` | `$pagination` |
| `$LimiteResultado` | `$limit` |
| `$botao` | `$buttons` |
| `$listGrupoPg` | `$pageGroups` |

### 5.4. Rotas (URLs)

#### Rotas Principais (migração do módulo antigo)

| Rota Antiga | Rota Nova |
|-------------|-----------|
| `/grupo-pg/listar` | `/page-groups/list` |
| `/cadastrar-grupo-pg/cad-grupo-pg` | `/add-page-group/create` |
| `/editar-grupo-pg/edit-grupo-pg` | `/edit-page-group/edit` |
| `/ver-grupo-pg/ver-grupo-pg` | `/view-page-group/view` |
| `/apagar-grupo-pg/apagar-grupo-pg` | `/delete-page-group/delete` |
| `/alt-ordem-grupo-pg/alt-ordem-grupo-pg` | `/reorder-page-group/reorder` |

#### Rotas Completas do Módulo

| Controller | Método | Rota | Descrição |
|------------|--------|------|-----------|
| `PageGroups` | `list` | `/page-groups/list/{page}` | Listagem com paginação |
| `PageGroups` | `index` | `/page-groups/index` | Alias para list |
| `PageGroups` | `statistics` | `/page-groups/statistics` | Estatísticas (AJAX) |
| `PageGroups` | `pagesPreview` | `/page-groups/pages-preview/{id}` | Preview páginas (AJAX) |
| `AddPageGroup` | `create` | `/add-page-group/create` | Adicionar grupo |
| `EditPageGroup` | `edit` | `/edit-page-group/edit/{id}` | Editar grupo |
| `ViewPageGroup` | `view` | `/view-page-group/view/{id}` | Visualizar grupo |
| `DeletePageGroup` | `delete` | `/delete-page-group/delete/{id}` | Excluir grupo |
| `ReorderPageGroup` | `index` | `/reorder-page-group/index` | Permissão base |
| `ReorderPageGroup` | `reorder` | `/reorder-page-group/reorder/{id}` | Alias para moveUp |
| `ReorderPageGroup` | `moveUp` | `/reorder-page-group/move-up/{id}` | Mover para cima |
| `ReorderPageGroup` | `moveDown` | `/reorder-page-group/move-down/{id}` | Mover para baixo |
| `ReorderPageGroup` | `saveOrder` | `/reorder-page-group/save-order` | Salvar ordem (drag&drop) |

---

## 6. Funcionalidades Implementadas

### 6.1. Funcionalidades Base (29/01/2026)

1. **Busca em Tempo Real** ✅
   - Campo de busca na listagem
   - Filtro por nome do grupo
   - Filtro por status (com/sem páginas)

2. **Cards de Estatísticas** ✅
   - Total de grupos
   - Total de páginas
   - Grupos sem páginas
   - Média de páginas por grupo
   - Grupo com mais páginas

3. **Modal de Confirmação de Exclusão** ✅
   - Mostrar páginas vinculadas
   - Contador de dependências

4. **Logging Completo** ✅
   - Log de criação, edição, exclusão
   - Log de alteração de ordem

### 6.2. Melhorias de UX (30/01/2026)

1. **Drag & Drop para Reordenação** ✅
   - Biblioteca SortableJS
   - Feedback visual durante arrasto
   - Atualização em lote

2. **Reordenação por Botões** ✅
   - Setas para cima/baixo
   - Atualização otimista da UI
   - Animação de destaque

3. **Preview de Páginas no Hover** ✅
   - Popover Bootstrap
   - Carregamento AJAX
   - Exibe até 10 páginas

4. **Notificações Server-Side** ✅
   - NotificationService gera HTML
   - Container #messages na view
   - Auto-hide após 5 segundos

### 6.3. Sugestões Futuras

1. **Operações em Lote**
   - Excluir múltiplos grupos
   - Selecionar vários para ação

2. **Cache de Selects**
   - Usar SelectCacheService
   - Invalidar ao modificar

3. **Validações Avançadas**
   - Nome único (já implementado)
   - Limite de caracteres
   - Caracteres permitidos

---

## 7. Checklist de Implementação

### Fase 1 - Preparação
- [x] Backup dos arquivos atuais
- [x] Documentar estado atual das rotas

### Fase 2 - Renomeação
- [x] Renomear controllers
- [x] Renomear models
- [x] Renomear diretório de views
- [x] Renomear arquivos de views
- [x] Criar estrutura de partials

### Fase 3 - Controllers
- [x] Refatorar PageGroups.php
- [x] Refatorar AddPageGroup.php
- [x] Refatorar EditPageGroup.php
- [x] Refatorar ViewPageGroup.php
- [x] Refatorar DeletePageGroup.php
- [x] Refatorar ReorderPageGroup.php

### Fase 4 - Models
- [x] Criar AdmsListPageGroups.php
- [x] Criar AdmsAddPageGroup.php
- [x] Criar AdmsEditPageGroup.php
- [x] Criar AdmsViewPageGroup.php
- [x] Criar AdmsDeletePageGroup.php
- [x] Criar AdmsStatisticsPageGroups.php
- [x] Criar AdmsReorderPageGroup.php

### Fase 5 - Views
- [x] Criar loadPageGroups.php
- [x] Criar listPageGroups.php
- [x] Criar _add_page_group_modal.php
- [x] Criar _edit_page_group_modal.php
- [x] Criar _view_page_group_modal.php
- [x] Criar _delete_page_group_modal.php

### Fase 6 - JavaScript
- [x] Criar page-groups.js
- [x] Implementar CRUD AJAX
- [x] Implementar busca
- [x] Implementar reordenação

### Fase 7 - Banco de Dados
- [x] Criar script SQL de migração (docs/sql/page_groups_routes_migration.sql)
- [x] Executar atualização das rotas em adms_paginas
- [x] Executar atualização das permissões em adms_nivacs_pgs
- [x] Executar atualização dos menus se necessário

### Fase 8 - Testes
- [x] Testar listagem
- [x] Testar criação
- [x] Testar edição
- [x] Testar visualização
- [x] Testar exclusão
- [x] Testar reordenação
- [x] Testar permissões
- [x] Testar responsividade

### Fase 9 - Limpeza
- [x] Remover arquivos antigos
- [x] Remover rotas antigas do banco
- [x] Atualizar documentação

### Fase 10 - Melhorias UX (30/01/2026)
- [x] Implementar Drag & Drop com SortableJS
- [x] Implementar reordenação por botões (setas)
- [x] Implementar preview de páginas no hover
- [x] Implementar padrão de notificações server-side
- [x] Atualizar documentação com novas funcionalidades

### Fase 11 - Testes Automatizados (30/01/2026)
- [x] Criar AdmsListPageGroupsTest.php (13 testes)
- [x] Criar AdmsStatisticsPageGroupsTest.php (17 testes)
- [x] Criar AdmsAddPageGroupTest.php (10 testes)
- [x] Criar AdmsReorderPageGroupTest.php (15 testes)
- [x] Criar PageGroupsControllerTest.php (34 testes)
- [x] Total: 89 testes, 188 assertions

---

## 8. Estimativa de Arquivos

### 8.1. Arquivos a Criar
- 6 Controllers (refatorados)
- 5 Models (consolidados)
- 6 Views (nova estrutura)
- 1 JavaScript

**Total: 18 arquivos novos**

### 8.2. Arquivos a Remover
- 6 Controllers antigos
- 6 Models antigos
- 4 Views antigas

**Total: 16 arquivos a remover**

---

## 9. Referência

### Módulo de Referência: Sales
O módulo Sales (`app/adms/Controllers/Sales.php`) deve ser usado como referência para:
- Estrutura de controller com match expression
- Padrão de models
- Estrutura de views com partials
- JavaScript async/await

### Documentação
- `REGRAS_DESENVOLVIMENTO.md` - Padrões de nomenclatura
- `PADRONIZACAO.md` - Templates de código
- `ANALISE_MODULO_SALES.md` - Implementação de referência

---

**Documento atualizado em 30/01/2026**
**Módulo de Grupos de Páginas - Refatoração CONCLUÍDA**
