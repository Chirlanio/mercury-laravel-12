# DamagedProducts — Backlog de melhorias pós-MVP

> Plano de ação para os 7 itens remanescentes do módulo Produtos Avariados.
> Módulo concluído em 2026-04-27 (8 fases + 5 ondas de refator UX).
> Detalhes técnicos do módulo: `C:\Users\MSDEV\.claude\projects\C--xampp-htdocs-mercury-laravel\memory\damaged_products_module.md`.

## Status atual (2026-04-27)

| Item | Status |
|------|--------|
| 1. Dashboard.jsx com 4 gráficos recharts | A implementar agora |
| 2. Hook Helpdesk fail-safe em rejeições | Adiado |
| 3. Match a 3 produtos (cadeia ABC) | Backlog |
| 4. Cross-match damaged ↔ mismatched | Backlog |
| 5. OCR de barcode na foto | Backlog (perguntas pendentes) |
| 6. Reverb realtime | A implementar agora |
| 7. API pública read-only para BI | Backlog (perguntas pendentes) |
| 8. Workflow "irreparável → baixa de estoque" CIGAM | Adiado (perguntas pendentes) |
| 9. Frontend page do config NetworkBrandRule | Adiado |

## Sequência sugerida

| Ordem | Item | Esforço | Por quê |
|------:|------|---------|---------|
| 1 | Dashboard | S | Standalone, baixo risco, ganho UX imediato |
| 2 | Reverb realtime | M | Infra pronta; precisa estar antes de chain/cross para que eventos novos já saiam broadcasting nativamente |
| 3 | Write-off CIGAM (Item 8) | M-L | Bloqueia em decisão CIGAM — começar discussão cedo |
| 4 | Cross-match (Item 4) | M | Engine extension sem schema profundo |
| 5 | Chain ABC (Item 3) | L | Maior complexidade; após cross validar engine |
| 6 | OCR barcode (Item 5) | M-L | Não bloqueia ninguém; depende de decisão cliente vs servidor |
| 7 | API pública BI (Item 7) | M | Por último — expõe schema final estabilizado |

---

## Item 1 — Dashboard.jsx com 4 gráficos recharts

**Esforço:** Small | **Status:** Em implementação

### Escopo
Página `/damaged-products/dashboard` paralela à listagem, com 4 gráficos analíticos. Action `dashboard` no PageHeader do Index. Sem entrada própria no menu central (paridade com `reversals.dashboard`).

### Decisões arquiteturais
- **Endpoint novo** `GET /damaged-products/dashboard` (Inertia) — não estende `/statistics` (que retorna JSON e é consumido pelo Index sem reload).
- **Gráficos:**
  1. Pie por status (cores do enum `DamagedProductStatus::color()`)
  2. Pie por `damage_type_id` (top 10) — insight operacional sobre tipos de avaria mais comuns
  3. Bar horizontal — top 10 lojas por contagem de registros
  4. Line — últimos 12 meses (criados vs resolvidos)
- **MetricCards:** taxa de resolução (já em `buildStatistics`), tempo médio open→resolved, score médio dos matches aceitos, total de transferências geradas.

### Arquivos modificados
- `app/Http/Controllers/DamagedProductController.php` — `dashboard()` + `buildAnalytics()`
- `routes/tenant-routes.php` — rota `/dashboard` antes de `/{damagedProduct}`
- `resources/js/Pages/DamagedProducts/Index.jsx` — action `dashboard` no PageHeader

### Arquivos novos
- `resources/js/Pages/DamagedProducts/Dashboard.jsx`

### Riscos
- Ordem de rotas: `/dashboard` antes de `/{damagedProduct}` (ULID), senão 404.

---

## Item 6 — Reverb realtime

**Esforço:** Medium | **Status:** Em implementação

### Escopo
Broadcast em 3 eventos críticos: `DamagedProductMatchFound`, `DamagedProductMatchAccepted`, `DamagedProductMatchRejected`. Toast no Index quando recebe match novo + reload silencioso (debounce 500ms para coalescer bursts). Database notifications continuam disparando como histórico/badge persistente.

### Decisões arquiteturais
- **Canal per-store privado** `damaged-products.store.{storeId}` — não per-user (evita 2N broadcasts por evento). Authorization no `routes/channels.php` resolve `user.store_id` (code) → `stores.id` (int) ou bypass por `MANAGE_DAMAGED_PRODUCTS`.
- Eventos passam a estender `BaseEvent` (já `ShouldBroadcastNow` + try/catch via Guzzle timeout — fail-safe se Reverb offline).
- `broadcastWith()` retorna payload mínimo (id, ulid, partner store, suggested_destination, score) — não o `match_payload` cheio.
- Listeners de notification DB ficam INALTERADOS (broadcast é "live ping"; DB é histórico).
- **Frontend:**
  - Subscribe condicional: usuários com escopo de loja subscrevem `damaged-products.store.{scopedStoreId}`; admins (sem scoping) caem no fallback de polling 30s (paridade Helpdesk).
  - Listener registra ambos `.damaged-match.found` e `DamagedProductMatchFound` (alguns setups de broadcast usam o nome da classe; paridade Helpdesk).
  - Toast `info` + `router.reload({ only: ['items', 'statistics'] })` com debounce 500ms.

