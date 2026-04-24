<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Classificação VIP anual de um cliente (Black/Gold).
 *
 * Registros gerados por `CustomerVipClassificationService::generateSuggestions()`
 * com `source = 'auto'`; curadoria manual pela Marketing muda `final_tier`
 * e preenche `curated_at`/`curated_by_user_id` (source continua 'auto' até
 * a curadoria sobrescrever — depois vira 'manual').
 */
class CustomerVipTier extends Model
{
    use Auditable, HasFactory;

    protected $table = 'customer_vip_tiers';

    protected $fillable = [
        'customer_id',
        'year',
        'suggested_tier',
        'final_tier',
        'total_revenue',
        'total_orders',
        'suggested_at',
        'curated_at',
        'curated_by_user_id',
        'source',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'total_revenue' => 'decimal:2',
        'total_orders' => 'integer',
        'suggested_at' => 'datetime',
        'curated_at' => 'datetime',
    ];

    public const TIER_BLACK = 'black';

    public const TIER_GOLD = 'gold';

    public const SOURCE_AUTO = 'auto';

    public const SOURCE_MANUAL = 'manual';

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function curatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'curated_by_user_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    public function scopeOfTier(Builder $query, string $tier): Builder
    {
        return $query->where('final_tier', $tier);
    }

    public function scopeActive(Builder $query): Builder
    {
        // VIPs ativos = linhas com final_tier preenchido (não removidos da lista)
        return $query->whereNotNull('final_tier');
    }

    public function scopePendingCuration(Builder $query): Builder
    {
        return $query->whereNull('curated_at');
    }
}
