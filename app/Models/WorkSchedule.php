<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedule extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'description',
        'weekly_hours',
        'is_active',
        'is_default',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'weekly_hours' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function days(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class)->orderBy('day_of_week');
    }

    public function employeeWorkSchedules(): HasMany
    {
        return $this->hasMany(EmployeeWorkSchedule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function getEmployeeCountAttribute(): int
    {
        return $this->employeeWorkSchedules()->whereNull('end_date')->count();
    }

    public function getFormattedWeeklyHoursAttribute(): string
    {
        return number_format($this->weekly_hours, 2, ',', '') . 'h';
    }

    public function getWorkDaysCountAttribute(): int
    {
        return $this->days()->where('is_work_day', true)->count();
    }
}
