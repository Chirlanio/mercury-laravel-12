# Plano de Acao - Modulo Products

**Data:** 03 de Marco de 2026
**Versao:** 1.3
**Documento de Referencia:** `docs/ANALISE_MODULO_PRODUCTS.md`

---

## Status Geral

| Fase | Descricao | Status | Progresso |
|------|-----------|--------|-----------|
| 1 | Cadastro base (sync + catalogo + EAN-13) | Completa | 100% |
| 2 | Historico de precos (venda + custo) | Completa | 100% |
| 3 | Historico de colecoes e estacoes | Completa | 100% |
| 4 | Produtos em promocao | Completa | 100% |
| 5 | Edicao de produto + variantes | Completa | 100% |
| 6 | Importacao de precos via arquivo CSV | Completa | 100% |
| 7 | CSV de rejeitados + Historico de operacoes | Completa | 100% |

---

## Fase 1 — Cadastro Base (COMPLETA)

### Entregaveis Concluidos

- [x] Estrutura de tabelas MySQL (adms_products, adms_product_variants, 8 lookups, sync_logs)
- [x] Normalizacao: 1 produto por referencia, variantes separadas
- [x] Sincronizacao chunked AJAX (4 fases: init, chunks, prices, finalize)
- [x] 6 tipos de sync (full, incremental, by_period, lookups_only, prices_only, manual)
- [x] Catalogo: listagem, busca com 5 filtros, paginacao
- [x] Estatisticas: total produtos, marcas, faixa de precos, ultima sync
- [x] Visualizacao detalhada: produto + variantes em modal
- [x] Geracao EAN-13 (codigos de barras internos)
- [x] CSRF refresh por fase (evita expiracao em syncs longos)
- [x] 121 testes automatizados
- [x] Sync de precos separado do sync de produtos

### Commits Relacionados

| Commit | Descricao |
|--------|-----------|
| d27aeb4b | Sync chunked AJAX (substituiu monolitico) |
| 887132e7 | Filtros: colecao distinct + cor → estacao |
| a6f9be6b | Separacao do sync de precos |
| b9388e5c | Catalogo: listagem, estatisticas, modal view |
| e41715e3 | Geracao EAN-13 para controle de pedidos |
| 99c50d0c | Fix CSRF token refresh por fase |
| 53653b62 | 121 testes automatizados |

---

## Fase 2 — Historico de Precos (PENDENTE)

### Objetivo

Registrar automaticamente cada alteracao de preco (venda e custo) durante a sincronizacao, mantendo historico completo para analise e consulta.

### 2.1 Tabela: `adms_product_price_history`

```sql
CREATE TABLE adms_product_price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    price_type ENUM('sale', 'cost') NOT NULL,
    old_price DECIMAL(10,2) NULL,
    new_price DECIMAL(10,2) NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sync_log_id INT NULL,
    created_by_user_id INT NULL,

    INDEX idx_product_id (product_id),
    INDEX idx_price_type (price_type),
    INDEX idx_changed_at (changed_at),
    INDEX idx_product_type_date (product_id, price_type, changed_at),
    CONSTRAINT fk_price_history_product
        FOREIGN KEY (product_id) REFERENCES adms_products(id) ON DELETE CASCADE,
    CONSTRAINT fk_price_history_sync_log
        FOREIGN KEY (sync_log_id) REFERENCES adms_prod_sync_logs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Arquivos a Criar/Modificar

| Arquivo | Acao | Descricao |
|---------|------|-----------|
| `docs/sql/create_price_history.sql` | Criar | Script SQL da tabela |
| `app/adms/Models/AdmsSynchronizeProducts.php` | Modificar | Detectar mudanca de preco e registrar historico |
| `app/adms/Models/AdmsViewProduct.php` | Modificar | Incluir historico de precos na visualizacao |
| `app/adms/Views/products/partials/_view_product_modal.php` | Modificar | Exibir timeline de precos |
| `tests/Products/AdmsSynchronizeProductsTest.php` | Modificar | Testes do historico |

### 2.3 Logica de Implementacao

**No `AdmsSynchronizeProducts.php` — metodo `updatePrices()`:**

```
Para cada produto com preco atualizado:
  1. Buscar preco atual (SELECT sale_price, cost_price FROM adms_products WHERE reference = :ref)
  2. Se sale_price mudou → INSERT em adms_product_price_history (type='sale', old, new)
  3. Se cost_price mudou → INSERT em adms_product_price_history (type='cost', old, new)
  4. UPDATE adms_products SET sale_price, cost_price (ja existente)
