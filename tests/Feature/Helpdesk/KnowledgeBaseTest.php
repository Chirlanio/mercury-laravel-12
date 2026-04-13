<?php

namespace Tests\Feature\Helpdesk;

use App\Models\HdArticle;
use App\Models\HdArticleFeedback;
use App\Models\HdArticleView;
use App\Models\HdCategory;
use App\Models\HdDepartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class KnowledgeBaseTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $department;
    protected HdCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $base = $this->createHelpdeskBaseData();
        $this->department = $base['department'];
        $this->category = $base['categories'][0];
    }

    // ----------------------------------------------------------------
    // Access control on admin pages
    // ----------------------------------------------------------------

    public function test_regular_user_cannot_access_admin_index(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('helpdesk.articles.index'))
            ->assertForbidden();
    }

    public function test_support_can_access_admin_index(): void
    {
        $this->actingAs($this->supportUser)
            ->get(route('helpdesk.articles.index'))
            ->assertOk();
    }

    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------

    public function test_store_creates_article_with_rendered_html(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('helpdesk.articles.store'), [
                'title' => 'Como solicitar férias',
                'summary' => 'Passo a passo para pedir férias.',
                'content_md' => "# Passo a passo\n\n1. Acesse o portal\n2. Preencha o formulário\n\n**Importante:** planeje com antecedência.",
                'department_id' => $this->department->id,
                'is_published' => true,
            ])
            ->assertRedirect();

        $article = HdArticle::where('title', 'Como solicitar férias')->first();

        $this->assertNotNull($article);
        $this->assertSame('como-solicitar-ferias', $article->slug);
        $this->assertNotNull($article->content_html);
        $this->assertStringContainsString('<h1', $article->content_html);
        $this->assertStringContainsString('<strong>Importante', $article->content_html);
        $this->assertNotNull($article->published_at);
        $this->assertSame($this->adminUser->id, $article->author_id);
    }

    public function test_slug_is_unique_with_numeric_suffix(): void
    {
        HdArticle::create([
            'title' => 'Teste',
            'slug' => 'teste',
            'content_md' => 'conteúdo',
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('helpdesk.articles.store'), [
                'title' => 'Teste',
                'content_md' => 'outro conteúdo',
            ]);

        $second = HdArticle::orderByDesc('id')->first();
        $this->assertSame('teste-2', $second->slug);
    }

    public function test_update_rerenders_html_when_markdown_changes(): void
    {
        $article = HdArticle::create([
            'title' => 'Original',
            'slug' => 'original',
            'content_md' => '# Original',
        ]);

        $this->actingAs($this->adminUser)
            ->put(route('helpdesk.articles.update', $article->id), [
                'title' => 'Atualizado',
                'content_md' => '## Novo conteúdo',
            ]);

        $fresh = $article->fresh();
        $this->assertSame('Atualizado', $fresh->title);
        $this->assertStringContainsString('<h2', $fresh->content_html);
        $this->assertStringNotContainsString('<h1', $fresh->content_html);
    }

    public function test_destroy_soft_deletes_article(): void
    {
        $article = HdArticle::create([
            'title' => 'Descartável',
            'slug' => 'descartavel',
            'content_md' => 'teste',
        ]);

        $this->actingAs($this->adminUser)
            ->delete(route('helpdesk.articles.destroy', $article->id))
            ->assertRedirect();

        $this->assertSoftDeleted('hd_articles', ['id' => $article->id]);
    }

    // ----------------------------------------------------------------
    // Public view + view tracking
    // ----------------------------------------------------------------

    public function test_show_increments_view_count_for_published_article(): void
    {
        $article = HdArticle::create([
            'title' => 'Público',
            'slug' => 'publico',
            'content_md' => 'conteúdo',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($this->regularUser)
            ->get(route('helpdesk.articles.show', $article->slug))
            ->assertOk();

        $this->assertSame(1, $article->fresh()->view_count);
        $this->assertSame(1, HdArticleView::where('article_id', $article->id)->count());
    }

    public function test_view_dedup_within_10_minutes(): void
    {
        $article = HdArticle::create([
            'title' => 'Dedup',
            'slug' => 'dedup',
            'content_md' => 'x',
            'is_published' => true,
        ]);

        $this->actingAs($this->regularUser);
        $this->get(route('helpdesk.articles.show', $article->slug));
        $this->get(route('helpdesk.articles.show', $article->slug));

        // Second view within the 10 minute window is not counted.
        $this->assertSame(1, $article->fresh()->view_count);
    }

    public function test_unpublished_article_hidden_from_non_admins(): void
    {
        $article = HdArticle::create([
            'title' => 'Rascunho',
            'slug' => 'rascunho',
            'content_md' => 'x',
            'is_published' => false,
        ]);

        $this->actingAs($this->regularUser)
            ->get(route('helpdesk.articles.show', $article->slug))
            ->assertNotFound();
    }

    public function test_unpublished_article_visible_to_admin(): void
    {
        $article = HdArticle::create([
            'title' => 'Rascunho',
            'slug' => 'rascunho',
            'content_md' => 'x',
            'is_published' => false,
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('helpdesk.articles.show', $article->slug))
            ->assertOk();
    }

    // ----------------------------------------------------------------
    // Search
    // ----------------------------------------------------------------

    public function test_search_returns_matches(): void
    {
        HdArticle::create([
            'title' => 'Como pedir férias',
            'content_md' => 'Informações sobre como solicitar férias na empresa.',
            'slug' => 'ferias',
            'is_published' => true,
        ]);
        HdArticle::create([
            'title' => 'Reset de senha',
            'content_md' => 'Procedimento para resetar a senha.',
            'slug' => 'senha',
            'is_published' => true,
        ]);

        $response = $this->actingAs($this->regularUser)
            ->getJson(route('helpdesk.articles.search').'?q=férias');

        $response->assertOk();
        $results = $response->json('results');
        $this->assertNotEmpty($results);
        $this->assertSame('ferias', $results[0]['slug']);
    }

    public function test_search_excludes_unpublished(): void
    {
        HdArticle::create([
            'title' => 'Segredo',
            'content_md' => 'Informação confidencial sobre férias.',
            'slug' => 'segredo',
            'is_published' => false,
        ]);

        $response = $this->actingAs($this->regularUser)
            ->getJson(route('helpdesk.articles.search').'?q=férias');

        $this->assertEmpty($response->json('results'));
    }

    public function test_search_rejects_short_queries(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson(route('helpdesk.articles.search').'?q=fe');

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
    }

    // ----------------------------------------------------------------
    // Feedback
    // ----------------------------------------------------------------

    public function test_helpful_feedback_increments_counter(): void
    {
        $article = HdArticle::create([
            'title' => 'FB',
            'slug' => 'fb',
            'content_md' => 'x',
            'is_published' => true,
        ]);

        $this->actingAs($this->regularUser)
            ->post(route('helpdesk.articles.feedback', $article->id), ['helpful' => true])
            ->assertRedirect();

        $this->assertSame(1, $article->fresh()->helpful_count);
        $this->assertSame(0, $article->fresh()->not_helpful_count);
    }

    public function test_flipping_feedback_adjusts_counters(): void
    {
        $article = HdArticle::create([
            'title' => 'FB',
            'slug' => 'fb2',
            'content_md' => 'x',
            'is_published' => true,
        ]);

        $this->actingAs($this->regularUser);
        $this->post(route('helpdesk.articles.feedback', $article->id), ['helpful' => true]);
        $this->post(route('helpdesk.articles.feedback', $article->id), ['helpful' => false]);

        $fresh = $article->fresh();
        $this->assertSame(0, $fresh->helpful_count);
        $this->assertSame(1, $fresh->not_helpful_count);
        $this->assertSame(1, HdArticleFeedback::where('article_id', $article->id)->count());
    }

    // ----------------------------------------------------------------
    // Deflection
    // ----------------------------------------------------------------

    public function test_deflect_creates_view_event_with_deflected_action(): void
    {
        $article = HdArticle::create([
            'title' => 'X',
            'slug' => 'x',
            'content_md' => 'y',
            'is_published' => true,
        ]);

        $this->actingAs($this->regularUser)
            ->postJson(route('helpdesk.articles.deflect', $article->id))
            ->assertOk();

        $this->assertSame(1, HdArticleView::where('article_id', $article->id)
            ->where('action', 'deflected')
            ->count());
    }
}
