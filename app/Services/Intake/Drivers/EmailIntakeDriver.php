<?php

namespace App\Services\Intake\Drivers;

use App\Models\HdAttachment;
use App\Models\HdChannel;
use App\Models\HdChatSession;
use App\Models\HdInteraction;
use App\Models\HdTicket;
use App\Models\User;
use App\Services\HelpdeskService;
use App\Services\Intake\IntakeDriverInterface;
use App\Services\Intake\IntakeStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Email-to-ticket driver for Postmark Inbound webhooks.
 *
 * Expected payload shape (normalized by ProcessInboundEmailJob from the raw
 * Postmark payload, so the driver stays independent of the provider):
 *
 *   [
 *     'from_email'    => 'user@example.com',
 *     'from_name'     => 'John Doe'|null,
 *     'to_email'      => 'ti@helpdesk.meiasola.com.br',
 *     'subject'       => 'original subject line',
 *     'text_body'     => 'plain text, already stripped of quoted history when possible',
 *     'message_id'    => '<abc@postmark>',
 *     'in_reply_to'   => '<xyz@...>'|null,
 *     'references'    => ['<a@...>', '<b@...>'],  // zero or more
 *     'attachments'   => [
 *        ['name'=>'photo.jpg', 'content_type'=>'image/jpeg', 'content'=>'base64...', 'size'=>12345],
 *        ...
 *     ],
 *   ]
 *
 * Thread continuity — two strategies, in order:
 *   1. `[#ID]` token in subject (primary). Simple, survives MUA rewrites.
 *   2. `In-Reply-To` or any `References` message-id matched against
 *      `hd_interactions.external_id`. This catches replies to outbound
 *      notifications once the notification pipeline stamps its own
 *      message-id into the interaction it created.
 *
 * Department routing: `to_email` is matched against `channel->config.addresses`
 * (a map of lowercased addresses → department_id). If the recipient is not
 * mapped, we fall back to `channel->config.default_department_id`. If that is
 * also null, the driver throws — the webhook job catches the throw and logs
 * it so the operator can fix the channel config.
 *
 * Requester resolution: the `from_email` is matched against `users.email`.
 * On hit, the user becomes the ticket's `requester_id`. On miss, we fall back
 * to a system bot user (`email-bot@system.local`), matching the WhatsApp
 * driver's philosophy — the ticket still opens so operators can triage it.
 *
 * Attachments: decoded from base64 and written under
 * `helpdesk/tickets/{ticket_id}/` on the `local` disk (same path as the web
 * upload flow). Oversized attachments (default >10 MB) are skipped with a
 * note in the ticket so operators know to ask the user to resend.
 *
 * The driver never "replies" in-band — email replies go out through the
 * existing notification pipeline. IntakeStep::done is returned so the
 * orchestrator and tests can read the ticket id.
 */
class EmailIntakeDriver implements IntakeDriverInterface
{
    /** Default cap per attachment in bytes (10 MB) if channel config omits it. */
    protected const DEFAULT_MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    public function __construct(private HelpdeskService $helpdeskService) {}