```

**No `AdmsViewProduct.php`:**

```
Adicionar query:
  SELECT price_type, old_price, new_price, changed_at
  FROM adms_product_price_history
  WHERE product_id = :id
  ORDER BY changed_at DESC
  LIMIT 20
```

### 2.4 Exibicao na View

- Timeline de precos no modal de visualizacao do produto
- Icone de seta (verde para reducao, vermelho para aumento)
- Formato: `R$ 89,90 → R$ 79,90` com data

### 2.5 Ordem de Execucao

1. Criar e executar SQL da tabela
2. Modificar `updatePrices()` para detectar mudancas e registrar
3. Modificar `AdmsViewProduct` para buscar historico
4. Modificar modal para exibir timeline
5. Atualizar testes
6. Testar sync completo (verificar se historico e populado)

---

## Fase 3 — Historico de Colecoes e Estacoes (PENDENTE)

### Objetivo

Registrar quando um produto muda de colecao ou estacao durante a sincronizacao, permitindo rastrear movimentacao entre colecoes.

### 3.1 Tabela: `adms_product_collection_history`

```sql
CREATE TABLE adms_product_collection_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    field_changed ENUM('collection', 'subcollection') NOT NULL,
    old_cigam_code VARCHAR(20) NULL,
    old_name VARCHAR(255) NULL,
    new_cigam_code VARCHAR(20) NOT NULL,
    new_name VARCHAR(255) NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sync_log_id INT NULL,

    INDEX idx_product_id (product_id),
    INDEX idx_field_changed (field_changed),
    INDEX idx_changed_at (changed_at),
    CONSTRAINT fk_collection_history_product
        FOREIGN KEY (product_id) REFERENCES adms_products(id) ON DELETE CASCADE,
    CONSTRAINT fk_collection_history_sync_log
        FOREIGN KEY (sync_log_id) REFERENCES adms_prod_sync_logs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Arquivos a Criar/Modificar

| Arquivo | Acao | Descricao |
|---------|------|-----------|
| `docs/sql/create_collection_history.sql` | Criar | Script SQL da tabela |
| `app/adms/Models/AdmsSynchronizeProducts.php` | Modificar | Detectar mudanca de colecao/subcolecao no upsert |
| `app/adms/Models/AdmsViewProduct.php` | Modificar | Incluir historico na visualizacao |
| `app/adms/Views/products/partials/_view_product_modal.php` | Modificar | Exibir historico de colecoes |
| `tests/Products/AdmsSynchronizeProductsTest.php` | Modificar | Testes do historico |

### 3.3 Logica de Implementacao

**No `AdmsSynchronizeProducts.php` — metodo `upsertProducts()`:**

```
Para cada produto sendo atualizado (UPDATE, nao INSERT):
  1. Buscar collection_cigam_code e subcollection_cigam_code atuais
  2. Se collection mudou → INSERT em adms_product_collection_history (field='collection')
  3. Se subcollection mudou → INSERT em adms_product_collection_history (field='subcollection')
  4. Prosseguir com UPDATE normal
```

### 3.4 Ordem de Execucao

1. Criar e executar SQL da tabela
2. Modificar `upsertProducts()` para detectar mudancas de colecao
3. Modificar `AdmsViewProduct` para buscar historico
4. Modificar modal para exibir timeline de colecoes
5. Atualizar testes
6. Testar sync (verificar registro de mudancas)

---

## Fase 4 — Produtos em Promocao (COMPLETA)

### Objetivo

Modulo para gerenciar produtos em promocao. Diferente das fases 2 e 3, este modulo requer cadastro manual (nao vem do CIGAM). Permite marcar produtos com preco promocional, periodo de vigencia e visualizacao em lista dedicada.

