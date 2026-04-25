<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\TypeExpense;

class TypeExpenseController extends ConfigController
{
    protected function modelClass(): string
    {
        return TypeExpense::class;
    }

    protected function viewTitle(): string
    {
        return 'Tipos de Despesa';
    }

    protected function viewDescription(): string
    {
        return 'Catálogo de categorias de despesa usadas na prestação de contas de verbas de viagem (alimentação, transporte, hospedagem, etc).';
    }

    protected function routeName(): string
    {
        return 'config.type-expenses';
    }

    protected function searchableFields(): array
    {
        return ['name'];
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
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'icon', 'label' => 'Ícone', 'sortable' => false],
            ['key' => 'color', 'label' => 'Cor', 'sortable' => false],
            ['key' => 'sort_order', 'label' => 'Ordem', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Alimentação'],
            ['name' => 'icon', 'label' => 'Ícone (Font Awesome)', 'type' => 'text', 'placeholder' => 'Ex: fa-solid fa-utensils'],
            [
                'name' => 'color',
                'label' => 'Cor',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => '— Padrão —'],
                    ['value' => 'gray', 'label' => 'Cinza'],
                    ['value' => 'red', 'label' => 'Vermelho'],
                    ['value' => 'orange', 'label' => 'Laranja'],
                    ['value' => 'yellow', 'label' => 'Amarelo'],
                    ['value' => 'green', 'label' => 'Verde'],
                    ['value' => 'blue', 'label' => 'Azul'],
                    ['value' => 'indigo', 'label' => 'Índigo'],
                    ['value' => 'purple', 'label' => 'Roxo'],
                    ['value' => 'pink', 'label' => 'Rosa'],
                ],
            ],
            ['name' => 'sort_order', 'label' => 'Ordem de exibição', 'type' => 'number', 'defaultValue' => 0],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:80|unique:type_expenses,name'.($isUpdate ? ','.$id : ''),
            'icon' => 'nullable|string|max:60',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    protected function stats(): array
    {
        return [
            'active' => TypeExpense::where('is_active', true)->count(),
            'inactive' => TypeExpense::where('is_active', false)->count(),
        ];
    }
}
