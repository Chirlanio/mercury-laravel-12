<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\CostCenter;
use App\Models\Manager;

class CostCenterController extends ConfigController
{
    protected function modelClass(): string
    {
        return CostCenter::class;
    }

    protected function viewTitle(): string
    {
        return 'Centros de Custo';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os centros de custo da empresa';
    }

    protected function routeName(): string
    {
        return 'config.cost-centers';
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
            ['key' => 'code', 'label' => 'Codigo', 'sortable' => true],
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'manager_name', 'label' => 'Gestor', 'sortable' => false],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Codigo', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: CC001'],
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Operacoes Comerciais'],
            ['name' => 'manager_id', 'label' => 'Gestor', 'type' => 'select', 'required' => false, 'optionsKey' => 'managers'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'code' => 'required|string|max:20|unique:cost_centers,code' . ($isUpdate ? ',' . $id : ''),
            'name' => 'required|string|max:255',
            'manager_id' => 'nullable|exists:managers,id',
            'is_active' => 'boolean',
        ];
    }

    protected function additionalData(): array
    {
        return [
            'managers' => Manager::where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])
                ->toArray(),
        ];
    }

    protected function transformItem($item): array
    {
        $data = $item->toArray();
        $data['manager_name'] = $item->manager?->name;
        return $data;
    }

    protected function with(): array
    {
        return ['manager'];
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