### 4.1 Tabela: `adms_product_promotions`

```sql
CREATE TABLE adms_product_promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    promotion_name VARCHAR(255) NOT NULL,
    original_price DECIMAL(10,2) NOT NULL,
    promotional_price DECIMAL(10,2) NOT NULL,
    discount_percent DECIMAL(5,2) GENERATED ALWAYS AS (
        ROUND(((original_price - promotional_price) / original_price) * 100, 2)
    ) STORED,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT NULL,
    created_by_user_id INT NOT NULL,
    updated_by_user_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_product_id (product_id),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_active (is_active),
    INDEX idx_active_dates (is_active, start_date, end_date),
    CONSTRAINT fk_promotion_product
        FOREIGN KEY (product_id) REFERENCES adms_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 Estrutura de Arquivos (Modulo Novo)

Seguindo padroes do projeto (REGRAS_DESENVOLVIMENTO.md):

```
app/adms/Controllers/
  ├── ProductPromotions.php               # Listagem + busca
  ├── AddProductPromotion.php             # Cadastro
  ├── EditProductPromotion.php            # Edicao
  └── DeleteProductPromotion.php          # Exclusao

app/adms/Models/
  ├── AdmsListProductPromotions.php       # Listagem com paginacao
  ├── AdmsAddProductPromotion.php         # INSERT
  ├── AdmsEditProductPromotion.php        # UPDATE
  ├── AdmsDeleteProductPromotion.php      # DELETE (soft)
  ├── AdmsViewProductPromotion.php        # Visualizacao
  └── AdmsStatisticsProductPromotions.php # Estatisticas

app/adms/Views/productPromotions/
  ├── loadProductPromotions.php           # Pagina principal
  ├── listProductPromotions.php           # Tabela (AJAX)
  └── partials/
      ├── _add_product_promotion_modal.php
      ├── _edit_product_promotion_modal.php
      ├── _view_product_promotion_modal.php
      ├── _delete_product_promotion_modal.php
      └── _statistics_dashboard.php

app/cpadms/Models/
  └── CpAdmsSearchProductPromotions.php   # Busca com filtros

assets/js/
  └── product-promotions.js               # Frontend

tests/ProductPromotions/                  # Testes
```

### 4.3 Funcionalidades

- **Listagem** de promocoes com filtros (produto, periodo, status ativo/expirado)
- **Cadastro** — selecionar produto, definir preco promocional e periodo
- **Edicao** — alterar preco, periodo, ativar/desativar
- **Exclusao** — soft delete
- **Estatisticas** — total ativas, desconto medio, proximas a expirar
- **Indicador na listagem de produtos** — badge "Em promocao" quando aplicavel

### 4.4 Integracao com Catalogo

No `AdmsListProducts.php`, adicionar LEFT JOIN para indicar produtos em promocao:

```sql
SELECT p.*,
    CASE WHEN pp.id IS NOT NULL THEN 1 ELSE 0 END AS has_promotion,
    pp.promotional_price, pp.discount_percent
FROM adms_products p
LEFT JOIN adms_product_promotions pp
    ON pp.product_id = p.id
    AND pp.is_active = 1
    AND CURDATE() BETWEEN pp.start_date AND pp.end_date
