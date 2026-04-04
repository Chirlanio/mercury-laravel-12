# Plano de Acao - Melhorias Modulo Sales

**Data:** 31 de Marco de 2026
**Versao:** 1.0
**Base:** ANALISE_COMPLETA_MODULO_SALES_2026_MAR.md
**Exclusao:** M-28 (unificacao de models CRUD)

---

## Resumo do Plano

- **Total de melhorias:** 28
- **Ja implementadas (Fases 1-3):** 10
- **Pendentes:** 18
- **Fases restantes:** 4 (numeradas 4 a 7)
- **Esforco estimado total:** ~30h de desenvolvimento
- **Periodo sugerido:** 2 a 3 sprints

---

## Fase 4 — Robustez da Sincronizacao (Esforco: ~5h)

**Objetivo:** Tornar o fluxo de sync mais resiliente e auditavel.

### 4.1 [M-22] Transactions em insertSalesValues()
- **Arquivo:** `app/adms/Models/AdmsSynchronizeSales.php`
- **Metodo:** `insertSalesValues()` (linha ~594)
- **O que fazer:**
  - Envolver o loop de INSERT em `beginTransaction()` / `commit()` com batch de 500 (mesmo padrao do `upsertSalesValues`)
  - Substituir `AdmsCreate` individual por PDO direto com prepared statement reutilizado
  - Adicionar `rollBack()` no catch com contagem de falhas por lote
- **Esforco:** 1h
- **Risco:** Baixo — metodo isolado, sem dependencias externas
- **Teste:** Criar teste que insere 5+ registros e verifica atomicidade

### 4.2 [M-21] Extrair constante SYNC_START_DATE
- **Arquivo:** `app/adms/Models/AdmsSynchronizeSales.php`
- **Linha:** ~147 (`$this->lastDate = '2020-08-01'`)
- **O que fazer:**
  - Adicionar `private const SYNC_START_DATE = '2020-08-01';`
  - Substituir literal pela constante
- **Esforco:** 5min
- **Risco:** Nenhum

### 4.3 [M-23] Eliminar logging duplicado controller+model
- **Arquivos:** `SynchronizeSales.php` (controller) + `AdmsSynchronizeSales.php` (model)
- **O que fazer:**
  - Remover os logs `SALES_SYNCHRONIZED` e `SALES_SYNC_FAILED` do **model** (upsertSalesValues e insertSalesValues)
  - Manter apenas os logs no **controller** (`handleSyncResult`), que ja inclui `user_id` e `sync_type`
  - O model deve apenas definir `$this->result`, `$this->message` e contadores — o controller decide o que logar
- **Esforco:** 30min
- **Risco:** Baixo — apenas remocao de chamadas redundantes
- **Cuidado:** Manter os logs de **erro** no model (UPSERT_FAILED, INSERT_FAILED, INSERT_EXCEPTION) pois sao especificos do ponto de falha

### 4.4 [M-24] Melhorar deteccao de timeout
- **Arquivo:** `app/adms/Models/AdmsSynchronizeSales.php`
- **Linhas:** ~173-174 e ~289-290 (`strpos($errorMsg, 'timeout')`)
- **O que fazer:**
  - Verificar `$e` como `PDOException` primeiro
  - Usar `$e->getCode()` (SQLSTATE `HYT00` = timeout, `08006` = connection failure)
  - Fallback para `strpos` em mensagens de exceptions genericas
  ```php
  if ($e instanceof \PDOException && in_array($e->getCode(), ['HYT00', '08006'])) {
      $this->message = 'O servidor CIGAM esta demorando...';
  } elseif (stripos($e->getMessage(), 'timeout') !== false) {
      $this->message = 'O servidor CIGAM esta demorando...';
  } else {
      $this->message = 'Erro na sincronizacao: ' . $e->getMessage();
  }
  ```
- **Esforco:** 30min
- **Risco:** Baixo

