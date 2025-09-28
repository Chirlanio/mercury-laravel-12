<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EmploymentContract extends Model
{
    use HasFactory;

    protected $table = 'employment_contracts';

    protected $fillable = [
        'employee_id',
        'position_id',
        'movement_type_id',
        'start_date',
        'end_date',
        'store_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the employee that owns the contract
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the position for this contract
     * Note: Position model would need to be created
     */
    // public function position(): BelongsTo
    // {
    //     return $this->belongsTo(Position::class);
    // }

    /**
     * Get the movement type for this contract
     * Note: MovementType model would need to be created
     */
    // public function movementType(): BelongsTo
    // {
    //     return $this->belongsTo(MovementType::class);
    // }

    /**
     * Check if contract is currently active
     */
    public function getIsActiveAttribute(): bool
    {
        return is_null($this->end_date) || $this->end_date->isFuture();
    }

    /**
     * Get contract duration in months
     */
    public function getDurationInMonthsAttribute(): int
    {
        $endDate = $this->end_date ?? Carbon::now();
        return $this->start_date->diffInMonths($endDate);
    }

    /**
     * Get contract duration in years
     */
    public function getDurationInYearsAttribute(): float
    {
        $endDate = $this->end_date ?? Carbon::now();
        return round($this->start_date->diffInYears($endDate, true), 2);
    }

    /**
     * Check if contract has ended
     */
    public function getHasEndedAttribute(): bool
    {
        return !is_null($this->end_date) && $this->end_date->isPast();
    }

    /**
     * Get formatted date range
     */
    public function getDateRangeAttribute(): string
    {
        $start = $this->start_date->format('d/m/Y');
        $end = $this->end_date ? $this->end_date->format('d/m/Y') : 'Atual';
        return "{$start} - {$end}";
    }

    /**
     * Scope to get active contracts
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>', Carbon::now());
        });
    }

    /**
     * Scope to get inactive contracts
     */
    public function scopeInactive($query)
    {
        return $query->whereNotNull('end_date')
                    ->where('end_date', '<=', Carbon::now());
    }

    /**
     * Scope to filter by employee
     */
    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by store
     */
    public function scopeByStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope to filter by position
     */
    public function scopeByPosition($query, int $positionId)
    {
        return $query->where('position_id', $positionId);
    }

    /**
     * Scope to get contracts started within date range
     */
    public function scopeStartedBetween($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get current contracts for an employee
     */
    public function scopeCurrentForEmployee($query, int $employeeId)
    {
        return $query->byEmployee($employeeId)->active();
    }

    /**
     * Get the latest contract for an employee
     */
    public static function getLatestForEmployee(int $employeeId): ?self
    {
        return static::byEmployee($employeeId)
                    ->orderBy('start_date', 'desc')
                    ->first();
    }

    /**
     * Get all contracts for an employee ordered by date
     */
    public static function getHistoryForEmployee(int $employeeId)
    {
        return static::byEmployee($employeeId)
                    ->orderBy('start_date', 'desc')
                    ->get();
    }
}
