<?php

namespace Tests\Feature\DamagedProducts;

use App\Enums\DamagedProductStatus;
use App\Enums\FootSide;
use App\Models\DamagedProduct;
use App\Models\DamagedProductMatch;
use App\Models\DamageType;
use App\Models\Product;
use App\Services\DamagedProductMatchingService;
use App\Services\DamagedProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DamagedProductServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected DamagedProductService $service;
    protected int $storeAId;
    protected int $storeBId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->storeAId = $this->createTestStore('Z421');
        $this->storeBId = $this->createTestStore('Z422');

        $this->service = app(DamagedProductService::class);
    }

    // ==================================================================
    // Validação de regras de negócio
    // ==================================================================

    public function test_create_requires_at_least_one_problem_type(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-001',
            'is_mismatched' => false,
            'is_damaged' => false,
        ], $this->adminUser);
    }

    public function test_mismatched_requires_both_foot_sizes(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-001',
            'is_mismatched' => true,
            'mismatched_left_size' => '38',
            // sem mismatched_right_size
        ], $this->adminUser);
    }

    public function test_mismatched_left_must_differ_from_right(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-001',
            'is_mismatched' => true,
            'mismatched_left_size' => '38',
            'mismatched_right_size' => '38', // igual = inválido
        ], $this->adminUser);
    }

    public function test_damaged_requires_type_and_foot(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-001',
            'is_damaged' => true,
            // sem damage_type_id nem damaged_foot
        ], $this->adminUser);
    }

    public function test_creates_mismatched_with_required_fields(): void
    {
        $product = $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-001',
            'is_mismatched' => true,
            'mismatched_left_size' => '38',
            'mismatched_right_size' => '39',
        ], $this->adminUser);

        $this->assertNotNull($product->ulid);
        $this->assertSame(DamagedProductStatus::OPEN, $product->status);
        $this->assertTrue($product->is_mismatched);
        $this->assertSame('38', $product->mismatched_left_size);
        $this->assertSame('39', $product->mismatched_right_size);
        $this->assertSame($this->adminUser->id, $product->created_by_user_id);
    }

    public function test_damaged_requires_at_least_one_photo(): void
    {
        $type = DamageType::first();

        $this->expectException(ValidationException::class);

        // is_damaged=true sem fotos deve falhar
        $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-PHOTO-001',
            'is_damaged' => true,
            'damage_type_id' => $type->id,
            'damaged_foot' => FootSide::BOTH->value,
            'damaged_size' => '38',
        ], $this->adminUser, photos: null);
    }

    public function test_creates_damaged_with_required_fields(): void
    {
        $type = DamageType::first();

        // Mock de UploadedFile pra satisfazer ensurePhotosForDamaged.
        // O Service.savePhotos só processa instâncias UploadedFile reais —
        // passar 1 placeholder não-UploadedFile é o suficiente pra passar
        // pelo guard sem disparar upload.
        $product = $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-001',
            'is_damaged' => true,
            'damage_type_id' => $type->id,
            'damaged_foot' => FootSide::BOTH->value,
            'damaged_size' => '38',
            'damage_description' => 'Teste',
        ], $this->adminUser, photos: ['placeholder-non-uploadable']);

        $this->assertTrue($product->is_damaged);
        $this->assertSame($type->id, $product->damage_type_id);
        $this->assertSame(FootSide::BOTH, $product->damaged_foot);
    }

    public function test_normalizes_reference_to_uppercase(): void
    {
        $product = $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'ref-001',
            'is_mismatched' => true,
            'mismatched_left_size' => '38',
            'mismatched_right_size' => '39',
        ], $this->adminUser);

        $this->assertSame('REF-001', $product->product_reference);
    }

    // ==================================================================
    // Dedup
    // ==================================================================

    public function test_blocks_duplicate_in_open_state(): void
    {
        $payload = [
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-001',
            'is_mismatched' => true,
            'mismatched_left_size' => '38',
            'mismatched_right_size' => '39',
        ];

        $this->service->create($payload, $this->adminUser);

        $this->expectException(ValidationException::class);
        $this->service->create($payload, $this->adminUser);
    }

    public function test_allows_duplicate_after_cancellation(): void
    {
        $payload = [
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-001',
            'is_mismatched' => true,
            'mismatched_left_size' => '38',
            'mismatched_right_size' => '39',
        ];

        $first = $this->service->create($payload, $this->adminUser);
        $first->update(['status' => DamagedProductStatus::CANCELLED->value]);

        // Pode recriar sem erro
        $second = $this->service->create($payload, $this->adminUser);
        $this->assertNotEquals($first->id, $second->id);
    }

    public function test_allows_duplicate_in_different_stores(): void
    {
        $payload = [
            'product_reference' => 'REF-001',
            'is_mismatched' => true,
            'mismatched_left_size' => '38',
            'mismatched_right_size' => '39',
        ];

        $a = $this->service->create($payload + ['store_id' => $this->storeAId], $this->adminUser);
        $b = $this->service->create($payload + ['store_id' => $this->storeBId], $this->adminUser);

        $this->assertNotEquals($a->id, $b->id);
    }

    // ==================================================================
    // Auto-fill do catálogo
    // ==================================================================

    public function test_auto_fills_brand_color_from_catalog_when_reference_matches(): void
    {
        // Cria os lookups de marca e cor (Product.brand/color resolvem por cigam_code)
        \App\Models\ProductBrand::create(['cigam_code' => 'AREZZO', 'name' => 'Arezzo']);
        \App\Models\ProductColor::create(['cigam_code' => 'PRETO', 'name' => 'Preto']);

        $catalog = Product::create([
            'reference' => 'REF-CATALOG',
            'description' => 'Sandália Catalog',
            'brand_cigam_code' => 'AREZZO',
            'color_cigam_code' => 'PRETO',
            'is_active' => true,
        ]);

        $product = $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-CATALOG',
            'is_mismatched' => true,
            'mismatched_left_size' => '38',
            'mismatched_right_size' => '39',
        ], $this->adminUser);

        $this->assertSame($catalog->id, $product->product_id);
        $this->assertSame('Sandália Catalog', $product->product_name);
        $this->assertSame('AREZZO', $product->brand_cigam_code);
        $this->assertSame('Arezzo', $product->brand_name);    // snapshot do nome
        $this->assertSame('Preto', $product->product_color);  // armazena NOME, não cigam_code
    }

    public function test_user_provided_values_override_catalog_autofill(): void
    {
        Product::create([
            'reference' => 'REF-CATALOG',
            'description' => 'Catalog Name',
            'brand_cigam_code' => 'AREZZO',
            'is_active' => true,
        ]);

        $product = $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-CATALOG',
            'product_name' => 'Custom Name',
            'brand_cigam_code' => 'CUSTOM',
            'is_mismatched' => true,
            'mismatched_left_size' => '38',
            'mismatched_right_size' => '39',
        ], $this->adminUser);

        $this->assertSame('Custom Name', $product->product_name);
        $this->assertSame('CUSTOM', $product->brand_cigam_code);
    }

    // ==================================================================
    // Update
    // ==================================================================

    public function test_blocks_update_on_final_status(): void
    {
        $product = $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-001',
            'is_mismatched' => true,
            'mismatched_left_size' => '38',
            'mismatched_right_size' => '39',
        ], $this->adminUser);

        $product->update(['status' => DamagedProductStatus::RESOLVED->value]);

        $this->expectException(ValidationException::class);
        $this->service->update($product->fresh(), ['notes' => 'Mudança'], $this->adminUser);
    }

    public function test_updates_in_open_status_succeeds(): void
    {
        $product = $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-001',
            'is_mismatched' => true,
            'mismatched_left_size' => '38',
            'mismatched_right_size' => '39',
        ], $this->adminUser);

        $updated = $this->service->update($product, [
            'notes' => 'Notas atualizadas',
        ], $this->adminUser);

        $this->assertSame('Notas atualizadas', $updated->notes);
        $this->assertSame($this->adminUser->id, $updated->updated_by_user_id);
    }

    // ==================================================================
    // expirePendingMatches
    // ==================================================================

    public function test_expirePendingMatches_marks_pending_as_expired(): void
    {
        $matching = app(DamagedProductMatchingService::class);

        $a = $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'REF-001',
            'is_mismatched' => true,
            'mismatched_left_size' => '38',
            'mismatched_right_size' => '39',
        ], $this->adminUser);

        $this->service->create([
            'store_id' => $this->storeBId,
            'product_reference' => 'REF-001',
            'is_mismatched' => true,
            'mismatched_left_size' => '39',  // espelho pra cruzar
            'mismatched_right_size' => '38',
        ], $this->adminUser);

        $matching->findMatchesFor($a);
        $this->assertEquals(1, DamagedProductMatch::where('status', 'pending')->count());

        $expired = $this->service->expirePendingMatches($a);

        $this->assertEquals(1, $expired);
        $this->assertEquals(1, DamagedProductMatch::where('status', 'expired')->count());
        $this->assertEquals(0, DamagedProductMatch::where('status', 'pending')->count());
    }
}
