# Análise Completa - Módulo SituacaoUser (Situação de Usuário)

**Data:** 2026-03-04
**Autor:** Claude Code
**Status Atual:** Semi-modernizado (AbstractConfigController legacy)
**Nota Geral:** 5/10

---

## 1. Visão Geral do Módulo

O módulo SituacaoUser gerencia as situações (status) dos usuários do sistema: Ativo, Inativo, Aguardando Confirmação, Spam. Cada situação possui uma cor associada (via `adms_cors`).

### Tabela: `adms_sits_usuarios`

| Coluna | Tipo | Nulo | Extra |
|--------|------|------|-------|
| id | int | NO | PK auto_increment |
| nome | varchar(220) | NO | |
| adms_cor_id | int | NO | FK → adms_cors |
| created | datetime | NO | |
| modified | datetime | YES | |

### Dados Atuais (4 registros)

| ID | Nome | Cor |
|----|------|-----|
| 1 | Ativo | success (Verde) |
| 2 | Inativo | warning (Laranja) |
| 3 | Aguardando confirmação | primary (Azul) |
| 4 | Spam | danger (Vermelho) |

### Tabela Relacionada: `adms_cors`

| ID | Nome | Classe Bootstrap |
|----|------|-----------------|
| 1 | Azul | primary |
| 2 | Vermelho | danger |
| 3 | Laranja | warning |
| 4 | Preto | dark |
| 5 | Branco | light |
| 6 | Cinza | secundary |
| 7 | Verde | success |
| 8 | Azul Claro | info |

---

## 2. Arquivos Atuais (9 arquivos)

### Controllers (5)

| # | Arquivo | Padrão | Problema |
|---|---------|--------|----------|
| 1 | `Controllers/SituacaoUser.php` | AbstractConfigController | Método `listar()` em português, usa `executeList()` (page-reload) |
| 2 | `Controllers/CadastrarSitUser.php` | AbstractConfigController | Método `cadSitUser()` em português, usa `executeCreate()` (page-reload) |
| 3 | `Controllers/EditarSitUser.php` | AbstractConfigController | Método `editSitUser()` em português, usa `executeEdit()` (page-reload) |
| 4 | `Controllers/ApagarSitUser.php` | AbstractConfigController | Método `apagarSitUser()` em português, usa `executeDelete()` (page-reload) |
| 5 | `Controllers/VerSitUser.php` | AbstractConfigController | Método `verSitUser()` em português, usa `executeView()` (page-reload) |

### Views (4)

| # | Arquivo | Padrão | Problema |
|---|---------|--------|----------|
| 1 | `Views/situacaoUser/listarSitUser.php` | Full-page list | Sem AJAX, sem filtros, sem paginação visual adequada |
| 2 | `Views/situacaoUser/cadSitUser.php` | Full-page form | Sem modal, sem XSS escape nos values, `var_dump` comentado |
| 3 | `Views/situacaoUser/editarSitUser.php` | Full-page form | Sem modal, sem XSS escape nos values, `var_dump` comentado |
| 4 | `Views/situacaoUser/verSitUser.php` | Full-page view | Verifica `URL` em vez de `URLADM`, sem cards organizados |

### JavaScript

**Nenhum arquivo JS dedicado** — usa submissão de formulário tradicional (full page reload).

---

## 3. Rotas no Banco de Dados

| ID | Controller | Método | menu_controller | menu_metodo |
|----|-----------|--------|-----------------|-------------|
| 65 | SituacaoUser | listar | situacao-user | listar |
| 66 | VerSitUser | verSitUser | ver-sit-user | ver-sit-user |
| 67 | CadastrarSitUser | cadSitUser | cadastrar-sit-user | cad-sit-user |
| 68 | EditarSitUser | editSitUser | editar-sit-user | edit-sit-user |
| 69 | ApagarSitUser | apagarSitUser | apagar-sit-user | apagar-sit-user |

**Problemas nas rotas:**
- Métodos em português (`listar`, `cadSitUser`, `editSitUser`, etc.)
- Não seguem padrão moderno (`list`, `create`, `edit`, `view`, `delete`)

---

## 4. Comparação com Padrão do Projeto

### 4.1 Controllers — Nomenclatura

| Aspecto | Padrão Moderno (HdCategories) | Atual (SituacaoUser) | Status |
|---------|-------------------------------|---------------------|--------|
| Controller nome | PascalCase inglês (`HdCategories`) | PascalCase misto (`SituacaoUser`) | Manter (já registrado no banco) |
| Add controller | `AddHdCategory` | `CadastrarSitUser` | Renomear → `AddSituacaoUser` |
| Edit controller | `EditHdCategory` | `EditarSitUser` | Renomear → `EditSituacaoUser` |
| Delete controller | `DeleteHdCategory` | `ApagarSitUser` | Renomear → `DeleteSituacaoUser` |
| View controller | `ViewHdCategory` | `VerSitUser` | Renomear → `ViewSituacaoUser` |
| List method | `list()` | `listar()` | Renomear → `list()` |
| Create method | `create()` | `cadSitUser()` | Renomear → `create()` |
| Edit method | `edit()` | `editSitUser()` | Renomear → `edit()` |
| Delete method | `delete()` | `apagarSitUser()` | Renomear → `delete()` |
| View method | `view()` | `verSitUser()` | Renomear → `view()` |

### 4.2 Controllers — Funcionalidades

