<?php

namespace Tests\Feature\Helpdesk;

use App\Events\Helpdesk\TicketStatusChangedEvent;
use App\Jobs\Helpdesk\SendCsatSurveyJob;
use App\Models\HdCategory;
use App\Models\HdDepartment;
use App\Models\HdSatisfactionSurvey;
use App\Models\HdTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CsatSurveyTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $department;
    protected HdCategory $category;
    protected HdTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $base = $this->createHelpdeskBaseData();
        $this->department = $base['department'];
        $this->category = $base['categories'][0];

        $this->ticket = HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'category_id' => $this->category->id,
            'status' => HdTicket::STATUS_IN_PROGRESS,
            'created_by_user_id' => $this->regularUser->id,
            'assigned_technician_id' => $this->supportUser->id,
        ]);

        Config::set('services.evolution.fake', true);
    }

    // ----------------------------------------------------------------
    // Listener
    // ----------------------------------------------------------------

    public function test_listener_dispatches_job_on_resolved_transition(): void
    {
        Bus::fake([SendCsatSurveyJob::class]);

        event(new TicketStatusChangedEvent(
            $this->ticket->id,
            $this->department->id,
            HdTicket::STATUS_IN_PROGRESS,
            HdTicket::STATUS_RESOLVED,
        ));

        Bus::assertDispatched(SendCsatSurveyJob::class, fn ($job) => $job->ticketId === $this->ticket->id);
    }

    public function test_listener_ignores_non_resolved_transitions(): void
    {
        Bus::fake([SendCsatSurveyJob::class]);

        event(new TicketStatusChangedEvent(
            $this->ticket->id,
            $this->department->id,
            HdTicket::STATUS_OPEN,
            HdTicket::STATUS_IN_PROGRESS,
        ));

        Bus::assertNotDispatched(SendCsatSurveyJob::class);
    }

    public function test_listener_is_idempotent(): void
    {
        // Pre-create a survey so the second fire is a no-op.
        HdSatisfactionSurvey::create([
            'ticket_id' => $this->ticket->id,
            'signed_token' => HdSatisfactionSurvey::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        Bus::fake([SendCsatSurveyJob::class]);

        event(new TicketStatusChangedEvent(
            $this->ticket->id,
            $this->department->id,
            HdTicket::STATUS_IN_PROGRESS,
            HdTicket::STATUS_RESOLVED,
        ));

        Bus::assertNotDispatched(SendCsatSurveyJob::class);
    }

    // ----------------------------------------------------------------
    // Job
    // ----------------------------------------------------------------

    public function test_job_creates_survey_row(): void
    {
        (new SendCsatSurveyJob($this->ticket->id))->handle();

        $survey = HdSatisfactionSurvey::where('ticket_id', $this->ticket->id)->first();

        $this->assertNotNull($survey);
        $this->assertSame($this->regularUser->id, $survey->requester_id);
        $this->assertSame($this->supportUser->id, $survey->resolved_by_user_id);
        $this->assertSame($this->department->id, $survey->department_id);
        $this->assertNull($survey->rating);
        $this->assertNotNull($survey->signed_token);
        $this->assertTrue($survey->expires_at->greaterThan(now()));
    }

    public function test_job_is_idempotent_when_survey_exists(): void
    {
        HdSatisfactionSurvey::create([
            'ticket_id' => $this->ticket->id,
            'signed_token' => HdSatisfactionSurvey::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        (new SendCsatSurveyJob($this->ticket->id))->handle();

        $this->assertSame(1, HdSatisfactionSurvey::where('ticket_id', $this->ticket->id)->count());
    }

    public function test_job_no_op_when_ticket_missing(): void
    {
        (new SendCsatSurveyJob(999999))->handle();

        $this->assertSame(0, HdSatisfactionSurvey::count());
    }

    // ----------------------------------------------------------------
    // Public survey endpoints
    // ----------------------------------------------------------------

    public function test_signed_url_shows_rating_form(): void
    {
        $survey = HdSatisfactionSurvey::create([
            'ticket_id' => $this->ticket->id,
            'requester_id' => $this->regularUser->id,
            'signed_token' => HdSatisfactionSurvey::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        $url = URL::temporarySignedRoute(
            'helpdesk.csat.show',
            $survey->expires_at,
            ['token' => $survey->signed_token],
        );

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Helpdesk/Csat/Show')
                ->where('token', $survey->signed_token)
            );
    }

    public function test_unsigned_url_is_rejected(): void
    {
        $survey = HdSatisfactionSurvey::create([
            'ticket_id' => $this->ticket->id,
            'signed_token' => HdSatisfactionSurvey::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        // Direct URL without signature query string — signed middleware rejects.
        $this->get("/helpdesk/csat/{$survey->signed_token}")
            ->assertForbidden();
    }

    public function test_expired_survey_shows_expired_page(): void
    {
        $survey = HdSatisfactionSurvey::create([
            'ticket_id' => $this->ticket->id,
            'signed_token' => HdSatisfactionSurvey::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        // Sign with a FUTURE timestamp so the signed middleware passes,
        // but then expire the survey in the DB — the controller handles
        // this with its own expiry check.
        $url = URL::temporarySignedRoute(
            'helpdesk.csat.show',
            now()->addDays(7),
            ['token' => $survey->signed_token],
        );
        $survey->update(['expires_at' => now()->subDay()]);

        $this->get($url)
            ->assertInertia(fn ($page) => $page
                ->component('Helpdesk/Csat/Expired')
                ->where('reason', 'expired')
            );
    }

    public function test_submit_persists_rating(): void
    {
        $survey = HdSatisfactionSurvey::create([
            'ticket_id' => $this->ticket->id,
            'signed_token' => HdSatisfactionSurvey::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        $url = URL::temporarySignedRoute(
            'helpdesk.csat.submit',
            $survey->expires_at,
            ['token' => $survey->signed_token],
        );

        $this->post($url, ['rating' => 5, 'comment' => 'Excelente atendimento!'])
            ->assertInertia(fn ($page) => $page
                ->component('Helpdesk/Csat/Submitted')
                ->where('rating', 5)
            );

        $fresh = $survey->fresh();
        $this->assertSame(5, $fresh->rating);
        $this->assertSame('Excelente atendimento!', $fresh->comment);
        $this->assertNotNull($fresh->submitted_at);
    }

    public function test_double_submit_shows_already_submitted(): void
    {
        $survey = HdSatisfactionSurvey::create([
            'ticket_id' => $this->ticket->id,
            'signed_token' => HdSatisfactionSurvey::generateToken(),
            'rating' => 4,
            'submitted_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $url = URL::temporarySignedRoute(
            'helpdesk.csat.submit',
            $survey->expires_at,
            ['token' => $survey->signed_token],
        );

        $this->post($url, ['rating' => 1])
            ->assertInertia(fn ($page) => $page
                ->component('Helpdesk/Csat/Submitted')
                ->where('already_submitted', true)
                ->where('rating', 4)
            );

        // Rating was NOT overwritten
        $this->assertSame(4, $survey->fresh()->rating);
    }

    public function test_submit_rejects_out_of_range_rating(): void
    {
        $survey = HdSatisfactionSurvey::create([
            'ticket_id' => $this->ticket->id,
            'signed_token' => HdSatisfactionSurvey::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        $url = URL::temporarySignedRoute(
            'helpdesk.csat.submit',
            $survey->expires_at,
            ['token' => $survey->signed_token],
        );

        $this->post($url, ['rating' => 10])
            ->assertSessionHasErrors(['rating']);
    }
}
