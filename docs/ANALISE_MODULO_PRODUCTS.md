# Analise do Modulo Products (Cadastro de Produtos)

**Data:** 03 de Marco de 2026
**Versao:** 1.3
**Status:** Fases 1-7 Completas

---

## 1. Visao Geral

O modulo Products e responsavel pelo cadastro e gerenciamento de produtos via importacao do ERP CIGAM (PostgreSQL) para o banco local MySQL. O cadastro nao e manual — todos os dados sao sincronizados a partir de views do CIGAM.

### 1.1 Arquitetura

```
CIGAM (PostgreSQL)                    Mercury (MySQL)
+-----------------------+             +---------------------------+
| msl_produtos_         | ──sync──>  | adms_products             |
| msl_prod_valor_       | ──sync──>  | (sale_price, cost_price)  |
| msl_dprodutos_        |             | adms_product_variants     |
+-----------------------+             +---------------------------+
| msl_prod_categoria_   | ──sync──>  | adms_prod_categories      |
| msl_prod_colecao_     | ──sync──>  | adms_prod_collections     |
| msl_prod_subcolecao_  | ──sync──>  | adms_prod_subcollections  |
| msl_prod_cor_         | ──sync──>  | adms_prod_colors          |
| msl_prod_marca_       | ──sync──>  | adms_prod_brands          |
| msl_prod_material_    | ──sync──>  | adms_prod_materials       |
| msl_prod_tamanho_     | ──sync──>  | adms_prod_sizes           |
| msl_prod_compartigo_  | ──sync──>  | adms_prod_article_compl.  |
| msl_dfornecedor_      | ──sync──>  | adms_suppliers            |
+-----------------------+             +---------------------------+
```

### 1.2 Estrutura de Arquivos

```
app/adms/Controllers/
  ├── Products.php                    # Listagem, busca, estatisticas, downloadSyncRejected
  ├── ViewProduct.php                 # Visualizacao detalhada (AJAX modal)
  ├── EditProduct.php                 # Edicao de produto + variantes (AJAX modal)
  ├── ImportProductPrices.php         # Importacao de precos via CSV (upload, process, download, history)
  └── SynchronizeProducts.php         # Sincronizacao chunked (AJAX 4 fases)

app/adms/Models/
  ├── AdmsListProducts.php            # Listagem com paginacao
  ├── AdmsViewProduct.php             # Produto + variantes (JOINs)
  ├── AdmsEditProduct.php             # Edicao + variantes + EAN-13 auto
  ├── AdmsStatisticsProducts.php      # Metricas do catalogo
  ├── AdmsImportProductPrices.php     # Engine de importacao de precos via CSV (rejected CSV + DB log)
  └── AdmsSynchronizeProducts.php     # Engine de sincronizacao (rejected CSV em sync precos)

app/adms/Views/products/
  ├── loadProducts.php                # Pagina principal (load)
  ├── listProducts.php                # Tabela de produtos (AJAX)
  └── partials/
      ├── _view_product_modal.php     # Modal de detalhes
      ├── _edit_product_modal.php     # Modal de edicao (produto + variantes)
      ├── _sync_products_modal.php    # Modal de sincronizacao (+ download rejeitados)
      ├── _import_product_prices_modal.php  # Modal de importacao de precos via CSV
      ├── _operation_history_modal.php      # Modal de historico de operacoes (import + sync)
      ├── _label_preset_modal.php     # Modal de impressao de etiquetas
      └── _statistics_dashboard.php   # Cards de estatisticas

app/adms/Services/
  └── Ean13Generator.php              # Geracao de codigos EAN-13

app/cpadms/Models/
  └── CpAdmsSearchProducts.php        # Busca com filtros multiplos

assets/js/
  ├── products.js                     # Listagem, busca, modais, import precos, historico
  └── products-sync.js                # Orquestracao da sincronizacao (+ download rejeitados)

database/migrations/
  └── 2026_03_03_import_product_prices_history.sql  # Migration: tabela import_logs + rejected_file + rotas

tests/Products/                       # 11 arquivos
  ├── ProductsControllerTest.php
  ├── ViewProductControllerTest.php
  ├── EditProductControllerTest.php   # Controller de edicao
  ├── SynchronizeProductsControllerTest.php
  ├── AdmsListProductsTest.php
  ├── AdmsStatisticsProductsTest.php
  ├── AdmsViewProductTest.php
  ├── AdmsEditProductTest.php         # Model de edicao + variantes
  ├── AdmsSynchronizeProductsTest.php
  ├── CpAdmsSearchProductsTest.php
  └── Ean13GeneratorTest.php
```

