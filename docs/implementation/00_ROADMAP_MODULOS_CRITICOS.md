# Roadmap de Implementacao — Modulos Criticos v1 → v2

**Versao:** 1.0
**Data:** 2026-04-09
**Projeto:** Mercury Laravel (SaaS Multi-Tenant)

---

## Matriz de Prioridade

| # | Modulo | Impacto | Compliance | Esforco | Fase |
|---|--------|---------|------------|---------|------|
| 1A | **Ordens de Pagamento** (melhoria) | ALTO | Financeiro | Baixo | 1 |
| 1B | **Ferias** | CRITICO | CLT obrigatorio | Alto | 1 |
| 1C | **Auditoria de Estoque** | CRITICO | Inventario | Alto | 1 |
| 2A | **Movimentacao de Pessoal** | ALTO | Processo RH | Medio | 2 |
| 2B | **Treinamentos** | MEDIO | Desenvolvimento RH | Medio | 2 |
| 3A | **Delivery + Routing** | MEDIO | Operacoes | Medio | 3 |
| 4A | **Chat + Helpdesk** | MENOR | Comunicacao | Alto | 4 |
| -- | **Relatorios/Exportacoes** | MENOR | Transversal | Incremental | Continuo |

## Fases

- **Fase 1** (Semanas 1-8): Order Payments Enhancement + Ferias + Auditoria Estoque
- **Fase 2** (Semanas 9-14): Movimentacao Pessoal + Treinamentos
- **Fase 3** (Semanas 15-18): Delivery + Routing
- **Fase 4** (Semanas 19-24): Chat + Helpdesk (requer laravel/reverb)

## Dependencias

```
Employee (existe) ──┬── Ferias (1B)
                    ├── Mov. Pessoal (2A) ── cascade ── Ferias cancel
                    ├── Treinamentos (2B)              └── cascade ── Training removal
                    └── Auditoria (1C)
Product (existe) ──── Auditoria (1C)
CIGAM (existe) ────── Auditoria (1C)
Transfer (existe) ─── Delivery (3A)
OrderPayment (parcial) ── OP Enhancement (1A)
WebSocket (NOVO) ──── Chat + Helpdesk (4A)
```

## Documentacao por Modulo

| Modulo | Arquivo |
|--------|---------|
| Order Payments Enhancement | [01_ORDER_PAYMENTS_ENHANCEMENT.md](01_ORDER_PAYMENTS_ENHANCEMENT.md) |
| Ferias | [02_FERIAS.md](02_FERIAS.md) |
| Auditoria de Estoque | [03_AUDITORIA_ESTOQUE.md](03_AUDITORIA_ESTOQUE.md) |
| Movimentacao de Pessoal | [04_MOVIMENTACAO_PESSOAL.md](04_MOVIMENTACAO_PESSOAL.md) |
| Treinamentos | [05_TREINAMENTOS.md](05_TREINAMENTOS.md) |
| Delivery + Routing | [06_DELIVERY_ROUTING.md](06_DELIVERY_ROUTING.md) |
| Chat + Helpdesk | [07_CHAT_HELPDESK.md](07_CHAT_HELPDESK.md) |

## Estimativa Total: ~167 arquivos novos

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
