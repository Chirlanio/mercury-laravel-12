# Plano de Acao: Integracao entre Modulos de RH

**Referencia:** `docs/ANALISE_INTEGRACAO_RH_MODULES.md` v1.0
**Modulos:** Funcionarios, Movimento de Pessoal, Abertura de Vagas
**Modelo:** Ordem de Pagamento (OrderPayments)
**Data:** 2026-04-02
**Ultima Atualizacao:** 2026-04-02
**Projeto:** Mercury

---

## Visao Geral

Criar integracoes nativas entre os 3 modulos de RH para formar o **Ciclo de Vida do Colaborador**, eliminando retrabalho manual e garantindo rastreabilidade completa entre desligamentos, vagas e contratacoes.

### Fluxos de Integracao

```
┌──────────────────────────────────────────────────────────────────────────┐
│                    CICLO DE VIDA DO COLABORADOR                          │
└──────────────────────────────────────────────────────────────────────────┘

 DESLIGAMENTO                  ABERTURA DE VAGA              ADMISSAO
 ═══════════                   ════════════════              ════════
 PersonnelMoviments            VacancyOpening               Employees

 ┌──────────┐  Fluxo A (auto) ┌──────────┐                ┌──────────┐
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
 ┌──────────┐                 ┌──────────┐  Fluxo B       ┌──────────┐
 │Concluido │                 │Em Admiss.│ ─────────────► │AddEmployee│
 │   (3)    │                 │   (3)    │ (pre-cadastro) │(pre-pop) │
 └──────────┘                 └────┬─────┘                └──────────┘
                                   │
                              ┌────┴────┐
                              ▼         ▼
                         ┌────────┐ ┌────────┐
                         │Finaliz.│ │Cancelad│
                         │  (4)   │ │  (5)   │
                         └────────┘ └────────┘
```

### Metricas de Sucesso

| Metrica | Antes | Depois |
|---------|:---:|:---:|
| Tempo manual desligamento → vaga | ~30min | 1 click (automatico) |
| Tempo manual vaga → cadastro | ~20min | 5min (pre-preenchido) |
| Rastreabilidade desligamento → contratacao | Inexistente | 100% |
| Integracoes ativas entre modulos | 0 | 3 fluxos |

---

### Status das Fases

| Fase | Status | Esforco | Dependencia |
|---|---|---|---|
| **Fase I-1:** VacancyIntegrationService | Pendente | 12h | MP Fase 1 + AV Fase 1 |
| **Fase I-2:** Migration de integracao | Pendente | 4h | Nenhuma (pode antecipar) |
| **Fase I-3:** Fluxo A (Desligamento → Vaga) | Pendente | 8h | I-1 + MP Fase 7 |
| **Fase I-4:** Fluxo B (Vaga → Pre-cadastro) | Pendente | 8h | I-1 + Func. Fase 6 + AV Fase 6 |
| **Fase I-5:** Links cruzados entre modulos | Pendente | 6h | I-3 + I-4 |
| **Fase I-6:** Dashboard RH unificado | Pendente | 16h | Todas anteriores |
| **Fase I-7:** Testes de integracao | Pendente | 12h | I-3 + I-4 |

**Esforco total estimado: 66h**

---

## Arquitetura de Dados

### Diagrama de Relacionamentos

```
adms_personnel_moviments (existente)
├── adms_employee_id FK → adms_employees.id (funcionario desligado)
├── adms_loja_id FK → tb_lojas.id
├── open_vacancy (NOVO) → flag para abrir vaga automaticamente
└── generated_vacancy_id (NOVO) FK → adms_vacancy_opening.id

adms_vacancy_opening (existente)
├── adms_employee_id FK → adms_employees.id (substituicao)
├── adms_loja_id FK → tb_lojas.id
├── adms_cargo_id FK → tb_cargos.id
├── origin_moviment_id (NOVO) FK → adms_personnel_moviments.id
└── hired_employee_id (NOVO) FK → adms_employees.id (contratado)

adms_employees (existente)
├── adms_store_id FK → tb_lojas.id
├── position_id FK → tb_cargos.id
└── origin_vacancy_id (NOVO) FK → adms_vacancy_opening.id

vw_rh_lifecycle (NOVA VIEW)
└── JOIN cruzado entre os 3 modulos para consultas e dashboard
```

