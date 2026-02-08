# Analise do Modulo StoreGoals (Metas por Lojas)

**Data:** 24 de Janeiro de 2026
**Autor:** Equipe Mercury
**Versao:** 2.1
**Status:** PARCIALMENTE MODERNIZADO

---

## 1. Visao Geral

O modulo `StoreGoals` gerencia as metas de vendas por loja, incluindo:
- Cadastro de metas mensais por loja
- Importacao em lote via arquivo CSV/XLSX ✅ (Jan/2026)
- Redistribuicao automatica de metas via `StoreGoalsRedistributionService` ✅
- Calculo de super metas (115%) e hiper metas (115% da super)
- Ajuste proporcional de feriados (non_working_days)
- Deducao de treinamento (3 dias para novas contratacoes)
- Deducao de atestados medicos >= 10 dias
- Visualizacao detalhada de metas e consultoras
- Confirmacao de vendas por consultora ✅
- Impressao de meta detalhada ✅
- 175 testes unitarios implementados ✅

### 1.1 Estrutura de Arquivos Atual

```
app/adms/Controllers/
├── StoreGoals.php           # Controller principal (listagem + estatisticas)
├── AddStoreGoals.php        # Adicionar meta (AJAX)
├── EditStoreGoal.php        # Editar meta (AJAX)
├── DeleteStoreGoal.php      # Excluir meta (AJAX)
├── ViewStoreGoals.php       # Visualizar meta (AJAX)
└── ImportStoreGoals.php     # ✅ Importar metas via CSV/XLSX (Jan/2026)

app/adms/Models/
├── AdmsAddStoreGoals.php    # Model de adicao (usa redistribution service)
├── AdmsEditStoreGoal.php    # Model de edicao (usa redistribution service)
├── AdmsDeleteStoreGoal.php  # Model de exclusao
├── AdmsListStoreGoals.php   # Model de listagem
├── AdmsViewStoreGoals.php   # Model de visualizacao (CTE com metas individuais)
├── AdmsStatisticsStoreGoals.php # Estatisticas
└── AdmsImportStoreGoals.php # ✅ Model de importacao em lote (Jan/2026)

app/adms/Services/
└── StoreGoalsRedistributionService.php  # ✅ Service central de redistribuicao

app/adms/Views/goals/
├── loadStoreGoals.php       # Pagina principal
├── listStoreGoals.php       # Lista AJAX
├── partials/
│   ├── _add_store_goal_modal.php        # Modal de adicao
│   ├── _edit_store_goal_modal.php       # Modal de edicao
│   ├── _edit_store_goal_form.php        # Formulario de edicao (AJAX)
│   ├── _view_store_goal_modal.php       # Modal de visualizacao
│   ├── _view_store_goals_content.php    # Conteudo da visualizacao
│   ├── _delete_store_goal_modal.php     # Modal de exclusao
│   ├── _confirm_sales_modal.php         # Modal de confirmacao de vendas
│   ├── _statistics_dashboard.php        # Cards de estatisticas
│   └── _import_store_goals_modal.php    # ✅ Modal de importacao (Jan/2026)

app/cpadms/Models/
└── CpAdmsSearchStoreGoals.php  # Busca

assets/js/
└── store-goals.js           # ✅ JavaScript dedicado (jQuery IIFE)

tests/StoreGoals/            # ✅ 175 testes unitarios
├── StoreGoalsRedistributionServiceTest.php
├── Unit/
│   ├── WeightCalculationTest.php
│   ├── GoalDistributionTest.php
│   └── ...
├── Models/
└── Integration/
```

---

## 2. Analise de Conformidade com Padroes

### 2.1 Nomenclatura

