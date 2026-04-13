<?php

namespace App\Services\AI;

/**
 * Immutable result of classifying a ticket. Classifiers produce this DTO;
 * the ClassifyTicketJob persists its fields onto hd_tickets.
 *
 * Confidence semantics: 0.0 = no opinion, 1.0 = certain. A confidence
 * below config('helpdesk.ai.apply_threshold', 0.7) means the dashboard
 * will NOT highlight the suggestion to the technician — it's still
 * stored (for analysis) but treated as low-signal.
 */
final class TicketClassification
{
    public function __construct(
        public readonly ?int $categoryId,
        public readonly ?int $priority,
        public readonly float $confidence,
        public readonly string $model,
        public readonly ?string $summary = null,
    ) {}

    /**
     * Empty classification used by NullClassifier and by the job when the
     * provider throws / is rate-limited. Signals "no AI opinion".
     */
    public static function empty(string $model = 'null'): self
    {
        return new self(
            categoryId: null,
            priority: null,
            confidence: 0.0,
            model: $model,
        );
    }

    public function isEmpty(): bool
    {
        return $this->categoryId === null && $this->priority === null && $this->confidence === 0.0;
    }

    public function toArray(): array
    {
        return [
            'category_id' => $this->categoryId,
            'priority' => $this->priority,
            'confidence' => $this->confidence,
            'model' => $this->model,
            'summary' => $this->summary,
        ];
    }
}
