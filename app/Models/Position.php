<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasFactory;

    protected $table = 'positions';

    protected $fillable = [
        'name',
        'level',
        'level_category_id',
        'status_id'
    ];

    protected $casts = [
        'level_category_id' => 'integer',
        'status_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all employees with this position
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Scope to get only active positions
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get positions by level category
     */
    public function scopeByLevelCategory($query, int $levelCategoryId)
    {
        return $query->where('level_category_id', $levelCategoryId);
    }

    /**
     * Get positions for leadership roles
     */
    public function scopeLeadership($query)
    {
        return $query->where('level_category_id', 1);
    }

    /**
     * Get positions for corporate roles
     */
    public function scopeCorporate($query)
    {
        return $query->where('level_category_id', 2);
    }

    /**
     * Check if position is active
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status_id === 1;
    }

    /**
     * Check if position is leadership level
     */
    public function getIsLeadershipAttribute(): bool
    {
        return $this->level_category_id === 1;
    }

    /**
     * Get position display name with level
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->level ? "{$this->name} ({$this->level})" : $this->name;
    }
}
