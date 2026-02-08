<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\PageStatus;

class PageStatusController extends ConfigController
{
    protected function modelClass(): string
    {
        return PageStatus::class;
    }

    protected function viewTitle(): string
    {
        return 'Status de Pagina';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os status disponiveis para paginas do sistema';
    }

    protected function routeName(): string
    {
        return 'config.page-statuses';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'color', 'label' => 'Cor', 'sortable' => true],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function sortableFields(): array
    {
        return ['id', 'name', 'color', 'created_at'];
    }

    protected function searchableFields(): array
    {
        return ['name', 'color'];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Ativo'],
            [
                'name' => 'color',
                'label' => 'Cor (Bootstrap)',
                'type' => 'select',
                'required' => false,
                'placeholder' => 'Selecione uma cor...',
                'options' => [
                    ['value' => 'primary', 'label' => 'Primary (Azul)'],
                    ['value' => 'success', 'label' => 'Success (Verde)'],
                    ['value' => 'danger', 'label' => 'Danger (Vermelho)'],
                    ['value' => 'warning', 'label' => 'Warning (Amarelo)'],
                    ['value' => 'info', 'label' => 'Info (Ciano)'],
                    ['value' => 'secondary', 'label' => 'Secondary (Cinza)'],
                ],
            ],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:255|unique:page_statuses,name' . ($isUpdate ? ',' . $id : ''),
            'color' => 'nullable|string|max:40',
        ];
    }

    protected function canDelete($model): bool|string
    {
        $count = $model->pages()->count();
        if ($count > 0) {
            return "Este status esta sendo usado por {$count} pagina(s) e nao pode ser excluido.";
        }
        return true;
    }
}
