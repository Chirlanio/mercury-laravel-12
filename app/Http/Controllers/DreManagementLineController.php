<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\DRE\ReorderDreManagementLinesRequest;
use App\Http\Requests\DRE\StoreDreManagementLineRequest;
use App\Http\Requests\DRE\UpdateDreManagementLineRequest;
use App\Models\DreManagementLine;
use App\Services\DRE\DreManagementLineService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD das linhas da DRE gerencial.
 *
 * Autorização em duas camadas:
 *   - Rota protegida por middleware `permission:dre.manage_structure` (create/update/delete/reorder).
 *   - Policy DreManagementLinePolicy aplicada via `$this->authorize()` — redundância
 *     intencional para suportar testes diretos do controller sem rota (padrão do projeto).
 */
class DreManagementLineController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly DreManagementLineService $service)
    {
    }

    /**
     * GET /dre/management-lines
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DreManagementLine::class);

        $lines = $this->service->list();

        return Inertia::render('DRE/ManagementLines/Index', [
            'lines' => $lines->map(fn (DreManagementLine $l) => $this->serialize($l))->values(),
            'can' => [
                'manage' => $request->user()?->hasPermissionTo(Permission::MANAGE_DRE_STRUCTURE->value) ?? false,
            ],
            'natureOptions' => [
                ['value' => DreManagementLine::NATURE_REVENUE, 'label' => 'Receita'],
                ['value' => DreManagementLine::NATURE_EXPENSE, 'label' => 'Despesa'],
                ['value' => DreManagementLine::NATURE_SUBTOTAL, 'label' => 'Subtotal'],
            ],
        ]);
    }

    public function store(StoreDreManagementLineRequest $request): RedirectResponse
    {
        $this->authorize('create', DreManagementLine::class);

        $data = $request->validated();
        $data['created_by_user_id'] = $request->user()?->id;

        $this->service->create($data);

        return redirect()->route('dre.management-lines.index')
            ->with('flash.success', 'Linha gerencial criada.');
    }

    public function update(UpdateDreManagementLineRequest $request, DreManagementLine $managementLine): RedirectResponse
    {
        $this->authorize('update', $managementLine);

        $data = $request->validated();
        $data['updated_by_user_id'] = $request->user()?->id;

        $this->service->update($managementLine, $data);

        return redirect()->route('dre.management-lines.index')
            ->with('flash.success', 'Linha gerencial atualizada.');
    }

    public function destroy(Request $request, DreManagementLine $managementLine): RedirectResponse
    {
        $this->authorize('delete', $managementLine);

        $this->service->delete(
            $managementLine,
            deletedByUserId: $request->user()?->id,
            reason: $request->input('reason')
        );

        return redirect()->route('dre.management-lines.index')
            ->with('flash.success', 'Linha gerencial excluída.');
    }

    /**
     * POST /dre/management-lines/reorder
     */
    public function reorder(ReorderDreManagementLinesRequest $request): RedirectResponse
    {
        $this->authorize('reorder', DreManagementLine::class);

        $this->service->reorder($request->input('ids'));

        return redirect()->route('dre.management-lines.index')
            ->with('flash.success', 'Ordem atualizada.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function serialize(DreManagementLine $line): array
    {
        return [
            'id' => $line->id,
            'code' => $line->code,
            'sort_order' => $line->sort_order,
            'is_subtotal' => (bool) $line->is_subtotal,
            'accumulate_until_sort_order' => $line->accumulate_until_sort_order,
            'level_1' => $line->level_1,
            'level_2' => $line->level_2,
            'level_3' => $line->level_3,
            'level_4' => $line->level_4,
            'nature' => $line->nature,
            'is_active' => (bool) $line->is_active,
            'notes' => $line->notes,
        ];
    }

    private function natureOptions(): array
    {
        return [
            ['value' => DreManagementLine::NATURE_REVENUE, 'label' => 'Receita'],
            ['value' => DreManagementLine::NATURE_EXPENSE, 'label' => 'Despesa'],
            ['value' => DreManagementLine::NATURE_SUBTOTAL, 'label' => 'Subtotal'],
        ];
    }
}
