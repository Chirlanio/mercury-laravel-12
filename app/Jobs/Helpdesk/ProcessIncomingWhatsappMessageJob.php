<?php

namespace App\Jobs\Helpdesk;

use App\Models\Tenant;
use App\Services\Channels\EvolutionApiClient;
use App\Services\HelpdeskIntakeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes a raw Evolution API webhook payload for inbound WhatsApp messages.
 *
 * Runs inside the target tenant's context (initialized from $tenantId). The
 * raw payload is persisted in the queue job itself so failed jobs can be
 * retried or inspected via Horizon.
 *
 * Flow:
 *   1. Extract number + message text + external message id from payload
 *   2. Initialize tenant context
 *   3. Delegate to HelpdeskIntakeService::handle('whatsapp', ...)
 *   4. Send the driver's prompt back to the contact via EvolutionApiClient
 */
class ProcessIncomingWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly string $tenantId,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            Log::warning('WhatsApp webhook: tenant not found', ['tenant' => $this->tenantId]);

            return;
        }

        $extracted = $this->extractMessage($this->payload);
        if (! $extracted) {
            Log::info('WhatsApp webhook: no actionable message in payload', [
                'tenant' => $this->tenantId,
            ]);

            return;
        }

        $tenant->run(function () use ($extracted) {
            /** @var HelpdeskIntakeService $intake */
            $intake = app(HelpdeskIntakeService::class);

            $step = $intake->handle(
                channelSlug: 'whatsapp',
                payload: [
                    'message' => $extracted['text'],
                ],
                context: [
                    'external_contact' => $extracted['number'],
                    'external_id' => $extracted['message_id'],
                    'push_name' => $extracted['push_name'],
                    'instance' => $extracted['instance'],
                ],
            );

            // Reply to the contact. Fire-and-forget — failures are logged
            // by the client.
            EvolutionApiClient::fromConfig()->sendText($extracted['number'], $step->prompt);

            // Mark inbound as read for UX parity with the old v1 flow.
            if ($extracted['message_id']) {
                EvolutionApiClient::fromConfig()->markRead($extracted['number'], $extracted['message_id']);
            }
        });
    }

    /**
     * Normalize the Evolution webhook payload into a minimal shape the driver
     * can consume. Evolution's payload format varies by version; this handles
     * both the flat and nested envelopes we've seen.
     *
     * @return array{number:string, text:string, message_id:?string, push_name:?string, instance:?string}|null
     */
    protected function extractMessage(array $payload): ?array
    {
        // Ignore anything that's not a MESSAGE_UPSERT event.
        $event = $payload['event'] ?? null;
        if ($event && $event !== 'messages.upsert') {
            return null;
        }

        $data = $payload['data'] ?? $payload;

        // Ignore messages sent by us (fromMe).
        $fromMe = $data['key']['fromMe'] ?? false;
        if ($fromMe) {
            return null;
        }

        // Ignore group messages — we only intake 1:1.
        $remoteJid = $data['key']['remoteJid'] ?? null;
        if (! $remoteJid || str_contains($remoteJid, '@g.us')) {
            return null;
        }

        // Extract the plain text body, trying the shapes Evolution emits.
        $text = $data['message']['conversation']
            ?? $data['message']['extendedTextMessage']['text']
            ?? $data['text']
            ?? null;

        if (! $text || trim((string) $text) === '') {
            return null;
        }

        // Number is the remoteJid stripped of the @s.whatsapp.net suffix.
        $number = preg_replace('/@.*$/', '', (string) $remoteJid);

        return [
            'number' => (string) $number,
            'text' => (string) $text,
            'message_id' => $data['key']['id'] ?? null,
            'push_name' => $data['pushName'] ?? null,
            'instance' => $payload['instance'] ?? null,
        ];
    }
}
