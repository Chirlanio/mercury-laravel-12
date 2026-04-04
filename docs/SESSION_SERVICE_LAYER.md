# Session Service Layer - Documentacao

**Data:** Marco 2026
**Issue:** #98

---

## Visao Geral

O projeto Mercury isolou todo acesso a `$_SESSION` atras de uma camada de services, centralizando o acesso em `SessionContext`, `PermissionService` e `AuthenticationService`.

### Objetivo

- Eliminar acesso direto a `$_SESSION` em Controllers, Models e Services
- Centralizar logica de sessao em um unico ponto
- Facilitar testes unitarios (mock via `setTestData()`)
- Melhorar type safety com metodos tipados

---

## Services Criados/Refatorados

### 1. SessionContext (NOVO)

**Arquivo:** `app/adms/Services/SessionContext.php`

Classe estatica que encapsula TODO acesso a `$_SESSION`.

```php
use App\adms\Services\SessionContext;

// Metodos tipados para chaves conhecidas
SessionContext::getUserId();          // ?int
SessionContext::getUserName();        // ?string
SessionContext::getUserEmail();       // ?string
SessionContext::getUserImage();       // ?string
SessionContext::getAccessLevel();     // int (default: 999)
SessionContext::getAccessLevelId();   // ?int
SessionContext::getUserStore();       // ?string
SessionContext::isLoggedIn();         // bool
SessionContext::mustChangePassword(); // bool

// Flash messages
SessionContext::setFlashMessage("mensagem");  // seta $_SESSION['msg']
SessionContext::getFlashMessage();             // le e remove $_SESSION['msg']

// Acesso generico
SessionContext::get('key', $default);  // leitura
SessionContext::set('key', $value);    // escrita
SessionContext::has('key');            // isset
SessionContext::remove('key');         // unset
```

### 2. PermissionService (REFATORADO)

**Arquivo:** `app/adms/Services/PermissionService.php`

Metodos estaticos para verificacao de permissoes.

```php
use App\adms\Services\PermissionService;

PermissionService::isSuperAdmin();      // ordem_nivac == 1
PermissionService::isAdmin();           // ordem_nivac <= ADMPERMITION
PermissionService::isSupport();         // ordem_nivac <= 3
PermissionService::isDp();              // ordem_nivac <= 7
PermissionService::isFinancial();       // ordem_nivac <= FINANCIALPERMITION
PermissionService::isStoreLevel();      // ordem_nivac >= STOREPERMITION
PermissionService::isDriver();          // ordem_nivac >= 22
PermissionService::getCurrentLevel();   // int
PermissionService::canAccessStore($id); // bool
PermissionService::getStoreFilter();    // ?string
PermissionService::buildStoreFilter();  // array{condition, paramString, paramPart}
```

### 3. AuthenticationService (COMPLETADO)

**Arquivo:** `app/adms/Services/AuthenticationService.php`

```php
use App\adms\Services\AuthenticationService;

AuthenticationService::isAuthenticated();    // bool
AuthenticationService::getCurrentUserId();   // int
AuthenticationService::getCurrentUserName(); // string
AuthenticationService::logout();             // void (session destroy completo)
AuthenticationService::mustChangePassword(); // bool
```

---

## Mapeamento de Substituicoes

| Antes (`$_SESSION`) | Depois (`SessionContext`) |
|---------------------|---------------------------|
| `$_SESSION['usuario_id']` | `SessionContext::getUserId()` |
| `$_SESSION['usuario_nome']` | `SessionContext::getUserName()` |
| `$_SESSION['usuario_email']` | `SessionContext::getUserEmail()` |
| `$_SESSION['usuario_imagem']` | `SessionContext::getUserImage()` |
| `$_SESSION['ordem_nivac']` | `SessionContext::getAccessLevel()` |
| `$_SESSION['adms_niveis_acesso_id']` | `SessionContext::getAccessLevelId()` |
| `$_SESSION['usuario_loja']` | `SessionContext::getUserStore()` |
| `$_SESSION['msg'] = "..."` | `SessionContext::setFlashMessage("...")` |
| `$_SESSION['msg']` (leitura) | `SessionContext::getFlashMessage()` |
| `isset($_SESSION['usuario_id'])` | `SessionContext::isLoggedIn()` |
| `$_SESSION['key']` (leitura) | `SessionContext::get('key')` |
| `$_SESSION['key'] = value` | `SessionContext::set('key', value)` |
| `isset($_SESSION['key'])` | `SessionContext::has('key')` |
| `unset($_SESSION['key'])` | `SessionContext::remove('key')` |

