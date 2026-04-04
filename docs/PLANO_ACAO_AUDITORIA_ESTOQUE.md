# Plano de Acao: Modulo de Auditoria de Estoque (Stock Audit)

**Referencia:** `docs/PROPOSTA_MODULO_AUDITORIA_ESTOQUE.md` v2.0
**Data:** 2026-03-06
**Ultima Atualizacao:** 2026-03-15
**Projeto:** Mercury

### Status das Fases

| Fase | Status | Data Conclusao |
|---|---|---|
| **Fase 1:** Fundacao | Concluida | 2026-03-06 |
| **Fase 2:** Contagem e Importacao | Concluida | 2026-03-08 |
| **Fase 3:** Conciliacao e Relatorios | Concluida | 2026-03-10 |
| **Fase 4A:** Auditoria Aleatoria | Concluida | 2026-03-15 |
| **Fase 4B:** Justificativas (Fase B - Auditor) | Concluida | 2026-03-10 |
| **Fase 4C:** Justificativas da Loja (Fase C) | Concluida | 2026-03-12 |
| **Fase 4D:** Dashboard | Concluida | 2026-03-15 |
| **Fase 4E:** Assinatura Digital | Concluida | 2026-03-15 |
| **Fase 4F:** Mapa de Calor | Concluida | 2026-03-15 |

---

## Fases de Entrega

O modulo sera implementado em 4 fases incrementais. Cada fase entrega valor funcional completo e pode ser testada independentemente.

---

## Fase 1: Fundacao (Tabelas + CRUDs Simples + Cabecalho)

### 1.1 Migration SQL

Criar `database/migrations/2026_03_XX_create_stock_audit_tables.sql`:

- Tabela `adms_stock_audit_statuses` (lookup) + seed 6 status
- Tabela `adms_stock_audit_cycles` + campos completos
- Tabela `adms_audit_vendors` + campos completos
- Tabela `adms_stock_audits` (cabecalho) + FKs
- Tabela `adms_stock_audit_items`
- Tabela `adms_audit_teams`
- Tabela `adms_stock_audit_import_logs`
- Tabela `adms_stock_audit_accuracy_history`
- Tabela `adms_stock_audit_signatures`
- Rotas em `adms_paginas` + permissoes em `adms_nivacs_pgs`
- Collation: `utf8mb4_unicode_ci` (obrigatorio)

### 1.2 CRUD Ciclos de Auditoria (AbstractConfigController)

Modulo simples de lookup usando AbstractConfigController:

| Arquivo | Tipo |
|---|---|
| `Controllers/StockAuditCycles.php` | Controller (extends AbstractConfigController) |
| `Views/stockAuditCycles/loadStockAuditCycles.php` | View principal |
| `Views/stockAuditCycles/listStockAuditCycles.php` | Listagem AJAX |
| `Views/stockAuditCycles/partials/_add_stock_audit_cycle_modal.php` | Modal adicionar |
| `Views/stockAuditCycles/partials/_edit_stock_audit_cycle_modal.php` | Modal editar |

### 1.3 CRUD Fornecedores de Auditoria (AbstractConfigController)

| Arquivo | Tipo |
|---|---|
| `Controllers/AuditVendors.php` | Controller (extends AbstractConfigController) |
| `Views/auditVendors/loadAuditVendors.php` | View principal |
| `Views/auditVendors/listAuditVendors.php` | Listagem AJAX |
| `Views/auditVendors/partials/_add_audit_vendor_modal.php` | Modal adicionar |
| `Views/auditVendors/partials/_edit_audit_vendor_modal.php` | Modal editar |

### 1.4 CRUD Auditorias (Cabecalho + Equipes)

Modulo principal com listagem, criacao, edicao e visualizacao:

