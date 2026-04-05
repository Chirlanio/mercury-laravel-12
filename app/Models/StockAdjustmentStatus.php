<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentStatus extends Model
{
    protected $fillable = ['name', 'color_theme_id', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function colorTheme(): BelongsTo
    {
        return $this->belongsTo(ColorTheme::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
