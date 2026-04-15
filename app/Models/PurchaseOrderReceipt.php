<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Recebimento de uma ordem de compra. Pode ter sido criado manualmente
 * ou pelo PurchaseOrderCigamMatcherService a partir de movements do CIGAM.
 */
class PurchaseOrderReceipt extends Model
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_CIGAM_MATCH = 'cigam_match';

    protected $fillable = [
        'purchase_order_id',
        'received_at',
        'invoice_number',
        'notes',
        'source',
        'matched_sync_batch_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class, 'receipt_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getTotalQuantityAttribute(): int
    {
        return (int) $this->items->sum('quantity_received');
    }

    public function isFromCigam(): bool
    {
        return $this->source === self::SOURCE_CIGAM_MATCH;
    }
}
