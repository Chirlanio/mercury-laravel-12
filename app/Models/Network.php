<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Network extends Model
{
    protected $fillable = [
        'nome',
        'type',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Get the stores for the network.
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * Scope a query to only include active networks.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to only include commercial networks.
     */
    public function scopeCommercial($query)
    {
        return $query->where('type', 'comercial');
    }

    /**
     * Scope a query to only include admin networks.
     */
    public function scopeAdmin($query)
    {
        return $query->where('type', 'admin');
    }
}
