# Modulo 1B: Ferias (Vacation Management)

**Status:** Pendente
**Fase:** 1B
**Prioridade:** CRITICA — Compliance CLT
**Estimativa:** ~22 arquivos novos
**Referencia v1:** `C:\wamp64\www\mercury\app\adms\Controllers\Vacations.php`, `Services\VacationValidatorService.php`
**Doc v1:** `docs/PROPOSTA_MODULO_FERIAS.md`

---

## 1. Visao Geral

Controle centralizado de periodos aquisitivos e gozo de ferias dos colaboradores com conformidade CLT. Inclui state machine de 9 estados, validacao de regras trabalhistas, geracao automatica de periodos aquisitivos e integracao com Employee (sync de status).

---

## 2. State Machine (9 estados)

```
Rascunho (draft)
    ↓ submit
Pendente Gestor (pending_manager)
    ↓ approve          ↓ reject
Aprovada Gestor       Rejeitada Gestor (rejected_manager)
(approved_manager)
    ↓ approve          ↓ reject
Aprovada RH           Rejeitada RH (rejected_rh)
(approved_rh)
    ↓ start
Em Gozo (in_progress)
    ↓ complete
Finalizada (completed)

Qualquer 1-4 → Cancelada (cancelled)
```

### Transicoes Validas
```php
VALID_TRANSITIONS = [
    'draft' => ['pending_manager', 'cancelled'],
    'pending_manager' => ['approved_manager', 'rejected_manager', 'cancelled'],
    'approved_manager' => ['approved_rh', 'rejected_rh', 'cancelled'],
    'approved_rh' => ['in_progress', 'cancelled'],
    'in_progress' => ['completed', 'cancelled'],
    'rejected_manager' => ['draft'],
    'rejected_rh' => ['approved_manager'],
];
```

### Side Effects
| Transicao | Efeito |
|-----------|--------|
| → in_progress | employee.status_id = 4 (Ferias) |
| → completed | employee.status_id = 2 (Ativo) |
| → cancelled (de in_progress) | Reverter days_taken + restaurar employee.status |

---

## 3. Regras CLT (VacationValidatorService)

| # | Artigo | Regra | Validacao |
|---|--------|-------|-----------|
| 1 | Art. 130 | Direito apos 12 meses de servico | admission_date + 12 meses |
| 2 | Art. 130 §1-2 | Reducao por faltas: >5=24d, >14=18d, >23=12d, >32=0d | Contar absences no periodo |
| 3 | Art. 134 | Gozo dentro de 12 meses apos aquisicao | date_limit_concessive |
| 4 | Art. 134 §1 | Max 3 parcelas por periodo | count(vacation_periods) |
| 5 | Art. 134 §1(I) | Uma parcela ≥ 14 dias | max(days_count) >= 14 |
| 6 | Art. 134 §1(II) | Nenhuma parcela < 5 dias | min(days_count) >= 5 |
| 7 | Art. 135 | Aviso 30 dias antes do inicio | start_date - today >= 30 |
| 8 | Art. 136 | Nao iniciar 2 dias antes de feriado/descanso | check holidays + weekends |
| 9 | Art. 137 | Ferias dobradas se vencido o periodo concessivo | alert |
| 10 | Art. 143 | Abono pecuniario max 1/3 (max 10 dias) | sell_days <= entitled/3 |
| 11 | Art. 145 | Pagamento 2 dias antes do inicio | warning |
| 12 | Custom | Funcionario deve ser CLT ativo | employment_relationship_id = 1 |

---

## 4. Arquivos a Criar

### Migrations (5)
```
database/migrations/tenant/
├── YYYY_MM_DD_100001_create_vacations_table.php
├── YYYY_MM_DD_100002_create_vacation_periods_table.php
├── YYYY_MM_DD_100003_create_vacation_logs_table.php
├── YYYY_MM_DD_100004_create_holidays_table.php
└── YYYY_MM_DD_100005_create_vacation_alert_logs_table.php
```

### Models (5)
```
app/Models/
├── Vacation.php              — 9 status, VALID_TRANSITIONS, Auditable
├── VacationPeriod.php        — belongsTo vacation, period_number 1-3
├── VacationLog.php           — old_status, new_status, changed_by
├── Holiday.php               — name, date, type, is_recurring
└── VacationAlertLog.php      — employee_id, alert_type, sent_at
```

