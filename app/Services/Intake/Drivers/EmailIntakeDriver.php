<?php

namespace App\Services\Intake\Drivers;

use App\Models\HdChannel;
use App\Models\HdChatSession;
use App\Services\Intake\IntakeDriverInterface;
use App\Services\Intake\IntakeStep;

/**
 * Stub — implemented in a later phase (email-to-ticket via inbound webhooks).
 */
class EmailIntakeDriver implements IntakeDriverInterface
{
    public function handle(HdChannel $channel, ?HdChatSession $session, array $payload, array $context = []): IntakeStep
    {
        throw new \RuntimeException('EmailIntakeDriver is not yet implemented.');
    }
}
