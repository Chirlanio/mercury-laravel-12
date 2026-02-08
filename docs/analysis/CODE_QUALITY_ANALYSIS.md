# Mercury Project - Code Quality Analysis Report

## Executive Summary

The Mercury project is a large legacy PHP application with **137,579 lines of code** across **426 views**, **542 models**, and **498 controllers**. The codebase exhibits multiple architectural and code quality antipatterns typical of monolithic applications that have grown organically without proper structure or design patterns.

### Critical Metrics
- **Total PHP Files**: 1,466
- **Total Lines of Code**: 137,579
- **Largest Model File**: 657 lines
- **Extract() Usage**: 319 files (22%)
- **Direct SESSION Access**: 2,472 instances
- **Hardcoded HTML**: 1,476 alert strings
- **Try/Catch Blocks**: Only 84 across entire codebase
- **Return Type Hints**: Only 24 in controllers
- **Error Handling**: <1% of codebase

---

## 1. CODE DUPLICATION (VERY HIGH SEVERITY)

### 1.1 Massive Duplication Pattern: Apagar/Add Controllers and Models
**Issue**: For almost every entity in the system, there's a dedicated controller and model file following the same pattern.

**Examples**:
- 50+ `Apagar*.php` (delete) models - **2,184 total lines**
- 40+ `Add*.php` models - similar duplication
- Every delete operation follows identical pattern

**Location**: `/home/user/mercury/app/adms/Models/AdmsApagar*.php`

**Code Example**:
```php
// AdmsApagarFunc.php
class AdmsApagarFunc {
    private $DadosId;
    private $Resultado;
    
    public function apagarFunc($DadosId = null) {
        $this->DadosId = (int) $DadosId;
        $apagarFunc = new \App\adms\Models\helper\AdmsDelete();
        $apagarFunc->exeDelete("tb_funcionarios", "WHERE id =:id", "id={$this->DadosId}");
        if ($apagarFunc->getResult()) {
            $_SESSION['msg'] = "<div class='alert alert-success'>Cadastro apagado com sucesso!</div>";
            $this->Resultado = true;
        } else {
            $_SESSION['msg'] = "<div class='alert alert-danger'>Erro: O cadastro não foi apagado!</div>";
            $this->Resultado = false;
        }
    }
}

// AdmsApagarBairro.php - identical structure
class AdmsApagarBairro {
    private $DadosId;
    private $Resultado;
    // ... identical implementation
}
```

**Refactoring Impact**: HIGH
- Could reduce 50+ delete models to 1 generic repository
- Could reduce 40+ add models to 1 generic service

---

## 2. VIOLATION OF SOLID PRINCIPLES

### 2.1 Single Responsibility Principle (SRP) - VIOLATED

**God Objects**:
- **AdmsAddRelocation.php**: 657 lines - Handles creation, validation, image upload, contract insertion, and multiple database operations
- **AdmsDeliveryRouting.php**: 639 lines - Mixing route calculation, database operations, and formatting
- **AdmsEditRelocation.php**: 547 lines - Edit operations mixing multiple concerns

**Example from AdmsAddEmployee.php**:
```php
// Model handling: data validation, image processing, database operations, session management
public function addEmployee(array $Dados) {
    // Validation
    $valCampoVazio->validarDados($this->Dados);
    // Image processing
    $this->validateImage();
    // Database insertion
    $cadFunc->exeCreate("adms_employees", $this->Dados);
    // Contract insertion
    $this->insertContract();
    // Session manipulation
    $_SESSION['msg'] = "...";
}
```

**Impact**: Each model should handle ONE responsibility

### 2.2 Open/Closed Principle (OCP) - VIOLATED

**Issue**: System requires code modification for every new entity type (add, edit, delete operations)

**No abstraction** for common patterns:
- Each delete operation creates a new class
- Each add operation creates a new class
- No factory pattern or strategy pattern

### 2.3 Liskov Substitution Principle (LSP) - VIOLATED

