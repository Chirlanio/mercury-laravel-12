<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManagementClass\StoreManagementClassRequest;
use App\Http\Requests\ManagementClass\UpdateManagementClassRequest;
use App\Models\AccountingClass;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Services\ManagementClassExportService;
use App\Services\ManagementClassImportService;
use App\Services\ManagementClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManagementClassController extends Controller
{
    public function __construct(
        private ManagementClassService $service,
        private ManagementClassExportService $exportService,
        private ManagementClassImportService $importService,
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

    /**
     * Lista os 11 departamentos gerenciais (sintéticos 8.1.DD) com as classes
     * analíticas filhas (8.1.DD.UU) e o CC pré-vinculado de cada uma.
     *
     * Consumido pelo form de OrderPayment para a cascata Área → Gerencial → CC.
     * O frontend escolhe uma área (departamento), depois escolhe uma gerencial
     * daquela área, e o CC vem automático da gerencial.
     *
     * Retorno:
     *   departments: [
     *     { id, code, name, classes: [
     *       { id, code, name, cost_center: { id, code, name } | null }
     *     ]}
     *   ]
     */
    public function departments(): JsonResponse
    {
        // Departamentos = sintéticos nível 3 (8.1.DD). Ignora 8 e 8.1 que são
        // raiz/agregador — só os 11 departamentos de negócio.
        $departments = ManagementClass::query()
            ->notDeleted()
            ->where('accepts_entries', false)
            ->where('code', 'like', '8.1._%')
            ->whereRaw('LENGTH(code) = ?', [6]) // "8.1.XX" — LENGTH é portável MySQL+SQLite
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $analyticals = ManagementClass::query()
            ->with(['costCenter:id,code,name'])
            ->notDeleted()
            ->where('accepts_entries', true)
            ->whereIn('parent_id', $departments->pluck('id'))
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'parent_id', 'cost_center_id']);

        $byParent = $analyticals->groupBy('parent_id');

        $payload = $departments->map(fn ($d) => [
            'id' => $d->id,
            'code' => $d->code,
            'name' => $d->name,
            'classes' => ($byParent[$d->id] ?? collect())->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'cost_center' => $c->costCenter
                    ? ['id' => $c->costCenter->id, 'code' => $c->costCenter->code, 'name' => $c->costCenter->name]
                    : null,
            ])->values(),
        ]);

        return response()->json(['departments' => $payload]);
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

    public function export(Request $request): BinaryFileResponse
    {
        $query = ManagementClass::query()->notDeleted();

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('accounting_link')) {
            if ($request->accounting_link === 'linked') {
                $query->linkedToAccounting();
            } elseif ($request->accounting_link === 'unlinked') {
                $query->unlinkedFromAccounting();
            }
        }

        return $this->exportService->exportExcel($query);
    }

    public function importPreview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:xlsx,xls,csv',
        ]);

        try {
            $result = $this->importService->preview($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erro ao processar a planilha: '.$e->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:xlsx,xls,csv',
        ]);

        try {
            $result = $this->importService->import(
                $request->file('file')->getRealPath(),
                $request->user()
            );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'file' => 'Erro ao importar: '.$e->getMessage(),
            ]);
        }

        $msg = sprintf(
            '%d criadas · %d atualizadas · %d ignoradas',
            $result['created'],
            $result['updated'],
            $result['skipped']
        );

        return redirect()
            ->route('management-classes.index')
            ->with('success', "Importação concluída: {$msg}")
            ->with('import_result', $result);
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
