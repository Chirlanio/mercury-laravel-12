<?php

namespace App\Events\Chat;

use App\Events\BaseEvent;
use Illuminate\Broadcasting\PrivateChannel;

class MessageEditedEvent extends BaseEvent
{
    public function __construct(
        public int $conversationId,
        public int $messageId,
        public int $editedBy,
        public string $content,
        public string $editedAt,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'message.edited';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'edited_by' => $this->editedBy,
            'content' => $this->content,
            'edited_at' => $this->editedAt,
        ];
    }
}
