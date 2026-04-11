<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrainingParticipant extends Model
{
    protected $fillable = [
        'training_id', 'employee_id',
        'participant_name', 'participant_email',
        'attendance_time', 'ip_address', 'is_late',
        'certificate_generated', 'certificate_path', 'certificate_sent_at',
    ];

    protected $casts = [
        'attendance_time' => 'datetime',
        'certificate_sent_at' => 'datetime',
        'is_late' => 'boolean',
        'certificate_generated' => 'boolean',
    ];

    // Relationships

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(TrainingEvaluation::class, 'participant_id');
    }

    // Accessors

    public function getDisplayNameAttribute(): string
    {
        if ($this->employee) {
            return $this->employee->name;
        }

        return $this->participant_name;
    }

    public function getHasEvaluatedAttribute(): bool
    {
        return $this->evaluation()->exists();
    }
}
