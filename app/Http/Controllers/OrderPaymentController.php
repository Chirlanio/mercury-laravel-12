<?php

namespace App\Http\Controllers;

use App\Models\OrderPayment;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = OrderPayment::with([
            'store:id,code,name',
            'requestedBy:id,name',
        ])->latest();

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        }

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('supplier_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('number_nf', 'like', "%{$search}%");
            });
        }

        $payments = $query->paginate(20)->through(fn ($p) => [
            'id' => $p->id,
            'store' => $p->store ? ['id' => $p->store->id, 'name' => $p->store->display_name] : null,
            'supplier_name' => $p->supplier_name,
            'description' => $p->description,
            'total_value' => $p->total_value,
            'formatted_total' => $p->formatted_total,
            'payment_type' => $p->payment_type,
            'status' => $p->status,
            'status_label' => $p->status_label,
            'number_nf' => $p->number_nf,
            'due_date' => $p->due_date?->format('d/m/Y'),
            'date_paid' => $p->date_paid?->format('d/m/Y'),
            'is_overdue' => $p->due_date && $p->due_date->isPast() && $p->status !== 'done',
            'requested_by' => $p->requestedBy?->name,
            'created_at' => $p->created_at->format('d/m/Y H:i'),
        ]);

        // Kanban data
        $kanbanData = [];
        foreach (OrderPayment::STATUS_LABELS as $status => $label) {
            $kanbanData[$status] = [
                'label' => $label,
                'count' => OrderPayment::forStatus($status)->count(),
            ];
        }

        $stores = Store::active()->orderedByStore()->get(['id', 'code', 'name']);

        return Inertia::render('OrderPayments/Index', [
            'payments' => $payments,
            'stores' => $stores,
            'filters' => $request->only(['search', 'status', 'store_id']),
            'statusOptions' => OrderPayment::STATUS_LABELS,
            'kanbanData' => $kanbanData,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'supplier_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'total_value' => 'required|numeric|min:0.01',
            'payment_type' => 'nullable|string|max:50',
            'due_date' => 'nullable|date',
            'installments' => 'integer|min:1',
        ]);

        $validated['status'] = 'backlog';
        $validated['requested_by_user_id'] = auth()->id();

        OrderPayment::create($validated);

        return redirect()->route('order-payments.index')
            ->with('success', 'Ordem de pagamento criada com sucesso.');
    }

    public function show(OrderPayment $orderPayment)
    {
        $orderPayment->load([
            'store:id,code,name',
            'requestedBy:id,name',
            'approvedBy:id,name',
        ]);

        return response()->json($orderPayment);
    }

    public function update(Request $request, OrderPayment $orderPayment)
    {
        $validated = $request->validate([
            'supplier_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'total_value' => 'required|numeric|min:0.01',
            'payment_type' => 'nullable|string|max:50',
            'due_date' => 'nullable|date',
            'number_nf' => 'nullable|string|max:255',
            'launch_number' => 'nullable|string|max:255',
            'installments' => 'integer|min:1',
            'bank_name' => 'nullable|string|max:255',
            'agency' => 'nullable|string|max:50',
            'checking_account' => 'nullable|string|max:50',
            'pix_key_type' => 'nullable|string|max:50',
            'pix_key' => 'nullable|string|max:255',
        ]);

        $orderPayment->update($validated);

        return redirect()->route('order-payments.index')
            ->with('success', 'Ordem de pagamento atualizada com sucesso.');
    }

    public function destroy(OrderPayment $orderPayment)
    {
        $orderPayment->delete();

        return redirect()->route('order-payments.index')
            ->with('success', 'Ordem de pagamento excluída com sucesso.');
    }

    public function updateStatus(Request $request, OrderPayment $orderPayment)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:backlog,doing,waiting,done',
            'number_nf' => 'nullable|string',
            'launch_number' => 'nullable|string',
            'date_paid' => 'nullable|date',
            'bank_name' => 'nullable|string',
            'agency' => 'nullable|string',
            'checking_account' => 'nullable|string',
            'pix_key_type' => 'nullable|string',
            'pix_key' => 'nullable|string',
        ]);

        $newStatus = $validated['status'];

        if (!$orderPayment->canTransitionTo($newStatus)) {
            return response()->json(['error' => 'Transição de status inválida.'], 422);
        }

        // Validate required fields per transition
        $currentStatus = $orderPayment->status;
        if ($currentStatus === 'backlog' && $newStatus === 'doing') {
            $request->validate([
                'number_nf' => 'required|string',
                'launch_number' => 'required|string',
            ]);
        }

        if ($currentStatus === 'doing' && $newStatus === 'waiting') {
            $request->validate([
                'launch_number' => 'required|string',
            ]);
        }

        if ($newStatus === 'done') {
            $request->validate([
                'date_paid' => 'required|date',
            ]);
        }

        $updateData = ['status' => $newStatus];

        if (isset($validated['number_nf'])) $updateData['number_nf'] = $validated['number_nf'];
        if (isset($validated['launch_number'])) $updateData['launch_number'] = $validated['launch_number'];
        if (isset($validated['date_paid'])) $updateData['date_paid'] = $validated['date_paid'];
        if (isset($validated['bank_name'])) $updateData['bank_name'] = $validated['bank_name'];
        if (isset($validated['agency'])) $updateData['agency'] = $validated['agency'];
        if (isset($validated['checking_account'])) $updateData['checking_account'] = $validated['checking_account'];
        if (isset($validated['pix_key_type'])) $updateData['pix_key_type'] = $validated['pix_key_type'];
        if (isset($validated['pix_key'])) $updateData['pix_key'] = $validated['pix_key'];

        if ($newStatus === 'done') {
            $updateData['approved_by_user_id'] = auth()->id();
        }

        $orderPayment->update($updateData);

        return response()->json([
            'message' => 'Status atualizado com sucesso.',
            'status' => $newStatus,
            'status_label' => OrderPayment::STATUS_LABELS[$newStatus],
        ]);
    }
}
