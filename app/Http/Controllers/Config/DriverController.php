<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\Driver;
use App\Models\User;

class DriverController extends ConfigController
{
    protected function modelClass(): string
    {
        return Driver::class;
    }

    protected function viewTitle(): string
    {
        return 'Motoristas';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os motoristas cadastrados para entregas e logística';
    }

    protected function routeName(): string
    {
        return 'config.drivers';
    }

    protected function searchableFields(): array
    {
        return ['name', 'cnh', 'phone'];
    }

    protected function sortableFields(): array
    {
        return ['id', 'name', 'cnh_category', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'cnh', 'label' => 'CNH', 'sortable' => false],
            ['key' => 'cnh_category', 'label' => 'Categoria', 'sortable' => true],
            ['key' => 'phone', 'label' => 'Telefone', 'sortable' => false],
            ['key' => 'user_name', 'label' => 'Usuário', 'sortable' => false],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ];
    }

    protected function transformItem($item): array
    {
        $data = $item->toArray();
        $data['user_name'] = $item->user?->name ?? '-';
        $data['phone'] = $this->formatPhone($item->phone);

        return $data;
    }

    private function formatPhone(?string $phone): string
    {
        if (! $phone) {
            return '-';
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 11) {
            return '('.substr($digits, 0, 2).') '.substr($digits, 2, 5).'-'.substr($digits, 7);
        }

        if (strlen($digits) === 10) {
            return '('.substr($digits, 0, 2).') '.substr($digits, 2, 4).'-'.substr($digits, 6);
        }

        return $phone;
    }

    protected function with(): array
    {
        return ['user'];
    }

    protected function formFields(): array
    {
        $users = User::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])
            ->toArray();

        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Nome completo do motorista', 'colSpan' => 'col-span-3'],
            [
                'name' => 'user_id',
                'label' => 'Usuário do Sistema',
                'type' => 'select',
                'required' => false,
                'placeholder' => 'Vincular a um usuário (para Painel do Motorista)',
                'options' => $users,
                'colSpan' => 'col-span-3',
            ],
            ['name' => 'cnh', 'label' => 'CNH', 'type' => 'text', 'required' => false, 'placeholder' => 'Número da CNH', 'colSpan' => 'col-span-2'],
            [
                'name' => 'cnh_category',
                'label' => 'Categoria CNH',
                'type' => 'select',
                'required' => false,
                'placeholder' => 'Selecione...',
                'options' => [
                    ['value' => 'A', 'label' => 'A'],
                    ['value' => 'B', 'label' => 'B'],
                    ['value' => 'AB', 'label' => 'AB'],
                    ['value' => 'C', 'label' => 'C'],
                    ['value' => 'D', 'label' => 'D'],
                    ['value' => 'E', 'label' => 'E'],
                ],
                'colSpan' => 'col-span-2',
            ],
            ['name' => 'phone', 'label' => 'Telefone', 'type' => 'text', 'required' => false, 'placeholder' => '(00) 00000-0000', 'mask' => 'phone', 'colSpan' => 'col-span-2'],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'cnh' => 'nullable|string|max:20|unique:drivers,cnh'.($isUpdate ? ','.$id : ''),
            'cnh_category' => 'nullable|string|max:5',
            'phone' => 'nullable|string|max:20',
            'user_id' => 'nullable|exists:users,id',
        ];
    }

    protected function modalMaxWidth(): ?string
    {
        return '7xl';
    }

    protected function formColumns(): ?string
    {
        return 'grid-cols-6';
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