| Arquivo | Tipo |
|---|---|
| `Controllers/StockAudit.php` | Controller principal (listagem + dashboard) |
| `Controllers/AddStockAudit.php` | Controller adicionar |
| `Controllers/EditStockAudit.php` | Controller editar |
| `Controllers/ViewStockAudit.php` | Controller visualizar |
| `Controllers/DeleteStockAudit.php` | Controller deletar |
| `Models/AdmsListStockAudits.php` | Model listagem com paginacao |
| `Models/AdmsStockAudit.php` | Model CRUD principal |
| `Models/AdmsViewStockAudit.php` | Model visualizacao detalhada |
| `Models/AdmsStatisticsStockAudits.php` | Model estatisticas |
| `Views/stockAudit/loadStockAudit.php` | Pagina principal com stats |
| `Views/stockAudit/listStockAudit.php` | Listagem AJAX |
| `Views/stockAudit/partials/_add_stock_audit_modal.php` | Modal criacao |
| `Views/stockAudit/partials/_edit_stock_audit_modal.php` | Modal edicao |
| `Views/stockAudit/partials/_view_stock_audit_modal.php` | Modal detalhes |
| `Views/stockAudit/partials/_delete_stock_audit_modal.php` | Modal exclusao |
| `Views/stockAudit/partials/_team_management_partial.php` | Gestao de equipe |
| `assets/js/stock-audit.js` | JavaScript principal |

### 1.5 Service: AuditStateMachineService

| Arquivo | Tipo |
|---|---|
| `Services/AuditStateMachineService.php` | Maquina de estados |

Responsabilidades:
- Validar transicoes de status permitidas
- Verificar permissoes do usuario para cada transicao
- Executar acoes colaterais (notificacao, log, e-mail)
- Metodos: `canTransition()`, `transition()`, `getAvailableTransitions()`

### 1.6 Testes Fase 1

| Arquivo | Cobertura |
|---|---|
| `tests/StockAudit/AuditStateMachineTest.php` | Transicoes de status, permissoes |
| `tests/StockAudit/AdmsStockAuditTest.php` | CRUD cabecalho, validacoes |

**Entregavel:** Cadastro completo de auditorias com fluxo de autorizacao, gestao de equipes e cronograma.

---

## Fase 2: Contagem e Importacao

### 2.1 Importacao Multi-Formato (CSV/TXT/XLSX/XLS)

| Arquivo | Tipo |
|---|---|
| `Controllers/ImportStockAuditCount.php` | Controller importacao + download templates |
| `Models/AdmsImportStockAuditCount.php` | Model processamento multi-formato |
| `Views/stockAudit/partials/_import_count_modal.php` | Modal importacao |

Funcionalidades:
- **Dois formatos de importacao com auto-deteccao:**
  - **Coletor de dados** — coluna unica com codigos de barras bipados sequencialmente. Codigos de area (inicio/fim) controlam a area ativa automaticamente. Cada bipagem = 1 unidade.
  - **Planilha** — colunas: `codigo_barras`, `quantidade`, `area` (opcional, codigo EAN-13), `observacao` (opcional). Delimitador: `;` (ponto e virgula).
- **Formatos de arquivo aceitos:** CSV, TXT, XLSX, XLS (maximo 10MB)
  - Arquivos Excel (XLSX/XLS) sao convertidos automaticamente para CSV via PhpSpreadsheet antes do processamento
  - Log de debug da primeira linha do CSV convertido para diagnostico
- **Download de templates modelo** via rota parametrizada (`download-template/spreadsheet` ou `download-template/collector`), sem rotas adicionais
- **Deteccao robusta de cabecalhos:** 20+ aliases para cada coluna (codigo_barras, barcode, ean, cod_barras, etc.) com remocao de acentos via `removeAccents()`
- **Resolucao de produtos apenas por codigo de barras** (2 tiers):
  1. `adms_product_variants.barcode` (EAN direto)
  2. `adms_product_variants.aux_reference` (referencia auxiliar/EAN alternativo)
  - Referencias de produto (`adms_products.reference`) NAO sao aceitas — garante integridade na conciliacao
- **Modo de importacao (add/replace):**
  - `add` (padrao): soma valores ao que ja existe (`COALESCE(count_N, 0) + :qty`)
  - `replace`: substitui a contagem existente (`count_N = :qty`)
- **Modelo multi-area por barcode:**
  - Cada combinacao `audit_id + product_barcode + area_id` gera uma linha separada no banco
  - Busca inteligente no import: `barcode + area` exata → fallback para `barcode + area IS NULL` (snapshot Cigam)
  - Se encontra item do snapshot Cigam (area NULL), atualiza o area_id nele (evita duplicatas com snapshot)
  - Se nao encontra nenhum, cria nova linha com a area informada
  - Consolidacao (`consolidateDuplicates`) so consolida duplicatas com **mesma area** — linhas com areas diferentes sao validas