| Item | Atual | Esperado | Status |
|------|-------|----------|--------|
| Controller Principal | `StoreGoals.php` | `StoreGoals.php` | ✅ OK |
| Controller Add | `AddStoreGoals.php` | `AddStoreGoals.php` | ✅ OK |
| Controller Edit | `EditStoreGoal.php` | `EditStoreGoals.php` | ❌ SINGULAR |
| Controller Delete | `DeleteStoreGoal.php` | `DeleteStoreGoals.php` | ❌ SINGULAR |
| Controller View | `ViewStoreGoals.php` | `ViewStoreGoals.php` | ✅ OK |
| Model Edit | `AdmsEditStoreGoal.php` | `AdmsEditStoreGoals.php` | ❌ SINGULAR |
| Model Delete | `AdmsDeleteStoreGoal.php` | `AdmsDeleteStoreGoals.php` | ❌ SINGULAR |
| Diretorio Views | `goals/` | `storeGoals/` | ❌ INCORRETO |
| JavaScript | `store-goals.js` | `store-goals.js` | ✅ OK |

### 2.2 Controllers - Problemas Identificados

#### StoreGoals.php (Controller Principal)

**Problemas:**

1. **Sem Type Hints nos retornos**
```php
// ❌ ATUAL
public function list(int|string|null $PageId = null) {

// ✅ ESPERADO
public function list(int|string|null $pageId = null): void {
```

2. **Variaveis em PascalCase**
```php
// ❌ ATUAL
private array|null $Dados;
private int|string|null $PageId;
private $TypeResult;

// ✅ ESPERADO
private ?array $data = [];
private int $pageId;
private ?int $requestType;
```

3. **Sem match expression para roteamento**
```php
// ❌ ATUAL
if (!empty($this->TypeResult) AND ( $this->TypeResult == 1)) {
    $this->listStoreGoalsPriv();
} elseif (!empty($this->TypeResult) AND ( $this->TypeResult == 2)) {
    $this->searchStoreGoalPriv();
} else {
    // ...
}

// ✅ ESPERADO
match ($this->requestType) {
    1 => $this->listAllGoals(),
    2 => $this->searchGoals(),
    default => $this->loadInitialPage(),
};
```

4. **Sem metodo loadButtons() estruturado**
   - Botoes carregados diretamente no metodo list()

5. **Sem metodo loadStats()**
   - Nao possui cards de estatisticas

6. **PHPDoc incompleto**
```php
// ❌ ATUAL
/**
 * Description of StoreGoals
 *
 * @copyright (c) year, Chirlanio Silva - Grupo Meia Sola
 */

// ✅ ESPERADO
/**
 * Controller de Metas por Loja
 *
 * Gerencia listagem, busca e estatisticas de metas
 *
 * @author Chirlanio Silva - Grupo Meia Sola
 * @copyright (c) 2025, Grupo Meia Sola
 */
```

7. **Uso de classes com namespace completo inline**
```php
// ❌ ATUAL
$listButtons = new \App\adms\Models\AdmsBotao();

// ✅ ESPERADO (usar use no topo)
use App\adms\Models\AdmsBotao;
// ...
$listButtons = new AdmsBotao();
```

#### AddStoreGoals.php (Controller de Adicao)

**Problemas Criticos:**

1. **NAO usa NotificationService**
```php
// ❌ ATUAL - Usa $_SESSION['msg'] diretamente
if ($addGoal->getResult()) {
    $result = ['erro' => true, 'msg' => $_SESSION['msg']];
    unset($_SESSION['msg']);
}

// ✅ ESPERADO
private NotificationService $notification;

public function __construct()
{
    $this->notification = new NotificationService();
}

public function create(): void
{
    // ...
    if ($result) {
        $this->notification->success('Meta cadastrada com sucesso!');
        $notificationHtml = $this->notification->getFlashMessage();

        $response = [
            'error' => false,
            'msg' => 'Meta cadastrada com sucesso!',
            'notification_html' => $notificationHtml
        ];
    }
}
```

2. **Inversao logica na resposta JSON**
```php
// ❌ ATUAL - 'erro' invertido
if ($addGoal->getResult()) {
    $result = ['erro' => true, 'msg' => $_SESSION['msg']];  // Result TRUE = erro TRUE ???
} else {
    $result = ['erro' => false, 'msg' => $_SESSION['msg']]; // Result FALSE = erro FALSE ???
}

// ✅ ESPERADO - Logica clara
if ($model->getResult()) {
    $response = ['error' => false, 'msg' => 'Sucesso'];
} else {
    $response = ['error' => true, 'msg' => $errorMessage];
}
```

