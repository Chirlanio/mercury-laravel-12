<?php

namespace App\Services\AI;

/**
 * Redacts personally identifiable information from free text before it
 * leaves the Mercury boundary toward an external AI provider (Groq today,
 * potentially Gemini/OpenRouter/others later).
 *
 * THIS CLASS IS THE ONLY APPROVED WAY to produce a string that will be
 * concatenated into an LLM prompt. No ad-hoc regex elsewhere in the app.
 *
 * What it redacts:
 *   - CPF: 11 digits (with or without formatting) → "[cpf]"
 *   - CNPJ: 14 digits → "[cnpj]"
 *   - Brazilian phone: (xx) xxxxx-xxxx or 11/13 sequential digits → "[telefone]"
 *   - Email addresses → "[email]"
 *   - Credit-card-ish digit clumps (13-19 digits) → "[cartao]"
 *
 * What it DOES NOT redact (by design, out of scope):
 *   - Full names in free text — not reliably detectable without NLP
 *   - Addresses — too varied, would produce false positives
 *
 * For the above, the caller is responsible for NOT placing such data into
 * the text fed into sanitize() in the first place. The sanitizer is a safety
 * net, not a policy enforcer.
 *
 * Phase 4 (Groq classifier) will depend on this class via a typed
 * `SanitizedContext` DTO so the type system prevents unsanitized data from
 * reaching the outbound API call.
 */
class PiiSanitizer
{
    /**
     * Redact sensitive tokens from free text. Idempotent — running twice
     * produces the same result. Preserves non-sensitive surrounding context.
     */
    public function sanitize(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // Order matters: more specific patterns (email, CNPJ) first so the
        // shorter patterns (CPF, phone) don't eat their digits.
        $patterns = [
            // Email — anything with @ and a tld-ish suffix.
            '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i' => '[email]',

            // CNPJ — 14 digits with optional .-/. formatting.
            '/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/' => '[cnpj]',

            // CPF — 11 digits with optional .-. formatting. Note the negative
            // lookbehind to avoid matching the last 11 digits of a CNPJ.
            '/(?<!\d)\d{3}\.?\d{3}\.?\d{3}-?\d{2}(?!\d)/' => '[cpf]',

            // Brazilian phone with country code: +55 85 98746-0451
            '/\+?55\s?\(?\d{2}\)?\s?9?\d{4,5}-?\d{4}/' => '[telefone]',

            // Brazilian phone without country code: (85) 98746-0451
            '/\(?\d{2}\)?\s?9\d{4}-?\d{4}/' => '[telefone]',

            // Credit card — 13-19 consecutive digits, optionally separated.
            '/\b(?:\d{4}[\s\-]?){3,4}\d{1,4}\b/' => '[cartao]',
        ];

        $sanitized = $text;
        foreach ($patterns as $regex => $replacement) {
            $sanitized = preg_replace($regex, $replacement, $sanitized) ?? $sanitized;
        }

        return $sanitized;
    }

    /**
     * Assert that a given string contains no obvious PII. Useful for defensive
     * checks before dispatching to external APIs. Throws on violation.
     *
     * @throws \RuntimeException when PII tokens are still present after sanitization.
     */
    public function assertClean(string $text): void
    {
        $after = $this->sanitize($text);
        if ($after !== $text) {
            throw new \RuntimeException('PII detected in text before external API call. Did you forget to sanitize?');
        }
    }
}
