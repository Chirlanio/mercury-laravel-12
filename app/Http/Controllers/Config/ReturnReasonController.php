<?php

namespace App\Http\Controllers\Config;

use App\Enums\ReturnReasonCategory;
use App\Http\Controllers\ConfigController;
use App\Models\ReturnReason;

class ReturnReasonController extends ConfigController
{
    protected function modelClass(): string
    {
        return ReturnReason::class;
    }

    protected function viewTitle(): string
    {
        return 'Motivos de Devolução';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os motivos utilizados nas solicitações de devolução/troca do e-commerce';
    }

    protected function routeName(): string
    {
        return 'config.return-reasons';
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
        return ['id', 'code', 'name', 'category', 'sort_order', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Código', 'sortable' => true],
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'category', 'label' => 'Categoria', 'sortable' => true],
            ['key' => 'sort_order', 'label' => 'Ordem', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ];
    }

    protected function formFields(): array
    {
        $categoryOptions = collect(ReturnReasonCategory::cases())
            ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])
            ->values()
            ->all();

        return [
            ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: DEFEITO_COSTURA'],
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true],
            [
                'name' => 'category',
                'label' => 'Categoria',
                'type' => 'select',
                'required' => true,
                'options' => $categoryOptions,
            ],
            ['name' => 'description', 'label' => 'Descrição', 'type' => 'textarea'],
            ['name' => 'sort_order', 'label' => 'Ordem de exibição', 'type' => 'number', 'defaultValue' => 0],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        $categoryValues = implode(',', array_column(ReturnReasonCategory::cases(), 'value'));

        return [
            'code' => 'required|string|max:30|unique:return_reasons,code'.($isUpdate ? ','.$id : ''),
            'name' => 'required|string|max:150',
            'category' => 'required|in:'.$categoryValues,
            'description' => 'nullable|string|max:1000',
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
