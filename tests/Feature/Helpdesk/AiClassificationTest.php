<?php

namespace Tests\Feature\Helpdesk;

use App\Jobs\Helpdesk\ClassifyTicketJob;
use App\Models\HdAiClassificationCorrection;
use App\Models\HdCategory;
use App\Models\HdDepartment;
use App\Models\HdTicket;
use App\Models\User;
use App\Services\HelpdeskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AiClassificationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $department;
    protected HdCategory $category;
    protected HdCategory $otherCategory;
    protected User $technician;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $base = $this->createHelpdeskBaseData();
        $this->department = $base['department'];
        $this->category = $base['categories'][0];
        $this->otherCategory = $base['categories'][1];

        $this->technician = User::factory()->create(['role' => 'support']);
        $this->grantHelpdeskPermission($this->technician, $this->department, 'manager');
    }

    // ---------------------------------------------------------------
    // Dispatch gating
    // ---------------------------------------------------------------

    public function test_creating_ticket_dispatches_classification_job(): void
    {
        Bus::fake([ClassifyTicketJob::class]);

        app(HelpdeskService::class)->createTicket([
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'title' => 'Teste',
            'description' => 'Descrição de teste',
        ], $this->regularUser->id);

        Bus::assertDispatched(ClassifyTicketJob::class);
    }

    public function test_job_skips_when_department_ai_disabled(): void
    {
        // Default state: ai_classification_enabled = false
        $ticket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->regularUser->id,
        ]);

        config(['helpdesk.ai.classifier' => 'null']);

        (new ClassifyTicketJob($ticket->id))->handle(
            app(\App\Services\AI\ClassifierFactory::class),
            app(\App\Services\AI\PiiSanitizer::class),
        );

        $fresh = $ticket->fresh();
        $this->assertNull($fresh->ai_category_id);
        $this->assertNull($fresh->ai_confidence);
    }

    public function test_job_persists_classification_when_department_enabled(): void
    {
        $this->department->update(['ai_classification_enabled' => true]);
        config([
            'helpdesk.ai.classifier' => 'groq',
            'helpdesk.ai.groq.api_key' => 'test-key',
            'helpdesk.ai.groq.base_url' => 'https://api.groq.com/openai/v1',
            'helpdesk.ai.groq.model' => 'llama-3.3-70b-versatile',
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[ 'message' => ['content' => json_encode([
                    'category_id' => $this->otherCategory->id,
                    'priority' => 3,
                    'confidence' => 0.88,
                    'summary' => 'Sumário',
                ])]]],
            ], 200),
        ]);

        $ticket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'priority' => HdTicket::PRIORITY_MEDIUM,
            'created_by_user_id' => $this->regularUser->id,
        ]);

        (new ClassifyTicketJob($ticket->id))->handle(
            app(\App\Services\AI\ClassifierFactory::class),
            app(\App\Services\AI\PiiSanitizer::class),
        );

        $fresh = $ticket->fresh();
        $this->assertSame($this->otherCategory->id, $fresh->ai_category_id);
        $this->assertSame(3, $fresh->ai_priority);
        $this->assertEqualsWithDelta(0.88, (float) $fresh->ai_confidence, 0.01);
        $this->assertSame('llama-3.3-70b-versatile', $fresh->ai_model);
        $this->assertNotNull($fresh->ai_classified_at);

        // User's own choice is NOT touched
        $this->assertSame($this->category->id, $fresh->category_id);
        $this->assertSame(HdTicket::PRIORITY_MEDIUM, $fresh->priority);
    }

    public function test_job_no_op_when_ticket_not_found(): void
    {
        (new ClassifyTicketJob(999999))->handle(
            app(\App\Services\AI\ClassifierFactory::class),
            app(\App\Services\AI\PiiSanitizer::class),
        );

        $this->addToAssertionCount(1);
    }

    // ---------------------------------------------------------------
    // Feedback loop
    // ---------------------------------------------------------------

    public function test_changing_priority_logs_correction_when_ai_present(): void
    {
        $ticket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'priority' => HdTicket::PRIORITY_MEDIUM,
            'ai_category_id' => $this->otherCategory->id,
            'ai_priority' => 3,
            'ai_confidence' => 0.85,
            'ai_model' => 'llama-3.3-70b-versatile',
            'ai_classified_at' => now(),
            'created_by_user_id' => $this->regularUser->id,
        ]);

        $this->actingAs($this->technician)
            ->postJson(route('helpdesk.change-priority', $ticket->id), [
                'priority' => HdTicket::PRIORITY_HIGH,
            ])
            ->assertOk();

        $correction = HdAiClassificationCorrection::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($correction);
        $this->assertSame($this->otherCategory->id, $correction->original_ai_category_id);
        $this->assertSame(3, $correction->original_ai_priority);
        $this->assertSame(HdTicket::PRIORITY_HIGH, $correction->corrected_priority);
        $this->assertSame($this->technician->id, $correction->corrected_by_user_id);
    }

    public function test_changing_category_logs_correction_when_ai_present(): void
    {
        $ticket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'ai_category_id' => $this->otherCategory->id,
            'ai_priority' => 2,
            'ai_confidence' => 0.91,
            'ai_model' => 'llama-3.3-70b-versatile',
            'ai_classified_at' => now(),
            'created_by_user_id' => $this->regularUser->id,
        ]);

        $this->actingAs($this->technician)
            ->postJson(route('helpdesk.change-category', $ticket->id), [
                'category_id' => $this->otherCategory->id,
            ])
            ->assertOk();

        $correction = HdAiClassificationCorrection::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($correction);
        $this->assertSame($this->otherCategory->id, $correction->corrected_category_id);
    }

    public function test_priority_change_without_ai_does_not_log_correction(): void
    {
        $ticket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'priority' => HdTicket::PRIORITY_MEDIUM,
            // No ai_* fields set
            'created_by_user_id' => $this->regularUser->id,
        ]);

        $this->actingAs($this->technician)
            ->postJson(route('helpdesk.change-priority', $ticket->id), [
                'priority' => HdTicket::PRIORITY_HIGH,
            ])
            ->assertOk();

        $this->assertSame(0, HdAiClassificationCorrection::count());
    }

    public function test_category_change_rejects_cross_department_category(): void
    {
        $otherDept = HdDepartment::factory()->create(['name' => 'Outro']);
        $foreignCategory = HdCategory::factory()->forDepartment($otherDept)->create();

        $ticket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->regularUser->id,
        ]);

        $this->actingAs($this->technician)
            ->postJson(route('helpdesk.change-category', $ticket->id), [
                'category_id' => $foreignCategory->id,
            ])
            ->assertStatus(422);
    }
}
