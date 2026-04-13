<?php

namespace App\Listeners\Helpdesk;

use App\Events\Helpdesk\TicketCommentEvent;
use App\Models\HdPermission;
use App\Models\HdTicket;
use App\Models\User;
use App\Notifications\Helpdesk\TicketCommentedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendTicketCommentNotifications implements ShouldQueue
{
    public function handle(TicketCommentEvent $event): void
    {
        $ticket = HdTicket::with(['requester', 'assignedTechnician'])->find($event->ticketId);
        if (! $ticket) {
            return;
        }

        $author = User::find($event->userId);
        $authorName = $author?->name ?? $event->userName;

        $recipientIds = [];

        if ($event->isInternal) {
            // Internal notes: only technicians/managers of the department (not requester).
            $recipientIds = HdPermission::where('department_id', $ticket->department_id)
                ->pluck('user_id')
                ->toArray();
        } else {
            // Public comment: notify requester and assigned technician.
            if ($ticket->requester_id) {
                $recipientIds[] = $ticket->requester_id;
            }
            if ($ticket->assigned_technician_id) {
                $recipientIds[] = $ticket->assigned_technician_id;
            }
        }

        // Never notify the author of their own comment.
        $recipientIds = array_filter(array_unique($recipientIds), fn ($id) => $id !== $event->userId);

        if (empty($recipientIds)) {
            return;
        }

        $recipients = User::whereIn('id', $recipientIds)->get();

        if ($recipients->isNotEmpty()) {
            Notification::send(
                $recipients,
                new TicketCommentedNotification($ticket, $authorName, $event->isInternal)
            );
        }
    }
}