**Issue**: No inheritance hierarchy or interfaces defined
- 0 classes implementing interfaces
- 0 classes using inheritance (except `AdmsConn` base class)
- Cannot substitute implementations

### 2.4 Interface Segregation Principle (ISP) - VIOLATED

**Issue**: Large monolithic data structures passed between layers
```php
$this->Dados = filter_input_array(INPUT_POST, FILTER_DEFAULT); // Entire POST array
```

### 2.5 Dependency Inversion Principle (DIP) - SEVERELY VIOLATED

**Tight Coupling Example**:
```php
// Controllers instantiate concrete classes directly
$addEmployee = new \App\adms\Models\AdmsAddEmployee();
$listSelect = new \App\adms\Models\AdmsAddEmployee();
$listButtons = new \App\adms\Models\AdmsBotao();
$listarMenu = new \App\adms\Models\AdmsMenu();

// No dependency injection, no service locator, no IoC container
```

**Locations**: Every controller file instantiates multiple concrete dependencies

---

## 3. MIXED CONCERNS (BUSINESS LOGIC IN VIEWS)

### 3.1 Views Accessing SESSION Directly
**Files Affected**: 393 view files
**Occurrences**: 2,472 direct SESSION['msg'] accesses

**Example** - `/home/user/mercury/app/adms/Views/usuario/cadUsuario.php`:
```php
<?php
if (isset($_SESSION['msg'])) {
    echo $_SESSION['msg'];
    unset($_SESSION['msg']);  // Business logic in view!
}
?>
```

**Related**: `/home/user/mercury/app/adms/Views/home/home.php`:
```php
<?php
if ($_SESSION['adms_niveis_acesso_id'] == 5) {  // Authorization in view!
    $nomeGerente = $_SESSION['nome_gerente'] ?? '';
    if (!empty($nomeGerente)) {
        $nome = explode(" ", $nomeGerente);
        $prim_nome = !empty($nome[0]) ? $nome[0] : '';
    }
}
?>
```

### 3.2 Extract() Function Usage
**Files Affected**: 319 files (22% of codebase)
**Major Anti-Pattern**: Creates unpredictable variable scope

**Location**: `/home/user/mercury/app/adms/Views/home/home.php` line 6:
```php
extract($this->Dados['select']);

// Now undefined variables created:
$permissions = ...;
$canViewKpis = ...;
$canViewOperations = ...;
```

**Risks**:
- Variable collision and overwriting
- Impossible to track variable origins
- Makes code unmaintainable and hard to debug
- Security risk with user input

### 3.3 Direct SQL in Models
**Occurrences**: 1,446 SELECT statements in models
**No Query Builder**: Using raw SQL with string concatenation

**Example** - AdmsAddMaterialRequest.php:
```php
$list->fullRead("SELECT id AS l_id, nome AS store_name FROM tb_lojas l 
    WHERE id NOT IN('Z999','Z441','Z442', 'Z443', 'Z457', 'Z500') 
    ORDER BY l.nome ASC");
```

---

## 4. MISSING ERROR HANDLING

### 4.1 Insufficient Try/Catch Blocks
- **Total Try/Catch Blocks**: 84
- **Total Locations**: 31 files
- **Coverage**: <1% of codebase

**Example** - Missing error handling in AdmsConn.php:
```php
protected function connectDb(): object {
    try {
        $this->connect = new PDO("mysql:host={$this->host};dbname=" . $this->dbname, $this->user, $this->pass);
        return $this->connect;
    } catch (PDOException $err) {
        die("Erro - 001: ..."); // Killing execution in database class!
    }
}
```

### 4.2 Fatal Errors with die()/exit()
- **Header/Die/Exit Calls**: 1,633 occurrences across 497 controller files
- **In Models**: Direct session management and HTML generation
- **In Views**: extract() and business logic

**Issues**:
- Kills execution abruptly
- Makes testing impossible
- No graceful error handling
- Cannot be caught or logged properly

---

## 5. MISSING TYPE DECLARATIONS

### 5.1 Lack of Type Hints

