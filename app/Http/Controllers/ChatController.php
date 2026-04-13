<?php

namespace App\Http\Controllers;

use App\Events\Chat\TypingIndicatorEvent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatPollingService;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function __construct(
        private ChatService $chatService,
        private ChatPollingService $pollingService,
    ) {}

    public function index(Request $request)
    {
        $userId = auth()->id();
        $conversations = $this->chatService->getConversationsForUser($userId, $request->get('search'));

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations->through(fn ($c) => $this->mapConversation($c, $userId)),
            'activeConversationId' => null,
            'messages' => [],
            'users' => User::where('id', '!=', $userId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $userId = auth()->id();

        // Verify participant
        if (! $conversation->participantRecords()->where('user_id', $userId)->exists()) {
            abort(403);
        }

        $conversations = $this->chatService->getConversationsForUser($userId);
        $messages = $this->chatService->getMessages($conversation->id, $request->get('before_id'));

        $this->chatService->markConversationAsRead($conversation->id, $userId);

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations->through(fn ($c) => $this->mapConversation($c, $userId)),
            'activeConversationId' => $conversation->id,
            'messages' => $messages->map(fn ($m) => $this->mapMessage($m)),
            'users' => User::where('id', '!=', $userId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function createDirect(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $conversation = $this->chatService->getOrCreateDirectConversation(
            auth()->id(),
            $validated['user_id'],
        );

        return redirect()->route('chat.show', $conversation->id);
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'content' => 'required_without:file_path|nullable|string|max:5000',
            'message_type' => 'nullable|string|in:text,image,video,file',
            'file_path' => 'nullable|string',
            'file_name' => 'nullable|string',
            'file_size' => 'nullable|integer',
            'file_mime' => 'nullable|string',
            'reply_to_message_id' => 'nullable|exists:messages,id',
        ]);

        $message = $this->chatService->sendMessage($conversation->id, auth()->id(), $validated);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => $this->mapMessage($message),
            ]);
        }

        return back();
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        $this->chatService->markConversationAsRead($conversation->id, auth()->id());

        return response()->json(['marked' => true]);
    }

    public function typing(Request $request, Conversation $conversation)
    {
        $user = auth()->user();

        try {
            TypingIndicatorEvent::dispatch(
                $conversation->id,
                $user->id,
                $user->name,
                $request->boolean('is_typing', true),
            );
        } catch (\Throwable $e) {
            // Broadcast indisponível (Reverb offline) — ignora silenciosamente
        }

        return response()->json(['ok' => true]);
    }

    public function search(Request $request)
    {
        $validated = $request->validate(['q' => 'required|string|min:2|max:100']);

        $results = $this->chatService->searchMessages(auth()->id(), $validated['q']);

        return response()->json([
            'results' => $results->map(fn ($m) => [
                'id' => $m->id,
                'content' => $m->content,
                'conversation_id' => $m->conversation_id,
                'sender_name' => $m->sender?->name,
                'created_at' => $m->created_at->format('d/m/Y H:i'),
            ]),
        ]);
    }

    public function uploadFile(Request $request)
    {
        $request->validate(['file' => 'required|file|max:10240']);

        $result = $this->chatService->uploadFile($request->file('file'));

        return response()->json($result);
    }

    public function unreadCounts()
    {
        $counts = $this->pollingService->getUnreadCounts(auth()->id());
        $counts['total'] = $counts['conversations'] + $counts['broadcasts'];

        return response()->json($counts);
    }

    public function conversationsJson(Request $request)
    {
        $userId = auth()->id();
        $conversations = $this->chatService->getConversationsForUser($userId, $request->get('search'));

        return response()->json([
            'conversations' => $conversations->through(fn ($c) => $this->mapConversation($c, $userId))->items(),
        ]);
    }

    public function deleteMessage(Message $message)
    {
        $this->chatService->deleteMessage($message->id, auth()->id());

        return response()->json(['deleted' => true]);
    }

    public function downloadAttachment(Message $message)
    {
        $userId = auth()->id();

        // Verify the user participates in the conversation that contains this message
        if (! $message->conversation->participantRecords()->where('user_id', $userId)->exists()) {
            abort(403);
        }

        if (! $message->file_path) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($message->file_path)) {
            abort(404);
        }

        // response()->file() streams inline so images render in <img> tags and PDFs open in browser.
        // For generic files, the frontend should use the download prop or a query param if desired.
        return response()->file($disk->path($message->file_path), [
            'Content-Type' => $message->file_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($message->file_name ?: basename($message->file_path)).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function loadMessages(Request $request, Conversation $conversation)
    {
        $messages = $this->chatService->getMessages(
            $conversation->id,
            $request->get('before_id'),
        );

        return response()->json([
            'messages' => $messages->map(fn ($m) => $this->mapMessage($m)),
        ]);
    }

    // Helpers

    private function mapConversation(Conversation $c, int $userId): array
    {
        $otherParticipant = $c->type === 'direct'
            ? $c->participants->firstWhere('id', '!=', $userId)
            : null;

        return [
            'id' => $c->id,
            'type' => $c->type,
            'title' => $c->type === 'direct' ? ($otherParticipant?->name ?? 'Conversa') : $c->title,
            'other_user_id' => $otherParticipant?->id,
            'latest_message' => $c->latestMessage ? [
                'content' => $c->latestMessage->content,
                'sender_name' => $c->latestMessage->sender?->name,
                'is_file' => $c->latestMessage->is_file,
                'created_at' => $c->latestMessage->created_at->diffForHumans(short: true),
            ] : null,
            'unread_count' => $c->unread_count ?? 0,
            'participants_count' => $c->participants->count(),
        ];
    }

    private function mapMessage($m): array
    {
        return [
            'id' => $m->id,
            'conversation_id' => $m->conversation_id,
            'sender_id' => $m->sender_id,
            'sender_name' => $m->sender?->name,
            'content' => $m->content,
            'message_type' => $m->message_type,
            'file_url' => $m->file_url,
            'file_name' => $m->file_name,
            'file_size' => $m->file_size,
            'reply_to' => $m->replyTo ? [
                'id' => $m->replyTo->id,
                'content' => $m->replyTo->content,
                'sender_name' => $m->replyTo->sender?->name,
            ] : null,
            'created_at' => $m->created_at->format('H:i'),
            'created_at_full' => $m->created_at->format('d/m/Y H:i'),
            'created_at_date' => $m->created_at->format('Y-m-d'),
        ];
    }
}