### 4.5 [M-25] Mock CIGAM para testes de integracao
- **Arquivo novo:** `tests/Sales/AdmsSynchronizeSalesCigamTest.php`
- **O que fazer:**
  - Criar classe `MockAdmsReadCigam` que simula respostas do CIGAM
  - Alternativa: usar `AdmsConnCigam` com banco SQLite in-memory ou tabela MySQL temporaria
  - Converter os 11 testes skipped em testes executaveis:
    1. `testSynchronizeExecutesSuccessfully` — mock retorna dados validos
    2. `testSynchronizeByMonthExecutesSuccessfully`
    3. `testSynchronizeByDateRangeExecutesSuccessfully`
    4. `testIncrementalSyncReturnsCorrectTypes`
    5. `testSynchronizeDelegatesToIncremental`
    6. `testUpsertDoesNotDuplicateRecords` — verificar UNIQUE KEY
    7. `testMaxHourIsPopulatedAfterIncrementalSync`
    8. `testMaxHourIsPopulatedAfterRangeSync`
    9. `testIncrementalSyncReturnsQuicklyWhenNoNewData`
    10. `testSynchronizeByMonthWithStoreFilter`
    11. `testYesterdayIsValidForSync`
  - Abordagem pragmatica: testar os metodos que NAO dependem de CIGAM (upsert, insert, delete) com dados reais no MySQL
- **Esforco:** 3h
- **Risco:** Medio — depende da arquitetura de injecao do AdmsReadCigam
- **Decisao tecnica:** Se AdmsReadCigam nao puder ser injetado/mockado, criar testes de integracao parcial que testam apenas a logica MySQL (upsert, batch, dedup)

---

## Fase 5 — Padronizacao e Qualidade de Codigo (Esforco: ~7h)

**Objetivo:** Alinhar o modulo com os padroes modernos do projeto.

### 5.1 [M-13] Migrar controllers para match expression
- **Arquivos (7 controllers):**
  1. `AddSales.php` — if/elseif no processamento de POST vs GET
  2. `ConfirmSales.php` — if/elseif na validacao
  3. `EditSales.php` — if/elseif no roteamento edit vs load
  4. `EditSalesByConsultant.php` — if/else no roteamento
  5. `DeleteSalesByConsultant.php` — if/else simples
  6. `DeleteSalesRange.php` — if/elseif no tipo de exclusao
  7. `ViewSalesByConsultant.php` — if/else simples
- **Padrao a seguir:** `Sales.php` linha 68-72 (match no requestType)
- **O que fazer:**
  - Identificar o ponto de roteamento principal de cada controller
  - Substituir if/elseif por `match` onde ha 2+ branches fixos
  - NAO forcar match em validacoes sequenciais (if/early return e mais legivel)
- **Esforco:** 2h
- **Risco:** Baixo — refatoracao sintatica
- **Teste:** Rodar suite completa apos cada controller

### 5.2 [M-16] Refatorar variaveis globais JS para modulo IIFE
- **Arquivo:** `assets/js/sales.js`
- **Variaveis globais atuais:**
  - `window.listSales` (linha 25)
  - `window.viewSalesByConsultant` (linha 315)
  - `window.editSaleByConsultant` (linha 459)
  - `window.deleteSaleByConsultant` (linha 943)
  - `currentViewSalesId` (linha 309)
  - `currentDeleteSaleId` (linha 312)
- **O que fazer:**
  - Encapsular todo o codigo em IIFE: `(function() { 'use strict'; ... })();`
  - Manter apenas `window.listSales` como export (usado por outros modulos/views)
  - Converter os demais para funcoes locais chamadas via event delegation
  - Mover `currentViewSalesId` e `currentDeleteSaleId` para variaveis locais do IIFE
- **Esforco:** 2h
- **Risco:** Medio — funcoes podem ser referenciadas em onclick inline das views
- **Pre-requisito:** M-17 (remover onclick inline primeiro)

### 5.3 [M-17] Substituir onclick inline por event delegation
- **Arquivo:** `app/adms/Views/sales/loadSales.php`
- **Linhas:** ~198, ~202 (botoes com `onclick="previewDeleteSales()"` e `onclick="confirmDeleteSales()"`)
- **O que fazer:**
  - Remover atributos `onclick`
  - Adicionar `id` ou `data-action` nos botoes
  - Tratar via `addEventListener` no JS correspondente (`sales-delete-range.js`)
- **Esforco:** 30min
- **Risco:** Baixo

### 5.4 [M-08] Testes para API controller SalesController
- **Arquivo novo:** `tests/Sales/SalesApiControllerTest.php`
- **Endpoints a testar (5):**
  1. `GET /api/v1/sales` — listagem paginada
  2. `GET /api/v1/sales/statistics` — estatisticas
  3. `GET /api/v1/sales/by-store` — relatorio por loja
  4. `GET /api/v1/sales/by-consultant` — relatorio por consultor
  5. `GET /api/v1/sales/consultants` — lista de consultores
