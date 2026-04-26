<?php

namespace Tests\Feature\TurnList;

use App\Enums\TurnListAttendanceStatus;
use App\Models\TurnListAttendance;
use App\Models\TurnListAttendanceOutcome;
use App\Models\TurnListQueueEntry;
use App\Services\TurnListAttendanceService;
use App\Services\TurnListQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TurnListAttendanceServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected TurnListAttendanceService $service;
    protected TurnListQueueService $queueService;
    protected string $storeCode = 'Z421';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore($this->storeCode);

        $this->service = app(TurnListAttendanceService::class);
        $this->queueService = app(TurnListQueueService::class);
    }

    protected function makeConsultora(string $name = 'Consultora'): int
    {
        return $this->createTestEmployee([
            'store_id' => $this->storeCode,
            'name' => $name.' '.uniqid(),
            'cpf' => str_pad((string) random_int(1, 99_999_999_999), 11, '0', STR_PAD_LEFT),
            'position_id' => 1,
            'status_id' => 2,
        ]);
    }

    protected function vendaOutcome(): TurnListAttendanceOutcome
    {
        return TurnListAttendanceOutcome::where('name', 'Venda Realizada')->first();
    }

    protected function retornaVezOutcome(): TurnListAttendanceOutcome
    {
        return TurnListAttendanceOutcome::where('name', 'Preferência/Retorna vez')->first();
    }

    public function test_start_captures_original_position_and_removes_from_queue(): void
    {
        $a = $this->makeConsultora();
        $b = $this->makeConsultora();

        $this->queueService->enter($a, $this->storeCode);
        $this->queueService->enter($b, $this->storeCode);

        $att = $this->service->start($b, $this->storeCode, $this->adminUser);

        $this->assertSame(2, $att->original_queue_position);
        $this->assertSame(TurnListAttendanceStatus::ACTIVE, $att->status);
        $this->assertNotNull($att->started_at);
        $this->assertSame($this->adminUser->id, $att->created_by_user_id);

        // Removeu da fila
        $this->assertNull($this->queueService->getPosition($b, $this->storeCode));
    }

    public function test_start_works_when_employee_not_in_queue(): void
    {
        $a = $this->makeConsultora();

        $att = $this->service->start($a, $this->storeCode);

        $this->assertNull($att->original_queue_position);
        $this->assertSame(TurnListAttendanceStatus::ACTIVE, $att->status);
    }

    public function test_start_blocks_if_already_attending(): void
    {
        $a = $this->makeConsultora();
        $this->service->start($a, $this->storeCode);

        $this->expectException(ValidationException::class);
        $this->service->start($a, $this->storeCode);
    }

    public function test_finish_calculates_duration_and_returns_to_end_of_queue_by_default(): void
    {
        $a = $this->makeConsultora();
        $this->queueService->enter($a, $this->storeCode);

        $att = $this->service->start($a, $this->storeCode);
        // Volta no tempo pra ter duração mensurável
        $att->update(['started_at' => now()->subMinutes(5)]);

        $finished = $this->service->finish($att->fresh(), $this->vendaOutcome()->id);

        $this->assertSame(TurnListAttendanceStatus::FINISHED, $finished->status);
        $this->assertGreaterThanOrEqual(290, $finished->duration_seconds);
        $this->assertNotNull($finished->finished_at);
        $this->assertTrue($finished->return_to_queue);

        // Voltou pra fila no fim
        $this->assertSame(1, $this->queueService->getPosition($a, $this->storeCode));
    }

    public function test_finish_skips_queue_when_returnToQueue_is_false(): void
    {
        $a = $this->makeConsultora();
        $this->queueService->enter($a, $this->storeCode);

        $att = $this->service->start($a, $this->storeCode);
        $this->service->finish($att->fresh(), $this->vendaOutcome()->id, returnToQueue: false);

        $this->assertNull($this->queueService->getPosition($a, $this->storeCode));
    }

    public function test_finish_throws_if_already_finished(): void
    {
        $a = $this->makeConsultora();
        $att = $this->service->start($a, $this->storeCode);
        $this->service->finish($att->fresh(), $this->vendaOutcome()->id);

        $this->expectException(ValidationException::class);
        $this->service->finish($att->fresh(), $this->vendaOutcome()->id);
    }

    public function test_finish_throws_with_invalid_outcome(): void
    {
        $a = $this->makeConsultora();
        $att = $this->service->start($a, $this->storeCode);

        $this->expectException(ValidationException::class);
        $this->service->finish($att->fresh(), 99_999);
    }

    public function test_restore_position_uses_original_when_outcome_flag_set(): void
    {
        $consultoras = collect(range(1, 5))->map(fn () => $this->makeConsultora())->all();

        foreach ($consultoras as $id) {
            $this->queueService->enter($id, $this->storeCode);
        }

        // Consultora #3 (pos 3) inicia atendimento — outcome restore_queue_position=true
        $att = $this->service->start($consultoras[2], $this->storeCode);
        $this->assertSame(3, $att->original_queue_position);

        // Finaliza com outcome de retorna vez (sem outras saírem na frente)
        $this->service->finish($att->fresh(), $this->retornaVezOutcome()->id);

        // Volta na pos 3 (max(1, 3 - 0))
        $this->assertSame(3, $this->queueService->getPosition($consultoras[2], $this->storeCode));
    }

    public function test_aheadCount_algorithm_adjusts_when_consultoras_in_front_left_after(): void
    {
        // Cenário crítico v1:
        //  - 5 consultoras na fila (pos 1..5)
        //  - C5 (pos 5) sai pra atender PRIMEIRO
        //  - C2 (pos 2) sai depois
        //  - C1 (pos 1) sai depois
        //  - C5 finaliza com restore_queue_position=true
        //
        // aheadCount de C5 = consultoras com pos<5 que saíram >= started_at de C5
        //                  = C2 e C1 = 2
        // adjustedPosition = max(1, 5 - 2) = 3
        $consultoras = collect(range(1, 5))->map(fn () => $this->makeConsultora())->all();

        foreach ($consultoras as $id) {
            $this->queueService->enter($id, $this->storeCode);
        }

        // C5 (pos 5) sai primeiro
        $att5 = $this->service->start($consultoras[4], $this->storeCode);
        $att5->update(['started_at' => now()->subMinutes(10)]);

        // C2 sai depois
        $att2 = $this->service->start($consultoras[1], $this->storeCode);
        $att2->update(['started_at' => now()->subMinutes(8)]);

        // C1 sai depois
        $att1 = $this->service->start($consultoras[0], $this->storeCode);
        $att1->update(['started_at' => now()->subMinutes(6)]);

        // Recalcula aheadCount via service (com snapshot atual)
        $adjusted = $this->service->calculateAdjustedRestorePosition($att5->fresh());

        // pos 5 - aheadCount(2) = 3
        $this->assertSame(3, $adjusted);

        // Confirma o fluxo completo: C5 finaliza com retorna vez
        $this->service->finish($att5->fresh(), $this->retornaVezOutcome()->id);
        $this->assertSame(3, $this->queueService->getPosition($consultoras[4], $this->storeCode));
    }

    public function test_aheadCount_floor_at_one(): void
    {
        $consultoras = collect(range(1, 3))->map(fn () => $this->makeConsultora())->all();

        foreach ($consultoras as $id) {
            $this->queueService->enter($id, $this->storeCode);
        }

        // C3 sai primeiro
        $att3 = $this->service->start($consultoras[2], $this->storeCode);
        $att3->update(['started_at' => now()->subMinutes(5)]);

        // C1 e C2 saem depois (ambos à frente do C3)
        $att1 = $this->service->start($consultoras[0], $this->storeCode);
        $att1->update(['started_at' => now()->subMinutes(3)]);
        $att2 = $this->service->start($consultoras[1], $this->storeCode);
        $att2->update(['started_at' => now()->subMinutes(1)]);

        // pos 3 - aheadCount(2) = 1 (não desce abaixo de 1)
        $this->assertSame(1, $this->service->calculateAdjustedRestorePosition($att3->fresh()));
    }

    public function test_get_active_by_employee(): void
    {
        $a = $this->makeConsultora();

        $this->assertNull($this->service->getActiveByEmployee($a));

        $att = $this->service->start($a, $this->storeCode);
        $found = $this->service->getActiveByEmployee($a);

        $this->assertNotNull($found);
        $this->assertSame($att->id, $found->id);
    }
}
