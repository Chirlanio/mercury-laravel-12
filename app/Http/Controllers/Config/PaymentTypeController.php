<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\PaymentType;

class PaymentTypeController extends ConfigController
{
    protected function modelClass(): string
    {
        return PaymentType::class;
    }

    protected function viewTitle(): string
    {
        return 'Tipos de Pagamento';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os tipos de pagamento disponíveis no sistema';
    }

    protected function routeName(): string
    {
        return 'config.payment-types';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Cartao de Credito'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255|unique:payment_types,name' . ($isUpdate ? ',' . $id : ''),
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