- **O que fazer:**
  - Testar a classe `SalesController` diretamente (instanciar com request mockado)
  - Ou testar via HTTP com `curl`/Guzzle se houver server de teste
  - Verificar: formato de resposta JSON, paginacao, filtros de permissao, campos obrigatorios
- **Esforco:** 2h
- **Risco:** Medio — depende da testabilidade do BaseApiController

### 5.5 [M-07] Mock CIGAM para testes skipped (complemento da 4.5)
- **Nota:** Se a Fase 4.5 nao resolver todos os 11 testes, completar aqui
- **Esforco:** Incluido no 4.5

---

## Fase 6 — Features e UX (Esforco: ~10h)

**Objetivo:** Novas funcionalidades e melhorias de experiencia.

### 6.1 [M-14] Export CSV/Excel nos relatorios
- **Arquivos:**
  - Controller: `Sales.php` — novo metodo `exportReport()`
  - Model: `AdmsStatisticsSales.php` — reutilizar `calculateSalesByStore()`, `calculateSalesByConsultant()`, `calculateMonthlyComparison()`
  - JS: `sales.js` — botao de export no dropdown de relatorios
- **O que fazer:**
  - Adicionar botao "Exportar" ao lado de cada tipo de relatorio
  - No controller, chamar o model correspondente e gerar arquivo via PhpSpreadsheet
  - Formatar: cabecalhos, valores monetarios BR (R$), datas, totais
  - Content-Disposition: attachment com nome descritivo (`vendas_por_loja_2026_03.xlsx`)
  - Suportar os 3 tipos: por loja, por consultor, comparativo mensal
- **Esforco:** 4h
- **Risco:** Baixo — PhpSpreadsheet ja e dependencia do projeto
- **Referencia:** Modulo Products usa `ExportService` — avaliar reuso

### 6.2 [M-15] Notificacao WebSocket quando sync completa
- **Arquivos:**
  - Controller: `SynchronizeSales.php` — adicionar notificacao apos `handleSyncResult`
  - Dependencia: `SystemNotificationService`
- **O que fazer:**
  - Apos sync bem-sucedida, notificar usuarios na pagina de vendas
  - Usar `SystemNotificationService::notifyUsers()` com categoria `'sales'`
  - Resolver destinatarios via `NotificationRecipientService::resolveRecipients('sales', $storeId)`
  - Excluir o usuario que iniciou a sync (`SessionContext::getUserId()`)
  - No JS (`sales.js`), ouvir `MercuryWS.on('notification.new')` com `category === 'sales'` para refresh automatico da lista
- **Esforco:** 1h
- **Risco:** Baixo — padrao ja implementado em AddSales
- **Referencia:** `AddSales.php` linhas 188-220

### 6.3 [M-20] Progresso real de sync (SSE ou polling)
- **Arquivos:**
  - Controller: `SynchronizeSales.php` — novo endpoint `syncProgress()`
  - Model: `AdmsSynchronizeSales.php` — salvar progresso em sessao/cache
  - JS: `sales-sync.js` — polling via `setInterval` + `fetch`
- **O que fazer:**
  - **Opcao A (Polling — recomendada):**
    1. No model, salvar progresso em `$_SESSION['sync_progress']` a cada batch (500 registros)
    2. Chamar `session_write_close()` antes do loop e `session_start()` para cada update
    3. Criar endpoint `syncProgress()` que retorna `$_SESSION['sync_progress']`
    4. No JS, `setInterval(fetchProgress, 2000)` durante a sync
  - **Opcao B (SSE):**
    1. Controller envia `text/event-stream` com progresso
    2. Mais complexo, menos compativel com a arquitetura atual
  - Exibir: % completo, registros processados/total, tempo decorrido
- **Esforco:** 4h
- **Risco:** Medio — `session_write_close` + `session_start` pode ter side effects
- **Referencia:** Modulo Products (`ImportProductPrices`) ja usa esse padrao de polling

