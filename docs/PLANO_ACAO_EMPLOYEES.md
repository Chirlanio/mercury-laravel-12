# Plano de Acao: Modernizacao do Modulo de Funcionarios (Employees)

**Referencia:** `docs/ANALISE_INTEGRACAO_RH_MODULES.md` v1.0
**Modelo:** Ordem de Pagamento (OrderPayments)
**Data:** 2026-04-02
**Ultima Atualizacao:** 2026-04-02
**Projeto:** Mercury

---

## Visao Geral

Modernizar o modulo de Funcionarios para o nivel de qualidade do modulo de Ordem de Pagamento (referencia), adicionando services dedicados, state machine de status, soft-delete com auditoria, dashboard com graficos, relatorios e integracao com o modulo de Abertura de Vagas.

### Alinhamento Atual: 65% → Meta: 90%+

### Integracoes Principais

| Modulo | Integracao | Impacto |
|---|---|---|
| **Abertura de Vagas** | Pre-cadastro de colaborador a partir de vaga finalizada; dados herdados: loja, cargo, escala, data admissao | Receptor |
| **Movimento de Pessoal** | Desativacao/reativacao automatica de status via `EmployeeInactivationService` | Bidirecional |
| **Metas por Lojas** | Redistribuicao automatica de metas via `StoreGoalsRedistributionService` (ja implementado) | Automatica |
| **Ferias** | Calculo de periodos aquisitivos, atualizacao de status durante gozo | Bidirecional |

---

### Status das Fases

| Fase | Status | Esforco | Data Conclusao |
|---|---|---|---|
| **Fase 1:** Service Layer + State Machine | Pendente | 20h | — |
| **Fase 2:** Soft-Delete + Status History | Pendente | 16h | — |
| **Fase 3:** Seguranca JavaScript | Pendente | 6h | — |
| **Fase 4:** Dashboard + Relatorios | Pendente | 24h | — |
| **Fase 5:** Testes Completos | Pendente | 16h | — |
| **Fase 6:** Integracao (receptor de vagas) | Pendente | 12h | — |

**Esforco total estimado: 94h**

---

## Diagnostico Atual

### Arquivos Existentes

**Controllers (8):**
- `app/adms/Controllers/Employees.php` — Listagem principal (match expressions)
- `app/adms/Controllers/AddEmployee.php` — Criacao (AJAX + JSON)
- `app/adms/Controllers/EditEmployee.php` — Edicao (modal + full-page)
- `app/adms/Controllers/DeleteEmployee.php` — Exclusao (hard-delete)
- `app/adms/Controllers/ViewEmployee.php` — Visualizacao (contratos, escalas, ferias)
- `app/adms/Controllers/ExportEmployee.php` — Export CSV/Excel
- `app/adms/Controllers/EmployeeScheduleOverride.php` — CRUD de overrides de escala
- `app/adms/Controllers/Api/V1/EmployeesController.php` — API REST (7 endpoints)

**Models (10):**
- `app/adms/Models/AdmsAddEmployee.php` (568 LOC)
- `app/adms/Models/AdmsEditEmployee.php` (801 LOC)
- `app/adms/Models/AdmsDeleteEmployee.php` (390 LOC)
- `app/adms/Models/AdmsListEmployee.php`
- `app/adms/Models/AdmsViewEmployee.php`
- `app/adms/Models/AdmsExportEmployee.php`
- `app/adms/Models/AdmsEmployeeScheduleOverride.php`
- `app/adms/Models/AdmsStatisticsEmployees.php`

**Views:** `app/adms/Views/employee/` — 5 principais + 14 partials
**JavaScript:** `assets/js/employees.js` (2.239 LOC)
**Testes:** 6+ arquivos em `tests/Employees/`

### Pontos Fortes

- CRUD completo com transacoes atomicas (beginTransaction/commit/rollback)
- API REST completa com 7 endpoints e JWT
- Validacao CPF (mod-11), mascaras de telefone, integracao ViaCEP
- Upload de foto com redimensionamento 150x150
- Gestao de contratos e escalas de trabalho com overrides
- Redistribuicao automatica de metas (StoreGoalsRedistributionService)
- Match expressions no controller principal