```

### 4.5 Rotas Necessarias

| Rota | Controller/Metodo |
|------|-------------------|
| product-promotions/list | ProductPromotions/list |
| product-promotions/index | ProductPromotions/index |
| add-product-promotion/create | AddProductPromotion/create |
| edit-product-promotion/edit | EditProductPromotion/edit |
| delete-product-promotion/delete | DeleteProductPromotion/delete |
| product-promotions/statistics | ProductPromotions/statistics |

### 4.6 Entregaveis Concluidos

- [x] Tabela `adms_product_promotions` com coluna calculada `discount_percent`
- [x] Rotas e permissoes registradas (9 rotas)
- [x] Controllers: ProductPromotions, AddProductPromotion, EditProductPromotion, DeleteProductPromotion
- [x] Models: List, Add, Edit, Delete, View, Statistics + CpAdmsSearch (7 arquivos)
- [x] Views: loadProductPromotions, listProductPromotions + 5 partials + edit form (8 arquivos)
- [x] JavaScript: product-promotions.js (AJAX CRUD completo, autocomplete, money mask, discount calc)
- [x] Integracao: badge "Promo" na listagem de produtos (AdmsListProducts, CpAdmsSearchProducts, listProducts view)
- [x] 97 testes unitarios (152 assertions)
- [x] Validacoes: preco promo < original, datas validas, overlap de periodos
- [x] Soft delete (is_active=0)
- [x] LoggerService + NotificationService em todas as operacoes CRUD
- [x] Busca com filtros: texto, status (ativa/expirada/agendada), periodo
- [x] Estatisticas: total ativas, desconto medio, expirando 7d, agendadas

---

## Ordem de Implementacao Recomendada

```
Fase 2 (Historico de Precos)
  ↓ menor esforco, maior valor imediato
  ↓ aproveita sync de precos ja existente
  ↓
Fase 3 (Historico de Colecoes)
  ↓ mesma logica da Fase 2, aplicada a colecoes
  ↓ modifica o mesmo arquivo (upsertProducts)
  ↓
Fase 4 (Promocoes)
  ↓ modulo independente (CRUD completo)
  ↓ maior esforco, mas auto-contido
```

### Estimativa de Esforco

| Fase | Arquivos Novos | Arquivos Modificados | Complexidade |
|------|---------------|---------------------|-------------|
| 2 | 1 (SQL) | 3 (model, view, test) | Baixa-Media |
| 3 | 1 (SQL) | 3 (model, view, test) | Baixa-Media |
| 4 | ~20 (modulo completo) | 2 (listProducts integracao) | Alta |

---

## Dependencias e Pre-requisitos

### Para todas as fases:
- Fase 1 completa (sync funcionando, catalogo operacional)
- Tabela `adms_products` normalizada (1 registro por referencia)
- Tabela `adms_product_variants` operacional

### Fase 4 especifica:
- Select de produtos deve estar disponivel no FormSelectRepository
- Permissoes de menu devem ser registradas para o novo modulo

---

## Criterios de Aceitacao

### Fase 2 — Historico de Precos
- [x] Tabela criada no banco de producao e teste
- [x] Sync de precos registra automaticamente mudancas
- [x] Modal de produto exibe timeline de precos (ultimas 20 mudancas)
- [x] Diferencas visuais (verde=reducao, vermelho=aumento)
- [x] Testes cobrindo: registro de mudanca, sem registro quando preco igual, visualizacao

### Fase 3 — Historico de Colecoes
- [x] Tabela criada no banco de producao e teste
- [x] Sync de produtos registra mudancas de colecao/subcolecao
- [x] Modal de produto exibe historico de colecoes
- [x] Testes cobrindo: registro de mudanca, sem registro quando igual

### Fase 4 — Promocoes
- [x] CRUD completo funcionando (listar, criar, editar, excluir)
- [x] Filtros de busca (produto, periodo, status)
- [x] Estatisticas no dashboard
- [x] Badge "Em Promocao" na listagem de produtos
- [x] Validacao: preco promocional < preco original, datas validas
- [x] Testes cobrindo todos os controllers e models
- [x] Rotas e permissoes registradas

---

## Fase 5 — Edicao de Produto + Variantes (COMPLETA)

### Objetivo

Permitir edicao manual de produtos e suas variantes diretamente no catalogo, com geracao automatica de codigos EAN-13 internos para variantes sem `aux_reference`.

### 5.1 Estrutura de Arquivos

| Arquivo | Acao | Descricao |
|---------|------|-----------|
| `app/adms/Controllers/EditProduct.php` | Criado | Controller AJAX (edit + update) |
| `app/adms/Models/AdmsEditProduct.php` | Criado | Model: load, update, processVariants |
| `app/adms/Views/products/partials/_edit_product_modal.php` | Criado | Modal com form + tabela de variantes |
| `app/adms/Controllers/Products.php` | Modificado | Adicionado botao edit_product |
| `app/adms/Views/products/listProducts.php` | Modificado | Botao de edicao na listagem |
| `app/adms/Views/products/loadProducts.php` | Modificado | Include do modal de edicao |
| `assets/js/products.js` | Modificado | editProduct(), setupVariantHandlers(), form submit |
| `database/migrations/add_edit_product_route.php` | Criado | Rota + permissao no banco |
| `tests/Products/EditProductControllerTest.php` | Criado | 13 testes do controller |
| `tests/Products/AdmsEditProductTest.php` | Criado | 27 testes do model |

### 5.2 Funcionalidades

- [x] Modal de edicao via AJAX (carrega produto + selects + variantes)
- [x] Campos editaveis: descricao, precos, marca, estacao, colecao, tipo, cor, material, grupo
- [x] Campos protegidos: referencia, fornecedor (readonly)
- [x] Sync lock automatico (sync_locked = 1 ao salvar)
- [x] Mascara de preco BRL (1.234,56)
- [x] Gerenciamento de variantes:
  - [x] Editar tamanho (select de adms_prod_sizes) e barcode
  - [x] Adicionar novas variantes
  - [x] Remover variantes (soft delete is_active=0)
  - [x] Campo aux_reference (Ref. Interna) visivel na tabela
- [x] EAN-13 automatico via Ean13Generator:
  - [x] Gera aux_reference para variantes sem valor ao salvar
  - [x] Preserva aux_reference existente
  - [x] Botao de regeneracao para limpar e gerar novo codigo
- [x] Validacao: campos permitidos, precos, size_cigam_code obrigatorio
- [x] Notificacao de sucesso/erro
- [x] 40 testes automatizados (controller + model)

### 5.3 Fluxo de Dados

```
[Modal Form] ──POST──> [EditProduct Controller] ──> [AdmsEditProduct Model]
                                                         │
                                                         ├── UPDATE adms_products (campos + sync_locked=1)
                                                         ├── UPDATE variantes existentes (size + barcode)
                                                         ├── INSERT novas variantes + EAN-13 auto
                                                         └── UPDATE is_active=0 (variantes removidas)
