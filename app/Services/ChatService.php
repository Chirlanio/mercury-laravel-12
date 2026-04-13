<?php

namespace App\Services;

use App\Events\Chat\MessageDeletedEvent;
use App\Events\Chat\MessageEditedEvent;
use App\Events\Chat\MessageReadEvent;
use App\Events\Chat\NewMessageEvent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ChatService
{
    /**
     * Get conversations for a user with latest message and unread count.
     */
    public function getConversationsForUser(int $userId, ?string $search = null): LengthAwarePaginator
    {
        $query = Conversation::forUser($userId)
            ->with(['latestMessage.sender', 'participants'])
            ->withCount(['messages as unread_count' => function ($q) use ($userId) {
                $q->where('sender_id', '!=', $userId)
                    ->whereHas('conversation.participantRecords', function ($pq) use ($userId) {
                        $pq->where('user_id', $userId)
                            ->where(function ($rq) {
                                $rq->whereColumn('messages.created_at', '>', 'conversation_participants.last_read_at')
                                    ->orWhereNull('conversation_participants.last_read_at');
                            });
                    });
            }]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('participants', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->orderByDesc(
            Message::select('created_at')
                ->whereColumn('conversation_id', 'conversations.id')
                ->latest('created_at')
                ->limit(1)
        )->paginate(30);
    }

    /**
     * Get or create a direct conversation between two users.
     */
    public function getOrCreateDirectConversation(int $userId, int $otherUserId): Conversation
    {
        // Find existing direct conversation between these two users.
        // We match candidate conversations that contain both users, then filter in PHP
        // to keep only conversations with exactly 2 participants. Using HAVING on a
        // withCount subquery is not portable (SQLite rejects HAVING without GROUP BY).
        $candidates = Conversation::direct()
            ->whereHas('participantRecords', fn ($q) => $q->where('user_id', $userId))
            ->whereHas('participantRecords', fn ($q) => $q->where('user_id', $otherUserId))
            ->withCount('participantRecords')
            ->get();

        $conversation = $candidates->firstWhere('participant_records_count', 2);

        if ($conversation) {
            return $conversation;
        }

        return DB::transaction(function () use ($userId, $otherUserId) {
            $otherUser = User::findOrFail($otherUserId);

            $conversation = Conversation::create([
                'type' => 'direct',
                'title' => null,
            ]);

            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
            ]);
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $otherUserId,
            ]);

            return $conversation->load('participants');
        });
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(int $conversationId, int $senderId, array $data): Message
    {
        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'content' => $data['content'] ?? null,
            'message_type' => $data['message_type'] ?? 'text',
            'file_path' => $data['file_path'] ?? null,
            'file_name' => $data['file_name'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'file_mime' => $data['file_mime'] ?? null,
            'reply_to_message_id' => $data['reply_to_message_id'] ?? null,
            'created_at' => now(),
        ]);

        $message->load('sender', 'replyTo');

        $sender = User::find($senderId);

        try {
            NewMessageEvent::dispatch(
                $conversationId,
                $senderId,
                $sender->name,
                [
                    'id' => $message->id,
                    'content' => $message->content,
                    'message_type' => $message->message_type,
                    'file_url' => $message->file_url,
                    'file_name' => $message->file_name,
                    'reply_to' => $message->replyTo ? [
                        'id' => $message->replyTo->id,
                        'content' => $message->replyTo->content,
                        'sender_name' => $message->replyTo->sender?->name,
                    ] : null,
                    'created_at' => $message->created_at->toIso8601String(),
                ],
            );
        } catch (\Throwable $e) {
            // Broadcast indisponível (Reverb offline) — ignora silenciosamente
        }

        return $message;
    }

    /**
     * Edit a text message. Only the sender can edit, and only text messages
     * can be edited (files/images are delete-and-resend). Sets edited_at and
     * broadcasts a MessageEditedEvent for real-time updates.
     */
    public function editMessage(int $messageId, int $userId, string $content): Message
    {
        $message = Message::findOrFail($messageId);

        if ($message->sender_id !== $userId) {
            abort(403, 'Você só pode editar suas próprias mensagens.');
        }

        if ($message->message_type !== 'text') {
            abort(422, 'Apenas mensagens de texto podem ser editadas.');
        }

        $message->update([
            'content' => $content,
            'edited_at' => now(),
        ]);

        $message->refresh();
        $message->load('sender', 'replyTo');

        try {
            MessageEditedEvent::dispatch(
                $message->conversation_id,
                $message->id,
                $userId,
                $message->content,
                $message->edited_at->toIso8601String(),
            );
        } catch (\Throwable $e) {
            // Broadcast indisponível — ignora silenciosamente
        }

        return $message;
    }

    /**
     * Delete a message. Only the sender can delete their own message.
     * Also removes any attached file from storage and broadcasts the deletion.
     */
    public function deleteMessage(int $messageId, int $userId): void
    {
        $message = Message::findOrFail($messageId);

        if ($message->sender_id !== $userId) {
            abort(403, 'Você só pode apagar suas próprias mensagens.');
        }

        $conversationId = $message->conversation_id;
        $filePath = $message->file_path;

        DB::transaction(function () use ($message, $filePath) {
            // Clear reply references first (FK uses nullOnDelete, so this is optional
            // but makes the intent explicit and avoids stale references in memory).
            Message::where('reply_to_message_id', $message->id)
                ->update(['reply_to_message_id' => null]);

            $message->delete();

            if ($filePath) {
                $disk = Storage::disk('public');
                if ($disk->exists($filePath)) {
                    $disk->delete($filePath);
                }
            }
        });

        try {
            MessageDeletedEvent::dispatch($conversationId, $messageId, $userId);
        } catch (\Throwable $e) {
            // Broadcast indisponível — ignora silenciosamente
        }
    }

    /**
     * Mark a conversation as read for a user.
     */
    public function markConversationAsRead(int $conversationId, int $userId): void
    {
        $participant = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->first();

        if ($participant) {
            $participant->update(['last_read_at' => now()]);

            try {
                MessageReadEvent::dispatch($conversationId, $userId, now()->toIso8601String());
            } catch (\Throwable $e) {
                // Broadcast indisponível — ignora silenciosamente
            }
        }
    }

    /**
     * Get messages for a conversation with pagination.
     */
    public function getMessages(int $conversationId, ?int $beforeId = null, int $limit = 50): Collection
    {
        $query = Message::forConversation($conversationId)
            ->with('sender', 'replyTo.sender')
            ->orderByDesc('created_at');

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        return $query->limit($limit)->get()->reverse()->values();
    }

    /**
     * Search messages across a user's conversations.
     */
    public function searchMessages(int $userId, string $query): Collection
    {
        return Message::whereHas('conversation', fn ($q) => $q->forUser($userId))
            ->where('content', 'like', "%{$query}%")
            ->with('sender', 'conversation')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /**
     * Get total unread count for a user across all conversations.
     */
    public function getUnreadCount(int $userId): int
    {
        if (! Schema::hasTable('conversation_participants')) {
            return 0;
        }

        $participants = ConversationParticipant::where('user_id', $userId)->get();

        return $participants->sum(function ($p) use ($userId) {
            $query = Message::where('conversation_id', $p->conversation_id)
                ->where('sender_id', '!=', $userId);
            if ($p->last_read_at) {
                $query->where('created_at', '>', $p->last_read_at);
            }

            return $query->count();
        });
    }

    /**
     * Upload a chat file attachment.
     */
    public function uploadFile(UploadedFile $file): array
    {
        $path = $file->store('chat-attachments', 'public');

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
    }
}
