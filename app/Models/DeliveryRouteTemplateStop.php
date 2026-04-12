<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryRouteTemplateStop extends Model
{
    protected $fillable = [
        'template_id', 'sequence_order', 'neighborhood',
        'address', 'reference_name', 'latitude', 'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DeliveryRouteTemplate::class, 'template_id');
    }
}
