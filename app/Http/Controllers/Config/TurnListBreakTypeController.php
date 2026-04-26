<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\TurnListBreakType;

class TurnListBreakTypeController extends ConfigController
{
    protected function modelClass(): string
    {
        return TurnListBreakType::class;
    }

    protected function viewTitle(): string
    {
        return 'Tipos de Pausa';
    }

    protected function viewDescription(): string
    {
        return 'Tipos de pausa disponíveis para as consultoras na Lista da Vez (Intervalo, Almoço). Cada tipo tem um tempo máximo — pausas que ultrapassam ficam destacadas em vermelho no painel.';
    }

    protected function routeName(): string
    {
        return 'config.turn-list-break-types';
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
        return ['id', 'name', 'max_duration_minutes', 'sort_order', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'max_duration_minutes', 'label' => 'Tempo máximo (min)', 'sortable' => true],
            ['key' => 'icon', 'label' => 'Ícone', 'sortable' => false],
            ['key' => 'color', 'label' => 'Cor', 'sortable' => false],
            ['key' => 'sort_order', 'label' => 'Ordem', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Intervalo'],
            ['name' => 'max_duration_minutes', 'label' => 'Tempo máximo (minutos)', 'type' => 'number', 'required' => true, 'help' => 'Pausas acima desse tempo aparecem em vermelho no painel (alerta).'],
            ['name' => 'icon', 'label' => 'Ícone (Font Awesome)', 'type' => 'text', 'placeholder' => 'Ex: fa-solid fa-mug-hot'],
            [
                'name' => 'color',
                'label' => 'Cor',
                'type' => 'select',
                'options' => [
                    ['value' => 'gray', 'label' => 'Cinza'],
                    ['value' => 'info', 'label' => 'Azul'],
                    ['value' => 'warning', 'label' => 'Amarelo/Laranja'],
                    ['value' => 'success', 'label' => 'Verde'],
                    ['value' => 'danger', 'label' => 'Vermelho'],
                    ['value' => 'purple', 'label' => 'Roxo'],
                ],
            ],
            ['name' => 'sort_order', 'label' => 'Ordem', 'type' => 'number', 'defaultValue' => 0],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:30|unique:turn_list_break_types,name'.($isUpdate ? ','.$id : ''),
            'max_duration_minutes' => 'required|integer|min:1|max:480',
            'icon' => 'nullable|string|max:60',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    protected function stats(): array
    {
        return [
            'active' => TurnListBreakType::where('is_active', true)->count(),
            'inactive' => TurnListBreakType::where('is_active', false)->count(),
        ];
    }
}
