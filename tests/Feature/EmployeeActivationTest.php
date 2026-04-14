<?php

namespace Tests\Feature;

use App\Jobs\SendExperienceNotificationJob;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\ExperienceEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre o fluxo de ativação de funcionário pré-cadastrado:
 * quando um Employee em status_id=1 (Pendente) — tipicamente criado pelo
 * VacancyIntegrationService ao finalizar uma vaga — tem seu status alterado
 * via EmployeeController::update, o hook activatePreRegisteredEmployee()
 * cria o EmploymentContract de admissão e as ExperienceEvaluations 45/90d.
 *
 * Também valida idempotência: re-editar um funcionário já ativo, ou
 * re-ativar um pendente cujo contrato já existe, não duplica registros.
 */
class EmployeeActivationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->createTestStore('Z999');
        Queue::fake();
    }

    /**
     * Helper: cria um Employee diretamente em estado Pendente, simulando
     * o que o VacancyIntegrationService::preRegisterEmployeeFromVacancy faz.
     * Intencionalmente NÃO cria contrato nem avaliações.
     */
    protected function createPreRegisteredEmployee(array $overrides = []): Employee
    {
        $id = DB::table('employees')->insertGetId(array_merge([
            'name' => 'PRE REGISTERED EMP',
            'short_name' => 'PRE REGISTERED EMP',
            'cpf' => '11122233344',
            'admission_date' => now()->toDateString(),
            'birth_date' => '1990-01-01',
            'position_id' => 1,
            'store_id' => 'Z999',
            'education_level_id' => 1,
            'gender_id' => 1,
            'area_id' => 1,
            'level' => 'Junior',
            'status_id' => 1, // Pendente
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return Employee::findOrFail($id);
    }

    /**
     * Helper: payload para o update() que simula o DP completando o cadastro.
     * Os defaults copiam dados do próprio employee para evitar validação falhar.
     */
    protected function validUpdatePayload(Employee $employee, array $overrides = []): array
    {
        return array_merge([
            'name' => $employee->name,
            'cpf' => $employee->cpf,
            'admission_date' => $employee->admission_date?->format('Y-m-d'),
            'position_id' => $employee->position_id,
            'store_id' => $employee->store_id,
            'status_id' => 2, // Ativo (transição principal)
            'education_level_id' => 1,
            'gender_id' => 1,
            'area_id' => 1,
            'level' => 'Junior',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Casos principais
    // ------------------------------------------------------------------

    public function test_activating_a_pre_registered_employee_creates_employment_contract(): void
    {
        $employee = $this->createPreRegisteredEmployee();
        $this->assertEquals(0, EmploymentContract::where('employee_id', $employee->id)->count());

        $this->actingAs($this->adminUser)
            ->put(route('employees.update', $employee->id), $this->validUpdatePayload($employee));

        $contract = EmploymentContract::where('employee_id', $employee->id)->first();
        $this->assertNotNull($contract);
        $this->assertEquals(1, $contract->movement_type_id); // Admissão
        $this->assertEquals($employee->position_id, $contract->position_id);
        $this->assertEquals($employee->store_id, $contract->store_id);
        $this->assertTrue((bool) $contract->is_active);
        $this->assertNull($contract->end_date);
    }

    public function test_activating_a_pre_registered_employee_creates_45_and_90_day_evaluations(): void
    {
        $employee = $this->createPreRegisteredEmployee([
            'admission_date' => '2026-04-01',
        ]);

        $this->actingAs($this->adminUser)
            ->put(route('employees.update', $employee->id), $this->validUpdatePayload($employee, [
                'admission_date' => '2026-04-01',
            ]));

        $evaluations = ExperienceEvaluation::where('employee_id', $employee->id)
            ->orderBy('milestone')
            ->get();

        $this->assertCount(2, $evaluations);
        $this->assertEquals('45', $evaluations[0]->milestone);
        $this->assertEquals('90', $evaluations[1]->milestone);
        $this->assertEquals('2026-05-16', $evaluations[0]->milestone_date->format('Y-m-d'));
        $this->assertEquals('2026-06-30', $evaluations[1]->milestone_date->format('Y-m-d'));

        // Cada avaliação deve ter disparado uma notificação (via queue fake)
        Queue::assertPushed(SendExperienceNotificationJob::class, 2);
    }

    public function test_activation_fires_only_when_status_leaves_pendente(): void
    {
        $employee = $this->createPreRegisteredEmployee();

        // Atualização que NÃO muda o status (continua Pendente) — não deve
        // criar contrato nem avaliações.
        $this->actingAs($this->adminUser)
            ->put(route('employees.update', $employee->id), $this->validUpdatePayload($employee, [
                'status_id' => 1, // continua Pendente
                'name' => 'UPDATED NAME',
            ]));

        $this->assertEquals(0, EmploymentContract::where('employee_id', $employee->id)->count());
        $this->assertEquals(0, ExperienceEvaluation::where('employee_id', $employee->id)->count());
        Queue::assertNothingPushed();
    }

    // ------------------------------------------------------------------
    // Idempotência
    // ------------------------------------------------------------------

    public function test_updating_an_already_active_employee_does_not_create_contract(): void
    {
        // Employee criado direto como Ativo (fluxo normal, não pré-cadastro)
        $employeeId = $this->createTestEmployee(['cpf' => '22233344455', 'status_id' => 2]);
        $employee = Employee::findOrFail($employeeId);

        $this->actingAs($this->adminUser)
            ->put(route('employees.update', $employee->id), $this->validUpdatePayload($employee, [
                'name' => 'NOVO NOME',
            ]));

        $this->assertEquals(0, EmploymentContract::where('employee_id', $employee->id)->count());
        Queue::assertNothingPushed();
    }

    public function test_re_activation_does_not_duplicate_contract_when_active_one_exists(): void
    {
        // Cenário: funcionário pré-cadastrado, ativado (cria contrato),
        // volta para Pendente por engano (admin corrige), e é re-ativado.
        // Na segunda ativação NÃO deve criar outro contrato.
        $employee = $this->createPreRegisteredEmployee();

        // Primeira ativação
        $this->actingAs($this->adminUser)
            ->put(route('employees.update', $employee->id), $this->validUpdatePayload($employee));
        $this->assertEquals(1, EmploymentContract::where('employee_id', $employee->id)->count());

        // Volta para Pendente
        $employee->refresh();
        $this->actingAs($this->adminUser)
            ->put(route('employees.update', $employee->id), $this->validUpdatePayload($employee, [
                'status_id' => 1,
            ]));

        // Segunda ativação
        $employee->refresh();
        $this->actingAs($this->adminUser)
            ->put(route('employees.update', $employee->id), $this->validUpdatePayload($employee, [
                'status_id' => 2,
            ]));

        // Ainda deve haver apenas 1 contrato ativo
        $this->assertEquals(
            1,
            EmploymentContract::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->count()
        );
    }

    public function test_re_activation_does_not_duplicate_evaluations(): void
    {
        $employee = $this->createPreRegisteredEmployee();

        // Primeira ativação
        $this->actingAs($this->adminUser)
            ->put(route('employees.update', $employee->id), $this->validUpdatePayload($employee));
        $this->assertEquals(2, ExperienceEvaluation::where('employee_id', $employee->id)->count());

        // Volta para Pendente e re-ativa
        $employee->refresh();
        $this->actingAs($this->adminUser)
            ->put(route('employees.update', $employee->id), $this->validUpdatePayload($employee, [
                'status_id' => 1,
            ]));
        $employee->refresh();
        $this->actingAs($this->adminUser)
            ->put(route('employees.update', $employee->id), $this->validUpdatePayload($employee, [
                'status_id' => 2,
            ]));

        // Ainda devem existir apenas 2 avaliações (45 + 90) — uma por milestone
        $this->assertEquals(2, ExperienceEvaluation::where('employee_id', $employee->id)->count());
    }

    public function test_activation_works_for_any_non_pendente_status(): void
    {
        // Se admin por algum motivo ativa direto para status=4 (Férias)
        // ou 5 (Licença), ainda assim é uma "saída do estado Pendente",
        // o contrato deve ser criado.
        $employee = $this->createPreRegisteredEmployee();

        $this->actingAs($this->adminUser)
            ->put(route('employees.update', $employee->id), $this->validUpdatePayload($employee, [
                'status_id' => 4, // Férias
            ]));

        $this->assertEquals(1, EmploymentContract::where('employee_id', $employee->id)->count());
    }
}
