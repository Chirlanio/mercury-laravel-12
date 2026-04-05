<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absence extends Model
{
    protected $fillable = [
        'employee_id',
        'absence_date',
        'type',
        'is_justified',
        'medical_certificate_id',
        'reason',
        'notes',
        'is_archived',
        'created_by_user_id',
    ];

    protected $casts = [
        'absence_date' => 'date',
        'is_justified' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function medicalCertificate(): BelongsTo
    {
        return $this->belongsTo(MedicalCertificate::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeUnjustified($query)
    {
        return $query->where('is_justified', false);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