### Gaps vs OrderPayments

| Gap | Impacto | Fase |
|-----|---------|------|
| Sem services dedicados (801 LOC no AdmsEditEmployee) | Critico | 1 |
| Sem state machine (status muda sem validacao) | Critico | 1 |
| Sem status history table | Alto | 2 |
| Hard-delete sem auditoria | Alto | 2 |
| Sem `escapeHtml()` no JS | Alto | 3 |
| Sem `fetchWithTimeout()` no JS | Medio | 3 |
| Sem CSRF auto-refresh on 403 | Medio | 3 |
| Sem dashboard/graficos | Medio | 4 |
| Sem relatorios dedicados | Medio | 4 |
| Testes insuficientes (~20 vs 343+) | Alto | 5 |

---

## Fase 1: Service Layer + State Machine (20h)

**Dependencia:** Nenhuma

### 1.1 Criar `EmployeeStatus.php` (Constants)

**Arquivo:** `app/adms/Models/constants/EmployeeStatus.php`

```php
<?php
namespace App\adms\Models\constants;

class EmployeeStatus
{
    const PENDING  = 1;
    const ACTIVE   = 2;
    const INACTIVE = 3;
    const VACATION = 4;
    const LEAVE    = 5;

    const LABELS = [
        self::PENDING  => 'Pendente',
        self::ACTIVE   => 'Ativo',
        self::INACTIVE => 'Inativo',
        self::VACATION => 'Ferias',
        self::LEAVE    => 'Afastado',
    ];

    const TRANSITIONS = [
        self::PENDING  => [self::ACTIVE, self::INACTIVE],
        self::ACTIVE   => [self::INACTIVE, self::VACATION, self::LEAVE],
        self::INACTIVE => [self::ACTIVE],
        self::VACATION => [self::ACTIVE],
        self::LEAVE    => [self::ACTIVE, self::INACTIVE],
    ];

    const REQUIRED_FIELDS = [
        '1_2' => ['date_admission', 'position_id', 'adms_store_id'],
        '2_3' => ['date_dismissal'],
        '3_2' => ['date_admission'],
    ];
}
```

### 1.2 Criar `EmployeeLifecycleService.php`

**Arquivo:** `app/adms/Services/EmployeeLifecycleService.php`

Seguindo padrao `OrderPaymentTransitionService`:

```php
<?php
namespace App\adms\Services;

use App\adms\Models\constants\EmployeeStatus;
use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsCreate;
use App\adms\Models\helper\AdmsUpdate;

class EmployeeLifecycleService
{
    /**
     * Valida se a transicao de status e permitida
     * @return array {valid: bool, errors: string[]}
     */
    public function validateTransition(int $fromStatus, int $toStatus, array $data): array
    {
        // 1. Verificar se transicao existe no mapa TRANSITIONS
        // 2. Validar campos obrigatorios (REQUIRED_FIELDS[$from_$to])
        // 3. Retornar {valid, errors[]}
    }

    /**
     * Executa transicao de status com auditoria
     */
    public function executeTransition(
        int $employeeId,
        int $fromStatus,
        int $toStatus,
        array $fields,
        int $userId,
        ?string $notes = null
    ): bool {
        // 1. UPDATE adms_employees SET adms_status_employee_id = :toStatus
        // 2. Gravar historico via recordStatusHistory()
        // 3. LoggerService::info('EMPLOYEE_STATUS_CHANGED', ...)
    }

    /**
     * Registra transicao na tabela de historico
     */
    public function recordStatusHistory(
        int $employeeId,
        ?int $fromStatus,
        int $toStatus,
        int $userId,
        ?string $notes = null
    ): void {
        // INSERT INTO adms_employee_status_history
    }

    /**
     * Carrega historico de transicoes com nomes de usuarios e status
     */
    public function getStatusHistory(int $employeeId): ?array
    {
        // SELECT com JOINs para user name e status name
        // ORDER BY created_at DESC
    }

    /**
     * Retorna proximos status validos a partir do atual
     */
    public function getAllowedTransitions(int $currentStatus): array
    {
        return EmployeeStatus::TRANSITIONS[$currentStatus] ?? [];
    }
}
```

