<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StockAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class StockAuditControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
    }

    public function test_admin_can_view_stock_audits_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('stock-audits.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('StockAudits/Index'));
    }

    public function test_user_can_view_stock_audits_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('stock-audits.index'));
        $response->assertStatus(200);
    }

    public function test_admin_can_create_audit(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('stock-audits.store'), [
            'store_id' => $this->store->id,
            'audit_type' => 'total',
            'requires_second_count' => true,
            'requires_third_count' => false,
        ]);

        $response->assertRedirect(route('stock-audits.index'));
        $this->assertDatabaseHas('stock_audits', [
            'store_id' => $this->store->id,
            'audit_type' => 'total',
            'status' => 'draft',
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    public function test_cannot_create_audit_with_invalid_type(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('stock-audits.store'), [
            'store_id' => $this->store->id,
            'audit_type' => 'invalido',
        ]);

        $response->assertSessionHasErrors('audit_type');
    }

    public function test_admin_can_view_audit_detail(): void
    {
        $audit = StockAudit::create([
            'store_id' => $this->store->id,
            'audit_type' => 'total',
            'status' => 'draft',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('stock-audits.show', $audit));

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $audit->id]);
    }

    public function test_admin_can_update_draft_audit(): void
    {
        $audit = StockAudit::create([
            'store_id' => $this->store->id,
            'audit_type' => 'total',
            'status' => 'draft',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('stock-audits.update', $audit), [
            'store_id' => $this->store->id,
            'audit_type' => 'parcial',
            'requires_second_count' => false,
            'requires_third_count' => false,
        ]);

        $response->assertRedirect(route('stock-audits.index'));
        $this->assertDatabaseHas('stock_audits', [
            'id' => $audit->id,
            'audit_type' => 'parcial',
        ]);
    }

    public function test_cannot_update_non_draft_audit(): void
    {
        $audit = StockAudit::create([
            'store_id' => $this->store->id,
            'audit_type' => 'total',
            'status' => 'counting',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('stock-audits.update', $audit), [
            'store_id' => $this->store->id,
            'audit_type' => 'parcial',
            'requires_second_count' => false,
            'requires_third_count' => false,
        ]);

        $response->assertSessionHasErrors('stock_audit');
    }

    public function test_admin_can_soft_delete_draft_audit(): void
    {
        $audit = StockAudit::create([
            'store_id' => $this->store->id,
            'audit_type' => 'total',
            'status' => 'draft',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('stock-audits.destroy', $audit));

        $response->assertRedirect(route('stock-audits.index'));
        $this->assertNotNull($audit->fresh()->deleted_at);
    }

    public function test_cannot_delete_non_draft_audit(): void
    {
        $audit = StockAudit::create([
            'store_id' => $this->store->id,
            'audit_type' => 'total',
            'status' => 'counting',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('stock-audits.destroy', $audit));

        $response->assertSessionHasErrors('stock_audit');
        $this->assertNull($audit->fresh()->deleted_at);
    }

    public function test_index_filters_by_status(): void
    {
        StockAudit::create([
            'store_id' => $this->store->id,
            'audit_type' => 'total',
            'status' => 'draft',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        StockAudit::create([
            'store_id' => $this->store->id,
            'audit_type' => 'total',
            'status' => 'finished',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('stock-audits.index', ['status' => 'draft']));

        $response->assertStatus(200);
    }

    public function test_total_audit_forces_second_count(): void
    {
        $this->actingAs($this->adminUser)->post(route('stock-audits.store'), [
            'store_id' => $this->store->id,
            'audit_type' => 'total',
            'requires_second_count' => false,
            'requires_third_count' => false,
        ]);

        $audit = StockAudit::latest()->first();
        $this->assertTrue($audit->requires_second_count);
    }
}
