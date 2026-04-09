# Modulo 1C: Auditoria de Estoque (Stock Audit)

**Status:** Pendente
**Fase:** 1C
**Prioridade:** CRITICA — Controle de inventario
**Estimativa:** ~33 arquivos novos
**Referencia v1:** `C:\wamp64\www\mercury\app\adms\Controllers\StockAudit*.php`, `Services\AuditStateMachineService.php`
**Doc v1:** `docs/PROPOSTA_MODULO_AUDITORIA_ESTOQUE.md`, `docs/MANUAL_USUARIO_AUDITORIA_ESTOQUE.md`

---

## 1. Visao Geral

Ecossistema completo de inventario com auditorias programadas, aleatorias e parciais. Controle de equipes, autorizacao digital, 3 rodadas de contagem, conciliacao automatica, justificativas em 2 fases (auditor + loja), assinatura digital e integracao CIGAM para saldo do sistema.

---

## 2. State Machine (6 estados + 3 fases)

```
Rascunho (draft) → Aguardando Autorizacao (pending_authorization)
→ Em Contagem (counting) → Conciliacao (reconciliation)
→ Finalizada (completed)

Qualquer → Cancelada (cancelled) [apenas admin]
```

### Fases de Conciliacao
- **Fase A:** Contagem (rodadas 1, 2 e 3)
- **Fase B:** Justificativa do auditor (para divergencias)
- **Fase C:** Justificativa da loja (para divergencias nao justificadas na B)

### Logica de Conciliacao
```
count_1 == count_2 → final_count = count_1 (auto)
count_1 != count_2 → marca para 3a contagem
count_3 realizada → final_count = count_3
divergence = final_count - system_quantity
```

---

## 3. Arquivos a Criar

### Migrations (10)
```
create_stock_audit_cycles_table.php
create_stock_audit_vendors_table.php
create_stock_audits_table.php
create_stock_audit_items_table.php
create_stock_audit_teams_table.php
create_stock_audit_team_members_table.php
create_stock_audit_signatures_table.php
create_stock_audit_justifications_table.php
create_stock_audit_import_logs_table.php
create_stock_audit_accuracy_history_table.php
```

### Models (10)
```
StockAudit.php, StockAuditItem.php, StockAuditTeam.php, StockAuditTeamMember.php,
StockAuditCycle.php, StockAuditVendor.php, StockAuditSignature.php,
StockAuditJustification.php, StockAuditImportLog.php, StockAuditAccuracyHistory.php
```

### Services (4)
```
StockAuditStateMachineService.php      — transicoes + validacao por fase
StockAuditCigamService.php             — puxa system_quantity do CIGAM
StockAuditRandomSelectionService.php   — selecao aleatoria por curva ABC
StockAuditReportService.php            — relatorios PDF + historico de acuracia
```

### Controller (1)
```
StockAuditController.php — index, store, show, update, destroy, transition,
    importItems, syncSystemQuantities, submitCount, submitJustification,
    sign, report, accuracyHistory, export
```

### Frontend (6)
```
Pages/StockAudits/Index.jsx
Components/StockAuditCreateModal.jsx
Components/StockAuditDetailModal.jsx
Components/StockAuditCountingView.jsx
Components/StockAuditReconciliationView.jsx
Components/StockAuditSignatureModal.jsx
```

### Import/Export (2)
```
app/Imports/StockAuditItemsImport.php
app/Exports/StockAuditExport.php
```

---

## 4. Permissions (6)

```php
VIEW_STOCK_AUDITS, CREATE_STOCK_AUDITS, EDIT_STOCK_AUDITS,
DELETE_STOCK_AUDITS, AUTHORIZE_STOCK_AUDITS, SIGN_STOCK_AUDITS
```

---

## 5. Tabelas Principais

### stock_audits
id, store_id (FK), cycle_id (FK nullable), audit_type (full/partial/random), status, phase (A/B/C), total_items, counted_items, accuracy_percentage, financial_loss, financial_surplus, notes, authorized_by_user_id, authorized_at, started_at, finalized_at, cancelled_at, created_by_user_id, timestamps, soft deletes

### stock_audit_items
id, audit_id (FK), product_variant_id (FK nullable), reference, barcode, description, system_quantity, count_1/count_1_by/count_1_at, count_2/count_2_by/count_2_at, count_3/count_3_by/count_3_at, final_count, divergence, divergence_value, unit_price, cost_price, resolution_type (auto/manual/uncounted), timestamps

### stock_audit_justifications
id, audit_id (FK), item_id (FK), phase (B/C), justification_text, found_quantity (nullable), created_by_user_id, review_status (pending/accepted/rejected), reviewed_by_user_id, reviewed_at, review_note, timestamps

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
