<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\Network;

class NetworkController extends ConfigController
{
    protected function modelClass(): string
    {
        return Network::class;
    }

    protected function viewTitle(): string
    {
        return 'Redes';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie as redes do sistema';
    }

    protected function routeName(): string
    {
        return 'config.networks';
    }

    protected function searchableFields(): array
    {
        return ['nome', 'type'];
    }

    protected function defaultSort(): string
    {
        return 'nome';
    }

    protected function sortableFields(): array
    {
        return ['id', 'nome', 'type', 'active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'nome', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'type', 'label' => 'Tipo', 'sortable' => true],
            ['key' => 'active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge', 'trueLabel' => 'Ativo', 'falseLabel' => 'Inativo'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'nome', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Nome da rede'],
            [
                'name' => 'type',
                'label' => 'Tipo',
                'type' => 'select',
                'required' => false,
                'placeholder' => 'Selecione o tipo...',
                'options' => [
                    ['value' => 'comercial', 'label' => 'Comercial'],
                    ['value' => 'admin', 'label' => 'Administrativo'],
                ],
            ],
            ['name' => 'active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'nome' => 'required|string|max:255|unique:networks,nome' . ($isUpdate ? ',' . $id : ''),
            'type' => 'nullable|string|max:50',
            'active' => 'boolean',
        ];
    }

    protected function stats(): array
    {
        $model = $this->modelClass();
        return [
            'active' => $model::where('active', true)->count(),
            'inactive' => $model::where('active', false)->count(),
        ];
    }

    protected function canDelete($model): bool|string
    {
        $count = $model->users()->count();
        if ($count > 0) {
            return "Esta rede esta sendo usada por {$count} usuario(s) e nao pode ser excluida.";
        }
        return true;
    }
}