---

## Fase I-1: VacancyIntegrationService (12h)

**Dependencia:** MP Fase 1 + AV Fase 1 (services base devem existir)

### Service centralizado

**Arquivo:** `app/adms/Services/VacancyIntegrationService.php`

Este service e o unico ponto de acoplamento entre os modulos. Cada modulo continua funcionando independentemente — a integracao e opcional.

```php
<?php
namespace App\adms\Services;

use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsCreate;
use App\adms\Models\helper\AdmsUpdate;
use App\adms\Models\constants\VacancyOpeningStatus;

class VacancyIntegrationService
{
    /**
     * Cria vaga de substituicao a partir de um movimento de desligamento
     *
     * Dados herdados do movimento/funcionario:
     * - adms_loja_id (loja do desligamento)
     * - adms_cargo_id (cargo do funcionario desligado)
     * - adms_employee_id (referencia ao desligado)
     * - adms_request_type_id = 1 (Substituicao)
     * - predicted_sla = calculado automaticamente
     * - origin_moviment_id = ID do movimento
     *
     * @param int $movimentId ID do movimento de pessoal
     * @param array $movimentData Dados do movimento
     * @return int|null ID da vaga criada ou null em caso de erro
     */
    public function createFromMoviment(int $movimentId, array $movimentData): ?int
    {
        // 1. Carregar dados do funcionario (cargo, area)
        $read = new AdmsRead();
        $read->fullRead(
            "SELECT position_id, adms_area_id FROM adms_employees WHERE id = :id LIMIT 1",
            "id={$movimentData['adms_employee_id']}"
        );
        $employee = $read->getResult()[0] ?? null;
        if (!$employee) return null;

        // 2. Preparar dados da vaga
        $vacancyData = [
            'adms_loja_id' => $movimentData['adms_loja_id'],
            'adms_cargo_id' => $employee['position_id'],
            'adms_employee_id' => $movimentData['adms_employee_id'],
            'adms_request_type_id' => 1,  // Substituicao
            'adms_sit_vacancy_id' => VacancyOpeningStatus::OPEN,
            'origin_moviment_id' => $movimentId,
            'created_by' => SessionContext::getUserId(),
            'created_by_name' => SessionContext::getUserName(),
            'created' => date('Y-m-d H:i:s'),
        ];

        // 3. Calcular SLA
        $userLevel = SessionContext::getAccessLevel();
        $vacancyData['predicted_sla'] = ($userLevel <= 2) ? 20 : 40;
        $vacancyData['delivery_forecast'] = date('Y-m-d', strtotime("+{$vacancyData['predicted_sla']} days"));

        // 4. Inserir vaga
        $create = new AdmsCreate();
        $create->exeCreate('adms_vacancy_opening', $vacancyData);
        if (!$create->getResult()) {
            LoggerService::error('VACANCY_AUTO_CREATE_FAILED', 'Erro ao criar vaga automatica', [
                'moviment_id' => $movimentId,
            ]);
            return null;
        }

        // 5. Obter ID da vaga criada
        $read->fullRead("SELECT MAX(id) as id FROM adms_vacancy_opening");
        $vacancyId = (int) ($read->getResult()[0]['id'] ?? 0);

        // 6. Registrar historico (via VacancyTransitionService se disponivel)
        // 7. Notificar RH via WebSocket
        try {
            SystemNotificationService::notifyUsers(
                NotificationRecipientService::resolveRecipients('hr_admins', null),
                'vacancy',
                'auto_created',
                'Vaga de substituicao criada automaticamente',
                "Vaga #{$vacancyId} criada a partir do desligamento #{$movimentId}",
                'fa-briefcase',
                'info',
                URLADM . "vacancy-opening/list"
            );
        } catch (\Exception $e) {
            // Fire-and-forget: nao bloquear operacao principal
        }

        // 8. Log
        LoggerService::info('VACANCY_AUTO_CREATED', 'Vaga criada via desligamento', [
            'vacancy_id' => $vacancyId,
            'moviment_id' => $movimentId,
            'store_id' => $movimentData['adms_loja_id'],
        ]);

        return $vacancyId;
    }

    /**
     * Prepara dados da vaga para pre-preencher formulario de AddEmployee
     *
     * @param int $vacancyId ID da vaga
     * @return array Dados formatados para o formulario de funcionario
     */
    public function prepareEmployeeData(int $vacancyId): array
    {
        $read = new AdmsRead();
        $read->fullRead(
            "SELECT vo.adms_loja_id, vo.adms_cargo_id, vo.adms_work_schedule_id,
                    vo.date_admission, vo.id as vacancy_id,
                    l.nome as store_name, c.nome as position_name
             FROM adms_vacancy_opening vo
             LEFT JOIN tb_lojas l ON l.id = vo.adms_loja_id
             LEFT JOIN tb_cargos c ON c.id = vo.adms_cargo_id
             WHERE vo.id = :id AND vo.deleted_at IS NULL LIMIT 1",
            "id={$vacancyId}"
        );

        $vacancy = $read->getResult()[0] ?? null;
        if (!$vacancy) return [];

        return [
            'adms_store_id' => $vacancy['adms_loja_id'],
            'position_id' => $vacancy['adms_cargo_id'],
            'work_schedule_id' => $vacancy['adms_work_schedule_id'],
            'date_admission' => $vacancy['date_admission'],
            'origin_vacancy_id' => $vacancy['vacancy_id'],
            '_meta' => [
                'store_name' => $vacancy['store_name'],
                'position_name' => $vacancy['position_name'],
            ],
        ];
    }

    /**
     * Vincula vaga ao funcionario contratado (bidirecional)
     */
    public function linkVacancyToEmployee(int $vacancyId, int $employeeId): bool
    {
        $update = new AdmsUpdate();

        // Atualizar vaga com hired_employee_id
        $update->exeUpdate('adms_vacancy_opening', [
            'hired_employee_id' => $employeeId,
            'modified' => date('Y-m-d H:i:s'),
        ], "WHERE id = :id", "id={$vacancyId}");

        // Atualizar employee com origin_vacancy_id
        $update->exeUpdate('adms_employees', [
            'origin_vacancy_id' => $vacancyId,
            'modified_at' => date('Y-m-d H:i:s'),
        ], "WHERE id = :id", "id={$employeeId}");

        LoggerService::info('VACANCY_LINKED_TO_EMPLOYEE', 'Vaga vinculada ao funcionario', [
            'vacancy_id' => $vacancyId,
            'employee_id' => $employeeId,
        ]);

        return true;
    }

    /**
     * Vincula movimento a vaga gerada
     */
    public function linkMovimentToVacancy(int $movimentId, int $vacancyId): bool
    {
        $update = new AdmsUpdate();
        $update->exeUpdate('adms_personnel_moviments', [
            'generated_vacancy_id' => $vacancyId,
            'modified' => date('Y-m-d H:i:s'),
        ], "WHERE id = :id", "id={$movimentId}");

        LoggerService::info('MOVIMENT_LINKED_TO_VACANCY', 'Movimento vinculado a vaga', [
            'moviment_id' => $movimentId,
            'vacancy_id' => $vacancyId,
        ]);

        return true;
    }
}
```

