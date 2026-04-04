# Plano: Monitoramento Real-Time de Usuarios via WebSocket

**Data:** 2026-03-05
**Status:** Planejado
**Modulo:** UsersOnline (upgrade)

---

## Contexto

O modulo **UsersOnline** ja existe com polling AJAX a cada 60s e heartbeat HTTP. O WebSocket (`MercuryWS`) ja roda em todas as paginas. Falta: rastrear **qual pagina** cada usuario esta acessando e fazer **push em tempo real** para admins monitorando.

**Objetivo:** Admin ve instantaneamente quando qualquer usuario navega entre paginas, sem polling.

**Base existente:**
- `adms_users_online` — tabela com login/logout, IP, loja, heartbeat (`last_activity`), status (1=online, 2=offline)
- `UsersOnline.php` — controller com list(), refresh(), ping(), forceLogout(), statistics(), view(), cleanup()
- `users-online.js` — polling 60s + heartbeat 60s
- `MercuryWS` (`mercury-ws.js`) — cliente WebSocket global carregado em todas as paginas
- `WebSocketService.php` — servidor Ratchet porta 8080, API interna porta 8081
- `WebSocketNotifier.php` — fire-and-forget curl para API interna

---

## Arquivos a Modificar (14 arquivos)

| # | Arquivo | Tipo | Fase |
|---|---------|------|------|
| 1 | `database/migrations/2026_03_05_add_page_tracking.sql` | **Novo** | 1 |
| 2 | `app/adms/Models/AdmsPages.php` | Editar | 2 |
| 3 | `app/adms/Models/AdmsUsersOnline.php` | Editar | 3 |
| 4 | `app/adms/Models/AdmsListUsersOnline.php` | Editar | 3 |
| 5 | `app/adms/Services/WebSocketService.php` | Editar | 4 |
| 6 | `bin/websocket-server.php` | Editar | 5 |
| 7 | `app/adms/Services/WebSocketNotifier.php` | Editar | 6 |
| 8 | `core/ConfigController.php` | Editar | 7 |
| 9 | `app/adms/Views/usersOnline/listUsersOnline.php` | Editar | 8 |
| 10 | `app/adms/Views/usersOnline/partials/_statistics_dashboard.php` | Editar | 8 |
| 11 | `app/adms/Views/usersOnline/partials/_view_user_online_content.php` | Editar | 8 |
| 12 | `app/adms/Views/usersOnline/loadUsersOnline.php` | Editar | 8 |
| 13 | `assets/js/users-online.js` | Editar | 9 |
| 14 | `app/adms/Controllers/UsersOnline.php` | Editar | 10 |

---

## Fase 1: Migration — Colunas de Page Tracking

**Arquivo:** `database/migrations/2026_03_05_add_page_tracking.sql`

Adicionar 5 colunas a `adms_users_online`:

```sql
ALTER TABLE adms_users_online
  ADD COLUMN current_controller  VARCHAR(100) NULL AFTER last_activity,
  ADD COLUMN current_method      VARCHAR(100) NULL AFTER current_controller,
  ADD COLUMN current_url         VARCHAR(500) NULL AFTER current_method,
  ADD COLUMN current_page_title  VARCHAR(200) NULL AFTER current_url,
  ADD COLUMN current_page_at     DATETIME     NULL AFTER current_page_title;

ALTER TABLE adms_users_online
  ADD INDEX idx_current_controller (current_controller);
```

**Notas:**
- `current_page_at` e separado de `last_activity` — heartbeat NAO atualiza este campo
- Migration deve ser idempotente (check IF NOT EXISTS)
- Colunas nullable para compatibilidade com sessoes existentes

---

## Fase 2: Capturar Nome da Pagina no Routing

**Arquivo:** `app/adms/Models/AdmsPages.php` metodo `listarPaginas()`

**Mudanca:** Adicionar `pg.nome_pagina, pg.obs` ao SELECT:

```sql
-- ANTES:
SELECT pg.id, tpg.tipo tipo_tpg, pg.lib_pub

-- DEPOIS:
SELECT pg.id, tpg.tipo tipo_tpg, pg.lib_pub, pg.nome_pagina, pg.obs
```

**Impacto:** Minimo — colunas extras no array de resultado nao quebram nenhum consumer existente. Essas colunas ja existem na tabela `adms_paginas`.

---

## Fase 3: Model — Novo Metodo + Queries Atualizadas

**Arquivo:** `app/adms/Models/AdmsUsersOnline.php`

### 3a. Novo metodo `updateCurrentPage()`:

