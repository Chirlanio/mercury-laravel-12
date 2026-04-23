<?php

namespace Tests\Feature\Coupons;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Enums\Role;
use App\Events\CouponStatusChanged;
use App\Listeners\NotifyCouponStakeholders;
use App\Models\Coupon;
use App\Models\SocialMedia;
use App\Models\User;
use App\Notifications\CouponStatusChangedNotification;
use App\Services\CouponService;
use App\Services\CouponTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre o canal mail do CouponStatusChangedNotification e a cópia
 * mail-only ao criador em → requested.
 */
class CouponNotificationTest extends TestCase
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

    protected function makeDraftCoupon(?User $creator = null): Coupon
    {
        $sm = SocialMedia::first();

        return $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Teste '.uniqid(),
            'cpf' => '100.'.rand(100, 999).'.'.rand(100, 999).'-'.rand(10, 99),
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
        ], $creator ?? $this->regularUser, autoRequest: false);
    }

    public function test_mail_channel_is_active_on_requested_transition(): void
    {
        $coupon = $this->makeDraftCoupon($this->regularUser);

        $notification = new CouponStatusChangedNotification(
            coupon: $coupon,
            fromStatus: CouponStatus::DRAFT,
            toStatus: CouponStatus::REQUESTED,
            actor: $this->regularUser,
            note: null,
        );

        $this->assertContains('mail', $notification->via($this->adminUser));
        $this->assertContains('database', $notification->via($this->adminUser));
    }

    public function test_mail_channel_is_active_on_active_transition(): void
    {
        $coupon = $this->makeDraftCoupon($this->regularUser);

        $notification = new CouponStatusChangedNotification(
            coupon: $coupon,
            fromStatus: CouponStatus::ISSUED,
            toStatus: CouponStatus::ACTIVE,
            actor: $this->adminUser,
            note: null,
        );

        $this->assertContains('mail', $notification->via($this->regularUser));
        $this->assertContains('database', $notification->via($this->regularUser));
    }

    public function test_mail_channel_is_inactive_on_issued_transition(): void
    {
        $coupon = $this->makeDraftCoupon($this->regularUser);

        $notification = new CouponStatusChangedNotification(
            coupon: $coupon,
            fromStatus: CouponStatus::REQUESTED,
            toStatus: CouponStatus::ISSUED,
            actor: $this->adminUser,
            note: null,
        );

        $channels = $notification->via($this->regularUser);
        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_mail_channel_is_inactive_on_cancelled_and_expired(): void
    {
        $coupon = $this->makeDraftCoupon($this->regularUser);

        foreach ([CouponStatus::CANCELLED, CouponStatus::EXPIRED] as $toStatus) {
            $notification = new CouponStatusChangedNotification(
                coupon: $coupon,
                fromStatus: CouponStatus::ACTIVE,
                toStatus: $toStatus,
                actor: $this->adminUser,
                note: 'Teste',
            );

            $channels = $notification->via($this->regularUser);
            $this->assertContains('database', $channels, "database deveria estar ativo em → {$toStatus->value}");
            $this->assertNotContains('mail', $channels, "mail NÃO deveria estar ativo em → {$toStatus->value}");
        }
    }

    public function test_mail_is_skipped_when_notifiable_has_no_email(): void
    {
        // Constrói sem persistir — users.email é NOT NULL no schema, mas
        // via() opera só sobre o atributo em memória.
        $userSemEmail = new User([
            'name' => 'Sem E-mail',
            'role' => Role::USER->value,
        ]);
        $userSemEmail->email = '';

        $coupon = $this->makeDraftCoupon($this->regularUser);

        $notification = new CouponStatusChangedNotification(
            coupon: $coupon,
            fromStatus: CouponStatus::DRAFT,
            toStatus: CouponStatus::REQUESTED,
            actor: $this->regularUser,
            note: null,
        );

        $channels = $notification->via($userSemEmail);
        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_to_mail_renders_for_requested_transition(): void
    {
        $coupon = $this->makeDraftCoupon($this->regularUser);
        $coupon->suggested_coupon = 'PROMO10';
        $coupon->save();

        $notification = new CouponStatusChangedNotification(
            coupon: $coupon,
            fromStatus: CouponStatus::DRAFT,
            toStatus: CouponStatus::REQUESTED,
            actor: $this->regularUser,
            note: null,
        );

        $mail = $notification->toMail($this->adminUser);
        $this->assertStringContainsString('Nova solicitação', $mail->subject);
        $this->assertStringContainsString('PROMO10', implode("\n", $mail->introLines));
    }

    public function test_to_mail_renders_for_active_transition_with_code(): void
    {
        $coupon = $this->makeDraftCoupon($this->regularUser);
        $coupon->coupon_site = 'INFLU2025';
        $coupon->save();

        $notification = new CouponStatusChangedNotification(
            coupon: $coupon,
            fromStatus: CouponStatus::ISSUED,
            toStatus: CouponStatus::ACTIVE,
            actor: $this->adminUser,
            note: null,
        );

        $mail = $notification->toMail($this->regularUser);
        $this->assertStringContainsString('INFLU2025', $mail->subject);
        $this->assertStringContainsString('INFLU2025', implode("\n", $mail->introLines));
    }

    public function test_listener_notifies_ecommerce_and_creator_on_requested(): void
    {
        Notification::fake();

        $coupon = $this->makeDraftCoupon($this->regularUser);

        $event = new CouponStatusChanged(
            coupon: $coupon,
            fromStatus: CouponStatus::DRAFT,
            toStatus: CouponStatus::REQUESTED,
            actor: $this->regularUser,
            note: null,
        );

        app(NotifyCouponStakeholders::class)->handle($event);

        // adminUser (ADMIN tem ISSUE_COUPON_CODE) recebe database + mail
        Notification::assertSentTo(
            $this->adminUser,
            CouponStatusChangedNotification::class,
            function ($notification) {
                return ! $notification->mailOnly
                    && $notification->toStatus === CouponStatus::REQUESTED;
            }
        );

        // regularUser (criador) recebe cópia mail-only, mesmo sendo o actor
        Notification::assertSentTo(
            $this->regularUser,
            CouponStatusChangedNotification::class,
            fn ($notification) => $notification->mailOnly === true
        );
    }

    public function test_mail_only_notification_skips_database_channel(): void
    {
        $coupon = $this->makeDraftCoupon($this->regularUser);

        $notification = new CouponStatusChangedNotification(
            coupon: $coupon,
            fromStatus: CouponStatus::DRAFT,
            toStatus: CouponStatus::REQUESTED,
            actor: $this->regularUser,
            note: null,
            mailOnly: true,
        );

        $channels = $notification->via($this->regularUser);
        $this->assertSame(['mail'], $channels);
    }

    public function test_listener_does_not_send_creator_copy_outside_requested(): void
    {
        Notification::fake();

        $coupon = $this->makeDraftCoupon($this->regularUser);
        $coupon->status = CouponStatus::ACTIVE;
        $coupon->coupon_site = 'ACTIVE25';
        $coupon->save();

        $event = new CouponStatusChanged(
            coupon: $coupon,
            fromStatus: CouponStatus::ISSUED,
            toStatus: CouponStatus::ACTIVE,
            actor: $this->adminUser,
            note: null,
        );

        app(NotifyCouponStakeholders::class)->handle($event);

        // Nenhuma notificação mailOnly deve sair quando toStatus != REQUESTED
        Notification::assertNotSentTo(
            $this->regularUser,
            CouponStatusChangedNotification::class,
            fn ($notification) => $notification->mailOnly === true
        );

        // Mas o fluxo padrão pro criador (regularUser) em → ACTIVE ainda roda
        Notification::assertSentTo(
            $this->regularUser,
            CouponStatusChangedNotification::class,
            function ($notification) {
                return ! $notification->mailOnly
                    && $notification->toStatus === CouponStatus::ACTIVE;
            }
        );
    }
}
