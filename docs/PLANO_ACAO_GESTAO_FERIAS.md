# Plano de Acao: Modulo de Gestao de Ferias (Vacation Management)

**Referencia:** `docs/PROPOSTA_MODULO_FERIAS.md` v1.0
**Data:** 2026-03-16
**Ultima Atualizacao:** 2026-03-16
**Projeto:** Mercury

---

## Visao Geral

Modulo para controle centralizado de periodos aquisitivos e gozo de ferias dos colaboradores, com conformidade CLT, fluxo de aprovacao multi-nivel, e integracao nativa com os modulos de **Funcionarios** (`adms_employees`) e **Metas por Lojas** (`adms_store_goals`).

### Integracoes Principais

| Modulo | Integracao | Impacto |
|---|---|---|
| **Funcionarios** | Calculo automatico de periodos aquisitivos a partir de `date_admission`; validacao de status ativo (`adms_status_employee_id = 1`); atualizacao de status do funcionario durante gozo de ferias | Bidirecional |
| **Metas por Lojas** | Redistribuicao automatica de metas individuais via `StoreGoalsRedistributionService` quando ferias >= 10 dias sao aprovadas; exclusao do consultor do calculo de metas no periodo de gozo | Automatica |

---

### Status das Fases

| Fase | Status | Data Conclusao |
|---|---|---|
| **Fase 1:** Fundacao (Tabelas + Lookups + CRUD Basico) | Concluida | 2026-03-16 |
| **Fase 2:** Solicitacao e Validacao CLT | Concluida | 2026-03-16 |
| **Fase 3:** Fluxo de Aprovacao e Notificacoes | Concluida | 2026-03-17 |
| **Fase 4:** Integracao Funcionarios + Metas | Concluida | 2026-03-17 |
| **Fase 5:** Exportacao e Rotinas Automaticas | Concluida | 2026-03-17 |

---

## Arquitetura de Dados

### Diagrama de Relacionamentos

```
adms_employees (existente)
├── date_admission → calculo automatico de periodos aquisitivos
├── adms_status_employee_id → validacao de elegibilidade
├── adms_store_id → filtro por loja (StorePermissionTrait)
├── position_id → identifica consultores (position_id = 1)
└── doc_cpf → vinculo com adms_total_sales (metas)

adms_vacation_periods (novo)
├── adms_employee_id FK → adms_employees.id
├── adms_status_vacation_period_id FK → adms_status_vacation_periods.id
└── has_many → adms_vacations

adms_vacations (novo)
├── adms_vacation_period_id FK → adms_vacation_periods.id
├── adms_status_vacation_id FK → adms_status_vacations.id
├── approved_by_manager FK → adms_usuarios.id
├── approved_by_hr FK → adms_usuarios.id
└── has_many → adms_vacation_logs

adms_store_goals (existente)
└── StoreGoalsRedistributionService → recalcula metas ao aprovar ferias >= 10 dias

adms_holidays (novo)
└── calendario de feriados para validacao de blackout dates
```

---

## Fase 1: Fundacao (Tabelas + Lookups + CRUD Basico)

### 1.1 Migration SQL

Criar `database/migrations/2026_03_XX_create_vacation_tables.sql`:

```sql
-- =============================================
-- Migration: Modulo de Gestao de Ferias
-- Data: 2026-03-XX
-- Collation: utf8mb4_unicode_ci (OBRIGATORIO)
-- =============================================

-- 1. Tabela de Feriados Nacionais/Estaduais
CREATE TABLE `adms_holidays` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `holiday_date` DATE NOT NULL,
    `type` ENUM('nacional', 'estadual', 'municipal') NOT NULL DEFAULT 'nacional',
    `recurring` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = repete todo ano',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `uk_holiday_date` (`holiday_date`, `type`),
    KEY `idx_holiday_date` (`holiday_date`),
    KEY `idx_recurring` (`recurring`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Status de Periodos Aquisitivos (lookup)
CREATE TABLE `adms_status_vacation_periods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `description_name` VARCHAR(50) NOT NULL,
    `adms_cor_id` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    CONSTRAINT `fk_svp_cor` FOREIGN KEY (`adms_cor_id`) REFERENCES `adms_cors`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed status periodos
INSERT INTO `adms_status_vacation_periods` (`id`, `description_name`, `adms_cor_id`, `created_at`) VALUES
(1, 'Em Aquisicao', 5, NOW()),    -- Azul (periodo corrente, ainda nao completou 12 meses)
(2, 'Disponivel', 3, NOW()),      -- Verde (12 meses completos, pode solicitar)
(3, 'Parcialmente Gozado', 7, NOW()), -- Amarelo (parte dos dias ja usada)
(4, 'Quitado', 6, NOW()),         -- Cinza (todos os dias usufruidos)
(5, 'Vencido', 4, NOW()),         -- Vermelho (passou do limite concessivo)
(6, 'Perdido', 4, NOW());         -- Vermelho (mais de 32 faltas no periodo — Art. 130)

-- 3. Status de Solicitacoes de Ferias (lookup)
CREATE TABLE `adms_status_vacations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `description_name` VARCHAR(50) NOT NULL,
    `adms_cor_id` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    CONSTRAINT `fk_sv_cor` FOREIGN KEY (`adms_cor_id`) REFERENCES `adms_cors`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed status solicitacoes
INSERT INTO `adms_status_vacations` (`id`, `description_name`, `adms_cor_id`, `created_at`) VALUES
(1, 'Rascunho', 6, NOW()),              -- Cinza
(2, 'Pendente Aprovacao Gestor', 7, NOW()), -- Amarelo
(3, 'Aprovada pelo Gestor', 5, NOW()),  -- Azul
(4, 'Aprovada pelo RH', 3, NOW()),      -- Verde
(5, 'Em Gozo', 2, NOW()),               -- Verde escuro
(6, 'Finalizada', 6, NOW()),            -- Cinza
(7, 'Cancelada', 4, NOW()),             -- Vermelho
(8, 'Rejeitada pelo Gestor', 4, NOW()), -- Vermelho
(9, 'Rejeitada pelo RH', 4, NOW());     -- Vermelho

-- 4. Periodos Aquisitivos
CREATE TABLE `adms_vacation_periods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `adms_employee_id` INT NOT NULL,
    `date_start_acq` DATE NOT NULL COMMENT 'Inicio do periodo aquisitivo',
    `date_end_acq` DATE NOT NULL COMMENT 'Fim do periodo aquisitivo (12 meses)',
    `date_limit_concessive` DATE NOT NULL COMMENT 'Data limite para gozo (12 meses apos fim aquisitivo)',
    `days_entitled` INT NOT NULL DEFAULT 30 COMMENT 'Dias de direito (Art. 130 CLT)',
    `days_taken` INT NOT NULL DEFAULT 0 COMMENT 'Dias ja gozados',
    `absences_count` INT NOT NULL DEFAULT 0 COMMENT 'Faltas injustificadas no periodo',
    `sell_days` INT NOT NULL DEFAULT 0 COMMENT 'Dias vendidos (abono pecuniario)',
    `adms_status_vacation_period_id` INT NOT NULL DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    `created_by_user_id` INT NOT NULL,
    `updated_by_user_id` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    CONSTRAINT `fk_vp_employee` FOREIGN KEY (`adms_employee_id`) REFERENCES `adms_employees`(`id`),
    CONSTRAINT `fk_vp_status` FOREIGN KEY (`adms_status_vacation_period_id`) REFERENCES `adms_status_vacation_periods`(`id`),
    CONSTRAINT `fk_vp_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `adms_usuarios`(`id`),
    KEY `idx_vp_employee` (`adms_employee_id`),
    KEY `idx_vp_status` (`adms_status_vacation_period_id`),
    KEY `idx_vp_dates` (`date_start_acq`, `date_end_acq`),
    KEY `idx_vp_limit` (`date_limit_concessive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Solicitacoes/Programacao de Ferias