### Checklist Fase I-1

- [ ] Criar `app/adms/Services/VacancyIntegrationService.php`
- [ ] Implementar `createFromMoviment()`
- [ ] Implementar `prepareEmployeeData()`
- [ ] Implementar `linkVacancyToEmployee()`
- [ ] Implementar `linkMovimentToVacancy()`
- [ ] Testar cada metodo isoladamente

---

## Fase I-2: Migration de Integracao (4h)

**Dependencia:** Nenhuma (pode antecipar — apenas adiciona colunas nullable)

### Migration SQL

**Arquivo:** `database/migrations/2026_04_rh_modules_integration.sql`

```sql
-- =============================================
-- Migration: Integracao Modulos RH
-- Data: 2026-04-XX
-- Collation: utf8mb4_unicode_ci
-- Descricao: Adiciona campos de rastreabilidade entre
--            PersonnelMoviments, VacancyOpening e Employees
-- =============================================

-- -----------------------------------------------
-- 1. Campos de integracao em adms_vacancy_opening
-- -----------------------------------------------
ALTER TABLE adms_vacancy_opening
    ADD COLUMN IF NOT EXISTS origin_moviment_id INT NULL DEFAULT NULL
        COMMENT 'FK: adms_personnel_moviments.id — Desligamento que originou esta vaga',
    ADD COLUMN IF NOT EXISTS hired_employee_id INT NULL DEFAULT NULL
        COMMENT 'FK: adms_employees.id — Funcionario contratado via esta vaga',
    ADD INDEX IF NOT EXISTS idx_vo_origin_moviment (origin_moviment_id),
    ADD INDEX IF NOT EXISTS idx_vo_hired_employee (hired_employee_id);

-- -----------------------------------------------
-- 2. Campos de integracao em adms_employees
-- -----------------------------------------------
ALTER TABLE adms_employees
    ADD COLUMN IF NOT EXISTS origin_vacancy_id INT NULL DEFAULT NULL
        COMMENT 'FK: adms_vacancy_opening.id — Vaga que originou esta contratacao',
    ADD INDEX IF NOT EXISTS idx_emp_origin_vacancy (origin_vacancy_id);

-- -----------------------------------------------
-- 3. Campos de integracao em adms_personnel_moviments
-- -----------------------------------------------
ALTER TABLE adms_personnel_moviments
    ADD COLUMN IF NOT EXISTS open_vacancy TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Flag: abrir vaga de substituicao automaticamente',
    ADD COLUMN IF NOT EXISTS generated_vacancy_id INT NULL DEFAULT NULL
        COMMENT 'FK: adms_vacancy_opening.id — Vaga gerada por este desligamento',
    ADD INDEX IF NOT EXISTS idx_pm_gen_vacancy (generated_vacancy_id);

-- -----------------------------------------------
-- 4. View de integracao para consultas cruzadas
-- -----------------------------------------------
CREATE OR REPLACE VIEW vw_rh_lifecycle AS
SELECT
    -- Movimento de Pessoal (desligamento)
    pm.id                           AS moviment_id,
    pm.adms_loja_id                 AS store_id,
    l.nome                          AS store_name,
    pm.adms_employee_id             AS dismissed_employee_id,
    emp_d.name_employee             AS dismissed_employee_name,
    emp_d.position_id               AS dismissed_position_id,
    c_d.nome                        AS dismissed_position_name,
    pm.last_day_worked,
    pm.adms_sits_personnel_mov_id   AS moviment_status,

    -- Vaga (substituicao)
    vo.id                           AS vacancy_id,
    vo.adms_sit_vacancy_id          AS vacancy_status,
    vo.predicted_sla,
    vo.effective_sla,
    vo.closing_date                 AS vacancy_closing_date,
    vo.adms_recruiter_id            AS recruiter_id,

    -- Funcionario contratado
    emp_h.id                        AS hired_employee_id,
    emp_h.name_employee             AS hired_employee_name,
    emp_h.date_admission            AS hire_date,

    -- Metricas calculadas
    DATEDIFF(emp_h.date_admission, pm.last_day_worked) AS days_to_replace,
    DATEDIFF(vo.closing_date, vo.created)               AS days_to_fill_vacancy

FROM adms_personnel_moviments pm
LEFT JOIN adms_vacancy_opening vo ON vo.origin_moviment_id = pm.id
    AND vo.deleted_at IS NULL
LEFT JOIN adms_employees emp_d ON emp_d.id = pm.adms_employee_id
LEFT JOIN adms_employees emp_h ON emp_h.id = vo.hired_employee_id
LEFT JOIN tb_lojas l ON l.id = pm.adms_loja_id
LEFT JOIN tb_cargos c_d ON c_d.id = emp_d.position_id
WHERE pm.deleted_at IS NULL;
```

