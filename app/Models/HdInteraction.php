<?php

namespace App\Models;

use App\Events\Helpdesk\HelpdeskInteractionCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HdInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id', 'user_id', 'comment', 'type',
        'old_value', 'new_value', 'external_id', 'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    /**
     * Maps the Eloquent `created` lifecycle event to our custom
     * HelpdeskInteractionCreated event, which the outbound pipeline
     * listens to for WhatsApp reply dispatch.
     */
    protected $dispatchesEvents = [
        'created' => HelpdeskInteractionCreated::class,
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HdTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HdAttachment::class, 'interaction_id');
    }
}