**Controller Functions**: 24 return type hints across entire codebase
**Model Properties**: 2,082 type hints (mostly added recently)
**Function Parameters**: Minimal type declarations

**Example** - AddEmployee.php:
```php
public function create() {  // No return type
    $this->Dados = filter_input_array(INPUT_POST, FILTER_DEFAULT);  // $Dados untyped
    
    $addEmployee = new \App\adms\Models\AdmsAddEmployee();
    $addEmployee->addEmployee($this->Dados);  // Parameter type not enforced
}
```

**Better Example** - AdmsAddEmployee.php:
```php
private array|object|null $UserImage;  // Union type
private string $DataUser;
public function addEmployee(array $Dados) {  // Parameter typed
    // ...
}
```

---

## 6. LONG METHODS (COMPLEXITY)

### 6.1 Methods Exceeding 50 Lines

**Largest Models**:
1. **AdmsAddRelocation.php**: 657 lines
2. **AdmsDeliveryRouting.php**: 639 lines
3. **AdmsEditRelocation.php**: 547 lines
4. **AdmsHome.php**: 427 lines
5. **AdmsAddPersonnelMoviments.php**: 378 lines

**Example** - AdmsAddEmployee::insertEmployee() ~26 lines:
```php
private function insertEmployee() {
    $this->Dados['cupom_site'] = strtoupper($this->Cupom);
    $this->Dados['date_dismissal'] = $this->Dismissal;
    $this->Dados['created_at'] = date("Y-m-d H:i:s");
    // ... more data manipulation
    $cadFunc = new \App\adms\Models\helper\AdmsCreate();
    $cadFunc->exeCreate("adms_employees", $this->Dados);
    // ... more database calls
    if ($cadFunc->getResult()) {
        $this->insertContract();
    }
}
```

---

## 7. HARDCODED VALUES AND MAGIC STRINGS

### 7.1 Hardcoded HTML Alert Strings
- **Occurrences**: 1,476 throughout codebase
- **Pattern**: Hardcoded Bootstrap alert divs

**Example** - AdmsApagarFunc.php:
```php
$_SESSION['msg'] = "<div class='alert alert-success'>Cadastro apagado com sucesso!</div>";
$_SESSION['msg'] = "<div class='alert alert-danger'>Erro: O cadastro não foi apagado!</div>";
```

**Repeated in 1,476 locations** - Creates maintenance nightmare

### 7.2 Hardcoded Table Names
**Example** - Models hardcode table names:
```php
$apagarBairro->exeDelete("tb_bairros", "WHERE id =:id", "id={$this->DadosId}");
```

No centralized table name definitions or ORM mapping.

### 7.3 Magic Numbers
- User level checks: `if ($_SESSION['adms_niveis_acesso_id'] == 5)`
- Status codes scattered throughout
- Pagination limits hardcoded

---

## 8. TIGHT COUPLING AND INSTANTIATION PATTERNS

### 8.1 Direct Class Instantiation

**Every controller creates multiple concrete instances**:
```php
// From AddEmployee.php
$this->Dados = filter_input_array(INPUT_POST, FILTER_DEFAULT);
$this->Dados['type'] = (!isset($this->Dados['type']) ? 1 : $this->Dados['type']);

$addEmployee = new \App\adms\Models\AdmsAddEmployee();
$addEmployee->addEmployee($this->Dados);

$listSelect = new \App\adms\Models\AdmsAddEmployee();
$this->Dados['select'] = $listSelect->listAdd();

$buttons = ['list_employee' => ['menu_controller' => 'employees', 'menu_metodo' => 'list']];
$listButtons = new \App\adms\Models\AdmsBotao();
$this->Dados['botao'] = $listButtons->valBotao($buttons);

$listarMenu = new \App\adms\Models\AdmsMenu();
$this->Dados['menu'] = $listarMenu->itemMenu();
```

**Problems**:
- Cannot swap implementations
- Cannot mock for testing
- Dependencies hidden from view
- Makes refactoring extremely difficult