### 1.3 Criar `EmployeeContractService.php`

**Arquivo:** `app/adms/Services/EmployeeContractService.php`

Extrair logica de contratos que esta inline em `AdmsAddEmployee` e `AdmsEditEmployee`:

```php
<?php
namespace App\adms\Services;

class EmployeeContractService
{
    public function createContract(int $employeeId, array $contractData): bool { /* ... */ }
    public function updateContract(int $contractId, array $contractData): bool { /* ... */ }
    public function deleteContract(int $contractId): bool { /* ... */ }
    public function getActiveContract(int $employeeId): ?array { /* ... */ }
    public function getContractHistory(int $employeeId): ?array { /* ... */ }
    public function assignWorkSchedule(int $employeeId, int $scheduleId, string $effectiveDate): bool { /* ... */ }
    public function closeCurrentSchedule(int $employeeId, string $endDate): bool { /* ... */ }
}
```

### 1.4 Refatorar Models

**`AdmsAddEmployee.php` — Alteracoes:**
- Extrair `insertContract()` → `EmployeeContractService::createContract()`
- Extrair `insertWorkScheduleAssignment()` → `EmployeeContractService::assignWorkSchedule()`
- Usar `EmployeeLifecycleService::recordStatusHistory()` apos criacao

**`AdmsEditEmployee.php` — Alteracoes:**
- Extrair logica de schedule change → `EmployeeContractService::closeCurrentSchedule()` + `assignWorkSchedule()`
- Usar `EmployeeLifecycleService::validateTransition()` ao mudar status
- Extrair logica de comparacao de dados antigos para service

### Checklist Fase 1

- [ ] Criar `app/adms/Models/constants/EmployeeStatus.php`
- [ ] Criar `app/adms/Services/EmployeeLifecycleService.php`
- [ ] Criar `app/adms/Services/EmployeeContractService.php`
- [ ] Refatorar `AdmsAddEmployee.php` (extrair contrato + schedule)
- [ ] Refatorar `AdmsEditEmployee.php` (extrair contrato + validar transicao)
- [ ] Testar CRUD completo apos refatoracao
- [ ] Verificar API REST continua funcionando

---

## Fase 2: Soft-Delete + Status History (16h)

**Dependencia:** Fase 1

### 2.1 Migration SQL

**Arquivo:** `database/migrations/2026_04_employees_modernization.sql`

```sql
-- =============================================
-- Migration: Modernizacao Modulo Funcionarios
-- Data: 2026-04-XX
-- Collation: utf8mb4_unicode_ci (OBRIGATORIO)
-- =============================================

-- Tabela de historico de status (padrao OrderPayments)
CREATE TABLE adms_employee_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adms_employee_id INT NOT NULL,
    old_status_id INT NULL,
    new_status_id INT NOT NULL,
    changed_by_user_id INT NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee_history (adms_employee_id),
    INDEX idx_changed_by (changed_by_user_id),
    CONSTRAINT fk_esh_employee FOREIGN KEY (adms_employee_id)
        REFERENCES adms_employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_esh_user FOREIGN KEY (changed_by_user_id)
        REFERENCES adms_usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campos de soft-delete e integracao
ALTER TABLE adms_employees
    ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN deleted_by_user_id INT NULL DEFAULT NULL,
    ADD COLUMN deleted_reason TEXT NULL DEFAULT NULL,
    ADD COLUMN origin_vacancy_id INT NULL DEFAULT NULL
        COMMENT 'Vaga que originou a contratacao',
    ADD INDEX idx_employee_deleted (deleted_at),
    ADD INDEX idx_origin_vacancy (origin_vacancy_id);
```

### 2.2 Criar `EmployeeDeleteService.php`

**Arquivo:** `app/adms/Services/EmployeeDeleteService.php`

Seguindo padrao `OrderPaymentDeleteService` com 3 niveis:

