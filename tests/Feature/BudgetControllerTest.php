<?php

namespace Tests\Feature;

use App\Enums\BudgetUploadType;
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

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected AccountingClass $ac;

    protected ManagementClass $mc;

    protected CostCenter $cc;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        Storage::fake('local');

        // Seed BR já popula accounting_classes via migration — pegamos uma folha
        $this->ac = AccountingClass::where('code', '5.2.01')->firstOrFail();

        $this->cc = CostCenter::create([
            'code' => 'CC-BUDGET',
            'name' => 'Admin CC',
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->mc = ManagementClass::create([
            'code' => 'MC-BUDGET',
            'name' => 'Admin MC',
            'accepts_entries' => true,
            'accounting_class_id' => $this->ac->id,
            'cost_center_id' => $this->cc->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->store = Store::factory()->create(['code' => 'Z999', 'name' => 'Loja Teste']);
    }

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'year' => 2026,
            'scope_label' => 'Administrativo',
            'upload_type' => 'novo',
            'notes' => 'Upload inicial',
            'file' => UploadedFile::fake()->create('orc-2026.xlsx', 100),
            'items' => [
                [
                    'accounting_class_id' => $this->ac->id,
                    'management_class_id' => $this->mc->id,
                    'cost_center_id' => $this->cc->id,
                    'store_id' => null,
                    'supplier' => 'Fornecedor X',
                    'month_01_value' => 1000,
                    'month_02_value' => 1000,
                    'month_12_value' => 1500,
                ],
            ],
        ], $overrides);
    }

    public function test_admin_can_view_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('budgets.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Budgets/Index'));
    }

    public function test_regular_user_cannot_view_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('budgets.index'));
        $response->assertStatus(403);
    }

    public function test_store_creates_budget_with_items_and_version_1_0(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.store'), $this->validPayload());

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('budget_uploads', [
            'year' => 2026,
            'scope_label' => 'Administrativo',
            'version_label' => '1.0',
            'major_version' => 1,
            'minor_version' => 0,
            'is_active' => true,
        ]);

        $upload = BudgetUpload::where('scope_label', 'Administrativo')->first();
        $this->assertEquals(1, $upload->items_count);
        $this->assertEquals(3500.00, $upload->total_year);
        $this->assertDatabaseHas('budget_items', [
            'budget_upload_id' => $upload->id,
            'year_total' => 3500,
        ]);

        // Arquivo armazenado e história registrada
        $this->assertNotNull($upload->stored_path);
        $this->assertDatabaseHas('budget_status_histories', [
            'budget_upload_id' => $upload->id,
            'event' => 'created',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.store'), []);

        $response->assertSessionHasErrors(['year', 'scope_label', 'upload_type', 'file', 'items']);
    }

    public function test_store_rejects_item_without_required_fks(): void
    {
        $payload = $this->validPayload();
        $payload['items'][0]['accounting_class_id'] = null;

        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.store'), $payload);

        $response->assertSessionHasErrors();
    }

    public function test_store_rejects_invalid_file_extension(): void
    {
        $payload = $this->validPayload([
            'file' => UploadedFile::fake()->create('orc.txt', 50),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.store'), $payload);

        $response->assertSessionHasErrors('file');
    }

    public function test_versioning_ajuste_increments_minor(): void
    {
        // 1.0
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload());

        // 1.01 (ajuste)
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload([
            'upload_type' => 'ajuste',
            'file' => UploadedFile::fake()->create('adj.xlsx', 50),
        ]));

        $this->assertDatabaseHas('budget_uploads', [
            'version_label' => '1.0',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('budget_uploads', [
            'version_label' => '1.01',
            'is_active' => true,
            'major_version' => 1,
            'minor_version' => 1,
        ]);
    }

    public function test_versioning_novo_increments_major(): void
    {
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload());

        // Mais um ajuste (1.01)
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload([
            'upload_type' => 'ajuste',
            'file' => UploadedFile::fake()->create('a.xlsx', 50),
        ]));

        // Agora novo (2.0, não 1.02)
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload([
            'upload_type' => 'novo',
            'file' => UploadedFile::fake()->create('n.xlsx', 50),
        ]));

        $this->assertDatabaseHas('budget_uploads', [
            'version_label' => '2.0',
            'is_active' => true,
            'major_version' => 2,
            'minor_version' => 0,
        ]);

        // Só uma versão ativa
        $activeCount = BudgetUpload::where('scope_label', 'Administrativo')
            ->where('year', 2026)
            ->active()
            ->count();
        $this->assertEquals(1, $activeCount);
    }

    public function test_versioning_resets_when_year_changes(): void
    {
        // 2026 — avança até 2.0
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload());
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload([
            'upload_type' => 'novo',
            'file' => UploadedFile::fake()->create('v2.xlsx', 50),
        ]));

        // 2027 — primeira vez → deve ser 1.0
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload([
            'year' => 2027,
            'file' => UploadedFile::fake()->create('2027.xlsx', 50),
        ]));

        $this->assertDatabaseHas('budget_uploads', [
            'year' => 2027,
            'scope_label' => 'Administrativo',
            'version_label' => '1.0',
            'is_active' => true,
        ]);

        // A versão ativa do 2026 NÃO é afetada
        $this->assertDatabaseHas('budget_uploads', [
            'year' => 2026,
            'version_label' => '2.0',
            'is_active' => true,
        ]);
    }

    public function test_versioning_is_per_scope(): void
    {
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload([
            'scope_label' => 'TI',
        ]));

        // Scope diferente — deve ser 1.0 (não interfere no "Administrativo")
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload([
            'scope_label' => 'Financeiro',
            'file' => UploadedFile::fake()->create('fin.xlsx', 50),
        ]));

        $this->assertDatabaseHas('budget_uploads', [
            'scope_label' => 'TI',
            'version_label' => '1.0',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('budget_uploads', [
            'scope_label' => 'Financeiro',
            'version_label' => '1.0',
            'is_active' => true,
        ]);
    }

    public function test_show_returns_json_with_items(): void
    {
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload());
        $upload = BudgetUpload::first();

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('budgets.show', $upload->id));

        $response->assertStatus(200);
        $response->assertJsonPath('budget.id', $upload->id);
        $response->assertJsonPath('budget.version_label', '1.0');
        $response->assertJsonCount(1, 'budget.items');
        $response->assertJsonCount(1, 'budget.status_history');
    }

    public function test_show_returns_404_for_deleted(): void
    {
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload());
        $upload = BudgetUpload::first();

        $upload->forceFill([
            'is_active' => false,
            'deleted_at' => now(),
            'deleted_by_user_id' => $this->adminUser->id,
            'deleted_reason' => 'test',
        ])->save();

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('budgets.show', $upload->id));
        $response->assertStatus(404);
    }

    public function test_update_meta_changes_notes(): void
    {
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload());
        $upload = BudgetUpload::first();

        $response = $this->actingAs($this->adminUser)
            ->put(route('budgets.update', $upload->id), ['notes' => 'Atualizado']);

        $response->assertRedirect();
        $this->assertEquals('Atualizado', $upload->fresh()->notes);
    }

    public function test_delete_blocks_active_version(): void
    {
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload());
        $upload = BudgetUpload::first();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('budgets.destroy', $upload->id), ['deleted_reason' => 'Motivo válido']);

        $response->assertSessionHasErrors();
        $this->assertNull($upload->fresh()->deleted_at);
    }

    public function test_delete_allows_inactive_version(): void
    {
        // 1.0 e 2.0 — 1.0 vira inativa automaticamente
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload());
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload([
            'upload_type' => 'novo',
            'file' => UploadedFile::fake()->create('v2.xlsx', 50),
        ]));

        $inactive = BudgetUpload::where('version_label', '1.0')->first();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('budgets.destroy', $inactive->id), ['deleted_reason' => 'Versão antiga']);

        $response->assertRedirect();
        $this->assertNotNull($inactive->fresh()->deleted_at);
    }

    public function test_delete_requires_reason(): void
    {
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload());
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload([
            'upload_type' => 'novo',
            'file' => UploadedFile::fake()->create('v2.xlsx', 50),
        ]));
        $inactive = BudgetUpload::where('version_label', '1.0')->first();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('budgets.destroy', $inactive->id), ['deleted_reason' => 'x']);

        $response->assertSessionHasErrors('deleted_reason');
        $this->assertNull($inactive->fresh()->deleted_at);
    }

    public function test_download_returns_file(): void
    {
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload());
        $upload = BudgetUpload::first();

        $response = $this->actingAs($this->adminUser)
            ->get(route('budgets.download', $upload->id));

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }

    public function test_index_hides_inactive_by_default(): void
    {
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload());
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload([
            'upload_type' => 'novo',
            'file' => UploadedFile::fake()->create('v2.xlsx', 50),
        ]));

        // Sem flag: mostra só ativos (1 registro)
        $response = $this->actingAs($this->adminUser)->get(route('budgets.index'));
        $response->assertInertia(fn ($page) => $page->has('budgets.data', 1));

        // Com include_inactive=1: mostra todos
        $response2 = $this->actingAs($this->adminUser)
            ->get(route('budgets.index', ['include_inactive' => 1]));
        $response2->assertInertia(fn ($page) => $page->has('budgets.data', 2));
    }

    public function test_statistics_endpoint(): void
    {
        $this->actingAs($this->adminUser)->post(route('budgets.store'), $this->validPayload());

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('budgets.statistics'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['total', 'active', 'inactive', 'distinct_scopes', 'distinct_years', 'total_amount_active']);
        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('active', 1);
        $response->assertJsonPath('distinct_scopes', 1);
    }

    public function test_year_total_computed_per_item(): void
    {
        $payload = $this->validPayload();
        $payload['items'][0] = array_merge($payload['items'][0], [
            'month_01_value' => 100,
            'month_02_value' => 200,
            'month_03_value' => 300,
            'month_04_value' => 400,
            'month_05_value' => 500,
            'month_06_value' => 600,
        ]);

        $this->actingAs($this->adminUser)->post(route('budgets.store'), $payload);

        // Default payload inclui month_12_value=1500, então total é
        // 100+200+300+400+500+600+1500 = 3600
        $item = \App\Models\BudgetItem::first();
        $this->assertEquals(3600.00, (float) $item->year_total);
    }
}
