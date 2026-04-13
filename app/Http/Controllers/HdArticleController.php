<?php

namespace App\Http\Controllers;

use App\Models\HdArticle;
use App\Models\HdArticleFeedback;
use App\Models\HdArticleView;
use App\Models\HdCategory;
use App\Models\HdDepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Knowledge Base admin + public view.
 *
 * The same controller serves admin CRUD and the public article view
 * because the KB is tenant-scoped and doesn't warrant two controllers —
 * the index/edit/etc methods enforce MANAGE_HD_DEPARTMENTS via the route
 * middleware, while show/search/feedback are public (any logged-in user).
 */
class HdArticleController extends Controller
{
    // ---------------------------------------------------------------
    // Admin
    // ---------------------------------------------------------------

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'department_id', 'published']);

        $query = HdArticle::with(['department:id,name', 'category:id,name', 'author:id,name'])
            ->latest('updated_at');

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%'.$filters['search'].'%')
                    ->orWhere('summary', 'like', '%'.$filters['search'].'%');
            });
        }
        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }
        if (isset($filters['published']) && $filters['published'] !== '') {
            $query->where('is_published', (bool) $filters['published']);
        }

        $articles = $query->paginate(20)->withQueryString();

        return Inertia::render('Helpdesk/Articles/Index', [
            'articles' => $articles->through(fn ($a) => [
                'id' => $a->id,
                'slug' => $a->slug,
                'title' => $a->title,
                'summary' => $a->summary,
                'department_name' => $a->department?->name,
                'category_name' => $a->category?->name,
                'author_name' => $a->author?->name,
                'is_published' => $a->is_published,
                'view_count' => $a->view_count,
                'helpful_count' => $a->helpful_count,
                'not_helpful_count' => $a->not_helpful_count,
                'updated_at' => $a->updated_at->format('d/m/Y H:i'),
            ]),
            'filters' => $filters,
            'departments' => HdDepartment::active()->ordered()->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Helpdesk/Articles/Edit', [
            'article' => null,
            'departments' => HdDepartment::active()->ordered()->get(['id', 'name']),
            'categories' => HdCategory::active()->orderBy('name')->get(['id', 'name', 'department_id']),
        ]);
    }

    public function edit(HdArticle $article)
    {
        return Inertia::render('Helpdesk/Articles/Edit', [
            'article' => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'summary' => $article->summary,
                'content_md' => $article->content_md,
                'department_id' => $article->department_id,
                'category_id' => $article->category_id,
                'is_published' => $article->is_published,
            ],
            'departments' => HdDepartment::active()->ordered()->get(['id', 'name']),
            'categories' => HdCategory::active()->orderBy('name')->get(['id', 'name', 'department_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['author_id'] = auth()->id();

        $article = HdArticle::create($data);

        return redirect()->route('helpdesk.articles.edit', $article->id)
            ->with('success', 'Artigo criado.');
    }

    public function update(Request $request, HdArticle $article)
    {
        $data = $this->validated($request, $article->id);
        $article->update($data);

        return back()->with('success', 'Artigo atualizado.');
    }

    public function destroy(HdArticle $article)
    {
        $article->delete();

        return redirect()->route('helpdesk.articles.index')
            ->with('success', 'Artigo removido.');
    }

    // ---------------------------------------------------------------
    // Public view + feedback
    // ---------------------------------------------------------------

    /**
     * Render a published article. Increments view counter and logs the
     * view event so deflection metrics can be computed later.
     */
    public function show(Request $request, string $slug)
    {
        $article = HdArticle::where('slug', $slug)->firstOrFail();

        // Unpublished articles only visible to admins.
        if (! $article->is_published) {
            abort_unless(
                auth()->user()?->hasPermissionTo(\App\Enums\Permission::MANAGE_HD_DEPARTMENTS->value),
                404,
            );
        }

        // Track the view. Deduped naively — a user viewing the same article
        // twice in 10 minutes counts as one view for the counter.
        $recentView = HdArticleView::where('article_id', $article->id)
            ->where('user_id', auth()->id())
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if (! $recentView) {
            HdArticleView::create([
                'article_id' => $article->id,
                'user_id' => auth()->id(),
                'source' => $request->get('source', 'direct_link'),
                'action' => 'viewed',
                'created_at' => now(),
            ]);
            $article->increment('view_count');
        }

        return Inertia::render('Helpdesk/Articles/Show', [
            'article' => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'summary' => $article->summary,
                'content_html' => $article->content_html,
                'department_name' => $article->department?->name,
                'category_name' => $article->category?->name,
                'author_name' => $article->author?->name,
                'view_count' => $article->view_count,
                'helpful_count' => $article->helpful_count,
                'not_helpful_count' => $article->not_helpful_count,
                'published_at' => $article->published_at?->format('d/m/Y'),
                'updated_at' => $article->updated_at->format('d/m/Y'),
            ],
        ]);
    }

    /**
     * Search endpoint used by the dashboard intake form + WhatsApp driver
     * to suggest relevant articles. Returns up to 5 matches with a
     * relevance score when running on MySQL FULLTEXT; falls back to LIKE
     * on SQLite (tests) so the same endpoint works everywhere.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $departmentId = $request->get('department_id') ? (int) $request->get('department_id') : null;

        if (mb_strlen($q) < 3) {
            return response()->json(['results' => []]);
        }

        $query = HdArticle::query()
            ->published()
            ->forDepartment($departmentId)
            ->limit(5);

        if (DB::connection()->getDriverName() === 'mysql') {
            $query
                ->selectRaw('*, MATCH(title, summary, content_md) AGAINST (? IN NATURAL LANGUAGE MODE) AS relevance', [$q])
                ->whereRaw('MATCH(title, summary, content_md) AGAINST (? IN NATURAL LANGUAGE MODE)', [$q])
                ->orderByDesc('relevance');
        } else {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('title', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('content_md', 'like', $like);
            });
        }

        $results = $query->get()->map(fn ($a) => [
            'id' => $a->id,
            'slug' => $a->slug,
            'title' => $a->title,
            'summary' => $a->summary,
            'department_name' => $a->department?->name,
            'url' => route('helpdesk.articles.show', $a->slug),
        ]);

        return response()->json(['results' => $results]);
    }

    /**
     * Register helpful / not-helpful feedback. One-time per user per
     * article; subsequent votes update the existing row (so flipping
     * helpful → not helpful correctly adjusts counters).
     */
    public function feedback(Request $request, HdArticle $article)
    {
        $validated = $request->validate([
            'helpful' => 'required|boolean',
            'comment' => 'nullable|string|max:500',
        ]);

        $helpful = (bool) $validated['helpful'];
        $comment = $validated['comment'] ?? null;

        DB::transaction(function () use ($article, $helpful, $comment) {
            $existing = HdArticleFeedback::where('article_id', $article->id)
                ->where('user_id', auth()->id())
                ->first();

            if ($existing) {
                // If flipping, adjust both counters.
                if ($existing->helpful !== $helpful) {
                    if ($existing->helpful) {
                        $article->decrement('helpful_count');
                        $article->increment('not_helpful_count');
                    } else {
                        $article->decrement('not_helpful_count');
                        $article->increment('helpful_count');
                    }
                    $existing->update(['helpful' => $helpful, 'comment' => $comment]);
                }
            } else {
                HdArticleFeedback::create([
                    'article_id' => $article->id,
                    'user_id' => auth()->id(),
                    'helpful' => $helpful,
                    'comment' => $comment,
                    'created_at' => now(),
                ]);
                if ($helpful) {
                    $article->increment('helpful_count');
                } else {
                    $article->increment('not_helpful_count');
                }
            }
        });

        return back()->with('success', 'Obrigado pelo feedback.');
    }

    /**
     * Record a deflection: the user found this article helpful enough to
     * not open a ticket. Distinct from feedback (helpful=true) because
     * deflection is a UX signal ("I'm leaving without creating a ticket")
     * while feedback is about quality.
     */
    public function deflect(Request $request, HdArticle $article)
    {
        HdArticleView::create([
            'article_id' => $article->id,
            'user_id' => auth()->id(),
            'source' => $request->get('source', 'intake_form'),
            'action' => 'deflected',
            'created_at' => now(),
        ]);

        return response()->json(['status' => 'deflected']);
    }

    // ---------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'summary' => 'nullable|string|max:300',
            'content_md' => 'required|string|max:60000',
            'department_id' => 'nullable|integer|exists:hd_departments,id',
            'category_id' => 'nullable|integer|exists:hd_categories,id',
            'is_published' => 'boolean',
        ]);

        // Cross-department category guard.
        if (! empty($data['category_id'])) {
            $category = HdCategory::find($data['category_id']);
            if ($category && $data['department_id'] && $category->department_id !== (int) $data['department_id']) {
                abort(422, 'A categoria não pertence ao departamento selecionado.');
            }
        }

        return $data;
    }
}
