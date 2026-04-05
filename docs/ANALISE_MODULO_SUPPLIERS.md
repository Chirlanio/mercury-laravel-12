# Analise do Modulo de Fornecedores (Suppliers)

**Versao:** 1.0
**Data:** 04 de Abril de 2026
**Autor:** Chirlanio Silva - Grupo Meia Sola

---

## 1. Visao Geral

O modulo de Fornecedores gerencia o cadastro, consulta, edicao e exclusao de fornecedores no sistema Mercury. E um modulo CRUD padrao com listagem paginada, busca com filtros, operacoes via modal (AJAX) e mascaras de entrada para documentos (CNPJ/CPF) e telefone.

### Classificacao

| Aspecto | Valor |
|---------|-------|
| **Tipo** | CRUD padrao com modais AJAX |
| **Maturidade** | Moderno (match expressions, type hints, async/await) |
| **Complexidade** | Media-baixa |
| **Cobertura de Testes** | 87 testes unitarios (6 arquivos, 2.048 linhas) |
| **Linhas de Codigo** | ~2.500 (backend) + 768 (JS) |

---

## 2. Arquitetura

### 2.1 Estrutura de Arquivos

```
app/adms/Controllers/
    Supplier.php              # Controller principal (listagem + busca)
    AddSupplier.php           # Criacao via POST/JSON
    EditSupplier.php          # Edicao (load + update)
    DeleteSupplier.php        # Exclusao via POST/JSON
    ViewSupplier.php          # Visualizacao (retorna HTML parcial)

app/adms/Models/
    AdmsSupplier.php          # Model base (utilitarios: formatacao, status)
    AdmsListSuppliers.php     # Listagem paginada + busca com filtros
    AdmsAddSupplier.php       # Criacao com validacao + duplicidade CNPJ
    AdmsEditSupplier.php      # Edicao com validacao + duplicidade CNPJ
    AdmsDeleteSupplier.php    # Exclusao com verificacao de existencia
    AdmsViewSupplier.php      # Consulta detalhada por ID
    AdmsListSupplierBrands.php    # Listagem de marcas por fornecedor (legado)
    AdmsAddSupplierBrand.php      # Adicionar marca ao fornecedor (legado)
    AdmsEditSupplierBrand.php     # Editar marca do fornecedor (legado)
    AdmsDeleteSupplierBrand.php   # Excluir marca do fornecedor (legado)

app/adms/Views/supplier/
    loadSupplier.php          # Pagina principal (container + modais)
    listSupplier.php          # Lista AJAX (tabela paginada)
    partials/
        _add_supplier_modal.php       # Modal de criacao
        _edit_supplier_modal.php      # Modal de edicao
        _view_supplier_modal.php      # Modal de visualizacao (wrapper)
        _view_supplier_content.php    # Conteudo do modal de visualizacao
        _delete_supplier_modal.php    # Modal de confirmacao de exclusao

app/cpadms/Models/
    CpAdmsSearchSupplier.php  # Busca avancada (modulo de pesquisa)

assets/js/
    suppliers.js              # JavaScript completo (768 linhas)

tests/Suppliers/
    AdmsSupplierTest.php          # 24 testes (formatacao, limpeza, status)
    AdmsListSuppliersTest.php     # 17 testes (listagem, busca, paginacao)
    AdmsAddSupplierTest.php       # 15 testes (criacao, validacao, duplicidade)
    AdmsEditSupplierTest.php      # 13 testes (load, update, validacao)
    AdmsViewSupplierTest.php      # 12 testes (consulta detalhada)
    AdmsDeleteSupplierTest.php    #  6 testes (exclusao, verificacao)
```

### 2.2 Banco de Dados

#### Tabela Principal: `adms_suppliers`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT (PK, AI) | Identificador unico |
| `corporate_social` | VARCHAR | Razao Social |
| `fantasy_name` | VARCHAR | Nome Fantasia |
| `cnpj_cpf` | VARCHAR | CNPJ ou CPF (apenas numeros) |
| `contact` | VARCHAR | Telefone (apenas numeros) |
| `email` | VARCHAR | E-mail do fornecedor |
| `status_id` | INT (FK) | Situacao (FK para `adms_sits`) |
| `created` | DATETIME | Data de criacao |
| `modified` | DATETIME | Data da ultima alteracao |