### Checklist Fase I-2

- [ ] Executar migration SQL
- [ ] Verificar que colunas foram criadas (nullable, sem impacto em dados existentes)
- [ ] Verificar que view `vw_rh_lifecycle` retorna dados (mesmo sem vinculos)
- [ ] Testar que modulos existentes continuam funcionando (colunas sao nullable)

---

## Fase I-3: Fluxo A — Desligamento gera Vaga (8h)

**Dependencia:** I-1 + Mov. Pessoal Fase 7

### Implementacao

1. **Checkbox no formulario de criacao do Movimento**
   - Alterar `_add_moviment_modal.php`: adicionar switch "Abrir vaga de substituicao"
   - Campo `open_vacancy` (tinyint, default 0)

2. **Logica no model de criacao**
   - Alterar `AdmsAddPersonnelMoviments.php`: apos `insertMoviment()`, se `open_vacancy == 1`:
     - Chamar `VacancyIntegrationService::createFromMoviment()`
     - Chamar `VacancyIntegrationService::linkMovimentToVacancy()`
     - Log: `VACANCY_AUTO_CREATED`

3. **Badge no view do Movimento**
   - Alterar `_view_moviment_content.php`: se `generated_vacancy_id`, mostrar link para a vaga

### Fluxo detalhado

```
Usuario cria Movimento de Pessoal
    ↓
Marca checkbox "Abrir vaga de substituicao"
    ↓
AdmsAddPersonnelMoviments::insertMoviment() com sucesso
    ↓
VacancyIntegrationService::createFromMoviment()
    ├── Carrega cargo/area do funcionario desligado
    ├── Cria vaga tipo Substituicao com dados herdados
    ├── Calcula SLA (20 ou 40 dias)
    ├── Notifica RH via WebSocket
    └── Retorna vacancy_id
    ↓
VacancyIntegrationService::linkMovimentToVacancy()
    └── Grava generated_vacancy_id no movimento
    ↓
View do Movimento mostra: "Vaga #X criada automaticamente"
```

