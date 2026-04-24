<?php

namespace App\Http\Controllers;

use App\Enums\ConsignmentStatus;
use App\Enums\ConsignmentType;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Consignment;
use App\Models\Employee;
use App\Models\Store;
use App\Models\User;
use App\Services\ConsignmentExportService;
use App\Services\ConsignmentLookupService;
use App\Services\ConsignmentReturnService;
use App\Services\ConsignmentService;
use App\Services\ConsignmentTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Controller de Consignações (Cliente / Influencer / E-commerce).
 *
 * Store scoping: sem MANAGE_CONSIGNMENTS, o usuário só vê/edita
 * consignações da própria loja. Scope é resolvido via
 * `resolveScopedStoreId` e aplicado em todas as queries + mutações.
 *
 * State machine: mutações de status nunca ocorrem aqui — sempre via
 * `ConsignmentTransitionService`. O endpoint /transition delega.
 */
class ConsignmentController extends Controller
{
    public function __construct(
        private ConsignmentService $service,
        private ConsignmentLookupService $lookup,
        private ConsignmentTransitionService $transitions,
        private ConsignmentReturnService $returnService,
        private ConsignmentExportService $exportService,
    ) {
    }

    // ==================================================================
    // Listagem / Detalhe
    // ==================================================================

    public function index(Request $request): Response
    {
        $user = $request->user();
        $scopedStoreId = $this->resolveScopedStoreId($user);

        $query = Consignment::query()
            ->with(['store', 'employee'])
            ->notDeleted()
            ->latest();

        if ($scopedStoreId) {
            $query->where('store_id', $scopedStoreId);
        } elseif ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->store_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } elseif (! $request->boolean('include_terminal')) {
            // Por padrão, esconde completed/cancelled da listagem ativa
            $query->whereNotIn('status', [
                ConsignmentStatus::COMPLETED->value,
                ConsignmentStatus::CANCELLED->value,
            ]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_document_clean', 'like', "%{$search}%")
                    ->orWhere('outbound_invoice_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('outbound_invoice_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('outbound_invoice_date', '<=', $request->date_to);
        }

        $consignments = $query->paginate(15)
            ->withQueryString()
            ->through(fn (Consignment $c) => $this->formatConsignment($c));

        $statistics = $this->buildStatistics($scopedStoreId);

        return Inertia::render('Consignments/Index', [
            'consignments' => $consignments,
            'filters' => $request->only([
                'store_id', 'type', 'status', 'search',
                'date_from', 'date_to', 'include_terminal',
            ]),
            'statistics' => $statistics,
            'typeOptions' => ConsignmentType::labels(),
            'statusOptions' => ConsignmentStatus::labels(),
            'statusColors' => ConsignmentStatus::colors(),
            'statusTransitions' => ConsignmentStatus::transitionMap(),
            'isStoreScoped' => $scopedStoreId !== null,
            'scopedStoreId' => $scopedStoreId,
            'selects' => [
                'stores' => $scopedStoreId
                    ? Store::where('id', $scopedStoreId)->get(['id', 'code', 'name'])
                    : Store::orderBy('name')->get(['id', 'code', 'name']),
            ],
            'can' => [
                'create' => $user?->hasPermissionTo(Permission::CREATE_CONSIGNMENTS->value) ?? false,
                'edit' => $user?->hasPermissionTo(Permission::EDIT_CONSIGNMENTS->value) ?? false,
                'delete' => $user?->hasPermissionTo(Permission::DELETE_CONSIGNMENTS->value) ?? false,
                'complete' => $user?->hasPermissionTo(Permission::COMPLETE_CONSIGNMENT->value) ?? false,
                'cancel' => $user?->hasPermissionTo(Permission::CANCEL_CONSIGNMENT->value) ?? false,
                'register_return' => $user?->hasPermissionTo(Permission::REGISTER_CONSIGNMENT_RETURN->value) ?? false,
                'override_lock' => $user?->hasPermissionTo(Permission::OVERRIDE_CONSIGNMENT_LOCK->value) ?? false,
                'export' => $user?->hasPermissionTo(Permission::EXPORT_CONSIGNMENTS->value) ?? false,
                'edit_return_period' => $this->canEditReturnPeriod($user),
                'choose_return_store' => $this->canChooseReturnStore($user),
            ],
            'user_store_code' => $user?->store_id,
        ]);
    }

    /**
     * True se o user pode escolher loja diferente ao lançar retorno.
     * Hierarquia >= SUPPORT.
     */
    protected function canChooseReturnStore(?User $user): bool
    {
        if (! $user || ! $user->role) {
            return false;
        }

        $role = $user->role instanceof Role ? $user->role : Role::tryFrom($user->role);

        return $role?->hasPermission(Role::SUPPORT) ?? false;
    }

    /**
     * Apenas usuários com hierarquia >= 8 (Finance/Accounting/Fiscal/
     * Admin/Super_admin) podem alterar o prazo de retorno. Operacional
     * (User/Support) usa o padrão de 7 dias — regra definida em 2026-04-23
     * pra evitar que loja estenda prazo sem autorização financeira.
     */
    protected function canEditReturnPeriod(?User $user): bool
    {
        if (! $user || ! $user->role) {
            return false;
        }

        $role = $user->role instanceof Role ? $user->role : Role::tryFrom($user->role);
        if (! $role) {
            return false;
        }

        return $role->hasPermission(Role::FINANCE);
    }

    public function show(Consignment $consignment, Request $request): JsonResponse
    {
        $this->ensureCanView($request->user(), $consignment);

        $consignment->load([
            'store', 'employee',
            'createdBy', 'completedBy', 'updatedBy', 'deletedBy',
            'items.product', 'items.variant', 'items.movement',
            'returns.items.consignmentItem', 'returns.registeredBy',
            'statusHistory.changedBy',
        ]);

        return response()->json([
            'consignment' => $this->formatConsignmentDetailed($consignment),
        ]);
    }

    // ==================================================================
    // Mutações
    // ==================================================================

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $scopedStoreId = $this->resolveScopedStoreId($user);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:cliente,influencer,ecommerce'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'customer_id' => ['nullable', 'integer'],
            'recipient_name' => ['required', 'string', 'max:200'],
            'recipient_document' => ['nullable', 'string', 'max:18'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'outbound_invoice_number' => ['required', 'string', 'max:20'],
            'outbound_invoice_date' => ['required', 'date'],
            'outbound_store_code' => ['nullable', 'string', 'max:10'],
            'return_period_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'expected_return_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'issue_now' => ['nullable', 'boolean'],
            'override_lock_reason' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            // Consignação é controle, não emite NF — TODOS os itens devem
            // vir da NF de saída (movement_id obrigatório). Backend nunca
            // aceita item manual, só populado via lookup CIGAM.
            'items.*.movement_id' => ['required', 'integer', 'exists:movements,id'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.reference' => ['nullable', 'string', 'max:50'],
            // Mercury/CIGAM: barcode = ref_size concat (ex: 'A1340000010002U35').
            'items.*.barcode' => ['nullable', 'string', 'max:32'],
            'items.*.size_label' => ['nullable', 'string', 'max:20'],
            'items.*.size_cigam_code' => ['nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_value' => ['required', 'numeric', 'min:0'],
        ]);

        // Scoped user — força store_id para sua loja
        if ($scopedStoreId && (int) $data['store_id'] !== $scopedStoreId) {
            abort(403, 'Você só pode criar consignações para a sua loja.');
        }

        // Hierarquia < 8 não altera prazo — força o default do tipo
        if (array_key_exists('return_period_days', $data) && ! $this->canEditReturnPeriod($user)) {
            unset($data['return_period_days']);
        }

        try {
            $this->service->create($data, $user);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Consignação criada com sucesso.');
    }

    public function update(Consignment $consignment, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $consignment);

        if ($consignment->isTerminal() && ! $request->user()->hasPermissionTo(Permission::OVERRIDE_CONSIGNMENT_LOCK->value)) {
            abort(403, 'Consignação em estado terminal. Use override para editar.');
        }

        $data = $request->validate([
            'recipient_name' => ['sometimes', 'string', 'max:200'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expected_return_date' => ['nullable', 'date'],
        ]);

        // Hierarquia < 8 não altera prazo via expected_return_date
        if (array_key_exists('expected_return_date', $data) && ! $this->canEditReturnPeriod($request->user())) {
            unset($data['expected_return_date']);
        }

        $consignment->update(array_merge($data, [
            'updated_by_user_id' => $request->user()->id,
        ]));

        return redirect()->back()->with('success', 'Consignação atualizada.');
    }

    public function destroy(Consignment $consignment, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $consignment);

        $data = $request->validate([
            'deleted_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->delete($consignment, $request->user(), $data['deleted_reason'] ?? null);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Consignação excluída.');
    }

    public function transition(Consignment $consignment, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $consignment);

        $data = $request->validate([
            'to_status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->transitions->transition(
                $consignment,
                $data['to_status'],
                $request->user(),
                $data['note'] ?? null,
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Status da consignação atualizado.');
    }

    public function registerReturn(Consignment $consignment, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $consignment);

        $data = $request->validate([
            'return_invoice_number' => ['required', 'string', 'max:20'],
            'return_date' => ['required', 'date'],
            'return_store_code' => ['required', 'string', 'max:10'],
            'movement_id' => ['nullable', 'integer', 'exists:movements,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sale_justification' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.consignment_item_id' => ['required', 'integer', 'exists:consignment_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.action' => ['nullable', 'string', 'in:returned,sold'],
        ]);

        // Se user < SUPPORT, força a loja do próprio user
        $data['return_store_code'] = $this->resolveReturnStoreCode(
            $request->user(),
            $data['return_store_code'],
        );

        try {
            $this->returnService->register(
                $consignment,
                [
                    'return_invoice_number' => $data['return_invoice_number'],
                    'return_date' => $data['return_date'],
                    'return_store_code' => $data['return_store_code'],
                    'movement_id' => $data['movement_id'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'sale_justification' => $data['sale_justification'] ?? null,
                ],
                $data['items'],
                $request->user(),
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Retorno registrado com sucesso.');
    }

    /**
     * Endpoint AJAX — busca a NF de retorno no CIGAM (code=21) e
     * retorna o diff contra a NF de saída da consignação.
     * Usado pelo RegisterReturnModal ao preencher os 3 campos.
     */
    public function lookupReturnCompare(Consignment $consignment, Request $request): JsonResponse
    {
        $this->ensureCanView($request->user(), $consignment);

        $data = $request->validate([
            'return_invoice_number' => ['required', 'string', 'max:20'],
            'return_date' => ['required', 'date'],
            'return_store_code' => ['required', 'string', 'max:10'],
        ]);

        $storeCode = $this->resolveReturnStoreCode($request->user(), $data['return_store_code']);

        $comparison = $this->lookup->compareReturnWithOutbound(
            $consignment,
            $storeCode,
            $data['return_invoice_number'],
            $data['return_date'],
        );

        $saleCheck = $this->lookup->verifyCustomerSale(
            $consignment->recipient_document_clean,
            $data['return_date'],
            7,
        );

        return response()->json([
            'comparison' => $comparison,
            'customer_sale_check' => [
                'cpf' => $consignment->recipient_document_clean,
                'window_days' => 7,
                'found_in_cigam' => $saleCheck['found'],
                'movements' => $saleCheck['movements'],
            ],
        ]);
    }

    /**
     * Resolve o store_code efetivo do retorno baseado na hierarquia
     * do user. USER/DRIVER (hierarquia < SUPPORT) ficam travados na
     * própria loja; SUPPORT+ pode escolher qualquer uma.
     */
    protected function resolveReturnStoreCode(?User $user, string $requested): string
    {
        if (! $user || ! $user->role) {
            return $requested;
        }

        $role = $user->role instanceof Role ? $user->role : Role::tryFrom($user->role);
        if (! $role) {
            return $requested;
        }

        // SUPPORT (hierarquia 2) ou superior pode escolher
        if ($role->hasPermission(Role::SUPPORT)) {
            return $requested;
        }

        return $user->store_id ?: $requested;
    }

    // ==================================================================
    // Dashboard — gráficos recharts
    // ==================================================================

    public function dashboard(Request $request): Response
    {
        $scopedStoreId = $this->resolveScopedStoreId($request->user());

        return Inertia::render('Consignments/Dashboard', [
            'analytics' => $this->buildAnalytics($scopedStoreId),
            'statistics' => $this->buildStatistics($scopedStoreId),
            'isStoreScoped' => $scopedStoreId !== null,
            'scopedStoreId' => $scopedStoreId,
            'typeOptions' => ConsignmentType::labels(),
            'statusOptions' => ConsignmentStatus::labels(),
            'statusColors' => ConsignmentStatus::colors(),
        ]);
    }

    /**
     * 4 agregações para os gráficos:
     *  1. evolução mensal (últimos 12 meses) — line
     *  2. distribuição por tipo — pie
     *  3. top 10 destinatários por volume — bar horizontal
     *  4. taxa de retorno por consultor(a) (finalizadas / total) — bar
     */
    protected function buildAnalytics(?int $scopedStoreId): array
    {
        $base = fn () => Consignment::query()->notDeleted()
            ->when($scopedStoreId, fn ($q) => $q->where('store_id', $scopedStoreId));

        $driver = Consignment::query()->getConnection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        // 1. Evolução mensal (últimos 12 meses)
        $byMonth = $base()
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total, COALESCE(SUM(outbound_total_value), 0) as value")
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $mesAbrev = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

        $monthSeries = $byMonth->map(function ($r) use ($mesAbrev) {
            [$year, $month] = explode('-', (string) $r->ym);

            return [
                'month' => ($mesAbrev[(int) $month - 1] ?? $month).'/'.$year,
                'total' => (int) $r->total,
                'value' => (float) $r->value,
            ];
        })->values();

        // 2. Distribuição por tipo
        $byType = $base()
            ->selectRaw('type, COUNT(*) as total, COALESCE(SUM(outbound_total_value), 0) as value')
            ->groupBy('type')
            ->get()
            ->map(function ($r) {
                $type = $r->type instanceof ConsignmentType
                    ? $r->type
                    : ConsignmentType::tryFrom((string) $r->type);

                return [
                    'type' => $type?->value ?? (string) $r->type,
                    'label' => $type?->label() ?? (string) $r->type,
                    'color' => $type?->color() ?? 'gray',
                    'total' => (int) $r->total,
                    'value' => (float) $r->value,
                ];
            })->values();

        // 3. Top 10 destinatários por volume (count de consignações)
        $byRecipient = $base()
            ->whereNotNull('recipient_document_clean')
            ->selectRaw('recipient_name, recipient_document_clean, COUNT(*) as total, COALESCE(SUM(outbound_total_value), 0) as value')
            ->groupBy('recipient_name', 'recipient_document_clean')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name' => $r->recipient_name,
                'total' => (int) $r->total,
                'value' => (float) $r->value,
            ])->values();

        // 4. Taxa de retorno por consultor(a) — só tipos que têm employee
        $byEmployee = $base()
            ->whereNotNull('employee_id')
            ->selectRaw('
                employee_id,
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count,
                COALESCE(SUM(returned_total_value), 0) as returned_value,
                COALESCE(SUM(outbound_total_value), 0) as outbound_value
            ', [ConsignmentStatus::COMPLETED->value])
            ->groupBy('employee_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $employeeNames = \App\Models\Employee::whereIn('id', $byEmployee->pluck('employee_id'))
            ->pluck('name', 'id');

        $byEmployeeData = $byEmployee->map(function ($r) use ($employeeNames) {
            $outbound = (float) $r->outbound_value;
            $returned = (float) $r->returned_value;
            $returnRate = $outbound > 0 ? round(($returned / $outbound) * 100, 1) : 0.0;

            return [
                'employee_id' => $r->employee_id,
                'name' => $employeeNames[$r->employee_id] ?? "ID {$r->employee_id}",
                'total' => (int) $r->total,
                'completed' => (int) $r->completed_count,
                'return_rate' => $returnRate,
            ];
        })->values();

        return [
            'by_month' => $monthSeries,
            'by_type' => $byType,
            'by_recipient' => $byRecipient,
            'by_employee' => $byEmployeeData,
        ];
    }

    // ==================================================================
    // AJAX lookups (M8 — catálogo de produtos + CIGAM)
    // ==================================================================

    public function lookupProducts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $results = $this->lookup->searchProducts(
            $data['q'],
            (int) ($data['limit'] ?? 20),
        );

        return response()->json(['results' => $results]);
    }

    public function lookupOutboundInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:20'],
            'store_code' => ['required', 'string', 'max:10'],
            'movement_date' => ['nullable', 'date'],
        ]);

        $result = $this->lookup->findOutboundInvoice(
            $data['store_code'],
            $data['invoice_number'],
            $data['movement_date'] ?? null,
        );

        return response()->json($result);
    }

    public function lookupReturnInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:20'],
            'store_code' => ['required', 'string', 'max:10'],
            'movement_date' => ['nullable', 'date'],
        ]);

