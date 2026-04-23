# Roadmap de Implementação — Módulos Críticos v1 → v2

**Versão:** 2.0
**Data inicial:** 2026-04-09
**Última atualização:** 2026-04-23
**Projeto:** Mercury Laravel (SaaS Multi-Tenant)

---

## Sumário Executivo

O roadmap original cobria **Fases 1–4** (Order Payments, Férias, Auditoria, Movimentação, Treinamentos, Delivery, Chat + Helpdesk). Todas foram entregues. Ao longo da execução foram adicionados escopos extras (4B RH Avançado, Financeiro expandido, DRE) e eles também foram concluídos.

- **Roadmap original (Fases 1–4):** 100% entregue
- **Escopo estendido (4B + Financeiro + DRE):** 100% entregue
- **Pendente:** apenas backlog pós-MVP de Helpdesk/Delivery + gaps v1→v2 fora do roadmap crítico

---

## Matriz de Prioridade — Status

| # | Módulo | Fase | Status | Conclusão |
|---|--------|------|--------|-----------|
| 1A | Ordens de Pagamento (melhoria) | 1 | ✅ CONCLUÍDO | — |
| 1B | Férias (CLT) | 1 | ✅ CONCLUÍDO | — |
| 1C | Auditoria de Estoque | 1 | ✅ CONCLUÍDO | — |
| 2A | Movimentação de Pessoal | 2 | ✅ CONCLUÍDO | — |
| 2B | Treinamentos & Capacitação (LMS) | 2 | ✅ CONCLUÍDO | — |
| 3A | Delivery + Routing | 3 | ✅ CONCLUÍDO | 2026-04-14 |
| 4A | Chat + Helpdesk | 4 | ✅ CONCLUÍDO | 2026-04-14 |
| 4B | RH Avançado (Vacancies + PersonnelRequests) | 4 (extensão) | ✅ CONCLUÍDO | 2026-04-14 |
| 5A | Purchase Orders (Ordens de Compra) | 5 — Financeiro | ✅ CONCLUÍDO | 2026-04-15 |
| 5B | Reversals (Estornos PDV) | 5 — Financeiro | ✅ CONCLUÍDO | 2026-04-17 |
| 5C | Returns (Devoluções e-commerce) | 5 — Financeiro | ✅ CONCLUÍDO | 2026-04-19 |
| 6A | Movements (refatoração CIGAM) | 6 — Infra | ✅ CONCLUÍDO | 2026-04-19 |
| 7A | Budgets Foundation (CostCenter + AccountingClass + ManagementClass) | 7 — DRE | ✅ CONCLUÍDO | 2026-04-20 |
| 7B | Budgets (módulo completo, fases 1–10) | 7 — DRE | ✅ CONCLUÍDO | 2026-04-21 |
| 7C | DRE (matriz gerencial) | 7 — DRE | ✅ CONCLUÍDO | 2026-04-22 |
| 8A | Coupons (cupons de desconto) | 8 — Financeiro | ✅ CONCLUÍDO | 2026-04-23 |

---

## Fases Entregues — Detalhamento

### Fase 1 — Fundacional (Semanas 1–8)
- **1A Order Payments Enhancement:** Kanban, transitions, allocations, installments, dashboard, statistics, export
- **1B Férias:** `VacationController` + 4 models + holiday management + balance checking (CLT compliance)
- **1C Auditoria de Estoque:** `StockAuditController` + 15 models + 7 services + Counting/Reconciliation + 3 test files

### Fase 2 — Recursos Humanos (Semanas 9–14)
- **2A Movimentação de Pessoal:** `PersonnelMovementController` + 3 models + 2 services + 6 frontend components
- **2B Treinamentos:** 6 controllers + 18 models + 7 services (LMS completo: cursos, quizzes, conteúdo, certificados, QR, catálogo público, Google OAuth)

### Fase 3 — Operações (Semanas 15–18)
- **3A Delivery + Routing:** Geocoding Nominatim + OSRM Trip API + Leaflet + GPS tracking + templates
  - Backlog pós-MVP (2 itens, não bloqueante): pin-on-map interativo e unit tests isolados