CREATE TABLE `adms_vacations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `adms_vacation_period_id` INT NOT NULL,
    `adms_employee_id` INT NOT NULL COMMENT 'Redundante para queries rapidas',
    `loja_id` VARCHAR(10) NOT NULL COMMENT 'Loja do funcionario no momento da solicitacao',
    `date_start` DATE NOT NULL COMMENT 'Inicio do gozo',
    `date_end` DATE NOT NULL COMMENT 'Fim do gozo',
    `date_return` DATE NOT NULL COMMENT 'Data de retorno ao trabalho',
    `days_quantity` INT NOT NULL COMMENT 'Dias de gozo (5 a 30)',
    `default_days_override` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = dias alterados do padrao do cargo',
    `override_reason` VARCHAR(255) DEFAULT NULL COMMENT 'Justificativa para alteracao do padrao',
    `installment` TINYINT NOT NULL DEFAULT 1 COMMENT 'Parcela: 1, 2 ou 3',
    `sell_allowance` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Abono pecuniario (venda de ate 1/3)',
    `sell_days` INT NOT NULL DEFAULT 0 COMMENT 'Dias vendidos nesta parcela',
    `advance_13th` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Adiantamento 13o salario',
    `payment_deadline` DATE DEFAULT NULL COMMENT 'Prazo pagamento (2 dias antes — Art. 145)',
    `adms_status_vacation_id` INT NOT NULL DEFAULT 1,
    `requested_by_user_id` INT NOT NULL COMMENT 'Usuario que criou a solicitacao',
    `manager_approved_by` INT DEFAULT NULL,
    `manager_approved_at` DATETIME DEFAULT NULL,
    `manager_notes` TEXT DEFAULT NULL,
    `hr_approved_by` INT DEFAULT NULL,
    `hr_approved_at` DATETIME DEFAULT NULL,
    `hr_notes` TEXT DEFAULT NULL,
    `cancellation_reason` TEXT DEFAULT NULL,
    `cancelled_by` INT DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `created_by_user_id` INT NOT NULL,
    `updated_by_user_id` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    CONSTRAINT `fk_v_period` FOREIGN KEY (`adms_vacation_period_id`) REFERENCES `adms_vacation_periods`(`id`),
    CONSTRAINT `fk_v_employee` FOREIGN KEY (`adms_employee_id`) REFERENCES `adms_employees`(`id`),
    CONSTRAINT `fk_v_status` FOREIGN KEY (`adms_status_vacation_id`) REFERENCES `adms_status_vacations`(`id`),
    CONSTRAINT `fk_v_requested_by` FOREIGN KEY (`requested_by_user_id`) REFERENCES `adms_usuarios`(`id`),
    KEY `idx_v_employee` (`adms_employee_id`),
    KEY `idx_v_period` (`adms_vacation_period_id`),
    KEY `idx_v_status` (`adms_status_vacation_id`),
    KEY `idx_v_dates` (`date_start`, `date_end`),
    KEY `idx_v_store` (`loja_id`),
    KEY `idx_v_payment` (`payment_deadline`, `adms_status_vacation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Controle de Alertas Enviados (evitar duplicidade)
CREATE TABLE `adms_vacation_alert_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `adms_vacation_period_id` INT NOT NULL,
    `alert_level` VARCHAR(20) NOT NULL COMMENT '90_days, 60_days, 30_days, expired',
    `sent_at` DATETIME NOT NULL,
    `sent_via` VARCHAR(20) NOT NULL DEFAULT 'websocket' COMMENT 'websocket, email, both',
    CONSTRAINT `fk_val_period` FOREIGN KEY (`adms_vacation_period_id`) REFERENCES `adms_vacation_periods`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_period_alert` (`adms_vacation_period_id`, `alert_level`),
    KEY `idx_val_sent` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Log de Acoes (Historico de Aprovacoes/Alteracoes)
CREATE TABLE `adms_vacation_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `adms_vacation_id` INT NOT NULL,
    `action_type` VARCHAR(50) NOT NULL COMMENT 'CREATED, SUBMITTED, MANAGER_APPROVED, HR_APPROVED, REJECTED, CANCELLED, STARTED, FINISHED',
    `old_status_id` INT DEFAULT NULL,
    `new_status_id` INT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `user_id` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    CONSTRAINT `fk_vl_vacation` FOREIGN KEY (`adms_vacation_id`) REFERENCES `adms_vacations`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vl_user` FOREIGN KEY (`user_id`) REFERENCES `adms_usuarios`(`id`),
    KEY `idx_vl_vacation` (`adms_vacation_id`),
    KEY `idx_vl_action` (`action_type`),
    KEY `idx_vl_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.2 Seed de Feriados Nacionais

```sql
-- Feriados nacionais fixos (recurring = 1)
INSERT INTO `adms_holidays` (`name`, `holiday_date`, `type`, `recurring`, `active`, `created_at`) VALUES
('Confraternizacao Universal', '2026-01-01', 'nacional', 1, 1, NOW()),
('Tiradentes', '2026-04-21', 'nacional', 1, 1, NOW()),
('Dia do Trabalho', '2026-05-01', 'nacional', 1, 1, NOW()),
('Independencia do Brasil', '2026-09-07', 'nacional', 1, 1, NOW()),
('Nossa Senhora Aparecida', '2026-10-12', 'nacional', 1, 1, NOW()),
('Finados', '2026-11-02', 'nacional', 1, 1, NOW()),
('Proclamacao da Republica', '2026-11-15', 'nacional', 1, 1, NOW()),
('Natal', '2026-12-25', 'nacional', 1, 1, NOW()),
-- Feriados moveis 2026 (recurring = 0, precisa atualizar todo ano)
('Carnaval', '2026-02-17', 'nacional', 0, 1, NOW()),
('Sexta-feira Santa', '2026-04-03', 'nacional', 0, 1, NOW()),
('Corpus Christi', '2026-06-04', 'nacional', 0, 1, NOW());
```

### 1.3 Rotas e Permissoes

```sql
-- Rotas em adms_paginas
INSERT INTO `adms_paginas` (`controller`, `metodo`, `menu_controller`, `obs`) VALUES
-- Modulo principal (Ferias)
('Vacations', 'list', 'vacations', 'Listagem de ferias'),
('AddVacation', 'create', 'add-vacation', 'Solicitar ferias'),
('EditVacation', 'update', 'edit-vacation', 'Editar solicitacao de ferias'),
('DeleteVacation', 'delete', 'delete-vacation', 'Cancelar solicitacao de ferias'),
('ViewVacation', 'view', 'view-vacation', 'Visualizar solicitacao de ferias'),
('ApproveVacation', 'approve', 'approve-vacation', 'Aprovar/rejeitar ferias'),
('ExportVacation', 'export', 'export-vacation', 'Exportar ferias para folha'),
-- Periodos Aquisitivos
('VacationPeriods', 'list', 'vacation-periods', 'Listagem de periodos aquisitivos'),
('AddVacationPeriod', 'create', 'add-vacation-period', 'Cadastrar periodo aquisitivo'),
('EditVacationPeriod', 'update', 'edit-vacation-period', 'Editar periodo aquisitivo'),
('ViewVacationPeriod', 'view', 'view-vacation-period', 'Visualizar periodo aquisitivo'),
-- Feriados (AbstractConfigController)
('Holidays', 'list', 'holidays', 'Listagem de feriados'),
-- Dashboard
('Vacations', 'dashboard', 'vacations', 'Dashboard de ferias');

-- Permissoes em adms_nivacs_pgs (ajustar IDs conforme ambiente)
-- INSERT INTO adms_nivacs_pgs ...