```

---

## Fase 6 — Importacao de Precos via Arquivo CSV (COMPLETA)

### Objetivo

Permitir importacao massiva de precos de produtos via upload de arquivo CSV, com processamento em background, progresso em tempo real e cancelamento.

### 6.1 Estrutura de Arquivos

| Arquivo | Acao | Descricao |
|---------|------|-----------|
| `app/adms/Controllers/ImportProductPrices.php` | Criado | Controller: upload, process, progress, dismiss, cancel |
| `app/adms/Models/AdmsImportProductPrices.php` | Criado | Model: validacao CSV, importacao chunked, progresso |
| `app/adms/Views/products/partials/_import_product_prices_modal.php` | Criado | Modal de upload + progresso + resultado |
| `app/adms/Views/products/loadProducts.php` | Modificado | Botao "Importar Precos" + include modal |
| `assets/js/products.js` | Modificado | Upload, progresso, resultado, cancelamento |

### 6.2 Funcionalidades

- [x] Upload de CSV com validacao de formato (referencia;preco_venda;preco_custo)
- [x] Processamento em background via `session_write_close()` + JSON progress file
- [x] Barra de progresso em tempo real com polling AJAX
- [x] Contadores: atualizados, inalterados, ignorados, nao encontrados, erros
- [x] Cancelamento durante processamento via flag no progress file
- [x] Validacao: formato numerico, referencia existente, preco positivo
- [x] Resultado detalhado com contadores visuais

### 6.3 Fluxo de Dados

```
[CSV Upload] ──POST──> [ImportProductPrices/upload] ──> Salva temp file
                                                              │
[Start Process] ──POST──> [ImportProductPrices/process] ──> [AdmsImportProductPrices/importChunked]
                                                              │
[Poll Progress] ──GET──>  [ImportProductPrices/progress] <── JSON progress file
                                                              │
