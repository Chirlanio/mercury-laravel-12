<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\TurnListAttendanceOutcome;

class TurnListAttendanceOutcomeController extends ConfigController
{
    protected function modelClass(): string
    {
        return TurnListAttendanceOutcome::class;
    }

    protected function viewTitle(): string
    {
        return 'Resultados de Atendimento';
    }

    protected function viewDescription(): string
    {
        return 'Resultados que a consultora escolhe ao finalizar atendimento (Venda Realizada, Pesquisa, Troca convertida...). A flag "Conta como conversão" entra nas métricas de % de conversão; "Volta na posição original" preserva a posição da consultora na fila ao invés de mandá-la pro fim.';
    }

    protected function routeName(): string
    {
        return 'config.turn-list-attendance-outcomes';
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
        return ['id', 'name', 'sort_order', 'is_active', 'is_conversion', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'description', 'label' => 'Descrição', 'sortable' => false],
            ['key' => 'is_conversion', 'label' => 'Conversão', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'restore_queue_position', 'label' => 'Volta na vez', 'sortable' => false, 'type' => 'badge'],
            ['key' => 'sort_order', 'label' => 'Ordem', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Venda Realizada'],
            ['name' => 'description', 'label' => 'Descrição', 'type' => 'text', 'placeholder' => 'Texto explicativo (opcional)'],
            ['name' => 'icon', 'label' => 'Ícone (Font Awesome)', 'type' => 'text', 'placeholder' => 'Ex: fa-solid fa-cart-shopping'],
            [
                'name' => 'color',
                'label' => 'Cor',
                'type' => 'select',
                'options' => [
                    ['value' => 'gray', 'label' => 'Cinza'],
                    ['value' => 'success', 'label' => 'Verde'],
                    ['value' => 'info', 'label' => 'Azul'],
                    ['value' => 'warning', 'label' => 'Amarelo/Laranja'],
                    ['value' => 'danger', 'label' => 'Vermelho'],
                    ['value' => 'purple', 'label' => 'Roxo'],
                ],
            ],
            ['name' => 'is_conversion', 'label' => 'Conta como conversão (entra no % de venda)', 'type' => 'checkbox', 'defaultValue' => false],
            ['name' => 'restore_queue_position', 'label' => 'Volta na posição original da fila (não vai pro fim)', 'type' => 'checkbox', 'defaultValue' => false, 'help' => 'Use em outcomes onde a consultora não deve perder a vez (ex: cliente pediu por ela, ou foi troca convertida).'],
            ['name' => 'sort_order', 'label' => 'Ordem', 'type' => 'number', 'defaultValue' => 0],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:60|unique:turn_list_attendance_outcomes,name'.($isUpdate ? ','.$id : ''),
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:60',
            'color' => 'nullable|string|max:20',
            'is_conversion' => 'boolean',
            'restore_queue_position' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    protected function stats(): array
    {
        return [
            'active' => TurnListAttendanceOutcome::where('is_active', true)->count(),
            'conversions' => TurnListAttendanceOutcome::where('is_conversion', true)->count(),
        ];
    }
}
