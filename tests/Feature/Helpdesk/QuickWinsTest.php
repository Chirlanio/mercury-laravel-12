<?php

namespace Tests\Feature\Helpdesk;

use App\Models\HdCategory;
use App\Models\HdDepartment;
use App\Models\HdInteraction;
use App\Models\HdReplyTemplate;
use App\Models\HdTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Combined coverage for the four quick-win features:
 *   - Reply templates CRUD + visibility rules
 *   - Ticket summary endpoint (quick + AI path)
 *   - AI accuracy report command (smoke test — it should run without
 *     errors on a tenant with data)
 *
 * The central sidebar migration is covered implicitly by tenants:migrate
 * and doesn't need a feature test here.
 */
class QuickWinsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $department;
    protected HdCategory $category;
    protected User $technician;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $base = $this->createHelpdeskBaseData();
        $this->department = $base['department'];
        $this->category = $base['categories'][0];

        $this->technician = User::factory()->create(['role' => 'support']);
        $this->grantHelpdeskPermission($this->technician, $this->department, 'technician');
    }

    // ----------------------------------------------------------------
    // Reply templates
    // ----------------------------------------------------------------

    public function test_technician_can_create_personal_reply_template(): void
    {
        $this->actingAs($this->technician)
            ->postJson(route('helpdesk.reply-templates.store'), [
                'name' => 'Saudação padrão',
                'category' => 'Geral',
                'body' => 'Olá {{requester.name}}, obrigado pelo contato. O chamado {{ticket.id}} está sendo atendido.',
                'is_shared' => false,
            ])
            ->assertOk();

        $template = HdReplyTemplate::where('name', 'Saudação padrão')->first();
        $this->assertNotNull($template);
        $this->assertSame($this->technician->id, $template->author_id);
        $this->assertFalse($template->is_shared);
    }

    public function test_listing_includes_shared_and_own_personal_templates(): void
    {
        $otherTech = User::factory()->create(['role' => 'support']);

        // Shared by another user
        HdReplyTemplate::create([
            'name' => 'Template compartilhado',
            'body' => 'Corpo compartilhado',
            'is_shared' => true,
            'author_id' => $otherTech->id,
        ]);
        // Personal by another user — should be HIDDEN
        HdReplyTemplate::create([
            'name' => 'Pessoal do outro',
            'body' => 'corpo',
            'is_shared' => false,
            'author_id' => $otherTech->id,
        ]);
        // Personal by the current technician — should be visible
        HdReplyTemplate::create([
            'name' => 'Meu pessoal',
            'body' => 'meu corpo',
            'is_shared' => false,
            'author_id' => $this->technician->id,
        ]);

        $response = $this->actingAs($this->technician)
            ->getJson(route('helpdesk.reply-templates.index'));

        $response->assertOk();
        $names = collect($response->json('templates'))->pluck('name');

        $this->assertTrue($names->contains('Template compartilhado'));
        $this->assertTrue($names->contains('Meu pessoal'));
        $this->assertFalse($names->contains('Pessoal do outro'));
    }

    public function test_cannot_edit_another_users_personal_template(): void
    {
        $otherTech = User::factory()->create(['role' => 'support']);
        $this->grantHelpdeskPermission($otherTech, $this->department, 'technician');

        $template = HdReplyTemplate::create([
            'name' => 'Alheio',
            'body' => 'original',
            'is_shared' => false,
            'author_id' => $otherTech->id,
        ]);

        $this->actingAs($this->technician)
            ->putJson(route('helpdesk.reply-templates.update', $template->id), [
                'name' => 'Roubado',
                'body' => 'modificado',
            ])
            ->assertForbidden();
    }

    public function test_record_usage_increments_counter(): void
    {
        $template = HdReplyTemplate::create([
            'name' => 'Uso',
            'body' => 'x',
            'is_shared' => true,
            'author_id' => $this->technician->id,
        ]);

        $this->actingAs($this->technician)
            ->postJson(route('helpdesk.reply-templates.use', $template->id))
            ->assertOk();

        $this->assertSame(1, $template->fresh()->usage_count);
    }

    public function test_department_filter_excludes_other_departments(): void
    {
        $otherDept = HdDepartment::factory()->create();

        HdReplyTemplate::create([
            'name' => 'Global',
            'body' => 'x',
            'is_shared' => true,
            'department_id' => null,
            'author_id' => $this->technician->id,
        ]);
        HdReplyTemplate::create([
            'name' => 'Do departamento de teste',
            'body' => 'y',
            'is_shared' => true,
            'department_id' => $this->department->id,
            'author_id' => $this->technician->id,
        ]);
        HdReplyTemplate::create([
            'name' => 'Do outro departamento',
            'body' => 'z',
            'is_shared' => true,
            'department_id' => $otherDept->id,
            'author_id' => $this->technician->id,
        ]);

        $response = $this->actingAs($this->technician)
            ->getJson(route('helpdesk.reply-templates.index', ['department_id' => $this->department->id]));

        $names = collect($response->json('templates'))->pluck('name');
        $this->assertTrue($names->contains('Global'));
        $this->assertTrue($names->contains('Do departamento de teste'));
        $this->assertFalse($names->contains('Do outro departamento'));
    }

    // ----------------------------------------------------------------
    // Ticket summary
    // ----------------------------------------------------------------

    public function test_summary_endpoint_returns_quick_stats(): void
    {
        $ticket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->regularUser->id,
        ]);

        // 3 public comments + 2 internal notes
        foreach (range(1, 3) as $i) {
            HdInteraction::create([
                'ticket_id' => $ticket->id,
                'user_id' => $this->technician->id,
                'comment' => "Comentário {$i}",
                'type' => 'comment',
                'is_internal' => false,
            ]);
        }
        foreach (range(1, 2) as $i) {
            HdInteraction::create([
                'ticket_id' => $ticket->id,
                'user_id' => $this->technician->id,
                'comment' => "Nota {$i}",
                'type' => 'comment',
                'is_internal' => true,
            ]);
        }

        $response = $this->actingAs($this->technician)
            ->getJson(route('helpdesk.summary', $ticket->id));

        $response->assertOk();
        $quick = $response->json('quick');
        $this->assertSame('quick', $quick['type']);
        $this->assertGreaterThanOrEqual(5, $quick['interactions']);
        $this->assertSame(3, $quick['public_comments']);
        $this->assertSame(2, $quick['internal_notes']);
        $this->assertSame($this->technician->name, $quick['last_author']);
        $this->assertNull($response->json('ai'));
    }

    public function test_summary_endpoint_forbidden_to_unrelated_user(): void
    {
        $ticket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'created_by_user_id' => $this->regularUser->id,
        ]);

        $outsider = User::factory()->create(['role' => 'user']);

        $this->actingAs($outsider)
            ->getJson(route('helpdesk.summary', $ticket->id))
            ->assertForbidden();
    }

    public function test_summary_endpoint_returns_ai_when_requested_and_configured(): void
    {
        config([
            'helpdesk.ai.classifier' => 'groq',
            'helpdesk.ai.groq.api_key' => 'test-key',
            'helpdesk.ai.groq.base_url' => 'https://api.groq.com/openai/v1',
            'helpdesk.ai.groq.model' => 'llama-3.3-70b-versatile',
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'O usuário relata senha expirada. Já foi orientado a trocar, aguardando confirmação.'],
                ]],
            ], 200),
        ]);

        $ticket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'created_by_user_id' => $this->regularUser->id,
            'description' => 'Minha senha não funciona',
        ]);

        HdInteraction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->technician->id,
            'comment' => 'Oi, por favor clique em esqueci senha',
            'type' => 'comment',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($this->technician)
            ->getJson(route('helpdesk.summary', $ticket->id).'?ai=1');

        $response->assertOk();
        $ai = $response->json('ai');
        $this->assertNotNull($ai);
        $this->assertSame('ai', $ai['type']);
        $this->assertStringContainsString('senha', mb_strtolower($ai['text']));
    }

    public function test_summary_ai_returns_null_when_classifier_is_null(): void
    {
        config(['helpdesk.ai.classifier' => 'null']);

        $ticket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'created_by_user_id' => $this->regularUser->id,
        ]);

        $response = $this->actingAs($this->technician)
            ->getJson(route('helpdesk.summary', $ticket->id).'?ai=1');

        $response->assertOk();
        $this->assertNull($response->json('ai'));
        $this->assertFalse($response->json('ai_available'));
    }

    // ----------------------------------------------------------------
    // AI accuracy report command — smoke test
    // ----------------------------------------------------------------

    public function test_accuracy_report_command_runs_without_data(): void
    {
        $this->artisan('helpdesk:ai:accuracy-report')
            ->assertExitCode(0);
    }
}
