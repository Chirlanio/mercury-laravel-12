<?php

namespace App\Jobs\Helpdesk;

use App\Models\Tenant;
use App\Services\HelpdeskIntakeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Normalizes a raw Postmark Inbound webhook payload into the shape that
 * EmailIntakeDriver expects, then hands it to HelpdeskIntakeService inside
 * the target tenant's context.
 *
 * Postmark's payload documentation:
 *   https://postmarkapp.com/developer/webhooks/inbound-webhook
 *
 * Key fields we consume:
 *   FromFull.{Email,Name}, ToFull[].Email, Subject,
 *   StrippedTextReply (preferred) or TextBody,
 *   MessageID, Headers[{Name,Value}], Attachments[{Name,ContentType,Content,ContentLength}]
 *
 * The queue job pattern mirrors ProcessIncomingWhatsappMessageJob: one job
 * per inbound message, tenant context initialized from $tenantId, retries
 * on transient failures. Payloads are serialized into the job so Horizon
 * keeps them on failure.
 */
class ProcessInboundEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(
        public readonly string $tenantId,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            Log::warning('Postmark inbound: tenant not found', ['tenant' => $this->tenantId]);

            return;
        }

        $normalized = $this->normalize($this->payload);
        if (! $normalized) {
            Log::info('Postmark inbound: payload could not be normalized', [
                'tenant' => $this->tenantId,
                'message_id' => $this->payload['MessageID'] ?? null,
            ]);

            return;
        }

        $tenant->run(function () use ($normalized) {
            /** @var HelpdeskIntakeService $intake */
            $intake = app(HelpdeskIntakeService::class);

            try {
                $intake->handle(
                    channelSlug: 'email',
                    payload: $normalized,
                    context: [
                        'external_contact' => $normalized['from_email'],
                        'external_id' => $normalized['message_id'],
                    ],
                );
            } catch (\Throwable $e) {
                Log::error('Postmark inbound: intake failed', [
                    'tenant' => $this->tenantId,
                    'from' => $normalized['from_email'],
                    'to' => $normalized['to_email'],
                    'subject' => $normalized['subject'],
                    'error' => $e->getMessage(),
                ]);
                throw $e; // let the queue retry
            }
        });
    }

    /**
     * Convert a raw Postmark Inbound payload into the shape the driver
     * consumes. Returns null if required fields are missing — we don't
     * want to open junk tickets for bounces and autoresponders.
     *
     * @return array{from_email:string, from_name:?string, to_email:string, subject:string, text_body:string, message_id:?string, in_reply_to:?string, references:array<int,string>, attachments:array}|null
     */
    protected function normalize(array $payload): ?array
    {
        $fromEmail = $payload['FromFull']['Email'] ?? $payload['From'] ?? null;
        $fromName = $payload['FromFull']['Name'] ?? null;

        // Postmark delivers all recipients in ToFull[]. We use the first
        // one as the routing address — if an email is sent to multiple
        // helpdesk addresses at once, the later recipients are ignored
        // and a single ticket is opened. Operators can forward internally.
        $toEmail = $payload['ToFull'][0]['Email'] ?? $payload['To'] ?? null;

        if (! $fromEmail || ! $toEmail) {
            return null;
        }

        // Prefer StrippedTextReply — it contains only the new text without
        // the quoted history. Falls back to TextBody when the email isn't
        // a reply (Postmark only fills StrippedTextReply on replies).
        $textBody = $payload['StrippedTextReply'] ?? $payload['TextBody'] ?? '';

        // Parse relevant headers into a lookup.
        $headers = [];
        foreach ((array) ($payload['Headers'] ?? []) as $h) {
            $name = $h['Name'] ?? null;
            $value = $h['Value'] ?? null;
            if ($name && $value) {
                $headers[strtolower($name)] = $value;
            }
        }

        $inReplyTo = $headers['in-reply-to'] ?? null;
        $references = [];
        if (isset($headers['references'])) {
            // The References header is a space-separated list of msg-ids.
            preg_match_all('/<[^>]+>/', (string) $headers['references'], $m);
            $references = $m[0] ?? [];
        }

        // Map Postmark's PascalCase attachment fields to the lowercase
        // shape the driver consumes. Unknown keys are dropped.
        $attachments = [];
        foreach ((array) ($payload['Attachments'] ?? []) as $att) {
            $attachments[] = [
                'name' => $att['Name'] ?? 'attachment.bin',
                'content_type' => $att['ContentType'] ?? 'application/octet-stream',
                'content' => $att['Content'] ?? '',
                'size' => (int) ($att['ContentLength'] ?? 0),
            ];
        }

        return [
            'from_email' => (string) $fromEmail,
            'from_name' => $fromName ? (string) $fromName : null,
            'to_email' => (string) $toEmail,
            'subject' => (string) ($payload['Subject'] ?? ''),
            'text_body' => (string) $textBody,
            'message_id' => isset($payload['MessageID']) ? '<'.trim($payload['MessageID'], '<>').'>' : null,
            'in_reply_to' => $inReplyTo,
            'references' => $references,
            'attachments' => $attachments,
        ];
    }
}
