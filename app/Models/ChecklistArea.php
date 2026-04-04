<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'weight',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'integer',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(ChecklistQuestion::class)->orderBy('display_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order');
    }
}
