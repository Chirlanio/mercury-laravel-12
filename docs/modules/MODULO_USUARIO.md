# Análise Completa do Módulo de Usuário

**Data:** 27 de Dezembro de 2025
**Versão:** 1.0
**Base de Comparação:** Módulos Estorno, Ordem de Serviço, Horas Extras (Dezembro 2025)

---

## 📊 Sumário Executivo

### ✅ Pontos Fortes
- CSRF token implementado corretamente nas views
- JavaScript moderno com async/await
- Event delegation implementado
- Estrutura de modais bem organizada
- Responsividade adequada

### ⚠️ Pontos de Atenção
- **CRÍTICO:** NotificationService NÃO está sendo usado (usa `$_SESSION['msg']` antiga)
- **CRÍTICO:** Controllers de Create/Update não removem CSRF token antes de passar para Model
- **CRÍTICO:** Sem validação de campos opcionais (tudo validado como obrigatório)
- Falta logging com LoggerService
- Redirecionamentos sem `exit()` e sem fallbacks
- Validação antiga (não exclui campos opcionais)

### ❌ Problemas Críticos
1. Notificações usando formato antigo de Bootstrap (HTML inline)
2. Sem tratamento robusto de redirecionamento
3. Validação não diferencia campos obrigatórios vs opcionais
4. Sem auditoria de ações (logging)

---

## 1. Estrutura de Arquivos

### 1.1. Controllers Identificados

```
NovoUsuario.php                      ⚠️ Padrão antigo
ApagarUsuario.php                    ⚠️ Padrão antigo
UsuariosTreinamento.php              ⚠️ Padrão antigo
CadastrarUsuarioTreinamento.php      ⚠️ Padrão antigo
EditarUsuarioTreinamento.php         ⚠️ Padrão antigo
ApagarUsuarioTreinamento.php         ⚠️ Padrão antigo
VerUsuarioTreinamento.php            ⚠️ Padrão antigo
```

**Problemas de Nomenclatura:**
- ❌ `NovoUsuario` → Deveria ser `AddUser` ou `AddUsuario`
- ❌ `ApagarUsuario` → Deveria ser `DeleteUser` ou `DeleteUsuario`
- ❌ `CadastrarUsuarioTreinamento` → Deveria ser `AddTrainingUser`
- ✅ `EditarUsuarioTreinamento` → OK mas poderia ser `EditTrainingUser`

**Padrão Recomendado:**
```
Users.php                    # Controller principal (listagem)
AddUser.php                  # Criar usuário
EditUser.php                 # Editar usuário
DeleteUser.php               # Deletar usuário
ViewUser.php                 # Visualizar usuário
```

### 1.2. Models Identificados

```
AdmsNovoUsuario.php                  ⚠️ Deveria ser AdmsAddUser
AdmsApagarUsuario.php                ⚠️ Deveria ser AdmsDeleteUser
AdmsCadastrarUsuario.php             ⚠️ Duplicado? AdmsAddUser
AdmsListarUsuarioTreinamento.php     ⚠️ Deveria ser AdmsListTrainingUsers
AdmsEditarUsuarioTreinamento.php     ⚠️ Deveria ser AdmsEditTrainingUser
AdmsApagarUsuarioTreinamento.php     ⚠️ Deveria ser AdmsDeleteTrainingUser
AdmsVerUsuarioTreinamento.php        ⚠️ Deveria ser AdmsViewTrainingUser
```

**Padrão Recomendado:**
```
AdmsUser.php                     # CRUD principal
AdmsListUsers.php                # Listagem (plural)
AdmsStatisticsUsers.php          # Estatísticas (plural)
AdmsViewUser.php                 # Visualização (singular)
AdmsAddUser.php                  # Criar (singular)
AdmsEditUser.php                 # Editar (singular)
AdmsDeleteUser.php               # Deletar (singular)
```

### 1.3. Views Identificadas

```
app/adms/Views/usuario/
├── loadUsers.php                         ✅ CORRETO
├── listUsers.php                         ✅ CORRETO
├── perfil.php                            ✅ CORRETO
├── editProfile.php                       ✅ CORRETO
├── alterarSenha.php                      ✅ CORRETO
├── listUsersOnline.php                   ✅ CORRETO
└── partials/
    ├── _add_user_modal.php               ✅ CORRETO
    ├── _edit_user_modal.php              ✅ CORRETO (mas chama content)
    ├── _edit_user_content.php            ⚠️ Deveria estar no modal
    ├── _view_user_modal.php              ✅ CORRETO (mas chama content)
    ├── _view_user_content.php            ⚠️ Deveria estar no modal
    ├── _delete_user_modal.php            ✅ CORRETO
    └── _statistics_dashboard.php         ✅ CORRETO
```

**Estrutura:** ✅ CORRETA em geral
**Observação:** Separação de modal/content é desnecessária - poderia ser tudo no modal

### 1.4. JavaScript

```
assets/js/users.js                        ✅ CORRETO (kebab-case)
```