3. **Sem type hints no retorno**
```php
// ❌ ATUAL
public function create() {

// ✅ ESPERADO
public function create(): void {
```

4. **Sem LoggerService**

5. **Sem validacao estruturada**

#### EditStoreGoal.php (Controller de Edicao)

**Problemas Criticos:**

1. **Usa $_SESSION['msg'] com HTML inline (VULNERAVEL)**
```php
// ❌ ATUAL - HTML hardcoded na session
$_SESSION['msg'] = "<div class='alert alert-danger alert-dismissible fade show' role='alert'><strong>Erro:</strong> Registro não encontrado!<button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button></div>";

// ✅ ESPERADO - NotificationService
$this->notification->error('Registro nao encontrado!');
```

2. **Sem exit() apos header()**
```php
// ❌ ATUAL
header("Location: $UrlDestino");
// Sem exit()

// ✅ ESPERADO
header("Location: $UrlDestino");
exit();
```

3. **Page-reload em vez de AJAX**
   - Formulario usa POST tradicional com redirect
   - Deveria usar modal AJAX como no Sales

4. **Recursao problematica**
```php
// ❌ ATUAL - Chama o proprio metodo em caso de erro
if ($editGoal->getResult()) {
    // sucesso
} else {
    $this->Dados['form'] = $this->Dados;
    $this->editStoreGoalPriv();  // RECURSAO!
}
```

#### DeleteStoreGoal.php (Controller de Exclusao)

**Problemas:**

1. **NAO usa NotificationService**
2. **Sem confirmacao via modal AJAX**
3. **Sem LoggerService para auditoria**
4. **Sem validacao de propriedade/permissao**

#### ViewStoreGoals.php (Controller de Visualizacao)

**Problemas:**

1. **Usa $_SESSION['msg'] com HTML inline**
2. **Sem tratamento adequado de erro**
3. **Usa renderList() para view individual** (deveria ser renderizar())

### 2.3 Models - Problemas Identificados

#### AdmsAddStoreGoals.php

**Problemas:**

1. **Gera notificacoes no Model (INCORRETO)**
```php
// ❌ ATUAL - Model gerando HTML de notificacao
$_SESSION['msg'] = "<div class='alert alert-success...>Meta cadastrada com sucesso!</div>";

// ✅ ESPERADO - Model retorna apenas resultado
// Controller usa NotificationService para notificacoes
```

2. **Variaveis em PascalCase**
```php
// ❌ ATUAL
private mixed $Result;
private array|null $Datas;
private int $GoalId;

// ✅ ESPERADO
private bool $result = false;
private ?array $data = null;
private int $goalId;
```

3. **PHPDoc com @copyright year**
```php
// ❌ ATUAL
@copyright (c) year, Chirlanio Silva

// ✅ ESPERADO
@copyright (c) 2025, Grupo Meia Sola
```

#### AdmsListStoreGoals.php

**Problemas:**

1. **Query SQL com erro de sintaxe (linha 45)**
```php
// ❌ ATUAL - Falta virgula
sg.adms_store_id sg.goal_meta

// ✅ ESPERADO
sg.adms_store_id, sg.goal_meta
```

2. **Metodo listAdd() duplicado** - Existe tanto em AdmsListStoreGoals quanto AdmsAddStoreGoals

3. **Sem type hints consistentes**

### 2.4 Views - Problemas Identificados

#### loadStoreGoals.php

**Problemas:**

1. **Modais inline em vez de partials**
```php
// ❌ ATUAL - Modal no arquivo principal
<div class="modal fade" id="viewGoalsModal">...</div>
<div class="modal fade addStoreGoals" id="addStoreGoals">...</div>

// ✅ ESPERADO - Partials separados
<?php include __DIR__ . '/partials/_add_store_goals_modal.php'; ?>
<?php include __DIR__ . '/partials/_view_store_goals_modal.php'; ?>
```

