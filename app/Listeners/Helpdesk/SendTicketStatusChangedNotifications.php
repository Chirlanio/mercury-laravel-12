<?php

namespace App\Listeners\Helpdesk;

use App\Events\Helpdesk\TicketStatusChangedEvent;
use App\Models\HdTicket;
use App\Notifications\Helpdesk\TicketStatusChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTicketStatusChangedNotifications implements ShouldQueue
{
    public function handle(TicketStatusChangedEvent $event): void
    {
        $ticket = HdTicket::with(['requester'])->find($event->ticketId);
        if (! $ticket || ! $ticket->requester) {
            return;
        }

        $ticket->requester->notify(
            new TicketStatusChangedNotification($ticket, $event->oldStatus, $event->newStatus)
        );
    }
}
