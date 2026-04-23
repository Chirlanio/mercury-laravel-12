<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\SocialMedia;

class SocialMediaController extends ConfigController
{
    protected function modelClass(): string
    {
        return SocialMedia::class;
    }

    protected function viewTitle(): string
    {
        return 'Redes Sociais';
    }

    protected function viewDescription(): string
    {
        return 'Gerencie as redes sociais utilizadas no cadastro de Influencers no módulo de Cupons';
    }

    protected function routeName(): string
    {
        return 'config.social-media';
    }

    protected function searchableFields(): array
    {
        return ['name'];
    }

    protected function defaultSort(): string
    {
        return 'sort_order';
    }

    protected function sortableFields(): array
    {
        return ['id', 'name', 'sort_order', 'is_active', 'created_at'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true],
            ['key' => 'link_type', 'label' => 'Tipo de link', 'sortable' => true],
            ['key' => 'sort_order', 'label' => 'Ordem', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Instagram'],
            ['name' => 'icon', 'label' => 'Ícone (Font Awesome)', 'type' => 'text', 'placeholder' => 'Ex: fa-brands fa-instagram'],
            [
                'name' => 'link_type',
                'label' => 'Tipo de link aceito',
                'type' => 'select',
                'required' => true,
                'defaultValue' => 'url',
                'options' => [
                    ['value' => 'url', 'label' => 'URL completa (ex: YouTube, Facebook)'],
                    ['value' => 'username', 'label' => '@usuário ou URL (ex: Instagram, TikTok, X)'],
                ],
            ],
            ['name' => 'link_placeholder', 'label' => 'Dica no formulário de cupom', 'type' => 'text', 'placeholder' => 'Ex: @usuario ou instagram.com/usuario'],
            ['name' => 'sort_order', 'label' => 'Ordem de exibição', 'type' => 'number', 'defaultValue' => 0],
            ['name' => 'is_active', 'label' => 'Ativo', 'type' => 'checkbox', 'defaultValue' => true],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'name' => 'required|string|max:60|unique:social_media,name'.($isUpdate ? ','.$id : ''),
            'icon' => 'nullable|string|max:60',
            'link_type' => 'required|in:url,username',
            'link_placeholder' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    protected function stats(): array
    {
        return [
            'active' => SocialMedia::where('is_active', true)->count(),
            'inactive' => SocialMedia::where('is_active', false)->count(),
        ];
    }
}
