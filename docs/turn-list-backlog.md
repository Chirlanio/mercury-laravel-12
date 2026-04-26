# TurnList — Backlog de Melhorias

Pendências identificadas pós-deploy do módulo Lista da Vez (CONCLUÍDO 2026-04-26). Cada item inclui contexto, esforço estimado e um **prompt pronto** para colar em uma sessão futura quando for implementar.

---

## 1. Config Module — Tipos de Pausa

**Status**: permission `MANAGE_TURN_LIST_BREAK_TYPES` já definida no `Permission` enum e atribuída a SUPER_ADMIN/ADMIN. Sem UI nem rotas.

**Por que importa**: hoje há só 2 tipos fixos via seed (`Lanche` 15min, `Almoço` 60min). Se a empresa quiser "Banheiro 5min", "Reunião 30min" etc, requer migration manual e deploy.

**Esforço**: ~1 hora. Padrão `ConfigController` já estabelecido em 39 outros módulos do projeto.

**Prompt**:

```
Implemente o config module para gerenciar os tipos de pausa (turn_list_break_types) do módulo Lista da Vez. Siga o padrão dos outros 39 config modules do projeto.

Passos:
1. Crie app/Http/Controllers/Config/TurnListBreakTypeController.php estendendo ConfigController. Defina:
   - modelClass(): App\Models\TurnListBreakType
   - viewTitle(): "Tipos de Pausa"
   - columns(): id, name, max_duration_minutes (com formatação "Xmin"), color (badge), icon (preview), sort_order, is_active
   - formFields(): name (text), max_duration_minutes (number, min 1), color (select com options info/warning/success/danger/purple/gray), icon (text), sort_order (number), is_active (checkbox)
   - validationRules(): name unique, max_duration_minutes integer mínimo 1
2. Adicione a rota em routes/tenant-routes.php no grupo /config protegido por MANAGE_TURN_LIST_BREAK_TYPES, seguindo o padrão dos outros configs.
3. Registre a página no menu central via migration de seed (similar a 2026_04_25_900001_seed_turn_list_page_and_menu.php) para MANAGE_TURN_LIST_BREAK_TYPES.
4. Garanta que ao excluir um break_type referenciado por um break ativo (status='active'), o controller bloqueie ou faça soft-delete-style (campo is_active=false). Verifique a referência no Service ou via foreign key constraint check.
5. Não exponha esse config para SUPPORT/USER — só ADMIN/SUPER_ADMIN.
6. Escreva testes feature (3-5) seguindo o padrão de outros ConfigController tests: index lista, create OK, update OK, delete bloqueia se em uso, permission gating.

Detalhes do módulo TurnList em C:\Users\MSDEV\.claude\projects\C--xampp-htdocs-mercury-laravel\memory\turn_list_module.md.
```

---

## 2. Config Module — Outcomes

**Status**: permission `MANAGE_TURN_LIST_OUTCOMES` já definida e atribuída a SUPER_ADMIN/ADMIN. Sem UI nem rotas.

**Por que importa**: hoje há 10 outcomes fixos (Venda Realizada, Pesquisa, Preço, Tamanho/Modelo, etc) com flags `is_conversion` e `restore_queue_position`. Mudanças requerem migration.

**Esforço**: ~1 hora. Mesmo padrão do item 1.

**Prompt**:

```
Implemente o config module para gerenciar os outcomes (turn_list_attendance_outcomes) do módulo Lista da Vez. Siga o padrão dos config modules do projeto.

Passos:
1. Crie app/Http/Controllers/Config/TurnListAttendanceOutcomeController.php estendendo ConfigController. Defina:
   - modelClass(): App\Models\TurnListAttendanceOutcome
   - viewTitle(): "Resultados de Atendimento"
   - columns(): id, name, description, color (badge), icon (preview), is_conversion (badge "Conversão"), restore_queue_position (badge "Volta na vez"), sort_order, is_active
   - formFields(): name (text), description (textarea), color (select), icon (text), is_conversion (checkbox com hint "Conta como venda nos relatórios"), restore_queue_position (checkbox com hint "Consultora volta na posição original da fila ao finalizar"), sort_order (number), is_active (checkbox)
   - validationRules(): name unique
2. Rota em routes/tenant-routes.php no grupo /config protegido por MANAGE_TURN_LIST_OUTCOMES.
3. Registre página no menu central via migration de seed.
4. Bloqueie exclusão de outcomes referenciados por atendimentos finalizados (FK existe? Verificar no migration). Se sim, adotar soft-delete-style via is_active=false.
5. Tooltip/hint nos checkboxes de is_conversion e restore_queue_position explicando o impacto (afetam relatórios e algoritmo aheadCount).
6. Testes feature (3-5) similar ao item anterior.

Detalhes do módulo TurnList em C:\Users\MSDEV\.claude\projects\C--xampp-htdocs-mercury-laravel\memory\turn_list_module.md. Atenção especial à flag restore_queue_position que dispara o algoritmo aheadCount em TurnListAttendanceService::calculateAdjustedRestorePosition().
```

---

## 3. UI de Settings da Loja

**Status**: rota `PUT /turn-list/settings` (toggle `return_to_position`) existe e tem teste cobrindo. Sem UI.

**Por que importa**: a configuração precisa ser feita via DB ou Tinker hoje. Gerente de loja não consegue ajustar.

**Esforço**: ~30 min. JSX simples.

**Prompt**:

```
Adicione UI para a configuração turn_list_store_settings.return_to_position no módulo Lista da Vez. A rota PUT /turn-list/settings já existe e funciona — só falta a interface.

Passos:
1. No componente resources/js/Pages/TurnList/Index.jsx, adicione um botão de "Configurações" no header (ou floating actions) visível apenas quando permissions.manage === true. Pode usar um ícone Cog6ToothIcon (Heroicons).
2. Ao clicar, abra um StandardModal com:
   - Título "Configurações da Loja"
   - Subtítulo: "Loja: {storeCode}"
   - Toggle (input checkbox estilizado) para "Voltar à posição original após pausa" — vinculado a storeSetting?.return_to_position vindo das props
   - Hint textual explicando: "Quando ativado, consultoras retornam à posição original na fila ao finalizar uma pausa. Quando desativado, vão para o final da fila."
   - Footer com Cancelar e Salvar
3. Ao salvar, faça window.axios.put(route('turn-list.settings.update'), { store_code, return_to_position }) e em sucesso feche o modal e mostre toast (use o padrão react-toastify do projeto). Em erro 422, mostre a mensagem do banner.
4. Garanta que o storeSetting vindo das props seja atualizado após o save (via fetchBoard ou refetch específico das settings).
5. O botão e o modal só aparecem para usuários com MANAGE_TURN_LIST (já passado em props.permissions.manage).

Não precisa criar novos endpoints — só consumir o existente. Detalhes em C:\Users\MSDEV\.claude\projects\C--xampp-htdocs-mercury-laravel\memory\turn_list_module.md.
```

---

## 4. Notificação de Pausa Excedida

**Status**: card vira vermelho na UI quando `elapsed > max_duration_minutes`, mas nada notifica supervisor.

**Por que importa**: gerentes não percebem pausas excedidas até olharem o painel. Outros módulos (Travel Expenses, Helpdesk, Coupons) usam padrão Event + Listener auto-discovered + WhatsApp/database notification.

**Esforço**: ~3-4 horas. Inclui Job, Event, Listener, Notification, command agendado, testes.

**Prompt**:

```
Implemente notificação de pausa excedida no módulo Lista da Vez seguindo o padrão Event/Listener auto-discovered do Laravel 12 e os padrões dos módulos Travel Expenses e Helpdesk.

Contexto: cada turn_list_break tem `started_at` e um `break_type_id` que aponta pra um break_type com `max_duration_minutes`. Considera-se "excedida" quando elapsed_seconds > max * 60 e a pausa ainda está ativa (status='active').

Passos:
1. Crie app/Console/Commands/TurnListBreakExceededAlertCommand.php com signature 'turn-list:break-exceeded-alert {--threshold=5 : Minutos de tolerância adicional antes de alertar}'. O command:
   - Itera por todos os tenants (Tenant::all() + ->run())
   - Em cada tenant, busca turn_list_breaks active com started_at + (max_duration_minutes + threshold) * 60 < now()
   - Para cada match, dispara o evento App\Events\TurnListBreakExceeded com $break
   - Idempotência: adicionar campo `alerted_at` (datetime nullable) na tabela turn_list_breaks via migration tenant nova; só dispara se alerted_at IS NULL e marca após dispatch.
2. Migration tenant: 2026_04_27_700001_add_alerted_at_to_turn_list_breaks.php — adiciona coluna alerted_at TIMESTAMP NULL.
3. Crie app/Events/TurnListBreakExceeded.php com $break (TurnListBreak instance) — typed e auto-discoverable.
4. Crie app/Listeners/SendTurnListBreakExceededNotification.php com handle(TurnListBreakExceeded $event):
   - Resolve usuários com MANAGE_TURN_LIST da mesma loja (store_code) — cuidado com central permissions: usar mesmo padrão de Helpdesk (CentralRoleResolver ou query direto).
   - Envia notification database (Laravel Notification class App\Notifications\TurnListBreakExceededNotification) — title "Pausa excedida", body "{employee_name} está em {break_type_name} há {elapsed_minutes}min ({max} permitidos)", action_url para /turn-list?store={store_code}.
   - NÃO registrar Event::listen manualmente — auto-discovery do Laravel 12 cobre. Confirme que app/Listeners/ tem o handler com type-hint correto pra evitar dupla execução (gotcha já documentado em Coupons).
5. Schedule em routes/console.php: roda everyFiveMinutes() com withoutOverlapping().
6. Testes feature: scanTenant pega break excedido, marca alerted_at, dispara evento; pula breaks já alertados; pula breaks dentro do limite; pula breaks finalizados.
7. Considerar (mas não implementar agora) WhatsApp via integration existente — tem que ver se o projeto tem helper genérico.

Detalhes do módulo em C:\Users\MSDEV\.claude\projects\C--xampp-htdocs-mercury-laravel\memory\turn_list_module.md. Padrão de Event/Listener auto-discovered em coupons_module.md (com gotcha do Event::listen duplicado).
```

---

## 5. Hook Helpdesk (paralelo aos outros módulos)

**Status**: não solicitado — observação. Padrão observado em Travel Expenses (rejeição abre ticket Financeiro), Returns/Reversals.

**Por que importa**: gerente de loja teria visibilidade contínua de problemas de comportamento. Pausa repetidamente excedida (3+ vezes na semana) poderia abrir ticket no depto "Departamento Pessoal" automaticamente.

**Esforço**: ~2 horas. Reusa padrão dos outros módulos.

**Prompt**:

