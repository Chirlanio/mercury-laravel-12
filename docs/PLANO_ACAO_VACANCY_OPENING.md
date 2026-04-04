# Plano de Acao: Modernizacao do Modulo de Abertura de Vagas (VacancyOpening)

**Referencia:** `docs/ANALISE_INTEGRACAO_RH_MODULES.md` v1.0
**Modelo:** Ordem de Pagamento (OrderPayments)
**Data:** 2026-04-02
**Ultima Atualizacao:** 2026-04-02
**Projeto:** Mercury

---

## Visao Geral

Modernizar o modulo de Abertura de Vagas — atualmente o menos maduro dos 3 modulos de RH (30% alinhado com OrderPayments) — adicionando services dedicados, state machine de status, soft-delete com auditoria, documentacao, testes abrangentes, export, dashboard com graficos, relatorios e integracao bidirecional com Movimento de Pessoal e Funcionarios.

### Alinhamento Atual: 30% → Meta: 85%+

### Integracoes Principais

| Modulo | Integracao | Impacto |
|---|---|---|
| **Movimento de Pessoal** | Recepcao automatica de vaga de substituicao criada por desligamento | Receptor |
| **Funcionarios** | Pre-cadastro de colaborador a partir de vaga finalizada; dados herdados: loja, cargo, escala, data admissao | Disparador |

---

### Status das Fases

| Fase | Status | Esforco | Data Conclusao |
|---|---|---|---|
| **Fase 1:** Service Layer + State Machine | Pendente | 20h | — |
| **Fase 2:** Soft-Delete + Status History | Pendente | 14h | — |
| **Fase 3:** Documentacao + Testes | Pendente | 20h | — |
| **Fase 4:** Seguranca JavaScript | Pendente | 6h | — |
| **Fase 5:** Export + Relatorios + Dashboard | Pendente | 28h | — |
| **Fase 6:** Integracao (pre-cadastro + recepcao) | Pendente | 16h | — |

**Esforco total estimado: 104h**

---

## Diagnostico Atual

### Arquivos Existentes

**Controllers (5):**
- `app/adms/Controllers/VacancyOpening.php` — Listagem (match expressions)
- `app/adms/Controllers/AddVacancyOpening.php` — Criacao (AJAX + SLA automatico)
- `app/adms/Controllers/EditVacancyOpening.php` — Edicao (permissoes por nivel)
- `app/adms/Controllers/DeleteVacancyOpening.php` — Exclusao (hard-delete, apenas Pendente)
- `app/adms/Controllers/ViewVacancyOpening.php` — Visualizacao

**Models (7):**
- `app/adms/Models/AdmsAddVacancyOpening.php` — SLA automatico (20/40 dias)
- `app/adms/Models/AdmsEditVacancyOpening.php` — Protecao de campos sensiveis
- `app/adms/Models/AdmsDeleteVacancyOpening.php` — Hard-delete apenas status 1
- `app/adms/Models/AdmsListVacancyOpenings.php` — Listagem com filtros
- `app/adms/Models/AdmsViewVacancyOpening.php` — Visualizacao simples
- `app/adms/Models/AdmsStatisticsVacancyOpenings.php` — KPIs com SLA

**Search:** `app/cpadms/Models/CpAdmsSearchVacancyOpening.php`
**Views:** `app/adms/Views/vacancyOpening/` — 5 principais + 5 partials
**JavaScript:** `assets/js/vacancy-opening.js` (~800 LOC)
**Testes:** **Apenas 2 arquivos** em `tests/VacancyOpening/`

### Pontos Fortes

- Match expressions no controller principal
- Gestao de SLA (20 dias para nivel 2, 40 dias para outros)
- Workflow de recrutamento (recrutador, entrevistas HR + lider, avaliadores)
- Estatisticas com metricas de SLA (dentro/fora do prazo)
- Controle por tipo: Substituicao (requer employee) vs Aumento de Quadro
- Notificacoes WebSocket

### Gaps vs OrderPayments (Maior Gap dos 3 Modulos)

