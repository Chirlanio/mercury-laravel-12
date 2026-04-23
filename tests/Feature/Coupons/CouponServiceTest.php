<?php

namespace Tests\Feature\Coupons;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\SocialMedia;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected CouponService $service;

    protected string $adminStoreCode = 'Z441'; // E-Commerce (network_id 6)
    protected int $adminEmployeeId;

    protected string $regularStoreCode = 'Z421'; // Arezzo (network_id 4)
    protected int $regularEmployeeId;

    protected string $otherRegularStoreCode = 'Z422'; // Arezzo (network_id 4)
    protected int $otherRegularEmployeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        // Cria stores distintas pra testar scoping
        $this->createTestStore('Z441', ['network_id' => 6, 'name' => 'E-Commerce']);
        $this->createTestStore('Z421', ['network_id' => 4, 'name' => 'Arezzo Riomar']);
        $this->createTestStore('Z422', ['network_id' => 4, 'name' => 'Arezzo Kennedy']);

        $this->adminEmployeeId = $this->createTestEmployee(['store_id' => 'Z441', 'name' => 'Ana Admin', 'cpf' => '10000000001']);
        $this->regularEmployeeId = $this->createTestEmployee(['store_id' => 'Z421', 'name' => 'Maria Silva', 'cpf' => '10000000002']);
        $this->otherRegularEmployeeId = $this->createTestEmployee(['store_id' => 'Z422', 'name' => 'Julia Costa', 'cpf' => '10000000003']);

        // Social media seed (migration já cria, mas garante em testes RefreshDatabase)
        if (! SocialMedia::where('name', 'Instagram')->exists()) {
            SocialMedia::create(['name' => 'Instagram', 'is_active' => true, 'sort_order' => 10]);
        }

        $this->service = app(CouponService::class);
        config(['queue.default' => 'sync']);
    }

    public function test_creates_influencer_coupon_with_autoRequest_transitions_to_requested(): void
    {
        $sm = SocialMedia::first();

        $coupon = $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Maria Influencer',
            'cpf' => '111.222.333-44',
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
            'social_media_link' => 'https://instagram.com/maria',
            'suggested_coupon' => 'MARIA25',
        ], $this->adminUser, autoRequest: true);

        $this->assertSame(CouponStatus::REQUESTED, $coupon->status);
        $this->assertSame('Maria Influencer', $coupon->influencer_name);
        $this->assertNull($coupon->store_code);
        $this->assertSame('Fortaleza', $coupon->city);
        $this->assertNotNull($coupon->requested_at);
    }

    public function test_creates_consultor_coupon_with_store_and_employee(): void
    {
        $coupon = $this->service->create([
            'type' => CouponType::CONSULTOR->value,
            'store_code' => $this->regularStoreCode,
            'employee_id' => $this->regularEmployeeId,
            'cpf' => '222.333.444-55',
            'suggested_coupon' => 'JOANA25',
        ], $this->adminUser, autoRequest: false);

        $this->assertSame(CouponStatus::DRAFT, $coupon->status);
        $this->assertSame($this->regularStoreCode, $coupon->store_code);
        $this->assertSame($this->regularEmployeeId, $coupon->employee_id);
    }

    public function test_ms_indica_rejected_for_non_administrative_store(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create([
            'type' => CouponType::MS_INDICA->value,
            'store_code' => $this->regularStoreCode, // network_id 4 — comercial
            'employee_id' => $this->regularEmployeeId,
            'cpf' => '333.444.555-66',
        ], $this->adminUser, autoRequest: false);
    }

    public function test_ms_indica_allowed_for_administrative_store(): void
    {
        $coupon = $this->service->create([
            'type' => CouponType::MS_INDICA->value,
            'store_code' => $this->adminStoreCode, // network_id 6 — E-Commerce
            'employee_id' => $this->adminEmployeeId,
            'cpf' => '444.555.666-77',
        ], $this->adminUser, autoRequest: false);

        $this->assertSame(CouponType::MS_INDICA, $coupon->type);
        $this->assertSame('Z441', $coupon->store_code);
    }

    public function test_validates_required_fields_for_influencer(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Sem Campos',
            'cpf' => '111.222.333-44',
            // Faltam: city, social_media_id
        ], $this->adminUser, autoRequest: false);
    }

    public function test_validates_required_fields_for_consultor(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create([
            'type' => CouponType::CONSULTOR->value,
            'cpf' => '111.222.333-44',
            // Faltam: store_code, employee_id
        ], $this->adminUser, autoRequest: false);
    }

    public function test_blocks_duplicate_influencer_same_cpf(): void
    {
        $sm = SocialMedia::first();

        $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Primeira',
            'cpf' => '999.888.777-66',
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
        ], $this->adminUser, autoRequest: false);

        $this->expectException(ValidationException::class);

        $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Segunda',
            'cpf' => '99988877766', // mesmo CPF, outra máscara
            'city' => 'Recife',
            'social_media_id' => $sm->id,
        ], $this->adminUser, autoRequest: false);
    }

    public function test_allows_consultor_same_cpf_different_stores(): void
    {
        $c1 = $this->service->create([
            'type' => CouponType::CONSULTOR->value,
            'store_code' => $this->regularStoreCode,
            'employee_id' => $this->regularEmployeeId,
            'cpf' => '777.888.999-00',
        ], $this->adminUser, autoRequest: false);

        $c2 = $this->service->create([
            'type' => CouponType::CONSULTOR->value,
            'store_code' => $this->otherRegularStoreCode, // outra loja
            'employee_id' => $this->otherRegularEmployeeId,
            'cpf' => '777.888.999-00', // mesmo CPF
        ], $this->adminUser, autoRequest: false);

        $this->assertSame(2, Coupon::where('cpf_hash', Coupon::hashCpf('77788899900'))->count());
        $this->assertNotSame($c1->store_code, $c2->store_code);
    }

    public function test_blocks_consultor_same_cpf_same_store(): void
    {
        $this->service->create([
            'type' => CouponType::CONSULTOR->value,
            'store_code' => $this->regularStoreCode,
            'employee_id' => $this->regularEmployeeId,
            'cpf' => '111.111.111-11',
        ], $this->adminUser, autoRequest: false);

        $this->expectException(ValidationException::class);

        $this->service->create([
            'type' => CouponType::CONSULTOR->value,
            'store_code' => $this->regularStoreCode, // mesma loja
            'employee_id' => $this->regularEmployeeId,
            'cpf' => '111.111.111-11', // mesmo CPF
        ], $this->adminUser, autoRequest: false);
    }

    public function test_soft_delete_blocks_issued_coupon(): void
    {
        $sm = SocialMedia::first();

        $coupon = $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Bloquear Delete',
            'cpf' => '123.123.123-12',
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
        ], $this->adminUser, autoRequest: false);

        // Simula estado issued sem passar pelo transition (teste isolado de softDelete)
        $coupon->forceFill([
            'status' => CouponStatus::ISSUED->value,
            'coupon_site' => 'TESTE123',
        ])->save();

        $this->expectException(ValidationException::class);

        $this->service->softDelete($coupon->fresh(), $this->adminUser, 'Tentativa');
    }

    public function test_soft_delete_succeeds_for_draft_coupon(): void
    {
        $sm = SocialMedia::first();

        $coupon = $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Vai Excluir',
            'cpf' => '456.456.456-45',
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
        ], $this->adminUser, autoRequest: false);

        $deleted = $this->service->softDelete($coupon, $this->adminUser, 'Criado por engano');

        $this->assertNotNull($deleted->deleted_at);
        $this->assertSame($this->adminUser->id, $deleted->deleted_by_user_id);
        $this->assertSame('Criado por engano', $deleted->deleted_reason);
    }

    public function test_influencer_url_link_required_for_youtube_type_social(): void
    {
        // SocialMedia tipo URL — link @username deve falhar
        $sm = SocialMedia::create([
            'name' => 'YouTube Test',
            'link_type' => 'url',
            'link_placeholder' => 'https://youtube.com/@canal',
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Canal Teste',
            'cpf' => '888.777.666-55',
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
            'social_media_link' => '@canal', // @ inválido pra YouTube (tipo url)
        ], $this->adminUser, autoRequest: false);
    }

    public function test_influencer_username_accepted_for_instagram_type_social(): void
    {
        // SocialMedia tipo username — aceita @user
        $sm = SocialMedia::create([
            'name' => 'Instagram Test',
            'link_type' => 'username',
            'link_placeholder' => '@usuario',
            'is_active' => true,
            'sort_order' => 98,
        ]);

        $coupon = $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Maria IG',
            'cpf' => '777.666.555-44',
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
            'social_media_link' => '@maria_ig',
        ], $this->adminUser, autoRequest: false);

        $this->assertSame('@maria_ig', $coupon->social_media_link);
    }

    public function test_status_history_is_recorded_on_create(): void
    {
        $sm = SocialMedia::first();

        $coupon = $this->service->create([
            'type' => CouponType::INFLUENCER->value,
            'influencer_name' => 'Com Histórico',
            'cpf' => '888.888.888-88',
            'city' => 'Fortaleza',
            'social_media_id' => $sm->id,
        ], $this->adminUser, autoRequest: true);

        $history = $coupon->statusHistory()->orderBy('id')->get();

        // Duas entradas: criação (null→draft) + auto-request (draft→requested)
        $this->assertCount(2, $history);
        $this->assertNull($history[0]->from_status);
        $this->assertSame(CouponStatus::DRAFT, $history[0]->to_status);
        $this->assertSame(CouponStatus::DRAFT, $history[1]->from_status);
        $this->assertSame(CouponStatus::REQUESTED, $history[1]->to_status);
    }
}