**Estrutura:** ✅ EXCELENTE
- Event delegation implementado
- Async/await moderno
- Funções bem organizadas
- AJAX bem estruturado

---

## 2. Análise do Controller `NovoUsuario.php`

### 2.1. Código Atual

```php
class NovoUsuario
{
    private $Dados;

    public function novoUsuario()
    {
        $this->Dados = filter_input_array(INPUT_POST, FILTER_DEFAULT);
        if (!empty($this->Dados['CadUserLogin'])) {
            unset($this->Dados['CadUserLogin']);  // ⚠️ Remove botão mas NÃO CSRF
            $cadUser = new \App\adms\Models\AdmsNovoUsuario();
            $cadUser->cadUser($this->Dados);
            if ($cadUser->getResultado()) {
                $UrlDestino = URLADM . 'login/acesso';
                header("Location: $UrlDestino");  // ❌ SEM exit()
            } else {
                $this->Dados['form'] = $this->Dados;
                $carregarView = new \Core\ConfigView("adms/Views/login/novoUsuario", $this->Dados);
                $carregarView->renderizarLogin();
            }
        } else {
            $carregarView = new \Core\ConfigView("adms/Views/login/novoUsuario", $this->Dados);
            $carregarView->renderizarLogin();
        }
    }
}
```

### 2.2. Problemas Identificados

| # | Problema | Gravidade | Comparação com Estorno |
|---|----------|-----------|------------------------|
| 1 | CSRF token não é removido | 🔴 CRÍTICO | Estorno: `unset($this->data['_csrf_token'])` ✅ |
| 2 | Redirect sem `exit()` | 🔴 CRÍTICO | Estorno: `header(); exit();` ✅ |
| 3 | Sem NotificationService | 🔴 CRÍTICO | Estorno: usa NotificationService ✅ |
| 4 | Sem headers_sent() fallback | 🟡 MÉDIO | Estorno: JavaScript/meta fallback ✅ |
| 5 | Sem logging de operações | 🟡 MÉDIO | Estorno: LoggerService em cada ação ✅ |
| 6 | Nomenclatura PHP 5 | 🟡 MÉDIO | Estorno: PHP 8+ com type hints ✅ |

### 2.3. Código Recomendado (Baseado em AddReversal.php)

```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsAddUser;
use App\adms\Services\NotificationService;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller para criação de novos usuários
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2025, Grupo Meia Sola
 */
class AddUser
{
    private array $data = [];
    private NotificationService $notification;

    public function __construct()
    {
        $this->notification = new NotificationService();
    }

    /**
     * Processa a criação do usuário
     *
     * @return void
     */
    public function create(): void
    {
        $this->data = filter_input_array(INPUT_POST, FILTER_DEFAULT);

        if (empty($this->data)) {
            $this->notification->error('Requisição inválida!');
            $this->jsonResponse([
                'error' => true,
                'msg' => 'Erro: Requisição inválida!',
                'notification' => $this->notification->getFlashMessage()
            ], 400);
            return;
        }

        // Remove CSRF token from data
        unset($this->data['_csrf_token']);

        // Handle file upload if exists
        $this->data['profile_image'] = $_FILES['imagem_nova'] ?? null;

        $addUser = new AdmsAddUser();
        $result = $addUser->createUser($this->data);

        if ($result) {
            $this->notification->success('Usuário cadastrado com sucesso!');
            $this->jsonResponse([
                'success' => true,
                'msg' => 'Usuário cadastrado com sucesso!',
                'notification' => $this->notification->getFlashMessage()
            ], 200);
        } else {
            $error = $addUser->getError() ?? 'Erro ao cadastrar usuário!';
            $this->notification->error($error);
            $this->jsonResponse([
                'error' => true,
                'msg' => $error,
                'notification' => $this->notification->getFlashMessage()
            ], 400);
        }
    }

    /**
     * Retorna resposta JSON
     *
     * @param array $data
     * @param int $statusCode
     * @return void
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}
```

---

## 3. Análise do Model `AdmsNovoUsuario.php`

### 3.1. Código Atual

```php
class AdmsNovoUsuario {
    private $Dados;
    private $Resultado;

    public function cadUser(array $Dados) {
        $this->Dados = $Dados;
        $this->validarDados();  // ⚠️ Validação antiga
        if ($this->Resultado) {
            // Validações específicas...
            if (/* todas validações OK */) {
                $this->inserir();
            } else {
                $this->Resultado = false;
            }
        }
    }

    private function validarDados() {
        $this->Dados = array_map('strip_tags', $this->Dados);
        $this->Dados = array_map('trim', $this->Dados);
        if (in_array('', $this->Dados)) {  // ❌ VALIDA TUDO
            $_SESSION['msg'] = "HTML INLINE...";  // ❌ Formato antigo
            $this->Resultado = false;
        }
    }

    private function inserir() {
        // ...
        if ($cadUser->getResult()) {
            if ($this->InfoCadUser[0]['env_email_conf'] == 1) {
                $this->dadosEmail();
            } else {
                $_SESSION['msg'] = "<div class='alert alert-success'>...</div>";  // ❌
                $this->Resultado = true;
            }
        } else {
            $_SESSION['msg'] = "<div class='alert alert-danger'>...</div>";  // ❌
            $this->Resultado = false;
        }
    }
}
```

