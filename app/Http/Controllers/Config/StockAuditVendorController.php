<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\StockAuditVendor;

class StockAuditVendorController extends ConfigController
{
    protected function modelClass(): string
    {
        return StockAuditVendor::class;
    }

    protected function viewTitle(): string
    {
        return 'Empresas Auditoras';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie as empresas auditoras de estoque';
    }

    protected function routeName(): string
    {
        return 'config.stock-audit-vendors';
    }

    protected function searchableFields(): array
    {
        return ['company_name', 'cnpj', 'contact_name'];
    }

    protected function defaultSort(): string
    {
        return 'company_name';
    }

    protected function sortableFields(): array
    {
        return ['id', 'company_name', 'cnpj', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'company_name', 'label' => 'Empresa', 'sortable' => true],
            ['key' => 'cnpj', 'label' => 'CNPJ', 'sortable' => true],
            ['key' => 'contact_name', 'label' => 'Contato', 'sortable' => false],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'company_name', 'label' => 'Nome da Empresa', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: ABC Auditorias Ltda'],
            ['name' => 'cnpj', 'label' => 'CNPJ', 'type' => 'text', 'placeholder' => '00.000.000/0000-00'],
            ['name' => 'contact_name', 'label' => 'Nome do Contato', 'type' => 'text', 'placeholder' => 'Ex: Joao Silva'],
            ['name' => 'contact_phone', 'label' => 'Telefone', 'type' => 'text', 'placeholder' => '(00) 00000-0000'],
            ['name' => 'contact_email', 'label' => 'E-mail', 'type' => 'email', 'placeholder' => 'contato@empresa.com'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'company_name' => 'required|string|max:150',
            'cnpj' => 'nullable|string|max:18',
            'contact_name' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:100',
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
