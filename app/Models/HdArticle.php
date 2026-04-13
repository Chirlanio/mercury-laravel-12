<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;

/**
 * Knowledge Base article. Content is stored as markdown; HTML is
 * pre-rendered on save via league/commonmark (already a Laravel transitive
 * dependency) so the public view never parses on read.
 *
 * Slug is auto-generated from the title on create if not provided.
 * Uniqueness is enforced at the DB level; this model appends a numeric
 * suffix until the slug is unique.
 */
class HdArticle extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'title',
        'summary',
        'content_md',
        'content_html',
        'department_id',
        'category_id',
        'is_published',
        'published_at',
        'author_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'view_count' => 'integer',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
    ];

    protected static function booted(): void
    {
        // Re-render HTML any time the markdown content is saved. Keeping
        // content_html always fresh means the public view can just dump
        // the stored HTML — no parsing on read.
        static::saving(function (self $article) {
            if ($article->isDirty('content_md')) {
                $article->content_html = static::renderMarkdown($article->content_md);
            }

            // Auto-generate slug from title if empty.
            if (empty($article->slug) && ! empty($article->title)) {
                $article->slug = static::uniqueSlug($article->title, $article->id);
            }

            // Stamp published_at when the article is first published.
            if ($article->is_published && ! $article->published_at) {
                $article->published_at = now();
            }
        });
    }

    /**
     * Render markdown to sanitized HTML. Uses league/commonmark with safe
     * defaults — raw HTML input is escaped, unsafe URLs blocked.
     */
    public static function renderMarkdown(?string $markdown): string
    {
        if (empty($markdown)) {
            return '';
        }

        $converter = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);

        return (string) $converter->convert($markdown);
    }

    /**
     * Generate a unique slug by appending a numeric suffix if needed.
     * $ignoreId lets us skip the current row during updates.
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'artigo-'.Str::random(6);
        }

        $slug = $base;
        $counter = 2;
        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$counter++;
            if ($counter > 1000) {
                // Defensive: give up and fall back to a random suffix.
                return $base.'-'.Str::random(6);
            }
        }

        return $slug;
    }

    // Relationships

    public function department(): BelongsTo
    {
        return $this->belongsTo(HdDepartment::class, 'department_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HdCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(HdArticleView::class, 'article_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(HdArticleFeedback::class, 'article_id');
    }

    // Scopes

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeForDepartment(Builder $query, ?int $departmentId): Builder
    {
        return $query->where(function ($q) use ($departmentId) {
            $q->whereNull('department_id')
                ->when($departmentId, fn ($q2) => $q2->orWhere('department_id', $departmentId));
        });
    }
}
