<?php

namespace Tests\Unit\Coupons;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use PHPUnit\Framework\TestCase;

class CouponEnumsTest extends TestCase
{
    public function test_coupon_type_labels(): void
    {
        $this->assertSame('Consultor(a)', CouponType::CONSULTOR->label());
        $this->assertSame('Influencer', CouponType::INFLUENCER->label());
        $this->assertSame('MS Indica', CouponType::MS_INDICA->label());
    }

    public function test_coupon_type_requirements_per_flow(): void
    {
        $this->assertTrue(CouponType::CONSULTOR->requiresStoreAndEmployee());
        $this->assertTrue(CouponType::MS_INDICA->requiresStoreAndEmployee());
        $this->assertFalse(CouponType::INFLUENCER->requiresStoreAndEmployee());

        $this->assertTrue(CouponType::INFLUENCER->requiresInfluencerFields());
        $this->assertFalse(CouponType::CONSULTOR->requiresInfluencerFields());
        $this->assertFalse(CouponType::MS_INDICA->requiresInfluencerFields());

        // Só MS Indica restringe a loja administrativa
        $this->assertTrue(CouponType::MS_INDICA->requiresAdministrativeStore());
        $this->assertFalse(CouponType::CONSULTOR->requiresAdministrativeStore());
        $this->assertFalse(CouponType::INFLUENCER->requiresAdministrativeStore());
    }

    public function test_coupon_status_transition_graph(): void
    {
        // draft → requested, cancelled
        $this->assertEqualsCanonicalizing(
            [CouponStatus::REQUESTED, CouponStatus::CANCELLED],
            CouponStatus::DRAFT->allowedTransitions()
        );

        // requested → issued, cancelled
        $this->assertEqualsCanonicalizing(
            [CouponStatus::ISSUED, CouponStatus::CANCELLED],
            CouponStatus::REQUESTED->allowedTransitions()
        );

        // issued → active, expired, cancelled
        $this->assertEqualsCanonicalizing(
            [CouponStatus::ACTIVE, CouponStatus::EXPIRED, CouponStatus::CANCELLED],
            CouponStatus::ISSUED->allowedTransitions()
        );

        // active → expired, cancelled
        $this->assertEqualsCanonicalizing(
            [CouponStatus::EXPIRED, CouponStatus::CANCELLED],
            CouponStatus::ACTIVE->allowedTransitions()
        );

        // terminais não transicionam
        $this->assertEmpty(CouponStatus::EXPIRED->allowedTransitions());
        $this->assertEmpty(CouponStatus::CANCELLED->allowedTransitions());
    }

    public function test_terminal_and_active_sets(): void
    {
        $terminal = CouponStatus::terminal();
        $this->assertContains(CouponStatus::EXPIRED, $terminal);
        $this->assertContains(CouponStatus::CANCELLED, $terminal);
        $this->assertCount(2, $terminal);

        $active = CouponStatus::active();
        $this->assertCount(4, $active);
        $this->assertNotContains(CouponStatus::EXPIRED, $active);
        $this->assertNotContains(CouponStatus::CANCELLED, $active);
    }

    public function test_transition_map_is_symmetric_with_can_transition_to(): void
    {
        $map = CouponStatus::transitionMap();

        foreach (CouponStatus::cases() as $from) {
            foreach (CouponStatus::cases() as $to) {
                $mappedAllows = in_array($to->value, $map[$from->value], true);
                $methodAllows = $from->canTransitionTo($to);
                $this->assertSame(
                    $mappedAllows,
                    $methodAllows,
                    "Inconsistência: {$from->value} → {$to->value}"
                );
            }
        }
    }
}
