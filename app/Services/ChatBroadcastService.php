<?php

namespace App\Services;

use App\Events\Chat\NewBroadcastEvent;
use App\Models\ChatBroadcast;
use App\Models\ChatBroadcastRead;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class ChatBroadcastService
{
    /**
     * Create a broadcast.
     */
    public function createBroadcast(array $data, int $senderId): ChatBroadcast
    {
        $broadcast = ChatBroadcast::create([
            'sender_user_id' => $senderId,
            'title' => $data['title'],
            'message_text' => $data['message_text'],
            'message_type' => $data['message_type'] ?? 'text',
            'file_path' => $data['file_path'] ?? null,
            'file_name' => $data['file_name'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'target_type' => $data['target_type'] ?? 'all',
            'target_ids' => $data['target_ids'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        // Resolve target users and broadcast event
        $targetUserIds = $this->resolveTargetUsers($broadcast);
        if ($targetUserIds->isNotEmpty()) {
            NewBroadcastEvent::dispatch($targetUserIds->toArray(), [
                'id' => $broadcast->id,
                'title' => $broadcast->title,
                'priority' => $broadcast->priority,
                'sender_name' => User::find($senderId)?->name,
            ]);
        }

        return $broadcast->load('sender');
    }

    /**
     * Update a broadcast.
     */
    public function updateBroadcast(ChatBroadcast $broadcast, array $data): ChatBroadcast
    {
        $broadcast->update(array_merge($data, ['edited_at' => now()]));

        return $broadcast->fresh()->load('sender');
    }

    /**
     * Delete a broadcast.
     */
    public function deleteBroadcast(ChatBroadcast $broadcast): void
    {
        $broadcast->update(['is_active' => false]);
    }

    /**
     * Get broadcasts for a user.
     */
    public function getBroadcastsForUser(User $user, ?string $filter = null): LengthAwarePaginator
    {
        $query = ChatBroadcast::active()
            ->with('sender')
            ->withCount(['reads as is_read' => fn ($q) => $q->where('user_id', $user->id)])
            ->latest();

        // Filter by target
        $query->where(function ($q) use ($user) {
            $q->where('target_type', 'all')
                ->orWhere(function ($q2) use ($user) {
                    $q2->where('target_type', 'store')
                        ->whereJsonContains('target_ids', $user->store_id ?? '');
                })
                ->orWhere(function ($q2) use ($user) {
                    $q2->where('target_type', 'custom')
                        ->whereJsonContains('target_ids', (string) $user->id);
                });
        });

        if ($filter === 'unread') {
            $query->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query->paginate(20);
    }

    /**
     * Mark a broadcast as read.
     */
    public function markAsRead(ChatBroadcast $broadcast, int $userId): void
    {
        ChatBroadcastRead::firstOrCreate([
            'broadcast_id' => $broadcast->id,
            'user_id' => $userId,
        ]);
    }

    /**
     * Get unread broadcast count for a user.
     */
    public function getUnreadBroadcastCount(User $user): int
    {
        if (! Schema::hasTable('chat_broadcasts')) {
            return 0;
        }

        return ChatBroadcast::active()
            ->where(function ($q) use ($user) {
                $q->where('target_type', 'all')
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('target_type', 'store')
                            ->whereJsonContains('target_ids', $user->store_id ?? '');
                    })
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('target_type', 'custom')
                            ->whereJsonContains('target_ids', (string) $user->id);
                    });
            })
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->count();
    }

    /**
     * Resolve target user IDs for a broadcast.
     */
    private function resolveTargetUsers(ChatBroadcast $broadcast): \Illuminate\Support\Collection
    {
        return match ($broadcast->target_type) {
            'all' => User::pluck('id'),
            'store' => User::whereIn('store_id', $broadcast->target_ids ?? [])->pluck('id'),
            'custom' => collect($broadcast->target_ids ?? []),
            default => collect(),
        };
    }
}