2. **Data attributes para paths (padrao antigo)**
```php
// ❌ ATUAL
<span class="path" data-path="<?php echo URLADM; ?>"></span>
<span class="pathStoreGoals" data-pathGoals="..."></span>

// ✅ ESPERADO - Container com data attributes padronizados
<div id="content_store_goals"
     data-list-url="<?= URLADM ?>store-goals/list"
     data-add-url="<?= URLADM ?>add-store-goals/create">
```

3. **Exibicao de $_SESSION['msg'] direta**
```php
// ❌ ATUAL
if (isset($_SESSION['msg'])) {
    echo $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// ✅ ESPERADO - Usar NotificationService na view
<?php $this->Dados['notification'] ?? '' ?>
```

### 2.5 JavaScript - Estado Atual

#### store-goals.js (Arquivo Dedicado) ✅

O modulo agora possui arquivo JavaScript dedicado (`assets/js/store-goals.js`) com:

- ✅ Vanilla JS (sem jQuery)
- ✅ async/await para chamadas AJAX
- ✅ Event delegation para elementos dinamicos
- ✅ Funcoes separadas por responsabilidade (loadGoals, viewGoal, addGoal, etc.)

> **Nota:** O antigo `customCreate.js` ainda pode conter codigo legado que deve ser removido.

---

## 3. Comparativo com Modulo Sales (Referencia)

| Aspecto | StoreGoals (Atual) | Sales (Referencia) | Status |
|---------|-------------------|-------------------|--------|
| Match Expression | ❌ if/elseif | ✅ match() | Pendente |
| NotificationService | ✅ Parcial (Add, Import, Delete, Edit) | ✅ 100% | Parcial |
| LoggerService | ✅ Service + Add + Import | ✅ 100% | Parcial |
| Type Hints | ✅ Parcial (novos controllers) | ✅ 100% | Parcial |
| Variaveis camelCase | ✅ Parcial (novos controllers) | ✅ camelCase | Parcial |
| JavaScript Dedicado | ✅ store-goals.js | ✅ sales.js | OK |
| jQuery IIFE | ✅ jQuery | ✅ async/await | Diferente |
| Modais em Partials | ✅ 100% (7 partials) | ✅ Partials | OK |
| Cards Estatisticas | ✅ Implementado | ✅ Implementado | OK |
| Testes Unitarios | ✅ 175 testes | ✅ 113 testes | OK |
| PHPDoc Completo | ✅ Parcial (novos controllers) | ✅ Completo | Parcial |
| Redistribution Service | ✅ Completo | N/A | OK |
| Importacao em Lote | ✅ CSV/XLSX | N/A | OK |

---

## 4. Vulnerabilidades e Riscos

### 4.1 Seguranca

1. **XSS via $_SESSION['msg']**
   - HTML nao sanitizado inserido diretamente na session
   - Risco: Injecao de scripts maliciosos

2. **Falta de exit() apos redirects**
   - Codigo continua executando apos header()
   - Risco: Execucao nao intencional de codigo

3. **Falta de validacao em DeleteStoreGoal**
   - Nao verifica se usuario tem permissao para excluir
   - Risco: Exclusao nao autorizada

### 4.2 Bugs Corrigidos (Jan/2026)

1. ~~**Erro de sintaxe SQL em AdmsListStoreGoals.php**~~ ✅ Corrigido
2. ~~**storeId convertido para int**~~ ✅ Corrigido (agora string em todo o fluxo)
3. ~~**Soma de metas != meta loja (arredondamento)**~~ ✅ Corrigido (round inteiro + residual)
4. ~~**Novo funcionario nao redistribuia metas**~~ ✅ Corrigido (AdmsAddEmployee agora chama service)

### 4.3 Bugs Pendentes

1. **Logica invertida em AddStoreGoals Controller**
   - `getResult() == true` retorna `erro: true` no JSON
   - Impacto: Confusao no frontend (contornado no JS)

---