| Aspecto | Padrão Moderno | Atual | Status |
|---------|---------------|-------|--------|
| AJAX list (`executeListAjax`) | Sim | Não (usa `executeList`) | Migrar |
| AJAX create (`executeCreateAjax`) | Sim | Não (usa `executeCreate`) | Migrar |
| AJAX edit (`executeEditFormAjax` + `executeUpdateAjax`) | Sim | Não (usa `executeEdit`) | Migrar |
| AJAX delete (`executeDeleteAjax`) | Sim | Não (usa `executeDelete`) | Migrar |
| AJAX view (`executeViewAjax`) | Sim | Não (usa `executeView`) | Migrar |
| Match expression em `list()` | Sim (type 1 → AJAX) | Não | Adicionar |
| `searchAlias` no MODULE | Sim | Não | Adicionar |
| `searchConfig` no MODULE | Sim | Não | Adicionar |
| `editQuery` no MODULE | Sim | Não | Adicionar |
| `timestampColumns` no MODULE | Sim | Não | Adicionar |
| `displayConfig` no MODULE | Sim | Não | Adicionar |

### 4.3 Views

| Aspecto | Padrão Moderno | Atual | Status |
|---------|---------------|-------|--------|
| Load page (SPA shell) | `loadHdCategory.php` | Não existe | Criar |
| List fragment (AJAX) | `listHdCategory.php` | `listarSitUser.php` (full-page) | Reescrever |
| Add modal | `_add_hd_category_modal.php` | `cadSitUser.php` (full-page) | Criar partial |
| Edit form fragment | `_edit_hd_category_form.php` | `editarSitUser.php` (full-page) | Criar partial |
| View details fragment | `_view_hd_category_details.php` | `verSitUser.php` (full-page) | Criar partial |
| Delete modal | Inline no load | Não existe (usa confirm JS) | Criar inline |
| Hidden config div | Sim | Não | Adicionar |
| Filtros de pesquisa | Sim | Não | Adicionar |
| Cards nos formulários | Sim | Não | Adicionar |
| Cor preview | Não | Não | Adicionar (diferencial) |

### 4.4 JavaScript

| Aspecto | Padrão Moderno | Atual | Status |
|---------|---------------|-------|--------|
| Arquivo JS dedicado | `hd-categories.js` | Não existe | Criar |
| Fetch API | Sim | Não | Implementar |
| Debounced search | Sim | Não | Implementar |
| Paginação AJAX | Sim | Não | Implementar |
| CRUD via modals | Sim | Não | Implementar |
| Notification helper | Sim | Não | Implementar |

### 4.5 Segurança

| Aspecto | Padrão | Atual | Status |
|---------|--------|-------|--------|
| XSS na listagem | `htmlspecialchars()` | Parcial (tem nos IDs e nomes) | OK |
| XSS nos forms | `htmlspecialchars()` | Ausente nos `value=""` do form | Corrigir |
| SQL Injection | Prepared statements | OK (via AbstractConfigController) | OK |
| CSRF | Token automático | OK (via AbstractConfigController) | OK |
| Permissões | `AdmsBotao` | OK (usa `$this->Dados['botao']`) | OK |
| Delete confirm | Modal customizado | `data-confirm` JS nativo | Migrar para modal |

### 4.6 Qualidade de Código

| Aspecto | Padrão | Atual | Status |
|---------|--------|-------|--------|
| Type hints nos métodos | Sim | Parcial (`$PageId` sem tipo) | Corrigir |
| PHPDoc | Sim | Mínimo | Adicionar |
| `var_dump` no código | Não | Sim (comentado no cadSitUser/editarSitUser) | Remover |
| Verificação `URL` vs `URLADM` | `URLADM` | `URL` no verSitUser.php | Corrigir |
| Cor preview no badge | Sim (em outros módulos) | Sim (parcial na listagem) | Melhorar |

---

## 5. Pontuação por Critério

| Critério | Nota | Comentário |
|----------|------|------------|
| Nomenclatura | 3/10 | Controllers e métodos em português |
| Arquitetura | 5/10 | Usa AbstractConfigController mas padrão legacy |
| Segurança | 7/10 | SQL/CSRF OK, XSS parcial nos forms |
| UX/Frontend | 3/10 | Full-page reload, sem filtros, sem modals |
| JavaScript | 1/10 | Inexistente |
| Responsividade | 5/10 | Dropdown mobile básico na listagem |
| Logging | 8/10 | Via AbstractConfigController (automático) |
| Código limpo | 5/10 | `var_dump` comentados, sem type hints completos |
| **MÉDIA** | **4.6/10** | |

---

## 6. Vulnerabilidades Identificadas

### XSS (2 ocorrências)

1. **cadSitUser.php:28** — `value="<?php echo $valorForm['nome']; ?>"` sem escape
2. **editarSitUser.php:36** — `value="<?php echo $valorForm['nome']; ?>"` sem escape

### Inconsistência

1. **verSitUser.php:2** — Verifica `URL` em vez de `URLADM`

---

## 7. Conclusão

O módulo SituacaoUser está numa posição intermediária: já migrou para `AbstractConfigController` mas usa apenas os métodos legacy (page-reload). Comparado com o padrão moderno (HdCategories, CostCenters), falta:

1. **AJAX modals** para todas as operações CRUD
2. **SPA shell** (load page) com filtros inline
3. **JavaScript dedicado** para operações assíncronas
4. **Nomenclatura inglesa** nos controllers e métodos
5. **Cards organizados** nos formulários
6. **Cor preview** nos badges e formulários
7. **Search/filter** funcionalidade

A migração é relativamente simples pois a base (AbstractConfigController) já está no lugar — basta trocar de `executeList/Create/Edit/Delete/View` para `executeListAjax/CreateAjax/EditFormAjax/UpdateAjax/DeleteAjax/ViewAjax` e criar as views/JS correspondentes.
