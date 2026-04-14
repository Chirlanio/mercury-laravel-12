<?php

namespace Tests\Feature;

use App\Enums\VacancyRequestType;
use App\Enums\VacancyStatus;
use App\Models\Employee;
use App\Models\Store;
use App\Models\Vacancy;
use App\Services\VacancyTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class VacancyTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Employee $employee;

    protected VacancyTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja']);
        $this->employee = Employee::factory()->create([
            'name' => 'Funcionário',
            'store_id' => $this->store->code,
            'position_id' => 1,
            'status_id' => 2,
            'area_id' => 1,
        ]);

        $this->service = app(VacancyTransitionService::class);
    }

    public function test_open_to_processing_requires_recruiter(): void
    {
        $vacancy = $this->createVacancy();

        $this->expectException(ValidationException::class);
        $this->service->transition($vacancy, VacancyStatus::PROCESSING, $this->adminUser);
    }

    public function test_open_to_processing_succeeds_with_recruiter(): void
    {
        $vacancy = $this->createVacancy();

        $result = $this->service->transition(
            $vacancy,
            VacancyStatus::PROCESSING,
            $this->adminUser,
            'Atribuindo recrutador',
            ['recruiter_id' => $this->supportUser->id]
        );

        $this->assertEquals('processing', $result->status->value);
        $this->assertEquals($this->supportUser->id, $result->recruiter_id);
        // Apenas a transição gera history no helper local (o helper não chama
        // VacancyService::create, que é quem registra o estado inicial).
        $this->assertEquals(1, $result->statusHistory()->count());
        $this->assertEquals('open', $result->statusHistory->first()->from_status);
        $this->assertEquals('processing', $result->statusHistory->first()->to_status);
    }

    public function test_open_cannot_transition_directly_to_finalized(): void
    {
        $vacancy = $this->createVacancy();

        $this->expectException(ValidationException::class);
        $this->service->transition($vacancy, VacancyStatus::FINALIZED, $this->adminUser);
    }

    public function test_processing_to_in_admission_keeps_recruiter(): void
    {
        $vacancy = $this->createVacancy([
            'status' => 'processing',
            'recruiter_id' => $this->supportUser->id,
        ]);

        $result = $this->service->transition($vacancy, VacancyStatus::IN_ADMISSION, $this->adminUser);

        $this->assertEquals('in_admission', $result->status->value);
    }

    public function test_cancel_requires_note(): void
    {
        $vacancy = $this->createVacancy();

        $this->expectException(ValidationException::class);
        $this->service->transition($vacancy, VacancyStatus::CANCELLED, $this->adminUser, '');
    }

    public function test_cancel_sets_closing_date(): void
    {
        $vacancy = $this->createVacancy();

        $result = $this->service->transition(
            $vacancy,
            VacancyStatus::CANCELLED,
            $this->adminUser,
            'Vaga não é mais necessária'
        );

        $this->assertEquals('cancelled', $result->status->value);
        $this->assertEquals(now()->toDateString(), $result->closing_date->toDateString());
    }

    public function test_finalized_is_terminal(): void
    {
        // Precisamos criar um Employee adicional para servir como "hired"
        $hired = Employee::factory()->create([
            'name' => 'Contratado',
            'store_id' => $this->store->code,
            'position_id' => 1,
            'status_id' => 1,
            'area_id' => 1,
        ]);

        $vacancy = $this->createVacancy([
            'status' => 'in_admission',
            'recruiter_id' => $this->supportUser->id,
            'hired_employee_id' => $hired->id,
            'date_admission' => now()->toDateString(),
        ]);

        $result = $this->service->transition(
            $vacancy,
            VacancyStatus::FINALIZED,
            $this->adminUser,
            'Finalizada'
        );

        $this->assertEquals('finalized', $result->status->value);
        $this->assertTrue($result->isTerminal());

        // Tentar transitar vaga terminal falha
        $this->expectException(ValidationException::class);
        $this->service->transition($result, VacancyStatus::OPEN, $this->adminUser);
    }

    public function test_cancelled_is_terminal(): void
    {
        $vacancy = $this->createVacancy();
        $this->service->transition($vacancy, VacancyStatus::CANCELLED, $this->adminUser, 'Cancelada');

        $this->expectException(ValidationException::class);
        $this->service->transition($vacancy->fresh(), VacancyStatus::OPEN, $this->adminUser);
    }

    public function test_finalized_calculates_effective_sla(): void
    {
        $hired = Employee::factory()->create([
            'name' => 'Contratado',
            'store_id' => $this->store->code,
            'position_id' => 1,
            'status_id' => 1,
            'area_id' => 1,
        ]);

        $vacancy = $this->createVacancy([
            'status' => 'in_admission',
            'recruiter_id' => $this->supportUser->id,
            'hired_employee_id' => $hired->id,
            'date_admission' => now()->toDateString(),
        ]);
        // Simular vaga criada 5 dias atrás
        $vacancy->created_at = now()->subDays(5);
        $vacancy->save();

        $result = $this->service->transition(
            $vacancy,
            VacancyStatus::FINALIZED,
            $this->adminUser,
            'ok'
        );

        $this->assertNotNull($result->effective_sla_days);
        $this->assertEquals(5, $result->effective_sla_days);
    }

    protected function createVacancy(array $overrides = []): Vacancy
    {
        return Vacancy::create(array_merge([
            'store_id' => $this->store->code,
            'position_id' => 1,
            'request_type' => VacancyRequestType::HEADCOUNT_INCREASE->value,
            'status' => VacancyStatus::OPEN->value,
            'predicted_sla_days' => 30,
            'delivery_forecast' => now()->addDays(30),
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }
}
