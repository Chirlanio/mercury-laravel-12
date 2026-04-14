<?php

namespace App\Services;

use App\Models\HdAiClassificationCorrection;
use App\Models\HdArticle;
use App\Models\HdArticleView;
use App\Models\HdCategory;
use App\Models\HdDepartment;
use App\Models\HdSatisfactionSurvey;
use App\Models\HdTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HelpdeskReportService
{
    /**
     * Volume of tickets by day.
     */
    public function volumeByDay(array $filters = []): array
    {
        if (! Schema::hasTable('hd_tickets')) {
            return [];
        }

        $query = HdTicket::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date');

        if (! empty($filters['department_id'])) {
            $query->forDepartment((int) $filters['department_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->get()->map(fn ($row) => [
            'date' => $row->date,
            'count' => $row->count,
        ])->toArray();
    }

    /**
     * SLA compliance percentage.
     */
    public function slaCompliance(array $filters = []): array
    {
        if (! Schema::hasTable('hd_tickets')) {
            return ['total' => 0, 'within_sla' => 0, 'breached' => 0, 'compliance_rate' => 0];
        }

        $query = HdTicket::whereNotNull('sla_due_at')
            ->whereIn('status', [HdTicket::STATUS_RESOLVED, HdTicket::STATUS_CLOSED]);

        if (! empty($filters['department_id'])) {
            $query->forDepartment((int) $filters['department_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $total = (clone $query)->count();
        $withinSla = (clone $query)->whereColumn('resolved_at', '<=', 'sla_due_at')->count();
        $breached = $total - $withinSla;

        return [
            'total' => $total,
            'within_sla' => $withinSla,
            'breached' => $breached,
            'compliance_rate' => $total > 0 ? round(($withinSla / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Distribution by department.
     */
    public function distributionByDepartment(array $filters = []): array
    {
        if (! Schema::hasTable('hd_tickets')) {
            return [];
        }

        $query = HdTicket::select('department_id', DB::raw('COUNT(*) as count'))
            ->with('department:id,name')
            ->groupBy('department_id');

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->get()->map(fn ($row) => [
            'department' => $row->department?->name ?? 'N/A',
            'count' => $row->count,
        ])->toArray();
    }

    /**
     * Average resolution time in hours.
     */
    public function averageResolutionTime(array $filters = []): float
    {
        if (! Schema::hasTable('hd_tickets')) {
            return 0;
        }

        $query = HdTicket::whereNotNull('resolved_at');

        if (! empty($filters['department_id'])) {
            $query->forDepartment((int) $filters['department_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Portable across MySQL/MariaDB/SQLite/Postgres: compute in PHP.
        // Reports are aggregate views — dataset is small, no hot path here.
        $tickets = $query->get(['created_at', 'resolved_at']);
        if ($tickets->isEmpty()) {
            return 0;
        }

        $totalHours = $tickets->sum(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at) / 60);

        return round($totalHours / $tickets->count(), 1);
    }

    // ------------------------------------------------------------------
    // CSAT — satisfaction surveys
    // ------------------------------------------------------------------

    /**
     * Overall CSAT: average rating across all submitted surveys within
     * the filter window plus a breakdown by star count so the UI can
     * render a distribution bar chart.
     *
     * Returns:
     *   [
     *     'total_submitted' => int,   // surveys with a rating
     *     'total_sent'      => int,   // surveys that exist at all in window
     *     'response_rate'   => float, // percentage
     *     'average'         => float, // 0..5 (0 if nothing submitted)
     *     'distribution'    => [1=>n, 2=>n, 3=>n, 4=>n, 5=>n],
     *     'nps_like'        => [
     *         'promoters'  => int,  // 5 stars
     *         'passives'   => int,  // 4 stars
     *         'detractors' => int,  // 1-3 stars
     *     ],
     *   ]
     */
    public function csatOverview(array $filters = []): array
    {
        if (! Schema::hasTable('hd_satisfaction_surveys')) {
            return $this->emptyCsatOverview();
        }

        $query = $this->baseCsatQuery($filters);

        $totalSent = (clone $query)->count();
        $submitted = (clone $query)->whereNotNull('submitted_at')->get(['rating']);
        $totalSubmitted = $submitted->count();

        if ($totalSubmitted === 0) {
            return array_merge($this->emptyCsatOverview(), ['total_sent' => $totalSent]);
        }

        $sum = $submitted->sum('rating');
        $average = round($sum / $totalSubmitted, 2);

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($submitted as $s) {
            $distribution[$s->rating] = ($distribution[$s->rating] ?? 0) + 1;
        }

        return [
            'total_submitted' => $totalSubmitted,
            'total_sent' => $totalSent,
            'response_rate' => $totalSent > 0 ? round(($totalSubmitted / $totalSent) * 100, 1) : 0.0,
            'average' => $average,
            'distribution' => $distribution,
            'nps_like' => [
                'promoters' => $distribution[5] ?? 0,
                'passives' => $distribution[4] ?? 0,
                'detractors' => ($distribution[1] ?? 0) + ($distribution[2] ?? 0) + ($distribution[3] ?? 0),
            ],
        ];
    }

    /**
     * Top technicians by average CSAT. Only includes technicians with
     * at least $minResponses submitted surveys so one lucky 5-star
     * doesn't dominate the ranking.
     *
     * @return array<int, array{user_id:int, user_name:?string, average:float, total:int}>
     */
    public function csatByTechnician(array $filters = [], int $minResponses = 1, int $limit = 10): array
    {
        if (! Schema::hasTable('hd_satisfaction_surveys')) {
            return [];
        }

        $query = $this->baseCsatQuery($filters)
            ->whereNotNull('submitted_at')
            ->whereNotNull('resolved_by_user_id')
            ->with('resolvedBy:id,name');

        $grouped = $query->get(['resolved_by_user_id', 'rating'])
            ->groupBy('resolved_by_user_id');

        $rows = [];
        foreach ($grouped as $userId => $surveys) {
            $total = $surveys->count();
            if ($total < $minResponses) {
                continue;
            }
            $avg = round($surveys->sum('rating') / $total, 2);
            $rows[] = [
                'user_id' => (int) $userId,
                'user_name' => $surveys->first()->resolvedBy?->name,
                'average' => $avg,
                'total' => $total,
            ];
        }

        usort($rows, fn ($a, $b) => [$b['average'], $b['total']] <=> [$a['average'], $a['total']]);

        return array_slice($rows, 0, $limit);
    }

    /**
     * Average CSAT per department.
     *
     * @return array<int, array{department_id:int, department_name:string, average:float, total:int}>
     */
    public function csatByDepartment(array $filters = []): array
    {
        if (! Schema::hasTable('hd_satisfaction_surveys')) {
            return [];
        }

        $surveys = $this->baseCsatQuery($filters)
            ->whereNotNull('submitted_at')
            ->whereNotNull('department_id')
            ->with('department:id,name')
            ->get(['department_id', 'rating']);

        $rows = [];
        foreach ($surveys->groupBy('department_id') as $deptId => $deptSurveys) {
            $total = $deptSurveys->count();
            $rows[] = [
                'department_id' => (int) $deptId,
                'department_name' => $deptSurveys->first()->department?->name ?? 'N/A',
                'average' => round($deptSurveys->sum('rating') / $total, 2),
                'total' => $total,
            ];
        }

        usort($rows, fn ($a, $b) => $b['average'] <=> $a['average']);

        return $rows;
    }

    /**
     * Build the base query that applies the shared date/department filters
     * to all CSAT reports. Uses sent_at as the anchor (the time the survey
     * was created, which is right after the ticket was resolved).
     */
    protected function baseCsatQuery(array $filters)
    {
        $query = HdSatisfactionSurvey::query();

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    protected function emptyCsatOverview(): array
    {
        return [
            'total_submitted' => 0,
            'total_sent' => 0,
            'response_rate' => 0.0,
            'average' => 0.0,
            'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'nps_like' => ['promoters' => 0, 'passives' => 0, 'detractors' => 0],
        ];
    }

    // ------------------------------------------------------------------
    // Knowledge Base deflection
    // ------------------------------------------------------------------

    /**
     * Deflection analytics from `hd_article_views`. A "deflection" is a
     * log row with `action = 'deflected'` — the user declared the article
     * solved their problem and opted NOT to open a ticket. Views with
     * `action = 'viewed'` are the denominator: everyone who saw the
     * article in an intake context.
     *
     * Returns:
     *   [
     *     'total_views'      => int,   // every view within the window
     *     'total_deflected'  => int,   // subset with action='deflected'
     *     'deflection_rate'  => float, // percentage
     *     'by_source'        => [ ['source'=>..., 'views'=>int, 'deflected'=>int, 'rate'=>float], ...],
     *     'top_articles'     => [ ['article_id'=>int, 'title'=>string, 'deflected'=>int], ... top 10 ],
     *   ]
     */
    public function deflectionStats(array $filters = []): array
    {
        if (! Schema::hasTable('hd_article_views')) {
            return $this->emptyDeflection();
        }

        $base = HdArticleView::query();

        if (! empty($filters['date_from'])) {
            $base->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $base->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Department filter joins through hd_articles.department_id — the
        // view row itself has no department column.
        if (! empty($filters['department_id']) && Schema::hasTable('hd_articles')) {
            $base->whereIn('article_id', HdArticle::query()
                ->where('department_id', (int) $filters['department_id'])
                ->pluck('id'));
        }

        $rows = (clone $base)->get(['article_id', 'source', 'action']);

        $totalViews = $rows->count();
        if ($totalViews === 0) {
            return $this->emptyDeflection();
        }

        $totalDeflected = $rows->where('action', 'deflected')->count();
        $rate = $totalViews > 0 ? round(($totalDeflected / $totalViews) * 100, 1) : 0.0;

        $bySource = $rows->groupBy('source')->map(function ($group, $source) {
            $views = $group->count();
            $deflected = $group->where('action', 'deflected')->count();

            return [
                'source' => $source ?: 'direct_link',
                'views' => $views,
                'deflected' => $deflected,
                'rate' => $views > 0 ? round(($deflected / $views) * 100, 1) : 0.0,
            ];
        })->values()->sortByDesc('deflected')->values()->all();

        // Top articles by absolute deflection count. Only include articles
        // that actually deflected at least once — viewed-only rows would
        // clutter the ranking.
        $topArticleIds = $rows
            ->where('action', 'deflected')
            ->groupBy('article_id')
            ->map->count()
            ->sortDesc()
            ->take(10);

        $articleLookup = Schema::hasTable('hd_articles')
            ? HdArticle::whereIn('id', $topArticleIds->keys())->pluck('title', 'id')
            : collect();

        $topArticles = [];
        foreach ($topArticleIds as $articleId => $count) {
            $topArticles[] = [
                'article_id' => (int) $articleId,
                'title' => $articleLookup[$articleId] ?? "#{$articleId}",
                'deflected' => (int) $count,
            ];
        }

        return [
            'total_views' => $totalViews,
            'total_deflected' => $totalDeflected,
            'deflection_rate' => $rate,
            'by_source' => $bySource,
            'top_articles' => $topArticles,
        ];
    }

    protected function emptyDeflection(): array
    {
        return [
            'total_views' => 0,
            'total_deflected' => 0,
            'deflection_rate' => 0.0,
            'by_source' => [],
            'top_articles' => [],
        ];
    }

    // ------------------------------------------------------------------
    // AI classification accuracy
    // ------------------------------------------------------------------

    /**
     * AI classification accuracy report. Mirrors HelpdeskAiAccuracyReportCommand
     * but exposed as a structured array for the dashboard UI.
     *
     * Measures tickets that had an AI suggestion (ai_category_id and
     * ai_classified_at populated). A ticket where the human kept the AI's
     * suggested category counts as "kept"; one that differs is "corrected".
     * Priority is reported separately over tickets with ai_priority set.
     *
     * Returns:
     *   [
     *     'total'             => int,   // tickets with an AI suggestion
     *     'category_kept'     => int,
     *     'category_corrected'=> int,
     *     'category_accuracy' => float, // percentage
     *     'priority_relevant' => int,   // tickets with ai_priority set
     *     'priority_accuracy' => float,
     *     'avg_confidence'    => float, // 0..1
     *     'by_department'     => [ ['department_id'=>int, 'department_name'=>string, 'total'=>int, 'kept'=>int, 'corrected'=>int, 'accuracy'=>float], ... ],
     *     'top_corrected'     => [ ['category_id'=>int, 'category_name'=>string, 'times'=>int], ... top 5 ],
     *   ]
     */
    public function aiAccuracy(array $filters = []): array
    {
        if (! Schema::hasTable('hd_tickets')) {
            return $this->emptyAiAccuracy();
        }

        $query = HdTicket::query()
            ->whereNotNull('ai_category_id')
            ->whereNotNull('ai_classified_at');

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $tickets = $query->get([
            'id', 'department_id', 'category_id', 'ai_category_id',
            'priority', 'ai_priority', 'ai_confidence',
        ]);

        $total = $tickets->count();
        if ($total === 0) {
            return $this->emptyAiAccuracy();
        }

        $categoryKept = $tickets->filter(fn ($t) => $t->category_id === $t->ai_category_id)->count();
        $categoryCorrected = $total - $categoryKept;
        $categoryAccuracy = round(($categoryKept / $total) * 100, 1);

        $priorityRelevant = $tickets->filter(fn ($t) => $t->ai_priority !== null)->count();
        $priorityKept = $tickets->filter(fn ($t) => $t->ai_priority !== null && $t->priority === $t->ai_priority)->count();
        $priorityAccuracy = $priorityRelevant > 0 ? round(($priorityKept / $priorityRelevant) * 100, 1) : 0.0;

        $avgConfidence = round((float) ($tickets->avg('ai_confidence') ?? 0), 2);

        // Per-department breakdown, sorted by volume desc.
        $deptNames = HdDepartment::whereIn('id', $tickets->pluck('department_id')->unique())
            ->pluck('name', 'id');

        $byDepartment = [];
        foreach ($tickets->groupBy('department_id') as $deptId => $deptTickets) {
            $deptTotal = $deptTickets->count();
            $deptKept = $deptTickets->filter(fn ($t) => $t->category_id === $t->ai_category_id)->count();
            $byDepartment[] = [
                'department_id' => (int) $deptId,
                'department_name' => $deptNames[$deptId] ?? "#{$deptId}",
                'total' => $deptTotal,
                'kept' => $deptKept,
                'corrected' => $deptTotal - $deptKept,
                'accuracy' => $deptTotal > 0 ? round(($deptKept / $deptTotal) * 100, 1) : 0.0,
            ];
        }
        usort($byDepartment, fn ($a, $b) => $b['total'] <=> $a['total']);

        // Top corrected categories — only those whose AI suggestion was
        // actually changed by a human. Derived from the corrections log
        // (hd_ai_classification_corrections) so direction is unambiguous.
        $topCorrected = [];
        if (Schema::hasTable('hd_ai_classification_corrections')) {
            $correctionsQuery = HdAiClassificationCorrection::query()
                ->whereNotNull('original_ai_category_id')
                ->whereColumn('corrected_category_id', '!=', 'original_ai_category_id');

            if (! empty($filters['date_from'])) {
                $correctionsQuery->whereDate('created_at', '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to'])) {
                $correctionsQuery->whereDate('created_at', '<=', $filters['date_to']);
            }

            $counts = $correctionsQuery
                ->get(['original_ai_category_id'])
                ->groupBy('original_ai_category_id')
                ->map->count()
                ->sortDesc()
                ->take(5);

            $catNames = HdCategory::whereIn('id', $counts->keys())->pluck('name', 'id');
            foreach ($counts as $catId => $count) {
                $topCorrected[] = [
                    'category_id' => (int) $catId,
                    'category_name' => $catNames[$catId] ?? "#{$catId}",
                    'times' => (int) $count,
                ];
            }
        }

        return [
            'total' => $total,
            'category_kept' => $categoryKept,
            'category_corrected' => $categoryCorrected,
            'category_accuracy' => $categoryAccuracy,
            'priority_relevant' => $priorityRelevant,
            'priority_accuracy' => $priorityAccuracy,
            'avg_confidence' => $avgConfidence,
            'by_department' => $byDepartment,
            'top_corrected' => $topCorrected,
        ];
    }

    protected function emptyAiAccuracy(): array
    {
        return [
            'total' => 0,
            'category_kept' => 0,
            'category_corrected' => 0,
            'category_accuracy' => 0.0,
            'priority_relevant' => 0,
            'priority_accuracy' => 0.0,
            'avg_confidence' => 0.0,
            'by_department' => [],
            'top_corrected' => [],
        ];
    }
}
