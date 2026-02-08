# Analise do Modulo de Usuarios Online

**Data:** 19 de Janeiro de 2026
**Versao:** 2.0
**Status:** Medio prazo concluido

---

## 1. Visao Geral

O modulo de Usuarios Online rastreia quais usuarios estao conectados ao sistema Mercury, registrando login/logout e permitindo visualizacao em tempo real.

### 1.1 Arquivos Atuais

```
app/adms/Controllers/UsersOnline.php     - Controller de listagem
app/adms/Controllers/Login.php           - Controller de login/logout
app/adms/Models/AdmsLogin.php            - Logica de login e tracking
app/adms/Models/AdmsListUsers.php        - Metodo usersOnline()
app/adms/Views/user/listUsersOnline.php  - View de listagem
```

### 1.2 Tabelas do Banco de Dados

```sql
-- Tabela principal de sessoes
adms_users_online (
    id, adms_user_id, hash_user_id, adms_store_id, adms_nivac_id,
    ip_access, adms_date_access, adms_hours_access,
    adms_date_logout, adms_hours_logout, adms_sit_access_id,
    created, modified
)

-- Tabela de status
adms_sit_access (
    id, name_sit, adms_cor_id
    -- 1 = Online, 2 = Offline
)
```

---

## 2. Comparacao com Padroes do Projeto

| Aspecto | Padrao Documentado | Implementacao Atual | Status |
|---------|-------------------|---------------------|--------|
| Controller | PascalCase | UsersOnline.php | OK |
| URL | /users-online/list | /users-online/list | OK |
| Model Listagem | AdmsListUsersOnline (dedicado) | AdmsListUsers::usersOnline() | Metodo em outro Model |
| Model Principal | AdmsUsersOnline | Nao existe (usa AdmsLogin) | Ausente |
| View Diretorio | usersOnline/ | user/ | Inconsistente |
| View Arquivo | loadUsersOnline.php + listUsersOnline.php | Apenas listUsersOnline.php | Incompleto |
| JavaScript | users-online.js | Nao existe | Ausente |
| Type Hints | Obrigatorio | Parcial | Incompleto |
| PHPDoc | Obrigatorio | Parcial | Incompleto |
| LoggerService | Obrigatorio para CRUD | Nao usado | Ausente |
| NotificationService | Obrigatorio | Usa $_SESSION['msg'] | Nao padronizado |

---

## 3. Problemas Identificados

### 3.1 Arquitetura

- **Responsabilidades misturadas**: AdmsLogin.php contem logica de tracking online
- **Model compartilhado**: usersOnline() esta em AdmsListUsers.php
- **View mal posicionada**: Esta em user/ em vez de usersOnline/
- **Sem JavaScript dedicado**: Nao ha arquivo JS para o modulo

### 3.2 Padronizacao

- **Sem type hints**: Propriedades sem tipagem (private $Dados)
- **Sem match expression**: Controller usa if/else em vez de match
- **Sem LoggerService**: Login/logout nao sao registrados
- **Usa $_SESSION['msg']**: Em vez de NotificationService

### 3.3 Funcionalidade

- **Sem heartbeat/ping**: Usuarios ficam "online" indefinidamente
- **Sem auto-logout**: Nao ha timeout de sessao
- **Sem filtros**: Nao permite filtrar por loja, data, etc.
- **Sem estatisticas**: Dashboard nao mostra metricas detalhadas

---

## 4. Plano de Acao

### 4.1 Curto Prazo (Quick Wins) - PRIORIDADE ALTA

| # | Tarefa | Arquivo | Status |
|---|--------|---------|--------|
| 1 | Adicionar LoggerService no login | AdmsLogin.php | Concluido |
| 2 | Adicionar LoggerService no logout | AdmsLogin.php | Concluido |
| 3 | Trocar $_SESSION['msg'] por NotificationService | AdmsLogin.php | Concluido |
| 4 | Adicionar type hints no Controller | UsersOnline.php | Concluido |
| 5 | Usar namespaces com use | UsersOnline.php | Concluido |

### 4.2 Medio Prazo (Refatoracao) - PRIORIDADE MEDIA

| # | Tarefa | Descricao | Status |
|---|--------|-----------|--------|
| 1 | Criar AdmsUsersOnline.php | Model dedicado com CRUD | Concluido |
| 2 | Criar AdmsListUsersOnline.php | Model dedicado para listagem | Concluido |
| 3 | Mover view para usersOnline/ | Reorganizar estrutura | Concluido |
| 4 | Implementar heartbeat | Ping a cada 1 minuto | Concluido |
| 5 | Adicionar filtros | Por loja, data, status | Concluido |
| 6 | Criar users-online.js | JavaScript dedicado | Concluido |

