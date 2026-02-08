<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\Position;
use App\Models\PositionLevel;
use App\Models\Status;

class PositionController extends ConfigController
{
    protected function modelClass(): string
    {
        return Position::class;
    }

    protected function viewTitle(): string
    {
        return 'Cargos';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os cargos disponiveis para funcionarios';
    }

    protected function routeName(): string
    {
        return 'config.positions';
    }

    protected function searchableFields(): array
    {
        return ['name', 'level'];
    }

    protected function sortableFields(): array
    {
        return ['id', 'name', 'level', 'level_category_id', 'status_id', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'level', 'label' => 'Nivel', 'sortable' => true],
            ['key' => 'level_category_name', 'label' => 'Categoria', 'sortable' => false],
            ['key' => 'status_name', 'label' => 'Status', 'sortable' => false],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome do Cargo', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Gerente de Loja'],
            ['name' => 'level', 'label' => 'Nivel', 'type' => 'text', 'required' => false, 'placeholder' => 'Ex: Senior'],
            ['name' => 'level_category_id', 'label' => 'Categoria', 'type' => 'select', 'required' => false, 'optionsKey' => 'positionLevels'],
            ['name' => 'status_id', 'label' => 'Status', 'type' => 'select', 'required' => false, 'optionsKey' => 'statuses'],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'level' => 'nullable|string|max:255',
            'level_category_id' => 'nullable|exists:position_levels,id',
            'status_id' => 'nullable|exists:statuses,id',
        ];
    }

    protected function additionalData(): array
    {
        return [
            'positionLevels' => PositionLevel::orderBy('name')
                ->get()
                ->map(fn ($pl) => ['id' => $pl->id, 'name' => $pl->name])
                ->toArray(),
            'statuses' => Status::orderBy('name')
                ->get()
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
                ->toArray(),
        ];
    }

    private ?array $positionLevelMap = null;
    private ?array $statusMap = null;

    protected function transformItem($item): array
    {
        // Cache dos maps para evitar N+1
        if ($this->positionLevelMap === null) {
            $this->positionLevelMap = PositionLevel::pluck('name', 'id')->toArray();
        }
        if ($this->statusMap === null) {
            $this->statusMap = Status::pluck('name', 'id')->toArray();
        }

        $data = $item->toArray();
        $data['level_category_name'] = $this->positionLevelMap[$item->level_category_id] ?? null;
        $data['status_name'] = $this->statusMap[$item->status_id] ?? null;
        return $data;
    }

    protected function canDelete($model): bool|string
    {
        $count = $model->employees()->count();
        if ($count > 0) {
            return "Este cargo esta sendo usado por {$count} funcionario(s) e nao pode ser excluido.";
        }
        return true;
    }
}