-- Menu
-- INSERT INTO adms_menus ...
```

### 1.4 CRUD Feriados (AbstractConfigController)

Modulo simples de lookup para manter o calendario de feriados:

| Arquivo | Tipo |
|---|---|
| `Controllers/Holidays.php` | Controller (extends AbstractConfigController) |
| `Views/holidays/loadHolidays.php` | View principal |
| `Views/holidays/listHolidays.php` | Listagem AJAX |
| `Views/holidays/partials/_add_holiday_modal.php` | Modal adicionar |
| `Views/holidays/partials/_edit_holiday_modal.php` | Modal editar |
| `Views/holidays/partials/_delete_holiday_modal.php` | Modal deletar |

Campos do formulario:
- `name` (text, obrigatorio)
- `holiday_date` (date, obrigatorio)
- `type` (select: nacional/estadual/municipal)
- `recurring` (checkbox: repete todo ano)
- `active` (checkbox)

### 1.5 CRUD Periodos Aquisitivos

| Arquivo | Tipo |
|---|---|
| `Controllers/VacationPeriods.php` | Controller principal (listagem) |
| `Controllers/AddVacationPeriod.php` | Controller adicionar |
| `Controllers/EditVacationPeriod.php` | Controller editar |
| `Controllers/ViewVacationPeriod.php` | Controller visualizar |
| `Models/AdmsListVacationPeriods.php` | Model listagem com paginacao |
| `Models/AdmsVacationPeriod.php` | Model CRUD principal |
| `Models/AdmsViewVacationPeriod.php` | Model visualizacao |
| `Models/AdmsStatisticsVacationPeriods.php` | Model estatisticas |
| `Views/vacationPeriods/loadVacationPeriods.php` | Pagina principal com stats |
| `Views/vacationPeriods/listVacationPeriods.php` | Listagem AJAX |
| `Views/vacationPeriods/partials/_add_vacation_period_modal.php` | Modal criacao |
| `Views/vacationPeriods/partials/_edit_vacation_period_modal.php` | Modal edicao |
| `Views/vacationPeriods/partials/_view_vacation_period_modal.php` | Modal detalhes |
| `assets/js/vacation-periods.js` | JavaScript principal |

Funcionalidades:
- Listagem com filtros: loja, status, funcionario, vencendo em 60 dias
- **Geracao automatica** de periodos a partir de `adms_employees.date_admission` (via Service)
- Cards de estatisticas: Total, Disponiveis, Vencendo (< 60 dias), Vencidos
- Calculo automatico de `days_entitled` baseado em faltas (Art. 130 CLT)
- Filtro por loja via `StorePermissionTrait`
- Permissao: apenas nivel RH (2) ou Admin (1) podem criar/editar manualmente

### 1.6 Service: VacationPeriodGeneratorService

| Arquivo | Tipo |
|---|---|
| `Services/VacationPeriodGeneratorService.php` | Geracao automatica de periodos |

Responsabilidades:
- `generateForEmployee(int $employeeId)`: Gera todos os periodos aquisitivos desde `date_admission` ate a data atual
- `generateForAllEmployees(?string $storeId)`: Geracao em lote (para carga inicial)
- `updateDaysEntitled(int $periodId, int $absences)`: Recalcula dias de direito baseado em faltas
- `checkExpiredPeriods()`: Atualiza status de periodos vencidos (para cron/rotina)

**Regra CLT Art. 130 — Reducao de Dias por Faltas:**

| Faltas Injustificadas | Dias de Direito |
|---|---|
| 0 a 5 | 30 dias |
| 6 a 14 | 24 dias |
| 15 a 23 | 18 dias |
| 24 a 32 | 12 dias |
| Acima de 32 | 0 dias (perde o direito) |

Calculo do periodo:
```
date_start_acq = date_admission (ou aniversario)
date_end_acq = date_start_acq + 12 meses - 1 dia
date_limit_concessive = date_end_acq + 12 meses
```

### 1.7 Testes Fase 1

| Arquivo | Cobertura |
|---|---|
| `tests/Vacations/VacationPeriodGeneratorServiceTest.php` | Geracao de periodos, calculo de faltas, vencimento |
| `tests/Vacations/AdmsVacationPeriodTest.php` | CRUD periodos, validacoes |

**Entregavel:** Cadastro de feriados, geracao automatica de periodos aquisitivos vinculados a `adms_employees.date_admission`, listagem com filtros e estatisticas.

---

## Fase 2: Solicitacao e Validacao CLT

### 2.1 Service: VacationValidatorService (Coracao do Modulo)

| Arquivo | Tipo |
|---|---|
| `Services/VacationValidatorService.php` | Validacao completa CLT |

Metodos de validacao:

| Metodo | Regra CLT | Descricao |
|---|---|---|
| `validatePeriodBalance()` | — | Verifica saldo de dias no periodo aquisitivo |
| `validateMinDays()` | Art. 134 §1 | Uma parcela >= 14 dias; demais >= 5 dias |
| `validateMaxInstallments()` | Art. 134 §1 | Maximo 3 parcelas por periodo |
| `validateOverlap()` | — | Impede ferias sobrepostas do mesmo funcionario |
| `validateBlackoutDates()` | Art. 134 §3 | Nao pode iniciar 2 dias antes de feriado ou DSR |
| `validateStartDate()` | Regra interna | Inicio preferencialmente no 1o dia util do mes (warning, nao bloqueio) |
| `validateDefaultDaysByPosition()` | Regra interna | Valida dias padrao por cargo; exige justificativa se diferente |
| `validateAdvanceNotice()` | Art. 135 | Minimo 30 dias de antecedencia na solicitacao |
| `validatePaymentDeadline()` | Art. 145 | Pagamento ate 2 dias antes do inicio |
| `validateSellAllowance()` | Art. 143 | Abono pecuniario maximo 1/3 dos dias (10 dias em 30) |
| `validateMinorRestrictions()` | Art. 136 | Menores de 18 anos: ferias coincidem com ferias escolares |
| `validateConcessivePeriod()` | Art. 137 | Alerta se proximo do vencimento (ferias dobradas) |
| `validateEmployeeStatus()` | — | Funcionario deve estar ativo (`adms_status_employee_id = 1`) |
| `validateAll()` | — | Executa todas as validacoes, retorna array de erros/warnings |

**Regra Art. 134 §3 — Blackout Dates (detalhada):**
```
Ferias NAO podem iniciar:
1. Em sabado ou domingo
2. Nos 2 dias que antecedem um feriado
3. Nos 2 dias que antecedem o DSR (descanso semanal remunerado)

Implementacao:
- Consultar adms_holidays para feriados
- Verificar dia da semana (date('N') == 6 ou 7 = sabado/domingo)
- Verificar se date_start - 1 ou date_start - 2 e feriado/DSR
- Retornar erro com a data sugerida mais proxima valida
```

**Regra Interna — Inicio Preferencial no 1o Dia Util do Mes:**
```
Ferias DEVEM iniciar preferencialmente no primeiro dia util do mes.

Tipo: WARNING (nao bloqueia, mas exibe alerta ao usuario e ao aprovador)

Implementacao:
- Calcular o 1o dia util do mes de date_start:
  - Dia 1 do mes → se sabado, pula para segunda (dia 3)
  - Se domingo, pula para segunda (dia 2)
  - Se feriado, pula para proximo dia util
- Se date_start != 1o dia util do mes:
  - Retornar warning: "Inicio recomendado para este mes: {data_sugerida}"
  - O formulario exibe alerta amarelo (nao impede submissao)
  - O aprovador ve o alerta no modal de aprovacao
  - Se o usuario confirmar data diferente, segue normalmente
```

**Regra Interna — Dias Padrao por Cargo (position_id):**
```
Cada cargo tem um padrao de dias de ferias por parcela:

| Cargo (position_id) | Dias Padrao | Fracionamento Tipico |
|---|---|---|
| Gerente de Loja (23) | 15 dias | 1 parcela de 15 dias |
| Demais funcionarios de loja | 30 dias | 1 parcela de 30 dias |

IMPORTANTE: O padrao pode ser alterado em situacoes especificas.

Comportamento:
1. Ao selecionar o funcionario no formulario, o campo `days_quantity`
   e pre-preenchido com o padrao do cargo:
   - position_id = 23 (gerente): 15 dias
   - Outros: 30 dias

2. O usuario pode alterar o valor manualmente. Se alterado:
   - Campo `default_days_override` = 1
   - Campo `override_reason` torna-se OBRIGATORIO
   - Exibe input de justificativa: "Motivo da alteracao do padrao"
   - Exemplos de motivos: "Solicitacao do funcionario", "Necessidade operacional",
     "Acordo com RH", "Periodo aquisitivo com saldo reduzido"

3. Na validacao (validateDefaultDaysByPosition):
   - Se days_quantity != padrao do cargo E override_reason vazio:
     → Erro: "Justificativa obrigatoria para alterar o padrao de {X} dias"
   - Se days_quantity > saldo disponivel:
     → Erro: "Saldo insuficiente (disponivel: {Y} dias)"

