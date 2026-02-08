<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeScheduleDayOverride extends Model
{
    protected $fillable = [
        'employee_work_schedule_id',
        'day_of_week',
        'is_work_day',
        'entry_time',
        'exit_time',
        'break_start',
        'break_end',
        'reason',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_work_day' => 'boolean',
    ];

    public function employeeWorkSchedule(): BelongsTo
    {
        return $this->belongsTo(EmployeeWorkSchedule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