#### Tabelas Relacionadas

| Tabela | Relacao | Descricao |
|--------|---------|-----------|
| `adms_sits` | `status_id → adms_sits.id` | Situacoes/status do fornecedor |
| `adms_cors` | `adms_sits.adms_cor_id → adms_cors.id` | Cores dos badges de status |
| `adms_brands_suppliers` | `adms_supplier_id → adms_suppliers.id` | Marcas associadas ao fornecedor |

#### Consumidores Externos

A tabela `adms_suppliers` e referenciada por:
- **OrderPayments** (`AdmsEditOrderPayment`, `AdmsAddOrderPayment`, `AdmsListOrderPayments`, etc.) — fornecedor como destinatario de ordens de pagamento
- **Relatorios** (`AdmsReportOrderPayments`, `CpAdmsCreateSpreadsheetOrderPayments`) — dados do fornecedor em relatorios financeiros
- **Cron** (`cron-installment-alerts.php`) — alertas de parcelas vinculadas a fornecedores

---

## 3. Fluxo de Operacoes

### 3.1 Listagem (GET)

```
Browser → GET /supplier/list/{page}?typesupplier=1
       → Supplier::list() → match(1) → listAllItems()
       → AdmsListSuppliers::list($page)
       → SQL: SELECT + JOIN adms_sits + adms_cors + LIMIT/OFFSET
       → ConfigView::renderList() → listSupplier.php (HTML parcial)
       → Response: HTML da tabela paginada
```

### 3.2 Busca (POST)

```
Browser → POST /supplier/list/{page}?typesupplier=2
       → Supplier::list() → match(2) → searchItems()
       → AdmsListSuppliers::search($filters, $page)
       → WHERE dinamico (corporate_social LIKE / fantasy_name LIKE / cnpj_cpf LIKE + status_id)
       → Response: HTML da tabela filtrada
```

### 3.3 Criacao (POST → JSON)

```
Browser → POST /add-supplier/create (FormData)
       → AddSupplier::create()
       → Validacao no controller (campos obrigatorios + email)
       → AdmsAddSupplier::create()
           → Validacao no model (campos obrigatorios)
           → cleanDocument() + cleanPhone() (remover formatacao)
           → existsByCnpj() (verificar duplicidade)
           → AdmsCreate::exeCreate('adms_suppliers', $data)
           → LoggerService::info('SUPPLIER_CREATED')
       → Response JSON: { success, message, supplier_id, notification }
```

### 3.4 Edicao (GET → JSON + POST → JSON)

```
# Carregar dados
Browser → GET /edit-supplier/edit/{id}
       → EditSupplier::edit($id)
       → AdmsEditSupplier::edit($id) → loadSupplier()
       → Response JSON: { success, data: {...} }

# Atualizar
Browser → POST /edit-supplier/update (FormData)
       → EditSupplier::update()
       → AdmsEditSupplier::update($data)
           → Validacao + cleanDocument/Phone
           → existsByCnpj($cnpj, $excludeId)  ← exclui o proprio registro
           → AdmsUpdate::exeUpdate('adms_suppliers', $data, WHERE id)
           → LoggerService::info('SUPPLIER_UPDATED')
       → Response JSON: { success, message, notification }
```

### 3.5 Exclusao (POST → JSON)

```
Browser → POST /delete-supplier/delete/{id}
       → DeleteSupplier::delete($id)
       → AdmsDeleteSupplier::delete($id)
           → SELECT verificacao de existencia
           → AdmsDelete::exeDelete('adms_suppliers', WHERE id)
           → LoggerService::info('SUPPLIER_DELETED', data completa)
       → Response JSON: { success, message, notification }
```

### 3.6 Visualizacao (GET → HTML)

```
Browser → GET /view-supplier/view/{id}
       → ViewSupplier::view($id)
       → AdmsViewSupplier::viewSupplier($id)
       → ConfigView::renderList() → _view_supplier_content.php
       → Response: HTML parcial (card com dados formatados)
```

---

## 4. Rotas

