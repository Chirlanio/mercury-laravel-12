# Plano de Acao: Modernizacao do Modulo de Movimento de Pessoal (PersonnelMoviments)

**Referencia:** `docs/ANALISE_INTEGRACAO_RH_MODULES.md` v1.0
**Modelo:** Ordem de Pagamento (OrderPayments)
**Data:** 2026-04-02
**Ultima Atualizacao:** 2026-04-02
**Projeto:** Mercury

---

## Visao Geral

Modernizar o modulo de Movimento de Pessoal para o nivel de qualidade do modulo de Ordem de Pagamento (referencia), adicionando services dedicados, state machine de status, soft-delete com auditoria, padronizacao de codigo, dashboard com graficos, relatorios e integracao com o modulo de Abertura de Vagas.

### Alinhamento Atual: 50% → Meta: 85%+

### Integracoes Principais

| Modulo | Integracao | Impacto |
|---|---|---|
| **Funcionarios** | Desativacao automatica do funcionario ao criar movimento (status → 3); reativacao ao deletar | Bidirecional |
| **Abertura de Vagas** | Criacao automatica de vaga de substituicao ao registrar desligamento (checkbox opt-in) | Disparador |
| **Ferias** | Consulta de periodos aquisitivos pendentes antes do desligamento | Consulta |

---

### Status das Fases

| Fase | Status | Esforco | Data Conclusao |
|---|---|---|---|
| **Fase 1:** Service Layer + State Machine | Pendente | 24h | — |
| **Fase 2:** Soft-Delete + Status History | Pendente | 14h | — |
| **Fase 3:** Padronizacao de Codigo | Pendente | 8h | — |
| **Fase 4:** Seguranca JavaScript | Pendente | 6h | — |
| **Fase 5:** Dashboard + Relatorios | Pendente | 24h | — |
| **Fase 6:** Testes Completos | Pendente | 14h | — |
| **Fase 7:** Integracao (disparar abertura de vaga) | Pendente | 12h | — |

**Esforco total estimado: 112h**

---

## Diagnostico Atual

### Arquivos Existentes

**Controllers (5):**
- `app/adms/Controllers/PersonnelMoviments.php` — Listagem (AJAX + stats + modal)
- `app/adms/Controllers/AddPersonnelMoviments.php` — Criacao (AJAX + email multicanal)
- `app/adms/Controllers/EditPersonnelMoviments.php` — Edicao (modal + file management)
- `app/adms/Controllers/DeletePersonnelMoviments.php` — Exclusao (hard-delete + reativacao)
- `app/adms/Controllers/ViewPersonnelMoviments.php` — Visualizacao (modal)

**Models (7):**
- `app/adms/Models/PersonnelMovimentsRepository.php` — Repository pattern (listagem + stats)
- `app/adms/Models/AdmsAddPersonnelMoviments.php` — Criacao com transacao + notificacoes
- `app/adms/Models/AdmsEditPersonnelMoviments.php` — Edicao com file management
- `app/adms/Models/AdmsDeletePersonnelMoviments.php` — Delete + reativacao de employee
- `app/adms/Models/AdmsViewPersonnelMoviments.php` — Visualizacao com reasons + files
- `app/adms/Models/AdmsListPersonnelMovements.php` — **LEGADO** (substituido por Repository)

**Search/Export:**
- `app/cpadms/Models/CpAdmsSearchPersonnelMoviments.php`
- `app/cpadms/Models/CpAdmsExportPersonnelMoviments.php`

**Views:** `app/adms/Views/personnelMoviments/` — 2 principais + 11 partials
**JavaScript:** `assets/js/personnelMoviments.js` (1.937 LOC) — **Nome em camelCase (deveria ser kebab-case)**
**Testes:** 7 arquivos em `tests/PersonnelMoviments/`

### Pontos Fortes

- Repository Pattern implementado (PersonnelMovimentsRepository)
- Notificacao multicanal (email para 6 areas + WebSocket)
- Follow-up de desligamento (uniforme, chip, cartao, ASO, TRCT)
- Desativacao/reativacao automatica do funcionario
- Estatisticas com modal detalhado via AJAX
- Export Excel com filtros e permissoes por loja

### Gaps vs OrderPayments

