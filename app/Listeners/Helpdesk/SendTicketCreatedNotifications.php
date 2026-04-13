<?php

namespace App\Listeners\Helpdesk;

use App\Events\Helpdesk\TicketCreatedEvent;
use App\Models\HdPermission;
use App\Models\HdTicket;
use App\Models\User;
use App\Notifications\Helpdesk\TicketCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendTicketCreatedNotifications implements ShouldQueue
{
    public function handle(TicketCreatedEvent $event): void
    {
        $ticket = HdTicket::with(['requester', 'department'])->find($event->ticketId);
        if (! $ticket) {
            return;
        }

        $technicianIds = HdPermission::where('department_id', $event->departmentId)
            ->pluck('user_id')
            ->toArray();

        if (empty($technicianIds)) {
            return;
        }

        $recipients = User::whereIn('id', $technicianIds)->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TicketCreatedNotification($ticket));
        }
    }
}
