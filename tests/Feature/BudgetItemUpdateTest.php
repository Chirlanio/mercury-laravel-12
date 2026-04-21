<?php

namespace Tests\Feature;

use App\Models\AccountingClass;
use App\Models\BudgetItem;
use App\Models\BudgetUpload;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre a Melhoria 8 do roadmap — edição inline de BudgetItem.
 *
 * Valida:
 *   - Update de valores mensais recalcula year_total + total_year do upload
 *   - Campos de texto são persistidos (supplier, descrições)
 *   - FKs (accounting_class_id/management_class_id/cost_center_id) NÃO mudam
 *     mesmo se enviadas no payload
 *   - Upload soft-deleted bloqueia edição
 *   - Permission UPLOAD_BUDGETS exigida
 */
class BudgetItemUpdateTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected BudgetUpload $budget;

    protected BudgetItem $item;

    protected AccountingClass $ac;

    protected AccountingClass $acOutra;

    protected CostCenter $cc;

    protected CostCenter $ccOutro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        app(\App\Services\CentralRoleResolver::class)->clearCache();

        $this->ac = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail();
        $this->acOutra = AccountingClass::where('code', '4.2.1.04.00083')->firstOrFail();

        $this->cc = CostCenter::create([
            'code' => 'CC-ITEM', 'name' => 'CC Item',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);
        $this->ccOutro = CostCenter::create([
            'code' => 'CC-ITEM-2', 'name' => 'CC Alt',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);
        $areaDept = ManagementClass::where('code', '8.1.01')->firstOrFail();

        $mc = ManagementClass::create([
            'code' => 'MC-ITEM', 'name' => 'MC Item',
            'accepts_entries' => true,
            'accounting_class_id' => $this->ac->id,
            'cost_center_id' => $this->cc->id,
            'parent_id' => $areaDept->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->budget = BudgetUpload::create([
            'year' => 2026, 'scope_label' => 'EditItemTest', 'version_label' => '1.0',
            'major_version' => 1, 'minor_version' => 0, 'upload_type' => 'novo',
            'area_department_id' => $areaDept->id,
            'original_filename' => 't.xlsx', 'stored_path' => 'budgets/2026/t.xlsx',
            'file_size_bytes' => 1, 'is_active' => true,
            'total_year' => 1200, 'items_count' => 1,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $this->item = BudgetItem::create([
            'budget_upload_id' => $this->budget->id,
            'accounting_class_id' => $this->ac->id,
            'management_class_id' => $mc->id,
            'cost_center_id' => $this->cc->id,
            'supplier' => 'Original',
            'month_01_value' => 100, 'month_02_value' => 100, 'month_03_value' => 100,
            'month_04_value' => 100, 'month_05_value' => 100, 'month_06_value' => 100,
            'month_07_value' => 100, 'month_08_value' => 100, 'month_09_value' => 100,
            'month_10_value' => 100, 'month_11_value' => 100, 'month_12_value' => 100,
            'year_total' => 1200,
        ]);
    }

    public function test_update_recalculates_year_total_and_upload_total(): void
    {
        // Frontend sempre envia os 12 meses (mesmo que 0) — usuário pode
        // zerar meses limpando o input. Backend não infere zero de campos
        // omitidos (preserva valor antigo para permitir updates parciais).
        $payload = [
            'supplier' => 'Fornecedor Novo',
            'month_01_value' => 500,
            'month_02_value' => 300,
        ];
        for ($m = 3; $m <= 12; $m++) {
            $payload['month_'.str_pad($m, 2, '0', STR_PAD_LEFT).'_value'] = 0;
        }

        $this->actingAs($this->adminUser)
            ->patchJson(route('budget-items.update', $this->item), $payload)
            ->assertStatus(200)
            ->assertJsonPath('item.supplier', 'Fornecedor Novo')
            ->assertJsonPath('item.year_total', 800)
            ->assertJsonPath('upload.total_year', 800);

        $this->item->refresh();
        $this->assertEquals(500, (int) $this->item->month_01_value);
        $this->assertEquals(800, (int) $this->item->year_total);

        $this->budget->refresh();
        $this->assertEquals(800, (int) $this->budget->total_year);
    }

    public function test_update_with_partial_payload_preserves_other_months(): void
    {
        // Documenta o comportamento: omitir meses = preservar.
        $this->actingAs($this->adminUser)
            ->patchJson(route('budget-items.update', $this->item), [
                'month_01_value' => 999,
                // outros meses omitidos — continuam 100 cada
            ])
            ->assertStatus(200);

        $this->item->refresh();
        $this->assertEquals(999, (int) $this->item->month_01_value);
        $this->assertEquals(100, (int) $this->item->month_02_value);
        $this->assertEquals(2099, (int) $this->item->year_total); // 999 + 11×100
    }

    public function test_update_ignores_fk_fields_in_payload(): void
    {
        // Tampering: tentar mudar AC e CC — devem permanecer inalterados
        $this->actingAs($this->adminUser)
            ->patchJson(route('budget-items.update', $this->item), [
                'supplier' => 'Teste',
                'accounting_class_id' => $this->acOutra->id,
                'cost_center_id' => $this->ccOutro->id,
                'budget_upload_id' => 9999,
            ])
            ->assertStatus(200);

        $this->item->refresh();
        $this->assertEquals($this->ac->id, $this->item->accounting_class_id);
        $this->assertEquals($this->cc->id, $this->item->cost_center_id);
        $this->assertEquals($this->budget->id, $this->item->budget_upload_id);
    }

    public function test_update_persists_text_fields(): void
    {
        $this->actingAs($this->adminUser)
            ->patchJson(route('budget-items.update', $this->item), [
                'supplier' => 'Novo Fornecedor',
                'justification' => 'Ajuste solicitado pela gerência',
                'account_description' => 'Descrição atualizada',
                'class_description' => 'Classe X',
            ])
            ->assertStatus(200);

        $this->item->refresh();
        $this->assertEquals('Novo Fornecedor', $this->item->supplier);
        $this->assertEquals('Ajuste solicitado pela gerência', $this->item->justification);
        $this->assertEquals('Descrição atualizada', $this->item->account_description);
        $this->assertEquals('Classe X', $this->item->class_description);
    }

    public function test_update_blocks_deleted_budget(): void
    {
        $this->budget->forceFill([
            'is_active' => false,
            'deleted_at' => now(),
            'deleted_by_user_id' => $this->adminUser->id,
            'deleted_reason' => 'teste',
        ])->save();

        $this->actingAs($this->adminUser)
            ->patchJson(route('budget-items.update', $this->item), [
                'supplier' => 'Tentativa',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['budget']);
    }

    public function test_update_rejects_negative_month_value(): void
    {
        $this->actingAs($this->adminUser)
            ->patchJson(route('budget-items.update', $this->item), [
                'month_01_value' => -100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month_01_value']);
    }

    public function test_regular_user_cannot_update_item(): void
    {
        $this->actingAs($this->regularUser)
            ->patchJson(route('budget-items.update', $this->item), [
                'supplier' => 'Teste',
            ])
            ->assertStatus(403);
    }

    public function test_update_creates_activity_log_entry(): void
    {
        $this->actingAs($this->adminUser)
            ->patchJson(route('budget-items.update', $this->item), [
                'supplier' => 'Logado',
                'month_01_value' => 999,
            ])
            ->assertStatus(200);

        // Auditable trait registra via activity_logs
        $this->assertDatabaseHas('activity_logs', [
            'model_type' => BudgetItem::class,
            'model_id' => $this->item->id,
            'action' => 'update',
        ]);
    }
}
