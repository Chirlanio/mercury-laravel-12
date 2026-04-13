<?php

namespace App\Services;

use App\Models\HdChannel;
use App\Models\HdChatSession;
use App\Services\Intake\Drivers\EmailIntakeDriver;
use App\Services\Intake\Drivers\WebIntakeDriver;
use App\Services\Intake\Drivers\WhatsappIntakeDriver;
use App\Services\Intake\IntakeDriverInterface;
use App\Services\Intake\IntakeStep;

/**
 * Orchestrator for multi-channel ticket intake.
 *
 * Responsibilities:
 *   - Resolve the channel (by slug) and the session (if any).
 *   - Delegate the actual conversation to a driver implementing
 *     IntakeDriverInterface (one per channel type).
 *   - Keep the HelpdeskController thin: controllers pass a payload,
 *     this service decides how to turn it into a ticket.
 *
 * This service is intentionally unaware of HTTP. Callers can be controllers
 * (web), queue jobs (WhatsApp/email webhooks), or artisan commands (seeding).
 */
class HelpdeskIntakeService
{
    /**
     * Map of driver slug → FQCN. Extended in Phase 3 with inbox drivers.
     *
     * @var array<string, class-string<IntakeDriverInterface>>
     */
    protected array $drivers = [
        'web' => WebIntakeDriver::class,
        'whatsapp' => WhatsappIntakeDriver::class,
        'email' => EmailIntakeDriver::class,
    ];

    /**
     * Entry point. `channelSlug` resolves to an hd_channels row; the driver
     * on that row is instantiated via the container so it can declare its
     * own dependencies (HelpdeskService, Evolution client, etc).
     *
     * @param  array<string, mixed>  $payload  driver-specific input (form fields, message, webhook body)
     * @param  array<string, mixed>  $context  orchestrator-provided hints (user_id for web, inbound webhook meta, etc.)
     */
    public function handle(string $channelSlug, array $payload, array $context = []): IntakeStep
    {
        $channel = HdChannel::findBySlug($channelSlug);

        if (! $channel) {
            throw new \RuntimeException("Canal desconhecido: {$channelSlug}");
        }

        if (! $channel->is_active) {
            throw new \RuntimeException("Canal {$channelSlug} está inativo.");
        }

        $session = $this->resolveSession($channel, $context);
        $driver = $this->resolveDriver($channel->driver);

        return $driver->handle($channel, $session, $payload, $context);
    }

    /**
     * Look up an alive session for a contact on a channel, if one was provided.
     * Web intake has no session (one-shot). WhatsApp/email drivers pass the
     * external contact and get a session back for continuation.
     */
    protected function resolveSession(HdChannel $channel, array $context): ?HdChatSession
    {
        $externalContact = $context['external_contact'] ?? null;
        if (! $externalContact) {
            return null;
        }

        return HdChatSession::query()
            ->where('channel_id', $channel->id)
            ->where('external_contact', $externalContact)
            ->alive()
            ->first();
    }

    protected function resolveDriver(string $driverSlug): IntakeDriverInterface
    {
        $class = $this->drivers[$driverSlug] ?? null;
        if (! $class) {
            throw new \RuntimeException("Driver de intake desconhecido: {$driverSlug}");
        }

        return app($class);
    }
}
