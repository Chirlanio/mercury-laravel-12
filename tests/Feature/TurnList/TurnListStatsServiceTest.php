<?php

namespace Tests\Feature\TurnList;

use App\Enums\TurnListAttendanceStatus;
use App\Models\TurnListAttendance;
use App\Models\TurnListAttendanceOutcome;
use App\Models\TurnListBreak;
use App\Services\TurnListStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TurnListStatsServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected TurnListStatsService $service;
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
            'name' => 'Consultora A',
            'cpf' => '11122233344',
            'position_id' => 1,
            'status_id' => 2,
        ]);

        $this->employeeB = $this->createTestEmployee([
            'store_id' => $this->storeCode,
            'name' => 'Consultora B',
            'cpf' => '22233344455',
            'position_id' => 1,
            'status_id' => 2,
        ]);

        $this->service = app(TurnListStatsService::class);
    }

    /**
     * Cria atendimento finalizado. Default: hoje às 10h (longe das bordas
     * de meia-noite pra evitar flakiness se o teste rodar na virada do dia).
     */
    protected function makeFinishedAttendance(int $employeeId, ?int $outcomeId, int $durationSeconds, ?string $startedAt = null): void
    {
        $start = $startedAt
            ? \Carbon\Carbon::parse($startedAt)
            : now()->startOfDay()->addHours(10);

        TurnListAttendance::create([
            'employee_id' => $employeeId,
            'store_code' => $this->storeCode,
            'status' => TurnListAttendanceStatus::FINISHED->value,
            'started_at' => $start,
            'finished_at' => $start->copy()->addSeconds($durationSeconds),
            'duration_seconds' => $durationSeconds,
            'outcome_id' => $outcomeId,
            'return_to_queue' => true,
        ]);
    }

    protected function makeFinishedBreak(int $employeeId, int $breakTypeId, int $durationSeconds): void
    {
        $start = now()->startOfDay()->addHours(10);

        TurnListBreak::create([
            'employee_id' => $employeeId,
            'store_code' => $this->storeCode,
            'break_type_id' => $breakTypeId,
            'original_queue_position' => 1,
            'status' => TurnListAttendanceStatus::FINISHED->value,
            'started_at' => $start,
            'finished_at' => $start->copy()->addSeconds($durationSeconds),
            'duration_seconds' => $durationSeconds,
        ]);
    }

    public function test_period_today_returns_only_today_records(): void
    {
        $venda = TurnListAttendanceOutcome::where('name', 'Venda Realizada')->first();

        // Hoje (10h)
        $this->makeFinishedAttendance($this->employeeA, $venda->id, 600);
        // Ontem (fora do período "today")
        $this->makeFinishedAttendance(
            $this->employeeA,
            $venda->id,
            600,
            now()->subDay()->startOfDay()->addHours(10)->toIso8601String(),
        );

        $report = $this->service->getReport($this->storeCode, 'today');

        $this->assertSame(1, $report['summary']['total_attendances']);
    }

    public function test_summary_calculates_conversion_rate(): void
    {
        $venda = TurnListAttendanceOutcome::where('name', 'Venda Realizada')->first();
        $pesquisa = TurnListAttendanceOutcome::where('name', 'Pesquisa')->first();

        $this->makeFinishedAttendance($this->employeeA, $venda->id, 300);
        $this->makeFinishedAttendance($this->employeeA, $venda->id, 400);
        $this->makeFinishedAttendance($this->employeeA, $pesquisa->id, 200);
        $this->makeFinishedAttendance($this->employeeB, $pesquisa->id, 100);

        $report = $this->service->getReport($this->storeCode, 'month');

        $this->assertSame(4, $report['summary']['total_attendances']);
        $this->assertSame(2, $report['summary']['total_employees']);
        $this->assertSame(2, $report['summary']['total_conversions']);
        $this->assertEquals(50.0, $report['summary']['conversion_rate']);
    }

    public function test_summary_handles_empty_period(): void
    {
        $report = $this->service->getReport($this->storeCode, 'today');

        $this->assertSame(0, $report['summary']['total_attendances']);
        $this->assertSame(0, $report['summary']['conversion_rate']);
        $this->assertSame(0, $report['summary']['avg_duration_seconds']);
    }

    public function test_top_employees_orders_by_volume(): void
    {
        $venda = TurnListAttendanceOutcome::where('name', 'Venda Realizada')->first();

        // A: 3 atendimentos, 2 vendas
        $this->makeFinishedAttendance($this->employeeA, $venda->id, 300);
        $this->makeFinishedAttendance($this->employeeA, $venda->id, 400);
        $this->makeFinishedAttendance($this->employeeA, null, 100);

        // B: 1 atendimento, 1 venda
        $this->makeFinishedAttendance($this->employeeB, $venda->id, 200);

        $report = $this->service->getReport($this->storeCode, 'month');

        $this->assertCount(2, $report['top_employees']);
        $this->assertSame($this->employeeA, $report['top_employees'][0]['employee_id']);
        $this->assertSame(3, $report['top_employees'][0]['attendances']);
        $this->assertSame(2, $report['top_employees'][0]['conversions']);
        $this->assertEquals(66.7, $report['top_employees'][0]['conversion_rate']);

        $this->assertSame($this->employeeB, $report['top_employees'][1]['employee_id']);
        $this->assertEquals(100.0, $report['top_employees'][1]['conversion_rate']);
    }

    public function test_by_outcome_groups_by_outcome_name(): void
    {
        $venda = TurnListAttendanceOutcome::where('name', 'Venda Realizada')->first();
        $pesquisa = TurnListAttendanceOutcome::where('name', 'Pesquisa')->first();

        $this->makeFinishedAttendance($this->employeeA, $venda->id, 100);
        $this->makeFinishedAttendance($this->employeeA, $pesquisa->id, 200);
        $this->makeFinishedAttendance($this->employeeB, $pesquisa->id, 300);

        $report = $this->service->getReport($this->storeCode, 'month');

        // Ordenado desc por count
        $this->assertSame('Pesquisa', $report['by_outcome'][0]['name']);
        $this->assertSame(2, $report['by_outcome'][0]['count']);
        $this->assertSame('Venda Realizada', $report['by_outcome'][1]['name']);
        $this->assertSame(1, $report['by_outcome'][1]['count']);
    }

    public function test_by_day_fills_empty_days_with_zero(): void
    {
        $venda = TurnListAttendanceOutcome::where('name', 'Venda Realizada')->first();

        // Apenas 1 atendimento hoje — restante do mês fica zero
        $this->makeFinishedAttendance($this->employeeA, $venda->id, 200);

        $report = $this->service->getReport($this->storeCode, 'month');

        // Série deve ter pelo menos 28 entradas (mês mínimo)
        $this->assertGreaterThanOrEqual(28, count($report['by_day']));
        $totalAttendances = array_sum(array_column($report['by_day'], 'attendances'));
        $this->assertSame(1, $totalAttendances);
    }

    public function test_by_hour_returns_24_buckets(): void
    {
        $report = $this->service->getReport($this->storeCode, 'month');

        $this->assertCount(24, $report['by_hour']);
        $this->assertSame(0, $report['by_hour'][0]['hour']);
        $this->assertSame(23, $report['by_hour'][23]['hour']);
    }

    public function test_break_stats_calculates_exceeded_pct(): void
    {
        // Intervalo (id=1, max 15min) — 1 dentro, 1 excedida
        $this->makeFinishedBreak($this->employeeA, 1, 600); // 10min — ok
        $this->makeFinishedBreak($this->employeeA, 1, 1_800); // 30min — excedida

        // Almoço (id=2, max 60min) — 1 dentro
        $this->makeFinishedBreak($this->employeeB, 2, 3_000); // 50min — ok

        $report = $this->service->getReport($this->storeCode, 'month');

        $intervalo = collect($report['break_stats'])->firstWhere('type_name', 'Intervalo');
        $almoco = collect($report['break_stats'])->firstWhere('type_name', 'Almoço');

        $this->assertSame(2, $intervalo['count']);
        $this->assertSame(1, $intervalo['exceeded_count']);
        $this->assertEquals(50.0, $intervalo['exceeded_pct']);

        $this->assertSame(1, $almoco['count']);
        $this->assertSame(0, $almoco['exceeded_count']);
    }

    public function test_period_is_returned_in_payload(): void
    {
        $report = $this->service->getReport($this->storeCode, 'month');

        $this->assertArrayHasKey('period', $report);
        $this->assertSame('Este mês', $report['period']['label']);
        $this->assertArrayHasKey('from', $report['period']);
        $this->assertArrayHasKey('to', $report['period']);
    }

    public function test_custom_period_uses_explicit_dates(): void
    {
        $venda = TurnListAttendanceOutcome::where('name', 'Venda Realizada')->first();
        $this->makeFinishedAttendance($this->employeeA, $venda->id, 100);

        $report = $this->service->getReport(
            $this->storeCode,
            'custom',
            now()->subDays(7)->toDateString(),
            now()->toDateString(),
        );

        $this->assertSame('Período customizado', $report['period']['label']);
        $this->assertSame(1, $report['summary']['total_attendances']);
    }

    public function test_storeCode_null_aggregates_all_stores(): void
    {
        $venda = TurnListAttendanceOutcome::where('name', 'Venda Realizada')->first();
        $this->makeFinishedAttendance($this->employeeA, $venda->id, 100);

        // Cria store + atendimento em outra loja
        $this->createTestStore('Z422');
        $employeeC = $this->createTestEmployee([
            'store_id' => 'Z422',
            'name' => 'Consultora C',
            'cpf' => '33344455566',
            'position_id' => 1,
            'status_id' => 2,
        ]);

        TurnListAttendance::create([
            'employee_id' => $employeeC,
            'store_code' => 'Z422',
            'status' => TurnListAttendanceStatus::FINISHED->value,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now(),
            'duration_seconds' => 600,
            'outcome_id' => $venda->id,
            'return_to_queue' => true,
        ]);

        $reportAll = $this->service->getReport(null, 'month');
        $this->assertSame(2, $reportAll['summary']['total_attendances']);

        $reportA = $this->service->getReport($this->storeCode, 'month');
        $this->assertSame(1, $reportA['summary']['total_attendances']);
    }
}