```php
public function updateCurrentPage(
    int $userId,
    string $controller,
    string $method,
    string $url,
    ?string $pageTitle = null
): bool {
    $update = new AdmsUpdate();
    $updateData = [
        'current_controller' => $controller,
        'current_method'     => $method,
        'current_url'        => $url,
        'current_page_title' => $pageTitle,
        'current_page_at'    => date('Y-m-d H:i:s'),
        'modified'           => date('Y-m-d H:i:s'),
    ];
    $update->exeUpdate(
        'adms_users_online',
        $updateData,
        'WHERE adms_user_id = :user_id AND adms_sit_access_id = 1',
        "user_id={$userId}"
    );
    return $update->getResult();
}
```

### 3b. Atualizar `getStatistics()` — adicionar query de paginas ativas:

```sql
SELECT current_page_title, current_controller, COUNT(*) AS viewer_count
FROM adms_users_online
WHERE adms_sit_access_id = 1 AND current_controller IS NOT NULL
GROUP BY current_controller, current_method, current_page_title
ORDER BY viewer_count DESC LIMIT 10
```

Retorno adiciona: `'active_pages' => $activePages`

### 3c. Atualizar `getSessionDetails()` — adicionar colunas ao SELECT:

```sql
uo.current_controller, uo.current_method,
uo.current_url, uo.current_page_title, uo.current_page_at,
```

**Arquivo:** `app/adms/Models/AdmsListUsersOnline.php`
- Mesmo: adicionar colunas `current_*` ao SELECT da listagem

---

## Fase 4: WebSocket — Sistema de Subscription para Monitors

**Arquivo:** `app/adms/Services/WebSocketService.php`

### 4a. Nova propriedade:
```php
protected array $monitoringConnections = []; // resourceId => ConnectionInterface
```

### 4b. Novos eventos no `onMessage()` match expression:
```php
'monitoring.subscribe'   => $this->handleMonitoringSubscribe($from),
'monitoring.unsubscribe' => $this->handleMonitoringUnsubscribe($from),
```

### 4c. Handlers:
```php
protected function handleMonitoringSubscribe(ConnectionInterface $conn): void
{
    $this->monitoringConnections[$conn->resourceId] = $conn;
}

protected function handleMonitoringUnsubscribe(ConnectionInterface $conn): void
{
    unset($this->monitoringConnections[$conn->resourceId]);
}
```

### 4d. Novo metodo publico:
```php
public function broadcastToMonitors(string $event, array $data): int
{
    $message = json_encode(['event' => $event, 'data' => $data]);
    $count = 0;
    foreach ($this->monitoringConnections as $resourceId => $conn) {
        $conn->send($message);
        $count++;
    }
    return $count;
}
```

### 4e. Cleanup no `onClose()`:
```php
unset($this->monitoringConnections[$conn->resourceId]);
```

---

## Fase 5: Internal API — Nova Rota para Monitors

**Arquivo:** `bin/websocket-server.php`

Nova rota ANTES do fallback 404:

```
POST /internal/broadcast-monitors
Header: X-Internal-Key (mesmo padrao existente)
Body: { "event": "user.page_changed", "data": { ... } }
Chama: $wsService->broadcastToMonitors(event, data)
Response: { "success": true, "notified": N }
```

Mesmo padrao de autenticacao e resposta do `/internal/broadcast` existente.

---

## Fase 6: WebSocketNotifier — Metodo para Monitors

**Arquivo:** `app/adms/Services/WebSocketNotifier.php`

Novo metodo estatico:

```php
public static function notifyMonitors(string $event, array $data = []): bool
```

- Mesmo padrao de `sendBroadcast()` mas URL = `/internal/broadcast-monitors`
- Fire-and-forget: CURLOPT_TIMEOUT = 2, CURLOPT_CONNECTTIMEOUT = 1
- Silent failure (try/catch retorna false)

---

## Fase 7: Tracking no ConfigController (CRITICO)

**Arquivo:** `core/ConfigController.php`

### 7a. Chamar em `carregarMetodo()`:
```php
private function carregarMetodo() {
    $this->validateCsrf();
    $this->validateUserSession();
    $this->checkForcePasswordChange();
    $this->trackCurrentPage();  // NOVO
    // ... instanciar controller e chamar metodo
}
```

### 7b. Novo metodo `trackCurrentPage()`:

