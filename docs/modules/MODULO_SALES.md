# Módulo de Vendas (Sales)

**Versão:** 4.0 (Laravel + React/Inertia)
**Última Atualização:** 22 de Fevereiro de 2026
**Status:** Implementado

---

## 1. Visão Geral

O módulo de Vendas gerencia registros de vendas diárias das consultoras por loja, com integração opcional ao sistema CIGAM (PostgreSQL) para importação automática de dados. A exibição principal é uma **tabela hierárquica** (Loja → Consultora → Vendas diárias) com totais consolidados que incluem vendas de loja física e e-commerce.

### Funcionalidades

- Visualização hierárquica: Loja → Consultora (accordion expansível)
- Modal de vendas diárias por consultora (loja física + e-commerce)
- CRUD completo de registros de vendas
- Sincronização com CIGAM (automática, por mês, por intervalo)
- Exclusão em lote por período
- Cards de estatísticas com comparativos (mês anterior, ano anterior)
- Filtros por loja, mês/ano, busca por nome
- Controle de acesso baseado em roles e permissões

---

## 2. Arquitetura

### 2.1 Estrutura de Arquivos

```
Backend
├── app/Http/Controllers/SaleController.php     # Controller (13 métodos)
├── app/Models/Sale.php                          # Model com scopes e relationships
├── app/Services/CigamSyncService.php            # Sincronização PostgreSQL → MySQL
├── database/migrations/..._create_sales_table.php
└── routes/web.php                               # 13 rotas com permissões

Frontend
├── resources/js/Pages/Sales/Index.jsx           # Página principal
└── resources/js/Components/
    ├── SalesHierarchyTable.jsx                  # Tabela accordion Loja → Consultora
    ├── EmployeeDailySalesModal.jsx              # Modal vendas diárias
    ├── SaleCreateModal.jsx                      # Modal criar venda
    ├── SaleEditModal.jsx                        # Modal editar venda
    ├── SaleViewModal.jsx                        # Modal visualizar venda
    ├── SaleSyncModal.jsx                        # Modal sincronização CIGAM
    ├── SaleBulkDeleteModal.jsx                  # Modal exclusão em lote
    └── SaleStatisticsCards.jsx                  # Cards de estatísticas

Testes
└── tests/Feature/SaleControllerTest.php         # 33 testes, 146 assertions
```

### 2.2 Stack

| Camada | Tecnologia |
|--------|-----------|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | React 18 + Inertia.js 2 |
| Banco primário | MySQL/MariaDB |
| Banco CIGAM | PostgreSQL (opcional, via conexão `cigam`) |
| Testes | PHPUnit + SQLite in-memory |

---

## 3. Banco de Dados

### 3.1 Tabela `sales`

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | bigint PK | Identificador |
| `store_id` | FK → stores | Loja onde a venda foi registrada |
| `employee_id` | FK → employees | Consultora que realizou a venda |
| `date_sales` | date | Data da venda |
| `total_sales` | decimal(10,2) | Valor total em R$ |
| `qtde_total` | integer | Quantidade de peças |
| `user_hash` | varchar(32), null | Hash de deduplicação (CIGAM) |
| `source` | enum('manual','cigam') | Origem do registro |
| `created_by_user_id` | FK → users, null | Usuário que criou |
| `updated_by_user_id` | FK → users, null | Usuário que atualizou |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Constraint única:** `(store_id, employee_id, date_sales)` — impede vendas duplicadas.

**Índices:** `(store_id, date_sales)`, `(employee_id, date_sales)`, `(date_sales)`.

### 3.2 Conexão CIGAM

Configurada em `config/database.php` como conexão `cigam` (driver `pgsql`). Variáveis de ambiente:

```
CIGAM_DB_HOST, CIGAM_DB_PORT, CIGAM_DB_DATABASE, CIGAM_DB_USERNAME, CIGAM_DB_PASSWORD
```

Tabela de origem: `msl_fmovimentodiario_` com colunas `data`, `cod_lojas`, `cpf_consultora`, `valor_realizado`, `qtde`, `controle`, `ent_sai`.

