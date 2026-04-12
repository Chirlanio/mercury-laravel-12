<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ChatPollingService
{
    public function __construct(
        private ChatService $chatService,
        private ChatBroadcastService $broadcastService,
    ) {}

    /**
     * Get unread counts for all chat modules.
     */
    public function getUnreadCounts(int $userId): array
    {
        $user = User::find($userId);

        return [
            'conversations' => $this->chatService->getUnreadCount($userId),
            'broadcasts' => $user ? $this->broadcastService->getUnreadBroadcastCount($user) : 0,
            'total' => 0, // Computed below
        ];
    }

    /**
     * Get conversations updated since a given timestamp.
     */
    public function getRecentUpdates(int $userId, string $since): array
    {
        if (! Schema::hasTable('messages')) {
            return ['conversations' => [], 'since' => $since];
        }

        $updatedConversations = \App\Models\Conversation::forUser($userId)
            ->whereHas('messages', fn ($q) => $q->where('created_at', '>', $since))
            ->with('latestMessage.sender')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'latest_message' => $c->latestMessage ? [
                    'content' => $c->latestMessage->content,
                    'sender_name' => $c->latestMessage->sender?->name,
                    'created_at' => $c->latestMessage->created_at->toIso8601String(),
                ] : null,
            ]);

        return [
            'conversations' => $updatedConversations,
            'since' => now()->toIso8601String(),
        ];
    }
}