        $result = $this->lookup->findReturnInvoice(
            $data['store_code'],
            $data['invoice_number'],
            $data['movement_date'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * Lista colaboradores ativos da loja selecionada — usado pelo select
     * "Consultor(a) responsável" no modal de criação. Filtro server-side
     * evita expor todos os colaboradores do tenant no frontend.
     */
    public function lookupEmployees(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
        ]);

        return response()->json([
            'employees' => $this->lookup->employeesByStore((int) $data['store_id']),
        ]);
    }

    // ==================================================================
    // Exports (XLSX + PDF comprovante com QR Code)
    // ==================================================================

    public function export(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $scopedStoreId = $this->resolveScopedStoreId($user);

        $query = Consignment::query()
            ->with(['store', 'employee', 'items'])
            ->notDeleted()
            ->latest();

        if ($scopedStoreId) {
            $query->where('store_id', $scopedStoreId);
        } elseif ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->store_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } elseif (! $request->boolean('include_terminal')) {
            $query->whereNotIn('status', [
                ConsignmentStatus::COMPLETED->value,
                ConsignmentStatus::CANCELLED->value,
            ]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_document_clean', 'like', "%{$search}%")
                    ->orWhere('outbound_invoice_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('outbound_invoice_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('outbound_invoice_date', '<=', $request->date_to);
        }

        return $this->exportService->exportExcel($query);
    }

    public function exportPdf(Consignment $consignment, Request $request): HttpResponse
    {
        $this->ensureCanView($request->user(), $consignment);

        return $this->exportService->exportPdf($consignment);
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * Resolve o store_id de escopo para o usuário — null quando pode
     * ver todas as lojas (MANAGE_CONSIGNMENTS), senão o id da loja do
     * próprio usuário via user.store_id.
     */
    protected function resolveScopedStoreId(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        if ($user->hasPermissionTo(Permission::MANAGE_CONSIGNMENTS->value)) {
            return null;
        }

        // user.store_id aqui é code de loja (Z421 etc.) — convertemos
        $storeCode = $user->store_id ?: null;
        if (! $storeCode) {
            return null;
        }

        return Store::where('code', $storeCode)->value('id');
    }

    protected function ensureCanView(?User $user, Consignment $consignment): void
    {
        $scopedStoreId = $this->resolveScopedStoreId($user);
        if ($scopedStoreId && $consignment->store_id !== $scopedStoreId) {
            abort(403, 'Você não tem acesso a consignações de outras lojas.');
        }
    }

    protected function buildStatistics(?int $scopedStoreId): array
    {
        $base = Consignment::query()->notDeleted();
        if ($scopedStoreId) {
            $base->where('store_id', $scopedStoreId);
        }

        $total = (clone $base)->count();
        $pending = (clone $base)->where('status', ConsignmentStatus::PENDING->value)->count();
        $partial = (clone $base)->where('status', ConsignmentStatus::PARTIALLY_RETURNED->value)->count();
        $overdue = (clone $base)->where('status', ConsignmentStatus::OVERDUE->value)->count();
        $completed = (clone $base)->where('status', ConsignmentStatus::COMPLETED->value)->count();
        $cancelled = (clone $base)->where('status', ConsignmentStatus::CANCELLED->value)->count();

        $outstandingValue = (clone $base)
            ->whereIn('status', [
                ConsignmentStatus::PENDING->value,
                ConsignmentStatus::PARTIALLY_RETURNED->value,
                ConsignmentStatus::OVERDUE->value,
            ])
            ->sum('outbound_total_value');

        return [
            'total' => $total,
            'pending' => $pending,
            'partially_returned' => $partial,
            'overdue' => $overdue,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'outstanding_value' => (float) $outstandingValue,
        ];
    }

    protected function formatConsignment(Consignment $c): array
    {
        return [
            'id' => $c->id,
            'uuid' => $c->uuid,
            'type' => $c->type->value,
            'type_label' => $c->type->label(),
            'status' => $c->status->value,
            'status_label' => $c->status->label(),
            'status_color' => $c->status->color(),
            'store' => [
                'id' => $c->store?->id,
                'code' => $c->store?->code,
                'name' => $c->store?->name,
            ],
            'employee' => $c->employee ? [
                'id' => $c->employee->id,
                'name' => $c->employee->name,
            ] : null,
            'recipient_name' => $c->recipient_name,
            'recipient_document' => $c->recipient_document,
            'outbound_invoice_number' => $c->outbound_invoice_number,
            'outbound_invoice_date' => $c->outbound_invoice_date?->format('Y-m-d'),
            'outbound_total_value' => (float) $c->outbound_total_value,
            'outbound_items_count' => $c->outbound_items_count,
            'returned_items_count' => $c->returned_items_count,
            'sold_items_count' => $c->sold_items_count,
            'lost_items_count' => $c->lost_items_count,
            'expected_return_date' => $c->expected_return_date?->format('Y-m-d'),
            'return_period_days' => $c->return_period_days,
            'is_overdue' => $c->is_overdue,
            'issued_at' => $c->issued_at?->toIso8601String(),
            'completed_at' => $c->completed_at?->toIso8601String(),
            'cancelled_at' => $c->cancelled_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }

    protected function formatConsignmentDetailed(Consignment $c): array
    {
        $base = $this->formatConsignment($c);

        return array_merge($base, [
            'recipient_phone' => $c->recipient_phone,
            'recipient_email' => $c->recipient_email,
            'outbound_store_code' => $c->outbound_store_code,
            'returned_total_value' => (float) $c->returned_total_value,
            'sold_total_value' => (float) $c->sold_total_value,
            'lost_total_value' => (float) $c->lost_total_value,
            'notes' => $c->notes,
            'cancelled_reason' => $c->cancelled_reason,
            'created_by' => $c->createdBy ? [
                'id' => $c->createdBy->id,
                'name' => $c->createdBy->name,
            ] : null,
            'completed_by' => $c->completedBy ? [
                'id' => $c->completedBy->id,
                'name' => $c->completedBy->name,
            ] : null,
            'items' => $c->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'reference' => $item->reference,
                'barcode' => $item->barcode,
                'size_label' => $item->size_label,
                'size_cigam_code' => $item->size_cigam_code,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'returned_quantity' => $item->returned_quantity,
                'sold_quantity' => $item->sold_quantity,
                'lost_quantity' => $item->lost_quantity,
                'pending_quantity' => $item->pending_quantity,
                'unit_value' => (float) $item->unit_value,
                'total_value' => (float) $item->total_value,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'status_color' => $item->status->color(),
            ])->values(),
            'returns' => $c->returns->map(fn ($r) => [
                'id' => $r->id,
                'return_invoice_number' => $r->return_invoice_number,
                'return_date' => $r->return_date?->format('Y-m-d'),
                'return_store_code' => $r->return_store_code,
                'returned_quantity' => $r->returned_quantity,
                'returned_value' => (float) $r->returned_value,
                'notes' => $r->notes,
                'registered_by' => $r->registeredBy ? [
                    'id' => $r->registeredBy->id,
                    'name' => $r->registeredBy->name,
                ] : null,
                'items' => $r->items->map(fn ($ri) => [
                    'consignment_item_id' => $ri->consignment_item_id,
                    'quantity' => $ri->quantity,
                    'subtotal' => (float) $ri->subtotal,
                ])->values(),
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values(),
            'status_history' => $c->statusHistory->map(fn ($h) => [
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'note' => $h->note,
                'context' => $h->context,
                'changed_by' => $h->changedBy ? [
                    'id' => $h->changedBy->id,
                    'name' => $h->changedBy->name,
                ] : null,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
