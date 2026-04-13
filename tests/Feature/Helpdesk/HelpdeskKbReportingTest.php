<?php

namespace Tests\Feature\Helpdesk;

use App\Models\HdArticle;
use App\Models\HdArticleView;
use App\Models\HdCategory;
use App\Models\HdDepartment;
use App\Models\HdSatisfactionSurvey;
use App\Models\HdTicket;
use App\Models\User;
use App\Services\HelpdeskReportService;
use App\Services\Intake\Drivers\WhatsappIntakeDriver;
use App\Models\HdChannel;
use App\Models\HdChatSession;
use App\Services\HelpdeskIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Combined coverage for the three pieces of F5B batch 2:
 *
 *   - CSAT reporting service methods
 *   - WhatsApp driver KB suggestion flow (match → confirm/deflect)
 *   - Article view tracking from the WhatsApp driver source
 *
 * Deflection via the web form is already covered by the /kb/{id}/deflect
 * endpoint test in KnowledgeBaseTest.
 */
class HelpdeskKbReportingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $department;
    protected HdCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        // Clean default seeded departments so the driver state machine
        // only sees the fixtures we create explicitly.
        HdCategory::query()->delete();
        HdDepartment::query()->delete();

        $base = $this->createHelpdeskBaseData();
        $this->department = $base['department'];
        $this->category = $base['categories'][0];

        // Drop the remaining base categories so the category menu has
        // exactly one option and "1" maps cleanly in the tests.
        HdCategory::query()->where('id', '!=', $this->category->id)->delete();
    }

    // ---------------------------------------------------------------
    // CSAT reporting
    // ---------------------------------------------------------------

    public function test_csat_overview_returns_zeroes_when_no_surveys(): void
    {
        $overview = app(HelpdeskReportService::class)->csatOverview([]);

        $this->assertSame(0, $overview['total_submitted']);
        $this->assertSame(0, $overview['total_sent']);
        $this->assertSame(0.0, $overview['average']);
        $this->assertSame(0.0, $overview['response_rate']);
    }

    public function test_csat_overview_aggregates_submitted_surveys(): void
    {
        $this->seedSurveys([5, 5, 4, 3, 1]);

        $overview = app(HelpdeskReportService::class)->csatOverview([]);

        $this->assertSame(5, $overview['total_submitted']);
        $this->assertSame(5, $overview['total_sent']);
        $this->assertSame(100.0, $overview['response_rate']);
        $this->assertEqualsWithDelta(3.6, $overview['average'], 0.01);
        $this->assertSame(2, $overview['distribution'][5]);
        $this->assertSame(1, $overview['distribution'][4]);
        $this->assertSame(1, $overview['distribution'][3]);
        $this->assertSame(0, $overview['distribution'][2]);
        $this->assertSame(1, $overview['distribution'][1]);

        // NPS-like breakdown
        $this->assertSame(2, $overview['nps_like']['promoters']);  // 5s
        $this->assertSame(1, $overview['nps_like']['passives']);   // 4s
        $this->assertSame(2, $overview['nps_like']['detractors']); // 1+3
    }

    public function test_csat_overview_counts_unsubmitted_toward_sent_but_not_average(): void
    {
        $this->seedSurveys([5, 5]);           // submitted
        $this->seedSurveys([null, null]);     // sent but not answered

        $overview = app(HelpdeskReportService::class)->csatOverview([]);

        $this->assertSame(2, $overview['total_submitted']);
        $this->assertSame(4, $overview['total_sent']);
        $this->assertSame(50.0, $overview['response_rate']);
        $this->assertSame(5.0, $overview['average']);
    }

    public function test_csat_by_technician_ranks_by_average(): void
    {
        $techA = User::factory()->create(['name' => 'Alice']);
        $techB = User::factory()->create(['name' => 'Bob']);

        $this->seedSurveys([5, 5, 4], ['resolved_by_user_id' => $techA->id]);
        $this->seedSurveys([3, 2], ['resolved_by_user_id' => $techB->id]);

        $ranking = app(HelpdeskReportService::class)->csatByTechnician([], 1, 10);

        $this->assertCount(2, $ranking);
        $this->assertSame('Alice', $ranking[0]['user_name']);
        $this->assertEqualsWithDelta(4.67, $ranking[0]['average'], 0.01);
        $this->assertSame(3, $ranking[0]['total']);
        $this->assertSame('Bob', $ranking[1]['user_name']);
        $this->assertEqualsWithDelta(2.5, $ranking[1]['average'], 0.01);
    }

    public function test_csat_by_technician_respects_min_responses(): void
    {
        $techA = User::factory()->create();
        $techB = User::factory()->create();

        $this->seedSurveys([5, 5, 5], ['resolved_by_user_id' => $techA->id]);
        $this->seedSurveys([5], ['resolved_by_user_id' => $techB->id]);

        $ranking = app(HelpdeskReportService::class)->csatByTechnician([], 2, 10);

        $this->assertCount(1, $ranking);
        $this->assertSame($techA->id, $ranking[0]['user_id']);
    }

    public function test_csat_by_department_groups_and_averages(): void
    {
        $otherDept = HdDepartment::factory()->create(['name' => 'Outro']);

        $this->seedSurveys([5, 5], ['department_id' => $this->department->id]);
        $this->seedSurveys([3, 2], ['department_id' => $otherDept->id]);

        $byDept = app(HelpdeskReportService::class)->csatByDepartment([]);

        $this->assertCount(2, $byDept);
        // Sorted by average descending
        $this->assertSame($this->department->id, $byDept[0]['department_id']);
        $this->assertSame(5.0, $byDept[0]['average']);
        $this->assertSame(2.5, $byDept[1]['average']);
    }

    // ---------------------------------------------------------------
    // WhatsApp driver KB suggestion flow
    // ---------------------------------------------------------------

    public function test_whatsapp_driver_suggests_kb_article_before_creating_ticket(): void
    {
        $channel = $this->whatsappChannel();
        Config::set('services.evolution.fake', true);

        HdArticle::create([
            'title' => 'Como solicitar férias',
            'slug' => 'como-solicitar-ferias',
            'summary' => 'Passo a passo para pedir férias.',
            'content_md' => 'Acesse o portal e preencha o formulário de férias.',
            'department_id' => $this->department->id,
            'is_published' => true,
            'published_at' => now(),
        ]);

        // Walk through the flow: greet → department → category → description
        $this->intake('5585987460452', 'oi');
        $this->intake('5585987460452', '1'); // Department (only one available)
        $this->intake('5585987460452', '1'); // Category
        $step = $this->intake('5585987460452', 'Preciso solicitar férias para o próximo mês, por favor.');

        // Driver should suggest the article, NOT open a ticket yet
        $this->assertFalse($step->isComplete);
        $this->assertStringContainsString('Como solicitar férias', $step->prompt);
        $this->assertStringContainsString('RESOLVIDO', $step->prompt);

        $session = HdChatSession::where('external_contact', '5585987460452')->first();
        $this->assertSame('awaiting_kb_confirmation', $session->step);
        $this->assertSame('Preciso solicitar férias para o próximo mês, por favor.', $session->context['pending_description']);

        // Ticket count still zero
        $this->assertSame(0, HdTicket::where('source', 'whatsapp')->count());
    }

    public function test_whatsapp_driver_deflects_when_user_confirms_article(): void
    {
        $channel = $this->whatsappChannel();
        Config::set('services.evolution.fake', true);

        $article = HdArticle::create([
            'title' => 'Reset de senha',
            'slug' => 'reset-senha',
            'content_md' => 'Acesse configurações e clique em reset de senha.',
            'department_id' => $this->department->id,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->intake('5585987460453', 'oi');
        $this->intake('5585987460453', '1');
        $this->intake('5585987460453', '1');
        $this->intake('5585987460453', 'Minha senha não funciona, preciso fazer reset de senha.');

        // User says the article resolved it
        $step = $this->intake('5585987460453', 'resolvido');

        $this->assertFalse($step->isComplete);
        $this->assertStringContainsString('Ficamos felizes', $step->prompt);

        // Session closed, no ticket created
        $this->assertDatabaseMissing('hd_chat_sessions', [
            'external_contact' => '5585987460453',
        ]);
        $this->assertSame(0, HdTicket::where('source', 'whatsapp')->count());

        // Deflection event logged
        $this->assertSame(
            1,
            HdArticleView::where('article_id', $article->id)
                ->where('action', 'deflected')
                ->where('source', 'intake_whatsapp')
                ->count(),
        );
    }

    public function test_whatsapp_driver_opens_ticket_when_user_says_sim(): void
    {
        $channel = $this->whatsappChannel();
        Config::set('services.evolution.fake', true);

        HdArticle::create([
            'title' => 'Reset de senha',
            'slug' => 'reset-senha-2',
            'content_md' => 'Reset de senha instruções.',
            'department_id' => $this->department->id,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->intake('5585987460454', 'oi');
        $this->intake('5585987460454', '1');
        $this->intake('5585987460454', '1');
        $this->intake('5585987460454', 'Meu problema é com reset de senha, preciso de ajuda.');
        $step = $this->intake('5585987460454', 'sim');

        $this->assertTrue($step->isComplete);
        $this->assertNotNull($step->ticketId);

        $ticket = HdTicket::findOrFail($step->ticketId);
        $this->assertSame('whatsapp', $ticket->source);
        $this->assertStringContainsString('reset de senha', mb_strtolower($ticket->description));
    }

    public function test_whatsapp_driver_skips_kb_when_no_matching_article(): void
    {
        $channel = $this->whatsappChannel();
        Config::set('services.evolution.fake', true);

        HdArticle::create([
            'title' => 'Algo totalmente diferente',
            'slug' => 'outro',
            'content_md' => 'Conteúdo completamente sem relação.',
            'department_id' => $this->department->id,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->intake('5585987460455', 'oi');
        $this->intake('5585987460455', '1');
        $this->intake('5585987460455', '1');
        $step = $this->intake('5585987460455', 'zzz xpto qwerty nenhum match possível aqui.');

        // Driver should fall through to regular ticket creation
        $this->assertTrue($step->isComplete);
        $this->assertNotNull($step->ticketId);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    protected function intake(string $contact, string $message): \App\Services\Intake\IntakeStep
    {
        return app(HelpdeskIntakeService::class)->handle('whatsapp', [
            'message' => $message,
        ], [
            'external_contact' => $contact,
            'push_name' => 'Teste',
            'instance' => 'test',
        ]);
    }

    protected function whatsappChannel(): HdChannel
    {
        $channel = HdChannel::firstOrCreate(
            ['slug' => 'whatsapp'],
            [
                'name' => 'WhatsApp',
                'driver' => 'whatsapp',
                'config' => ['greeting' => 'Olá!'],
                'is_active' => true,
            ],
        );
        $channel->update(['is_active' => true]);
        return $channel;
    }

    /**
     * Seed a batch of satisfaction surveys for reporting tests. $ratings
     * is an array of integers 1..5 (or null for "sent but unanswered").
     * Shared overrides can set resolved_by_user_id or department_id.
     *
     * @param  array<int, ?int>  $ratings
     */
    protected function seedSurveys(array $ratings, array $overrides = []): void
    {
        foreach ($ratings as $i => $rating) {
            $ticket = HdTicket::factory()->create([
                'requester_id' => $this->regularUser->id,
                'department_id' => $overrides['department_id'] ?? $this->department->id,
                'created_by_user_id' => $this->regularUser->id,
            ]);

            HdSatisfactionSurvey::create(array_merge([
                'ticket_id' => $ticket->id,
                'requester_id' => $ticket->requester_id,
                'department_id' => $overrides['department_id'] ?? $this->department->id,
                'signed_token' => HdSatisfactionSurvey::generateToken(),
                'expires_at' => now()->addDays(7),
                'rating' => $rating,
                'submitted_at' => $rating !== null ? now() : null,
            ], $overrides));
        }
    }
}