**Regras de importação:**
- `controle = 2` → venda (valor positivo)
- `controle = 6` + `ent_sai = 'E'` → devolução (valor negativo)
- Agrupado por `(data, cod_lojas, cpf_consultora)`

---

## 4. Rotas e Permissões

### 4.1 Tabela de Rotas

Todas sob middleware `auth` + `permission:VIEW_SALES`.

| Método | URI | Action | Permissão Extra | Descrição |
|--------|-----|--------|-----------------|-----------|
| GET | `/sales` | `index` | — | Listagem hierárquica |
| GET | `/sales/statistics` | `statistics` | — | Cards de estatísticas (JSON) |
| GET | `/sales/employee-daily` | `employeeDailySales` | — | Vendas diárias por consultora (JSON) |
| POST | `/sales` | `store` | CREATE_SALES | Criar venda |
| GET | `/sales/{sale}` | `show` | — | Detalhes da venda (JSON) |
| GET | `/sales/{sale}/edit` | `edit` | EDIT_SALES | Dados para edição (JSON) |
| PUT | `/sales/{sale}` | `update` | EDIT_SALES | Atualizar venda |
| DELETE | `/sales/{sale}` | `destroy` | DELETE_SALES | Excluir venda |
| POST | `/sales/sync/auto` | `syncAuto` | CREATE_SALES | Sync automática CIGAM |
| POST | `/sales/sync/month` | `syncByMonth` | CREATE_SALES | Sync por mês |
| POST | `/sales/sync/range` | `syncByDateRange` | CREATE_SALES | Sync por intervalo |
| POST | `/sales/bulk-delete/preview` | `bulkDeletePreview` | DELETE_SALES | Preview exclusão em lote |
| POST | `/sales/bulk-delete` | `bulkDelete` | DELETE_SALES | Executar exclusão em lote |

### 4.2 Permissões por Role

| Role | VIEW | CREATE | EDIT | DELETE |
|------|------|--------|------|--------|
| SUPER_ADMIN | Sim | Sim | Sim | Sim |
| ADMIN | Sim | Sim | Sim | Sim |
| SUPPORT | Sim | — | — | — |
| USER | — | — | — | — |

### 4.3 Restrição por Loja

Usuários não-admin (SUPPORT) são automaticamente restritos à loja do seu funcionário vinculado. O filtro inclui vendas de e-commerce das consultoras contratadas nessa loja.

---

## 5. Controller — `SaleController`

### 5.1 `index()` — Listagem Hierárquica

Retorna dados agrupados por loja → consultora para o componente `SalesHierarchyTable`.

**Parâmetros de query:** `store_id`, `month`, `year`, `search`

**Props Inertia retornadas:**

| Prop | Tipo | Descrição |
|------|------|-----------|
| `salesByStore` | array | Lojas com consultoras aninhadas (ver estrutura abaixo) |
| `grandTotals` | object | `{total_sales, qtde_total, total_stores, total_employees}` |
| `stores` | array | Lista de lojas ativas para o filtro |
| `filters` | object | Filtros ativos `{store_id, month, year, search}` |
| `cigamAvailable` | boolean | Se a conexão CIGAM está disponível |

**Estrutura de `salesByStore`:**

```json
[
  {
    "store_id": 1,
    "store_name": "Z421 - Loja A",
    "total_sales": 50000.00,
    "qtde_total": 200,
    "employees": [
      {
        "employee_id": 1,
        "employee_name": "FULANA",
        "total_sales": 25000.00,
        "qtde_total": 100
      }
    ]
  }
]
```

**Lógica de consolidação e-commerce:**

O total de cada consultora e cada loja inclui tanto vendas da loja física quanto do e-commerce. O mecanismo varia conforme o contexto:

- **Com filtro de loja** (`groupSalesUnderSingleStore`): O scope `forStoreWithEcommerce` já traz vendas da loja física + e-commerce das consultoras contratadas. Todas são agrupadas sob a loja filtrada.
- **Sem filtro — todas as lojas** (`groupSalesWithEcommerceRemapping`): Vendas de e-commerce são reatribuídas à loja física da consultora, determinada pelo contrato de trabalho ativo (`EmploymentContract`). Consultoras sem contrato em loja física permanecem sob o e-commerce (Z441).

### 5.2 `employeeDailySales()` — Vendas Diárias

Endpoint JSON que retorna todas as vendas de uma consultora no mês, **independente da loja** (mostra o panorama completo: loja física + e-commerce).

**Parâmetros:** `employee_id`, `store_id`, `month`, `year`

**Resposta:**

```json
{
  "employee": { "id": 1, "name": "FULANA DA SILVA", "short_name": "FULANA" },
  "store": { "id": 1, "name": "Z421 - Loja A" },
  "daily_sales": [
    {
      "id": 10,
      "date_sales": "15/01/2026",
      "date_sales_raw": "2026-01-15",
      "total_sales": 800.00,
      "qtde_total": 5,
      "source": "cigam",
      "store_id": 1,
      "store_name": "Z421 - Loja A",
      "is_ecommerce": false
    }
  ],
  "totals": {
    "store_total": 20000.00,
    "store_qtde": 100,
    "ecommerce_total": 5000.00,
    "ecommerce_qtde": 20,
    "total": 25000.00,
    "total_qtde": 120
  }
}
```

### 5.3 `statistics()` — Cards de Estatísticas

Retorna métricas para os cards no topo da página:

| Campo | Descrição |
|-------|-----------|
| `current_month_total` | Total do mês atual |
| `last_month_total` | Total do mês anterior |
| `variation` | Variação % mês a mês |
| `same_month_last_year` | Total do mesmo mês no ano anterior |
| `yoy_variation` | Variação % ano a ano |
| `active_stores` | Lojas com vendas no mês |
| `active_consultants` | Consultoras com vendas no mês |
| `total_records` | Total de registros |
| `avg_per_store` | Média por loja |
| `avg_per_consultant` | Média por consultora |
| `last_sync` | Data da última sincronização CIGAM |

### 5.4 Sincronização CIGAM

Três modos disponíveis:

| Método | Descrição |
|--------|-----------|
| `syncAuto()` | Da última data sincronizada até ontem |
| `syncByMonth()` | Mês/ano específico (opcionalmente filtrado por loja) |
| `syncByDateRange()` | Intervalo de datas customizado |

O `CigamSyncService` conecta ao PostgreSQL, lê `msl_fmovimentodiario_`, mapeia `cod_lojas` → `stores.id` e `cpf_consultora` → `employees.id`, e faz upsert na tabela `sales`. Registros com CPFs ou lojas não mapeados são reportados na mensagem flash.

### 5.5 Exclusão em Lote

- `bulkDeletePreview()` — Retorna contagem de registros, valor total, lojas e funcionários afetados
- `bulkDelete()` — Executa a exclusão. Modos: por mês ou por intervalo de datas, opcionalmente filtrado por loja

---

## 6. Model — `Sale`

### 6.1 Relationships

| Método | Tipo | Model |
|--------|------|-------|
| `store()` | BelongsTo | Store |
| `employee()` | BelongsTo | Employee |
| `createdBy()` | BelongsTo | User |
| `updatedBy()` | BelongsTo | User |

### 6.2 Scopes

| Scope | Parâmetros | Descrição |
|-------|------------|-----------|
| `forStore` | `int $storeId` | Filtra por `store_id` |
| `forStoreWithEcommerce` | `int $storeId` | Filtra por loja + e-commerce das consultoras contratadas |
| `forEmployee` | `int $employeeId` | Filtra por `employee_id` |
| `forMonth` | `int $month, int $year` | Filtra por mês/ano |
| `forDateRange` | `$start, $end` | Filtra por intervalo de datas |
| `fromCigam` | — | Apenas registros `source = 'cigam'` |
| `manual` | — | Apenas registros `source = 'manual'` |