| Gap | Impacto | Fase |
|-----|---------|------|
| Sem services dedicados (notificacao 50+ LOC inline no model) | Critico | 1 |
| Sem state machine (if/elseif ad-hoc) | Critico | 1 |
| Logica de ativar/desativar employee duplicada (Add + Delete) | Alto | 1 |
| Sem status history table | Alto | 2 |
| Hard-delete sem auditoria | Alto | 2 |
| Sem match expressions no controller | Medio | 3 |
| JS com nome em camelCase | Baixo | 3 |
| Paths de upload inconsistentes (`personnel_moviments/` vs `mp/`) | Medio | 3 |
| Model legado coexiste com Repository | Baixo | 3 |
| Sem `escapeHtml()` no JS | Alto | 4 |
| Sem dashboard/graficos | Medio | 5 |
| Sem relatorios dedicados | Medio | 5 |

---

## Fase 1: Service Layer + State Machine (24h)

**Dependencia:** Nenhuma

### 1.1 Criar `PersonnelMovimentStatus.php` (Constants)

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
        '1_2' => [],  // Iniciar processamento: sem campos extras
        '2_3' => ['dismissal_follow_up_complete'],  // Concluir: follow-up deve estar completo
        '3_2' => ['reopen_reason'],  // Reabrir: justificativa obrigatoria
    ];
}
```

### 1.2 Criar `PersonnelMovimentTransitionService.php`

**Arquivo:** `app/adms/Services/PersonnelMovimentTransitionService.php`

Seguindo padrao `OrderPaymentTransitionService`:

```php
<?php
namespace App\adms\Services;

use App\adms\Models\constants\PersonnelMovimentStatus;

class PersonnelMovimentTransitionService
{
    public function validateTransition(int $from, int $to, array $data): array
    {
        $allowed = PersonnelMovimentStatus::TRANSITIONS[$from] ?? [];
        if (!in_array($to, $allowed)) {
            return ['valid' => false, 'errors' => ['Transicao nao permitida.']];
        }

        $key = "{$from}_{$to}";
        $required = PersonnelMovimentStatus::REQUIRED_FIELDS[$key] ?? [];
        $errors = [];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "Campo obrigatorio: {$field}";
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function executeTransition(
        int $movimentId,
        int $fromStatus,
        int $toStatus,
        array $fields,
        int $userId,
        ?string $notes = null
    ): bool { /* UPDATE + recordStatusHistory + LoggerService */ }

    public function recordStatusHistory(
        int $movimentId,
        ?int $fromStatus,
        int $toStatus,
        int $userId,
        ?string $notes = null
    ): void { /* INSERT INTO adms_personnel_moviment_status_history */ }

    public function getStatusHistory(int $movimentId): ?array
    { /* SELECT com JOINs para user name e status name */ }

    public function getAllowedTransitions(int $currentStatus): array
    {
        return PersonnelMovimentStatus::TRANSITIONS[$currentStatus] ?? [];
    }
}
```

### 1.3 Criar `DismissalNotificationService.php`

**Arquivo:** `app/adms/Services/DismissalNotificationService.php`

Extrair de `AdmsAddPersonnelMoviments.php`:

```php
<?php
namespace App\adms\Services;

class DismissalNotificationService
{
    /** Areas que recebem notificacao: DP(4), Marketing(6), Operacoes(7), TI(9), P&C(13), E-commerce(16) */
    const NOTIFICATION_AREAS = [4, 6, 7, 9, 13, 16];

    public function notifyDismissal(int $movimentId, array $movimentData): void
    {
        // 1. getNotificationRecipients()
        // 2. sendEmailToManagers()
        // 3. SystemNotificationService::notifyUsers() (WebSocket)
    }

    public function getNotificationRecipients(int $boardId): array
    {
        // Buscar gestores das areas NOTIFICATION_AREAS + diretoria
    }

    public function sendEmailToManagers(array $managers, array $emailData): void
    {
        // Loop por cada gestor, buildEmailHtml(), enviar via AdmsPhpMailer
    }

