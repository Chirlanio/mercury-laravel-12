<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeEventType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'requires_document',
        'requires_date_range',
        'requires_single_date',
        'is_active',
    ];

    protected $casts = [
        'requires_document' => 'boolean',
        'requires_date_range' => 'boolean',
        'requires_single_date' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Relacionamento com eventos
     */
    public function events(): HasMany
    {
        return $this->hasMany(EmployeeEvent::class, 'event_type_id');
    }

    /**
     * Scope para tipos de eventos ativos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
