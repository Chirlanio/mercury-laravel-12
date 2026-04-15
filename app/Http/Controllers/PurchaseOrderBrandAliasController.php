<?php

namespace App\Http\Controllers;

use App\Models\ProductBrand;
use App\Models\PurchaseOrderBrandAlias;
use App\Services\PurchaseOrderBrandAliasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de aliases de marca pra importação de planilhas.
 *
 * Resolve o problema de marcas da planilha v1 que não batem com
 * `product_brands` sincronizado do CIGAM (ex: "FACCINE" vs "MS FACCINE",
 * "HITS" vs "HITZ", ou marcas descontinuadas não mais no catálogo CIGAM).
 */
class PurchaseOrderBrandAliasController extends Controller
{
    public function __construct(
        private PurchaseOrderBrandAliasService $service,
    ) {}

    public function index(Request $request): Response
    {
        $query = PurchaseOrderBrandAlias::query()
            ->with('productBrand')
            ->orderBy('source_name');

        if ($request->filled('search')) {
            $query->where('source_name', 'like', '%' . mb_strtoupper($request->search) . '%');
        }

        if ($request->filled('status')) {
            if ($request->status === 'resolved') {
                $query->whereNotNull('product_brand_id');
            } elseif ($request->status === 'pending') {
                $query->whereNull('product_brand_id');
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $aliases = $query->paginate(30)->through(fn ($a) => [
            'id' => $a->id,
            'source_name' => $a->source_name,
            'product_brand_id' => $a->product_brand_id,
            'product_brand_name' => $a->productBrand?->name,
            'product_brand_cigam_code' => $a->productBrand?->cigam_code,
            'is_active' => $a->is_active,
            'auto_detected' => $a->auto_detected,
            'notes' => $a->notes,
            'created_at' => $a->created_at?->format('d/m/Y H:i'),
        ]);

        $stats = [
            'total' => PurchaseOrderBrandAlias::count(),
            'resolved' => PurchaseOrderBrandAlias::whereNotNull('product_brand_id')->count(),
            'pending' => PurchaseOrderBrandAlias::whereNull('product_brand_id')->count(),
            'inactive' => PurchaseOrderBrandAlias::where('is_active', false)->count(),
        ];

        return Inertia::render('PurchaseOrders/BrandAliases/Index', [
            'aliases' => $aliases,
            'filters' => $request->only(['search', 'status']),
            'stats' => $stats,
            'productBrands' => ProductBrand::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'cigam_code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_name' => 'required|string|max:100',
            'product_brand_id' => 'nullable|exists:product_brands,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $normalized = PurchaseOrderBrandAlias::normalizeName($data['source_name']);

        if (PurchaseOrderBrandAlias::where('source_name', $normalized)->exists()) {
            return redirect()->back()->withErrors([
                'source_name' => "Já existe alias para '{$normalized}'.",
            ])->withInput();
        }

        PurchaseOrderBrandAlias::create([
            'source_name' => $normalized,
            'product_brand_id' => $data['product_brand_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'auto_detected' => false,
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('purchase-orders.brand-aliases.index')
            ->with('success', "Alias '{$normalized}' criado.");
    }

    public function update(PurchaseOrderBrandAlias $brandAlias, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_brand_id' => 'nullable|exists:product_brands,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $brandAlias->update(array_merge($data, [
            'auto_detected' => false,
            'updated_by_user_id' => $request->user()->id,
        ]));

        return redirect()->route('purchase-orders.brand-aliases.index')
            ->with('success', "Alias '{$brandAlias->source_name}' atualizado.");
    }

    public function destroy(PurchaseOrderBrandAlias $brandAlias): RedirectResponse
    {
        $name = $brandAlias->source_name;
        $brandAlias->delete();

        return redirect()->route('purchase-orders.brand-aliases.index')
            ->with('success', "Alias '{$name}' removido.");
    }

    /**
     * Auto-detecta aliases pra nomes pendentes verificando se existe
     * "MS {nome}" em product_brands (convenção Meia Sola).
     */
    public function autoDetect(): RedirectResponse
    {
        $result = $this->service->autoDetectMsPrefix();

        $msg = "Auto-detect: {$result['detected']} alias(es) resolvido(s) via prefixo MS, {$result['skipped']} ainda pendentes";

        return redirect()->route('purchase-orders.brand-aliases.index')->with('success', $msg);
    }

    /**
     * Cria uma ProductBrand manualmente (cigam_code=MANUAL-...) e vincula
     * um alias apontando pra ela. Usado quando a marca histórica não
     * existe mais no CIGAM e precisa ser registrada pra preservar o
     * histórico.
     */
    public function createManualBrand(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_name' => 'required|string|max:100',
            'brand_name' => 'required|string|max:150',
        ]);

        $result = $this->service->createManualBrandWithAlias(
            sourceName: $data['source_name'],
            brandName: $data['brand_name'],
            userId: $request->user()->id,
        );

        return redirect()->route('purchase-orders.brand-aliases.index')
            ->with('success', "Marca '{$result['product_brand']->name}' criada manualmente ({$result['product_brand']->cigam_code}) com alias '{$result['alias']->source_name}'.");
    }
}