## 5. Importacao em Lote (Jan/2026)

### 5.1 Visao Geral

Funcionalidade para importar metas de multiplas lojas via arquivo CSV ou XLSX.
Utiliza PhpSpreadsheet para leitura de planilhas e dispara redistribuicao
automatica para cada meta criada.

### 5.2 Arquivos

| Arquivo | Descricao |
|---------|-----------|
| `Controllers/ImportStoreGoals.php` | Controller AJAX (import + download template) |
| `Models/AdmsImportStoreGoals.php` | Model: leitura, validacao e insercao batch |
| `Views/goals/partials/_import_store_goals_modal.php` | Modal Bootstrap com upload |

### 5.3 Rotas

| URL | Metodo | Descricao |
|-----|--------|-----------|
| `import-store-goals/import` | POST | Processa arquivo enviado via AJAX |
| `import-store-goals/download-template` | GET | Gera e serve arquivo modelo .xlsx |

### 5.4 Formato do Arquivo Modelo

| Coluna | Header | Tipo | Exemplo |
|--------|--------|------|---------|
| A | Codigo Loja | string | Z424 |
| B | Mes | int (1-12) | 2 |
| C | Ano | int (>= 2020) | 2026 |
| D | Meta (R$) | decimal | 150000.00 |
| E | Dias Uteis | int (1-31) | 26 |
| F | Feriados | int (0-10) | 1 |

- Formatos aceitos: `.xlsx`, `.xls`, `.csv`
- CSV aceita delimitador `;` ou `,` (deteccao automatica)
- Coluna Meta aceita formato brasileiro (150.000,00) ou numerico (150000.00)
- Maximo: 100 linhas por arquivo
- Template gerado programaticamente via PhpSpreadsheet (cabecalhos estilizados)

### 5.5 Fluxo de Processamento

```
1. Usuario clica "Importar Metas" (botao desktop ou dropdown mobile)
2. Modal #importGoalsModal abre com link para download do modelo
3. Usuario seleciona arquivo preenchido
4. Clica "Importar" → AJAX POST com FormData
5. Controller valida extensao e tamanho (max 5MB)
6. Model le planilha (PhpSpreadsheet para XLSX/XLS, fgetcsv para CSV)
7. Para cada linha (a partir da 2):
   a. Valida: loja existe, mes 1-12, ano >= 2020, meta > 0, dias 1-31, feriados 0-10
   b. Verifica duplicata (store + month + year)
   c. Se valida e nao duplicata: INSERT em adms_store_goals (UUID v7)
   d. Calcula super_meta = goal_meta * 1.15
   e. Dispara StoreGoalsRedistributionService::redistribute()
8. Retorna JSON com contagens (success, duplicate, error) e lista de erros por linha
9. JavaScript exibe resultado no modal e recarrega tabela
```

### 5.6 Colunas Inseridas em adms_store_goals

| Coluna | Origem |
|--------|--------|
| `ulid` | UUID v7 gerado automaticamente |
| `adms_store_id` | Coluna A (Codigo Loja) |
| `reference_month` | Coluna B (Mes) |
| `reference_year` | Coluna C (Ano) |
| `goal_meta` | Coluna D (Meta R$) |
| `super_meta` | goal_meta * 1.15 |
| `business_days` | Coluna E (Dias Uteis) |
| `non_working_days` | Coluna F (Feriados) |
| `created_at` | Timestamp UTC |

### 5.7 Validacoes

| Campo | Regra | Mensagem |
|-------|-------|----------|
| Codigo Loja | Deve existir em tb_lojas (status_id=1) | "Loja 'X' nao encontrada" |
| Mes | 1-12, inteiro | "Mes invalido: 'X'" |
| Ano | >= 2020, inteiro | "Ano invalido: 'X'" |
| Meta | > 0, numerico | "Valor da meta invalido: 'X'" |
| Dias Uteis | 1-31, inteiro | "Dias uteis invalido: 'X'" |
| Feriados | 0-10, inteiro | "Feriados invalido: 'X'" |
| Duplicata | store+month+year unico | "Meta ja existe para loja X em M/YYYY" |