### 6.3 Scope `forStoreWithEcommerce` — Detalhamento

Este scope é central para a lógica de e-commerce. Dado um `store_id` de loja física:

1. Busca os `employee_id` com contrato ativo (`EmploymentContract`) nessa loja
2. Retorna vendas onde `store_id = loja` **OU** `(store_id = Z441 AND employee_id IN contratados)`

Se o `store_id` for o próprio e-commerce (Z441), retorna apenas `where store_id = Z441`.

### 6.4 Accessor

- `formatted_total` — Retorna `R$ 1.234,56`

---

## 7. Frontend

### 7.1 Página Principal — `Sales/Index.jsx`

Compõe a tela com:

1. **Header** com botões: Sincronizar, Excluir Período, Nova Venda
2. **SaleStatisticsCards** — 4 cards com métricas (fetch assíncrono para `/sales/statistics`)
3. **Filtros** — Loja, Mês, Ano, Limpar Filtros
4. **SalesHierarchyTable** — Tabela hierárquica principal

**Modais gerenciados:**

| Modal | Trigger |
|-------|---------|
| `SaleCreateModal` | Botão "Nova Venda" |
| `EmployeeDailySalesModal` | Clique em consultora na hierarquia |
| `SaleEditModal` | Botão editar dentro do modal de vendas diárias |
| `SaleSyncModal` | Botão "Sincronizar" |
| `SaleBulkDeleteModal` | Botão "Excluir Período" |
| Delete confirmation (inline) | Botão excluir dentro do modal de vendas diárias |

### 7.2 `SalesHierarchyTable` — Tabela Accordion

Exibição em dois níveis expansíveis:

```
| ▶ Z421 - Loja A      | 5 consultoras | 200  | R$ 50.000,00  |
| ▼ Z422 - Loja B      | 3 consultoras | 150  | R$ 30.000,00  |
|   ● CONSULTORA 1      |               | 80   | R$ 16.000,00  |  ← clicável
|   ● CONSULTORA 2      |               | 40   | R$ 8.000,00   |
| ▶ Z441 - E-Commerce   | 2 consultoras | 100  | R$ 20.000,00  |
|========================|===============|======|===============|
| TOTAL                  | 10            | 450  | R$ 100.000,00 |
```

- Linhas de loja com fundo `indigo-50`, clique para expandir/recolher (chevron)
- Consultoras ordenadas por `total_sales` desc dentro de cada loja
- Lojas ordenadas por `total_sales` desc
- Busca por nome de consultora (filtra via URL, preserva filtros)
- Rodapé com totais gerais

### 7.3 `EmployeeDailySalesModal` — Vendas Diárias

Modal aberto ao clicar numa consultora. Faz fetch para `/sales/employee-daily`.

**Layout:**
- Header: nome da consultora, loja, mês/ano
- Tabela: Data, Local (badge roxa "E-Commerce" ou nome da loja), Qtde, Valor, Origem (CIGAM/Manual), Ações
- Totais: Loja Física, E-Commerce, Total (com peças)
- Loading skeleton durante o fetch
- Botões editar/excluir por linha

### 7.4 `SaleStatisticsCards`

4 cards com fetch assíncrono:

1. **Total Mês Atual** — valor + variação % vs mês anterior
2. **Mesmo Mês Ano Anterior** — valor + variação % YoY
3. **Lojas / Consultores** — contagem de lojas e consultoras ativas
4. **Média por Loja / Consultor** — valor médio

### 7.5 `SaleSyncModal`

3 abas de sincronização:

| Aba | Endpoint | Parâmetros |
|-----|----------|------------|
| Automática | `POST /sales/sync/auto` | — |
| Por Mês | `POST /sales/sync/month` | month, year, store_id? |
| Por Período | `POST /sales/sync/range` | start_date, end_date, store_id? |

Exibe aviso se `cigamAvailable = false`.

### 7.6 `SaleBulkDeleteModal`