- **Exibicao agrupada por barcode:**
  - `getItems()` usa `GROUP BY product_sku, product_barcode` com `SUM()` nas contagens
  - Sem filtro de area: mostra total consolidado (soma de todas as areas)
  - Com filtro de area: `WHERE area_id = :area_id` antes do agrupamento → mostra apenas quantidades daquela area
  - `getSummary()` usa `COUNT(DISTINCT product_barcode)` para contagem correta de itens unicos
- Mapeamento para rodada (count_1, count_2, count_3) com selecao de area
- CSV de rejeitados (padrao `uploads/import_errors/`)
- Log em `adms_stock_audit_import_logs`
- Background processing com `session_write_close` + JSON progress (padrao Products)
- Mensagem de erro aprimorada mostra colunas detectadas no arquivo

### 2.2 Contagem em Tempo Real (Leitor de Codigo de Barras)

| Arquivo | Tipo |
|---|---|
| `Controllers/StockAuditCount.php` | Controller tela de contagem |
| `Models/AdmsStockAuditCount.php` | Model contagem unitaria + limpeza |
| `Views/stockAuditCount/loadStockAuditCount.php` | Tela de contagem |
| `assets/js/stock-audit-count.js` | JS para leitor de barras + limpeza |

Funcionalidades:
- Campo de input com autofocus para receber scan
- Ao escanear EAN: busca produto, exibe nome, incrementa quantidade
- Ajuste manual de quantidade
- Indicador visual de itens ja contados vs pendentes
- Salvamento automatico a cada scan (sem botao de salvar)
- **Integracao com areas:** contagem vinculada a area da auditoria (mesma logica multi-area do import)
  - `incrementCount()` busca por `barcode + area` exata → fallback `area IS NULL` → cria nova linha
- **Limpeza de contagem por rodada** (`clearCount` endpoint):
  - Zera campos `count_N`, `count_N_by`, `count_N_at` de todos os itens da rodada
  - Remove itens orfaos (onde todas as 3 contagens ficam NULL)
  - Filtro opcional por area
  - **Bloqueio de rodadas finalizadas:** verifica `count_N_finalized_at` antes de permitir limpeza
  - Confirmacao dupla: modal + `confirm()` nativo do navegador
  - Retorna contagem de itens limpos e removidos

### 2.3 Carga Automatica do Saldo Cigam

| Arquivo | Tipo |
|---|---|
| `Services/StockAuditCigamService.php` | Service integracao Cigam |

Funcionalidades:
- Ao abrir auditoria (status → Em Contagem): puxa saldo atual de todos os SKUs da loja via `AdmsReadCigam`
- Preenche `system_quantity` e `unit_price` em `adms_stock_audit_items`
- Log de sincronizacao

### 2.4 Testes Fase 2

| Arquivo | Cobertura |
|---|---|
| `tests/StockAudit/AdmsImportStockAuditCountTest.php` | Import CSV, validacao, rejeitados |
| `tests/StockAudit/AdmsStockAuditCountTest.php` | Contagem unitaria, incremento |

**Entregavel:** Importacao de contagens multi-formato (CSV/TXT/XLSX/XLS) com modo add/replace, contagem em tempo real por leitor de codigo de barras, limpeza de rodada com protecao de finalizacao.

---

## Fase 3: Conciliacao e Relatorios

### 3.1 Conciliacao de Contagens

| Arquivo | Tipo |
|---|---|
| `Controllers/StockAuditReconciliation.php` | Controller conciliacao |
| `Models/AdmsStockAuditReconciliation.php` | Model logica de conciliacao |
| `Views/stockAudit/loadStockAuditReconciliation.php` | Tela de conciliacao |
| `Views/stockAudit/partials/_reconciliation_detail_modal.php` | Detalhe por item |
| `assets/js/stock-audit-reconciliation.js` | JS conciliacao |