### 6.4 [M-18] Adicionar aria-labels nos botoes de acao
- **Arquivos:**
  - `app/adms/Views/sales/listSales.php` — botoes de view/edit/delete nas linhas ~114-135
  - `app/adms/Views/sales/viewSalesByConsultant.php` — botoes edit/delete nas linhas ~122-137
- **O que fazer:**
  - Substituir `title="Visualizar"` por `title="Visualizar" aria-label="Visualizar venda"`
  - Adicionar `aria-label` em todos os botoes de icone (sem texto visivel)
  - Padrao: `aria-label="[Acao] venda de [contexto]"` quando possivel
- **Esforco:** 1h
- **Risco:** Nenhum

---

## Fase 7 — Melhorias Estruturais (Esforco: ~8h)

**Objetivo:** Refatoracoes profundas para alinhar com a arquitetura alvo.

### 7.1 [M-19] Substituir MD5 por hash mais forte para user_hash
- **Arquivos:**
  - `AdmsSynchronizeSales.php` — `upsertSalesValues()` linha ~436
  - `AdmsAddSales.php` — `addSaleDirect()` linha ~92
  - `AdmsSynchronizeSales.php` — `insertSalesValues()` linha ~600
- **O que fazer:**
  - Substituir `md5($cpf)` por `hash('xxh3', $cpf)` (rapido, sem colisoes praticas)
  - Alternativa: `crc32($cpf)` se performance for critica
  - **ATENCAO:** Requer migracao dos hashes existentes no banco
  - **Migracao:** `UPDATE adms_total_sales SET user_hash = SHA2(adms_cpf_employee, 256) WHERE 1=1`
  - **Plano:**
    1. Adicionar coluna `user_hash_v2` temporaria
    2. Popular com novo hash via migration
    3. Atualizar codigo para usar `user_hash_v2`
    4. Renomear colunas
    5. Remover coluna antiga
  - **Alternativa simples:** Manter MD5 — o user_hash nao e usado para seguranca, apenas como agrupador de CPF para a view de consultor. MD5 e suficiente para este caso.
- **Esforco:** 1h (se manter MD5: 0h)
- **Risco:** Alto se migrar (dados existentes); Nenhum se manter MD5
- **Recomendacao:** **Manter MD5** — nao e vetor de ataque, serve apenas como chave de agrupamento

### 7.2 [M-26] Transactions em CRUD via helpers
- **Arquivos:** `AdmsCreate.php`, `AdmsUpdate.php` (helpers core)
- **O que fazer:**
  - Adicionar metodos `beginTransaction()`, `commit()`, `rollBack()` nos helpers
  - Ou expor `AdmsConn::getConn()` para uso direto com transactions
  - Aplicar em: `AdmsEditSales::altSales()` (atualiza multiplos registros)
- **Esforco:** 4h (inclui refatoracao dos helpers)
- **Risco:** Alto — helpers sao usados em 600+ models
- **Recomendacao:** Implementar como metodos OPCIONAIS (nao quebrar interface existente)

### 7.3 [M-27] Renomear plural para singular
- **Decisao:** NAO IMPLEMENTAR neste ciclo
- **Motivo:** Requer alteracao em `adms_paginas` (rotas DB), `adms_nivacs_pgs` (permissoes), `adms_menus` (navegacao), e todos os links/redirects. Risco de breaking change muito alto para beneficio cosmetico.
- **Alternativa:** Documentar como "divergencia aceita" e nao replicar em novos modulos.

### 7.4 [M-29] Sanitizacao client-side no JS
- **Arquivo:** `assets/js/sales.js`
- **Pontos de risco:**
  - Linha ~48: `innerHTML` para carregar lista HTML do server
  - Linha ~359: `innerHTML` para carregar conteudo de modal
  - Linhas ~631-710: HTML de notificacao gerado inline
- **O que fazer:**
  - **Para conteudo do server** (linhas 48, 359): Aceitavel — o server ja escapa com `htmlspecialchars()`
  - **Para notificacoes inline** (linhas 631-710): Usar `textContent` em vez de `innerHTML` para mensagens
  - Alternativa: Adicionar DOMPurify como dependencia (overkill para este caso)
- **Esforco:** 2h
- **Risco:** Baixo
- **Recomendacao:** Apenas substituir `innerHTML` por `textContent` nas notificacoes geradas no JS

---

## Cronograma Sugerido

