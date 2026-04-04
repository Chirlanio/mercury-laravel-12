<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        $query = Transfer::with([
            'originStore:id,code,name',
            'destinationStore:id,code,name',
            'createdBy:id,name',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('transfer_type')) {
            $query->where('transfer_type', $request->transfer_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('observations', 'like', "%{$search}%")
                    ->orWhere('receiver_name', 'like', "%{$search}%");
            });
        }

        $transfers = $query->paginate(20)->through(fn ($t) => [
            'id' => $t->id,
            'origin_store' => $t->originStore ? ['id' => $t->originStore->id, 'name' => $t->originStore->display_name] : null,
            'destination_store' => $t->destinationStore ? ['id' => $t->destinationStore->id, 'name' => $t->destinationStore->display_name] : null,
            'invoice_number' => $t->invoice_number,
            'volumes_qty' => $t->volumes_qty,
            'products_qty' => $t->products_qty,
            'transfer_type' => $t->transfer_type,
            'type_label' => $t->type_label,
            'status' => $t->status,
            'status_label' => $t->status_label,
            'created_by' => $t->createdBy?->name,
            'created_at' => $t->created_at->format('d/m/Y H:i'),
        ]);

        $stores = Store::active()->orderedByStore()->get(['id', 'code', 'name']);

        return Inertia::render('Transfers/Index', [
            'transfers' => $transfers,
            'stores' => $stores,
            'filters' => $request->only(['search', 'status', 'store_id', 'transfer_type']),
            'statusOptions' => Transfer::STATUS_LABELS,
            'typeOptions' => Transfer::TYPE_LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'origin_store_id' => 'required|exists:stores,id',
            'destination_store_id' => 'required|exists:stores,id|different:origin_store_id',
            'invoice_number' => 'nullable|string|max:255',
            'volumes_qty' => 'nullable|integer|min:0',
            'products_qty' => 'nullable|integer|min:0',
            'transfer_type' => 'required|in:transfer,relocation,return,exchange',
            'observations' => 'nullable|string',
        ]);

        $validated['status'] = 'pending';
        $validated['created_by_user_id'] = auth()->id();

        Transfer::create($validated);

        return redirect()->route('transfers.index')
            ->with('success', 'Transferência criada com sucesso.');
    }

    public function show(Transfer $transfer)
    {
        $transfer->load([
            'originStore:id,code,name',
            'destinationStore:id,code,name',
            'createdBy:id,name',
            'driver:id,name',
            'confirmedBy:id,name',
        ]);

        return response()->json($transfer);
    }

    public function update(Request $request, Transfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return redirect()->back()->with('error', 'Apenas transferências pendentes podem ser editadas.');
        }

        $validated = $request->validate([
            'destination_store_id' => 'required|exists:stores,id|different:origin_store_id',
            'invoice_number' => 'nullable|string|max:255',
            'volumes_qty' => 'nullable|integer|min:0',
            'products_qty' => 'nullable|integer|min:0',
            'transfer_type' => 'required|in:transfer,relocation,return,exchange',
            'observations' => 'nullable|string',
        ]);

        $transfer->update($validated);

        return redirect()->route('transfers.index')
            ->with('success', 'Transferência atualizada com sucesso.');
    }

    public function destroy(Transfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return redirect()->back()->with('error', 'Apenas transferências pendentes podem ser excluídas.');
        }

        $transfer->delete();

        return redirect()->route('transfers.index')
            ->with('success', 'Transferência excluída com sucesso.');
    }

    public function confirmPickup(Request $request, Transfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return redirect()->back()->with('error', 'Transferência não está pendente.');
        }

        $transfer->update([
            'status' => 'in_transit',
            'pickup_date' => now()->toDateString(),
            'pickup_time' => now()->toTimeString(),
            'driver_user_id' => auth()->id(),
        ]);

        return redirect()->route('transfers.index')
            ->with('success', 'Coleta confirmada. Transferência em rota.');
    }

    public function confirmDelivery(Request $request, Transfer $transfer)
    {
        if ($transfer->status !== 'in_transit') {
            return redirect()->back()->with('error', 'Transferência não está em rota.');
        }

        $validated = $request->validate([
            'receiver_name' => 'required|string|max:255',
        ]);

        $transfer->update([
            'status' => 'delivered',
            'delivery_date' => now()->toDateString(),
            'delivery_time' => now()->toTimeString(),
            'receiver_name' => $validated['receiver_name'],
        ]);

        return redirect()->route('transfers.index')
            ->with('success', 'Entrega confirmada.');
    }

    public function confirmReceipt(Transfer $transfer)
    {
        if ($transfer->status !== 'delivered') {
            return redirect()->back()->with('error', 'Transferência não foi entregue.');
        }

        $transfer->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_by_user_id' => auth()->id(),
        ]);

        return redirect()->route('transfers.index')
            ->with('success', 'Recebimento confirmado.');
    }

    public function cancel(Transfer $transfer)
    {
        if (in_array($transfer->status, ['confirmed', 'cancelled'])) {
            return redirect()->back()->with('error', 'Esta transferência não pode ser cancelada.');
        }

        $transfer->update(['status' => 'cancelled']);

        return redirect()->route('transfers.index')
            ->with('success', 'Transferência cancelada.');
    }
}
