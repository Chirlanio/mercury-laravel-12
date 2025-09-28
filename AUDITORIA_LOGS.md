# Sistema de Auditoria e Logs

Este documento descreve o sistema de auditoria e logs implementado no projeto Mercury Laravel.

## 📋 Funcionalidades Implementadas

### ✅ Estrutura Base
- **Modelo ActivityLog**: Model completo para armazenar logs de auditoria
- **Migration**: Tabela `activity_logs` com todos os campos necessários
- **Middleware**: `ActivityLogMiddleware` para captura automática de ações HTTP

### ✅ Serviços e Traits
- **AuditLogService**: Serviço completo para gerenciamento de logs
- **Trait Auditable**: Trait para auditoria automática de models Eloquent
- **Configuração**: Arquivo `config/audit.php` com configurações personalizáveis

### ✅ Interface Web
- **Controller**: `ActivityLogController` com visualização, filtros e exportação
- **Views React**: Interface completa com:
  - Listagem de logs com filtros avançados
  - Visualização detalhada de logs individuais
  - Estatísticas em tempo real
  - Exportação de dados

### ✅ Comandos Artisan
- **audit:stats**: Estatísticas detalhadas com gráficos
- **audit:cleanup**: Limpeza automática de logs antigos

### ✅ Funcionalidades Avançadas
- Exportação em CSV, Excel e JSON
- Detecção de atividade suspeita
- Auditoria automática de models
- Sistema de permissões integrado

## 🚀 Como Usar

### 1. Auditoria Automática de Models

Para habilitar auditoria automática em qualquer model:

```php
<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class ExemploModel extends Model
{
    use Auditable;

    // Seu model funcionará normalmente
    // As alterações serão auditadas automaticamente
}
```

### 2. Logs Manuais

Para registrar logs manualmente:

```php
use App\Services\AuditLogService;

$auditService = app(AuditLogService::class);

// Log simples
$auditService->log('custom_action', 'Descrição da ação');

// Log com modelo
$auditService->logModelCreated($model);
$auditService->logModelUpdated($model, $oldValues);
$auditService->logModelDeleted($model);

// Log de acesso
$auditService->logResourceAccess('dashboard');

// Log customizado
$auditService->logCustomAction(
    action: 'backup_created',
    description: 'Backup automático criado',
    metadata: ['size' => '150MB', 'duration' => '2min']
);
```

### 3. Auditoria de Ações Específicas

Para registrar ações específicas em um model:

```php
// Em qualquer lugar do seu código
$user = User::find(1);

// Registra que o usuário foi visualizado
$user->logAccess('perfil');

// Registra uma ação customizada
$user->logCustomAction(
    'password_reset_requested',
    'Usuário solicitou reset de senha',
    ['ip' => request()->ip()]
);
```

### 4. Comandos Artisan

#### Visualizar Estatísticas
```bash
# Estatísticas dos últimos 30 dias
php artisan audit:stats

# Estatísticas dos últimos 7 dias
php artisan audit:stats --days=7

# Exportar estatísticas para JSON
php artisan audit:stats --export

# Saída em formato JSON
php artisan audit:stats --format=json
```

#### Limpeza de Logs
```bash
# Simular limpeza (não remove nada)
php artisan audit:cleanup --dry-run

# Limpar logs mais antigos que 90 dias
php artisan audit:cleanup --days=90

# Limpeza forçada (sem confirmação)
php artisan audit:cleanup --days=90 --force
```

### 5. Interface Web

Acesse as rotas de auditoria:

- **Listagem**: `/activity-logs`
- **Detalhes**: `/activity-logs/{id}`
- **Exportação**: Via interface web com filtros

## ⚙️ Configuração

### Arquivo de Configuração

Edite `config/audit.php` para personalizar:

```php
return [
    // Habilitar/desabilitar auditoria globalmente
    'enabled' => env('AUDIT_ENABLED', true),

    // Configurações de limpeza automática
    'cleanup' => [
        'enabled' => env('AUDIT_CLEANUP_ENABLED', false),
        'older_than_days' => env('AUDIT_CLEANUP_DAYS', 365),
    ],

    // Detecção de atividade suspeita
    'suspicious_activity' => [
        'enabled' => env('AUDIT_SUSPICIOUS_DETECTION', true),
        'time_window_minutes' => 5,
        'max_actions_per_window' => 20,
    ],

    // Configurações de exportação
    'export' => [
        'formats' => ['csv', 'excel', 'json'],
        'max_records' => 10000,
    ],
];
```