### Checklist Fase I-3

- [ ] Checkbox no formulario de criacao
- [ ] Integrar `VacancyIntegrationService` no model de criacao
- [ ] Badge no view do movimento
- [ ] Testar: criar movimento com checkbox → vaga criada → link visivel
- [ ] Testar: criar movimento SEM checkbox → nenhuma vaga criada

---

## Fase I-4: Fluxo B — Vaga gera Pre-cadastro (8h)

**Dependencia:** I-1 + Funcionarios Fase 6 + Ab. Vagas Fase 6

### Implementacao

1. **Botao no view da Vaga**
   - Alterar `_view_vacancy_opening_content.php`: botao "Pre-cadastrar Colaborador" (status >= 3)
   - Visivel apenas se `hired_employee_id` ainda vazio

2. **Endpoint no AddEmployee**
   - Criar metodo `createFromVacancy(?int $vacancyId)` em `AddEmployee.php`
   - Carrega dados via `VacancyIntegrationService::prepareEmployeeData()`
   - Renderiza formulario pre-preenchido

3. **Vinculo apos criacao**
   - Apos criar funcionario, chamar `VacancyIntegrationService::linkVacancyToEmployee()`
   - Vaga recebe `hired_employee_id`
   - Employee recebe `origin_vacancy_id`

