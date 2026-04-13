<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conversational intake session — holds the state machine cursor for a
 * contact on a given channel while they are answering the intake questions.
 * The session is cleared (or archived) once a ticket is produced.
 */
class HdChatSession extends Model
{
    protected $fillable = [
        'channel_id', 'external_contact', 'step', 'context', 'ticket_id', 'expires_at',
    ];

    protected $casts = [
        'context' => 'array',
        'expires_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(HdChannel::class, 'channel_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HdTicket::class, 'ticket_id');
    }

    public function scopeAlive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
