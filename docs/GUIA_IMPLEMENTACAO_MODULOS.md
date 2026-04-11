# Guia de Implementacao para Novos Modulos

**Ultima Atualizacao:** Abril/2026
**Versao:** 3.0 (Laravel 12 + React 18 + Inertia.js 2)

---

## 1. Introducao

Este documento estabelece o padrao de arquitetura e o fluxo de trabalho para a criacao de novos modulos no Mercury Laravel. **Todos os modulos devem seguir esta estrutura para manter consistencia visual e tecnica.**

### 1.1 Modulos de Referencia

| Complexidade | Modulo | Diretorio |
|---|---|---|
| CRUD completo com stats | Transfers | `Pages/Transfers/` |
| Workflow com state machine | PersonnelMovements | `Pages/PersonnelMovements/` |
| Sync externa + CIGAM | Products | `Pages/Products/` |
| Config simples (CRUD generico) | Position, Sector, etc. | `Pages/Config/` (generico) |

### 1.2 Stack

- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** React 18 + Inertia.js 2 (sem API separada)
- **Componentes:** Biblioteca padrao em `Components/` e `Components/Shared/`
- **Estilo:** Tailwind CSS 3 + Heroicons (`@heroicons/react`)

---

## 2. Estrutura de Arquivos

Para um novo modulo chamado `MeuModulo`:

```
app/
├── Http/Controllers/
│   └── MeuModuloController.php       # Controller unico (resourceful)
├── Models/
│   └── MeuModulo.php                 # Eloquent model
├── Services/
│   └── MeuModuloService.php          # Logica de negocio (se necessario)
│
database/migrations/tenant/
│   └── 2026_xx_xx_create_meu_modulos_table.php
│
resources/js/Pages/MeuModulo/
│   ├── Index.jsx                     # Pagina principal (listagem)
│   ├── CreateModal.jsx               # Modal de criacao (se complexo)
│   ├── DetailModal.jsx               # Modal de visualizacao
│   └── EditModal.jsx                 # Modal de edicao (se complexo)
│
routes/
│   └── tenant-routes.php             # Rotas do modulo
│
tests/Feature/
│   └── MeuModuloControllerTest.php   # Testes de feature
```

### 2.1 Quando separar modais em arquivos

- **Simples (< 50 linhas de form):** Modal inline no `Index.jsx`
- **Medio (50-150 linhas):** Arquivo separado no diretorio do modulo
- **Complexo (> 150 linhas ou logica propria):** Arquivo separado com sub-componentes

---

## 3. Backend

### 3.1 Controller (Resourceful)

```php
<?php

namespace App\Http\Controllers;

use App\Models\MeuModulo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MeuModuloController extends Controller
{
    public function index(Request $request)
    {
        $query = MeuModulo::query();

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Estatisticas para StatisticsGrid
        $statusCounts = MeuModulo::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('MeuModulo/Index', [
            'items' => $query->latest()->paginate($request->per_page ?? 15),
            'filters' => $request->only(['status', 'search']),
            'statusCounts' => $statusCounts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // ... campos
        ]);

        MeuModulo::create($validated);

        return redirect()->route('meu-modulo.index')
            ->with('success', 'Registro criado com sucesso.');
    }

    public function show(MeuModulo $meuModulo)
    {
        return response()->json(['item' => $meuModulo->load('relations')]);
    }

    public function update(Request $request, MeuModulo $meuModulo)
    {
        $validated = $request->validate([...]);
        $meuModulo->update($validated);

        return redirect()->route('meu-modulo.index')
            ->with('success', 'Registro atualizado com sucesso.');
    }

    public function destroy(MeuModulo $meuModulo)
    {
        $meuModulo->delete();

        return redirect()->route('meu-modulo.index')
            ->with('success', 'Registro excluido com sucesso.');
    }
}
```

### 3.2 Rotas

