<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StockAudit;
use App\Models\StockAuditItem;
use App\Models\StockAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class StockAuditTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
    }

    private function createAudit(string $status = 'draft', array $extra = []): StockAudit
    {
        return StockAudit::create(array_merge([
            'store_id' => $this->store->id,
            'audit_type' => 'total',
            'status' => $status,
            'requires_second_count' => true,
            'created_by_user_id' => $this->adminUser->id,
        ], $extra));
    }

    public function test_draft_to_awaiting_authorization(): void
    {
        $audit = $this->createAudit('draft');

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('stock-audits.transition', $audit), [
                'new_status' => 'awaiting_authorization',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('awaiting_authorization', $audit->fresh()->status);
    }

    public function test_invalid_transition_returns_error(): void
    {
        $audit = $this->createAudit('draft');

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('stock-audits.transition', $audit), [
                'new_status' => 'finished',
            ]);

        $response->assertStatus(422);
        $this->assertEquals('draft', $audit->fresh()->status);
    }

    public function test_cancel_audit(): void
    {
        $audit = $this->createAudit('counting');

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('stock-audits.transition', $audit), [
                'new_status' => 'cancelled',
                'cancellation_reason' => 'Motivo de teste',
            ]);

        $response->assertStatus(200);
        $fresh = $audit->fresh();
        $this->assertEquals('cancelled', $fresh->status);
        $this->assertEquals('Motivo de teste', $fresh->cancellation_reason);
        $this->assertNotNull($fresh->cancelled_at);
    }

    public function test_transition_creates_log(): void
    {
        $audit = $this->createAudit('draft');

        $this->actingAs($this->adminUser)
            ->postJson(route('stock-audits.transition', $audit), [
                'new_status' => 'awaiting_authorization',
            ]);

        $this->assertDatabaseHas('stock_audit_logs', [
            'audit_id' => $audit->id,
            'action_type' => 'transition',
            'old_status' => 'draft',
            'new_status' => 'awaiting_authorization',
            'changed_by_user_id' => $this->adminUser->id,
        ]);
    }

    public function test_counting_to_reconciliation_requires_finalized_round(): void
    {
        $audit = $this->createAudit('counting', ['count_1_finalized' => false]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('stock-audits.transition', $audit), [
                'new_status' => 'reconciliation',
            ]);

        $response->assertStatus(422);
        $this->assertEquals('counting', $audit->fresh()->status);
    }

    public function test_counting_to_reconciliation_with_finalized_rounds(): void
    {
        $audit = $this->createAudit('counting', [
            'count_1_finalized' => true,
            'count_2_finalized' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('stock-audits.transition', $audit), [
                'new_status' => 'reconciliation',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('reconciliation', $audit->fresh()->status);
    }

    public function test_finished_terminal_state(): void
    {
        $audit = $this->createAudit('finished');

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('stock-audits.transition', $audit), [
                'new_status' => 'cancelled',
            ]);

        $response->assertStatus(422);
    }
}
