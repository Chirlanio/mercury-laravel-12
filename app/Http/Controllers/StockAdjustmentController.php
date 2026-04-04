<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockAdjustmentStatusHistory;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $query = StockAdjustment::with([
            'store:id,code,name',
            'employee:id,name',
            'createdBy:id,name',
        ])->active()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('observation', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        $adjustments = $query->withCount('items')
            ->paginate(20)
            ->through(fn ($a) => [
                'id' => $a->id,
                'store' => $a->store ? ['id' => $a->store->id, 'name' => $a->store->display_name] : null,
                'employee' => $a->employee?->name,
                'status' => $a->status,
                'status_label' => $a->status_label,
                'observation' => $a->observation,
                'items_count' => $a->items_count,
                'created_by' => $a->createdBy?->name,
                'created_at' => $a->created_at->format('d/m/Y H:i'),
            ]);

        $stores = Store::active()->orderedByStore()->get(['id', 'code', 'name']);

        return Inertia::render('StockAdjustments/Index', [
            'adjustments' => $adjustments,
            'stores' => $stores,
            'filters' => $request->only(['search', 'status', 'store_id']),
            'statusOptions' => StockAdjustment::STATUS_LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'employee_id' => 'nullable|exists:employees,id',
            'observation' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.reference' => 'required|string|max:255',
            'items.*.size' => 'nullable|string|max:50',
            'items.*.is_adjustment' => 'boolean',
        ]);

        $adjustment = DB::transaction(function () use ($validated) {
            $adjustment = StockAdjustment::create([
                'store_id' => $validated['store_id'],
                'employee_id' => $validated['employee_id'] ?? null,
                'status' => 'pending',
                'observation' => $validated['observation'] ?? null,
                'created_by_user_id' => auth()->id(),
            ]);

            foreach ($validated['items'] as $index => $item) {
                $adjustment->items()->create([
                    'reference' => $item['reference'],
                    'size' => $item['size'] ?? null,
                    'is_adjustment' => $item['is_adjustment'] ?? true,
                    'sort_order' => $index,
                ]);
            }

            return $adjustment;
        });

        return redirect()->route('stock-adjustments.index')
            ->with('success', 'Ajuste de estoque criado com sucesso.');
    }

    public function show(StockAdjustment $stockAdjustment)
    {
        $stockAdjustment->load([
            'store:id,code,name',
            'employee:id,name',
            'createdBy:id,name',
            'items',
            'statusHistory.changedBy:id,name',
        ]);

        return response()->json($stockAdjustment);
    }

    public function update(Request $request, StockAdjustment $stockAdjustment)
    {
        if ($stockAdjustment->status !== 'pending') {
            return redirect()->back()->with('error', 'Apenas ajustes pendentes podem ser editados.');
        }

        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'observation' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.reference' => 'required|string|max:255',
            'items.*.size' => 'nullable|string|max:50',
            'items.*.is_adjustment' => 'boolean',
        ]);

        DB::transaction(function () use ($stockAdjustment, $validated) {
            $stockAdjustment->update([
                'employee_id' => $validated['employee_id'] ?? null,
                'observation' => $validated['observation'] ?? null,
            ]);

            $stockAdjustment->items()->delete();
            foreach ($validated['items'] as $index => $item) {
                $stockAdjustment->items()->create([
                    'reference' => $item['reference'],
                    'size' => $item['size'] ?? null,
                    'is_adjustment' => $item['is_adjustment'] ?? true,
                    'sort_order' => $index,
                ]);
            }
        });

        return redirect()->route('stock-adjustments.index')
            ->with('success', 'Ajuste de estoque atualizado com sucesso.');
    }

    public function destroy(StockAdjustment $stockAdjustment, Request $request)
    {
        if ($stockAdjustment->status !== 'pending') {
            return redirect()->back()->with('error', 'Apenas ajustes pendentes podem ser excluídos.');
        }

        $stockAdjustment->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => auth()->id(),
            'delete_reason' => $request->input('reason', 'Excluído pelo usuário'),
        ]);

        return redirect()->route('stock-adjustments.index')
            ->with('success', 'Ajuste de estoque excluído com sucesso.');
    }

    public function transition(Request $request, StockAdjustment $stockAdjustment)
    {
        $validated = $request->validate([
            'new_status' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $newStatus = $validated['new_status'];

        if (!$stockAdjustment->canTransitionTo($newStatus)) {
            return redirect()->back()->with('error', 'Transição de status inválida.');
        }

        if ($newStatus === 'pending' && $stockAdjustment->status === 'cancelled') {
            $user = auth()->user();
            if (!in_array($user->role->value, ['super_admin', 'admin'])) {
                return redirect()->back()->with('error', 'Apenas administradores podem reabrir ajustes cancelados.');
            }
        }

        DB::transaction(function () use ($stockAdjustment, $newStatus, $validated) {
            StockAdjustmentStatusHistory::create([
                'stock_adjustment_id' => $stockAdjustment->id,
                'old_status' => $stockAdjustment->status,
                'new_status' => $newStatus,
                'changed_by_user_id' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $stockAdjustment->update(['status' => $newStatus]);
        });

        $statusLabel = StockAdjustment::STATUS_LABELS[$newStatus] ?? $newStatus;

        return redirect()->route('stock-adjustments.index')
            ->with('success', "Status atualizado para: {$statusLabel}.");
    }
}
