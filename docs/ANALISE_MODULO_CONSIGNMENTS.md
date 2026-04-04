# Módulo de Consignações - Documentação Técnica

**Versão:** 2.0
**Última Atualização:** 20/03/2026

---

## Visão Geral

O módulo de Consignações gerencia transferências temporárias de produtos para clientes. Permite rastrear quais produtos foram consignados, para quem, por quanto tempo, e o status de retorno.

### Workflow

```
Pendente (1) ──→ Finalizada (2)
      │
      └──────→ Cancelada (3)
```

- **Pendente:** Consignação criada, aguardando retorno
- **Finalizada:** Produtos devolvidos, data de retorno preenchida automaticamente
- **Cancelada:** Consignação cancelada

---

## Estrutura de Arquivos

### Controllers

| Arquivo | Método | Descrição |
|---------|--------|-----------|
| `Consignments.php` | `list()` | Listagem principal com match expression (1=list, 2=search, 3=stats, 4=dashboard) |
| `AddConsignment.php` | `create()` | Criação via AJAX (JSON response) |
| `EditConsignment.php` | `edit()` | Edição via modal AJAX |
| `DeleteConsignment.php` | `delete()` | Exclusão com privilege escalation |
| `ViewConsignment.php` | `view()` | Detalhes via modal AJAX |
| `DeleteProductConsignment.php` | `delete()` | Exclusão de produto via AJAX (JSON) |
| `PrintConsignment.php` | `print()` | Página de impressão standalone |
| `ExportConsignment.php` | `export()` | Exportação CSV via ExportService |

### Models

| Arquivo | Responsabilidade |
|---------|-----------------|
| `AdmsAddConsignment.php` | Criação com validação explícita, UUID v7, duplicatas |
| `AdmsEditConsignment.php` | Edição com validação explícita, cálculo de datas |
| `AdmsDeleteConsignment.php` | Exclusão cascata com privilege escalation |
| `AdmsDeleteProductConsignment.php` | Exclusão de produto individual |
| `AdmsViewConsignment.php` | Consulta com JOINs de lookup |
| `AdmsListConsignments.php` | Listagem paginada com filtro por loja |
| `AdmsStatisticsConsignments.php` | 10 métodos: stats, filtros, por mês, por loja, recentes, financeiro |
| `AdmsExportConsignments.php` | Dados para exportação CSV (sem paginação) |
| `CpAdmsSearchConsignments.php` | Busca com filtros dinâmicos e paginação |

### Views

```
Views/consignments/
├── loadConsignments.php          # Página principal
├── listConsignments.php          # Tabela AJAX
├── printConsignment.php          # Página de impressão standalone
└── partials/
    ├── _add_consignment_form.php
    ├── _add_consignment_modal.php
    ├── _edit_consignment_form.php
    ├── _edit_consignment_modal.php
    ├── _view_consignment_content.php
    ├── _view_consignment_modal.php
    ├── _delete_consignment_modal.php
    ├── _statistics_dashboard.php    # 5 cards: Total, Pendentes, Concluídas, Produtos, Valor Pendente
    └── _dashboard_charts.php        # Chart.js: por mês (line) e por loja (stacked bar)
```

### JavaScript

| Arquivo | Linhas | Features |
|---------|--------|----------|
| `consignments.js` | ~950 | IIFE, async/await, CRUD modals, search, stats, stock lookup, Chart.js dashboard, print |

### Cron

| Arquivo | Schedule | Descrição |
|---------|----------|-----------|
| `bin/cron-consignment-alerts.php` | `0 9 * * *` | Alerta de consignações pendentes há 7+ dias |

---

## Tabelas do Banco

| Tabela | Descrição |
|--------|-----------|
| `adms_consignments` | Dados principais da consignação |
| `adms_consignment_products` | Produtos vinculados (1:N) |
| `adms_sit_consignments` | Lookup: situações (Pendente, Finalizada, Cancelada) |
| `adms_sit_consignment_products` | Lookup: situações de produtos |
| `adms_consignment_alerts` | Deduplicação de alertas do cron |

