<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\ColorTheme;
use App\Models\StockAdjustmentStatus;

class StockAdjustmentStatusController extends ConfigController
{
    protected function modelClass(): string
    {
        return StockAdjustmentStatus::class;
    }

    protected function viewTitle(): string
    {
        return 'Situações de Ajuste de Estoque';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie as situações disponíveis para ajustes de estoque';
    }

    protected function routeName(): string
    {
        return 'config.stock-adjustment-statuses';
    }

    protected function with(): array
    {
        return ['colorTheme'];
    }

    protected function sortableFields(): array
    {
        return ['id', 'name', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'color_theme_id', 'label' => 'Cor', 'sortable' => false, 'type' => 'color'],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Pendente'],
            ['name' => 'color_theme_id', 'label' => 'Cor', 'type' => 'select', 'required' => false, 'optionsKey' => 'colorThemes'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255|unique:stock_adjustment_statuses,name'.($isUpdate ? ','.$id : ''),
            'color_theme_id' => 'nullable|exists:color_themes,id',
            'is_active' => 'boolean',
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

    protected function stats(): array
    {
        $model = $this->modelClass();

        return [
            'active' => $model::where('is_active', true)->count(),
            'inactive' => $model::where('is_active', false)->count(),
        ];
    }
}