Funcionalidades:
- Painel com todos os itens e suas 3 contagens lado a lado
- Destaque visual para divergencias (vermelho = perda, azul = sobra)
- Filtros: todos, apenas divergentes, pendentes de 3a contagem
- **Indicadores de divergencia por area (Fase A):**
  - `getCountReconciliationAreaSummary()` retorna totais por area (divergentes, resolvidos, pendentes)
  - Badges coloridos: verde (sem divergencia), vermelho (divergencia pendente), amarelo (divergencia resolvida), azul (pendente sem divergencia)
  - Badges clicaveis para filtrar cards por area (`data-area-id` nos rows)
- Botao "Aceitar contagem" por item ou em lote
- Calculo automatico de `final_quantity`, `divergence`, `divergence_value`
- Resumo financeiro em tempo real (total perdas, total sobras, acuracidade)

### 3.2 Integracao Razao/Movimentacao

| Arquivo | Tipo |
|---|---|
| `Models/AdmsStockAuditMovements.php` | Model consulta movimentacao |
| `Views/stockAudit/partials/_movement_history_modal.php` | Modal historico |

Funcionalidades:
- Para cada item divergente: botao "Ver Movimentacao"
- Modal mostra ultimos 30/60/90 dias de movimentacao do SKU
- Dados de: vendas, transferencias, recebimentos, ajustes (MySQL + Cigam)
- Ajuda a identificar causa raiz da divergencia

### 3.3 Relatorios e PDF

| Arquivo | Tipo |
|---|---|
| `Controllers/StockAuditReport.php` | Controller relatorios |
| `Models/AdmsStockAuditReport.php` | Model dados do relatorio |
| `Services/StockAuditReportService.php` | Service geracao PDF + envio e-mail |
| `Views/stockAudit/partials/_report_preview_modal.php` | Preview do relatorio |

Relatorio inclui:
- Resumo executivo (acuracidade, totais financeiros)
- Top 10 divergencias (maiores perdas e sobras)
- Lista completa de itens auditados
- Composicao da equipe
- Timeline de eventos (abertura, autorizacao, contagens, finalizacao)

### 3.4 Historico de Acuracidade

| Arquivo | Tipo |
|---|---|
| `Models/AdmsStockAuditAccuracyHistory.php` | Model historico |

Funcionalidades:
- Registro automatico ao finalizar auditoria
- Grafico de evolucao por loja (Chart.js ou similar)
- Comparativo entre lojas no dashboard

### 3.5 Testes Fase 3

| Arquivo | Cobertura |
|---|---|
| `tests/StockAudit/AdmsStockAuditReconciliationTest.php` | Logica conciliacao, calculos |
| `tests/StockAudit/AdmsStockAuditReportTest.php` | Geracao de dados do relatorio |

**Entregavel:** Conciliacao completa de 3 rodadas, relatorios PDF/e-mail, historico de acuracidade.

---

## Fase 4B: Justificativas do Auditor (Concluida)

Implementada como parte da Fase 3 (Conciliacao). Durante a conciliacao, o auditor pode justificar itens divergentes (`is_justified = 1`), removendo-os do calculo de divergencia.

- Justificativa por item com nota textual
- Registro de quem justificou e quando
- Perdas justificadas sao deduzidas do resultado
- Sobras justificadas sao confirmadas (permanecem no resultado)

---

## Fase 4C: Justificativas da Loja (Concluida)

### Visao Geral

Apos a conciliacao (Fase B), itens ainda divergentes podem receber justificativas da loja (Fase C). A loja submete justificativas que sao revisadas (aceitas ou rejeitadas) por um usuario com permissao superior.

### Arquivos

| Arquivo | Tipo |
|---|---|
| `Controllers/StockAuditStoreJustification.php` | Controller CRUD justificativas |
| `Models/AdmsStockAuditStoreJustification.php` | Model com logica de submissao e revisao |
| `Views/stockAuditStoreJustification/loadStockAuditStoreJustification.php` | Pagina principal |
| `Views/stockAuditStoreJustification/listStoreJustificationItems.php` | Listagem AJAX |
| `assets/js/stock-audit-store-justification.js` | JavaScript (submissao, revisao, filtros) |

### Tabela: `adms_stock_audit_store_justifications`

