<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\ManagementReason;

class ManagementReasonController extends ConfigController
{
    protected function modelClass(): string
    {
        return ManagementReason::class;
    }

    protected function viewTitle(): string
    {
        return 'Razões Gerenciais';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie as razões gerenciais para rateio de custos';
    }

    protected function routeName(): string
    {
        return 'config.management-reasons';
    }

    protected function searchableFields(): array
    {
        return ['code', 'name'];
    }

    protected function defaultSort(): string
    {
        return 'code';
    }

    protected function sortableFields(): array
    {
        return ['id', 'code', 'name', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Código', 'sortable' => true],
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Código', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: RG001'],
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Marketing'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'code' => 'required|string|max:20|unique:management_reasons,code'.($isUpdate ? ','.$id : ''),
            'name' => 'required|string|max:255',
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