```php
<?php
namespace App\adms\Services;

class EmployeeDeleteService
{
    /**
     * Verifica se o usuario pode deletar o funcionario
     *
     * Nivel 1: Pendente + criador + sem edicoes → sem motivo
     * Nivel 2: Pendente/Ativo + nivel <= 5 → com motivo
     * Nivel 3: Qualquer status + Super Admin → com motivo + confirmacao
     *
     * @return array {allowed, requireReason, requireConfirmation, message, level}
     */
    public function canDelete(array $employee, int $userId, int $userLevel): array
    {
        // Nivel 1: rascunho do proprio criador
        if ($employee['adms_status_employee_id'] == 1
            && $employee['created_by'] == $userId
            && empty($employee['modified_at'])) {
            return [
                'allowed' => true,
                'requireReason' => false,
                'requireConfirmation' => false,
                'level' => 1,
            ];
        }

        // Nivel 2: gestor/financeiro
        if (in_array($employee['adms_status_employee_id'], [1, 2]) && $userLevel <= 5) {
            return [
                'allowed' => true,
                'requireReason' => true,
                'requireConfirmation' => false,
                'level' => 2,
            ];
        }

        // Nivel 3: super admin
        if ($userLevel === 1) {
            return [
                'allowed' => true,
                'requireReason' => true,
                'requireConfirmation' => true,
                'level' => 3,
            ];
        }

        return ['allowed' => false, 'message' => 'Sem permissao para excluir este funcionario.'];
    }
}
```

### 2.3 Refatorar `AdmsDeleteEmployee.php`

**Alteracoes:**
- Substituir hard-delete por soft-delete: `UPDATE SET deleted_at, deleted_by_user_id, deleted_reason`
- Delegar verificacao de permissao para `EmployeeDeleteService::canDelete()`
- Manter cascata de contratos como soft-delete tambem
- Nao deletar foto fisicamente (manter para restauracao)

### 2.4 Atualizar queries de listagem

Adicionar `AND e.deleted_at IS NULL` em:
- `AdmsListEmployee.php`
- `AdmsStatisticsEmployees.php`
- `AdmsExportEmployee.php`
- `AdmsViewEmployee.php`
- `FormSelectRepository::getEmployees()`

### Checklist Fase 2

- [ ] Executar migration SQL
- [ ] Criar `app/adms/Services/EmployeeDeleteService.php`
- [ ] Refatorar `AdmsDeleteEmployee.php` para soft-delete
- [ ] Adicionar `deleted_at IS NULL` em todas as queries SELECT
- [ ] Atualizar `FormSelectRepository::getEmployees()` para excluir deletados
- [ ] Adicionar metodo `restore()` no controller (Super Admin only)
- [ ] Testar delete + restore + listagem

---

## Fase 3: Seguranca JavaScript (6h)

**Dependencia:** Nenhuma (pode executar em paralelo com Fases 1-2)

### 3.1 Adicionar funcoes utilitarias em `employees.js`

```javascript
// XSS prevention (padrao OrderPayments)
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Fetch com timeout (padrao OrderPayments)
async function fetchWithTimeout(url, options = {}, timeoutMs = 15000) {
    const controller = new AbortController();
    const id = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const response = await fetch(url, { ...options, signal: controller.signal });
        clearTimeout(id);
        return response;
    } catch (err) {
        clearTimeout(id);
        throw err;
    }
}

// CSRF auto-refresh on 403 (padrao OrderPayments)
async function refreshCsrfToken() {
    try {
        const response = await fetch(URLADM + 'csrf-refresh', { method: 'GET' });
        if (response.ok) {
            const data = await response.json();
            document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                input.value = data.token;
            });
        }
    } catch (e) {
        console.error('Erro ao renovar CSRF token:', e);
    }
}
```

### 3.2 Aplicar escapeHtml() em innerHTML dinamico

Revisar todos os pontos em `employees.js` onde dados do servidor sao inseridos via `innerHTML` ou template literals e aplicar `escapeHtml()`.

### 3.3 Substituir fetch por fetchWithTimeout

Substituir chamadas `fetch()` diretas por `fetchWithTimeout()` em todos os endpoints AJAX.

### Checklist Fase 3

