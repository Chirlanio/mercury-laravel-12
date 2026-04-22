<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\DRE\BulkAssignDreMappingRequest;
use App\Http\Requests\DRE\StoreDreMappingRequest;
use App\Http\Requests\DRE\UpdateDreMappingRequest;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreManagementLine;
use App\Models\DreMapping;
use App\Services\DRE\DreMappingListFilter;
use App\Services\DRE\DreMappingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD do de-para conta contábil → linha gerencial da DRE.
 *
 * A tela principal lista CONTAS (não mappings) com o mapping vigente
 * embutido. Operações de escrita (store/update/destroy/bulk) mexem
 * em `dre_mappings` diretamente.
 */
class DreMappingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly DreMappingService $service)
    {
    }

    /**
     * GET /dre/mappings
     * Tela central — lista contas analíticas com mapping vigente.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DreMapping::class);

        $filter = DreMappingListFilter::fromRequest($request);
        $paginator = $this->service->list($filter);

        return Inertia::render('DRE/Mappings/Index', [
            'accounts' => [
                'data' => $paginator->getCollection()->map(fn ($account) => $this->serializeAccountRow($account))->values(),
                'links' => $paginator->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => [
                'search' => $filter->search,
                'account_group' => $filter->accountGroup,
                'cost_center_id' => $filter->costCenterId,
                'management_line_id' => $filter->managementLineId,
                'only_unmapped' => $filter->onlyUnmapped,
                'effective_on' => $filter->effectiveOn,
                'per_page' => $filter->perPage,
            ],
            'managementLines' => $this->managementLineOptions(),
            'costCenters' => $this->costCenterOptions(),
            'accountGroups' => [
                ['value' => 3, 'label' => 'Receitas'],
                ['value' => 4, 'label' => 'Custos e Despesas'],
                ['value' => 5, 'label' => 'Resultado'],
            ],
            'can' => [
                'manage' => $request->user()?->hasPermissionTo(Permission::MANAGE_DRE_MAPPINGS->value) ?? false,
            ],
        ]);
    }

    public function store(StoreDreMappingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by_user_id'] = $request->user()?->id;

        $this->service->create($data);

        return back()->with('flash.success', 'Mapeamento criado.');
    }

    public function update(UpdateDreMappingRequest $request, DreMapping $mapping): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by_user_id'] = $request->user()?->id;

        $this->service->update($mapping, $data);

        return back()->with('flash.success', 'Mapeamento atualizado.');
    }

    public function destroy(Request $request, DreMapping $mapping): RedirectResponse
    {
        $this->authorize('delete', $mapping);

        $this->service->delete(
            $mapping,
            deletedByUserId: $request->user()?->id,
            reason: $request->input('reason')
        );

        return back()->with('flash.success', 'Mapeamento removido.');
    }

    /**
     * POST /dre/mappings/bulk
     */
    public function bulk(BulkAssignDreMappingRequest $request): RedirectResponse
    {
        $this->authorize('bulkAssign', DreMapping::class);

        $result = $this->service->bulkAssignDetailed(
            accountIds: $request->input('account_ids'),
            costCenterId: $request->input('cost_center_id'),
            managementLineId: (int) $request->input('dre_management_line_id'),
            effectiveFrom: $request->input('effective_from'),
            createdByUserId: $request->user()?->id,
        );

        $msg = sprintf(
            '%d mapeamento(s) criado(s). %d pulado(s).',
            $result['created'],
            $result['skipped']
        );

        return back()->with([
            'flash.success' => $msg,
            'bulk_errors' => $result['errors'],
        ]);
    }

    /**
     * GET /dre/mappings/unmapped
     * Vista dedicada das contas 3/4/5 sem mapping vigente.
     */
    public function unmapped(Request $request): Response
    {
        $this->authorize('viewAny', DreMapping::class);

        $accounts = $this->service->findUnmappedAccounts(
            effectiveOn: $request->input('effective_on')
        );

        return Inertia::render('DRE/Mappings/Unmapped', [
            'accounts' => $accounts->map(fn ($account) => [
                'id' => $account->id,
                'code' => $account->code,
                'reduced_code' => $account->reduced_code,
                'name' => $account->name,
                'account_group' => $account->account_group?->value,
                'account_group_label' => $account->account_group?->label(),
                'classification_level' => (int) $account->classification_level,
                'default_management_class' => $account->defaultManagementClass
                    ? [
                        'id' => $account->defaultManagementClass->id,
                        'code' => $account->defaultManagementClass->code,
                        'name' => $account->defaultManagementClass->name,
                    ]
                    : null,
            ])->values(),
            'managementLines' => $this->managementLineOptions(),
            'costCenters' => $this->costCenterOptions(),
            'effective_on' => $request->input('effective_on') ?? now()->toDateString(),
            'can' => [
                'manage' => $request->user()?->hasPermissionTo(Permission::MANAGE_DRE_MAPPINGS->value) ?? false,
            ],
        ]);
    }

    /**
     * GET /dre/mappings/search-accounts?q=...
     * Endpoint AJAX do autocomplete da tela de mapping.
     */
    public function searchAccounts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DreMapping::class);

        $q = trim((string) $request->input('q', ''));
        $limit = min(50, max(5, (int) $request->input('limit', 20)));

        $query = ChartOfAccount::query()
            ->notDeleted()
            ->analytical()
            ->whereIn('account_group', [3, 4, 5])
            ->orderBy('code')
            ->limit($limit);

        if ($q !== '') {
            $query->search($q);
        }

        $results = $query->get(['id', 'code', 'reduced_code', 'name', 'account_group']);

        return response()->json([
            'results' => $results->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'reduced_code' => $a->reduced_code,
                'name' => $a->name,
                'account_group' => $a->account_group?->value,
                'label' => sprintf('%s — %s', $a->code, $a->name),
            ])->values(),
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Serializa uma linha de conta + mapping vigente.
     * Os campos `active_mapping_*` vêm do LEFT JOIN do service.
     */
    private function serializeAccountRow(ChartOfAccount $account): array
    {
        return [
            'id' => $account->id,
            'code' => $account->code,
            'reduced_code' => $account->reduced_code,
            'name' => $account->name,
            'type' => $account->type?->value,
            'account_group' => $account->account_group?->value,
            'classification_level' => (int) $account->classification_level,
            'is_active' => (bool) $account->is_active,
            'mapping' => $account->active_mapping_id ? [
                'id' => (int) $account->active_mapping_id,
                'cost_center_id' => $account->active_mapping_cost_center_id,
                'dre_management_line_id' => (int) $account->active_mapping_line_id,
            ] : null,
        ];
    }

    private function managementLineOptions(): array
    {
        return DreManagementLine::query()
            ->notDeleted()
            ->ordered()
            ->get(['id', 'code', 'sort_order', 'is_subtotal', 'level_1', 'nature'])
            ->map(fn ($line) => [
                'id' => $line->id,
                'code' => $line->code,
                'sort_order' => $line->sort_order,
                'is_subtotal' => (bool) $line->is_subtotal,
                'label' => $line->level_1,
                'nature' => $line->nature,
            ])
            ->values()
            ->toArray();
    }

    private function costCenterOptions(): array
    {
        return CostCenter::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn ($cc) => [
                'id' => $cc->id,
                'code' => $cc->code,
                'name' => $cc->name,
                'label' => sprintf('%s — %s', $cc->code, $cc->name),
            ])
            ->values()
            ->toArray();
    }
}
