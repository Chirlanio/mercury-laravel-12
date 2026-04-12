<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatGroupMember extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'group_id', 'user_id', 'role', 'can_send',
        'is_muted', 'joined_at', 'left_at',
    ];

    protected $casts = [
        'can_send' => 'boolean',
        'is_muted' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    // Relationships

    public function group(): BelongsTo
    {
        return $this->belongsTo(ChatGroup::class, 'group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('left_at');
    }

    // Helpers

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canSendMessages(): bool
    {
        return $this->can_send && ! $this->is_muted && $this->left_at === null;
    }
}
