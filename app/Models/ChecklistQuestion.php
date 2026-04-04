<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_area_id',
        'description',
        'points',
        'weight',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'checklist_area_id' => 'integer',
        'points' => 'integer',
        'weight' => 'integer',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(ChecklistArea::class, 'checklist_area_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ChecklistAnswer::class);
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
