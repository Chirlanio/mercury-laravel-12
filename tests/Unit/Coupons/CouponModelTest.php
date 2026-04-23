<?php

namespace Tests\Unit\Coupons;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CouponModelTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_cpf_hash_is_deterministic_regardless_of_mask(): void
    {
        $h1 = Coupon::hashCpf('123.456.789-00');
        $h2 = Coupon::hashCpf('12345678900');
        $h3 = Coupon::hashCpf(' 123 456 789 00 ');

        $this->assertSame($h1, $h2);
        $this->assertSame($h1, $h3);
        $this->assertSame(64, strlen($h1));
    }

    public function test_cpf_is_encrypted_in_database(): void
    {
        $coupon = $this->makeCoupon(['cpf' => '111.222.333-44']);

        $raw = $coupon->getRawOriginal('cpf');
        $this->assertNotSame('111.222.333-44', $raw);
        $this->assertNotSame('11122233344', $raw);

        // Accessor decripta
        $this->assertSame('111.222.333-44', $coupon->fresh()->cpf);
    }

    public function test_cpf_hash_is_recomputed_on_set(): void
    {
        $coupon = $this->makeCoupon(['cpf' => '111.222.333-44']);

        $this->assertSame(Coupon::hashCpf('11122233344'), $coupon->cpf_hash);

        $coupon->cpf = '999.888.777-66';
        $coupon->save();

        $this->assertSame(Coupon::hashCpf('99988877766'), $coupon->fresh()->cpf_hash);
    }

    public function test_masked_cpf_formats_digits(): void
    {
        $coupon = $this->makeCoupon(['cpf' => '11122233344']);

        $this->assertSame('111.222.333-44', $coupon->fresh()->masked_cpf);
    }

    public function test_state_machine_allowed_transitions(): void
    {
        $coupon = $this->makeCoupon();
        $this->assertSame(CouponStatus::DRAFT, $coupon->status);

        $this->assertTrue($coupon->canTransitionTo('requested'));
        $this->assertTrue($coupon->canTransitionTo(CouponStatus::CANCELLED));
        $this->assertFalse($coupon->canTransitionTo('active'));
        $this->assertFalse($coupon->canTransitionTo('expired'));
    }

    public function test_terminal_states_reject_further_transitions(): void
    {
        $coupon = $this->makeCoupon();
        $coupon->status = CouponStatus::EXPIRED;
        $this->assertTrue($coupon->isTerminal());
        $this->assertFalse($coupon->canTransitionTo('active'));

        $coupon->status = CouponStatus::CANCELLED;
        $this->assertTrue($coupon->isTerminal());
        $this->assertFalse($coupon->canTransitionTo('requested'));
    }

    public function test_beneficiary_name_falls_back_by_type(): void
    {
        $employee = Employee::first();

        $consultor = $this->makeCoupon([
            'type' => CouponType::CONSULTOR,
            'employee_id' => $employee?->id,
            'store_code' => $employee?->store_id,
            'influencer_name' => null,
        ]);
        $this->assertSame($employee?->name ?? '', $consultor->fresh()->beneficiary_name);

        $influencer = $this->makeCoupon([
            'type' => CouponType::INFLUENCER,
            'employee_id' => null,
            'store_code' => null,
            'influencer_name' => 'Maria Exemplo',
            'city' => 'Fortaleza',
        ]);
        $this->assertSame('Maria Exemplo', $influencer->fresh()->beneficiary_name);
    }

    public function test_active_scope_excludes_terminal_states(): void
    {
        $this->makeCoupon(['status' => CouponStatus::DRAFT]);
        $this->makeCoupon(['status' => CouponStatus::REQUESTED]);
        $this->makeCoupon(['status' => CouponStatus::ACTIVE]);
        $this->makeCoupon(['status' => CouponStatus::EXPIRED]);
        $this->makeCoupon(['status' => CouponStatus::CANCELLED]);

        $this->assertSame(3, Coupon::active()->count());
    }

    public function test_for_cpf_hash_scope_matches_by_hash(): void
    {
        $this->makeCoupon(['cpf' => '111.222.333-44']);
        $this->makeCoupon(['cpf' => '999.888.777-66']);

        $found = Coupon::forCpfHash(Coupon::hashCpf('111.222.333-44'))->first();
        $this->assertNotNull($found);
        $this->assertSame('111.222.333-44', $found->cpf);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeCoupon(array $attrs = []): Coupon
    {
        $user = User::first() ?? $this->adminUser;

        return Coupon::create(array_merge([
            'type' => CouponType::INFLUENCER,
            'status' => CouponStatus::DRAFT,
            'influencer_name' => 'Teste '.uniqid(),
            'cpf' => '100.'.rand(100, 999).'.'.rand(100, 999).'-'.rand(10, 99),
            'city' => 'Fortaleza',
            'created_by_user_id' => $user->id,
        ], $attrs));
    }
}
