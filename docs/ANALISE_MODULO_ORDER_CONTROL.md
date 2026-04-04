# Análise do Módulo — Ordens de Compra (OrderControl)

**Data:** 24/03/2026
**Versão:** 2.0 (pós-refatoração)
**Testes:** 232 (703 assertions)

---

## Visão Geral

Módulo de gestão de ordens de compra com CRUD completo, importação de planilhas, geração de códigos de barras EAN-13, precificação em lote e integração WebSocket.

---

## Arquitetura

### Controllers (12)

| Arquivo | Propósito |
|---------|-----------|
| `OrderControl.php` | Listagem, busca, estatísticas |
| `AddOrderControl.php` | Criação de ordem |
| `EditOrderControl.php` | Edição de ordem |
| `DeleteOrderControl.php` | Exclusão com transação + notificação WS |
| `ViewOrderControl.php` | Visualização (AJAX modal + full page) |
| `DuplicateOrderControl.php` | Duplicação de ordem com itens |
| `ExportOrderControl.php` | Exportação Excel e PDF |
| `AddOrderControlItems.php` | Adicionar itens (single + múltiplos) |
| `EditOrderControlItem.php` | Editar item |
| `DeleteOrderControlItem.php` | Excluir item |
| `ViewOrderControlItem.php` | Visualizar item |
| `ImportOrderControl.php` | Importar planilha |
| `GenerateBarcodesOrderControl.php` | Gerar EAN-13 |

### Models (14)

| Arquivo | Propósito |
|---------|-----------|
| `AdmsAddOrderControl.php` | Create com `OrderControlValidationService` |
| `AdmsEditOrderControl.php` | Update com `OrderControlValidationService` |
| `AdmsDeleteOrderControl.php` | Delete com transação PDO + `checkDependencies()` |
| `AdmsViewOrderControl.php` | View ordem + itens + EAN-13 + histórico |
| `AdmsListOrderControl.php` | Listagem paginada com `StorePermissionTrait` |
| `AdmsStatisticsOrderControl.php` | KPIs consolidados (2 queries) com `StorePermissionTrait` |
| `AdmsDuplicateOrderControl.php` | Duplicação com transação PDO |
| `AdmsExportOrderControl.php` | Dados para exportação com `StorePermissionTrait` |
| `AdmsAddOrderControlItems.php` | Inserção de itens (single + size matrix) |
| `AdmsEditOrderControlItem.php` | Edição de item |
| `AdmsDeleteOrderControlItem.php` | Exclusão de item |
| `AdmsViewOrderControlItem.php` | Visualização de item |
| `AdmsImportOrderControl.php` | Parsing de planilha + validação + chunked insert |
| `AdmsGenerateBarcodesOrderControl.php` | Geração EAN-13 com tabelas auxiliares |

### Services (3)

| Arquivo | Propósito |
|---------|-----------|
| `OrderControlValidationService.php` | Validação centralizada (campos, datas, loja, marca, unicidade) |
| `OrderControlStatusTransitionService.php` | State machine de status com permissões e histórico |
| `Ean13Generator.php` | Geração de EAN-13 (prefixo 2, uso interno) |

### Constants (1)

| Arquivo | Propósito |
|---------|-----------|
| `OrderControlStatus.php` | PENDING(1), INVOICED(2), PARTIAL_INVOICED(3), CANCELLED(4), DELIVERED(5) |

### Views (3 pages + 15 partials)

- `loadOrderControl.php` — Página principal (cards, filtros avançados, tabela)
- `listOrderControl.php` — Lista AJAX (sort, indicador atraso, empty state)
- `importOrderControl.php` — Upload de planilha

### JavaScript (3)

- `order-control.js` — CRUD + sort + barcode + statistics
- `order-control-items.js` — Gestão de itens
- `import-order-control.js` — Importação com progresso

---

## Tabelas do Banco de Dados

| Tabela | Propósito |
|--------|-----------|
| `adms_purchase_order_controls` | Cabeçalho da ordem |
| `adms_purchase_order_control_items` | Itens/produtos da ordem |
| `adms_sits_orders` | Status (lookup) |
| `adms_order_control_status_history` | Histórico de transições de status |
| `adms_order_barcode_products` | Registro auxiliar de produtos para EAN-13 (AUTO_INCREMENT 700001) |
| `adms_order_barcode_variants` | Registro auxiliar de variantes para EAN-13 |
| `adms_marcas` | Marcas (lookup) |
| `tb_lojas` | Lojas (lookup) |
| `adms_product_types` | Tipos de produto (lookup) |
| `adms_cors` | Cores dos status (lookup) |

