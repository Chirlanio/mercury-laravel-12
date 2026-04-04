# Plano de Acao - Lista da Vez: Intervalo e Almoco

**Data:** 06/03/2026
**Modulo:** Lista da Vez (Turn List)
**Feature:** Pausa para Intervalo e Almoco

---

## 1. Resumo Executivo

Adicionar ao modulo Lista da Vez a capacidade de consultoras sinalizarem que estao em **Intervalo** (pausa curta, ~15min) ou **Almoco** (pausa longa, ~60min), preservando sua posicao na fila de espera e exibindo um 4o painel visual no board.

### Decisoes de Projeto

| Aspecto | Decisao |
|---------|---------|
| **Posicao na fila** | Preservada — ao voltar, retorna na mesma posicao (ajustada) |
| **Exibicao visual** | Novo painel "Em Pausa" com cores distintas |
| **Limite de tempo** | Intervalo: 15min, Almoco: 60min — alerta visual ao exceder |
| **Permissao** | Consultora pode se pausar + Gestor pode pausar qualquer uma |

---

## 2. Arquitetura Atual (Antes)

### Estados da Consultora

```
┌─────────────┐     ┌──────────────┐     ┌──────────────────┐
│  Disponivel │────→│  Na Fila     │────→│  Em Atendimento  │
│  (sem fila) │←────│  (posicao N) │←────│  (timer ativo)   │
└─────────────┘     └──────────────┘     └──────────────────┘
```

### Tabelas Envolvidas

| Tabela | Funcao |
|--------|--------|
| `ldv_waiting_queue` | Fila de espera (employee_id, position, entered_at) |
| `ldv_attendances` | Atendimentos (status_id: 1=andamento, 2=finalizado) |
| `ldv_attendance_status` | Situacoes do atendimento (2 registros) |
| `ldv_attendance_outcomes` | Desfechos do atendimento (8 registros) |
| `ldv_attendance_history` | Historico diario agregado |

---

## 3. Arquitetura Proposta (Depois)

### Novo Fluxo de Estados

```
                          ┌────────────────┐
                     ┌───→│   EM PAUSA     │───┐
                     │    │ (intervalo ou   │   │
                     │    │  almoco, timer) │   │
                     │    └────────────────┘   │
                     │         ↑     │          │
                     │    pausar│     │voltar    │
                     │         │     ↓          │
┌─────────────┐     ┌──────────────┐     ┌──────────────────┐
│  Disponivel │────→│  Na Fila     │────→│  Em Atendimento  │
│  (sem fila) │←────│  (posicao N) │←────│  (timer ativo)   │
└─────────────┘     └──────────────┘     └──────────────────┘
```

### Regras de Transicao

| De | Para | Acao | Efeito na Fila |
|----|------|------|----------------|
| Na Fila | Em Pausa | Clicar "Intervalo" ou "Almoco" | Sai da fila, guarda `original_position` |
| Em Pausa | Na Fila | Clicar "Voltar" | Retorna na posicao original (ajustada) |
| Em Pausa | Disponivel | Clicar "Sair da Pausa" (sem voltar a fila) | Nao retorna a fila |
| Disponivel | Em Pausa | **NAO PERMITIDO** — precisa estar na fila primeiro |
| Em Atendimento | Em Pausa | **NAO PERMITIDO** — precisa finalizar atendimento primeiro |

---

## 4. Banco de Dados

### 4.1 Nova Tabela: `ldv_breaks`

