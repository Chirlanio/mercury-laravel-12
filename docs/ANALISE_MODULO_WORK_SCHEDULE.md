# Módulo de Escala de Trabalho (Work Schedule)

**Data de Criação:** 05 de Fevereiro de 2026
**Data de Conclusão:** 05 de Fevereiro de 2026
**Versão:** 2.0 (Implementado)
**Status:** ✅ **CONCLUÍDO**
**Autor:** Equipe Mercury - Grupo Meia Sola

---

## 1. Resumo Executivo

Módulo de **Controle de Escala de Trabalho** integrado aos módulos de **Funcionários (Employees)** e **Controle de Horas Extras (OvertimeControl)**.

### 1.1. Objetivo

Sistema para gerenciar escalas de trabalho dos funcionários:
- ✅ Dias da semana trabalhados
- ✅ Horários de entrada e saída
- ✅ Horários de intervalo (almoço/descanso)
- ✅ Personalização por funcionário (overrides de folga)

### 1.2. Integrações Implementadas

| Módulo | Integração | Status |
|--------|------------|--------|
| **Employees** | Exibir/editar escala na visualização do funcionário | ✅ Concluído |
| **OvertimeControl** | Validar se horas extras estão fora da escala normal | ✅ Concluído |

---

## 2. Status de Implementação

### 2.1. Fases Concluídas

#### ✅ Fase 1: Infraestrutura
- [x] Criar tabelas no banco de dados
- [x] Implementar CRUD básico de escalas
- [x] Criar escalas padrão (5x2, 6x1, Shopping)

#### ✅ Fase 2: Integração Employees
- [x] Vincular funcionários às escalas
- [x] Exibir escala na visualização do funcionário
- [x] Exibir dias e horários detalhados
- [x] Permitir alteração de escala na edição
- [x] Histórico de escalas do funcionário

#### ✅ Fase 3: Validação OvertimeControl
- [x] Implementar validador de escala (`AdmsWorkScheduleValidator`)
- [x] Integrar validação no cadastro de horas extras
- [x] Integrar validação na edição de horas extras
- [x] Adicionar mensagens de erro descritivas
- [x] 17 testes unitários para o validador

#### ✅ Fase 4: Melhorias
- [x] Histórico de alterações de escala
- [x] Escalas personalizadas por funcionário (overrides)
- [x] Interface para gerenciar folgas personalizadas

---

## 3. Arquitetura Implementada

### 3.1. Estrutura de Arquivos

```
app/adms/Controllers/
├── WorkSchedule.php                    # Controller principal (listagem)
├── AddWorkSchedule.php                 # Adicionar escala
├── EditWorkSchedule.php                # Editar escala
├── DeleteWorkSchedule.php              # Deletar escala
├── ViewWorkSchedule.php                # Visualizar escala
└── EmployeeScheduleOverride.php        # Gerenciar overrides por funcionário

app/adms/Models/
├── AdmsAddWorkSchedule.php             # Criar escala
├── AdmsEditWorkSchedule.php            # Editar escala
├── AdmsDeleteWorkSchedule.php          # Deletar escala
├── AdmsViewWorkSchedule.php            # Visualizar escala
├── AdmsListWorkSchedules.php           # Listagem com paginação
├── AdmsStatisticsWorkSchedules.php     # Estatísticas
├── AdmsWorkScheduleValidator.php       # Validador para OvertimeControl
└── AdmsEmployeeScheduleOverride.php    # CRUD de overrides

app/adms/Views/workSchedule/
├── loadWorkSchedule.php                # Página principal
├── listWorkSchedule.php                # Listagem AJAX
└── partials/
    ├── _add_work_schedule_modal.php
    ├── _edit_work_schedule_modal.php
    ├── _view_work_schedule_modal.php
    └── _delete_work_schedule_modal.php

assets/js/
├── work-schedule.js                    # JavaScript do módulo
└── employees.js                        # Funções de override integradas

tests/
├── WorkSchedule/                       # 129 testes do módulo
└── OvertimeControl/
    └── AdmsWorkScheduleValidatorTest.php  # 17 testes do validador
```

### 3.2. Modelo de Dados

