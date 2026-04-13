<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HdTicketChannel extends Model
{
    protected $fillable = [
        'ticket_id', 'channel_id', 'external_contact', 'external_id', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HdTicket::class, 'ticket_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(HdChannel::class, 'channel_id');
    }
}
