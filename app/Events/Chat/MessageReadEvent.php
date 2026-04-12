<?php

namespace App\Events\Chat;

use App\Events\BaseEvent;
use Illuminate\Broadcasting\PrivateChannel;

class MessageReadEvent extends BaseEvent
{
    public function __construct(
        public int $conversationId,
        public int $userId,
        public string $readAt,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'read_at' => $this->readAt,
        ];
    }
}
