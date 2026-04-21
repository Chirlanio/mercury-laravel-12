# Roadmap — Pendências do Módulo Orçamento (Budgets)

**Data:** 2026-04-21
**Estado atual:** Fases 1–7 concluídas + integração OrderPayments (C1+C2+C3a+C3b) + todas 3 melhorias (8/9/10) entregues.
**Pendente único:** C3c (tornar cost_center_id + accounting_class_id NOT NULL em order_payments — aguarda classificação manual das 12 OPs antigas).

---

## Fases pendentes (ordem recomendada)

### Fase 5 — Campo Área no BudgetUpload [PRIORIDADE ALTA]

**Motivação.** Hoje cada upload amarra a "área" implicitamente via `scope_label` (texto livre) + via items com MCs que pertencem a um departamento `8.1.DD`. Sem estrutura, o filtro `management-classes/departments?year=Y` precisa inferir (via join-pesquisa por MCs com budget_item ativo). Adicionar área explícita simplifica o fluxo e evita ambiguidade quando uma planilha traz MCs de áreas diferentes.

**Escopo.**

1. Migration: `budget_uploads.area_department_id` (unsignedBigInteger, nullable, FK → `management_classes.id` `nullOnDelete`). Nullable para não quebrar uploads antigos.
2. Model: `areaDepartment()` belongsTo + fillable.
3. Wizard step 3 (Confirm): select **Área (obrigatório)** populado via `GET /management-classes/departments?all=1`.
4. Parser/upload: validar coerência — se planilha traz MCs de outra área (DD diferente do `area_department_id` escolhido), erro 422 + lista dos códigos divergentes.
5. Endpoint `management-classes/departments?year=Y`: usar `budget_uploads.area_department_id` direto no WHERE (O(1)) em vez de derivar dos items.
6. Endpoint `budgets.accounting-classes-for-cost-center`: opcional — usar área para refinar quando CC é transversal.
7. Testes:
    - Upload com área válida
    - Upload onde planilha tem MCs de outra área → erro 422
    - `departments?year` retorna apenas áreas dos uploads ativos no ano (mais rápido)

**Migração de uploads antigos.** Upload #2 atual (Comercial) não tem `area_department_id`. Backfill:
- Detecta a MC dominante em cada upload (maior `SUM(year_total)`)
- Atribui o `parent_id` dessa MC como `area_department_id`

**Risco.** Baixo. Tudo nullable + backfill automático. Obrigatoriedade fica num commit posterior (C3 style), depois de confirmar que todos os uploads têm área.

**Estimativa.** 2–3 commits (migration + wizard + backend + backfill).

---

### Fase 6 — Export Consolidado [PRIORIDADE ALTA]

**Motivação.** Dashboard mostra dados no navegador, mas o financeiro precisa de xlsx pra consolidar com a contabilidade externa. Fase 5 do roadmap oficial mencionava isso.

**Escopo.**

1. `BudgetExportService`:
    - Sheet 1: Resumo Anual (Previsto × Comprometido × Realizado, totais + utilização)
    - Sheet 2: Por Centro de Custo (agregado)
    - Sheet 3: Por Conta Contábil (agregado)
    - Sheet 4: Por Área (usando `area_department_id` da Fase 5)
    - Sheet 5: Por Mês (12 colunas × 3 métricas)
    - Sheet 6: Detalhe por Item (tabela completa, cada linha do BudgetItem)
2. Rota `GET /budgets/{budget}/export` → xlsx via Maatwebsite/Excel.
3. Botão "Exportar" no Dashboard (padrão Reversals).
4. Permission: `EXPORT_BUDGETS` já existe no enum — só usar.
5. Testes: shape do xlsx, sheets corretas, totais batem com `getConsumption()`.

**Risco.** Baixo. Reusa `BudgetConsumptionService` para cálculos.

**Estimativa.** 2 commits (service+rota+testes + frontend).

---

### Fase 7 — AC Fallback no form de OP [DECIDIDO: Opção A · 2026-04-21]

**Problema identificado.** No form de OrderPayment, ao escolher MC de loja específica (ex: "Comercial - Arezzo Kennedy" → CC 8), o dropdown AC mostra só 1 opção (Internet), porque as outras ACs (Eventos, Fretes, Uber…) estão alocadas só no CC 15 (Comercial - Geral).

**Decisão.** Manter comportamento atual (Opção A). Despesas transversais (viagens, eventos, fretes, etc.) devem usar a MC "Geral" da área, não a MC da loja específica. Isso preserva a rastreabilidade orçamentária — cada centavo consumido sai do CC correto.

**Implementação.**
- Hint UX atualizado no `AccountingClassSelect` (OrderPayments/Index.jsx) — quando a lista aparece vazia num CC de loja, explica ao user a convenção "Geral".
- Sem mudanças de arquitetura/endpoint.