| Coluna | Tipo | Descricao |
|---|---|---|
| `id` | INT (PK) | Auto increment |
| `audit_id` | INT (FK) | Referencia `adms_stock_audits` |
| `item_id` | INT (FK) | Referencia `adms_stock_audit_items` |
| `justification_text` | TEXT | Texto da justificativa |
| `found_quantity` | DECIMAL(10,2) (nullable) | Quantidade encontrada (parcial) |
| `submitted_by` | INT (FK) | Usuario que submeteu |
| `submitted_at` | DATETIME | Timestamp submissao |
| `review_status` | ENUM('pending','accepted','rejected') | Status da revisao |
| `reviewed_by` | INT (FK, nullable) | Usuario que revisou |
| `reviewed_at` | DATETIME (nullable) | Timestamp revisao |
| `review_note` | TEXT (nullable) | Nota do revisor |

### Colunas adicionais em `adms_stock_audit_items`

| Coluna | Tipo | Descricao |
|---|---|---|
| `store_justified` | TINYINT(1) DEFAULT 0 | 1 = justificativa aceita pela loja |
| `store_justified_quantity` | DECIMAL(10,2) (nullable) | Qtd encontrada (parcial) |

### Logica Assimetrica de Calculo (Perdas vs Sobras)

**Regra de negocio fundamental:** perdas e sobras sao tratadas de forma assimetrica nas justificativas.

#### Perdas (divergence < 0)
- **Justificada Fase B** (`is_justified = 1`): deduz do resultado (perda explicada)
- **Fase C aceita** (`store_justified = 1`): deduz do resultado (perda confirmada pela loja)
- **Fase C rejeitada**: permanece como perda no resultado
- **Formula:** `resultado_faltas = bruto - fase_B - fase_C_aceitas`

#### Sobras (divergence > 0)
- **Justificada Fase B** (`is_justified = 1`): permanece no resultado (sobra confirmada)
- **Fase C aceita** (`store_justified = 1`): permanece no resultado (estoque real, precisa ajuste)
- **Fase C rejeitada**: deduz do resultado (erro de contagem confirmado)
- **Formula:** `resultado_sobras = bruto - fase_C_rejeitadas`

#### Justificativa

- **Sobra aceita** confirma a existencia do estoque → precisa de ajuste no sistema
- **Sobra rejeitada** confirma erro de contagem → estoque nao deve ser ajustado
- **Perda justificada** tem explicacao valida → nao penaliza a loja
- **Perda rejeitada** nao tem explicacao valida → permanece como perda

### Relatorios com Breakdown Financeiro

Todos os 4 tipos de relatorio (completo, divergencias, faltas, sobras) incluem tabela de breakdown:

```
| Descricao              | Faltas (Venda/Custo/Un.) | Sobras (Venda/Custo/Un.)  |
|------------------------|--------------------------|---------------------------|
| Divergencias Brutas    | valores                  | valores                   |
| (-) Just. Fase B       | valores deduzidos        | "Sobras confirmadas"      |
| (-) Just. Aceitas C    | valores deduzidos        | "Sobras confirmadas"      |
| (-) Rejeitadas C       | "Faltas permanecem"      | valores deduzidos         |
| **Resultado**          | **resultado**            | **resultado**             |
| **Perda de Estoque**   | resultado_faltas - resultado_sobras              |
```

### Funcionalidade de Recalculo

- Botao "Recalcular Resultados" no modal de visualizacao (status >= 4)
- Rota: `stock-audit-reconciliation/recalculate` (POST AJAX)
- Recalcula e atualiza `adms_stock_audits` com valores corretos
- Invalida cache de relatorios PDF
- Util para corrigir auditorias ja finalizadas apos correcao de bugs

### Testes

| Arquivo | Cobertura |
|---|---|
| `tests/StockAudit/AdmsStockAuditReportTest.php` | Mock data com breakdown completo, 11 testes |
| `tests/StockAudit/AdmsStockAuditReconciliationTest.php` | Logica de conciliacao, 31 testes |

---

## Fase 4A: Auditoria Aleatoria (Concluida)

### Visao Geral

Ao criar uma auditoria do tipo "Aleatoria", o usuario define um `random_sample_size` (minimo 10). Ao carregar o saldo do Cigam, o service seleciona apenas os N produtos com maior volume de movimentacao nos ultimos 90 dias.

### Arquivos

| Arquivo | Tipo |
|---|---|
| `Services/StockAuditRandomSelectionService.php` | Service selecao por movimentacao |
| `database/2026_03_14_stock_audit_phase4a_random.sql` | Migration (coluna `random_sample_size`) |

