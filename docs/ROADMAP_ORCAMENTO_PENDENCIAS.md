# Roadmap — Pendências do Módulo Orçamento (Budgets)

**Data:** 2026-04-20
**Estado atual:** Fases 1–4 concluídas + integração com OrderPayments (C1+C2+C3a).
**Pendentes:** 3 features principais + 3 melhorias recomendadas.

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

### Fase 7 — AC Fallback no form de OP [DECISÃO PENDENTE]

**Problema identificado.** No form de OrderPayment, ao escolher MC de loja específica (ex: "Comercial - Arezzo Kennedy" → CC 8), o dropdown AC mostra só 1 opção (Internet), porque as outras ACs (Eventos, Fretes, Uber…) estão alocadas só no CC 15 (Comercial - Geral). UX parece "bug" pro user.

**Opções.**

- **(A) Manter**: documentar que despesas transversais usam MC "Geral", não específica de loja.
- **(B) Expandir dropdown**: quando user está em CC de loja, incluir ACs do CC "Geral" do **mesmo departamento** como sub-seção ("Compartilhadas com Geral").

**Pré-requisito.** Fase 5 (área explícita) torna o fallback trivial — basta procurar o CC "Geral" do mesmo department.

**Estimativa.** 1 commit depois da decisão + Fase 5.

---

## Melhorias recomendadas (não críticas)

### 8 — Editor inline de BudgetItem [UX]

**Motivação.** Hoje só dá pra substituir via upload novo. Ajustar 1 valor → re-upload da planilha inteira → nova versão minor. Peso operacional alto para correções pontuais.

**Escopo.**

1. `BudgetItemController::update` + policy (quem pode editar items do budget ativo?)
2. Modal edição (single item): ajustar valores mensais, supplier, descrição
3. Tabela no Dashboard/Show com ✏ ao lado de cada linha
4. Auditar mudanças (nova tabela `budget_item_histories` ou reusar `budget_status_histories`)
5. Impacto: itens editados não mudam o `budget_upload.total_year` até recálculo manual (ou trigger)

**Risco.** Médio. Mexer em orçamento ativo é sensível — precisa audit trail forte.

**Estimativa.** 3 commits.

---

### 9 — Comparativo entre versões [ANALÍTICO]

**Motivação.** Quando há upload "ajuste" (v1.01, v1.02), o user não tem visão de o que mudou entre versões. Útil em auditoria.

**Escopo.**

1. Service que calcula diff entre 2 uploads (linhas novas, removidas, alteradas, totais por mês)
2. Página `/budgets/compare?v1=X&v2=Y` com tabela lado-a-lado
3. Limitação: só compara uploads do mesmo `(year, scope_label)`

**Risco.** Baixo. Read-only, não altera estado.

**Estimativa.** 2 commits.

---

### 10 — Lixeira de BudgetUpload [ADMIN]

**Motivação.** Upload soft-deleted some do dashboard. Se o user deletou por engano ou quer reativar, não tem UI.

**Escopo.**

1. Página `/budgets/trash` (só admin+)
2. Listagem de uploads deletados com motivo + quem/quando
3. Botão "Restaurar" (retira `deleted_at`, não reativa — precisa upload novo para ativar)
4. Botão "Excluir definitivamente" (destrutivo — policy exige super_admin)

**Risco.** Baixo. Soft-delete já existe.

**Estimativa.** 1 commit.

---

## Sequência recomendada

| Ordem | Fase | Dependência | Tipo |
|---|---|---|---|
| 1 | Fase 5 (Área) | — | Arquitetural |
| 2 | Fase 6 (Export) | Fase 5 (útil mas não bloqueia) | Feature |
| 3 | Fase 7 (AC fallback) | Fase 5 (obrigatório) + decisão | UX |
| 4 | Melhoria 8 (Edit inline) | — | UX |
| 5 | Melhoria 9 (Comparativo) | — | Analítico |
| 6 | Melhoria 10 (Lixeira) | — | Admin |

Fases 1–3 fazem sentido como um pacote (fechamento da Fase 5 oficial do roadmap Budgets). Melhorias 8–10 são incrementos separados, sem dependência estrita.

---

## Estado atual dos módulos envolvidos

**budget_uploads** — schema completo, falta `area_department_id`.
**budget_items** — schema completo.
**budget_status_histories** — schema completo (recovered após drop manual).
**order_payments** — integração funcional (C1+C2+C3a). Falta C3b (backfill) + C3c (NOT NULL).
**management_classes** — seed 11 deptos + 169 analíticas. Endpoint `/departments` funcional.

---

## Referências

- [ANALISE_MODULO_BUDGETS.md](ANALISE_MODULO_BUDGETS.md) — escopo das fases 1–4
- [ANALISE_MODULO_ORDERPAYMENTS.md](ANALISE_MODULO_ORDERPAYMENTS.md) — contexto da integração
- `app/Services/BudgetConsumptionService.php` — base para o export consolidado
- `app/Http/Controllers/BudgetController.php` — endpoints existentes
