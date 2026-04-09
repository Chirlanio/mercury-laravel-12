# Modulo 1A: Order Payments — Enhancement

**Status:** Em Implementacao
**Fase:** 1A (Aquecimento)
**Prioridade:** ALTA
**Estimativa:** ~6 arquivos novos

---

## 1. Estado Atual (v2)

### Backend — 90% completo
- `app/Http/Controllers/OrderPaymentController.php` — 14 metodos (index, store, show, update, transition, bulkTransition, destroy, checkDeletePermission, restore, saveAllocations, markInstallmentPaid, statistics)
- `app/Services/OrderPaymentTransitionService.php` — state machine completo (backlog→doing→waiting→done)
- `app/Services/OrderPaymentDeleteService.php` — soft delete 3 niveis
- `app/Services/OrderPaymentAllocationService.php` — rateio por centro de custo
- `app/Models/OrderPayment.php` — STATUS_*, VALID_TRANSITIONS, scopes, accessors
- `app/Models/OrderPaymentInstallment.php` — parcelas
- `app/Models/OrderPaymentAllocation.php` — rateio
- `app/Models/OrderPaymentStatusHistory.php` — historico

### Frontend — 70% completo
- `resources/js/Pages/OrderPayments/Index.jsx` (798 linhas) — Kanban + Table + Create + Transition + KPI cards
- Faltam: Dashboard com graficos, Export, Print voucher, Detail/Edit modal

### Rotas — 13 rotas existentes
Faltam: export, report, dashboard

---

## 2. Trabalho Restante

### 2.1 Backend

**Novos arquivos:**
| Arquivo | Descricao |
|---------|-----------|
| `app/Exports/OrderPaymentExport.php` | Export Excel (padrao EmployeesExport) |
| `app/Jobs/OrderPaymentOverdueAlertJob.php` | Job schedulado para alertas de vencimento |

**Metodos novos no controller existente:**
| Metodo | Descricao |
|--------|-----------|
| `export(Request)` | Gera Excel com filtros aplicados |
| `dashboard(Request)` | Dados para graficos (por mes, por area, por fornecedor) |

### 2.2 Frontend

**Novos arquivos:**
| Arquivo | Descricao |
|---------|-----------|
| `resources/js/Components/OrderPayments/DashboardCharts.jsx` | Modal/secao com graficos Chart.js (doughnut status, bar areas, line mensal) |
| `resources/js/Components/OrderPayments/DetailModal.jsx` | Modal de visualizacao detalhada com timeline de status |
| `resources/js/Components/OrderPayments/EditModal.jsx` | Modal de edicao (reutiliza logica do CreateModal) |

**Dependencias NPM:**
```
recharts (leve, React-native, sem Canvas dependency)
```

### 2.3 Rotas Novas

```php
Route::get('/order-payments/export', [OrderPaymentController::class, 'export'])->name('order-payments.export');
Route::get('/order-payments/dashboard', [OrderPaymentController::class, 'dashboard'])->name('order-payments.dashboard');
```

---

## 3. Detalhamento

### 3.1 OrderPaymentExport.php

Seguir padrao `EmployeesExport.php`:
- Implements: FromQuery, WithHeadings, WithMapping, WithStyles
- Filtros: status, store_id, search, date range
- Colunas: ID, Fornecedor, Loja, Valor, Data Pagamento, NF, Status, Parcelas, Criado por, Data Criacao

### 3.2 Dashboard Charts

Endpoint `statistics()` ja retorna: by_status, overdue, monthly_flow, installments.

Graficos:
1. **Doughnut:** Distribuicao por status (count)
2. **Bar horizontal:** Valor total por status
3. **Line:** Fluxo mensal (criadas vs pagas) — ultimos 6 meses
4. **Cards:** Parcelas vencidas, a vencer, pagas

### 3.3 Detail Modal

Ao clicar em um card do Kanban ou linha da tabela:
- Fetch GET /order-payments/{id} (show — ja implementado)
- Exibir: dados basicos, parcelas, rateio, timeline de status
- Botoes: Editar, Transicao, Excluir (com permissao)

### 3.4 Overdue Alert Job

Job diario que:
1. Busca OrderPayments com date_payment < today e status != done
2. Busca OrderPaymentInstallments com date_payment < today e is_paid = false
3. Loga alerta (via flash notification ou log)

---

## 4. Implementacao

### Ordem:
1. Export class + rota + metodo controller
2. Dashboard method no controller + rota
3. NPM: instalar recharts
4. DashboardCharts.jsx
5. DetailModal.jsx
6. EditModal.jsx
7. Overdue Alert Job + scheduler
8. Testes

### Testes Adicionais

| Cenario | Tipo |
|---------|------|
| Export gera Excel com dados corretos | Feature |
| Export aplica filtros (status, store) | Feature |
| Dashboard retorna estatisticas corretas | Feature |
| Overdue scope retorna ordens corretas | Unit |

---

**Referencia v1:** `docs/ANALISE_MODULO_ORDERPAYMENTS.md`, `docs/ROADMAP_ORDER_PAYMENTS.md`