| Gap | Impacto | Fase |
|-----|---------|------|
| **Nenhuma documentacao dedicada** | Critico | 3 |
| **Apenas 2 testes** (menor cobertura do projeto) | Critico | 3 |
| Sem services dedicados | Critico | 1 |
| Sem state machine (verificacao hardcoded por access level) | Critico | 1 |
| Sem status history table | Alto | 2 |
| Hard-delete sem auditoria | Alto | 2 |
| Sem `escapeHtml()` no JS | Alto | 4 |
| Sem export dedicado | Medio | 5 |
| Sem dashboard/graficos | Medio | 5 |
| Sem relatorios | Medio | 5 |
| Sem API REST | Baixo | Futuro |
| Classificado como LEGACY no CHECKLIST_MODULOS.md | — | Todas |

---

## Fase 1: Service Layer + State Machine (20h)

**Dependencia:** Nenhuma

### 1.1 Criar `VacancyOpeningStatus.php` (Constants)

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

    /** Status terminais (nao permitem transicao) */
    const TERMINAL = [self::FINALIZED, self::CANCELED];
}
```

### 1.2 Criar `VacancyTransitionService.php`

**Arquivo:** `app/adms/Services/VacancyTransitionService.php`

Seguindo padrao `OrderPaymentTransitionService`:

```php
<?php
namespace App\adms\Services;

use App\adms\Models\constants\VacancyOpeningStatus;
use App\adms\Models\helper\AdmsRead;
use App\adms\Models\helper\AdmsCreate;
use App\adms\Models\helper\AdmsUpdate;

class VacancyTransitionService
{
    /**
     * Valida se a transicao de status e permitida
     * @return array {valid: bool, errors: string[]}
     */
    public function validateTransition(int $from, int $to, array $data): array
    {
        // 1. Verificar se $to esta em TRANSITIONS[$from]
        // 2. Validar REQUIRED_FIELDS["{$from}_{$to}"]
        // 3. Retornar {valid, errors[]}
    }

    /**
     * Executa transicao + calcula SLA efetivo se terminal
     */
    public function executeTransition(
        int $vacancyId,
        int $fromStatus,
        int $toStatus,
        array $fields,
        int $userId,
        ?string $notes = null
    ): bool {
        // 1. UPDATE adms_vacancy_opening SET adms_sit_vacancy_id = :toStatus
        // 2. Se status terminal (4): calcular effective_sla
        // 3. recordStatusHistory()
        // 4. LoggerService::info('VACANCY_STATUS_CHANGED', ...)
    }

    /**
     * Registra transicao no historico
     */
    public function recordStatusHistory(
        int $vacancyId,
        ?int $fromStatus,
        int $toStatus,
        int $userId,
        ?string $notes = null
    ): void {
        // INSERT INTO adms_vacancy_opening_status_history
    }

    /**
     * Carrega historico com nomes de usuarios e status
     */
    public function getStatusHistory(int $vacancyId): ?array
    {
        // SELECT com JOINs, ORDER BY created_at DESC
    }

    /**
     * Retorna proximos status validos
     */
    public function getAllowedTransitions(int $currentStatus): array
    {
        return VacancyOpeningStatus::TRANSITIONS[$currentStatus] ?? [];
    }

    /**
     * Calcula SLA efetivo (dias entre criacao e finalizacao)
     */
    public function calculateEffectiveSla(int $vacancyId): ?int
    {
        // DATEDIFF(closing_date, created)
    }
}
```

### 1.3 Criar `VacancyRecruitmentService.php`

**Arquivo:** `app/adms/Services/VacancyRecruitmentService.php`

```php
<?php
namespace App\adms\Services;

class VacancyRecruitmentService
{
    /**
     * Atribui recrutador a vaga
     */
    public function assignRecruiter(int $vacancyId, int $recruiterId, int $userId): bool
    {
        // UPDATE adms_vacancy_opening SET adms_recruiter_id
        // LoggerService::info('VACANCY_RECRUITER_ASSIGNED', ...)
    }

    /**
     * Agenda entrevista (HR ou lider)
     * @param string $type 'hr' ou 'leader'
     */
    public function scheduleInterview(
        int $vacancyId,
        string $type,
        string $date,
        string $evaluators,
        int $userId
    ): bool {
        // UPDATE interview_hr/interview_leader, evaluators_hr/evaluators_leader
    }

    /**
     * Marca vaga como aprovada
     */
    public function markApproved(int $vacancyId, int $userId): bool
    {
        // UPDATE adms_vacancy_opening SET approved = date
    }

