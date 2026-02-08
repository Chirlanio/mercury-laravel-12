<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\Sector;
use App\Models\Manager;

class SectorController extends ConfigController
{
    protected function modelClass(): string
    {
        return Sector::class;
    }

    protected function viewTitle(): string
    {
        return 'Setores';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os setores da empresa e seus gestores';
    }

    protected function routeName(): string
    {
        return 'config.sectors';
    }

    protected function searchableFields(): array
    {
        return ['sector_name'];
    }

    protected function defaultSort(): string
    {
        return 'sector_name';
    }

    protected function sortableFields(): array
    {
        return ['id', 'sector_name', 'is_active', 'created_at'];
    }

    protected function with(): array
    {
        return ['areaManager', 'sectorManager'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'sector_name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'area_manager_name', 'label' => 'Gestor de Area', 'sortable' => false],
            ['key' => 'sector_manager_name', 'label' => 'Gestor do Setor', 'sortable' => false],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'sector_name', 'label' => 'Nome do Setor', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Recursos Humanos'],
            ['name' => 'area_manager_id', 'label' => 'Gestor de Area', 'type' => 'select', 'required' => false, 'optionsKey' => 'managers'],
            ['name' => 'sector_manager_id', 'label' => 'Gestor do Setor', 'type' => 'select', 'required' => false, 'optionsKey' => 'managers'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'sector_name' => 'required|string|max:255|unique:sectors,sector_name' . ($isUpdate ? ',' . $id : ''),
            'area_manager_id' => 'nullable|exists:managers,id',
            'sector_manager_id' => 'nullable|exists:managers,id',
            'is_active' => 'boolean',
        ];
    }

    protected function additionalData(): array
    {
        return [
            'managers' => Manager::where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])
                ->toArray(),
        ];
    }

    protected function transformItem($item): array
    {
        $data = $item->toArray();
        $data['area_manager_name'] = $item->areaManager?->name;
        $data['sector_manager_name'] = $item->sectorManager?->name;
        return $data;
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
            return "Este setor esta sendo usado por {$count} funcionario(s) e nao pode ser excluido.";
        }
        return true;
    }
}
