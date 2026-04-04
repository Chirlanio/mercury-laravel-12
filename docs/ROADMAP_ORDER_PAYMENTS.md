# Order Payments Module — Roadmap de Fases Futuras

**Última Atualização:** 02/03/2026

## Histórico de Fases Concluídas

| Fase | Descrição | Testes | Data |
|------|-----------|--------|------|
| 1-3 | Core CRUD, validação explícita, MoneyConverterTrait, transação DB | — | Fev/2026 |
| 4 | JS rewrite: Kanban, modals AJAX, drag-and-drop, money mask | — | Fev/2026 |
| 5 | Reports R1-R8, export Excel, transition service, allocations | — | Fev/2026 |
| 6 | 151 testes (420 assertions), fix delete column bug | 151 | Fev/2026 |
| 7 | Edit tests (15), JS hardening (fetchWithTimeout, loadModalContent), notification store fix | 166 | Mar/2026 |
| 8 | Delete model tests (19), NotificationRecipientService tests (13), MoneyConverterTrait tests (15) | 212 | Mar/2026 |
| 9 | UX/Accessibility: ARIA, keyboard nav, focus mgmt, mobile tabs, CSS a11y (131 tests, 177 assertions) | 343 | Mar/2026 |
| 10 | Performance: KPI merge (4→1 query), cache selects (9→0 on hit), debounce rAF, skeleton loading, load-more per column, composite indexes | 343 | Mar/2026 |
| 11 | Funcionalidades avançadas: bulk transitions, dashboard, timeline, anexos preview, cron alertas | 343 | Mar/2026 |

---

## ~~Fase 10 — Performance~~ ✅ CONCLUÍDA (02/03/2026)

### Implementado

| Sub-fase | Descrição | Impacto |
|----------|-----------|---------|
| 10a | KPI merge: 4 SUM queries → 1 GROUP BY | 4→1 queries |
| 10b | Cache selects com SelectCacheService (9 lookups) | 9→0 queries em cache hit |
| 10c | Debounce rAF no recalculateAllocationTotals | Elimina DOM thrashing |
| 10d | Load-more por coluna (typeorderpayment=3) | Paginação independente |
| 10e | Skeleton loading para AJAX reload | UX feedback estruturado |
| 10f | 2 índices compostos (status+deleted+date, status+deleted+total) | Query plan otimizado |

### Arquivos Modificados
- `database/migrations/2026_03_order_payments_performance_indexes.php` (NOVO)
- `app/adms/Models/AdmsListOrderPayments.php` — KPI merge, cache, public const, getter
- `app/adms/Controllers/OrderPayments.php` — loadMoreColumn()
- `app/adms/Views/orderPayment/listOrderPayment.php` — load-more buttons
- `assets/js/order-payments.js` — debounce, skeleton, loadMoreCards
- `assets/css/personalizado.css` — skeleton animation

---

## ~~Fase 11 — Funcionalidades Avançadas~~ ✅ CONCLUÍDA (02/03/2026)

### Implementado

| Sub-fase | Feature | Descrição |
|----------|---------|-----------|
| 11a | Bulk Transitions | Checkboxes nos cards Kanban, barra flutuante sticky, seleção múltipla, validação de campos obrigatórios por transição, permissão `bulk_transition`, endpoint `bulkTransition()` |
| 11b | Dashboard com Gráficos | Modal com Chart.js 3.9.1, 4 gráficos (doughnut status, bar áreas, line mensal, bar fornecedores), filtro por período com auto-refresh, alertas de parcelas vencidas/a vencer, botão limpar filtros |
| 11c | Timeline no View Modal | Timeline vertical visual substituindo tabela de histórico, nós coloridos por status, badges De→Para, ícones, collapsible, responsiva |
| 11d | Cron Alertas de Parcelas | Script CLI `bin/cron-installment-alerts.php`, tabela dedup `adms_installment_alerts`, busca parcelas vencidas não pagas, agrupa por loja, notifica via WebSocket (`NotificationRecipientService` + `SystemNotificationService`) |
| 11e | Anexos com Preview | Grid de cards com thumbnails de imagens, ícones PDF, tamanho formatado, botões download e preview |

### Arquivos Criados
- `bin/cron-installment-alerts.php` — script CLI para execução diária via crontab
- `database/migrations/2026_03_installment_alerts.php` — tabela dedup
- `app/adms/Views/orderPayment/partials/_dashboard_modal.php` — modal dashboard

