<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\StockAuditCycle;

class StockAuditCycleController extends ConfigController
{
    protected function modelClass(): string
    {
        return StockAuditCycle::class;
    }

    protected function viewTitle(): string
    {
        return 'Ciclos de Auditoria';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os ciclos de auditoria de estoque';
    }

    protected function routeName(): string
    {
        return 'config.stock-audit-cycles';
    }

    protected function searchableFields(): array
    {
        return ['cycle_name'];
    }

    protected function defaultSort(): string
    {
        return 'cycle_name';
    }

    protected function sortableFields(): array
    {
        return ['id', 'cycle_name', 'frequency', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'cycle_name', 'label' => 'Nome do Ciclo', 'sortable' => true],
            ['key' => 'frequency', 'label' => 'Frequencia', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'cycle_name', 'label' => 'Nome do Ciclo', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Ciclo Trimestral 2026 Q1'],
            [
                'name' => 'frequency',
                'label' => 'Frequencia',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'mensal', 'label' => 'Mensal'],
                    ['value' => 'bimestral', 'label' => 'Bimestral'],
                    ['value' => 'trimestral', 'label' => 'Trimestral'],
                    ['value' => 'semestral', 'label' => 'Semestral'],
                    ['value' => 'anual', 'label' => 'Anual'],
                ],
            ],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'cycle_name' => 'required|string|max:100',
            'frequency' => 'required|in:mensal,bimestral,trimestral,semestral,anual',
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
