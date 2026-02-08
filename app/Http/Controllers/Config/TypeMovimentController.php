<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\TypeMoviment;

class TypeMovimentController extends ConfigController
{
    protected function modelClass(): string
    {
        return TypeMoviment::class;
    }

    protected function viewTitle(): string
    {
        return 'Tipos de Movimentacao';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os tipos de movimentacao de funcionarios';
    }

    protected function routeName(): string
    {
        return 'config.type-moviments';
    }

    protected function searchableFields(): array
    {
        return ['name', 'description'];
    }

    protected function sortableFields(): array
    {
        return ['id', 'name', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'description', 'label' => 'Descricao', 'sortable' => false],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Admissao'],
            ['name' => 'description', 'label' => 'Descricao', 'type' => 'textarea', 'required' => false, 'placeholder' => 'Descricao do tipo de movimentacao'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255|unique:type_moviments,name' . ($isUpdate ? ',' . $id : ''),
            'description' => 'nullable|string|max:500',
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
        $count = $model->employmentContracts()->count();
        if ($count > 0) {
            return "Este tipo de movimentacao esta sendo usado por {$count} contrato(s) e nao pode ser excluido.";
        }
        return true;
    }
}
