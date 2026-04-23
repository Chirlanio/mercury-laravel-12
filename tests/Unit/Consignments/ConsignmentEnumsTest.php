<?php

namespace Tests\Unit\Consignments;

use App\Enums\ConsignmentItemStatus;
use App\Enums\ConsignmentStatus;
use App\Enums\ConsignmentType;
use PHPUnit\Framework\TestCase;

class ConsignmentEnumsTest extends TestCase
{
    // ------------------------------------------------------------------
    // ConsignmentType
    // ------------------------------------------------------------------

    public function test_consignment_type_labels(): void
    {
        $this->assertSame('Cliente', ConsignmentType::CLIENTE->label());
        $this->assertSame('Influencer', ConsignmentType::INFLUENCER->label());
        $this->assertSame('E-commerce', ConsignmentType::ECOMMERCE->label());
    }

    public function test_consignment_type_colors_are_distinct(): void
    {
        $colors = collect(ConsignmentType::cases())->map(fn ($c) => $c->color());
        $this->assertSame(3, $colors->unique()->count(), 'Cada tipo deve ter cor distinta');
    }

    public function test_only_cliente_requires_employee(): void
    {
        $this->assertTrue(ConsignmentType::CLIENTE->requiresEmployee());
        $this->assertFalse(ConsignmentType::INFLUENCER->requiresEmployee());
        $this->assertFalse(ConsignmentType::ECOMMERCE->requiresEmployee());
    }

    public function test_all_types_allow_legal_entity(): void
    {
        foreach (ConsignmentType::cases() as $type) {
            $this->assertTrue($type->allowsLegalEntity(), "Type {$type->value} should allow CNPJ");
        }
    }

    public function test_all_types_share_seven_day_return_period(): void
    {
        // Decisão de escopo 2026-04-23: prazo unificado 7d para todos os tipos
        foreach (ConsignmentType::cases() as $type) {
            $this->assertSame(7, $type->defaultReturnPeriodDays());
        }
    }

    public function test_consignment_type_labels_static(): void
    {
        $labels = ConsignmentType::labels();
        $this->assertArrayHasKey('cliente', $labels);
        $this->assertArrayHasKey('influencer', $labels);
        $this->assertArrayHasKey('ecommerce', $labels);
    }

    // ------------------------------------------------------------------
    // ConsignmentStatus
    // ------------------------------------------------------------------

    public function test_consignment_status_transition_graph(): void
    {
        // draft → pending, cancelled
        $this->assertEqualsCanonicalizing(
            [ConsignmentStatus::PENDING, ConsignmentStatus::CANCELLED],
            ConsignmentStatus::DRAFT->allowedTransitions()
        );

        // pending → partially_returned, completed, overdue, cancelled
        $this->assertEqualsCanonicalizing(
            [
                ConsignmentStatus::PARTIALLY_RETURNED,
                ConsignmentStatus::COMPLETED,
                ConsignmentStatus::OVERDUE,
                ConsignmentStatus::CANCELLED,
            ],
            ConsignmentStatus::PENDING->allowedTransitions()
        );

        // partially_returned → completed, overdue, cancelled
        $this->assertEqualsCanonicalizing(
            [
                ConsignmentStatus::COMPLETED,
                ConsignmentStatus::OVERDUE,
                ConsignmentStatus::CANCELLED,
            ],
            ConsignmentStatus::PARTIALLY_RETURNED->allowedTransitions()
        );

        // overdue → partially_returned (retorno tardio), completed, cancelled
        $this->assertEqualsCanonicalizing(
            [
                ConsignmentStatus::PARTIALLY_RETURNED,
                ConsignmentStatus::COMPLETED,
                ConsignmentStatus::CANCELLED,
            ],
            ConsignmentStatus::OVERDUE->allowedTransitions()
        );

        // terminais
        $this->assertEmpty(ConsignmentStatus::COMPLETED->allowedTransitions());
        $this->assertEmpty(ConsignmentStatus::CANCELLED->allowedTransitions());
    }

    public function test_can_transition_to_honors_graph(): void
    {
        $this->assertTrue(ConsignmentStatus::PENDING->canTransitionTo(ConsignmentStatus::OVERDUE));
        $this->assertTrue(ConsignmentStatus::OVERDUE->canTransitionTo(ConsignmentStatus::COMPLETED));
        $this->assertFalse(ConsignmentStatus::DRAFT->canTransitionTo(ConsignmentStatus::COMPLETED));
        $this->assertFalse(ConsignmentStatus::COMPLETED->canTransitionTo(ConsignmentStatus::PENDING));
    }

