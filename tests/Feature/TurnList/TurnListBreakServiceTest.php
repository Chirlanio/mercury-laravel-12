<?php

namespace Tests\Feature\TurnList;

use App\Enums\TurnListAttendanceStatus;
use App\Models\TurnListBreakType;
use App\Models\TurnListStoreSetting;
use App\Services\TurnListBreakService;
use App\Services\TurnListQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TurnListBreakServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected TurnListBreakService $service;
    protected TurnListQueueService $queueService;
    protected string $storeCode = 'Z421';
    protected int $intervaloId;
    protected int $almocoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore($this->storeCode);

        $this->intervaloId = TurnListBreakType::where('name', 'Intervalo')->value('id');
        $this->almocoId = TurnListBreakType::where('name', 'Almoço')->value('id');

        $this->service = app(TurnListBreakService::class);
        $this->queueService = app(TurnListQueueService::class);
    }

    protected function makeConsultora(): int
    {
        return $this->createTestEmployee([
            'store_id' => $this->storeCode,
            'name' => 'Consultora '.uniqid(),
            'cpf' => str_pad((string) random_int(1, 99_999_999_999), 11, '0', STR_PAD_LEFT),
            'position_id' => 1,
            'status_id' => 2,
        ]);
    }

    public function test_start_requires_employee_to_be_in_queue(): void
    {
        $a = $this->makeConsultora();

        $this->expectException(ValidationException::class);
        $this->service->start($a, $this->storeCode, $this->intervaloId);
    }

    public function test_start_captures_position_and_removes_from_queue(): void
    {
        $a = $this->makeConsultora();
        $b = $this->makeConsultora();

        $this->queueService->enter($a, $this->storeCode);
        $this->queueService->enter($b, $this->storeCode);

        $break = $this->service->start($b, $this->storeCode, $this->intervaloId, $this->adminUser);

        $this->assertSame(2, $break->original_queue_position);
        $this->assertSame(TurnListAttendanceStatus::ACTIVE, $break->status);
        $this->assertSame($this->intervaloId, $break->break_type_id);
        $this->assertNull($this->queueService->getPosition($b, $this->storeCode));
    }

    public function test_start_throws_if_already_on_break(): void
    {
        $a = $this->makeConsultora();
        $this->queueService->enter($a, $this->storeCode);
        $this->service->start($a, $this->storeCode, $this->intervaloId);

        $this->expectException(ValidationException::class);
        $this->service->start($a, $this->storeCode, $this->almocoId);
    }

    public function test_start_validates_break_type(): void
    {
        $a = $this->makeConsultora();
        $this->queueService->enter($a, $this->storeCode);

        $this->expectException(ValidationException::class);
        $this->service->start($a, $this->storeCode, 99_999);
    }

    public function test_finish_restores_to_original_position_by_default(): void
    {
        $a = $this->makeConsultora();
        $b = $this->makeConsultora();
        $c = $this->makeConsultora();

        $this->queueService->enter($a, $this->storeCode);
        $this->queueService->enter($b, $this->storeCode);
        $this->queueService->enter($c, $this->storeCode);

        // B (pos 2) entra em pausa
        $break = $this->service->start($b, $this->storeCode, $this->intervaloId);
        $break->update(['started_at' => now()->subMinutes(10)]);

        // Default da loja: return_to_position=true (sem registro em store_settings)
        $finished = $this->service->finish($break->fresh());

        $this->assertSame(TurnListAttendanceStatus::FINISHED, $finished->status);
        $this->assertGreaterThanOrEqual(590, $finished->duration_seconds);

        // Voltou na posição 2
        $this->assertSame(2, $this->queueService->getPosition($b, $this->storeCode));
    }

    public function test_finish_appends_to_end_when_store_setting_disabled(): void
    {
        TurnListStoreSetting::create([
            'store_code' => $this->storeCode,
            'return_to_position' => false,
        ]);

        $a = $this->makeConsultora();
        $b = $this->makeConsultora();

        $this->queueService->enter($a, $this->storeCode);
        $this->queueService->enter($b, $this->storeCode);

        // B (pos 2) pausa, A continua → ao voltar, B vai pro fim
        $break = $this->service->start($b, $this->storeCode, $this->intervaloId);
        $this->service->finish($break->fresh());

        // A na pos 1 (continuou), B na pos 2 (única que estava na fila + foi pro fim)
        // Como A não saiu e B voltou no fim, B pega pos 2
        $posA = $this->queueService->getPosition($a, $this->storeCode);
        $posB = $this->queueService->getPosition($b, $this->storeCode);

        $this->assertSame(1, $posA);
        $this->assertSame(2, $posB);
    }

    public function test_finish_throws_if_already_finished(): void
    {
        $a = $this->makeConsultora();
        $this->queueService->enter($a, $this->storeCode);
        $break = $this->service->start($a, $this->storeCode, $this->intervaloId);
        $this->service->finish($break->fresh());

        $this->expectException(ValidationException::class);
        $this->service->finish($break->fresh());
    }

    public function test_get_active_by_employee(): void
    {
        $a = $this->makeConsultora();

        $this->assertNull($this->service->getActiveByEmployee($a));

        $this->queueService->enter($a, $this->storeCode);
        $break = $this->service->start($a, $this->storeCode, $this->intervaloId);
        $found = $this->service->getActiveByEmployee($a);

        $this->assertNotNull($found);
        $this->assertSame($break->id, $found->id);
    }
}
