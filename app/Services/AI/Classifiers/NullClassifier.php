<?php

namespace App\Services\AI\Classifiers;

use App\Services\AI\SanitizedContext;
use App\Services\AI\TicketClassification;
use App\Services\AI\TicketClassifierInterface;

/**
 * Default fallback — always returns empty classification. Used when:
 *   - No provider is configured (HELPDESK_AI_CLASSIFIER unset)
 *   - Tests want to exercise the rest of the pipeline without network
 *   - A real provider throws too often and operations temporarily
 *     swaps it in via config until the incident is resolved
 */
class NullClassifier implements TicketClassifierInterface
{
    public function classify(SanitizedContext $context, ?string $departmentPrompt = null): TicketClassification
    {
        return TicketClassification::empty('null');
    }
}