```sql
CREATE TABLE ldv_breaks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    store_id VARCHAR(10) NOT NULL,
    break_type_id TINYINT UNSIGNED NOT NULL COMMENT '1=Intervalo, 2=Almoco',
    original_queue_position INT UNSIGNED NOT NULL COMMENT 'Posicao na fila antes da pausa',
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL DEFAULT NULL,
    duration_seconds INT UNSIGNED NULL DEFAULT NULL,
    status_id TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=Ativo, 2=Finalizado',
    created_by_user_id INT NOT NULL,
    updated_by_user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_employee_active (employee_id, status_id),
    INDEX idx_store_status (store_id, status_id),
    INDEX idx_started_at (started_at),

    CONSTRAINT fk_break_employee FOREIGN KEY (employee_id)
        REFERENCES adms_employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_break_type FOREIGN KEY (break_type_id)
        REFERENCES ldv_break_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 Nova Tabela: `ldv_break_types`

```sql
CREATE TABLE ldv_break_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL,
    max_duration_minutes INT UNSIGNED NOT NULL COMMENT 'Tempo maximo antes do alerta',
    color_class VARCHAR(20) NOT NULL COMMENT 'Classe Bootstrap para cor',
    icon VARCHAR(30) NOT NULL COMMENT 'Icone FontAwesome',
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ldv_break_types (id, name, max_duration_minutes, color_class, icon) VALUES
(1, 'Intervalo', 15, 'info', 'fas fa-coffee'),
(2, 'Almoço',    60, 'warning', 'fas fa-utensils');
```

### 4.3 Nova View: `vw_ldv_active_breaks`

```sql
CREATE OR REPLACE VIEW vw_ldv_active_breaks AS
SELECT
    b.id AS break_id,
    b.employee_id,
    b.store_id,
    b.break_type_id,
    bt.name AS break_type_name,
    bt.max_duration_minutes,
    bt.color_class,
    bt.icon,
    b.original_queue_position,
    b.started_at,
    TIMESTAMPDIFF(SECOND, b.started_at, NOW()) AS elapsed_seconds,
    TIMESTAMPDIFF(MINUTE, b.started_at, NOW()) AS elapsed_minutes,
    CASE
        WHEN TIMESTAMPDIFF(MINUTE, b.started_at, NOW()) > bt.max_duration_minutes
        THEN 1 ELSE 0
    END AS is_exceeded,
    e.name_employee,
    SUBSTRING_INDEX(e.name_employee, ' ', 1) AS short_name,
    e.user_image,
    l.nome AS store_name
FROM ldv_breaks b
JOIN ldv_break_types bt ON bt.id = b.break_type_id
JOIN adms_employees e ON e.id = b.employee_id
JOIN tb_lojas l ON l.id = b.store_id
WHERE b.status_id = 1;
```

### 4.4 Atualizar View: `vw_ldv_available_employees`

Adicionar exclusao de consultoras em pausa:

```sql
-- Consultoras disponiveis = NAO na fila AND NAO atendendo AND NAO em pausa
CREATE OR REPLACE VIEW vw_ldv_available_employees AS
SELECT e.id, e.name_employee, e.user_image, e.adms_store_id AS store_id, l.nome AS store_name
FROM adms_employees e
LEFT JOIN tb_lojas l ON l.id = e.adms_store_id
WHERE e.position_id = 1
  AND e.adms_status_employee_id = 2
  AND e.id NOT IN (SELECT employee_id FROM ldv_waiting_queue)
  AND e.id NOT IN (SELECT employee_id FROM ldv_attendances WHERE status_id = 1)
  AND e.id NOT IN (SELECT employee_id FROM ldv_breaks WHERE status_id = 1)  -- NOVO