| Rota | Controller | Metodo | Tipo | Descricao |
|------|-----------|--------|------|-----------|
| `supplier/list/{page}` | Supplier | list | GET/POST | Listagem e busca |
| `supplier/index` | Supplier | index | GET | Redirect para list |
| `add-supplier/create` | AddSupplier | create | POST | Criar fornecedor |
| `edit-supplier/edit/{id}` | EditSupplier | edit | GET | Carregar dados para edicao |
| `edit-supplier/update` | EditSupplier | update | POST | Atualizar fornecedor |
| `delete-supplier/delete/{id}` | DeleteSupplier | delete | POST | Excluir fornecedor |
| `view-supplier/view/{id}` | ViewSupplier | view | GET | Visualizar detalhes |

---

## 5. Campos do Formulario

| Campo | Label | Obrigatorio | Validacao | Mascara JS |
|-------|-------|:-----------:|-----------|:----------:|
| `corporate_social` | Razao Social | Sim | Nao vazio | - |
| `fantasy_name` | Nome Fantasia | Sim | Nao vazio | - |
| `cnpj_cpf` | CNPJ/CPF | Sim | Nao vazio + unicidade | CNPJ/CPF auto |
| `contact` | Contato | Sim | Nao vazio | Telefone |
| `email` | E-mail | Sim | FILTER_VALIDATE_EMAIL | - |
| `status_id` | Situacao | Sim | Nao vazio | - |

### Mascaras de Entrada (JavaScript)

- **CNPJ:** `00.000.000/0000-00` (14 digitos)
- **CPF:** `000.000.000-00` (11 digitos)
- **Telefone fixo:** `(00) 0000-0000` (10 digitos)
- **Telefone celular:** `(00) 00000-0000` (11 digitos)

A mascara CNPJ/CPF e automatica: detecta pelo numero de digitos se e CPF (<=11) ou CNPJ (>11).

---

## 6. Seguranca

### 6.1 Pontos Positivos

| Aspecto | Implementacao |
|---------|---------------|
| **SQL Injection** | Prepared statements via AdmsRead/Create/Update/Delete |
| **XSS** | `htmlspecialchars(ENT_QUOTES, UTF-8)` em todas as views |
| **CSRF** | Token via `csrf_field()` no formulario, removido no controller |
| **Permissoes** | Sistema dinamico via `AdmsBotao::valBotao()` |
| **Validacao dupla** | Controller + Model validam campos obrigatorios |
| **Unicidade** | Verificacao de CNPJ/CPF duplicado no banco |
| **Logging** | LoggerService para CREATE/UPDATE/DELETE com dados contextuais |

### 6.2 Pontos de Atencao

| Item | Detalhe | Severidade |
|------|---------|:----------:|
| **FILTER_SANITIZE_FULL_SPECIAL_CHARS** | Usado no `getSearchData()` do controller principal — pode corromper caracteres acentuados em buscas | Baixa |
| **Exclusao fisica** | Hard delete sem verificacao de dependencias (OrderPayments) | Media |
| **Validacao CNPJ/CPF** | Verifica unicidade mas nao valida digitos verificadores | Baixa |
| **Update retorno false** | `AdmsUpdate::getResult()` retorna false quando 0 rows affected — `updateSupplier()` interpreta como erro | Baixa |

---

## 7. JavaScript (suppliers.js)

### 7.1 Organizacao (768 linhas)

| Secao | Linhas | Descricao |
|-------|:------:|-----------|
| Mascaras de entrada | 1-148 | CNPJ, CPF, telefone com cursor position preservation |
| Formatacao para exibicao | 150-204 | formatCNPJ, formatCPF, formatPhone |
| Listagem AJAX | 207-273 | `listSuppliers()` + ajuste de links de paginacao |
| Busca com filtros | 275-366 | Busca com debounce (500ms), suporte a filtros + paginacao |
| CRUD (Add/Edit/View/Delete) | 368-705 | Formularios async com loading states e retry |
| Notificacoes | 710-768 | `showMessage()` + `renderNotification()` do servidor |

### 7.2 Padroes Utilizados

- **Async/await** para todas as requisicoes AJAX
- **Event delegation** para paginacao e botoes dinamicos
- **Debounce** (500ms) no campo de busca
- **Loading states** em todos os botoes de submit (spinner + disabled)
- **Retry pattern** para modais de edit/view em caso de erro
- **Server-side notifications** via `NotificationService::generateNotificationHtml()`
- **Cursor position preservation** nas mascaras de input