---

## 2. Banco de Dados

### 2.1 Tabelas Principais

#### `adms_products` (1 registro por referencia)

| Campo | Tipo | Descricao |
|-------|------|-----------|
| id | INT PK AUTO_INCREMENT | |
| reference | VARCHAR(50) UNIQUE | Referencia do produto |
| description | VARCHAR(500) | Descricao |
| brand_cigam_code | VARCHAR(20) | FK → adms_prod_brands |
| collection_cigam_code | VARCHAR(20) | FK → adms_prod_collections (estacao) |
| subcollection_cigam_code | VARCHAR(20) | FK → adms_prod_subcollections |
| category_cigam_code | VARCHAR(20) | FK → adms_prod_categories |
| color_cigam_code | VARCHAR(20) | FK → adms_prod_colors |
| material_cigam_code | VARCHAR(20) | FK → adms_prod_materials |
| article_cigam_code | VARCHAR(20) | Codigo do artigo (linha) |
| article_complement_cigam_code | VARCHAR(20) | FK → adms_prod_article_complements |
| supplier | VARCHAR(255) | FK → msl_dfornecedor_.codigo_for |
| sale_price | DECIMAL(10,2) | Preco de venda |
| cost_price | DECIMAL(10,2) | Preco de custo |
| cigam_created_at | DATE | Data cadastro no CIGAM |
| cigam_updated_at | DATE | Data atualizacao no CIGAM |
| is_active | TINYINT(1) DEFAULT 1 | |
| synced_at | DATETIME | Ultima sincronizacao |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### `adms_product_variants` (1 registro por barcode)

| Campo | Tipo | Descricao |
|-------|------|-----------|
| id | INT PK AUTO_INCREMENT | |
| product_id | INT NOT NULL | FK → adms_products.id (CASCADE) |
| barcode | VARCHAR(50) | Codigo de barras |
| aux_reference | VARCHAR(100) | Referencia auxiliar |
| size_cigam_code | VARCHAR(20) | FK → adms_prod_sizes |
| is_active | TINYINT(1) DEFAULT 1 | |
| synced_at | DATETIME | |
| created_at | DATETIME | |
| updated_at | DATETIME | |

**Indices:** UNIQUE(product_id, barcode), INDEX(product_id), INDEX(barcode)

#### `adms_prod_sync_logs` (auditoria de sincronizacao)

| Campo | Tipo | Descricao |
|-------|------|-----------|
| id | INT PK AUTO_INCREMENT | |
| sync_type | ENUM | full, incremental, lookups_only, by_period, prices_only |
| status | ENUM | started, in_progress, completed, failed, partial, cancelled |
| total_records | INT | Total de produtos a sincronizar |
| processed_count | INT | Processados (cumulativo) |
| inserted_count | INT | Inseridos (cumulativo) |
| updated_count | INT | Atualizados (cumulativo) |
| error_count | INT | Erros (cumulativo) |
| last_offset | INT | Offset para processamento chunked |
| sync_from_date / sync_to_date | DATE | Periodo (quando aplicavel) |
| lookups_synced | TINYINT | Flag: lookups sincronizados |
| products_synced | TINYINT | Flag: produtos sincronizados |
| prices_synced | TINYINT | Flag: precos sincronizados |
| error_details | TEXT | JSON com detalhes de erros |
| rejected_file | VARCHAR(500) | Caminho do CSV de referencias rejeitadas (Fase 7) |
| duration_seconds | INT | Duracao total |
| created_by_user_id | INT | Usuario que iniciou |
| started_at / completed_at | DATETIME | Timestamps |

