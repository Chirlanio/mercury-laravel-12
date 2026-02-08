<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\Gender;

class GenderController extends ConfigController
{
    protected function modelClass(): string
    {
        return Gender::class;
    }

    protected function viewTitle(): string
    {
        return 'Generos';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os generos disponiveis para cadastro de funcionarios';
    }

    protected function routeName(): string
    {
        return 'config.genders';
    }

    protected function searchableFields(): array
    {
        return ['description_name'];
    }

    protected function defaultSort(): string
    {
        return 'description_name';
    }

    protected function sortableFields(): array
    {
        return ['id', 'description_name', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'description_name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'description_name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Masculino'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'description_name' => 'required|string|max:255|unique:genders,description_name' . ($isUpdate ? ',' . $id : ''),
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
        $count = $model->employees()->count();
        if ($count > 0) {
            return "Este genero esta sendo usado por {$count} funcionario(s) e nao pode ser excluido.";
        }
        return true;
    }
}
