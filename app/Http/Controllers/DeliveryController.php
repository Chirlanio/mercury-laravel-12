<?php

namespace App\Http\Controllers;

use App\Jobs\GeocodeDeliveryJob;
use App\Models\Delivery;
use App\Models\DeliveryReturnReason;
use App\Models\Employee;
use App\Models\Neighborhood;
use App\Models\PaymentType;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $query = Delivery::with(['store', 'createdBy'])
            ->active()
            ->latest();

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }
        if ($request->filled('status')) {
            $query->forStatus($request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%");
            });
        }

        $deliveries = $query->paginate(20)->through(fn ($d) => [
            'id' => $d->id,
            'client_name' => $d->client_name,
            'address' => $d->address,
            'neighborhood' => $d->neighborhood,
            'contact_phone' => $d->contact_phone,
            'store_name' => $d->store?->name,
            'store_id' => $d->store_id,
            'sale_value' => $d->sale_value,
            'status' => $d->status,
            'status_label' => $d->status_label,
            'status_color' => $d->status_color,
            'needs_card_machine' => $d->needs_card_machine,
            'is_exchange' => $d->is_exchange,
            'is_gift' => $d->is_gift,
            'created_at' => $d->created_at->format('d/m/Y H:i'),
        ]);

        $statusCounts = [];
        foreach (array_keys(Delivery::STATUS_LABELS) as $status) {
            $statusCounts[$status] = Delivery::active()->forStatus($status)->count();
        }

        return Inertia::render('Deliveries/Index', [
            'deliveries' => $deliveries,
            'filters' => $request->only(['search', 'status', 'store_id', 'date_from', 'date_to']),
            'statusOptions' => Delivery::STATUS_LABELS,
            'statusCounts' => $statusCounts,
            'stores' => Store::orderBy('name')->get(['id', 'name', 'code']),
            'paymentTypes' => PaymentType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'neighborhoods' => Neighborhood::active()->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::whereNotNull('store_id')
                ->where('status_id', 2)
                ->orderBy('name')
                ->get(['id', 'name', 'short_name', 'store_id'])
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => $e->short_name ?: $e->name,
                    'store_id' => $e->store_id,
                ]),
            'returnReasons' => Schema::hasTable('delivery_return_reasons')
                ? DeliveryReturnReason::active()->orderBy('name')->get(['id', 'code', 'name'])
                : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|string|exists:stores,code',
            'employee_id' => 'nullable|exists:employees,id',
            'client_name' => 'required|string|max:255',
            'invoice_number' => 'required|string|max:50',
            'address' => 'nullable|string|max:500',
            'neighborhood' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'sale_value' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'installments' => 'required|integer|min:1',
            'products_qty' => 'required|integer|min:1',
            'exit_point' => 'nullable|string|max:50',
            'needs_card_machine' => 'boolean',
            'is_exchange' => 'boolean',
            'is_gift' => 'boolean',
            'observations' => 'nullable|string|max:2000',
        ]);

        $validated['status'] = Delivery::STATUS_REQUESTED;
        $validated['created_by_user_id'] = auth()->id();

        $delivery = Delivery::create($validated);

        GeocodeDeliveryJob::dispatch($delivery->id);

        return redirect()->route('deliveries.index')
            ->with('success', 'Entrega criada com sucesso.');
    }

    public function show(Delivery $delivery)
    {
        $delivery->load(['store', 'employee', 'createdBy', 'routeItems.route.driver']);

        return response()->json([
            'delivery' => [
                'id' => $delivery->id,
                'client_name' => $delivery->client_name,
                'invoice_number' => $delivery->invoice_number,
                'address' => $delivery->address,
                'neighborhood' => $delivery->neighborhood,
                'contact_phone' => $delivery->contact_phone,
                'store_name' => $delivery->store?->name,
                'store_id' => $delivery->store_id,
                'employee_id' => $delivery->employee_id,
                'employee_name' => $delivery->employee?->name,
                'sale_value' => $delivery->sale_value,
                'payment_method' => $delivery->payment_method,
                'installments' => $delivery->installments,
                'products_qty' => $delivery->products_qty,
                'exit_point' => $delivery->exit_point,
                'needs_card_machine' => $delivery->needs_card_machine,
                'is_exchange' => $delivery->is_exchange,
                'is_gift' => $delivery->is_gift,
                'status' => $delivery->status,
                'status_label' => $delivery->status_label,
                'status_color' => $delivery->status_color,
                'observations' => $delivery->observations,
                'next_status' => Delivery::NEXT_STATUS[$delivery->status] ?? null,
                'next_status_label' => Delivery::NEXT_STATUS_LABELS[$delivery->status] ?? null,
                'can_cancel' => ! $delivery->isTerminal() && $delivery->status !== Delivery::STATUS_IN_ROUTE,
                'route' => $delivery->routeItems->first()?->route ? [
                    'route_number' => $delivery->routeItems->first()->route->route_number,
                    'driver_name' => $delivery->routeItems->first()->route->driver?->name,
                ] : null,
                'created_by' => $delivery->createdBy?->name,
                'created_at' => $delivery->created_at->format('d/m/Y H:i'),
            ],
        ]);
    }

    public function update(Request $request, Delivery $delivery)
    {
        if ($delivery->isTerminal()) {
            return back()->with('error', 'Não é possível editar uma entrega finalizada.');
        }

        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'client_name' => 'required|string|max:255',
            'invoice_number' => 'required|string|max:50',
            'address' => 'nullable|string|max:500',
            'neighborhood' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'sale_value' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'installments' => 'required|integer|min:1',
            'products_qty' => 'required|integer|min:1',
            'exit_point' => 'nullable|string|max:50',
            'needs_card_machine' => 'boolean',
            'is_exchange' => 'boolean',
            'is_gift' => 'boolean',
            'observations' => 'nullable|string|max:2000',
        ]);

        $validated['updated_by_user_id'] = auth()->id();

        $addressChanged = $delivery->address !== ($validated['address'] ?? $delivery->address)
            || $delivery->neighborhood !== ($validated['neighborhood'] ?? $delivery->neighborhood);

        $delivery->update($validated);

        if ($addressChanged) {
            $delivery->update(['geocoded_at' => null]);
            GeocodeDeliveryJob::dispatch($delivery->id);
        }

        return redirect()->route('deliveries.index')
            ->with('success', 'Entrega atualizada com sucesso.');
    }

    public function destroy(Delivery $delivery)
    {
        $delivery->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => auth()->id(),
        ]);

        return redirect()->route('deliveries.index')
            ->with('success', 'Entrega excluída com sucesso.');
    }

    public function updateStatus(Request $request, Delivery $delivery)
    {
        $request->validate([
            'status' => 'required|string|in:'.implode(',', array_keys(Delivery::STATUS_LABELS)),
            'return_reason_id' => 'nullable|integer',
            'received_by' => 'nullable|string|max:255',
        ]);

        $newStatus = $request->status;

        if (! $delivery->canTransitionTo($newStatus)) {
            return response()->json([
                'error' => "Transição de '{$delivery->status_label}' para '".Delivery::STATUS_LABELS[$newStatus]."' não permitida.",
            ], 422);
        }

        $oldStatus = $delivery->status;
        $oldStatusLabel = $delivery->status_label;

        $updateData = [
            'status' => $newStatus,
            'updated_by_user_id' => auth()->id(),
        ];

        if (Schema::hasColumn('deliveries', 'return_reason_id')) {
            $updateData['return_reason_id'] = $newStatus === Delivery::STATUS_RETURNED ? $request->return_reason_id : null;
        }

        $delivery->update($updateData);

        $delivery->logCustomAction('status_transition',
            "Status alterado de '{$oldStatusLabel}' para '".Delivery::STATUS_LABELS[$newStatus]."'",
            ['old_status' => $oldStatus, 'new_status' => $newStatus]
        );

        return response()->json([
            'message' => 'Status atualizado para '.Delivery::STATUS_LABELS[$newStatus].'.',
        ]);
    }

    public function statistics(Request $request)
    {
        $query = Delivery::active();

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        $total = (clone $query)->count();

        $stats = [
            'total' => $total,
            'by_status' => [],
        ];

        foreach (Delivery::STATUS_LABELS as $status => $label) {
            $stats['by_status'][$status] = [
                'count' => (clone $query)->forStatus($status)->count(),
                'label' => $label,
                'color' => Delivery::STATUS_COLORS[$status],
            ];
        }

        return response()->json($stats);
    }
}
