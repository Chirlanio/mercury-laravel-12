# Análise Completa — Módulo Centros de Custos

**Data:** 04/03/2026
**Status:** Legacy (Page-Reload CRUD)
**Nota Geral:** 3/10

---

## 1. Inventário de Arquivos

### Controllers (5 arquivos)
| Arquivo | Linhas | Método Público |
|---------|--------|----------------|
| `Controllers/CostCenters.php` | 43 | `list($PageId)` |
| `Controllers/AddCostCenter.php` | 59 | `costCenter()` |
| `Controllers/EditCostCenter.php` | 82 | `costCenter($DadosId)` |
| `Controllers/DeleteCostCenter.php` | 33 | `costCenter($DadosId)` |
| `Controllers/ViewCostCenter.php` | 46 | `costCenter($DadosId)` |

### Models (6 arquivos)
| Arquivo | Linhas | Função |
|---------|--------|--------|
| `Models/AdmsAddCostCenter.php` | 73 | Create + listAdd() |
| `Models/AdmsEditCostCenter.php` | 85 | Edit + listAdd() |
| `Models/AdmsDelCostCenter.php` | 41 | Delete (hard delete) |
| `Models/AdmsViewCostCenter.php` | 29 | View single record |
| `Models/AdmsListCostCenter.php` | 44 | List with pagination |
| `cpadms/Models/CpAdmsPesqCostCenter.php` | 84 | Search/filter |

### Views (4 arquivos)
| Arquivo | Linhas | Tipo |
|---------|--------|------|
| `Views/costCenter/listCostCenter.php` | 132 | Lista com tabela |
| `Views/costCenter/addCostCenter.php` | 147 | Formulário full-page |
| `Views/costCenter/editCostCenter.php` | 157 | Formulário full-page |
| `Views/costCenter/viewCostCenter.php` | 94 | Detalhe full-page |

### JavaScript
**Nenhum arquivo dedicado.** Não existe `cost-centers.js` ou similar.

### Partials/Modals
**Nenhum.** O módulo não usa modais — todo CRUD é page-reload.

---

## 2. Análise de Conformidade

### 2.1 Controllers vs Padrão Atual

| Critério | Esperado | Encontrado | Status |
|----------|----------|------------|--------|
| Type hints nos parâmetros | `int\|string\|null $PageId` | `$PageId = null` (sem type hint) | FALHA |
| Type hints no retorno | `: void` | Ausente | FALHA |
| PHPDoc em métodos públicos | Sim | Não | FALHA |
| `match` expression para routing | Sim | Não usa | FALHA |
| Métodos em inglês (`create`, `update`) | Sim | `costCenter()` (Portuguese) | FALHA |
| LoggerService | Obrigatório | Não utiliza | FALHA |
| NotificationService | Obrigatório | Não utiliza — usa `$_SESSION['msg']` com HTML hardcoded | FALHA |
| JSON response para AJAX | Sim | Não usa AJAX | FALHA |
| `filter_input()` com tipo | Sim | Apenas `FILTER_DEFAULT` | PARCIAL |
| CSRF token removal | `unset($data['_csrf_token'])` | Presente (Deploy 5) | OK |
| Permissões via AdmsBotao | Sim | Sim | OK |

### 2.2 Models vs Padrão Atual

| Critério | Esperado | Encontrado | Status |
|----------|----------|------------|--------|
| Private properties (`$result`, `$resultBd`) | Sim | Parcial — apenas `$Resultado` e `$ResultadoBd` | FALHA |
| Public getters (`getResult()`, `getResultBd()`) | Sim | Usa `getResult()` e `getResultadoBd()` | PARCIAL |
| Validação explícita de campos | Sim | Usa `AdmsCampoVazio` (legacy) | FALHA |
| Audit fields (`created_by_user_id`) | Sim | Apenas `created` e `modified` — sem user tracking | FALHA |
| UUID v7 para `hash_id` | Quando aplicável | Não | N/A |
| Prepared statements | Sim | Sim | OK |
| LoggerService em CRUD | Obrigatório | Não utiliza | FALHA |
| Tratamento de `AdmsUpdate::getResult() = false` | Verificar 0 rows | Não trata | FALHA |

### 2.3 Views vs Padrão Atual

| Critério | Esperado | Encontrado | Status |
|----------|----------|------------|--------|
| Security check `!defined('URLADM')` | Sim | Apenas em `listCostCenter` e `viewCostCenter` | PARCIAL |
| Layout com modais AJAX | Sim | Full-page reload CRUD | FALHA |
| `loadEntityName.php` + `listEntityName.php` | Sim | Apenas `listCostCenter.php` (sem load) | FALHA |
| Partials `_snake_case_modal.php` | Sim | Nenhum modal existe | FALHA |
| `htmlspecialchars()` em TODOS os outputs | Sim | **3 vulnerabilidades XSS** | FALHA |
| Container `id="content_entity"` para AJAX | Sim | Não existe | FALHA |
| Cards de estatísticas | Recomendado | Não existe | FALHA |
| Filtros de busca no load | Recomendado | Busca via form POST redirect | FALHA |
| Responsivo (mobile + desktop) | Sim | Sim (usa `d-none d-md-block` + dropdown) | OK |

