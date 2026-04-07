<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\ConfigController;
use App\Models\PercentageAward;

class PercentageAwardController extends ConfigController
{
    protected function modelClass(): string
    {
        return PercentageAward::class;
    }

    protected function viewTitle(): string
    {
        return 'Premiação por Nível';
    }

    protected function viewDescription(): string
    {
        return 'Configure os percentuais de comissão por nível de consultor e faixa de meta';
    }

    protected function routeName(): string
    {
        return 'config.percentage-awards';
    }

    protected function searchableFields(): array
    {
        return ['level'];
    }

    protected function defaultSort(): string
    {
        return 'level';
    }

    protected function sortableFields(): array
    {
        return ['id', 'level', 'no_goal_pct', 'goal_pct', 'super_goal_pct', 'hiper_goal_pct'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'level', 'label' => 'Nível', 'sortable' => true],
            ['key' => 'no_goal_pct', 'label' => 'Sem Meta (%)', 'sortable' => true],
            ['key' => 'goal_pct', 'label' => 'Meta (%)', 'sortable' => true],
            ['key' => 'super_goal_pct', 'label' => 'Super Meta (%)', 'sortable' => true],
            ['key' => 'hiper_goal_pct', 'label' => 'Hiper Meta (%)', 'sortable' => true],
        ];
    }

    protected function formFields(): array
    {
        return [
            ['name' => 'level', 'label' => 'Nível', 'type' => 'select', 'required' => true, 'options' => [
                ['value' => 'Júnior', 'label' => 'Júnior'],
                ['value' => 'Pleno', 'label' => 'Pleno'],
                ['value' => 'Sênior', 'label' => 'Sênior'],
            ]],
            ['name' => 'no_goal_pct', 'label' => 'Sem Meta (%)', 'type' => 'number', 'required' => true, 'step' => '0.01', 'min' => '0', 'placeholder' => 'Ex: 1.00'],
            ['name' => 'goal_pct', 'label' => 'Meta (%)', 'type' => 'number', 'required' => true, 'step' => '0.01', 'min' => '0', 'placeholder' => 'Ex: 2.00'],
            ['name' => 'super_goal_pct', 'label' => 'Super Meta (%)', 'type' => 'number', 'required' => true, 'step' => '0.01', 'min' => '0', 'placeholder' => 'Ex: 2.50'],
            ['name' => 'hiper_goal_pct', 'label' => 'Hiper Meta (%)', 'type' => 'number', 'required' => true, 'step' => '0.01', 'min' => '0', 'placeholder' => 'Ex: 3.00'],
        ];
    }

    protected function validationRules(bool $isUpdate = false, $id = null): array
    {
        return [
            'level' => 'required|string|max:20|unique:percentage_awards,level' . ($isUpdate ? ',' . $id : ''),
            'no_goal_pct' => 'required|numeric|min:0|max:100',
            'goal_pct' => 'required|numeric|min:0|max:100',
            'super_goal_pct' => 'required|numeric|min:0|max:100',
            'hiper_goal_pct' => 'required|numeric|min:0|max:100',
        ];
    }
}
