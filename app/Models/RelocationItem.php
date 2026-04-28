<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item (linha) de um remanejo: produto × tamanho × 3 quantidades
 * (requested, separated, received) + dispatched_quantity (origem CIGAM)
 * + received_quantity (destino CIGAM) preenchidos pelo
 * CIGAM matcher.
 *
 * Status do item é DERIVADO (não persistido):
 *   - completed se qty_received >= qty_requested
 *   - partial   se 0 < qty_received < qty_requested
 *   - pending   se qty_received == 0
 *
 * Snapshot de product_name, product_color, size, barcode preserva os
 * dados mesmo se o catálogo for atualizado depois.
 */
class RelocationItem extends Model
{
    protected $fillable = [
        'legacy_id',
        'relocation_id',
        'product_id',
        'product_reference',
        'product_name',
        'product_color',
        'size',
        'barcode',
        'qty_requested',
        'qty_separated',
        'qty_received',
        'dispatched_quantity',
        'received_quantity',
        'reason_code',
        'observations',
    ];

    protected $casts = [
        'qty_requested' => 'integer',
        'qty_separated' => 'integer',
        'qty_received' => 'integer',
        'dispatched_quantity' => 'integer',
        'received_quantity' => 'integer',
    ];

    /**
     * Aderência da origem ao solicitado: dispatched / requested.
     * 100% quando origem despachou tudo que foi pedido.
     */
    public function getDispatchAdherenceAttribute(): float
    {
        if ($this->qty_requested <= 0) return 0.0;
        return round(($this->dispatched_quantity / $this->qty_requested) * 100, 2);
    }

    public const ITEM_STATUS_PENDING = 'pending';
    public const ITEM_STATUS_PARTIAL = 'partial';
    public const ITEM_STATUS_COMPLETED = 'completed';

    public function relocation(): BelongsTo
    {
        return $this->belongsTo(Relocation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getItemStatusAttribute(): string
    {
        if ($this->qty_received <= 0) {
            return self::ITEM_STATUS_PENDING;
        }

        if ($this->qty_received < $this->qty_requested) {
            return self::ITEM_STATUS_PARTIAL;
        }

        return self::ITEM_STATUS_COMPLETED;
    }

    public function isFullyReceived(): bool
    {
        return $this->qty_received >= $this->qty_requested;
    }
}
