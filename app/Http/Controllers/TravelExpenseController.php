<?php

namespace App\Http\Controllers;

use App\Enums\AccountabilityStatus;
use App\Enums\Permission;
use App\Enums\TravelExpenseStatus;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\Store;
use App\Models\TravelExpense;
use App\Models\TravelExpenseItem;
use App\Models\TypeExpense;
use App\Models\TypeKeyPix;
use App\Models\User;
use App\Services\TravelExpenseAccountabilityService;
use App\Services\TravelExpenseExportService;
use App\Services\TravelExpenseService;
use App\Services\TravelExpenseTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TravelExpenseController extends Controller
{
    public function __construct(
        private TravelExpenseService $service,
        private TravelExpenseTransitionService $transitionService,
        private TravelExpenseAccountabilityService $accountabilityService,
        private TravelExpenseExportService $exportService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $query = $this->service->scopedQuery($user)
            ->with(['employee:id,name', 'store:id,code,name', 'bank:id,bank_name', 'pixType:id,name', 'createdBy:id,name'])
            ->latest();

        // Override scoping com filtro explícito (só para quem pode ver tudo)
        if (! $scopedStoreCode && $request->filled('store_code')) {
            $query->forStore($request->store_code);
        }

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        } elseif (! $request->boolean('include_terminal')) {
            $query->whereNotIn('status', [
                TravelExpenseStatus::REJECTED->value,
                TravelExpenseStatus::FINALIZED->value,
                TravelExpenseStatus::CANCELLED->value,
            ]);
        }

        if ($request->filled('accountability_status')) {
            $query->where('accountability_status', $request->accountability_status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('origin', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($qq) => $qq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('initial_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('end_date', '<=', $request->date_to);
        }

        $expenses = $query->paginate(15)
            ->withQueryString()
            ->through(fn ($te) => $this->formatExpense($te));

        $statistics = $this->buildStatistics($scopedStoreCode);

        return Inertia::render('TravelExpenses/Index', [
            'expenses' => $expenses,
            'filters' => $request->only([
                'store_code', 'status', 'accountability_status', 'employee_id',
                'search', 'date_from', 'date_to', 'include_terminal',
            ]),
            'statistics' => $statistics,
            'statusOptions' => TravelExpenseStatus::labels(),
            'statusColors' => TravelExpenseStatus::colors(),
            'statusTransitions' => TravelExpenseStatus::transitionMap(),
            'accountabilityStatusOptions' => AccountabilityStatus::labels(),
            'accountabilityStatusColors' => AccountabilityStatus::colors(),
            'accountabilityTransitions' => AccountabilityStatus::transitionMap(),
            'isStoreScoped' => $scopedStoreCode !== null,
            'scopedStoreCode' => $scopedStoreCode,
            'permissions' => [
                'create' => $user->hasPermissionTo(Permission::CREATE_TRAVEL_EXPENSES->value),
                'edit' => $user->hasPermissionTo(Permission::EDIT_TRAVEL_EXPENSES->value),
                'delete' => $user->hasPermissionTo(Permission::DELETE_TRAVEL_EXPENSES->value),
                'approve' => $user->hasPermissionTo(Permission::APPROVE_TRAVEL_EXPENSES->value),
                'manage' => $user->hasPermissionTo(Permission::MANAGE_TRAVEL_EXPENSES->value),
                'manageAccountability' => $user->hasPermissionTo(Permission::MANAGE_ACCOUNTABILITY->value),
                'export' => $user->hasPermissionTo(Permission::EXPORT_TRAVEL_EXPENSES->value),
            ],
            'selects' => [
                'stores' => $scopedStoreCode
                    ? Store::where('code', $scopedStoreCode)->get(['id', 'code', 'name'])
                    : Store::orderBy('name')->get(['id', 'code', 'name']),
                'employees' => Employee::orderBy('name')->get(['id', 'name']),
                'banks' => Bank::orderBy('bank_name')->get(['id', 'bank_name', 'cod_bank']),
                'pixTypes' => TypeKeyPix::active()->orderBy('sort_order')->get(['id', 'name']),
                'typeExpenses' => TypeExpense::active()->orderBy('sort_order')->get(['id', 'name', 'icon', 'color']),
            ],
            'defaultDailyRate' => TravelExpenseService::DEFAULT_DAILY_RATE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate($this->validationRules());

        // Scoping: usuário sem MANAGE não escolhe loja livre — usa a sua
        $scoped = $this->resolveScopedStoreCode($user);
        if ($scoped) {
            $data['store_code'] = $scoped;
        }

        $autoSubmit = $request->boolean('auto_submit');

        try {
            $this->service->create($data, $user, $autoSubmit);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with(
            'success',
            $autoSubmit ? 'Verba enviada para aprovação.' : 'Verba salva como rascunho.'
        );
    }

    public function show(TravelExpense $travelExpense, Request $request): JsonResponse
    {
        $this->ensureCanView($request->user(), $travelExpense);

        $travelExpense->load([
            'employee:id,name',
            'store:id,code,name',
            'bank:id,bank_name,cod_bank',
            'pixType:id,name',
            'items.typeExpense:id,name,icon,color',
            'items.createdBy:id,name',
            'statusHistory.changedBy:id,name',
            'createdBy:id,name',
            'approver:id,name',
            'updatedBy:id,name',
            'deletedBy:id,name',
        ]);

        return response()->json([
            'expense' => $this->formatExpenseDetailed($travelExpense),
        ]);
    }

    public function update(TravelExpense $travelExpense, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $travelExpense);

        $data = $request->validate($this->validationRules(isUpdate: true));

        try {
            $this->service->update($travelExpense, $data, $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Verba atualizada.');
    }

    public function destroy(TravelExpense $travelExpense, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $travelExpense);

        if (! $request->user()->hasPermissionTo(Permission::DELETE_TRAVEL_EXPENSES->value)) {
            abort(403, 'Você não tem permissão para excluir verbas.');
        }

        $data = $request->validate([
            'deleted_reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $this->service->delete($travelExpense, $request->user(), $data['deleted_reason']);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Verba excluída.');
    }

    public function transition(TravelExpense $travelExpense, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $travelExpense);

        $data = $request->validate([
            'kind' => 'required|in:expense,accountability',
            'to_status' => 'required|string',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            if ($data['kind'] === 'expense') {
                $this->transitionService->transitionExpense(
                    $travelExpense,
                    $data['to_status'],
                    $request->user(),
                    $data['note'] ?? null
                );
            } else {
                $this->transitionService->transitionAccountability(
                    $travelExpense,
                    $data['to_status'],
                    $request->user(),
                    $data['note'] ?? null
                );
            }
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    // ==================================================================
    // Itens da prestação de contas
    // ==================================================================

    public function storeItem(TravelExpense $travelExpense, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $travelExpense);

        $data = $request->validate([
            'type_expense_id' => 'required|exists:type_expenses,id',
            'expense_date' => 'required|date',
            'value' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:250',
            'invoice_number' => 'nullable|string|max:30',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment');
        }

        try {
            $this->accountabilityService->addItem($travelExpense, $data, $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Item adicionado à prestação.');
    }

    public function updateItem(TravelExpense $travelExpense, TravelExpenseItem $item, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $travelExpense);

        if ($item->travel_expense_id !== $travelExpense->id) {
            abort(404);
        }

        $data = $request->validate([
            'type_expense_id' => 'sometimes|exists:type_expenses,id',
            'expense_date' => 'sometimes|date',
            'value' => 'sometimes|numeric|min:0.01',
            'description' => 'sometimes|string|max:250',
            'invoice_number' => 'nullable|string|max:30',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment');
        }

        try {
            $this->accountabilityService->updateItem($item, $data, $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Item atualizado.');
    }

    public function destroyItem(TravelExpense $travelExpense, TravelExpenseItem $item, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $travelExpense);

        if ($item->travel_expense_id !== $travelExpense->id) {
            abort(404);
        }

        try {
            $this->accountabilityService->deleteItem($item, $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Item removido.');
    }

    // ==================================================================
    // Export (XLSX listagem + PDF comprovante individual)
    // ==================================================================

    public function export(Request $request): BinaryFileResponse
    {
        $user = $request->user();

        $query = $this->service->scopedQuery($user);
        $this->applyExportFilters($query, $request);

        return $this->exportService->exportExcel($query);
    }

    public function exportPdf(TravelExpense $travelExpense, Request $request): HttpResponse
    {
        $this->ensureCanView($request->user(), $travelExpense);

        return $this->exportService->exportPdf($travelExpense);
    }

    /**
     * Aplica os mesmos filtros do index() ao export. Mantido inline pra
     * espelhar exatamente a query da listagem (paridade index/export).
     */
    protected function applyExportFilters($query, Request $request): void
    {
        if ($request->filled('store_code')) {
            $query->forStore($request->store_code);
        }

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        } elseif (! $request->boolean('include_terminal')) {
            $query->whereNotIn('status', [
                TravelExpenseStatus::REJECTED->value,
                TravelExpenseStatus::FINALIZED->value,
                TravelExpenseStatus::CANCELLED->value,
            ]);
        }

        if ($request->filled('accountability_status')) {
            $query->where('accountability_status', $request->accountability_status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('origin', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($qq) => $qq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('initial_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('end_date', '<=', $request->date_to);
        }
    }

    // ==================================================================
    // Dashboard
    // ==================================================================

    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $period = (int) $request->input('months', 12);
        $period = max(3, min(24, $period)); // clamp 3..24

        $analytics = $this->buildDashboardAnalytics($scopedStoreCode, $period);

        return Inertia::render('TravelExpenses/Dashboard', [
            'analytics' => $analytics,
            'period' => $period,
            'isStoreScoped' => $scopedStoreCode !== null,
            'scopedStoreCode' => $scopedStoreCode,
            'permissions' => [
                'export' => $user->hasPermissionTo(Permission::EXPORT_TRAVEL_EXPENSES->value),
            ],
        ]);
    }

    /**
     * 4 séries: gasto mensal, top destinos, distribuição por tipo,
     * top beneficiados.
     */
    protected function buildDashboardAnalytics(?string $scopedStoreCode, int $months): array
    {
        $base = TravelExpense::notDeleted()
            ->whereIn('status', [
                TravelExpenseStatus::APPROVED->value,
                TravelExpenseStatus::FINALIZED->value,
            ]);

        if ($scopedStoreCode) {
            $base->forStore($scopedStoreCode);
        }

        $startDate = now()->subMonths($months - 1)->startOfMonth();
        $base->where('initial_date', '>=', $startDate->toDateString());

        // 1) Gasto mensal (linha)
        $monthly = (clone $base)
            ->select(
                DB::raw("DATE_FORMAT(initial_date, '%Y-%m') as month"),
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(value), 0) as total_value'),
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Preenche meses sem registros com zero (front recebe série completa)
        $monthlySeries = [];
        for ($i = 0; $i < $months; $i++) {
            $cursor = $startDate->copy()->addMonths($i);
            $key = $cursor->format('Y-m');
            $row = $monthly->get($key);
            $monthlySeries[] = [
                'month' => $key,
                'month_label' => $cursor->locale('pt_BR')->isoFormat('MMM/YY'),
                'count' => (int) ($row->count ?? 0),
                'total_value' => (float) ($row->total_value ?? 0),
            ];
        }

        // 2) Top destinos (barra)
        $topDestinations = (clone $base)
            ->select('destination', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(value), 0) as total_value'))
            ->groupBy('destination')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'destination' => $r->destination,
                'count' => (int) $r->count,
                'total_value' => (float) $r->total_value,
            ])
            ->all();

        // 3) Distribuição por tipo de despesa (pizza)
        $byType = DB::table('travel_expense_items')
            ->join('travel_expenses', 'travel_expenses.id', '=', 'travel_expense_items.travel_expense_id')
            ->join('type_expenses', 'type_expenses.id', '=', 'travel_expense_items.type_expense_id')
            ->whereNull('travel_expense_items.deleted_at')
            ->whereNull('travel_expenses.deleted_at')
            ->where('travel_expenses.initial_date', '>=', $startDate->toDateString())
            ->when($scopedStoreCode, fn ($q) => $q->where('travel_expenses.store_code', $scopedStoreCode))
            ->select(
                'type_expenses.id',
                'type_expenses.name',
                'type_expenses.color',
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(travel_expense_items.value), 0) as total_value'),
            )
            ->groupBy('type_expenses.id', 'type_expenses.name', 'type_expenses.color')
            ->orderByDesc('total_value')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'color' => $r->color,
                'count' => (int) $r->count,
                'total_value' => (float) $r->total_value,
            ])
            ->all();

        // 4) Top beneficiados (barra horizontal)
        $topBeneficiaries = (clone $base)
            ->select('employee_id', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(value), 0) as total_value'))
            ->groupBy('employee_id')
            ->orderByDesc('total_value')
            ->limit(10)
            ->with('employee:id,name')
            ->get()
            ->map(fn ($r) => [
                'employee_id' => $r->employee_id,
                'name' => $r->employee?->name ?? '—',
                'count' => (int) $r->count,
                'total_value' => (float) $r->total_value,
            ])
            ->all();

        // Resumo
        $totalCount = (clone $base)->count();
        $totalValue = (float) (clone $base)->sum('value');
        $avgTicket = $totalCount > 0 ? $totalValue / $totalCount : 0;

        return [
            'summary' => [
                'count' => $totalCount,
                'total_value' => $totalValue,
                'avg_ticket' => $avgTicket,
                'period_label' => $months.' meses (desde '.$startDate->format('m/Y').')',
            ],
            'monthly' => $monthlySeries,
            'top_destinations' => $topDestinations,
            'by_type' => $byType,
            'top_beneficiaries' => $topBeneficiaries,
        ];
    }

    public function downloadAttachment(TravelExpense $travelExpense, TravelExpenseItem $item, Request $request)
    {
        $this->ensureCanView($request->user(), $travelExpense);

        if ($item->travel_expense_id !== $travelExpense->id || ! $item->attachment_path) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($item->attachment_path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return Storage::disk('public')->download(
            $item->attachment_path,
            $item->attachment_original_name ?: basename($item->attachment_path)
        );
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    protected function resolveScopedStoreCode(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->hasPermissionTo(Permission::MANAGE_TRAVEL_EXPENSES->value)
            || $user->hasPermissionTo(Permission::APPROVE_TRAVEL_EXPENSES->value)) {
            return null;
        }

        return $user->store_id ?: null;
    }

    protected function ensureCanView(?User $user, TravelExpense $te): void
    {
        if (! $user) {
            abort(403);
        }

        if ($user->hasPermissionTo(Permission::MANAGE_TRAVEL_EXPENSES->value)
            || $user->hasPermissionTo(Permission::APPROVE_TRAVEL_EXPENSES->value)) {
            return;
        }

        $isOwner = $te->created_by_user_id === $user->id;
        $sameStore = $user->store_id && $te->store_code === $user->store_id;

        if (! $isOwner && ! $sameStore) {
            abort(403, 'Você não tem acesso a esta verba.');
        }
    }

    protected function buildStatistics(?string $scopedStoreCode): array
    {
        $base = TravelExpense::notDeleted();
        if ($scopedStoreCode) {
            $base->forStore($scopedStoreCode);
        }

        $total = (clone $base)->count();
        $draft = (clone $base)->forStatus(TravelExpenseStatus::DRAFT)->count();
        $submitted = (clone $base)->forStatus(TravelExpenseStatus::SUBMITTED)->count();
        $approved = (clone $base)->forStatus(TravelExpenseStatus::APPROVED)->count();
        $finalized = (clone $base)->forStatus(TravelExpenseStatus::FINALIZED)->count();
        $accountabilityOverdue = (clone $base)->accountabilityOverdue(3)->count();
        $totalValue = (float) (clone $base)->whereIn('status', [
            TravelExpenseStatus::APPROVED->value,
            TravelExpenseStatus::FINALIZED->value,
        ])->sum('value');

        return [
            'total' => $total,
            'draft' => $draft,
            'submitted' => $submitted,
            'approved' => $approved,
            'finalized' => $finalized,
            'accountability_overdue' => $accountabilityOverdue,
            'total_value' => $totalValue,
        ];
    }

    protected function validationRules(bool $isUpdate = false): array
    {
        $rules = [
            'employee_id' => ['required', 'exists:employees,id'],
            'store_code' => ['required', 'string', 'max:10'],
            'origin' => ['required', 'string', 'max:120'],
            'destination' => ['required', 'string', 'max:120'],
            'initial_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:initial_date'],
            'description' => ['required', 'string', 'max:1000'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'client_name' => ['nullable', 'string', 'max:150'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'bank_id' => ['nullable', 'exists:banks,id'],
            'bank_branch' => ['nullable', 'string', 'max:10'],
            'bank_account' => ['nullable', 'string', 'max:20'],
            'pix_type_id' => ['nullable', 'exists:type_key_pixs,id'],
            'pix_key' => ['nullable', 'string', 'max:200'],
            'internal_notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($isUpdate) {
            // Em update, todos os campos viram opcionais (PATCH style)
            foreach ($rules as $k => $r) {
                $rules[$k] = array_map(fn ($v) => $v === 'required' ? 'sometimes' : $v, $r);
            }
        }

        return $rules;
    }

    protected function formatExpense(TravelExpense $te): array
    {
        return [
            'id' => $te->id,
            'ulid' => $te->ulid,
            'employee' => $te->employee ? [
                'id' => $te->employee->id,
                'name' => $te->employee->name,
            ] : null,
            'store' => $te->store ? [
                'id' => $te->store->id,
                'code' => $te->store->code,
                'name' => $te->store->name,
            ] : null,
            'store_code' => $te->store_code,
            'origin' => $te->origin,
            'destination' => $te->destination,
            'initial_date' => optional($te->initial_date)->format('Y-m-d'),
            'end_date' => optional($te->end_date)->format('Y-m-d'),
            'days_count' => $te->days_count,
            'daily_rate' => (float) $te->daily_rate,
            'value' => (float) $te->value,
            'status' => $te->status->value,
            'status_label' => $te->status->label(),
            'status_color' => $te->status->color(),
            'accountability_status' => $te->accountability_status->value,
            'accountability_status_label' => $te->accountability_status->label(),
            'accountability_status_color' => $te->accountability_status->color(),
            'created_at' => optional($te->created_at)->format('Y-m-d H:i:s'),
            'created_by' => $te->createdBy ? ['id' => $te->createdBy->id, 'name' => $te->createdBy->name] : null,
            'is_overdue' => $te->status === TravelExpenseStatus::APPROVED
                && $te->end_date
                && $te->end_date->lt(now()->subDays(3))
                && ! in_array($te->accountability_status, [
                    AccountabilityStatus::SUBMITTED,
                    AccountabilityStatus::APPROVED,
                ], true),
        ];
    }

    protected function formatExpenseDetailed(TravelExpense $te): array
    {
        $base = $this->formatExpense($te);

        return array_merge($base, [
            'description' => $te->description,
            'internal_notes' => $te->internal_notes,
            'client_name' => $te->client_name,
            'masked_cpf' => $te->masked_cpf,
            'bank' => $te->bank ? [
                'id' => $te->bank->id,
                'bank_name' => $te->bank->bank_name,
                'cod_bank' => $te->bank->cod_bank,
            ] : null,
            'bank_branch' => $te->bank_branch,
            'bank_account' => $te->bank_account,
            'pix_type' => $te->pixType ? ['id' => $te->pixType->id, 'name' => $te->pixType->name] : null,
            'pix_key' => $te->pix_key, // já decriptada via accessor
            'submitted_at' => optional($te->submitted_at)->format('Y-m-d H:i:s'),
            'approved_at' => optional($te->approved_at)->format('Y-m-d H:i:s'),
            'approver' => $te->approver ? ['id' => $te->approver->id, 'name' => $te->approver->name] : null,
            'rejected_at' => optional($te->rejected_at)->format('Y-m-d H:i:s'),
            'rejection_reason' => $te->rejection_reason,
            'finalized_at' => optional($te->finalized_at)->format('Y-m-d H:i:s'),
            'cancelled_at' => optional($te->cancelled_at)->format('Y-m-d H:i:s'),
            'cancelled_reason' => $te->cancelled_reason,
            'accountability_submitted_at' => optional($te->accountability_submitted_at)->format('Y-m-d H:i:s'),
            'accountability_approved_at' => optional($te->accountability_approved_at)->format('Y-m-d H:i:s'),
            'accountability_rejected_at' => optional($te->accountability_rejected_at)->format('Y-m-d H:i:s'),
            'accountability_rejection_reason' => $te->accountability_rejection_reason,
            'accounted_value' => (float) $te->accounted_value,
            'balance' => (float) $te->balance,
            'items' => $te->items->map(fn ($i) => $this->formatItem($i))->all(),
            'history' => $te->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'kind' => $h->kind,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'note' => $h->note,
                'created_at' => optional($h->created_at)->format('Y-m-d H:i:s'),
                'changed_by' => $h->changedBy ? ['id' => $h->changedBy->id, 'name' => $h->changedBy->name] : null,
            ])->all(),
            'updated_at' => optional($te->updated_at)->format('Y-m-d H:i:s'),
            'updated_by' => $te->updatedBy ? ['id' => $te->updatedBy->id, 'name' => $te->updatedBy->name] : null,
        ]);
    }

    protected function formatItem(TravelExpenseItem $item): array
    {
        return [
            'id' => $item->id,
            'type_expense' => $item->typeExpense ? [
                'id' => $item->typeExpense->id,
                'name' => $item->typeExpense->name,
                'icon' => $item->typeExpense->icon,
                'color' => $item->typeExpense->color,
            ] : null,
            'expense_date' => optional($item->expense_date)->format('Y-m-d'),
            'value' => (float) $item->value,
            'invoice_number' => $item->invoice_number,
            'description' => $item->description,
            'has_attachment' => $item->has_attachment,
            'attachment_original_name' => $item->attachment_original_name,
            'attachment_size_formatted' => $item->attachment_size_formatted,
            'created_at' => optional($item->created_at)->format('Y-m-d H:i:s'),
            'created_by' => $item->createdBy ? ['id' => $item->createdBy->id, 'name' => $item->createdBy->name] : null,
        ];
    }
}