- [ ] Adicionar `escapeHtml()`, `fetchWithTimeout()`, `refreshCsrfToken()`
- [ ] Aplicar `escapeHtml()` em todo innerHTML dinamico
- [ ] Substituir `fetch()` por `fetchWithTimeout()` em todos os endpoints
- [ ] Adicionar retry com `refreshCsrfToken()` quando response.status === 403
- [ ] Testar formularios e listagem no navegador

---

## Fase 4: Dashboard + Relatorios (24h)

**Dependencia:** Fase 1

### 4.1 Dashboard (modal com Chart.js)

**Arquivo novo:** `app/adms/Views/employee/partials/_dashboard_modal.php`

**Endpoint:** `employees/list?typeemployee=dashboard` (match expression no controller)

**Graficos:**

| Grafico | Tipo | Dados |
|---------|------|-------|
| Distribuicao por status | Doughnut | COUNT por status (ativo, inativo, ferias, afastado) |
| Funcionarios por loja | Bar | COUNT por loja (top 15) |
| Admissoes e desligamentos | Line | COUNT por mes (ultimos 12 meses) |
| Turnover por loja | Stacked Bar | Admissoes vs desligamentos por loja |

**Endpoint JSON:**
```php
public function list(?int $PageId = null): void
{
    $typeRequest = filter_input(INPUT_GET, 'typeemployee', FILTER_VALIDATE_INT);
    match ($typeRequest) {
        1 => $this->listAllEmployees($PageId),
        2 => $this->searchEmployees(),
        3 => $this->dashboardData(),  // NOVO
        default => $this->loadInitialPage(),
    };
}
```

### 4.2 Relatorios

**Arquivo novo:** `app/adms/Controllers/ReportEmployees.php`
**Arquivo novo:** `app/adms/Models/AdmsReportEmployees.php`

| Tipo | Descricao | Filtros |
|------|-----------|---------|
| `headcount` | Quadro atual por loja/cargo/area | loja, cargo, area |
| `turnover` | Taxa de rotatividade mensal | periodo, loja |
| `admissions` | Admissoes no periodo | periodo, loja, cargo |
| `dismissals` | Desligamentos no periodo (via status history) | periodo, loja |
| `sla` | Tempo medio de contratacao (vaga → admissao) | periodo, loja |

**Arquivo novo:** `app/adms/Views/employee/partials/_report_modal.php`
**Arquivo novo:** `app/adms/Views/employee/partials/_dashboard_modal.php`

### Checklist Fase 4

- [ ] Criar endpoint `dashboardData()` no controller
- [ ] Criar model `AdmsReportEmployees.php` com 5 tipos de relatorio
- [ ] Criar controller `ReportEmployees.php`
- [ ] Criar partial `_dashboard_modal.php` com 4 graficos Chart.js
- [ ] Criar partial `_report_modal.php` com filtros e tabela de resultados
- [ ] Adicionar botoes "Dashboard" e "Relatorios" na toolbar da listagem
- [ ] Registrar rotas em `adms_paginas`

---

## Fase 5: Testes Completos (16h)

**Dependencia:** Fases 1 e 2

### Arquivos novos em `tests/Employees/`

| Teste | Cobertura | Assertions estimadas |
|-------|-----------|:---:|
| `EmployeeLifecycleServiceTest.php` | Transicoes validas/invalidas, campos obrigatorios, historico | 30+ |
| `EmployeeContractServiceTest.php` | CRUD contratos, contrato ativo, historico | 20+ |
| `EmployeeDeleteServiceTest.php` | 3 niveis permissao, soft-delete, restore | 25+ |
| `EmployeeWorkflowIntegrationTest.php` | Fluxo: criar → ativar → ferias → ativar → inativar | 15+ |
| `EmployeeDashboardTest.php` | Endpoint dashboard, dados agregados | 10+ |

**Meta:** De ~20 testes para 80+ testes

### Checklist Fase 5