### 2.4 JavaScript vs Padrão Atual

| Critério | Esperado | Encontrado | Status |
|----------|----------|------------|--------|
| Arquivo `kebab-case.js` dedicado | Sim | Não existe | FALHA |
| `async/await` + `fetch()` | Sim | Nenhum JS | FALHA |
| Event delegation | Sim | Nenhum | FALHA |
| Loading states | Sim | Nenhum | FALHA |
| Notificações do server (HTML) | Sim | Nenhum | FALHA |

---

## 3. Problemas Críticos

### 3.1 Vulnerabilidades XSS (CRÍTICO)

**addCostCenter.php** — Linhas 36-47: Valores de form output SEM escape:
```php
// ❌ VULNERÁVEL
value="<?php if (isset($valorForm['cost_center_id'])) { echo $valorForm['cost_center_id']; } ?>"
value="<?php if (isset($valorForm['name'])) { echo $valorForm['name']; } ?>"

// ✅ CORRETO
value="<?= htmlspecialchars($valorForm['cost_center_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
```

**editCostCenter.php** — Linhas 44-54: Mesmo problema em `cost_center_id`, `costCenter` (name).

**listCostCenter.php** — Linha 30: `$_SESSION['search']` output sem escape:
```php
// ❌ VULNERÁVEL
value="<?php if (isset($_SESSION['search'])) { echo $_SESSION['search']; } ?>"
```

### 3.2 Inconsistência de Tabelas (ALTO)

Os models referenciam tabelas diferentes para o campo "responsável/gerência":

| Arquivo | Tabela | Coluna |
|---------|--------|--------|
| `AdmsAddCostCenter.php` | `adms_managers` | `name` as `responsavel` |
| `AdmsEditCostCenter.php` | `adms_managers` | `name` as `gerencia` |
| `AdmsViewCostCenter.php` | `adms_employees` | `name_employee` as `gerencia` |
| `AdmsListCostCenter.php` | `adms_managers` | `name` as `gerencia` |
| `CpAdmsPesqCostCenter.php` | `adms_managers` | `name` as `gerencia` |

O `AdmsViewCostCenter` usa `adms_employees` enquanto os demais usam `adms_managers`. Isso pode causar dados incorretos na visualização.

### 3.3 Inconsistência de JOINs (ALTO)

| Arquivo | Manager JOIN | Status JOIN |
|---------|-------------|-------------|
| `AdmsListCostCenter.php` | LEFT JOIN | INNER JOIN |
| `CpAdmsPesqCostCenter.php` | INNER JOIN | INNER JOIN |
| `AdmsViewCostCenter.php` | LEFT JOIN | INNER JOIN |

A pesquisa (`CpAdmsPesqCostCenter`) usa INNER JOIN no manager, ocultando registros sem gerente atribuído. A listagem usa LEFT JOIN, mostrando-os. Resultado: contagem diferente entre lista e pesquisa.

### 3.4 Inconsistência de Permissões nas Views (MÉDIO)

```php
// addCostCenter.php — usa igualdade
if ($_SESSION['adms_niveis_acesso_id'] == STOREPERMITION) { // == 18

// editCostCenter.php — usa maior-que
if ($_SESSION['adms_niveis_acesso_id'] > ADMPERMITION) { // > 1
```

Lógicas diferentes para o mesmo propósito (desabilitar selects por nível de acesso). Resultam em comportamento diferente: o add bloqueia apenas nível 18, o edit bloqueia níveis 2-18.

### 3.5 Delete sem Confirmação Adequada (MÉDIO)

O delete usa `data-confirm` attribute nos links, que depende de um handler global `confirm()`. Não existe modal de confirmação customizado conforme padrão atual.

### 3.6 Hard Delete (MÉDIO)

O módulo faz DELETE físico da tabela. Não há soft delete (`deleted_at`), não há log do registro antes de deletar, e não há verificação de dependências (FK constraints).

### 3.7 Sem Logging/Auditoria (MÉDIO)

Nenhuma operação CRUD é registrada via `LoggerService`. Não há rastreabilidade de quem criou, editou ou deletou um centro de custo.

---

## 4. Código Duplicado

### 4.1 Selects com if/else para permissão (Views)

Em `addCostCenter.php` e `editCostCenter.php`, cada select (Área, Responsável, Situação) é duplicado com a única diferença sendo o atributo `disabled`. São ~40 linhas duplicadas por select × 3 selects × 2 views = **~240 linhas** que poderiam ser reduzidas a ~30 com uma abordagem simples:

