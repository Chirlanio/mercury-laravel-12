<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

abstract class ConfigController extends Controller
{
    /**
     * FQCN do model (ex: \App\Models\Gender::class)
     */
    abstract protected function modelClass(): string;

    /**
     * Titulo da pagina
     */
    abstract protected function viewTitle(): string;

    /**
     * Descricao da pagina
     */
    abstract protected function viewDescription(): string;

    /**
     * Definicao de colunas para a DataTable
     * Formato: [['key' => 'name', 'label' => 'Nome', 'sortable' => true], ...]
     */
    abstract protected function columns(): array;

    /**
     * Definicao de secoes do formulario para o GenericFormModal
     * Formato compativel com o componente GenericFormModal
     */
    abstract protected function formFields(): array;

    /**
     * Regras de validacao
     */
    abstract protected function validationRules(bool $isUpdate = false, $id = null): array;

    /**
     * Campos pesquisaveis (usado no WHERE LIKE)
     */
    protected function searchableFields(): array
    {
        return ['name'];
    }

    /**
     * Campos permitidos para ordenacao
     */
    protected function sortableFields(): array
    {
        return ['id', 'name', 'created_at'];
    }

    /**
     * Campo de ordenacao padrao
     */
    protected function defaultSort(): string
    {
        return 'name';
    }

    /**
     * Direcao de ordenacao padrao
     */
    protected function defaultSortDirection(): string
    {
        return 'asc';
    }

    /**
     * Registros por pagina
     */
    protected function perPage(): int
    {
        return 15;
    }

    /**
     * Verificar se o item pode ser excluido.
     * Retorna true se pode, ou uma string com mensagem de erro.
     */
    protected function canDelete($model): bool|string
    {
        return true;
    }

    /**
     * Dados adicionais para a view (selects, etc.)
     */
    protected function additionalData(): array
    {
        return [];
    }

    /**
     * Transformar item antes de enviar para a view
     */
    protected function transformItem($item): array
    {
        return $item->toArray();
    }

    /**
     * Eager load de relacionamentos para a listagem
     */
    protected function with(): array
    {
        return [];
    }

    /**
     * Estatisticas extras (alem do total)
     */
    protected function stats(): array
    {
        return [];
    }

    /**
     * Nome da rota base (ex: 'config.genders')
     */
    abstract protected function routeName(): string;

    /**
     * Index - Listagem com busca, ordenacao e paginacao
     */
    public function index(Request $request)
    {
        $modelClass = $this->modelClass();
        $perPage = $request->get('per_page', $this->perPage());
        $search = $request->get('search');
        $sortField = $request->get('sort', $this->defaultSort());
        $sortDirection = $request->get('direction', $this->defaultSortDirection());

        // Validar campo de ordenacao
        if (!in_array($sortField, $this->sortableFields())) {
            $sortField = $this->defaultSort();
        }

        // Validar direcao
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = $this->defaultSortDirection();
        }

        $query = $modelClass::query();

        // Eager load
        if (!empty($this->with())) {
            $query->with($this->with());
        }

        // Busca
        if ($search) {
            $searchableFields = $this->searchableFields();
            $query->where(function ($q) use ($search, $searchableFields) {
                foreach ($searchableFields as $field) {
                    $q->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        // Ordenacao
        $query->orderBy($sortField, $sortDirection);

        // Paginacao
        $items = $query->paginate($perPage);

        // Transformar items
        $items->through(function ($item) {
            return $this->transformItem($item);
        });

        // Estatisticas
        $totalCount = $modelClass::count();
        $baseStats = ['total' => $totalCount];
        $extraStats = $this->stats();

        return Inertia::render('Config/Index', [
            'items' => $items,
            'config' => [
                'title' => $this->viewTitle(),
                'description' => $this->viewDescription(),
                'columns' => $this->columns(),
                'formFields' => $this->formFields(),
                'routeName' => $this->routeName(),
            ],
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => (int) $perPage,
            ],
            'stats' => array_merge($baseStats, $extraStats),
            'additionalData' => $this->additionalData(),
        ]);
    }

    /**
     * Obter os dados do request, garantindo que campos booleanos
     * nao enviados sejam tratados como false.
     */
    protected function getRequestData(Request $request, array $rules): array
    {
        $fields = array_keys($rules);
        $data = $request->only($fields);

        // Campos com regra 'boolean' que nao vieram no request => false
        foreach ($rules as $field => $rule) {
            $ruleStr = is_array($rule) ? implode('|', $rule) : $rule;
            if (str_contains($ruleStr, 'boolean') && !$request->has($field)) {
                $data[$field] = false;
            }
        }

        return $data;
    }

    /**
     * Store - Criar novo registro
     */
    public function store(Request $request)
    {
        $rules = $this->validationRules();
        $request->validate($rules);

        $modelClass = $this->modelClass();
        $modelClass::create($this->getRequestData($request, $rules));

        return back()->with('success', $this->viewTitle() . ' criado com sucesso!');
    }

    /**
     * Update - Atualizar registro existente
     */
    public function update(Request $request, $id)
    {
        $modelClass = $this->modelClass();
        $model = $modelClass::findOrFail($id);

        $rules = $this->validationRules(true, $id);
        $request->validate($rules);

        $model->update($this->getRequestData($request, $rules));

        return back()->with('success', $this->viewTitle() . ' atualizado com sucesso!');
    }

    /**
     * Destroy - Excluir registro
     */
    public function destroy($id)
    {
        $modelClass = $this->modelClass();
        $model = $modelClass::findOrFail($id);

        $canDelete = $this->canDelete($model);

        if ($canDelete !== true) {
            return back()->with('error', $canDelete);
        }

        $model->delete();

        return back()->with('success', $this->viewTitle() . ' excluido com sucesso!');
    }
}