---

## Permissoes (Substituicoes Comuns)

| Antes | Depois |
|-------|--------|
| `$_SESSION['ordem_nivac'] <= FINANCIALPERMITION` | `PermissionService::isFinancial()` |
| `$_SESSION['ordem_nivac'] >= STOREPERMITION` | `PermissionService::isStoreLevel()` |
| `$_SESSION['ordem_nivac'] == 1` | `PermissionService::isSuperAdmin()` |
| `in_array($_SESSION['ordem_nivac'], [1, 2])` | `PermissionService::isAdmin()` |

---

## Testes Unitarios

### TestSessionHelper Trait

**Arquivo:** `tests/TestSessionHelper.php`

```php
class MeuTest extends TestCase
{
    use TestSessionHelper;

    protected function setUp(): void
    {
        $this->setUpAdminSession();  // Admin nivel 1
        // ou
        $this->setUpStoreSession('B002');  // Usuario loja
        // ou
        $this->setUpFinancialSession();  // Usuario financeiro
        // ou
        $this->setUpSessionData([...]);  // Dados customizados
    }

    protected function tearDown(): void
    {
        $this->tearDownSession();  // Limpa testData
    }
}
```

### Usando SessionContext diretamente

```php
// setUp
SessionContext::setTestData([
    'usuario_id' => 1,
    'ordem_nivac' => 1,
    'usuario_loja' => 'A001',
]);

// tearDown
SessionContext::resetTestData();
```

---

## Excecoes

Arquivos que AINDA usam `$_SESSION` diretamente:

1. **`core/Config.php`** - Bootstrap (session_start, inicializacao)
2. **`app/adms/Services/SessionContext.php`** - O proprio wrapper
3. **`app/adms/Services/CsrfService.php`** - Gerencia proprio token CSRF
4. **`app/adms/Services/SelectCacheService.php`** - flush()/stats() iteram $_SESSION
5. **Views** - `$_SESSION['msg']` para flash messages (padrao de leitura+display)
6. **`core/Api/`** - API endpoints com autenticacao propria

---

## Traits Atualizados

### StorePermissionTrait

Agora delega internamente para `PermissionService`:

```php
protected function isStoreRestricted(): bool
{
    return PermissionService::isStoreLevel();
}

protected function getStoreId(): ?string
{
    return PermissionService::getStoreFilter();
}
```

### FinancialPermissionTrait

Agora delega internamente para `PermissionService`:

```php
protected function isFinancialRestricted(): bool
{
    return PermissionService::isFinancialRestricted();
}

protected function getFinancialStoreFilter(): ?string
{
    return PermissionService::getFinancialStoreFilter();
}
```

---

## Estatisticas da Migracao

| Camada | Arquivos Migrados |
|--------|-------------------|
| Services (Fase 5) | ~15 |
| Controllers modernos (Fase 6) | ~420 |
| Controllers legados (Fase 8) | 81 |
| Models (Fases 6+8) | ~280 |
| Views (Fase 7) | ~68 (non-msg) |
| Helpers (Fase 8) | 17 |
| Core (Fase 4) | 1 |
| **Total** | **~880 arquivos** |

---

## Regras para Novos Modulos

1. **NUNCA** usar `$_SESSION` diretamente em Controllers, Models ou Services
2. **SEMPRE** usar `SessionContext` para acesso a dados de sessao
3. **SEMPRE** usar `PermissionService` para verificacoes de permissao
4. **SEMPRE** usar `NotificationService` para flash messages (nunca `$_SESSION['msg']` direto)
5. **Views** podem usar `$_SESSION['msg']` para exibicao de flash messages (padrao existente)
6. **Testes** devem usar `SessionContext::setTestData()` ou `TestSessionHelper` trait