### 3.2. Problemas Identificados

| # | Problema | Gravidade | Comparação com Estorno |
|---|----------|-----------|------------------------|
| 1 | `in_array('', $this->Dados)` valida TUDO | 🔴 CRÍTICO | Estorno: exclui campos opcionais ✅ |
| 2 | Usa `$_SESSION['msg']` com HTML | 🔴 CRÍTICO | Estorno: Controller gerencia notificações ✅ |
| 3 | Sem exclusão de campos opcionais | 🔴 CRÍTICO | Estorno: `unset($dataToValidate['obs'])` ✅ |
| 4 | Sem logging de operações | 🟡 MÉDIO | Estorno: `LoggerService::info()` ✅ |
| 5 | Nomenclatura PHP 5 | 🟡 MÉDIO | Estorno: Type hints PHP 8+ ✅ |

### 3.3. Código Recomendado (Baseado em AdmsAddReversal.php)

```php
<?php

namespace App\adms\Models;

use App\adms\Models\helper\AdmsCampoVazio;
use App\adms\Models\helper\AdmsCreate;
use App\adms\Models\helper\AdmsEmail;
use App\adms\Models\helper\AdmsEmailUnico;
use App\adms\Models\helper\AdmsValUsuario;
use App\adms\Models\helper\AdmsValSenha;
use App\adms\Services\LoggerService;

if (!defined('URL')) {
    header("Location: /");
    exit();
}

/**
 * Model para criação de usuários
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2025, Grupo Meia Sola
 */
class AdmsAddUser
{
    private array $data = [];
    private ?string $error = null;
    private bool $result = false;
    private ?array $profileImage = null;

    /**
     * Retorna resultado da operação
     */
    public function getResult(): bool
    {
        return $this->result;
    }

    /**
     * Retorna mensagem de erro
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Cria novo usuário
     *
     * @param array $data
     * @return bool
     */
    public function createUser(array $data): bool
    {
        $this->data = $data;
        $this->profileImage = $this->data['profile_image'] ?? null;
        unset($this->data['profile_image']);

        // Remove campos opcionais da validação
        $dataToValidate = $this->data;
        unset($dataToValidate['apelido']);        // Apelido é opcional
        unset($dataToValidate['adms_area_id']);   // Área é opcional
        unset($dataToValidate['loja_id']);        // Loja é opcional

        $valEmptyField = new AdmsCampoVazio();
        $valEmptyField->validarDados($dataToValidate);

        if (!$valEmptyField->getResultado()) {
            $this->error = 'Preencha todos os campos obrigatórios!';
            LoggerService::warning('USER_CREATE_VALIDATION_FAILED', $this->error);
            return false;
        }

        // Validações específicas
        if (!$this->validateSpecificFields()) {
            return false;
        }

        return $this->insertUser();
    }

    /**
     * Valida campos específicos
     */
    private function validateSpecificFields(): bool
    {
        // Email válido
        $valEmail = new AdmsEmail();
        $valEmail->valEmail($this->data['email']);
        if (!$valEmail->getResultado()) {
            $this->error = 'E-mail inválido!';
            return false;
        }

        // Email único
        $valEmailUnico = new AdmsEmailUnico();
        $valEmailUnico->valEmailUnico($this->data['email']);
        if (!$valEmailUnico->getResultado()) {
            $this->error = 'E-mail já cadastrado!';
            return false;
        }

        // Usuário válido
        $valUsuario = new AdmsValUsuario();
        $valUsuario->valUsuario($this->data['usuario']);
        if (!$valUsuario->getResultado()) {
            $this->error = 'Nome de usuário inválido ou já existe!';
            return false;
        }

        // Senha válida
        $valSenha = new AdmsValSenha();
        $valSenha->valSenha($this->data['senha']);
        if (!$valSenha->getResultado()) {
            $this->error = 'Senha inválida (mínimo 6 caracteres)!';
            return false;
        }

        return true;
    }

    /**
     * Insere usuário no banco
     */
    private function insertUser(): bool
    {
        // Hash da senha
        $this->data['senha'] = password_hash($this->data['senha'], PASSWORD_DEFAULT);
        $this->data['created'] = gmdate('Y-m-d H:i:s');

        // Upload de imagem se fornecida
        if (!empty($this->profileImage['name'])) {
            // TODO: Implementar upload com FileUploadService
        }

        $create = new AdmsCreate();
        $create->exeCreate('adms_usuarios', $this->data);

        if ($create->getResult()) {
            $userId = $create->getResult();
            LoggerService::info('USER_CREATED', 'Novo usuário cadastrado', [
                'user_id' => $userId,
                'username' => $this->data['usuario'],
                'email' => $this->data['email']
            ]);
            $this->result = true;
            return true;
        } else {
            $this->error = 'Erro ao cadastrar usuário no banco de dados!';
            LoggerService::error('USER_CREATE_DB_ERROR', $this->error);
            return false;
        }
    }
}
```

