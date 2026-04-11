<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryRouteItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'route_id', 'delivery_id', 'sequence_order',
        'client_name', 'address',
        'delivered_at', 'received_by', 'delivery_notes',
        'created_by_user_id', 'created_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // Accessors

    public function getIsDeliveredAttribute(): bool
    {
        return $this->delivered_at !== null;
    }

    // Relationships

    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'route_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
