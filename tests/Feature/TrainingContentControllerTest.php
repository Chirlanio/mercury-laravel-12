<?php

namespace Tests\Feature;

use App\Models\TrainingContent;
use App\Models\TrainingContentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TrainingContentControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected TrainingContentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->category = TrainingContentCategory::first()
            ?? TrainingContentCategory::create([
                'name' => 'Teste',
                'icon' => 'AcademicCapIcon',
                'color' => 'primary',
                'is_active' => true,
            ]);
    }

    // ==========================================
    // Index
    // ==========================================

    public function test_admin_can_list_contents(): void
    {
        $this->createContent();

        $response = $this->actingAs($this->adminUser)->get(route('training-contents.index'));
        $response->assertStatus(200);
        $response->assertJsonStructure(['contents', 'typeOptions', 'typeCounts', 'categories']);
    }

    public function test_can_filter_by_type(): void
    {
        $this->createContent(['content_type' => 'video']);
        $this->createContent(['content_type' => 'text']);

        $response = $this->actingAs($this->adminUser)->get(route('training-contents.index', ['content_type' => 'video']));
        $response->assertStatus(200);
        $response->assertJsonPath('contents.data.0.content_type', 'video');
    }

    public function test_can_search_contents(): void
    {
        $this->createContent(['title' => 'Atendimento ao Cliente']);
        $this->createContent(['title' => 'Gestao de Estoque']);

        $response = $this->actingAs($this->adminUser)->get(route('training-contents.index', ['search' => 'Atendimento']));
        $response->assertStatus(200);
    }

    // ==========================================
    // Store
    // ==========================================

    public function test_admin_can_create_text_content(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('training-contents.store'), [
            'title' => 'Guia de Atendimento',
            'description' => 'Guia completo',
            'content_type' => 'text',
            'text_content' => '<h1>Passo 1</h1><p>Detalhes...</p>',
            'category_id' => $this->category->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('content.title', 'Guia de Atendimento');
        $this->assertDatabaseHas('training_contents', [
            'title' => 'Guia de Atendimento',
            'content_type' => 'text',
        ]);
    }

    public function test_admin_can_create_link_content(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('training-contents.store'), [
            'title' => 'Tutorial YouTube',
            'content_type' => 'link',
            'external_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_contents', [
            'title' => 'Tutorial YouTube',
            'content_type' => 'link',
        ]);
    }

    public function test_admin_can_create_document_content_with_upload(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->adminUser)->post(route('training-contents.store'), [
            'title' => 'Manual PDF',
            'content_type' => 'document',
            'file' => UploadedFile::fake()->create('manual.pdf', 1024, 'application/pdf'),
            'category_id' => $this->category->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_contents', [
            'title' => 'Manual PDF',
            'content_type' => 'document',
        ]);

        $content = TrainingContent::where('title', 'Manual PDF')->first();
        $this->assertNotNull($content->file_path);
        $this->assertEquals('manual.pdf', $content->file_name);
    }

    public function test_support_cannot_create_content(): void
    {
        $response = $this->actingAs($this->supportUser)->post(route('training-contents.store'), [
            'title' => 'Blocked',
            'content_type' => 'text',
            'text_content' => 'content',
        ]);

        $response->assertStatus(403);
    }

    public function test_validation_requires_title_and_type(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('training-contents.store'), []);

        $response->assertSessionHasErrors(['title', 'content_type']);
    }

    // ==========================================
    // Show
    // ==========================================

    public function test_can_view_content_detail(): void
    {
        $content = $this->createContent();

        $response = $this->actingAs($this->adminUser)->get(route('training-contents.show', $content));
        $response->assertStatus(200);
        $response->assertJsonStructure(['content' => ['id', 'title', 'content_type', 'type_label']]);
    }

    // ==========================================
    // Update
    // ==========================================

    public function test_admin_can_update_content(): void
    {
        $content = $this->createContent(['title' => 'Original']);

        $response = $this->actingAs($this->adminUser)->put(route('training-contents.update', $content), [
            'title' => 'Atualizado',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_contents', [
            'id' => $content->id,
            'title' => 'Atualizado',
        ]);
    }

    // ==========================================
    // Destroy (soft delete)
    // ==========================================

    public function test_admin_can_soft_delete_content(): void
    {
        $content = $this->createContent();

        $response = $this->actingAs($this->adminUser)->delete(route('training-contents.destroy', $content));

        $response->assertStatus(200);
        $this->assertNotNull($content->fresh()->deleted_at);
    }

    // ==========================================
    // Categories
    // ==========================================

    public function test_admin_can_create_category(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('training-content-categories.store'), [
            'name' => 'Nova Categoria',
            'icon' => 'StarIcon',
            'color' => 'orange',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_content_categories', ['name' => 'Nova Categoria']);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createContent(array $overrides = []): TrainingContent
    {
        return TrainingContent::create(array_merge([
            'title' => 'Conteudo Teste',
            'content_type' => 'text',
            'text_content' => '<p>Conteudo de teste</p>',
            'category_id' => $this->category->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }
}
