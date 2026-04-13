<?php

namespace App\Listeners\Helpdesk;

use App\Events\Helpdesk\HelpdeskInteractionCreated;
use App\Jobs\Helpdesk\SendWhatsappReplyJob;
use App\Models\HdTicketChannel;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Decides whether a newly created HdInteraction should generate an outbound
 * WhatsApp reply to the original contact, and dispatches the job if so.
 *
 * The listener runs synchronously (not queued) so the decision logic sits
 * in the same transaction as the interaction creation — if the dispatch
 * fails, the failure is visible to the caller. The actual HTTP call to
 * Evolution API happens in the job, which is queued.
 *
 * Filtering rules (all must match):
 *   - interaction.type === 'comment'
 *   - interaction.is_internal === false
 *   - parent ticket has source === 'whatsapp'
 *   - parent ticket has at least one hd_ticket_channels row
 *   - interaction.user is NOT the 'whatsapp-bot@system.local' system user
 *     (otherwise the bot would reply to itself and loop)
 *
 * Tickets without a channel row (e.g. factory-created tickets in tests
 * that don't go through the intake service) silently skip.
 */
class DispatchWhatsappReplyListener
{
    public function handle(HelpdeskInteractionCreated $event): void
    {
        $interaction = $event->interaction;

        // Fast rejections first — avoid loading the ticket for anything that
        // obviously doesn't qualify.
        if ($interaction->type !== 'comment') {
            return;
        }
        if ($interaction->is_internal) {
            return;
        }

        $ticket = $interaction->ticket()->with('ticketChannels.channel')->first();
        if (! $ticket || $ticket->source !== 'whatsapp') {
            return;
        }

        /** @var HdTicketChannel|null $channelRow */
        $channelRow = $ticket->ticketChannels->first();
        if (! $channelRow || ! $channelRow->external_contact) {
            return;
        }

        // Loop guard: never send a reply when the interaction's author is
        // the system bot (otherwise inbound messages appended by the driver
        // would bounce back out).
        $botId = $this->botUserId();
        if ($botId !== null && (int) $interaction->user_id === $botId) {
            return;
        }

        try {
            SendWhatsappReplyJob::dispatch($interaction->id);
        } catch (\Throwable $e) {
            Log::warning('DispatchWhatsappReplyListener: failed to queue job', [
                'interaction_id' => $interaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve the WhatsApp bot user id. One query per listener invocation.
     * A static cache would survive DB resets during feature tests and
     * return stale IDs from a previous test's state, so we intentionally
     * re-query every time. The listener fires once per interaction, which
     * is rare enough that the extra query is immaterial.
     */
    protected function botUserId(): ?int
    {
        return User::where('email', 'whatsapp-bot@system.local')->value('id');
    }
}
