<?php

namespace App\Services\Channels;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the Evolution API (WhatsApp gateway).
 *
 * Runs inside the same Laravel process as the rest of the app. When the app
 * is containerized later, the service name for Evolution comes from the
 * EVOLUTION_API_URL env var — no code change needed.
 *
 * Fake mode (config services.evolution.fake = true) short-circuits every
 * outbound call, returning a canned response. Used in tests and local dev
 * where no real Evolution container is available.
 */
class EvolutionApiClient
{
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $apiKey = null,
        private readonly ?string $instance = null,
        private readonly bool $fake = false,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: (string) config('services.evolution.base_url', ''),
            apiKey: config('services.evolution.api_key'),
            instance: config('services.evolution.instance'),
            fake: (bool) config('services.evolution.fake', false),
        );
    }

    /**
     * Send a plain text message to a WhatsApp number.
     *
     * @param  string  $number  E.164 without `+`, e.g. 5585999999999
     * @return array{success:bool, message_id:?string, raw:array}
     */
    public function sendText(string $number, string $text): array
    {
        if ($this->fake) {
            return $this->fakeResponse($number, ['text' => $text]);
        }

        if (! $this->isConfigured()) {
            Log::warning('EvolutionApiClient::sendText called without full config; skipping.', [
                'number' => $number,
            ]);

            return ['success' => false, 'message_id' => null, 'raw' => []];
        }

        $response = $this->http()->post("/message/sendText/{$this->instance}", [
            'number' => $number,
            'text' => $text,
        ]);

        return $this->parse($response);
    }

    /**
     * Send media (image/document/audio/video) to a WhatsApp number.
     *
     * @param  array{mediatype:string, media:string, caption?:string, fileName?:string}  $media
     */
    public function sendMedia(string $number, array $media): array
    {
        if ($this->fake) {
            return $this->fakeResponse($number, $media);
        }

        if (! $this->isConfigured()) {
            return ['success' => false, 'message_id' => null, 'raw' => []];
        }

        $response = $this->http()->post("/message/sendMedia/{$this->instance}", array_merge(
            ['number' => $number],
            $media,
        ));

        return $this->parse($response);
    }

    // ---------------------------------------------------------------------
    // Webhook admin — configured from artisan command, not runtime code.
    // ---------------------------------------------------------------------

    /**
     * Upsert the webhook config for the current instance.
     *
     * @param  array<int, string>  $events  Evolution event enum values, e.g. ['MESSAGES_UPSERT']
     * @param  array<string, string>  $headers  extra headers Evolution sends on every call
     * @return array{success:bool, raw:array, status:?int}
     */
    public function setWebhook(string $url, array $events, array $headers = [], bool $byEvents = false, bool $base64 = false): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'raw' => ['error' => 'Evolution client not configured'], 'status' => null];
        }

        $response = $this->http()->post("/webhook/set/{$this->instance}", [
            'webhook' => [
                'enabled' => true,
                'url' => $url,
                'headers' => (object) $headers, // force JSON object even when empty
                'byEvents' => $byEvents,
                'base64' => $base64,
                'events' => $events,
            ],
        ]);

        return [
            'success' => $response->successful(),
            'raw' => (array) ($response->json() ?? []),
            'status' => $response->status(),
        ];
    }

    /**
     * Fetch the current webhook config for the instance.
     */
    public function getWebhook(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'raw' => ['error' => 'Evolution client not configured'], 'status' => null];
        }

        $response = $this->http()->get("/webhook/find/{$this->instance}");

        return [
            'success' => $response->successful(),
            'raw' => (array) ($response->json() ?? []),
            'status' => $response->status(),
        ];
    }

    /**
     * Mark an inbound message as read. Safe to fail silently — this is a UX
     * nicety, not a transactional operation.
     */
    public function markRead(string $number, string $messageId): void
    {
        if ($this->fake || ! $this->isConfigured()) {
            return;
        }

        try {
            $this->http()->post("/chat/markMessageAsRead/{$this->instance}", [
                'readMessages' => [
                    ['remoteJid' => $number.'@s.whatsapp.net', 'id' => $messageId],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::debug('EvolutionApiClient::markRead failed', ['error' => $e->getMessage()]);
        }
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->withHeaders(['apikey' => (string) $this->apiKey])
            ->timeout(10)
            ->retry(2, 250);
    }

    protected function isConfigured(): bool
    {
        return ! empty($this->baseUrl) && ! empty($this->apiKey) && ! empty($this->instance);
    }

    protected function parse(\Illuminate\Http\Client\Response $response): array
    {
        if (! $response->successful()) {
            Log::warning('EvolutionApiClient non-2xx response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['success' => false, 'message_id' => null, 'raw' => (array) $response->json() ?? []];
        }

        $data = (array) $response->json();

        return [
            'success' => true,
            'message_id' => $data['key']['id'] ?? $data['id'] ?? null,
            'raw' => $data,
        ];
    }

    protected function fakeResponse(string $number, array $payload): array
    {
        Log::info('[EvolutionApiClient fake] would send', [
            'number' => $number,
            'payload' => $payload,
        ]);

        return [
            'success' => true,
            'message_id' => 'fake-'.uniqid(),
            'raw' => ['fake' => true],
        ];
    }
}
