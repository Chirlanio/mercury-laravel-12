<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HdSavedView extends Model
{
    protected $fillable = ['user_id', 'name', 'filters', 'is_default', 'position'];

    protected $casts = [
        'filters' => 'array',
        'is_default' => 'boolean',
        'position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
