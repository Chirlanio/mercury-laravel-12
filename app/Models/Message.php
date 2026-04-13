<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'conversation_id', 'sender_id', 'content', 'message_type',
        'file_path', 'file_name', 'file_size', 'file_mime',
        'reply_to_message_id', 'created_at', 'edited_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'edited_at' => 'datetime',
        'file_size' => 'integer',
    ];

    // Relationships

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    // Scopes

    public function scopeForConversation(Builder $query, int $conversationId): Builder
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeRecent(Builder $query, int $limit = 50): Builder
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    // Accessors

    public function getIsFileAttribute(): bool
    {
        return $this->message_type !== 'text';
    }

    public function getIsEditedAttribute(): bool
    {
        return $this->edited_at !== null;
    }

    public function getFileUrlAttribute(): ?string
    {
        // Avoid using asset()/Storage::url() because tenancy rewrites them to
        // /tenancy/assets/... which requires the tenancy asset middleware —
        // that route currently fails domain resolution for chat attachments.
        // Instead we stream the file through our own chat controller endpoint,
        // which runs within the already-working tenant route group.
        if (! $this->file_path || ! $this->id) {
            return null;
        }

        return route('chat.download-attachment', $this->id);
    }
}
