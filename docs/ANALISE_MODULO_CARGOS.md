# Análise Completa: Módulo de Cargos

**Versão:** 1.0
**Data:** 04 de Fevereiro de 2026
**Autor:** Equipe Mercury
**Status:** Pronto para Refatoração

---

## Sumário Executivo

O módulo **Cargos** (Positions/Jobs) é uma implementação legada que **diverge significativamente** dos padrões atuais de desenvolvimento do projeto Mercury (versão 2.1). O módulo carece de recursos modernos do PHP 8.0+, type hints adequados, logging e segue convenções de nomenclatura desatualizadas.

### Métricas de Qualidade

| Métrica | Módulo Cargos | Padrão Mercury | Gap |
|---------|---------------|----------------|-----|
| Type Hints | 0% | 100% | -100% |
| PHPDoc | ~5% | 100% | -95% |
| Return Types | 0% | 100% | -100% |
| Logging | 0% | 100% | -100% |
| Consistência de Nomenclatura | 20% | 100% | -80% |
| Risco SQL Injection | 0% (Seguro) | 0% (Seguro) | ✓ |
| Risco XSS | Baixo | Baixo | ✓ |
| Cobertura de Testes | 0% | Deveria ter | ❌ |

---

## 1. Estrutura Atual

### 1.1 Arquivos do Módulo

#### Controllers (5 arquivos)
| Arquivo | Função | Localização |
|---------|--------|-------------|
| `Cargo.php` | Controller principal (listagem) | `app/adms/Controllers/` |
| `CadastrarCargo.php` | Ação de criar | `app/adms/Controllers/` |
| `EditarCargo.php` | Ação de editar | `app/adms/Controllers/` |
| `VerCargo.php` | Ação de visualizar | `app/adms/Controllers/` |
| `ApagarCargo.php` | Ação de deletar | `app/adms/Controllers/` |

#### Models (5 arquivos)
| Arquivo | Função | Localização |
|---------|--------|-------------|
| `AdmsListarCargo.php` | Model de listagem | `app/adms/Models/` |
| `AdmsCadastrarCargo.php` | Model de criação | `app/adms/Models/` |
| `AdmsEditarCargo.php` | Model de edição | `app/adms/Models/` |
| `AdmsVerCargo.php` | Model de visualização | `app/adms/Models/` |
| `AdmsApagarCargo.php` | Model de deleção | `app/adms/Models/` |

#### Views (4 arquivos)
| Arquivo | Função | Localização |
|---------|--------|-------------|
| `listarCargo.php` | View de listagem | `app/adms/Views/cargo/` |
| `cadCargo.php` | Formulário de criação | `app/adms/Views/cargo/` |
| `editarCargo.php` | Formulário de edição | `app/adms/Views/cargo/` |
| `verCargo.php` | View de detalhes | `app/adms/Views/cargo/` |

#### JavaScript
- **Não existe** arquivo JavaScript específico para o módulo

### 1.2 Estrutura do Banco de Dados

```sql
-- Tabela principal
CREATE TABLE `tb_cargos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50),
  `nivel` varchar(50),              -- Agrupamento/categoria
  `created` datetime NOT NULL,
  `modified` datetime DEFAULT NULL,
  `adms_niv_cargo_id` int(11),      -- FK para níveis
  `adms_sit_id` int(11)             -- Status ID
);

-- Tabela de níveis
CREATE TABLE `adms_niv_cargos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50)                -- Nome do nível
);
```

---

## 2. Análise Detalhada por Arquivo

### 2.1 Controllers

#### Cargo.php (Controller Principal)

**Problemas Identificados:**

```php
// Linha 20: SEM TYPE HINTS
public function listarCargo($PageId = null) {  // ❌ Deveria ser: int|string|null
    $this->PageId = (int) $PageId ? $PageId : 1;  // ❌ Lógica problemática
```

| Linha | Problema | Severidade |
|-------|----------|------------|
| 20 | Sem type hints no parâmetro | Crítico |
| 20 | Sem return type | Crítico |
| 10-14 | PHPDoc genérico | Alto |
| - | Não usa match expression | Médio |
| - | Nomenclatura em português | Médio |

#### CadastrarCargo.php (Controller de Criação)