---

## 4. Análise da View `_add_user_modal.php`

### 4.1. Pontos Positivos ✅

1. **CSRF Token:** ✅ Implementado corretamente
   ```php
   <?= csrf_field() ?>
   ```

2. **Responsividade:** ✅ Classes Bootstrap adequadas
   ```html
   <div class="form-group col-md-6">  <!-- 2 colunas em desktop -->
   <div class="form-group col-md-4">  <!-- 3 colunas em desktop -->
   ```

3. **Validação Client-Side:** ✅ Atributos HTML5
   ```html
   <input name="nome" type="text" required>
   <input name="senha" type="password" required minlength="6">
   ```

4. **Segurança XSS:** ✅ htmlspecialchars() nos selects
   ```php
   <?= htmlspecialchars($area['a_id']) ?>
   ```

5. **Estrutura Semântica:** ✅ Cards bem organizados
   ```html
   <!-- Informações Pessoais -->
   <!-- Informações de Acesso -->
   <!-- Informações da Loja -->
   ```

### 4.2. Comparação com Estorno

| Aspecto | Usuário | Estorno | Status |
|---------|---------|---------|--------|
| CSRF token | ✅ `<?= csrf_field() ?>` | ✅ `<?= csrf_field() ?>` | ✅ IGUAL |
| Responsividade | ✅ col-md-6, col-md-4 | ✅ col-md-3, col-md-12 | ✅ IGUAL |
| Validação HTML5 | ✅ required, minlength | ✅ required | ✅ IGUAL |
| XSS Protection | ✅ htmlspecialchars | ✅ htmlspecialchars | ✅ IGUAL |
| Estrutura | ✅ Cards organizados | ✅ Cards organizados | ✅ IGUAL |

**Conclusão:** ✅ View está CORRETA e seguindo padrões atuais

---

## 5. Análise do JavaScript `users.js`

### 5.1. Pontos Positivos ✅

1. **Event Delegation:** ✅ Implementado
   ```javascript
   const container = document.getElementById('users-container');
   if (!container) return;
   ```

2. **Async/Await:** ✅ JavaScript moderno
   ```javascript
   window.listUsers = async function(page = 1, isSearch = false) {
       const response = await fetch(url, options);
   }
   ```

3. **Error Handling:** ✅ Try/catch adequado
   ```javascript
   try {
       const response = await fetch(url);
       if (!response.ok) throw new Error('Erro na requisição.');
   } catch (error) {
       contentDiv.innerHTML = `<div class="alert alert-danger">...</div>`;
   }
   ```

4. **Loading States:** ✅ Feedback visual
   ```javascript
   contentDiv.innerHTML = `<div class="text-center p-5">
       <i class="fas fa-spinner fa-spin fa-3x"></i>
       <p class="mt-3">Carregando...</p>
   </div>`;
   ```

5. **URL Building:** ✅ Bem estruturado
   ```javascript
   const params = new URLSearchParams(formData);
   url = `${URL_BASE}users/list/${page}?typeuser=2&${params.toString()}`;
   ```

### 5.2. Comparação com Estorno

| Aspecto | Usuário | Estorno | Status |
|---------|---------|---------|--------|
| Event delegation | ✅ DOMContentLoaded | ✅ DOMContentLoaded | ✅ IGUAL |
| Async/Await | ✅ Moderno | ✅ Moderno | ✅ IGUAL |
| Error handling | ✅ Try/catch | ✅ Try/catch | ✅ IGUAL |
| Loading states | ✅ Spinner | ✅ Spinner | ✅ IGUAL |
| AJAX structure | ✅ Fetch API | ✅ Fetch API | ✅ IGUAL |

**Conclusão:** ✅ JavaScript está EXCELENTE e seguindo padrões modernos

---

## 6. Gaps Identificados vs Padrões Recentes

### 6.1. NotificationService (CRÍTICO 🔴)

**Status Atual:** ❌ NÃO IMPLEMENTADO

**Evidência:**
```php
// AdmsNovoUsuario.php linha 54
$_SESSION['msg'] = "<div class='alert alert-danger'>...</div>";

// AdmsNovoUsuario.php linha 75
$_SESSION['msg'] = "<div class='alert alert-success'>...</div>";
```

**Padrão Recomendado (Estorno):**
```php
use App\adms\Services\NotificationService;

// No Controller
$this->notification = new NotificationService();
$this->notification->success('Usuário cadastrado com sucesso!');

// OU erro
$this->notification->error('Erro ao cadastrar usuário!');
```

**Impacto:**
- Notificações com HTML inline (vulnerável a XSS se dados não sanitizados)
- Inconsistência visual com módulos modernos
- Código duplicado em múltiplos lugares

### 6.2. Validação de Campos Opcionais (CRÍTICO 🔴)

