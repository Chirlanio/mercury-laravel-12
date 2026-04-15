<?php

namespace App\Http\Controllers;

use App\Models\ProductSize;
use App\Models\PurchaseOrderSizeMapping;
use App\Services\PurchaseOrderSizeMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD do de-para de tamanhos usados na importação de planilhas de
 * ordens de compra.
 *
 * Resolve o problema de tamanhos da planilha v1 que não batem com
 * `product_sizes` sincronizado do CIGAM (ex: "33/34", "35/36" — tamanhos
 * duplos sem equivalente direto).
 */
class PurchaseOrderSizeMappingController extends Controller
{
    public function __construct(
        private PurchaseOrderSizeMappingService $service,
    ) {}

    public function index(Request $request): Response
    {
        $query = PurchaseOrderSizeMapping::query()
            ->with('productSize')
            ->orderBy('source_label');

        if ($request->filled('search')) {
            $query->where('source_label', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            if ($request->status === 'resolved') {
                $query->whereNotNull('product_size_id');
            } elseif ($request->status === 'pending') {
                $query->whereNull('product_size_id');
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $mappings = $query->paginate(30)->through(fn ($m) => [
            'id' => $m->id,
            'source_label' => $m->source_label,
            'product_size_id' => $m->product_size_id,
            'product_size_name' => $m->productSize?->name,
            'is_active' => $m->is_active,
            'auto_detected' => $m->auto_detected,
            'notes' => $m->notes,
            'created_at' => $m->created_at?->format('d/m/Y H:i'),
        ]);

        $stats = [
            'total' => PurchaseOrderSizeMapping::count(),
            'resolved' => PurchaseOrderSizeMapping::whereNotNull('product_size_id')->count(),
            'pending' => PurchaseOrderSizeMapping::whereNull('product_size_id')->count(),
            'inactive' => PurchaseOrderSizeMapping::where('is_active', false)->count(),
        ];

        return Inertia::render('PurchaseOrders/SizeMappings/Index', [
            'mappings' => $mappings,
            'filters' => $request->only(['search', 'status']),
            'stats' => $stats,
            'productSizes' => ProductSize::active()
                ->orderBy('name')
                ->get(['id', 'name', 'cigam_code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_label' => 'required|string|max:50',
            'product_size_id' => 'nullable|exists:product_sizes,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $normalized = PurchaseOrderSizeMapping::normalizeLabel($data['source_label']);

        if (PurchaseOrderSizeMapping::where('source_label', $normalized)->exists()) {
            return redirect()->back()->withErrors([
                'source_label' => "Já existe mapeamento para o label '{$normalized}'.",
            ])->withInput();
        }

        PurchaseOrderSizeMapping::create([
            'source_label' => $normalized,
            'product_size_id' => $data['product_size_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'auto_detected' => false,
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('purchase-orders.size-mappings.index')
            ->with('success', "Mapeamento '{$normalized}' criado.");
    }

    public function update(PurchaseOrderSizeMapping $sizeMapping, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_size_id' => 'nullable|exists:product_sizes,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $sizeMapping->update(array_merge($data, [
            'auto_detected' => false, // qualquer edição manual desmarca
            'updated_by_user_id' => $request->user()->id,
        ]));

        return redirect()->route('purchase-orders.size-mappings.index')
            ->with('success', "Mapeamento '{$sizeMapping->source_label}' atualizado.");
    }

    public function destroy(PurchaseOrderSizeMapping $sizeMapping): RedirectResponse
    {
        $label = $sizeMapping->source_label;
        $sizeMapping->delete();

        return redirect()->route('purchase-orders.size-mappings.index')
            ->with('success', "Mapeamento '{$label}' removido.");
    }

    /**
     * Re-detecta automaticamente mappings baseado em product_sizes atuais.
     * Útil após sync do CIGAM trazer tamanhos novos.
     */
    public function autoDetect(): RedirectResponse
    {
        $result = $this->service->autoDetectFromProductSizes();

        $msg = "Auto-detect: {$result['created']} criados, {$result['updated']} atualizados, {$result['pending']} pendentes";

        return redirect()->route('purchase-orders.size-mappings.index')->with('success', $msg);
    }
}
