<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Store;
use App\Models\Transfer;
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

        // Filter by store for non-admin users
        $user = $request->user();
        if (! $this->isAdminUser()) {
            $userStoreId = $this->resolveUserStoreId();
            if ($userStoreId) {
                $query->forStore($userStoreId);
            }
        } elseif ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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
            'origin_store_id' => $t->origin_store_id,
            'destination_store_id' => $t->destination_store_id,
            'invoice_number' => $t->invoice_number,
            'volumes_qty' => $t->volumes_qty,
            'products_qty' => $t->products_qty,
            'transfer_type' => $t->transfer_type,
            'type_label' => $t->type_label,
            'status' => $t->status,
            'status_label' => $t->status_label,
            'observations' => $t->observations,
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

        $userStoreId = $this->resolveUserStoreId();
        $isAdmin = $this->isAdminUser();

        return response()->json([
            'transfer' => [
                'id' => $transfer->id,
                'origin_store' => $transfer->originStore ? ['id' => $transfer->originStore->id, 'name' => $transfer->originStore->display_name] : null,
                'destination_store' => $transfer->destinationStore ? ['id' => $transfer->destinationStore->id, 'name' => $transfer->destinationStore->display_name] : null,
                'origin_store_id' => $transfer->origin_store_id,
                'destination_store_id' => $transfer->destination_store_id,
                'invoice_number' => $transfer->invoice_number,
                'volumes_qty' => $transfer->volumes_qty,
                'products_qty' => $transfer->products_qty,
                'transfer_type' => $transfer->transfer_type,
                'type_label' => $transfer->type_label,
                'status' => $transfer->status,
                'status_label' => $transfer->status_label,
                'observations' => $transfer->observations,
                'created_by' => $transfer->createdBy?->name,
                'driver_name' => $transfer->driver?->name,
                'confirmed_by' => $transfer->confirmedBy?->name,
                'receiver_name' => $transfer->receiver_name,
                'pickup_date' => $transfer->pickup_date?->format('d/m/Y'),
                'pickup_time' => $transfer->pickup_time,
                'delivery_date' => $transfer->delivery_date?->format('d/m/Y'),
                'delivery_time' => $transfer->delivery_time,
                'confirmed_at' => $transfer->confirmed_at?->format('d/m/Y H:i'),
                'created_at' => $transfer->created_at->format('d/m/Y H:i'),
                'updated_at' => $transfer->updated_at->format('d/m/Y H:i'),
            ],
            'can_edit' => $transfer->status === 'pending' && ($isAdmin || $transfer->origin_store_id === $userStoreId),
            'can_confirm_pickup' => $transfer->status === 'pending',
            'can_confirm_delivery' => $transfer->status === 'in_transit',
            'can_confirm_receipt' => $transfer->status === 'delivered' && ($isAdmin || $transfer->destination_store_id === $userStoreId),
            'can_cancel' => ! in_array($transfer->status, ['confirmed', 'cancelled']),
        ]);
    }

    public function update(Request $request, Transfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return redirect()->back()->with('error', 'Apenas transferências pendentes podem ser editadas.');
        }

        // Store-based permission check
        if (! $this->isAdminUser()) {
            $userStoreId = $this->resolveUserStoreId();
            if ($userStoreId && $transfer->origin_store_id !== $userStoreId) {
                return redirect()->back()->with('error', 'Você só pode editar transferências originadas na sua loja.');
            }
        }

        $validated = $request->validate([
            'destination_store_id' => 'required|exists:stores,id|different:origin_store_id',
            'invoice_number' => 'nullable|string|max:255',
            'volumes_qty' => 'nullable|integer|min:0',
            'products_qty' => 'nullable|integer|min:0',
            'transfer_type' => 'required|in:transfer,relocation,return,exchange',
            'observations' => 'nullable|string',
        ]);

        // Non-admin users have restricted fields
        if (! $this->isAdminUser()) {
            $validated = array_intersect_key($validated, array_flip([
                'destination_store_id', 'invoice_number', 'volumes_qty', 'products_qty', 'observations',
            ]));
        }

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

        // Only destination store (or admin) can confirm receipt
        if (! $this->isAdminUser()) {
            $userStoreId = $this->resolveUserStoreId();
            if ($userStoreId && $transfer->destination_store_id !== $userStoreId) {
                return redirect()->back()->with('error', 'Apenas a loja destino pode confirmar o recebimento.');
            }
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

    public function statistics(Request $request)
    {
        $query = Transfer::query();

        // Apply same store-based filtering as index
        if (! $this->isAdminUser()) {
            $userStoreId = $this->resolveUserStoreId();
            if ($userStoreId) {
                $query->forStore($userStoreId);
            }
        } elseif ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('transfer_type')) {
            $query->where('transfer_type', $request->transfer_type);
        }

        $total = (clone $query)->count();
        $totalVolumes = (int) (clone $query)->sum('volumes_qty');
        $totalProducts = (int) (clone $query)->sum('products_qty');
        $avgProducts = $total > 0 ? round($totalProducts / $total, 1) : 0;

        return response()->json([
            'total_transfers' => $total,
            'total_volumes' => $totalVolumes,
            'total_products' => $totalProducts,
            'avg_products' => $avgProducts,
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'in_transit' => (clone $query)->where('status', 'in_transit')->count(),
            'delivered' => (clone $query)->where('status', 'delivered')->count(),
            'confirmed' => (clone $query)->where('status', 'confirmed')->count(),
        ]);
    }

    private function resolveUserStoreId(): ?int
    {
        $user = auth()->user();

        // Try employee relationship first (if exists), then fall back to user's store_id
        if ($user->employee && $user->employee->store_id) {
            return Store::where('code', $user->employee->store_id)->value('id');
        }

        if ($user->store_id) {
            return Store::where('code', $user->store_id)->value('id');
        }

        return null;
    }

    private function isAdminUser(): bool
    {
        return in_array(auth()->user()->role, [Role::ADMIN, Role::SUPER_ADMIN]);
    }
}
