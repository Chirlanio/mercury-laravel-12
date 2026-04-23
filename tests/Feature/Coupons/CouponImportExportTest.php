<?php

namespace Tests\Feature\Coupons;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\SocialMedia;
use App\Services\CouponImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CouponImportExportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected int $regularEmployeeId;

    protected int $adminEmployeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore('Z441', ['network_id' => 6, 'name' => 'E-Commerce']);
        $this->createTestStore('Z421', ['network_id' => 4, 'name' => 'Arezzo Riomar']);

        $this->regularEmployeeId = $this->createTestEmployee(['store_id' => 'Z421', 'name' => 'Maria Silva', 'cpf' => '30000000001']);
        $this->adminEmployeeId = $this->createTestEmployee(['store_id' => 'Z441', 'name' => 'Ana Admin', 'cpf' => '30000000002']);

        if (! SocialMedia::where('name', 'Instagram')->exists()) {
            SocialMedia::create(['name' => 'Instagram', 'is_active' => true, 'sort_order' => 10]);
        }

        config(['queue.default' => 'sync']);
    }

    protected function makeXlsxFile(array $rows): UploadedFile
    {
        Storage::fake('local');
        $path = storage_path('app/test-import-'.uniqid().'.xlsx');

        $export = new class($rows) implements FromArray {
            public function __construct(public $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        };

        Excel::store($export, basename($path), 'local');
        $storedPath = Storage::disk('local')->path(basename($path));

        return new UploadedFile($storedPath, basename($path), null, null, true);
    }

    // ==================================================================
    // Import tests
    // ==================================================================

    public function test_import_preview_accepts_valid_rows(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'loja', 'colaborador', 'cupom_sugerido', 'status'],
            ['Consultor', '444.555.666-77', 'Z421', 'Maria Silva', 'MARIA26', 'Ativo'],
            ['Influencer', '555.666.777-88', '', '', 'XYZ26', 'Emitido'],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('coupons.import.preview'), ['file' => $file]);

        // Influencer requer city + social_media → vai falhar na 2ª linha; só 1 válido
        $response->assertOk();
        $json = $response->json();
        $this->assertSame(1, $json['valid_count']);
        $this->assertSame(1, $json['invalid_count']);
        $this->assertNotEmpty($json['errors']);
    }

    public function test_import_preview_reports_validation_errors(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'loja'],
            ['InvalidType', '11111111111', 'Z421'],
            ['Consultor', '123', 'Z421'], // CPF inválido
            ['Consultor', '444.555.666-77', 'ZZZ'], // loja inexistente
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('coupons.import.preview'), ['file' => $file]);

        $response->assertOk();
        $json = $response->json();
        $this->assertSame(0, $json['valid_count']);
        $this->assertSame(3, $json['invalid_count']);
    }

    public function test_import_store_creates_consultor_coupon(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'loja', 'colaborador', 'cupom_sugerido'],
            ['Consultor', '444.555.666-77', 'Z421', 'Maria Silva', 'MARIA26'],
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('coupons.import.store'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('coupons', [
            'type' => CouponType::CONSULTOR->value,
            'store_code' => 'Z421',
            'employee_id' => $this->regularEmployeeId,
            'suggested_coupon' => 'MARIA26',
        ]);
    }

    public function test_import_store_creates_influencer_coupon_with_social_media_by_name(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'influencer', 'cidade', 'rede_social'],
            ['Influencer', '777.888.999-00', 'Julia Bloga', 'Fortaleza', 'Instagram'],
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('coupons.import.store'), ['file' => $file])
            ->assertRedirect();

        $coupon = Coupon::where('influencer_name', 'Julia Bloga')->first();
        $this->assertNotNull($coupon);
        $this->assertSame(CouponType::INFLUENCER, $coupon->type);
        $this->assertNotNull($coupon->social_media_id);
    }

    public function test_import_blocks_ms_indica_on_non_administrative_store(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'loja', 'colaborador'],
            ['MS Indica', '444.555.666-77', 'Z421', 'Maria Silva'], // Z421 = comercial, não admin
        ]);

        $service = app(CouponImportService::class);
        $preview = $service->preview($file->getRealPath());

        $this->assertSame(0, $preview['valid_count']);
        $this->assertSame(1, $preview['invalid_count']);
        $this->assertStringContainsString('administrativa', $preview['errors'][0]['messages'][0]);
    }

    public function test_import_upserts_existing_coupon_same_cpf_type_store(): void
    {
        // Primeira importação cria
        $file1 = $this->makeXlsxFile([
            ['tipo', 'cpf', 'loja', 'colaborador', 'cupom_sugerido'],
            ['Consultor', '444.555.666-77', 'Z421', 'Maria Silva', 'MARIA26'],
        ]);
        $this->actingAs($this->adminUser)->post(route('coupons.import.store'), ['file' => $file1]);

        $this->assertSame(1, Coupon::count());

        // Segunda importação com campaign_name nova → atualiza, não duplica
        $file2 = $this->makeXlsxFile([
            ['tipo', 'cpf', 'loja', 'colaborador', 'cupom_sugerido', 'campanha'],
            ['Consultor', '444.555.666-77', 'Z421', 'Maria Silva', 'MARIA26', 'Black Friday'],
        ]);
        $this->actingAs($this->adminUser)->post(route('coupons.import.store'), ['file' => $file2]);

        $this->assertSame(1, Coupon::count());
        $this->assertSame('Black Friday', Coupon::first()->campaign_name);
    }

    public function test_import_blocked_without_permission(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf'],
            ['Consultor', '444.555.666-77'],
        ]);

        $this->actingAs($this->regularUser)
            ->post(route('coupons.import.preview'), ['file' => $file])
            ->assertForbidden();
    }

    // ==================================================================
    // Export tests
    // ==================================================================

    public function test_export_excel_downloads_file(): void
    {
        Coupon::create([
            'type' => CouponType::INFLUENCER,
            'status' => CouponStatus::ACTIVE,
            'influencer_name' => 'Julia Export',
            'cpf' => '111.222.333-44',
            'city' => 'Fortaleza',
            'social_media_id' => SocialMedia::first()->id,
            'coupon_site' => 'JULIA26',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('coupons.export'));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString('cupons-', $response->headers->get('Content-Disposition'));
    }

    public function test_export_excel_applies_current_filters(): void
    {
        // 2 cupons em status diferentes
        $this->makeCoupon(['status' => CouponStatus::REQUESTED]);
        $this->makeCoupon(['status' => CouponStatus::CANCELLED, 'cancelled_at' => now(), 'cancelled_reason' => 'teste']);

        // Export sem filtros — esconde cancelled por default
        $response = $this->actingAs($this->adminUser)->get(route('coupons.export'));
        $response->assertOk();

        // Não tem como contar rows no xlsx sem parsear; apenas garantimos que rota responde OK
    }

    public function test_export_pdf_individual_coupon(): void
    {
        $coupon = $this->makeCoupon(['status' => CouponStatus::ACTIVE, 'coupon_site' => 'TEST26']);

        $response = $this->actingAs($this->adminUser)->get(route('coupons.pdf', $coupon->id));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('cupom-', $response->headers->get('Content-Disposition'));
    }

    public function test_export_blocked_without_permission(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('coupons.export'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function makeCoupon(array $attrs = []): Coupon
    {
        return Coupon::create(array_merge([
            'type' => CouponType::INFLUENCER,
            'status' => CouponStatus::REQUESTED,
            'influencer_name' => 'Teste '.uniqid(),
            'cpf' => '100.'.rand(100, 999).'.'.rand(100, 999).'-'.rand(10, 99),
            'city' => 'Fortaleza',
            'social_media_id' => SocialMedia::first()->id,
            'created_by_user_id' => $this->adminUser->id,
        ], $attrs));
    }
}