```
Adicione hook Helpdesk fail-safe ao módulo Lista da Vez seguindo o padrão de Travel Expenses, Reversal e Returns.

Regra de negócio: se a mesma consultora tiver 3+ pausas excedidas na mesma semana, abre ticket automaticamente no departamento "Departamento Pessoal" com categoria "Comportamento" (ou similar — verificar quais categorias existem nesse depto).

Passos:
1. Reaproveitar o evento TurnListBreakExceeded criado no item 4 do backlog. Adicionar listener separado App\Listeners\OpenHelpdeskTicketForRepeatedBreakExceeded:
   - Conta breaks excedidos da mesma employee_id na semana corrente (Carbon::now()->startOfWeek())
   - Se >= 3 e ainda não há ticket Helpdesk ativo (helpdesk_ticket_id na tabela), abre ticket
   - Idempotente: adicionar campo helpdesk_ticket_id (integer nullable) em turn_list_breaks via migration tenant
2. 3 camadas de fail-safe (mesmo padrão do TravelExpenses):
   - Módulo Helpdesk não instalado → log + skip silencioso
   - Departamento "Departamento Pessoal" não existe → log + skip
   - Erro inesperado → try/catch + log
3. Ticket payload: subject "Pausas excedidas recorrentes - {employee_name}", description com lista de breaks excedidos da semana, priority MEDIUM, requester = gerente da loja com MANAGE_TURN_LIST.
4. Não registrar Event::listen manualmente — auto-discovery cobre.
5. Testes (4-5): hook abre ticket no 3o break excedido, idempotente (não duplica), fail-safe quando módulo não instalado, fail-safe quando depto não existe.

Detalhes em turn_list_module.md. Padrão fail-safe em travel_expenses_module.md.
```

---

## 6. Realtime via Reverb (em vez de polling 30s)

**Status**: hoje todos os usuários da mesma loja fazem fetch a cada 30s. Reverb já configurado em `bootstrap.js` (echo + pusher).

**Por que importa**: latência (até 30s) entre uma ação de uma vendedora e a propagação para outros tablets na mesma loja. Carga de N requests/30s cresce com tablets simultâneos.

**Esforço**: ~4-6 horas. Inclui events broadcastable, canal de presença, frontend listener.

**Prompt**:

```
Migre o módulo Lista da Vez de polling 30s para realtime via Reverb. Reverb já está configurado em resources/js/bootstrap.js (window.Echo).

Passos:
1. Crie eventos broadcastable em app/Events/:
   - TurnListBoardChanged($storeCode) — disparado por qualquer mutação que afete o board (enter, leave, reorder, start/finish attendance, start/finish break, settings update). Implementa ShouldBroadcastNow.
   - broadcastOn(): new PresenceChannel("turn-list.{$this->storeCode}") — canal de presença para saber quantas conexões da loja estão abertas.
   - broadcastAs(): 'board.changed'.
2. Em routes/channels.php, defina autorização do canal:
   - Broadcast::channel('turn-list.{storeCode}', function (User $user, string $storeCode) { return $user->hasPermissionTo(Permission::VIEW_TURN_LIST->value) && ($user->hasPermissionTo(Permission::MANAGE_TURN_LIST->value) || $user->store_id === $storeCode); });
3. No TurnListController, dispare o evento ao final de cada mutação (após $this->respondOk):
   - enterQueue, leaveQueue, reorderQueue, startAttendance, finishAttendance, startBreak, finishBreak, updateSettings.
   - Usar event(new TurnListBoardChanged($storeCode)) ou broadcast() helper.
4. No frontend resources/js/Pages/TurnList/Index.jsx:
   - Substituir setInterval(fetchBoard, 30000) por subscription Echo:
     useEffect(() => {
       if (!storeCode || !window.Echo) return;
       const channel = window.Echo.join(`turn-list.${storeCode}`)
         .listen('.board.changed', () => fetchBoard())
         .here(...).joining(...).leaving(...) // opcional: contar quem tá online
       return () => window.Echo.leave(`turn-list.${storeCode}`);
     }, [storeCode, fetchBoard]);
   - Manter o tick 1Hz para timers locais.
   - Como fallback (em caso de Reverb não disponível), manter um polling preguiçoso de ~120s? Ou só remover.
5. Testes:
   - Backend: TurnListBoardChanged é dispatched em cada endpoint (use Event::fake()). Channel auth: USER da própria loja autoriza, USER de outra loja é negado, MANAGE autoriza qualquer.
   - Frontend: difícil testar em PHPUnit; documentar como testar manual (abrir 2 abas, fazer mudança em uma, ver a outra atualizar instantaneamente).
6. Manter optimistic UI no lado de quem disparou a ação. O broadcast atualiza os outros tablets.

Detalhes em turn_list_module.md. Sobre Echo/Reverb: usar window.Echo (já configurado em bootstrap.js). Padrão de ShouldBroadcastNow vs ShouldBroadcast: usar Now pra evitar delay de queue worker.
```