---

## 9. POOR NAMING CONVENTIONS

### 9.1 Portuguese Mixed with English
- Portuguese class names: `AdmsApagarFunc`, `AdmsApagarBairro`
- Portuguese method names: `apagarArquivo`, `cadFunc`
- English method names: `create`, `addEmployee`

### 9.2 Unclear/Abbreviated Names
- `Dados` instead of `Data` or `DataArray`
- `DadosId` instead of `EntityId`
- `listAdd` instead of `getSelectOptions`
- `Resultado` instead of `Result` or `Success`
- `Cupom` instead of `Coupon`

### 9.3 Inconsistent Naming
- Some tables: `tb_` prefix (legacy), some without
- Some columns: `id`, some: `s_id`, `l_id`, `m_id`
- Some methods: `get*`, some: `list*`, some: `exeRead`

---

## 10. VIEWS USING GLOBAL STATE

### 10.1 Views Extract Variables
**File**: `/home/user/mercury/app/adms/Views/home/home.php` line 6:
```php
extract($this->Dados['select']);
```

Creates implicit variables in view scope with no documentation.

### 10.2 Direct Session Access
**All 393 view files** access SESSION directly:
```php
$_SESSION['adms_niveis_acesso_id']
$_SESSION['nome_gerente']
$_SESSION['usuario_nome']
$_SESSION['usuario_loja']
```

### 10.3 Authorization Logic in Views
Authorization checks scattered in views instead of controller:
```php
if ($_SESSION['adms_niveis_acesso_id'] == 5) {
    // Render admin content
}
```

---

## 11. DATABASE ARCHITECTURE ISSUES

### 11.1 No Data Access Layer
- Models directly instantiate helper classes
- Raw SQL concatenation
- No ORM or query builder
- No transaction management

**Example** - Direct SQL in models:
```php
$viewUser->fullRead("SELECT id, name_employee FROM adms_employees WHERE doc_cpf =:cpf", "cpf={$this->DataUser}");
```

### 11.2 Database Helper Classes
**Location**: `/home/user/mercury/app/adms/Models/helper/`

**Classes**:
- `AdmsRead` - Basic SELECT operations
- `AdmsCreate` - INSERT operations
- `AdmsUpdate` - UPDATE operations
- `AdmsDelete` - DELETE operations
- `AdmsConn` - Database connection

**Issues**:
- No connection pooling
- No prepared statement caching
- No transaction support
- Die on error (cannot be caught)

---

## 12. SERVICES LAYER (INADEQUATE)

### 12.1 Services Exist But Are Stub Implementations

**Location**: `/home/user/mercury/app/adms/Services/`

**Files**:
- `AuthenticationService.php` - 31 lines, all stub methods
- `ExportService.php` - Minimal implementation
- `ImportService.php` - Minimal implementation
- `FormSelectRepository.php`
- `LoggerService.php`
- `NotificationService.php`
- `PermissionService.php`
- `StatisticsService.php`

**Example** - AuthenticationService.php:
```php
class AuthenticationService {
    public function login(string $username, string $password): bool {
        // Logic from AdmsLogin would go here
        return false;
    }

    public function logout(): void {
        // Logic to destroy session would go here
    }
}
```

Services are declared but not actually used - actual logic remains in models.

---

## 13. MISSING INTERFACES

### 13.1 Zero Interface Usage
- No Repository interfaces
- No Service interfaces
- No Factory interfaces
- Cannot swap implementations

---

## SUMMARY TABLE: ANTIPATTERNS FOUND