```php
// ✅ Solução: atributo disabled condicional
$disabled = ($_SESSION['adms_niveis_acesso_id'] > ADMPERMITION) ? 'disabled' : '';
echo "<select name='field' class='form-control' {$disabled}>";
```

### 4.2 listAdd() Duplicado nos Models

`AdmsAddCostCenter::listAdd()` e `AdmsEditCostCenter::listAdd()` contêm queries quase idênticas para carregar selects, com diferenças nas colunas retornadas:
- Add: `id r_id, name responsavel` de `adms_managers`
- Edit: `id f_id, name gerencia` de `adms_managers`

Estas diferenças causam problemas nas views que esperam alias diferentes.

---

## 5. Comparação com Módulo de Referência (Sales)

| Aspecto | Sales (Referência) | CostCenters (Atual) |
|---------|-------------------|---------------------|
| Controller routing | `match` expression | if/elseif |
| CRUD pattern | AJAX modals | Page-reload |
| Type hints | Completos | Ausentes |
| Logging | LoggerService em tudo | Nenhum |
| Notificações | NotificationService | `$_SESSION['msg']` hardcoded |
| Estatísticas | Cards com totais | Nenhum |
| JavaScript | `sales.js` (async/await) | Nenhum JS dedicado |
| Filtros | AJAX inline com debounce | Form POST redirect |
| Views | load + list + modals | 4 páginas separadas |
| Testes | 162 testes unitários | 0 testes |
| Validação | Explícita no model | `AdmsCampoVazio` legacy |
| Segurança XSS | `htmlspecialchars` em tudo | 3 vulnerabilidades |
| Responsividade | Completa com cards | Básica (tabela) |

---

## 6. Candidato a AbstractConfigController?

**Sim — é um candidato ideal.** O módulo de Centros de Custos é uma tabela de lookup simples com CRUD básico, sem lógica de negócio complexa. Encaixa perfeitamente no padrão `AbstractConfigController`:

- Tabela principal: `adms_cost_centers`
- CRUD simples (sem workflows, aprovações, ou transições de status)
- Selects de lookup: áreas, managers, situações
- Sem dependências complexas
- Busca simples por nome

A migração para `AbstractConfigController` resolveria automaticamente: logging, validação, notificações, paginação, AJAX, modais, e permissões — eliminando os 5 controllers, 6 models e 4 views legados.

---

## 7. Sugestões de Melhorias

### Prioridade Alta
1. **Corrigir vulnerabilidades XSS** — Adicionar `htmlspecialchars()` em todos os outputs não-escapados
2. **Unificar tabela de managers** — Decidir entre `adms_managers` e `adms_employees` e corrigir todos os models
3. **Padronizar JOINs** — Usar LEFT JOIN consistentemente para manager (permite registros sem gerente)
4. **Migrar para AbstractConfigController** — Elimina toda a base de código legacy de uma vez

### Prioridade Média
5. **Adicionar LoggerService** — Log de create, update, delete com contexto
6. **Implementar soft delete** — Adicionar `deleted_at` e verificação de FK antes de delete
7. **Criar arquivo JavaScript** — `cost-centers.js` com AJAX, modais, event delegation
8. **Adicionar cards de estatísticas** — Total, ativos, inativos, por área
9. **Padronizar permissões** — Usar mesma lógica em add e edit
10. **Remover código duplicado** — Selects com `disabled` condicional

### Prioridade Baixa
11. **Adicionar testes unitários** — Pelo menos para CRUD do model
12. **Adicionar validação de formato** — Máscara para `cost_center_id` (0.0.00.00)
13. **Adicionar audit fields** — `created_by_user_id`, `updated_by_user_id`
14. **Adicionar timestamps UTC** — `created_at` e `updated_at` com padrão ISO
15. **Remover `var_dump` comentados** — Limpar código de debug

---

## 8. Esforço Estimado de Modernização

### Opção A: Migrar para AbstractConfigController (Recomendado)
- **Escopo:** Criar 1 controller principal + 4 action controllers + MODULE array + 4 views (load, list, edit partial, view partial) + 1 JS
- **Benefício:** Elimina 5 controllers + 6 models + 4 views legados. Ganha logging, AJAX, modais, validação, notificações automaticamente
- **Arquivos novos:** ~8 arquivos
- **Arquivos removidos:** ~15 arquivos

### Opção B: Refatorar In-Place
- **Escopo:** Corrigir XSS, adicionar type hints, logging, JS, converter para modais
- **Benefício:** Mantém estrutura existente, correções incrementais
- **Arquivos modificados:** ~15 arquivos

**Recomendação: Opção A** — O módulo é simples o suficiente para a migração completa, e o resultado é código muito mais limpo e mantido pelo framework.
