# Módulo de Logs de Atividade - Sistema Mercury

## Visão Geral

O módulo de Logs de Atividade é responsável por registrar, rastrear e visualizar todas as ações importantes que ocorrem no sistema Mercury. Ele fornece uma solução completa de auditoria, segurança e debugging.

## Características Principais

- ✅ **5 Níveis de Log**: DEBUG, INFO, WARNING, ERROR, CRITICAL
- ✅ **Registro Automático**: IP, User Agent, URL, Método HTTP
- ✅ **Filtragem de Dados Sensíveis**: Senhas, tokens e chaves de API são automaticamente ocultados
- ✅ **Busca Avançada**: Filtros por nível, ação, mensagem, data e IP
- ✅ **Visualização Detalhada**: Modal com todos os detalhes do log
- ✅ **Estatísticas em Tempo Real**: Dashboard com KPIs
- ✅ **Performance Otimizada**: Índices de banco de dados para buscas rápidas
- ✅ **Interface Responsiva**: Compatível com todos os dispositivos

## Estrutura do Módulo

```
mercury/
├── database/
│   └── migrations/
│       └── create_activity_logs_table.sql     # Schema do banco de dados
├── app/
│   └── adms/
│       ├── Controllers/
│       │   └── ActivityLog.php                # Controller principal
│       ├── Models/
│       │   ├── AdmsListActivityLogs.php       # Model de listagem
│       │   └── AdmsViewActivityLog.php        # Model de visualização
│       ├── Services/
│       │   └── LoggerService.php              # Serviço de logging
│       └── Views/
│           └── activityLog/
│               ├── loadActivityLog.php        # View principal
│               ├── listActivityLog.php        # View de listagem
│               └── viewActivityLog.php        # View detalhada
└── assets/
    └── js/
        └── activity-log.js                    # JavaScript do módulo
```

## Instalação

### 1. Criar a Tabela no Banco de Dados

Execute o arquivo de migração:

```bash
mysql -u username -p database_name < database/migrations/create_activity_logs_table.sql
```

### 2. Configurar Rotas

Adicione as seguintes rotas no arquivo de configuração de rotas do sistema:

```php
// Logs de Atividade
$router->add('activity-log/list', 'App\adms\Controllers\ActivityLog@list');
$router->add('activity-log/listAjax/{id}', 'App\adms\Controllers\ActivityLog@listAjax');
$router->add('activity-log/search/{id}', 'App\adms\Controllers\ActivityLog@search');
$router->add('activity-log/view/{id}', 'App\adms\Controllers\ActivityLog@view');
$router->add('activity-log/statistics', 'App\adms\Controllers\ActivityLog@statistics');
```

### 3. Adicionar ao Menu

Adicione o link no menu de administração:

```php
<li class="nav-item">
    <a class="nav-link" href="<?php echo URLADM . 'activity-log/list'; ?>">
        <i class="fas fa-file-alt"></i>
        <span>Logs de Atividade</span>
    </a>
</li>
```

## Como Usar o LoggerService

### Importar o Service

```php
use App\adms\Services\LoggerService;
```

### Exemplos de Uso

#### 1. Log de Informação (INFO)

```php
// Login bem-sucedido
LoggerService::info(
    'LOGIN_SUCCESS',
    "Usuário '{$username}' realizou login com sucesso",
    ['username' => $username]
);

// Produto criado
LoggerService::info(
    'PRODUCT_CREATE',
    "Novo produto criado: {$productName}",
    [
        'product_id' => $productId,
        'product_name' => $productName,
        'price' => $price
    ]
);
```

#### 2. Log de Aviso (WARNING)

```php
// Tentativa de acesso não autorizado
LoggerService::warning(
    'UNAUTHORIZED_ACCESS',
    "Usuário tentou acessar recurso restrito: {$resource}",
    [
        'resource' => $resource,
        'required_permission' => $permission
    ]
);

// Estoque baixo
LoggerService::warning(
    'LOW_STOCK',
    "Produto com estoque baixo: {$productName}",
    [
        'product_id' => $productId,
        'current_stock' => $stock,
        'minimum_stock' => $minStock
    ]
);
```

#### 3. Log de Erro (ERROR)