4. **Card de origem no ViewEmployee**
   - Se `origin_vacancy_id` preenchido, mostrar link para a vaga

### Fluxo detalhado

```
Vaga atinge status "Em Admissao" (3) ou "Finalizada" (4)
    ↓
Botao "Pre-cadastrar Colaborador" visivel no view
    ↓
Clique redireciona para AddEmployee::createFromVacancy($vacancyId)
    ↓
VacancyIntegrationService::prepareEmployeeData($vacancyId)
    ├── Carrega: loja, cargo, escala, data admissao
    └── Retorna array com campos formatados
    ↓
Formulario de AddEmployee renderizado com campos pre-preenchidos
    ↓
Usuario completa dados restantes (nome, CPF, foto, etc.)
    ↓
AdmsAddEmployee::create() com sucesso
    ↓
VacancyIntegrationService::linkVacancyToEmployee()
    ├── Vaga.hired_employee_id = employee.id
    └── Employee.origin_vacancy_id = vacancy.id
    ↓
ViewEmployee mostra: "Contratado via Vaga #X"
ViewVacancy mostra: "Colaborador contratado: Fulano (#Y)"
```

### Checklist Fase I-4

- [ ] Botao de pre-cadastro na view da vaga
- [ ] Metodo `createFromVacancy()` no AddEmployee
- [ ] Integrar `prepareEmployeeData()` e `linkVacancyToEmployee()`
- [ ] Card de origem no ViewEmployee
- [ ] Card de contratado no ViewVacancy
- [ ] Testar fluxo completo

---

## Fase I-5: Links Cruzados entre Modulos (6h)

**Dependencia:** I-3 + I-4

### Links a implementar

| View | Condicao | Link |
|------|----------|------|
| ViewEmployee | `origin_vacancy_id` preenchido | "Contratado via Vaga #X" → ViewVacancy |
| ViewVacancy | `origin_moviment_id` preenchido | "Gerada pelo Desligamento #X" → ViewMoviment |
| ViewVacancy | `hired_employee_id` preenchido | "Colaborador contratado: Fulano" → ViewEmployee |
| ViewMoviment | `generated_vacancy_id` preenchido | "Vaga de substituicao: #X" → ViewVacancy |

### Implementacao

Cada link usa `onclick` para abrir modal do modulo correspondente:

```html
<!-- Exemplo: ViewEmployee → Vaga -->
<?php if (!empty($this->Dados['origin_vacancy_id'])): ?>
<div class="alert alert-info mb-3">
    <i class="fas fa-link"></i> Contratado via
    <a href="<?= URLADM ?>vacancy-opening/list"
       onclick="event.preventDefault(); viewVacancyOpening(<?= (int)$this->Dados['origin_vacancy_id'] ?>)">
        Vaga #<?= (int)$this->Dados['origin_vacancy_id'] ?>
    </a>
</div>
<?php endif; ?>
```

### Checklist Fase I-5

- [ ] Link no ViewEmployee (vaga de origem)
- [ ] Link no ViewVacancy (movimento de origem)
- [ ] Link no ViewVacancy (funcionario contratado)
- [ ] Link no ViewMoviment (vaga gerada)
- [ ] Testar todos os 4 links em ambas as direcoes

---

## Fase I-6: Dashboard RH Unificado (16h)

**Dependencia:** Todas as fases anteriores

### Arquitetura

**Novos arquivos:**
- `app/adms/Controllers/DashboardRH.php`
- `app/adms/Models/AdmsDashboardRH.php`
- `app/adms/Views/dashboardRH/loadDashboardRH.php`
- `app/adms/Views/dashboardRH/partials/_kpi_cards.php`
- `app/adms/Views/dashboardRH/partials/_charts.php`
- `assets/js/dashboard-rh.js`

