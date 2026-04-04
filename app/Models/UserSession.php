<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'store_id',
        'ip_address',
        'user_agent',
        'logged_in_at',
        'last_activity_at',
        'logged_out_at',
        'is_online',
        'current_page',
        'idle_status',
        'idle_since',
    ];

    protected $casts = [
        'logged_in_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'logged_out_at' => 'datetime',
        'idle_since' => 'datetime',
        'is_online' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeOnline($query)
    {
        return $query->where('is_online', true)
            ->where('last_activity_at', '>=', now()->subMinutes(5));
    }

    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public static function markInactiveSessions(): int
    {
        return static::where('is_online', true)
            ->where('last_activity_at', '<', now()->subMinutes(5))
            ->update([
                'is_online' => false,
                'logged_out_at' => now(),
            ]);
    }
}