### Fase 4 — Comunicação (Semanas 19–24)
- **4A Chat + Helpdesk (Reverb):**
  - Chat 1:1, grupos, broadcasts, realtime, widget global, edit/delete
  - Helpdesk com SLA business-hours, WhatsApp in/out, AI Groq, KB, CSAT, email intake IMAP+Postmark, deflection + AI accuracy dashboards
  - 202 testes no Helpdesk, 19 testes no Chat
  - Backlog pós-MVP (5 itens, não bloqueante): suggestions web, WhatsApp media, reply templates auto, split tickets, Policies

### Fase 4B — RH Avançado (extensão, 2026-04-14)
- **Vacancies (Abertura de Vagas):** state machine 5 estados, SLA auto-resolvido do nível do cargo, entrevistas RH+líder, integração bidirecional com PersonnelMovement, 36 testes + 7 de activation
- **PersonnelRequests:** acoplado ao Helpdesk via seeds (departamento DP enriquecido + 4 categorias + 5 intake templates + 5 KB articles)

### Fase 5 — Financeiro (expansão)
- **5A PurchaseOrders (2026-04-15):** 5 estados, size matrix, receipts manuais + matcher CIGAM, FK em order_payments, import XLSX/CSV paridade v1, export XLSX/PDF, EAN-13, dashboard, 2 commands agendados. **81 testes / 293 assertions**
- **5B Reversals (2026-04-17):** paridade v1 sobre `movements` (code 2), state machine 6 estados, hook Helpdesk fail-safe, dedup via service. **62 testes / 184 assertions, 18 rotas, 10 permissions**
- **5C Returns (2026-04-19):** devoluções e-commerce (loja Z441), 3 tipos, 6 estados com `awaiting_product`, 15 motivos seeded. **51 testes / 155 assertions, 14 rotas, 11 permissions**

### Fase 6 — Infra (2026-04-19)
- **6A Movements refatorado:** fonte de verdade CIGAM, statistics 1 SELECT com CASE, NF como chave composta, export com filtros, 44 testes / 122 assertions

### Fase 7 — DRE/Budgets
- **7A Budgets Foundation (2026-04-20):** CostCenter standalone (hierarquia + 7 perms), AccountingClass (11 grupos DRE BR + ~50 contas + toggle lista/árvore), ManagementClass (visão interna + vínculo opcional). **72 testes / 245 assertions**
- **7B Budgets completo (2026-04-21):** fases 1–10 entregues (versionamento v1, parser xlsx, wizard, dashboard recharts, alertas daily, export multi-sheet, editor inline, compare versões, lixeira admin). Integração OP auto-resolve budget_item_id. **18+ rotas, 72+ testes**
- **7C DRE (2026-04-22):** plano de contas real (839/289), 20 linhas executivas gerenciais, `dre_mappings` com precedência, `DreMatrixService` cacheado, `DrePeriodClosingService` (close/reopen), projetores OP+Sale+Budget com observers, imports manuais, matriz React 3 tabs, exports XLSX+PDF, warm-cache scheduled. **214 testes DRE + ~200 regressão. 8 permissions novas**

### Fase 8 — Financeiro (novos, 2026-04-23)
- **8A Coupons:** 3 tipos (Consultor/Influencer/MsIndica) com regras condicionais, CPF encryption + cpf_hash HMAC-SHA256, state machine 6 estados, config module `SocialMedia` auxiliar, export XLSX + PDF + import, 2 commands agendados (expire-stale + remind-pending), dashboard recharts. **91 testes / 280 assertions, 14 rotas, 8 permissions**

---

## Dependências (histórico — todas resolvidas)

