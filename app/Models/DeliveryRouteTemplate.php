<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryRouteTemplate extends Model
{
    use Auditable;

    protected $fillable = [
        'name', 'driver_id', 'notes',
        'start_point_lat', 'start_point_lng',
        'is_active', 'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_point_lat' => 'decimal:8',
        'start_point_lng' => 'decimal:8',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(DeliveryRouteTemplateStop::class, 'template_id')->orderBy('sequence_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
