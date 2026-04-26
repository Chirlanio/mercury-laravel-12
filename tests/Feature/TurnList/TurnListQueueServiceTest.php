<?php

namespace Tests\Feature\TurnList;

use App\Enums\TurnListAttendanceStatus;
use App\Models\TurnListAttendance;
use App\Models\TurnListBreak;
use App\Models\TurnListQueueEntry;
use App\Services\TurnListQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TurnListQueueServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected TurnListQueueService $service;

    protected string $storeA = 'Z421';
    protected string $storeB = 'Z422';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore($this->storeA);
        $this->createTestStore($this->storeB);

        $this->service = app(TurnListQueueService::class);
    }

    protected function makeConsultora(string $storeCode, string $name = 'Consultora'): int
    {
        return $this->createTestEmployee([
            'store_id' => $storeCode,
            'name' => $name.' '.uniqid(),
            'cpf' => str_pad((string) random_int(1, 99_999_999_999), 11, '0', STR_PAD_LEFT),
            'position_id' => 1,
            'status_id' => 2,
        ]);
    }

    public function test_enter_appends_to_end_of_queue(): void
    {
        $a = $this->makeConsultora($this->storeA);
        $b = $this->makeConsultora($this->storeA);
        $c = $this->makeConsultora($this->storeA);

        $entryA = $this->service->enter($a, $this->storeA, $this->adminUser);
        $entryB = $this->service->enter($b, $this->storeA, $this->adminUser);
        $entryC = $this->service->enter($c, $this->storeA, $this->adminUser);

        $this->assertSame(1, $entryA->position);
        $this->assertSame(2, $entryB->position);
        $this->assertSame(3, $entryC->position);
        $this->assertSame($this->adminUser->id, $entryA->created_by_user_id);
    }

    public function test_enter_isolates_by_store(): void
    {
        $a = $this->makeConsultora($this->storeA);
        $b = $this->makeConsultora($this->storeB);

        $this->service->enter($a, $this->storeA);
        $entryB = $this->service->enter($b, $this->storeB);

        // Cada loja recomeça em 1
        $this->assertSame(1, $entryB->position);
    }

    public function test_enter_blocks_if_already_in_queue(): void
    {
        $a = $this->makeConsultora($this->storeA);
        $this->service->enter($a, $this->storeA);

        $this->expectException(ValidationException::class);
        $this->service->enter($a, $this->storeA);
    }

    public function test_enter_blocks_if_attending(): void
    {
        $a = $this->makeConsultora($this->storeA);

        TurnListAttendance::create([
            'employee_id' => $a,
            'store_code' => $this->storeA,
            'status' => TurnListAttendanceStatus::ACTIVE->value,
            'started_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        $this->service->enter($a, $this->storeA);
    }

    public function test_enter_blocks_if_on_break(): void
    {
        $a = $this->makeConsultora($this->storeA);

        TurnListBreak::create([
            'employee_id' => $a,
            'store_code' => $this->storeA,
            'break_type_id' => 1,
            'original_queue_position' => 1,
            'status' => TurnListAttendanceStatus::ACTIVE->value,
            'started_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        $this->service->enter($a, $this->storeA);
    }

    public function test_leave_removes_and_shifts_subsequent(): void
    {
        $ids = collect(range(1, 4))->map(fn () => $this->makeConsultora($this->storeA));

        foreach ($ids as $id) {
            $this->service->enter($id, $this->storeA);
        }

        $removed = $this->service->leave($ids[1], $this->storeA);
        $this->assertTrue($removed);

        $rows = TurnListQueueEntry::where('store_code', $this->storeA)
            ->orderBy('position')
            ->get(['employee_id', 'position'])
            ->all();

        $this->assertCount(3, $rows);
        // Posições devem ter compactado: 1, 2, 3 (sem buracos)
        $this->assertSame(1, $rows[0]->position);
        $this->assertSame(2, $rows[1]->position);
        $this->assertSame(3, $rows[2]->position);
    }

    public function test_leave_returns_false_when_not_in_queue(): void
    {
        $id = $this->makeConsultora($this->storeA);

        $this->assertFalse($this->service->leave($id, $this->storeA));
    }

    public function test_reorder_moves_employee_up_and_compacts(): void
    {
        $ids = collect(range(1, 4))->map(fn () => $this->makeConsultora($this->storeA))->all();

        foreach ($ids as $id) {
            $this->service->enter($id, $this->storeA);
        }

        // Move último (pos 4) pra primeiro (pos 1) — esperado: 4 entra em 1, demais shiftam +1
        $this->service->reorder($ids[3], $this->storeA, 1);

        $rows = TurnListQueueEntry::where('store_code', $this->storeA)
            ->orderBy('position')
            ->get(['employee_id', 'position'])
            ->all();

        $this->assertSame($ids[3], $rows[0]->employee_id);
        $this->assertSame($ids[0], $rows[1]->employee_id);
        $this->assertSame($ids[1], $rows[2]->employee_id);
        $this->assertSame($ids[2], $rows[3]->employee_id);
    }

    public function test_reorder_moves_employee_down(): void
    {
        $ids = collect(range(1, 4))->map(fn () => $this->makeConsultora($this->storeA))->all();

        foreach ($ids as $id) {
            $this->service->enter($id, $this->storeA);
        }

        // Move primeiro (pos 1) pra terceiro (pos 3)
        $this->service->reorder($ids[0], $this->storeA, 3);

        $rows = TurnListQueueEntry::where('store_code', $this->storeA)
            ->orderBy('position')
            ->get(['employee_id', 'position'])
            ->all();

        $this->assertSame($ids[1], $rows[0]->employee_id);
        $this->assertSame($ids[2], $rows[1]->employee_id);
        $this->assertSame($ids[0], $rows[2]->employee_id);
        $this->assertSame($ids[3], $rows[3]->employee_id);
    }

    public function test_reorder_clamps_to_valid_range(): void
    {
        $ids = collect(range(1, 3))->map(fn () => $this->makeConsultora($this->storeA))->all();

        foreach ($ids as $id) {
            $this->service->enter($id, $this->storeA);
        }

        // Pede pos=99 — clampa pra 3 (max)
        $this->service->reorder($ids[0], $this->storeA, 99);

        $row = TurnListQueueEntry::where('employee_id', $ids[0])->first();
        $this->assertSame(3, $row->position);
    }

    public function test_reorder_throws_when_not_in_queue(): void
    {
        $id = $this->makeConsultora($this->storeA);

        $this->expectException(ValidationException::class);
        $this->service->reorder($id, $this->storeA, 1);
    }

    public function test_enter_at_position_shifts_others(): void
    {
        $ids = collect(range(1, 3))->map(fn () => $this->makeConsultora($this->storeA))->all();

        foreach ($ids as $id) {
            $this->service->enter($id, $this->storeA);
        }

        $newId = $this->makeConsultora($this->storeA);
        $entry = $this->service->enterAtPosition($newId, $this->storeA, 2);

        $this->assertSame(2, $entry->position);

        $rows = TurnListQueueEntry::where('store_code', $this->storeA)
            ->orderBy('position')
            ->get(['employee_id', 'position'])
            ->all();

        $this->assertSame($ids[0], $rows[0]->employee_id);
        $this->assertSame($newId, $rows[1]->employee_id);
        $this->assertSame($ids[1], $rows[2]->employee_id);
        $this->assertSame($ids[2], $rows[3]->employee_id);
    }

    public function test_enter_at_position_clamps_to_max_plus_one(): void
    {
        $ids = collect(range(1, 2))->map(fn () => $this->makeConsultora($this->storeA))->all();

        foreach ($ids as $id) {
            $this->service->enter($id, $this->storeA);
        }

        $newId = $this->makeConsultora($this->storeA);
        // Pede pos=99 com max=2 → clampa pra 3 (final da fila)
        $entry = $this->service->enterAtPosition($newId, $this->storeA, 99);

        $this->assertSame(3, $entry->position);
    }

    public function test_get_position_returns_null_when_absent(): void
    {
        $id = $this->makeConsultora($this->storeA);

        $this->assertNull($this->service->getPosition($id, $this->storeA));

        $this->service->enter($id, $this->storeA);

        $this->assertSame(1, $this->service->getPosition($id, $this->storeA));
    }
}