**Status Atual:** ❌ VALIDA TUDO COMO OBRIGATÓRIO

**Evidência:**
```php
// AdmsNovoUsuario.php linha 53
if (in_array('', $this->Dados)) {  // ❌ Valida TODOS os campos
    $_SESSION['msg'] = "Erro: Necessário preencher todos os campos!";
    $this->Resultado = false;
}
```

**Padrão Recomendado (Estorno):**
```php
// Remove campos opcionais da validação
$dataToValidate = $this->data;
unset($dataToValidate['apelido']);        // Apelido é opcional
unset($dataToValidate['adms_area_id']);   // Área é opcional
unset($dataToValidate['loja_id']);        // Loja é opcional

$valEmptyField = new AdmsCampoVazio();
$valEmptyField->validarDados($dataToValidate);
```

**Impacto:**
- Usuários DEVEM preencher campos opcionais (apelido, área, loja)
- Experiência ruim para o usuário
- Bug potencial bloqueando cadastros válidos

### 6.3. Remoção de CSRF Token (CRÍTICO 🔴)

**Status Atual:** ❌ NÃO REMOVE

**Evidência:**
```php
// NovoUsuario.php linha 24
unset($this->Dados['CadUserLogin']);  // Remove apenas o botão
// ❌ NÃO remove _csrf_token
```

**Padrão Recomendado (Estorno):**
```php
// AddReversal.php linha 52
unset($this->data['_csrf_token']);  // ✅ Remove CSRF token
```

**Impacto:**
- Token CSRF pode ser enviado para o banco de dados
- Erro SQL: "Column '_csrf_token' not found"
- Já aconteceu em Ordem de Serviço e foi corrigido

### 6.4. Redirecionamento sem exit() (CRÍTICO 🔴)

**Status Atual:** ❌ SEM exit()

**Evidência:**
```php
// NovoUsuario.php linha 29
header("Location: $UrlDestino");  // ❌ SEM exit()
```

**Padrão Recomendado (Estorno, Ordem de Serviço):**
```php
if (headers_sent($file, $line)) {
    error_log("Headers já enviados em {$file}:{$line}");
    echo "<script>window.location.href = '{$UrlDestino}';</script>";
    echo "<noscript><meta http-equiv='refresh' content='0;url={$UrlDestino}'></noscript>";
} else {
    header("Location: $UrlDestino");
}
exit();  // ✅ SEMPRE adicionar exit()
```

**Impacto:**
- Código continua executando após redirect
- Possível exibição de conteúdo indevido
- Vulnerabilidade de segurança potencial

### 6.5. Logging com LoggerService (MÉDIO 🟡)

**Status Atual:** ❌ NÃO IMPLEMENTADO

**Padrão Recomendado (Estorno):**
```php
use App\adms\Services\LoggerService;

// Sucesso
LoggerService::info('USER_CREATED', 'Novo usuário cadastrado', [
    'user_id' => $userId,
    'username' => $this->data['usuario']
]);

// Erro
LoggerService::error('USER_CREATE_FAILED', 'Erro ao criar usuário', [
    'error' => $this->error
]);

// Validação
LoggerService::warning('USER_VALIDATION_FAILED', 'Campos obrigatórios vazios');
```

**Impacto:**
- Sem auditoria de operações
- Dificulta troubleshooting
- Não rastreia quem criou/editou usuários

### 6.6. Type Hints PHP 8+ (MÉDIO 🟡)

**Status Atual:** ⚠️ PHP 5 Style

**Evidência:**
```php
class AdmsNovoUsuario {
    private $Dados;          // ❌ Sem type hint
    private $Resultado;      // ❌ Sem type hint

    function getResultado() {  // ❌ Sem return type
        return $this->Resultado;
    }
}
```

**Padrão Recomendado (Estorno):**
```php
class AdmsAddReversal
{
    private array $data = [];
    private bool $result = false;
    private ?string $error = null;

    public function getResult(): bool {
        return $this->result;
    }

    public function getError(): ?string {
        return $this->error;
    }
}
```

**Impacto:**
- Código menos seguro (sem verificação de tipos)
- Dificulta manutenção
- Inconsistência com módulos modernos

---

## 7. Recomendações de Atualização

### 7.1. Prioridade CRÍTICA 🔴 (Implementar Imediatamente)

1. **Migrar para NotificationService**
   - Remover todos `$_SESSION['msg']` com HTML inline
   - Usar NotificationService no Controller
   - Model não deve gerenciar notificações

2. **Corrigir Validação de Campos**
   - Criar array `$dataToValidate`
   - Remover campos opcionais antes de validar
   - Documentar quais campos são opcionais

3. **Adicionar Remoção de CSRF Token**
   - `unset($this->data['_csrf_token'])` em controllers que usam `filter_input_array(INPUT_POST)`
   - Aplicar APENAS em Create/Update/Delete controllers
   - Após `filter_input_array()` e antes de passar array completo para Model
   - NÃO necessário em: listagem, visualização, ou quando constrói array manualmente