---

## 8. Testes

### 8.1 Resumo

| Arquivo | Testes | Foco |
|---------|:------:|------|
| `AdmsSupplierTest.php` | 24 | Formatacao CNPJ/CPF/telefone, limpeza, status |
| `AdmsListSuppliersTest.php` | 17 | Listagem paginada, busca com filtros |
| `AdmsAddSupplierTest.php` | 15 | Criacao, validacao, duplicidade CNPJ |
| `AdmsEditSupplierTest.php` | 13 | Load, update, validacao, duplicidade excluindo ID |
| `AdmsViewSupplierTest.php` | 12 | Consulta detalhada, formato de retorno |
| `AdmsDeleteSupplierTest.php` | 6 | Exclusao, verificacao de existencia |
| **Total** | **87** | |

### 8.2 Observacoes

- Todos os testes usam `PDO` direto + `SessionContext::setTestData()` para mock de sessao
- Testes dependem de banco de dados real (`mercury_test`) — nao sao puramente unitarios
- Boa cobertura para o model base (formatacao) e operacoes CRUD

---

## 9. Sub-modulo: Marcas de Fornecedor (Supplier Brands)

### 9.1 Descricao

Gerencia a associacao entre fornecedores e marcas via tabela `adms_brands_suppliers`. E um sub-modulo **legado** com estilo de codigo antigo (sem type hints, camelCase em propriedades, PHPDoc minimo).

### 9.2 Tabela: `adms_brands_suppliers`

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| `id` | INT (PK) | Identificador |
| `brand` | VARCHAR | Nome da marca |
| `adms_supplier_id` | INT (FK) | FK para `adms_suppliers.id` |
| `status_id` | INT (FK) | FK para `adms_sits.id` |
| `created` | DATETIME | Data de criacao |

### 9.3 Models

- `AdmsListSupplierBrands` — Listagem com JOIN para nome do fornecedor e status
- `AdmsAddSupplierBrand` — Criacao de associacao marca-fornecedor
- `AdmsEditSupplierBrand` — Edicao da associacao
- `AdmsDeleteSupplierBrand` — Exclusao da associacao

---

## 10. Integracao com Outros Modulos

### 10.1 OrderPayments (Ordens de Pagamento)

O modulo de fornecedores e fortemente integrado com OrderPayments:
- `AdmsAddOrderPayment` / `AdmsEditOrderPayment` — seleciona fornecedor como beneficiario
- `AdmsListOrderPayments` — exibe nome do fornecedor na listagem
- `AdmsReportOrderPayments` — inclui dados do fornecedor nos relatorios
- `CpAdmsSearchOrderPayments` — busca por fornecedor nas ordens de pagamento
- `CpAdmsCreateSpreadsheetOrderPayments` — exporta dados com fornecedor para Excel
- `cron-installment-alerts.php` — alertas de parcelas com dados do fornecedor

### 10.2 Supplier Brands

Sub-modulo de marcas associadas ao fornecedor (detalhado na secao 9).

---

## 11. Conformidade com Padroes Mercury

| Padrao | Status | Observacao |
|--------|:------:|-----------|
| Controllers PascalCase | OK | `Supplier.php`, `AddSupplier.php`, etc. |
| Models com prefixo `Adms` | OK | `AdmsSupplier.php`, `AdmsListSuppliers.php`, etc. |
| Views camelCase | OK | `supplier/loadSupplier.php`, `listSupplier.php` |
| Partials snake_case | OK | `_add_supplier_modal.php`, etc. |
| JS kebab-case | PARCIAL | Arquivo e `suppliers.js` (plural), padrao seria `supplier.js` |
| Match expression | OK | Controller principal usa match |
| Type hints | OK | Todos os metodos publicos tipados |
| PHPDoc | OK | Todos os metodos documentados |
| Prepared statements | OK | Todas as queries parametrizadas |
| XSS prevention | OK | htmlspecialchars em todas as views |
| Permissoes via AdmsBotao | OK | 4 botoes (add/view/edit/delete) |
| LoggerService | OK | CREATE/UPDATE/DELETE logados |
| NotificationService | OK | Todas as operacoes notificam |
| Responsividade | OK | Desktop (btn-group) + Mobile (dropdown) |
| SessionContext | OK | Nenhum acesso direto a `$_SESSION` nos models/controllers |
| Validacao explicita | OK | Nao usa AdmsCampoVazio |

