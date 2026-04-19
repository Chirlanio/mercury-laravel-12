<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManagementClass\StoreManagementClassRequest;
use App\Http\Requests\ManagementClass\UpdateManagementClassRequest;
use App\Models\AccountingClass;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Services\ManagementClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ManagementClassController extends Controller
{
    public function __construct(
        private ManagementClassService $service,
    ) {}

    public function index(Request $request): Response
    {
        $query = ManagementClass::query()
            ->with(['parent', 'accountingClass', 'costCenter', 'createdBy', 'updatedBy'])
            ->notDeleted();

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        if ($request->filled('accepts_entries')) {
            $query->where('accepts_entries', $request->boolean('accepts_entries'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('parent_id')) {
            if ($request->parent_id === 'root') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->integer('parent_id'));
            }
        }

        if ($request->filled('accounting_link')) {
            if ($request->accounting_link === 'linked') {
                $query->linkedToAccounting();
            } elseif ($request->accounting_link === 'unlinked') {
                $query->unlinkedFromAccounting();
            }
        }

        if ($request->filled('accounting_class_id')) {
            $query->where('accounting_class_id', $request->integer('accounting_class_id'));
        }

        if ($request->filled('cost_center_id')) {
            $query->where('cost_center_id', $request->integer('cost_center_id'));
        }

        $query->orderBy('code');

        $managementClasses = $query->paginate(25)
            ->withQueryString()
            ->through(fn ($c) => $this->format($c));

        return Inertia::render('ManagementClasses/Index', [
            'managementClasses' => $managementClasses,
            'filters' => $request->only([
                'search', 'accepts_entries', 'is_active', 'parent_id',
                'accounting_link', 'accounting_class_id', 'cost_center_id',
            ]),
            'statistics' => $this->buildStatistics(),
            'selects' => [
                'parents' => ManagementClass::query()
                    ->notDeleted()
                    ->syntheticGroups()
                    ->orderBy('code')
                    ->get(['id', 'code', 'name', 'parent_id']),
                'accountingClasses' => AccountingClass::query()
                    ->notDeleted()
                    ->leaves()
                    ->orderBy('code')
                    ->get(['id', 'code', 'name']),
                'costCenters' => CostCenter::query()
                    ->notDeleted()
                    ->orderBy('code')
                    ->get(['id', 'code', 'name']),
            ],
        ]);
    }

    public function statistics(): JsonResponse
    {
        return response()->json($this->buildStatistics());
    }

    public function tree(): JsonResponse
    {
        $all = ManagementClass::query()
            ->with(['accountingClass:id,code,name', 'costCenter:id,code,name'])
            ->notDeleted()
            ->orderBy('code')
            ->get();

        return response()->json([
            'tree' => $this->buildTree($all),
        ]);
    }

    public function show(ManagementClass $managementClass): JsonResponse
    {
        if ($managementClass->isDeleted()) {
            return response()->json(['message' => 'Conta excluída.'], 404);
        }

        $managementClass->load([
            'parent',
            'accountingClass',
            'costCenter',
            'children' => fn ($q) => $q->whereNull('deleted_at')->orderBy('code'),
            'createdBy',
            'updatedBy',
        ]);

        return response()->json([
            'managementClass' => $this->format($managementClass, full: true),
        ]);
    }

    public function store(StoreManagementClassRequest $request): RedirectResponse
    {
        try {
            $this->service->create($request->validated(), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('management-classes.index')
            ->with('success', 'Conta gerencial cadastrada.');
    }

    public function update(UpdateManagementClassRequest $request, ManagementClass $managementClass): RedirectResponse
    {
        try {
            $this->service->update($managementClass, $request->validated(), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('management-classes.index')
            ->with('success', 'Conta atualizada.');
    }

    public function destroy(Request $request, ManagementClass $managementClass): RedirectResponse
    {
        $reason = (string) $request->input('deleted_reason', '');

        try {
            $this->service->delete($managementClass, $reason, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('management-classes.index')
            ->with('success', 'Conta excluída.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function buildStatistics(): array
    {
        $base = ManagementClass::query()->notDeleted();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
            'leaves' => (clone $base)->where('accepts_entries', true)->count(),
            'synthetic_groups' => (clone $base)->where('accepts_entries', false)->count(),
            'linked_to_accounting' => (clone $base)->whereNotNull('accounting_class_id')->count(),
            'unlinked_from_accounting' => (clone $base)->whereNull('accounting_class_id')->count(),
        ];
    }

    protected function format(ManagementClass $c, bool $full = false): array
    {
        $payload = [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'description' => $c->description,
            'parent_id' => $c->parent_id,
            'parent_label' => $c->parent
                ? $c->parent->code.' · '.$c->parent->name
                : null,
            'accounting_class_id' => $c->accounting_class_id,
            'accounting_class_label' => $c->accountingClass
                ? $c->accountingClass->code.' · '.$c->accountingClass->name
                : null,
            'cost_center_id' => $c->cost_center_id,
            'cost_center_label' => $c->costCenter
                ? $c->costCenter->code.' · '.$c->costCenter->name
                : null,
            'accepts_entries' => (bool) $c->accepts_entries,
            'sort_order' => (int) $c->sort_order,
            'is_active' => (bool) $c->is_active,
            'has_accounting_link' => $c->hasAccountingLink(),
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
                'accepts_entries' => (bool) $child->accepts_entries,
                'is_active' => (bool) $child->is_active,
            ])->values()->all() ?? [];
        }

        return $payload;
    }

    protected function buildTree($collection): array
    {
        $map = [];
        foreach ($collection as $item) {
            $map[$item->id] = [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'parent_id' => $item->parent_id,
                'accepts_entries' => (bool) $item->accepts_entries,
                'is_active' => (bool) $item->is_active,
                'accounting_class_label' => $item->accountingClass
                    ? $item->accountingClass->code.' · '.$item->accountingClass->name
                    : null,
                'cost_center_label' => $item->costCenter
                    ? $item->costCenter->code.' · '.$item->costCenter->name
                    : null,
                'children' => [],
            ];
        }

        $roots = [];
        foreach ($map as $id => &$node) {
            if ($node['parent_id'] && isset($map[$node['parent_id']])) {
                $map[$node['parent_id']]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }

        return $roots;
    }
}
