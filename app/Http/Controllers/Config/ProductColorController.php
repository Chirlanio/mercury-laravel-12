<?php

namespace App\Http\Controllers\Config;

use App\Models\Product;
use App\Models\ProductColor;

class ProductColorController extends ProductLookupConfigController
{
    protected function productForeignKey(): string
    {
        return 'color_cigam_code';
    }

    protected function lookupType(): string
    {
        return 'colors';
    }

    protected function modelClass(): string
    {
        return ProductColor::class;
    }

    protected function viewTitle(): string
    {
        return 'Cores de Produto';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie as cores do cadastro de produtos';
    }

    protected function routeName(): string
    {
        return 'config.product-colors';
    }

    protected function searchableFields(): array
    {
        return ['name', 'cigam_code'];
    }

    protected function sortableFields(): array
    {
        return ['id', 'cigam_code', 'name', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'cigam_code', 'label' => 'Código CIGAM', 'sortable' => true],
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'group_name', 'label' => 'Grupo', 'sortable' => false],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'cigam_code', 'label' => 'Código CIGAM', 'type' => 'text', 'required' => true, 'placeholder' => 'Código no CIGAM'],
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Nome da cor'],
            ['name' => 'group_id', 'label' => 'Grupo', 'type' => 'select', 'required' => false, 'placeholder' => 'Sem grupo', 'optionsKey' => 'groups'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'cigam_code' => 'required|string|max:20|unique:product_colors,cigam_code'.($isUpdate ? ','.$id : ''),
            'name' => 'required|string|max:255',
            'group_id' => 'nullable|integer|exists:product_lookup_groups,id',
            'is_active' => 'boolean',
        ];
    }

    protected function stats(): array
    {
        $model = $this->modelClass();

        return [
            'active' => $model::where('is_active', true)->count(),
            'inactive' => $model::where('is_active', false)->count(),
        ];
    }

    protected function canDelete($model): bool|string
    {
        $count = Product::where('color_cigam_code', $model->cigam_code)->count();
        if ($count > 0) {
            return "Esta cor está vinculada a {$count} produto(s) e não pode ser excluída.";
        }

        return true;
    }
}
