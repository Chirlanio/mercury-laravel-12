<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkScheduleDay extends Model
{
    protected $fillable = [
        'work_schedule_id',
        'day_of_week',
        'is_work_day',
        'entry_time',
        'exit_time',
        'break_start',
        'break_end',
        'break_duration_minutes',
        'daily_hours',
        'notes',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_work_day' => 'boolean',
        'daily_hours' => 'decimal:2',
        'break_duration_minutes' => 'integer',
    ];

    protected static array $dayNames = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
    ];

    protected static array $dayShortNames = [
        0 => 'Dom',
        1 => 'Seg',
        2 => 'Ter',
        3 => 'Qua',
        4 => 'Qui',
        5 => 'Sex',
        6 => 'Sáb',
    ];

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function getDayNameAttribute(): string
    {
        return self::$dayNames[$this->day_of_week] ?? 'Desconhecido';
    }

    public function getDayShortNameAttribute(): string
    {
        return self::$dayShortNames[$this->day_of_week] ?? '???';
    }
}