### KPI Cards

| Card | Fonte | Query |
|------|-------|-------|
| Headcount Ativo | adms_employees | `COUNT WHERE status=2 AND deleted_at IS NULL` |
| Desligamentos (mes atual) | adms_personnel_moviments | `COUNT WHERE MONTH(created) = MONTH(NOW())` |
| Vagas Abertas | adms_vacancy_opening | `COUNT WHERE status IN (1,2,3) AND deleted_at IS NULL` |
| Tempo Medio Reposicao | vw_rh_lifecycle | `AVG(days_to_replace) WHERE days_to_replace IS NOT NULL` |
| Taxa Turnover (%) | Calculado | `(desligamentos / headcount) * 100` |
| SLA Vagas (%) | adms_vacancy_opening | `% WHERE effective_sla <= predicted_sla` |

### Graficos (Chart.js)

| Grafico | Tipo | Dados |
|---------|------|-------|
| Admissoes vs Desligamentos | Line | COUNT por mes (ultimos 12 meses) |
| Pipeline de Vagas | Funnel/Bar | COUNT por status (Aberta → Processando → Admissao → Finalizada) |
| Turnover por Loja | Bar | (desligamentos / headcount) * 100 por loja |
| Motivos de Desligamento | Doughnut | COUNT por motivo (adms_reasons_for_dismissals) |
| Vagas por Tipo | Stacked Bar | Substituicao vs Aumento por loja |

### Filtros

- Periodo (date_from, date_to)
- Loja (select com permissao)
- Area (select)

### Rota

Registrar em `adms_paginas`:
- Controller: `dashboard-rh`
- Metodo: `list`
- Permissao: nivel <= 5 (gestores e acima)

### Checklist Fase I-6

- [ ] Criar controller `DashboardRH.php`
- [ ] Criar model `AdmsDashboardRH.php` (queries usando vw_rh_lifecycle)
- [ ] Criar view `loadDashboardRH.php` com grid responsivo
- [ ] Criar partial `_kpi_cards.php` (6 cards)
- [ ] Criar partial `_charts.php` (5 graficos)
- [ ] Criar `dashboard-rh.js` (Chart.js + filtros AJAX)
- [ ] Registrar rotas e permissoes
- [ ] Testar com dados reais

---

## Fase I-7: Testes de Integracao (12h)

**Dependencia:** I-3 + I-4

### Arquivo

**`tests/Integration/RHModulesIntegrationTest.php`**

### Cenarios de teste

| Teste | Cenario | Assertions |
|-------|---------|:---:|
| `testCreateFromMovimentCreatesVacancy` | Criar movimento com open_vacancy=1 → vaga criada | 5+ |
| `testCreateFromMovimentSetsOrigin` | Vaga tem origin_moviment_id preenchido | 3 |
| `testMovimentLinkedToVacancy` | Movimento tem generated_vacancy_id preenchido | 3 |
| `testCreateFromMovimentWithoutFlag` | Criar movimento sem checkbox → nenhuma vaga criada | 3 |
| `testPrepareEmployeeData` | Dados da vaga formatados para formulario | 5 |
| `testLinkVacancyToEmployee` | Vinculo bidirecional correto | 4 |
| `testPreRegisterFromVacancy` | Fluxo: vaga → pre-cadastro → employee com dados herdados | 8 |
| `testFullLifecycle` | Desligamento → vaga auto → recrutamento → pre-cadastro → admissao | 12+ |
| `testCrossLinksDataIntegrity` | Todos os links cruzados retornam dados corretos | 8 |
| `testVwRhLifecycleView` | View SQL retorna dados completos e corretos | 5 |
| `testDashboardRHKpis` | KPIs calculados corretamente | 6 |
| `testDashboardRHWithFilters` | Filtros de periodo e loja funcionam | 4 |

**Meta:** 60+ assertions

### Checklist Fase I-7

