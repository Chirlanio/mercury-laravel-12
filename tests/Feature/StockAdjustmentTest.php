<?php

namespace Tests\Feature;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockAdjustmentReason;
use App\Models\Store;
use App\Models\User;
use App\Services\StockAdjustmentTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;
    protected Store $otherStore;
    protected StockAdjustmentReason $reason;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
        $this->otherStore = Store::factory()->create(['code' => 'Z425', 'name' => 'Outra Loja']);
        $this->reason = StockAdjustmentReason::factory()->create([
            'code' => 'TEST_REASON',
            'name' => 'Teste',
            'applies_to' => 'both',
        ]);
    }

    // ========================================================================
    // CREATE
    // ========================================================================

    public function test_admin_can_create_adjustment_with_items(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('stock-adjustments.store'), [
            'store_id' => $this->store->id,
            'observation' => 'Divergência encontrada no inventário',
            'items' => [
                [
                    'reference' => 'REF001',
                    'size' => '36',
                    'direction' => 'increase',
                    'quantity' => 3,
                    'reason_id' => $this->reason->id,
                ],
                [
                    'reference' => 'REF002',
                    'size' => '38',
                    'direction' => 'decrease',
                    'quantity' => 2,
                    'reason_id' => $this->reason->id,
                ],
            ],
        ]);

        $response->assertRedirect(route('stock-adjustments.index'));
        $this->assertDatabaseCount('stock_adjustments', 1);
        $this->assertDatabaseCount('stock_adjustment_items', 2);

        $adjustment = StockAdjustment::first();
        $this->assertSame('pending', $adjustment->status);
        $this->assertSame($this->adminUser->id, $adjustment->created_by_user_id);

        $this->assertDatabaseHas('stock_adjustment_items', [
            'reference' => 'REF001',
            'direction' => 'increase',
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('stock_adjustment_items', [
            'reference' => 'REF002',
            'direction' => 'decrease',
            'quantity' => 2,
        ]);
    }

    public function test_create_requires_at_least_one_item(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('stock-adjustments.store'), [
            'store_id' => $this->store->id,
            'items' => [],
        ]);

        $response->assertSessionHasErrors('items');
    }

    public function test_create_requires_quantity_greater_than_zero(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('stock-adjustments.store'), [
            'store_id' => $this->store->id,
            'items' => [[
                'reference' => 'REF001',
                'direction' => 'increase',
                'quantity' => 0,
            ]],
        ]);

        $response->assertSessionHasErrors('items.0.quantity');
    }

    public function test_create_requires_direction(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('stock-adjustments.store'), [
            'store_id' => $this->store->id,
            'items' => [[
                'reference' => 'REF001',
                'quantity' => 1,
            ]],
        ]);

        $response->assertSessionHasErrors('items.0.direction');
    }

    // ========================================================================
    // UPDATE / DELETE restrictions
    // ========================================================================

    public function test_update_only_allowed_when_pending(): void
    {
        $adj = StockAdjustment::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'adjusted',
            'created_by_user_id' => $this->adminUser->id,
        ]);
        StockAdjustmentItem::factory()->create(['stock_adjustment_id' => $adj->id]);

        $response = $this->actingAs($this->adminUser)->put(route('stock-adjustments.update', $adj), [
            'observation' => 'nova obs',
            'items' => [[
                'reference' => 'REF999',
                'direction' => 'increase',
                'quantity' => 1,
            ]],
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('adjusted', $adj->fresh()->status);
    }

    public function test_destroy_only_allowed_when_pending(): void
    {
        $adj = StockAdjustment::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'under_analysis',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('stock-adjustments.destroy', $adj));

        $response->assertSessionHas('error');
        $this->assertNull($adj->fresh()->deleted_at);
    }

    public function test_destroy_pending_sets_soft_delete(): void
    {
        $adj = StockAdjustment::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'pending',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser)
            ->delete(route('stock-adjustments.destroy', $adj), ['reason' => 'engano']);

        $fresh = $adj->fresh();
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame($this->adminUser->id, $fresh->deleted_by_user_id);
        $this->assertSame('engano', $fresh->delete_reason);
    }

    // ========================================================================
    // State machine
    // ========================================================================

    public function test_valid_transition_records_history(): void
    {
        $adj = StockAdjustment::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'pending',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser)->post(route('stock-adjustments.transition', $adj), [
            'new_status' => 'under_analysis',
            'notes' => 'revisando',
        ]);

        $this->assertSame('under_analysis', $adj->fresh()->status);
        $this->assertDatabaseHas('stock_adjustment_status_history', [
            'stock_adjustment_id' => $adj->id,
            'old_status' => 'pending',
            'new_status' => 'under_analysis',
            'notes' => 'revisando',
        ]);
    }

    public function test_invalid_transition_rejected(): void
    {
        $adj = StockAdjustment::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'adjusted', // terminal
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser)->post(route('stock-adjustments.transition', $adj), [
            'new_status' => 'pending',
        ]);

        $this->assertSame('adjusted', $adj->fresh()->status);
    }

    public function test_only_admin_can_reopen_cancelled(): void
    {
        $adj = StockAdjustment::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'cancelled',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        // Support não pode reabrir
        $service = app(StockAdjustmentTransitionService::class);
        $validation = $service->validateTransition($adj, 'pending', $this->supportUser);
        $this->assertFalse($validation['valid']);

        // Admin pode
        $validation = $service->validateTransition($adj, 'pending', $this->adminUser);
        $this->assertTrue($validation['valid']);
    }

    public function test_terminal_status_has_no_transitions(): void
    {
        $adj = StockAdjustment::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'no_adjustment',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $service = app(StockAdjustmentTransitionService::class);
        $this->assertSame([], $service->allowedTransitions($adj, $this->adminUser));
    }

    // ========================================================================
    // Bulk transition
    // ========================================================================

    public function test_bulk_transition_updates_multiple_records(): void
    {
        $ids = StockAdjustment::factory()
            ->count(3)
            ->create([
                'store_id' => $this->store->id,
                'status' => 'pending',
                'created_by_user_id' => $this->adminUser->id,
            ])
            ->pluck('id')
            ->toArray();

        $this->actingAs($this->adminUser)->post(route('stock-adjustments.bulk-transition'), [
            'ids' => $ids,
            'new_status' => 'under_analysis',
        ]);

        foreach ($ids as $id) {
            $this->assertSame('under_analysis', StockAdjustment::find($id)->status);
        }
    }

    public function test_bulk_transition_limited_to_50(): void
    {
        $ids = range(1, 60);
        $response = $this->actingAs($this->adminUser)->post(route('stock-adjustments.bulk-transition'), [
            'ids' => $ids,
            'new_status' => 'adjusted',
        ]);

        $response->assertSessionHasErrors('ids');
    }

    // ========================================================================
    // Listing & scope
    // ========================================================================

    public function test_index_returns_page_with_adjustments(): void
    {
        StockAdjustment::factory()->count(2)->create([
            'store_id' => $this->store->id,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('stock-adjustments.index'));
        $response->assertOk();
    }

    public function test_deleted_adjustments_not_returned_in_listing(): void
    {
        StockAdjustment::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'pending',
            'created_by_user_id' => $this->adminUser->id,
            'deleted_at' => now(),
            'deleted_by_user_id' => $this->adminUser->id,
        ]);

        $this->assertSame(0, StockAdjustment::active()->count());
    }
}