---

## 7. Backlog técnico menor

### 7a. Anti-flicker no drag rapidíssimo

**Status**: drag de 2 cards em <500ms pode ter brief sobreposição quando o `fetchBoard()` do primeiro chega depois do segundo optimistic update.

**Esforço**: ~1-2 horas.

**Prompt**:

```
Mitigue o flicker no drag-drop rapidíssimo do módulo Lista da Vez. Hoje, se o usuário arrasta 2 cards em <500ms, o segundo setBoard(prev => ...) é construído sobre estado otimista do primeiro, mas o fetchBoard() do primeiro pode chegar depois e brevemente sobrepor.

Solução: refatorar o estado do board para useReducer com queue de mutações pendentes.

Passos:
1. Crie um reducer em resources/js/Pages/TurnList/Index.jsx (ou em um hook custom):
   - state: { board, pendingMutations: [{ id, optimisticState }] }
   - actions: APPLY_OPTIMISTIC (push pending + atualizar board), CONFIRM_MUTATION (remove pending), SERVER_UPDATE (substitui board mas reaplica pending mutations sobre o estado servidor)
2. Em apiPost(), gere um id único pra cada mutation. Push pending. Em sucesso, remove do queue. Em fetchBoard, mescla server state com pending mutations restantes.
3. Não aplique fetchBoard() entre mutations pendentes (use uma flag isProcessing).

Validar: simular 2 drags rápidos com cy.click() ou jest fake timers. Confirmar UI consistente.
```

### 7b. Endpoint JSON puro para integrações externas

**Status**: existe `/turn-list/board` JSON, mas é gated por sessão Laravel. Apps externos (TV de parede da loja com display HTMX/Vue?) não têm como consumir.

**Esforço**: ~2 horas (rota API + token Sanctum + recursos).

**Prompt**:

```
Adicione endpoint API REST público (com auth via Sanctum token) para o board do módulo Lista da Vez, permitindo consumo por dashboards externos (ex.: TV de parede da loja).

Passos:
1. Crie rota em routes/api.php (ou tenant-api.php se existir):
   - GET /api/turn-list/board?store={code} → retorna mesmo payload do TurnListController::board, mas em API resource
   - Auth: middleware('auth:sanctum') + permission VIEW_TURN_LIST
2. Crie App\Http\Resources\TurnListBoardResource para serializar a resposta com schema versionado (incluir 'api_version' field).
3. Crie um Controller dedicado App\Http\Controllers\Api\TurnListApiController que reusa TurnListBoardService.
4. Sanctum tokens criáveis via /admin com scope "turn-list.read" — admin gera um token por loja/dispositivo.
5. Rate limit razoável: 60 requests/min por token.
6. Testes feature: token válido + permission OK retorna board, token sem permission retorna 403, rate limit funciona, store inválido retorna 422.

Detalhes em turn_list_module.md. Para Sanctum: ver como Helpdesk ou Travel Expenses fazem (se já tiverem).
```

---

## Resumo de prioridade prática

| # | Item | Esforço | Valor |
|---|---|---|---|
| 3 | UI de Settings | 30min | Médio (gerente consegue configurar) |
| 1 | Config Break Types | 1h | Médio (flexibilidade pra empresa) |
| 2 | Config Outcomes | 1h | Médio |
| 4 | Notification pausa excedida | 3-4h | **Alto** (supervisão real) |
| 6 | Reverb realtime | 4-6h | **Alto** (UX colaborativo) |
| 5 | Hook Helpdesk | 2h | Baixo-médio |
| 7a | Anti-flicker drag | 1-2h | Baixo (caso raro) |
| 7b | API externa | 2h | Baixo (sem demanda concreta) |
