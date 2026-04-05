<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalCertificate extends Model
{
    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'cid_code',
        'cid_description',
        'doctor_name',
        'doctor_crm',
        'notes',
        'certificate_file',
        'created_by_user_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    public function getDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeActive($query)
    {
        return $query->where('end_date', '>=', now()->toDateString());
    }

    public function scopeLongTerm($query, int $minDays = 10)
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return $query->whereRaw("julianday(end_date) - julianday(start_date) + 1 >= ?", [$minDays]);
        }

        return $query->whereRaw('DATEDIFF(end_date, start_date) + 1 >= ?', [$minDays]);
    }
}
