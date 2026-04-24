<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Threshold mínimo de faturamento para cada tier VIP num dado ano.
 *
 * Preenchido por Marketing antes de rodar `customers:vip-suggest --year=X`.
 * Um registro por (year, tier) — regra garantida por índice unique.
 */
class CustomerVipTierConfig extends Model
{
    use Auditable, HasFactory;

    protected $table = 'customer_vip_tier_configs';

    protected $fillable = [
        'year',
        'tier',
        'min_revenue',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'min_revenue' => 'decimal:2',
    ];

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }
}