```php
// routes/tenant-routes.php
Route::middleware(['auth', 'permission:VIEW_MEU_MODULO', 'tenant.module:meu-modulo'])
    ->prefix('meu-modulo')
    ->name('meu-modulo.')
    ->controller(MeuModuloController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store')
            ->middleware('permission:CREATE_MEU_MODULO');
        Route::get('/{meuModulo}', 'show')->name('show');
        Route::put('/{meuModulo}', 'update')->name('update')
            ->middleware('permission:EDIT_MEU_MODULO');
        Route::delete('/{meuModulo}', 'destroy')->name('destroy')
            ->middleware('permission:DELETE_MEU_MODULO');
    });
```

### 3.3 Model

```php
<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class MeuModulo extends Model
{
    use Auditable;

    protected $fillable = ['name', 'status', ...];

    protected $casts = [
        'effective_date' => 'date',
    ];
}
```

### 3.4 Permissions

Adicionar ao `app/Enums/Permission.php`:

```php
case VIEW_MEU_MODULO = 'view_meu_modulo';
case CREATE_MEU_MODULO = 'create_meu_modulo';
case EDIT_MEU_MODULO = 'edit_meu_modulo';
case DELETE_MEU_MODULO = 'delete_meu_modulo';
```

Adicionar ao `resources/js/Hooks/usePermissions.js` na constante `PERMISSIONS`:

```js
VIEW_MEU_MODULO: 'view_meu_modulo',
CREATE_MEU_MODULO: 'create_meu_modulo',
EDIT_MEU_MODULO: 'edit_meu_modulo',
DELETE_MEU_MODULO: 'delete_meu_modulo',
```

---

## 4. Frontend — Componentes Obrigatorios

**REGRA:** Nunca crie componentes especificos para um modulo quando ja existe um generico reutilizavel. Consulte `CLAUDE.md` secao "Frontend Component Standards" para a lista completa.

### 4.1 Pagina de Listagem (Index.jsx)

