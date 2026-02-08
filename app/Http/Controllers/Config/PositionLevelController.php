<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\PositionLevel;

class PositionLevelController extends ConfigController
{
    protected function modelClass(): string
    {
        return PositionLevel::class;
    }

    protected function viewTitle(): string
    {
        return 'Niveis de Cargo';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os niveis de cargo (Gerencial, Operacional, Aprendiz)';
    }

    protected function routeName(): string
    {
        return 'config.position-levels';
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
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Gerencial'],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255|unique:position_levels,name' . ($isUpdate ? ',' . $id : ''),
        ];
    }

    protected function canDelete($model): bool|string
    {
        $count = $model->employees()->count();
        if ($count > 0) {
            return "Este nivel de cargo esta sendo usado por {$count} funcionario(s) e nao pode ser excluido.";
        }
        return true;
    }
}