### Arquivos modificados
- `app/Events/DamagedProductMatchFound.php` — extends `BaseEvent` + `broadcastOn/As/With`
- `app/Events/DamagedProductMatchAccepted.php` — idem
- `app/Events/DamagedProductMatchRejected.php` — idem
- `routes/channels.php` — adiciona canal `damaged-products.store.{storeId}`
- `resources/js/Pages/DamagedProducts/Index.jsx` — subscribe condicional + toast + reload

### Arquivos novos
nenhum (reaproveita `BaseEvent` + padrão de subscribe direto via `window.Echo`)

### Riscos
- **Cast store_id:** `users.store_id` é code (`Z441`); `stores.id` é numérico. Auth callback faz lookup.
- **Auto-discovery dos listeners DB:** continuam disparando — broadcast é PUBLIC do event, listener é PRIVATE (não conflita).
- **Admin sem scoping:** subscribe apenas se `auth.user.id` + `scopedStoreId` (admin já tem visão global e usaria o action manual de Atualizar do PageHeader como reload).
- **Channel auth:** dispatcha em try/catch silencioso (igual Helpdesk) pra não quebrar Index se Echo não está configurado.

---

# Itens em backlog (não implementados nesta iteração)

## Item 2 — Hook Helpdesk fail-safe em rejeições
Abrir ticket no depto Logística/Operações quando match é rejeitado. Mesmo padrão de Reversals/TravelExpenses (3 níveis fail-safe + idempotente via `helpdesk_ticket_id`).

**Por que adiado:** depende de definição do depto Logística no Helpdesk + fluxo operacional ainda em discussão.

## Item 3 — Match a 3 produtos (cadeia ABC)
**Esforço:** Large

Casos raros mas reais em redes grandes: A=(L38,R39), B=(L39,R40), C=(L40,R38) → ciclo fecha 3 pares.

**Arquitetura proposta:** tabela nova `damaged_product_match_chains` + 2 pivots. NÃO estender `damaged_product_matches` (quebra unique). Algoritmo BFS depth=2 pré-filtrando por `product_reference` (~10-50 nodes por ref). Sempre prefere binário sobre chain.

**Quando implementar:** após cross-match validar que MatchingService aguenta múltiplos modos.

## Item 4 — Cross-match damaged ↔ mismatched
**Esforço:** Medium

Cenário: Loja A com par MISMATCHED (left=38, right=39) + Loja B com damaged left=38 → A entrega left 38 pra B; A fica com right 39 sobrando (regenera novo DamagedProduct com `regenerated_from_match_id`).

**Arquitetura:** ALTER do enum `match_type` para `damaged_mismatched_cross` (no-op em SQLite). Resíduo regenerado via flag para auditoria.

**Quando implementar:** após Reverb (eventos novos já broadcast).

## Item 5 — OCR de barcode na foto
**Esforço:** Medium-Large

Reduzir erro de digitação do EAN-13 via OCR.

**Decisão arquitetural pendente — perguntas:**
1. % de cadastros mobile vs desktop? (>70% mobile = client-side puro `@zxing/browser`)
2. Orçamento para Google Vision API (~$15-30/mês esperado)?
3. Fotos atuais incluem etiqueta separada das do dano?
4. Política IT permite câmera no navegador?
5. UX desejada se OCR falhar (digitação manual obrigatória ou re-tentativa)?

**Recomendação preliminar:** Opção A (client-side com `@zxing/browser` ou `html5-qrcode`) lazy-loaded.

## Item 7 — API pública read-only para BI
**Esforço:** Medium

Endpoints REST `/api/v1/damaged-products/*` para PowerBI/Looker/scripts internos.

**Decisão arquitetural pendente — perguntas:**
1. Quem consome (PowerBI / Looker / script Python interno)?
2. Volume esperado (req/min) — define rate limit?
3. Token per-tenant ou per-user?
4. Documentação: Scribe (instalar dep) ou markdown manual?

**Recomendação preliminar:** reutilizar middleware `integration.auth` existente; adicionar coluna `api_key_hash` em `tenant_integrations` (config é Crypt — não dá WHERE direto).

## Item 8 — Workflow "irreparável → baixa de estoque" CIGAM
**Esforço:** Medium-Large

Status novo `WRITTEN_OFF` para registros marcados `is_repairable=false`.

**Decisões arquiteturais pendentes — perguntas críticas:**
1. CIGAM tem `movement_code` específico para baixa por sinistro? Quais `controle` válidos?
2. Sentido (E/S) e regra de valor para baixa?
3. Mercury escreve direto no DB CIGAM ou exporta arquivo? **Hoje só LÊ movements** — escrever é inversão arquitetural significativa.
4. Necessidade de NF fiscal de descarte?
5. Quem aprova (admin only ou fluxo multi-step)?
6. Baixa é IRREVERSÍVEL ou pode ser revertida?

**Plano alternativo:** entregar APENAS lado Mercury (status novo + UI + comando) e deixar baixa real como TODO documentado.

## Item 9 — Frontend page do config NetworkBrandRule
Pivot UI para Network → marcas. Página existe em `central_pages` mas precisa controller + componente React dedicado (DamageType usa template genérico de Config sem código novo).

**Por que adiado:** baixa prioridade — atualmente as regras são geridas via seed/import e raramente mudam.

---

## Histórico de decisões

- **2026-04-27**: Plano original criado pós-conclusão do MVP. Decidido implementar Itens 1 + 6 nesta iteração; demais ficam para iterações futuras conforme priorização.
