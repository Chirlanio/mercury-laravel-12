<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\EmployeeEventType;

class EmployeeEventTypeController extends ConfigController
{
    protected function modelClass(): string
    {
        return EmployeeEventType::class;
    }

    protected function viewTitle(): string
    {
        return 'Tipos de Evento de Funcionario';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os tipos de eventos que podem ser registrados para funcionarios';
    }

    protected function routeName(): string
    {
        return 'config.employee-event-types';
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
            ['key' => 'requires_document', 'label' => 'Req. Documento', 'sortable' => false, 'type' => 'badge', 'trueLabel' => 'Sim', 'falseLabel' => 'Nao'],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Advertencia'],
            ['name' => 'description', 'label' => 'Descricao', 'type' => 'textarea', 'required' => false, 'placeholder' => 'Descricao do tipo de evento'],
            ['name' => 'requires_document', 'label' => 'Requer Documento', 'type' => 'checkbox', 'defaultValue' => false],
            ['name' => 'requires_date_range', 'label' => 'Requer Periodo (data inicio e fim)', 'type' => 'checkbox', 'defaultValue' => false],
            ['name' => 'requires_single_date', 'label' => 'Requer Data Unica', 'type' => 'checkbox', 'defaultValue' => false],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255|unique:employee_event_types,name' . ($isUpdate ? ',' . $id : ''),
            'description' => 'nullable|string|max:500',
            'requires_document' => 'boolean',
            'requires_date_range' => 'boolean',
            'requires_single_date' => 'boolean',
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
        $count = $model->events()->count();
        if ($count > 0) {
            return "Este tipo de evento esta sendo usado por {$count} evento(s) e nao pode ser excluido.";
        }
        return true;
    }
}
