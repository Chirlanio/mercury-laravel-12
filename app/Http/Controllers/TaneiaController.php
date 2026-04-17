<?php

namespace App\Http\Controllers;

use App\Models\TaneiaConversation;
use App\Models\TaneiaMessage;
use App\Services\TaneiaClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TaneiaController extends Controller
{
    public function __construct(private readonly TaneiaClient $taneia) {}

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
            'taneiaApi' => $this->taneiaApiConfig(),
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
            ->get(['id', 'role', 'content', 'sources', 'rating', 'created_at']);

        return Inertia::render('Taneia/Index', [
            'conversations' => $conversations,
            'activeConversationId' => $conversation->id,
            'messages' => $messages,
            'taneiaApi' => $this->taneiaApiConfig(),
        ]);
    }

    /**
     * Config do microservico Python exposta ao frontend para streaming direto.
     * O frontend usa esta URL + tenant_id para chamar o FastAPI sem passar
     * pelo proxy do Laravel durante o streaming (o save posterior eh via Laravel).
     */
    private function taneiaApiConfig(): array
    {
        return [
            'chat_url' => rtrim((string) config('services.taneia.base_url'), '/')
                .'/'.ltrim((string) config('services.taneia.chat_path'), '/'),
            'tenant_id' => (string) (tenant()?->id ?? 'default'),
        ];
    }

    /**
     * Create a new (empty) conversation and redirect to it.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $conversation = TaneiaConversation::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'] ?? 'Nova conversa',
        ]);

        // AJAX/JSON fetch recebe o ID direto; Inertia/full page segue o redirect.
        if ($request->wantsJson()) {
            return response()->json([
                'conversation' => $conversation->only(['id', 'title', 'updated_at']),
            ], 201);
        }

        return redirect()->route('taneia.show', $conversation);
    }

    /**
     * Silent save apos o streaming ter terminado no cliente.
     *
     * O streaming (React -> FastAPI direto) eh efemero. Esta rota persiste
     * o par (pergunta do usuario, resposta acumulada) atomicamente, gera
     * titulo automatico na primeira mensagem e devolve os objetos com os
     * ids/timestamps reais para substituir os otimistas no frontend.
     */
    public function sendMessage(Request $request, TaneiaConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'user_content' => ['required', 'string', 'max:5000'],
            'assistant_content' => ['required', 'string', 'max:50000'],
            'sources' => ['nullable', 'array'],
            'sources.*.filename' => ['required_with:sources', 'string', 'max:255'],
            // Page nullable: planilhas nao tem pagina, PDFs tem (1-indexed).
            'sources.*.page' => ['nullable', 'integer', 'min:1'],
        ]);

        $userMessage = $conversation->messages()->create([
            'role' => TaneiaMessage::ROLE_USER,
            'content' => $validated['user_content'],
        ]);

        if (blank($conversation->title) || $conversation->title === 'Nova conversa') {
            $conversation->title = Str::limit($validated['user_content'], 60, '...');
            $conversation->save();
        }

        $assistantMessage = $conversation->messages()->create([
            'role' => TaneiaMessage::ROLE_ASSISTANT,
            'content' => $validated['assistant_content'],
            'sources' => $validated['sources'] ?? null,
        ]);

        $conversation->touch();

        return response()->json([
            'user_message' => $userMessage->only(['id', 'role', 'content', 'created_at']),
            'assistant_message' => $assistantMessage->only(['id', 'role', 'content', 'sources', 'rating', 'created_at']),
            'conversation' => $conversation->only(['id', 'title', 'updated_at']),
        ]);
    }

    /**
     * Registra avaliacao (+1 / -1 / null) em uma mensagem do assistente.
     * Usado para curar o dataset de fine-tuning.
     */
    public function rateMessage(Request $request, TaneiaMessage $message)
    {
        abort_unless(
            $message->conversation->user_id === $request->user()->id,
            403,
            'Voce nao pode avaliar mensagens de outra conversa.'
        );

        abort_if(
            $message->role !== TaneiaMessage::ROLE_ASSISTANT,
            422,
            'Apenas respostas da TaneIA podem ser avaliadas.'
        );

        $validated = $request->validate([
            'rating' => ['nullable', 'integer', 'in:-1,1'],
        ]);

        $message->update(['rating' => $validated['rating'] ?? null]);

        return response()->json([
            'id' => $message->id,
            'rating' => $message->rating,
        ]);
    }

    /**
     * Envia um PDF para o microservico indexar no ChromaDB (RAG).
     * Retorna JSON com metadados para o frontend exibir feedback.
     */
    public function uploadDocument(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,csv,xlsx,txt', 'max:10240'], // 10MB
        ]);

        try {
            $result = $this->taneia->uploadDocument($validated['file']);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'message' => 'Documento indexado com sucesso.',
            'document' => $result,
        ]);
    }

    /**
     * Ensure the conversation belongs to the authenticated user.
     */
    private function authorizeConversation(Request $request, TaneiaConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);
    }
}
