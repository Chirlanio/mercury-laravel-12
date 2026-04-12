<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatGroup extends Model
{
    use Auditable, HasUuids;

    protected $fillable = [
        'name', 'description', 'avatar_path', 'created_by_user_id',
        'conversation_id', 'max_members', 'only_admins_can_send', 'is_active',
    ];

    protected $casts = [
        'max_members' => 'integer',
        'only_admins_can_send' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChatGroupMember::class, 'group_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(ChatGroupMember::class, 'group_id')->whereNull('left_at');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('activeMembers', fn ($q) => $q->where('user_id', $userId));
    }
}