[Dismiss] ──POST──>       [ImportProductPrices/dismiss] ──> Limpa temp files
```

### Commits Relacionados

| Commit | Descricao |
|--------|-----------|
| fd9d573d | feat(products): add product price import via file with background processing |

---

## Fase 7 — CSV de Rejeitados + Historico de Operacoes (COMPLETA)

### Objetivo

Gerar CSV downloadavel de referencias problematicas apos importacao/sincronizacao de precos, persistir logs de importacao em banco, e unificar visualizacao de historico em modal.

### 7.1 Estrutura de Arquivos

| Arquivo | Acao | Descricao |
|---------|------|-----------|
| `database/migrations/2026_03_03_import_product_prices_history.sql` | Criado | Tabela `adms_prod_import_logs` + coluna `rejected_file` + rotas |
| `app/adms/Models/AdmsImportProductPrices.php` | Modificado | +rejectedRows, saveRejectedRowsCsv(), createImportLog() |
| `app/adms/Models/AdmsSynchronizeProducts.php` | Modificado | +rejectedPriceRows, saveRejectedPricesCsv(), finalizeSyncLog |
| `app/adms/Controllers/ImportProductPrices.php` | Modificado | +downloadRejected(), operationHistory() |
| `app/adms/Controllers/Products.php` | Modificado | +downloadSyncRejected(), operation_history button |
| `app/adms/Controllers/SynchronizeProducts.php` | Modificado | +rejected_file no response de finalize |
| `app/adms/Views/products/partials/_operation_history_modal.php` | Criado | Modal historico (AJAX, tabela responsiva) |
| `app/adms/Views/products/loadProducts.php` | Modificado | Botao "Historico" + include modal |
| `app/adms/Views/products/partials/_sync_products_modal.php` | Modificado | Div para download de rejeitados sync |
| `assets/js/products.js` | Modificado | Download rejeitados import + historico modal completo |
| `assets/js/products-sync.js` | Modificado | Download rejeitados no resultado da sync |

### 7.2 Funcionalidades

- [x] CSV de rejeitados na importacao (LINHA;REFERENCIA;MOTIVO;PRECO_VENDA;PRECO_CUSTO)
- [x] CSV de rejeitados na sincronizacao (REFERENCIA;MOTIVO)
- [x] BOM UTF-8 + delimitador `;` para compatibilidade Excel PT-BR
- [x] Log persistente em `adms_prod_import_logs` (contadores, duracao, usuario, CSV path)
- [x] Coluna `rejected_file` em `adms_prod_sync_logs`
- [x] Link de download no resultado da importacao
- [x] Link de download no resultado da sincronizacao
- [x] Modal de historico com UNION ALL (import_logs + sync_logs)
- [x] Badges de status e tipo com cores distintas
- [x] Download de CSVs rejeitados diretamente do historico
- [x] Protecao contra path traversal nos endpoints de download
- [x] 4 novas rotas registradas com permissoes (downloadRejected, operationHistory, downloadSyncRejected)

### 7.3 Arquitetura do Historico

```
Modal Historico ──POST──> [ImportProductPrices/operationHistory]
                              │
                              ├── UNION ALL
                              │   ├── adms_prod_import_logs (importacoes de precos)
                              │   └── adms_prod_sync_logs (sincronizacoes CIGAM)
                              │
                              └── LEFT JOIN adms_usuarios (nome do usuario)
                                  │
                                  └── JSON response → renderOperationHistory() (JS)
                                      │
                                      └── Tabela com: data, tipo, subtipo, status,
                                          contadores, duracao, usuario, download
```

### 7.4 Notas Tecnicas

- **Collation:** Tabela `adms_prod_import_logs` DEVE usar `utf8mb4_unicode_ci` explicitamente
  - MySQL 8 default e `utf8mb4_0900_ai_ci`, incompativel em UNION com tabelas existentes
  - Erro silencioso em `AdmsRead` (PDOException capturada internamente)
- **CSRF:** Endpoint `operationHistory` e POST — requer `_csrf_token` via FormData
- **CSVs salvos em:** `uploads/import_errors/` (mesmo diretorio do OrderControl)

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
**Ultima Atualizacao:** 03/03/2026 (Fase 7 concluida)