#### `adms_prod_import_logs` (auditoria de importacao de precos — Fase 7)

| Campo | Tipo | Descricao |
|-------|------|-----------|
| id | INT PK AUTO_INCREMENT | |
| import_type | VARCHAR(30) DEFAULT 'price_import' | Tipo de importacao |
| file_name | VARCHAR(255) | Nome do arquivo CSV importado |
| status | ENUM | started, processing, completed, error |
| total_rows | INT | Total de linhas no CSV |
| processed_rows | INT | Linhas processadas |
| success_count | INT | Precos atualizados com sucesso |
| unchanged_count | INT | Precos iguais (sem alteracao) |
| skipped_count | INT | Linhas ignoradas (formato invalido) |
| not_found_count | INT | Referencias nao encontradas |
| error_count | INT | Erros de processamento |
| rejected_file | VARCHAR(500) | Caminho do CSV de referencias rejeitadas |
| duration_seconds | INT | Duracao total em segundos |
| created_by_user_id | INT | Usuario que iniciou |
| started_at | DATETIME | Inicio da importacao |
| completed_at | DATETIME | Fim da importacao |
| created_at | DATETIME | Timestamp de criacao |

### 2.2 Tabelas de Lookup (8 tabelas)

Todas seguem o mesmo padrao:

| Campo | Tipo | Descricao |
|-------|------|-----------|
| id | INT PK AUTO_INCREMENT | |
| cigam_code | VARCHAR(20) UNIQUE | Codigo no CIGAM |
| name | VARCHAR(255) | Nome/descricao |
| is_active | TINYINT(1) DEFAULT 1 | |
| created_at / updated_at | DATETIME | |

| Tabela MySQL | View CIGAM | Conteudo |
|-------------|------------|----------|
| adms_prod_categories | msl_prod_categoria_ | Categorias |
| adms_prod_collections | msl_prod_colecao_ | Colecoes (estacoes) |
| adms_prod_subcollections | msl_prod_subcolecao_ | Subcolecoes |
| adms_prod_colors | msl_prod_cor_ | Cores |
| adms_prod_brands | msl_prod_marca_ | Marcas |
| adms_prod_materials | msl_prod_material_ | Materiais |
| adms_prod_sizes | msl_prod_tamanho_ | Tamanhos |
| adms_prod_article_complements | msl_prod_compartigo_ | Complementos de artigo |

### 2.3 Views CIGAM (PostgreSQL - Fonte de Dados)

| View | Uso | Campos Principais |
|------|-----|------------------|
| msl_produtos_ | Produtos + variantes | referencia, descricao, codbarra, codtamanho, cod* (lookups) |
| msl_dprodutos_ | Produtos agregados | referencia, descricao, colecao, marca, min_vlrvenda, min_vlrcusto |
| msl_prod_valor_ | Precos | referencia, min_vlrvenda, min_vlrcusto |
| msl_prod_categoria_ | Lookup | codcategoria, dsccategoria |
| msl_prod_colecao_ | Lookup | codcolecao, dsccolecao |
| msl_prod_subcolecao_ | Lookup | codsubcolecao, codcolecao, dscsubcolecao |
| msl_prod_cor_ | Lookup | codcor, dsccor |
| msl_prod_marca_ | Lookup | codmarca, dscmarca |
| msl_prod_material_ | Lookup | codmaterial, dscmaterial |
| msl_prod_tamanho_ | Lookup | cod_tamanho, descricao |
| msl_prod_compartigo_ | Lookup | codcompartigo, dsccompartigo |
| msl_dfornecedor_ | Fornecedores | codigo_for, cnpj, razao_social, nome_fantasia |

---

## 3. Sincronizacao (Engine)

### 3.1 Arquitetura Chunked AJAX

A sincronizacao usa uma arquitetura de 4 fases para evitar timeouts:

