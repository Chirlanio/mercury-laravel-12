<?php

namespace App\Services\Intake;

use App\Models\HdChannel;
use App\Models\HdChatSession;

/**
 * Contract for channel-specific intake flows. Each driver owns its own
 * conversational state machine; the HelpdeskIntakeService orchestrates
 * session lookup and handoff, not the per-channel logic.
 *
 * Implementations:
 *   - WebIntakeDriver: pass-through. The form already collected everything;
 *     handle() returns immediately with a ticket.
 *   - WhatsappIntakeDriver (Phase 3): multi-step menu flow.
 *   - EmailIntakeDriver (later): subject-as-title, body-as-description.
 */
interface IntakeDriverInterface
{
    /**
     * Handle one intake turn.
     *
     * @param  HdChannel        $channel   channel config (driver, credentials)
     * @param  HdChatSession|null $session  ongoing session for this contact, or null on first contact
     * @param  array<string, mixed> $payload  driver-specific input ('message' string, uploaded file, etc.)
     * @param  array<string, mixed> $context additional context (requester_id, preselected department, etc.)
     */
    public function handle(HdChannel $channel, ?HdChatSession $session, array $payload, array $context = []): IntakeStep;
}
