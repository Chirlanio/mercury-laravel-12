<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustmentReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'applies_to',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const APPLIES_INCREASE = 'increase';
    public const APPLIES_DECREASE = 'decrease';
    public const APPLIES_BOTH = 'both';

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDirection(Builder $query, string $direction): Builder
    {
        return $query->whereIn('applies_to', [$direction, self::APPLIES_BOTH]);
    }
}
