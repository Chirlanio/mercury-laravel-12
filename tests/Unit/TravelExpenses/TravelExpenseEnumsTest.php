<?php

namespace Tests\Unit\TravelExpenses;

use App\Enums\AccountabilityStatus;
use App\Enums\TravelExpenseStatus;
use PHPUnit\Framework\TestCase;

class TravelExpenseEnumsTest extends TestCase
{
    // ==================================================================
    // TravelExpenseStatus — state machine principal (6 estados)
    // ==================================================================

    public function test_travel_expense_status_labels(): void
    {
        $this->assertSame('Rascunho', TravelExpenseStatus::DRAFT->label());
        $this->assertSame('Solicitada', TravelExpenseStatus::SUBMITTED->label());
        $this->assertSame('Aprovada', TravelExpenseStatus::APPROVED->label());
        $this->assertSame('Rejeitada', TravelExpenseStatus::REJECTED->label());
        $this->assertSame('Finalizada', TravelExpenseStatus::FINALIZED->label());
        $this->assertSame('Cancelada', TravelExpenseStatus::CANCELLED->label());
    }

    public function test_travel_expense_status_transition_graph(): void
    {
        // draft → submitted, cancelled
        $this->assertEqualsCanonicalizing(
            [TravelExpenseStatus::SUBMITTED, TravelExpenseStatus::CANCELLED],
            TravelExpenseStatus::DRAFT->allowedTransitions()
        );

        // submitted → draft (volta), approved, rejected, cancelled
        $this->assertEqualsCanonicalizing(
            [
                TravelExpenseStatus::DRAFT,
                TravelExpenseStatus::APPROVED,
                TravelExpenseStatus::REJECTED,
                TravelExpenseStatus::CANCELLED,
            ],
            TravelExpenseStatus::SUBMITTED->allowedTransitions()
        );

        // approved → finalized, cancelled
        $this->assertEqualsCanonicalizing(
            [TravelExpenseStatus::FINALIZED, TravelExpenseStatus::CANCELLED],
            TravelExpenseStatus::APPROVED->allowedTransitions()
        );

        // Terminais não transicionam
        $this->assertEmpty(TravelExpenseStatus::REJECTED->allowedTransitions());
        $this->assertEmpty(TravelExpenseStatus::FINALIZED->allowedTransitions());
        $this->assertEmpty(TravelExpenseStatus::CANCELLED->allowedTransitions());
    }

    public function test_can_transition_to_validates_target(): void
    {
        $this->assertTrue(TravelExpenseStatus::DRAFT->canTransitionTo(TravelExpenseStatus::SUBMITTED));
        $this->assertFalse(TravelExpenseStatus::DRAFT->canTransitionTo(TravelExpenseStatus::APPROVED));
        $this->assertFalse(TravelExpenseStatus::DRAFT->canTransitionTo(TravelExpenseStatus::FINALIZED));

        $this->assertTrue(TravelExpenseStatus::APPROVED->canTransitionTo(TravelExpenseStatus::FINALIZED));
        $this->assertFalse(TravelExpenseStatus::APPROVED->canTransitionTo(TravelExpenseStatus::REJECTED));
        $this->assertFalse(TravelExpenseStatus::FINALIZED->canTransitionTo(TravelExpenseStatus::APPROVED));
    }

    public function test_terminal_helper(): void
    {
        $this->assertTrue(TravelExpenseStatus::REJECTED->isTerminal());
        $this->assertTrue(TravelExpenseStatus::FINALIZED->isTerminal());
        $this->assertTrue(TravelExpenseStatus::CANCELLED->isTerminal());
        $this->assertFalse(TravelExpenseStatus::DRAFT->isTerminal());
        $this->assertFalse(TravelExpenseStatus::SUBMITTED->isTerminal());
        $this->assertFalse(TravelExpenseStatus::APPROVED->isTerminal());
    }

    public function test_active_returns_only_non_terminal(): void
    {
        $active = TravelExpenseStatus::active();
        $this->assertContains(TravelExpenseStatus::DRAFT, $active);
        $this->assertContains(TravelExpenseStatus::SUBMITTED, $active);
        $this->assertContains(TravelExpenseStatus::APPROVED, $active);
        $this->assertNotContains(TravelExpenseStatus::REJECTED, $active);
        $this->assertNotContains(TravelExpenseStatus::FINALIZED, $active);
        $this->assertNotContains(TravelExpenseStatus::CANCELLED, $active);
    }