### Campos de Auditoria (adms_purchase_order_controls)

- `created_by` — Nome do criador (VARCHAR)
- `created_by_user_id` — ID do criador (INT, FK adms_usuarios)
- `updated_by_user_id` — ID do último editor (INT, FK adms_usuarios)
- `created_at` / `updated_at` — Timestamps

---

## Fluxo de Status (State Machine)

```
Pendente(1) ──→ Faturado(2) ──→ Entregue(5) [final]
    │               │
    ├──→ Faturado Parcial(3) ──→ Faturado(2)
    │               │
    └──→ Cancelado(4) ←──────────┘
         │
         └──→ Pendente(1) [reabrir, admin apenas]
```

### Permissões por Nível

| Transição | Admin (≤2) | Gerente (≤5) | Loja (>5) |
|-----------|:---:|:---:|:---:|
| Faturar / Faturar Parcial | ✓ | ✓ | ✗ |
| Entregar | ✓ | ✓ | ✗ |
| Cancelar | ✓ | ✗ | ✗ |
| Reabrir | ✓ | ✗ | ✗ |

---

## Geração de Códigos de Barras EAN-13

- Tabelas auxiliares independentes do catálogo Cigam
- `adms_order_barcode_products` com AUTO_INCREMENT 700001 (evita colisão)
- Geração idempotente: mesma referência + tamanho = mesmo EAN-13
- Composição: `2` (prefixo in-store) + `product_id` (6 dígitos) + `variant_id` (5 dígitos) + checksum
- Barcode exibido no modal de detalhes (card dedicado)

---

## Filtros Disponíveis

| Filtro | Tipo | Aplicado em |
|--------|------|-------------|
| Pesquisa geral | Texto (LIKE descrição, estação, coleção, marca) | Listagem + Estatísticas |
| Situação | Select (adms_sits_orders) | Listagem + Estatísticas |
| Marca | Select (adms_marcas) | Listagem + Estatísticas |
| Período | Date (order_date >= / <=) | Listagem + Estatísticas |
| Loja | Automático (StorePermissionTrait) | Listagem + Busca + Estatísticas + Exportação |

---

## Segurança

- **SQL Injection**: Todas as queries usam prepared statements (AdmsRead/AdmsCreate/AdmsUpdate)
- **XSS**: Todas as views usam `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`
- **CSRF**: Token validado pelo ConfigController (middleware global)
- **Permissões**: Botões via `AdmsBotao::valBotao()` + status via `OrderControlStatusTransitionService`
- **Filtro de loja**: `StorePermissionTrait` em listagem, busca, estatísticas e exportação
- **Transação**: Delete e duplicação usam PDO `beginTransaction`/`commit`/`rollBack`
- **Validação**: `OrderControlValidationService` centralizado (campos, datas, FK, unicidade)

---

## Testes

| Arquivo | Testes | Escopo |
|---------|--------|--------|
| `AdmsImportOrderControlTest.php` | 50+ | Mapeamento de colunas, parsing |
| `AdmsStatisticsOrderControlTest.php` | 20+ | Estatísticas, filtros |
| `ImportOrderControlBackgroundTest.php` | ~15 | Processamento em background |
| `ImportOrderControlControllerTest.php` | ~10 | Controller de importação |
| `ImportOrderControlFeatureTest.php` | ~15 | E2E importação |
| `ImportOrderControlValidationTest.php` | ~10 | Validação de dados |
| `ViewOrderControlItemTest.php` | ~10 | Visualização de itens |
| `OrderControlValidationServiceTest.php` | 15 | Validação centralizada |
| `OrderControlStatusTransitionServiceTest.php` | 35 | State machine + permissões |
| `AdmsAddOrderControlTest.php` | 10 | Criação de ordens |
| `AdmsDeleteOrderControlTest.php` | 8 | Exclusão de ordens |
| `AdmsDuplicateOrderControlTest.php` | 7 | Duplicação de ordens |
| **Total** | **232** | **703 assertions** |

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
