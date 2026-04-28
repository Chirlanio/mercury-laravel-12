# Remanejos — Backlog pós-MVP

> **Status atualizado 2026-04-28**: Itens 1–8 ✅ **TODOS IMPLEMENTADOS** na 2ª iteração
> da sessão. Detalhes em `memory/relocations_module.md` (seção "Iteração pós-MVP").
> Arquivo mantido como referência histórica do escopo do trabalho.
>
> **Pendentes (não bloqueantes, baixa prioridade)**:
> - Item 9: Eventos de auditoria detalhada (item-level) — RelocationStatusHistory
>   já cobre status do header; granularidade item-a-item é refinamento de
>   compliance se exigido por auditoria externa.
> - Item 10: Comando de retroprocessamento CIGAM — re-roda matcher pra
>   relocations antigos. Cron `relocations:cigam-match` already cobre o caso
>   normal; comando manual seria pra cenários de re-sincronização emergencial.

## Implementações concluídas (mapeamento item → commit)

- ✅ #1 Curva ABC + sazonalidade — commit `a6b56c6` (sessão anterior)
- ✅ #2 Substituição similar quando origem em ruptura — commit `d92ae70`
- ✅ #3 Reverb realtime — commit `543a311`
- ✅ #4 Validação saldo absoluto antes de approved — commit `303379a`
  (também cobre saldo comprometido entre remanejos via `RelocationCommittedStockService`)
- ✅ #5 QR code no romaneio — commit `2d4bab2`
- ✅ #6 Ranking de aderência por loja — commit `a544d0e` (medalhas 🥇🥈🥉,
  6 métricas: completed_count, delivery_rate, dispatch_accuracy,
  avg_separation_hours, discrepancy_count, total_dispatched)
- ✅ #7 Reabertura/clone — commit `1d27c35`
- ✅ #8 Bulk approve — commit `5f900da`

## Bonus implementado (fora do backlog original)

- Validação NF on-demand contra movements (CIGAM) — `RelocationDispatchValidationService`
- Notification + Helpdesk hook em divergência de despacho
- Multi-origem em N remanejos no CreateModal (split automático)
- Gráfico Solicitado×Enviado no Dashboard
- Comando `relocations:import-from-legacy` (211 remanejos + 2.772 items
  migrados do banco antigo `u401878354_meiaso26_bd_me`)

---

> Conteúdo abaixo preservado como histórico do briefing original.

---

## 1. Curva ABC + sazonalidade no `RelocationSuggestionService`

**Esforço:** M (1 dia) · **Prioridade:** Alta · **Status:** backlog

A heurística atual rankeia produtos por venda total nos últimos N dias. Não
distingue best-sellers consistentes (curva A) de hits sazonais. Pra
planejamento estratégico, vale classificar:

- **Curva A** (top 20% que somam 80% das vendas): priorizar reposição
- **Curva B** (próximos 30%): reposição moderada
- **Curva C** (50% restante): só sob demanda

Adicionalmente, comparar venda dos últimos 30 dias vs mesma janela do ano
anterior pra detectar produtos sazonais subindo (sandália em dezembro → janeiro).

**Prompt:**
> No `app/Services/RelocationSuggestionService.php` adicione classificação ABC e flag sazonal nas sugestões. ABC: top 20% por valor acumulado vendido = A, próximos 30% = B, resto = C. Sazonal: marcar produto cujas vendas N dias > 1.5x média do mesmo produto nas N janelas anteriores. Expor `curve` e `is_seasonal` em cada item retornado. Rankear primeiro por curva (A > B > C) e dentro da curva por venda total. UI: badge ao lado do nome no SuggestionsModal.

---

## 2. Substituição similar quando origem em ruptura

**Esforço:** M+ (1.5 dia) · **Prioridade:** Média · **Status:** backlog

Quando a loja sugerida pro produto X está em ruptura (saldo estimado ≤ 0),
sugerir um produto similar (mesma `collection_cigam_code` e mesma cor) que
tenha saldo. UI mostra "produto X esgotado em todas as origens — sugerimos
produto Y como substituto".

**Prompt:**
> Estenda `RelocationSuggestionService::suggestForStore()` pra detectar items sem origem viável (todas com `estimated_balance <= 0`) e procurar substituto similar via `products.collection_cigam_code` + `product_color` matching, retornando `substitute_for_barcode` no payload. Frontend `SuggestionsModal.jsx` mostra linha agrupada (original em cinza tachado + substituto em destaque) quando há substituição.

---

## 3. Reverb realtime substituindo refresh manual

**Esforço:** M (1 dia) · **Prioridade:** Média · **Status:** backlog

Hoje a listagem só atualiza com page reload. Em produção com várias lojas
operando simultaneamente, a loja destino quer ver remanejo aparecer assim que
origem despacha. Padrão já estabelecido pelo módulo Helpdesk e DamagedProducts.

**Prompt:**
> Crie eventos broadcast `RelocationCreated`, `RelocationStatusBroadcast` (do listener existente) e canal privado `relocations.store.{store_id}` em `routes/channels.php`. No `Pages/Relocations/Index.jsx` faça subscribe se `scopedStoreId` estiver setado, com debounce 500ms via `setTimeout` pra coalescer bursts (mesmo padrão de DamagedProducts). Toast notification quando remanejo entra em `in_transit` com loja sendo o destino. Try/catch silencioso se Echo offline.

---

## 4. Validação de saldo absoluto na origem antes de aprovar

**Esforço:** L (2 dias) · **Prioridade:** Baixa · **Status:** backlog