4. No modal de aprovacao, o aprovador ve:
   - Padrao do cargo: 15 dias (Gerente) ou 30 dias (Funcionario)
   - Dias solicitados: {days_quantity}
   - Se diferente do padrao: badge "Alterado" + motivo da alteracao

5. Constantes no service (configuravel):
   const DEFAULT_DAYS_MANAGER = 15;    // position_id = 23
   const DEFAULT_DAYS_EMPLOYEE = 30;   // demais cargos
   const MANAGER_POSITION_ID = 23;     // ID do cargo gerente
```

### 2.2 Service: VacationCalculationService

| Arquivo | Tipo |
|---|---|
| `Services/VacationCalculationService.php` | Calculos de datas e valores |

Metodos:

| Metodo | Descricao |
|---|---|
| `calculateEndDate(date_start, days_quantity)` | Calcula data fim (pula feriados e DSR se aplicavel) |
| `calculateReturnDate(date_end)` | Proximo dia util apos o fim |
| `calculatePaymentDeadline(date_start)` | 2 dias uteis antes do inicio |
| `calculateBalance(periodId)` | `days_entitled - days_taken - sell_days` |
| `calculateRemainingInstallments(periodId)` | Parcelas ja usadas vs maximo 3 |
| `calculateDaysEntitledByAbsences(absences)` | Aplica tabela Art. 130 |
| `suggestNextValidDate(date)` | Proxima data que nao viola blackout |
| `getFirstBusinessDay(month, year)` | Retorna o 1o dia util do mes (pula feriados, sabados, domingos) |
| `getDefaultDaysByPosition(positionId)` | Retorna dias padrao: 15 (gerente) ou 30 (demais) |

### 2.3 CRUD Solicitacoes de Ferias

| Arquivo | Tipo |
|---|---|
| `Controllers/Vacations.php` | Controller principal (listagem + dashboard) |
| `Controllers/AddVacation.php` | Controller solicitar ferias |
| `Controllers/EditVacation.php` | Controller editar solicitacao |
| `Controllers/DeleteVacation.php` | Controller cancelar solicitacao |
| `Controllers/ViewVacation.php` | Controller visualizar detalhes |
| `Models/AdmsListVacations.php` | Model listagem com paginacao |
| `Models/AdmsVacation.php` | Model CRUD principal |
| `Models/AdmsViewVacation.php` | Model visualizacao detalhada |
| `Models/AdmsStatisticsVacations.php` | Model estatisticas |
| `Views/vacations/loadVacations.php` | Pagina principal com stats |
| `Views/vacations/listVacations.php` | Listagem AJAX |
| `Views/vacations/partials/_add_vacation_modal.php` | Modal solicitacao |
| `Views/vacations/partials/_edit_vacation_modal.php` | Modal edicao |
| `Views/vacations/partials/_view_vacation_modal.php` | Modal detalhes |
| `Views/vacations/partials/_delete_vacation_modal.php` | Modal cancelamento |
| `assets/js/vacations.js` | JavaScript principal |
| `app/cpadms/Models/CpAdmsSearchVacation.php` | Search model (busca avancada) |

Funcionalidades do formulario de solicitacao:
- Select de funcionario (filtrado por loja via `FormSelectRepository::getEmployeesByStore()`)
  - **Ao selecionar funcionario:** pre-preenche `days_quantity` com padrao do cargo (15 para gerente, 30 para demais)
  - Exibe badge informativo: "Padrao para Gerente: 15 dias" ou "Padrao: 30 dias"
- Select de periodo aquisitivo (apenas status Disponivel ou Parcialmente Gozado)
- Exibicao dinamica do saldo de dias
- Campo `date_start`:
  - Sugere automaticamente o 1o dia util do mes selecionado
  - Se data diferente do 1o dia util: exibe alerta amarelo (warning, nao bloqueia)
  - Valida blackout dates em tempo real via AJAX
- Campo `days_quantity`:
  - Pre-preenchido com padrao do cargo
  - Se alterado do padrao: exibe campo obrigatorio `override_reason` (justificativa)
  - Calcula `date_end` e `date_return` automaticamente via JS
- Checkbox: abono pecuniario (com campo de dias, maximo 1/3)
- Checkbox: adiantamento 13o salario
- Select de parcela (1, 2 ou 3 — desabilita as ja usadas)
- **Validacao em tempo real no frontend** (JS) + validacao completa no backend (VacationValidatorService)
- Exibicao de alertas: "Data inicio cai em blackout", "Saldo insuficiente", "Dias diferente do padrao", etc.

Cards de estatisticas:
- Total de solicitacoes (periodo filtrado)
- Pendentes de aprovacao
- Em gozo atualmente
- Proximas ferias (30 dias)

Filtros da listagem:
- Loja (automatico para nivel >= STOREPERMITION)
- Status da solicitacao
- Funcionario (autocomplete)
- Periodo (data inicio/fim)
- Parcela

### 2.4 JavaScript: vacations.js

Funcionalidades:
- **Padrao por cargo:** ao selecionar funcionario, busca `position_id` e pre-preenche `days_quantity` (15 ou 30)
- **Sugestao de data:** ao selecionar mes, sugere o 1o dia util via AJAX (`GET /vacations/suggest-start-date?month=5&year=2026`)
- **Alerta de data:** se `date_start` != 1o dia util, exibe warning amarelo (nao bloqueia)
- **Override de dias:** se `days_quantity` != padrao do cargo, exibe campo `override_reason` (toggle de visibilidade)
- Calculo em tempo real: ao digitar `date_start` e `days_quantity`, calcula `date_end` e `date_return`
- Validacao de blackout dates via AJAX (`GET /vacations/check-date?date=2026-05-01`)
- Exibicao de saldo ao selecionar periodo aquisitivo
- Formatacao de datas (BR)
- Event delegation para botoes de acao em modais
- Feedback visual de erros de validacao inline (sem reload)
- Responsivo: botoes desktop vs dropdown mobile

### 2.5 Testes Fase 2

| Arquivo | Cobertura |
|---|---|
| `tests/Vacations/VacationValidatorServiceTest.php` | Todas as regras CLT, blackout dates, parcelas, saldo |
| `tests/Vacations/VacationCalculationServiceTest.php` | Calculo de datas, prazos, saldos |
| `tests/Vacations/AdmsVacationTest.php` | CRUD solicitacoes, validacoes de model |

**Entregavel:** Solicitacao de ferias com validacao CLT completa, calculo automatico de datas, validacao frontend + backend.

---

## Fase 3: Fluxo de Aprovacao e Notificacoes

### 3.1 Fluxo de Aprovacao Multi-Nivel

```
                    ┌──────────────┐
                    │  Rascunho(1) │
                    └──────┬───────┘
                           │ Funcionario submete
                           ▼
              ┌─────────────────────────┐
              │ Pendente Aprov. Gestor(2)│
              └─────┬──────────┬────────┘
                    │          │
          Aprovado  │          │  Rejeitado
                    ▼          ▼
        ┌──────────────┐  ┌──────────────────┐
        │ Aprov. Gestor│  │Rejeit. Gestor (8)│
        │    (3)       │  └──────────────────┘
        └──────┬───────┘
               │ Automatico (vai para RH)
               ▼
     ┌────────────────────┐
     │ Aprovada RH (4)    │◄── RH valida/ajusta
     └─────┬──────┬───────┘
           │      │
           │      │  Rejeitado pelo RH
           │      ▼
           │  ┌──────────────┐
           │  │Rejeit. RH (9)│
           │  └──────────────┘
           │
           │ Data inicio chegou (automatico ou manual)
           ▼
     ┌──────────────┐
     │  Em Gozo (5) │
     └──────┬───────┘
            │ Data retorno chegou
            ▼
     ┌──────────────┐
     │Finalizada (6)│
     └──────────────┘

     Cancelamento: qualquer status (1-4) → Cancelada (7)
     - Status 1: funcionario pode cancelar
     - Status 2-3: funcionario solicita, gestor aprova cancelamento
     - Status 4: apenas RH pode cancelar
