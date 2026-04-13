<?php

namespace Tests\Feature\Helpdesk;

use App\Jobs\Helpdesk\SendWhatsappReplyJob;
use App\Models\HdCategory;
use App\Models\HdChannel;
use App\Models\HdDepartment;
use App\Models\HdInteraction;
use App\Models\HdTicket;
use App\Models\HdTicketChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Validates the outbound pipeline: when a technician adds a public comment
 * on a WhatsApp ticket, the listener fires, qualifies the event, and
 * dispatches SendWhatsappReplyJob which calls Evolution to deliver the
 * reply to the original contact.
 */
class WhatsappOutboundTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $department;
    protected HdCategory $category;
    protected HdChannel $whatsapp;
    protected User $technician;
    protected HdTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $base = $this->createHelpdeskBaseData();
        $this->department = $base['department'];
        $this->category = $base['categories'][0];

        $this->technician = User::factory()->create(['role' => 'support']);
        $this->grantHelpdeskPermission($this->technician, $this->department, 'technician');

        $this->whatsapp = HdChannel::firstOrCreate(
            ['slug' => 'whatsapp'],
            [
                'name' => 'WhatsApp',
                'driver' => 'whatsapp',
                'is_active' => true,
            ],
        );

        // Build a whatsapp-originated ticket fixture with its channel row.
        $this->ticket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'source' => 'whatsapp',
            'created_by_user_id' => $this->regularUser->id,
        ]);

        HdTicketChannel::create([
            'ticket_id' => $this->ticket->id,
            'channel_id' => $this->whatsapp->id,
            'external_contact' => '5585987460451',
            'external_id' => 'WAMSG-INBOUND',
        ]);

        Config::set('services.evolution.fake', true);
    }

    // -----------------------------
    // Listener filters
    // -----------------------------

    public function test_public_comment_by_technician_dispatches_job(): void
    {
        Bus::fake([SendWhatsappReplyJob::class]);

        HdInteraction::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->technician->id,
            'comment' => 'Olá Maria, seu holerite já foi enviado por email.',
            'type' => 'comment',
            'is_internal' => false,
        ]);

        Bus::assertDispatched(SendWhatsappReplyJob::class);
    }

    public function test_internal_note_does_not_dispatch(): void
    {
        Bus::fake([SendWhatsappReplyJob::class]);

        HdInteraction::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->technician->id,
            'comment' => 'Nota interna, não enviar.',
            'type' => 'comment',
            'is_internal' => true,
        ]);

        Bus::assertNotDispatched(SendWhatsappReplyJob::class);
    }

    public function test_status_change_does_not_dispatch(): void
    {
        Bus::fake([SendWhatsappReplyJob::class]);

        HdInteraction::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->technician->id,
            'comment' => null,
            'type' => 'status_change',
            'old_value' => 'open',
            'new_value' => 'in_progress',
            'is_internal' => false,
        ]);

        Bus::assertNotDispatched(SendWhatsappReplyJob::class);
    }

    public function test_bot_comment_does_not_dispatch(): void
    {
        Bus::fake([SendWhatsappReplyJob::class]);

        $bot = User::create([
            'name' => 'WhatsApp Bot',
            'email' => 'whatsapp-bot@system.local',
            'password' => bcrypt('secret-does-not-matter'),
            'role' => 'user',
        ]);

        HdInteraction::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $bot->id,
            'comment' => 'Mensagem recebida pelo driver',
            'type' => 'comment',
            'is_internal' => false,
        ]);

        Bus::assertNotDispatched(SendWhatsappReplyJob::class);
    }

    public function test_non_whatsapp_ticket_does_not_dispatch(): void
    {
        Bus::fake([SendWhatsappReplyJob::class]);

        $webTicket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'source' => 'web',
            'created_by_user_id' => $this->regularUser->id,
        ]);

        HdInteraction::create([
            'ticket_id' => $webTicket->id,
            'user_id' => $this->technician->id,
            'comment' => 'Comentário num ticket web, não deve enviar pro WhatsApp.',
            'type' => 'comment',
            'is_internal' => false,
        ]);

        Bus::assertNotDispatched(SendWhatsappReplyJob::class);
    }

    public function test_ticket_without_channel_row_does_not_dispatch(): void
    {
        Bus::fake([SendWhatsappReplyJob::class]);

        // Another whatsapp-marked ticket but WITHOUT a ticket_channels row.
        // This simulates tickets imported from legacy data before the channel
        // bookkeeping existed.
        $orphan = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'source' => 'whatsapp',
            'created_by_user_id' => $this->regularUser->id,
        ]);

        HdInteraction::create([
            'ticket_id' => $orphan->id,
            'user_id' => $this->technician->id,
            'comment' => 'Órfão, sem canal.',
            'type' => 'comment',
            'is_internal' => false,
        ]);

        Bus::assertNotDispatched(SendWhatsappReplyJob::class);
    }

    // -----------------------------
    // Job execution
    // -----------------------------

    public function test_job_sends_message_and_stores_external_id(): void
    {
        // No Bus::fake — we want the job to actually run synchronously so we
        // can observe its side effects. Evolution is in fake mode.
        $interaction = HdInteraction::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->technician->id,
            'comment' => 'Seu holerite foi encaminhado.',
            'type' => 'comment',
            'is_internal' => false,
        ]);

        // Run the job manually to avoid dependency on queue drivers in tests.
        (new SendWhatsappReplyJob($interaction->id))->handle();

        $fresh = $interaction->fresh();
        $this->assertNotNull($fresh->external_id);
        $this->assertStringStartsWith('fake-', $fresh->external_id);
    }

    public function test_job_silently_returns_when_interaction_missing(): void
    {
        // Simulate a job arriving after the interaction was soft-deleted /
        // manually removed. Should not throw.
        (new SendWhatsappReplyJob(999999999))->handle();

        $this->addToAssertionCount(1);
    }

    public function test_job_failure_creates_internal_warning_note(): void
    {
        $interaction = HdInteraction::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->technician->id,
            'comment' => 'Teste de falha.',
            'type' => 'comment',
            'is_internal' => false,
        ]);

        (new SendWhatsappReplyJob($interaction->id))->failed(new \RuntimeException('Evolution offline'));

        $warning = HdInteraction::where('ticket_id', $this->ticket->id)
            ->where('is_internal', true)
            ->where('comment', 'like', '%Falha ao enviar%')
            ->first();

        $this->assertNotNull($warning);
        $this->assertStringContainsString('Evolution offline', $warning->comment);
    }
}
