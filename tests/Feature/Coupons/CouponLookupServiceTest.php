<?php

namespace Tests\Feature\Coupons;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\SocialMedia;
use App\Services\CouponLookupService;
use App\Services\CouponService;
use App\Services\CouponTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CouponLookupServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected CouponLookupService $lookup;

    protected CouponService $service;

    protected CouponTransitionService $transition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore('Z441', ['network_id' => 6, 'name' => 'E-Commerce']);
        $this->createTestStore('Z443', ['network_id' => 7, 'name' => 'CD Meia Sola']);
        $this->createTestStore('Z421', ['network_id' => 4, 'name' => 'Arezzo Riomar']);

        $this->createTestEmployee(['store_id' => 'Z421', 'name' => 'Maria Silva', 'cpf' => '22211100001']);
        $this->createTestEmployee(['store_id' => 'Z441', 'name' => 'Ana Admin', 'cpf' => '22211100002']);

        if (! SocialMedia::where('name', 'Instagram')->exists()) {
            SocialMedia::create(['name' => 'Instagram', 'is_active' => true, 'sort_order' => 10]);
        }

        $this->lookup = app(CouponLookupService::class);
        $this->service = app(CouponService::class);
        $this->transition = app(CouponTransitionService::class);
        config(['queue.default' => 'sync']);
    }

    public function test_existing_active_for_cpf_returns_active_coupons(): void
    {
        $sm = SocialMedia::first();

        $coupon = $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Busca Pelo CPF',
            'cpf' => '333.444.555-66',
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
        ], $this->adminUser, autoRequest: true);

        $found = $this->lookup->existingActiveForCpf('333.444.555-66');

        $this->assertCount(1, $found);
        $this->assertSame($coupon->id, $found->first()->id);
    }

    public function test_existing_active_ignores_cancelled_coupons(): void
    {
        $sm = SocialMedia::first();

        $coupon = $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Vai Cancelar',
            'cpf' => '444.555.666-77',
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
        ], $this->adminUser, autoRequest: false);

        $this->transition->cancel($coupon, 'Teste', $this->adminUser);

        $found = $this->lookup->existingActiveForCpf('444.555.666-77');

        $this->assertCount(0, $found);
    }

    public function test_existing_active_ignores_deleted_coupons(): void
    {
        $sm = SocialMedia::first();

        $coupon = $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Vai Deletar',
            'cpf' => '555.666.777-88',
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
        ], $this->adminUser, autoRequest: false);

        $this->service->softDelete($coupon, $this->adminUser, 'Teste');

        $found = $this->lookup->existingActiveForCpf('555.666.777-88');

        $this->assertCount(0, $found);
    }

    public function test_is_administrative_store(): void
    {
        $this->assertTrue($this->lookup->isAdministrativeStore('Z441'));  // E-Commerce (network 6)
        $this->assertTrue($this->lookup->isAdministrativeStore('Z443'));  // CD (network 7)
        $this->assertFalse($this->lookup->isAdministrativeStore('Z421')); // Arezzo (network 4)
        $this->assertFalse($this->lookup->isAdministrativeStore('ZINEXISTENT'));
    }

    public function test_employees_by_store(): void
    {
        $employees = $this->lookup->employeesByStore('Z421');

        $this->assertCount(1, $employees);
        $this->assertSame('Maria Silva', $employees->first()['name']);
        // CPF retornado é mascarado
        $this->assertStringContainsString('***.', $employees->first()['cpf_masked']);
    }

    public function test_employee_details_returns_store_network_info(): void
    {
        $maria = \App\Models\Employee::where('name', 'Maria Silva')->first();

        $details = $this->lookup->employeeDetails($maria->id);

        $this->assertSame('Maria Silva', $details['name']);
        $this->assertSame('Z421', $details['store_code']);
        $this->assertSame(4, $details['network_id']);
    }

    public function test_suggest_coupon_code_removes_accents_and_spaces(): void
    {
        $code = $this->lookup->suggestCouponCode('Maria José da Silva', 2026);

        // Nome normalizado é cortado em 15 chars + 2 dígitos do ano
        $this->assertSame('MARIAJOSEDASILV26', $code);
    }

    public function test_suggest_coupon_code_adds_suffix_on_collision(): void
    {
        // Cria cupom com código já em uso
        Coupon::create([
            'type' => CouponType::INFLUENCER,
            'status' => CouponStatus::ACTIVE,
            'influencer_name' => 'Coisinha',
            'cpf' => '999.000.111-22',
            'city' => 'Fortaleza',
            'social_media_id' => SocialMedia::first()->id,
            'coupon_site' => 'ANA26',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $code = $this->lookup->suggestCouponCode('Ana', 2026);

        $this->assertSame('ANA261', $code);
    }

    public function test_active_social_media_excludes_inactive(): void
    {
        SocialMedia::create(['name' => 'Inativa', 'is_active' => false, 'sort_order' => 99]);

        $list = $this->lookup->activeSocialMedia();

        $names = $list->pluck('name')->all();
        $this->assertContains('Instagram', $names);
        $this->assertNotContains('Inativa', $names);
    }
}