```php
private function trackCurrentPage(): void
{
    // Skip: paginas publicas (lib_pub = 1)
    if (isset($this->Paginas[0]['lib_pub']) && (int)$this->Paginas[0]['lib_pub'] === 1) return;

    // Skip: nao autenticado
    if (!isset($_SESSION['usuario_id'])) return;

    // Skip: AJAX (so full-page navigation gera push)
    if ($this->isAjaxRequest()) return;

    // Skip: modulo de monitoramento (evita loop)
    if ($this->UrlController === 'UsersOnline') return;

    // Skip: infraestrutura
    if ($this->UrlController === 'WsToken') return;

    $userId     = (int) $_SESSION['usuario_id'];
    $controller = $this->UrlController;
    $method     = $this->UrlMetodo;
    $url        = $_SERVER['REQUEST_URI'] ?? '';
    $pageTitle  = $this->Paginas[0]['obs'] ?? $this->Paginas[0]['nome_pagina'] ?? $controller;

    // 1. Update DB (sincrono mas rapido — single UPDATE por indice)
    $model = new \App\adms\Models\AdmsUsersOnline();
    $model->updateCurrentPage($userId, $controller, $method, $url, $pageTitle);

    // 2. Push para monitors (fire-and-forget via localhost curl)
    \App\adms\Services\WebSocketNotifier::notifyMonitors('user.page_changed', [
        'user_id'      => $userId,
        'user_name'    => $_SESSION['usuario_nome'] ?? '',
        'controller'   => $controller,
        'method'       => $method,
        'page_title'   => $pageTitle,
        'page_at'      => date('Y-m-d H:i:s'),
        'store_id'     => $_SESSION['usuario_loja'] ?? null,
        'access_level' => $_SESSION['adms_niveis_acesso_id'] ?? null,
    ]);
}
```

**Performance:** UPDATE por indice existente (~1ms) + curl localhost (~1-2ms). Total ~3ms adicionais por page load. Negligivel.

**Filtros de seguranca:**
- AJAX filtrado = sem flood de sub-requests (modais, filtros, etc.)
- UsersOnline filtrado = sem feedback loop
- WsToken/Login filtrados = infraestrutura interna

---

## Fase 8: Views — Coluna "Pagina Atual"

### 8a. `listUsersOnline.php`
- Nova coluna `<th>Pagina Atual</th>` (classe `d-none d-xl-table-cell`)
- Celula com badge mostrando `current_page_title` + hora
- Classe `page-cell` no `<td>` para targeting via JS

### 8b. `_view_user_online_content.php`
- Bloco `alert-info` com "Pagina Atual" nos detalhes da sessao (controller/method + timestamp)

### 8c. `_statistics_dashboard.php`
- Novo card "Paginas Ativas" mostrando count de paginas distintas sendo acessadas

### 8d. `loadUsersOnline.php`
- Indicador de status WebSocket: badge verde "Tempo real" / cinza "Polling"
- Data attribute `data-ws-url` no config div hidden

---

## Fase 9: JavaScript — WebSocket Push + Real-Time Updates

**Arquivo:** `assets/js/users-online.js`

### 9a. Setup WebSocket monitoring:

```javascript
function setupMonitoringWebSocket() {
    if (!window.MercuryWS) return; // fallback polling

    MercuryWS.on('user.page_changed', handlePageChangedEvent);
    MercuryWS.on('user.online',       handleUserOnlineEvent);
    MercuryWS.on('user.offline',      handleUserOfflineEvent);

    MercuryWS.on('_connected', () => {
        MercuryWS.send('monitoring.subscribe', {});
        updateWsIndicator(true);
    });
    MercuryWS.on('_disconnected', () => updateWsIndicator(false));

    // Subscribe imediato se ja conectado
    if (MercuryWS.isConnected()) {
        MercuryWS.send('monitoring.subscribe', {});
        updateWsIndicator(true);
    }

    // Unsubscribe ao sair da pagina
    window.addEventListener('beforeunload', () => {
        if (MercuryWS.isConnected()) MercuryWS.send('monitoring.unsubscribe', {});
    });
}
```

### 9b. Handler de page changed:

```javascript
function handlePageChangedEvent(data) {
    const row = document.querySelector(`tr[data-user-id="${data.user_id}"]`);
    if (!row) return; // usuario nao visivel (paginacao/filtro)

    const pageCell = row.querySelector('td.page-cell');
    if (pageCell) {
        // Atualizar conteudo da celula
        pageCell.innerHTML = buildPageCellHtml(data);
    }

    // Flash amarelo na linha por 2s (feedback visual)
    row.style.backgroundColor = '#fff3cd';
    row.style.transition = 'background-color 2000ms ease-out';
    setTimeout(() => row.style.backgroundColor = '', 2000);
}
```

### 9c. Mudancas de config:

```javascript
const CONFIG = {
    HEARTBEAT_INTERVAL:    60000,   // Manter 60s
    AUTO_REFRESH_INTERVAL: 300000,  // Reduzir para 5min (fallback apenas)
    // ... resto igual
};
```