Hoje confiamos no usuário (planejamento) pra saber se a origem tem saldo. Em
produção houve casos de planejar transferência de algo que a origem já vendeu.
Solução cara: criar `stock_balances` (loja × variant × qty) sincronizada via
job + atualizada por movimento. Solução barata: query agregada em `movements`
(saldo proxy 90d) com TTL de cache de 5 min.

**Prompt:**
> Crie `app/Services/StockBalanceService.php` com método `estimateBalance(int $storeId, string $barcode): int` que calcula saldo proxy via `+code=1 +code=5+E +code=6+E -code=2 -code=5+S` últimos 90d. Cache `array` 5 min por (store, barcode). No `RelocationTransitionService::transition()`, na transição → `approved`, valide que cada item.barcode tem saldo >= qty_requested na origem; se não, retorne ValidationException listando items sem saldo. Permita override via `force_approve_without_stock=true` no payload.

---

## 5. Romaneio com QR code (scan no destino → abre Receive direto)

**Esforço:** S (4h) · **Prioridade:** Baixa · **Status:** backlog

PDF Romaneio atual tem só checkbox físico. Adicionar QR code com URL
`/relocations/{ulid}` no canto. Vendedora destino escaneia → app abre direto
no Receive modal preenchido.

**Prompt:**
> Adicione QR code no `resources/views/pdf/relocation-romaneio.blade.php` apontando pra `route('relocations.show', $relocation->ulid)`. Use `endroid/qr-code` (já presente no Composer via Consignments). Posicione no canto superior direito do header, tamanho 80x80px.

---

## 6. Ranking gamificado de aderência por loja origem

**Esforço:** M (1 dia) · **Prioridade:** Baixa · **Status:** backlog

Já temos `dispatch_adherence` por item. Falta visualização agregada mensal
mostrando ranking público "loja X despachou 95% do solicitado este mês". Pode
virar dashboard público interno (TV na sala da diretoria).

**Prompt:**
> Crie endpoint `GET /relocations/leaderboard?period=current_month|last_30d` retornando ranking de lojas origem ordenado por aderência (com tie-break por volume), com pódio (top 3) destacado. Página `Pages/Relocations/Leaderboard.jsx` simples com 1 tabela + medalhas 🥇🥈🥉. Acessível via menu "Ranking" no PageHeader do Index. Permission VIEW_RELOCATIONS basta.

---

## 7. Reabertura via clone de remanejo cancelado/rejeitado

**Esforço:** S (3h) · **Prioridade:** Baixa · **Status:** backlog

Cancelar/rejeitar deixa o remanejo em estado terminal. Pra revisar e tentar
de novo, hoje precisa criar do zero. Adicionar botão "Clonar pra novo
rascunho" copiando origem, destino, tipo, prioridade e items.

**Prompt:**
> Adicione método `RelocationService::cloneAsDraft(Relocation $source, User $actor): Relocation` que cria novo Relocation em status=draft com mesmos items (zerando qty_separated/received/dispatched_quantity). Endpoint `POST /relocations/{relocation}/clone` + botão "Clonar como rascunho" no DetailModal disponível em terminal states (rejected/cancelled).

---

## 8. Bulk approve no planejamento

**Esforço:** S (4h) · **Prioridade:** Média · **Status:** backlog

Planejamento que recebe 20 requested num dia tem que clicar Aprovar em cada
um. Adicionar checkbox de seleção múltipla no DataTable + botão "Aprovar
selecionados".

**Prompt:**
> No `Pages/Relocations/Index.jsx`, quando filtro `status=requested` está ativo, ative coluna de checkbox no DataTable e botão "Aprovar N selecionados" no header (visible quando >=1 selecionado). Endpoint `POST /relocations/bulk-approve` aceitando array de ulids, valida APPROVE_RELOCATIONS, transita cada um em transação. Falha em 1 não bloqueia o resto — retorna { approved: int, failed: array<{ulid, error}> } na flash. Toast consolidado.

---

## 9. Eventos de auditoria detalhada (item-level)

**Esforço:** M (1 dia) · **Prioridade:** Baixa · **Status:** backlog

Hoje só registramos transições do cabeçalho. Mudanças nos itens (qty_separated
durante separação, qty_received durante recebimento) não viram history. Pra
disputas tipo "a origem alega que separou 5 mas o destino registrou 3"
precisamos de trail.

**Prompt:**
> Crie tabela `relocation_item_changes` (id, item_id, field, old_value, new_value, user_id, source enum 'manual'|'cigam', changed_at). Hook em `RelocationItem::saving()` que detecta mudança em qty_separated/received/dispatched_quantity/received_quantity e grava. Source='cigam' quando o write vem do CigamMatcherService. UI: nova seção "Auditoria de itens" no DetailModal collapsable.

---

## 10. Comando de retroprocessamento CIGAM

**Esforço:** S (2h) · **Prioridade:** Baixa · **Status:** backlog

Se o `movements:sync` falhou por dias e voltou, os remanejos in_transit
podem estar sem match porque o scope `pendingCigamMatch` filtra ok mas o
matcher só roda nos últimos 60d. Precisa de reprocessamento manual.

**Prompt:**
> Adicione opção `--days=N` ao `relocations:cigam-match` que sobrescreve `LOOKUP_DAYS` constante do CigamMatcherService. Default mantém 60. Adicione `--force` que ignora `cigam_dispatched_at`/`cigam_received_at` setados (re-roda matching). Útil pra catch-up após sync atrasado ou correção de NF errada.