    /**
     * Retorna timeline do processo seletivo
     */
    public function getRecruitmentTimeline(int $vacancyId): array
    {
        // Monta array cronologico: criacao, recrutador, entrevistas, aprovacao, fechamento
    }
}
```

### 1.4 Refatorar Models

**`AdmsAddVacancyOpening.php` — Alteracoes:**
- Usar `VacancyOpeningStatus::OPEN` ao inves de magic number `1`
- Usar `VacancyTransitionService::recordStatusHistory()` na criacao

**`AdmsEditVacancyOpening.php` — Alteracoes:**
- Substituir verificacoes hardcoded (`if ($status >= 4 && $level > SUPPORT)`) por `VacancyTransitionService::validateTransition()`
- Delegar logica de recrutamento para `VacancyRecruitmentService`
- Usar constants para comparacoes de status

**`AdmsDeleteVacancyOpening.php` — Alteracoes (preparar para Fase 2):**
- Substituir `VacancyOpeningStatus::OPEN` por constant

### Checklist Fase 1

- [ ] Criar `app/adms/Models/constants/VacancyOpeningStatus.php`
- [ ] Criar `app/adms/Services/VacancyTransitionService.php`
- [ ] Criar `app/adms/Services/VacancyRecruitmentService.php`
- [ ] Refatorar `AdmsAddVacancyOpening.php` (constants + historico)
- [ ] Refatorar `AdmsEditVacancyOpening.php` (transition service + recruitment service)
- [ ] Refatorar `AdmsDeleteVacancyOpening.php` (constants)
- [ ] Testar CRUD completo apos refatoracao
- [ ] Verificar que SLA continua sendo calculado

---

## Fase 2: Soft-Delete + Status History (14h)

**Dependencia:** Fase 1

### 2.1 Migration SQL

**Arquivo:** `database/migrations/2026_04_vacancy_opening_modernization.sql`

```sql
-- =============================================
-- Migration: Modernizacao Abertura de Vagas
-- Data: 2026-04-XX
-- Collation: utf8mb4_unicode_ci (OBRIGATORIO)
-- =============================================

-- Tabela de historico de status (padrao OrderPayments)
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
    ADD COLUMN origin_moviment_id INT NULL DEFAULT NULL
        COMMENT 'Movimento de Pessoal que originou a vaga',
    ADD COLUMN hired_employee_id INT NULL DEFAULT NULL
        COMMENT 'Funcionario contratado via esta vaga',
    ADD INDEX idx_vo_deleted (deleted_at),
    ADD INDEX idx_vo_origin_moviment (origin_moviment_id),
    ADD INDEX idx_vo_hired_employee (hired_employee_id);
```

### 2.2 Criar `VacancyDeleteService.php`

**Arquivo:** `app/adms/Services/VacancyDeleteService.php`

```php
<?php
namespace App\adms\Services;

use App\adms\Models\constants\VacancyOpeningStatus;

