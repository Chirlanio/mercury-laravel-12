<?php

namespace Tests\Feature\TurnList;

use App\Services\TurnListAttendanceService;
use App\Services\TurnListBoardService;
use App\Services\TurnListBreakService;
use App\Services\TurnListQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TurnListBoardServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected TurnListBoardService $service;
    protected TurnListQueueService $queueService;
    protected TurnListAttendanceService $attendanceService;
    protected TurnListBreakService $breakService;

    protected string $storeA = 'Z421';
    protected string $storeB = 'Z422';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore($this->storeA);
        $this->createTestStore($this->storeB);

        $this->service = app(TurnListBoardService::class);
        $this->queueService = app(TurnListQueueService::class);
        $this->attendanceService = app(TurnListAttendanceService::class);
        $this->breakService = app(TurnListBreakService::class);
    }

    protected function makeConsultora(string $store, string $name = 'Consultora'): int
    {
        return $this->createTestEmployee([
            'store_id' => $store,
            'name' => $name.' '.uniqid(),
            'cpf' => str_pad((string) random_int(1, 99_999_999_999), 11, '0', STR_PAD_LEFT),
            'position_id' => 1,
            'status_id' => 2,
        ]);
    }

    public function test_get_board_returns_4_panels_with_counts(): void
    {
        $a = $this->makeConsultora($this->storeA, 'Maria');
        $b = $this->makeConsultora($this->storeA, 'Joana');
        $c = $this->makeConsultora($this->storeA, 'Cristina');
        $d = $this->makeConsultora($this->storeA, 'Daniela');

        $this->queueService->enter($b, $this->storeA);
        $this->queueService->enter($c, $this->storeA);
        $this->attendanceService->start($d, $this->storeA);

        $board = $this->service->getBoard($this->storeA);

        $this->assertArrayHasKey('available', $board);
        $this->assertArrayHasKey('queue', $board);
        $this->assertArrayHasKey('attending', $board);
        $this->assertArrayHasKey('on_break', $board);
        $this->assertArrayHasKey('counts', $board);

        // a → available (não está em fila/atendimento/pausa)
        // b, c → queue
        // d → attending
        $this->assertSame(1, $board['counts']['available']);
        $this->assertSame(2, $board['counts']['queue']);
        $this->assertSame(1, $board['counts']['attending']);
        $this->assertSame(0, $board['counts']['on_break']);
    }

    public function test_available_excludes_employees_from_other_stores(): void
    {
        $this->makeConsultora($this->storeA, 'Maria A');
        $this->makeConsultora($this->storeB, 'Joana B');

        $boardA = $this->service->getBoard($this->storeA);
        $boardB = $this->service->getBoard($this->storeB);

        $this->assertSame(1, $boardA['counts']['available']);
        $this->assertSame(1, $boardB['counts']['available']);
    }

    public function test_available_excludes_inactive_or_non_consultora_employees(): void
    {
        $this->makeConsultora($this->storeA, 'Ativa');

        // Inativa (status 3 = Inativo) — não deve aparecer
        $this->createTestEmployee([
            'store_id' => $this->storeA,
            'name' => 'Inativa '.uniqid(),
            'cpf' => '99988877766',
            'position_id' => 1,
            'status_id' => 3,
        ]);

        // Posição diferente (Gerente, position_id 2) — não deve aparecer
        $this->createTestEmployee([
            'store_id' => $this->storeA,
            'name' => 'Gerente '.uniqid(),
            'cpf' => '88877766655',
            'position_id' => 2,
            'status_id' => 2,
        ]);

        $board = $this->service->getBoard($this->storeA);

        $this->assertSame(1, $board['counts']['available']);
    }

    public function test_queue_is_ordered_by_position(): void
    {
        $a = $this->makeConsultora($this->storeA, 'Aaa');
        $b = $this->makeConsultora($this->storeA, 'Bbb');
        $c = $this->makeConsultora($this->storeA, 'Ccc');

        $this->queueService->enter($c, $this->storeA);
        $this->queueService->enter($a, $this->storeA);
        $this->queueService->enter($b, $this->storeA);

        $board = $this->service->getBoard($this->storeA);

        $this->assertSame($c, $board['queue'][0]['employee_id']);
        $this->assertSame($a, $board['queue'][1]['employee_id']);
        $this->assertSame($b, $board['queue'][2]['employee_id']);
        $this->assertSame(1, $board['queue'][0]['position']);
    }

    public function test_attending_includes_elapsed_seconds_and_ulid(): void
    {
        $a = $this->makeConsultora($this->storeA);
        $att = $this->attendanceService->start($a, $this->storeA);

        $board = $this->service->getBoard($this->storeA);

        $this->assertCount(1, $board['attending']);
        $this->assertSame($att->ulid, $board['attending'][0]['attendance_ulid']);
        $this->assertArrayHasKey('elapsed_seconds', $board['attending'][0]);
    }

    public function test_on_break_includes_break_type_and_is_exceeded(): void
    {
        $a = $this->makeConsultora($this->storeA);
        $this->queueService->enter($a, $this->storeA);
        $break = $this->breakService->start($a, $this->storeA, 1); // intervalo (15min max)

        $board = $this->service->getBoard($this->storeA);

        $this->assertCount(1, $board['on_break']);
        $this->assertNotNull($board['on_break'][0]['break_type']);
        $this->assertSame('Intervalo', $board['on_break'][0]['break_type']['name']);
        $this->assertArrayHasKey('is_exceeded', $board['on_break'][0]);
        $this->assertFalse($board['on_break'][0]['is_exceeded']); // recém-iniciada
    }

    public function test_employee_initials_are_first_and_last_letters(): void
    {
        $a = $this->createTestEmployee([
            'store_id' => $this->storeA,
            'name' => 'Maria José Silva',
            'cpf' => '11122233344',
            'position_id' => 1,
            'status_id' => 2,
        ]);

        $board = $this->service->getBoard($this->storeA);

        $this->assertSame('MS', $board['available'][0]['employee_initials']);
    }

    public function test_employee_initials_handle_single_name(): void
    {
        $this->createTestEmployee([
            'store_id' => $this->storeA,
            'name' => 'Madonna',
            'cpf' => '22233344455',
            'position_id' => 1,
            'status_id' => 2,
        ]);

        $board = $this->service->getBoard($this->storeA);

        $this->assertSame('M', $board['available'][0]['employee_initials']);
    }
}
