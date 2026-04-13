<?php

namespace App\Services\Helpdesk;

use App\Models\HdTicket;
use App\Services\AI\PiiSanitizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Generates two flavours of ticket summary for the detail modal:
 *
 *   - quick()  — deterministic stats (interaction count, first/last
 *                authors, span) with no external calls. Always safe.
 *   - ai()     — Groq Llama completion over the sanitized history. Only
 *                runs when the Groq classifier is configured AND the
 *                caller explicitly opts in.
 *
 * AI output is capped at ~200 tokens so technicians can skim the main
 * points of a long thread without wading through dozens of messages.
 * PII is stripped before any text leaves the Mercury boundary, same
 * contract used by the classifier layer.
 */
class TicketSummarizer
{
    public function __construct(private PiiSanitizer $sanitizer) {}

    /**
     * Deterministic summary. Always runs, no network, no AI.
     *
     * @return array{
     *   type:string,
     *   interactions:int,
     *   public_comments:int,
     *   internal_notes:int,
     *   first_at:?string,
     *   last_at:?string,
     *   last_author:?string,
     *   unique_authors:int
     * }
     */
    public function quick(HdTicket $ticket): array
    {
        $ticket->loadMissing(['interactions.user']);
        $interactions = $ticket->interactions;

        $comments = $interactions->where('type', 'comment');

        $first = $interactions->first();
        $last = $interactions->last();

        return [
            'type' => 'quick',
            'interactions' => $interactions->count(),
            'public_comments' => $comments->where('is_internal', false)->count(),
            'internal_notes' => $comments->where('is_internal', true)->count(),
            'first_at' => $first?->created_at?->format('d/m/Y H:i'),
            'last_at' => $last?->created_at?->format('d/m/Y H:i'),
            'last_author' => $last?->user?->name,
            'unique_authors' => $interactions->pluck('user_id')->unique()->filter()->count(),
        ];
    }

    /**
     * AI-generated narrative summary. Returns null when AI isn't
     * configured (HELPDESK_AI_CLASSIFIER=null or missing key) or when
     * the rate limiter bucket is full — callers should fall back to
     * quick() in those cases.
     *
     * @return array{type:string, text:string, model:string}|null
     */
    public function ai(HdTicket $ticket): ?array
    {
        $classifier = (string) config('helpdesk.ai.classifier', 'null');
        if ($classifier !== 'groq') {
            return null;
        }

        $apiKey = config('helpdesk.ai.groq.api_key');
        if (! $apiKey) {
            return null;
        }

        if (RateLimiter::tooManyAttempts('helpdesk-groq-summarize', 20)) {
            Log::info('TicketSummarizer: rate limit reached');

            return null;
        }

        $ticket->loadMissing(['interactions.user', 'department', 'category']);

        // Build the sanitized transcript. We strip PII from every message
        // before it leaves Mercury — same contract as the classifier.
        $lines = ["CHAMADO: {$this->sanitizer->sanitize($ticket->title)}"];
        $lines[] = 'Departamento: '.($ticket->department?->name ?? '-');
        $lines[] = 'Categoria: '.($ticket->category?->name ?? '-');
        $lines[] = '';
        $lines[] = 'DESCRIÇÃO INICIAL:';
        $lines[] = $this->sanitizer->sanitize((string) $ticket->description);
        $lines[] = '';
        $lines[] = 'CONVERSA:';

        // Cap at last 30 interactions so the prompt stays bounded.
        $tail = $ticket->interactions->slice(-30)->values();
        foreach ($tail as $interaction) {
            if ($interaction->type !== 'comment' || empty($interaction->comment)) {
                continue;
            }
            $author = $interaction->user?->name ?? 'Sistema';
            $flag = $interaction->is_internal ? ' (nota interna)' : '';
            $body = $this->sanitizer->sanitize((string) $interaction->comment);
            $lines[] = "- {$author}{$flag}: {$body}";
        }

        $transcript = implode("\n", $lines);

        try {
            RateLimiter::hit('helpdesk-groq-summarize', 60);

            $response = Http::baseUrl(rtrim((string) config('helpdesk.ai.groq.base_url'), '/'))
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(20)
                ->post('/chat/completions', [
                    'model' => config('helpdesk.ai.groq.model', 'llama-3.3-70b-versatile'),
                    'temperature' => 0.2,
                    'max_tokens' => 250,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Você resume chamados de helpdesk para o atendente ler rápido. Produza em português um resumo de 3-5 linhas com: (1) o problema em uma frase, (2) o que já foi tentado, (3) próximo passo sugerido se houver. NÃO inclua dados pessoais, apenas o essencial.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $transcript,
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('TicketSummarizer: HTTP exception', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('TicketSummarizer: non-2xx response', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $body = $response->json();
        $text = trim((string) ($body['choices'][0]['message']['content'] ?? ''));

        if ($text === '') {
            return null;
        }

        return [
            'type' => 'ai',
            'text' => $text,
            'model' => (string) config('helpdesk.ai.groq.model', 'llama-3.3-70b-versatile'),
        ];
    }
}