    public function test_transition_map_serializes_to_string_arrays(): void
    {
        $map = TravelExpenseStatus::transitionMap();
        $this->assertIsArray($map);
        $this->assertContains('submitted', $map['draft']);
        $this->assertContains('cancelled', $map['draft']);
        $this->assertEmpty($map['finalized']);
    }

    public function test_labels_helper_returns_full_map(): void
    {
        $labels = TravelExpenseStatus::labels();
        $this->assertCount(6, $labels);
        $this->assertSame('Rascunho', $labels['draft']);
        $this->assertSame('Finalizada', $labels['finalized']);
    }

    public function test_colors_helper_returns_color_per_status(): void
    {
        $colors = TravelExpenseStatus::colors();
        $this->assertSame('gray', $colors['draft']);
        $this->assertSame('warning', $colors['submitted']);
        $this->assertSame('success', $colors['finalized']);
        $this->assertSame('danger', $colors['rejected']);
        $this->assertSame('danger', $colors['cancelled']);
    }

    // ==================================================================
    // AccountabilityStatus — state machine paralela (5 estados)
    // ==================================================================

    public function test_accountability_status_labels(): void
    {
        $this->assertSame('Aguardando Lançamento', AccountabilityStatus::PENDING->label());
        $this->assertSame('Em Andamento', AccountabilityStatus::IN_PROGRESS->label());
        $this->assertSame('Aguardando Aprovação', AccountabilityStatus::SUBMITTED->label());
        $this->assertSame('Aprovada', AccountabilityStatus::APPROVED->label());
        $this->assertSame('Recusada', AccountabilityStatus::REJECTED->label());
    }

    public function test_accountability_status_transition_graph(): void
    {
        // pending → in_progress (auto, ao adicionar primeiro item)
        $this->assertEqualsCanonicalizing(
            [AccountabilityStatus::IN_PROGRESS],
            AccountabilityStatus::PENDING->allowedTransitions()
        );

        // in_progress → pending (volta) | submitted (envia)
        $this->assertEqualsCanonicalizing(
            [AccountabilityStatus::PENDING, AccountabilityStatus::SUBMITTED],
            AccountabilityStatus::IN_PROGRESS->allowedTransitions()
        );

        // submitted → in_progress (devolve), approved, rejected
        $this->assertEqualsCanonicalizing(
            [
                AccountabilityStatus::IN_PROGRESS,
                AccountabilityStatus::APPROVED,
                AccountabilityStatus::REJECTED,
            ],
            AccountabilityStatus::SUBMITTED->allowedTransitions()
        );

        // rejected → in_progress (volta para correção)
        $this->assertEqualsCanonicalizing(
            [AccountabilityStatus::IN_PROGRESS],
            AccountabilityStatus::REJECTED->allowedTransitions()
        );

        // approved é terminal
        $this->assertEmpty(AccountabilityStatus::APPROVED->allowedTransitions());
    }

    public function test_accountability_is_terminal_only_for_approved(): void
    {
        $this->assertTrue(AccountabilityStatus::APPROVED->isTerminal());
        $this->assertFalse(AccountabilityStatus::PENDING->isTerminal());
        $this->assertFalse(AccountabilityStatus::IN_PROGRESS->isTerminal());
        $this->assertFalse(AccountabilityStatus::SUBMITTED->isTerminal());
        $this->assertFalse(AccountabilityStatus::REJECTED->isTerminal());
    }

    public function test_accountability_skipping_states_is_blocked(): void
    {
        // Não pode ir de pending direto para approved
        $this->assertFalse(AccountabilityStatus::PENDING->canTransitionTo(AccountabilityStatus::APPROVED));
        // Não pode ir de in_progress direto para approved (precisa passar por submitted)
        $this->assertFalse(AccountabilityStatus::IN_PROGRESS->canTransitionTo(AccountabilityStatus::APPROVED));
        // Não pode voltar de approved
        $this->assertFalse(AccountabilityStatus::APPROVED->canTransitionTo(AccountabilityStatus::IN_PROGRESS));
    }
}