### Funcionalidades

- Coluna `random_sample_size` em `adms_stock_audits` (INT NULL)
- Validacao no create/edit: tipo "Aleatoria" exige `random_sample_size >= 10`
- `getHighMovementBarcodes()`: consulta `adms_stock_movements`, agrupa por `ref_size`, soma `ABS(quantity)`, ordena por `total_movement DESC`, limita a N
- Filtragem aplicada em `StockAuditCigamService::loadStockBalance()` apos carregar itens do Cigam
- Campo condicional no modal de criacao/edicao (visivel apenas para tipo "Aleatoria")
- Validacao JS no frontend com toggle de visibilidade

---

## Fase 4D: Dashboard (Concluida)

### Visao Geral

Pagina dedicada de dashboard com KPIs, graficos Chart.js e filtros por loja/periodo. Acessivel via botao "Dashboard" na listagem de auditorias.

### Arquivos

| Arquivo | Tipo |
|---|---|
| `Controllers/StockAudit.php` | Metodos `dashboard()` e `dashboardData()` |
| `Models/AdmsDashboardStockAudits.php` | Model com queries de agregacao |
| `Views/stockAudit/loadStockAuditDashboard.php` | Pagina do dashboard |
| `assets/js/stock-audit-dashboard.js` | Chart.js 3.9.1 (4 graficos) |
| `database/2026_03_14_stock_audit_phase4d_dashboard.sql` | Migration (rotas + permissoes) |

### Funcionalidades

- **6 KPI cards:** Total, Ativas, Finalizadas, Acuracidade media, Perdas (R$), Sobras (R$)
- **4 graficos Chart.js:**
  - Evolucao de Acuracidade (Line) — requer selecao de loja
  - Ranking de Lojas (Bar horizontal) — cores por faixa (verde >=95%, amarelo >=85%, vermelho <85%)
  - Distribuicao por Status (Doughnut) — cores Bootstrap por status
  - Impacto Financeiro por Loja (Bar) — perdas vs sobras lado a lado
- **Filtros:** loja (para usuarios com nivel < STOREPERMITION), data de/ate
- **Endpoint JSON:** `stock-audit/dashboard-data` retorna stats, status, financeiro, tendencia mensal, evolucao e ranking
- **Responsivo:** graficos em container fixo de 300px com `maintainAspectRatio: false`

---

## Fase 4E: Assinatura Digital (Concluida)

### Visao Geral

Assinaturas digitais obrigatorias (gerente + auditor) capturadas via SignaturePad.js ao finalizar auditoria na Fase C. Assinaturas armazenadas em base64 e renderizadas no relatorio PDF.

### Arquivos

| Arquivo | Tipo |
|---|---|
| `Controllers/StockAuditStoreJustification.php` | Metodo `signAndFinalize()` |
| `Models/AdmsStockAuditStoreJustification.php` | Metodos `saveSignature()`, `getSignatures()`, `hasRequiredSignatures()` |
| `Services/StockAuditReportService.php` | Metodo `buildSignaturesSection()` |
| `Views/stockAuditStoreJustification/loadStockAuditStoreJustification.php` | Modal `#signatureModal` com 2 canvas |
| `assets/js/stock-audit-store-justification.js` | SignaturePad init, validacao, submit |
| `database/2026_03_14_stock_audit_phase4e_signatures.sql` | Migration (rota `sign-and-finalize`) |

### Tabela: `adms_stock_audit_signatures`

Criada na migration da Fase 1. Colunas: `id`, `audit_id`, `role` (gerente/auditor), `signer_user_id`, `signature_data` (LONGTEXT base64), `signed_at`, `ip_address`, `user_agent`.

### Funcionalidades

- **Captura:** SignaturePad.js 4.2.0 via CDN, dois canvas (gerente e auditor)
- **Validacao:** ambas assinaturas obrigatorias antes de submeter
- **Armazenamento:** base64 data URI em `adms_stock_audit_signatures`, com IP e user agent
- **Limpeza:** remove assinatura anterior do mesmo role antes de salvar (permite reassinar)
- **PDF:** `buildSignaturesSection()` renderiza assinaturas inline como `<img src="data:image/png;base64,...">` antes do footer
- **Fallback:** se SignaturePad.js nao carregar, cai no modal de finalizacao tradicional (sem assinatura)

