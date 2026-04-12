<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'last_read_at'];

    protected $casts = [
        'last_read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Count unread messages for this participant.
     */
    public function getUnreadCountAttribute(): int
    {
        $query = Message::where('conversation_id', $this->conversation_id)
            ->where('sender_id', '!=', $this->user_id);

        if ($this->last_read_at) {
            $query->where('created_at', '>', $this->last_read_at);
        }

        return $query->count();
    }
}
