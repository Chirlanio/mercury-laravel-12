<?php

namespace App\Events\Helpdesk;

use App\Events\BaseEvent;
use Illuminate\Broadcasting\PrivateChannel;

class TicketAssignedEvent extends BaseEvent
{
    public function __construct(
        public int $ticketId,
        public int $departmentId,
        public int $technicianId,
        public ?int $previousTechnicianId = null,
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
        return 'ticket.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'technician_id' => $this->technicianId,
            'previous_technician_id' => $this->previousTechnicianId,
        ];
    }
}
