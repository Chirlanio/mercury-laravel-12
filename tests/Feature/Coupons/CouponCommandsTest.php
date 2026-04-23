<?php

namespace Tests\Feature\Coupons;

use App\Console\Commands\CouponsExpireStaleCommand;
use App\Console\Commands\CouponsRemindPendingCommand;
use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\SocialMedia;
use App\Services\CouponTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CouponCommandsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        if (! SocialMedia::where('name', 'Instagram')->exists()) {
            SocialMedia::create(['name' => 'Instagram', 'is_active' => true, 'sort_order' => 10]);
        }

        config(['queue.default' => 'sync']);
    }

    protected function expireCommand(): CouponsExpireStaleCommand
    {
        $cmd = app(CouponsExpireStaleCommand::class);
        $input = new ArrayInput([]);
        $cmd->setOutput(new \Illuminate\Console\OutputStyle($input, new BufferedOutput()));

        return $cmd;
    }

    protected function remindCommand(): CouponsRemindPendingCommand
    {
        $cmd = app(CouponsRemindPendingCommand::class);
        $input = new ArrayInput([]);
        $cmd->setOutput(new \Illuminate\Console\OutputStyle($input, new BufferedOutput()));

        return $cmd;
    }

    protected function makeCoupon(array $attrs = []): Coupon
    {
        return Coupon::create(array_merge([
            'type' => CouponType::INFLUENCER,
            'status' => CouponStatus::ACTIVE,
            'influencer_name' => 'Teste '.uniqid(),
            'cpf' => '100.'.rand(100, 999).'.'.rand(100, 999).'-'.rand(10, 99),
            'city' => 'Fortaleza',
            'social_media_id' => SocialMedia::first()->id,
            'created_by_user_id' => $this->adminUser->id,
        ], $attrs));
    }

    // ==================================================================
    // coupons:expire-stale
    // ==================================================================

    public function test_expire_stale_marks_expired_coupons(): void
    {
        $cmd = $this->expireCommand();
        $transition = app(CouponTransitionService::class);

        // Expirado (ontem, status active)
        $expired = $this->makeCoupon([
            'status' => CouponStatus::ACTIVE,
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        // Ainda válido
        $valid = $this->makeCoupon([
            'status' => CouponStatus::ACTIVE,
            'valid_until' => now()->addMonth()->toDateString(),
        ]);

        // Sem validade — não mexe
        $noValidity = $this->makeCoupon([
            'status' => CouponStatus::ACTIVE,
            'valid_until' => null,
        ]);

        $count = $cmd->scanTenant($transition);

        $this->assertSame(1, $count);
        $this->assertSame(CouponStatus::EXPIRED, $expired->fresh()->status);
        $this->assertSame(CouponStatus::ACTIVE, $valid->fresh()->status);
        $this->assertSame(CouponStatus::ACTIVE, $noValidity->fresh()->status);
    }

    public function test_expire_stale_ignores_draft_and_requested(): void
    {
        $cmd = $this->expireCommand();
        $transition = app(CouponTransitionService::class);

        // Vencido mas em draft — não deve mexer (ainda nem foi emitido)
        $draft = $this->makeCoupon([
            'status' => CouponStatus::DRAFT,
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $requested = $this->makeCoupon([
            'status' => CouponStatus::REQUESTED,
            'valid_until' => now()->subDay()->toDateString(),
            'requested_at' => now()->subDays(2),
        ]);

        $count = $cmd->scanTenant($transition);

        $this->assertSame(0, $count);
        $this->assertSame(CouponStatus::DRAFT, $draft->fresh()->status);
        $this->assertSame(CouponStatus::REQUESTED, $requested->fresh()->status);
    }

    public function test_expire_stale_is_idempotent(): void
    {
        $cmd = $this->expireCommand();
        $transition = app(CouponTransitionService::class);

        $this->makeCoupon([
            'status' => CouponStatus::ACTIVE,
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $first = $cmd->scanTenant($transition);
        $second = $cmd->scanTenant($transition);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
    }

    // ==================================================================
    // coupons:remind-pending
    // ==================================================================

    public function test_remind_pending_sends_notifications_for_stale_requested(): void
    {
        Notification::fake();

        // Stale: requested há 4 dias (threshold default 3)
        $this->makeCoupon([
            'status' => CouponStatus::REQUESTED,
            'requested_at' => now()->subDays(4),
        ]);

        $cmd = $this->remindCommand();
        $count = $cmd->scanTenant(3);

        // adminUser tem ISSUE_COUPON_CODE → recebe lembrete
        $this->assertGreaterThan(0, $count);
    }

    public function test_remind_pending_skips_recent_requested(): void
    {
        Notification::fake();

        // Requested hoje — não passou do threshold
        $this->makeCoupon([
            'status' => CouponStatus::REQUESTED,
            'requested_at' => now(),
        ]);

        $cmd = $this->remindCommand();
        $count = $cmd->scanTenant(3);

        $this->assertSame(0, $count);
    }

    public function test_remind_pending_skips_non_requested_status(): void
    {
        Notification::fake();

        // Active há 10 dias — não é requested, não entra no alerta
        $this->makeCoupon([
            'status' => CouponStatus::ACTIVE,
            'requested_at' => now()->subDays(10),
        ]);

        $cmd = $this->remindCommand();
        $count = $cmd->scanTenant(3);

        $this->assertSame(0, $count);
    }

    public function test_remind_pending_uses_created_at_when_requested_at_null(): void
    {
        Notification::fake();

        // Fallback: requested_at null → usa created_at pra comparar
        $coupon = $this->makeCoupon([
            'status' => CouponStatus::REQUESTED,
            'requested_at' => null,
        ]);
        $coupon->created_at = now()->subDays(5);
        $coupon->save();

        $cmd = $this->remindCommand();
        $count = $cmd->scanTenant(3);

        $this->assertGreaterThan(0, $count);
    }
}
