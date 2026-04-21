<?php

namespace Tests\Feature;

use App\Models\AccountingClass;
use App\Models\BudgetUpload;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre a Fase 5 — Campo Área (area_department_id) no BudgetUpload.
 *
 * Regras validadas:
 *   - Upload válido com MC da área correta → OK
 *   - Upload sem area_department_id → 422
 *   - Upload com MC de outra área → 422 listando códigos divergentes
 *   - Endpoint /management-classes/departments?year usa area_department_id direto
 *     quando todos uploads ativos têm a coluna preenchida
 */
class BudgetAreaDepartmentTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected AccountingClass $ac;

    protected CostCenter $cc;

    protected ManagementClass $marketingDept;

    protected ManagementClass $comercialDept;

    protected ManagementClass $mcMarketing;

    protected ManagementClass $mcComercial;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        Storage::fake('local');

        $this->ac = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail();

        $this->cc = CostCenter::create([
            'code' => 'CC-AREA-TEST', 'name' => 'CC Teste',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->marketingDept = ManagementClass::where('code', '8.1.01')->firstOrFail();
        $this->comercialDept = ManagementClass::where('code', '8.1.09')->firstOrFail();

        $this->mcMarketing = ManagementClass::create([
            'code' => 'MC-AREA-MKT', 'name' => 'MC Marketing',
            'accepts_entries' => true,
            'parent_id' => $this->marketingDept->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->mcComercial = ManagementClass::create([
            'code' => 'MC-AREA-COM', 'name' => 'MC Comercial',
            'accepts_entries' => true,
            'parent_id' => $this->comercialDept->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    protected function makePayload(int $areaDepartmentId, ManagementClass $mc): array
    {
        return [
            'year' => 2026,
            'scope_label' => 'ÁreaTest',
            'area_department_id' => $areaDepartmentId,
            'upload_type' => 'novo',
            'file' => UploadedFile::fake()->create('orc.xlsx', 50),
            'items' => [[
                'accounting_class_id' => $this->ac->id,
                'management_class_id' => $mc->id,
                'cost_center_id' => $this->cc->id,
                'month_01_value' => 100,
            ]],
        ];
    }

    public function test_store_accepts_matching_area(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('budgets.store'), $this->makePayload(
                $this->marketingDept->id,
                $this->mcMarketing
            ))
            ->assertRedirect()
            ->assertSessionHas('success');

        $upload = BudgetUpload::where('scope_label', 'ÁreaTest')->firstOrFail();
        $this->assertEquals($this->marketingDept->id, $upload->area_department_id);
    }

    public function test_store_rejects_missing_area(): void
    {
        $payload = $this->makePayload($this->marketingDept->id, $this->mcMarketing);
        unset($payload['area_department_id']);

        $this->actingAs($this->adminUser)
            ->post(route('budgets.store'), $payload)
            ->assertSessionHasErrors(['area_department_id']);
    }

    public function test_store_rejects_mc_from_different_area(): void
    {
        // Tenta cadastrar upload de Marketing mas a MC é Comercial — 422
        $this->actingAs($this->adminUser)
            ->post(route('budgets.store'), $this->makePayload(
                $this->marketingDept->id,
                $this->mcComercial  // MC de outra área
            ))
            ->assertSessionHasErrors(['area_department_id']);

        $this->assertDatabaseMissing('budget_uploads', ['scope_label' => 'ÁreaTest']);
    }

    public function test_departments_endpoint_filters_by_area_department_id(): void
    {
        // Cria upload Marketing para 2026
        $this->actingAs($this->adminUser)->post(
            route('budgets.store'),
            $this->makePayload($this->marketingDept->id, $this->mcMarketing)
        );

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('management-classes.departments', ['year' => 2026]));

        $response->assertStatus(200);
        $departments = $response->json('departments');

        // Deve retornar só Marketing
        $codes = collect($departments)->pluck('code')->all();
        $this->assertContains('8.1.01', $codes);
        $this->assertNotContains('8.1.09', $codes, 'Comercial não tem budget 2026 — não deve aparecer');
    }

    public function test_departments_all_param_returns_everything_regardless_of_budget(): void
    {
        // Sem query year → todos os 11 depts
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('management-classes.departments'));

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(11, count($response->json('departments')));
    }
}
