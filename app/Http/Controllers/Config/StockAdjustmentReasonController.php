<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\StockAdjustmentReason;

class StockAdjustmentReasonController extends ConfigController
{
    protected function modelClass(): string
    {
        return StockAdjustmentReason::class;
    }

    protected function viewTitle(): string
    {
        return 'Motivos de Ajuste de Estoque';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os motivos (causas-raiz) utilizados nos ajustes de estoque';
    }

    protected function routeName(): string
    {
        return 'config.stock-adjustment-reasons';
    }

    protected function searchableFields(): array
    {
        return ['code', 'name', 'description'];
    }

    protected function defaultSort(): string
    {
        return 'sort_order';
    }

    protected function sortableFields(): array
    {
        return ['id', 'code', 'name', 'applies_to', 'sort_order', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Código', 'sortable' => true],
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'applies_to', 'label' => 'Aplica-se a', 'sortable' => true],
            ['key' => 'sort_order', 'label' => 'Ordem', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: AVARIA'],
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true],
            ['name' => 'description', 'label' => 'Descrição', 'type' => 'textarea'],
            [
                'name' => 'applies_to',
                'label' => 'Aplica-se a',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'both', 'label' => 'Inclusão e remoção'],
                    ['value' => 'increase', 'label' => 'Apenas inclusão (crédito)'],
                    ['value' => 'decrease', 'label' => 'Apenas remoção (débito)'],
                ],
                'defaultValue' => 'both',
            ],
            ['name' => 'sort_order', 'label' => 'Ordem de exibição', 'type' => 'number', 'defaultValue' => 0],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'code' => 'required|string|max:30|unique:stock_adjustment_reasons,code'.($isUpdate ? ','.$id : ''),
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'applies_to' => 'required|in:increase,decrease,both',
            'sort_order' => 'nullable|integer|min:0',
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
}