```php
// Linha 19: SEM TYPE HINTS
public function cadCargo() {  // ❌ Deveria: public function create(): void
    $this->Dados = filter_input_array(INPUT_POST, FILTER_DEFAULT);
```

| Linha | Problema | Severidade |
|-------|----------|------------|
| 19 | Sem type hints | Crítico |
| 24 | Sem return type | Crítico |
| 42 | Sem LoggerService | Crítico |
| 42 | HTML hardcoded na sessão | Médio |
| - | Não usa NotificationService | Alto |

#### EditarCargo.php (Controller de Edição)

```php
// Linha 20-21: SEM TYPE HINTS
public function editCargo($DadosId = null) {
    $this->Dados = filter_input_array(INPUT_POST, FILTER_DEFAULT);

// Linha 30, 43, 52: HTML hardcoded
$_SESSION['msg'] = "<div class='alert alert-danger'>..."
```

| Linha | Problema | Severidade |
|-------|----------|------------|
| 20 | Sem type hints | Crítico |
| 11 | PHPDoc errado (diz "VerCor") | Alto |
| 30, 43, 52 | HTML hardcoded | Médio |
| - | Sem logging de atualizações | Crítico |

#### VerCargo.php (Controller de Visualização)

| Linha | Problema | Severidade |
|-------|----------|------------|
| 20 | Sem type hints | Crítico |
| 11 | PHPDoc errado (diz "VerCor") | Alto |
| 91 | URL slug incorreta (`listarCargo` vs `listar-cargo`) | Crítico |

#### ApagarCargo.php (Controller de Deleção)

```php
// Linha 29: URL INCORRETA
$UrlDestino = URLADM . 'cargo/listarCargo';  // ❌ Deveria ser 'cargo/listar-cargo'
```

| Linha | Problema | Severidade |
|-------|----------|------------|
| 20 | Sem type hints | Crítico |
| 29 | URL slug incorreta | Crítico |
| - | Sem logging de deleção | Crítico |
| - | Sem backup antes de deletar | Crítico |

---

### 2.2 Models

#### AdmsListarCargo.php (Model de Listagem)

```php
// Linha 22: SEM RETURN TYPE
function getResultPg() {
    return $this->ResultPg;
}

// Linha 36: Query sem filtro de status
$listCargo->fullRead("SELECT id, nome, nivel FROM tb_cargos ORDER BY nome ASC...");
// ❌ Deveria filtrar: WHERE adms_sit_id = 1
```

| Linha | Problema | Severidade |
|-------|----------|------------|
| 22, 26 | Sem type hints | Crítico |
| - | Sem PHPDoc | Alto |
| 36 | Query sem filtro de status ativo | Médio |
| - | Nomenclatura mista (classe PT, método EN) | Médio |

#### AdmsCadastrarCargo.php (Model de Criação)

```php
// Linha 38: SEM LOGGING
$this->Dados['created'] = date("Y-m-d H:i:s");
// ❌ Deveria também setar:
// - created_by_user_id = $_SESSION['usuario_id']
// - adms_sit_id = 1 (ativo)
// - Log com LoggerService::info()

// Linha 42: HTML HARDCODED
$_SESSION['msg'] = "<div class='alert alert-success'>Cargo cadastrado!</div>";
```

| Linha | Problema | Severidade |
|-------|----------|------------|
| 20, 24, 50 | Sem type hints | Crítico |
| 38 | Sem LoggerService | Crítico |
| 42 | HTML hardcoded | Médio |
| - | Falta created_by_user_id | Alto |
| - | Falta adms_sit_id padrão | Alto |

#### AdmsEditarCargo.php (Model de Edição)

| Linha | Problema | Severidade |
|-------|----------|------------|
| 25, 33 | Sem type hints | Crítico |
| 48 | Sem LoggerService | Crítico |
| - | Falta updated_by_user_id | Alto |
| - | Sem PHPDoc | Alto |

#### AdmsApagarCargo.php (Model de Deleção)

```php
// Linha 27: DELETA SEM LER ANTES
$apagarCargo->exeDelete("tb_cargos", "WHERE id =:id", "id={$this->DadosId}");
// ❌ FALTANDO:
// 1. Ler dados antes de deletar para log
// 2. LoggerService::info('CARGO_DELETED', ..., ['data' => $beforeDelete])
// 3. Verificar se há funcionários usando este cargo
```

