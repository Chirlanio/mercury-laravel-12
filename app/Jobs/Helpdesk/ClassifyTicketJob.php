<?php

namespace App\Jobs\Helpdesk;

use App\Models\HdCategory;
use App\Models\HdTicket;
use App\Services\AI\ClassifierFactory;
use App\Services\AI\PiiSanitizer;
use App\Services\AI\SanitizedContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs the AI classifier on a freshly created ticket and persists the
 * suggestion onto the ticket itself (hd_tickets.ai_* fields).
 *
 * Design notes:
 *   - Queued so the HTTP call to Groq never blocks ticket creation
 *   - Gated on hd_departments.ai_classification_enabled — departments
 *     that opt out get a fast no-op
 *   - Always runs via ClassifierFactory → NullClassifier by default, so
 *     missing env keys produce a silent no-op rather than an error
 *   - PiiSanitizer is applied to EVERY free-text field before building
 *     SanitizedContext; the classifier signature enforces this
 *   - Never throws on business conditions; only genuine bugs bubble up
 *     and get retried by the queue (default tries)
 */
class ClassifyTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public readonly int $ticketId) {}

    public function handle(ClassifierFactory $factory, PiiSanitizer $sanitizer): void
    {
        $ticket = HdTicket::with(['department', 'employee'])->find($this->ticketId);

        if (! $ticket) {
            Log::info('ClassifyTicketJob: ticket not found', ['id' => $this->ticketId]);

            return;
        }

        $department = $ticket->department;
        if (! $department || ! $department->ai_classification_enabled) {
            // Opted out at the department level — silent no-op.
            return;
        }

        // Candidate categories are the active ones of the ticket's department.
        // We only send id + name; anything else would be needless PII surface.
        $categories = HdCategory::query()
            ->where('department_id', $department->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->all();

        if (empty($categories)) {
            Log::info('ClassifyTicketJob: no categories available for department', [
                'ticket_id' => $ticket->id,
                'department_id' => $department->id,
            ]);

            return;
        }

        $context = SanitizedContext::build(
            sanitizer: $sanitizer,
            rawTitle: (string) $ticket->title,
            rawDescription: (string) $ticket->description,
            departmentName: (string) $department->name,
            categories: $categories,
            employeeFirstName: $ticket->employee?->first_name,
            storeCode: $ticket->employee?->store_id,
        );

        $classifier = $factory->make();
        $classification = $classifier->classify(
            context: $context,
            departmentPrompt: $department->ai_classification_prompt,
        );

        if ($classification->isEmpty()) {
            Log::info('ClassifyTicketJob: classifier returned empty', [
                'ticket_id' => $ticket->id,
                'model' => $classification->model,
            ]);

            return;
        }

        // Persist the AI opinion onto the ticket. We deliberately NEVER
        // touch the USER-chosen category_id or priority — the technician
        // sees the disagreement in the dashboard and decides.
        $ticket->forceFill([
            'ai_category_id' => $classification->categoryId,
            'ai_priority' => $classification->priority,
            'ai_confidence' => $classification->confidence,
            'ai_model' => $classification->model,
            'ai_classified_at' => now(),
        ])->save();
    }
}
