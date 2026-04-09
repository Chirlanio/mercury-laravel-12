<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAuditItem extends Model
{
    protected $fillable = [
        'audit_id',
        'area_id',
        'product_variant_id',
        'product_reference',
        'product_description',
        'product_barcode',
        'product_size',
        'system_quantity',
        'unit_price',
        'cost_price',
        'count_1',
        'count_1_by_user_id',
        'count_1_at',
        'count_2',
        'count_2_by_user_id',
        'count_2_at',
        'count_3',
        'count_3_by_user_id',
        'count_3_at',
        'accepted_count',
        'resolution_type',
        'divergence',
        'divergence_value',
        'divergence_value_cost',
        'is_justified',
        'justification_note',
        'justified_by_user_id',
        'justified_at',
        'store_justified',
        'store_justified_quantity',
        'observation',
    ];

    protected $casts = [
        'system_quantity' => 'decimal:2',
        'count_1' => 'decimal:2',
        'count_2' => 'decimal:2',
        'count_3' => 'decimal:2',
        'accepted_count' => 'decimal:2',
        'divergence' => 'decimal:2',
        'divergence_value' => 'decimal:2',
        'divergence_value_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'store_justified_quantity' => 'decimal:2',
        'count_1_at' => 'datetime',
        'count_2_at' => 'datetime',
        'count_3_at' => 'datetime',
        'justified_at' => 'datetime',
        'is_justified' => 'boolean',
        'store_justified' => 'boolean',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class, 'audit_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(StockAuditArea::class, 'area_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function count1By(): BelongsTo
    {
        return $this->belongsTo(User::class, 'count_1_by_user_id');
    }

    public function count2By(): BelongsTo
    {
        return $this->belongsTo(User::class, 'count_2_by_user_id');
    }

    public function count3By(): BelongsTo
    {
        return $this->belongsTo(User::class, 'count_3_by_user_id');
    }

    public function justifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'justified_by_user_id');
    }

    public function storeJustifications(): HasMany
    {
        return $this->hasMany(StockAuditStoreJustification::class, 'item_id');
    }

    // Scopes

    public function scopeWithDivergence(Builder $query): Builder
    {
        return $query->where('divergence', '!=', 0);
    }

    public function scopeUncounted(Builder $query): Builder
    {
        return $query->whereNull('count_1');
    }

    public function scopeForArea(Builder $query, int $areaId): Builder
    {
        return $query->where('area_id', $areaId);
    }

    public function scopeNeedsThirdCount(Builder $query): Builder
    {
        return $query->whereNotNull('count_1')
            ->whereNotNull('count_2')
            ->whereColumn('count_1', '!=', 'count_2')
            ->whereNull('count_3');
    }
}
