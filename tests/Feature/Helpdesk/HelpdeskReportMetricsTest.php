<?php

namespace Tests\Feature\Helpdesk;

use App\Models\HdAiClassificationCorrection;
use App\Models\HdArticle;
use App\Models\HdArticleView;
use App\Models\HdCategory;
use App\Models\HdDepartment;
use App\Models\HdTicket;
use App\Services\HelpdeskReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Coverage for the two report service methods added alongside the
 * helpdesk dashboard panels:
 *
 *   - HelpdeskReportService::deflectionStats — aggregates
 *     hd_article_views by action/source and top articles.
 *   - HelpdeskReportService::aiAccuracy — mirrors the CLI report but
 *     returns a structured array for the UI.
 *
 * Both methods are pure over read-only inputs, so the tests set up
 * minimal fixtures and assert the shape/content directly.
 */
class HelpdeskReportMetricsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $department;
    protected HdCategory $catHardware;
    protected HdCategory $catSoftware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        HdCategory::query()->delete();
        HdDepartment::query()->delete();

        $base = $this->createHelpdeskBaseData();
        $this->department = $base['department'];
        $this->catHardware = $base['categories'][0];
        $this->catSoftware = $base['categories'][1];
    }

    // ------------------------------------------------------------------
    // Deflection stats
    // ------------------------------------------------------------------

    public function test_deflection_stats_returns_empty_structure_when_no_views(): void
    {
        $stats = app(HelpdeskReportService::class)->deflectionStats([]);

        $this->assertSame(0, $stats['total_views']);
        $this->assertSame(0, $stats['total_deflected']);
        $this->assertSame(0.0, $stats['deflection_rate']);
        $this->assertSame([], $stats['by_source']);
        $this->assertSame([], $stats['top_articles']);
    }

    public function test_deflection_stats_computes_rate_and_breakdown(): void
    {
        $article1 = $this->seedArticle('Reset de senha');
        $article2 = $this->seedArticle('Configurar e-mail');

        // article1: 3 views + 2 deflected from intake_form
        $this->logViews($article1->id, 'intake_form', views: 3, deflected: 2);
        // article2: 4 views + 1 deflected from intake_whatsapp
        $this->logViews($article2->id, 'intake_whatsapp', views: 4, deflected: 1);
        // article1 also gets 1 direct_link view (no deflection)
        $this->logViews($article1->id, 'direct_link', views: 1, deflected: 0);

        $stats = app(HelpdeskReportService::class)->deflectionStats([]);

        // Totals — helper's `views` arg is the total row count
        // (viewed + deflected), so 3 + 4 + 1 = 8 rows inserted.
        $this->assertSame(8, $stats['total_views']);
        $this->assertSame(3, $stats['total_deflected']);
        $this->assertEqualsWithDelta(37.5, $stats['deflection_rate'], 0.05);

        // by_source sorted by deflected desc:
        // intake_form: 3 rows (1 viewed + 2 deflected)
        // intake_whatsapp: 4 rows (3 viewed + 1 deflected)
        // direct_link: 1 row (1 viewed + 0 deflected)
        $this->assertCount(3, $stats['by_source']);
        $this->assertSame('intake_form', $stats['by_source'][0]['source']);
        $this->assertSame(2, $stats['by_source'][0]['deflected']);
        $this->assertSame(3, $stats['by_source'][0]['views']);

        // Top articles: article1 deflected the most (2×)
        $this->assertSame($article1->id, $stats['top_articles'][0]['article_id']);
        $this->assertSame(2, $stats['top_articles'][0]['deflected']);
        $this->assertSame('Reset de senha', $stats['top_articles'][0]['title']);
    }

    public function test_deflection_stats_respects_date_filter(): void
    {
        $article = $this->seedArticle('Antigo');

        // 2 events last month, 1 event today
        HdArticleView::create([
            'article_id' => $article->id,
            'source' => 'intake_form',
            'action' => 'deflected',
            'created_at' => now()->subMonth(),
        ]);
        HdArticleView::create([
            'article_id' => $article->id,
            'source' => 'intake_form',
            'action' => 'viewed',
            'created_at' => now()->subMonth(),
        ]);
        HdArticleView::create([
            'article_id' => $article->id,
            'source' => 'intake_form',
            'action' => 'deflected',
            'created_at' => now(),
        ]);

        $stats = app(HelpdeskReportService::class)->deflectionStats([
            'date_from' => now()->subDay()->toDateString(),
        ]);

        $this->assertSame(1, $stats['total_views']);
        $this->assertSame(1, $stats['total_deflected']);
        $this->assertSame(100.0, $stats['deflection_rate']);
    }

    public function test_deflection_stats_respects_department_filter(): void
    {
        $otherDept = HdDepartment::factory()->create(['name' => 'RH']);
        $myArticle = $this->seedArticle('Meu depto');
        $otherArticle = HdArticle::create([
            'title' => 'Outro depto',
            'slug' => 'outro-depto',
            'content_md' => 'conteúdo',
            'department_id' => $otherDept->id,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->logViews($myArticle->id, 'intake_form', views: 2, deflected: 2);
        $this->logViews($otherArticle->id, 'intake_form', views: 5, deflected: 5);

        $stats = app(HelpdeskReportService::class)->deflectionStats([
            'department_id' => $this->department->id,
        ]);

        $this->assertSame(2, $stats['total_views']);
        $this->assertSame(2, $stats['total_deflected']);
    }

    // ------------------------------------------------------------------
    // AI accuracy
    // ------------------------------------------------------------------

    public function test_ai_accuracy_returns_empty_when_no_classified_tickets(): void
    {
        HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'category_id' => $this->catHardware->id,
            'ai_category_id' => null,
            'ai_classified_at' => null,
        ]);

        $report = app(HelpdeskReportService::class)->aiAccuracy([]);

        $this->assertSame(0, $report['total']);
        $this->assertSame(0.0, $report['category_accuracy']);
        $this->assertSame([], $report['by_department']);
    }

    public function test_ai_accuracy_distinguishes_kept_from_corrected(): void
    {
        // 3 kept (category_id == ai_category_id) + 2 corrected
        $this->seedAiTicket(kept: true, confidence: 0.9, aiPriority: 2);
        $this->seedAiTicket(kept: true, confidence: 0.8, aiPriority: 2);
        $this->seedAiTicket(kept: true, confidence: 0.95, aiPriority: 2);
        $this->seedAiTicket(kept: false, confidence: 0.5, aiPriority: 3);
        $this->seedAiTicket(kept: false, confidence: 0.6, aiPriority: 3);

        $report = app(HelpdeskReportService::class)->aiAccuracy([]);

        $this->assertSame(5, $report['total']);
        $this->assertSame(3, $report['category_kept']);
        $this->assertSame(2, $report['category_corrected']);
        $this->assertSame(60.0, $report['category_accuracy']);
        $this->assertEqualsWithDelta(0.75, $report['avg_confidence'], 0.01);
        $this->assertSame(5, $report['priority_relevant']);
    }

    public function test_ai_accuracy_breaks_down_by_department(): void
    {
        $otherDept = HdDepartment::factory()->create(['name' => 'Financeiro']);
        $otherCat = HdCategory::factory()->forDepartment($otherDept)->create(['name' => 'Pagamentos']);

        // Department A: 2 kept, 1 corrected (67%)
        $this->seedAiTicket(kept: true);
        $this->seedAiTicket(kept: true);
        $this->seedAiTicket(kept: false);

        // Department B: 1 kept, 0 corrected (100%)
        HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $otherDept->id,
            'category_id' => $otherCat->id,
            'ai_category_id' => $otherCat->id,
            'ai_classified_at' => now(),
            'ai_confidence' => 0.9,
        ]);

        $report = app(HelpdeskReportService::class)->aiAccuracy([]);

        $this->assertCount(2, $report['by_department']);
        // Sorted by volume desc: dept A (3 tickets) first
        $this->assertSame($this->department->id, $report['by_department'][0]['department_id']);
        $this->assertSame(3, $report['by_department'][0]['total']);
        $this->assertEqualsWithDelta(66.7, $report['by_department'][0]['accuracy'], 0.1);
        $this->assertSame($otherDept->id, $report['by_department'][1]['department_id']);
        $this->assertSame(100.0, $report['by_department'][1]['accuracy']);
    }

    public function test_ai_accuracy_top_corrected_uses_corrections_log(): void
    {
        // Create some tickets (one corrected, one kept) so the parent
        // query has something to report on.
        $corrected = $this->seedAiTicket(kept: false);
        $this->seedAiTicket(kept: true);

        // hardware was suggested but corrected to software, 3 times.
        for ($i = 0; $i < 3; $i++) {
            HdAiClassificationCorrection::create([
                'ticket_id' => $corrected->id,
                'original_ai_category_id' => $this->catHardware->id,
                'corrected_category_id' => $this->catSoftware->id,
                'created_at' => now(),
            ]);
        }

        // One correction where the corrected_category_id matches the AI
        // suggestion — should be ignored (priority-only correction).
        HdAiClassificationCorrection::create([
            'ticket_id' => $corrected->id,
            'original_ai_category_id' => $this->catHardware->id,
            'corrected_category_id' => $this->catHardware->id,
            'original_ai_priority' => 2,
            'corrected_priority' => 4,
            'created_at' => now(),
        ]);

        $report = app(HelpdeskReportService::class)->aiAccuracy([]);

        $this->assertCount(1, $report['top_corrected']);
        $this->assertSame($this->catHardware->id, $report['top_corrected'][0]['category_id']);
        $this->assertSame('Hardware', $report['top_corrected'][0]['category_name']);
        $this->assertSame(3, $report['top_corrected'][0]['times']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function seedArticle(string $title): HdArticle
    {
        return HdArticle::create([
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(),
            'content_md' => $title.' conteúdo.',
            'department_id' => $this->department->id,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    protected function logViews(int $articleId, string $source, int $views, int $deflected): void
    {
        // $views is the total (viewed + deflected), $deflected is the subset.
        $viewedOnly = $views - $deflected;
        for ($i = 0; $i < $viewedOnly; $i++) {
            HdArticleView::create([
                'article_id' => $articleId,
                'source' => $source,
                'action' => 'viewed',
                'created_at' => now(),
            ]);
        }
        for ($i = 0; $i < $deflected; $i++) {
            HdArticleView::create([
                'article_id' => $articleId,
                'source' => $source,
                'action' => 'deflected',
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Create a ticket with an AI suggestion. When $kept is true, the
     * human kept the AI's category. When false, the human changed it
     * from hardware to software (so ai_category_id != category_id).
     */
    protected function seedAiTicket(bool $kept, ?float $confidence = 0.8, ?int $aiPriority = 2): HdTicket
    {
        $aiCategoryId = $this->catHardware->id;
        $finalCategoryId = $kept ? $this->catHardware->id : $this->catSoftware->id;

        return HdTicket::factory()->create([
            'requester_id' => $this->regularUser->id,
            'department_id' => $this->department->id,
            'category_id' => $finalCategoryId,
            'ai_category_id' => $aiCategoryId,
            'ai_priority' => $aiPriority,
            'priority' => $aiPriority, // priority kept in these fixtures
            'ai_confidence' => $confidence,
            'ai_model' => 'llama-3.3-70b-test',
            'ai_classified_at' => now(),
        ]);
    }
}
