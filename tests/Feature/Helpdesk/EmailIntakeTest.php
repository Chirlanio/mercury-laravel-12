<?php

namespace Tests\Feature\Helpdesk;

use App\Jobs\Helpdesk\ProcessInboundEmailJob;
use App\Models\HdAttachment;
use App\Models\HdCategory;
use App\Models\HdChannel;
use App\Models\HdDepartment;
use App\Models\HdInteraction;
use App\Models\HdTicket;
use App\Models\HdTicketChannel;
use App\Models\User;
use App\Services\HelpdeskIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Coverage for the email intake pipeline:
 *
 *   - Postmark webhook authentication + queuing
 *   - Normalization of Postmark payload shape into driver payload
 *   - EmailIntakeDriver ticket creation, reply append (subject token + header),
 *     department routing, requester resolution, attachment import.
 *
 * The webhook endpoint tests use Bus::fake so we only assert dispatch.
 * The driver tests call HelpdeskIntakeService directly with a pre-normalized
 * payload to keep the domain logic isolated from Postmark quirks.
 */
class EmailIntakeTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $deptTi;
    protected HdDepartment $deptRh;
    protected HdCategory $category;
    protected HdChannel $emailChannel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        HdCategory::query()->delete();
        HdDepartment::query()->delete();

        $base = $this->createHelpdeskBaseData();
        $this->deptTi = $base['department'];
        $this->category = $base['categories'][0];
        HdCategory::query()->where('id', '!=', $this->category->id)->delete();

        $this->deptRh = HdDepartment::factory()->create([
            'name' => 'RH',
            'sort_order' => 2,
        ]);

        // The seed migration may or may not have created the email channel
        // depending on order in the test database. Either way, make sure it
        // exists with our routing config.
        $this->emailChannel = HdChannel::firstOrCreate(
            ['slug' => 'email'],
            [
                'name' => 'E-mail',
                'driver' => 'email',
                'is_active' => true,
                'config' => [],
            ],
        );
        $this->emailChannel->update([
            'is_active' => true,
            'config' => [
                'addresses' => [
                    'ti@helpdesk.test' => $this->deptTi->id,
                    'rh@helpdesk.test' => $this->deptRh->id,
                ],
                'default_department_id' => $this->deptTi->id,
                'max_attachment_size_mb' => 10,
            ],
        ]);

        Storage::fake('local');
    }

    // ---------------------------------------------------------------
    // Webhook endpoint
    // ---------------------------------------------------------------

    public function test_webhook_rejects_invalid_token(): void
    {
        Config::set('services.postmark_inbound.webhook_token', 'expected-secret');

        $response = $this->postJson('/api/webhooks/helpdesk/email/meia-sola', [
            'FromFull' => ['Email' => 'x@test.com'],
            'ToFull' => [['Email' => 'ti@helpdesk.test']],
            'Subject' => 'Hi',
            'TextBody' => 'Hello',
        ], ['x-mercury-webhook-token' => 'wrong']);

        $response->assertStatus(401);
    }

    public function test_webhook_rejects_unknown_tenant(): void
    {
        Config::set('services.postmark_inbound.webhook_token', null);

        $response = $this->postJson('/api/webhooks/helpdesk/email/nonexistent', [
            'FromFull' => ['Email' => 'x@test.com'],
            'ToFull' => [['Email' => 'ti@helpdesk.test']],
        ]);

        $response->assertStatus(404);
    }

    public function test_webhook_queues_job_on_valid_token(): void
    {
        Bus::fake([ProcessInboundEmailJob::class]);
        Config::set('services.postmark_inbound.webhook_token', 'expected-secret');

        $tenantId = \App\Models\Tenant::query()->value('id');
        if (! $tenantId) {
            $this->markTestSkipped('No tenant available in test context.');
        }

        $response = $this->postJson("/api/webhooks/helpdesk/email/{$tenantId}", [
            'FromFull' => ['Email' => 'user@example.com', 'Name' => 'User'],
            'ToFull' => [['Email' => 'ti@helpdesk.test']],
            'Subject' => 'Impressora não imprime',
            'TextBody' => 'A impressora do meu setor parou de imprimir.',
            'MessageID' => 'pm-abc-123',
        ], ['x-mercury-webhook-token' => 'expected-secret']);

        $response->assertStatus(202);
        Bus::assertDispatched(ProcessInboundEmailJob::class);
    }

    public function test_webhook_accepts_basic_auth_username(): void
    {
        Bus::fake([ProcessInboundEmailJob::class]);
        Config::set('services.postmark_inbound.webhook_token', 'expected-secret');

        $tenantId = \App\Models\Tenant::query()->value('id');
        if (! $tenantId) {
            $this->markTestSkipped('No tenant available in test context.');
        }

        $response = $this->postJson("/api/webhooks/helpdesk/email/{$tenantId}", [
            'FromFull' => ['Email' => 'x@test.com'],
            'ToFull' => [['Email' => 'ti@helpdesk.test']],
            'TextBody' => 'x',
        ], [
            // Basic auth username "expected-secret" (pass ignored)
            'Authorization' => 'Basic '.base64_encode('expected-secret:ignored'),
        ]);

        $response->assertStatus(202);
    }

    // ---------------------------------------------------------------
    // Driver — ticket creation
    // ---------------------------------------------------------------

    public function test_routes_to_department_by_recipient_address(): void
    {
        $this->intake([
            'from_email' => 'anon@acme.com',
            'to_email' => 'rh@helpdesk.test',
            'subject' => 'Férias',
            'text_body' => 'Gostaria de solicitar férias para janeiro.',
            'message_id' => '<m1@postmark>',
        ]);

        $ticket = HdTicket::latest('id')->first();
        $this->assertNotNull($ticket);
        $this->assertSame($this->deptRh->id, $ticket->department_id);
        $this->assertSame('email', $ticket->source);
        $this->assertSame('Férias', $ticket->title);
    }

    public function test_falls_back_to_default_department_when_recipient_unmapped(): void
    {
        $this->intake([
            'from_email' => 'anon@acme.com',
            'to_email' => 'unknown@helpdesk.test',
            'subject' => 'Qualquer coisa',
            'text_body' => 'Teste de rota padrão.',
            'message_id' => '<m2@postmark>',
        ]);

        $ticket = HdTicket::latest('id')->first();
        $this->assertSame($this->deptTi->id, $ticket->department_id);
    }

    public function test_throws_when_no_default_department_and_unmapped_recipient(): void
    {
        $this->emailChannel->update([
            'config' => [
                'addresses' => ['ti@helpdesk.test' => $this->deptTi->id],
                'default_department_id' => null,
            ],
        ]);

        $this->expectException(\RuntimeException::class);

        $this->intake([
            'from_email' => 'anon@acme.com',
            'to_email' => 'unmapped@helpdesk.test',
            'subject' => 'x',
            'text_body' => 'y',
            'message_id' => '<m3@postmark>',
        ]);
    }

    public function test_matches_requester_by_email_when_user_exists(): void
    {
        $user = User::factory()->create(['email' => 'known@company.com']);

        $this->intake([
            'from_email' => 'known@company.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => 'Problema no PC',
            'text_body' => 'Não liga.',
            'message_id' => '<m4@postmark>',
        ]);

        $ticket = HdTicket::latest('id')->first();
        $this->assertSame($user->id, $ticket->requester_id);
    }

    public function test_falls_back_to_bot_user_when_sender_unknown(): void
    {
        $this->intake([
            'from_email' => 'stranger@outside.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => 'Sou novo aqui',
            'text_body' => 'Preciso de ajuda.',
            'message_id' => '<m5@postmark>',
        ]);

        $ticket = HdTicket::latest('id')->first();
        $this->assertNotNull($ticket->requester_id);
        $bot = User::where('email', 'email-bot@system.local')->first();
        $this->assertNotNull($bot);
        $this->assertSame($bot->id, $ticket->requester_id);
    }

    public function test_strips_reply_prefixes_and_id_token_from_title(): void
    {
        $this->intake([
            'from_email' => 'user@test.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => 'Re: Fwd: [#999] Impressora nova',
            'text_body' => 'Detalhes do pedido.',
            'message_id' => '<m6@postmark>',
        ]);

        $ticket = HdTicket::latest('id')->first();
        // Note: #999 doesn't exist as a ticket, so this opens a NEW ticket
        // and the title has the prefix + token stripped.
        $this->assertSame('Impressora nova', $ticket->title);
    }

    // ---------------------------------------------------------------
    // Driver — thread continuation
    // ---------------------------------------------------------------

    public function test_reply_with_id_token_appends_to_existing_ticket(): void
    {
        // Create the original ticket through the intake pipeline.
        $this->intake([
            'from_email' => 'user@test.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => 'Problema X',
            'text_body' => 'Descrição inicial.',
            'message_id' => '<original@postmark>',
        ]);
        $original = HdTicket::latest('id')->first();

        $this->intake([
            'from_email' => 'user@test.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => "Re: [#{$original->id}] Problema X",
            'text_body' => 'Segue mais uma informação.',
            'message_id' => '<reply-1@postmark>',
        ]);

        // No new ticket was created
        $this->assertSame(1, HdTicket::count());

        // The reply was appended as an interaction
        $this->assertTrue(
            HdInteraction::where('ticket_id', $original->id)
                ->where('comment', 'Segue mais uma informação.')
                ->exists(),
        );
    }

    public function test_reply_by_in_reply_to_header_also_appends(): void
    {
        $this->intake([
            'from_email' => 'user@test.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => 'Primeiro email',
            'text_body' => 'Corpo inicial.',
            'message_id' => '<first@postmark>',
        ]);
        $original = HdTicket::latest('id')->first();

        // Reply with NO subject token but a matching In-Reply-To header.
        // Should still find the ticket through hd_interactions.external_id.
        $this->intake([
            'from_email' => 'user@test.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => 'Coisa totalmente diferente',
            'text_body' => 'Resposta via thread header.',
            'message_id' => '<reply-header@postmark>',
            'in_reply_to' => '<first@postmark>',
        ]);

        $this->assertSame(1, HdTicket::count());
        $this->assertTrue(
            HdInteraction::where('ticket_id', $original->id)
                ->where('comment', 'Resposta via thread header.')
                ->exists(),
        );
    }

    public function test_reply_to_closed_ticket_opens_new_ticket(): void
    {
        $this->intake([
            'from_email' => 'user@test.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => 'Antigo',
            'text_body' => 'Descrição.',
            'message_id' => '<old@postmark>',
        ]);
        $old = HdTicket::latest('id')->first();
        $old->update(['status' => HdTicket::STATUS_CLOSED, 'resolved_at' => now(), 'closed_at' => now()]);

        $this->intake([
            'from_email' => 'user@test.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => "Re: [#{$old->id}] Antigo",
            'text_body' => 'Reabrindo discussão.',
            'message_id' => '<reopen@postmark>',
        ]);

        // Terminal status → new ticket, not an append
        $this->assertSame(2, HdTicket::count());
    }

    // ---------------------------------------------------------------
    // Driver — attachments
    // ---------------------------------------------------------------

    public function test_attachments_are_imported_under_size_limit(): void
    {
        $smallContent = str_repeat('A', 1024); // 1 KB
        $b64 = base64_encode($smallContent);

        $this->intake([
            'from_email' => 'user@test.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => 'Com anexo',
            'text_body' => 'Segue foto.',
            'message_id' => '<att-1@postmark>',
            'attachments' => [
                [
                    'name' => 'foto.jpg',
                    'content_type' => 'image/jpeg',
                    'content' => $b64,
                    'size' => 1024,
                ],
            ],
        ]);

        $ticket = HdTicket::latest('id')->first();
        $this->assertSame(1, HdAttachment::where('ticket_id', $ticket->id)->count());

        $attachment = HdAttachment::where('ticket_id', $ticket->id)->first();
        $this->assertSame('foto.jpg', $attachment->original_filename);
        $this->assertSame('image/jpeg', $attachment->mime_type);
        $this->assertSame(1024, $attachment->size_bytes);
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_oversized_attachments_are_skipped_with_internal_note(): void
    {
        // Shrink the cap to 1 KB so we can exercise the oversize path with
        // a tiny payload — otherwise the real 10 MB default forces us to
        // allocate >15 MB for base64 + decoded bytes + strings, which is
        // wasteful in a test process that already holds the full suite.
        $this->emailChannel->update([
            'config' => array_merge($this->emailChannel->config, [
                'max_attachment_size_mb' => 0, // invalid → driver uses default
            ]),
        ]);
        // Use a cap of ~1 KB via a custom config we pass through — easier:
        // send a payload that's just above the tiny custom cap we set below.
        $this->emailChannel->update([
            'config' => array_merge($this->emailChannel->config, [
                // 1 KB cap — we use an int, and the driver multiplies by 1024*1024
                // so we can't go below 1 MB via the public knob. Instead, just
                // use a 2 MB payload against the default 1 MB-ish custom cap by
                // setting max_attachment_size_mb to 1. That's 1 MB * 1024*1024 cap.
                'max_attachment_size_mb' => 1,
            ]),
        ]);

        // 2 MB payload — over the 1 MB cap we just set. Still small enough
        // to not blow the test process's memory budget.
        $large = str_repeat('X', 2 * 1024 * 1024);
        $b64 = base64_encode($large);

        $this->intake([
            'from_email' => 'user@test.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => 'Anexo gigante',
            'text_body' => 'Veja o arquivo.',
            'message_id' => '<att-big@postmark>',
            'attachments' => [
                ['name' => 'huge.zip', 'content_type' => 'application/zip', 'content' => $b64],
            ],
        ]);

        $ticket = HdTicket::latest('id')->first();
        $this->assertSame(0, HdAttachment::where('ticket_id', $ticket->id)->count());

        // An internal note warning the operator should exist
        $this->assertTrue(
            HdInteraction::where('ticket_id', $ticket->id)
                ->where('is_internal', true)
                ->where('comment', 'like', '%Anexos ignorados%')
                ->exists(),
        );
    }

    // ---------------------------------------------------------------
    // Driver — metadata
    // ---------------------------------------------------------------

    public function test_creates_ticket_channel_row_with_external_id(): void
    {
        $this->intake([
            'from_email' => 'user@test.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => 'Metadados',
            'text_body' => 'Teste.',
            'message_id' => '<meta@postmark>',
        ]);

        $ticket = HdTicket::latest('id')->first();
        $tc = HdTicketChannel::where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($tc);
        $this->assertSame($this->emailChannel->id, $tc->channel_id);
        $this->assertSame('user@test.com', $tc->external_contact);
        $this->assertSame('<meta@postmark>', $tc->external_id);
    }

    public function test_empty_body_is_replaced_with_placeholder(): void
    {
        $this->intake([
            'from_email' => 'user@test.com',
            'to_email' => 'ti@helpdesk.test',
            'subject' => 'Só o assunto',
            'text_body' => '',
            'message_id' => '<empty@postmark>',
        ]);

        $ticket = HdTicket::latest('id')->first();
        $this->assertStringContainsString('(mensagem vazia', $ticket->description);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Invoke HelpdeskIntakeService with an email payload. The input is the
     * same pre-normalized shape the driver expects (i.e. the shape the job
     * produces from the raw Postmark webhook).
     */
    protected function intake(array $payload): \App\Services\Intake\IntakeStep
    {
        $payload = array_merge([
            'from_name' => null,
            'in_reply_to' => null,
            'references' => [],
            'attachments' => [],
        ], $payload);

        return app(HelpdeskIntakeService::class)->handle('email', $payload, [
            'external_contact' => $payload['from_email'],
            'external_id' => $payload['message_id'],
        ]);
    }
}
