<?php

namespace App\Listeners\Helpdesk;

use App\Events\Helpdesk\TicketStatusChangedEvent;
use App\Jobs\Helpdesk\SendCsatSurveyJob;
use App\Models\HdSatisfactionSurvey;
use App\Models\HdTicket;

/**
 * Dispatches a CSAT survey job when a ticket transitions to RESOLVED.
 *
 * The listener runs synchronously (not queued) because all it does is
 * check the transition and enqueue a job. The actual survey delivery
 * (email or WhatsApp) happens in the queued SendCsatSurveyJob, which
 * has its own retry semantics.
 *
 * Idempotency: only one survey per ticket — if a survey already exists
 * the listener is a no-op. This handles the reopen-then-re-resolve case
 * gracefully: the first survey stays, a second isn't sent.
 */
class DispatchCsatSurveyListener
{
    public function handle(TicketStatusChangedEvent $event): void
    {
        if ($event->newStatus !== HdTicket::STATUS_RESOLVED) {
            return;
        }

        // Guard against double-dispatch if the listener fires twice for
        // the same transition (e.g. Laravel event test reruns).
        $existing = HdSatisfactionSurvey::where('ticket_id', $event->ticketId)->first();
        if ($existing) {
            return;
        }

        SendCsatSurveyJob::dispatch($event->ticketId);
    }
}