Fluxo em 2 etapas:
1. **Preview** (`POST /sales/bulk-delete/preview`) — mostra registros, valor, lojas e funcionários afetados
2. **Confirmação** (`POST /sales/bulk-delete`) — requer checkbox de confirmação

---

## 8. Lógica de E-Commerce

A loja Z441 (`Store::ECOMMERCE_CODE`) é tratada de forma especial em todo o módulo.

### 8.1 Princípio

Consultoras contratadas em lojas físicas podem ter vendas registradas tanto na loja física quanto no e-commerce. Para fins de visualização e totais, essas vendas de e-commerce devem ser **consolidadas** com as vendas da loja física.

### 8.2 Mecanismo — Visão Geral

| Contexto | Comportamento |
|----------|---------------|
| **Filtro por loja física** | `forStoreWithEcommerce` traz vendas da loja + e-commerce das contratadas. `groupSalesUnderSingleStore` agrupa tudo sob a loja filtrada. |
| **Sem filtro (todas as lojas)** | `groupSalesWithEcommerceRemapping` consulta `EmploymentContract` para mapear `employee_id → loja física` e reatribui vendas de e-commerce. |
| **Filtro por e-commerce (Z441)** | Retorna apenas vendas registradas no e-commerce, sem reatribuição. |
| **Modal de vendas diárias** | Mostra TODAS as vendas da consultora no mês (qualquer loja), com flag `is_ecommerce` e totais separados. |

### 8.3 Fluxo de Mapeamento (`groupSalesWithEcommerceRemapping`)

```
1. Obter employee_ids únicos das vendas do mês
2. Buscar EmploymentContract ativo para cada employee_id
3. Mapear store_id (código) → stores.id via tabela stores
4. Para cada venda de e-commerce:
   - Se employee tem contrato em loja física → display_store_id = loja física
   - Senão → display_store_id = e-commerce (Z441)
5. Agrupar por display_store_id → employee_id
```

---

## 9. Testes

### 9.1 Arquivo

`tests/Feature/SaleControllerTest.php` — **33 testes, 146 assertions**

### 9.2 Cobertura

| Categoria | Testes | O que cobre |
|-----------|--------|-------------|
| Index | 5 | Auth, permissões, dados agrupados, filtro loja, filtro mês/ano, busca |
| CRUD | 7 | Criar, validação, duplicata, data futura, show JSON, update, delete |
| Statistics | 2 | Dados corretos, filtro non-admin |
| Employee Daily Sales | 4 | Dados, e-commerce split, permissões, validação |
| Bulk Delete | 3 | Preview, execução, permissões |
| Permissions | 2 | Support read-only, user sem acesso |
| E-Commerce Filter | 6 | Loja física, e-commerce por contrato, outra loja, filtro e-commerce, merge totais, remap all-stores, statistics |

### 9.3 Executar Testes

```bash
# Requer PHP com pdo_sqlite
C:\Users\MSDEV\php84\php.exe artisan test --filter=SaleControllerTest
```

---

## 10. Configuração

### 10.1 Variáveis de Ambiente

```env
# Conexão CIGAM (opcional)
CIGAM_DB_HOST=192.168.x.x
CIGAM_DB_PORT=5432
CIGAM_DB_DATABASE=cigam
CIGAM_DB_USERNAME=user
CIGAM_DB_PASSWORD=pass
```

### 10.2 PHP

A sincronização CIGAM e os testes requerem o PHP com extensões `pdo_pgsql` e `pdo_sqlite`:

```bash
C:\Users\MSDEV\php84\php.exe artisan serve
```

---

## 11. Histórico de Versões

| Versão | Data | Alterações |
|--------|------|------------|
| 4.0 | 22/02/2026 | Migração Laravel: tabela hierárquica (Loja → Consultora), modal vendas diárias, consolidação e-commerce nos totais, 33 testes |
| 3.0 | 21/01/2026 | Refatoração Mercury PHP: AJAX, modais, 113 testes |
| 2.0 | 05/11/2025 | Análise CRUD e plano de refatoração |
| 1.0 | — | Versão inicial (legado Mercury PHP) |
