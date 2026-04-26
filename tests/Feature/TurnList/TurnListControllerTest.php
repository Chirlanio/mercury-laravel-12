<?php

namespace Tests\Feature\TurnList;

use App\Enums\Role;
use App\Enums\TurnListAttendanceStatus;
use App\Models\TurnListAttendance;
use App\Models\TurnListAttendanceOutcome;
use App\Models\TurnListBreak;
use App\Models\TurnListBreakType;
use App\Models\TurnListQueueEntry;
use App\Models\TurnListStoreSetting;
use App\Models\User;
use App\Services\TurnListAttendanceService;
use App\Services\TurnListBreakService;
use App\Services\TurnListQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TurnListControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected string $storeA = 'Z421';
    protected string $storeB = 'Z422';
    protected int $employeeA;
    protected int $employeeB;
    protected User $userInStoreA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore($this->storeA);
        $this->createTestStore($this->storeB);

        $this->employeeA = $this->createTestEmployee([
            'store_id' => $this->storeA,
            'name' => 'Maria A',
            'cpf' => '11122233344',
            'position_id' => 1,
            'status_id' => 2,
        ]);

        $this->employeeB = $this->createTestEmployee([
            'store_id' => $this->storeB,
            'name' => 'Joana B',
            'cpf' => '22233344455',
            'position_id' => 1,
            'status_id' => 2,
        ]);

        $this->userInStoreA = User::factory()->create([
            'role' => Role::USER->value,
            'access_level_id' => 4,
            'store_id' => $this->storeA,
        ]);

        config(['queue.default' => 'sync']);
    }

    // ==================================================================
    // Auth + Index
    // ==================================================================

    public function test_guest_redirected_to_login(): void
    {
        $this->get(route('turn-list.index'))->assertRedirect('/login');
    }

    public function test_index_renders_inertia_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('turn-list.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('TurnList/Index'));
    }

    public function test_index_resolves_store_from_query_for_manager(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('turn-list.index', ['store' => $this->storeB]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('storeCode', $this->storeB));
    }

    public function test_index_pins_user_to_their_own_store(): void
    {
        $response = $this->actingAs($this->userInStoreA)->get(route('turn-list.index', ['store' => $this->storeB]));

        $response->assertOk();
        // USER sem MANAGE_TURN_LIST — query string ignorada, fica na própria loja
        $response->assertInertia(fn ($page) => $page->where('storeCode', $this->storeA));
    }

    public function test_index_marks_isStoreScoped_for_user(): void
    {
        $response = $this->actingAs($this->userInStoreA)->get(route('turn-list.index'));

        $response->assertInertia(fn ($page) => $page->where('isStoreScoped', true));
    }

    public function test_index_marks_isStoreScoped_false_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('turn-list.index'));

        $response->assertInertia(fn ($page) => $page->where('isStoreScoped', false));
    }

    // ==================================================================
    // Board (JSON)
    // ==================================================================

    public function test_board_returns_json_snapshot(): void
    {
        app(TurnListQueueService::class)->enter($this->employeeA, $this->storeA);

        $response = $this->actingAs($this->adminUser)->getJson(route('turn-list.board', ['store' => $this->storeA]));

        $response->assertOk();
        $response->assertJsonStructure([
            'store_code',
            'board' => ['available', 'queue', 'attending', 'on_break', 'counts'],
            'fetched_at',
        ]);
        $response->assertJson(['store_code' => $this->storeA]);
    }

    public function test_board_returns_422_when_no_store(): void
    {
        // Admin sem store_id e sem ?store
        $response = $this->actingAs($this->adminUser)->getJson(route('turn-list.board'));

        $response->assertStatus(422);
    }

    // ==================================================================
    // Queue endpoints
    // ==================================================================

    public function test_enterQueue_creates_entry(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('turn-list.queue.enter'), [
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeA,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('turn_list_waiting_queue', [
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeA,
            'position' => 1,
        ]);
    }

    public function test_enterQueue_blocks_user_from_other_store(): void
    {
        $response = $this->actingAs($this->userInStoreA)->post(route('turn-list.queue.enter'), [
            'employee_id' => $this->employeeB,
            'store_code' => $this->storeB,
        ]);

        $response->assertStatus(403);
    }

    public function test_enterQueue_validates_input(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->from(route('turn-list.index'))
            ->post(route('turn-list.queue.enter'), []);

        $response->assertSessionHasErrors(['employee_id', 'store_code']);
    }

    public function test_leaveQueue_removes_entry(): void
    {
        app(TurnListQueueService::class)->enter($this->employeeA, $this->storeA);

        $response = $this->actingAs($this->adminUser)->post(route('turn-list.queue.leave'), [
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeA,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('turn_list_waiting_queue', [
            'employee_id' => $this->employeeA,
        ]);
    }

    public function test_reorderQueue_changes_position(): void
    {
        $queue = app(TurnListQueueService::class);
        $queue->enter($this->employeeA, $this->storeA);
        $employeeC = $this->createTestEmployee([
            'store_id' => $this->storeA,
            'name' => 'Carla',
            'cpf' => '33344455566',
            'position_id' => 1,
            'status_id' => 2,
        ]);
        $queue->enter($employeeC, $this->storeA);

        $response = $this->actingAs($this->adminUser)->post(route('turn-list.queue.reorder'), [
            'employee_id' => $employeeC,
            'store_code' => $this->storeA,
            'new_position' => 1,
        ]);

        $response->assertRedirect();
        $this->assertSame(1, app(TurnListQueueService::class)->getPosition($employeeC, $this->storeA));
    }

    // ==================================================================
    // Attendance endpoints
    // ==================================================================

    public function test_startAttendance_creates_active(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('turn-list.attendances.start'), [
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeA,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('turn_list_attendances', [
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeA,
            'status' => 'active',
        ]);
    }

    public function test_finishAttendance_records_outcome(): void
    {
        $att = app(TurnListAttendanceService::class)->start($this->employeeA, $this->storeA);
        $venda = TurnListAttendanceOutcome::where('name', 'Venda Realizada')->first();

        $response = $this->actingAs($this->adminUser)->post(
            route('turn-list.attendances.finish', ['attendance' => $att->ulid]),
            [
                'outcome_id' => $venda->id,
                'return_to_queue' => true,
                'notes' => 'Venda fechada',
            ],
        );

        $response->assertRedirect();
        $att->refresh();
        $this->assertSame(TurnListAttendanceStatus::FINISHED, $att->status);
        $this->assertSame($venda->id, $att->outcome_id);
        $this->assertSame('Venda fechada', $att->notes);
    }

    public function test_finishAttendance_blocks_user_from_other_store(): void
    {
        $att = app(TurnListAttendanceService::class)->start($this->employeeB, $this->storeB);
        $venda = TurnListAttendanceOutcome::where('name', 'Venda Realizada')->first();

        $response = $this->actingAs($this->userInStoreA)->post(
            route('turn-list.attendances.finish', ['attendance' => $att->ulid]),
            ['outcome_id' => $venda->id],
        );

        $response->assertStatus(403);
    }

    // ==================================================================
    // Break endpoints
    // ==================================================================

    public function test_startBreak_requires_employee_in_queue(): void
    {
        $intervaloId = TurnListBreakType::where('name', 'Intervalo')->value('id');

        $response = $this->actingAs($this->adminUser)
            ->from(route('turn-list.index'))
            ->post(route('turn-list.breaks.start'), [
                'employee_id' => $this->employeeA,
                'store_code' => $this->storeA,
                'break_type_id' => $intervaloId,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('queue');
    }

    public function test_startBreak_creates_active_break(): void
    {
        app(TurnListQueueService::class)->enter($this->employeeA, $this->storeA);
        $intervaloId = TurnListBreakType::where('name', 'Intervalo')->value('id');

        $response = $this->actingAs($this->adminUser)->post(route('turn-list.breaks.start'), [
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeA,
            'break_type_id' => $intervaloId,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('turn_list_breaks', [
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeA,
            'break_type_id' => $intervaloId,
            'status' => 'active',
        ]);
    }

    public function test_finishBreak_finalizes(): void
    {
        app(TurnListQueueService::class)->enter($this->employeeA, $this->storeA);
        $intervaloId = TurnListBreakType::where('name', 'Intervalo')->value('id');
        $break = app(TurnListBreakService::class)->start($this->employeeA, $this->storeA, $intervaloId);

        $response = $this->actingAs($this->adminUser)->post(
            route('turn-list.breaks.finish', ['break' => $break->id]),
        );

        $response->assertRedirect();
        $break->refresh();
        $this->assertSame(TurnListAttendanceStatus::FINISHED, $break->status);
    }

    // ==================================================================
    // Settings
    // ==================================================================

    public function test_updateSettings_persists_toggle(): void
    {
        $response = $this->actingAs($this->adminUser)->put(route('turn-list.settings.update'), [
            'store_code' => $this->storeA,
            'return_to_position' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('turn_list_store_settings', [
            'store_code' => $this->storeA,
            'return_to_position' => false,
        ]);
    }

    public function test_updateSettings_blocked_for_user_without_manage(): void
    {
        $response = $this->actingAs($this->userInStoreA)->put(route('turn-list.settings.update'), [
            'store_code' => $this->storeA,
            'return_to_position' => false,
        ]);

        $response->assertStatus(403);
    }

    // ==================================================================
    // Reports
    // ==================================================================

    public function test_reports_renders_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('turn-list.reports'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('TurnList/Reports'));
    }

    public function test_reports_blocked_for_user_without_view_reports(): void
    {
        // USER role só tem VIEW + OPERATE; não tem VIEW_TURN_LIST_REPORTS
        $response = $this->actingAs($this->userInStoreA)->get(route('turn-list.reports'));

        $response->assertStatus(403);
    }

    public function test_reports_passes_filters_through(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('turn-list.reports', [
            'period' => 'today',
            'store' => $this->storeA,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('filters.period', 'today')
            ->where('storeCode', $this->storeA)
        );
    }
}
