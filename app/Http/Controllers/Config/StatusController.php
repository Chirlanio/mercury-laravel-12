<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\Status;
use App\Models\ColorTheme;

class StatusController extends ConfigController
{
    protected function modelClass(): string
    {
        return Status::class;
    }

    protected function viewTitle(): string
    {
        return 'Status';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os status gerais do sistema';
    }

    protected function routeName(): string
    {
        return 'config.statuses';
    }

    protected function with(): array
    {
        return ['colorTheme'];
    }

    protected function sortableFields(): array
    {
        return ['id', 'name', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'color_theme_id', 'label' => 'Cor', 'sortable' => false, 'type' => 'color'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Ativo'],
            ['name' => 'color_theme_id', 'label' => 'Cor', 'type' => 'select', 'required' => false, 'optionsKey' => 'colorThemes'],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255|unique:statuses,name' . ($isUpdate ? ',' . $id : ''),
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
}
