<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\HdCategory;
use App\Models\HdDepartment;
use App\Models\HdTicket;

class HdCategoryController extends ConfigController
{
    protected function modelClass(): string
    {
        return HdCategory::class;
    }

    protected function viewTitle(): string
    {
        return 'Categorias do Helpdesk';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie as categorias de chamados por departamento';
    }

    protected function routeName(): string
    {
        return 'config.hd-categories';
    }

    protected function searchableFields(): array
    {
        return ['name', 'description'];
    }

    protected function sortableFields(): array
    {
        return ['id', 'name', 'department_id', 'default_priority', 'is_active', 'created_at'];
    }

    protected function with(): array
    {
        return ['department:id,name'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'department_name', 'label' => 'Departamento', 'sortable' => false],
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'default_priority_label', 'label' => 'Prioridade Padrão', 'sortable' => false],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ];
    }

    protected function formFields(): array
    {
        return [
            [
                'name' => 'department_id',
                'label' => 'Departamento',
                'type' => 'select',
                'required' => true,
                'optionsKey' => 'departments',
            ],
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Hardware'],
            ['name' => 'description', 'label' => 'Descrição', 'type' => 'textarea'],
            [
                'name' => 'default_priority',
                'label' => 'Prioridade Padrão',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => HdTicket::PRIORITY_LOW, 'label' => 'Baixa'],
                    ['value' => HdTicket::PRIORITY_MEDIUM, 'label' => 'Média'],
                    ['value' => HdTicket::PRIORITY_HIGH, 'label' => 'Alta'],
                    ['value' => HdTicket::PRIORITY_URGENT, 'label' => 'Urgente'],
                ],
                'defaultValue' => HdTicket::PRIORITY_MEDIUM,
            ],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'department_id' => 'required|integer|exists:hd_departments,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_priority' => 'required|integer|in:1,2,3,4',
            'is_active' => 'boolean',
        ];
    }

    protected function transformItem($item): array
    {
        return array_merge($item->toArray(), [
            'department_name' => $item->department?->name ?? '-',
            'default_priority_label' => HdTicket::PRIORITY_LABELS[$item->default_priority] ?? '-',
        ]);
    }

    protected function canDelete($model): bool|string
    {
        if ($model->tickets()->exists()) {
            return 'Não é possível excluir: existem chamados associados a esta categoria.';
        }

        return true;
    }

    protected function additionalData(): array
    {
        return [
            'departments' => HdDepartment::orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])
                ->toArray(),
        ];
    }

    protected function stats(): array
    {
        return [
            'active' => HdCategory::where('is_active', true)->count(),
            'inactive' => HdCategory::where('is_active', false)->count(),
        ];
    }
}
