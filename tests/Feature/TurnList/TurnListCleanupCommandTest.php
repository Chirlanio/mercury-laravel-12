<?php

namespace Tests\Feature\TurnList;

use App\Console\Commands\TurnListCleanupCommand;
use App\Enums\TurnListAttendanceStatus;
use App\Models\TurnListAttendance;
use App\Models\TurnListBreak;
use App\Models\TurnListQueueEntry;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TurnListCleanupCommandTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected string $storeCode = 'Z421';
    protected int $employeeA;
    protected int $employeeB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore($this->storeCode);

        $this->employeeA = $this->createTestEmployee([
            'store_id' => $this->storeCode,
            'name' => 'Maria',
            'cpf' => '11122233344',
            'position_id' => 1,
            'status_id' => 2,
        ]);

        $this->employeeB = $this->createTestEmployee([
            'store_id' => $this->storeCode,
            'name' => 'Joana',
            'cpf' => '22233344455',
            'position_id' => 1,
            'status_id' => 2,
        ]);
    }

    /**
     * Bind a buffered IO to the command so scanTenant() can call $this->line()
     * without going through the tenant loop in handle().
     */
    protected function makeCommand(): TurnListCleanupCommand
    {
        $cmd = app(TurnListCleanupCommand::class);
        $cmd->setLaravel(app());
        $cmd->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        return $cmd;
    }

    public function test_finishes_attendances_older_than_threshold(): void
    {
        // Atendimento com 13h de duração — órfão
        TurnListAttendance::create([
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeCode,
            'status' => TurnListAttendanceStatus::ACTIVE->value,
            'started_at' => now()->subHours(13),
        ]);

        $totals = $this->makeCommand()->scanTenant(12);

        $this->assertSame(1, $totals['attendances']);

        $att = TurnListAttendance::where('employee_id', $this->employeeA)->first();
        $this->assertSame(TurnListAttendanceStatus::FINISHED, $att->status);
        $this->assertNotNull($att->finished_at);
        $this->assertGreaterThan(0, $att->duration_seconds);
        $this->assertFalse($att->return_to_queue);
        $this->assertStringContainsString('cleanup', $att->notes);
    }

    public function test_keeps_attendances_younger_than_threshold(): void
    {
        TurnListAttendance::create([
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeCode,
            'status' => TurnListAttendanceStatus::ACTIVE->value,
            'started_at' => now()->subHours(2),
        ]);

        $totals = $this->makeCommand()->scanTenant(12);

        $this->assertSame(0, $totals['attendances']);

        $att = TurnListAttendance::where('employee_id', $this->employeeA)->first();
        $this->assertSame(TurnListAttendanceStatus::ACTIVE, $att->status);
    }

    public function test_finishes_breaks_older_than_threshold(): void
    {
        TurnListBreak::create([
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeCode,
            'break_type_id' => 1,
            'original_queue_position' => 1,
            'status' => TurnListAttendanceStatus::ACTIVE->value,
            'started_at' => now()->subHours(15),
        ]);

        $totals = $this->makeCommand()->scanTenant(12);

        $this->assertSame(1, $totals['breaks']);

        $break = TurnListBreak::where('employee_id', $this->employeeA)->first();
        $this->assertSame(TurnListAttendanceStatus::FINISHED, $break->status);
        $this->assertNotNull($break->finished_at);
        $this->assertGreaterThan(0, $break->duration_seconds);
    }

    public function test_clears_entire_queue(): void
    {
        TurnListQueueEntry::create([
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeCode,
            'position' => 1,
            'entered_at' => now()->subMinutes(5),
        ]);
        TurnListQueueEntry::create([
            'employee_id' => $this->employeeB,
            'store_code' => $this->storeCode,
            'position' => 2,
            'entered_at' => now()->subMinutes(2),
        ]);

        $totals = $this->makeCommand()->scanTenant(12);

        $this->assertSame(2, $totals['queue_entries']);
        $this->assertSame(0, TurnListQueueEntry::count());
    }

    public function test_idempotent_does_not_reprocess_finished(): void
    {
        TurnListAttendance::create([
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeCode,
            'status' => TurnListAttendanceStatus::FINISHED->value,
            'started_at' => now()->subHours(20),
            'finished_at' => now()->subHours(19),
            'duration_seconds' => 3_600,
        ]);

        $totals = $this->makeCommand()->scanTenant(12);

        $this->assertSame(0, $totals['attendances']);
    }

    public function test_respects_custom_hours_option(): void
    {
        // Atendimento com 5h — órfão se threshold=4h
        TurnListAttendance::create([
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeCode,
            'status' => TurnListAttendanceStatus::ACTIVE->value,
            'started_at' => now()->subHours(5),
        ]);

        $totals = $this->makeCommand()->scanTenant(4);
        $this->assertSame(1, $totals['attendances']);
    }

    public function test_processes_all_three_categories_in_one_run(): void
    {
        TurnListAttendance::create([
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeCode,
            'status' => TurnListAttendanceStatus::ACTIVE->value,
            'started_at' => now()->subHours(13),
        ]);

        TurnListBreak::create([
            'employee_id' => $this->employeeB,
            'store_code' => $this->storeCode,
            'break_type_id' => 1,
            'original_queue_position' => 1,
            'status' => TurnListAttendanceStatus::ACTIVE->value,
            'started_at' => now()->subHours(14),
        ]);

        TurnListQueueEntry::create([
            'employee_id' => $this->employeeA,
            'store_code' => $this->storeCode,
            'position' => 1,
            'entered_at' => now()->subMinutes(30),
        ]);

        $totals = $this->makeCommand()->scanTenant(12);

        $this->assertSame(1, $totals['attendances']);
        $this->assertSame(1, $totals['breaks']);
        $this->assertSame(1, $totals['queue_entries']);
    }
}
