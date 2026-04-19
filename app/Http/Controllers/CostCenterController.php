<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\CostCenter\StoreCostCenterRequest;
use App\Http\Requests\CostCenter\UpdateCostCenterRequest;
use App\Models\CostCenter;
use App\Models\Manager;
use App\Services\CostCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CostCenterController extends Controller
{
    public function __construct(
        private CostCenterService $service,
    ) {}

    public function index(Request $request): Response
    {
        $query = CostCenter::query()
            ->with(['manager', 'parent', 'createdBy', 'updatedBy'])
            ->notDeleted();

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        if ($request->filled('parent_id')) {
            if ($request->parent_id === 'root') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->integer('parent_id'));
            }
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sort = $request->string('sort', 'code')->toString();
        $direction = $request->string('direction', 'asc')->toString() === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['code', 'name', 'is_active', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'code';
        }
        $query->orderBy($sort, $direction);

        $costCenters = $query->paginate(15)
            ->withQueryString()
            ->through(fn ($c) => $this->formatCostCenter($c));

        return Inertia::render('CostCenters/Index', [
            'costCenters' => $costCenters,
            'filters' => $request->only(['search', 'parent_id', 'is_active', 'sort', 'direction']),
            'statistics' => $this->buildStatistics(),
            'selects' => [
                'managers' => Manager::orderBy('name')->get(['id', 'name']),
                'parents' => CostCenter::query()
                    ->notDeleted()
                    ->orderBy('code')
                    ->get(['id', 'code', 'name', 'parent_id']),
            ],
        ]);
    }

    public function statistics(): JsonResponse
    {
        return response()->json($this->buildStatistics());
    }

    public function show(CostCenter $costCenter): JsonResponse
    {
        if ($costCenter->isDeleted()) {
            return response()->json(['message' => 'Centro de custo excluído.'], 404);
        }

        $costCenter->load(['manager', 'parent', 'children' => function ($q) {
            $q->whereNull('deleted_at')->orderBy('code');
        }, 'createdBy', 'updatedBy']);

        return response()->json([
            'costCenter' => $this->formatCostCenter($costCenter, full: true),
        ]);
    }

    public function store(StoreCostCenterRequest $request): RedirectResponse
    {
        try {
            $this->service->create($request->validated(), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('cost-centers.index')
            ->with('success', 'Centro de custo cadastrado com sucesso.');
    }

    public function update(UpdateCostCenterRequest $request, CostCenter $costCenter): RedirectResponse
    {
        try {
            $this->service->update($costCenter, $request->validated(), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('cost-centers.index')
            ->with('success', 'Centro de custo atualizado com sucesso.');
    }

    public function destroy(Request $request, CostCenter $costCenter): RedirectResponse
    {
        $reason = (string) $request->input('deleted_reason', '');

        try {
            $this->service->delete($costCenter, $reason, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('cost-centers.index')
            ->with('success', 'Centro de custo excluído.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function buildStatistics(): array
    {
        $base = CostCenter::query()->notDeleted();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
            'with_parent' => (clone $base)->whereNotNull('parent_id')->count(),
            'roots' => (clone $base)->whereNull('parent_id')->count(),
        ];
    }

    protected function formatCostCenter(CostCenter $c, bool $full = false): array
    {
        $payload = [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'description' => $c->description,
            'area_id' => $c->area_id,
            'parent_id' => $c->parent_id,
            'parent_label' => $c->parent
                ? $c->parent->code.' · '.$c->parent->name
                : null,
            'default_accounting_class_id' => $c->default_accounting_class_id,
            'manager_id' => $c->manager_id,
            'manager_name' => $c->manager?->name,
            'is_active' => (bool) $c->is_active,
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];

        if ($full) {
            $payload['created_by'] = $c->createdBy?->name;
            $payload['updated_by'] = $c->updatedBy?->name;
            $payload['children'] = $c->children?->map(fn ($child) => [
                'id' => $child->id,
                'code' => $child->code,
                'name' => $child->name,
                'is_active' => (bool) $child->is_active,
            ])->values()->all() ?? [];
        }

        return $payload;
    }
}