    public function handle(HdChannel $channel, ?HdChatSession $session, array $payload, array $context = []): IntakeStep
    {
        $fromEmail = $this->normalizeEmail($payload['from_email'] ?? null);
        $toEmail = $this->normalizeEmail($payload['to_email'] ?? null);

        if (! $fromEmail) {
            throw new \InvalidArgumentException('EmailIntakeDriver: payload is missing from_email.');
        }
        if (! $toEmail) {
            throw new \InvalidArgumentException('EmailIntakeDriver: payload is missing to_email.');
        }

        $subject = trim((string) ($payload['subject'] ?? ''));
        $body = trim((string) ($payload['text_body'] ?? ''));

        // Postmark sometimes delivers an empty text body (HTML-only email).
        // Rather than create an empty ticket, stamp a placeholder so the
        // technician at least sees the subject.
        if ($body === '') {
            $body = '(mensagem vazia — ver anexos ou corpo HTML original)';
        }

        $messageId = $payload['message_id'] ?? null;
        $inReplyTo = $payload['in_reply_to'] ?? null;
        $references = (array) ($payload['references'] ?? []);
        $attachments = (array) ($payload['attachments'] ?? []);

        // 1) Try to resolve an existing ticket before creating a new one.
        $existing = $this->findExistingTicket($subject, $inReplyTo, $references);
        if ($existing) {
            return $this->appendReplyToTicket(
                ticket: $existing,
                fromEmail: $fromEmail,
                body: $body,
                messageId: $messageId,
                attachments: $attachments,
                channel: $channel,
            );
        }

        // 2) No existing ticket — create a fresh one. Route by recipient.
        $departmentId = $this->resolveDepartmentId($channel, $toEmail);
        $requesterId = $this->resolveRequesterId($fromEmail);

        return $this->createTicketFromEmail(
            channel: $channel,
            departmentId: $departmentId,
            requesterId: $requesterId,
            fromEmail: $fromEmail,
            fromName: $payload['from_name'] ?? null,
            subject: $subject,
            body: $body,
            messageId: $messageId,
            attachments: $attachments,
        );
    }

    // ------------------------------------------------------------------
    // Thread continuity
    // ------------------------------------------------------------------

