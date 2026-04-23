<?php

namespace Tests\Feature\Coupons;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponStatusHistory;
use App\Models\SocialMedia;
use App\Services\CouponService;
use App\Services\CouponTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CouponTransitionServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected CouponService $service;

    protected CouponTransitionService $transition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        if (! SocialMedia::where('name', 'Instagram')->exists()) {
            SocialMedia::create(['name' => 'Instagram', 'is_active' => true, 'sort_order' => 10]);
        }

        $this->service = app(CouponService::class);
        $this->transition = app(CouponTransitionService::class);
        config(['queue.default' => 'sync']);
    }

    protected function makeDraftCoupon(): Coupon
    {
        $sm = SocialMedia::first();

        return $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Teste '.uniqid(),
            'cpf' => '100.'.rand(100, 999).'.'.rand(100, 999).'-'.rand(10, 99),
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
        ], $this->adminUser, autoRequest: false);
    }

    public function test_draft_to_requested_via_request_helper(): void
    {
        $coupon = $this->makeDraftCoupon();

        $updated = $this->transition->request($coupon, $this->adminUser);

        $this->assertSame(CouponStatus::REQUESTED, $updated->status);
        $this->assertNotNull($updated->requested_at);
    }

    public function test_requested_to_issued_persists_coupon_site(): void
    {
        $coupon = $this->transition->request($this->makeDraftCoupon(), $this->adminUser);

        $updated = $this->transition->issueCode($coupon, 'PROMO123', $this->adminUser);

        $this->assertSame(CouponStatus::ISSUED, $updated->status);
        $this->assertSame('PROMO123', $updated->coupon_site);
        $this->assertNotNull($updated->issued_at);
        $this->assertSame($this->adminUser->id, $updated->issued_by_user_id);
    }

    public function test_issue_requires_coupon_site_in_context(): void
    {
        $coupon = $this->transition->request($this->makeDraftCoupon(), $this->adminUser);

        $this->expectException(ValidationException::class);

        $this->transition->transition($coupon, CouponStatus::ISSUED, $this->adminUser, null, []);
    }

    public function test_rejects_invalid_transition_draft_to_active(): void
    {
        $coupon = $this->makeDraftCoupon();

        $this->expectException(ValidationException::class);

        $this->transition->transition($coupon, CouponStatus::ACTIVE, $this->adminUser);
    }

    public function test_cancel_requires_reason(): void
    {
        $coupon = $this->makeDraftCoupon();

        $this->expectException(ValidationException::class);

        $this->transition->transition($coupon, CouponStatus::CANCELLED, $this->adminUser, null);
    }

    public function test_cancel_with_reason_succeeds(): void
    {
        $coupon = $this->makeDraftCoupon();

        $cancelled = $this->transition->cancel($coupon, 'Cliente desistiu', $this->adminUser);

        $this->assertSame(CouponStatus::CANCELLED, $cancelled->status);
        $this->assertSame('Cliente desistiu', $cancelled->cancelled_reason);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_terminal_states_block_further_transitions(): void
    {
        $coupon = $this->transition->cancel($this->makeDraftCoupon(), 'Encerrado', $this->adminUser);

        $this->expectException(ValidationException::class);

        $this->transition->transition($coupon, CouponStatus::REQUESTED, $this->adminUser);
    }

    public function test_expire_allowed_without_actor(): void
    {
        $coupon = $this->transition->request($this->makeDraftCoupon(), $this->adminUser);
        $coupon = $this->transition->issueCode($coupon, 'EXPTEST25', $this->adminUser);
        $coupon = $this->transition->activate($coupon, $this->adminUser);

        // Command agendado roda sem actor
        $expired = $this->transition->expire($coupon);

        $this->assertSame(CouponStatus::EXPIRED, $expired->status);
        $this->assertNotNull($expired->expired_at);
    }

    public function test_non_expired_transition_requires_actor(): void
    {
        $coupon = $this->makeDraftCoupon();

        $this->expectException(ValidationException::class);

        $this->transition->transition($coupon, CouponStatus::REQUESTED, null);
    }

    public function test_duplicate_coupon_site_blocked_on_issue(): void
    {
        $c1 = $this->transition->request($this->makeDraftCoupon(), $this->adminUser);
        $this->transition->issueCode($c1, 'CODIGO999', $this->adminUser);

        $c2 = $this->transition->request($this->makeDraftCoupon(), $this->adminUser);

        $this->expectException(ValidationException::class);

        $this->transition->issueCode($c2, 'CODIGO999', $this->adminUser);
    }

    public function test_history_records_each_transition(): void
    {
        $coupon = $this->makeDraftCoupon();
        $coupon = $this->transition->request($coupon, $this->adminUser);
        $coupon = $this->transition->issueCode($coupon, 'H1STORIA', $this->adminUser);
        $coupon = $this->transition->activate($coupon, $this->adminUser);

        $history = CouponStatusHistory::where('coupon_id', $coupon->id)->orderBy('id')->get();

        // Cria (null→draft) + request (draft→requested) + issue (requested→issued) + activate (issued→active)
        $this->assertCount(4, $history);
        $this->assertSame(CouponStatus::REQUESTED, $history[1]->to_status);
        $this->assertSame(CouponStatus::ISSUED, $history[2]->to_status);
        $this->assertSame(CouponStatus::ACTIVE, $history[3]->to_status);
    }

    public function test_deleted_coupon_blocks_transition(): void
    {
        $coupon = $this->makeDraftCoupon();
        $this->service->softDelete($coupon, $this->adminUser, 'Teste');

        $this->expectException(ValidationException::class);

        $this->transition->request($coupon->fresh(), $this->adminUser);
    }
}