ORDER BY e.name_employee;
```

---

## 5. Backend — Arquivos a Criar/Modificar

### 5.1 Novo Controller: `BreakConsultant.php`

```
app/adms/Controllers/BreakConsultant.php
```

**Metodos:**

| Metodo | HTTP | Acao |
|--------|------|------|
| `start()` | POST | Iniciar pausa (recebe employee_id, break_type_id) |
| `finish()` | POST | Finalizar pausa (recebe break_id ou employee_id, return_to_queue) |
| `active()` | GET | Listar pausas ativas da loja |

**Fluxo `start()`:**
1. Validar: consultora ativa, na fila, nao atendendo, nao em pausa
2. Guardar `original_queue_position` da consultora
3. Remover da `ldv_waiting_queue` (reordenar posicoes)
4. Inserir em `ldv_breaks` com status_id=1
5. Log: `BREAK_STARTED`
6. Retornar JSON sucesso

**Fluxo `finish()`:**
1. Validar: pausa ativa existe
2. Calcular `duration_seconds`
3. Atualizar `ldv_breaks`: status_id=2, finished_at, duration_seconds
4. Se `return_to_queue=1`:
   - Calcular posicao ajustada (mesma logica de `AdmsAttendance::finish`)
   - `AdmsWaitingQueue::enterAtPosition(employee_id, store_id, adjusted_position)`
5. Log: `BREAK_FINISHED`
6. Retornar JSON sucesso

### 5.2 Novo Model: `AdmsBreak.php`

```
app/adms/Models/AdmsBreak.php
```

**Metodos:**

| Metodo | Descricao |
|--------|-----------|
| `start(int $employeeId, string $storeId, int $breakTypeId): bool` | Inicia pausa |
| `finish(int $breakId, bool $returnToQueue = true): bool` | Finaliza pausa |
| `getActiveByEmployee(int $employeeId): ?array` | Pausa ativa de um funcionario |
| `getActiveByStore(?string $storeId): array` | Todas as pausas ativas da loja |
| `isOnBreak(int $employeeId): bool` | Verifica se esta em pausa |
| `getBreakTypes(): array` | Retorna tipos de pausa ativos |
| `getResult(): mixed` | Resultado da operacao |
| `getError(): ?string` | Mensagem de erro |

### 5.3 Modificar Model: `AdmsTurnListBoard.php`

**Alteracoes:**

1. **`getBoardData()`** — adicionar 4o painel:
```php
public function getBoardData(?string $storeId = null): array
{
    return [
        'available' => $this->getAvailableEmployees($storeId),
        'queue' => $this->getQueueEmployees($storeId),
        'attending' => $this->getAttendingEmployees($storeId),
        'on_break' => $this->getOnBreakEmployees($storeId),  // NOVO
    ];
}
```

2. **Novo metodo `getOnBreakEmployees()`:**
```php
public function getOnBreakEmployees(?string $storeId = null): array
{
    // Query vw_ldv_active_breaks com filtro de loja
    // Retorna: employee data + break_type, elapsed, is_exceeded
}
```

3. **`getAvailableEmployees()`** — excluir consultoras em pausa (via view atualizada)

4. **`getCounts()`** — adicionar contagem de pausas:
```php
return [
    'total' => ...,
    'in_queue' => ...,
    'attending' => ...,
    'on_break' => $breakCount,  // NOVO
    'available' => $total - $inQueue - $attending - $onBreak,  // AJUSTADO
];
```

### 5.4 Modificar Model: `AdmsWaitingQueue.php`

**Alteracoes:**

1. **`enter()`** — adicionar validacao: consultora nao pode estar em pausa
```php
// Verificar se esta em pausa
$break = new AdmsBreak();
if ($break->isOnBreak($employeeId)) {
    $this->error = 'Consultora está em pausa. Finalize a pausa primeiro.';
    return false;
}
```

### 5.5 Modificar Model: `AdmsQueueSessionManager.php`

**Alteracoes:**

1. **`initializeSession()`** — limpar pausas expiradas:
```php
// Finalizar pausas de dias anteriores
// Finalizar pausas > 12 horas
$this->cleanupExpiredBreaks($storeId);
```

### 5.6 Modificar Controller: `TurnList.php`

**Alteracoes:**

1. **`board()`** — passar dados de pausa para a view:
```php
$this->data['board'] = $boardModel->getBoardData($effectiveStoreId);
$this->data['break_types'] = $breakModel->getBreakTypes();
```

2. **`loadButtons()`** — adicionar permissoes de pausa:
```php
$buttonsConfig['break_start'] = ['menu_controller' => 'break-consultant', 'menu_metodo' => 'start'];
$buttonsConfig['break_finish'] = ['menu_controller' => 'break-consultant', 'menu_metodo' => 'finish'];
```

3. **`getStats()`** — incluir contagem de pausas

### 5.7 Registrar Rotas

```sql
-- Paginas do controller de pausa
INSERT INTO adms_paginas (menu_controller, menu_metodo, nome_pagina, adms_sits_pg_id, adms_tp_pg_id)
VALUES
('break-consultant', 'start', 'Iniciar Pausa Consultora', 1, 2),
('break-consultant', 'finish', 'Finalizar Pausa Consultora', 1, 2),
('break-consultant', 'active', 'Ver Pausas Ativas', 1, 2);

