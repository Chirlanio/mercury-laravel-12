<?php

namespace App\Events\Chat;

use App\Events\BaseEvent;
use Illuminate\Broadcasting\PrivateChannel;

class MessageDeletedEvent extends BaseEvent
{
    public function __construct(
        public int $conversationId,
        public int $messageId,
        public int $deletedBy,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'deleted_by' => $this->deletedBy,
        ];
    }
}