### 4.3 Longo Prazo (Novas Funcionalidades) - PRIORIDADE BAIXA

| # | Tarefa | Descricao |
|---|--------|-----------|
| 1 | Auto-logout por inatividade | Timeout configuravel |
| 2 | Dashboard de atividade | Graficos e metricas |
| 3 | Historico de sessoes | Relatorios de auditoria |
| 4 | Notificacoes real-time | Websocket |

---

## 5. Detalhamento Tecnico

### 5.1 Heartbeat (Medio Prazo)

**JavaScript (users-online.js):**
```javascript
// Ping a cada 60 segundos para manter status online
setInterval(() => {
    fetch(URLADM + 'users-online/ping', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
}, 60000);
```

**Controller (UsersOnline.php):**
```php
public function ping(): void
{
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        return;
    }

    $model = new AdmsUsersOnline();
    $model->updateLastActivity($_SESSION['usuario_id']);

    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'timestamp' => time()]);
}
```

**Model (AdmsUsersOnline.php):**
```php
public function updateLastActivity(int $userId): bool
{
    $update = new AdmsUpdate();
    $update->exeUpdate(
        'adms_users_online',
        ['last_activity' => date('Y-m-d H:i:s'), 'modified' => date('Y-m-d H:i:s')],
        'WHERE adms_user_id = :user_id AND adms_sit_access_id = 1',
        "user_id={$userId}"
    );
    return $update->getResult();
}
```

### 5.2 Modelo de Dados Sugerido

```sql
-- Adicionar campo last_activity para heartbeat
ALTER TABLE adms_users_online
ADD COLUMN last_activity DATETIME NULL AFTER adms_hours_access;

-- Adicionar indice para consultas de usuarios inativos
ALTER TABLE adms_users_online
ADD INDEX idx_last_activity (last_activity),
ADD INDEX idx_status_activity (adms_sit_access_id, last_activity);

-- Cleanup de sessoes abandonadas (cron job)
UPDATE adms_users_online
SET adms_sit_access_id = 2,
    adms_date_logout = CURDATE(),
    adms_hours_logout = CURTIME(),
    modified = NOW()
WHERE adms_sit_access_id = 1
AND last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE);
```

### 5.3 Estrutura de Arquivos Final

```
app/adms/Controllers/
    UsersOnline.php              # Controller principal (refatorado)

app/adms/Models/
    AdmsUsersOnline.php          # CRUD de sessoes online
    AdmsListUsersOnline.php      # Listagem com filtros
    AdmsStatisticsUsersOnline.php # Estatisticas (futuro)

app/adms/Views/usersOnline/
    loadUsersOnline.php          # Pagina principal
    listUsersOnline.php          # Lista AJAX
    partials/
        _filters_modal.php       # Modal de filtros
        _user_detail_modal.php   # Detalhes do usuario

assets/js/
    users-online.js              # JavaScript do modulo
```

---

## 6. Metricas de Sucesso

### 6.1 Precisao do Status Online
- **Antes**: Usuarios ficam "online" ate logout manual
- **Depois**: Status atualizado a cada 1 minuto, timeout de 5 minutos

### 6.2 Auditoria
- **Antes**: Sem registro de login/logout
- **Depois**: Todos os eventos registrados via LoggerService

### 6.3 Conformidade com Padroes
- **Antes**: ~40% de conformidade
- **Depois**: ~90% de conformidade

---

## 7. Riscos e Mitigacoes

| Risco | Probabilidade | Impacto | Mitigacao |
|-------|---------------|---------|-----------|
| Heartbeat sobrecarregar servidor | Media | Alto | Rate limiting, intervalo ajustavel |
| Usuarios perderem sessao | Baixa | Alto | Grace period antes de marcar offline |
| Incompatibilidade com chat | Media | Medio | Testar integracao com ChatService |

---

## 8. Historico de Alteracoes

| Data | Versao | Descricao | Autor |
|------|--------|-----------|-------|
| 2026-01-19 | 1.0 | Documento inicial | Claude |
| 2026-01-19 | 1.1 | Concluido curto prazo (LoggerService, NotificationService, type hints) | Claude |
| 2026-01-19 | 2.0 | Concluido medio prazo (Models, Views, Controller, JavaScript, Heartbeat) | Claude |

---

## 9. Referencias

- [REGRAS_DESENVOLVIMENTO.md](../.claude/REGRAS_DESENVOLVIMENTO.md)
- [CLAUDE.md](../.claude/CLAUDE.md)
- [LoggerService](../app/adms/Services/LoggerService.php)
- [NotificationService](../app/adms/Services/NotificationService.php)
