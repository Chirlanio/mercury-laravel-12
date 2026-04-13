<?php

namespace App\Services\AI;

/**
 * Contract for any external LLM-backed ticket classifier.
 *
 * The signature forces callers to pass a SanitizedContext (never raw user
 * text), so the type system guarantees PII sanitization before any
 * network call.
 *
 * Implementations:
 *   - NullClassifier: no-op, used as the default fallback and in tests
 *   - GroqClassifier: real provider, Llama 3.3 70b via Groq API
 *   - (future) GeminiClassifier, OpenRouterClassifier, etc.
 *
 * Classifiers MUST NOT throw on provider errors — they return
 * TicketClassification::empty() so the intake pipeline never breaks just
 * because the AI provider is down or rate-limited. Specific error details
 * go to the log.
 */
interface TicketClassifierInterface
{
    /**
     * Classify a ticket. Returns TicketClassification::empty() when the
     * model has no confident opinion OR when the provider errored out.
     */
    public function classify(SanitizedContext $context, ?string $departmentPrompt = null): TicketClassification;
}
