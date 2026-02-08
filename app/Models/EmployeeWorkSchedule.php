<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeWorkSchedule extends Model
{
    use Auditable;

    protected $fillable = [
        'employee_id',
        'work_schedule_id',
        'effective_date',
        'end_date',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function dayOverrides(): HasMany
    {
        return $this->hasMany(EmployeeScheduleDayOverride::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }

    public function scopeCurrentForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId)
                     ->whereNull('end_date')
                     ->orderBy('effective_date', 'desc');
    }

    public function getIsCurrentAttribute(): bool
    {
        return is_null($this->end_date);
    }
}