    public function test_terminal_states(): void
    {
        $this->assertTrue(ConsignmentStatus::COMPLETED->isTerminal());
        $this->assertTrue(ConsignmentStatus::CANCELLED->isTerminal());
        $this->assertFalse(ConsignmentStatus::PENDING->isTerminal());
        $this->assertFalse(ConsignmentStatus::OVERDUE->isTerminal());

        $terminal = ConsignmentStatus::terminal();
        $this->assertCount(2, $terminal);
        $this->assertContains(ConsignmentStatus::COMPLETED, $terminal);
        $this->assertContains(ConsignmentStatus::CANCELLED, $terminal);
    }

    public function test_open_states_include_all_non_terminal(): void
    {
        $open = ConsignmentStatus::openStates();
        $this->assertContains(ConsignmentStatus::DRAFT, $open);
        $this->assertContains(ConsignmentStatus::PENDING, $open);
        $this->assertContains(ConsignmentStatus::PARTIALLY_RETURNED, $open);
        $this->assertContains(ConsignmentStatus::OVERDUE, $open);
        $this->assertNotContains(ConsignmentStatus::COMPLETED, $open);
        $this->assertNotContains(ConsignmentStatus::CANCELLED, $open);
    }

    public function test_blocking_states_only_overdue(): void
    {
        // M9: bloqueio de novo cadastro só dispara em OVERDUE
        $blocking = ConsignmentStatus::blockingStates();
        $this->assertSame([ConsignmentStatus::OVERDUE], $blocking);
    }

    public function test_transition_map_is_consistent_with_can_transition_to(): void
    {
        $map = ConsignmentStatus::transitionMap();

        foreach (ConsignmentStatus::cases() as $from) {
            foreach ($map[$from->value] as $toValue) {
                $to = ConsignmentStatus::from($toValue);
                $this->assertTrue(
                    $from->canTransitionTo($to),
                    "{$from->value} → {$to->value} está em transitionMap mas canTransitionTo retorna false"
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // ConsignmentItemStatus::derive — lógica central do status por item
    // ------------------------------------------------------------------

    public function test_item_status_derive_pending_when_nothing_resolved(): void
    {
        $this->assertSame(
            ConsignmentItemStatus::PENDING,
            ConsignmentItemStatus::derive(quantity: 5, returned: 0, sold: 0, lost: 0)
        );
    }

    public function test_item_status_derive_partial_when_part_resolved(): void
    {
        $this->assertSame(
            ConsignmentItemStatus::PARTIAL,
            ConsignmentItemStatus::derive(quantity: 5, returned: 2, sold: 0, lost: 0)
        );

        $this->assertSame(
            ConsignmentItemStatus::PARTIAL,
            ConsignmentItemStatus::derive(quantity: 5, returned: 1, sold: 1, lost: 1)
        );
    }

    public function test_item_status_derive_single_category_complete(): void
    {
        $this->assertSame(
            ConsignmentItemStatus::RETURNED,
            ConsignmentItemStatus::derive(quantity: 5, returned: 5, sold: 0, lost: 0)
        );

        $this->assertSame(
            ConsignmentItemStatus::SOLD,
            ConsignmentItemStatus::derive(quantity: 5, returned: 0, sold: 5, lost: 0)
        );

        $this->assertSame(
            ConsignmentItemStatus::LOST,
            ConsignmentItemStatus::derive(quantity: 5, returned: 0, sold: 0, lost: 5)
        );
    }

    public function test_item_status_derive_mixed_when_multiple_categories_complete(): void
    {
        // 3 devolvidos + 2 vendidos = 5 total (mixed)
        $this->assertSame(
            ConsignmentItemStatus::MIXED,
            ConsignmentItemStatus::derive(quantity: 5, returned: 3, sold: 2, lost: 0)
        );

        // 2 devolvidos + 2 vendidos + 1 perdido = 5 (mixed)
        $this->assertSame(
            ConsignmentItemStatus::MIXED,
            ConsignmentItemStatus::derive(quantity: 5, returned: 2, sold: 2, lost: 1)
        );
    }

    public function test_item_status_is_open_only_for_pending_and_partial(): void
    {
        $this->assertTrue(ConsignmentItemStatus::PENDING->isOpen());
        $this->assertTrue(ConsignmentItemStatus::PARTIAL->isOpen());
        $this->assertFalse(ConsignmentItemStatus::RETURNED->isOpen());
        $this->assertFalse(ConsignmentItemStatus::SOLD->isOpen());
        $this->assertFalse(ConsignmentItemStatus::MIXED->isOpen());
        $this->assertFalse(ConsignmentItemStatus::LOST->isOpen());
    }
}