```

### 3.2 Controller de Aprovacao

| Arquivo | Tipo |
|---|---|
| `Controllers/ApproveVacation.php` | Controller aprovacao/rejeicao |

Metodos:

| Metodo | Acao | Permissao |
|---|---|---|
| `approve()` | Aprovacao pelo gestor (2→3) | Nivel <= 5 (gestor) da mesma loja |
| `approveHR()` | Aprovacao pelo RH (3→4) | Nivel <= 2 (RH/Admin) |
| `reject()` | Rejeicao pelo gestor (2→8) | Nivel <= 5 + notas obrigatorias |
| `rejectHR()` | Rejeicao pelo RH (3→9) | Nivel <= 2 + notas obrigatorias |
| `cancel()` | Cancelamento (1-4→7) | Depende do status atual |
| `startVacation()` | Inicio do gozo (4→5) | Sistema automatico ou RH |
| `finishVacation()` | Fim do gozo (5→6) | Sistema automatico ou RH |

Padrao de implementacao (seguindo `ApproveHolidayPayment.php`):
```php
private function changeStatus(int $statusId, string $statusName, string $logAction): void
{
    // 1. Validar permissao via SessionContext
    // 2. Validar input (notas obrigatorias em rejeicao)
    // 3. Verificar se pode transicionar (status atual permite?)
    // 4. Atualizar status em adms_vacations
    // 5. Registrar em adms_vacation_logs
    // 6. LoggerService::info()
    // 7. SystemNotificationService::notify() para o solicitante
    // 8. Retornar JSON response
}
```

### 3.3 Service: VacationStatusTransitionService

| Arquivo | Tipo |
|---|---|
| `Services/VacationStatusTransitionService.php` | Maquina de estados |

Responsabilidades:
- Definir transicoes validas (mapa de `from → [to]`)
- Validar permissao do usuario para cada transicao
- Executar acoes colaterais por transicao:
  - **2→3 (Aprovado Gestor):** Notificar RH
  - **3→4 (Aprovado RH):** Notificar funcionario + gestor; calcular `payment_deadline`; **disparar integracao com Metas** (ver Fase 4)
  - **4→5 (Em Gozo):** Atualizar status do funcionario em `adms_employees`; **disparar redistribuicao de metas** (ver Fase 4)
  - **5→6 (Finalizada):** Restaurar status do funcionario; atualizar `days_taken` no periodo; **recalcular metas**
  - **Qualquer→7 (Cancelada):** Reverter `days_taken` se necessario; **recalcular metas**

### 3.4 Alertas, Notificacoes e Confirmacoes

Usando `SystemNotificationService` (WebSocket) + PHPMailer (e-mail para alertas criticos).

#### 3.4.1 Notificacoes de Fluxo (WebSocket)

| Evento | Destinatarios | Tipo | Canal |
|---|---|---|---|
| Solicitacao criada | Gestor da loja | `vacation_requested` | WebSocket |
| Aprovada pelo gestor | RH + solicitante | `vacation_manager_approved` | WebSocket |
| Aprovada pelo RH | Solicitante + gestor | `vacation_hr_approved` | WebSocket + E-mail |
| Rejeitada (gestor ou RH) | Solicitante | `vacation_rejected` | WebSocket |
| Cancelada | Gestor + RH (se aplicavel) | `vacation_cancelled` | WebSocket |
| Ferias aprovadas >= 10 dias (consultor) | Gestor da loja | `vacation_goals_impact` | WebSocket |

#### 3.4.2 Alertas de Periodos Aquisitivos (Escalonados)

Alertas automaticos via cron, escalonados por urgencia:

| Prazo | Nivel | Destinatarios | Canal | Cor |
|---|---|---|---|---|
| **90 dias** antes do vencimento | Informativo | Gestor da loja | WebSocket | Azul (info) |
| **60 dias** antes do vencimento | Atencao | Gestor + RH | WebSocket + E-mail | Amarelo (warning) |
| **30 dias** antes do vencimento | Urgente | Gestor + RH + Funcionario | WebSocket + E-mail | Laranja (danger) |
| **Periodo vencido** (ferias dobradas) | Critico | RH + Diretoria (nivel 1) | WebSocket + E-mail | Vermelho (danger) |

```
Mensagens:
- 90 dias: "Periodo aquisitivo de {nome} vence em {data}. Programe as ferias."
- 60 dias: "ATENCAO: Periodo de {nome} vence em {data}. Ferias ainda nao programadas."
- 30 dias: "URGENTE: Periodo de {nome} vence em {data}. Risco de ferias dobradas!"
- Vencido: "CRITICO: Periodo de {nome} VENCEU em {data}. Ferias em dobro devidas (Art. 137 CLT)."
```

Controle de envio (evitar spam):
- Tabela `adms_vacation_alert_log` registra alertas ja enviados
- Cada combinacao `period_id + alert_level` e enviada apenas 1 vez
- Cron verifica diariamente e envia apenas alertas novos

#### 3.4.3 Alertas de Validacao de Calendario

Alertas em tempo real durante a criacao/aprovacao de ferias:

| Validacao | Tipo | Momento | Comportamento |
|---|---|---|---|
| Data fora do 1o dia util do mes | Warning | Criacao | Alerta amarelo, nao bloqueia |
| Data cai em blackout (feriado/DSR) | Erro | Criacao | Bloqueia submissao, sugere data alternativa |
| Data em sabado/domingo | Erro | Criacao | Bloqueia submissao, sugere proxima segunda |
| Dias diferentes do padrao do cargo | Warning | Criacao | Exige justificativa, nao bloqueia |
| Saldo insuficiente no periodo | Erro | Criacao | Bloqueia submissao |
| Sobreposicao com outra ferias do funcionario | Erro | Criacao | Bloqueia, mostra datas conflitantes |
| **Conflito de lotacao na loja** | Warning | Criacao + Aprovacao | Alerta se > 20% dos funcionarios da loja estarao de ferias simultaneamente |
| **Proximo do vencimento sem programacao** | Warning | Listagem | Badge pulsante no card do periodo |
| Prazo de pagamento < 2 dias uteis | Warning | Aprovacao RH | Alerta ao RH que o prazo legal esta apertado |

**Conflito de Lotacao (detalhado):**
```
Ao criar ou aprovar ferias, o sistema verifica:

1. Buscar total de funcionarios ativos da loja
2. Buscar quantos estarao de ferias no periodo solicitado
   (status IN (2, 3, 4, 5) = pendente a em gozo)
3. Calcular percentual: ausentes / total * 100

Se >= 20% (configuravel):
  - Criacao: warning amarelo "X de Y funcionarios estarao ausentes neste periodo"
  - Aprovacao: warning vermelho ao gestor com lista de nomes ausentes

Se >= 40% (configuravel):
  - Alerta critico: "ATENCAO: Mais de 40% da loja estara ausente. Confirme a operacao."
  - Requer confirmacao explicita do aprovador (checkbox "Ciente do impacto operacional")

Constantes:
  const STAFF_WARNING_THRESHOLD = 0.20;   // 20%
  const STAFF_CRITICAL_THRESHOLD = 0.40;  // 40%
```

#### 3.4.4 Confirmacao de Calendario Proposto

Fluxo para o RH propor e a loja confirmar o calendario anual de ferias:

```
Fluxo de Confirmacao:

1. RH monta o calendario proposto (programacao anual de ferias por loja)
   - Pode ser feito via tela de criacao em lote ou individual
   - Todas as solicitacoes ficam com status "Rascunho" (1)

2. RH submete o calendario da loja para revisao
   - Transicao em lote: Rascunho (1) → Pendente Aprovacao Gestor (2)
   - Notificacao WebSocket + E-mail para o gestor da loja:
     "Calendario de ferias 2026 proposto. {N} programacoes aguardam sua confirmacao."

3. Gestor da loja revisa
   - Ve todas as ferias propostas da sua loja em visao calendario
   - Pode aprovar individualmente ou em lote ("Confirmar Calendario")
   - Pode solicitar alteracoes (rejeitar com notas)
   - Acao em lote: botao "Aprovar Todas" com confirmacao

4. Funcionario recebe ciencia
   - Apos aprovacao do gestor, funcionario recebe notificacao:
     "Suas ferias foram programadas: {data_inicio} a {data_fim} ({dias} dias)"
   - Funcionario deve confirmar ciencia (botao "Ciente" na notificacao)
   - Campo `employee_acknowledged_at` em adms_vacations
   - Se nao confirmar em 7 dias: lembrete automatico

