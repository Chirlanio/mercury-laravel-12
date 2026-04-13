<?php

namespace App\Listeners\Helpdesk;

use App\Events\Helpdesk\TicketAssignedEvent;
use App\Models\HdTicket;
use App\Models\User;
use App\Notifications\Helpdesk\TicketAssignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTicketAssignedNotifications implements ShouldQueue
{
    public function handle(TicketAssignedEvent $event): void
    {
        $ticket = HdTicket::with(['department'])->find($event->ticketId);
        if (! $ticket) {
            return;
        }

        $technician = User::find($event->technicianId);
        if (! $technician) {
            return;
        }

        $technician->notify(new TicketAssignedNotification($ticket));
    }
}
