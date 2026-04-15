<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha de recebimento — qual item da ordem foi recebido em qual quantidade
 * dentro de um receipt específico.
 *
 * matched_movement_id é único: garante idempotência do matcher CIGAM.
 */
class PurchaseOrderReceiptItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'receipt_id',
        'purchase_order_item_id',
        'quantity_received',
        'matched_movement_id',
        'unit_cost_cigam',
        'created_at',
    ];

    protected $casts = [
        'quantity_received' => 'integer',
        'unit_cost_cigam' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderReceipt::class, 'receipt_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function matchedMovement(): BelongsTo
    {
        return $this->belongsTo(Movement::class, 'matched_movement_id');
    }
}
