# Analise e Plano de Acao: Integracao dos Modulos de RH

**Modulos:** Funcionarios (Employees), Movimento de Pessoal (PersonnelMoviments), Abertura de Vagas (VacancyOpening)
**Modelo de Referencia:** Ordem de Pagamento (OrderPayments)
**Data:** 2026-04-02
**Ultima Atualizacao:** 2026-04-02
**Projeto:** Mercury
**Versao:** 1.0

---

## Sumario

1. [Visao Geral](#1-visao-geral)
2. [Diagnostico por Modulo](#2-diagnostico-por-modulo)
3. [Comparacao com OrderPayments (Referencia)](#3-comparacao-com-orderpayments-referencia)
4. [Arquitetura de Integracao](#4-arquitetura-de-integracao)
5. [Plano de Acao — Funcionarios](#5-plano-de-acao--funcionarios)
6. [Plano de Acao — Movimento de Pessoal](#6-plano-de-acao--movimento-de-pessoal)
7. [Plano de Acao — Abertura de Vagas](#7-plano-de-acao--abertura-de-vagas)
8. [Plano de Acao — Integracao entre Modulos](#8-plano-de-acao--integracao-entre-modulos)
9. [Cronograma Consolidado](#9-cronograma-consolidado)
10. [Riscos e Mitigacoes](#10-riscos-e-mitigacoes)

---

## 1. Visao Geral

### Objetivo

Modernizar os 3 modulos de RH do Mercury para o nivel de qualidade do modulo de Ordem de Pagamento (referencia), e criar integracoes nativas que formem o **Ciclo de Vida do Colaborador**:

```
Desligamento ──► Abertura de Vaga ──► Recrutamento ──► Pre-cadastro ──► Admissao
```

### Estado Atual

| Modulo | Alinhamento c/ OrderPayments | Status | Services | Testes | Documentacao |
|--------|:---:|---|:---:|:---:|:---:|
| **Funcionarios** | 65% | Avancado Hibrido | 0 dedicados | ~20 | 1 doc |
| **Mov. Pessoal** | 50% | Funcional Hibrido | 0 dedicados | ~15 | 1 doc |
| **Ab. Vagas** | 30% | Legacy/Parcial | 0 dedicados | 2 | 0 docs |
| **OrderPayments** (ref) | 100% | Moderno completo | 3 dedicados | 343+ | 5 docs |

### Principios da Modernizacao

1. **Services para logica de negocio** — Extrair do model para services dedicados (padrao OrderPaymentTransitionService)
2. **State machine explicito** — Constants com mapa de transicoes + campos obrigatorios por transicao
3. **Status history** — Tabela dedicada para rastrear toda mudanca de status
4. **Soft-delete com auditoria** — Nunca perder dados, sempre rastrear quem deletou e por que
5. **Integracao via eventos** — Transicoes em um modulo disparam acoes em outro

---

## 2. Diagnostico por Modulo

### 2.1 Funcionarios (Employees)

**Arquivos atuais:**
- Controllers: `Employees.php`, `AddEmployee.php`, `EditEmployee.php`, `DeleteEmployee.php`, `ViewEmployee.php`, `ExportEmployee.php`, `EmployeeScheduleOverride.php`, `Api/V1/EmployeesController.php`
- Models: `AdmsAddEmployee.php` (568 LOC), `AdmsEditEmployee.php` (801 LOC), `AdmsDeleteEmployee.php` (390 LOC), `AdmsListEmployee.php`, `AdmsViewEmployee.php`, `AdmsExportEmployee.php`, `AdmsEmployeeScheduleOverride.php`, `AdmsStatisticsEmployees.php`
- Views: `employee/loadEmployees.php`, `listEmployees.php` + 14 partials
- JS: `employees.js` (2.239 LOC)
- Testes: 6+ arquivos em `tests/Employees/`

**Pontos fortes:**
- CRUD completo com transacoes atomicas
- API REST completa com 7 endpoints
- Validacao CPF (mod-11), mascaras, integracao ViaCEP
- Upload de foto com redimensionamento
- Gestao de contratos e escalas
- Redistribuicao automatica de metas (StoreGoalsRedistributionService)
- Match expressions no controller principal

**Gaps criticos vs OrderPayments:**
- Sem services dedicados (logica de 801 LOC no AdmsEditEmployee)
- Sem state machine (status muda sem validacao de transicao)
- Sem status history table
- Hard-delete sem auditoria
- Sem dashboard/graficos
- Sem relatorios dedicados (apenas export)

### 2.2 Movimento de Pessoal (PersonnelMoviments)

**Arquivos atuais:**
- Controllers: `PersonnelMoviments.php`, `AddPersonnelMoviments.php`, `EditPersonnelMoviments.php`, `DeletePersonnelMoviments.php`, `ViewPersonnelMoviments.php`
- Models: `PersonnelMovimentsRepository.php`, `AdmsAddPersonnelMoviments.php`, `AdmsEditPersonnelMoviments.php`, `AdmsDeletePersonnelMoviments.php`, `AdmsViewPersonnelMoviments.php`, `AdmsListPersonnelMovements.php` (legado)
- Views: `personnelMoviments/` + 11 partials
- JS: `personnelMoviments.js` (1.937 LOC)
- Testes: 7 arquivos em `tests/PersonnelMoviments/`
- Search/Export: `CpAdmsSearchPersonnelMoviments.php`, `CpAdmsExportPersonnelMoviments.php`

**Pontos fortes:**
- Repository Pattern (PersonnelMovimentsRepository)
- Notificacao multicanal (email para 6 areas + WebSocket)
- Follow-up de desligamento (uniforme, chip, ASO, TRCT)
- Desativacao/reativacao automatica do funcionario
- Estatisticas com modal detalhado via AJAX
- Export Excel com filtros

**Gaps criticos vs OrderPayments:**
- Sem services dedicados (notificacao de 50+ LOC inline no model)
- Sem state machine (verificacao ad-hoc: `if ($status <= 2)`)
- Sem status history table
- Hard-delete sem auditoria
- Paths de upload inconsistentes (`personnel_moviments/` vs `mp/`)
- Sem match expressions (if/elseif)
- Sem dashboard/graficos
- JS com nome em camelCase (deveria ser kebab-case)
- Model legado AdmsListPersonnelMovements coexiste com Repository

### 2.3 Abertura de Vagas (VacancyOpening)

**Arquivos atuais:**
- Controllers: `VacancyOpening.php`, `AddVacancyOpening.php`, `EditVacancyOpening.php`, `DeleteVacancyOpening.php`, `ViewVacancyOpening.php`
- Models: `AdmsAddVacancyOpening.php`, `AdmsEditVacancyOpening.php`, `AdmsDeleteVacancyOpening.php`, `AdmsListVacancyOpenings.php`, `AdmsViewVacancyOpening.php`, `AdmsStatisticsVacancyOpenings.php`
- Views: `vacancyOpening/` + 5 partials
- JS: `vacancy-opening.js` (~800 LOC)
- Testes: 2 arquivos em `tests/VacancyOpening/`
- Search: `CpAdmsSearchVacancyOpening.php`

**Pontos fortes:**
- Match expressions no controller principal
- Gestao de SLA (20/40 dias conforme nivel)
- Workflow de recrutamento (recrutador, entrevistas, avaliadores)
- Estatisticas com metricas de SLA
- Controle por tipo (Substituicao vs Aumento de Quadro)
- Notificacoes WebSocket

**Gaps criticos vs OrderPayments:**
- **Nenhuma documentacao dedicada**
- **Apenas 2 testes** (menor cobertura do projeto)
- Sem services dedicados
- Sem state machine (verificacao hardcoded por access level)
- Sem status history table
- Hard-delete sem auditoria
- Sem export dedicado
- Sem dashboard/graficos
- Sem relatorios
- Sem API REST
- Classificado como LEGACY no CHECKLIST_MODULOS.md

---

## 3. Comparacao com OrderPayments (Referencia)

### 3.1 Matriz de Conformidade

| Padrao OrderPayments | Funcionarios | Mov. Pessoal | Ab. Vagas |
|---------------------|:---:|:---:|:---:|
| **Services dedicados (3)** | 0 | 0 | 0 |
| **TransitionService (state machine)** | Inexistente | Inexistente | Inexistente |
| **DeleteService (permissoes niveis)** | Inexistente | Inexistente | Inexistente |
| **Constants/Enum (status)** | Inline | Inline | Inline |
| **Soft-delete + audit** | Hard-delete | Hard-delete | Hard-delete |
| **Status history table** | Inexistente | Inexistente | Inexistente |
| **Match expressions** | Sim | Nao | Sim |
| **Dual mode (AJAX + full-page)** | Sim | Sim | Sim |
| **Kanban/visual workflow** | Tabela | Tabela | Tabela |
| **Drag-and-drop** | Nao | Nao | Nao |
| **Bulk actions** | Nao | Nao | Nao |
| **Dashboard (Chart.js)** | Nao | Nao | Nao |
| **Timeline visual** | Nao | Nao | Nao |
| **Relatorios (8 tipos)** | 1 (export) | 1 (export) | 0 |
| **Skeleton loading** | Nao | Nao | Nao |
| **XSS escapeHtml() JS** | Nao | Nao | Nao |
| **fetchWithTimeout()** | Nao | Nao | Nao |
| **CSRF auto-refresh 403** | Nao | Nao | Nao |
| **API REST + JWT** | Sim (7 ep) | Nao | Nao |
| **OpenAPI spec** | Nao | Nao | Nao |
| **Testes (343+)** | ~20 | ~15 | 2 |
| **Testes service** | Nao | Nao | Nao |
| **Testes workflow** | Nao | Nao | Nao |
| **Documentacao (5 docs)** | 1 doc | 1 doc | 0 docs |
| **Cron automacao** | Nao | Nao | Nao |

### 3.2 Padroes a Replicar (Prioridade)

**P1 — Obrigatorio (impacto alto, base para integracao):**
1. Services dedicados com state machine (TransitionService)
2. Status history table por modulo
3. Soft-delete com audit trail
4. Constants/Enum para status

**P2 — Importante (qualidade e seguranca):**
5. XSS `escapeHtml()` no JavaScript
6. `fetchWithTimeout()` com abort
7. CSRF auto-refresh on 403
8. Testes de service + workflow

**P3 — Desejavel (UX e analytics):**
9. Dashboard com Chart.js
10. Relatorios dedicados
11. Timeline visual no view
12. Skeleton loading

---

## 4. Arquitetura de Integracao

### 4.1 Ciclo de Vida do Colaborador

```
┌──────────────────────────────────────────────────────────────────────────┐
│                    CICLO DE VIDA DO COLABORADOR                          │
│         Integracao nativa entre os 3 modulos de RH                      │
└──────────────────────────────────────────────────────────────────────────┘

 DESLIGAMENTO                  ABERTURA DE VAGA              ADMISSAO
 ═══════════                   ════════════════              ════════
 PersonnelMoviments            VacancyOpening               Employees
 Transition Service            Transition Service           Lifecycle Service

 ┌──────────┐  trigger auto   ┌──────────┐                ┌──────────┐
 │ Pendente │ ─────────────►  │  Aberta  │                │ Pendente │
 │   (1)    │  (checkbox)     │   (1)    │                │   (1)    │
 └────┬─────┘                 └────┬─────┘                └────┬─────┘
      │                            │                           │
      ▼                            ▼                           ▼
 ┌──────────┐                 ┌──────────┐                ┌──────────┐
 │Em Andam. │                 │Processand│                │  Ativo   │
 │   (2)    │                 │   (2)    │                │   (2)    │
 └────┬─────┘                 └────┬─────┘                └──────────┘
      │                            │
      ▼                            ▼
 ┌──────────┐                 ┌──────────┐  pre-cadastro  ┌──────────┐
 │Concluido │                 │Em Admiss.│ ─────────────► │AddEmployee│
 │   (3)    │                 │   (3)    │  (dados auto)  │(pre-pop) │
 └──────────┘                 └────┬─────┘                └──────────┘
                                   │
                              ┌────┴────┐
                              ▼         ▼
                         ┌────────┐ ┌────────┐
                         │Finaliz.│ │Cancelad│
                         │  (4)   │ │  (5)   │
                         └────────┘ └────────┘
```

### 4.2 Tabelas de Integracao (Novas)

```sql
-- Rastreabilidade entre modulos
ALTER TABLE adms_vacancy_opening
    ADD COLUMN origin_moviment_id INT NULL COMMENT 'FK para adms_personnel_moviments (vaga gerada por desligamento)',
    ADD COLUMN hired_employee_id INT NULL COMMENT 'FK para adms_employees (funcionario contratado)',
    ADD INDEX idx_origin_moviment (origin_moviment_id),
    ADD INDEX idx_hired_employee (hired_employee_id);

ALTER TABLE adms_employees
    ADD COLUMN origin_vacancy_id INT NULL COMMENT 'FK para adms_vacancy_opening (vaga que originou a contratacao)',
    ADD INDEX idx_origin_vacancy (origin_vacancy_id);
```

### 4.3 Fluxos de Integracao

**Fluxo A — Desligamento gera Vaga:**
1. Usuario cria Movimento de Pessoal (desligamento)
2. Marca checkbox "Abrir vaga de substituicao"
3. `PersonnelMovimentTransitionService` dispara `VacancyIntegrationService::createFromMoviment()`
4. Vaga criada automaticamente: tipo=Substituicao, loja, cargo, employee_id do desligado, `origin_moviment_id` preenchido
5. Notificacao WebSocket para RH: "Vaga de substituicao aberta automaticamente"

**Fluxo B — Vaga gera Pre-cadastro:**
1. Vaga atinge status "Em Admissao" (3) ou "Finalizada" (4)
2. Botao "Pre-cadastrar Colaborador" aparece no ViewVacancyOpening
3. `VacancyIntegrationService::prepareEmployeeData()` coleta dados da vaga
4. Redireciona para AddEmployee com dados pre-populados via query params ou session
5. Campos herdados: `adms_store_id`, `position_id`, `adms_work_schedule_id`, `date_admission`
6. Funcionario criado com `origin_vacancy_id` preenchido
7. Vaga atualizada com `hired_employee_id`

**Fluxo C — Consulta cruzada:**
1. ViewEmployee mostra link "Vaga de origem" (se `origin_vacancy_id` preenchido)
2. ViewVacancyOpening mostra link "Movimento de origem" (se `origin_moviment_id` preenchido)
3. ViewPersonnelMoviments mostra link "Vaga gerada" (query reversa)

---

## 5. Plano de Acao — Funcionarios

### Status das Fases

| Fase | Status | Esforco | Dependencia |
|------|--------|---------|-------------|
| **Fase 1:** Service Layer + State Machine | Pendente | 20h | Nenhuma |
| **Fase 2:** Soft-Delete + Status History | Pendente | 16h | Fase 1 |
| **Fase 3:** Seguranca JavaScript | Pendente | 6h | Nenhuma |
| **Fase 4:** Dashboard + Relatorios | Pendente | 24h | Fase 1 |
| **Fase 5:** Testes Completos | Pendente | 16h | Fases 1-2 |
| **Fase 6:** Integracao (receptor de vagas) | Pendente | 12h | Fase 1 + Ab. Vagas Fase 6 |

---

### Fase 1: Service Layer + State Machine (20h)

#### 1.1 Criar `EmployeeStatus.php` (Constants)

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

#### 1.2 Criar `EmployeeLifecycleService.php`

**Arquivo:** `app/adms/Services/EmployeeLifecycleService.php`

Seguindo o padrao `OrderPaymentTransitionService`:

**Metodos:**
- `validateTransition(int $fromStatus, int $toStatus, array $data): array` — Valida transicao no mapa + campos obrigatorios
- `executeTransition(int $employeeId, int $fromStatus, int $toStatus, array $fields, int $userId, ?string $notes): bool` — Executa transicao + grava historico
- `recordStatusHistory(int $employeeId, ?int $fromStatus, int $toStatus, int $userId, ?string $notes): void` — Insere em `adms_employee_status_history`
- `getStatusHistory(int $employeeId): ?array` — Carrega historico com JOINs
- `getAllowedTransitions(int $currentStatus): array` — Retorna proximos status validos

#### 1.3 Criar `EmployeeContractService.php`

**Arquivo:** `app/adms/Services/EmployeeContractService.php`

Extrair logica de contratos que hoje esta inline em `AdmsAddEmployee` e `AdmsEditEmployee`:

**Metodos:**
- `createContract(int $employeeId, array $contractData): bool`
- `updateContract(int $contractId, array $contractData): bool`
- `deleteContract(int $contractId): bool`
- `getActiveContract(int $employeeId): ?array`
- `getContractHistory(int $employeeId): ?array`

#### 1.4 Refatorar Models

- `AdmsAddEmployee.php`: Extrair `insertContract()` e `insertWorkScheduleAssignment()` para `EmployeeContractService`
- `AdmsEditEmployee.php`: Extrair logica de schedule change para `EmployeeContractService`; usar `EmployeeLifecycleService::validateTransition()` ao mudar status
- `AdmsDeleteEmployee.php`: Delegar verificacao de permissao para novo `EmployeeDeleteService` (padrao OrderPaymentDeleteService)

---

### Fase 2: Soft-Delete + Status History (16h)

#### 2.1 Migration SQL

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
    CONSTRAINT fk_esh_employee FOREIGN KEY (adms_employee_id) REFERENCES adms_employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_esh_user FOREIGN KEY (changed_by_user_id) REFERENCES adms_usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campos de soft-delete no adms_employees
ALTER TABLE adms_employees
    ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN deleted_by_user_id INT NULL DEFAULT NULL,
    ADD COLUMN deleted_reason TEXT NULL DEFAULT NULL,
    ADD COLUMN origin_vacancy_id INT NULL DEFAULT NULL COMMENT 'Vaga que originou a contratacao',
    ADD INDEX idx_employee_deleted (deleted_at),
    ADD INDEX idx_origin_vacancy (origin_vacancy_id);
```

#### 2.2 Criar `EmployeeDeleteService.php`

**Arquivo:** `app/adms/Services/EmployeeDeleteService.php`

Seguindo padrao `OrderPaymentDeleteService` com 3 niveis:

| Nivel | Condicao | Motivo | Confirmacao |
|-------|----------|--------|-------------|
| 1 | Status=Pendente + criador + sem edicoes | Nao | Nao |
| 2 | Status=Pendente/Ativo + nivel <= 5 | Sim | Nao |
| 3 | Qualquer status + nivel = 1 (Super Admin) | Sim | Sim |

#### 2.3 Atualizar queries

Adicionar `AND deleted_at IS NULL` em todas as queries SELECT de:
- `AdmsListEmployee.php`
- `AdmsStatisticsEmployees.php`
- `AdmsExportEmployee.php`
- `AdmsViewEmployee.php`
- `CpAdmsSearchEmployee.php` (se existir)

---

### Fase 3: Seguranca JavaScript (6h)

#### 3.1 Adicionar funcoes utilitarias em `employees.js`

Seguindo padrao `order-payments.js`:

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
    // ... implementacao
}
```

#### 3.2 Aplicar `escapeHtml()` em todo innerHTML dinâmico

Revisar `employees.js` e aplicar `escapeHtml()` em todos os pontos onde dados do servidor sao inseridos no DOM via `innerHTML` ou template literals.

---

### Fase 4: Dashboard + Relatorios (24h)

#### 4.1 Dashboard (modal com Chart.js)

**Arquivo novo:** `app/adms/Views/employee/partials/_dashboard_modal.php`

Graficos:
- **Doughnut:** Distribuicao por status (ativo, inativo, ferias, afastado)
- **Bar:** Funcionarios por loja
- **Line:** Admissoes e desligamentos por mes (ultimos 12 meses)
- **Stacked Bar:** Turnover por loja (admissoes vs desligamentos)

**Endpoint:** `employees/list?typeemployee=dashboard` (match expression no controller)

#### 4.2 Relatorios

**Arquivo novo:** `app/adms/Controllers/ReportEmployees.php`

| Tipo | Descricao | Filtros |
|------|-----------|---------|
| `headcount` | Quadro atual por loja/cargo/area | loja, cargo, area |
| `turnover` | Taxa de rotatividade mensal | periodo, loja |
| `admissions` | Admissoes no periodo | periodo, loja, cargo |
| `dismissals` | Desligamentos no periodo | periodo, loja, motivo |
| `sla` | Tempo medio de contratacao (vaga→admissao) | periodo, loja |

---

### Fase 5: Testes Completos (16h)

**Arquivos novos em `tests/Employees/`:**

| Teste | Cobertura |
|-------|-----------|
| `EmployeeLifecycleServiceTest.php` | Transicoes validas/invalidas, campos obrigatorios por transicao, historico |
| `EmployeeContractServiceTest.php` | CRUD de contratos, contrato ativo, historico |
| `EmployeeDeleteServiceTest.php` | 3 niveis de permissao, soft-delete, restauracao |
| `EmployeeWorkflowIntegrationTest.php` | Fluxo completo: criar→ativar→inativar→reativar |
| `EmployeeDashboardTest.php` | Endpoint dashboard, dados agregados |

**Meta:** Alcançar 80+ testes (alinhado proporcional ao OrderPayments)

---

### Fase 6: Integracao — Receptor de Vagas (12h)

**Dependencia:** Ab. Vagas Fase 6 concluida

#### 6.1 Endpoint de pre-cadastro

**Metodo novo em `AddEmployee.php`:**
```php
public function createFromVacancy(?int $vacancyId = null): void
```

- Carrega dados da vaga via `AdmsViewVacancyOpening::viewOrder()`
- Pre-preenche: `adms_store_id`, `position_id`, `adms_work_schedule_id`, `date_admission`
- Define `origin_vacancy_id` automaticamente
- Renderiza formulario de AddEmployee com campos pre-populados

#### 6.2 Exibir origem no ViewEmployee

Se `origin_vacancy_id` preenchido, exibir card com link para a vaga de origem:
```html
<div class="alert alert-info">
    <i class="fas fa-link"></i> Contratado via Vaga #123 — <a href="...">Ver Vaga</a>
</div>
```

---

## 6. Plano de Acao — Movimento de Pessoal

### Status das Fases

| Fase | Status | Esforco | Dependencia |
|------|--------|---------|-------------|
| **Fase 1:** Service Layer + State Machine | Pendente | 24h | Nenhuma |
| **Fase 2:** Soft-Delete + Status History | Pendente | 14h | Fase 1 |
| **Fase 3:** Padronizacao de Codigo | Pendente | 8h | Nenhuma |
| **Fase 4:** Seguranca JavaScript | Pendente | 6h | Nenhuma |
| **Fase 5:** Dashboard + Relatorios | Pendente | 24h | Fase 1 |
| **Fase 6:** Testes Completos | Pendente | 14h | Fases 1-2 |
| **Fase 7:** Integracao (disparar abertura de vaga) | Pendente | 12h | Fase 1 + Ab. Vagas Fase 1 |

---

### Fase 1: Service Layer + State Machine (24h)

#### 1.1 Criar `PersonnelMovimentStatus.php` (Constants)

**Arquivo:** `app/adms/Models/constants/PersonnelMovimentStatus.php`

```php
<?php
namespace App\adms\Models\constants;

class PersonnelMovimentStatus
{
    const PENDING     = 1;
    const IN_PROGRESS = 2;
    const COMPLETED   = 3;

    const LABELS = [
        self::PENDING     => 'Pendente',
        self::IN_PROGRESS => 'Em Andamento',
        self::COMPLETED   => 'Concluido',
    ];

    const TRANSITIONS = [
        self::PENDING     => [self::IN_PROGRESS],
        self::IN_PROGRESS => [self::PENDING, self::COMPLETED],
        self::COMPLETED   => [self::IN_PROGRESS],  // reabrir se necessario
    ];

    const REQUIRED_FIELDS = [
        '1_2' => [],  // Iniciar processamento
        '2_3' => ['dismissal_follow_up_complete'],  // Concluir requer follow-up
        '3_2' => ['reopen_reason'],  // Reabrir requer justificativa
    ];
}
```

#### 1.2 Criar `PersonnelMovimentTransitionService.php`

**Arquivo:** `app/adms/Services/PersonnelMovimentTransitionService.php`

Seguindo padrao `OrderPaymentTransitionService`:

**Metodos:**
- `validateTransition(int $from, int $to, array $data): array`
- `executeTransition(int $movimentId, int $fromStatus, int $toStatus, array $fields, int $userId, ?string $notes): bool`
- `recordStatusHistory(int $movimentId, ?int $fromStatus, int $toStatus, int $userId, ?string $notes): void`
- `getStatusHistory(int $movimentId): ?array`
- `getAllowedTransitions(int $currentStatus): array`

#### 1.3 Criar `DismissalNotificationService.php`

**Arquivo:** `app/adms/Services/DismissalNotificationService.php`

Extrair de `AdmsAddPersonnelMoviments.php` (metodos `sendNotifications()`, `getManagersByAreas()`, `sendEmailToManager()`, `buildEmailHtml()`):

**Metodos:**
- `notifyDismissal(int $movimentId, array $movimentData): void`
- `getNotificationRecipients(int $boardId): array` — Areas 4, 6, 7, 9, 13, 16
- `sendEmailToManagers(array $managers, array $emailData): void`
- `buildDismissalEmailHtml(array $data): string`
- `buildDismissalEmailText(array $data): string`

#### 1.4 Criar `EmployeeInactivationService.php`

**Arquivo:** `app/adms/Services/EmployeeInactivationService.php`

Extrair logica duplicada entre `AdmsAddPersonnelMoviments::deactivateEmployee()` e `AdmsDeletePersonnelMoviments::reactivateEmployee()`:

**Metodos:**
- `deactivate(int $employeeId, int $userId, ?string $reason): bool` — Status → 3 (Inativo)
- `reactivate(int $employeeId, int $userId, ?string $reason): bool` — Status → 2 (Ativo)
- `validateDeactivation(int $employeeId): array` — Verificar se pode desativar (nao esta em ferias, etc.)

---

### Fase 2: Soft-Delete + Status History (14h)

#### 2.1 Migration SQL

**Arquivo:** `database/migrations/2026_04_personnel_moviments_modernization.sql`

```sql
-- =============================================
-- Migration: Modernizacao Movimento de Pessoal
-- Data: 2026-04-XX
-- Collation: utf8mb4_unicode_ci (OBRIGATORIO)
-- =============================================

-- Tabela de historico de status
CREATE TABLE adms_personnel_moviment_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adms_personnel_moviment_id INT NOT NULL,
    old_status_id INT NULL,
    new_status_id INT NOT NULL,
    changed_by_user_id INT NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_moviment_history (adms_personnel_moviment_id),
    INDEX idx_changed_by (changed_by_user_id),
    CONSTRAINT fk_pmsh_moviment FOREIGN KEY (adms_personnel_moviment_id)
        REFERENCES adms_personnel_moviments(id) ON DELETE CASCADE,
    CONSTRAINT fk_pmsh_user FOREIGN KEY (changed_by_user_id)
        REFERENCES adms_usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campos de soft-delete
ALTER TABLE adms_personnel_moviments
    ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN deleted_by_user_id INT NULL DEFAULT NULL,
    ADD COLUMN deleted_reason TEXT NULL DEFAULT NULL,
    ADD COLUMN open_vacancy TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Abrir vaga de substituicao',
    ADD COLUMN generated_vacancy_id INT NULL DEFAULT NULL COMMENT 'Vaga gerada automaticamente',
    ADD INDEX idx_pm_deleted (deleted_at),
    ADD INDEX idx_pm_vacancy (generated_vacancy_id);
```

#### 2.2 Criar `PersonnelMovimentDeleteService.php`

**Arquivo:** `app/adms/Services/PersonnelMovimentDeleteService.php`

| Nivel | Condicao | Motivo | Use Case |
|-------|----------|--------|----------|
| 1 | Pendente + criador + sem edicoes | Nao | Cancelar rascunho proprio |
| 2 | Pendente/Em Andamento + nivel <= 5 | Sim | Gerente cancela |
| 3 | Concluido + nivel = 1 | Sim + confirmacao | Super Admin reverte |

---

### Fase 3: Padronizacao de Codigo (8h)

#### 3.1 Match expressions no controller

Refatorar `PersonnelMoviments.php` para usar match expression no metodo `list()`:

```php
match ($typeRequest) {
    1 => $this->handleAjaxListRequest($filters),
    2 => $this->searchPersonnelMoviments($filters),
    3 => $this->getFilteredStats(),
    4 => $this->getStatisticsModal(),
    5 => $this->dashboardData(),
    default => $this->loadInitialPage(),
};
```

#### 3.2 Renomear JavaScript

- Renomear `personnelMoviments.js` → `personnel-moviments.js` (kebab-case conforme padrao)
- Atualizar referencia na view `loadPersonnelMoviments.php`

#### 3.3 Unificar paths de upload

- Padronizar path para `assets/files/personnel_moviments/{movimentId}/`
- Criar migration de dados para mover arquivos de `mp/` para `personnel_moviments/`
- Atualizar `AdmsEditPersonnelMoviments.php` para usar path correto

#### 3.4 Remover model legado

- Remover `AdmsListPersonnelMovements.php` (substituido por `PersonnelMovimentsRepository`)
- Verificar que nenhum controller ainda referencia o model legado

---

### Fase 4: Seguranca JavaScript (6h)

Mesma abordagem da Fase 3 de Funcionarios:
- Adicionar `escapeHtml()`, `fetchWithTimeout()`, `refreshCsrfToken()`
- Aplicar `escapeHtml()` em todo innerHTML dinamico
- Implementar CSRF auto-refresh on 403

---

### Fase 5: Dashboard + Relatorios (24h)

#### 5.1 Dashboard

**Arquivo novo:** `app/adms/Views/personnelMoviments/partials/_dashboard_modal.php`

Graficos:
- **Doughnut:** Movimentos por status
- **Bar:** Desligamentos por loja (ultimos 12 meses)
- **Line:** Tendencia mensal de desligamentos
- **Stacked Bar:** Motivos de desligamento por loja

#### 5.2 Relatorios

**Arquivo novo:** `app/adms/Controllers/ReportPersonnelMoviments.php`

| Tipo | Descricao |
|------|-----------|
| `by_reason` | Desligamentos por motivo |
| `by_store` | Desligamentos por loja |
| `by_period` | Desligamentos no periodo |
| `follow_up` | Status de follow-up (pendentes, completos) |
| `turnover` | Taxa de rotatividade por loja |
| `sla` | Tempo medio de processamento (criacao→conclusao) |

---

### Fase 6: Testes Completos (14h)

**Arquivos novos em `tests/PersonnelMoviments/`:**

| Teste | Cobertura |
|-------|-----------|
| `PersonnelMovimentTransitionServiceTest.php` | State machine, validacoes, historico |
| `DismissalNotificationServiceTest.php` | Destinatarios, email, areas |
| `EmployeeInactivationServiceTest.php` | Desativar, reativar, validacoes |
| `PersonnelMovimentDeleteServiceTest.php` | 3 niveis, soft-delete |
| `PersonnelMovimentWorkflowTest.php` | Fluxo completo: criar→processar→concluir |

**Meta:** Alcançar 50+ testes

---

### Fase 7: Integracao — Disparar Abertura de Vaga (12h)

**Dependencia:** Ab. Vagas Fase 1 concluida

#### 7.1 Checkbox no formulario de criacao

**Alterar:** `app/adms/Views/personnelMoviments/partials/_add_moviment_modal.php`

```html
<div class="form-group">
    <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" id="open_vacancy" name="open_vacancy" value="1">
        <label class="custom-control-label" for="open_vacancy">
            Abrir vaga de substituicao automaticamente
        </label>
    </div>
</div>
```

#### 7.2 Criar `VacancyIntegrationService.php`

**Arquivo:** `app/adms/Services/VacancyIntegrationService.php`

Service compartilhado entre os modulos:

**Metodos:**
- `createFromMoviment(int $movimentId, array $movimentData): ?int` — Cria vaga tipo Substituicao a partir do desligamento, retorna vacancy_id
- `prepareEmployeeData(int $vacancyId): array` — Prepara dados da vaga para pre-cadastro de funcionario
- `linkVacancyToEmployee(int $vacancyId, int $employeeId): bool` — Vincula vaga ao funcionario contratado
- `linkMovimentToVacancy(int $movimentId, int $vacancyId): bool` — Vincula movimento a vaga gerada

#### 7.3 Integrar no fluxo de criacao

**Alterar:** `AdmsAddPersonnelMoviments.php`

Apos `insertMoviment()` com sucesso, se `open_vacancy == 1`:
```php
if (!empty($this->data['open_vacancy'])) {
    $vacancyService = new VacancyIntegrationService();
    $vacancyId = $vacancyService->createFromMoviment($this->movimentId, [
        'adms_loja_id' => $this->data['adms_loja_id'],
        'adms_employee_id' => $this->data['adms_employee_id'],
        'position_id' => $employeePosition,
        'request_area_id' => $this->data['request_area_id'],
    ]);
    if ($vacancyId) {
        $vacancyService->linkMovimentToVacancy($this->movimentId, $vacancyId);
    }
}
```

---

## 7. Plano de Acao — Abertura de Vagas

### Status das Fases

| Fase | Status | Esforco | Dependencia |
|------|--------|---------|-------------|
| **Fase 1:** Service Layer + State Machine | Pendente | 20h | Nenhuma |
| **Fase 2:** Soft-Delete + Status History | Pendente | 14h | Fase 1 |
| **Fase 3:** Documentacao + Testes | Pendente | 20h | Fase 1 |
| **Fase 4:** Seguranca JavaScript | Pendente | 6h | Nenhuma |
| **Fase 5:** Export + Relatorios + Dashboard | Pendente | 28h | Fase 1 |
| **Fase 6:** Integracao (pre-cadastro + recepcao de movimentos) | Pendente | 16h | Fase 1 + Func. Fase 6 + MP Fase 7 |

---

### Fase 1: Service Layer + State Machine (20h)

#### 1.1 Criar `VacancyOpeningStatus.php` (Constants)

**Arquivo:** `app/adms/Models/constants/VacancyOpeningStatus.php`

```php
<?php
namespace App\adms\Models\constants;

class VacancyOpeningStatus
{
    const OPEN          = 1;  // Aberta
    const PROCESSING    = 2;  // Em Processamento
    const IN_ADMISSION  = 3;  // Em Admissao
    const FINALIZED     = 4;  // Finalizada
    const CANCELED      = 5;  // Cancelada

    const LABELS = [
        self::OPEN         => 'Aberta',
        self::PROCESSING   => 'Em Processamento',
        self::IN_ADMISSION => 'Em Admissao',
        self::FINALIZED    => 'Finalizada',
        self::CANCELED     => 'Cancelada',
    ];

    const TRANSITIONS = [
        self::OPEN         => [self::PROCESSING, self::CANCELED],
        self::PROCESSING   => [self::OPEN, self::IN_ADMISSION, self::CANCELED],
        self::IN_ADMISSION => [self::PROCESSING, self::FINALIZED, self::CANCELED],
        self::FINALIZED    => [],   // terminal
        self::CANCELED     => [],   // terminal
    ];

    const REQUIRED_FIELDS = [
        '1_2' => ['adms_recruiter_id'],
        '2_3' => ['interview_hr', 'evaluators_hr', 'approved'],
        '3_4' => ['closing_date', 'date_admission'],
    ];

    const TERMINAL = [self::FINALIZED, self::CANCELED];
}
```

#### 1.2 Criar `VacancyTransitionService.php`

**Arquivo:** `app/adms/Services/VacancyTransitionService.php`

Seguindo padrao `OrderPaymentTransitionService`:

**Metodos:**
- `validateTransition(int $from, int $to, array $data): array` — Valida no mapa + campos obrigatorios
- `executeTransition(int $vacancyId, int $fromStatus, int $toStatus, array $fields, int $userId, ?string $notes): bool` — Executa + calcula SLA efetivo se terminal
- `recordStatusHistory(int $vacancyId, ?int $fromStatus, int $toStatus, int $userId, ?string $notes): void`
- `getStatusHistory(int $vacancyId): ?array`
- `getAllowedTransitions(int $currentStatus): array`
- `calculateEffectiveSla(int $vacancyId): ?int` — Dias entre criacao e finalizacao

#### 1.3 Criar `VacancyRecruitmentService.php`

**Arquivo:** `app/adms/Services/VacancyRecruitmentService.php`

**Metodos:**
- `assignRecruiter(int $vacancyId, int $recruiterId, int $userId): bool`
- `scheduleInterview(int $vacancyId, string $type, string $date, string $evaluators, int $userId): bool` — type = 'hr' ou 'leader'
- `markApproved(int $vacancyId, int $userId): bool`
- `getRecruitmentTimeline(int $vacancyId): array` — Retorna timeline do processo seletivo

#### 1.4 Refatorar Models

- `AdmsAddVacancyOpening.php`: Usar `VacancyOpeningStatus::OPEN` ao inves de magic number; usar `VacancyTransitionService::recordStatusHistory()` na criacao
- `AdmsEditVacancyOpening.php`: Validar transicao via `VacancyTransitionService::validateTransition()`; delegar recrutamento para `VacancyRecruitmentService`
- `AdmsDeleteVacancyOpening.php`: Substituir hard-delete por soft-delete (Fase 2)

---

### Fase 2: Soft-Delete + Status History (14h)

#### 2.1 Migration SQL

**Arquivo:** `database/migrations/2026_04_vacancy_opening_modernization.sql`

```sql
-- =============================================
-- Migration: Modernizacao Abertura de Vagas
-- Data: 2026-04-XX
-- Collation: utf8mb4_unicode_ci (OBRIGATORIO)
-- =============================================

-- Tabela de historico de status
CREATE TABLE adms_vacancy_opening_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adms_vacancy_opening_id INT NOT NULL,
    old_status_id INT NULL,
    new_status_id INT NOT NULL,
    changed_by_user_id INT NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vacancy_history (adms_vacancy_opening_id),
    INDEX idx_changed_by (changed_by_user_id),
    CONSTRAINT fk_vosh_vacancy FOREIGN KEY (adms_vacancy_opening_id)
        REFERENCES adms_vacancy_opening(id) ON DELETE CASCADE,
    CONSTRAINT fk_vosh_user FOREIGN KEY (changed_by_user_id)
        REFERENCES adms_usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campos de soft-delete e integracao
ALTER TABLE adms_vacancy_opening
    ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN deleted_by_user_id INT NULL DEFAULT NULL,
    ADD COLUMN deleted_reason TEXT NULL DEFAULT NULL,
    ADD COLUMN origin_moviment_id INT NULL DEFAULT NULL COMMENT 'Movimento de Pessoal que originou a vaga',
    ADD COLUMN hired_employee_id INT NULL DEFAULT NULL COMMENT 'Funcionario contratado via esta vaga',
    ADD INDEX idx_vo_deleted (deleted_at),
    ADD INDEX idx_vo_origin_moviment (origin_moviment_id),
    ADD INDEX idx_vo_hired_employee (hired_employee_id);
```

#### 2.2 Criar `VacancyDeleteService.php`

**Arquivo:** `app/adms/Services/VacancyDeleteService.php`

| Nivel | Condicao | Motivo | Use Case |
|-------|----------|--------|----------|
| 1 | Aberta + criador + sem edicoes | Nao | Cancelar rascunho |
| 2 | Aberta/Processando + nivel <= 5 | Sim | RH cancela |
| 3 | Em Admissao + nivel = 1 | Sim + confirmacao | Super Admin reverte |

**Nota:** Status Finalizada e Cancelada NAO podem ser deletados (sao terminais).

---

### Fase 3: Documentacao + Testes (20h)

#### 3.1 Documentacao

**Arquivo novo:** `docs/modules/MODULO_VACANCY_OPENING.md`

Conteudo (seguindo padrao dos demais modulos):
- Visao geral e proposito
- Arquitetura (controllers, models, services, views)
- Fluxo de status com diagrama
- Tabelas e relacionamentos
- Permissoes por nivel
- Integracoes (com Mov. Pessoal e Funcionarios)
- Endpoints AJAX
- Validacoes de negocio (SLA, tipo de vaga)

#### 3.2 Testes

**Arquivos novos em `tests/VacancyOpening/`:**

| Teste | Cobertura |
|-------|-----------|
| `VacancyTransitionServiceTest.php` | State machine, validacoes, SLA, historico |
| `VacancyRecruitmentServiceTest.php` | Recrutador, entrevistas, aprovacao |
| `VacancyDeleteServiceTest.php` | 3 niveis, soft-delete |
| `AdmsAddVacancyOpeningTest.php` | Criacao, validacao, SLA automatico |
| `AdmsDeleteVacancyOpeningTest.php` | Soft-delete, permissoes |
| `AdmsViewVacancyOpeningTest.php` | Visualizacao com historico |
| `AdmsStatisticsVacancyOpeningsTest.php` | KPIs, filtros |
| `VacancyWorkflowIntegrationTest.php` | Fluxo completo: abrir→processar→admissao→finalizar |
| `VacancyIntegrationServiceTest.php` | Criar de movimento, pre-cadastro, vinculos |

**Meta:** Alcançar 60+ testes (de 2 para 60+)

---

### Fase 4: Seguranca JavaScript (6h)

Mesma abordagem das Fases anteriores:
- Adicionar `escapeHtml()`, `fetchWithTimeout()`, `refreshCsrfToken()` em `vacancy-opening.js`
- Aplicar em todo innerHTML dinamico
- Implementar CSRF auto-refresh on 403

---

### Fase 5: Export + Relatorios + Dashboard (28h)

#### 5.1 Export Dedicado

**Arquivo novo:** `app/adms/Controllers/ExportVacancyOpening.php`
**Arquivo novo:** `app/adms/Models/AdmsExportVacancyOpening.php`

Export CSV/Excel com filtros: status, loja, tipo, periodo, recrutador.

#### 5.2 Dashboard

**Arquivo novo:** `app/adms/Views/vacancyOpening/partials/_dashboard_modal.php`

Graficos:
- **Doughnut:** Vagas por status
- **Bar:** SLA medio por loja (previsto vs efetivo)
- **Line:** Vagas abertas vs finalizadas por mes (ultimos 12 meses)
- **Horizontal Bar:** Top 10 cargos mais solicitados

#### 5.3 Relatorios

**Arquivo novo:** `app/adms/Controllers/ReportVacancyOpening.php`

| Tipo | Descricao |
|------|-----------|
| `sla` | SLA por loja/cargo (previsto vs efetivo) |
| `by_status` | Vagas por status no periodo |
| `by_type` | Substituicao vs Aumento de Quadro |
| `by_recruiter` | Performance por recrutador |
| `by_position` | Cargos mais solicitados |
| `pipeline` | Funil de recrutamento (aberta→processando→admissao→finalizada) |

---

### Fase 6: Integracao — Pre-cadastro + Recepcao de Movimentos (16h)

**Dependencia:** Funcionarios Fase 6 + Mov. Pessoal Fase 7

#### 6.1 Recepcao de Movimentos

Quando `VacancyIntegrationService::createFromMoviment()` cria a vaga:
- Preencher `origin_moviment_id`
- Tipo = Substituicao
- Campos herdados: loja, cargo (via employee), employee_id de referencia
- SLA calculado automaticamente (20 ou 40 dias)
- Exibir badge "Gerada por Desligamento #X" na view

#### 6.2 Botao de Pre-cadastro

**Alterar:** `app/adms/Views/vacancyOpening/partials/_view_vacancy_opening_content.php`

Botao visivel quando status >= 3 (Em Admissao):
```html
<?php if ($this->Dados['adms_sit_vacancy_id'] >= 3 && !empty($this->Dados['buttons']['pre_register'])): ?>
<a href="<?= URLADM ?>add-employee/create-from-vacancy/<?= $this->Dados['id'] ?>"
   class="btn btn-success btn-sm">
    <i class="fas fa-user-plus"></i> Pre-cadastrar Colaborador
</a>
<?php endif; ?>
```

#### 6.3 Vinculo apos contratacao

Quando funcionario e criado via `AddEmployee::createFromVacancy()`:
- `VacancyIntegrationService::linkVacancyToEmployee($vacancyId, $employeeId)` atualiza `hired_employee_id`
- Exibir link "Colaborador contratado: Fulano" na view da vaga

#### 6.4 Exibir links cruzados

**ViewVacancyOpening:** Se `origin_moviment_id` → link para o Movimento; se `hired_employee_id` → link para o Funcionario
**ViewPersonnelMoviments:** Se `generated_vacancy_id` → link para a Vaga
**ViewEmployee:** Se `origin_vacancy_id` → link para a Vaga

---

## 8. Plano de Acao — Integracao entre Modulos

### Status das Fases

| Fase | Status | Esforco | Dependencia |
|------|--------|---------|-------------|
| **Fase I-1:** VacancyIntegrationService | Pendente | 12h | MP Fase 1 + AV Fase 1 |
| **Fase I-2:** Migration de integracao | Pendente | 4h | Nenhuma (pode antecipar) |
| **Fase I-3:** Fluxo A (Desligamento → Vaga) | Pendente | 8h | I-1 + MP Fase 7 |
| **Fase I-4:** Fluxo B (Vaga → Pre-cadastro) | Pendente | 8h | I-1 + Func. Fase 6 + AV Fase 6 |
| **Fase I-5:** Links cruzados entre modulos | Pendente | 6h | I-3 + I-4 |
| **Fase I-6:** Dashboard RH unificado | Pendente | 16h | Todas as fases anteriores |
| **Fase I-7:** Testes de integracao | Pendente | 12h | I-3 + I-4 |

---

### Fase I-1: VacancyIntegrationService (12h)

**Arquivo:** `app/adms/Services/VacancyIntegrationService.php`

Service centralizado para todas as integracoes entre modulos:

```php
<?php
namespace App\adms\Services;

use App\adms\Models\constants\VacancyOpeningStatus;

class VacancyIntegrationService
{
    /**
     * Cria vaga de substituicao a partir de um movimento de desligamento
     *
     * @param int $movimentId ID do movimento de pessoal
     * @param array $movimentData Dados do movimento (loja, employee, cargo)
     * @return int|null ID da vaga criada ou null em caso de erro
     */
    public function createFromMoviment(int $movimentId, array $movimentData): ?int
    {
        // 1. Carregar dados do funcionario (cargo, area)
        // 2. Criar vaga tipo Substituicao (adms_request_type_id = 1)
        // 3. Preencher: loja, cargo, employee_id, origin_moviment_id
        // 4. Calcular SLA automatico
        // 5. Registrar status history (criacao)
        // 6. Notificar RH via WebSocket
        // 7. LoggerService::info('VACANCY_CREATED_FROM_MOVIMENT', ...)
        // 8. Retornar vacancy_id
    }

    /**
     * Prepara dados da vaga para pre-preencher formulario de AddEmployee
     *
     * @param int $vacancyId ID da vaga
     * @return array Dados formatados para o formulario de funcionario
     */
    public function prepareEmployeeData(int $vacancyId): array
    {
        // 1. Carregar dados da vaga (loja, cargo, schedule, date_admission)
        // 2. Formatar para campos do formulario de Employee
        // 3. Retornar array com campos pre-preenchidos
    }

    /**
     * Vincula vaga ao funcionario contratado
     */
    public function linkVacancyToEmployee(int $vacancyId, int $employeeId): bool
    {
        // 1. UPDATE adms_vacancy_opening SET hired_employee_id = :employeeId
        // 2. UPDATE adms_employees SET origin_vacancy_id = :vacancyId
        // 3. LoggerService::info('VACANCY_LINKED_TO_EMPLOYEE', ...)
    }

    /**
     * Vincula movimento a vaga gerada
     */
    public function linkMovimentToVacancy(int $movimentId, int $vacancyId): bool
    {
        // 1. UPDATE adms_personnel_moviments SET generated_vacancy_id = :vacancyId
        // 2. LoggerService::info('MOVIMENT_LINKED_TO_VACANCY', ...)
    }
}
```

---

### Fase I-2: Migration de Integracao (4h)

**Arquivo:** `database/migrations/2026_04_rh_modules_integration.sql`

```sql
-- =============================================
-- Migration: Integracao Modulos RH
-- Data: 2026-04-XX
-- Collation: utf8mb4_unicode_ci
-- =============================================

-- Campos de integracao em adms_vacancy_opening
ALTER TABLE adms_vacancy_opening
    ADD COLUMN IF NOT EXISTS origin_moviment_id INT NULL DEFAULT NULL
        COMMENT 'FK para adms_personnel_moviments',
    ADD COLUMN IF NOT EXISTS hired_employee_id INT NULL DEFAULT NULL
        COMMENT 'FK para adms_employees',
    ADD INDEX IF NOT EXISTS idx_vo_origin_moviment (origin_moviment_id),
    ADD INDEX IF NOT EXISTS idx_vo_hired_employee (hired_employee_id);

-- Campos de integracao em adms_employees
ALTER TABLE adms_employees
    ADD COLUMN IF NOT EXISTS origin_vacancy_id INT NULL DEFAULT NULL
        COMMENT 'FK para adms_vacancy_opening',
    ADD INDEX IF NOT EXISTS idx_emp_origin_vacancy (origin_vacancy_id);

-- Campos de integracao em adms_personnel_moviments
ALTER TABLE adms_personnel_moviments
    ADD COLUMN IF NOT EXISTS open_vacancy TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Flag: abrir vaga de substituicao',
    ADD COLUMN IF NOT EXISTS generated_vacancy_id INT NULL DEFAULT NULL
        COMMENT 'FK para adms_vacancy_opening',
    ADD INDEX IF NOT EXISTS idx_pm_gen_vacancy (generated_vacancy_id);

-- View de integracao para consultas cruzadas
CREATE OR REPLACE VIEW vw_rh_lifecycle AS
SELECT
    pm.id AS moviment_id,
    pm.adms_loja_id AS store_id,
    pm.adms_employee_id AS dismissed_employee_id,
    emp_d.name_employee AS dismissed_employee_name,
    pm.last_day_worked,
    pm.adms_sits_personnel_mov_id AS moviment_status,
    vo.id AS vacancy_id,
    vo.adms_sit_vacancy_id AS vacancy_status,
    vo.predicted_sla,
    vo.effective_sla,
    vo.closing_date AS vacancy_closing_date,
    emp_h.id AS hired_employee_id,
    emp_h.name_employee AS hired_employee_name,
    emp_h.date_admission AS hire_date,
    DATEDIFF(emp_h.date_admission, pm.last_day_worked) AS days_to_replace
FROM adms_personnel_moviments pm
LEFT JOIN adms_vacancy_opening vo ON vo.origin_moviment_id = pm.id
LEFT JOIN adms_employees emp_d ON emp_d.id = pm.adms_employee_id
LEFT JOIN adms_employees emp_h ON emp_h.id = vo.hired_employee_id
WHERE pm.deleted_at IS NULL;
```

---

### Fase I-3: Fluxo A — Desligamento gera Vaga (8h)

1. Adicionar checkbox "Abrir vaga de substituicao" no `_add_moviment_modal.php`
2. No `AdmsAddPersonnelMoviments`, apos criacao com sucesso, chamar `VacancyIntegrationService::createFromMoviment()` se checkbox marcado
3. Gravar `generated_vacancy_id` no movimento
4. Notificacao WebSocket para RH: "Vaga #X criada automaticamente a partir do desligamento #Y"
5. Exibir badge no card/view do movimento: "Vaga gerada: #X"

---

### Fase I-4: Fluxo B — Vaga gera Pre-cadastro (8h)

1. Adicionar botao "Pre-cadastrar Colaborador" no `_view_vacancy_opening_content.php` (status >= 3)
2. Criar metodo `AddEmployee::createFromVacancy()` que carrega dados via `VacancyIntegrationService::prepareEmployeeData()`
3. Renderizar formulario de AddEmployee com campos pre-preenchidos (loja, cargo, escala, data admissao)
4. Apos criacao do funcionario, chamar `VacancyIntegrationService::linkVacancyToEmployee()`
5. Atualizar status da vaga para Finalizada (4) automaticamente
6. Notificacao WebSocket: "Colaborador X cadastrado via Vaga #Y"

---

### Fase I-5: Links Cruzados (6h)

Adicionar cards informativos nos modals de view:

**ViewEmployee (se `origin_vacancy_id`):**
```html
<div class="alert alert-info mb-3">
    <i class="fas fa-link"></i> Contratado via
    <a href="#" onclick="viewVacancyOpening(123)">Vaga #123</a>
</div>
```

**ViewVacancyOpening (se `origin_moviment_id`):**
```html
<div class="alert alert-warning mb-3">
    <i class="fas fa-exchange-alt"></i> Gerada pelo
    <a href="#" onclick="viewMoviment(456)">Desligamento #456</a>
</div>
```

**ViewVacancyOpening (se `hired_employee_id`):**
```html
<div class="alert alert-success mb-3">
    <i class="fas fa-user-check"></i> Colaborador contratado:
    <a href="#" onclick="viewEmployee(789)">Joao Silva (#789)</a>
</div>
```

**ViewPersonnelMoviments (se `generated_vacancy_id`):**
```html
<div class="alert alert-info mb-3">
    <i class="fas fa-briefcase"></i> Vaga de substituicao:
    <a href="#" onclick="viewVacancyOpening(123)">Vaga #123</a>
</div>
```

---

### Fase I-6: Dashboard RH Unificado (16h)

**Arquivo novo:** `app/adms/Controllers/DashboardRH.php`
**Arquivo novo:** `app/adms/Views/dashboardRH/loadDashboardRH.php`

Dashboard centralizado com KPIs e graficos dos 3 modulos:

**KPI Cards:**
| Card | Fonte | Calculo |
|------|-------|---------|
| Headcount Ativo | Employees | COUNT WHERE status=2 |
| Desligamentos (mes) | PersonnelMoviments | COUNT WHERE mes atual |
| Vagas Abertas | VacancyOpening | COUNT WHERE status IN (1,2,3) |
| Tempo Medio Reposicao | View vw_rh_lifecycle | AVG(days_to_replace) |
| Taxa Turnover | Calculado | (desligamentos / headcount) * 100 |
| SLA Vagas (%) | VacancyOpening | % vagas dentro do SLA |

**Graficos:**
- **Line:** Admissoes vs Desligamentos (12 meses)
- **Funnel:** Pipeline de vagas (Aberta → Processando → Admissao → Finalizada)
- **Bar:** Turnover por loja
- **Doughnut:** Motivos de desligamento
- **Stacked Bar:** Vagas por tipo (Substituicao vs Aumento) por loja

---

### Fase I-7: Testes de Integracao (12h)

**Arquivo novo:** `tests/Integration/RHModulesIntegrationTest.php`

| Teste | Cenario |
|-------|---------|
| `testDismissalCreatesVacancy` | Criar movimento com checkbox → vaga criada automaticamente |
| `testVacancyLinkedToMoviment` | Verificar `origin_moviment_id` e `generated_vacancy_id` |
| `testVacancyPreRegistersEmployee` | Finalizar vaga → pre-cadastrar → verificar dados herdados |
| `testEmployeeLinkedToVacancy` | Verificar `origin_vacancy_id` e `hired_employee_id` |
| `testFullLifecycle` | Desligamento → Vaga → Pre-cadastro → Admissao (fluxo completo) |
| `testCrossLinks` | Verificar que todos os links cruzados retornam dados corretos |
| `testVwRhLifecycleView` | Verificar que a view SQL retorna dados completos |
| `testDashboardRHKpis` | Verificar calculos dos KPIs |

---

## 9. Cronograma Consolidado

### Ordem de Execucao Recomendada

A execucao e paralela quando possivel, mas respeita dependencias entre modulos.

```
Semana 1-2: FUNDACAO (paralelo)
├── Func. Fase 1: Service Layer + State Machine ............ 20h
├── MP Fase 1: Service Layer + State Machine ............... 24h
├── AV Fase 1: Service Layer + State Machine ............... 20h
└── Integ. Fase I-2: Migration de integracao ............... 4h
                                                        Total: 68h

Semana 3: INFRAESTRUTURA (paralelo)
├── Func. Fase 2: Soft-Delete + Status History ............. 16h
├── MP Fase 2: Soft-Delete + Status History ................ 14h
├── AV Fase 2: Soft-Delete + Status History ................ 14h
└── MP Fase 3: Padronizacao de Codigo ...................... 8h
                                                        Total: 52h

Semana 4: QUALIDADE (paralelo)
├── Func. Fase 3: Seguranca JavaScript ..................... 6h
├── MP Fase 4: Seguranca JavaScript ........................ 6h
├── AV Fase 4: Seguranca JavaScript ........................ 6h
└── AV Fase 3: Documentacao + Testes ....................... 20h
                                                        Total: 38h

Semana 5-6: INTEGRACAO
├── Integ. Fase I-1: VacancyIntegrationService ............. 12h
├── Integ. Fase I-3: Fluxo A (Desligamento → Vaga) ........ 8h
├── Integ. Fase I-4: Fluxo B (Vaga → Pre-cadastro) ........ 8h
├── Integ. Fase I-5: Links cruzados ........................ 6h
├── Func. Fase 6: Integracao receptor de vagas ............. 12h
├── MP Fase 7: Integracao disparar vaga .................... 12h
└── AV Fase 6: Integracao pre-cadastro + recepcao ......... 16h
                                                        Total: 74h

Semana 7-8: TESTES + ANALYTICS (paralelo)
├── Func. Fase 5: Testes completos ......................... 16h
├── MP Fase 6: Testes completos ............................ 14h
├── Integ. Fase I-7: Testes de integracao .................. 12h
├── Func. Fase 4: Dashboard + Relatorios ................... 24h
├── MP Fase 5: Dashboard + Relatorios ...................... 24h
└── AV Fase 5: Export + Relatorios + Dashboard ............. 28h
                                                        Total: 118h

Semana 9: DASHBOARD UNIFICADO
└── Integ. Fase I-6: Dashboard RH unificado ................ 16h
                                                        Total: 16h
```

### Resumo de Esforco

| Modulo | Esforco Total | Fases |
|--------|:---:|:---:|
| **Funcionarios** | 94h | 6 fases |
| **Movimento de Pessoal** | 112h | 7 fases |
| **Abertura de Vagas** | 104h | 6 fases |
| **Integracao** | 66h | 7 fases |
| **TOTAL** | **376h** | **26 fases** |

---

## 10. Riscos e Mitigacoes

### R1: Impacto em producao (soft-delete migration)

**Risco:** ALTER TABLE em tabelas com muitos registros pode causar lock
**Mitigacao:** Executar migrations em horario de baixo uso (noite/fim de semana); usar `ALGORITHM=INPLACE` quando possivel

### R2: Regressao nos modules existentes

**Risco:** Refatoracao dos models pode quebrar funcionalidades existentes
**Mitigacao:** Manter metodos publicos com mesma assinatura; services sao adicionados (nao substituem); testes antes e depois de cada fase

### R3: Inconsistencia de dados na integracao

**Risco:** Vaga criada automaticamente com dados incorretos (cargo errado, loja errada)
**Mitigacao:** `VacancyIntegrationService` valida todos os dados antes de criar; rollback se qualquer campo falhar; log detalhado

### R4: Performance do Dashboard RH

**Risco:** Queries cruzando 3 tabelas com JOINs podem ser lentas
**Mitigacao:** View SQL `vw_rh_lifecycle` pre-computa os JOINs; indices compostos nas FKs de integracao; cache em `SelectCacheService`

### R5: Complexidade de manutencao

**Risco:** VacancyIntegrationService cria acoplamento entre modulos
**Mitigacao:** Service e o unico ponto de acoplamento (inversao de dependencia); cada modulo continua funcionando independentemente; integracao e opcional (checkbox, botao)

### R6: Abertura de Vagas como gargalo

**Risco:** Modulo menos maduro (30% alinhado) recebe integracao
**Mitigacao:** Fases 1-3 do AV devem ser concluidas ANTES da integracao; testes de AV devem passar antes de conectar com outros modulos

---

## Apendice A: Arquivos por Fase (Checklist)

### Funcionarios

```
Fase 1 (Novos):
  app/adms/Models/constants/EmployeeStatus.php
  app/adms/Services/EmployeeLifecycleService.php
  app/adms/Services/EmployeeContractService.php

Fase 1 (Alterados):
  app/adms/Models/AdmsAddEmployee.php
  app/adms/Models/AdmsEditEmployee.php

Fase 2 (Novos):
  database/migrations/2026_04_employees_modernization.sql
  app/adms/Services/EmployeeDeleteService.php

Fase 2 (Alterados):
  app/adms/Models/AdmsDeleteEmployee.php
  app/adms/Models/AdmsListEmployee.php
  app/adms/Models/AdmsStatisticsEmployees.php
  app/adms/Models/AdmsExportEmployee.php
  app/adms/Models/AdmsViewEmployee.php

Fase 3 (Alterados):
  assets/js/employees.js

Fase 4 (Novos):
  app/adms/Controllers/ReportEmployees.php
  app/adms/Models/AdmsReportEmployees.php
  app/adms/Views/employee/partials/_dashboard_modal.php
  app/adms/Views/employee/partials/_report_modal.php

Fase 5 (Novos):
  tests/Employees/EmployeeLifecycleServiceTest.php
  tests/Employees/EmployeeContractServiceTest.php
  tests/Employees/EmployeeDeleteServiceTest.php
  tests/Employees/EmployeeWorkflowIntegrationTest.php
  tests/Employees/EmployeeDashboardTest.php

Fase 6 (Alterados):
  app/adms/Controllers/AddEmployee.php (createFromVacancy)
  app/adms/Views/employee/partials/_view_employee_details.php (link vaga)
```

### Movimento de Pessoal

```
Fase 1 (Novos):
  app/adms/Models/constants/PersonnelMovimentStatus.php
  app/adms/Services/PersonnelMovimentTransitionService.php
  app/adms/Services/DismissalNotificationService.php
  app/adms/Services/EmployeeInactivationService.php

Fase 1 (Alterados):
  app/adms/Models/AdmsAddPersonnelMoviments.php
  app/adms/Models/AdmsEditPersonnelMoviments.php

Fase 2 (Novos):
  database/migrations/2026_04_personnel_moviments_modernization.sql
  app/adms/Services/PersonnelMovimentDeleteService.php

Fase 2 (Alterados):
  app/adms/Models/AdmsDeletePersonnelMoviments.php
  app/adms/Models/PersonnelMovimentsRepository.php

Fase 3 (Alterados):
  app/adms/Controllers/PersonnelMoviments.php (match expressions)
  assets/js/personnelMoviments.js → personnel-moviments.js (rename)
  app/adms/Views/personnelMoviments/loadPersonnelMoviments.php (ref JS)
  app/adms/Models/AdmsEditPersonnelMoviments.php (path upload)

Fase 3 (Removidos):
  app/adms/Models/AdmsListPersonnelMovements.php (legado)

Fase 4 (Alterados):
  assets/js/personnel-moviments.js

Fase 5 (Novos):
  app/adms/Controllers/ReportPersonnelMoviments.php
  app/adms/Models/AdmsReportPersonnelMoviments.php
  app/adms/Views/personnelMoviments/partials/_dashboard_modal.php
  app/adms/Views/personnelMoviments/partials/_report_modal.php

Fase 6 (Novos):
  tests/PersonnelMoviments/PersonnelMovimentTransitionServiceTest.php
  tests/PersonnelMoviments/DismissalNotificationServiceTest.php
  tests/PersonnelMoviments/EmployeeInactivationServiceTest.php
  tests/PersonnelMoviments/PersonnelMovimentDeleteServiceTest.php
  tests/PersonnelMoviments/PersonnelMovimentWorkflowTest.php

Fase 7 (Alterados):
  app/adms/Views/personnelMoviments/partials/_add_moviment_modal.php (checkbox)
  app/adms/Models/AdmsAddPersonnelMoviments.php (integracao vaga)
  app/adms/Views/personnelMoviments/partials/_view_moviment_content.php (link vaga)
```

### Abertura de Vagas

```
Fase 1 (Novos):
  app/adms/Models/constants/VacancyOpeningStatus.php
  app/adms/Services/VacancyTransitionService.php
  app/adms/Services/VacancyRecruitmentService.php

Fase 1 (Alterados):
  app/adms/Models/AdmsAddVacancyOpening.php
  app/adms/Models/AdmsEditVacancyOpening.php

Fase 2 (Novos):
  database/migrations/2026_04_vacancy_opening_modernization.sql
  app/adms/Services/VacancyDeleteService.php

Fase 2 (Alterados):
  app/adms/Models/AdmsDeleteVacancyOpening.php
  app/adms/Models/AdmsListVacancyOpenings.php
  app/adms/Models/AdmsStatisticsVacancyOpenings.php

Fase 3 (Novos):
  docs/modules/MODULO_VACANCY_OPENING.md
  tests/VacancyOpening/VacancyTransitionServiceTest.php
  tests/VacancyOpening/VacancyRecruitmentServiceTest.php
  tests/VacancyOpening/VacancyDeleteServiceTest.php
  tests/VacancyOpening/AdmsAddVacancyOpeningTest.php
  tests/VacancyOpening/AdmsDeleteVacancyOpeningTest.php
  tests/VacancyOpening/AdmsViewVacancyOpeningTest.php
  tests/VacancyOpening/AdmsStatisticsVacancyOpeningsTest.php
  tests/VacancyOpening/VacancyWorkflowIntegrationTest.php
  tests/VacancyOpening/VacancyIntegrationServiceTest.php

Fase 4 (Alterados):
  assets/js/vacancy-opening.js

Fase 5 (Novos):
  app/adms/Controllers/ExportVacancyOpening.php
  app/adms/Models/AdmsExportVacancyOpening.php
  app/adms/Controllers/ReportVacancyOpening.php
  app/adms/Models/AdmsReportVacancyOpening.php
  app/adms/Views/vacancyOpening/partials/_dashboard_modal.php
  app/adms/Views/vacancyOpening/partials/_report_modal.php

Fase 6 (Alterados):
  app/adms/Views/vacancyOpening/partials/_view_vacancy_opening_content.php (botao + links)
```

### Integracao

```
Fase I-1 (Novos):
  app/adms/Services/VacancyIntegrationService.php

Fase I-2 (Novos):
  database/migrations/2026_04_rh_modules_integration.sql

Fase I-6 (Novos):
  app/adms/Controllers/DashboardRH.php
  app/adms/Models/AdmsDashboardRH.php
  app/adms/Views/dashboardRH/loadDashboardRH.php
  app/adms/Views/dashboardRH/partials/_kpi_cards.php
  app/adms/Views/dashboardRH/partials/_charts.php
  assets/js/dashboard-rh.js

Fase I-7 (Novos):
  tests/Integration/RHModulesIntegrationTest.php
```

---

## Apendice B: Metricas de Sucesso

### Antes vs Depois (Metas)

| Metrica | Atual | Meta |
|---------|:---:|:---:|
| Alinhamento Func. c/ OrderPayments | 65% | 90%+ |
| Alinhamento MP c/ OrderPayments | 50% | 85%+ |
| Alinhamento AV c/ OrderPayments | 30% | 85%+ |
| Services dedicados (total 3 modulos) | 0 | 12 |
| Testes (total 3 modulos) | ~37 | 190+ |
| Documentacao (docs) | 2 | 4+ |
| Integracoes ativas | 0 | 3 fluxos |
| Tempo manual desligamento→vaga | Manual (~30min) | Automatico (1 click) |
| Tempo manual vaga→cadastro | Manual (~20min) | Pre-preenchido (5min) |

---

**Documento gerado em:** 2026-04-02
**Baseado na analise comparativa com OrderPayments (modulo referencia)**
**Mantido por:** Equipe Mercury — Grupo Meia Sola
