<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resultado configurável do atendimento na Lista da Vez.
 *
 * Flags importantes:
 *  - `is_conversion` — atendimento contou como venda (entra nas métricas
 *    de % de conversão dos relatórios).
 *  - `restore_queue_position` — quando true, a consultora volta à posição
 *    original na fila ao finalizar (ajustada pelo algoritmo do Service
 *    para descontar consultoras que estavam à frente e também saíram).
 *    Usado em outcomes "Retorna vez" (cliente pediu pra ser atendido por
 *    aquela consultora especificamente, ou troca convertida).
 *
 * Seeds default (paridade v1 — 10 outcomes):
 *  1. Venda Realizada            (is_conversion=1, restore=0)
 *  2. Pesquisa                   (is_conversion=0, restore=0)
 *  3. Produto Indisponível       (is_conversion=0, restore=0)
 *  4. Entrou e Saiu              (is_conversion=0, restore=0)
 *  5. Preço                      (is_conversion=0, restore=0)
 *  6. Tamanho/Modelo             (is_conversion=0, restore=0)
 *  7. Troca/Devolução            (is_conversion=0, restore=0)
 *  9. Troca convertida/Retorna   (is_conversion=1, restore=1)
 * 10. Preferência/Retorna vez    (is_conversion=0, restore=1)
 *  8. Outro                      (is_conversion=0, restore=0)
 */
class TurnListAttendanceOutcome extends Model
{
    protected $table = 'turn_list_attendance_outcomes';

    protected $fillable = [
        'name',
        'description',
        'color',
        'icon',
        'is_conversion',
        'restore_queue_position',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_conversion' => 'boolean',
        'restore_queue_position' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeConversions(Builder $query): Builder
    {
        return $query->where('is_conversion', true);
    }
}
