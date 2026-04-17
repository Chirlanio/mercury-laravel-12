<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item individual de estorno parcial por produto. Vinculado a uma linha
 * original em `movements` (movement_id, nullable por resilência).
 */
class ReversalItem extends Model
{
    protected $fillable = [
        'reversal_id',
        'movement_id',
        'barcode',
        'ref_size',
        'product_name',
        'quantity',
        'unit_price',
        'amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function reversal(): BelongsTo
    {
        return $this->belongsTo(Reversal::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }
}
