<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\Product;
use App\Models\ProductLookupGroup;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Base controller for product lookup/auxiliary config modules.
 * Adds merge functionality to consolidate duplicate entries.
 */
abstract class ProductLookupConfigController extends ConfigController
{
    /**
     * The foreign key column on the products table that references this lookup.
     * e.g. 'brand_cigam_code', 'category_cigam_code'
     */
    abstract protected function productForeignKey(): string;

    /**
     * The lookup_type value for the product_lookup_groups table.
     * e.g. 'brands', 'categories', 'colors'
     */
    abstract protected function lookupType(): string;

    /**
     * Whether this lookup is referenced by product_variants instead of products.
     */
    protected function isVariantLookup(): bool
    {
        return false;
    }

    /**
     * Eager load group relationship.
     */
    protected function with(): array
    {
        return ['group'];
    }

    /**
     * Transform item to include group_name for display.
     */
    protected function transformItem($item): array
    {
        $data = $item->toArray();
        $data['group_name'] = $item->group?->name ?? '-';

        return $data;
    }

    /**
     * Pass groups and merge support flag to the frontend.
     */
    protected function additionalData(): array
    {
        $groups = ProductLookupGroup::active()
            ->byType($this->lookupType())
            ->orderBy('name')
            ->get(['id', 'name']);

        return array_merge(parent::additionalData(), [
            'supportsMerge' => true,
            'groups' => $groups,
        ]);
    }

    /**
     * Preview merge: show how many products will be affected.
     */
    public function mergePreview(Request $request)
    {
        $request->validate([
            'target_id' => 'required|integer',
            'source_ids' => 'required|array|min:1',
            'source_ids.*' => 'integer',
        ]);

        $modelClass = $this->modelClass();
        $target = $modelClass::findOrFail($request->target_id);
        $sources = $modelClass::whereIn('id', $request->source_ids)
            ->where('id', '!=', $target->id)
            ->get();

        if ($sources->isEmpty()) {
            return back()->with('error', 'Nenhum registro de origem válido selecionado.');
        }

        $sourceCodes = $sources->pluck('cigam_code')->toArray();
        $fk = $this->productForeignKey();

        if ($this->isVariantLookup()) {
            $affectedCount = ProductVariant::whereIn($fk, $sourceCodes)->count();
        } else {
            $affectedCount = Product::whereIn($fk, $sourceCodes)->count();
        }

        return response()->json([
            'target' => $target,
            'sources' => $sources,
            'affected_products' => $affectedCount,
        ]);
    }

    /**
     * Execute merge: reassign all product references and deactivate sources.
     */
    public function merge(Request $request)
    {
        $request->validate([
            'target_id' => 'required|integer',
            'source_ids' => 'required|array|min:1',
            'source_ids.*' => 'integer',
        ]);

        $modelClass = $this->modelClass();
        $target = $modelClass::findOrFail($request->target_id);
        $sources = $modelClass::whereIn('id', $request->source_ids)
            ->where('id', '!=', $target->id)
            ->get();

        if ($sources->isEmpty()) {
            return back()->with('error', 'Nenhum registro de origem válido selecionado.');
        }

        $sourceCodes = $sources->pluck('cigam_code')->toArray();
        $fk = $this->productForeignKey();

        DB::transaction(function () use ($fk, $sourceCodes, $target, $sources) {
            // Reassign product references to target
            if ($this->isVariantLookup()) {
                ProductVariant::whereIn($fk, $sourceCodes)
                    ->update([$fk => $target->cigam_code]);
            } else {
                Product::whereIn($fk, $sourceCodes)
                    ->update([$fk => $target->cigam_code]);
            }

            // Mark source records as merged (not just deactivated)
            // merged_into tells the sync service this code is an alias
            foreach ($sources as $source) {
                $source->update([
                    'is_active' => false,
                    'merged_into' => $target->cigam_code,
                ]);
            }
        });

        $count = $sources->count();

        return back()->with('success', "{$count} registro(s) mesclado(s) com sucesso em \"{$target->name}\".");
    }
}
