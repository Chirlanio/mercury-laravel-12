<?php

namespace App\Services\AI\Classifiers;

use App\Services\AI\SanitizedContext;
use App\Services\AI\TicketClassification;
use App\Services\AI\TicketClassifierInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Groq-powered classifier using Llama 3.3 70b. Groq exposes an
 * OpenAI-compatible /chat/completions endpoint with generous free tier
 * (~30 requests/minute), making it the cheapest way to start.
 *
 * Failure modes all degrade to TicketClassification::empty():
 *   - Missing api_key → log once per request, return empty
 *   - Rate limit exceeded → return empty, let the next ticket try again
 *   - HTTP non-2xx → log response body, return empty
 *   - Malformed JSON body → log parse error, return empty
 *   - Category id not in whitelist → return empty (prompt hallucination)
 *
 * The classifier never throws on business conditions. Exceptions from
 * the HTTP client are caught defensively as well. ClassifyTicketJob is
 * therefore able to treat every result uniformly: "ok, I have a DTO,
 * persist the non-null parts".
 */
class GroqClassifier implements TicketClassifierInterface
{
    /**
     * @param  string|null  $apiKey  from config('services.evolution.ai.groq.api_key') or similar
     */
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly string $defaultPrompt,
        private readonly int $rateLimitPerMinute = 25,
    ) {}

    public function classify(SanitizedContext $context, ?string $departmentPrompt = null): TicketClassification
    {
        if (! $this->apiKey) {
            Log::warning('GroqClassifier: api_key missing; returning empty classification');

            return TicketClassification::empty($this->model);
        }

        // Single shared bucket — we don't classify fast enough to justify
        // per-tenant buckets, and Groq's rate limit is global per API key.
        if (RateLimiter::tooManyAttempts('helpdesk-groq-classify', $this->rateLimitPerMinute)) {
            Log::info('GroqClassifier: rate limit reached, skipping classification');

            return TicketClassification::empty($this->model);
        }

        RateLimiter::hit('helpdesk-groq-classify', 60);

        try {
            $prompt = $this->buildPrompt($context, $departmentPrompt);

            $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->timeout(15)
                ->post('/chat/completions', [
                    'model' => $this->model,
                    'temperature' => 0.1,
                    'max_tokens' => 400,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Você é um classificador de chamados de helpdesk. Responda SEMPRE em JSON válido, sem comentários extras.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('GroqClassifier: HTTP exception', ['error' => $e->getMessage()]);

            return TicketClassification::empty($this->model);
        }

        if (! $response->successful()) {
            Log::warning('GroqClassifier: non-2xx response', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return TicketClassification::empty($this->model);
        }

        $body = $response->json();
        $content = $body['choices'][0]['message']['content'] ?? null;

        if (! $content) {
            Log::warning('GroqClassifier: empty content in response');

            return TicketClassification::empty($this->model);
        }

        return $this->parseClassification($content, $context);
    }

    /**
     * Build the user-message prompt from the department's custom template
     * (if any) or the default one from config. Performs simple
     * {{placeholder}} substitution.
     */
    protected function buildPrompt(SanitizedContext $context, ?string $departmentPrompt): string
    {
        $template = $departmentPrompt ?: $this->defaultPrompt;

        $categoriesList = collect($context->categories)
            ->map(fn ($c) => "  - id: {$c['id']}, nome: \"{$c['name']}\"")
            ->implode("\n");

        $employeeBlock = $context->employeeFirstName
            ? "Colaborador (identificado): primeiro nome \"{$context->employeeFirstName}\""
                .($context->storeCode ? ", loja \"{$context->storeCode}\"" : '')
            : 'Colaborador: (não identificado)';

        $replacements = [
            '{{department_name}}' => $context->departmentName,
            '{{categories_list}}' => $categoriesList,
            '{{employee_block}}' => $employeeBlock,
            '{{title}}' => $context->title,
            '{{description}}' => $context->description,
        ];

        return strtr($template, $replacements);
    }

    /**
     * Parse the JSON content returned by the model. Defensively validates
     * that category_id is in the whitelist of available categories — if
     * the model hallucinates an unknown id, we discard the suggestion.
     */
    protected function parseClassification(string $content, SanitizedContext $context): TicketClassification
    {
        $data = json_decode($content, true);

        if (! is_array($data)) {
            Log::warning('GroqClassifier: content is not JSON', ['content' => substr($content, 0, 300)]);

            return TicketClassification::empty($this->model);
        }

        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $priority = isset($data['priority']) ? (int) $data['priority'] : null;
        $confidence = isset($data['confidence']) ? (float) $data['confidence'] : 0.0;
        $summary = isset($data['summary']) ? (string) $data['summary'] : null;

        // Whitelist category against the context.
        $validIds = array_column($context->categories, 'id');
        if ($categoryId !== null && ! in_array($categoryId, $validIds, true)) {
            Log::info('GroqClassifier: hallucinated category_id discarded', [
                'returned' => $categoryId,
                'valid' => $validIds,
            ]);
            $categoryId = null;
        }

        // Priority must be 1..4 (see HdTicketPriority).
        if ($priority !== null && ($priority < 1 || $priority > 4)) {
            $priority = null;
        }

        // Confidence must be 0..1.
        if ($confidence < 0 || $confidence > 1) {
            $confidence = max(0.0, min(1.0, $confidence));
        }

        return new TicketClassification(
            categoryId: $categoryId,
            priority: $priority,
            confidence: $confidence,
            model: $this->model,
            summary: $summary,
        );
    }
}
