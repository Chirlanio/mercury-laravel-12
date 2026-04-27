<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProductImageBulkControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        Storage::fake('public');
    }

    /* ==================== PERMISSION ==================== */

    public function test_preview_requires_edit_products_permission(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson('/products/images/preview', ['filenames' => ['REF-001.jpg']])
            ->assertStatus(403);
    }

    public function test_upload_batch_requires_edit_products_permission(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson('/products/images/upload-batch', [
                'files' => [UploadedFile::fake()->image('REF-001.jpg', 100, 100)],
                'on_conflict' => 'skip',
            ])
            ->assertStatus(403);
    }

    /* ==================== PREVIEW ==================== */

    public function test_preview_classifies_matched_conflicts_not_found_invalid(): void
    {
        $this->createTestProduct(['reference' => 'REF-MATCHED']);
        $this->createTestProduct(['reference' => 'REF-CONFLICT', 'image' => 'products/old.jpg']);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/products/images/preview', [
                'filenames' => [
                    'REF-MATCHED.jpg',
                    'REF-CONFLICT.png',
                    'REF-NONEXISTENT.webp',
                    'document.pdf',
                ],
            ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertSame(1, $data['counts']['matched']);
        $this->assertSame(1, $data['counts']['conflicts']);
        $this->assertSame(1, $data['counts']['not_found']);
        $this->assertSame(1, $data['counts']['invalid']);

        $this->assertSame('REF-MATCHED.jpg', $data['matched'][0]['filename']);
        $this->assertSame('REF-CONFLICT.png', $data['conflicts'][0]['filename']);
        $this->assertTrue($data['conflicts'][0]['has_existing_image']);
    }

    public function test_preview_validates_input(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/products/images/preview', ['filenames' => []])
            ->assertStatus(422);

        $this->actingAs($this->adminUser)
            ->postJson('/products/images/preview', [])
            ->assertStatus(422);
    }

    public function test_preview_caps_at_1000_filenames(): void
    {
        $names = array_map(fn ($i) => "REF-{$i}.jpg", range(1, 1001));

        $this->actingAs($this->adminUser)
            ->postJson('/products/images/preview', ['filenames' => $names])
            ->assertStatus(422);
    }

    /* ==================== UPLOAD BATCH ==================== */

    public function test_upload_batch_uploads_image_to_product_without_existing(): void
    {
        $product = $this->createTestProduct(['reference' => 'REF-NEW']);

        $response = $this->actingAs($this->adminUser)
            ->post('/products/images/upload-batch', [
                'files' => [$this->makeImage('REF-NEW.jpg')],
                'on_conflict' => 'skip',
            ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertSame(1, $data['summary']['uploaded']);
        $this->assertSame(0, $data['summary']['replaced']);
        $this->assertSame('uploaded', $data['results'][0]['status']);
        $this->assertNotNull($data['results'][0]['image_url']);

        $product->refresh();
        $this->assertNotNull($product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_upload_batch_skips_when_product_has_image_and_on_conflict_skip(): void
    {
        $product = $this->createTestProduct([
            'reference' => 'REF-OLD',
            'image' => 'products/existing.jpg',
        ]);
        Storage::disk('public')->put('products/existing.jpg', 'fake');

        $response = $this->actingAs($this->adminUser)
            ->post('/products/images/upload-batch', [
                'files' => [$this->makeImage('REF-OLD.jpg')],
                'on_conflict' => 'skip',
            ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('summary.skipped'));
        $this->assertSame('skipped', $response->json('results.0.status'));

        $product->refresh();
        $this->assertSame('products/existing.jpg', $product->image);
        Storage::disk('public')->assertExists('products/existing.jpg');
    }

    public function test_upload_batch_replaces_existing_when_on_conflict_replace(): void
    {
        $product = $this->createTestProduct([
            'reference' => 'REF-REPL',
            'image' => 'products/existing.jpg',
        ]);
        Storage::disk('public')->put('products/existing.jpg', 'fake');

        $response = $this->actingAs($this->adminUser)
            ->post('/products/images/upload-batch', [
                'files' => [$this->makeImage('REF-REPL.jpg')],
                'on_conflict' => 'replace',
            ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('summary.replaced'));
        $this->assertSame('replaced', $response->json('results.0.status'));

        $product->refresh();
        $this->assertNotSame('products/existing.jpg', $product->image);
        Storage::disk('public')->assertMissing('products/existing.jpg');
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_upload_batch_returns_not_found_status_for_unknown_reference(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/products/images/upload-batch', [
                'files' => [$this->makeImage('REF-DOES-NOT-EXIST.jpg')],
                'on_conflict' => 'skip',
            ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('summary.not_found'));
        $this->assertSame('not_found', $response->json('results.0.status'));
    }

    public function test_upload_batch_validates_on_conflict_value(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/products/images/upload-batch', [
                'files' => [UploadedFile::fake()->image('REF-X.jpg')],
                'on_conflict' => 'invalid_value',
            ])
            ->assertStatus(422);
    }

    public function test_upload_batch_caps_at_20_files(): void
    {
        $files = array_map(
            fn ($i) => UploadedFile::fake()->image("REF-{$i}.jpg"),
            range(1, 21),
        );

        $this->actingAs($this->adminUser)
            ->postJson('/products/images/upload-batch', [
                'files' => $files,
                'on_conflict' => 'skip',
            ])
            ->assertStatus(422);
    }

    public function test_upload_batch_processes_mixed_results(): void
    {
        $this->createTestProduct(['reference' => 'REF-A']);
        $this->createTestProduct(['reference' => 'REF-B', 'image' => 'products/b.jpg']);
        Storage::disk('public')->put('products/b.jpg', 'fake');

        $response = $this->actingAs($this->adminUser)
            ->post('/products/images/upload-batch', [
                'files' => [
                    $this->makeImage('REF-A.jpg'),
                    $this->makeImage('REF-B.jpg'),
                    $this->makeImage('REF-MISSING.jpg'),
                ],
                'on_conflict' => 'skip',
            ]);

        $response->assertStatus(200);
        $summary = $response->json('summary');
        $this->assertSame(1, $summary['uploaded']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(1, $summary['not_found']);
        $this->assertSame(3, $summary['total']);
    }

    /**
     * Cria um arquivo de imagem real (não fake) que passa por getimagesize().
     * UploadedFile::fake()->image() já gera um PNG válido com dimensões reais.
     */
    private function makeImage(string $name, int $width = 100, int $height = 100): File
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }
}