5. RH finaliza
   - Aprovacao final do RH (status 3→4)
   - Gera relatorio de programacao confirmada
```

**Novo campo em `adms_vacations`:**
```sql
ALTER TABLE `adms_vacations`
    ADD COLUMN `employee_acknowledged_at` DATETIME DEFAULT NULL
    COMMENT 'Data em que o funcionario confirmou ciencia das ferias';
```

**Novos endpoints:**
- `POST /approve-vacation/batch-approve` — Aprovacao em lote pelo gestor
- `POST /vacations/acknowledge` — Funcionario confirma ciencia
- `GET /vacations/calendar-review/{storeId}` — Visao calendario para revisao do gestor

**Nova view:**
```
Views/vacations/partials/_calendar_review_partial.php  — Visao calendario para confirmacao
Views/vacations/partials/_batch_approve_modal.php      — Modal de aprovacao em lote
Views/vacations/partials/_acknowledge_modal.php        — Modal de ciencia do funcionario
```

#### 3.4.5 Lembretes Automaticos (Cron)

| Lembrete | Frequencia | Destinatarios | Condicao |
|---|---|---|---|
| Ferias em 7 dias | Diaria | Funcionario + gestor | Status = Aprovada RH (4) |
| Ferias em 3 dias | Diaria | Funcionario | Status = Aprovada RH (4) |
| Prazo de pagamento em 3 dias | Diaria | RH (financeiro) | `payment_deadline` proximo |
| Ciencia pendente | Diaria | Funcionario | `employee_acknowledged_at` IS NULL e aprovada ha > 3 dias |
| Calendario pendente de revisao | Semanal | Gestor da loja | Solicitacoes pendentes ha > 7 dias |

### 3.5 Views de Aprovacao e Confirmacao

| Arquivo | Tipo |
|---|---|
| `Views/vacations/partials/_approve_vacation_modal.php` | Modal aprovacao (gestor) — inclui alertas de conflito de lotacao |
| `Views/vacations/partials/_approve_hr_vacation_modal.php` | Modal aprovacao (RH) — inclui alerta de prazo de pagamento |
| `Views/vacations/partials/_reject_vacation_modal.php` | Modal rejeicao (notas obrigatorias) |
| `Views/vacations/partials/_cancel_vacation_modal.php` | Modal cancelamento |
| `Views/vacations/partials/_batch_approve_modal.php` | Modal aprovacao em lote (calendario proposto) |
| `Views/vacations/partials/_acknowledge_modal.php` | Modal ciencia do funcionario |
| `Views/vacations/partials/_calendar_review_partial.php` | Visao calendario para revisao do gestor |
| `Views/vacations/partials/_staff_conflict_alert.php` | Partial de alerta de conflito de lotacao (reutilizavel) |

### 3.6 Testes Fase 3

| Arquivo | Cobertura |
|---|---|
| `tests/Vacations/VacationStatusTransitionServiceTest.php` | Transicoes de status, permissoes, acoes colaterais |
| `tests/Vacations/ApproveVacationTest.php` | Fluxo completo de aprovacao/rejeicao |

**Entregavel:** Fluxo de aprovacao multi-nivel (Gestor → RH), notificacoes WebSocket, maquina de estados, log completo de acoes.

---

## Fase 4: Integracao Funcionarios + Metas por Lojas

Esta e a fase mais critica do modulo, pois conecta ferias aos dois sistemas existentes.

### 4.1 Integracao com Funcionarios (`adms_employees`)

#### 4.1.1 Atualizacao Automatica de Status

Quando ferias transitam para **Em Gozo (5)**:
```php
// Em VacationStatusTransitionService, ao transicionar 4→5:
// 1. Salvar status atual do funcionario (para restaurar depois)
$currentStatus = $employee['adms_status_employee_id'];
// Armazenar em adms_vacations.previous_employee_status (coluna auxiliar)

// 2. Atualizar status do funcionario
// NOTA: O status "Ferias" NÃO existe hoje em adms_status_employees.
// Sera necessario verificar os IDs existentes e adicionar se necessario.
// Exemplo: INSERT INTO adms_status_employees (description_name, adms_cor_id) VALUES ('Ferias', 5);
$update->exeUpdate("adms_employees",
    "adms_status_employee_id = :status",
    "status={$vacationStatusId}",
    "WHERE id = :id", "id={$employeeId}");

// 3. LoggerService
LoggerService::info('EMPLOYEE_STATUS_VACATION', 'Funcionario entrou em ferias', [
    'employee_id' => $employeeId,
    'vacation_id' => $vacationId,
    'previous_status' => $currentStatus
]);
```

Quando ferias transitam para **Finalizada (6)** ou **Cancelada (7)**:
```php
// Restaurar status anterior do funcionario
$previousStatus = $vacation['previous_employee_status'] ?? 1; // fallback para Ativo
$update->exeUpdate("adms_employees",
    "adms_status_employee_id = :status",
    "status={$previousStatus}",
    "WHERE id = :id", "id={$employeeId}");
```

#### 4.1.2 Coluna Auxiliar em `adms_vacations`

```sql
ALTER TABLE `adms_vacations`
    ADD COLUMN `previous_employee_status` INT DEFAULT NULL
    COMMENT 'Status do funcionario antes das ferias (para restaurar)';
```

#### 4.1.3 Geracao de Periodos a Partir de Admissao

O `VacationPeriodGeneratorService` consulta `adms_employees`:

```php
public function generateForEmployee(int $employeeId): array
{
    // 1. Buscar date_admission e adms_status_employee_id
    $read->fullRead(
        "SELECT id, name_employee, date_admission, adms_status_employee_id, adms_store_id
         FROM adms_employees WHERE id = :id",
        "id={$employeeId}"
    );

    // 2. Validar: funcionario ativo e com data de admissao
    // 3. Calcular todos os periodos desde admissao ate hoje
    // 4. Verificar quais ja existem em adms_vacation_periods
    // 5. Criar apenas os faltantes
    // 6. Atualizar status de periodos vencidos
}
```

#### 4.1.4 Validacao de Elegibilidade

Antes de permitir solicitacao de ferias, verificar:
```php
// 1. Funcionario ativo
if ($employee['adms_status_employee_id'] != 1) {
    return ['error' => 'Funcionario nao esta ativo'];
}

// 2. Possui periodo aquisitivo disponivel
// 3. Nao possui ferias sobrepostas
// 4. Nao esta em periodo de experiencia (< 90 dias de admissao)
```

#### 4.1.5 Impacto no Modulo de Funcionarios (Existente)

Adicionar na view de detalhes do funcionario (`_view_employee_details.php`):
- Aba/secao "Ferias" com:
  - Periodos aquisitivos do funcionario
  - Saldo atual de dias
  - Historico de ferias gozadas
  - Proximas ferias agendadas
  - Link para solicitar ferias

---

### 4.2 Integracao com Metas por Lojas (`adms_store_goals`)

#### 4.2.1 Trigger de Redistribuicao

O `StoreGoalsRedistributionService` ja possui o metodo `redistributeFromMedicalLeave()` que funciona para ausencias >= 10 dias. A integracao com ferias segue o **mesmo padrao**:

```php
// Em VacationStatusTransitionService, ao transicionar 3→4 (Aprovado RH):
// Se days_quantity >= 10 E employee.position_id == 1 (consultor):

use App\adms\Services\StoreGoalsRedistributionService;

$redistributionService = new StoreGoalsRedistributionService();
$redistributionService->redistributeFromVacation(
    $employeeId,
    $storeId,
    $vacation['date_start'],
    $vacation['date_end'],
    $vacation['days_quantity']
);
```

#### 4.2.2 Novo Metodo em StoreGoalsRedistributionService

Adicionar ao service existente:

```php
/**
 * Redistribui metas quando um consultor entra em ferias aprovadas >= 10 dias
 * Segue o mesmo padrao de redistributeFromMedicalLeave()
 */
