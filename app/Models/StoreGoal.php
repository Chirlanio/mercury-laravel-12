<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreGoal extends Model
{
    use Auditable;

    const SUPER_MULTIPLIER = 1.15;

    protected $fillable = [
        'store_id',
        'reference_month',
        'reference_year',
        'goal_amount',
        'super_goal',
        'business_days',
        'non_working_days',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'goal_amount' => 'decimal:2',
        'super_goal' => 'decimal:2',
        'store_id' => 'integer',
        'reference_month' => 'integer',
        'reference_year' => 'integer',
        'business_days' => 'integer',
        'non_working_days' => 'integer',
    ];

    // Relationships

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function consultantGoals(): HasMany
    {
        return $this->hasMany(ConsultantGoal::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    // Scopes

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForMonth(Builder $query, int $month, int $year): Builder
    {
        return $query->where('reference_month', $month)->where('reference_year', $year);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('reference_year', $year);
    }

    // Accessors

    public function getFormattedGoalAttribute(): string
    {
        return 'R$ ' . number_format($this->goal_amount, 2, ',', '.');
    }

    public function getFormattedSuperGoalAttribute(): string
    {
        return 'R$ ' . number_format($this->super_goal, 2, ',', '.');
    }

    public function getPeriodLabelAttribute(): string
    {
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];

        return ($months[$this->reference_month] ?? '') . '/' . $this->reference_year;
    }

    // Helpers

    public static function calculateSuperGoal(float $goalAmount): float
    {
        return round($goalAmount * self::SUPER_MULTIPLIER, 2);
    }
}
