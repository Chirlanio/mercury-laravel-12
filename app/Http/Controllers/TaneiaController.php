<?php

namespace App\Http\Controllers;

use App\Models\TaneiaConversation;
use App\Models\TaneiaMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TaneiaController extends Controller
{
    /**
     * Render the TaneIA assistant page with the user's conversation history.
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $conversations = TaneiaConversation::where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at']);

        return Inertia::render('Taneia/Index', [
            'conversations' => $conversations,
            'activeConversationId' => null,
            'messages' => [],
        ]);
    }

    /**
     * Show a specific conversation and its messages.
     */
    public function show(Request $request, TaneiaConversation $conversation): Response
    {
        $this->authorizeConversation($request, $conversation);

        $conversations = TaneiaConversation::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at']);

        $messages = $conversation->messages()
            ->get(['id', 'role', 'content', 'created_at']);

        return Inertia::render('Taneia/Index', [
            'conversations' => $conversations,
            'activeConversationId' => $conversation->id,
            'messages' => $messages,
        ]);
    }

    /**
     * Create a new (empty) conversation and redirect to it.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $conversation = TaneiaConversation::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'] ?? 'Nova conversa',
        ]);

        return redirect()->route('taneia.show', $conversation);
    }

    /**
     * Persist a user message, forward it to the Python microservice and
     * persist the assistant reply. Returns the two new messages as JSON so
     * the frontend can append them optimistically.
     */
    public function sendMessage(Request $request, TaneiaConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        [$userMessage, $assistantMessage] = DB::transaction(function () use ($conversation, $validated) {
            $userMessage = $conversation->messages()->create([
                'role' => TaneiaMessage::ROLE_USER,
                'content' => $validated['content'],
            ]);

            // Auto-title the conversation from the first user message
            if (blank($conversation->title) || $conversation->title === 'Nova conversa') {
                $conversation->title = Str::limit($validated['content'], 60, '...');
            }

            $assistantReply = $this->callTaneiaService($conversation, $validated['content']);

            $assistantMessage = $conversation->messages()->create([
                'role' => TaneiaMessage::ROLE_ASSISTANT,
                'content' => $assistantReply,
            ]);

            $conversation->touch();
            $conversation->save();

            return [$userMessage, $assistantMessage];
        });

        return response()->json([
            'user_message' => $userMessage->only(['id', 'role', 'content', 'created_at']),
            'assistant_message' => $assistantMessage->only(['id', 'role', 'content', 'created_at']),
            'conversation' => $conversation->only(['id', 'title', 'updated_at']),
        ]);
    }

    /**
     * Forward the user prompt to the Python microservice. When the service
     * is unreachable (or not yet implemented), returns a mocked reply so the
     * UI can be exercised end-to-end during development.
     */
    private function callTaneiaService(TaneiaConversation $conversation, string $prompt): string
    {
        $endpoint = config('services.taneia.url', 'http://localhost:8000/api/taneia');

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->post($endpoint, [
                    'conversation_id' => $conversation->id,
                    'user_id' => $conversation->user_id,
                    'prompt' => $prompt,
                ]);

            if ($response->successful() && $response->json('reply')) {
                return (string) $response->json('reply');
            }

            Log::warning('TaneIA service returned a non-success response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('TaneIA service unreachable, returning mock reply', [
                'error' => $e->getMessage(),
            ]);
        }

        // Mocked fallback so the UI can be tested before the Python service is ready.
        return "Olá! Sou a TaneIA. Recebi sua mensagem: \"{$prompt}\". "
            .'Ainda estou conectando-me ao meu cérebro de IA, mas em breve poderei responder com respostas reais.';
    }

    /**
     * Ensure the conversation belongs to the authenticated user.
     */
    private function authorizeConversation(Request $request, TaneiaConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);
    }
}