---

## 12. Pontos de Melhoria

### 12.1 Prioridade Alta

| # | Item | Descricao |
|---|------|-----------|
| 1 | **Verificacao de dependencias na exclusao** | `DeleteSupplier` faz hard delete sem verificar se ha OrderPayments vinculadas. Pode quebrar integridade referencial. Implementar soft-delete ou verificacao antes da exclusao. |
| 2 | **AdmsUpdate retorno false** | `AdmsEditSupplier::updateSupplier()` retorna `false` quando 0 rows affected (dados identicos). Deve tratar como sucesso, similar ao padrao do Chat. |

### 12.2 Prioridade Media

| # | Item | Descricao |
|---|------|-----------|
| 3 | **FILTER_SANITIZE_FULL_SPECIAL_CHARS** | No `Supplier::getSearchData()` pode corromper acentos. Substituir por `FILTER_DEFAULT`. |
| 4 | **Codigo duplicado: clean/format** | Metodos `cleanDocument()` e `cleanPhone()` duplicados em `AdmsSupplier` (static), `AdmsAddSupplier` (private) e `AdmsEditSupplier` (private). Unificar usando os metodos static de `AdmsSupplier`. |
| 5 | **Validacao duplicada** | Validacao de campos obrigatorios duplicada no controller e no model. Manter apenas no model e usar `getError()` no controller. |
| 6 | **Validacao de digitos verificadores** | Nao ha validacao de CNPJ/CPF validos (algoritmo Receita Federal). Apenas verifica se esta preenchido e se e unico. |
| 7 | **Falta Statistics Model** | Nao possui `AdmsStatisticsSuppliers` — modulos de referencia (Sales, VacationPeriods) tem cards de estatisticas. |

### 12.3 Prioridade Baixa

| # | Item | Descricao |
|---|------|-----------|
| 8 | **Supplier Brands legado** | Sub-modulo de marcas usa estilo antigo (sem type hints, naming inconsistente). Precisa refatoracao. |
| 9 | **Campos de auditoria incompletos** | Falta `created_by_user_id` e `updated_by_user_id` na tabela. Apenas `created` e `modified` timestamps. |
| 10 | **Comentario na view** | `_view_supplier_content.php` diz "Compativel com Bootstrap 4" mas o projeto usa Bootstrap 5.3. |
| 11 | **Nome do JS** | Arquivo `suppliers.js` (plural) foge do padrao kebab-case singular (`supplier.js`). |
| 12 | **Falta API REST** | Nao possui endpoint API REST (outros modulos como Sales, OrderPayments tem). |
| 13 | **WebSocket notifications** | Nao possui integracao com `SystemNotificationService` para notificacoes em tempo real. |

---

## 13. Metricas

| Metrica | Valor |
|---------|-------|
| Controllers | 5 |
| Models (core) | 6 |
| Models (brands) | 4 |
| Views | 7 |
| JavaScript | 1 arquivo (768 linhas) |
| Search Model | 1 |
| Testes | 87 (6 arquivos, 2.048 linhas) |
| Tabelas | 2 (adms_suppliers + adms_brands_suppliers) |
| Integracao | OrderPayments, Cron alerts |
| Rotas | 7 |

---

## 14. Diagrama de Dependencias

```
                    ┌─────────────────┐
                    │  adms_suppliers  │
                    └────────┬────────┘
                             │
            ┌────────────────┼────────────────────┐
            │                │                     │
            ▼                ▼                     ▼
   ┌─────────────┐  ┌──────────────────┐  ┌──────────────┐
   │  adms_sits   │  │ adms_brands_     │  │ OrderPayments│
   │  (status)    │  │ suppliers        │  │ (financeiro) │
   └──────┬──────┘  └──────────────────┘  └──────────────┘
          │
          ▼
   ┌─────────────┐
   │  adms_cors   │
   │  (cores)     │
   └─────────────┘
```

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
**Versao:** 1.0
**Data:** 04/04/2026
