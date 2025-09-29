<?php

namespace App\Http\Controllers;

use App\Models\AccessLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccessLevelController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'order');
        $sortDirection = $request->get('direction', 'asc');
        $category = $request->get('category');

        // Validar campos de ordenação permitidos
        $allowedSortFields = ['name', 'order', 'created_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'order';
        }

        // Validar direção da ordenação
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $query = AccessLevel::with('colorTheme');

        // Aplicar busca se fornecida
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Filtrar por categoria se fornecida
        if ($category && $category !== '') {
            switch ($category) {
                case 'administrative':
                    $query->administrative();
                    break;
                case 'operational':
                    $query->operational();
                    break;
                case 'financial':
                    $query->financial();
                    break;
                case 'human_resources':
                    $query->humanResources();
                    break;
                case 'commercial':
                    $query->commercial();
                    break;
                case 'management':
                    $query->management();
                    break;
            }
        }

        // Aplicar ordenação
        $query->orderBy($sortField, $sortDirection);

        $accessLevels = $query->paginate($perPage);

        return Inertia::render('AccessLevels/Index', [
            'accessLevels' => $accessLevels->through(function ($accessLevel) {
                return [
                    'id' => $accessLevel->id,
                    'name' => $accessLevel->name,
                    'order' => $accessLevel->order,
                    'color_theme_id' => $accessLevel->color_theme_id,
                    'created_at' => $accessLevel->created_at,
                    'updated_at' => $accessLevel->updated_at,
                    'color' => $accessLevel->color,
                    'color_class' => $accessLevel->color_class,
                    'is_administrative' => $accessLevel->is_administrative,
                    'is_operational' => $accessLevel->is_operational,
                    'is_financial' => $accessLevel->is_financial,
                    'is_human_resources' => $accessLevel->is_human_resources,
                    'is_commercial' => $accessLevel->is_commercial,
                    'is_management' => $accessLevel->is_management,
                    'is_super_admin' => $accessLevel->is_super_admin,
                    'is_level_1' => $accessLevel->is_level_1,
                    'authorized_pages_count' => $accessLevel->authorizedPages()->count(),
                    'total_pages_count' => $accessLevel->pages()->count(),
                ];
            }),
            'categories' => AccessLevel::getCategories(),
            'groupedAccessLevels' => AccessLevel::getGroupedOptions(),
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
                'category' => $category,
            ],
            'stats' => [
                'total' => AccessLevel::count(),
                'administrative' => AccessLevel::administrative()->count(),
                'operational' => AccessLevel::operational()->count(),
                'financial' => AccessLevel::financial()->count(),
                'human_resources' => AccessLevel::humanResources()->count(),
                'commercial' => AccessLevel::commercial()->count(),
                'management' => AccessLevel::management()->count(),
            ],
        ]);
    }

    public function show(AccessLevel $accessLevel)
    {
        $accessLevel->load('colorTheme', 'pages');

        return Inertia::render('AccessLevels/Show', [
            'accessLevel' => [
                'id' => $accessLevel->id,
                'name' => $accessLevel->name,
                'order' => $accessLevel->order,
                'color_theme_id' => $accessLevel->color_theme_id,
                'created_at' => $accessLevel->created_at,
                'updated_at' => $accessLevel->updated_at,
                'color' => $accessLevel->color,
                'color_class' => $accessLevel->color_class,
                'color_theme' => $accessLevel->colorTheme ? [
                    'id' => $accessLevel->colorTheme->id,
                    'name' => $accessLevel->colorTheme->name,
                    'color' => $accessLevel->colorTheme->color,
                    'bootstrap_class' => $accessLevel->colorTheme->bootstrap_class,
                ] : null,
                'is_administrative' => $accessLevel->is_administrative,
                'is_operational' => $accessLevel->is_operational,
                'is_financial' => $accessLevel->is_financial,
                'is_human_resources' => $accessLevel->is_human_resources,
                'is_commercial' => $accessLevel->is_commercial,
                'is_management' => $accessLevel->is_management,
                'is_super_admin' => $accessLevel->is_super_admin,
                'is_level_1' => $accessLevel->is_level_1,
                'authorized_pages' => $accessLevel->authorizedPages->map(function ($page) {
                    return [
                        'id' => $page->id,
                        'page_name' => $page->page_name,
                        'controller' => $page->controller,
                        'method' => $page->method,
                        'route' => $page->route,
                        'permission' => $page->pivot->permission,
                        'order' => $page->pivot->order,
                        'dropdown' => $page->pivot->dropdown,
                        'lib_menu' => $page->pivot->lib_menu,
                        'menu_id' => $page->pivot->menu_id,
                    ];
                }),
                'total_pages' => $accessLevel->pages->map(function ($page) {
                    return [
                        'id' => $page->id,
                        'page_name' => $page->page_name,
                        'controller' => $page->controller,
                        'method' => $page->method,
                        'route' => $page->route,
                        'permission' => $page->pivot->permission,
                        'order' => $page->pivot->order,
                        'dropdown' => $page->pivot->dropdown,
                        'lib_menu' => $page->pivot->lib_menu,
                        'menu_id' => $page->pivot->menu_id,
                    ];
                }),
            ],
        ]);
    }
}