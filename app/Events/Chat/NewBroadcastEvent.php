<?php

namespace App\Events\Chat;

use App\Events\BaseEvent;
use Illuminate\Broadcasting\PrivateChannel;

class NewBroadcastEvent extends BaseEvent
{
    public function __construct(
        public array $targetUserIds,
        public array $broadcastData,
    ) {}

    public function broadcastOn(): array
    {
        return collect($this->targetUserIds)
            ->map(fn ($id) => new PrivateChannel("user.{$id}"))
            ->toArray();
    }

    public function broadcastAs(): string
    {
        return 'broadcast.new';
    }

    public function broadcastWith(): array
    {
        return $this->broadcastData;
    }
}
