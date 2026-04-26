<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de pausa configurável para o módulo Lista da Vez.
 * Cada tipo tem `max_duration_minutes` — após esse tempo, a UI
 * destaca a pausa em vermelho (alerta de pausa excedida).
 *
 * Seeds default (paridade v1):
 *  - Intervalo: 15min, info, fa-coffee
 *  - Almoço: 60min, warning, fa-utensils
 */
class TurnListBreakType extends Model
{
    protected $table = 'turn_list_break_types';

    protected $fillable = [
        'name',
        'max_duration_minutes',
        'color',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'max_duration_minutes' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
