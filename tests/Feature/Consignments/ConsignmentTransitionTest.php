<?php

namespace Tests\Feature\Consignments;

use App\Enums\ConsignmentStatus;
use App\Models\Consignment;
use App\Models\ConsignmentReturn;
use App\Models\Store;
use App\Services\ConsignmentTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobertura da state machine de Consignação.
 * Testa transições permitidas, bloqueadas, histórico e overrides.
 */
class ConsignmentTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected ConsignmentTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        app(\App\Services\CentralRoleResolver::class)->clearCache();

        $this->store = Store::factory()->create(['code' => 'Z421']);
        $this->service = app(ConsignmentTransitionService::class);
    }

    // ------------------------------------------------------------------
    // Transições válidas
    // ------------------------------------------------------------------

    public function test_draft_to_pending_sets_issued_at(): void
    {
        $c = Consignment::factory()->draft()->forStore($this->store)->create();

        $updated = $this->service->transition($c, ConsignmentStatus::PENDING, $this->adminUser);

        $this->assertEquals(ConsignmentStatus::PENDING, $updated->status);
        $this->assertNotNull($updated->issued_at);
    }

    public function test_pending_to_completed_sets_completed_at_and_user(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();
        $this->seedReturn($c);

        $updated = $this->service->transition($c, ConsignmentStatus::COMPLETED, $this->adminUser);

        $this->assertEquals(ConsignmentStatus::COMPLETED, $updated->status);
        $this->assertNotNull($updated->completed_at);
        $this->assertEquals($this->adminUser->id, $updated->completed_by_user_id);
    }

    public function test_cannot_complete_without_return(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('pelo menos um retorno');

        $this->service->transition($c, ConsignmentStatus::COMPLETED, $this->adminUser);
    }

    public function test_pending_to_cancelled_requires_reason(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('motivo');

        $this->service->transition($c, ConsignmentStatus::CANCELLED, $this->adminUser);
    }

    public function test_cancel_writes_reason(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        $updated = $this->service->cancel($c, $this->adminUser, 'Cliente desistiu');

        $this->assertEquals(ConsignmentStatus::CANCELLED, $updated->status);
        $this->assertNotNull($updated->cancelled_at);
        $this->assertEquals('Cliente desistiu', $updated->cancelled_reason);
    }

    public function test_mark_overdue_works_without_actor(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        $updated = $this->service->markOverdue($c);

        $this->assertEquals(ConsignmentStatus::OVERDUE, $updated->status);

        $history = $updated->statusHistory()->first();
        $this->assertNull($history->changed_by_user_id);
        $this->assertEquals('consignments:mark-overdue', $history->context['command']);
    }

    // ------------------------------------------------------------------
    // Transições inválidas
    // ------------------------------------------------------------------

    public function test_completed_cannot_transition(): void
    {
        $c = Consignment::factory()->completed()->forStore($this->store)->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Transição inválida');

        $this->service->transition($c, ConsignmentStatus::PENDING, $this->adminUser);
    }

    public function test_cancelled_cannot_transition(): void
    {
        $c = Consignment::factory()->cancelled()->forStore($this->store)->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Transição inválida');

        $this->service->transition($c, ConsignmentStatus::PENDING, $this->adminUser);
    }

    public function test_draft_to_completed_is_invalid(): void
    {
        $c = Consignment::factory()->draft()->forStore($this->store)->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Transição inválida');

        $this->service->transition($c, ConsignmentStatus::COMPLETED, $this->adminUser);
    }

    public function test_actor_required_for_non_overdue_transitions(): void
    {
        $c = Consignment::factory()->draft()->forStore($this->store)->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('obrigatório');

        $this->service->transition($c, ConsignmentStatus::PENDING, null);
    }

    // ------------------------------------------------------------------
    // Histórico
    // ------------------------------------------------------------------

    public function test_transition_creates_history_entry(): void
    {
        $c = Consignment::factory()->draft()->forStore($this->store)->create();

        $this->service->issue($c, $this->adminUser, 'Emitido pelo caixa');

        $history = $c->fresh()->statusHistory()->first();
        $this->assertEquals('draft', $history->from_status);
        $this->assertEquals('pending', $history->to_status);
        $this->assertEquals($this->adminUser->id, $history->changed_by_user_id);
        $this->assertEquals('Emitido pelo caixa', $history->note);
    }

    public function test_overdue_to_completed_via_late_return(): void
    {
        $c = Consignment::factory()->overdue()->forStore($this->store)->create();
        $this->seedReturn($c);

        $updated = $this->service->complete($c, $this->adminUser);

        $this->assertEquals(ConsignmentStatus::COMPLETED, $updated->status);
    }

    public function test_deleted_consignment_cannot_transition(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();
        $this->seedReturn($c);
        $c->update(['deleted_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('excluída');

        $this->service->transition($c->fresh(), ConsignmentStatus::COMPLETED, $this->adminUser);
    }

    // ------------------------------------------------------------------
    // Permissões
    // ------------------------------------------------------------------

    public function test_regular_user_cannot_complete(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();
        $this->seedReturn($c);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('finalizar');

        $this->service->complete($c, $this->regularUser);
    }

    public function test_regular_user_cannot_cancel(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cancelar');

        $this->service->cancel($c, $this->regularUser, 'Tentativa indevida');
    }

    public function test_support_user_can_complete(): void
    {
        // SUPPORT tem COMPLETE_CONSIGNMENT
        $c = Consignment::factory()->pending()->forStore($this->store)->create();
        $this->seedReturn($c);

        $updated = $this->service->complete($c, $this->supportUser);

        $this->assertEquals(ConsignmentStatus::COMPLETED, $updated->status);
    }

    /**
     * Helper — cria um ConsignmentReturn mínimo pra satisfazer a regra
     * que exige ao menos um retorno registrado antes de finalizar.
     */
    protected function seedReturn(Consignment $c): ConsignmentReturn
    {
        return ConsignmentReturn::create([
            'consignment_id' => $c->id,
            'return_invoice_number' => '999',
            'return_date' => now()->toDateString(),
            'return_store_code' => $this->store->code,
            'returned_quantity' => 1,
            'returned_value' => 0,
            'registered_by_user_id' => $this->adminUser->id,
        ]);
    }
}