```jsx
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { PlusIcon, XMarkIcon, ChartBarIcon, ClockIcon, CheckCircleIcon } from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import { useConfirm } from '@/Hooks/useConfirm';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';

export default function Index({ items, filters = {}, statusCounts = {} }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_MEU_MODULO);
    const canEdit = hasPermission(PERMISSIONS.EDIT_MEU_MODULO);
    const canDelete = hasPermission(PERMISSIONS.DELETE_MEU_MODULO);

    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'detail', 'edit']);
    const { confirm, ConfirmDialogComponent } = useConfirm();

    // ── Filtros ──
    const applyFilter = (key, value) => {
        const params = { ...filters, [key]: value || undefined };
        router.visit(route('meu-modulo.index', params), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // ── Acoes ──
    const handleDelete = async (item) => {
        const confirmed = await confirm({
            title: 'Excluir registro',
            message: `Tem certeza que deseja excluir "${item.name}"?`,
            type: 'danger',
            confirmText: 'Excluir',
        });
        if (confirmed) {
            router.delete(route('meu-modulo.destroy', item.id), {
                preserveState: true,
                preserveScroll: true,
            });
        }
    };

    // ── Colunas da DataTable ──
    const columns = [
        { field: 'name', label: 'Nome', sortable: true },
        {
            field: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => (
                <StatusBadge variant={row.status === 'active' ? 'success' : 'gray'}>
                    {row.status_label}
                </StatusBadge>
            ),
        },
        { field: 'created_at', label: 'Data', sortable: true },
        {
            field: 'actions',
            label: 'Acoes',
            render: (row) => (
                <ActionButtons
                    onView={() => openModal('detail', row)}
                    onEdit={canEdit ? () => openModal('edit', row) : null}
                    onDelete={canDelete ? () => handleDelete(row) : null}
                />
            ),
        },
    ];

    // ── Cards de estatisticas ──
    const statsCards = [
        {
            label: 'Total',
            value: Object.values(statusCounts).reduce((s, v) => s + (v || 0), 0),
            format: 'number',
            icon: ChartBarIcon,
            color: 'indigo',
        },
        {
            label: 'Pendentes',
            value: statusCounts.pending || 0,
            format: 'number',
            icon: ClockIcon,
            color: 'yellow',
            active: filters.status === 'pending',
            onClick: () => applyFilter('status', filters.status === 'pending' ? '' : 'pending'),
        },
        {
            label: 'Concluidos',
            value: statusCounts.completed || 0,
            format: 'number',
            icon: CheckCircleIcon,
            color: 'green',
            active: filters.status === 'completed',
            onClick: () => applyFilter('status', filters.status === 'completed' ? '' : 'completed'),
        },
    ];

    return (
        <>
            <Head title="Meu Modulo" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

                    {/* 1. Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">Meu Modulo</h1>
                                <p className="mt-1 text-sm text-gray-600">Descricao do modulo</p>
                            </div>
                            {canCreate && (
                                <Button variant="primary" onClick={() => openModal('create')} icon={PlusIcon}>
                                    Novo Registro
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* 2. StatisticsGrid */}
                    <StatisticsGrid cards={statsCards} />

                    {/* 3. Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select
                                    value={filters.status || ''}
                                    onChange={(e) => applyFilter('status', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos</option>
                                    <option value="active">Ativos</option>
                                    <option value="inactive">Inativos</option>
                                </select>
                            </div>
                            <div>
                                <Button variant="secondary" size="sm" className="h-[42px]"
                                    onClick={() => router.visit(route('meu-modulo.index'))}
                                    icon={XMarkIcon}>
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* 4. DataTable */}
                    <DataTable
                        data={items}
                        columns={columns}
                        searchPlaceholder="Buscar..."
                        emptyMessage="Nenhum registro encontrado"
                        onRowClick={(row) => openModal('detail', row)}
                    />
                </div>
            </div>

            {/* 5. Modais — SEMPRE usar StandardModal */}

            {/* Modal de Criacao */}
            <StandardModal
                show={modals.create}
                onClose={() => closeModal('create')}
                title="Novo Registro"
                headerColor="bg-indigo-600"
                headerIcon={<PlusIcon className="h-5 w-5" />}
                onSubmit={handleCreate}
                footer={
                    <StandardModal.Footer
                        onCancel={() => closeModal('create')}
                        onSubmit="submit"
                        submitLabel="Salvar"
                        processing={processing}
                    />
                }
            >
                <StandardModal.Section title="Dados Gerais">
                    <div className="grid grid-cols-2 gap-4">
                        {/* TextInput, InputLabel, InputError para cada campo */}
                    </div>
                </StandardModal.Section>
            </StandardModal>

            {/* Modal de Detalhes */}
            <StandardModal
                show={modals.detail}
                onClose={() => closeModal('detail')}
                title={selected?.name || 'Detalhes'}
                headerColor="bg-gray-700"
            >
                <StandardModal.Section title="Informacoes">
                    <div className="grid grid-cols-2 gap-4">
                        <StandardModal.Field label="Nome" value={selected?.name} />
                        <StandardModal.Field label="Status" value={selected?.status_label} badge="green" />
                    </div>
                </StandardModal.Section>
            </StandardModal>

            {/* ConfirmDialog (obrigatorio via useConfirm) */}
            {ConfirmDialogComponent}
        </>
    );
}
```

### 4.2 Referencia Rapida de Componentes

