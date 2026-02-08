<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\Manager;
use App\Models\Sector;

class ManagerController extends ConfigController
{
    protected function modelClass(): string
    {
        return Manager::class;
    }

    protected function viewTitle(): string
    {
        return 'Gestores';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os gestores de area e setor';
    }

    protected function routeName(): string
    {
        return 'config.managers';
    }

    protected function searchableFields(): array
    {
        return ['name', 'email'];
    }

    protected function sortableFields(): array
    {
        return ['id', 'name', 'email', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'email', 'label' => 'E-mail', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Nome completo do gestor'],
            ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true, 'placeholder' => 'email@exemplo.com'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:managers,email' . ($isUpdate ? ',' . $id : ''),
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
        // Verificar se o gestor esta vinculado a algum setor
        $areaCount = Sector::where('area_manager_id', $model->id)->count();
        $sectorCount = Sector::where('sector_manager_id', $model->id)->count();
        $total = $areaCount + $sectorCount;

        if ($total > 0) {
            return "Este gestor esta vinculado a {$total} setor(es) e nao pode ser excluido.";
        }
        return true;
    }
}
