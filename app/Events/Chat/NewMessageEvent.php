<?php

namespace App\Events\Chat;

use App\Events\BaseEvent;
use Illuminate\Broadcasting\PrivateChannel;

class NewMessageEvent extends BaseEvent
{
    public function __construct(
        public int $conversationId,
        public int $senderId,
        public string $senderName,
        public array $messageData,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'sender_id' => $this->senderId,
            'sender_name' => $this->senderName,
            'message' => $this->messageData,
        ];
    }
}