-- Permissoes (niveis: 1=SuperAdmin, 2=Admin, 3=Suporte, 5=Loja, 18=Gerente, 19=Vendedor, 20=Level20)
INSERT INTO adms_nivacs_pgs (adms_pagina_id, adms_niveis_acesso_id, permissao)
SELECT p.id, n.id, 1
FROM adms_paginas p
CROSS JOIN (SELECT id FROM adms_niveis_acessos WHERE id IN (1, 2, 3, 5, 18, 19, 20)) n
WHERE p.menu_controller = 'break-consultant';
```

---

## 6. Frontend

### 6.1 Modificar View: `boardTurnList.php`

**Adicionar 4o painel "Em Pausa":**

```html
<!-- Painel Em Pausa -->
<div class="col-md-6 col-lg-3 mb-3">
    <div class="card border-info">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-pause-circle mr-1"></i> Em Pausa</span>
            <span class="badge badge-light"><?= count($board['on_break']) ?></span>
        </div>
        <div class="card-body p-2" id="panel-on-break" style="min-height: 200px; max-height: 70vh; overflow-y: auto;">
            <?php if (empty($board['on_break'])): ?>
                <p class="text-muted text-center mt-3"><i class="fas fa-check-circle"></i> Nenhuma pausa ativa</p>
            <?php else: ?>
                <?php foreach ($board['on_break'] as $emp): ?>
                    <div class="card mb-2 <?= $emp['is_exceeded'] ? 'border-danger' : '' ?>"
                         data-employee-id="<?= $emp['employee_id'] ?>">
                        <div class="card-body p-2">
                            <div class="d-flex align-items-center">
                                <!-- Avatar -->
                                <div class="mr-2">
                                    <!-- avatar circle/image -->
                                </div>
                                <div class="flex-grow-1">
                                    <strong><?= htmlspecialchars($emp['short_name']) ?></strong>
                                    <br>
                                    <small>
                                        <span class="badge badge-<?= $emp['color_class'] ?>">
                                            <i class="<?= $emp['icon'] ?>"></i>
                                            <?= htmlspecialchars($emp['break_type_name']) ?>
                                        </span>
                                        <span class="break-timer <?= $emp['is_exceeded'] ? 'text-danger font-weight-bold' : 'text-muted' ?>"
                                              data-started="<?= $emp['elapsed_seconds'] ?>">
                                            <?= floor($emp['elapsed_minutes']) ?>min
                                        </span>
                                        <?php if ($emp['is_exceeded']): ?>
                                            <i class="fas fa-exclamation-triangle text-danger ml-1"
                                               title="Tempo excedido!"></i>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-success btn-finish-break"
                                            data-break-id="<?= $emp['break_id'] ?>"
                                            data-employee-id="<?= $emp['employee_id'] ?>"
                                            title="Voltar à Fila">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
```

**Modificar layout dos paineis:**
- Antes: 3 paineis (col-md-6 col-lg-4 cada)
- Depois: 4 paineis (col-md-6 col-lg-3 cada)

**Adicionar botoes de pausa nos cards da Fila:**
```html
<!-- No card de cada consultora na fila -->
<div class="btn-group btn-group-sm">
    <button class="btn btn-info btn-start-break" data-type="1" title="Intervalo">
        <i class="fas fa-coffee"></i>
    </button>
    <button class="btn btn-warning btn-start-break" data-type="2" title="Almoço">
        <i class="fas fa-utensils"></i>
    </button>
</div>
```

### 6.2 Modificar View: `loadTurnList.php`

**Atualizar cards de estatisticas (de 4 para 5):**

```
┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐
│   Total    │ │  Na Fila   │ │ Atendendo  │ │  Em Pausa  │ │ Disponivel │
│     12     │ │     4      │ │     2      │ │     1      │ │     5      │
└────────────┘ └────────────┘ └────────────┘ └────────────┘ └────────────┘
```

- Card "Em Pausa": cor `info`, icone `fa-pause-circle`
- Grid: `col-6 col-sm-4 col-md-6 col-lg` (auto-ajuste para 5 cards)

### 6.3 Modificar JS: `turn-list.js`

**Novas funcoes:**

```javascript
// Iniciar pausa
async function startBreak(employeeId, breakTypeId) {
    const response = await fetch(urlBase + 'break-consultant/start', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `employee_id=${employeeId}&break_type_id=${breakTypeId}&${csrfParam}`
    });
    const data = await response.json();
    if (data.success) {
        showNotification('success', data.message);
        refreshBoard();
    } else {
        showNotification('error', data.error);
    }
}

