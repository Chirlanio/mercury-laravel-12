# Modulo 3A: Delivery + Routing

**Status:** Pendente
**Fase:** 3A
**Prioridade:** MEDIA — Operacoes
**Estimativa:** ~18 arquivos novos
**Referencia v1:** `C:\wamp64\www\mercury\app\adms\Controllers\Delivery.php`

---

## 1. Visao Geral

Gestao de entregas e rotas de motoristas, integrado com modulo de Transferencias e Motoristas (ja existentes).

## 2. State Machine (Deliveries)
```
Pendente → Atribuida → Em Transito → Entregue → Confirmada
Qualquer (nao terminal) → Cancelada
```

## 3. Arquivos a Criar

### Migrations (4)
create_deliveries, delivery_items, routes, route_stops

### Models (4), Services (2), Controllers (2), Frontend (4), Tests (1)

## 4. Permissions (5)
VIEW_DELIVERIES, CREATE_DELIVERIES, EDIT_DELIVERIES, DELETE_DELIVERIES, MANAGE_ROUTES

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