### Services (4)
```
app/Services/
├── VacationValidatorService.php       — 12 regras CLT
├── VacationCalculationService.php     — saldo, reducao por faltas
├── VacationTransitionService.php      — state machine (padrao OrderPaymentTransitionService)
└── VacationPeriodGeneratorService.php — gera periodos aquisitivos
```

### Controller (1)
```
app/Http/Controllers/VacationController.php
— index, store, show, update, destroy, transition, balance, storeHoliday, updateHoliday, destroyHoliday, export
```

### Frontend (5)
```
resources/js/
├── Pages/Vacations/Index.jsx
└── Components/
    ├── VacationCreateModal.jsx
    ├── VacationDetailModal.jsx
    ├── VacationBalanceCard.jsx
    └── HolidayManageModal.jsx
```

### Export (1)
```
app/Exports/VacationsExport.php
```

### Tests (1)
```
tests/Feature/VacationControllerTest.php — ~25-30 cenarios
```

---

## 5. Permissions

```php
// Permission.php
case VIEW_VACATIONS = 'vacations.view';
case CREATE_VACATIONS = 'vacations.create';
case EDIT_VACATIONS = 'vacations.edit';
case DELETE_VACATIONS = 'vacations.delete';
case APPROVE_VACATIONS_MANAGER = 'vacations.approve_manager';
case APPROVE_VACATIONS_RH = 'vacations.approve_rh';
case MANAGE_HOLIDAYS = 'vacations.manage_holidays';
```

**Atribuicao de Roles:**
- SUPER_ADMIN/ADMIN: todas
- SUPPORT: VIEW, CREATE, EDIT, APPROVE_MANAGER
- USER: VIEW (somente proprio)

---

## 6. Schema das Tabelas

### vacations
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | bigint PK | Auto increment |
| employee_id | bigint FK | employees.id |
| acquisitive_period_start | date | Inicio periodo aquisitivo |
| acquisitive_period_end | date | Fim periodo aquisitivo |
| date_limit_concessive | date | Limite para gozo (12m apos fim aquisitivo) |
| days_entitled | int | Dias de direito (30, reduzido por faltas) |
| days_taken | int default 0 | Dias ja usufruidos no periodo |
| status | varchar(30) | draft, pending_manager, etc. |
| sell_days | int default 0 | Dias vendidos (abono pecuniario) |
| advance_13th | boolean default false | Adiantamento de 13o |
| observations | text nullable | |
| created_by_user_id | bigint FK | |
| approved_manager_by_user_id | bigint FK nullable | |
| approved_manager_at | timestamp nullable | |
| approved_rh_by_user_id | bigint FK nullable | |
| approved_rh_at | timestamp nullable | |
| rejected_by_user_id | bigint FK nullable | |
| rejected_at | timestamp nullable | |
| rejection_reason | text nullable | |
| cancelled_by_user_id | bigint FK nullable | |
| cancelled_at | timestamp nullable | |
| cancellation_reason | text nullable | |
| finalized_at | timestamp nullable | |
| timestamps | | |
| deleted_at | timestamp nullable | Soft delete |
| deleted_by_user_id | bigint FK nullable | |
| delete_reason | text nullable | |

### vacation_periods
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | bigint PK | |
| vacation_id | bigint FK | vacations.id |
| period_number | tinyint | 1, 2 ou 3 |
| start_date | date | Inicio do gozo |
| end_date | date | Fim do gozo |
| days_count | int | Quantidade de dias |
| is_abono | boolean | Se e abono pecuniario |
| timestamps | | |

### vacation_logs
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | bigint PK | |
| vacation_id | bigint FK | |
| old_status | varchar(30) nullable | |
| new_status | varchar(30) | |
| changed_by_user_id | bigint FK | |
| notes | text nullable | |
| timestamps | | |

### holidays
| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | bigint PK | |
| name | varchar(100) | |
| date | date | |
| type | varchar(20) | national, state, municipal |
| is_recurring | boolean default true | Repetir todo ano |
| year | int nullable | Se nao recorrente, ano especifico |
| is_active | boolean default true | |
| timestamps | | |

---

## 7. Testes Principais

1. CRUD com verificacao de permissoes
2. Validacao CLT Art. 130 (12 meses)
3. Validacao Art. 134 (fracionamento: ≥14d, ≥5d, max 3 parcelas)
4. Validacao Art. 136 (blackout: 2 dias antes feriado)
5. Validacao Art. 143 (abono max 1/3)
6. Transicoes de estado validas e invalidas
7. Side effects: employee status sync
8. Calculo de saldo
9. Export Excel
10. CRUD de feriados

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