class VacancyDeleteService
{
    /**
     * Nivel 1: Aberta + criador + sem edicoes → sem motivo
     * Nivel 2: Aberta/Processando + nivel <= 5 → com motivo
     * Nivel 3: Em Admissao + Super Admin → com motivo + confirmacao
     * Finalizada/Cancelada: NAO pode deletar (terminal)
     */
    public function canDelete(array $vacancy, int $userId, int $userLevel): array
    {
        $status = (int) $vacancy['adms_sit_vacancy_id'];

        // Status terminal: nunca pode deletar
        if (in_array($status, VacancyOpeningStatus::TERMINAL)) {
            return ['allowed' => false, 'message' => 'Vagas finalizadas ou canceladas nao podem ser excluidas.'];
        }

        // Nivel 1: rascunho do proprio criador
        if ($status === VacancyOpeningStatus::OPEN
            && (int) $vacancy['created_by'] === $userId
            && empty($vacancy['modified'])) {
            return ['allowed' => true, 'requireReason' => false, 'requireConfirmation' => false, 'level' => 1];
        }

        // Nivel 2: RH/gerente
        if (in_array($status, [VacancyOpeningStatus::OPEN, VacancyOpeningStatus::PROCESSING])
            && $userLevel <= 5) {
            return ['allowed' => true, 'requireReason' => true, 'requireConfirmation' => false, 'level' => 2];
        }

        // Nivel 3: super admin
        if ($status === VacancyOpeningStatus::IN_ADMISSION && $userLevel === 1) {
            return ['allowed' => true, 'requireReason' => true, 'requireConfirmation' => true, 'level' => 3];
        }

        return ['allowed' => false, 'message' => 'Sem permissao para excluir esta vaga.'];
    }
}
```

### 2.3 Refatorar delete e queries

**`AdmsDeleteVacancyOpening.php`:** Substituir hard-delete por soft-delete

**Adicionar `AND vo.deleted_at IS NULL` em:**
- `AdmsListVacancyOpenings.php`
- `AdmsStatisticsVacancyOpenings.php`
- `AdmsViewVacancyOpening.php`
- `CpAdmsSearchVacancyOpening.php`

### Checklist Fase 2

- [ ] Executar migration SQL
- [ ] Criar `app/adms/Services/VacancyDeleteService.php`
- [ ] Refatorar `AdmsDeleteVacancyOpening.php` para soft-delete
- [ ] Adicionar `deleted_at IS NULL` em todas as queries SELECT
- [ ] Adicionar metodo `restore()` no controller (Super Admin only)
- [ ] Testar delete + restore + listagem

---

## Fase 3: Documentacao + Testes (20h)

**Dependencia:** Fase 1

### 3.1 Criar documentacao do modulo

**Arquivo novo:** `docs/modules/MODULO_VACANCY_OPENING.md`

Conteudo (seguindo padrao dos demais modulos em `docs/modules/`):

1. **Visao geral** — Proposito, contexto de negocio
2. **Arquitetura** — Controllers, models, services, views, JS
3. **Fluxo de status** — Diagrama com 5 status + transicoes permitidas
4. **Tabelas e relacionamentos** — Schema com FKs e indices
5. **Permissoes por nivel** — Quem pode fazer o que por access level
6. **SLA** — Regras de calculo (20/40 dias), efetivo vs previsto
7. **Tipos de vaga** — Substituicao vs Aumento de Quadro
8. **Workflow de recrutamento** — Recrutador → Entrevistas → Aprovacao → Fechamento
9. **Integracoes** — Com Mov. Pessoal (receptor) e Funcionarios (disparador)
10. **Endpoints AJAX** — Lista de todas as rotas
11. **Validacoes de negocio** — Campos obrigatorios, regras por status

### 3.2 Criar testes

**Arquivos novos em `tests/VacancyOpening/`:**

| Teste | Cobertura | Assertions estimadas |
|-------|-----------|:---:|
| `VacancyTransitionServiceTest.php` | State machine completo, SLA, historico | 30+ |
| `VacancyRecruitmentServiceTest.php` | Recrutador, entrevistas, aprovacao, timeline | 20+ |
| `VacancyDeleteServiceTest.php` | 3 niveis + terminal, soft-delete | 25+ |
| `AdmsAddVacancyOpeningTest.php` | Criacao, validacao, SLA automatico | 15+ |
| `AdmsDeleteVacancyOpeningTest.php` | Soft-delete, permissoes, restauracao | 15+ |
| `AdmsViewVacancyOpeningTest.php` | Visualizacao com historico | 10+ |
| `AdmsStatisticsVacancyOpeningsTest.php` | KPIs, filtros, SLA metrics | 10+ |
| `VacancyWorkflowIntegrationTest.php` | Fluxo: abrir → processar → admissao → finalizar | 15+ |
| `VacancyIntegrationServiceTest.php` | Criar de movimento, pre-cadastro, vinculos | 15+ |

**Meta:** De 2 testes para 60+ testes

### Checklist Fase 3

- [ ] Criar `docs/modules/MODULO_VACANCY_OPENING.md`
- [ ] Criar 9 arquivos de teste
- [ ] Verificar testes existentes continuam passando
- [ ] Rodar `phpunit tests/VacancyOpening/` sem falhas
- [ ] Atualizar `docs/CHECKLIST_MODULOS.md`: status de LEGACY → MODERNO

---

## Fase 4: Seguranca JavaScript (6h)

**Dependencia:** Nenhuma (pode executar em paralelo)

### 4.1 Adicionar funcoes utilitarias

Em `vacancy-opening.js`:

```javascript
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

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

