<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationAlertLog extends Model
{
    protected $fillable = [
        'vacation_period_id',
        'employee_id',
        'alert_type',
        'message',
        'sent_at',
        'acknowledged_at',
        'acknowledged_by_user_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function vacationPeriod(): BelongsTo
    {
        return $this->belongsTo(VacationPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }
}