```
Frontend (products-sync.js)           Backend (SynchronizeProducts controller)
┌────────────────────┐                ┌────────────────────┐
│ 1. startSync()     │ ──POST──>      │ handleInitSync()   │
│                    │ <──JSON──      │  - cria sync_log   │
│                    │                │  - sync lookups    │
│                    │                │  - conta total     │
├────────────────────┤                ├────────────────────┤
│ 2. processChunk(0) │ ──POST──>      │ handleProcessChunk │
│    processChunk(1) │ ──POST──>      │  - fetch 1000      │
│    processChunk(N) │ ──POST──>      │  - upsert products │
│    (loop ate       │ <──JSON──      │  - upsert variants │
│     hasMore=false) │                │  - update progress │
├────────────────────┤                ├────────────────────┤
│ 3. (precos separ.) │                │ syncPricesOnly()   │
├────────────────────┤                ├────────────────────┤
│ 4. finalizeSync()  │ ──POST──>      │ handleFinalizeSync │
│                    │ <──JSON──      │  - le contadores   │
│                    │                │  - fecha log       │
└────────────────────┘                └────────────────────┘
```

### 3.2 Tipos de Sincronizacao

| Tipo | Descricao | Trigger |
|------|-----------|---------|
| sync_all | Full ou incremental (auto-detecta) | Botao "Sincronizar" |
| by_period | Periodo especifico (data inicio/fim) | Radio "Por Periodo" |
| lookups_only | Apenas tabelas auxiliares | Radio "Tabelas Auxiliares" |
| prices_only | Apenas precos (request unico) | Radio "Atualizar Precos" |
| incremental | Desde ultima sync bem-sucedida | Automatico (se houver sync anterior) |
| full | Catalogo completo | Automatico (primeira sync) |

### 3.3 Constantes

- `CHUNK_SIZE = 1000` — produtos por requisicao AJAX
- `CSRF Token` — renovado em cada fase via `CsrfService::refresh()`
- `set_time_limit(300)` — 5 minutos por fase

---

## 4. Funcionalidades Implementadas

### 4.1 Catalogo de Produtos

- **Listagem** com paginacao (50 por pagina)
- **Busca** por referencia, descricao, marca, colecao, categoria, estacao
- **Estatisticas** — total de produtos, marcas ativas, faixa de precos, ultima sync
- **Visualizacao detalhada** — modal com produto + todas as variantes (tamanhos, barcodes)
- **Edicao de produto** — modal AJAX com campos do produto + gerenciamento de variantes
- **Imagens** — carregadas via URL externa com fallback

### 4.4 Edicao de Produto (EditProduct)

- **Campos editaveis:** descricao, precos (venda/custo), marca, estacao, colecao, tipo, cor, material, grupo
- **Campos protegidos:** referencia, fornecedor (somente leitura, vindos do CIGAM)
- **Sync lock automatico:** ao salvar, `sync_locked = 1` e aplicado automaticamente
- **Gerenciamento de variantes inline:**
  - Editar tamanho (select) e codigo de barras de variantes existentes
  - Adicionar novas variantes com tamanho + barcode
  - Remover variantes (soft delete: `is_active = 0`)
- **EAN-13 automatico:** `aux_reference` gerado via `Ean13Generator` ao salvar
  - Variantes existentes sem `aux_reference`: gera automaticamente
  - Variantes novas: gera apos INSERT usando `lastInsertId`
  - Variantes com `aux_reference` preenchido: preserva valor original
  - Botao de regeneracao para limpar e gerar novo codigo

### 4.5 Importacao de Precos via Arquivo (ImportProductPrices — Fase 6)

- **Upload CSV** com colunas: referencia, preco_venda, preco_custo (delimitador `;`)
- **Processamento chunked** em background (`session_write_close` + JSON progress file)
- **Progresso em tempo real** com polling AJAX (barra de progresso + contadores)
- **Validacao:** formato numerico, referencia existente no banco, preco positivo
- **Cancelamento** durante processamento via flag no arquivo de progresso
- **Resultado detalhado:** atualizados, inalterados, ignorados, nao encontrados, erros
- **CSV de rejeitados:** gera CSV com BOM UTF-8 das referencias com problemas (Fase 7)
- **Log persistente:** cada importacao registrada em `adms_prod_import_logs` (Fase 7)

