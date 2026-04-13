<?php

namespace App\Services\Intake;

/**
 * Result of processing a single intake turn. Drivers return one of these
 * after consuming a message. When `isComplete` is true, a ticket has been
 * created and `ticketId` is populated.
 *
 *   - prompt: the next question to ask the contact (human-readable)
 *   - options: optional menu choices (for numeric menu flows)
 *   - collected: accumulated fields the driver captured so far
 *   - isComplete: terminal turn — ticket exists
 *   - ticketId: populated when isComplete
 */
class IntakeStep
{
    public function __construct(
        public readonly string $prompt,
        public readonly array $options = [],
        public readonly array $collected = [],
        public readonly bool $isComplete = false,
        public readonly ?int $ticketId = null,
    ) {}

    public static function ask(string $prompt, array $options = [], array $collected = []): self
    {
        return new self(prompt: $prompt, options: $options, collected: $collected);
    }

    public static function done(int $ticketId, string $prompt = 'Chamado aberto com sucesso.', array $collected = []): self
    {
        return new self(
            prompt: $prompt,
            collected: $collected,
            isComplete: true,
            ticketId: $ticketId,
        );
    }

    public function toArray(): array
    {
        return [
            'prompt' => $this->prompt,
            'options' => $this->options,
            'collected' => $this->collected,
            'is_complete' => $this->isComplete,
            'ticket_id' => $this->ticketId,
        ];
    }
}
