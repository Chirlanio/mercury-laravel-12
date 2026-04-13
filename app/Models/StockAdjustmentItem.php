<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_adjustment_id',
        'reference',
        'size',
        'direction',
        'quantity',
        'current_stock',
        'reason_id',
        'notes',
        'is_adjustment',
        'sort_order',
    ];

    protected $casts = [
        'is_adjustment' => 'boolean',
        'quantity' => 'integer',
        'current_stock' => 'integer',
        'sort_order' => 'integer',
    ];

    public const DIRECTION_INCREASE = 'increase';
    public const DIRECTION_DECREASE = 'decrease';

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(StockAdjustmentReason::class, 'reason_id');
    }

    public function nfs(): HasMany
    {
        return $this->hasMany(StockAdjustmentItemNf::class, 'stock_adjustment_item_id');
    }

    public function getSignedQuantityAttribute(): int
    {
        return $this->direction === self::DIRECTION_DECREASE
            ? -1 * (int) $this->quantity
            : (int) $this->quantity;
    }
}