### 5.8 Permissoes

- Botao controlado via `adms_nivacs_pgs` (chave `import_goals`)
- Rota: `menu_controller = 'import-store-goals'`, `menu_metodo = 'import'`
- Permissoes copiadas do `add-store-goals/create`

---

## 6. Plano de Modernizacao

### Fase 1: Correcoes Criticas (Prioridade Alta)

**Objetivo:** Corrigir bugs e vulnerabilidades

| # | Tarefa | Arquivos | Esforco |
|---|--------|----------|---------|
| 1.1 | Corrigir erro SQL em AdmsListStoreGoals | AdmsListStoreGoals.php | 5 min |
| 1.2 | Adicionar exit() apos todos os header() | Todos Controllers | 15 min |
| 1.3 | Corrigir logica invertida em AddStoreGoals | AddStoreGoals.php | 10 min |

### Fase 2: Implementar NotificationService (Prioridade Alta)

**Objetivo:** Remover $_SESSION['msg'] com HTML

| # | Tarefa | Arquivos | Esforco |
|---|--------|----------|---------|
| 2.1 | Implementar NotificationService em AddStoreGoals | AddStoreGoals.php | 30 min |
| 2.2 | Implementar NotificationService em EditStoreGoal | EditStoreGoal.php | 30 min |
| 2.3 | Implementar NotificationService em DeleteStoreGoal | DeleteStoreGoal.php | 20 min |
| 2.4 | Implementar NotificationService em ViewStoreGoals | ViewStoreGoals.php | 15 min |
| 2.5 | Remover $_SESSION['msg'] dos Models | Todos Models | 45 min |

### Fase 3: Padronizacao de Codigo (Prioridade Media)

**Objetivo:** Alinhar com padroes do projeto

| # | Tarefa | Arquivos | Esforco |
|---|--------|----------|---------|
| 3.1 | Renomear arquivos (singular -> plural) | Controllers, Models | 20 min |
| 3.2 | Converter variaveis para camelCase | Todos | 1 hora |
| 3.3 | Adicionar type hints em todos metodos | Todos | 45 min |
| 3.4 | Implementar match expression em StoreGoals | StoreGoals.php | 30 min |
| 3.5 | Atualizar PHPDoc completo | Todos | 45 min |
| 3.6 | Adicionar use statements (remover FQN inline) | Todos | 20 min |

### Fase 4: Modernizacao de Views (Prioridade Media)

**Objetivo:** Separar modais em partials

| # | Tarefa | Arquivos | Esforco |
|---|--------|----------|---------|
| 4.1 | Criar diretorio storeGoals/ | - | 5 min |
| 4.2 | Mover views para storeGoals/ | Todos Views | 15 min |
| 4.3 | Criar _add_store_goals_modal.php | Novo | 30 min |
| 4.4 | Criar _edit_store_goals_modal.php | Novo | 30 min |
| 4.5 | Criar _view_store_goals_modal.php | Novo | 30 min |
| 4.6 | Criar _delete_store_goals_modal.php | Novo | 20 min |
| 4.7 | Atualizar loadStoreGoals.php | loadStoreGoals.php | 30 min |

### Fase 5: Modernizacao JavaScript ✅ CONCLUIDA

**Objetivo:** Criar arquivo dedicado com padroes modernos

| # | Tarefa | Status |
|---|--------|--------|
| 5.1 | Criar store-goals.js | ✅ |
| 5.2 | Implementar async/await | ✅ |
| 5.3 | Implementar event delegation | ✅ |
| 5.4 | Remover codigo de customCreate.js | Pendente |

### Fase 6: Implementar LoggerService (Prioridade Media)

**Objetivo:** Adicionar auditoria completa

| # | Tarefa | Arquivos | Esforco |
|---|--------|----------|---------|
| 6.1 | Log de criacao de metas | AddStoreGoals.php | 15 min |
| 6.2 | Log de edicao de metas | EditStoreGoal.php | 15 min |
| 6.3 | Log de exclusao de metas | DeleteStoreGoal.php | 15 min |