---

## Fase 4F (Concluida 2026-03-15): Mapa de Calor

Pagina dedicada com 3 abas de visualizacao: divergencias por area, por loja, e produtos recorrentes. Cards com gradiente de cores indicando acuracidade (verde >=95%, ciano 85-94%, amarelo 70-84%, vermelho <70%).

### Arquivos

| Arquivo | Tipo |
|---|---|
| `Models/AdmsStockAuditHeatmap.php` | Model com 3 metodos: getAreaDivergences, getStoreDivergences, getRecurrentDivergences |
| `Controllers/StockAudit.php` | Metodos heatmap() e heatmapData() adicionados |
| `Views/stockAudit/loadStockAuditHeatmap.php` | Pagina com filtros, 3 tabs, legenda |
| `assets/js/stock-audit-heatmap.js` | JS renderizacao cards e tabela produtos |
| `tests/StockAudit/AdmsStockAuditHeatmapTest.php` | 30 testes |
| `database/2026_03_15_stock_audit_phase4f_heatmap.sql` | Rotas e permissoes |

### Funcionalidades

- **Aba Areas**: Cards com acuracidade, total/divergentes, perdas/sobras (valor + unidades) por area
- **Aba Lojas**: Cards com metricas por loja, acessivel apenas para niveis acima de STOREPERMITION
- **Aba Produtos Recorrentes**: Tabela com top 20 SKUs divergentes em 2+ auditorias
- Filtros: loja (condicional por nivel), periodo (data inicio/fim)
- Dados de auditorias finalizadas (status_id = 5)
- Botao de acesso na listagem principal e no dashboard
- Responsivo: labels curtos em mobile, colunas ocultas em telas menores

**Entregavel:** Mapa de calor por area e loja para identificar areas problematicas e produtos recorrentes.

---

## Melhorias UX na Tela de Contagem (2026-03-15)

### Painel Visual de Areas (substituiu dropdown)

O seletor de area foi redesenhado de um `<select>` dropdown para um painel visual com cards por area. Cada card mostra uma grade de contadores × rodadas com badges clicaveis para atribuicao inline (sem modal).

**Estrutura:**
- Accordion colapsavel (Bootstrap 4 collapse), inicia fechado
- Um card por area com tabela: Contador | 1ª | 2ª | (3ª)
- Badges clicaveis: `badge-info` (atribuido, com × para remover), `badge-light disabled` (rodada tomada por outro), borda tracejada com + (disponivel)
- Footer com totais reais: itens contados / unidades por rodada (dados de `adms_stock_audit_items`)
- Card da area selecionada destacado com borda `border-info`

**Funcoes JS adicionadas:**
- `sacRenderAreaPanel()` — renderiza grid de contadores × rodadas
- `sacAssignCounterDirect(areaId, countRound, userId)` — atribuicao AJAX inline
- `sacUpdateAreaPanelHighlight()` — destaca card da area ativa

### Toolbar Consolidada (dropdown unico)

Botoes da toolbar (Areas, Etiquetas, Importar, Limpar) consolidados em um dropdown "Opcoes" com icone engrenagem. Inclui:
- **Areas**: abre modal de gestao
- **Etiquetas**: gera PDF de etiquetas
- **Importar**: abre modal de importacao
- **Conciliar**: transiciona status 3→4 via AJAX e redireciona para conciliacao (desabilitado ate todas as rodadas finalizadas)
- **Limpar** (danger): abre modal de limpeza

### Transicao para Conciliacao (botao "Conciliar")

O botao "Conciliar" no dropdown realiza:
1. Dialog de confirmacao via `sacConfirmAction()`
2. POST AJAX para `stock-audit/transition` com `to_status=4`
3. Redirect para `stock-audit-reconciliation/reconcile/{AUDIT_ID}`
4. Tratamento de erro com notificacao flash

**Habilitado quando:** `$round1Finalized && (!$requireCount2 || $round2Finalized) && (!$requireCount3 || $round3Finalized)`

### Arquivos Modificados

