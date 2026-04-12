<?php

namespace App\Http\Controllers;

use App\Models\ChatBroadcast;
use App\Services\ChatBroadcastService;
use Illuminate\Http\Request;

class ChatBroadcastController extends Controller
{
    public function __construct(private ChatBroadcastService $broadcastService) {}

    public function index(Request $request)
    {
        $broadcasts = $this->broadcastService->getBroadcastsForUser(
            auth()->user(),
            $request->get('filter'),
        );

        return response()->json([
            'broadcasts' => $broadcasts->through(fn ($b) => [
                'id' => $b->id,
                'title' => $b->title,
                'message_text' => $b->message_text,
                'message_type' => $b->message_type,
                'file_url' => $b->file_path ? asset('storage/'.$b->file_path) : null,
                'file_name' => $b->file_name,
                'priority' => $b->priority,
                'priority_label' => $b->priority_label,
                'priority_color' => $b->priority_color,
                'sender_name' => $b->sender?->name,
                'is_read' => (bool) $b->is_read,
                'created_at' => $b->created_at->format('d/m/Y H:i'),
                'expires_at' => $b->expires_at?->format('d/m/Y H:i'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message_text' => 'required|string|max:5000',
            'message_type' => 'nullable|string|in:text,image,video,file',
            'file_path' => 'nullable|string',
            'file_name' => 'nullable|string',
            'file_size' => 'nullable|integer',
            'priority' => 'nullable|string|in:normal,important,urgent',
            'target_type' => 'nullable|string|in:all,access_level,store,custom',
            'target_ids' => 'nullable|array',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $broadcast = $this->broadcastService->createBroadcast($validated, auth()->id());

        return response()->json([
            'message' => 'Comunicado enviado.',
            'broadcast_id' => $broadcast->id,
        ]);
    }

    public function update(Request $request, ChatBroadcast $broadcast)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'message_text' => 'nullable|string|max:5000',
            'priority' => 'nullable|string|in:normal,important,urgent',
            'expires_at' => 'nullable|date',
        ]);

        $this->broadcastService->updateBroadcast($broadcast, $validated);

        return response()->json(['message' => 'Comunicado atualizado.']);
    }

    public function destroy(ChatBroadcast $broadcast)
    {
        $this->broadcastService->deleteBroadcast($broadcast);

        return response()->json(['message' => 'Comunicado removido.']);
    }

    public function markRead(ChatBroadcast $broadcast)
    {
        $this->broadcastService->markAsRead($broadcast, auth()->id());

        return response()->json(['marked' => true]);
    }
}