### 9d. Indicador WS:
- Badge verde "Tempo real" quando MercuryWS conectado + subscrito
- Badge cinza "Polling" quando sem WS

---

## Fase 10: Controller — Push de Online/Offline

**Arquivo:** `app/adms/Controllers/UsersOnline.php`

No metodo `forceLogout()`, apos marcar offline com sucesso:

```php
WebSocketNotifier::notifyMonitors('user.offline', [
    'user_id'   => $userId,
    'user_name' => $userName,
    'reason'    => 'force_logout',
    'by_user'   => $_SESSION['usuario_id'],
]);
```

---

## Ordem de Implementacao

```
Paralelo A:                    Paralelo B:
1. Migration SQL               5. WebSocketService.php
2. AdmsPages.php               6. websocket-server.php
3. AdmsUsersOnline.php          7. WebSocketNotifier.php
4. AdmsListUsersOnline.php

         Depois de A + B:
         8. ConfigController.php
         9. Views (4 arquivos)
         10. users-online.js
         11. UsersOnline controller
         12. Restart WebSocket server
```

---

## Sugestoes de Melhorias Futuras

### 1. Deteccao de Inatividade (Idle)
- JS detecta ausencia de mouse/teclado por X minutos
- Envia `user.idle` via WebSocket
- Dashboard mostra: **Online** (verde), **Ausente** (amarelo), **Offline** (vermelho)

### 2. Timeline de Navegacao por Sessao
- Nova tabela `adms_page_visits` (user_id, controller, page_title, visited_at, duration_seconds)
- Modal de detalhes mostra timeline visual das paginas visitadas na sessao
- Calculo de "tempo na pagina" = diferenca entre visitas consecutivas

### 3. Analytics de Paginas
- Dashboard com graficos: paginas mais acessadas, horarios de pico, tempo medio
- Heatmap de atividade por hora/dia
- Filtros por loja, nivel de acesso, periodo

### 4. Alertas Configuraveis
- Admin configura alertas para acesso a paginas sensiveis
- Notificacao push via WebSocket quando regra e trigada
- Tabela `adms_monitoring_alerts` (page_controller, notify_levels, active)

### 5. Informacoes de Dispositivo
- Parse do User-Agent para icone de browser + tipo de device
- Geolocalizacao aproximada por IP (GeoIP Lite)

### 6. Mapa de Calor de Navegacao
- Visualizacao tipo Sankey/flow dos caminhos mais comuns entre paginas
- Util para UX: identificar onde usuarios ficam "presos"

### 7. Export de Dados
- Botao para exportar sessoes ativas para Excel/CSV
- Relatorio de atividade por periodo (integrar com ExportService)

### 8. Widget no Dashboard Principal
- Card compacto "Usuarios Online Agora" no Home
- Mini-lista dos ultimos 5 acessos em tempo real

---

## Verificacao / Testes

1. **Migration:** `DESCRIBE adms_users_online` — verificar 5 novas colunas
2. **Page tracking:** Navegar entre paginas e conferir:
   ```sql
   SELECT adms_user_id, current_controller, current_page_title, current_page_at
   FROM adms_users_online WHERE adms_sit_access_id = 1
   ```
3. **WS subscription:** Console do browser na pagina UsersOnline → log `[UsersOnline] Subscribed to monitoring channel`
4. **Push real-time:** Em outra aba, navegar. Admin ve celula "Pagina Atual" atualizar + flash amarelo
5. **Fallback polling:** Parar WS server → auto-refresh 5min continua funcionando
6. **AJAX nao gera push:** Abrir modais, filtrar → nenhum push disparado
7. **Force logout:** Push `user.offline` aparece para monitors

---

## Riscos e Mitigacoes

| Risco | Mitigacao |
|-------|-----------|
| UPDATE a cada page load adiciona latencia | Query por indice existente (~1ms) + curl async (~1-2ms) |
| WS server offline | `notifyMonitors()` falha silenciosamente, polling 5min como fallback |
| Admin sem WS conectado | Polling 5min garante dados, indicador mostra "Polling" |
| Flood com muitos usuarios | Apenas full-page loads (AJAX filtrado). Batch futuro se necessario |
| `AdmsUpdate::getResult()` retorna false em 0 rows | Caller ignora retorno (comportamento conhecido) |
| `AdmsPages::listarPaginas()` mudanca quebra routing | Colunas extras sao aditivas, nao afetam logica existente |

---

**Autor:** Claude Code (Assistente AI)
**Projeto:** Mercury - Grupo Meia Sola
**Versao do plano:** 1.0
