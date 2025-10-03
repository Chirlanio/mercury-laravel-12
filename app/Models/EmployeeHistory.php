<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'event_type',
        'title',
        'description',
        'old_value',
        'new_value',
        'event_date',
        'created_by',
    ];

    protected $casts = [
        'event_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the employee that owns this history entry
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who created this history entry
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get formatted event type
     */
    public function getEventTypeLabelAttribute(): string
    {
        return match($this->event_type) {
            'promotion' => 'Promoção',
            'position_change' => 'Mudança de Cargo',
            'transfer' => 'Transferência',
            'salary_change' => 'Alteração Salarial',
            'status_change' => 'Mudança de Status',
            'admission' => 'Admissão',
            'dismissal' => 'Demissão',
            default => 'Outro',
        };
    }
}