4. **Corrigir Redirecionamentos**
   - Adicionar `exit()` após TODOS os `header()`
   - Implementar fallback com `headers_sent()`
   - Usar JavaScript/meta refresh como backup

### 7.2. Prioridade ALTA 🟠 (Próxima Sprint)

5. **Implementar LoggerService**
   - USER_CREATED
   - USER_UPDATED
   - USER_DELETED
   - USER_LOGIN
   - USER_PASSWORD_CHANGED

6. **Refatorar Nomenclatura**
   - NovoUsuario → AddUser
   - ApagarUsuario → DeleteUser
   - Manter consistência com módulos recentes

### 7.3. Prioridade MÉDIA 🟡 (Refatoração Futura)

7. **Migrar para PHP 8+ Type Hints**
   - Adicionar types em propriedades
   - Adicionar return types em métodos
   - Usar promoted properties onde aplicável

8. **Implementar FileUploadService**
   - Substituir upload manual de imagem
   - Usar padrão unificado do Issue #99

---

## 8. Comparação Detalhada: Usuário vs Estorno

### 8.1. Tabela Comparativa

| Aspecto | Usuário | Estorno | Gap |
|---------|---------|---------|-----|
| **CONTROLLERS** |
| Nomenclatura | NovoUsuario, ApagarUsuario | AddReversal, EditReversal | ❌ Inconsistente |
| Type hints | Não | Sim (PHP 8+) | ❌ Falta |
| NotificationService | Não | Sim | ❌ CRÍTICO |
| CSRF removal | Não | Sim | ❌ CRÍTICO |
| exit() após redirect | Não | Sim | ❌ CRÍTICO |
| headers_sent() fallback | Não | Sim | ⚠️ Médio |
| JSON responses | Não (redirect) | Sim | ⚠️ Médio |
| LoggerService | Não | Sim | ⚠️ Médio |
| **MODELS** |
| Type hints | Não | Sim | ❌ Falta |
| Validação campos opcionais | Não | Sim | ❌ CRÍTICO |
| NotificationService | $_SESSION['msg'] | No Controller | ❌ CRÍTICO |
| LoggerService | Não | Sim | ⚠️ Médio |
| Error handling | Boolean | string $error | ⚠️ Médio |
| **VIEWS** |
| CSRF token | ✅ Sim | ✅ Sim | ✅ OK |
| Responsividade | ✅ Sim | ✅ Sim | ✅ OK |
| XSS protection | ✅ Sim | ✅ Sim | ✅ OK |
| Estrutura | ✅ Cards | ✅ Cards | ✅ OK |
| **JAVASCRIPT** |
| Event delegation | ✅ Sim | ✅ Sim | ✅ OK |
| Async/await | ✅ Sim | ✅ Sim | ✅ OK |
| Error handling | ✅ Sim | ✅ Sim | ✅ OK |
| Loading states | ✅ Sim | ✅ Sim | ✅ OK |

### 8.2. Score de Conformidade

**Views + JavaScript:** 95% ✅
- Praticamente perfeito
- Seguindo padrões modernos
- Poucas mudanças necessárias

**Models:** 40% ❌
- Validação problemática
- Sem type hints
- Sem logging
- Notificações antigas

**Controllers:** 35% ❌
- Sem NotificationService
- Sem CSRF removal
- Sem exit() após redirect
- Sem logging

**Score Geral do Módulo:** 57% ⚠️
- Precisa atualização urgente nos Controllers e Models
- Views e JS estão excelentes

---

## 9. Checklist de Migração

### Para CADA Controller de Usuário:

```markdown
- [ ] Renomear para padrão moderno (NovoUsuario → AddUser)
- [ ] Adicionar type hints em propriedades e métodos
- [ ] Importar NotificationService
- [ ] Criar instância de NotificationService no construtor
- [ ] Remover CSRF token: `unset($this->data['_csrf_token'])`
- [ ] Adicionar `exit()` após todos os `header()`
- [ ] Implementar fallback `headers_sent()`
- [ ] Usar JSON response para AJAX
- [ ] Adicionar LoggerService para operações críticas
- [ ] Testar CRUD completo
```

### Para CADA Model de Usuário:

```markdown
- [ ] Renomear para padrão moderno (AdmsNovoUsuario → AdmsAddUser)
- [ ] Adicionar type hints: array $data, bool $result, ?string $error
- [ ] Criar array $dataToValidate separado
- [ ] Identificar campos opcionais (apelido, área, loja, etc)
- [ ] Remover campos opcionais com unset() antes de validar
- [ ] REMOVER todas referências a $_SESSION['msg']
- [ ] Retornar apenas boolean (ou ID em caso de create)
- [ ] Armazenar erro em propriedade $error
- [ ] Adicionar LoggerService::info() para sucessos
- [ ] Adicionar LoggerService::error() para erros
- [ ] Adicionar LoggerService::warning() para validações
- [ ] Testar validação com campos opcionais vazios
- [ ] Testar validação com campos obrigatórios vazios
```

### Para CADA View:

```markdown
- [ ] Verificar <?= csrf_field() ?> presente
- [ ] Verificar htmlspecialchars() em todos outputs dinâmicos
- [ ] Verificar required nos campos obrigatórios
- [ ] Remover required dos campos opcionais
- [ ] Verificar classes responsivas (col-md-*, d-none d-md-block)
- [ ] Verificar estrutura de cards
- [ ] Testar em mobile
- [ ] Testar em tablet
- [ ] Testar em desktop
```

### Para JavaScript:

```markdown
- [x] Event delegation implementado
- [x] Async/await moderno
- [x] Error handling adequado
- [x] Loading states
- [x] URL building correto
- [ ] Atualizar para usar JSON responses (se controllers migrarem)
```

---

## 10. Templates de Código Atualizados

### 10.1. Template: Controller CRUD (Create)

```php
<?php

namespace App\adms\Controllers;

use App\adms\Models\AdmsAddUser;
use App\adms\Services\NotificationService;
use App\adms\Services\LoggerService;

if (!defined('URLADM')) {
    header("Location: /");
    exit();
}

/**
 * Controller para criação de usuários
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2025, Grupo Meia Sola
 */
class AddUser
{
    private array $data = [];
    private NotificationService $notification;

    public function __construct()
    {
        $this->notification = new NotificationService();
    }

    /**
     * Processa a criação do usuário
     *
     * @return void
     */
    public function create(): void
    {
        $this->data = filter_input_array(INPUT_POST, FILTER_DEFAULT);

        if (empty($this->data)) {
            $this->notification->error('Requisição inválida!');
            $this->jsonResponse([
                'error' => true,
                'msg' => 'Erro: Requisição inválida!',
                'notification' => $this->notification->getFlashMessage()
            ], 400);
            return;
        }

        // Remove CSRF token from data
        unset($this->data['_csrf_token']);

        // Handle file upload if exists
        $this->data['profile_image'] = $_FILES['imagem_nova'] ?? null;

        $addUser = new AdmsAddUser();
        $result = $addUser->createUser($this->data);

        if ($result) {
            $this->notification->success('Usuário cadastrado com sucesso!');
            $this->jsonResponse([
                'success' => true,
                'msg' => 'Usuário cadastrado com sucesso!',
                'notification' => $this->notification->getFlashMessage()
            ], 200);
        } else {
            $error = $addUser->getError() ?? 'Erro ao cadastrar usuário!';
            $this->notification->error($error);
            $this->jsonResponse([
                'error' => true,
                'msg' => $error,
                'notification' => $this->notification->getFlashMessage()
            ], 400);
        }
    }

    /**
     * Retorna resposta JSON
     *
     * @param array $data
     * @param int $statusCode
     * @return void
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}
```

### 10.2. Template: Model CRUD (Create)