| Linha | Problema | Severidade |
|-------|----------|------------|
| 24 | Sem type hints | Crítico |
| 27 | Sem backup antes de deletar | Crítico |
| 27 | Sem logging | Crítico |
| - | Não verifica dependências | Alto |

---

### 2.3 Views

#### listarCargo.php (View de Listagem)

**Pontos Positivos:**
- ✅ Escapa output com `htmlspecialchars()`
- ✅ Design responsivo
- ✅ Classes Bootstrap corretas

**Problemas:**

| Linha | Problema | Severidade |
|-------|----------|------------|
| 33 | `echo $_SESSION['msg']` sem escape | Baixo |
| 64-66 | URLs hardcoded com concatenação | Baixo |

#### cadCargo.php (Formulário de Criação)

```php
// Linha 48-49: ERRO DE LÓGICA
<?= ($valorForm['nivel'] ?? '') == $niv['niv_id'] ? 'selected' : '' ?>
// ❌ Compara 'nivel' com 'niv_id' (campo errado!)
// Deveria comparar com 'adms_niv_cargo_id'
```

| Linha | Problema | Severidade |
|-------|----------|------------|
| 48-49 | Comparação de campo errada | Crítico |
| 2-8 | Lógica órfã (deveria estar no controller) | Médio |

#### editarCargo.php (Formulário de Edição)

| Linha | Problema | Severidade |
|-------|----------|------------|
| 57 | Comparação correta (diferente do create) | - |
| - | Código duplicado com cadCargo.php | Médio |

#### verCargo.php (View de Detalhes)

| Linha | Problema | Severidade |
|-------|----------|------------|
| 76, 81 | Formatação de data pode ter problema de timezone | Baixo |
| 91 | URL slug incorreta | Crítico |

---

## 3. Comparativo: Implementação Atual vs Padrões do Projeto

### 3.1 Nomenclatura de Arquivos

| Componente | Atual | Padrão (REGRAS_DESENVOLVIMENTO.md) | Status |
|------------|-------|-------------------------------------|--------|
| Controller Principal | `Cargo.php` | `Cargos.php` (plural) | ❌ |
| Controller Criar | `CadastrarCargo.php` | `AddCargo.php` | ❌ |
| Controller Editar | `EditarCargo.php` | `EditCargo.php` | ❌ |
| Controller Deletar | `ApagarCargo.php` | `DeleteCargo.php` | ❌ |
| Controller Visualizar | `VerCargo.php` | `ViewCargo.php` | ❌ |
| Model Principal | `AdmsCadastrarCargo.php` | `AdmsCargo.php` | ❌ |
| Model Listagem | `AdmsListarCargo.php` | `AdmsListCargos.php` (plural) | ❌ |
| View Diretório | `cargo/` | `cargo/` | ✅ |
| View Load | - | `loadCargo.php` | ❌ Falta |
| View List | `listarCargo.php` | `listCargo.php` | ❌ |
| JavaScript | - | `cargo.js` | ❌ Falta |

### 3.2 Estrutura de Métodos

| Requisito | Atual | Padrão | Status |
|-----------|-------|--------|--------|
| Type Hints | Nenhum | Todos parâmetros e retornos | ❌ |
| PHPDoc | Genérico/Errado | Todos métodos públicos | ❌ |
| Match Expression | Não usa | PHP 8+ padrão | ❌ |
| Return Types | Nenhum | Declarações explícitas | ❌ |

### 3.3 Serviços e Helpers

| Serviço | Atual | Padrão | Status |
|---------|-------|--------|--------|
| LoggerService | Não usa | Obrigatório em CRUD | ❌ |
| NotificationService | HTML na sessão | Obrigatório | ❌ |
| FormSelectRepository | Não usa | Para selects | ❌ |
| Prepared Statements | ✅ Usa | Obrigatório | ✅ |
| Output Escaping | ✅ Usa | Obrigatório | ✅ |

### 3.4 Comparação com Módulo de Referência (Sales)

