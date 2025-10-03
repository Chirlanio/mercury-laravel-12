<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeMoviment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all employment contracts with this movement type
     */
    public function employmentContracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class, 'movement_type_id');
    }

    /**
     * Scope to get active movement types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get inactive movement types
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}
