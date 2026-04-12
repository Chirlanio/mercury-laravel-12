<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatBroadcast extends Model
{
    use Auditable, HasUuids;

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_IMPORTANT = 'important';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITY_LABELS = [
        self::PRIORITY_NORMAL => 'Normal',
        self::PRIORITY_IMPORTANT => 'Importante',
        self::PRIORITY_URGENT => 'Urgente',
    ];

    public const PRIORITY_COLORS = [
        self::PRIORITY_NORMAL => 'gray',
        self::PRIORITY_IMPORTANT => 'warning',
        self::PRIORITY_URGENT => 'danger',
    ];

    public const TARGET_ALL = 'all';

    public const TARGET_ACCESS_LEVEL = 'access_level';

    public const TARGET_STORE = 'store';

    public const TARGET_CUSTOM = 'custom';

    protected $fillable = [
        'sender_user_id', 'title', 'message_text', 'message_type',
        'file_path', 'file_name', 'file_size',
        'priority', 'target_type', 'target_ids',
        'expires_at', 'is_active', 'edited_at',
    ];

    protected $casts = [
        'target_ids' => 'array',
        'file_size' => 'integer',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    // Relationships

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ChatBroadcastRead::class, 'broadcast_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    // Accessors

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? $this->priority;
    }

    public function getPriorityColorAttribute(): string
    {
        return self::PRIORITY_COLORS[$this->priority] ?? 'gray';
    }
}