// Finalizar pausa (voltar a fila)
async function finishBreak(breakId, returnToQueue = true) {
    const response = await fetch(urlBase + 'break-consultant/finish', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `break_id=${breakId}&return_to_queue=${returnToQueue ? 1 : 0}&${csrfParam}`
    });
    const data = await response.json();
    if (data.success) {
        showNotification('success', data.message);
        refreshBoard();
    } else {
        showNotification('error', data.error);
    }
}
```

**Modificar `initializeTimers()`:**
- Adicionar timers para pausas ativas
- Colorir timer em vermelho quando exceder limite

**Modificar event delegation:**
```javascript
// Delegacao de eventos para botoes de pausa
document.addEventListener('click', function(e) {
    const btnBreak = e.target.closest('.btn-start-break');
    if (btnBreak) {
        const employeeId = btnBreak.closest('[data-employee-id]').dataset.employeeId;
        const breakType = btnBreak.dataset.type;
        startBreak(employeeId, breakType);
    }

    const btnFinish = e.target.closest('.btn-finish-break');
    if (btnFinish) {
        finishBreak(btnFinish.dataset.breakId);
    }
});
```

---

## 7. Logica de Restauracao de Posicao

### Cenario Detalhado

```
Estado inicial da fila:
  #1 Ana
  #2 Maria
  #3 Joao
  #4 Paula