### 4.6 CSV de Referencias Rejeitadas + Historico de Operacoes (Fase 7)

- **CSV de rejeitados na importacao:** gera `precos_rejeitados_*.csv` em `uploads/import_errors/`
  - Colunas: LINHA, REFERENCIA, MOTIVO, PRECO_VENDA, PRECO_CUSTO
  - BOM UTF-8 para compatibilidade Excel, delimitador `;`
  - Link de download no resultado da importacao
- **CSV de rejeitados na sincronizacao:** gera `sync_precos_rejeitados_*.csv`
  - Colunas: REFERENCIA, MOTIVO
  - Captura: produtos nao encontrados, produtos bloqueados (sync_locked)
  - Link de download no resultado da sincronizacao
- **Log persistente de importacoes:** tabela `adms_prod_import_logs`
  - Contadores: total, success, unchanged, skipped, not_found, error
  - Path do CSV rejeitado, duracao, usuario
- **Modal de historico de operacoes:** unifica importacoes + sincronizacoes
  - Query UNION ALL entre `adms_prod_import_logs` e `adms_prod_sync_logs`
  - Badges de status (completed=verde, error=vermelho, partial=amarelo)
  - Badges de tipo (import=info, sync=warning)
  - Download de CSVs rejeitados diretamente do historico
  - Informacoes: data, tipo, subtipo, arquivo, contadores, duracao, usuario
- **Download seguro:** validacao de path traversal (`str_starts_with('uploads/import_errors/')`)

### 4.2 Sincronizacao

- **Produtos** — upsert por referencia (INSERT ON DUPLICATE KEY UPDATE)
- **Variantes** — upsert por product_id + barcode
- **8 tabelas de lookup** — sync em batch
- **Fornecedores** — sync da tabela msl_dfornecedor_
- **Precos** — sync separado (sale_price + cost_price)
- **Log de auditoria** — cada sync registrado com contadores e duracao
- **Cancelamento** — sync pode ser cancelado durante execucao
- **Progresso** — barra de progresso em tempo real

### 4.3 EAN-13

- **Geracao** de codigos de barras internos (prefixo 2)
- **Validacao** de checksum GS1 Modulo 10
- **Decomposicao** em componentes (product_id, variant_id)
- Formato: `2PPPPPPVVVVVC` (P=produto, V=variante, C=checksum)

---

## 5. Rotas Registradas

| Rota | Controller/Metodo | Tipo |
|------|-------------------|------|
| products/list | Products/list | Pagina |
| products/index | Products/index | AJAX |
| products/statistics | Products/statistics | AJAX |
| view-product/view | ViewProduct/view | AJAX |
| edit-product/edit | EditProduct/edit | AJAX |
| edit-product/update | EditProduct/update | AJAX POST |
| synchronize-products/sync-all | SynchronizeProducts/syncAll | AJAX |
| synchronize-products/sync-by-period | SynchronizeProducts/syncByPeriod | AJAX |
| synchronize-products/sync-lookups | SynchronizeProducts/syncLookups | AJAX |
| synchronize-products/sync-prices | SynchronizeProducts/syncPrices | AJAX |
| synchronize-products/cancel | SynchronizeProducts/cancel | AJAX |
| synchronize-products/get-progress | SynchronizeProducts/getProgress | AJAX |
| synchronize-products/get-last-sync | SynchronizeProducts/getLastSync | AJAX |
| import-product-prices/upload | ImportProductPrices/upload | AJAX POST |
| import-product-prices/process | ImportProductPrices/process | AJAX POST |
| import-product-prices/progress | ImportProductPrices/progress | AJAX |
| import-product-prices/dismiss | ImportProductPrices/dismiss | AJAX POST |
| import-product-prices/cancel | ImportProductPrices/cancel | AJAX POST |
| import-product-prices/download-rejected | ImportProductPrices/downloadRejected | GET (download) |
| import-product-prices/operation-history | ImportProductPrices/operationHistory | AJAX POST |
| products/download-sync-rejected | Products/downloadSyncRejected | GET (download) |

