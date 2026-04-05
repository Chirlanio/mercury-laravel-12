<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\Driver;

class DriverController extends ConfigController
{
    protected function modelClass(): string
    {
        return Driver::class;
    }

    protected function viewTitle(): string
    {
        return 'Motoristas';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os motoristas cadastrados para entregas e logistica';
    }

    protected function routeName(): string
    {
        return 'config.drivers';
    }

    protected function searchableFields(): array
    {
        return ['name', 'cnh', 'phone'];
    }

    protected function sortableFields(): array
    {
        return ['id', 'name', 'cnh_category', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'cnh', 'label' => 'CNH', 'sortable' => false],
            ['key' => 'cnh_category', 'label' => 'Categoria', 'sortable' => true],
            ['key' => 'phone', 'label' => 'Telefone', 'sortable' => false],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Nome completo do motorista'],
            ['name' => 'cnh', 'label' => 'CNH', 'type' => 'text', 'required' => false, 'placeholder' => 'Numero da CNH'],
            [
                'name' => 'cnh_category',
                'label' => 'Categoria CNH',
                'type' => 'select',
                'required' => false,
                'placeholder' => 'Selecione...',
                'options' => [
                    ['value' => 'A', 'label' => 'A'],
                    ['value' => 'B', 'label' => 'B'],
                    ['value' => 'AB', 'label' => 'AB'],
                    ['value' => 'C', 'label' => 'C'],
                    ['value' => 'D', 'label' => 'D'],
                    ['value' => 'E', 'label' => 'E'],
                ],
            ],
            ['name' => 'phone', 'label' => 'Telefone', 'type' => 'text', 'required' => false, 'placeholder' => '(00) 00000-0000'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'cnh' => 'nullable|string|max:20|unique:drivers,cnh' . ($isUpdate ? ',' . $id : ''),
            'cnh_category' => 'nullable|string|max:5',
            'phone' => 'nullable|string|max:20',
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
