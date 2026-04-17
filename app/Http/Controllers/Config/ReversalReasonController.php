<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\ReversalReason;

class ReversalReasonController extends ConfigController
{
    protected function modelClass(): string
    {
        return ReversalReason::class;
    }

    protected function viewTitle(): string
    {
        return 'Motivos de Estorno';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os motivos utilizados nas solicitações de estorno';
    }

    protected function routeName(): string
    {
        return 'config.reversal-reasons';
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
        return ['id', 'code', 'name', 'sort_order', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Código', 'sortable' => true],
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'sort_order', 'label' => 'Ordem', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: FURO_ESTOQUE'],
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true],
            ['name' => 'description', 'label' => 'Descrição', 'type' => 'textarea'],
            ['name' => 'sort_order', 'label' => 'Ordem de exibição', 'type' => 'number', 'defaultValue' => 0],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'code' => 'required|string|max:30|unique:reversal_reasons,code'.($isUpdate ? ','.$id : ''),
            'name' => 'required|string|max:150',
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