```
┌─────────────────────────────────┐
│      adms_work_schedules        │
├─────────────────────────────────┤
│ id (PK)                         │
│ name                            │
│ description                     │
│ weekly_hours                    │
│ is_active                       │
│ is_default                      │
│ created_by_user_id (FK)         │
│ updated_by_user_id (FK)         │
│ created_at                      │
│ updated_at                      │
└─────────────────────────────────┘
              │
              │ 1:N
              ▼
┌─────────────────────────────────┐
│   adms_work_schedule_days       │
├─────────────────────────────────┤
│ id (PK)                         │
│ adms_work_schedule_id (FK)      │
│ day_of_week (0-6)               │
│ is_work_day                     │
│ entry_time                      │
│ exit_time                       │
│ break_start                     │
│ break_end                       │
│ break_duration_minutes          │  ← Calculado por trigger
│ daily_hours                     │  ← Calculado por trigger
│ notes                           │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ adms_employee_work_schedules    │
├─────────────────────────────────┤
│ id (PK)                         │
│ adms_employee_id (FK)           │
│ adms_work_schedule_id (FK)      │
│ effective_date                  │
│ end_date                        │  ← NULL = vigente
│ notes                           │
│ created_by_user_id (FK)         │
│ created_at                      │
└─────────────────────────────────┘
              │
              │ 1:N
              ▼
┌─────────────────────────────────────────┐
│ adms_employee_schedule_day_overrides    │
├─────────────────────────────────────────┤
│ id (PK)                                 │
│ adms_employee_work_schedule_id (FK)     │
│ day_of_week (0-6)                       │
│ is_work_day                             │  ← Override de folga
│ entry_time                              │  ← Override de horário
│ exit_time                               │
│ break_start                             │
│ break_end                               │
│ reason                                  │
│ created_by_user_id (FK)                 │
│ updated_by_user_id (FK)                 │
│ created_at                              │
│ updated_at                              │
└─────────────────────────────────────────┘
```

---

## 4. Validação no OvertimeControl

### 4.1. Fluxo de Validação

```
┌───────────────────────────────────────────────────────────────┐
│                   Cadastro de Hora Extra                      │
└───────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────┐
│ 1. Buscar escala vigente do funcionário                       │
│    - Considera effective_date e end_date                      │
│    - Retorna NULL se não houver escala (permite hora extra)   │
└───────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────┐
│ 2. Buscar configuração do dia (com overrides)                 │
│    - Primeiro verifica se há override para o dia              │
│    - Se não houver, usa configuração da escala base           │
└───────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────┐
│ 3. Verificar se é dia de trabalho                             │
│    - Se NÃO for dia de trabalho: ✓ PERMITE hora extra         │
│    - Se FOR dia de trabalho: valida horários                  │
└───────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────┐
│ 4. Validar horários (se dia de trabalho)                      │
│    - Hora extra ANTES do entry_time: ✓ OK (Abertura)          │
│    - Hora extra DEPOIS do exit_time: ✓ OK (Fechamento)        │
│    - Hora extra DENTRO do horário normal: ✗ ERRO              │
└───────────────────────────────────────────────────────────────┘
```

### 4.2. Cenários de Validação

| Cenário | Escala | Hora Extra | Resultado |
|---------|--------|------------|-----------|
| Abertura | 08:00-17:00 | 06:00-08:00 | ✓ Válido |
| Fechamento | 08:00-17:00 | 17:00-22:00 | ✓ Válido |
| Dia de Folga | Sábado off | 08:00-14:00 | ✓ Válido |
| Override Folga | Quarta off (override) | 10:00-16:00 | ✓ Válido |
| Dentro Expediente | 08:00-17:00 | 10:00-14:00 | ✗ Bloqueado |
| Atravessa Início | 08:00-17:00 | 07:00-09:00 | ✗ Bloqueado |
| Atravessa Fim | 08:00-17:00 | 16:00-19:00 | ✗ Bloqueado |

---

## 5. Funcionalidades por Módulo

### 5.1. Módulo WorkSchedule

| Funcionalidade | Controller | Model |
|----------------|------------|-------|
| Listar escalas | WorkSchedule | AdmsListWorkSchedules |
| Adicionar escala | AddWorkSchedule | AdmsAddWorkSchedule |
| Editar escala | EditWorkSchedule | AdmsEditWorkSchedule |
| Visualizar escala | ViewWorkSchedule | AdmsViewWorkSchedule |
| Deletar escala | DeleteWorkSchedule | AdmsDeleteWorkSchedule |
| Estatísticas | WorkSchedule | AdmsStatisticsWorkSchedules |

### 5.2. Módulo Employees (Integração)

| Funcionalidade | Localização |
|----------------|-------------|
| Exibir escala atual | `_view_employee_details.php` |
| Exibir dias/horários | `_view_employee_details.php` |
| Histórico de escalas | `_view_employee_details.php` |
| Atribuir escala | `AdmsAddEmployee.php`, `AdmsEditEmployee.php` |
| Gerenciar overrides | `EmployeeScheduleOverride.php` |
| Buscar escala vigente | `AdmsViewEmployee.php` |

