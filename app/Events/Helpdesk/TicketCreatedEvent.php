<?php

namespace App\Events\Helpdesk;

use App\Events\BaseEvent;
use Illuminate\Broadcasting\PrivateChannel;

class TicketCreatedEvent extends BaseEvent
{
    public function __construct(
        public int $ticketId,
        public int $departmentId,
        public string $title,
        public string $requesterName,
        public string $priority,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("hd-department.{$this->departmentId}")];
    }

    public function broadcastAs(): string
    {
        return 'ticket.created';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'title' => $this->title,
            'requester_name' => $this->requesterName,
            'priority' => $this->priority,
        ];
    }
}
