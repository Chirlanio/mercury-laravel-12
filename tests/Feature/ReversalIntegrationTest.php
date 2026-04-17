<?php

namespace Tests\Feature;

use App\Enums\ReversalStatus;
use App\Enums\ReversalType;
use App\Events\ReversalStatusChanged;
use App\Listeners\NotifyReversalStakeholders;
use App\Listeners\OpenHelpdeskTicketForReversal;
use App\Models\HdDepartment;
use App\Models\HdTicket;
use App\Models\Reversal;
use App\Models\ReversalReason;
use App\Models\Store;
use App\Notifications\ReversalStatusChangedNotification;
use App\Services\ReversalTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReversalIntegrationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected ReversalReason $reason;

    protected ReversalTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja']);
        $this->reason = ReversalReason::where('code', 'FURO_ESTOQUE')->firstOrFail();
        $this->service = app(ReversalTransitionService::class);
    }

    protected function makeReversal(ReversalStatus $status = ReversalStatus::PENDING_REVERSAL): Reversal
    {
        return Reversal::create([
            'invoice_number' => 'NF-'.rand(1000, 9999),
            'store_code' => $this->store->code,
            'movement_date' => now()->toDateString(),
            'customer_name' => 'Cliente',
            'sale_total' => 500,
            'type' => ReversalType::TOTAL->value,
            'amount_original' => 500,
            'amount_reversal' => 500,
            'status' => $status->value,
            'reversal_reason_id' => $this->reason->id,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Listener: NotifyReversalStakeholders
    // ------------------------------------------------------------------

    public function test_pending_authorization_notifies_approvers(): void
    {
        Notification::fake();
        $reversal = $this->makeReversal(ReversalStatus::PENDING_REVERSAL);

        // supportUser é criador; adminUser transiciona → approvers são
        // os demais usuarios com APPROVE_REVERSALS
        $reversal->update(['created_by_user_id' => $this->supportUser->id]);

        $this->service->transition(
            $reversal,
            ReversalStatus::PENDING_AUTHORIZATION,
            $this->supportUser
        );

        // admin tem APPROVE_REVERSALS e não é o actor → deve receber
        Notification::assertSentTo($this->adminUser, ReversalStatusChangedNotification::class);
    }

    public function test_actor_is_excluded_from_notifications(): void
    {
        Notification::fake();
        $reversal = $this->makeReversal(ReversalStatus::PENDING_REVERSAL);

        $this->service->transition(
            $reversal,
            ReversalStatus::PENDING_AUTHORIZATION,
            $this->adminUser
        );

        // O actor (adminUser) nunca deve ser notificado
        Notification::assertNotSentTo($this->adminUser, ReversalStatusChangedNotification::class);
    }

    public function test_reversed_notifies_only_creator(): void
    {
        Notification::fake();

        $reversal = $this->makeReversal(ReversalStatus::PENDING_FINANCE);
        $reversal->update(['created_by_user_id' => $this->supportUser->id]);

        $this->service->transition(
            $reversal,
            ReversalStatus::REVERSED,
            $this->adminUser
        );

        Notification::assertSentTo($this->supportUser, ReversalStatusChangedNotification::class);
    }

    // ------------------------------------------------------------------
    // Listener: OpenHelpdeskTicketForReversal
    // ------------------------------------------------------------------

    public function test_helpdesk_hook_does_nothing_when_department_missing(): void
    {
        $reversal = $this->makeReversal(ReversalStatus::PENDING_REVERSAL);

        $this->service->transition(
            $reversal,
            ReversalStatus::PENDING_AUTHORIZATION,
            $this->adminUser
        );

        $this->assertNull($reversal->fresh()->helpdesk_ticket_id);
    }

    public function test_helpdesk_hook_opens_ticket_when_department_exists(): void
    {
        // Seed departamento Financeiro mínimo (sem categorias)
        $department = HdDepartment::create([
            'name' => 'Financeiro',
            'description' => 'Depto Financeiro',
            'is_active' => true,
            'auto_assign' => false,
            'requires_identification' => false,
            'ai_classification_enabled' => false,
            'sort_order' => 5,
        ]);

        $reversal = $this->makeReversal(ReversalStatus::PENDING_REVERSAL);

        $this->service->transition(
            $reversal,
            ReversalStatus::PENDING_AUTHORIZATION,
            $this->adminUser
        );

        $reversal->refresh();
        $this->assertNotNull($reversal->helpdesk_ticket_id);

        $ticket = HdTicket::find($reversal->helpdesk_ticket_id);
        $this->assertNotNull($ticket);
        $this->assertEquals($department->id, $ticket->department_id);
        $this->assertStringContainsString($reversal->invoice_number, $ticket->title);
    }

    public function test_helpdesk_hook_is_idempotent(): void
    {
        HdDepartment::create([
            'name' => 'Financeiro',
            'is_active' => true,
            'auto_assign' => false,
            'requires_identification' => false,
            'ai_classification_enabled' => false,
            'sort_order' => 5,
        ]);

        $reversal = $this->makeReversal(ReversalStatus::PENDING_REVERSAL);

        // 1ª transição para pending_authorization
        $this->service->transition(
            $reversal,
            ReversalStatus::PENDING_AUTHORIZATION,
            $this->adminUser
        );
        $firstTicketId = $reversal->fresh()->helpdesk_ticket_id;
        $this->assertNotNull($firstTicketId);

        // Volta e tenta de novo — não deve criar outro ticket
        $this->service->transition(
            $reversal->fresh(),
            ReversalStatus::PENDING_REVERSAL,
            $this->adminUser
        );
        $this->service->transition(
            $reversal->fresh(),
            ReversalStatus::PENDING_AUTHORIZATION,
            $this->adminUser
        );

        $this->assertEquals($firstTicketId, $reversal->fresh()->helpdesk_ticket_id);
        $this->assertEquals(1, HdTicket::count());
    }

    public function test_helpdesk_hook_only_triggers_on_pending_authorization(): void
    {
        HdDepartment::create([
            'name' => 'Financeiro',
            'is_active' => true,
            'auto_assign' => false,
            'requires_identification' => false,
            'ai_classification_enabled' => false,
            'sort_order' => 5,
        ]);

        $reversal = $this->makeReversal(ReversalStatus::PENDING_FINANCE);

        $this->service->transition(
            $reversal,
            ReversalStatus::REVERSED,
            $this->adminUser
        );

        $this->assertNull($reversal->fresh()->helpdesk_ticket_id);
        $this->assertEquals(0, HdTicket::count());
    }
}