public function redistributeFromVacation(
    int $employeeId,
    string $storeId,
    string $dateStart,
    string $dateEnd,
    int $daysAway
): bool {
    // Somente redistribuir se >= MIN_VACATION_DAYS (10)
    if ($daysAway < self::MIN_VACATION_DAYS) {
        return false;
    }

    // Somente se for consultor (position_id = 1)
    $employee = $this->getEmployeeData($employeeId);
    if ($employee['position_id'] != 1) {
        return false;
    }

    // Determinar meses afetados
    $affectedMonths = $this->getAffectedMonths($dateStart, $dateEnd);

    // Redistribuir cada mes (apenas atual + futuros)
    foreach ($affectedMonths as $month) {
        $this->redistribute($storeId, $month['month'], $month['year']);
    }

    LoggerService::info('GOALS_REDISTRIBUTED_VACATION', 'Metas redistribuidas por ferias', [
        'employee_id' => $employeeId,
        'store_id' => $storeId,
        'date_start' => $dateStart,
        'date_end' => $dateEnd,
        'days_away' => $daysAway,
        'affected_months' => count($affectedMonths)
    ]);

    return true;
}
```

#### 4.2.3 Ajuste no getEligibleConsultants()

O metodo `getEligibleConsultants()` ja exclui consultores inativos. Para excluir consultores em ferias, adicionar a query:

```sql
-- Dentro de getEligibleConsultants(), adicionar LEFT JOIN com ferias aprovadas
LEFT JOIN adms_vacations v ON v.adms_employee_id = e.id
    AND v.adms_status_vacation_id IN (4, 5) -- Aprovada RH ou Em Gozo
    AND v.date_start <= LAST_DAY(:yearMonth)
    AND v.date_end >= :yearMonthFirst

-- No calculo de effective_days, descontar dias de ferias no mes:
-- vacation_days_in_month = DATEDIFF(
--     LEAST(v.date_end, LAST_DAY(:yearMonth)),
--     GREATEST(v.date_start, :yearMonthFirst)
-- ) + 1
```

#### 4.2.4 Cenarios de Redistribuicao

| Evento | Acao nas Metas |
|---|---|
| Ferias aprovadas (>= 10 dias, consultor) | Redistribuir metas dos meses afetados |
| Ferias canceladas (apos aprovacao) | Redistribuir novamente (consultor volta ao pool) |
| Ferias finalizadas | Redistribuir mes seguinte se aplicavel |
| Ferias < 10 dias | Nenhuma acao (consultor continua elegivel) |
| Ferias de nao-consultor | Nenhuma acao (position_id != 1) |

#### 4.2.5 Notificacao de Impacto em Metas

Ao aprovar ferias de consultor >= 10 dias:
```php
SystemNotificationService::notify(
    $storeManagerUserId,
    'vacation_goals_impact',
    'store_goals',
    "Ferias aprovadas para {$employeeName} ({$daysQuantity} dias)",
    "As metas individuais da loja {$storeId} foram recalculadas automaticamente.",
    ['icon' => 'fa-chart-bar', 'color' => 'warning', 'action_url' => '/store-goals/list']
);
```

### 4.3 Testes Fase 4

| Arquivo | Cobertura |
|---|---|
| `tests/Vacations/VacationEmployeeIntegrationTest.php` | Status do funcionario, geracao de periodos, elegibilidade |
| `tests/Vacations/VacationGoalsIntegrationTest.php` | Redistribuicao de metas, cenarios de aprovacao/cancelamento |
| `tests/StoreGoals/StoreGoalsVacationRedistributionTest.php` | Metodo redistributeFromVacation no service existente |

**Entregavel:** Atualizacao automatica de status do funcionario durante ferias; redistribuicao automatica de metas ao aprovar ferias de consultores >= 10 dias; notificacoes de impacto para gestores.

---

## Fase 5: Dashboard, Relatorios e Exportacao

### 5.1 Dashboard de Ferias

| Arquivo | Tipo |
|---|---|
| `Controllers/Vacations.php` | Metodos `dashboard()` e `dashboardData()` |
| `Models/AdmsDashboardVacations.php` | Model com queries de agregacao |
| `Views/vacations/loadVacationDashboard.php` | Pagina do dashboard |
| `assets/js/vacation-dashboard.js` | Chart.js graficos |

**KPI Cards (6):**
1. Total de Funcionarios em Ferias (agora)
2. Solicitacoes Pendentes de Aprovacao
3. Periodos Vencendo (< 60 dias)
4. Periodos Vencidos (ferias dobradas)
5. Dias Medios de Gozo (ultimos 12 meses)
6. Taxa de Aprovacao (%)

**Graficos Chart.js (4):**
1. **Calendario Mensal** (Bar) — funcionarios em ferias por mes (12 meses)
2. **Distribuicao por Loja** (Bar horizontal) — ferias agendadas por loja
3. **Status das Solicitacoes** (Doughnut) — pendentes, aprovadas, rejeitadas, etc.
4. **Periodos Aquisitivos** (Stacked Bar) — disponiveis vs gozados vs vencidos por loja

**Filtros:** loja (condicional por nivel), periodo (data inicio/fim)

### 5.2 Calendario Visual

| Arquivo | Tipo |
|---|---|
| `Views/vacations/partials/_vacation_calendar_partial.php` | Calendario mensal |
| `assets/js/vacation-calendar.js` | Renderizacao do calendario |

Funcionalidades:
- Visao mensal em grade (similar a calendario)
- Cada funcionario = uma linha
- Dias de ferias = barras coloridas (por status: aprovado verde, pendente amarelo, gozo azul)
- Filtro por loja
- Navegacao mes anterior/proximo
- Responsivo: em mobile, mostra lista em vez de grade

**Nota:** Implementacao mais simples que Gantt — usa `<table>` com colunas = dias do mes, sem dependencia de biblioteca externa.

### 5.3 Relatorios e Exportacao

| Arquivo | Tipo |
|---|---|
| `Controllers/ExportVacation.php` | Controller exportacao |
| `Models/AdmsExportVacation.php` | Model dados de exportacao |

Tipos de exportacao:

| Tipo | Formato | Destino |
|---|---|---|
| Listagem de ferias | Excel (PhpSpreadsheet) | Gestao interna |
| Exportacao para folha | CSV (delimitador `;`, BOM UTF-8) | Sistema de folha de pagamento |
| Relatorio de vencimentos | PDF (DomPDF) | RH para acompanhamento |

**Exportacao para Folha de Pagamento (CSV):**
Colunas:
```
CPF;NOME;LOJA;DATA_INICIO;DATA_FIM;DIAS_GOZO;ABONO_PECUNIARIO;DIAS_VENDIDOS;ADIANTAMENTO_13;PARCELA;PERIODO_AQUISITIVO_INICIO;PERIODO_AQUISITIVO_FIM
```

Filtros: loja, mes referencia, status (apenas Aprovada RH ou Em Gozo)

**Relatorio de Vencimentos (PDF):**
- Lista funcionarios com periodos vencendo em 30/60/90 dias
- Destaque para periodos ja vencidos (ferias dobradas)
- Agrupado por loja
- DomPDF com chunks de 200 linhas (padrao Stock Audit para tabelas grandes)

### 5.4 Rotina Automatica (Cron Jobs)

| Tarefa | Frequencia | Descricao |
|---|---|---|
| `checkVacationStart` | Diaria | Transiciona ferias Aprovadas (4) para Em Gozo (5) na data de inicio |
| `checkVacationEnd` | Diaria | Transiciona ferias Em Gozo (5) para Finalizada (6) na data de retorno |
| `sendPeriodAlerts` | Diaria | Alertas escalonados de periodos (90/60/30/vencido) — verifica `adms_vacation_alert_log` para nao repetir |
| `updateExpiredPeriods` | Diaria | Atualiza status de periodos que passaram do limite concessivo + notifica RH/Diretoria |
| `generateNewPeriods` | Mensal | Gera novos periodos aquisitivos para funcionarios ativos |
| `reminderUpcomingVacation` | Diaria | Notifica funcionario + gestor 7 dias antes; funcionario 3 dias antes |
| `reminderPaymentDeadline` | Diaria | Notifica RH/financeiro quando `payment_deadline` esta a 3 dias |
| `reminderAcknowledge` | Diaria | Lembra funcionario de confirmar ciencia (se pendente > 3 dias) |
| `reminderPendingCalendar` | Semanal | Lembra gestor de revisar calendario proposto (se pendente > 7 dias) |

Implementacao via endpoint interno ou CLI script em `bin/vacation-cron.php`.

### 5.5 Testes Fase 5

| Arquivo | Cobertura |
|---|---|
| `tests/Vacations/AdmsDashboardVacationsTest.php` | KPIs, agregacoes, filtros |
| `tests/Vacations/AdmsExportVacationTest.php` | Exportacao CSV, Excel, dados |
| `tests/Vacations/VacationCronTest.php` | Transicoes automaticas, notificacoes |

**Entregavel:** Dashboard com KPIs e graficos, calendario visual de ferias, exportacao para folha de pagamento, relatorio de vencimentos, rotinas automaticas.

---

## Resumo de Arquivos por Fase

| Fase | Controllers | Models | Services | Views | JS | Tests | Total |
|---|---|---|---|---|---|---|---|
| **1** Fundacao | 5 | 5 | 1 | 10 | 2 | 2 | **25** |
| **2** Validacao CLT | 5 | 5 | 2 | 8 | 1 | 3 | **24** |
| **3** Aprovacao + Alertas | 1 | — | 1 | 8 | 1 (mod) | 2 | **13** |
| **4** Integracoes | — | — | 1 (mod) | 1 (mod) | — | 3 | **5** |
| **5** Dashboard + Cron | 1 | 2 | — | 3 | 2 | 3 | **11** |
| **Total** | **12** | **12** | **5** | **30** | **6** | **13** | **~78** |

---

## Dependencias Externas

| Dependencia | Uso | Status |
|---|---|---|
| PhpSpreadsheet 5.3 | Export Excel | Ja instalado |
| DomPDF 3.0 | Relatorio PDF vencimentos | Ja instalado |
| Chart.js 3.9.1 | Graficos dashboard (CDN) | Ja disponivel |
| SystemNotificationService | Notificacoes WebSocket | Ja disponivel |
| StoreGoalsRedistributionService | Redistribuicao de metas | Ja disponivel (requer novo metodo) |
| FormSelectRepository | Selects de funcionarios/lojas | Ja disponivel |
| LoggerService | Auditoria de operacoes | Ja disponivel |
| SessionContext | Controle de sessao/permissao | Ja disponivel |
| StorePermissionTrait | Filtro por loja | Ja disponivel |

---

## Regras Implementadas (CLT + Internas)

### Regras CLT

| Artigo | Regra | Implementacao |
|---|---|---|
| **Art. 129** | Direito a ferias apos 12 meses de trabalho | `VacationPeriodGeneratorService` |
| **Art. 130** | Reducao de dias por faltas (tabela 30/24/18/12/0) | `VacationCalculationService::calculateDaysEntitledByAbsences()` |
| **Art. 134 §1** | Fracionamento em ate 3 parcelas (1 >= 14 dias, demais >= 5 dias) | `VacationValidatorService::validateMinDays()` |
| **Art. 134 §3** | Inicio nao pode ser 2 dias antes de feriado ou DSR | `VacationValidatorService::validateBlackoutDates()` |
| **Art. 135** | Comunicacao com 30 dias de antecedencia | `VacationValidatorService::validateAdvanceNotice()` |
| **Art. 136** | Menor de 18: coincide com ferias escolares | `VacationValidatorService::validateMinorRestrictions()` |
| **Art. 137** | Ferias dobradas se nao concedidas no periodo concessivo | `VacationPeriodGeneratorService::checkExpiredPeriods()` |
| **Art. 143** | Abono pecuniario de ate 1/3 dos dias | `VacationValidatorService::validateSellAllowance()` |
| **Art. 145** | Pagamento ate 2 dias antes do inicio | `VacationCalculationService::calculatePaymentDeadline()` |

### Regras Internas (Grupo Meia Sola)

| Regra | Tipo | Implementacao |
|---|---|---|
| Inicio preferencial no 1o dia util do mes | Warning (nao bloqueia) | `VacationValidatorService::validateStartDate()` |
| Gerentes de loja (position_id=23): padrao 15 dias | Default + override | `VacationValidatorService::validateDefaultDaysByPosition()` |
| Demais funcionarios de loja: padrao 30 dias | Default + override | `VacationValidatorService::validateDefaultDaysByPosition()` |
| Alteracao do padrao exige justificativa obrigatoria | Bloqueio | `VacationValidatorService::validateDefaultDaysByPosition()` |
| Nao pode iniciar em sabado ou domingo | Bloqueio | `VacationValidatorService::validateBlackoutDates()` |

---

## Ordem de Execucao Recomendada

```
Fase 1 (Fundacao)
  ├── 1.1 Migration SQL (todas as tabelas + seeds)
  ├── 1.2 Seed de feriados nacionais
  ├── 1.3 Rotas e permissoes
  ├── 1.4 CRUD Feriados (AbstractConfigController)
  ├── 1.5 CRUD Periodos Aquisitivos
  ├── 1.6 VacationPeriodGeneratorService
  └── 1.7 Testes

