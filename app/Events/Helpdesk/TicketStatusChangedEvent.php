<?php

namespace App\Events\Helpdesk;

use App\Events\BaseEvent;
use Illuminate\Broadcasting\PrivateChannel;

class TicketStatusChangedEvent extends BaseEvent
{
    public function __construct(
        public int $ticketId,
        public int $departmentId,
        public string $oldStatus,
        public string $newStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("ticket.{$this->ticketId}"),
            new PrivateChannel("hd-department.{$this->departmentId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.status_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
        ];
    }
}