| Antipattern | Severity | Count/Files | Impact |
|---|---|---|---|
| Code Duplication (Apagar/Add) | CRITICAL | 90+ files | 2,184 duplicate lines |
| Direct Session Access | CRITICAL | 2,472 | All business logic exposed |
| extract() Usage | CRITICAL | 319 files | Security & maintainability |
| Missing Error Handling | CRITICAL | <1% | Production crashes |
| No Type Hints | HIGH | 95%+ functions | IDE support, refactoring |
| Hardcoded HTML | HIGH | 1,476 | Maintenance nightmare |
| Tight Coupling | HIGH | 498 controllers | Impossible to test |
| Direct SQL in Models | HIGH | 1,446 | SQL injection risk |
| Long Methods | HIGH | 20+ files >300 lines | Complexity, testing |
| Mixed Concerns | HIGH | All layers | SRP violated |
| No Interfaces | MEDIUM | 100% | No abstraction |
| Portuguese Names | MEDIUM | ~50% of code | Consistency |
| Magic Numbers | MEDIUM | ~100+ locations | Maintainability |
| God Objects | MEDIUM | 10+ models | SRP violated |
| Views with Logic | HIGH | 393 files | Testability |

---

## PRIORITY CLASSIFICATION

### HIGH PRIORITY (Fix Immediately)
1. **Remove extract() from 319 files** - Security risk
2. **Implement try/catch properly** - Application crashes
3. **Remove direct SESSION access from models** - Decouple layers
4. **Create Repository pattern** - Replace 90+ duplicate classes
5. **Add return type hints** - Enable IDE support

### MEDIUM PRIORITY (Fix Within Sprint)
6. **Create Service layer** - Decouple business logic
7. **Implement dependency injection** - Enable testing
8. **Centralize alert messages** - Enable i18n
9. **Create interfaces** - Enable polymorphism
10. **Refactor long methods** - Improve maintainability

### LOW PRIORITY (Refactor Over Time)
11. **Add unit tests** - Coverage currently 0%
12. **Unify naming conventions** - Portuguese/English mix
13. **Create constants** - Magic numbers
14. **Documentation** - No architectural docs
15. **Performance optimization** - SELECT * queries

---

## REFACTORING ROADMAP

### Phase 1: Foundation (1-2 weeks)
- [ ] Remove extract() from all files
- [ ] Add return types to all functions
- [ ] Create base Repository class
- [ ] Implement proper exception handling

### Phase 2: Architecture (2-3 weeks)
- [ ] Create Repository interfaces
- [ ] Implement Service layer properly
- [ ] Remove direct model instantiation
- [ ] Add dependency injection container

### Phase 3: Testing (2-3 weeks)
- [ ] Create unit test infrastructure
- [ ] Test repositories first
- [ ] Test services
- [ ] Integration tests

### Phase 4: Refactoring (4+ weeks)
- [ ] Refactor long methods (<100 lines each)
- [ ] Unify naming conventions
- [ ] Remove hardcoded strings
- [ ] Database query optimization

---

## RECOMMENDATIONS

### Immediate Actions
1. **Add PHPStan static analysis** - Catch type errors
2. **Add PHP CodeSniffer** - Enforce style
3. **Add SonarQube** - Code quality metrics
4. **Create .editorconfig** - Consistent formatting
5. **Add pre-commit hooks** - Prevent bad code

### Short-term (1 month)
1. Create base `Repository` class
2. Create base `Service` class
3. Remove all `extract()` calls
4. Add return type hints systematically
5. Centralize alert message generation

### Medium-term (3 months)
1. Implement full repository pattern
2. Implement proper service layer
3. Add unit tests (target 50% coverage)
4. Create interfaces for all services
5. Implement dependency injection

### Long-term (6+ months)
1. Consider migrating to framework (Laravel, Symfony)
2. Implement CQRS pattern for complex operations
3. Add event system for decoupling
4. Create API layer for frontend
5. Achieve 80%+ test coverage

---

## CONCLUSION

The Mercury project has **critical code quality issues** that pose **security risks** (extract(), direct SQL), **maintainability challenges** (extreme duplication), and **testing impossibility** (tight coupling, global state). 

**The codebase is unmaintainable in its current state** and requires substantial refactoring. The most pressing issues are removing `extract()`, eliminating duplicate code, and implementing proper separation of concerns through a repository and service pattern.

Estimated refactoring effort: **3-6 months** of dedicated work with experienced team.