Fase 2 (Validacao CLT)
  ├── 2.1 VacationValidatorService (todas as regras)
  ├── 2.2 VacationCalculationService
  ├── 2.3 CRUD Solicitacoes (controllers + models + views)
  ├── 2.4 JavaScript (calculo tempo real + validacoes)
  └── 2.5 Testes

Fase 3 (Aprovacao)
  ├── 3.1 Fluxo multi-nivel (Gestor → RH)
  ├── 3.2 ApproveVacation controller
  ├── 3.3 VacationStatusTransitionService
  ├── 3.4 Notificacoes WebSocket
  ├── 3.5 Views de aprovacao/rejeicao
  └── 3.6 Testes

Fase 4 (Integracoes) ⭐ CRITICA
  ├── 4.1 Integracao Funcionarios
  │   ├── Atualizacao status (Ativo ↔ Ferias)
  │   ├── Geracao periodos a partir de date_admission
  │   └── Secao "Ferias" na view do funcionario
  ├── 4.2 Integracao Metas por Lojas
  │   ├── Metodo redistributeFromVacation()
  │   ├── Ajuste em getEligibleConsultants()
  │   └── Notificacao de impacto em metas
  └── 4.3 Testes de integracao

Fase 5 (Dashboard + Relatorios)
  ├── 5.1 Dashboard com KPIs e graficos
  ├── 5.2 Calendario visual
  ├── 5.3 Exportacao (Excel, CSV folha, PDF vencimentos)
  ├── 5.4 Rotinas automaticas (cron)
  └── 5.5 Testes
```

---

## Consideracoes de Seguranca

| Item | Implementacao |
|---|---|
| **SQL Injection** | Prepared statements via AdmsRead/AdmsCreate/AdmsUpdate |
| **XSS** | `htmlspecialchars()` em todos os outputs nas views |
| **CSRF** | Token via `CsrfService` em todos os formularios |
| **Permissoes** | `adms_nivacs_pgs` + `AdmsBotao` para botoes de acao |
| **Filtro por loja** | `StorePermissionTrait` em todos os models de listagem |
| **Auditoria** | `LoggerService` em todas as operacoes CRUD e transicoes |
| **Sessao** | `SessionContext` (nunca `$_SESSION` direto nos models/controllers) |
| **Dados sensiveis** | CPF nunca exibido completo em listagens (mascarar: `***.123.456-**`) |

---

## Riscos e Mitigacoes

| Risco | Impacto | Mitigacao |
|---|---|---|
| Faltas injustificadas sem registro no sistema | Calculo incorreto de `days_entitled` | Permitir input manual de faltas no periodo aquisitivo; integracao futura com AbsenceControl |
| Feriados moveis nao atualizados | Blackout dates incorretas | Rotina anual de atualizacao + campo `recurring` para fixos vs moveis |
| Redistribuicao de metas incorreta | Metas desbalanceadas | Testes de integracao extensivos; log detalhado; botao de recalculo manual |
| Funcionario demitido durante ferias | Status inconsistente | Validar status antes de restaurar; tratar demissao como caso especial |
| Volume de periodos (carga inicial) | Lentidao na geracao em lote | Processar em chunks; `session_write_close` + progress bar |
