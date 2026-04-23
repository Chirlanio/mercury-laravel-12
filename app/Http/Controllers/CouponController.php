<?php

namespace App\Http\Controllers;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Enums\Permission;
use App\Http\Requests\Coupon\StoreCouponRequest;
use App\Http\Requests\Coupon\TransitionCouponRequest;
use App\Http\Requests\Coupon\UpdateCouponRequest;
use App\Models\Coupon;
use App\Models\SocialMedia;
use App\Models\Store;
use App\Models\User;
use App\Services\CouponExportService;
use App\Services\CouponImportService;
use App\Services\CouponLookupService;
use App\Services\CouponService;
use App\Services\CouponTransitionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private CouponService $service,
        private CouponLookupService $lookup,
        private CouponTransitionService $transitionService,
        private CouponExportService $exportService,
        private CouponImportService $importService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorize('viewAny', Coupon::class);

        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $query = Coupon::with(['employee:id,name,store_id', 'store:code,name', 'socialMedia:id,name,icon', 'createdBy:id,name'])
            ->notDeleted()
            ->latest();

        // Store scoping automático quando sem MANAGE_COUPONS
        if ($scopedStoreCode) {
            // Usuário store-scoped vê seus cupons da loja + os que criou (inclui influencers)
            $query->where(function ($q) use ($scopedStoreCode, $user) {
                $q->where('store_code', $scopedStoreCode)
                    ->orWhere('created_by_user_id', $user->id);
            });
        } elseif ($request->filled('store_code')) {
            $query->forStore($request->store_code);
        }

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        } elseif (! $request->boolean('include_cancelled')) {
            // Default esconde cancelled + expired (ruído). Usuário pode filtrar explicitamente.
            $query->whereNotIn('status', [
                CouponStatus::CANCELLED->value,
                CouponStatus::EXPIRED->value,
            ]);
        }

        if ($request->filled('type')) {
            $query->forType($request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('influencer_name', 'like', "%{$search}%")
                    ->orWhere('coupon_site', 'like', "%{$search}%")
                    ->orWhere('suggested_coupon', 'like', "%{$search}%")
                    ->orWhere('campaign_name', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($e) => $e->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $coupons = $query->paginate(15)
            ->withQueryString()
            ->through(fn (Coupon $c) => $this->formatCoupon($c));

        return Inertia::render('Coupons/Index', [
            'coupons' => $coupons,
            'filters' => $request->only([
                'store_code', 'status', 'type',
                'search', 'date_from', 'date_to', 'include_cancelled',
            ]),
            'statistics' => $this->buildStatistics($scopedStoreCode, $user),
            'statusOptions' => CouponStatus::labels(),
            'statusColors' => CouponStatus::colors(),
            'statusTransitions' => CouponStatus::transitionMap(),
            'typeOptions' => CouponType::labels(),
            'isStoreScoped' => $scopedStoreCode !== null,
            'scopedStoreCode' => $scopedStoreCode,
            'selects' => [
                'stores' => $scopedStoreCode
                    ? Store::where('code', $scopedStoreCode)->get(['id', 'code', 'name', 'network_id'])
                    : Store::orderBy('name')->get(['id', 'code', 'name', 'network_id']),
                'socialMedia' => SocialMedia::where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'icon', 'link_type', 'link_placeholder']),
            ],
            'can' => [
                'create' => $user->can('create', Coupon::class),
                'export' => $user->can('export', Coupon::class),
                'issue' => $user->hasPermissionTo(Permission::ISSUE_COUPON_CODE->value),
                'manage' => $user->hasPermissionTo(Permission::MANAGE_COUPONS->value),
            ],
        ]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $this->authorize('create', Coupon::class);

        $data = $request->validated();
        $autoRequest = (bool) ($data['auto_request'] ?? true);
        unset($data['auto_request']);

        // Store scoping automático: usuário sem MANAGE só cria pra própria loja
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);
        if ($scopedStoreCode
            && isset($data['store_code'])
            && $data['store_code'] !== $scopedStoreCode
            && $data['type'] !== CouponType::INFLUENCER->value) {
            abort(403, 'Você só pode criar cupons para sua própria loja.');
        }

        try {
            $this->service->create($data, $user, autoRequest: $autoRequest);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Cupom criado com sucesso.');
    }

    public function show(Coupon $coupon, Request $request): JsonResponse
    {
        $this->authorize('view', $coupon);

        $coupon->load([
            'employee:id,name,cpf,store_id',
            'store:code,name,network_id',
            'socialMedia:id,name,icon',
            'createdBy:id,name',
            'issuedBy:id,name',
            'updatedBy:id,name',
            'deletedBy:id,name',
            'statusHistory.changedBy:id,name',
        ]);

        return response()->json([
            'coupon' => $this->formatCouponDetailed($coupon),
        ]);
    }

    public function update(Coupon $coupon, UpdateCouponRequest $request): RedirectResponse
    {
        $this->authorize('update', $coupon);

        try {
            $this->service->update($coupon, $request->validated(), $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Cupom atualizado.');
    }

    public function destroy(Coupon $coupon, Request $request): RedirectResponse
    {
        $this->authorize('delete', $coupon);

        $data = $request->validate([
            'deleted_reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $this->service->softDelete($coupon, $request->user(), $data['deleted_reason']);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Cupom excluído.');
    }

    public function transition(Coupon $coupon, TransitionCouponRequest $request): RedirectResponse
    {
        $target = CouponStatus::from($request->input('to_status'));
        $note = $request->input('note');
        $couponSite = $request->input('coupon_site');

        // Autorização por transição:
        //   - cancelled: policy::cancel
        //   - issued: policy::issueCode
        //   - outros: policy::update
        $ability = match ($target) {
            CouponStatus::CANCELLED => 'cancel',
            CouponStatus::ISSUED => 'issueCode',
            default => 'update',
        };

        $this->authorize($ability, $coupon);

        try {
            $this->transitionService->transition(
                $coupon,
                $target,
                $request->user(),
                $note,
                $couponSite ? ['coupon_site' => $couponSite] : []
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    // ------------------------------------------------------------------
    // AJAX endpoints (modal de criação)
    // ------------------------------------------------------------------

    public function lookupExisting(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Coupon::class);

        $data = $request->validate([
            'cpf' => 'required|string|min:11',
            'type' => 'nullable|string',
            'store_code' => 'nullable|string|max:10',
        ]);

        $type = ! empty($data['type']) ? CouponType::tryFrom($data['type']) : null;
        $existing = $this->lookup->existingActiveForCpf(
            $data['cpf'],
            $type,
            $data['store_code'] ?? null
        );

        return response()->json([
            'existing' => $existing->map(fn (Coupon $c) => [
                'id' => $c->id,
                'type' => $c->type->value,
                'type_label' => $c->type->label(),
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'store_code' => $c->store_code,
                'store_name' => $c->store?->name,
                'beneficiary_name' => $c->beneficiary_name,
                'coupon_site' => $c->coupon_site,
                'suggested_coupon' => $c->suggested_coupon,
                'created_at' => $c->created_at?->format('Y-m-d'),
            ])->all(),
        ]);
    }

    public function lookupEmployees(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Coupon::class);

        $data = $request->validate([
            'store_code' => 'required|string|max:10',
        ]);

        return response()->json([
            'employees' => $this->lookup->employeesByStore($data['store_code']),
        ]);
    }

    public function lookupEmployeeDetails(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Coupon::class);

        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
        ]);

        $details = $this->lookup->employeeDetails($data['employee_id']);

        return response()->json([
            'employee' => $details,
        ]);
    }

    public function suggestCode(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Coupon::class);

        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'year' => 'nullable|integer',
        ]);

        return response()->json([
            'code' => $this->lookup->suggestCouponCode($data['name'], $data['year'] ?? null),
        ]);
    }

    // ------------------------------------------------------------------
    // Dashboard — gráficos recharts
    // ------------------------------------------------------------------

    public function dashboard(Request $request): \Inertia\Response
    {
        $this->authorize('viewAny', Coupon::class);

        $scopedStoreCode = $this->resolveScopedStoreCode($request->user());

        return Inertia::render('Coupons/Dashboard', [
            'analytics' => $this->buildAnalytics($scopedStoreCode, $request->user()),
            'isStoreScoped' => $scopedStoreCode !== null,
            'scopedStoreCode' => $scopedStoreCode,
        ]);
    }

    /**
     * 4 agregações usadas pelos gráficos:
     *  1. emissões por mês (últimos 12) — line
     *  2. top 10 lojas solicitantes — bar
     *  3. top 10 influencers por volume — bar
     *  4. distribuição por status — pie
     */
    protected function buildAnalytics(?string $scopedStoreCode, ?User $user): array
    {
        $base = fn () => Coupon::query()->notDeleted()
            ->when(
                $scopedStoreCode,
                fn ($q) => $q->where(function ($q2) use ($scopedStoreCode, $user) {
                    $q2->where('store_code', $scopedStoreCode)
                        ->orWhere('created_by_user_id', $user?->id);
                })
            );

        // 1. Emissões por mês (últimos 12 meses)
        // Pegamos o driver da conexão real do Model (em tenant mode é `mysql`,
        // em testes é `sqlite`). Usar config('database.default') aqui é errado:
        // ele reflete a conexão default do central, não a que o Coupon usa.
        $driver = Coupon::query()->getConnection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $byMonth = $base()
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        // Formato amigável: "abr/2026" em vez de "2026-04".
        // Evita dependência de locale do SO (LC_TIME varia em Windows/Linux).
        $mesAbrev = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

        $monthSeries = $byMonth->map(function ($r) use ($mesAbrev) {
            [$year, $month] = explode('-', (string) $r->ym);

            return [
                'month' => ($mesAbrev[(int) $month - 1] ?? $month).'/'.$year,
                'total' => (int) $r->total,
            ];
        })->values();

        // 2. Top 10 lojas solicitantes — resolve nome da loja em segunda query.
        $byStore = $base()
            ->whereNotNull('store_code')
            ->selectRaw('store_code, COUNT(*) as total')
            ->groupBy('store_code')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $storeNames = \App\Models\Store::whereIn('code', $byStore->pluck('store_code'))
            ->pluck('name', 'code');

        $byStoreData = $byStore->map(fn ($r) => [
            'store_code' => $r->store_code,
            'store_name' => $storeNames[$r->store_code] ?? $r->store_code,
            // Label combinada pra o YAxis exibir "Z436 — Arezzo Iguatemi"
            'label' => ($storeNames[$r->store_code] ?? $r->store_code),
            'total' => (int) $r->total,
        ])->values();

        // 3. Top 10 influencers
        $byInfluencer = $base()
            ->where('type', CouponType::INFLUENCER->value)
            ->whereNotNull('influencer_name')
            ->selectRaw('influencer_name, COUNT(*) as total')
            ->groupBy('influencer_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name' => $r->influencer_name,
                'total' => (int) $r->total,
            ])->values();

        // 4. Distribuição por status.
        // O cast `'status' => CouponStatus::class` no Model já converte
        // $r->status para instância de enum. Não passamos por tryFrom novamente.
        $byStatus = $base()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->map(function ($r) {
                $status = $r->status instanceof CouponStatus
                    ? $r->status
                    : CouponStatus::tryFrom((string) $r->status);

                return [
                    'status' => $status?->value ?? (string) $r->status,
                    'label' => $status?->label() ?? (string) $r->status,
                    'color' => $status?->color() ?? 'gray',
                    'total' => (int) $r->total,
                ];
            })->values();

        return [
            'by_month' => $monthSeries,
            'by_store' => $byStoreData,
            'by_influencer' => $byInfluencer,
            'by_status' => $byStatus,
        ];
    }

    // ------------------------------------------------------------------
    // Export (Excel + PDF individual)
    // ------------------------------------------------------------------

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('export', Coupon::class);

        $query = $this->buildFilteredQuery($request);

        return $this->exportService->exportExcel($query);
    }

    public function exportPdf(Coupon $coupon, Request $request): HttpResponse
    {
        $this->authorize('view', $coupon);

        if (! $request->user()->hasPermissionTo(Permission::EXPORT_COUPONS->value)) {
            abort(403, 'Você não tem permissão para exportar cupons.');
        }

        return $this->exportService->exportPdf($coupon);
    }

    // ------------------------------------------------------------------
    // Import (preview + store)
    // ------------------------------------------------------------------

    public function importPreview(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo(Permission::IMPORT_COUPONS->value)) {
            abort(403);
        }

        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        $result = $this->importService->preview($data['file']->getRealPath());

        return response()->json($result);
    }

    public function importStore(Request $request)
    {
        if (! $request->user()->hasPermissionTo(Permission::IMPORT_COUPONS->value)) {
            abort(403);
        }

        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        $result = $this->importService->import($data['file']->getRealPath(), $request->user());

        $msg = sprintf(
            'Importação concluída: %d criados, %d atualizados, %d ignorados.',
            $result['created'],
            $result['updated'],
            $result['skipped']
        );

        return redirect()->back()->with('success', $msg)->with('import_result', $result);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Aplica os mesmos filtros da listagem numa query crua — usado pelo export
     * pra garantir paridade entre "o que vejo na tela" e "o que exporto".
     */
    protected function buildFilteredQuery(Request $request)
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $query = Coupon::query()
            ->with(['employee', 'store', 'socialMedia', 'createdBy', 'issuedBy'])
            ->notDeleted();

        if ($scopedStoreCode) {
            $query->where(function ($q) use ($scopedStoreCode, $user) {
                $q->where('store_code', $scopedStoreCode)
                    ->orWhere('created_by_user_id', $user->id);
            });
        } elseif ($request->filled('store_code')) {
            $query->forStore($request->store_code);
        }

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        } elseif (! $request->boolean('include_cancelled')) {
            $query->whereNotIn('status', [
                \App\Enums\CouponStatus::CANCELLED->value,
                \App\Enums\CouponStatus::EXPIRED->value,
            ]);
        }

        if ($request->filled('type')) {
            $query->forType($request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('influencer_name', 'like', "%{$search}%")
                    ->orWhere('coupon_site', 'like', "%{$search}%")
                    ->orWhere('suggested_coupon', 'like', "%{$search}%")
                    ->orWhere('campaign_name', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($e) => $e->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query->latest();
    }

    protected function resolveScopedStoreCode(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->hasPermissionTo(Permission::MANAGE_COUPONS->value)) {
            return null;
        }

        return $user->store_id ?: null;
    }

    protected function buildStatistics(?string $scopedStoreCode, ?User $user): array
    {
        $base = Coupon::notDeleted();

        if ($scopedStoreCode) {
            $base->where(function ($q) use ($scopedStoreCode, $user) {
                $q->where('store_code', $scopedStoreCode)
                    ->orWhere('created_by_user_id', $user?->id);
            });
        }

        $total = (clone $base)->count();

        $byStatus = (clone $base)
            ->selectRaw('status as s, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 's');

        $byType = (clone $base)
            ->selectRaw('type as t, COUNT(*) as c')
            ->groupBy('type')
            ->pluck('c', 't');

        return [
            'total' => $total,
            'draft' => (int) ($byStatus[CouponStatus::DRAFT->value] ?? 0),
            'requested' => (int) ($byStatus[CouponStatus::REQUESTED->value] ?? 0),
            'issued' => (int) ($byStatus[CouponStatus::ISSUED->value] ?? 0),
            'active' => (int) ($byStatus[CouponStatus::ACTIVE->value] ?? 0),
            'expired' => (int) ($byStatus[CouponStatus::EXPIRED->value] ?? 0),
            'cancelled' => (int) ($byStatus[CouponStatus::CANCELLED->value] ?? 0),
            'consultor' => (int) ($byType[CouponType::CONSULTOR->value] ?? 0),
            'influencer' => (int) ($byType[CouponType::INFLUENCER->value] ?? 0),
            'ms_indica' => (int) ($byType[CouponType::MS_INDICA->value] ?? 0),
        ];
    }

    protected function formatCoupon(Coupon $c): array
    {
        return [
            'id' => $c->id,
            'type' => $c->type->value,
            'type_label' => $c->type->label(),
            'type_color' => $c->type->color(),
            'status' => $c->status->value,
            'status_label' => $c->status->label(),
            'status_color' => $c->status->color(),
            'beneficiary_name' => $c->beneficiary_name,
            'masked_cpf' => $c->masked_cpf,
            'store_code' => $c->store_code,
            'store_name' => $c->store?->name,
            'employee_id' => $c->employee_id,
            'employee_name' => $c->employee?->name,
            'social_media_id' => $c->social_media_id,
            'social_media_name' => $c->socialMedia?->name,
            'social_media_link' => $c->social_media_link,
            'city' => $c->city,
            'influencer_name' => $c->influencer_name,
            'suggested_coupon' => $c->suggested_coupon,
            'coupon_site' => $c->coupon_site,
            'campaign_name' => $c->campaign_name,
            'valid_from' => $c->valid_from?->format('Y-m-d'),
            'valid_until' => $c->valid_until?->format('Y-m-d'),
            'usage_count' => (int) $c->usage_count,
            'max_uses' => $c->max_uses,
            'created_at' => $c->created_at?->format('Y-m-d H:i'),
            'requested_at' => $c->requested_at?->format('Y-m-d H:i'),
            'issued_at' => $c->issued_at?->format('Y-m-d H:i'),
            'created_by_name' => $c->createdBy?->name,
            'is_deleted' => $c->is_deleted,
        ];
    }

    protected function formatCouponDetailed(Coupon $c): array
    {
        $base = $this->formatCoupon($c);

        return array_merge($base, [
            // Detalhes adicionais visíveis só no modal
            'cpf' => $c->cpf,
            'notes' => $c->notes,
            'cancelled_at' => $c->cancelled_at?->format('Y-m-d H:i'),
            'cancelled_reason' => $c->cancelled_reason,
            'expired_at' => $c->expired_at?->format('Y-m-d H:i'),
            'activated_at' => $c->activated_at?->format('Y-m-d H:i'),
            'deleted_at' => $c->deleted_at?->format('Y-m-d H:i'),
            'deleted_reason' => $c->deleted_reason,
            'issued_by_name' => $c->issuedBy?->name,
            'updated_by_name' => $c->updatedBy?->name,
            'deleted_by_name' => $c->deletedBy?->name,
            'history' => $c->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status?->value,
                'from_status_label' => $h->from_status?->label(),
                'to_status' => $h->to_status->value,
                'to_status_label' => $h->to_status->label(),
                'to_status_color' => $h->to_status->color(),
                'note' => $h->note,
                'changed_by_name' => $h->changedBy?->name,
                'created_at' => $h->created_at?->format('Y-m-d H:i'),
            ])->all(),
        ]);
    }
}