**Módulo Sales (Moderno - Jan 2026):**
```php
// ✅ Type hints completos
public function list(int|string|null $pageId = null): void

// ✅ Match expression para roteamento
match ($this->requestType) {
    1 => $this->listAllSales(),
    2 => $this->searchSales(),
    default => $this->loadInitialPage(),
};

// ✅ PHPDoc completo
/**
 * Método principal de listagem de vendas
 *
 * @param int|string|null $pageId Número da página
 * @return void
 */

// ✅ NotificationService
private NotificationService $notification;
```

**Módulo Cargos (Legado):**
```php
// ❌ Sem type hints
public function listarCargo($PageId = null) {

// ❌ Sem roteamento
$listCargo = new \App\adms\Models\AdmsListarCargo();

// ❌ PHPDoc genérico
/**
 * Description of Cargo
 */

// ❌ HTML hardcoded
$_SESSION['msg'] = "<div class='alert alert-success'>...</div>";
```

---

## 4. Problemas de Segurança

### 4.1 SQL Injection
**Status:** ✅ BAIXO RISCO
- Todas as queries usam prepared statements
- Parâmetros são vinculados corretamente

### 4.2 XSS (Cross-Site Scripting)
**Status:** ⚠️ RISCO MÉDIO

**Problema 1:** Exibição de mensagem de sessão
```php
// listarCargo.php linha 33
echo $_SESSION['msg'];  // ❌ Se admin injetar HTML/JS
```

**Problema 2:** HTML hardcoded nos controllers
```php
// EditarCargo.php linha 43
$_SESSION['msg'] = "<div class='alert alert-success'>..."
```

### 4.3 CSRF
**Status:** ✅ CONFORME
- Usa helper `csrf_field()` nos formulários

### 4.4 Validação de Input
**Status:** ⚠️ BÁSICO
- Usa `filter_input_array(INPUT_POST, FILTER_DEFAULT)`
- Falta validação específica por tipo

---

## 5. Problemas por Severidade

### 5.1 CRÍTICOS (Devem ser corrigidos)

| # | Problema | Arquivos Afetados |
|---|----------|-------------------|
| 1 | Sem type hints em todos os métodos | Todos controllers e models |
| 2 | Sem logging de operações CRUD | AdmsCadastrarCargo, AdmsEditarCargo, AdmsApagarCargo |
| 3 | URL slug incorreta após delete | ApagarCargo.php:29, verCargo.php:91 |
| 4 | Deleção sem backup para auditoria | AdmsApagarCargo.php:27 |
| 5 | Erro de lógica no formulário create | cadCargo.php:48-49 |

### 5.2 ALTOS (Devem ser corrigidos em breve)

| # | Problema | Arquivos Afetados |
|---|----------|-------------------|
| 6 | Sem PHPDoc em métodos públicos | Todos arquivos |
| 7 | Nomenclatura inconsistente | Todos controllers e models |
| 8 | Não usa NotificationService | Todos controllers |
| 9 | Sem return type declarations | Todos métodos |
| 10 | Falta created_by_user_id/updated_by_user_id | Models de CRUD |

### 5.3 MÉDIOS (Devem ser melhorados)

| # | Problema | Arquivos Afetados |
|---|----------|-------------------|
| 11 | Não retorna JSON para AJAX | Todos controllers |
| 12 | Nomes de campos desatualizados | Banco de dados |
| 13 | Não usa match expression | Controllers |
| 14 | Estrutura de banco inadequada | tb_cargos |
| 15 | Sem validação de status | Models |

---

## 6. Sugestões de Melhorias

### 6.1 Melhorias de Código

#### Adicionar Type Hints
```php
// ANTES
public function listarCargo($PageId = null) {

// DEPOIS
public function list(int|string|null $pageId = null): void
```

#### Implementar Logging
```php
use App\adms\Services\LoggerService;

// Ao criar
LoggerService::info('CARGO_CREATED', 'Novo cargo criado', [
    'cargo_id' => $id,
    'name' => $data['nome'],
    'user_id' => $_SESSION['usuario_id']
]);

// Ao deletar (ler antes)
$read = new AdmsRead();
$read->fullRead("SELECT * FROM tb_cargos WHERE id = :id", "id={$id}");
$beforeDelete = $read->getResult();

LoggerService::info('CARGO_DELETED', 'Cargo deletado', [
    'data' => $beforeDelete[0]
]);
```

#### Usar NotificationService
```php
// ANTES
$_SESSION['msg'] = "<div class='alert alert-success'>Cargo cadastrado!</div>";

// DEPOIS
$this->notification->success('Cargo cadastrado com sucesso!');
```

