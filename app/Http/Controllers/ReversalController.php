<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\ReversalStatus;
use App\Enums\ReversalType;
use App\Http\Requests\Reversal\StoreReversalRequest;
use App\Http\Requests\Reversal\TransitionReversalRequest;
use App\Http\Requests\Reversal\UpdateReversalRequest;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\PaymentType;
use App\Models\Reversal;
use App\Models\ReversalFile;
use App\Models\ReversalReason;
use App\Models\Store;
use App\Models\User;
use App\Services\ReversalExportService;
use App\Services\ReversalImportService;
use App\Services\ReversalLookupService;
use App\Services\ReversalService;
use App\Services\ReversalTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReversalController extends Controller
{
    public function __construct(
        private ReversalService $service,
        private ReversalLookupService $lookup,
        private ReversalTransitionService $transitionService,
        private ReversalExportService $exportService,
        private ReversalImportService $importService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $query = Reversal::with(['reason', 'paymentType', 'employee', 'store'])
            ->notDeleted()
            ->latest();

        if ($scopedStoreCode) {
            $query->forStore($scopedStoreCode);
        } elseif ($request->filled('store_code')) {
            $query->forStore($request->store_code);
        }

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        } elseif (! $request->boolean('include_terminal')) {
            $query->whereNotIn('status', [
                ReversalStatus::REVERSED->value,
                ReversalStatus::CANCELLED->value,
            ]);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('reversal_reason_id')) {
            $query->where('reversal_reason_id', $request->reversal_reason_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('cpf_customer', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $reversals = $query->paginate(15)
            ->withQueryString()
            ->through(fn ($r) => $this->formatReversal($r));

        $statistics = $this->buildStatistics($scopedStoreCode);

        return Inertia::render('Reversals/Index', [
            'reversals' => $reversals,
            'filters' => $request->only([
                'store_code', 'status', 'type', 'reversal_reason_id',
                'search', 'date_from', 'date_to', 'include_terminal',
            ]),
            'statistics' => $statistics,
            'statusOptions' => ReversalStatus::labels(),
            'statusColors' => ReversalStatus::colors(),
            'statusTransitions' => ReversalStatus::transitionMap(),
            'typeOptions' => ReversalType::labels(),
            'isStoreScoped' => $scopedStoreCode !== null,
            'scopedStoreCode' => $scopedStoreCode,
            'selects' => [
                'stores' => $scopedStoreCode
                    ? Store::where('code', $scopedStoreCode)->get(['id', 'code', 'name'])
                    : Store::orderBy('name')->get(['id', 'code', 'name']),
                'reasons' => ReversalReason::active()
                    ->orderBy('sort_order')
                    ->get(['id', 'code', 'name']),
                'paymentTypes' => PaymentType::orderBy('name')->get(['id', 'name']),
                'banks' => Bank::orderBy('bank_name')->get(['id', 'bank_name', 'cod_bank']),
            ],
        ]);
    }

    public function store(StoreReversalRequest $request): RedirectResponse
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $data = $request->validated();

        // Resolve a loja a ser usada no lookup. Usuario com scoping fixa sua
        // propria loja; sem scoping, a loja deve vir do payload (select no
        // frontend). Sem loja definida, a busca ficaria ambigua porque o
        // numero da NF/cupom nao eh unico entre lojas.
        $data['store_code_filter'] = $scopedStoreCode ?? $data['store_code'] ?? null;

        if (! $data['store_code_filter']) {
            return redirect()->back()->withErrors([
                'store_code' => 'Selecione a loja da venda antes de continuar.',
            ])->withInput();
        }

        // Numero de NF/cupom se repete entre anos — a data da venda vinda do
        // frontend desempata quando ha multiplas ocorrencias do mesmo numero.
        $data['movement_date_filter'] = $data['movement_date'] ?? null;

        try {
            $reversal = $this->service->create($data, $user);

            if ($request->hasFile('files')) {
                $this->service->attachFiles($reversal, $request->file('files'), $user);
            }
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Estorno criado com sucesso.');
    }

    public function show(Reversal $reversal, Request $request): JsonResponse
    {
        $this->ensureCanView($request->user(), $reversal);

        $reversal->load([
            'reason', 'paymentType', 'pixBank', 'employee', 'store',
            'createdBy', 'authorizedBy', 'processedBy', 'updatedBy', 'deletedBy',
            'items.movement',
            'files.uploadedBy',
            'statusHistory.changedBy',
        ]);

        return response()->json([
            'reversal' => $this->formatReversalDetailed($reversal),
        ]);
    }

    public function update(Reversal $reversal, UpdateReversalRequest $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $reversal);

        try {
            $this->service->update($reversal, $request->validated(), $request->user());

            if ($request->hasFile('files')) {
                $this->service->attachFiles($reversal, $request->file('files'), $request->user());
            }
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Estorno atualizado.');
    }

    public function destroy(Reversal $reversal, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $reversal);

        if (! $request->user()->hasPermissionTo(Permission::DELETE_REVERSALS->value)) {
            abort(403, 'Você não tem permissão para excluir estornos.');
        }

        $data = $request->validate([
            'deleted_reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $this->service->softDelete($reversal, $request->user(), $data['deleted_reason']);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Estorno excluído.');
    }

    public function transition(Reversal $reversal, TransitionReversalRequest $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $reversal);

        $data = $request->validated();

        try {
            $this->transitionService->transition(
                $reversal,
                $data['to_status'],
                $request->user(),
                $data['note'] ?? null
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Status do estorno atualizado.');
    }

    /**
     * AJAX endpoint chamado pelo modal de criação ao digitar o número da NF.
     * Resolve a NF em `movements` e devolve o preview da venda com itens.
     */
    public function lookupInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_number' => 'required|string|max:50',
            'store_code' => 'nullable|string|max:10',
            'movement_date' => 'nullable|date',
        ]);

        $scopedStoreCode = $this->resolveScopedStoreCode($request->user());

        // Usuario com scoping so busca na propria loja. Sem scoping, a loja
        // precisa vir no query string — numero de NF/cupom nao eh unico
        // entre lojas, entao busca sem filtro retornaria vendas erradas.
        $storeCode = $scopedStoreCode ?? ($data['store_code'] ?? null);

        if (! $storeCode) {
            return response()->json([
                'found' => false,
                'invoice_number' => $data['invoice_number'],
                'store_code' => null,
                'movement_date' => null,
                'cpf_customer' => null,
                'cpf_consultant' => null,
                'sale_total' => 0,
                'items_count' => 0,
                'available_dates' => [],
                'items' => [],
                'suggested_employee_id' => null,
                'error' => 'Informe a loja para buscar a NF/cupom.',
            ]);
        }

        $result = $this->lookup->lookupInvoice(
            $data['invoice_number'],
            $storeCode,
            $data['movement_date'] ?? null
        );

        // Enriquece items com employee_id lookup do cpf_consultant (quando
        // possível — otimiza preenchimento do formulário).
        if ($result['found'] && $result['cpf_consultant']) {
            $employee = Employee::where('cpf', $result['cpf_consultant'])->first();
            $result['suggested_employee_id'] = $employee?->id;
        } else {
            $result['suggested_employee_id'] = null;
        }

        return response()->json($result);
    }

    /**
     * Endpoint de estatísticas para refresh do StatisticsGrid sem page
     * reload — útil após criar/transicionar via modal.
     */
    public function statistics(Request $request): JsonResponse
    {
        $scopedStoreCode = $this->resolveScopedStoreCode($request->user());

        return response()->json(
            $this->buildStatistics($scopedStoreCode)
        );
    }

    /**
     * Dashboard com gráficos em página separada. Dados agregados para
     * recharts (pizza por motivo, barra por loja, linha temporal, métricas
     * de performance).
     */
    public function dashboard(Request $request): Response
    {
        $scopedStoreCode = $this->resolveScopedStoreCode($request->user());

        return Inertia::render('Reversals/Dashboard', [
            'statistics' => $this->buildStatistics($scopedStoreCode),
            'analytics' => $this->buildAnalytics($scopedStoreCode),
            'isStoreScoped' => $scopedStoreCode !== null,
            'scopedStoreCode' => $scopedStoreCode,
        ]);
    }

    // ------------------------------------------------------------------
    // Export (Fase 5)
    // ------------------------------------------------------------------

    /**
     * Export XLSX com os mesmos filtros aplicados na listagem.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $query = Reversal::query()->notDeleted()->latest();

        if ($scopedStoreCode) {
            $query->forStore($scopedStoreCode);
        } elseif ($request->filled('store_code')) {
            $query->forStore($request->store_code);
        }

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('reversal_reason_id')) {
            $query->where('reversal_reason_id', $request->reversal_reason_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('cpf_customer', 'like', "%{$search}%");
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $this->exportService->exportExcel($query);
    }

    /**
     * Gera o comprovante PDF individual do estorno.
     */
    public function exportPdf(Reversal $reversal, Request $request): HttpResponse
    {
        $this->ensureCanView($request->user(), $reversal);

        return $this->exportService->exportPdf($reversal);
    }

    // ------------------------------------------------------------------
    // Import (Fase 5)
    // ------------------------------------------------------------------

    /**
     * Endpoint de preview: usuário envia arquivo, recebe amostra + erros
     * sem persistir nada.
     */
    public function importPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $path = $data['file']->getRealPath();

        try {
            $result = $this->importService->preview($path);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Falha ao ler arquivo: '.$e->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    /**
     * Persiste os registros válidos (upsert por NF + loja + valor).
     */
    public function importStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $path = $data['file']->getRealPath();

        try {
            $result = $this->importService->import($path, $request->user());
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors([
                'file' => 'Falha no import: '.$e->getMessage(),
            ]);
        }

        return redirect()
            ->route('reversals.index')
            ->with('success', sprintf(
                'Import concluído: %d criado(s), %d atualizado(s), %d ignorado(s).',
                $result['created'],
                $result['updated'],
                $result['skipped']
            ));
    }

    /**
     * Remove um anexo. Bloqueia se o estorno já estiver em estado
     * terminal (reversed/cancelled).
     */
    public function destroyFile(Reversal $reversal, ReversalFile $file, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $reversal);

        if ($file->reversal_id !== $reversal->id) {
            abort(404);
        }

        if ($reversal->isTerminal()) {
            return redirect()->back()->withErrors([
                'file' => 'Não é possível remover anexos de estornos já finalizados.',
            ]);
        }

        $this->service->deleteFile($file);

        return redirect()->back()->with('success', 'Anexo removido.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Usuário sem MANAGE_REVERSALS fica restrito à própria loja
     * (user.store_id referencia stores.code).
     */
    protected function resolveScopedStoreCode(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->hasPermissionTo(Permission::MANAGE_REVERSALS->value)) {
            return null;
        }

        return $user->store_id ?: null;
    }

    protected function ensureCanView(?User $user, Reversal $reversal): void
    {
        $scoped = $this->resolveScopedStoreCode($user);
        if ($scoped && $reversal->store_code !== $scoped) {
            abort(403, 'Você não tem acesso a estornos de outras lojas.');
        }
    }

    protected function buildStatistics(?string $scopedStoreCode): array
    {
        $base = Reversal::notDeleted();
        if ($scopedStoreCode) {
            $base->forStore($scopedStoreCode);
        }

        $total = (clone $base)->count();

        // Alias `status_value` evita o cast automático do Reversal model
        // (que converteria a string para enum e quebraria o keyBy).
        $byStatus = (clone $base)
            ->selectRaw('status as status_value, COUNT(*) as count, COALESCE(SUM(amount_reversal), 0) as total_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status_value');

        $monthTotal = (clone $base)
            ->forMonth((int) now()->format('m'), (int) now()->format('Y'))
            ->sum('amount_reversal');

        return [
            'total' => $total,
            'total_amount' => (float) (clone $base)->sum('amount_reversal'),
            'pending_approval' => $byStatus->get(ReversalStatus::PENDING_AUTHORIZATION->value)?->count ?? 0,
            'pending_finance' => $byStatus->get(ReversalStatus::PENDING_FINANCE->value)?->count ?? 0,
            'reversed_this_month_amount' => (float) $monthTotal,
            'cancelled' => $byStatus->get(ReversalStatus::CANCELLED->value)?->count ?? 0,
        ];
    }

    /**
     * Agregações para os gráficos do Dashboard.
     */
    protected function buildAnalytics(?string $scopedStoreCode): array
    {
        $baseQuery = fn () => $scopedStoreCode
            ? Reversal::notDeleted()->forStore($scopedStoreCode)
            : Reversal::notDeleted();

        // 1. Distribuição por motivo (pizza)
        $byReason = $baseQuery()
            ->leftJoin('reversal_reasons', 'reversal_reasons.id', '=', 'reversals.reversal_reason_id')
            ->selectRaw('COALESCE(reversal_reasons.name, ?) as reason, COUNT(*) as count, COALESCE(SUM(reversals.amount_reversal), 0) as total_amount', ['(Sem motivo)'])
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'reason' => $r->reason,
                'count' => (int) $r->count,
                'total_amount' => (float) $r->total_amount,
            ])
            ->values();

        // 2. Distribuição por loja (barra)
        $byStore = $baseQuery()
            ->leftJoin('stores', 'stores.code', '=', 'reversals.store_code')
            ->selectRaw('reversals.store_code, COALESCE(stores.name, reversals.store_code) as store_name, COUNT(*) as count, COALESCE(SUM(reversals.amount_reversal), 0) as total_amount')
            ->groupBy('reversals.store_code', 'stores.name')
            ->orderByDesc('count')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'store_code' => $r->store_code,
                'store_name' => $r->store_name,
                'count' => (int) $r->count,
                'total_amount' => (float) $r->total_amount,
            ])
            ->values();

        // 3. Distribuição por status
        // Usa alias `status_value` para evitar o cast automático que o
        // Reversal model aplica em `status` → ReversalStatus enum.
        $byStatus = $baseQuery()
            ->selectRaw('status as status_value, COUNT(*) as count, COALESCE(SUM(amount_reversal), 0) as total_amount')
            ->groupBy('status')
            ->get()
            ->map(function ($r) {
                $raw = is_string($r->status_value) ? $r->status_value : ($r->status_value?->value ?? (string) $r->status_value);
                $status = ReversalStatus::tryFrom($raw);
                return [
                    'status' => $raw,
                    'label' => $status?->label() ?? $raw,
                    'color' => $status?->color() ?? 'gray',
                    'count' => (int) $r->count,
                    'total_amount' => (float) $r->total_amount,
                ];
            })
            ->values();

        // 4. Linha temporal — últimos 12 meses
        $twelveMonthsAgo = now()->subMonths(11)->startOfMonth();
        $timeline = $baseQuery()
            ->where('created_at', '>=', $twelveMonthsAgo)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count, COALESCE(SUM(amount_reversal), 0) as total_amount")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $timelineFull = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('Y-m');
            $row = $timeline->get($key);
            $timelineFull[] = [
                'month' => $key,
                'label' => $month->locale('pt_BR')->isoFormat('MMM/YY'),
                'count' => (int) ($row?->count ?? 0),
                'total_amount' => (float) ($row?->total_amount ?? 0),
            ];
        }

        // 5. Performance: tempo médio criação→estorno (em dias) e taxa de autorização
        $reversedRows = $baseQuery()
            ->whereNotNull('reversed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, reversed_at)) as avg_hours')
            ->first();
        $avgHoursToReverse = (float) ($reversedRows->avg_hours ?? 0);

        $processedCount = $baseQuery()
            ->whereIn('status', [ReversalStatus::REVERSED->value, ReversalStatus::CANCELLED->value])
            ->count();
        $reversedCount = $baseQuery()
            ->forStatus(ReversalStatus::REVERSED)
            ->count();
        $authRate = $processedCount > 0 ? round(($reversedCount / $processedCount) * 100, 1) : 0.0;

        return [
            'by_reason' => $byReason,
            'by_store' => $byStore,
            'by_status' => $byStatus,
            'timeline' => $timelineFull,
            'performance' => [
                'avg_hours_to_reverse' => round($avgHoursToReverse, 1),
                'avg_days_to_reverse' => round($avgHoursToReverse / 24, 1),
                'authorization_rate' => $authRate,
                'processed_count' => $processedCount,
                'reversed_count' => $reversedCount,
            ],
        ];
    }

    protected function formatReversal(Reversal $r): array
    {
        return [
            'id' => $r->id,
            'invoice_number' => $r->invoice_number,
            'store_code' => $r->store_code,
            'store_name' => $r->store?->name,
            'movement_date' => $r->movement_date?->toDateString(),
            'customer_name' => $r->customer_name,
            'cpf_customer' => $r->cpf_customer,
            'cpf_consultant' => $r->cpf_consultant,
            'employee_id' => $r->employee_id,
            'employee_name' => $r->employee?->name,
            'sale_total' => (float) $r->sale_total,
            'type' => $r->type?->value,
            'type_label' => $r->type?->label(),
            'partial_mode' => $r->partial_mode?->value,
            'partial_mode_label' => $r->partial_mode?->label(),
            'amount_original' => (float) $r->amount_original,
            'amount_correct' => $r->amount_correct !== null ? (float) $r->amount_correct : null,
            'amount_reversal' => (float) $r->amount_reversal,
            'status' => $r->status?->value,
            'status_label' => $r->status?->label(),
            'status_color' => $r->status?->color(),
            'reversal_reason_id' => $r->reversal_reason_id,
            'reason_name' => $r->reason?->name,
            'payment_type_id' => $r->payment_type_id,
            'payment_type_name' => $r->paymentType?->name,
            'expected_refund_date' => $r->expected_refund_date?->toDateString(),
            'reversed_at' => $r->reversed_at?->toDateTimeString(),
            'cancelled_at' => $r->cancelled_at?->toDateTimeString(),
            'is_terminal' => $r->isTerminal(),
            'created_at' => $r->created_at?->toDateTimeString(),
        ];
    }

    protected function formatReversalDetailed(Reversal $r): array
    {
        return array_merge($this->formatReversal($r), [
            'cancelled_reason' => $r->cancelled_reason,
            'notes' => $r->notes,
            'payment_brand' => $r->payment_brand,
            'installments_count' => $r->installments_count,
            'nsu' => $r->nsu,
            'authorization_code' => $r->authorization_code,
            'pix_key_type' => $r->pix_key_type,
            'pix_key' => $r->pix_key,
            'pix_beneficiary' => $r->pix_beneficiary,
            'pix_bank_id' => $r->pix_bank_id,
            'pix_bank_name' => $r->pixBank?->bank_name,
            'created_by_name' => $r->createdBy?->name,
            'authorized_by_name' => $r->authorizedBy?->name,
            'processed_by_name' => $r->processedBy?->name,
            'updated_by_name' => $r->updatedBy?->name,
            'deleted_at' => $r->deleted_at?->toDateTimeString(),
            'deleted_reason' => $r->deleted_reason,
            'deleted_by_name' => $r->deletedBy?->name,
            'synced_to_cigam_at' => $r->synced_to_cigam_at?->toDateTimeString(),
            'helpdesk_ticket_id' => $r->helpdesk_ticket_id,
            'items' => $r->items->map(fn ($i) => [
                'id' => $i->id,
                'movement_id' => $i->movement_id,
                'barcode' => $i->barcode,
                'ref_size' => $i->ref_size,
                'product_name' => $i->product_name,
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'amount' => (float) $i->amount,
            ])->values(),
            'files' => $r->files->map(fn ($f) => [
                'id' => $f->id,
                'file_name' => $f->file_name,
                'file_path' => $f->file_path,
                'file_type' => $f->file_type,
                'file_size' => $f->file_size,
                'uploaded_by_name' => $f->uploadedBy?->name,
                'created_at' => $f->created_at?->toDateTimeString(),
            ])->values(),
            'status_history' => $r->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status?->value,
                'to_status' => $h->to_status?->value,
                'from_status_label' => $h->from_status?->label(),
                'to_status_label' => $h->to_status?->label(),
                'note' => $h->note,
                'changed_by_name' => $h->changedBy?->name,
                'created_at' => $h->created_at?->toDateTimeString(),
            ])->values(),
        ]);
    }
}