```php
<?php

namespace App\adms\Models;

use App\adms\Models\helper\AdmsCampoVazio;
use App\adms\Models\helper\AdmsCreate;
use App\adms\Models\helper\AdmsEmail;
use App\adms\Models\helper\AdmsEmailUnico;
use App\adms\Models\helper\AdmsValUsuario;
use App\adms\Models\helper\AdmsValSenha;
use App\adms\Services\LoggerService;

if (!defined('URL')) {
    header("Location: /");
    exit();
}

/**
 * Model para criação de usuários
 *
 * @author Equipe Mercury - Grupo Meia Sola
 * @copyright (c) 2025, Grupo Meia Sola
 */
class AdmsAddUser
{
    private array $data = [];
    private ?string $error = null;
    private bool $result = false;
    private ?array $profileImage = null;

    /**
     * Retorna resultado da operação
     */
    public function getResult(): bool
    {
        return $this->result;
    }

    /**
     * Retorna mensagem de erro
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Cria novo usuário
     *
     * @param array $data
     * @return bool
     */
    public function createUser(array $data): bool
    {
        $this->data = $data;
        $this->profileImage = $this->data['profile_image'] ?? null;
        unset($this->data['profile_image']);

        // Remove campos opcionais da validação
        $dataToValidate = $this->data;
        unset($dataToValidate['apelido']);        // Apelido é opcional
        unset($dataToValidate['adms_area_id']);   // Área é opcional
        unset($dataToValidate['loja_id']);        // Loja é opcional

        $valEmptyField = new AdmsCampoVazio();
        $valEmptyField->validarDados($dataToValidate);

        if (!$valEmptyField->getResultado()) {
            $this->error = 'Preencha todos os campos obrigatórios!';
            LoggerService::warning('USER_CREATE_VALIDATION_FAILED', $this->error);
            return false;
        }

        // Validações específicas
        if (!$this->validateSpecificFields()) {
            return false;
        }

        return $this->insertUser();
    }

    /**
     * Valida campos específicos (email, usuário, senha)
     */
    private function validateSpecificFields(): bool
    {
        // Email válido
        $valEmail = new AdmsEmail();
        $valEmail->valEmail($this->data['email']);
        if (!$valEmail->getResultado()) {
            $this->error = 'E-mail inválido!';
            LoggerService::warning('USER_INVALID_EMAIL', $this->error, [
                'email' => $this->data['email']
            ]);
            return false;
        }

        // Email único
        $valEmailUnico = new AdmsEmailUnico();
        $valEmailUnico->valEmailUnico($this->data['email']);
        if (!$valEmailUnico->getResultado()) {
            $this->error = 'E-mail já cadastrado!';
            LoggerService::warning('USER_DUPLICATE_EMAIL', $this->error, [
                'email' => $this->data['email']
            ]);
            return false;
        }

        // Usuário válido
        $valUsuario = new AdmsValUsuario();
        $valUsuario->valUsuario($this->data['usuario']);
        if (!$valUsuario->getResultado()) {
            $this->error = 'Nome de usuário inválido ou já existe!';
            LoggerService::warning('USER_INVALID_USERNAME', $this->error, [
                'username' => $this->data['usuario']
            ]);
            return false;
        }

        // Senha válida
        $valSenha = new AdmsValSenha();
        $valSenha->valSenha($this->data['senha']);
        if (!$valSenha->getResultado()) {
            $this->error = 'Senha inválida (mínimo 6 caracteres)!';
            LoggerService::warning('USER_INVALID_PASSWORD', $this->error);
            return false;
        }

        return true;
    }

    /**
     * Insere usuário no banco de dados
     */
    private function insertUser(): bool
    {
        // Hash da senha
        $this->data['senha'] = password_hash($this->data['senha'], PASSWORD_DEFAULT);

        // Timestamps UTC
        $this->data['created'] = gmdate('Y-m-d H:i:s');

        // Auditoria
        $this->data['created_by_user_id'] = $_SESSION['usuario_id'] ?? null;

        // Upload de imagem se fornecida
        if (!empty($this->profileImage['name'])) {
            // TODO: Implementar upload com FileUploadService
            // $uploadService = new FileUploadService();
            // $config = UploadConfig::image('assets/imagens/usuarios/', 2097152);
            // $result = $uploadService->uploadSingle($this->profileImage, $config);
        }

        $create = new AdmsCreate();
        $create->exeCreate('adms_usuarios', $this->data);

        if ($create->getResult()) {
            $userId = $create->getResult();

            LoggerService::info('USER_CREATED', 'Novo usuário cadastrado com sucesso', [
                'user_id' => $userId,
                'username' => $this->data['usuario'],
                'email' => $this->data['email'],
                'nivel_acesso_id' => $this->data['adms_niveis_acesso_id'] ?? null,
                'created_by' => $_SESSION['usuario_id'] ?? null
            ]);

            $this->result = true;
            return true;
        } else {
            $this->error = 'Erro ao cadastrar usuário no banco de dados!';

            LoggerService::error('USER_CREATE_DB_ERROR', $this->error, [
                'username' => $this->data['usuario'],
                'email' => $this->data['email']
            ]);

            return false;
        }
    }
}
```

---

## 11. Próximos Passos

### Fase 1: Atualização Crítica (Sprint 1)
1. ✅ Criar este documento de análise
2. ✅ Atualizar REGRAS_DESENVOLVIMENTO.md com novos padrões
3. ⏳ Criar Issues no GitHub para cada item crítico
4. ⏳ Migrar Controllers de Create/Update para NotificationService
5. ⏳ Corrigir validação de campos opcionais em Models
6. ⏳ Adicionar `unset($_csrf_token)` em Controllers que usam `filter_input_array(INPUT_POST)`
7. ⏳ Corrigir redirecionamentos (adicionar `exit()` e fallbacks)

### Fase 2: Modernização (Sprint 2)
8. ⏳ Implementar LoggerService
9. ⏳ Refatorar nomenclatura (NovoUsuario → AddUser)
10. ⏳ Testar CRUD completo

### Fase 3: Refatoração (Sprint 3)
11. ⏳ Migrar para PHP 8+ type hints
12. ⏳ Implementar FileUploadService para imagens de perfil
13. ⏳ Atualizar testes automatizados

---

## 12. Conclusão

O módulo de usuário apresenta uma **dicotomia interessante**:

**Frontend (Views + JavaScript):** ✅ **EXCELENTE (95%)**
- Código moderno, bem estruturado
- Seguindo todos os padrões atuais
- Praticamente não precisa de mudanças

**Backend (Controllers + Models):** ❌ **DEFASADO (37%)**
- Usando padrões antigos (PHP 5 style)
- Sem NotificationService
- Validação problemática
- Sem logging adequado
- Vulnerável a bugs já corrigidos em outros módulos

**Recomendação:** Priorizar atualização do **backend** nas próximas sprints, focando nos itens críticos primeiro (NotificationService, validação, CSRF).

---

**Documento preparado por:** Claude Sonnet 4.5
**Data:** 27 de Dezembro de 2025
**Baseado em:** Análise dos módulos Estorno, Ordem de Serviço, Horas Extras (Dezembro 2025)
