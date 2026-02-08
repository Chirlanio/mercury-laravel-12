<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\EmployeeStatus;
use App\Models\ColorTheme;

class EmployeeStatusController extends ConfigController
{
    protected function modelClass(): string
    {
        return EmployeeStatus::class;
    }

    protected function viewTitle(): string
    {
        return 'Status de Funcionario';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os status disponiveis para funcionarios (Ativo, Inativo, Ferias, etc.)';
    }

    protected function routeName(): string
    {
        return 'config.employee-statuses';
    }

    protected function searchableFields(): array
    {
        return ['description_name'];
    }

    protected function defaultSort(): string
    {
        return 'description_name';
    }

    protected function sortableFields(): array
    {
        return ['id', 'description_name', 'created_at'];
    }

    protected function with(): array
    {
        return ['colorTheme'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'description_name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'color_theme_id', 'label' => 'Cor', 'sortable' => false, 'type' => 'color'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'description_name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Ativo'],
            ['name' => 'color_theme_id', 'label' => 'Cor', 'type' => 'select', 'required' => false, 'optionsKey' => 'colorThemes'],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'description_name' => 'required|string|max:255|unique:employee_statuses,description_name' . ($isUpdate ? ',' . $id : ''),
            'color_theme_id' => 'nullable|exists:color_themes,id',
        ];
    }

    protected function additionalData(): array
    {
        return [
            'colorThemes' => ColorTheme::orderBy('name')
                ->get()
                ->map(fn ($ct) => ['id' => $ct->id, 'name' => $ct->name])
                ->toArray(),
        ];
    }

    protected function transformItem($item): array
    {
        $data = $item->toArray();
        $data['color_theme'] = $item->colorTheme ? [
            'id' => $item->colorTheme->id,
            'name' => $item->colorTheme->name,
            'hex_color' => $item->colorTheme->hex_color,
        ] : null;
        return $data;
    }

    protected function canDelete($model): bool|string
    {
        $count = $model->employees()->count();
        if ($count > 0) {
            return "Este status esta sendo usado por {$count} funcionario(s) e nao pode ser excluido.";
        }
        return true;
    }
}
