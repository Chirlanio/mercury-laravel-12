<?php

namespace App\Services\Helpdesk;

use Webklex\PHPIMAP\Message;

/**
 * Convert a Webklex PHPIMAP Message into the same normalized payload shape
 * that EmailIntakeDriver consumes. This is the IMAP equivalent of the
 * Postmark normalizer that lives inside ProcessInboundEmailJob, so the
 * downstream driver stays truly provider-agnostic.
 *
 * Output shape (kept in sync with EmailIntakeDriver docblock):
 *
 *   [
 *     'from_email'  => 'user@example.com',
 *     'from_name'   => 'John Doe'|null,
 *     'to_email'    => 'ti@meiasola.com.br',  // the account we're polling
 *     'subject'     => 'original subject',
 *     'text_body'   => 'plain text',
 *     'message_id'  => '<abc@origin>'|null,
 *     'in_reply_to' => '<xyz@...>'|null,
 *     'references'  => ['<a@...>', ...],
 *     'attachments' => [
 *         ['name'=>..., 'content_type'=>..., 'content'=>base64, 'size'=>int],
 *     ],
 *   ]
 *
 * Design: the Message → raw dict step and the raw dict → driver payload
 * step are separated so the transformation logic can be unit-tested with
 * plain arrays (no Mockery against Webklex internals). The Message
 * adapter is only exercised during end-to-end integration runs.
 */
class ImapMessageNormalizer
{
    /**
     * Public entrypoint — extracts raw pieces from the Webklex Message
     * and delegates to normalizeFromRaw. The polling address comes from
     * the caller (the fetch command) instead of headers because a single
     * message may have multiple recipients and we specifically care about
     * the one tied to the mailbox being polled.
     *
     * @return array<string, mixed>
     */
    public function normalize(Message $message, string $pollingAddress): array
    {
        return $this->normalizeFromRaw($this->extractRaw($message), $pollingAddress);
    }