### Fase 7: Adicionar Estatisticas (Prioridade Baixa)

**Objetivo:** Implementar cards de estatisticas

| # | Tarefa | Arquivos | Esforco |
|---|--------|----------|---------|
| 7.1 | Criar AdmsStatisticsStoreGoals.php | Novo | 2 horas |
| 7.2 | Implementar loadStats() no controller | StoreGoals.php | 30 min |
| 7.3 | Adicionar cards na view | loadStoreGoals.php | 1 hora |

### Fase 8: Testes Unitarios ✅ CONCLUIDA

**Objetivo:** Criar suite de testes

| # | Tarefa | Status |
|---|--------|--------|
| 8.1 | StoreGoalsRedistributionServiceTest.php | ✅ 175 testes |
| 8.2 | WeightCalculationTest.php | ✅ |
| 8.3 | GoalDistributionTest.php | ✅ |
| 8.4 | EffectiveWorkingDaysTest.php | ✅ |
| 8.5 | MedicalLeaveRulesTest.php | ✅ |
| 8.6 | AffectedMonthsTest.php | ✅ |
| 8.7 | Models e Integration tests | ✅ |

---

## 7. Estimativa Total

| Fase | Descricao | Esforco Estimado |
|------|-----------|------------------|
| 1 | Correcoes Criticas | 30 min |
| 2 | NotificationService | 2.5 horas |
| 3 | Padronizacao de Codigo | 3.5 horas |
| 4 | Modernizacao Views | 2.5 horas |
| 5 | Modernizacao JavaScript | 2.5 horas |
| 6 | LoggerService | 45 min |
| 7 | Estatisticas | 3.5 horas |
| 8 | Testes Unitarios | 6 horas |
| **TOTAL** | | **~21 horas** |

---

## 8. Ordem de Execucao Recomendada

1. **Imediato:** Fase 1 (Correcoes Criticas)
2. **Curto Prazo:** Fases 2 e 3 (NotificationService + Padronizacao)
3. **Medio Prazo:** Fases 4, 5 e 6 (Views + JS + Logger)
4. **Longo Prazo:** Fases 7 e 8 (Estatisticas + Testes)

---

## 9. Dependencias

- NotificationService ja implementado no projeto ✅
- LoggerService ja implementado no projeto ✅
- PhpSpreadsheet (^5.3) para leitura/geracao de XLSX ✅
- Modulo Sales como referencia ✅
- PHPUnit configurado para testes ✅

---

## 10. Metricas de Sucesso

Progresso da refatoracao:

- [ ] 0 referencias a $_SESSION['msg'] com HTML (pendente)
- [ ] 100% type hints em metodos publicos (pendente)
- [ ] 100% NotificationService em controllers (pendente - parcial nos novos)
- [x] LoggerService no redistribution service ✅
- [x] LoggerService em AddStoreGoals e ImportStoreGoals ✅
- [x] JavaScript dedicado (store-goals.js) ✅
- [x] Modais em partials separados ✅ (add, edit, view, delete, confirm, import)
- [x] Cards de estatisticas funcionando ✅
- [x] Suite de testes (175 testes, redistribuicao) ✅
- [x] Redistribuicao automatica completa ✅
- [x] storeId como string em todo o fluxo ✅
- [x] Ajuste proporcional de feriados ✅
- [x] Deducao de treinamento (3 dias) ✅
- [x] Filtro de atestados >= 10 dias ✅
- [x] Importacao em lote (CSV/XLSX) com validacao ✅
- [x] Download de template modelo (.xlsx) ✅
- [x] Confirmacao de vendas por consultora ✅
- [x] Exclusao via modal AJAX com confirmacao ✅
- [x] Impressao de meta detalhada ✅

---

**Documento criado em:** 21/01/2026
**Ultima atualizacao:** 24/01/2026
**Proxima revisao:** Apos conclusao das Fases 2 e 3 (NotificationService + Padronizacao)