async function refreshCsrfToken() { /* padrao OrderPayments */ }
```

### 4.2 Aplicar em todo innerHTML dinamico

Revisar `vacancy-opening.js` e aplicar `escapeHtml()` em todos os pontos de insercao de dados no DOM.

### Checklist Fase 4

- [ ] Adicionar `escapeHtml()`, `fetchWithTimeout()`, `refreshCsrfToken()`
- [ ] Aplicar `escapeHtml()` em todo innerHTML dinamico
- [ ] Substituir `fetch()` por `fetchWithTimeout()`
- [ ] Adicionar retry com CSRF refresh on 403
- [ ] Testar formularios e listagem no navegador

---

## Fase 5: Export + Relatorios + Dashboard (28h)

**Dependencia:** Fase 1

### 5.1 Export Dedicado

**Arquivo novo:** `app/adms/Controllers/ExportVacancyOpening.php`
**Arquivo novo:** `app/adms/Models/AdmsExportVacancyOpening.php`

Filtros: status, loja, tipo (substituicao/aumento), periodo, recrutador.
Formatos: CSV e Excel.

Colunas do export:
| Coluna | Campo |
|--------|-------|
| ID | id |
| Loja | store_name |
| Cargo | position_name |
| Tipo | type_name (Substituicao/Aumento) |
| Funcionario Substituido | employee_name (se aplicavel) |
| Status | status_name |
| Recrutador | recruiter_name |
| SLA Previsto | predicted_sla |
| SLA Efetivo | effective_sla |
| Data Abertura | created |
| Data Fechamento | closing_date |
| Data Admissao | date_admission |

### 5.2 Dashboard

**Arquivo novo:** `app/adms/Views/vacancyOpening/partials/_dashboard_modal.php`

| Grafico | Tipo | Dados |
|---------|------|-------|
| Vagas por status | Doughnut | COUNT por status |
| SLA medio por loja | Bar | AVG(effective_sla) por loja (previsto vs efetivo) |
| Vagas abertas vs finalizadas | Line | COUNT por mes (ultimos 12 meses) |
| Top 10 cargos | Horizontal Bar | COUNT por cargo |

### 5.3 Relatorios

**Arquivo novo:** `app/adms/Controllers/ReportVacancyOpening.php`
**Arquivo novo:** `app/adms/Models/AdmsReportVacancyOpening.php`

| Tipo | Descricao | Filtros |
|------|-----------|---------|
| `sla` | SLA por loja/cargo (previsto vs efetivo) | periodo, loja, cargo |
| `by_status` | Vagas por status no periodo | periodo, loja |
| `by_type` | Substituicao vs Aumento de Quadro | periodo, loja |
| `by_recruiter` | Performance por recrutador (vagas fechadas, SLA medio) | periodo |
| `by_position` | Cargos mais solicitados | periodo, loja |
| `pipeline` | Funil de recrutamento (aberta → processando → admissao → finalizada) | periodo, loja |

### Checklist Fase 5

- [ ] Criar controller + model de export
- [ ] Criar controller + model de relatorios (6 tipos)
- [ ] Criar partial `_dashboard_modal.php` com 4 graficos Chart.js
- [ ] Criar partial `_report_modal.php`
- [ ] Adicionar endpoint `dashboardData()` no controller principal
- [ ] Adicionar botoes "Export", "Dashboard", "Relatorios" na toolbar
- [ ] Registrar rotas em `adms_paginas`

---

## Fase 6: Integracao — Pre-cadastro + Recepcao de Movimentos (16h)

**Dependencia:** Fase 1 + Funcionarios Fase 6 + Mov. Pessoal Fase 7

### 6.1 Recepcao de vagas (via Movimento de Pessoal)

Quando `VacancyIntegrationService::createFromMoviment()` cria a vaga:
- `origin_moviment_id` preenchido automaticamente
- Tipo = Substituicao (`adms_request_type_id = 1`)
- Campos herdados: loja, cargo (via employee), employee_id de referencia
- SLA calculado automaticamente
- Exibir badge na view:

```html
<?php if (!empty($this->Dados['origin_moviment_id'])): ?>
<div class="alert alert-warning mb-3">
    <i class="fas fa-exchange-alt"></i> Gerada pelo desligamento
    <a href="#" onclick="viewMoviment(<?= (int)$this->Dados['origin_moviment_id'] ?>)">
        #<?= (int)$this->Dados['origin_moviment_id'] ?>
    </a>