### Variáveis de Ambiente

Adicione ao seu `.env`:

```env
# Auditoria
AUDIT_ENABLED=true
AUDIT_CLEANUP_ENABLED=false
AUDIT_CLEANUP_DAYS=365
AUDIT_SUSPICIOUS_DETECTION=true
AUDIT_EXPORT_MAX_RECORDS=10000
```

## 🔐 Permissões

O sistema integra com o sistema de permissões existente:

- `VIEW_ACTIVITY_LOGS`: Visualizar logs de auditoria
- `EXPORT_ACTIVITY_LOGS`: Exportar logs
- `MANAGE_SYSTEM_SETTINGS`: Limpeza de logs (super admin)

## 📊 Tipos de Logs Registrados

### Automáticos (via Middleware)
- Login/Logout de usuários
- Criação, edição e exclusão via web
- Tentativas de acesso negado

### Automáticos (via Trait Auditable)
- Criação de registros
- Atualização de registros
- Exclusão de registros

### Manuais (via Service)
- Ações customizadas
- Acesso a recursos
- Operações de sistema

## 🛡️ Segurança

### Dados Sensíveis
Campos sensíveis são automaticamente ignorados:
- Senhas
- Tokens
- Timestamps (updated_at, created_at)

### Detecção de Atividade Suspeita
O sistema monitora:
- Muitas ações em pouco tempo
- Múltiplos IPs diferentes
- Tentativas de acesso negado

### Auditoria da Auditoria
Operações de limpeza e manutenção são registradas.

## 🔧 Manutenção

### Limpeza Automática
Configure limpeza automática via cron:

```bash
# No crontab
0 2 * * * cd /path/to/project && php artisan audit:cleanup --force --days=365
```

### Monitoramento
Use o comando de estatísticas para monitorar:

```bash
# Script de monitoramento
php artisan audit:stats --days=1 --format=json > daily_audit_stats.json
```

## 📈 Performance

### Otimizações Implementadas
- Índices de banco de dados otimizados
- Processamento em lotes para limpeza
- Configuração de campos ignorados
- Opção de processamento via queue (configurável)

### Recomendações
- Execute limpeza regularmente
- Monitore crescimento da tabela
- Use filtros específicos nas exportações
- Configure retenção por tipo de ação

## 🐛 Troubleshooting

### Logs não aparecem
1. Verifique se `AUDIT_ENABLED=true`
2. Confirme que o middleware está registrado
3. Verifique permissões do usuário

### Performance lenta
1. Execute limpeza de logs antigos
2. Verifique índices do banco
3. Use filtros nas consultas

### Exportação falhando
1. Verifique limite de registros
2. Use filtros mais específicos
3. Confirme permissões

## 📚 Exemplos Práticos

### Exemplo 1: Auditoria de Upload de Arquivo
```php
public function uploadFile(Request $request)
{
    $file = $request->file('document');

    // Salvar arquivo...

    // Registrar na auditoria
    app(AuditLogService::class)->logCustomAction(
        action: 'file_upload',
        description: "Upload do arquivo: {$file->getClientOriginalName()}",
        metadata: [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]
    );
}
```

### Exemplo 2: Auditoria de Mudança de Status
```php
public function changeOrderStatus(Order $order, string $newStatus)
{
    $oldStatus = $order->status;
    $order->update(['status' => $newStatus]);

    // A trait Auditable já registrará a mudança automaticamente
    // Mas podemos adicionar um log customizado para melhor descrição
    $order->logCustomAction(
        'status_changed',
        "Status alterado de {$oldStatus} para {$newStatus}"
    );
}
```

### Exemplo 3: Monitoramento de Acesso a Dados Sensíveis
```php
public function viewSensitiveData(User $user)
{
    // Registrar acesso antes de mostrar os dados
    app(AuditLogService::class)->logResourceAccess(
        resource: 'dados_sensíveis_usuario',
        model: $user
    );

    // Continuar com a lógica...
}
```

---

## 📞 Suporte

Para dúvidas ou problemas:
1. Verifique os logs do Laravel em `storage/logs/`
2. Execute `php artisan audit:stats` para verificar o status
3. Consulte este documento para exemplos de uso