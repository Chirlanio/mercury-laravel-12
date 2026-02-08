# Módulo de Vendas (Sales) - Documentação

**Data:** 21 de Janeiro de 2026
**Autor:** Claude - Assistente de Desenvolvimento
**Versão:** 3.0 (Refatoração Completa)
**Status:** ✅ MODERNO

## 1. Visão Geral

O módulo `Sales` gerencia o ciclo de vida completo (CRUD) dos registros de vendas dos consultores, incluindo listagem, cadastro, edição, exclusão, sincronização com CIGAM e exclusão em lote por período.

### Status da Refatoração: ✅ COMPLETO (21/01/2026)

| Categoria | Status Anterior | Status Atual |
|---|---|---|
| **Arquitetura** | Múltiplos Controllers, page-reload | AJAX-driven com modais |
| **Padrão de Código** | Código espalhado | Padrão unificado |
| **UX (Experiência)** | Lenta e datada | Rápida e moderna |
| **Manutenibilidade** | Muito Baixa | Alta |
| **Testabilidade** | Nula | Alta (113 testes) |
| **NotificationService** | Parcial | ✅ 100% |
| **LoggerService** | Parcial | ✅ 100% |
| **Type Hints** | Parcial | ✅ 100% |

---

## 2. Arquitetura Atual

### 2.1 Estrutura de Arquivos

```
app/adms/Controllers/
├── Sales.php                    # Controller principal (listagem, busca, estatísticas)
├── AddSales.php                 # Adicionar venda
├── EditSalesByConsultant.php    # Editar vendas por consultor
├── ViewSalesByConsultant.php    # Visualizar vendas por consultor
├── DeleteSalesByConsultant.php  # Excluir vendas por consultor
├── SynchronizeSales.php         # Sincronização com CIGAM
└── DeleteSalesRange.php         # Exclusão em lote por período

app/adms/Models/
├── AdmsListSales.php            # Listagem paginada
├── AdmsStatisticsSales.php      # Estatísticas (cards)
├── AdmsAddSales.php             # CRUD - Adicionar
├── AdmsEditSalesByConsultant.php # CRUD - Editar
├── AdmsViewSalesByConsultant.php # CRUD - Visualizar
├── AdmsDeleteSalesByConsultant.php # CRUD - Excluir
├── AdmsSynchronizeSales.php     # Sincronização CIGAM
└── AdmsDeleteSalesRange.php     # Exclusão em lote

app/adms/Views/sales/
├── loadSales.php                # Página principal
├── listSales.php                # Listagem AJAX
├── editSalesByConsultant.php    # Formulário de edição
├── viewSalesByConsultant.php    # Visualização detalhada
└── partials/
    ├── _add_sale_modal.php      # Modal de adicionar
    ├── _edit_sale_by_consultant_modal.php
    ├── _delete_sale_by_consultant_modal.php
    ├── _delete_sales_range_modal.php
    └── _sync_sales_modal.php    # Modal de sincronização

assets/js/
├── sales.js                     # JavaScript principal
├── sales-delete-range.js        # Exclusão em lote
└── sales-sync.js                # Sincronização

tests/Sales/
├── AdmsAddSalesTest.php
├── AdmsDeleteSalesRangeTest.php
├── AdmsListSalesTest.php
├── AdmsStatisticsSalesTest.php
├── AdmsSynchronizeSalesTest.php
└── SalesControllerTest.php
```

### 2.2 Fluxo de Interação (AJAX)

- **Listagem/Busca:** AJAX com debounce, sem recarregamento de página
- **Criação:** Modal AJAX com validação e feedback instantâneo
- **Edição:** Modal AJAX carregado dinamicamente
- **Visualização:** Modal AJAX com dados completos
- **Exclusão:** Confirmação via modal, exclusão AJAX
- **Sincronização:** Modal com progresso em tempo real

---

## 3. Funcionalidades Implementadas

### 3.1 Listagem e Busca

- Listagem paginada via AJAX
- Busca com debounce (500ms)
- Filtros por consultor, loja e período
- Match expression para roteamento
- Cards de estatísticas em tempo real

### 3.2 CRUD Completo

- **Adicionar:** Modal AJAX com validação
- **Editar:** Modal carregado dinamicamente por consultor
- **Visualizar:** Modal com detalhes completos
- **Excluir:** Confirmação e exclusão AJAX

### 3.3 Funcionalidades Especiais

- **Sincronização CIGAM:** Modal com progresso, processamento em lote
- **Exclusão em Lote:** Por período, com confirmação e logging
- **Estatísticas:** Total de vendas, por loja, por período

### 3.4 Padrões Implementados

- ✅ NotificationService em todos os controllers
- ✅ LoggerService para auditoria completa
- ✅ Type hints em 100% dos métodos
- ✅ PHPDoc completo
- ✅ Match expression para roteamento
- ✅ FormSelectRepository para selects
- ✅ Modais em partials separados
- ✅ CSRF em todas as requisições

---

## 4. Testes Implementados

### 4.1 Cobertura de Testes

| Arquivo de Teste | Testes | Cobertura |
|------------------|--------|-----------|
| AdmsAddSalesTest.php | 15 | Validação, criação, permissões |
| AdmsDeleteSalesRangeTest.php | 12 | Exclusão em lote, validação de datas |
| AdmsListSalesTest.php | 18 | Listagem, paginação, filtros |
| AdmsStatisticsSalesTest.php | 20 | Cards, cálculos, permissões |
| AdmsSynchronizeSalesTest.php | 18 | Sincronização CIGAM, erros |
| SalesControllerTest.php | 30 | Controller, rotas, AJAX |
| **Total** | **113** | - |

### 4.2 Executar Testes

```bash
# Todos os testes do módulo Sales
php vendor/bin/phpunit tests/Sales/

# Teste específico
php vendor/bin/phpunit tests/Sales/SalesControllerTest.php
```

---

## 5. Referência para Outros Módulos

O módulo Sales serve como **implementação de referência** para refatoração de outros módulos complexos do projeto Mercury.

### 5.1 Padrões a Seguir

1. **Controller Principal:** Usar `match()` para roteamento
2. **Controllers de Ação:** NotificationService + LoggerService
3. **Models:** Type hints, getResult/getError padronizados
4. **Views:** Modais em partials, data-attributes no container
5. **JavaScript:** async/await, debounce, event delegation
6. **Testes:** PHPUnit com cobertura de validação e permissões

### 5.2 Arquivos de Exemplo

- `app/adms/Controllers/Sales.php` - Controller principal com match
- `app/adms/Controllers/AddSales.php` - Controller de ação AJAX
- `app/adms/Models/AdmsStatisticsSales.php` - Model de estatísticas
- `assets/js/sales.js` - JavaScript moderno
- `tests/Sales/SalesControllerTest.php` - Testes completos

---

## 6. Histórico de Versões

| Versão | Data | Autor | Alterações |
|--------|------|-------|------------|
| 3.0 | 21/01/2026 | Claude | Refatoração completa: AJAX, testes, padrões |
| 2.0 | 05/11/2025 | Gemini | Análise CRUD e plano de refatoração |
| 1.0 | - | - | Versão inicial (legado) |

---

**Última Atualização:** 21/01/2026
**Status:** ✅ MODERNO
**Testes:** 113 passando