Maria (#2) entra em Almoco:
  → Maria sai da fila, guarda original_position = 2
  → Fila reordena: Ana (#1), Joao (#2), Paula (#3)

Enquanto Maria esta no almoco, Joao (#2) inicia atendimento:
  → Fila: Ana (#1), Paula (#2)

Maria volta do almoco (return_to_queue = true):
  → Posicao original era #2
  → Contar quantas consultoras ANTES dela (posicao < 2) tambem sairam: Joao saiu (pos original era #3, NAO estava antes)
  → Nenhuma antes dela saiu → posicao ajustada = 2
  → Resultado: Ana (#1), Maria (#2), Paula (#3)
```

### Algoritmo

```php
private function calculateAdjustedPosition(int $originalPosition, string $storeId): int
{
    // Contar quantas posicoes ficaram vagas ANTES da posicao original
    $queue = new AdmsWaitingQueue();
    $currentCount = $queue->getCount($storeId);

    // Garantir que a posicao nao exceda o tamanho atual + 1
    $adjustedPosition = min($originalPosition, $currentCount + 1);

    // Garantir posicao minima de 1
    return max(1, $adjustedPosition);
}
```

---

## 8. Alertas de Tempo Excedido

### Visual (Frontend)

Quando o tempo da pausa excede o limite:

| Elemento | Normal | Excedido |
|----------|--------|----------|
| Card border | `border-info` ou `border-warning` | `border-danger` |
| Timer text | `text-muted` | `text-danger font-weight-bold` |
| Icone | Nenhum | `fa-exclamation-triangle text-danger` |
| Tooltip | — | "Tempo excedido! Intervalo: 15min / Almoco: 60min" |

### Verificacao (Backend)

A view `vw_ldv_active_breaks` ja calcula `is_exceeded` automaticamente:
```sql
CASE
    WHEN TIMESTAMPDIFF(MINUTE, b.started_at, NOW()) > bt.max_duration_minutes
    THEN 1 ELSE 0
END AS is_exceeded
```

**NAO ha retorno automatico** — apenas alerta visual. O gestor decide quando intervir.

---

## 9. Testes Unitarios

### Novo arquivo: `tests/TurnList/AdmsBreakTest.php`

| # | Teste | Descricao |
|---|-------|-----------|
| 1 | `testStartBreakFromQueue` | Consultora na fila inicia pausa com sucesso |
| 2 | `testStartBreakNotInQueue` | Rejeita pausa se consultora nao esta na fila |
| 3 | `testStartBreakAlreadyOnBreak` | Rejeita se ja esta em pausa |
| 4 | `testStartBreakWhileAttending` | Rejeita se esta atendendo |
| 5 | `testStartBreakInvalidType` | Rejeita tipo de pausa invalido |
| 6 | `testFinishBreakReturnToQueue` | Volta a fila na posicao correta |
| 7 | `testFinishBreakNoReturn` | Finaliza sem voltar a fila (fica disponivel) |
| 8 | `testPositionPreservation` | Posicao original preservada apos voltar |
| 9 | `testPositionAdjustment` | Posicao ajustada quando outros sairam |
| 10 | `testIsOnBreak` | Verifica status de pausa |
| 11 | `testGetActiveByStore` | Lista pausas ativas por loja |
| 12 | `testBreakCleanupExpired` | Limpeza de pausas expiradas (>12h) |
| 13 | `testQueueEnterBlockedDuringBreak` | Nao pode entrar na fila estando em pausa |
| 14 | `testAvailableExcludesOnBreak` | Painel disponiveis exclui quem esta em pausa |
| 15 | `testBreakDurationCalculation` | Calculo de duracao correto |

**Estimativa:** 15-20 testes

---

## 10. Arquivos Afetados

### Novos (5 arquivos)

| Arquivo | Tipo |
|---------|------|
| `app/adms/Controllers/BreakConsultant.php` | Controller |
| `app/adms/Models/AdmsBreak.php` | Model |
| `database/migrations/ldv_breaks.sql` | Migration |
| `tests/TurnList/AdmsBreakTest.php` | Testes |
| `docs/PLANO_ACAO_LISTA_DA_VEZ_PAUSA.md` | Este documento |

### Modificados (6 arquivos)

| Arquivo | Alteracao |
|---------|-----------|
| `app/adms/Controllers/TurnList.php` | Passar break_types, atualizar stats, botoes |
| `app/adms/Models/AdmsTurnListBoard.php` | Novo metodo `getOnBreakEmployees()`, ajustar contagens |
| `app/adms/Models/AdmsWaitingQueue.php` | Validacao: bloquear entrada se em pausa |
| `app/adms/Models/AdmsQueueSessionManager.php` | Limpar pausas expiradas |
| `app/adms/Views/turnList/boardTurnList.php` | 4o painel, botoes de pausa |
| `assets/js/turn-list.js` | Funcoes startBreak/finishBreak, timers, eventos |

### View principal (1 arquivo)

| Arquivo | Alteracao |
|---------|-----------|
| `app/adms/Views/turnList/loadTurnList.php` | 5o card estatistica "Em Pausa" |

---

## 11. Estimativa de Esforco

| Fase | Descricao | Horas |
|------|-----------|-------|
| 1 | Migration SQL (tabelas, views, dados) | 1h |
| 2 | Model `AdmsBreak.php` | 3h |
| 3 | Controller `BreakConsultant.php` | 2h |
| 4 | Modificar models existentes | 2h |
| 5 | View: 4o painel + botoes | 2h |
| 6 | JavaScript: funcoes + timers | 2h |
| 7 | Testes unitarios | 2h |
| 8 | Registrar rotas + permissoes | 0.5h |
| 9 | Testes manuais e ajustes | 1.5h |
| **Total** | | **16h** |

---

## 12. Criterios de Aceite

- [ ] Consultora na fila pode iniciar Intervalo ou Almoco
- [ ] Pausa remove da fila mas preserva posicao original
- [ ] 4o painel "Em Pausa" exibe consultoras com timer
- [ ] Timer fica vermelho ao exceder limite (15min/60min)
- [ ] "Voltar" retorna a consultora na posicao ajustada
- [ ] Consultora em pausa nao aparece como "Disponivel"
- [ ] Consultora em pausa nao pode entrar na fila novamente
- [ ] Gestor e consultora podem iniciar/finalizar pausa
- [ ] Estatisticas incluem contagem de pausas
- [ ] Limpeza automatica de pausas >12h
- [ ] 15+ testes unitarios passando
- [ ] Rotas e permissoes registradas no banco
- [ ] Zero regressoes no fluxo atual (fila, atendimento)

---

## 13. Riscos

| Risco | Probabilidade | Mitigacao |
|-------|---------------|-----------|
| Consultora esquece de voltar da pausa | Media | Alerta visual + gestor monitora |
| Posicao restaurada causa confusao | Baixa | Notificacao: "Maria voltou na posicao #2" |
| Muitas pausas simultaneas esvaziam a fila | Baixa | Visivel no painel, gestor controla |
| Drag-drop conflita com painel novo | Baixa | Painel de pausa nao suporta drag (somente botao) |

---

**Elaborado por:** Claude Code
**Status:** Aguardando aprovacao
