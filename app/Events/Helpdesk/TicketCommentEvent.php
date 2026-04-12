<?php

namespace App\Events\Helpdesk;

use App\Events\BaseEvent;
use Illuminate\Broadcasting\PrivateChannel;

class TicketCommentEvent extends BaseEvent
{
    public function __construct(
        public int $ticketId,
        public int $userId,
        public string $userName,
        public bool $isInternal = false,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("ticket.{$this->ticketId}")];
    }

    public function broadcastAs(): string
    {
        return 'ticket.comment';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'user_name' => $this->userName,
            'is_internal' => $this->isInternal,
        ];
    }
}