    /**
     * Pure transformation: takes a raw payload (shape documented below)
     * and produces the driver-ready array. Safe to unit test with plain
     * arrays — no IMAP or Webklex dependency.
     *
     * Expected $raw shape:
     *   [
     *     'subject'     => string,
     *     'text_body'   => string,
     *     'html_body'   => string,           // fallback when text_body empty
     *     'from_email'  => string,           // raw, may be mixed case
     *     'from_name'   => string|null,
     *     'headers'     => array<string,string|null>,  // lowercase keys
     *     'attachments' => array<int, array{name:string, content_type:string, content:string}>,
     *   ]
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalizeFromRaw(array $raw, string $pollingAddress): array
    {
        $headers = (array) ($raw['headers'] ?? []);

        return [
            'from_email' => mb_strtolower(trim((string) ($raw['from_email'] ?? ''))),
            'from_name' => $this->nullIfBlank($raw['from_name'] ?? null),
            'to_email' => mb_strtolower(trim($pollingAddress)),
            'subject' => trim((string) ($raw['subject'] ?? '')),
            'text_body' => $this->pickBody($raw),
            'message_id' => $this->extractMessageIdFromHeaders($headers),
            'in_reply_to' => $this->normalizeHeaderValue($headers['in-reply-to'] ?? null),
            'references' => $this->parseReferences($headers['references'] ?? null),
            'attachments' => $this->normalizeAttachments((array) ($raw['attachments'] ?? [])),
        ];
    }

    // ------------------------------------------------------------------
    // Raw extraction from Webklex Message (integration-layer only)
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    protected function extractRaw(Message $message): array
    {
        return [
            'subject' => $this->safeString(fn () => $message->getSubject()),
            'text_body' => $this->safeString(fn () => $message->getTextBody()),
            'html_body' => $this->safeString(fn () => $message->getHTMLBody()),
            'from_email' => $this->extractFromEmail($message),
            'from_name' => $this->extractFromName($message),
            'headers' => $this->extractHeaders($message),
            'attachments' => $this->extractAttachments($message),
        ];
    }

    protected function extractFromEmail(Message $message): string
    {
        try {
            $from = $message->getFrom();
            if ($from && method_exists($from, 'first')) {
                $address = $from->first();
                if ($address && isset($address->mail)) {
                    return (string) $address->mail;
                }
            }
        } catch (\Throwable) {
        }

        $raw = (string) ($message->from ?? '');
        if (preg_match('/<([^>]+)>/', $raw, $m)) {
            return $m[1];
        }

        return $raw;
    }

    protected function extractFromName(Message $message): ?string
    {
        try {
            $from = $message->getFrom();
            if ($from && method_exists($from, 'first')) {
                $address = $from->first();
                if ($address && ! empty($address->personal)) {
                    return (string) $address->personal;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * @return array<string, string|null>
     */
    protected function extractHeaders(Message $message): array
    {
        $out = [];

        try {
            $header = $message->getHeader();
        } catch (\Throwable) {
            return $out;
        }

        if (! $header) {
            return $out;
        }

        foreach (['message-id', 'in-reply-to', 'references'] as $name) {
            try {
                $value = $header->get($name);
                if ($value === null || $value === '') {
                    $out[$name] = null;

                    continue;
                }
                $str = is_object($value) && method_exists($value, 'toString')
                    ? $value->toString()
                    : (string) $value;
                $out[$name] = trim(preg_replace('/\s+/', ' ', $str));
            } catch (\Throwable) {
                $out[$name] = null;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{name:string, content_type:string, content:string}>
     */
    protected function extractAttachments(Message $message): array
    {
        try {
            $attachments = $message->getAttachments();
        } catch (\Throwable) {
            return [];
        }

        if (! $attachments) {
            return [];
        }

        $out = [];
        foreach ($attachments as $att) {
            try {
                $out[] = [
                    'name' => (string) ($att->getName() ?: 'attachment.bin'),
                    'content_type' => (string) ($att->getContentType() ?: 'application/octet-stream'),
                    'content' => (string) $att->getContent(),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Pure helpers — testable without any Webklex dependency
    // ------------------------------------------------------------------

    protected function pickBody(array $raw): string
    {
        $text = trim((string) ($raw['text_body'] ?? ''));
        if ($text !== '') {
            return (string) $raw['text_body'];
        }

        $html = trim((string) ($raw['html_body'] ?? ''));
        if ($html !== '') {
            return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    }

    protected function extractMessageIdFromHeaders(array $headers): ?string
    {
        $raw = $headers['message-id'] ?? null;
        if (! $raw) {
            return null;
        }

        return $this->wrapMessageId((string) $raw);
    }

    protected function normalizeHeaderValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        $trim = trim($value);

        return $trim !== '' ? $trim : null;
    }

    /**
     * References is a space-separated list of `<msg-id>` tokens.
     *
     * @return array<int, string>
     */
    protected function parseReferences(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        preg_match_all('/<[^>]+>/', $raw, $m);

        return $m[0] ?? [];
    }

    /**
     * Ensure a message-id is wrapped in angle brackets regardless of
     * whether the caller passed it with or without them.
     */
    protected function wrapMessageId(string $id): string
    {
        $id = trim($id);
        if (str_starts_with($id, '<') && str_ends_with($id, '>')) {
            return $id;
        }

        return '<'.trim($id, '<>').'>';
    }

    /**
     * Normalize the attachments dict, base64 encoding content and
     * dropping entries with empty content.
     *
     * @param  array<int, array<string, mixed>>  $input
     * @return array<int, array{name:string, content_type:string, content:string, size:int}>
     */
    protected function normalizeAttachments(array $input): array
    {
        $out = [];

        foreach ($input as $att) {
            $content = (string) ($att['content'] ?? '');
            if ($content === '') {
                continue;
            }

            $out[] = [
                'name' => (string) ($att['name'] ?? 'attachment.bin'),
                'content_type' => (string) ($att['content_type'] ?? 'application/octet-stream'),
                'content' => base64_encode($content),
                'size' => strlen($content),
            ];
        }

        return $out;
    }

    protected function nullIfBlank(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }

    /**
     * Call $fn and return its string result, or '' on any Throwable.
     *
     * @param  callable(): mixed  $fn
     */
    protected function safeString(callable $fn): string
    {
        try {
            $value = $fn();
            if (is_object($value) && method_exists($value, 'toString')) {
                return (string) $value->toString();
            }

            return (string) $value;
        } catch (\Throwable) {
            return '';
        }
    }
}