### Colunas Principais (adms_consignments)

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | INT PK | ID auto-incremento |
| `hash_id` | VARCHAR | UUID v7 para referência externa |
| `client_name` | VARCHAR | Nome do cliente |
| `documents` | VARCHAR | CPF (11 dígitos, sem formatação) |
| `adms_store_id` | VARCHAR FK | ID da loja (tb_lojas) |
| `adms_employee_id` | INT FK | ID do funcionário |
| `total_products` | INT | Quantidade total de produtos |
| `total_product_value` | DECIMAL | Valor total dos produtos |
| `consignment_note` | VARCHAR | Nota de remessa |
| `return_note` | VARCHAR | Nota de retorno |
| `date_consignments` | DATE | Data de envio |
| `date_return_consignments` | DATE | Data de retorno (auto ou manual) |
| `consignment_time` | INT | Diferença em dias (calculado) |
| `adms_sit_consignment_id` | INT FK | Status (1=Pendente, 2=Finalizada, 3=Cancelada) |

> **Nota:** Nomenclatura corrigida em 2026-03-26 (migration: `2026_03_26_fix_consignment_column_emploeey.sql`).

---

## Features

### Consulta de Estoque (P2.2)

Ao digitar uma referência de produto nos formulários de add/edit, o sistema consulta automaticamente o estoque via `FindProduct::searchLocal()`. Exibe badge inline:

- **Verde:** "Em estoque (X un)" com detalhamento por tamanho
- **Vermelho:** "Sem estoque"
- **Amarelo:** "Produto não encontrado"
- **Cinza:** "Estoque indisponível" (CIGAM offline)

A consulta é read-only — não deduz estoque automaticamente.

### Dashboard Chart.js (P3.4)

Seção colapsável com 2 gráficos:
- **Line chart:** Consignações por mês (últimos 12 meses)
- **Stacked bar chart:** Consignações por loja (Pendentes/Concluídas/Canceladas)

Endpoint: `consignments/list/1?typeconsignment=4` (JSON)

### Exportação CSV (P2.1)

Botão "Exportar" no header aplica filtros ativos da busca. Gera CSV UTF-8 BOM com separador `;`.

### Impressão (P2.4)

Página standalone com CSS `@media print`. Inclui: dados da consignação, tabela de produtos, assinaturas (consultor, cliente, responsável), rodapé com timestamp.

### Alertas de Pendência (P4.1)

Cron diário às 9h. Alerta consignações pendentes há 7+ dias via WebSocket. Deduplicação via `adms_consignment_alerts`.

### Relatório Financeiro (P4.2)

Cards de estatísticas incluem "Valor Pendente" (R$). Dashboard retorna dados de `getFinancialSummary()` com valores por status.

---

## Segurança

### Permissões

| Nível | Acesso |
|-------|--------|
| 1-3 (Admin) | Todas as lojas, editar/excluir finalizadas/canceladas |
| 4-17 | Lojas específicas, restrição em finalizadas |
| 18+ (Loja) | Apenas própria loja, sem editar finalizadas |

### Validação

- **Add:** Validação explícita de 6 campos + CPF (11 dígitos) + mínimo 1 produto
- **Edit:** Validação explícita de 5 campos + CPF (11 dígitos)
- **Duplicatas:** Máximo 1 consignação pendente por (CPF, Loja)
- **CSRF:** Token global via `csrf_field()`
- **XSS:** `htmlspecialchars()` em toda saída
- **SQL Injection:** Prepared statements em todas as queries

---

## Integrações

| Módulo/Serviço | Uso |
|----------------|-----|
| `LoggerService` | Auditoria (created, updated, deleted, exported, duplicated) |
| `SystemNotificationService` | WebSocket real-time (create, status_change, alerts) |
| `NotificationRecipientService` | Resolução de destinatários |
| `FormSelectRepository` | Dropdowns (stores, employees) |
| `ExportService` | CSV export |
| `FindProduct/ProductLookupService` | Consulta de estoque |
| `SessionContext` | Autenticação e permissões |
| `AdmsBotao` | Botões baseados em permissão |

---

## Rotas

| Rota | Controller | Método |
|------|-----------|--------|
| `consignments/list` | Consignments | list |
| `add-consignment/create` | AddConsignment | create |
| `edit-consignment/edit` | EditConsignment | edit |
| `delete-consignment/delete` | DeleteConsignment | delete |
| `view-consignment/view` | ViewConsignment | view |
| `delete-product-consignment/delete` | DeleteProductConsignment | delete |
| `print-consignment/print` | PrintConsignment | print |
| `export-consignment/export` | ExportConsignment | export |

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
