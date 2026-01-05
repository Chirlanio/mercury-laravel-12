<?php

namespace App\Http\Controllers;

use App\Models\PageGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PageGroupController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');

        // Validar campos de ordenacao permitidos
        $allowedSortFields = ['name', 'pages_count', 'created_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'name';
        }

        // Validar direcao da ordenacao
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $query = PageGroup::withCount('pages');

        // Aplicar busca se fornecida
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Aplicar ordenacao
        $query->orderBy($sortField, $sortDirection);

        $pageGroups = $query->paginate($perPage);

        // Estatisticas
        $stats = [
            'total' => PageGroup::count(),
            'with_pages' => PageGroup::whereHas('pages')->count(),
            'empty' => PageGroup::whereDoesntHave('pages')->count(),
        ];

        return Inertia::render('PageGroup/Index', [
            'pageGroups' => $pageGroups->through(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'pages_count' => $group->pages_count,
                    'created_at' => $group->created_at,
                    'updated_at' => $group->updated_at,
                    'can_delete' => $group->pages_count === 0,
                ];
            }),
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(Request $request, PageGroup $pageGroup)
    {
        $pageGroup->loadCount('pages');

        $data = [
            'id' => $pageGroup->id,
            'name' => $pageGroup->name,
            'pages_count' => $pageGroup->pages_count,
            'created_at' => $pageGroup->created_at,
            'updated_at' => $pageGroup->updated_at,
            'can_delete' => $pageGroup->pages_count === 0,
            'pages' => $pageGroup->pages()->select(['id', 'page_name', 'route', 'is_active'])->get(),
        ];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($data);
        }

        return Inertia::render('PageGroup/Show', [
            'pageGroup' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:page_groups,name',
        ], [
            'name.required' => 'O nome do grupo e obrigatorio.',
            'name.max' => 'O nome do grupo deve ter no maximo 50 caracteres.',
            'name.unique' => 'Ja existe um grupo com este nome.',
        ]);

        PageGroup::create($validated);

        return redirect()->route('page-groups.index')->with('success', 'Grupo de paginas criado com sucesso!');
    }

    public function update(Request $request, PageGroup $pageGroup)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:page_groups,name,' . $pageGroup->id,
        ], [
            'name.required' => 'O nome do grupo e obrigatorio.',
            'name.max' => 'O nome do grupo deve ter no maximo 50 caracteres.',
            'name.unique' => 'Ja existe um grupo com este nome.',
        ]);

        $pageGroup->update($validated);

        return redirect()->route('page-groups.index')->with('success', 'Grupo de paginas atualizado com sucesso!');
    }

    public function destroy(PageGroup $pageGroup)
    {
        // Verificar se o grupo tem paginas vinculadas
        if ($pageGroup->pages()->count() > 0) {
            return back()->withErrors(['delete' => 'Nao e possivel excluir um grupo que possui paginas vinculadas.']);
        }

        $pageGroup->delete();

        return redirect()->route('page-groups.index')->with('success', 'Grupo de paginas excluido com sucesso!');
    }
}
