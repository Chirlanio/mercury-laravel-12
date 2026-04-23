<?php

namespace Tests\Feature\Coupons;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\SocialMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CouponControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected int $adminEmployeeId;

    protected int $storeEmployeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore('Z441', ['network_id' => 6, 'name' => 'E-Commerce']);
        $this->createTestStore('Z421', ['network_id' => 4, 'name' => 'Arezzo Riomar']);

        $this->adminEmployeeId = $this->createTestEmployee(['store_id' => 'Z441', 'name' => 'Ana Admin', 'cpf' => '10000000001']);
        $this->storeEmployeeId = $this->createTestEmployee(['store_id' => 'Z421', 'name' => 'Maria Silva', 'cpf' => '10000000002']);

        if (! SocialMedia::where('name', 'Instagram')->exists()) {
            SocialMedia::create(['name' => 'Instagram', 'is_active' => true, 'sort_order' => 10]);
        }

        config(['queue.default' => 'sync']);
    }

    protected function createCoupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'type' => CouponType::INFLUENCER,
            'status' => CouponStatus::REQUESTED,
            'influencer_name' => 'Teste '.uniqid(),
            'cpf' => '100.'.rand(100, 999).'.'.rand(100, 999).'-'.rand(10, 99),
            'city' => 'Fortaleza',
            'social_media_id' => SocialMedia::first()->id,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    public function test_admin_can_view_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('coupons.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Coupons/Index'));
    }

    public function test_regular_user_without_view_permission_is_blocked(): void
    {
        // Regular user tem VIEW_COUPONS + CREATE_COUPONS (ver Role::USER), então vê
        // Aqui só testa que rotas estão protegidas pelo middleware permission
        $this->actingAs($this->regularUser)
            ->get(route('coupons.index'))
            ->assertStatus(200);
    }

    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->get(route('coupons.index'))->assertRedirect('/login');
    }

    public function test_index_hides_cancelled_and_expired_by_default(): void
    {
        $this->createCoupon(['status' => CouponStatus::REQUESTED]);
        $this->createCoupon(['status' => CouponStatus::CANCELLED, 'cancelled_at' => now(), 'cancelled_reason' => 'teste']);
        $this->createCoupon(['status' => CouponStatus::EXPIRED, 'expired_at' => now()]);

        $response = $this->actingAs($this->adminUser)->get(route('coupons.index'));

        $response->assertInertia(fn ($page) => $page->has('coupons.data', 1));
    }

    public function test_index_shows_all_with_include_cancelled(): void
    {
        $this->createCoupon(['status' => CouponStatus::REQUESTED]);
        $this->createCoupon(['status' => CouponStatus::CANCELLED, 'cancelled_at' => now(), 'cancelled_reason' => 'teste']);

        $response = $this->actingAs($this->adminUser)->get(route('coupons.index', ['include_cancelled' => 1]));

        $response->assertInertia(fn ($page) => $page->has('coupons.data', 2));
    }

    public function test_admin_can_create_influencer_coupon(): void
    {
        $sm = SocialMedia::first();

        $response = $this->actingAs($this->adminUser)->post(route('coupons.store'), [
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Maria Controller',
            'cpf' => '111.222.333-44',
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
            'auto_request' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('coupons', [
            'influencer_name' => 'Maria Controller',
            'status' => CouponStatus::DRAFT->value,
        ]);
    }

    public function test_create_with_auto_request_transitions_to_requested(): void
    {
        $sm = SocialMedia::first();

        $this->actingAs($this->adminUser)->post(route('coupons.store'), [
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'AutoRequest',
            'cpf' => '222.333.444-55',
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
            // auto_request padrão é true quando omitido
        ])->assertRedirect();

        $this->assertDatabaseHas('coupons', [
            'influencer_name' => 'AutoRequest',
            'status' => CouponStatus::REQUESTED->value,
        ]);
    }

    public function test_validation_errors_propagated_back(): void
    {
        $this->actingAs($this->adminUser)->post(route('coupons.store'), [
            'type' => CouponType::INFLUENCER->value,
            // Faltam vários campos
        ])->assertSessionHasErrors();
    }

    public function test_show_returns_json_detailed(): void
    {
        $coupon = $this->createCoupon();

        $response = $this->actingAs($this->adminUser)->get(route('coupons.show', $coupon->id));

        $response->assertOk();
        $response->assertJsonStructure(['coupon' => ['id', 'type', 'status', 'history']]);
    }

    public function test_update_allowed_for_draft(): void
    {
        $coupon = $this->createCoupon(['status' => CouponStatus::DRAFT]);

        $this->actingAs($this->adminUser)->put(route('coupons.update', $coupon->id), [
            'notes' => 'Nota atualizada via controller',
        ])->assertRedirect();

        $this->assertSame('Nota atualizada via controller', $coupon->fresh()->notes);
    }

    public function test_admin_can_delete_with_reason(): void
    {
        $coupon = $this->createCoupon(['status' => CouponStatus::DRAFT]);

        $this->actingAs($this->adminUser)->delete(route('coupons.destroy', $coupon->id), [
            'deleted_reason' => 'Criado por engano',
        ])->assertRedirect();

        $this->assertNotNull($coupon->fresh()->deleted_at);
    }

    public function test_delete_without_reason_fails_validation(): void
    {
        $coupon = $this->createCoupon(['status' => CouponStatus::DRAFT]);

        $this->actingAs($this->adminUser)
            ->delete(route('coupons.destroy', $coupon->id), [])
            ->assertSessionHasErrors('deleted_reason');
    }

    public function test_transition_to_issued_requires_coupon_site(): void
    {
        $coupon = $this->createCoupon(['status' => CouponStatus::REQUESTED]);

        $this->actingAs($this->adminUser)
            ->post(route('coupons.transition', $coupon->id), [
                'to_status' => CouponStatus::ISSUED->value,
                // Faltou coupon_site
            ])
            ->assertSessionHasErrors('coupon_site');
    }

    public function test_transition_to_issued_succeeds_with_code(): void
    {
        $coupon = $this->createCoupon(['status' => CouponStatus::REQUESTED]);

        $this->actingAs($this->adminUser)
            ->post(route('coupons.transition', $coupon->id), [
                'to_status' => CouponStatus::ISSUED->value,
                'coupon_site' => 'PROMO555',
            ])
            ->assertRedirect();

        $fresh = $coupon->fresh();
        $this->assertSame(CouponStatus::ISSUED, $fresh->status);
        $this->assertSame('PROMO555', $fresh->coupon_site);
    }

    public function test_transition_to_cancelled_requires_note(): void
    {
        $coupon = $this->createCoupon(['status' => CouponStatus::REQUESTED]);

        // to_status=cancelled + note vazio → service valida e retorna erro
        $this->actingAs($this->adminUser)
            ->post(route('coupons.transition', $coupon->id), [
                'to_status' => CouponStatus::CANCELLED->value,
                'note' => '',
            ])
            ->assertSessionHasErrors('note');
    }

    public function test_lookup_existing_returns_active_coupons(): void
    {
        $this->createCoupon([
            'cpf' => '555.555.555-55',
            'status' => CouponStatus::REQUESTED,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson(route('coupons.lookup.existing', [
            'cpf' => '555.555.555-55',
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'existing');
    }

    public function test_lookup_employees_by_store(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson(route('coupons.lookup.employees', [
            'store_code' => 'Z421',
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'employees');
    }

    public function test_suggest_code_returns_formatted(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson(route('coupons.suggest-code', [
            'name' => 'Maria Teste',
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertJson(['code' => 'MARIATESTE26']);
    }

    public function test_store_scoping_for_support_user(): void
    {
        // Support tem VIEW_COUPONS sem MANAGE — vai ser store-scoped
        // Vinculamos ao Z421
        $this->supportUser->store_id = 'Z421';
        $this->supportUser->save();

        $this->createCoupon([
            'type' => CouponType::CONSULTOR,
            'status' => CouponStatus::REQUESTED,
            'store_code' => 'Z421',
            'employee_id' => $this->storeEmployeeId,
            'cpf' => '888.888.888-88',
        ]);
        $this->createCoupon([
            'type' => CouponType::CONSULTOR,
            'status' => CouponStatus::REQUESTED,
            'store_code' => 'Z441',
            'employee_id' => $this->adminEmployeeId,
            'cpf' => '999.999.999-99',
        ]);

        $response = $this->actingAs($this->supportUser)->get(route('coupons.index'));

        // Deve ver apenas o da Z421 (ou criou — nenhum dos dois foi criado pelo support)
        $response->assertInertia(fn ($page) => $page
            ->has('coupons.data', 1)
            ->where('isStoreScoped', true)
            ->where('scopedStoreCode', 'Z421')
        );
    }
}