**Por que Opção A foi escolhida (decisão de negócio, 2026-04-21).**
- Preserva a fidelidade do orçamento ao CC.
- Evita que usuário crie OP num CC de loja mas consuma orçamento do CC Geral (difícil de auditar).
- Hint educacional na UI resolve o atrito de descoberta.

---

## Melhorias recomendadas (não críticas)

### 8 — Editor inline de BudgetItem [UX] ✅ CONCLUÍDA 2026-04-21

**Entregue.** `BudgetItemController::update` + `EditItemModal` com 12 meses + descrições + supplier. Auditoria via `Auditable` trait (log_activity). Recálculo automático de `year_total` do item + `total_year` do upload.

Commits: `d2dff5a feat(budgets): Melhoria 8` e `407cc0e fix(budgets): EditItemModal recebe campos de texto + 12 meses`.

---

### 9 — Comparativo entre versões [ANALÍTICO] ✅ CONCLUÍDA 2026-04-21

**Entregue.** `BudgetDiffService` com identidade lógica (AC + MC + CC + store). Página `/budgets/compare?v1=X&v2=Y` com tabs (Adicionadas/Removidas/Alteradas/Resumo). Endpoint retorna diff estruturado + totais + deltas mensais. Gate por `(year, scope_label)` igual.

Commit: `181027d feat(budgets): Melhoria 9`. Tests: 8 assertions em `BudgetCompareTest`.

---

### 10 — Lixeira de BudgetUpload [ADMIN] ✅ CONCLUÍDA 2026-04-21

**Entregue.** Rota `/budgets/trash` (permission `MANAGE_BUDGETS`) lista uploads soft-deletados com motivo + quem/quando. Botão "Restaurar" zera `deleted_at/by/reason` mantendo `is_active=false`. Botão "Excluir definitivamente" destrói upload + items via FK cascade, restrito a `super_admin`. Botão "Lixeira" adicionado ao header do Index.

Commit: `d203ba5 feat(budgets): Melhoria 10`. Tests: 9 passed / 41 assertions em `BudgetTrashTest`.

**Fix colateral.** `BudgetController::forceDelete` usava `method_exists($user->role, 'value')` — falso pra `BackedEnum` porque `value` é property readonly, não método. Trocado por `$role instanceof BackedEnum ? $role->value : …`. Bug invisível na branch `admin` do check (admin já caía no 403 correto), aparecia só ao testar super_admin.

---

## Sequência recomendada

| Ordem | Fase | Status | Tipo |
|---|---|---|---|
| 1 | Fase 5 (Área) | ✅ Concluída 2026-04-21 | Arquitetural |
| 2 | Fase 6 (Export) | ✅ Concluída 2026-04-21 | Feature |
| 3 | Fase 7 (AC fallback) | ✅ Decidido 2026-04-21 (Opção A + hint UX) | UX |
| 4 | Melhoria 8 (Edit inline) | ✅ Concluída 2026-04-21 | UX |
| 5 | Melhoria 9 (Comparativo) | ✅ Concluída 2026-04-21 | Analítico |
| 6 | Melhoria 10 (Lixeira) | ✅ Concluída 2026-04-21 | Admin |
| 7 | C3b (backfill OPs antigas) | ✅ Concluída 2026-04-21 | Integração |
| 8 | C3c (NOT NULL em order_payments) | ⏳ Aguarda classificação das 12 OPs antigas | Integração |

Todo o pacote planejado está entregue. Única pendência: após classificar manualmente via modal "Editar" o último lote de OPs que ficaram sem CC/AC na integração inicial, rodar migration para tornar `order_payments.cost_center_id` e `order_payments.accounting_class_id` NOT NULL.

---

## Estado atual dos módulos envolvidos

**budget_uploads** — schema completo, `area_department_id` populado (FK obrigatória em novos uploads).
**budget_items** — schema completo, editável inline (Melhoria 8).
**budget_status_histories** — schema completo.
**order_payments** — integração funcional (C1+C2+C3a+C3b). Falta só C3c (NOT NULL).
**management_classes** — seed 11 deptos + 169 analíticas. Endpoint `/departments?year=Y` usa `area_department_id` direto (O(1)).
**Rotas Budgets** — 18+ endpoints: index, statistics, dashboard, consumption, show, store, update, destroy, restore, force-delete, trash, compare, export, download, template, items-for-cost-center, accounting-classes-for-cost-center, items update (inline).

---

## Referências

- [ANALISE_MODULO_BUDGETS.md](ANALISE_MODULO_BUDGETS.md) — escopo das fases 1–4
- [ANALISE_MODULO_ORDERPAYMENTS.md](ANALISE_MODULO_ORDERPAYMENTS.md) — contexto da integração
- `app/Services/BudgetConsumptionService.php` — base para o export consolidado
- `app/Http/Controllers/BudgetController.php` — endpoints existentes
