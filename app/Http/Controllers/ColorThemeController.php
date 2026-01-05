<?php

namespace App\Http\Controllers;

use App\Models\ColorTheme;
use App\Models\AccessLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ColorThemeController extends Controller
{
    /**
     * Paleta de cores predefinidas
     */
    private function getColorPalette(): array
    {
        return [
            // Cores Bootstrap
            'bootstrap' => [
                'label' => 'Bootstrap',
                'colors' => [
                    ['name' => 'Primary', 'class' => 'primary', 'hex' => '#3B82F6'],
                    ['name' => 'Secondary', 'class' => 'secondary', 'hex' => '#6B7280'],
                    ['name' => 'Success', 'class' => 'success', 'hex' => '#22C55E'],
                    ['name' => 'Danger', 'class' => 'danger', 'hex' => '#EF4444'],
                    ['name' => 'Warning', 'class' => 'warning', 'hex' => '#F59E0B'],
                    ['name' => 'Info', 'class' => 'info', 'hex' => '#06B6D4'],
                    ['name' => 'Light', 'class' => 'light', 'hex' => '#F3F4F6'],
                    ['name' => 'Dark', 'class' => 'dark', 'hex' => '#1F2937'],
                ],
            ],
            // Tons de Azul
            'blues' => [
                'label' => 'Azuis',
                'colors' => [
                    ['name' => 'Azul Claro', 'class' => 'blue-300', 'hex' => '#93C5FD'],
                    ['name' => 'Azul', 'class' => 'blue-500', 'hex' => '#3B82F6'],
                    ['name' => 'Azul Escuro', 'class' => 'blue-700', 'hex' => '#1D4ED8'],
                    ['name' => 'Azul Marinho', 'class' => 'blue-900', 'hex' => '#1E3A8A'],
                    ['name' => 'Indigo', 'class' => 'indigo-500', 'hex' => '#6366F1'],
                    ['name' => 'Ciano', 'class' => 'cyan-500', 'hex' => '#06B6D4'],
                    ['name' => 'Teal', 'class' => 'teal-500', 'hex' => '#14B8A6'],
                    ['name' => 'Sky', 'class' => 'sky-500', 'hex' => '#0EA5E9'],
                ],
            ],
            // Tons de Verde
            'greens' => [
                'label' => 'Verdes',
                'colors' => [
                    ['name' => 'Verde Claro', 'class' => 'green-300', 'hex' => '#86EFAC'],
                    ['name' => 'Verde', 'class' => 'green-500', 'hex' => '#22C55E'],
                    ['name' => 'Verde Escuro', 'class' => 'green-700', 'hex' => '#15803D'],
                    ['name' => 'Esmeralda', 'class' => 'emerald-500', 'hex' => '#10B981'],
                    ['name' => 'Lima', 'class' => 'lime-500', 'hex' => '#84CC16'],
                ],
            ],
            // Tons de Vermelho/Rosa
            'reds' => [
                'label' => 'Vermelhos',
                'colors' => [
                    ['name' => 'Vermelho Claro', 'class' => 'red-300', 'hex' => '#FCA5A5'],
                    ['name' => 'Vermelho', 'class' => 'red-500', 'hex' => '#EF4444'],
                    ['name' => 'Vermelho Escuro', 'class' => 'red-700', 'hex' => '#B91C1C'],
                    ['name' => 'Rosa', 'class' => 'pink-500', 'hex' => '#EC4899'],
                    ['name' => 'Fuchsia', 'class' => 'fuchsia-500', 'hex' => '#D946EF'],
                    ['name' => 'Rose', 'class' => 'rose-500', 'hex' => '#F43F5E'],
                ],
            ],
            // Tons de Amarelo/Laranja
            'yellows' => [
                'label' => 'Amarelos e Laranjas',
                'colors' => [
                    ['name' => 'Amarelo', 'class' => 'yellow-400', 'hex' => '#FACC15'],
                    ['name' => 'Amber', 'class' => 'amber-500', 'hex' => '#F59E0B'],
                    ['name' => 'Laranja', 'class' => 'orange-500', 'hex' => '#F97316'],
                    ['name' => 'Laranja Escuro', 'class' => 'orange-700', 'hex' => '#C2410C'],
                ],
            ],
            // Tons de Roxo
            'purples' => [
                'label' => 'Roxos',
                'colors' => [
                    ['name' => 'Violeta Claro', 'class' => 'violet-300', 'hex' => '#C4B5FD'],
                    ['name' => 'Violeta', 'class' => 'violet-500', 'hex' => '#8B5CF6'],
                    ['name' => 'Roxo', 'class' => 'purple-500', 'hex' => '#A855F7'],
                    ['name' => 'Roxo Escuro', 'class' => 'purple-700', 'hex' => '#7E22CE'],
                ],
            ],
            // Neutros
            'neutrals' => [
                'label' => 'Neutros',
                'colors' => [
                    ['name' => 'Cinza Claro', 'class' => 'gray-300', 'hex' => '#D1D5DB'],
                    ['name' => 'Cinza', 'class' => 'gray-500', 'hex' => '#6B7280'],
                    ['name' => 'Cinza Escuro', 'class' => 'gray-700', 'hex' => '#374151'],
                    ['name' => 'Slate', 'class' => 'slate-500', 'hex' => '#64748B'],
                    ['name' => 'Zinc', 'class' => 'zinc-500', 'hex' => '#71717A'],
                    ['name' => 'Stone', 'class' => 'stone-500', 'hex' => '#78716C'],
                ],
            ],
        ];
    }

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');

        // Validar campos de ordenacao permitidos
        $allowedSortFields = ['name', 'color_class', 'created_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'name';
        }

        // Validar direcao da ordenacao
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $query = ColorTheme::query();

        // Aplicar busca se fornecida
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('color_class', 'like', "%{$search}%")
                    ->orWhere('hex_color', 'like', "%{$search}%");
            });
        }

        // Aplicar ordenacao
        $query->orderBy($sortField, $sortDirection);

        $colorThemes = $query->paginate($perPage);

        // Contar quantos niveis de acesso usam cada cor
        $usageCount = AccessLevel::selectRaw('color_theme_id, count(*) as count')
            ->whereNotNull('color_theme_id')
            ->groupBy('color_theme_id')
            ->pluck('count', 'color_theme_id')
            ->toArray();

        return Inertia::render('ColorThemes/Index', [
            'colorThemes' => $colorThemes->through(function ($theme) use ($usageCount) {
                return [
                    'id' => $theme->id,
                    'name' => $theme->name,
                    'color_class' => $theme->color_class,
                    'hex_color' => $theme->hex_color,
                    'created_at' => $theme->created_at,
                    'updated_at' => $theme->updated_at,
                    'usage_count' => $usageCount[$theme->id] ?? 0,
                ];
            }),
            'colorPalette' => $this->getColorPalette(),
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
            ],
            'stats' => [
                'total' => ColorTheme::count(),
                'in_use' => AccessLevel::whereNotNull('color_theme_id')->distinct('color_theme_id')->count('color_theme_id'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:40|unique:color_themes,name',
            'color_class' => 'nullable|string|max:40',
            'hex_color' => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        // Se nao tiver color_class, gerar um baseado no nome
        $colorClass = $request->color_class ?: strtolower(str_replace(' ', '-', $request->name));

        ColorTheme::create([
            'name' => $request->name,
            'color_class' => $colorClass,
            'hex_color' => $request->hex_color,
        ]);

        return back()->with('success', 'Tema de cor criado com sucesso!');
    }

    public function update(Request $request, ColorTheme $colorTheme)
    {
        $request->validate([
            'name' => 'required|string|max:40|unique:color_themes,name,' . $colorTheme->id,
            'color_class' => 'nullable|string|max:40',
            'hex_color' => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        // Se nao tiver color_class, gerar um baseado no nome
        $colorClass = $request->color_class ?: strtolower(str_replace(' ', '-', $request->name));

        $colorTheme->update([
            'name' => $request->name,
            'color_class' => $colorClass,
            'hex_color' => $request->hex_color,
        ]);

        return back()->with('success', 'Tema de cor atualizado com sucesso!');
    }

    public function destroy(ColorTheme $colorTheme)
    {
        // Verificar se a cor esta sendo usada
        $usageCount = AccessLevel::where('color_theme_id', $colorTheme->id)->count();

        if ($usageCount > 0) {
            return back()->with('error', "Esta cor esta sendo usada por {$usageCount} nivel(is) de acesso e nao pode ser excluida.");
        }

        $colorTheme->delete();

        return back()->with('success', 'Tema de cor excluido com sucesso!');
    }
}