    /**
     * Find an existing (non-terminal) ticket this email should append to.
     * Returns null when the email should open a new ticket.
     */
    protected function findExistingTicket(string $subject, ?string $inReplyTo, array $references): ?HdTicket
    {
        // Strategy 1: [#ID] token in subject. We accept both '[#1234]' and
        // '#1234' so replies from MUAs that strip the square brackets still
        // work.
        if ($subject !== '' && preg_match('/(?:\[#|#)(\d+)\]?/', $subject, $m)) {
            $ticket = HdTicket::find((int) $m[1]);
            if ($ticket && ! in_array($ticket->status, HdTicket::TERMINAL_STATUSES, true)) {
                return $ticket;
            }
        }

        // Strategy 2: In-Reply-To header, matched against any interaction
        // we've previously stamped with external_id. This is how replies to
        // outbound notifications get attached.
        $candidateIds = array_filter(array_merge([$inReplyTo], $references));
        if (! empty($candidateIds)) {
            $interaction = HdInteraction::query()
                ->whereIn('external_id', $candidateIds)
                ->latest('id')
                ->first();

            if ($interaction) {
                $ticket = $interaction->ticket;
                if ($ticket && ! in_array($ticket->status, HdTicket::TERMINAL_STATUSES, true)) {
                    return $ticket;
                }
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Append path — reply to an existing ticket
    // ------------------------------------------------------------------

    protected function appendReplyToTicket(
        HdTicket $ticket,
        string $fromEmail,
        string $body,
        ?string $messageId,
        array $attachments,
        HdChannel $channel,
    ): IntakeStep {
        $userId = $this->resolveRequesterId($fromEmail);

        DB::transaction(function () use ($ticket, $userId, $body, $messageId, $attachments, $channel) {
            $interaction = HdInteraction::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'comment' => $body,
                'type' => 'comment',
                'external_id' => $messageId,
                'is_internal' => false,
            ]);

            $this->importAttachments($ticket, $interaction->id, $userId, $attachments, $channel);
        });

        return IntakeStep::done(
            ticketId: $ticket->id,
            prompt: "Resposta anexada ao chamado #{$ticket->id}.",
            collected: [
                'thread_action' => 'append',
            ],
        );
    }

    // ------------------------------------------------------------------
    // Create path — open a new ticket
    // ------------------------------------------------------------------

    protected function createTicketFromEmail(
        HdChannel $channel,
        int $departmentId,
        int $requesterId,
        string $fromEmail,
        ?string $fromName,
        string $subject,
        string $body,
        ?string $messageId,
        array $attachments,
    ): IntakeStep {
        // Strip common reply/forward prefixes and any [#ID] residue so the
        // ticket title reads cleanly.
        $title = $this->cleanSubject($subject);
        if ($title === '') {
            $title = 'E-mail sem assunto';
        }
        // hd_tickets.title is varchar(80) — truncate to match the WhatsApp driver.
        $title = mb_substr($title, 0, 80);

        $data = [
            'department_id' => $departmentId,
            'title' => $title,
            'description' => $body,
            'priority' => HdTicket::PRIORITY_MEDIUM,
            'source' => 'email',
            'channel_id' => $channel->id,
            'external_contact' => $fromEmail,
            'external_id' => $messageId,
            'channel_metadata' => [
                'from_name' => $fromName,
                'to_email' => $this->resolveToEmailFromChannel($channel, $departmentId),
            ],
        ];

        $ticket = DB::transaction(function () use ($data, $requesterId, $attachments, $channel, $messageId) {
            $ticket = $this->helpdeskService->createTicket($data, $requesterId);

            // Stamp the inbound message-id onto the creation interaction so
            // subsequent in-thread replies can find this ticket via
            // In-Reply-To. The initial interaction logged by createTicket is
            // the most recent one on the ticket at this point.
            if ($messageId) {
                HdInteraction::where('ticket_id', $ticket->id)
                    ->latest('id')
                    ->limit(1)
                    ->update(['external_id' => $messageId]);
            }

            // Import attachments into the initial interaction.
            $initialInteractionId = HdInteraction::where('ticket_id', $ticket->id)
                ->latest('id')
                ->value('id');

            $this->importAttachments($ticket, $initialInteractionId, $requesterId, $attachments, $channel);

            return $ticket;
        });

        return IntakeStep::done(
            ticketId: $ticket->id,
            prompt: "Chamado #{$ticket->id} criado a partir do e-mail.",
            collected: [
                'department_id' => $departmentId,
                'thread_action' => 'create',
            ],
        );
    }

    // ------------------------------------------------------------------
    // Attachments
    // ------------------------------------------------------------------

    /**
     * Decode and persist each attachment. Silently skips items that exceed
     * the per-file size cap — a best-effort note is dropped on the ticket
     * so the technician can ask the user to resend.
     *
     * @param  array<int, array{name?: string, content_type?: string, content?: string, size?: int}>  $attachments
     */
    protected function importAttachments(
        HdTicket $ticket,
        ?int $interactionId,
        int $userId,
        array $attachments,
        HdChannel $channel,
    ): void {
        if (empty($attachments)) {
            return;
        }

        $maxBytes = $this->maxAttachmentBytes($channel);
        $skipped = [];

        foreach ($attachments as $att) {
            $name = (string) ($att['name'] ?? 'attachment.bin');
            $contentB64 = (string) ($att['content'] ?? '');
            if ($contentB64 === '') {
                continue;
            }

            $decoded = base64_decode($contentB64, true);
            if ($decoded === false) {
                Log::warning('EmailIntakeDriver: skipping attachment with invalid base64', [
                    'ticket_id' => $ticket->id,
                    'name' => $name,
                ]);

                continue;
            }

            $size = strlen($decoded);
            if ($size > $maxBytes) {
                $skipped[] = sprintf('%s (%s)', $name, $this->humanBytes($size));

                continue;
            }

            $storedName = Str::random(40).'_'.Str::slug(pathinfo($name, PATHINFO_FILENAME)).'.'.pathinfo($name, PATHINFO_EXTENSION);
            $path = "helpdesk/tickets/{$ticket->id}/{$storedName}";

            Storage::disk('local')->put($path, $decoded);

            HdAttachment::create([
                'ticket_id' => $ticket->id,
                'interaction_id' => $interactionId,
                'original_filename' => $name,
                'stored_filename' => $storedName,
                'file_path' => $path,
                'mime_type' => $att['content_type'] ?? 'application/octet-stream',
                'size_bytes' => $size,
                'uploaded_by_user_id' => $userId,
            ]);
        }

        if (! empty($skipped)) {
            HdInteraction::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'comment' => "⚠ Anexos ignorados por excederem o limite de ".$this->humanBytes($maxBytes).": ".implode(', ', $skipped),
                'type' => 'comment',
                'is_internal' => true,
            ]);
        }
    }

    protected function maxAttachmentBytes(HdChannel $channel): int
    {
        $mb = (int) ($channel->config['max_attachment_size_mb'] ?? 0);

        return $mb > 0 ? $mb * 1024 * 1024 : self::DEFAULT_MAX_ATTACHMENT_BYTES;
    }

    protected function humanBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' MB';
        }

        return round($bytes / 1024).' KB';
    }

    // ------------------------------------------------------------------
    // Department / requester resolution
    // ------------------------------------------------------------------

    /**
     * Match the recipient address against the channel's address → department
     * map, then fall back to default_department_id. Throws when neither is
     * available so the operator sees the misconfiguration early.
     */
    protected function resolveDepartmentId(HdChannel $channel, string $toEmail): int
    {
        $config = $channel->config ?? [];

        $addresses = [];
        foreach ((array) ($config['addresses'] ?? []) as $addr => $deptId) {
            $addresses[$this->normalizeEmail($addr) ?? strtolower($addr)] = (int) $deptId;
        }

        if (isset($addresses[$toEmail])) {
            return $addresses[$toEmail];
        }

        $default = $config['default_department_id'] ?? null;
        if ($default) {
            return (int) $default;
        }

        throw new \RuntimeException(
            "EmailIntakeDriver: no department mapped for '{$toEmail}' and no default_department_id set on the email channel. ".
            'Configure hd_channels.config.addresses or .default_department_id.'
        );
    }

    /**
     * Try to find a Mercury user for this sender address. On miss, return
     * the shared system-bot user id so the ticket still opens.
     */
    protected function resolveRequesterId(string $fromEmail): int
    {
        $user = User::where('email', $fromEmail)->first();
        if ($user) {
            return $user->id;
        }

        return $this->systemBotUserId();
    }

    protected function systemBotUserId(): int
    {
        $bot = User::where('email', 'email-bot@system.local')->first();
        if ($bot) {
            return $bot->id;
        }

        return User::create([
            'name' => 'Email Bot',
            'email' => 'email-bot@system.local',
            'password' => bcrypt(bin2hex(random_bytes(16))),
            'role' => 'user',
        ])->id;
    }

    /**
     * Reverse-lookup: given the department we routed to, what address in the
     * channel config points at it? Used only for metadata bookkeeping.
     */
    protected function resolveToEmailFromChannel(HdChannel $channel, int $departmentId): ?string
    {
        foreach ((array) ($channel->config['addresses'] ?? []) as $addr => $deptId) {
            if ((int) $deptId === $departmentId) {
                return $this->normalizeEmail($addr);
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function normalizeEmail(?string $email): ?string
    {
        if (! $email) {
            return null;
        }
        $trim = trim($email);
        if ($trim === '') {
            return null;
        }

        return mb_strtolower($trim);
    }

    /**
     * Strip Re:/Fwd: prefixes and [#ID] tokens for a clean ticket title.
     */
    protected function cleanSubject(string $subject): string
    {
        $subject = trim($subject);
        // Strip [#1234] or #1234 — must match the same regex used by the
        // thread resolver above so we don't leave artifacts in the title.
        $subject = preg_replace('/\s*\[?#\d+\]?\s*/', ' ', $subject);
        // Strip reply/forward prefixes (up to 3 levels, any combination).
        for ($i = 0; $i < 3; $i++) {
            $subject = preg_replace('/^\s*(?:Re|RE|Res|Fw|Fwd|FW|FWD|Enc|ENC)\s*:\s*/i', '', $subject, 1);
        }

        return trim((string) $subject);
    }
}
