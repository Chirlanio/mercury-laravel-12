<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\ProductLookupGroup;
use Illuminate\Http\Request;

/**
 * Base controller for product lookup/auxiliary config modules.
 * Adds group management and bulk group assignment.
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
     * Pass groups to the frontend.
     */
    protected function additionalData(): array
    {
        $groups = ProductLookupGroup::active()
            ->byType($this->lookupType())
            ->orderBy('name')
            ->get(['id', 'name']);

        return array_merge(parent::additionalData(), [
            'supportsGroups' => true,
            'groups' => $groups,
        ]);
    }

    /**
     * Bulk assign a group to multiple records.
     */
    public function assignGroup(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'group_id' => 'nullable|integer|exists:product_lookup_groups,id',
        ]);

        $modelClass = $this->modelClass();
        $count = $modelClass::whereIn('id', $request->ids)
            ->update(['group_id' => $request->group_id]);

        if ($request->group_id) {
            $group = ProductLookupGroup::find($request->group_id);

            return back()->with('success', "{$count} registro(s) atribuído(s) ao grupo \"{$group->name}\".");
        }

        return back()->with('success', "{$count} registro(s) removido(s) do grupo.");
    }
}
