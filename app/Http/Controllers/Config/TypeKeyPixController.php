<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\TypeKeyPix;

class TypeKeyPixController extends ConfigController
{
    protected function modelClass(): string
    {
        return TypeKeyPix::class;
    }

    protected function viewTitle(): string
    {
        return 'Tipos de Chave PIX';
    }

    protected function viewDescription(): string
    {
        return 'Catálogo de tipos de chave PIX aceitos no cadastro de dados de pagamento (verbas de viagem, fornecedores).';
    }

    protected function routeName(): string
    {
        return 'config.type-key-pixs';
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
            ['key' => 'sort_order', 'label' => 'Ordem', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: CPF/CNPJ'],
            ['name' => 'sort_order', 'label' => 'Ordem de exibição', 'type' => 'number', 'defaultValue' => 0],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:60|unique:type_key_pixs,name'.($isUpdate ? ','.$id : ''),
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    protected function stats(): array
    {
        return [
            'active' => TypeKeyPix::where('is_active', true)->count(),
            'inactive' => TypeKeyPix::where('is_active', false)->count(),
        ];
    }
}
