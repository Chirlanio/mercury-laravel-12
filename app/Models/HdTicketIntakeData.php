<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HdTicketIntakeData extends Model
{
    protected $table = 'hd_ticket_intake_data';

    protected $fillable = ['ticket_id', 'template_id', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HdTicket::class, 'ticket_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(HdIntakeTemplate::class, 'template_id');
    }
}