- [ ] Criar `EmployeeLifecycleServiceTest.php`
- [ ] Criar `EmployeeContractServiceTest.php`
- [ ] Criar `EmployeeDeleteServiceTest.php`
- [ ] Criar `EmployeeWorkflowIntegrationTest.php`
- [ ] Criar `EmployeeDashboardTest.php`
- [ ] Verificar que testes existentes continuam passando
- [ ] Rodar `phpunit tests/Employees/` sem falhas

---

## Fase 6: Integracao — Receptor de Vagas (12h)

**Dependencia:** Fase 1 + Abertura de Vagas Fase 6

### 6.1 Endpoint de pre-cadastro

**Metodo novo em `AddEmployee.php`:**

```php
/**
 * Pre-cadastra colaborador a partir de uma vaga
 * Herda: loja, cargo, escala, data admissao
 */
public function createFromVacancy(?int $vacancyId = null): void
{
    // 1. Carregar dados da vaga via VacancyIntegrationService::prepareEmployeeData()
    // 2. Pre-preencher formulario de AddEmployee
    // 3. Definir origin_vacancy_id automaticamente
    // 4. Renderizar formulario com campos pre-populados
}
```

### 6.2 Vinculo apos criacao

Quando funcionario e criado via `createFromVacancy()`:
- Chamar `VacancyIntegrationService::linkVacancyToEmployee($vacancyId, $employeeId)`
- Atualiza `hired_employee_id` na vaga
- Atualiza `origin_vacancy_id` no funcionario

### 6.3 Exibir origem no ViewEmployee

Se `origin_vacancy_id` preenchido:
```html
<div class="alert alert-info mb-3">
    <i class="fas fa-link"></i> Contratado via
    <a href="#" onclick="viewVacancyOpening(<?= $vacancy_id ?>)">Vaga #<?= $vacancy_id ?></a>
</div>
```

### Checklist Fase 6

- [ ] Criar metodo `createFromVacancy()` em `AddEmployee.php`
- [ ] Integrar `VacancyIntegrationService::prepareEmployeeData()`
- [ ] Integrar `VacancyIntegrationService::linkVacancyToEmployee()`
- [ ] Adicionar card de origem no `_view_employee_details.php`
- [ ] Registrar rota `add-employee/create-from-vacancy` em `adms_paginas`
- [ ] Testar fluxo completo: vaga → pre-cadastro → funcionario criado

---

## Checklist de Arquivos

### Novos

```
app/adms/Models/constants/EmployeeStatus.php
app/adms/Services/EmployeeLifecycleService.php
app/adms/Services/EmployeeContractService.php
app/adms/Services/EmployeeDeleteService.php
app/adms/Controllers/ReportEmployees.php
app/adms/Models/AdmsReportEmployees.php
app/adms/Views/employee/partials/_dashboard_modal.php
app/adms/Views/employee/partials/_report_modal.php
database/migrations/2026_04_employees_modernization.sql
tests/Employees/EmployeeLifecycleServiceTest.php
tests/Employees/EmployeeContractServiceTest.php
tests/Employees/EmployeeDeleteServiceTest.php
tests/Employees/EmployeeWorkflowIntegrationTest.php
tests/Employees/EmployeeDashboardTest.php
```

### Alterados

```
app/adms/Controllers/Employees.php (dashboard endpoint)
app/adms/Controllers/AddEmployee.php (createFromVacancy)
app/adms/Controllers/DeleteEmployee.php (soft-delete)
app/adms/Models/AdmsAddEmployee.php (extrair contrato/schedule)
app/adms/Models/AdmsEditEmployee.php (extrair contrato + validar transicao)
app/adms/Models/AdmsDeleteEmployee.php (soft-delete)
app/adms/Models/AdmsListEmployee.php (filtro deleted_at)
app/adms/Models/AdmsStatisticsEmployees.php (filtro deleted_at)
app/adms/Models/AdmsExportEmployee.php (filtro deleted_at)
app/adms/Models/AdmsViewEmployee.php (historico status + link vaga)
app/adms/Services/FormSelectRepository.php (filtro deleted_at)
app/adms/Views/employee/partials/_view_employee_details.php (link vaga origem)
assets/js/employees.js (escapeHtml, fetchWithTimeout, CSRF refresh)
```

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
