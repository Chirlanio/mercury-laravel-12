<?php

namespace App\Services\Intake\Drivers;

use App\Models\HdChannel;
use App\Models\HdChatSession;
use App\Services\HelpdeskService;
use App\Services\Intake\IntakeDriverInterface;
use App\Services\Intake\IntakeStep;

/**
 * Web intake is not conversational — the form collects every required field
 * in one submit. This driver therefore produces a ticket on the first call
 * and never keeps a session. It exists mainly to keep the intake pipeline
 * uniform (same entry point for web / whatsapp / email) and to consistently
 * attach hd_ticket_channels metadata regardless of the origin.
 */
class WebIntakeDriver implements IntakeDriverInterface
{
    public function __construct(private HelpdeskService $helpdeskService) {}

    public function handle(HdChannel $channel, ?HdChatSession $session, array $payload, array $context = []): IntakeStep
    {
        $userId = $context['user_id'] ?? null;
        if (! $userId) {
            throw new \InvalidArgumentException('WebIntakeDriver requires user_id in context.');
        }

        // Map the incoming form payload onto the HelpdeskService contract.
        $data = [
            'department_id' => $payload['department_id'] ?? null,
            'category_id' => $payload['category_id'] ?? null,
            'store_id' => $payload['store_id'] ?? null,
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
            'priority' => $payload['priority'] ?? 2,
            'source' => 'web',
            'channel_id' => $channel->id,
            'channel_metadata' => [
                'ip' => $payload['ip'] ?? null,
                'user_agent' => $payload['user_agent'] ?? null,
            ],
        ];

        $ticket = $this->helpdeskService->createTicket($data, (int) $userId);

        return IntakeStep::done(
            ticketId: $ticket->id,
            prompt: "Chamado #{$ticket->id} criado com sucesso.",
            collected: [
                'title' => $ticket->title,
                'priority' => $ticket->priority,
                'department_id' => $ticket->department_id,
            ],
        );
    }
}