| Arquivo | Alteracao |
|---|---|
| `Views/stockAuditCount/loadStockAuditCount.php` | Painel areas (accordion), dropdown toolbar, botao conciliar |
| `assets/js/stock-audit-count.js` | Render panel, assign inline, reconcile handler |
| `Models/AdmsStockAuditArea.php` | Query count_totals por area/rodada em `getAreas()` |

---

## Resumo de Arquivos por Fase

| Fase | Controllers | Models | Services | Views | JS | Tests | Total |
|---|---|---|---|---|---|---|---|
| **1** | 7 | 4 | 1 | 12 | 1 | 2 | **27** |
| **2** | 2 | 2 | 1 | 3 | 1 | 2 | **11** |
| **3** | 2 | 3 | 1 | 4 | 1 | 2 | **13** |
| **4A** | — | 1 (mod) | 1 | — | 1 (mod) | — | **3** |
| **4B** | — | — | — | — | — | — | (parte da Fase 3) |
| **4C** | 1 | 1 | — | 2 | 1 | 2 | **7** |
| **4D** | 1 (mod) | 1 | — | 1 | 1 | — | **4** |
| **4E** | 1 (mod) | 1 (mod) | 1 (mod) | 1 (mod) | 1 (mod) | — | **5** |
| **4F** | 1 (mod) | 1 | — | 1 | 1 | 1 | **5** |
| **Total** | **14** | **14** | **5** | **24** | **7** | **8** | **~72** |

---

## Melhorias Globais Relacionadas

### CSRF Token Refresh via Heartbeat

Corrigido problema de token CSRF expirando durante importacoes longas (TTL 3600s). O heartbeat existente (`UsersOnline/ping`, a cada 60s) agora retorna um token CSRF atualizado na resposta JSON. O JavaScript (`heartbeat.js`) atualiza automaticamente a meta tag `csrf-token` e todos os inputs hidden `_csrf_token` no DOM. O endpoint `ping` e isento de validacao CSRF no `ConfigController`.

---

## Dependencias Externas

| Dependencia | Uso | Status |
|---|---|---|
| DomPDF 3.0 | Geracao PDF relatorios | Ja instalado |
| PHPMailer | Envio e-mail relatorios | Ja instalado |
| PhpSpreadsheet 5.3 | Import Excel | Ja instalado |
| AdmsReadCigam | Saldo estoque Cigam | Ja disponivel |
| WebSocket (Ratchet) | Notificacoes tempo real | Ja disponivel |
| LoggerService | Auditoria de operacoes | Ja disponivel |
| FileUploadService | Upload CSV | Ja disponivel |
| Chart.js 3.9.1 | Graficos dashboard (CDN) | Ja disponivel |
| SignaturePad.js 4.2.0 | Assinatura digital (CDN) | Ja disponivel |

---

## Ordem de Execucao Recomendada

```
Fase 1 (Fundacao)
  ├── 1.1 Migration SQL
  ├── 1.2 CRUD Ciclos (AbstractConfigController)
  ├── 1.3 CRUD Fornecedores (AbstractConfigController)
  ├── 1.4 CRUD Auditorias (cabecalho + equipes)
  ├── 1.5 AuditStateMachineService
  └── 1.6 Testes

Fase 2 (Contagem)
  ├── 2.1 Import CSV/Excel
  ├── 2.2 Contagem tempo real (leitor barras)
  ├── 2.3 Carga saldo Cigam
  └── 2.4 Testes

Fase 3 (Conciliacao)
  ├── 3.1 Conciliacao 3 rodadas
  ├── 3.2 Integracao Razao/Movimentacao
  ├── 3.3 Relatorios PDF + e-mail
  ├── 3.4 Historico acuracidade
  └── 3.5 Testes

Fase 4A (Auditoria Aleatoria) ✅
  └── Selecao top-N por movimentacao

Fase 4B (Justificativas Auditor) ✅
  └── Parte da Fase 3

Fase 4C (Justificativas Loja) ✅
  └── Submissao + revisao + logica assimetrica

Fase 4D (Dashboard) ✅
  └── KPIs + 4 graficos Chart.js + filtros

Fase 4E (Assinatura Digital) ✅
  └── SignaturePad.js + PDF inline

Fase 4F (Mapa de Calor) ✅
  └── Heatmap por area/loja + produtos recorrentes
```
