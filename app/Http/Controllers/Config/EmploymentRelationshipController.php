<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\EmploymentRelationship;

class EmploymentRelationshipController extends ConfigController
{
    protected function modelClass(): string
    {
        return EmploymentRelationship::class;
    }

    protected function viewTitle(): string
    {
        return 'Vinculos Empregaticos';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os tipos de vinculo empregaticio (CLT, Estagiario, etc.)';
    }

    protected function routeName(): string
    {
        return 'config.employment-relationships';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Colaborador efetivo'],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255|unique:employment_relationships,name' . ($isUpdate ? ',' . $id : ''),
        ];
    }
}
