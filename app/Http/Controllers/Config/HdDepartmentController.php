<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\HdDepartment;

class HdDepartmentController extends ConfigController
{
    protected function modelClass(): string
    {
        return HdDepartment::class;
    }

    protected function viewTitle(): string
    {
        return 'Departamentos do Helpdesk';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os departamentos do módulo de chamados';
    }

    protected function routeName(): string
    {
        return 'config.hd-departments';
    }

    protected function searchableFields(): array
    {
        return ['name', 'description'];
    }

    protected function defaultSort(): string
    {
        return 'sort_order';
    }

    protected function sortableFields(): array
    {
        return ['id', 'name', 'sort_order', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'sort_order', 'label' => 'Ordem', 'sortable' => true],
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'description', 'label' => 'Descrição'],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'tickets_count', 'label' => 'Chamados'],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: TI'],
            ['name' => 'description', 'label' => 'Descrição', 'type' => 'textarea', 'placeholder' => 'Descrição curta do departamento'],
            ['name' => 'icon', 'label' => 'Ícone (FontAwesome)', 'type' => 'text', 'placeholder' => 'Ex: fas fa-laptop-code'],
            ['name' => 'sort_order', 'label' => 'Ordem', 'type' => 'number', 'defaultValue' => 0],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
            ['name' => 'auto_assign', 'label' => 'Atribuição automática (round-robin)', 'type' => 'checkbox', 'defaultValue' => false],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255|unique:hd_departments,name'.($isUpdate ? ','.$id : ''),
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'auto_assign' => 'boolean',
        ];
    }

    protected function transformItem($item): array
    {
        return array_merge($item->toArray(), [
            'tickets_count' => $item->tickets()->count(),
        ]);
    }

    protected function canDelete($model): bool|string
    {
        if ($model->tickets()->exists()) {
            return 'Não é possível excluir: existem chamados associados a este departamento.';
        }

        if ($model->categories()->exists()) {
            return 'Não é possível excluir: existem categorias associadas. Remova-as primeiro.';
        }

        return true;
    }

    protected function stats(): array
    {
        return [
            'active' => HdDepartment::where('is_active', true)->count(),
            'inactive' => HdDepartment::where('is_active', false)->count(),
        ];
    }
}
