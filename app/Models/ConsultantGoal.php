<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultantGoal extends Model
{
    const SUPER_MULTIPLIER = 1.15;
    const HIPER_MULTIPLIER = 1.3225; // 1.15 * 1.15

    const LEVEL_WEIGHTS = [
        'Júnior' => 0.90,
        'Junior' => 0.90,
        'Pleno' => 1.00,
        'Sênior' => 1.15,
        'Senior' => 1.15,
    ];

    protected $fillable = [
        'store_goal_id',
        'employee_id',
        'reference_month',
        'reference_year',
        'working_days',
        'business_days',
        'deducted_days',
        'individual_goal',
        'super_goal',
        'hiper_goal',
        'level_snapshot',
        'weight',
    ];

    protected $casts = [
        'individual_goal' => 'decimal:2',
        'super_goal' => 'decimal:2',
        'hiper_goal' => 'decimal:2',
        'weight' => 'decimal:2',
        'working_days' => 'integer',
        'business_days' => 'integer',
        'deducted_days' => 'integer',
        'reference_month' => 'integer',
        'reference_year' => 'integer',
    ];

    // Relationships

    public function storeGoal(): BelongsTo
    {
        return $this->belongsTo(StoreGoal::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Scopes

    public function scopeForMonth(Builder $query, int $month, int $year): Builder
    {
        return $query->where('reference_month', $month)->where('reference_year', $year);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    // Helpers

    public static function getWeightForLevel(string $level): float
    {
        return self::LEVEL_WEIGHTS[$level] ?? 1.00;
    }

    public function getAchievementTier(float $actualSales): string
    {
        if ($actualSales >= $this->hiper_goal) {
            return 'hiper';
        }
        if ($actualSales >= $this->super_goal) {
            return 'super';
        }
        if ($actualSales >= $this->individual_goal) {
            return 'goal';
        }

        return 'below';
    }
}