### 5.3. Módulo OvertimeControl (Integração)

| Funcionalidade | Localização |
|----------------|-------------|
| Validar ao adicionar | `AdmsAddOvertimeControl.php` |
| Validar ao editar | `AdmsEditOvertimeControl.php` |
| Validador de escala | `AdmsWorkScheduleValidator.php` |

---

## 6. Testes Automatizados

### 6.1. Resumo de Testes

| Módulo | Arquivo | Testes | Assertions |
|--------|---------|--------|------------|
| WorkSchedule | tests/WorkSchedule/*.php | 129 | 282 |
| Validator | AdmsWorkScheduleValidatorTest.php | 17 | 35 |
| **Total** | | **146** | **317** |

### 6.2. Testes do Validador

```
✔ Allows overtime for employee without schedule
✔ Has no active schedule for employee without schedule
✔ Has active schedule for employee with schedule
✔ Allows overtime on day off
✔ Allows overtime on sunday
✔ Allows overtime before work hours
✔ Allows overtime after work hours
✔ Blocks overtime during work hours
✔ Blocks overtime that overlaps work hours
✔ Blocks overtime that overlaps work end
✔ Respects override day off
✔ Respects override with different hours
✔ Get employee schedule summary
✔ Get employee schedule summary returns null for no schedule
✔ Blocks overtime starting exactly at work start
✔ Allows overtime starting exactly at work end
✔ Allows overtime ending exactly at work start
```

---

## 7. Migrations para Deploy

### 7.1. Arquivos SQL

| Ordem | Arquivo | Descrição |
|-------|---------|-----------|
| 1 | `2026_02_05_create_work_schedule_tables.sql` | Tabelas principais + triggers + views + escalas padrão |
| 2 | `2026_02_05_create_employee_schedule_day_overrides.sql` | Tabela de overrides + view merged |
| 3 | `2026_02_05_add_employee_schedule_override_routes.sql` | Rota do controller de overrides |
| 4 | (Manual) | Rotas do módulo WorkSchedule |

### 7.2. Rotas a Cadastrar Manualmente

```sql
-- WorkSchedule
INSERT INTO adms_paginas (nome_pagina, obs, publish, controller)
VALUES ('work-schedule', 'Listagem de Escalas de Trabalho', 1, 'WorkSchedule');

INSERT INTO adms_paginas (nome_pagina, obs, publish, controller)
VALUES ('add-work-schedule', 'Adicionar Escala', 1, 'AddWorkSchedule');

INSERT INTO adms_paginas (nome_pagina, obs, publish, controller)
VALUES ('view-work-schedule', 'Visualizar Escala', 1, 'ViewWorkSchedule');

INSERT INTO adms_paginas (nome_pagina, obs, publish, controller)
VALUES ('edit-work-schedule', 'Editar Escala', 1, 'EditWorkSchedule');

INSERT INTO adms_paginas (nome_pagina, obs, publish, controller)
VALUES ('delete-work-schedule', 'Deletar Escala', 1, 'DeleteWorkSchedule');

-- Permissões (ajustar nivel_id conforme necessário)
INSERT INTO adms_nivacs_pgs (adms_nivel_id, adms_pagina_id, permissao)
SELECT 1, id, 1 FROM adms_paginas WHERE controller LIKE '%WorkSchedule%';
```

---

## 8. Melhorias Futuras

| Melhoria | Prioridade | Status |
|----------|------------|--------|
| Integração com ponto eletrônico | Baixa | Pendente |
| Alertas de inconsistência | Média | Pendente |
| Escala por período (temporada) | Baixa | Pendente |
| Aprovação de alterações por gestor | Média | Pendente |
| Dashboard de jornada da equipe | Média | Pendente |
| Relatório de jornada por funcionário | Alta | Pendente |

---

## 9. Commits Relacionados

| Hash | Descrição |
|------|-----------|
| `edc8a9fd` | feat(overtime): integrate WorkSchedule validation with OvertimeControl |
| `a2795160` | refactor(employee): move schedule override JS to employees.js |
| `03eea6b1` | feat(employee): add schedule day override functionality |
| `9a3e5eea` | feat(employee): add detailed work schedule display in view modal |
| `9e25cd9e` | feat(employee): integrate work schedule assignment with employee module |
| `56116c80` | feat(work-schedule): integrate WorkSchedule with FormSelectRepository |

---

**Implementação concluída em:** 05 de Fevereiro de 2026
**Responsável:** Equipe Mercury - Grupo Meia Sola
