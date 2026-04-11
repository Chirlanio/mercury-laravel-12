# Mercury Project - Padroes de Codificacao e Templates

**Ultima Atualizacao:** Abril/2026
**Versao:** 3.0 (Laravel 12 + React 18 + Inertia.js 2)

Este documento define os padroes de codificacao, templates e boas praticas para o projeto Mercury Laravel.

---

## Indice

1. [Nomenclatura](#1-nomenclatura)
2. [Backend — Controllers](#2-backend--controllers)
3. [Backend — Models](#3-backend--models)
4. [Backend — Services](#4-backend--services)
5. [Backend — Rotas](#5-backend--rotas)
6. [Frontend — Componentes Obrigatorios](#6-frontend--componentes-obrigatorios)
7. [Frontend — Pagina de Listagem](#7-frontend--pagina-de-listagem)
8. [Frontend — Modais (StandardModal)](#8-frontend--modais-standardmodal)
9. [Frontend — Hooks Obrigatorios](#9-frontend--hooks-obrigatorios)
10. [Testes](#10-testes)
11. [Boas Praticas](#11-boas-praticas)
12. [Padrao ConfigController (Modulos de Configuracao)](#12-padrao-configcontroller-modulos-de-configuracao)

---

## 1. Nomenclatura

### 1.1 Backend

| Item | Padrao | Exemplo |
|------|--------|---------|
| Controller | PascalCase + `Controller` | `PersonnelMovementController.php` |
| Model | PascalCase singular | `PersonnelMovement.php` |
| Migration | snake_case com prefixo de data | `2026_04_11_create_personnel_movements_table.php` |
| Service | PascalCase + `Service` | `PersonnelMovementTransitionService.php` |
| Enum | PascalCase | `Permission.php`, `Role.php` |
| Trait | PascalCase | `Auditable.php` |
| Tabela DB | snake_case plural | `personnel_movements` |

### 1.2 Frontend

| Item | Padrao | Exemplo |
|------|--------|---------|
| Pagina (Page) | PascalCase dir + `Index.jsx` | `Pages/PersonnelMovements/Index.jsx` |
| Componente | PascalCase | `StandardModal.jsx`, `DataTable.jsx` |
| Hook | camelCase com `use` prefix | `usePermissions.js`, `useModalManager.js` |
| Rota (Ziggy) | kebab-case | `personnel-movements.index` |

### 1.3 Rotas URL

```
/personnel-movements          GET    index
/personnel-movements          POST   store
/personnel-movements/{id}     GET    show
/personnel-movements/{id}     PUT    update
/personnel-movements/{id}     DELETE destroy
```

---

## 2. Backend — Controllers

Controller unico com metodos resourceful. Nao criar controllers separados por acao (ex: ~~`AddMeuModulo`~~, ~~`DeleteMeuModulo`~~).

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
        $query = MeuModulo::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s));

        return Inertia::render('MeuModulo/Index', [
            'items' => $query->latest()->paginate($request->per_page ?? 15),
            'filters' => $request->only(['search', 'status']),
            'statusCounts' => MeuModulo::selectRaw('status, count(*) as total')
                ->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        MeuModulo::create($validated);

        return redirect()->route('meu-modulo.index')
            ->with('success', 'Registro criado com sucesso.');
    }

    public function show(MeuModulo $meuModulo)
    {
        return response()->json([
            'item' => $meuModulo->load('relations'),
        ]);
    }

    public function update(Request $request, MeuModulo $meuModulo)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $meuModulo->update($validated);

        return redirect()->route('meu-modulo.index')
            ->with('success', 'Registro atualizado.');
    }

    public function destroy(MeuModulo $meuModulo)
    {
        $meuModulo->delete();

        return redirect()->route('meu-modulo.index')
            ->with('success', 'Registro excluido.');
    }
}
```

---

## 3. Backend — Models

```php
<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeuModulo extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'status',
        'employee_id',
        'effective_date',
    ];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
```

---

## 4. Backend — Services

Usar services para logica de negocio complexa. Manter controllers magros.

```php
<?php

namespace App\Services;

use App\Models\MeuModulo;

class MeuModuloService
{
    public function transition(MeuModulo $item, string $newStatus, ?string $notes = null): void
    {
        $item->update(['status' => $newStatus]);

        $item->statusHistory()->create([
            'from_status' => $item->getOriginal('status'),
            'to_status' => $newStatus,
            'notes' => $notes,
            'changed_by' => auth()->id(),
        ]);
    }
}
```

---

## 5. Backend — Rotas

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

---

## 6. Frontend — Componentes Obrigatorios

### 6.1 Componentes Compartilhados (`Components/Shared/`)

| Componente | Quando usar | NUNCA fazer |
|---|---|---|
| `StatisticsGrid` | Cards de KPIs/estatisticas no topo de listagens. Props: `cards[]` com `label`, `value`, `format`, `icon`, `color`, `sub`, `variation`, `onClick`, `active`. | Criar cards de stats com HTML/Tailwind avulso ou componentes especificos por modulo. |
| `StatusBadge` | Status, tipos ou labels categorizados. Variantes: success, warning, danger, info, purple, indigo, teal, orange, gray. | Criar badges manuais com `<span className="bg-green-100...">`. |
| `FormSection` | Agrupar campos de formulario. Props: `title`, `cols` (1-4). | Criar `<div>` com titulo e grid manual. |
| `EmptyState` | Tela vazia. Props: `title`, `description`, `icon`, `action`, `compact`. | Criar mensagens "Nenhum registro" com HTML avulso. |
| `LoadingSpinner` | Carregamento. Props: `size`, `color`, `label`, `fullPage`. | Criar spinners com CSS manual. |
| `SkeletonCard` | Placeholder de loading. Props: `lines`, `hasHeader`. | Criar skeletons com divs e `animate-pulse`. |

### 6.2 Componentes Core (`Components/`)

| Componente | Quando usar | NUNCA fazer |
|---|---|---|
| `StandardModal` | **Todo modal da aplicacao.** Header colorido, body scrollavel, footer fixo, loading/error states. Sub-componentes: `.Section`, `.Field`, `.InfoCard`, `.MiniField`, `.Footer`, `.Highlight`, `.Timeline`. | Usar `Modal` base diretamente (reservado para uso interno do `StandardModal`). Criar modais com HTML manual. |
| `Button` | Todo botao. Variantes: primary, secondary, success, warning, danger, info, light, dark, outline. Tamanhos: xs-xl. | Criar `<button>` com estilos inline. |
| `DataTable` | Toda listagem com busca, ordenacao, paginacao. | Criar `<table>` manual. |
| `ActionButtons` | Acoes em colunas de tabela (view, edit, delete + custom). | Criar botoes de acao com HTML avulso. |
| `ConfirmDialog` | Confirmacao de acoes destrutivas. **Usar via `useConfirm()`**. | Usar `window.confirm()`. |
| `TextInput` / `InputLabel` / `InputError` / `Checkbox` | Campos de formulario. | Criar `<input>` com estilos manuais. |
| `ImageUpload` | Upload com drag-and-drop e preview. | Criar `<input type="file">` manual. |
| `EmployeeAvatar` / `UserAvatar` | Avatars com fallback de iniciais. | Criar avatars com `<img>` manual. |

---

## 7. Frontend — Pagina de Listagem

Estrutura obrigatoria para toda pagina de listagem:

```
1. Header (titulo + botoes de acao)
2. StatisticsGrid (cards de KPIs — clicaveis para filtrar)
3. Filtros (bg-white shadow-sm rounded-lg p-4 mb-6)
4. DataTable (colunas com ActionButtons e StatusBadge)
5. Modais (StandardModal para create, detail, edit)
6. ConfirmDialogComponent (do useConfirm)
```

Exemplo completo no `docs/GUIA_IMPLEMENTACAO_MODULOS.md` secao 4.1.

---

## 8. Frontend — Modais (StandardModal)

### 8.1 Modal de Criacao (com formulario)

```jsx
<StandardModal
    show={modals.create}
    onClose={() => closeModal('create')}
    title="Novo Item"
    headerColor="bg-indigo-600"
    headerIcon={<PlusIcon className="h-5 w-5" />}
    onSubmit={handleSubmit}
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
        <FormSection cols={2}>
            <div>
                <InputLabel htmlFor="name" value="Nome" />
                <TextInput id="name" value={data.name}
                    onChange={(e) => setData('name', e.target.value)} />
                <InputError message={errors.name} />
            </div>
        </FormSection>
    </StandardModal.Section>
</StandardModal>
```

### 8.2 Modal de Detalhes (somente leitura)

```jsx
<StandardModal
    show={modals.detail}
    onClose={() => closeModal('detail')}
    title={selected?.name}
    subtitle={selected?.store_name}
    headerColor="bg-gray-700"
    headerBadges={[{ text: selected?.status_label, className: 'bg-green-500/20 text-green-100' }]}
    loading={loadingDetail}
>
    <StandardModal.Section title="Informacoes Gerais">
        <div className="grid grid-cols-2 gap-4">
            <StandardModal.Field label="Nome" value={selected?.name} />
            <StandardModal.Field label="Status" value={selected?.status_label} badge="green" />
            <StandardModal.Field label="Data" value={selected?.created_at} />
            <StandardModal.Field label="Codigo" value={selected?.code} mono />
        </div>
    </StandardModal.Section>

    <StandardModal.Section title="Resumo">
        <div className="grid grid-cols-3 gap-3">
            <StandardModal.InfoCard label="Total" value="R$ 1.500" highlight />
            <StandardModal.InfoCard label="Parcelas" value="3" />
            <StandardModal.InfoCard label="Vencimento" value="15/04/2026" />
        </div>
    </StandardModal.Section>

    {/* Timeline de historico */}
    <StandardModal.Section title="Historico">
        <StandardModal.Timeline items={selected?.history?.map(h => ({
            id: h.id,
            title: h.status_label,
            subtitle: `${h.user_name} - ${h.date}`,
            notes: h.notes,
            dotColor: h.status === 'completed' ? 'bg-green-500' : 'bg-indigo-500',
        }))} />
    </StandardModal.Section>
</StandardModal>
```

### 8.3 Sub-componentes do StandardModal

| Sub-componente | Uso |
|---|---|
| `StandardModal.Section` | Card com header e corpo. Props: `title`, `icon`. |
| `StandardModal.Field` | Label + valor. Props: `label`, `value`, `mono`, `badge`. |
| `StandardModal.InfoCard` | Metrica em destaque. Props: `label`, `value`, `icon`, `highlight`, `colorClass`. |
| `StandardModal.MiniField` | Campo compacto. Props: `label`, `value`. |
| `StandardModal.Footer` | Botoes padrao. Props: `onCancel`, `onSubmit`, `submitLabel`, `processing`, `submitColor`. |
| `StandardModal.Highlight` | Bloco de destaque. Props: `children`, `className`. |
| `StandardModal.Timeline` | Historico. Props: `items[]` com `id`, `title`, `subtitle`, `notes`, `dotColor`. |

---

## 9. Frontend — Hooks Obrigatorios

| Hook | Quando usar | NUNCA fazer |
|---|---|---|
| `usePermissions()` | Verificar permissoes. `hasPermission()`, `hasAnyPermission()`, `hasRole()`. | Hardcodar verificacoes de role. |
| `useTenant()` | Verificar modulos ativos. `hasModule(slug)`. | Logica manual de verificacao de modulo. |
| `useModalManager(names[])` | Gerenciar multiplos modais. `openModal()`, `closeModal()`, `switchModal()`. | Multiplos `useState` para modais. |
| `useConfirm()` | Confirmacao com Promise. | `window.confirm()`. |
| `useMasks` | Mascaras BR: `maskMoney`, `maskCpf`, `maskCnpj`, `maskPhone`, `parseMoney`. | Funcoes de formatacao avulsas. |

---

## 10. Testes

### 10.1 Padrao

- Arquivo: `tests/Feature/{Modulo}ControllerTest.php`
- Trait: `TestHelpers` para setup de usuarios e dados
- DB: SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`)
- Executar: `C:\Users\MSDEV\php84\php.exe artisan test --filter=MeuModuloControllerTest`

### 10.2 Cobertura minima

- Autenticacao (rota protegida redireciona para login)
- Permissoes (usuario sem permissao recebe 403)
- CRUD completo (index, store, show, update, destroy)
- Validacao (campos obrigatorios, tipos, limites)
- Filtros (busca, status, paginacao)

---

## 11. Boas Praticas

### 11.1 Gerais

- Controllers magros, logica complexa em Services
- Usar `Auditable` trait em todos os models
- Flash messages via `->with('success', '...')` — exibidas como toast automaticamente
- Navegacao via `Inertia router.visit()` e `router.post()` — nunca Axios para dados de pagina

### 11.2 Frontend

- Icones: sempre `@heroicons/react/24/outline` (ou `/24/solid` para enfase)
- Formularios: `useForm()` do Inertia para state + submit + errors
- Envio de formulario em modal: usar prop `onSubmit` do `StandardModal` (converte body em `<form>`)
- Cores de header do `StandardModal`: `bg-indigo-600` (criar), `bg-gray-700` (detalhe), `bg-amber-600` (editar), `bg-red-600` (excluir)

### 11.3 Seguranca

- Middlewares `permission:` e `tenant.module:` em todas as rotas
- Validacao no backend (nunca confiar em validacao frontend)
- Verificar permissoes no frontend com `usePermissions()` para UX (ocultar botoes)
- Usar `$request->validate()` — nunca inserir dados sem validacao

---

## 12. Padrao ConfigController (Modulos de Configuracao)

Para modulos CRUD simples (Position, Sector, Gender, etc.):

- Estender `app/Http/Controllers/ConfigController.php`
- Definir: `modelClass()`, `viewTitle()`, `columns()`, `formFields()`, `validationRules()`
- Frontend: reutiliza `Pages/Config/Index.jsx` (generico, data-driven)
- Rota: `/config/{module}` com permissao `MANAGE_SETTINGS`

```php
class PositionController extends ConfigController
{
    protected function modelClass(): string { return Position::class; }
    protected function viewTitle(): string { return 'Cargos'; }
    protected function columns(): array { return [['field' => 'name', 'label' => 'Nome']]; }
    protected function formFields(): array { return [['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true]]; }
    protected function validationRules(): array { return ['name' => 'required|string|max:255']; }
}
```

---

**Mantido por:** Equipe Mercury — Grupo Meia Sola
