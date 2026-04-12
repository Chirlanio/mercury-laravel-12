<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['title', 'type'];

    // Relationships

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function participantRecords(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    public function group(): HasOne
    {
        return $this->hasOne(ChatGroup::class);
    }

    // Scopes

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('participantRecords', fn ($q) => $q->where('user_id', $userId));
    }

    public function scopeDirect(Builder $query): Builder
    {
        return $query->where('type', 'direct');
    }

    public function scopeGroup(Builder $query): Builder
    {
        return $query->where('type', 'group');
    }
}