### Sprint 1 (Semana 1) — Foco: Robustez
| Dia | Tarefa | Esforco |
|-----|--------|---------|
| Seg | 4.1 Transactions em insertSalesValues | 1h |
| Seg | 4.2 Constante SYNC_START_DATE | 5min |
| Seg | 4.3 Eliminar logging duplicado | 30min |
| Seg | 4.4 Melhorar deteccao de timeout | 30min |
| Ter-Qua | 4.5 Mock CIGAM para testes | 3h |
| | **Subtotal Sprint 1** | **~5h** |

### Sprint 2 (Semana 2) — Foco: Padronizacao
| Dia | Tarefa | Esforco |
|-----|--------|---------|
| Seg | 5.3 Remover onclick inline | 30min |
| Seg | 5.1 Match expression (7 controllers) | 2h |
| Ter | 5.2 Refatorar variaveis globais JS | 2h |
| Qua | 5.4 Testes API controller | 2h |
| | **Subtotal Sprint 2** | **~7h** |

### Sprint 3 (Semana 3-4) — Foco: Features + Estrutural
| Dia | Tarefa | Esforco |
|-----|--------|---------|
| Seg-Ter | 6.1 Export CSV/Excel | 4h |
| Qua | 6.2 Notificacao WebSocket em sync | 1h |
| Qui-Sex | 6.3 Progresso real de sync | 4h |
| Sex | 6.4 Aria-labels | 1h |
| | **Subtotal Sprint 3** | **~10h** |

### Sprint 4 (Quando prioritario) — Foco: Estrutural
| Dia | Tarefa | Esforco |
|-----|--------|---------|
| - | 7.1 Hash user_hash (recomendacao: manter MD5) | 0h |
| - | 7.2 Transactions em helpers (quando houver demanda) | 4h |
| - | 7.3 Renomear plural (NAO IMPLEMENTAR) | 0h |
| - | 7.4 Sanitizacao client-side | 2h |
| | **Subtotal Sprint 4** | **~6h** |

---

## Metricas de Sucesso

### Apos Fase 4 (Robustez)
- [ ] 0 `error_log()` no modulo Sales
- [ ] Transactions em ambos os metodos de insercao (upsert + insert)
- [ ] 11 testes CIGAM convertidos de skipped para executaveis (ou parcialmente)
- [ ] Logging sem duplicacao controller/model

### Apos Fase 5 (Padronizacao)
- [ ] 10/10 controllers com match expression (onde aplicavel)
- [ ] 0 variaveis globais JS (exceto `window.listSales`)
- [ ] 0 onclick inline nas views
- [ ] Testes para API controller (5 endpoints)

### Apos Fase 6 (Features)
- [ ] 3 tipos de relatorio exportaveis em Excel
- [ ] Notificacao WebSocket em sync para todos os usuarios
- [ ] Barra de progresso real durante sync
- [ ] 100% de botoes com aria-labels

### Nota Final Esperada
- **Atual:** 8.5/10
- **Apos Fase 4:** 9.0/10
- **Apos Fase 5:** 9.3/10
- **Apos Fase 6:** 9.7/10

---

## Dependencias Entre Tarefas

```
M-17 (onclick inline) ──> M-16 (IIFE JS)
M-22 (transactions insert) ──> M-25 (mock CIGAM, para testar)
M-23 (logging duplicado) ──> nenhuma
M-14 (CSV/Excel) ──> nenhuma
M-15 (WebSocket sync) ──> nenhuma
M-20 (progresso real) ──> nenhuma (mas complementa M-15)
```

---

## Riscos e Mitigacoes

| Risco | Probabilidade | Impacto | Mitigacao |
|-------|:---:|:---:|-----------|
| Mock CIGAM nao funciona com arquitetura atual | Media | Medio | Testar apenas logica MySQL (upsert, batch, dedup) |
| Match expression quebra fluxo de controller | Baixa | Baixo | Rodar testes apos cada controller |
| Export Excel com muitos dados trava | Baixa | Medio | Limitar a 10.000 registros por export |
| Progresso real causa race condition na sessao | Media | Medio | Usar `session_write_close` + reabrir (padrao Products) |
| Refatoracao JS quebra funcionalidade | Media | Alto | Testar manualmente cada fluxo (CRUD + sync + delete) |

---

**Mantido por:** Equipe Mercury - Grupo Meia Sola
**Proxima revisao:** Apos conclusao de cada fase
