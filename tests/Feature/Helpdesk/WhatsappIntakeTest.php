<?php

namespace Tests\Feature\Helpdesk;

use App\Jobs\Helpdesk\ProcessIncomingWhatsappMessageJob;
use App\Models\HdCategory;
use App\Models\HdChannel;
use App\Models\HdChatSession;
use App\Models\HdDepartment;
use App\Models\HdInteraction;
use App\Models\HdTicket;
use App\Models\HdTicketChannel;
use App\Services\HelpdeskIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class WhatsappIntakeTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $department;
    protected HdCategory $category;
    protected HdChannel $whatsappChannel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        // Remove any departments seeded by migrations so the menu state machine
        // deals only with the test fixtures we create below. Without this, the
        // driver lists several real departments and choice "1" picks the wrong one.
        HdCategory::query()->delete();
        HdDepartment::query()->delete();

        $base = $this->createHelpdeskBaseData();
        $this->department = $base['department'];
        $this->category = $base['categories'][0];

        // Keep only the first category so the category menu also has exactly one option.
        HdCategory::query()->where('id', '!=', $this->category->id)->delete();

        // The seed migration may have created an inactive whatsapp channel.
        // Reuse or create, then force it active for the test.
        $this->whatsappChannel = HdChannel::firstOrCreate(
            ['slug' => 'whatsapp'],
            [
                'name' => 'WhatsApp',
                'driver' => 'whatsapp',
                'config' => ['greeting' => 'Olá! Sou o atendimento virtual.'],
                'is_active' => true,
            ]
        );
        $this->whatsappChannel->update([
            'is_active' => true,
            'config' => ['greeting' => 'Olá! Sou o atendimento virtual.'],
        ]);

        // Make sure the fake evolution client is active so outbound calls noop.
        Config::set('services.evolution.fake', true);
    }

    // -----------------------------
    // Webhook endpoint
    // -----------------------------

    public function test_webhook_rejects_invalid_token(): void
    {
        Config::set('services.evolution.webhook_token', 'expected-secret');

        $response = $this->postJson('/api/webhooks/whatsapp/meia-sola', [
            'event' => 'messages.upsert',
        ], ['x-mercury-webhook-token' => 'wrong']);

        $response->assertStatus(401);
    }

    public function test_webhook_accepts_query_string_token_fallback(): void
    {
        Config::set('services.evolution.webhook_token', 'expected-secret');

        $tenantId = \App\Models\Tenant::query()->value('id');
        if (! $tenantId) {
            $this->markTestSkipped('No tenant available in test context.');
        }

        // No header — only the query string. Should still pass.
        $response = $this->postJson("/api/webhooks/whatsapp/{$tenantId}?token=expected-secret", [
            'event' => 'messages.upsert',
            'data' => [
                'key' => ['fromMe' => false, 'id' => 'MSG1', 'remoteJid' => '5585999999999@s.whatsapp.net'],
                'message' => ['conversation' => 'oi'],
            ],
        ]);

        $response->assertStatus(202);
    }

    public function test_webhook_rejects_unknown_tenant(): void
    {
        Config::set('services.evolution.webhook_token', null);

        $response = $this->postJson('/api/webhooks/whatsapp/nonexistent-tenant', [
            'event' => 'messages.upsert',
        ]);

        $response->assertStatus(404);
    }

    public function test_webhook_queues_job_on_valid_token(): void
    {
        Bus::fake([ProcessIncomingWhatsappMessageJob::class]);
        Config::set('services.evolution.webhook_token', 'expected-secret');

        // Ensure a tenant exists for the route parameter.
        $tenantId = \App\Models\Tenant::query()->value('id');
        if (! $tenantId) {
            $this->markTestSkipped('No tenant available in test context.');
        }

        $response = $this->postJson("/api/webhooks/whatsapp/{$tenantId}", [
            'event' => 'messages.upsert',
            'data' => [
                'key' => ['fromMe' => false, 'id' => 'MSG1', 'remoteJid' => '5585999999999@s.whatsapp.net'],
                'message' => ['conversation' => 'oi'],
                'pushName' => 'João',
            ],
        ], ['x-mercury-webhook-token' => 'expected-secret']);

        $response->assertStatus(202);
        Bus::assertDispatched(ProcessIncomingWhatsappMessageJob::class);
    }

    // -----------------------------
    // State machine
    // -----------------------------

    public function test_first_message_starts_department_menu(): void
    {
        $step = $this->intake('5585999999999', 'oi');

        $this->assertFalse($step->isComplete);
        $this->assertStringContainsString('Escolha um departamento', $step->prompt);
        $this->assertStringContainsString('1) '.$this->department->name, $step->prompt);

        $this->assertDatabaseHas('hd_chat_sessions', [
            'channel_id' => $this->whatsappChannel->id,
            'external_contact' => '5585999999999',
            'step' => 'awaiting_department',
        ]);
    }

    public function test_department_choice_advances_to_category_menu(): void
    {
        $this->intake('5585999999999', 'oi');
        $step = $this->intake('5585999999999', '1');

        $this->assertFalse($step->isComplete);
        $this->assertStringContainsString('escolha o tipo de solicitação', mb_strtolower($step->prompt));
        $this->assertStringContainsString('1) '.$this->category->name, $step->prompt);

        $session = HdChatSession::where('external_contact', '5585999999999')->first();
        $this->assertSame('awaiting_category', $session->step);
    }

    public function test_invalid_department_choice_reprompts(): void
    {
        $this->intake('5585999999999', 'oi');
        $step = $this->intake('5585999999999', 'banana');

        $this->assertFalse($step->isComplete);
        $this->assertStringContainsString('Não entendi', $step->prompt);

        $session = HdChatSession::where('external_contact', '5585999999999')->first();
        $this->assertSame('awaiting_department', $session->step);
    }

    public function test_short_description_is_rejected(): void
    {
        $this->intake('5585999999999', 'oi');
        $this->intake('5585999999999', '1');
        $this->intake('5585999999999', '1');
        $step = $this->intake('5585999999999', 'oi');

        $this->assertFalse($step->isComplete);
        $this->assertStringContainsString('muito curta', $step->prompt);
    }

    public function test_full_flow_creates_whatsapp_ticket(): void
    {
        $this->intake('5585999999999', 'oi');
        $this->intake('5585999999999', '1');
        $this->intake('5585999999999', '1');
        $step = $this->intake('5585999999999', 'Meu computador não liga desde hoje cedo, preciso de ajuda urgente.');

        $this->assertTrue($step->isComplete);
        $this->assertNotNull($step->ticketId);

        $ticket = HdTicket::findOrFail($step->ticketId);
        $this->assertSame('whatsapp', $ticket->source);
        $this->assertSame($this->department->id, $ticket->department_id);
        $this->assertSame($this->category->id, $ticket->category_id);

        $channelRow = HdTicketChannel::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($channelRow);
        $this->assertSame('5585999999999', $channelRow->external_contact);
        $this->assertSame($this->whatsappChannel->id, $channelRow->channel_id);

        // Session was cleared after completion.
        $this->assertDatabaseMissing('hd_chat_sessions', [
            'external_contact' => '5585999999999',
        ]);
    }

    public function test_reentry_with_open_ticket_appends_interaction(): void
    {
        // Create ticket via full flow
        $this->intake('5585999999999', 'oi');
        $this->intake('5585999999999', '1');
        $this->intake('5585999999999', '1');
        $first = $this->intake('5585999999999', 'Primeira mensagem com descrição longa.');

        $ticketId = $first->ticketId;
        $this->assertNotNull($ticketId);

        // New message on the same number — should append, not start over.
        $step = $this->intake('5585999999999', 'Esqueci de mencionar que é urgente.');

        $this->assertFalse($step->isComplete);
        $this->assertStringContainsString("#{$ticketId}", $step->prompt);

        $interactions = HdInteraction::where('ticket_id', $ticketId)
            ->where('type', 'comment')
            ->where('is_internal', false)
            ->get();

        $this->assertTrue($interactions->contains(fn ($i) => str_contains($i->comment, 'Esqueci de mencionar')));
    }

    public function test_closed_ticket_starts_new_flow(): void
    {
        // Create and close a ticket
        $this->intake('5585999999999', 'oi');
        $this->intake('5585999999999', '1');
        $this->intake('5585999999999', '1');
        $first = $this->intake('5585999999999', 'Descrição longa do primeiro chamado.');

        HdTicket::find($first->ticketId)->update([
            'status' => HdTicket::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        // New message must open a fresh flow, not append to the closed ticket.
        $step = $this->intake('5585999999999', 'oi de novo');

        $this->assertFalse($step->isComplete);
        $this->assertStringContainsString('Escolha um departamento', $step->prompt);
    }

    public function test_inactive_whatsapp_channel_rejects_intake(): void
    {
        $this->whatsappChannel->update(['is_active' => false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/inativo/');

        $this->intake('5585999999999', 'oi');
    }

    // -----------------------------
    // Helpers
    // -----------------------------

    protected function intake(string $contact, string $message): \App\Services\Intake\IntakeStep
    {
        return app(HelpdeskIntakeService::class)->handle('whatsapp', [
            'message' => $message,
        ], [
            'external_contact' => $contact,
            'push_name' => 'Teste',
            'instance' => 'test-instance',
        ]);
    }
}