    public function buildDismissalEmailHtml(array $data): string { /* ... */ }
    public function buildDismissalEmailText(array $data): string { /* ... */ }
}
```

### 1.4 Criar `EmployeeInactivationService.php`

**Arquivo:** `app/adms/Services/EmployeeInactivationService.php`

Unificar logica duplicada entre Add e Delete:

```php
<?php
namespace App\adms\Services;

class EmployeeInactivationService
{
    /**
     * Desativa funcionario (status → 3 Inativo)
     * Chamado pelo AddPersonnelMoviments ao criar movimento de desligamento
     */
    public function deactivate(int $employeeId, int $userId, ?string $reason = null): bool
    {
        // 1. Validar que employee existe e esta ativo
        // 2. UPDATE adms_employees SET adms_status_employee_id = 3
        // 3. Se EmployeeLifecycleService existir, usar para gravar historico
        // 4. LoggerService::info('EMPLOYEE_DEACTIVATED', ...)
    }

    /**
     * Reativa funcionario (status → 2 Ativo)
     * Chamado pelo DeletePersonnelMoviments ao reverter movimento
     */
    public function reactivate(int $employeeId, int $userId, ?string $reason = null): bool
    {
        // 1. Validar que employee existe e esta inativo
        // 2. UPDATE adms_employees SET adms_status_employee_id = 2
        // 3. LoggerService::info('EMPLOYEE_REACTIVATED', ...)
    }