```php
// Falha ao enviar email
LoggerService::error(
    'EMAIL_SEND_FAILED',
    "Falha ao enviar email para: {$recipient}",
    [
        'recipient' => $recipient,
        'subject' => $subject,
        'error' => $errorMessage
    ]
);

// Falha no pagamento
LoggerService::error(
    'PAYMENT_FAILED',
    "Falha ao processar pagamento",
    [
        'order_id' => $orderId,
        'amount' => $amount,
        'payment_method' => $method,
        'error_code' => $errorCode
    ]
);
```

#### 4. Log Crítico (CRITICAL)

```php
// Falha na conexão com banco de dados
LoggerService::critical(
    'DATABASE_CONNECTION_FAILED',
    "Não foi possível conectar ao banco de dados",
    [
        'host' => $dbHost,
        'database' => $dbName
    ]
);

// Sistema fora do ar
LoggerService::critical(
    'SYSTEM_DOWN',
    "Sistema ficou fora do ar",
    [
        'reason' => $reason,
        'duration' => $duration
    ]
);
```

#### 5. Log de Debug (DEBUG)

```php
// Query SQL executada
LoggerService::debug(
    'SQL_QUERY',
    "Query executada",
    [
        'query' => $sql,
        'params' => $params,
        'execution_time' => $time
    ]
);
```

#### 6. Log de Exceção

```php
try {
    // Código que pode gerar exceção
    $result = processPayment($orderId);
} catch (Exception $e) {
    // Loga a exceção completa com stack trace
    LoggerService::logException(
        $e,
        'PAYMENT_PROCESSING_EXCEPTION',
        [
            'order_id' => $orderId,
            'amount' => $amount
        ]
    );

    // Tratamento adicional...
}
```

## Filtragem Automática de Dados Sensíveis

O LoggerService **automaticamente** remove dados sensíveis do contexto antes de salvar no banco. As seguintes chaves são filtradas:

- `password` / `senha` / `passwd` / `pwd`
- `cvv`
- `credit_card` / `card_number`
- `token` / `auth_token` / `access_token`
- `api_key`
- `secret`

**Exemplo:**

```php
// Entrada
LoggerService::info('USER_UPDATE', 'Usuário atualizado', [
    'user_id' => 123,
    'username' => 'admin',
    'password' => 'minha_senha_secreta', // Será filtrado!
    'api_key' => 'sk_live_abc123'        // Será filtrado!
]);

// Resultado salvo no banco
{
    "user_id": 123,
    "username": "admin",
    "password": "********",
    "api_key": "********"
}
```

## Interface do Usuário

### Dashboard de Estatísticas

A página principal exibe 4 KPIs:

1. **Total de Logs**: Quantidade total de logs registrados
2. **Críticos**: Quantidade de logs de nível CRITICAL
3. **Avisos**: Quantidade de logs de nível WARNING
4. **Hoje**: Quantidade de logs registrados hoje

### Filtros de Busca

- **Nível**: Filtra por DEBUG, INFO, WARNING, ERROR, CRITICAL
- **Ação**: Busca pelo código da ação (ex: LOGIN_SUCCESS)
- **Mensagem**: Busca por texto na mensagem
- **Data**: Filtra logs a partir de uma data específica

### Lista de Logs

A tabela de logs exibe:

- ID do log
- Badge colorido com o nível (verde=INFO, amarelo=WARNING, vermelho=CRITICAL)
- Código da ação
- Mensagem (truncada se muito longa)
- Usuário responsável (com avatar)
- Endereço IP
- Data e hora
- Botão para visualizar detalhes

### Visualização Detalhada

O modal de visualização mostra:

- Todas as informações do log
- Contexto adicional em formato JSON
- User Agent completo
- URL e método HTTP
- Informações do usuário responsável

## Boas Práticas

### 1. Use o Nível Apropriado

```php
// ✅ Correto
LoggerService::info('USER_LOGIN', 'Usuário logou');
LoggerService::warning('SUSPICIOUS_ACTIVITY', 'Atividade suspeita detectada');
LoggerService::error('API_ERROR', 'Erro na API externa');
LoggerService::critical('DATABASE_DOWN', 'Banco de dados inacessível');

// ❌ Incorreto
LoggerService::critical('USER_LOGIN', 'Usuário logou'); // Não é crítico!
LoggerService::info('DATABASE_DOWN', 'Banco de dados inacessível'); // É crítico!
```

### 2. Use Códigos de Ação Consistentes

Padronize os códigos de ação:

```php
// ✅ Padrão recomendado: RECURSO_AÇÃO
'USER_CREATE'
'USER_UPDATE'
'USER_DELETE'
'PRODUCT_CREATE'
'PRODUCT_UPDATE'
'ORDER_PAID'
'LOGIN_SUCCESS'
'LOGIN_FAILED'
```

### 3. Forneça Contexto Relevante

```php
// ✅ Bom - contexto útil
LoggerService::error('ORDER_PAYMENT_FAILED', 'Falha no pagamento', [
    'order_id' => $orderId,
    'amount' => $amount,
    'payment_method' => $method,
    'error_code' => $errorCode,
    'gateway_response' => $response
]);

// ❌ Ruim - pouco contexto
LoggerService::error('ORDER_PAYMENT_FAILED', 'Falha no pagamento');
```

### 4. Não Logue Dados Sensíveis Manualmente

```php
// ✅ Correto - o sistema filtra automaticamente
LoggerService::info('USER_UPDATE', 'Dados atualizados', [
    'password' => $newPassword  // Será filtrado automaticamente
]);

// ❌ Incorreto - evite incluir diretamente na mensagem
LoggerService::info(
    'USER_UPDATE',
    "Senha atualizada para: {$newPassword}"  // Ficará visível!
);
```

## Manutenção e Rotação de Logs

### Recomendações

1. **Arquivar logs antigos**: Crie uma tabela `adms_activity_logs_archive` e mova logs com mais de 6 meses
2. **Procedimento automatizado**: Use o procedimento SQL fornecido na migration
3. **Cron job mensal**: Agende execução automática do procedimento
4. **Backup regular**: Faça backup dos logs antes de deletar

### Exemplo de Procedimento de Arquivamento

```sql
DELIMITER $$
CREATE PROCEDURE archive_old_logs()
BEGIN
    -- Arquivar logs com mais de 6 meses
    INSERT INTO adms_activity_logs_archive
    SELECT * FROM adms_activity_logs
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

    -- Deletar logs arquivados
    DELETE FROM adms_activity_logs
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
END$$
DELIMITER ;
```

### Agendar no Cron

```bash
# Executar todo dia 1 às 00:00
0 0 1 * * mysql -u user -p database -e "CALL archive_old_logs();"
```

## Performance

### Índices Criados

A tabela possui os seguintes índices para otimização:

- `idx_user_id`: Busca por usuário
- `idx_level`: Busca por nível
- `idx_action`: Busca por ação
- `idx_created_at`: Busca por data
- `idx_user_action`: Busca combinada usuário + ação
- `idx_level_created`: Busca combinada nível + data
- `idx_level_date_range`: Busca por período e nível
- `idx_user_action_date`: Busca por usuário, ação e data

### Paginação

- **50 logs por página**: Limite otimizado para performance
- **AJAX**: Sem reload de página
- **Cache de estatísticas**: Considere implementar cache para o dashboard

## Segurança

### Proteção Implementada

1. ✅ **XSS Prevention**: Uso de `htmlspecialchars()` em todas as saídas
2. ✅ **SQL Injection**: Prepared statements com PDO
3. ✅ **CSRF**: Verificação de requisições AJAX
4. ✅ **Filtragem de Dados**: Remoção automática de informações sensíveis
5. ✅ **IP Real**: Detecta IP real mesmo atrás de proxies

### Permissões

Recomenda-se criar permissões específicas:

```php
// Permissões sugeridas
'activity_log_view'    // Visualizar logs
'activity_log_export'  // Exportar logs (recurso futuro)
'activity_log_delete'  // Deletar logs (uso administrativo)
```

## Integrações Futuras

### Recursos Planejados

- [ ] **Exportação de Logs**: CSV, JSON, PDF
- [ ] **Alertas em Tempo Real**: Notificações para logs críticos
- [ ] **Gráficos de Tendências**: Visualização de padrões ao longo do tempo
- [ ] **API REST**: Endpoints para integração com sistemas externos
- [ ] **Webhook**: Enviar logs para serviços externos (Slack, Discord, etc.)

## Suporte

Para dúvidas, problemas ou sugestões relacionadas ao módulo de Logs de Atividade, entre em contato com a equipe de desenvolvimento.

---

**Copyright © 2025 - Chirlanio Silva - Grupo Meia Sola**
**Sistema Mercury - Versão 1.0.0**
