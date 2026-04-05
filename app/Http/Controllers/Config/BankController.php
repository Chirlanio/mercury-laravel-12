<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\Bank;

class BankController extends ConfigController
{
    protected function modelClass(): string
    {
        return Bank::class;
    }

    protected function viewTitle(): string
    {
        return 'Bancos';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os bancos cadastrados no sistema';
    }

    protected function routeName(): string
    {
        return 'config.banks';
    }

    protected function searchableFields(): array
    {
        return ['bank_name', 'cod_bank'];
    }

    protected function defaultSort(): string
    {
        return 'bank_name';
    }

    protected function sortableFields(): array
    {
        return ['id', 'bank_name', 'cod_bank', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'cod_bank', 'label' => 'Codigo', 'sortable' => true],
            ['key' => 'bank_name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'cod_bank', 'label' => 'Codigo do Banco', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: 001'],
            ['name' => 'bank_name', 'label' => 'Nome do Banco', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Banco do Brasil'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'cod_bank' => 'required|string|max:10|unique:banks,cod_bank' . ($isUpdate ? ',' . $id : ''),
            'bank_name' => 'required|string|max:255',
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