```
Employee (existe) ──┬── Férias (1B) ✅
                    ├── Mov. Pessoal (2A) ── cascade ── Férias cancel ✅
                    ├── Treinamentos (2B) ── cascade ── Training removal ✅
                    └── Auditoria (1C) ✅
Product (existe) ──── Auditoria (1C) ✅
CIGAM (existe) ────── Auditoria (1C) ✅ + Movements (6A) ✅
Transfer (existe) ─── Delivery (3A) ✅
OrderPayment (parcial) ── OP Enhancement (1A) ✅ + Budgets (7B) ✅
WebSocket (NOVO) ──── Chat + Helpdesk (4A) ✅ (laravel/reverb)
Movements ──────────── Reversals (5B) ✅ + Returns (5C) ✅ + PurchaseOrders (5A) ✅
PersonnelMovement ──── Vacancies (4B) ✅ (event/listener bidirecional)
Helpdesk ──────────── PersonnelRequests (4B) ✅ (acoplado via seeds)
CostCenter + ContaGerencial ── Budgets (7B) ✅ + DRE (7C) ✅
```

---

## Documentação por Módulo

### Originais do roadmap
| Módulo | Arquivo |
|--------|---------|
| Order Payments Enhancement | [01_ORDER_PAYMENTS_ENHANCEMENT.md](01_ORDER_PAYMENTS_ENHANCEMENT.md) |
| Férias | [02_FERIAS.md](02_FERIAS.md) |
| Auditoria de Estoque | [03_AUDITORIA_ESTOQUE.md](03_AUDITORIA_ESTOQUE.md) |
| Movimentação de Pessoal | [04_MOVIMENTACAO_PESSOAL.md](04_MOVIMENTACAO_PESSOAL.md) |
| Treinamentos (5 submódulos) | [05_TREINAMENTOS.md](05_TREINAMENTOS.md) |
| Delivery + Routing | [06_DELIVERY_ROUTING.md](06_DELIVERY_ROUTING.md) |
| Chat + Helpdesk | [07_CHAT_HELPDESK.md](07_CHAT_HELPDESK.md) |

### Adicionais (não cobertos no roadmap original — consultar memory/CLAUDE.md)
| Módulo | Referência |
|--------|------------|
| Vacancies + PersonnelRequests | `memory/critical_modules_roadmap.md` (Fase 4B) |
| PurchaseOrders | `memory/purchase_orders_module.md` |
| Reversals | `memory/reversals_module.md` |
| Returns | `memory/returns_module.md` |
| Movements (refactor) | `memory/movements_module.md` |
| Budgets Foundation | `memory/budgets_foundation.md` |
| Budgets | `memory/budgets_module.md` |
| DRE | `docs/dre-README.md` + `memory/dre_module.md` |
| Coupons | `docs/ANALISE_MODULO_COUPONS.md` |

---

## Pendente

### Backlog pós-MVP (dentro dos módulos críticos, não bloqueante)

**Helpdesk (5 itens):**
1. Article suggestions no modal web de criação — portabilidade de `WhatsappIntakeDriver::findKbSuggestion`
2. WhatsApp media inbound/outbound (imagens/docs)
3. Auto-suggestions de reply templates por categoria/keywords (pode ser sem IA)
4. Split de tickets (inverso do merge)
5. Laravel Policies para tickets (refactor puro, só faz sentido se expor API REST pública)

**Delivery (2 itens):**
1. Mapa interativo na criação de rota (pin-on-map drag-and-drop)
2. Unit tests isolados de `RouteOptimizationService` + `GeocodingService`

**DRE (divergências conscientes, não bloqueante):**
- Imports síncronos (jobs adiados)
- XLSX multi-sheet 3 em vez de 5
- `default_management_class_id` nulo no tenant meia-sola (hints não aplicados ainda)

### Gaps v1→v2 fora do roadmap crítico

Módulos v1 que nunca entraram neste roadmap e continuam pendentes:

**Financeiro:**
- TravelExpenses

**Estoque:**
- Consignments
- DamagedProducts
- Relocation
- FixedAssets
- ProductPromotions

**Comunicação:**
- SystemNotifications

**Especializados:**
- ServiceOrder
- Ecommerce
- ProcessLibrary
- Policies
- MaterialRequest
- TurnList (LDV)

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