### Arquivos Modificados
- `app/adms/Controllers/OrderPayments.php` — `bulkTransition()`, `dashboardData()`, permissão bulk
- `app/adms/Models/AdmsListOrderPayments.php` — `launch_number` no kanban SQL
- `app/adms/Views/orderPayment/listOrderPayment.php` — barra flutuante, select-all, condicionais permissão
- `app/adms/Views/orderPayment/partials/_kanban_card.php` — checkboxes inline, data attributes
- `app/adms/Views/orderPayment/partials/_view_order_payment_content.php` — timeline, anexos preview
- `app/adms/Views/orderPayment/loadOrderPayments.php` — include dashboard, CDN Chart.js
- `assets/js/order-payments.js` — bulk selection, dashboard charts, timeline, anexos
- `assets/css/personalizado.css` — timeline CSS, dashboard, bulk bar, thumbnails

### Crontab (produção)
```bash
# Alertas de parcelas vencidas — diário às 8h
0 8 * * * php /path/to/mercury/bin/cron-installment-alerts.php >> /var/log/mercury-alerts.log 2>&1
```

---

## Fase 12 — API REST ✅ Concluída (02/03/2026)

### Objetivo
Expor o módulo via REST API v1 para integrações externas.

### Implementação

**10 endpoints** registrados no `ApiRouter.php`, todos com autenticação JWT:

| Método | Rota | Action | Descrição |
|--------|------|--------|-----------|
| GET | `/api/v1/order-payments` | `index` | Listagem paginada com 10 filtros |
| GET | `/api/v1/order-payments/statistics` | `statistics` | Dashboard analytics (5 queries) |
| POST | `/api/v1/order-payments` | `store` | Criar ordem + parcelas + alocações |
| GET | `/api/v1/order-payments/{id}` | `show` | Detalhes + installments + allocations + history |
| PUT | `/api/v1/order-payments/{id}` | `update` | Partial update com upsert de alocações |
| DELETE | `/api/v1/order-payments/{id}` | `destroy` | Soft-delete 3 níveis (order + installments + allocations) |
| POST | `/api/v1/order-payments/{id}/transition` | `transition` | Transição de status via TransitionService |
| GET | `/api/v1/order-payments/{id}/installments` | `installments` | Parcelas com summary |
| POST | `/api/v1/order-payments/{id}/installments/{instId}/mark-paid` | `markInstallmentPaid` | Marcar/desmarcar parcela |
| GET | `/api/v1/order-payments/{id}/history` | `history` | Histórico de transições |

### Decisões de Design
- **Permissão:** `canViewFinancial()` (FINANCIALPERMITION) para restrição por loja
- **Rate limiting:** Global via ApiRateLimiter (60/min)
- **Valores monetários:** API aceita/retorna float numérico; fallback BRL via parseMoneyValue()
- **Scopes JWT:** Não implementado (nenhum controller existente usa)
- **Reuso:** TransitionService, DeleteService, AllocationService, AdmsViewOrderPayment
- **Helper:** `verifyOrderAccess(id)` centraliza fetch + 404 + 403

### Testes
- **88 testes, 220 assertions** em `tests/Api/OrderPaymentsApiTest.php`
- Cobertura: estrutura, rotas, permissões, validação, transições, money parsing, logging

### Arquivos
- `app/adms/Controllers/Api/V1/OrderPaymentsController.php` — Controller API
- `core/Api/ApiRouter.php` — 10 rotas registradas
- `tests/Api/OrderPaymentsApiTest.php` — 88 testes
- `docs/api/order-payments.yaml` — OpenAPI 3.0 spec

---

## Prioridade Sugerida

| Prioridade | Fase | Status | Justificativa |
|-----------|------|--------|---------------|
| ~~Alta~~ | ~~9 (UX/A11y)~~ | ✅ Concluída | Impacto direto na experiência do usuário |
| ~~Média~~ | ~~10 (Performance)~~ | ✅ Concluída | Queries otimizadas, cache, debounce, load-more |
| ~~Alta~~ | ~~11 (Funcionalidades)~~ | ✅ Concluída | Bulk transitions, dashboard, timeline, cron alertas |
| ~~Média~~ | ~~12 (API)~~ | ✅ Concluída | 10 endpoints REST, 88 testes, OpenAPI spec |

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