| Necessidade | Componente | Import |
|---|---|---|
| Cards de KPIs/stats | `StatisticsGrid` | `@/Components/Shared/StatisticsGrid` |
| Badges de status | `StatusBadge` | `@/Components/Shared/StatusBadge` |
| Secoes de formulario | `FormSection` | `@/Components/Shared/FormSection` |
| Estado vazio | `EmptyState` | `@/Components/Shared/EmptyState` |
| Loading spinner | `LoadingSpinner` | `@/Components/Shared/LoadingSpinner` |
| Skeleton de loading | `SkeletonCard` | `@/Components/Shared/SkeletonCard` |
| Botoes | `Button` | `@/Components/Button` |
| Tabela com paginacao | `DataTable` | `@/Components/DataTable` |
| Acoes em tabela | `ActionButtons` | `@/Components/ActionButtons` |
| Modal padrao | `StandardModal` | `@/Components/StandardModal` |
| Confirmacao | `useConfirm()` | `@/Hooks/useConfirm` |
| Gerenciar modais | `useModalManager()` | `@/Hooks/useModalManager` |
| Permissoes | `usePermissions()` | `@/Hooks/usePermissions` |
| Modulos tenant | `useTenant()` | `@/Hooks/useTenant` |
| Mascaras BR | `useMasks` | `@/Hooks/useMasks` |
| Inputs de formulario | `TextInput` / `InputLabel` / `InputError` | `@/Components/TextInput` etc. |
| Upload de imagem | `ImageUpload` | `@/Components/ImageUpload` |
| Avatar | `EmployeeAvatar` / `UserAvatar` | `@/Components/EmployeeAvatar` |

### 4.3 O que NUNCA fazer

- Criar componente de estatisticas especifico por modulo (ex: `SaleStatisticsCards`) — usar `StatisticsGrid`
- Usar `Modal` base diretamente — sempre usar `StandardModal`
- Usar `window.confirm()` — sempre usar `useConfirm()`
- Criar multiplos `useState` para modais — sempre usar `useModalManager()`
- Criar badges com HTML/Tailwind inline — sempre usar `StatusBadge`
- Criar botoes com `<button className="bg-...">` — sempre usar `Button`
- Criar tabelas com `<table>` manual — sempre usar `DataTable`
- Hardcodar verificacoes de role — sempre usar `usePermissions()`

---

## 5. Testes

### 5.1 Estrutura

```php
<?php

namespace Tests\Feature;

use App\Models\MeuModulo;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class MeuModuloControllerTest extends TestCase
{
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestUsers(); // admin, support, regular
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('meu-modulo.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_view_index(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('meu-modulo.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MeuModulo/Index')
                ->has('items')
                ->has('filters')
            );
    }

    public function test_admin_can_create(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('meu-modulo.store'), [
                'name' => 'Teste',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('meu_modulos', ['name' => 'Teste']);
    }

    public function test_admin_can_delete(): void
    {
        $item = MeuModulo::factory()->create();

        $this->actingAs($this->adminUser)
            ->delete(route('meu-modulo.destroy', $item))
            ->assertRedirect();

        $this->assertDatabaseMissing('meu_modulos', ['id' => $item->id]);
    }
}
```

### 5.2 Executar testes

```bash
C:\Users\MSDEV\php84\php.exe artisan test --filter=MeuModuloControllerTest
```

---

## 6. Checklist de Novo Modulo

- [ ] **Migration** criada em `database/migrations/tenant/`
- [ ] **Model** com `Auditable` trait e `$fillable`
- [ ] **Controller** resourceful com `Inertia::render()`
- [ ] **Permissions** adicionadas em `Permission.php` e `usePermissions.js`
- [ ] **Rotas** com middlewares `auth`, `permission:`, `tenant.module:`
- [ ] **Frontend** usando componentes obrigatorios:
  - [ ] `StatisticsGrid` para KPIs
  - [ ] `StandardModal` para todos os modais
  - [ ] `DataTable` para listagem
  - [ ] `ActionButtons` para acoes em tabela
  - [ ] `StatusBadge` para status
  - [ ] `Button` para botoes
  - [ ] `useModalManager()` para controle de modais
  - [ ] `useConfirm()` para confirmacoes
  - [ ] `usePermissions()` para verificacao de acesso
- [ ] **Testes** de feature cobrindo CRUD + permissoes
- [ ] **Seeder** de navegacao em `CentralNavigationSeeder`

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
