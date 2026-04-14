<?php

namespace Tests\Feature;

use App\Enums\VacancyRequestType;
use App\Enums\VacancyStatus;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Store;
use App\Models\Vacancy;
use App\Services\VacancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class VacancyControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Store $store2;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
        $this->store2 = Store::factory()->create(['code' => 'Z425', 'name' => 'Loja Secundária']);

        $this->employee = Employee::factory()->create([
            'name' => 'João Silva',
            'store_id' => $this->store->code,
            'position_id' => 1,
            'status_id' => 2,
            'area_id' => 1,
        ]);
    }

    public function test_admin_can_view_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('vacancies.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Vacancies/Index'));
    }

    public function test_regular_user_cannot_view_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('vacancies.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_create_headcount_increase_vacancy(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('vacancies.store'), [
            'store_id' => $this->store->code,
            'position_id' => 1,
            'request_type' => VacancyRequestType::HEADCOUNT_INCREASE->value,
            'comments' => 'Crescimento da loja',
        ]);

        $response->assertRedirect(route('vacancies.index'));
        $this->assertDatabaseHas('vacancies', [
            'store_id' => $this->store->code,
            'position_id' => 1,
            'request_type' => 'headcount_increase',
            'status' => 'open',
        ]);

        // Histórico inicial registrado
        $vacancy = Vacancy::first();
        $this->assertEquals(1, $vacancy->statusHistory()->count());
        $this->assertNull($vacancy->statusHistory->first()->from_status);
        $this->assertEquals('open', $vacancy->statusHistory->first()->to_status);
    }

    public function test_substitution_requires_replaced_employee(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('vacancies.store'), [
            'store_id' => $this->store->code,
            'position_id' => 1,
            'request_type' => VacancyRequestType::SUBSTITUTION->value,
        ]);

        $response->assertSessionHasErrors('replaced_employee_id');
        $this->assertDatabaseMissing('vacancies', [
            'store_id' => $this->store->code,
            'request_type' => 'substitution',
        ]);
    }

    public function test_substitution_with_replaced_employee_succeeds(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('vacancies.store'), [
            'store_id' => $this->store->code,
            'position_id' => 1,
            'request_type' => VacancyRequestType::SUBSTITUTION->value,
            'replaced_employee_id' => $this->employee->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vacancies', [
            'store_id' => $this->store->code,
            'request_type' => 'substitution',
            'replaced_employee_id' => $this->employee->id,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('vacancies.store'), []);

        $response->assertSessionHasErrors(['store_id', 'position_id', 'request_type']);
        // predicted_sla_days NÃO é mais validado aqui — é calculado pelo
        // VacancyService a partir do position.
        $response->assertSessionDoesntHaveErrors(['predicted_sla_days', 'delivery_forecast']);
    }

    public function test_destroy_requires_reason(): void
    {
        $vacancy = $this->createTestVacancy();

        $response = $this->actingAs($this->adminUser)->delete(route('vacancies.destroy', $vacancy->id));

        $response->assertSessionHasErrors('deleted_reason');
        $this->assertNull($vacancy->fresh()->deleted_at);
    }

    public function test_destroy_with_reason_soft_deletes(): void
    {
        $vacancy = $this->createTestVacancy();

        $response = $this->actingAs($this->adminUser)->delete(
            route('vacancies.destroy', $vacancy->id),
            ['deleted_reason' => 'Duplicata de outra vaga']
        );

        $response->assertRedirect(route('vacancies.index'));
        $fresh = $vacancy->fresh();
        $this->assertNotNull($fresh->deleted_at);
        $this->assertEquals('Duplicata de outra vaga', $fresh->deleted_reason);
        $this->assertEquals($this->adminUser->id, $fresh->deleted_by_user_id);
    }

    public function test_managerial_position_applies_40_day_sla(): void
    {
        // Position id=1 (Vendedor) no TestHelpers tem level_category_id=1,
        // o mesmo id usado pelo PositionLevelSeeder para "Gerencial" em
        // producao. Tratamos como gerencial nos testes.
        $this->actingAs($this->adminUser)->post(route('vacancies.store'), [
            'store_id' => $this->store->code,
            'position_id' => 1,
            'request_type' => VacancyRequestType::HEADCOUNT_INCREASE->value,
        ]);

        $vacancy = Vacancy::first();
        $this->assertEquals(VacancyService::SLA_MANAGERIAL_DAYS, $vacancy->predicted_sla_days);
        $this->assertEquals(
            now()->addDays(VacancyService::SLA_MANAGERIAL_DAYS)->toDateString(),
            $vacancy->delivery_forecast->toDateString()
        );
    }

    public function test_operational_position_applies_20_day_sla(): void
    {
        // Cria uma position com level_category_id=2 (operacional) só pra
        // este teste, pois o TestHelpers so semeia category_id=1.
        $operationalPositionId = DB::table('positions')->insertGetId([
            'name' => 'Caixa',
            'level' => 'Junior',
            'level_category_id' => 2,
            'status_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->adminUser)->post(route('vacancies.store'), [
            'store_id' => $this->store->code,
            'position_id' => $operationalPositionId,
            'request_type' => VacancyRequestType::HEADCOUNT_INCREASE->value,
        ]);

        $vacancy = Vacancy::where('position_id', $operationalPositionId)->first();
        $this->assertNotNull($vacancy);
        $this->assertEquals(VacancyService::SLA_OPERATIONAL_DAYS, $vacancy->predicted_sla_days);
        $this->assertEquals(
            now()->addDays(VacancyService::SLA_OPERATIONAL_DAYS)->toDateString(),
            $vacancy->delivery_forecast->toDateString()
        );
    }

    public function test_sla_in_payload_is_ignored_on_create(): void
    {
        // Mesmo se o cliente insistir em mandar predicted_sla_days, o
        // backend ignora e aplica o SLA resolvido do position.
        $this->actingAs($this->adminUser)->post(route('vacancies.store'), [
            'store_id' => $this->store->code,
            'position_id' => 1,
            'request_type' => VacancyRequestType::HEADCOUNT_INCREASE->value,
            'predicted_sla_days' => 7, // tentando forçar 7 dias
        ]);

        $vacancy = Vacancy::first();
        $this->assertEquals(VacancyService::SLA_MANAGERIAL_DAYS, $vacancy->predicted_sla_days);
    }

    public function test_show_returns_detailed_vacancy(): void
    {
        $vacancy = $this->createTestVacancy();

        $response = $this->actingAs($this->adminUser)->get(route('vacancies.show', $vacancy->id));
        $response->assertStatus(200);
        $response->assertJson(['vacancy' => ['id' => $vacancy->id]]);
    }

    public function test_statistics_endpoint_returns_counts(): void
    {
        $this->createTestVacancy();
        $this->createTestVacancy(['status' => VacancyStatus::PROCESSING->value]);

        $response = $this->actingAs($this->adminUser)->get(route('vacancies.statistics'));

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('statistics', $data);
        $this->assertEquals(1, $data['statistics']['open']);
        $this->assertEquals(1, $data['statistics']['processing']);
        $this->assertEquals(2, $data['statistics']['total_active']);
    }

    // ------------------------------------------------------------------

    protected function createTestVacancy(array $overrides = []): Vacancy
    {
        return Vacancy::create(array_merge([
            'store_id' => $this->store->code,
            'position_id' => 1,
            'request_type' => VacancyRequestType::HEADCOUNT_INCREASE->value,
            'status' => VacancyStatus::OPEN->value,
            'predicted_sla_days' => VacancyService::SLA_MANAGERIAL_DAYS,
            'delivery_forecast' => now()->addDays(VacancyService::SLA_MANAGERIAL_DAYS),
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }
}
