<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EmployeeEvent extends Model
{
    protected $fillable = [
        'employee_id',
        'event_type_id',
        'start_date',
        'end_date',
        'document_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Relacionamento com funcionário
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relacionamento com tipo de evento
     */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EmployeeEventType::class, 'event_type_id');
    }

    /**
     * Relacionamento com usuário que criou o evento
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Accessor para URL do documento
     */
    public function getDocumentUrlAttribute()
    {
        if (!$this->document_path) {
            return null;
        }

        return asset('storage/' . $this->document_path);
    }

    /**
     * Accessor para duração do evento em dias
     */
    public function getDurationInDaysAttribute()
    {
        if (!$this->start_date || !$this->end_date) {
            return 1; // Eventos de um dia
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Accessor para período formatado
     */
    public function getPeriodAttribute()
    {
        if (!$this->start_date) {
            return 'Não informado';
        }

        if (!$this->end_date || $this->start_date->eq($this->end_date)) {
            return $this->start_date->format('d/m/Y');
        }

        return $this->start_date->format('d/m/Y') . ' - ' . $this->end_date->format('d/m/Y');
    }
}
