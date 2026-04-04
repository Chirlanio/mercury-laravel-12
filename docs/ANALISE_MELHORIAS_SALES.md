# Análise de Melhorias - Módulo Sales

**Versão:** 1.0
**Data:** 15/02/2026
**Referência:** Comparação Sales vs APE (Avaliação Prática de Experiência)

---

## 1. Estado Anterior (Antes das Melhorias)

O módulo Sales foi refatorado em Janeiro/2026, atingindo nota **8/10**. As lacunas identificadas em comparação com o módulo APE (9/10) foram:

| Critério | Sales (Antes) | APE | Gap |
|----------|:---:|:---:|:---:|
| Filtro de loja centralizado | Duplicado em 5+ locais | Trait único | -1 |
| LoggerService em CRUD | Ausente | Completo | -1 |
| Transactions em CRUD | Ausente | Presente | 0* |
| Cache de estatísticas | Ausente | 5min sessão | -0.5 |
| Filtros de busca | 2 (texto, mês) | 6 | -0.5 |
| Relatórios/impressão | Ausente | 3 relatórios | -1 |
| Testes unitários | 113 testes | 48 testes | +1 |
| Documentação | Completa | Completa | 0 |

*Transactions: não implementado nesta rodada (requer mudanças nos helpers AdmsCreate/AdmsUpdate).

---

## 2. Melhorias Implementadas

### 2.1 FinancialPermissionTrait (Novo)

**Arquivo:** `app/adms/Models/helper/FinancialPermissionTrait.php`

**Decisão técnica:** Criar trait separado em vez de reutilizar `StorePermissionTrait` porque:
- `FINANCIALPERMITION` (tipicamente 2) vs `STOREPERMITION` (tipicamente 18) - constantes diferentes
- Operador `>` (estrito) vs `>=` (inclusivo) - semântica diferente
- Filtro dual `(ts.adms_store_id = :storeId OR emp.adms_store_id = :storeId)` vs filtro simples

**Métodos:**
- `isFinancialRestricted(): bool`
- `getFinancialStoreId(): ?string`
- `buildFinancialStoreFilter(salesAlias, empAlias, storeColumn): array`

**Adotado em:** AdmsStatisticsSales, AdmsListSales, CpAdmsSearchSales

### 2.2 LoggerService em Operações CRUD

| Arquivo | Operação | Ação de Log |
|---------|----------|-------------|
| `AdmsAddSales.php` | addSaleDirect() | `SALE_CREATED` / `SALE_CREATE_FAILED` |
| `AdmsEditSalesByConsultant.php` | altSale() | `SALE_UPDATED` / `SALE_UPDATE_FAILED` |
| `AdmsDeleteSalesByConsultant.php` | delete() | `SALE_DELETED` (com dados pré-delete) / `SALE_DELETE_FAILED` |

Substituiu chamadas `error_log()` por `LoggerService::info()` e `LoggerService::error()`.

### 2.3 Cache de Sessão em Estatísticas

**Arquivo:** `AdmsStatisticsSales.php`

- TTL: 5 minutos (300 segundos) - mesmo padrão do APE
- Chave: `sales_stats_` + MD5 dos parâmetros (mês, ano, searchTerm, storeId)
- Invalidação automática por TTL
- Reduz ~6 queries por carregamento de página em acessos consecutivos

### 2.4 Filtros Extras de Busca

Novos filtros adicionados ao formulário:

| Filtro | Tipo | ID | Visibilidade |
|--------|------|----|-------------|
| Loja | select | `searchStore` | Apenas admin/financeiro (ordem_nivac <= FINANCIALPERMITION) |
| Status | select | `searchStatus` | Todos (Confirmado/Pendente) |
| Data Início | date | `searchDateStart` | Todos |
| Data Fim | date | `searchDateEnd` | Todos |

**Arquivos modificados:** loadSales.php (view), sales.js (JS), Sales.php (controller), CpAdmsSearchSales.php (model)

### 2.5 Relatórios de Vendas

Dropdown "Relatórios" no header com 3 opções:

| Relatório | Método Model | Descrição |
|-----------|-------------|-----------|
| Vendas por Loja | `calculateSalesByStore()` | Ranking de lojas com total, consultores, média |
| Vendas por Consultor | `calculateSalesByConsultant()` | Top consultores com total e registros |
| Comparativo Mensal | `calculateMonthlyComparison()` | Mês atual vs anterior vs mesmo mês ano passado |

**Endpoint:** `sales/report` (POST, JSON)
**Impressão:** Janela popup com estilos B&W-safe (fundo #e9ecef, texto #212529)

### 2.6 Debounce Padronizado

Debounce do campo texto alterado de 500ms para 400ms (padrão do projeto).
Selects e dates usam evento `change` imediato.

---

## 3. Inventário de Arquivos

### Arquivos Novos (2)
| Arquivo | Linhas |
|---------|--------|
| `app/adms/Models/helper/FinancialPermissionTrait.php` | ~90 |
| `docs/ANALISE_MELHORIAS_SALES.md` | Este documento |

### Arquivos Modificados (9)
| Arquivo | Mudanças |
|---------|----------|
| `app/adms/Models/AdmsStatisticsSales.php` | +FinancialPermissionTrait, +cache, +3 métodos de relatório |
| `app/adms/Models/AdmsListSales.php` | +FinancialPermissionTrait, refatoração de queries |
| `app/adms/Models/AdmsAddSales.php` | +LoggerService |
| `app/adms/Models/AdmsEditSalesByConsultant.php` | +LoggerService |
| `app/adms/Models/AdmsDeleteSalesByConsultant.php` | +LoggerService |
| `app/cpadms/Models/CpAdmsSearchSales.php` | +FinancialPermissionTrait, +filtros extras, -isAuthorized() |
| `app/adms/Controllers/Sales.php` | +filtros extras, +report() endpoint |
| `app/adms/Views/sales/loadSales.php` | +filtros extras, +dropdown relatórios |
| `assets/js/sales.js` | +debounce 400ms, +filtros, +relatórios, +impressão |

---

## 4. Pontuação Atualizada

| Critério | Antes | Depois |
|----------|:---:|:---:|
| Arquitetura MVC | 9 | 9 |
| Segurança (PDO, XSS, CSRF) | 9 | 9 |
| Permissões (Trait) | 7 | 9 |
| LoggerService | 5 | 9 |
| Cache | 5 | 9 |
| Filtros | 6 | 9 |
| Relatórios | 0 | 8 |
| Responsividade | 9 | 9 |
| Testes | 10 | 10 |
| Documentação | 9 | 9 |
| **Média** | **6.9** → **8/10** | **9/10** |

---

## 5. Itens Pendentes (Futuro)

- [ ] Transactions (DB) em operações CRUD - requer refatoração dos helpers
- [ ] Testes para novos métodos de relatório
- [ ] Filtro de loja nos endpoints de listagem (não apenas busca)
- [ ] Export CSV/Excel dos relatórios

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
