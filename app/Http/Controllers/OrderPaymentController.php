<?php

namespace App\Http\Controllers;

use App\Exports\OrderPaymentExport;
use App\Models\Bank;
use App\Models\Brand;
use App\Models\AccountingClass;
use App\Models\BudgetItem;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Models\ManagementReason;
use App\Models\Manager;
use App\Models\OrderPayment;
use App\Models\OrderPaymentInstallment;
use App\Models\PaymentType;
use App\Models\PixKeyType;
use App\Models\Sector;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\OrderPaymentAllocationService;
use App\Services\OrderPaymentDeleteService;
use App\Services\OrderPaymentTransitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class OrderPaymentController extends Controller
{
    public function __construct(
        private OrderPaymentTransitionService $transitionService,
        private OrderPaymentDeleteService $deleteService,
        private OrderPaymentAllocationService $allocationService,
    ) {}

    /**
     * List order payments (Kanban + Table view)
     */
    public function index(Request $request)
    {
        $query = OrderPayment::with([
            'store:id,code,name',
            'supplier:id,nome_fantasia',
            'manager:id,name',
            'createdBy:id,name',
        ])->active()->latest('date_payment');

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        }

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('number_nf', 'like', "%{$search}%")
                    ->orWhere('launch_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($q) => $q->where('nome_fantasia', 'like', "%{$search}%"));
            });
        }

        $payments = $query->paginate(20)->through(fn ($p) => $this->formatPayment($p));

        // Kanban summary — count and total per status
        $kanbanData = [];
        foreach (OrderPayment::STATUS_LABELS as $status => $label) {
            $statusQuery = OrderPayment::active()->forStatus($status);
            $kanbanData[$status] = [
                'label' => $label,
                'color' => OrderPayment::STATUS_COLORS[$status],
                'count' => $statusQuery->count(),
                'total' => round($statusQuery->sum('total_value'), 2),
            ];
        }

        // Kanban cards by status (for kanban view)
        $kanbanCards = [];
        foreach (OrderPayment::STATUS_LABELS as $status => $label) {
            $kanbanCards[$status] = OrderPayment::with(['supplier:id,nome_fantasia'])
                ->active()
                ->forStatus($status)
                ->orderBy('date_payment')
                ->limit(50)
                ->get()
                ->map(fn ($p) => $this->formatPayment($p));
        }

        // All select options (matching legacy v1 listAdd)
        $selects = [
            'areas' => Sector::where('is_active', true)->orderBy('id')->get(['id', 'sector_name as name']),
            'costCenters' => CostCenter::active()->orderBy('name')->get(['id', 'code', 'name', 'area_id']),
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::where('is_active', true)->orderBy('nome_fantasia')->get(['id', 'nome_fantasia', 'cnpj']),
            'stores' => Store::active()->orderedByStore()->get(['id', 'code', 'name']),
            'managers' => Manager::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'paymentTypes' => PaymentType::active()->orderBy('id')->get(['id', 'name']),
            'banks' => Bank::active()->orderBy('bank_name')->get(['id', 'bank_name']),
            'pixKeyTypes' => PixKeyType::active()->orderBy('id')->get(['id', 'name']),
            'managementReasons' => ManagementReason::active()->orderBy('name')->get(['id', 'code', 'name']),
        ];

        return Inertia::render('OrderPayments/Index', [
            'payments' => $payments,
            'selects' => $selects,
            'filters' => $request->only(['search', 'status', 'store_id']),
            'statusOptions' => OrderPayment::STATUS_LABELS,
            'kanbanData' => $kanbanData,
            'kanbanCards' => $kanbanCards,
        ]);
    }

    /**
     * Create order payment
     */
    public function store(Request $request)
    {
        $isBoleto = $request->input('payment_type') === 'Boleto';

        $validated = $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'area_id' => 'nullable|integer',
            'cost_center_id' => 'nullable|exists:cost_centers,id',
            'accounting_class_id' => 'nullable|exists:accounting_classes,id',
            'management_class_id' => 'nullable|exists:management_classes,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'manager_id' => 'nullable|exists:managers,id',
            'description' => 'required|string',
            'total_value' => 'required|numeric|min:0.01',
            'date_payment' => 'required|date',
            'competence_date' => 'nullable|date',
            'payment_type' => 'nullable|string',
            'advance' => 'boolean',
            'advance_amount' => 'nullable|numeric|min:0',
            'proof' => 'boolean',
            'number_nf' => 'nullable|string|max:50',
            'launch_number' => 'nullable|string|max:50',
            'observations' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'agency' => 'nullable|string|max:20',
            'checking_account' => 'nullable|string|max:25',
            'type_account' => 'nullable|string',
            'name_supplier' => 'nullable|string|max:100',
            'document_number_supplier' => 'nullable|string|max:20',
            'pix_key_type' => 'nullable|string',
            'pix_key' => 'nullable|string',
            // Boleto: mínimo 1 parcela com valor e data obrigatórios.
            // Outros tipos: parcelamento é opcional.
            'installments' => [$isBoleto ? 'required' : 'nullable', 'integer', 'min:' . ($isBoleto ? 1 : 0), 'max:12'],
            'installment_items' => [$isBoleto ? 'required' : 'nullable', 'array', $isBoleto ? 'min:1' : ''],
            'installment_items.*.value' => 'required_with:installment_items|numeric|min:0.01',
            'installment_items.*.date' => 'required_with:installment_items|date',
            'allocations' => 'nullable|array',
        ]);

        // Deriva cost_center_id da ManagementClass quando a UI usa a cascata
        // Área→Gerencial→CC (o CC não vem no payload, vem embutido na MC).
        $validated['cost_center_id'] = $this->resolveCostCenterId($validated);

        // Auto-resolve budget_item_id pelo trio (CC, AC, ano da competência)
        $validated['budget_item_id'] = $this->resolveBudgetItemId($validated);

        $order = DB::transaction(function () use ($validated) {
            $totalValue = $validated['total_value'];
            $advanceAmount = $validated['advance_amount'] ?? 0;

            $order = OrderPayment::create([
                ...$validated,
                'status' => ($validated['advance'] ?? false) ? OrderPayment::STATUS_WAITING : OrderPayment::STATUS_BACKLOG,
                'diff_payment_advance' => $totalValue - $advanceAmount,
                'has_allocation' => ! empty($validated['allocations']),
                'created_by_user_id' => auth()->id(),
            ]);

            // Create installments
            if (! empty($validated['installment_items'])) {
                foreach ($validated['installment_items'] as $index => $item) {
                    $order->installmentItems()->create([
                        'installment_number' => $index + 1,
                        'installment_value' => $item['value'],
                        'date_payment' => $item['date'],
                    ]);
                }
            }

            // Create allocations
            if (! empty($validated['allocations'])) {
                $validation = $this->allocationService->validate($totalValue, $validated['allocations']);
                if ($validation['valid']) {
                    $this->allocationService->create($order, $validated['allocations']);
                }
            }

            // Record initial status
            $this->transitionService->recordStatusHistory(
                $order->id, null, $order->status, auth()->id(), 'Criação'
            );

            return $order;
        });

        return redirect()->route('order-payments.index')
            ->with('success', 'Ordem de pagamento #'.$order->id.' criada com sucesso.');
    }

    /**
     * Show order payment details
     */
    public function show(OrderPayment $orderPayment)
    {
        $orderPayment->load([
            'store:id,code,name',
            'supplier:id,nome_fantasia',
            'manager:id,name',
            'createdBy:id,name',
            'updatedBy:id,name',
            'deletedBy:id,name',
            'installmentItems',
            'allocations',
            'statusHistory.changedBy:id,name',
        ]);

        return response()->json([
            'order' => $this->formatPaymentDetailed($orderPayment),
            'installments' => $orderPayment->installmentItems->map(fn ($i) => [
                'id' => $i->id,
                'number' => $i->installment_number,
                'value' => $i->installment_value,
                'formatted_value' => $i->formatted_value,
                'date_payment' => $i->date_payment->format('d/m/Y'),
                'is_paid' => $i->is_paid,
                'date_paid' => $i->date_paid?->format('d/m/Y'),
                'is_overdue' => $i->is_overdue,
            ]),
            'allocations' => $orderPayment->allocations->map(fn ($a) => [
                'id' => $a->id,
                'cost_center_id' => $a->cost_center_id,
                'store_id' => $a->store_id,
                'percentage' => $a->allocation_percentage,
                'value' => $a->allocation_value,
            ]),
            'status_history' => $orderPayment->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'old_status' => $h->old_status_label,
                'new_status' => $h->new_status_label,
                'changed_by' => $h->changedBy?->name,
                'notes' => $h->notes,
                'created_at' => $h->created_at->format('d/m/Y H:i'),
            ]),
        ]);
    }

    /**
     * Update order payment
     */
    public function update(Request $request, OrderPayment $orderPayment)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'total_value' => 'required|numeric|min:0.01',
            'date_payment' => 'required|date',
            'competence_date' => 'nullable|date',
            'payment_type' => 'nullable|string',
            'store_id' => 'nullable|exists:stores,id',
            'area_id' => 'nullable|integer',
            'cost_center_id' => 'nullable|exists:cost_centers,id',
            'accounting_class_id' => 'nullable|exists:accounting_classes,id',
            'management_class_id' => 'nullable|exists:management_classes,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'manager_id' => 'nullable|exists:managers,id',
            'advance' => 'boolean',
            'advance_amount' => 'nullable|numeric|min:0',
            'proof' => 'boolean',
            'number_nf' => 'nullable|string|max:50',
            'launch_number' => 'nullable|string|max:50',
            'observations' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'agency' => 'nullable|string|max:20',
            'checking_account' => 'nullable|string|max:25',
            'type_account' => 'nullable|string',
            'name_supplier' => 'nullable|string|max:100',
            'document_number_supplier' => 'nullable|string|max:20',
            'pix_key_type' => 'nullable|string',
            'pix_key' => 'nullable|string',
            'installment_items' => 'nullable|array',
            'allocations' => 'nullable|array',
        ]);

        // Deriva CC da MC quando a cascata Área→Gerencial preencher management_class_id.
        // O payload pode trazer cost_center_id explícito OU management_class_id — a MC
        // ganha prioridade porque é a fonte autoritária (CC é faceta da MC).
        $mergedForResolve = [
            'cost_center_id' => $validated['cost_center_id'] ?? $orderPayment->cost_center_id,
            'accounting_class_id' => $validated['accounting_class_id'] ?? $orderPayment->accounting_class_id,
            'management_class_id' => $validated['management_class_id'] ?? $orderPayment->management_class_id,
            'competence_date' => $validated['competence_date'] ?? $orderPayment->competence_date,
            'date_payment' => $validated['date_payment'],
        ];
        $validated['cost_center_id'] = $this->resolveCostCenterId($mergedForResolve);
        $mergedForResolve['cost_center_id'] = $validated['cost_center_id'];

        // Recalcula budget_item_id sempre que CC, AC ou competence_date mudar.
        $validated['budget_item_id'] = $this->resolveBudgetItemId($mergedForResolve);

        DB::transaction(function () use ($orderPayment, $validated) {
            $totalValue = $validated['total_value'];
            $advanceAmount = $validated['advance_amount'] ?? 0;

            $validated['diff_payment_advance'] = $totalValue - $advanceAmount;
            $validated['has_allocation'] = ! empty($validated['allocations']);
            $validated['updated_by_user_id'] = auth()->id();

            $orderPayment->update($validated);

            // Sync installments
            if (isset($validated['installment_items'])) {
                $orderPayment->installmentItems()->delete();
                foreach ($validated['installment_items'] as $index => $item) {
                    $orderPayment->installmentItems()->create([
                        'installment_number' => $index + 1,
                        'installment_value' => $item['value'],
                        'date_payment' => $item['date'],
                    ]);
                }
            }

            // Sync allocations
            if (isset($validated['allocations'])) {
                if (! empty($validated['allocations'])) {
                    $validation = $this->allocationService->validate($totalValue, $validated['allocations']);
                    if ($validation['valid']) {
                        $this->allocationService->update($orderPayment, $validated['allocations']);
                    }
                } else {
                    $orderPayment->allocations()->delete();
                }
            }

            // Recalculate allocations if total changed
            if ($orderPayment->has_allocation && $orderPayment->wasChanged('total_value')) {
                $this->allocationService->recalculate($orderPayment, $totalValue);
            }
        });

        return redirect()->route('order-payments.index')
            ->with('success', 'Ordem de pagamento atualizada com sucesso.');
    }

    /**
     * Status transition (Kanban move)
     */
    public function transition(Request $request, OrderPayment $orderPayment)
    {
        $validated = $request->validate([
            'new_status' => 'required|string|in:backlog,doing,waiting,done',
            'notes' => 'nullable|string',
            'number_nf' => 'nullable|string',
            'launch_number' => 'nullable|string',
            'date_paid' => 'nullable|date',
            'bank_name' => 'nullable|string',
            'agency' => 'nullable|string',
            'checking_account' => 'nullable|string',
            'pix_key_type' => 'nullable|string',
            'pix_key' => 'nullable|string',
        ]);

        $newStatus = $validated['new_status'];

        // Validate transition
        $validation = $this->transitionService->validateTransition(
            $orderPayment, $newStatus, $validated
        );

        if (! $validation['valid']) {
            return response()->json([
                'error' => true,
                'message' => implode(' ', $validation['errors']),
            ], 422);
        }

        // Extract additional fields to update on the order
        $additionalFields = array_filter([
            'number_nf' => $validated['number_nf'] ?? null,
            'launch_number' => $validated['launch_number'] ?? null,
            'date_paid' => $validated['date_paid'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'agency' => $validated['agency'] ?? null,
            'checking_account' => $validated['checking_account'] ?? null,
            'pix_key_type' => $validated['pix_key_type'] ?? null,
            'pix_key' => $validated['pix_key'] ?? null,
        ], fn ($v) => $v !== null);

        $this->transitionService->executeTransition(
            $orderPayment,
            $newStatus,
            $additionalFields,
            auth()->id(),
            $validated['notes'] ?? null
        );

        return response()->json([
            'error' => false,
            'message' => 'Status atualizado para: '.(OrderPayment::STATUS_LABELS[$newStatus] ?? $newStatus),
            'status' => $newStatus,
            'status_label' => OrderPayment::STATUS_LABELS[$newStatus] ?? $newStatus,
        ]);
    }

    /**
     * Bulk status transition
     */
    public function bulkTransition(Request $request)
    {
        $validated = $request->validate([
            'order_ids' => 'required|array|max:50',
            'order_ids.*' => 'integer|exists:order_payments,id',
            'new_status' => 'required|string|in:backlog,doing,waiting,done',
            'notes' => 'nullable|string',
        ]);

        $succeeded = [];
        $failed = [];

        foreach ($validated['order_ids'] as $orderId) {
            $order = OrderPayment::find($orderId);
            if (! $order || $order->is_deleted) {
                $failed[] = ['id' => $orderId, 'error' => 'Ordem não encontrada ou excluída.'];

                continue;
            }

            $validation = $this->transitionService->validateTransition(
                $order, $validated['new_status'], []
            );

            if (! $validation['valid']) {
                $failed[] = ['id' => $orderId, 'error' => implode(' ', $validation['errors'])];

                continue;
            }

            $this->transitionService->executeTransition(
                $order, $validated['new_status'], [], auth()->id(), $validated['notes'] ?? null
            );

            $succeeded[] = $orderId;
        }

        return response()->json([
            'succeeded' => $succeeded,
            'failed' => $failed,
            'message' => count($succeeded).' ordem(ns) movida(s) com sucesso.',
        ]);
    }

    /**
     * Delete order payment (3-level soft delete)
     */
    public function destroy(Request $request, OrderPayment $orderPayment)
    {
        $user = $request->user();
        $permission = $this->deleteService->canDelete($orderPayment, $user);

        if (! $permission['allowed']) {
            return response()->json(['error' => true, 'message' => $permission['message']], 403);
        }

        if ($permission['require_reason'] && empty($request->input('reason'))) {
            return response()->json(['error' => true, 'message' => 'Motivo da exclusão é obrigatório.'], 422);
        }

        if ($permission['require_confirmation'] && ! $request->boolean('confirmed')) {
            return response()->json([
                'error' => true,
                'message' => 'Confirmação necessária para excluir esta ordem.',
                'require_confirmation' => true,
            ], 422);
        }

        $this->deleteService->softDelete($orderPayment, $user, $request->input('reason'));

        return response()->json([
            'error' => false,
            'message' => 'Ordem de pagamento excluída com sucesso.',
        ]);
    }

    /**
     * Check delete permission (for frontend modal)
     */
    public function checkDeletePermission(OrderPayment $orderPayment)
    {
        return response()->json(
            $this->deleteService->canDelete($orderPayment, auth()->user())
        );
    }

    /**
     * Restore soft-deleted order
     */
    public function restore(OrderPayment $orderPayment)
    {
        $restored = $this->deleteService->restore($orderPayment, auth()->user());

        if (! $restored) {
            return response()->json(['error' => true, 'message' => 'Sem permissão para restaurar.'], 403);
        }

        return response()->json([
            'error' => false,
            'message' => 'Ordem de pagamento restaurada com sucesso.',
        ]);
    }

    /**
     * Save allocations for an order
     */
    public function saveAllocations(Request $request, OrderPayment $orderPayment)
    {
        $validated = $request->validate([
            'allocations' => 'required|array|min:1',
            'allocations.*.cost_center_id' => 'required|integer',
            'allocations.*.store_id' => 'nullable|integer',
            'allocations.*.percentage' => 'required|numeric|min:0.01|max:100',
            'allocations.*.value' => 'required|numeric|min:0.01',
        ]);

        $validation = $this->allocationService->validate(
            $orderPayment->total_value, $validated['allocations']
        );

        if (! $validation['valid']) {
            return response()->json([
                'error' => true,
                'errors' => $validation['errors'],
            ], 422);
        }

        $this->allocationService->update($orderPayment, $validated['allocations']);

        $orderPayment->update(['has_allocation' => true, 'updated_by_user_id' => auth()->id()]);

        return response()->json([
            'error' => false,
            'message' => 'Rateio salvo com sucesso.',
        ]);
    }

    /**
     * Mark installment as paid/unpaid
     */
    public function markInstallmentPaid(Request $request, OrderPaymentInstallment $installment)
    {
        $validated = $request->validate([
            'action' => 'required|in:mark,unmark',
            'date_paid' => 'required_if:action,mark|nullable|date',
        ]);

        if ($validated['action'] === 'mark') {
            $installment->update([
                'is_paid' => true,
                'date_paid' => $validated['date_paid'],
                'paid_by_user_id' => auth()->id(),
            ]);
        } else {
            $installment->update([
                'is_paid' => false,
                'date_paid' => null,
                'paid_by_user_id' => null,
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => $validated['action'] === 'mark' ? 'Parcela marcada como paga.' : 'Marcação removida.',
        ]);
    }

    /**
     * Statistics / KPI dashboard data
     */
    public function statistics(Request $request)
    {
        // Totals by status
        $byStatus = [];
        foreach (OrderPayment::STATUS_LABELS as $status => $label) {
            $query = OrderPayment::active()->forStatus($status);
            $byStatus[$status] = [
                'label' => $label,
                'count' => $query->count(),
                'total' => round($query->sum('total_value'), 2),
            ];
        }

        // Overdue
        $overdueCount = OrderPayment::overdue()->count();
        $overdueTotal = round(OrderPayment::overdue()->sum('total_value'), 2);

        // Monthly totals (last 6 months)
        $monthlyFlow = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyFlow[] = [
                'month' => $date->format('M/Y'),
                'created' => round(OrderPayment::active()
                    ->forMonth($date->month, $date->year)->sum('total_value'), 2),
                'paid' => round(OrderPayment::active()
                    ->where('status', 'done')
                    ->whereMonth('date_paid', $date->month)
                    ->whereYear('date_paid', $date->year)
                    ->sum('total_value'), 2),
            ];
        }

        // Installment summary
        $installmentSummary = [
            'overdue' => OrderPaymentInstallment::where('is_paid', false)
                ->where('date_payment', '<', today())->count(),
            'upcoming' => OrderPaymentInstallment::where('is_paid', false)
                ->whereBetween('date_payment', [today(), today()->addDays(30)])->count(),
            'paid' => OrderPaymentInstallment::where('is_paid', true)->count(),
        ];

        return response()->json([
            'by_status' => $byStatus,
            'overdue' => ['count' => $overdueCount, 'total' => $overdueTotal],
            'monthly_flow' => $monthlyFlow,
            'installments' => $installmentSummary,
        ]);
    }

    /**
     * Export order payments to Excel
     */
    public function export(Request $request)
    {
        $filters = $request->only(['search', 'status', 'store_id', 'date_from', 'date_to']);
        $filename = 'ordens_pagamento_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(new OrderPaymentExport($filters), $filename);
    }

    /**
     * Dashboard analytics data (by area, by supplier, by month)
     */
    public function dashboard(Request $request)
    {
        // By area (sector)
        $byArea = OrderPayment::active()
            ->selectRaw('area_id, count(*) as count, coalesce(sum(total_value), 0) as total')
            ->groupBy('area_id')
            ->get()
            ->map(function ($item) {
                $sector = $item->area_id ? Sector::find($item->area_id) : null;

                return [
                    'area' => $sector?->sector_name ?? 'Sem área',
                    'count' => $item->count,
                    'total' => round($item->total, 2),
                ];
            });

        // Top 10 suppliers by total value
        $bySupplier = OrderPayment::active()
            ->selectRaw('supplier_id, count(*) as count, coalesce(sum(total_value), 0) as total')
            ->whereNotNull('supplier_id')
            ->groupBy('supplier_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $supplier = Supplier::find($item->supplier_id);

                return [
                    'supplier' => $supplier?->nome_fantasia ?? 'Desconhecido',
                    'count' => $item->count,
                    'total' => round($item->total, 2),
                ];
            });

        // Monthly totals (last 12 months)
        $monthlyDetailed = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthQuery = OrderPayment::active()
                ->whereMonth('date_payment', $date->month)
                ->whereYear('date_payment', $date->year);

            $monthlyDetailed[] = [
                'month' => $date->format('M/Y'),
                'total' => round((clone $monthQuery)->sum('total_value'), 2),
                'count' => (clone $monthQuery)->count(),
                'paid' => round(OrderPayment::active()
                    ->where('status', 'done')
                    ->whereMonth('date_paid', $date->month)
                    ->whereYear('date_paid', $date->year)
                    ->sum('total_value'), 2),
            ];
        }

        return response()->json([
            'by_area' => $byArea,
            'by_supplier' => $bySupplier,
            'monthly_detailed' => $monthlyDetailed,
        ]);
    }

    // ==========================================
    // Private helpers
    // ==========================================

    private function formatPayment(OrderPayment $p): array
    {
        return [
            'id' => $p->id,
            'store' => $p->store ? ['id' => $p->store->id, 'name' => $p->store->display_name] : null,
            'supplier_name' => $p->supplier?->nome_fantasia ?? '-',
            'manager_name' => $p->manager?->name ?? '-',
            'description' => $p->description,
            'total_value' => $p->total_value,
            'formatted_total' => $p->formatted_total,
            'payment_type' => $p->payment_type,
            'status' => $p->status,
            'status_label' => $p->status_label,
            'status_color' => $p->status_color,
            'number_nf' => $p->number_nf,
            'launch_number' => $p->launch_number,
            'date_payment' => $p->date_payment?->format('d/m/Y'),
            'date_paid' => $p->date_paid?->format('d/m/Y'),
            'is_overdue' => $p->is_overdue,
            'is_deleted' => $p->is_deleted,
            'advance' => $p->advance,
            'advance_amount' => $p->advance_amount,
            'proof' => $p->proof,
            'payment_prepared' => $p->payment_prepared,
            'has_allocation' => $p->has_allocation,
            'installments' => $p->installments,
            'created_by' => $p->createdBy?->name,
            'created_at' => $p->created_at->format('d/m/Y H:i'),
        ];
    }

    private function formatPaymentDetailed(OrderPayment $p): array
    {
        return array_merge($this->formatPayment($p), [
            'area_id' => $p->area_id,
            'cost_center_id' => $p->cost_center_id,
            'brand_id' => $p->brand_id,
            'supplier_id' => $p->supplier_id,
            'store_id' => $p->store_id,
            'manager_id' => $p->manager_id,
            'bank_name' => $p->bank_name,
            'agency' => $p->agency,
            'checking_account' => $p->checking_account,
            'type_account' => $p->type_account,
            'name_supplier' => $p->name_supplier,
            'document_number_supplier' => $p->document_number_supplier,
            'pix_key_type' => $p->pix_key_type,
            'pix_key' => $p->pix_key,
            'advance_paid' => $p->advance_paid,
            'diff_payment_advance' => $p->diff_payment_advance,
            'observations' => $p->observations,
            'file_name' => $p->file_name,
            'updated_by' => $p->updatedBy?->name,
            'deleted_by' => $p->deletedBy?->name,
            'deleted_at' => $p->deleted_at?->format('d/m/Y H:i'),
            'delete_reason' => $p->delete_reason,
        ]);
    }

    /**
     * Resolve o cost_center_id quando a UI enviar a cascata
     * Área → Gerencial → CC — nesse caso o CC é derivado da ManagementClass
     * selecionada (já pré-vinculada no seed de management_classes).
     *
     * Se o payload já trouxer cost_center_id explícito e não houver
     * management_class_id, mantém o que veio. Se houver conflito (cost_center_id
     * explícito ≠ cost_center_id da MC), a MC ganha — é a fonte autoritária
     * do vínculo conforme a taxonomia gerencial.
     */
    protected function resolveCostCenterId(array $attrs): ?int
    {
        $mcId = $attrs['management_class_id'] ?? null;
        $ccExplicit = $attrs['cost_center_id'] ?? null;

        if ($mcId) {
            $mcCc = ManagementClass::whereKey($mcId)->value('cost_center_id');
            if ($mcCc) {
                return $mcCc;
            }
        }

        return $ccExplicit;
    }

    /**
     * Resolve o budget_item_id a partir do trio (cost_center_id,
     * accounting_class_id, ano da competência). Retorna null se falta algum
     * dos dois códigos, ou se não há budget ativo que case com o par no ano.
     *
     * O ano vem de competence_date quando informado; fallback para
     * date_payment. Esse fallback alinha o regime contábil natural — a OP
     * que não tem competência explícita cai no orçamento do ano do caixa.
     */
    protected function resolveBudgetItemId(array $attrs): ?int
    {
        $ccId = $attrs['cost_center_id'] ?? null;
        $acId = $attrs['accounting_class_id'] ?? null;

        if (! $ccId || ! $acId) {
            return null;
        }

        $dateSource = $attrs['competence_date'] ?? $attrs['date_payment'] ?? null;
        if (! $dateSource) {
            return null;
        }
        $year = (int) date('Y', is_string($dateSource) ? strtotime($dateSource) : $dateSource->timestamp);

        return BudgetItem::query()
            ->where('cost_center_id', $ccId)
            ->where('accounting_class_id', $acId)
            ->whereHas('upload', fn ($q) => $q->where('year', $year)->where('is_active', true))
            ->value('id');
    }
}
