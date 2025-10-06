# Sistema de Menu Dinâmico - Mapeamento de Rotas

## Visão Geral

O sistema de menu dinâmico converte automaticamente as rotas antigas do formato `controller/method` para as rotas Laravel corretas.

## Arquitetura

### 1. MenuService (app/Services/MenuService.php)

O `MenuService` é responsável por:
- Buscar menus e páginas baseados no `access_level_id` do usuário
- Converter rotas antigas para rotas Laravel
- Retornar estrutura de menu formatada

### 2. Mapeamento de Rotas

O mapeamento está em `MenuService::getRouteMapping()` e segue o formato:

```php
'controller-antigo/metodo-antigo' => '/rota-laravel-nova'
```

### 3. Rotas Mapeadas

As principais rotas já mapeadas incluem:

#### Dashboard/Home
- `home/index` → `/dashboard`
- `dashboard/listar` → `/dashboard`

#### Usuários & Acesso
- `usuarios/listar` → `/users`
- `nivel-acesso/listar` → `/access-levels`
- `employees/list` → `/employees`

#### Administração
- `pagina/listar` → `/pages`
- `menu/listar` → `/menus`
- `editar-conf-email/edit-conf-email` → `/admin/email-settings`

#### Controle de Jornada
- `overtime-control/list` → `/work-shifts`

#### Outros
- `login/logout` → `/logout`

**Ver lista completa em:** `app/Services/MenuService.php` método `getRouteMapping()`

## Como Adicionar Novas Rotas

### Passo 1: Identificar a Rota Antiga

Verifique no banco de dados (`pages` table) qual é o `menu_controller` e `menu_method`:

```sql
SELECT id, page_name, menu_controller, menu_method
FROM pages
WHERE page_name = 'Nome da Página';
```

### Passo 2: Adicionar ao Mapeamento

Edite `app/Services/MenuService.php` e adicione a rota em `getRouteMapping()`:

```php
private static function getRouteMapping(): array
{
    return [
        // ... outras rotas ...

        // Sua nova rota
        'controller-antigo/metodo-antigo' => '/nova-rota-laravel',
    ];
}
```

### Passo 3: Testar

1. Limpe o cache:
```bash
php artisan optimize:clear
```

2. Acesse o sistema e verifique se a rota está funcionando no menu

## Rotas Sem Mapeamento

Se uma rota não tiver mapeamento definido, o sistema usará a rota antiga formatada:

```
controller/method → /controller/method
```

## Estrutura do Menu Retornado

```json
{
    "main": [
        {
            "id": 1,
            "name": "Home",
            "icon": "fas fa-home",
            "order": 1,
            "is_dropdown": true,
            "items": [
                {
                    "id": 1,
                    "name": "Home",
                    "controller": "home",
                    "method": "index",
                    "route": "/dashboard",  // ← Rota convertida
                    "icon": "fas fa-home",
                    "order": 1,
                    "permission": true
                }
            ]
        }
    ],
    "hr": [],
    "utility": [],
    "system": []
}
```

## Sidebar (Frontend)

O componente `Sidebar.jsx` consome as rotas já convertidas:

```javascript
const itemRoute = item.route; // Rota já vem correta do backend

<button onClick={() => router.get(itemRoute)}>
    {item.name}
</button>
```

## Benefícios

1. **Centralizado**: Todo mapeamento em um só lugar
2. **Compatibilidade**: Mantém compatibilidade com sistema antigo
3. **Flexível**: Fácil adicionar/modificar rotas
4. **Performance**: Conversão feita no backend, não no frontend

## Troubleshooting

### Menu não aparece
1. Verifique se o usuário tem `access_level_id` definido
2. Verifique se há registros em `access_level_pages` para aquele nível
3. Confirme que `permission = true` e `lib_menu = true`

### Rota errada
1. Adicione o mapeamento correto em `getRouteMapping()`
2. Limpe o cache: `php artisan optimize:clear`

### Rota não existe
1. Crie a rota em `routes/web.php`
2. Adicione ao mapeamento
3. Teste acessando diretamente a URL

## Exemplos

### Adicionar Nova Funcionalidade

**Cenário**: Adicionar página "Relatórios de Vendas"

1. **Criar rota Laravel**:
```php
// routes/web.php
Route::get('/relatorios/vendas', [RelatoriosController::class, 'vendas'])
    ->name('relatorios.vendas');
```

2. **Adicionar ao mapeamento**:
```php
// app/Services/MenuService.php
'relatorios/listar-vendas' => '/relatorios/vendas',
```

3. **Configurar permissão**:
- Acesse `/access-levels`
- Clique em "Gerenciar menus"
- Selecione o menu desejado
- Marque a página "Relatórios de Vendas"
- Salve

Pronto! A rota aparecerá no menu para usuários com permissão.