---

## 6. Testes Automatizados

**11 arquivos de teste**

| Arquivo | Testes | Tipo |
|---------|--------|------|
| ProductsControllerTest | 13 | Estrutura + Reflexao |
| ViewProductControllerTest | 8 | Estrutura + Output |
| EditProductControllerTest | 13 | Estrutura + Reflexao + Output |
| SynchronizeProductsControllerTest | 11 | Estrutura + CsrfService |
| AdmsListProductsTest | 8 | Integracao DB |
| AdmsStatisticsProductsTest | 9 | Integracao DB |
| AdmsViewProductTest | 8 | Integracao DB |
| AdmsEditProductTest | 27 | Integracao DB + Variantes + EAN-13 |
| AdmsSynchronizeProductsTest | 18 | Integracao DB + Validacao |
| CpAdmsSearchProductsTest | 12 | Integracao DB |
| Ean13GeneratorTest | 26 | Unitario puro |

---

## 7. Sanitizacao de Dados

A sincronizacao aplica sanitizacao automatica:

- **Colecoes:** Remove prefixo antes de ` - ` ou `-`, remove sufixo `/`
  - Exemplo: `"001 - VERAO/2025"` → `"VERAO"`
- **Subcolecoes:** Mesma logica de limpeza
- **Todos os nomes:** Convertidos para UPPERCASE
- **Codigos CIGAM:** Trim de espacos

---

## 8. Decisoes Arquiteturais

| Decisao | Justificativa |
|---------|---------------|
| Sync chunked (AJAX) | Evita timeout em catalogos grandes (86k+ registros) |
| Precos separados | Query de precos no CIGAM e lenta (2.7M+ rows em msl_prod_valor_) |
| 1 produto = 1 referencia | Normalizacao: variantes em tabela separada |
| EAN-13 com prefixo 2 | Padrao GS1 para codigos internos de loja |
| EAN-13 auto em variantes | aux_reference gerado automaticamente ao salvar se vazio |
| Edicao com sync_locked | Produto editado manualmente nao e sobrescrito pelo CIGAM |
| Variantes soft delete | Remocao via is_active=0, preserva historico |
| CSRF refresh por fase | Token expira em 1h, syncs podem ser longos |
| Lookup batch upsert | INSERT ON DUPLICATE KEY UPDATE para eficiencia |
| Import precos via CSV | Upload + processamento em background com progress polling |
| session_write_close() | Libera sessao durante processamento pesado para nao bloquear polling |
| CSV rejeitados com BOM | BOM UTF-8 (\xEF\xBB\xBF) + delimitador `;` para Excel PT-BR |
| Log persistente imports | `adms_prod_import_logs` — importacoes nao tinham log antes |
| UNION ALL historico | Query combinando import_logs + sync_logs com collation explicita |
| Collation explicita | `utf8mb4_unicode_ci` obrigatorio — MySQL 8 default e `0900_ai_ci` (incompativel em UNION) |
| Path traversal protection | Validacao `str_starts_with('uploads/import_errors/')` no download |

---

## 9. Proximas Fases (Pendente)

Ver documento: `docs/PLANO_ACAO_PRODUCTS.md`

| Fase | Descricao | Status |
|------|-----------|--------|
| Fase 1 | Cadastro base (sync + catalogo + lookups) | Completa |
| Fase 2 | Historico de precos | Completa |
| Fase 3 | Historico de colecoes/estacoes | Completa |
| Fase 4 | Produtos em promocao | Completa |
| Fase 5 | Edicao de produto + variantes + EAN-13 | Completa |
| Fase 6 | Importacao de precos via arquivo CSV | Completa |
| Fase 7 | CSV de rejeitados + Historico de operacoes | Completa |

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
**Ultima Atualizacao:** 03/03/2026
