<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\ProductLookupGroup;

class ProductLookupGroupController extends ConfigController
{
    protected function modelClass(): string
    {
        return ProductLookupGroup::class;
    }

    protected function viewTitle(): string
    {
        return 'Grupos de Produto';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie os grupos de agregação das tabelas auxiliares de produtos';
    }

    protected function routeName(): string
    {
        return 'config.product-lookup-groups';
    }

    protected function modalMaxWidth(): ?string
    {
        return '2xl';
    }

    protected function searchableFields(): array
    {
        return ['name', 'lookup_type'];
    }

    protected function sortableFields(): array
    {
        return ['id', 'name', 'lookup_type', 'is_active', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'lookup_type';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'lookup_type_label', 'label' => 'Tipo', 'sortable' => false],
            ['key' => 'name', 'label' => 'Nome do Grupo', 'sortable' => true],
            ['key' => 'items_count', 'label' => 'Itens', 'sortable' => false],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
            ['key' => 'created_at', 'label' => 'Criado em', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            [
                'name' => 'lookup_type',
                'label' => 'Tipo',
                'type' => 'select',
                'required' => true,
                'placeholder' => 'Selecione o tipo...',
                'options' => [
                    ['value' => 'brands', 'label' => 'Marcas'],
                    ['value' => 'categories', 'label' => 'Categorias'],
                    ['value' => 'collections', 'label' => 'Coleções'],
                    ['value' => 'subcollections', 'label' => 'Subcoleções'],
                    ['value' => 'colors', 'label' => 'Cores'],
                    ['value' => 'materials', 'label' => 'Materiais'],
                    ['value' => 'sizes', 'label' => 'Tamanhos'],
                    ['value' => 'article_complements', 'label' => 'Complementos de Artigo'],
                ],
            ],
            ['name' => 'name', 'label' => 'Nome do Grupo', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: M|S, Neutros, Adulto...'],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        $uniqueRule = 'unique:product_lookup_groups,name';
        if ($isUpdate) {
            $uniqueRule .= ','.$id.',id,lookup_type,'.\request('lookup_type');
        } else {
            $uniqueRule .= ',NULL,id,lookup_type,'.\request('lookup_type');
        }

        return [
            'lookup_type' => 'required|string|in:brands,categories,collections,subcollections,colors,materials,sizes,article_complements',
            'name' => ['required', 'string', 'max:100', $uniqueRule],
            'is_active' => 'boolean',
        ];
    }

    protected function transformItem($item): array
    {
        $labels = [
            'brands' => 'Marcas',
            'categories' => 'Categorias',
            'collections' => 'Coleções',
            'subcollections' => 'Subcoleções',
            'colors' => 'Cores',
            'materials' => 'Materiais',
            'sizes' => 'Tamanhos',
            'article_complements' => 'Compl. Artigo',
        ];

        $data = $item->toArray();
        $data['lookup_type_label'] = $labels[$item->lookup_type] ?? $item->lookup_type;
        $data['items_count'] = $this->countItemsInGroup($item);

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
        $count = $this->countItemsInGroup($model);
        if ($count > 0) {
            return "Este grupo possui {$count} item(ns) vinculado(s). Remova-os do grupo antes de excluir.";
        }

        return true;
    }

    private function countItemsInGroup(ProductLookupGroup $group): int
    {
        $tableMap = [
            'brands' => 'product_brands',
            'categories' => 'product_categories',
            'collections' => 'product_collections',
            'subcollections' => 'product_subcollections',
            'colors' => 'product_colors',
            'materials' => 'product_materials',
            'sizes' => 'product_sizes',
            'article_complements' => 'product_article_complements',
        ];

        $table = $tableMap[$group->lookup_type] ?? null;
        if (! $table) {
            return 0;
        }

        return \DB::table($table)->where('group_id', $group->id)->count();
    }
}
