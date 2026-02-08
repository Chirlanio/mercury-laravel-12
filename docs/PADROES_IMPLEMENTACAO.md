# Padroes de Implementacao - Mercury Laravel

## 1. Estrutura de Arquivos

```
app/
  Http/Controllers/          # Controllers
  Http/Controllers/Admin/    # Controllers administrativos
  Models/                    # Eloquent Models
  Services/                  # Business logic services
  Enums/                     # Enums (Permission, Role)
  Traits/                    # Traits reutilizaveis

resources/js/
  Pages/                     # React pages (Inertia)
  Components/                # React components reutilizaveis
  Hooks/                     # Custom React hooks
  Layouts/                   # Layout components

routes/
  web.php                    # Rotas web
  auth.php                   # Rotas de autenticacao
```

## 2. Model Pattern

```php
class ExampleModel extends Model
{
    use HasFactory;

    protected $table = 'examples';

    protected $fillable = ['name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Static helpers
    public static function getOptions(): array
    {
        return static::pluck('name', 'id')->toArray();
    }
}
```

## 3. Controller Pattern (CRUD Simples)

Baseado em `ColorThemeController`:

```php
class ExampleController extends Controller
{
    public function index(Request $request)
    {
        // 1. Parametros de paginacao/filtro/ordenacao
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');

        // 2. Validar campos permitidos
        // 3. Montar query com busca e ordenacao
        // 4. Paginar
        // 5. Retornar via Inertia::render com items, filters, stats
    }

    public function store(Request $request)
    {
        // 1. Validar inline
        // 2. Criar registro
        // 3. return back()->with('success', '...');
    }

    public function update(Request $request, Model $model)
    {
        // 1. Validar (unique com excecao do ID)
        // 2. Atualizar
        // 3. return back()->with('success', '...');
    }

    public function destroy(Model $model)
    {
        // 1. Verificar se esta em uso (relationships)
        // 2. Se em uso, return back()->with('error', '...');
        // 3. Deletar
        // 4. return back()->with('success', '...');
    }
}
```

## 4. Route Pattern

```php
// Agrupar por permissao
Route::middleware(['auth', 'permission:' . Permission::MANAGE_SETTINGS->value])
    ->prefix('config')
    ->group(function () {
        Route::resource('items', ItemController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });
```

## 5. React Page Pattern

```jsx
export default function Index({ auth, items, filters, stats }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [selectedItem, setSelectedItem] = useState(null);
    const { hasPermission } = usePermissions();

    // Columns para DataTable
    const columns = [/* ... */];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="..." />
            {/* Header + Stats + DataTable + Modals */}
        </AuthenticatedLayout>
    );
}
```

## 6. Permission Pattern

- Backend: `Permission` enum em `app/Enums/Permission.php`
- Middleware: `'permission:' . Permission::CASE->value`
- Frontend: `usePermissions()` hook + `PERMISSIONS` constantes
- Role matrix: definido em `usePermissions.js` (ROLE_PERMISSIONS)

## 7. Validation Pattern

- Validacao inline no controller (sem FormRequest para CRUDs simples)
- `unique` com excecao do ID em updates: `'unique:table,field,' . $model->id`
- Mensagens flash: `back()->with('success|error', '...')`

## 8. Generic Components

- **DataTable**: Tabela com busca, ordenacao e paginacao
- **GenericFormModal**: Modal generico para criar/editar com secoes e campos dinamicos
- **ConfirmDialog**: Dialog de confirmacao com tipos (warning, danger, info, success)
- **Button**: Botao com variantes, tamanhos e icones
- **Modal**: Modal base com transicoes

## 9. Padrao de Modulo de Configuracao

Para modulos simples de CRUD (config), usar o `ConfigController` base:

1. Criar controller que extends `ConfigController`
2. Definir configuracao (model, campos, validacao, etc.)
3. Registrar rota em `web.php`
4. Pagina React generica `Config/Index.jsx` renderiza tudo

Ver `app/Http/Controllers/ConfigController.php` para detalhes.