- [ ] Criar `tests/Integration/RHModulesIntegrationTest.php`
- [ ] Implementar 12 cenarios de teste
- [ ] Rodar `phpunit tests/Integration/RHModulesIntegrationTest.php` sem falhas
- [ ] Verificar que testes individuais dos 3 modulos continuam passando

---

## Cronograma de Execucao

```
Semana 1-2: FUNDACAO (paralelo com planos individuais)
└── Fase I-2: Migration de integracao .................. 4h
    (pode antecipar — apenas colunas nullable)

Semana 5-6: INTEGRACAO (apos services base prontos)
├── Fase I-1: VacancyIntegrationService ................ 12h
├── Fase I-3: Fluxo A (Desligamento → Vaga) ........... 8h
├── Fase I-4: Fluxo B (Vaga → Pre-cadastro) ........... 8h
└── Fase I-5: Links cruzados ........................... 6h

Semana 7-8: TESTES
└── Fase I-7: Testes de integracao ..................... 12h

Semana 9: DASHBOARD
└── Fase I-6: Dashboard RH unificado .................. 16h
```

---

## Riscos e Mitigacoes

### R1: Dados inconsistentes na criacao automatica de vaga

**Risco:** Cargo ou loja do funcionario desligado pode estar desatualizado
**Mitigacao:** `createFromMoviment()` busca dados frescos de `adms_employees` no momento da criacao; log detalhado para auditoria

### R2: Performance da view vw_rh_lifecycle

**Risco:** JOINs cruzando 5 tabelas podem ser lentos com volume alto
**Mitigacao:** Indices compostos nas FKs de integracao (criados na migration); cache de dashboard em `SelectCacheService`; query limitada por periodo

### R3: Acoplamento entre modulos

**Risco:** `VacancyIntegrationService` cria dependencia entre modulos
**Mitigacao:** Service e o UNICO ponto de acoplamento (inversao de dependencia); cada modulo funciona sem integracao; checkbox e botao sao opcionais; try-catch em notificacoes (fire-and-forget)

### R4: Concorrencia no Fluxo A

**Risco:** Dois usuarios criam movimento para mesmo funcionario simultaneamente
**Mitigacao:** Validacao no `VacancyIntegrationService`: verificar se ja existe vaga com `origin_moviment_id` antes de criar

### R5: Vaga orfã

**Risco:** Vaga criada automaticamente mas movimento e deletado depois
**Mitigacao:** Soft-delete do movimento NAO deleta a vaga automaticamente; vaga permanece independente; log de auditoria registra a relacao original

---

## Checklist de Arquivos

### Novos

```
app/adms/Services/VacancyIntegrationService.php
app/adms/Controllers/DashboardRH.php
app/adms/Models/AdmsDashboardRH.php
app/adms/Views/dashboardRH/loadDashboardRH.php
app/adms/Views/dashboardRH/partials/_kpi_cards.php
app/adms/Views/dashboardRH/partials/_charts.php
assets/js/dashboard-rh.js
database/migrations/2026_04_rh_modules_integration.sql
tests/Integration/RHModulesIntegrationTest.php
```

### Alterados (via planos individuais)

```
app/adms/Controllers/AddEmployee.php (createFromVacancy — Func. Fase 6)
app/adms/Models/AdmsAddPersonnelMoviments.php (checkbox vaga — MP Fase 7)
app/adms/Views/personnelMoviments/partials/_add_moviment_modal.php (checkbox — MP Fase 7)
app/adms/Views/personnelMoviments/partials/_view_moviment_content.php (link — MP Fase 7)
app/adms/Views/vacancyOpening/partials/_view_vacancy_opening_content.php (botao + badges — AV Fase 6)
app/adms/Views/employee/partials/_view_employee_details.php (link vaga — Func. Fase 6)
app/adms/Models/AdmsViewVacancyOpening.php (JOINs integracao — AV Fase 6)
```

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