</div>
<?php endif; ?>
```

### 6.2 Botao de pre-cadastro

**Alterar:** `app/adms/Views/vacancyOpening/partials/_view_vacancy_opening_content.php`

Botao visivel quando status >= 3 (Em Admissao) e ainda sem `hired_employee_id`:

```html
<?php if ((int)$this->Dados['adms_sit_vacancy_id'] >= 3
         && empty($this->Dados['hired_employee_id'])
         && !empty($this->Dados['buttons']['pre_register'])): ?>
<a href="<?= URLADM ?>add-employee/create-from-vacancy/<?= (int)$this->Dados['id'] ?>"
   class="btn btn-success btn-sm">
    <i class="fas fa-user-plus"></i> Pre-cadastrar Colaborador
</a>
<?php endif; ?>
```

### 6.3 Exibir colaborador contratado

Se `hired_employee_id` preenchido:

```html
<?php if (!empty($this->Dados['hired_employee_id'])): ?>
<div class="alert alert-success mb-3">
    <i class="fas fa-user-check"></i> Colaborador contratado:
    <a href="#" onclick="viewEmployee(<?= (int)$this->Dados['hired_employee_id'] ?>)">
        <?= htmlspecialchars($this->Dados['hired_employee_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        (#<?= (int)$this->Dados['hired_employee_id'] ?>)
    </a>
</div>
<?php endif; ?>
```

### 6.4 Atualizar ViewVacancyOpening model

**Alterar:** `AdmsViewVacancyOpening.php`

Adicionar JOINs para carregar dados de integracao:
```sql
LEFT JOIN adms_personnel_moviments pm ON pm.id = vo.origin_moviment_id
LEFT JOIN adms_employees emp_hired ON emp_hired.id = vo.hired_employee_id
```

Campos adicionais no resultado:
- `origin_moviment_id`
- `hired_employee_id`, `hired_employee_name`

### Checklist Fase 6

- [ ] Alterar `_view_vacancy_opening_content.php` (badge origem + botao pre-cadastro + link employee)
- [ ] Alterar `AdmsViewVacancyOpening.php` (JOINs de integracao)
- [ ] Registrar rota `add-employee/create-from-vacancy` em `adms_paginas` (se nao feito)
- [ ] Registrar permissao `pre_register` em `AdmsBotao`
- [ ] Testar: vaga recebida de movimento → botao pre-cadastro → funcionario criado → link visivel

---

## Checklist de Arquivos

### Novos

```
app/adms/Models/constants/VacancyOpeningStatus.php
app/adms/Services/VacancyTransitionService.php
app/adms/Services/VacancyRecruitmentService.php
app/adms/Services/VacancyDeleteService.php
app/adms/Controllers/ExportVacancyOpening.php
app/adms/Models/AdmsExportVacancyOpening.php
app/adms/Controllers/ReportVacancyOpening.php
app/adms/Models/AdmsReportVacancyOpening.php
app/adms/Views/vacancyOpening/partials/_dashboard_modal.php
app/adms/Views/vacancyOpening/partials/_report_modal.php
database/migrations/2026_04_vacancy_opening_modernization.sql
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
```

### Alterados

```
app/adms/Models/AdmsAddVacancyOpening.php (constants + historico)
app/adms/Models/AdmsEditVacancyOpening.php (transition + recruitment service)
app/adms/Models/AdmsDeleteVacancyOpening.php (soft-delete)
app/adms/Models/AdmsListVacancyOpenings.php (filtro deleted_at)
app/adms/Models/AdmsStatisticsVacancyOpenings.php (filtro deleted_at)
app/adms/Models/AdmsViewVacancyOpening.php (JOINs integracao + historico)
app/cpadms/Models/CpAdmsSearchVacancyOpening.php (filtro deleted_at)
app/adms/Views/vacancyOpening/partials/_view_vacancy_opening_content.php (badges + botao)
assets/js/vacancy-opening.js (escapeHtml, fetchWithTimeout, CSRF refresh)
docs/CHECKLIST_MODULOS.md (status LEGACY → MODERNO)
```

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