#### Implementar Match Expression
```php
// ANTES
if ($this->requestType == 1) {
    $this->listAll();
} elseif ($this->requestType == 2) {
    $this->search();
} else {
    $this->loadPage();
}

// DEPOIS
match ($this->requestType) {
    1 => $this->listAll(),
    2 => $this->search(),
    default => $this->loadPage(),
};
```

### 6.2 Melhorias de Banco de Dados

```sql
-- Atualizar estrutura da tabela
ALTER TABLE tb_cargos
    CHANGE COLUMN `created` `created_at` DATETIME NOT NULL,
    CHANGE COLUMN `modified` `updated_at` DATETIME DEFAULT NULL,
    ADD COLUMN `created_by_user_id` INT(11) DEFAULT NULL AFTER `updated_at`,
    ADD COLUMN `updated_by_user_id` INT(11) DEFAULT NULL AFTER `created_by_user_id`,
    ADD COLUMN `hash_id` CHAR(36) DEFAULT NULL AFTER `id`,
    ADD COLUMN `is_deleted` TINYINT(1) DEFAULT 0 AFTER `adms_sit_id`;

-- Índices
ALTER TABLE tb_cargos
    ADD INDEX idx_hash_id (hash_id),
    ADD INDEX idx_is_deleted (is_deleted);
```

### 6.3 Melhorias de UX

1. **Modal de confirmação para deleção** - Atualmente deleta direto
2. **Formulário AJAX** - Atualmente usa redirect
3. **Validação client-side** - JavaScript para validar antes de enviar
4. **Feedback visual** - Spinners durante operações

---

## 7. Plano de Ação para Implementação

### Fase 1: Correções Críticas (Prioridade Alta)
**Estimativa:** 2-3 horas

| # | Tarefa | Arquivos |
|---|--------|----------|
| 1.1 | Corrigir URLs slugs incorretas | ApagarCargo.php, verCargo.php |
| 1.2 | Corrigir erro de lógica no form create | cadCargo.php |
| 1.3 | Adicionar backup antes de deletar | AdmsApagarCargo.php |
| 1.4 | Adicionar LoggerService básico | Todos models CRUD |

### Fase 2: Renomeação de Arquivos (Prioridade Alta)
**Estimativa:** 1-2 horas

| Arquivo Atual | Novo Nome |
|---------------|-----------|
| `Cargo.php` | `Cargos.php` |
| `CadastrarCargo.php` | `AddCargo.php` |
| `EditarCargo.php` | `EditCargo.php` |
| `ApagarCargo.php` | `DeleteCargo.php` |
| `VerCargo.php` | `ViewCargo.php` |
| `AdmsListarCargo.php` | `AdmsListCargos.php` |
| `AdmsCadastrarCargo.php` | `AdmsCargo.php` |
| `AdmsEditarCargo.php` | `AdmsEditCargo.php` |
| `AdmsApagarCargo.php` | `AdmsDeleteCargo.php` |
| `AdmsVerCargo.php` | `AdmsViewCargo.php` |
| `listarCargo.php` | `listCargo.php` |

**Nota:** Atualizar rotas em `adms_paginas` após renomear.

### Fase 3: Modernização do Código (Prioridade Média)
**Estimativa:** 3-4 horas

| # | Tarefa | Arquivos |
|---|--------|----------|
| 3.1 | Adicionar type hints em todos os métodos | Todos |
| 3.2 | Adicionar return types | Todos |
| 3.3 | Adicionar PHPDoc completo | Todos |
| 3.4 | Implementar match expression | Controllers |
| 3.5 | Substituir mensagens por NotificationService | Controllers |

### Fase 4: Melhorias de Banco de Dados (Prioridade Média)
**Estimativa:** 1-2 horas

| # | Tarefa |
|---|--------|
| 4.1 | Criar migration para alterar estrutura |
| 4.2 | Renomear campos created/modified |
| 4.3 | Adicionar campos de auditoria |
| 4.4 | Adicionar hash_id (UUID) |
| 4.5 | Adicionar soft delete |

### Fase 5: Modernização Frontend (Prioridade Baixa)
**Estimativa:** 2-3 horas

