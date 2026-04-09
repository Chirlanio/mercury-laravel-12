# Modulo 2A: Movimentacao de Pessoal (Personnel Movements)

**Status:** Pendente
**Fase:** 2A
**Prioridade:** ALTA — Processo RH
**Estimativa:** ~18 arquivos novos
**Referencia v1:** `C:\wamp64\www\mercury\app\adms\Controllers\PersonnelMoviments.php`
**Doc v1:** `docs/ANALISE_INTEGRACAO_RH_MODULES.md`

---

## 1. Visao Geral

Modulo de gestao de movimentacoes de pessoal: admissao, promocao, transferencia, alteracao salarial, desligamento e reativacao. Inclui checklist de follow-up para desligamentos com notificacao para 6 departamentos.

---

## 2. Tipos de Movimentacao

| Tipo | Descricao | Side Effects |
|------|-----------|-------------|
| admission | Admissao | Ativar employee |
| promotion | Promocao | Atualizar position_id |
| transfer | Transferencia entre lojas | Atualizar store_id |
| salary_change | Alteracao salarial | Registrar novo salario |
| dismissal | Desligamento | Inativar employee, cancelar ferias, remover treinamentos, notificar 6 deptos |
| reactivation | Reativacao | Reativar employee |

## 3. State Machine

```
Pendente (pending) → Em Andamento (in_progress) → Concluido (completed)
                                                     ↓ reopen
                                                   Pendente
Qualquer → Cancelado (cancelled) [admin]
```

## 4. Arquivos a Criar

### Migrations (4)
create_personnel_movements_table, create_personnel_movement_logs_table, create_dismissal_follow_ups_table, create_dismissal_follow_up_items_table

### Models (4)
PersonnelMovement, PersonnelMovementLog, DismissalFollowUp, DismissalFollowUpItem

### Services (3)
PersonnelMovementTransitionService, PersonnelMovementDeleteService, DismissalNotificationService

### Controller (1), Frontend (4), Export (1), Tests (1)

## 5. Permissions (4)
VIEW_PERSONNEL_MOVEMENTS, CREATE_PERSONNEL_MOVEMENTS, EDIT_PERSONNEL_MOVEMENTS, DELETE_PERSONNEL_MOVEMENTS

## 6. Side Effects Criticos (Desligamento)
1. Setar employee.status = Inativo + dismissal_date
2. Cancelar ferias pendentes (status draft-approved_rh)
3. Remover de treinamentos pendentes (status registered)
4. Criar checklist de follow-up (6 departamentos)
5. Notificar RH, Financeiro, TI, Comercial, Operacional, Juridico
6. Auto-criar vaga de substituicao (se configurado)

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