    /**
     * Verifica se pode desativar (nao esta em ferias, afastado, etc.)
     */
    public function validateDeactivation(int $employeeId): array
    {
        // Verificar status atual, ferias ativas, etc.
    }
}
```

### 1.5 Refatorar Models

**`AdmsAddPersonnelMoviments.php` — Alteracoes:**
- Extrair `sendNotifications()`, `getManagersByAreas()`, `sendEmailToManager()`, `buildEmailHtml()`, `buildEmailText()` → `DismissalNotificationService`
- Extrair `deactivateEmployee()` → `EmployeeInactivationService::deactivate()`
- Usar `PersonnelMovimentTransitionService::recordStatusHistory()` na criacao

**`AdmsEditPersonnelMoviments.php` — Alteracoes:**
- Usar `PersonnelMovimentTransitionService::validateTransition()` ao mudar status

**`AdmsDeletePersonnelMoviments.php` — Alteracoes:**
- Extrair `reactivateEmployee()` → `EmployeeInactivationService::reactivate()`

### Checklist Fase 1

- [ ] Criar `app/adms/Models/constants/PersonnelMovimentStatus.php`
- [ ] Criar `app/adms/Services/PersonnelMovimentTransitionService.php`
- [ ] Criar `app/adms/Services/DismissalNotificationService.php`
- [ ] Criar `app/adms/Services/EmployeeInactivationService.php`
- [ ] Refatorar `AdmsAddPersonnelMoviments.php` (extrair notificacao + inativacao)
- [ ] Refatorar `AdmsEditPersonnelMoviments.php` (validar transicao)
- [ ] Refatorar `AdmsDeletePersonnelMoviments.php` (extrair reativacao)
- [ ] Testar CRUD completo apos refatoracao
- [ ] Verificar emails continuam sendo enviados

---

## Fase 2: Soft-Delete + Status History (14h)

**Dependencia:** Fase 1

### 2.1 Migration SQL

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

-- Campos de soft-delete e integracao
ALTER TABLE adms_personnel_moviments
    ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN deleted_by_user_id INT NULL DEFAULT NULL,
    ADD COLUMN deleted_reason TEXT NULL DEFAULT NULL,
    ADD COLUMN open_vacancy TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Flag: abrir vaga de substituicao automaticamente',
    ADD COLUMN generated_vacancy_id INT NULL DEFAULT NULL
        COMMENT 'FK para adms_vacancy_opening gerada',
    ADD INDEX idx_pm_deleted (deleted_at),
    ADD INDEX idx_pm_gen_vacancy (generated_vacancy_id);
```

### 2.2 Criar `PersonnelMovimentDeleteService.php`

**Arquivo:** `app/adms/Services/PersonnelMovimentDeleteService.php`

| Nivel | Condicao | Motivo | Confirmacao | Use Case |
|-------|----------|--------|-------------|----------|
| 1 | Pendente + criador + sem edicoes | Nao | Nao | Cancelar rascunho proprio |
| 2 | Pendente/Em Andamento + nivel <= 5 | Sim | Nao | Gerente cancela |
| 3 | Concluido + nivel = 1 (Super Admin) | Sim | Sim | Super Admin reverte |

### 2.3 Refatorar delete para soft-delete

**`AdmsDeletePersonnelMoviments.php` — Alteracoes:**
- Substituir DELETE por UPDATE SET `deleted_at`, `deleted_by_user_id`, `deleted_reason`
- Delegar permissao para `PersonnelMovimentDeleteService::canDelete()`
- Manter reativacao de employee via `EmployeeInactivationService::reactivate()`
- Adicionar metodo `restore()` (Super Admin only)

### 2.4 Atualizar queries

Adicionar `AND pm.deleted_at IS NULL` em:
- `PersonnelMovimentsRepository.php`
- `CpAdmsSearchPersonnelMoviments.php`
- `CpAdmsExportPersonnelMoviments.php`

### Checklist Fase 2

- [ ] Executar migration SQL
- [ ] Criar `app/adms/Services/PersonnelMovimentDeleteService.php`
- [ ] Refatorar `AdmsDeletePersonnelMoviments.php` para soft-delete
- [ ] Adicionar `deleted_at IS NULL` em todas as queries SELECT
- [ ] Adicionar metodo `restore()` no controller
- [ ] Testar delete + restore + listagem

---

## Fase 3: Padronizacao de Codigo (8h)

**Dependencia:** Nenhuma (pode executar em paralelo)

### 3.1 Match expressions no controller

**Alterar:** `app/adms/Controllers/PersonnelMoviments.php`

```php
public function list(int|string|null $pageId = null): void
{
    $typeRequest = filter_input(INPUT_GET, 'typepersonnelmoviment', FILTER_VALIDATE_INT);
    match ($typeRequest) {
        1 => $this->handleAjaxListRequest($this->getFiltersFromRequest()),
        2 => $this->searchPersonnelMoviments($this->getFiltersFromRequest()),
        3 => $this->getFilteredStats(),
        4 => $this->getStatisticsModal(),
        5 => $this->dashboardData(),  // preparar para Fase 5
        default => $this->loadInitialPage($pageId),
    };
}
```

### 3.2 Renomear JavaScript

```
personnelMoviments.js → personnel-moviments.js
```

Atualizar referencia em `loadPersonnelMoviments.php`:
```php
<!-- Antes -->
<script src="<?= URLJS ?>personnelMoviments.js"></script>
<!-- Depois -->
<script src="<?= URLJS ?>personnel-moviments.js"></script>
```

### 3.3 Unificar paths de upload

**Path padrao:** `assets/files/personnel_moviments/{movimentId}/`

**Alterar:** `AdmsEditPersonnelMoviments.php`
- Substituir `assets/files/mp/{$movimentId}/` por `assets/files/personnel_moviments/{$movimentId}/`
- Criar script de migracao para mover arquivos existentes

### 3.4 Remover model legado

**Remover:** `app/adms/Models/AdmsListPersonnelMovements.php`

Verificar que nenhum controller ainda referencia este model (substituido por `PersonnelMovimentsRepository`).

### Checklist Fase 3

- [ ] Refatorar controller para match expressions
- [ ] Renomear JS: `personnelMoviments.js` → `personnel-moviments.js`
- [ ] Atualizar referencia na view
- [ ] Unificar paths de upload para `personnel_moviments/`
- [ ] Migrar arquivos existentes de `mp/` para `personnel_moviments/`
- [ ] Remover `AdmsListPersonnelMovements.php` (legado)
- [ ] Testar listagem, upload e download de arquivos

---

## Fase 4: Seguranca JavaScript (6h)

**Dependencia:** Fase 3 (renomear JS primeiro)

### 4.1 Adicionar funcoes utilitarias

Em `personnel-moviments.js` (apos rename):

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

async function refreshCsrfToken() { /* ... padrao OrderPayments ... */ }
```

### 4.2 Aplicar em todo innerHTML dinamico

Revisar todos os pontos onde dados do servidor sao inseridos no DOM e aplicar `escapeHtml()`.

### Checklist Fase 4

- [ ] Adicionar `escapeHtml()`, `fetchWithTimeout()`, `refreshCsrfToken()`
- [ ] Aplicar `escapeHtml()` em todo innerHTML dinamico
- [ ] Substituir `fetch()` por `fetchWithTimeout()`
- [ ] Adicionar retry com CSRF refresh on 403
- [ ] Testar formularios e listagem no navegador

---

## Fase 5: Dashboard + Relatorios (24h)

**Dependencia:** Fase 1

### 5.1 Dashboard

**Arquivo novo:** `app/adms/Views/personnelMoviments/partials/_dashboard_modal.php`

| Grafico | Tipo | Dados |
|---------|------|-------|
| Movimentos por status | Doughnut | COUNT por status |
| Desligamentos por loja | Bar | COUNT por loja (top 15, ultimos 12 meses) |
| Tendencia mensal | Line | COUNT por mes (ultimos 12 meses) |
| Motivos de desligamento | Stacked Bar | COUNT por motivo por loja |

### 5.2 Relatorios

**Arquivo novo:** `app/adms/Controllers/ReportPersonnelMoviments.php`
**Arquivo novo:** `app/adms/Models/AdmsReportPersonnelMoviments.php`

| Tipo | Descricao | Filtros |
|------|-----------|---------|
| `by_reason` | Desligamentos por motivo | periodo, loja, motivo |
| `by_store` | Desligamentos por loja | periodo |
| `by_period` | Desligamentos no periodo | periodo, loja |
| `follow_up` | Status de follow-up (pendentes, completos) | loja |
| `turnover` | Taxa de rotatividade por loja | periodo |
| `sla` | Tempo medio de processamento (criacao → conclusao) | periodo, loja |

### Checklist Fase 5

- [ ] Criar endpoint `dashboardData()` no controller
- [ ] Criar model `AdmsReportPersonnelMoviments.php` com 6 tipos
- [ ] Criar controller `ReportPersonnelMoviments.php`
- [ ] Criar partial `_dashboard_modal.php` com 4 graficos Chart.js
- [ ] Criar partial `_report_modal.php`
- [ ] Adicionar botoes na toolbar
- [ ] Registrar rotas em `adms_paginas`

---

## Fase 6: Testes Completos (14h)

**Dependencia:** Fases 1 e 2

### Arquivos novos em `tests/PersonnelMoviments/`

| Teste | Cobertura | Assertions estimadas |
|-------|-----------|:---:|
| `PersonnelMovimentTransitionServiceTest.php` | State machine, validacoes, historico | 25+ |
| `DismissalNotificationServiceTest.php` | Destinatarios por area, email HTML/texto | 20+ |
| `EmployeeInactivationServiceTest.php` | Desativar, reativar, validacoes | 15+ |
| `PersonnelMovimentDeleteServiceTest.php` | 3 niveis permissao, soft-delete | 20+ |
| `PersonnelMovimentWorkflowTest.php` | Fluxo: criar → processar → concluir → reabrir | 15+ |

**Meta:** De ~15 testes para 50+ testes

### Checklist Fase 6

- [ ] Criar 5 arquivos de teste
- [ ] Verificar testes existentes continuam passando
- [ ] Rodar `phpunit tests/PersonnelMoviments/` sem falhas

---

## Fase 7: Integracao — Disparar Abertura de Vaga (12h)

**Dependencia:** Fase 1 + Abertura de Vagas Fase 1

### 7.1 Checkbox no formulario de criacao

**Alterar:** `app/adms/Views/personnelMoviments/partials/_add_moviment_modal.php`

Adicionar apos a secao de observacoes:

```html
<div class="form-group mt-3">
    <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input"
               id="open_vacancy" name="open_vacancy" value="1">
        <label class="custom-control-label" for="open_vacancy">
            <i class="fas fa-briefcase text-info"></i>
            Abrir vaga de substituicao automaticamente
        </label>
        <small class="form-text text-muted">
            Uma vaga tipo "Substituicao" sera criada com os dados deste desligamento.
        </small>
    </div>
</div>
```

### 7.2 Integrar no fluxo de criacao

**Alterar:** `AdmsAddPersonnelMoviments.php`

Apos `insertMoviment()` com sucesso:

```php
// Apos insercao bem-sucedida do movimento
if (!empty($this->data['open_vacancy'])) {
    $vacancyService = new VacancyIntegrationService();
    $vacancyId = $vacancyService->createFromMoviment($this->movimentId, [
        'adms_loja_id' => $this->data['adms_loja_id'],
        'adms_employee_id' => $this->data['adms_employee_id'],
        'position_id' => $this->employeePosition,
        'request_area_id' => $this->data['request_area_id'],
    ]);

    if ($vacancyId) {
        $vacancyService->linkMovimentToVacancy($this->movimentId, $vacancyId);
        LoggerService::info('VACANCY_AUTO_CREATED', 'Vaga criada automaticamente', [
            'moviment_id' => $this->movimentId,
            'vacancy_id' => $vacancyId,
        ]);
    }
}
```

### 7.3 Exibir link no view

**Alterar:** `app/adms/Views/personnelMoviments/partials/_view_moviment_content.php`

Se `generated_vacancy_id` preenchido:
```html
<?php if (!empty($this->Dados['generated_vacancy_id'])): ?>
<div class="alert alert-info mb-3">
    <i class="fas fa-briefcase"></i> Vaga de substituicao:
    <a href="#" onclick="viewVacancyOpening(<?= (int)$this->Dados['generated_vacancy_id'] ?>)">
        Vaga #<?= (int)$this->Dados['generated_vacancy_id'] ?>
    </a>
</div>
<?php endif; ?>
```

### Checklist Fase 7

- [ ] Adicionar checkbox na view de criacao
- [ ] Integrar `VacancyIntegrationService::createFromMoviment()` no model
- [ ] Integrar `VacancyIntegrationService::linkMovimentToVacancy()` no model
- [ ] Adicionar card de vaga gerada no `_view_moviment_content.php`
- [ ] Testar: criar movimento com checkbox → vaga criada → link visivel

---

## Checklist de Arquivos

### Novos

```
app/adms/Models/constants/PersonnelMovimentStatus.php
app/adms/Services/PersonnelMovimentTransitionService.php
app/adms/Services/DismissalNotificationService.php
app/adms/Services/EmployeeInactivationService.php
app/adms/Services/PersonnelMovimentDeleteService.php
app/adms/Controllers/ReportPersonnelMoviments.php
app/adms/Models/AdmsReportPersonnelMoviments.php
app/adms/Views/personnelMoviments/partials/_dashboard_modal.php
app/adms/Views/personnelMoviments/partials/_report_modal.php
database/migrations/2026_04_personnel_moviments_modernization.sql
tests/PersonnelMoviments/PersonnelMovimentTransitionServiceTest.php
tests/PersonnelMoviments/DismissalNotificationServiceTest.php
tests/PersonnelMoviments/EmployeeInactivationServiceTest.php
tests/PersonnelMoviments/PersonnelMovimentDeleteServiceTest.php
tests/PersonnelMoviments/PersonnelMovimentWorkflowTest.php
```

### Alterados

```
app/adms/Controllers/PersonnelMoviments.php (match expressions + dashboard)
app/adms/Controllers/DeletePersonnelMoviments.php (soft-delete)
app/adms/Models/AdmsAddPersonnelMoviments.php (extrair services + integracao vaga)
app/adms/Models/AdmsEditPersonnelMoviments.php (validar transicao)
app/adms/Models/AdmsDeletePersonnelMoviments.php (soft-delete + service)
app/adms/Models/PersonnelMovimentsRepository.php (filtro deleted_at)
app/cpadms/Models/CpAdmsSearchPersonnelMoviments.php (filtro deleted_at)
app/cpadms/Models/CpAdmsExportPersonnelMoviments.php (filtro deleted_at)
app/adms/Views/personnelMoviments/loadPersonnelMoviments.php (ref JS)
app/adms/Views/personnelMoviments/partials/_add_moviment_modal.php (checkbox vaga)
app/adms/Views/personnelMoviments/partials/_view_moviment_content.php (link vaga)
assets/js/personnelMoviments.js → personnel-moviments.js (rename + security)
```

### Removidos

```
app/adms/Models/AdmsListPersonnelMovements.php (legado, substituido por Repository)
```

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
