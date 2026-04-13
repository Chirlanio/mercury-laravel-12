<?php

namespace App\Events\Helpdesk;

use App\Models\HdInteraction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever any HdInteraction row is persisted. Listeners decide if
 * they care based on the interaction type, is_internal, and the parent
 * ticket's channel/source.
 *
 * Not a broadcast event — if realtime dashboard updates are needed, use
 * the existing TicketCommentEvent which is already broadcasted.
 */
class HelpdeskInteractionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public HdInteraction $interaction) {}
}
