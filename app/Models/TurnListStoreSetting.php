<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuração da Lista da Vez por loja. Tabela esparsa — apenas lojas
 * que customizam aparecem aqui. Lojas sem registro usam defaults.
 *
 * `return_to_position` (default true) — quando uma consultora termina
 * pausa, volta na posição ORIGINAL da fila (true) ou no FIM (false).
 */
class TurnListStoreSetting extends Model
{
    protected $table = 'turn_list_store_settings';

    protected $fillable = [
        'store_code',
        'return_to_position',
        'updated_by_user_id',
    ];

    protected $casts = [
        'return_to_position' => 'boolean',
    ];

    /**
     * Helper para obter o valor de return_to_position de uma loja com
     * fallback ao default true (lojas sem registro).
     */
    public static function returnToPositionFor(string $storeCode): bool
    {
        $setting = static::where('store_code', $storeCode)->first();

        return $setting ? (bool) $setting->return_to_position : true;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_code', 'code');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