| # | Tarefa |
|---|--------|
| 5.1 | Criar arquivo cargo.js |
| 5.2 | Implementar CRUD via AJAX |
| 5.3 | Criar modais para add/edit/delete |
| 5.4 | Adicionar validação client-side |
| 5.5 | Criar view loadCargo.php |

### Fase 6: Testes (Prioridade Média)
**Estimativa:** 2-3 horas

| # | Tarefa |
|---|--------|
| 6.1 | Criar testes unitários para Models |
| 6.2 | Criar testes de integração |
| 6.3 | Testar fluxo completo CRUD |

---

## 8. Checklist de Implementação

### Pré-Refatoração
- [ ] Backup do banco de dados
- [ ] Backup dos arquivos atuais
- [ ] Documentar rotas atuais em adms_paginas

### Controllers
- [ ] Renomear arquivos seguindo padrão
- [ ] Adicionar type hints em todos os métodos
- [ ] Adicionar return types
- [ ] Adicionar PHPDoc completo
- [ ] Implementar match expression
- [ ] Usar NotificationService
- [ ] Corrigir URLs slugs
- [ ] Adicionar resposta JSON para AJAX

### Models
- [ ] Renomear arquivos seguindo padrão
- [ ] Adicionar type hints em todos os métodos
- [ ] Adicionar return types
- [ ] Adicionar PHPDoc completo
- [ ] Implementar LoggerService
- [ ] Adicionar campos de auditoria
- [ ] Implementar backup antes de delete
- [ ] Verificar dependências antes de delete

### Views
- [ ] Renomear arquivos seguindo padrão
- [ ] Criar loadCargo.php
- [ ] Criar partials para modais
- [ ] Corrigir erro de lógica no form create
- [ ] Remover código órfão das views

### JavaScript
- [ ] Criar cargo.js
- [ ] Implementar handlers de eventos
- [ ] Implementar AJAX para CRUD
- [ ] Adicionar validação client-side

### Banco de Dados
- [ ] Criar migration
- [ ] Atualizar estrutura da tabela
- [ ] Atualizar rotas em adms_paginas

### Testes
- [ ] Criar testes unitários
- [ ] Testar fluxo completo
- [ ] Validar permissões

---

## 9. Estrutura Final Esperada

```
app/adms/Controllers/
├── Cargos.php              # Controller principal (listagem)
├── AddCargo.php            # Criar
├── EditCargo.php           # Editar
├── DeleteCargo.php         # Deletar
└── ViewCargo.php           # Visualizar

app/adms/Models/
├── AdmsCargo.php           # Model principal (CRUD)
├── AdmsListCargos.php      # Listagem
├── AdmsViewCargo.php       # Visualização
└── AdmsStatisticsCargos.php # Estatísticas (opcional)

app/adms/Views/cargo/
├── loadCargo.php           # Página principal
├── listCargo.php           # Lista AJAX
└── partials/
    ├── _add_cargo_modal.php
    ├── _edit_cargo_modal.php
    ├── _view_cargo_modal.php
    └── _delete_cargo_modal.php

assets/js/
└── cargo.js                # JavaScript do módulo
```

---

## 10. Referências

- **Módulo de Referência:** `docs/ANALISE_MODULO_SALES.md`
- **Regras de Desenvolvimento:** `.claude/REGRAS_DESENVOLVIMENTO.md`
- **Guia de Implementação:** `docs/GUIA_IMPLEMENTACAO_MODULOS.md`
- **Padrões de Código:** `docs/PADRONIZACAO.md`

---

## 11. Conclusão

O módulo de Cargos requer uma **refatoração significativa** para alinhar-se aos padrões atuais do projeto Mercury. As principais áreas de foco são:

1. **Type hints e PHPDoc** - Modernização para PHP 8.0+
2. **Logging e auditoria** - Rastreabilidade de operações
3. **Nomenclatura** - Consistência com outros módulos
4. **UX moderna** - AJAX e modais
5. **Segurança** - Validação e sanitização aprimoradas

**Esforço Estimado Total:** 12-18 horas

**Recomendação:** Usar o módulo Sales como referência e seguir o plano de ação em fases, priorizando as correções críticas.

---

**Documento criado em:** 04 de Fevereiro de 2026
**Próxima revisão:** Após conclusão da Fase 1
