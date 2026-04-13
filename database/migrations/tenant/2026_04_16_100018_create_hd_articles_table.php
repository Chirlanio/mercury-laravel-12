<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Knowledge Base article storage. Articles are optionally scoped to a
 * department and/or category for contextual suggestion during ticket
 * intake. Content is stored as markdown (content_md) with a pre-rendered
 * HTML copy (content_html) updated on save via league/commonmark.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_articles')) {
            Schema::create('hd_articles', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 180)->unique();
                $table->string('title', 200);
                // Short teaser shown in list views and search suggestions.
                $table->string('summary', 300)->nullable();
                $table->longText('content_md');
                // Pre-rendered HTML, updated on every save. Stored separately
                // so the public view never does markdown parsing on read.
                $table->longText('content_html')->nullable();
                $table->foreignId('department_id')->nullable()->constrained('hd_departments')->nullOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('hd_categories')->nullOnDelete();
                $table->boolean('is_published')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->unsignedInteger('view_count')->default(0);
                $table->unsignedInteger('helpful_count')->default(0);
                $table->unsignedInteger('not_helpful_count')->default(0);
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();

                $table->index(['is_published', 'department_id']);
                $table->index(['department_id', 'category_id']);
                $table->index('author_id');
            });

            // MySQL-only: full-text index for KB search. SQLite silently skips.
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE hd_articles ADD FULLTEXT INDEX hd_articles_title_content_fulltext (title, summary, content_md)');
            }
        }

        if (! Schema::hasTable('hd_article_views')) {
            Schema::create('hd_article_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('article_id')->constrained('hd_articles')->cascadeOnDelete();
                // Nullable so views from the public CSAT form or unauthenticated
                // intake suggestions (future) still count.
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                // 'admin' | 'intake_form' | 'intake_whatsapp' | 'direct_link'
                $table->string('source', 30)->default('direct_link');
                // 'viewed' | 'deflected' — deflected means the viewer decided
                // the article solved their problem and did NOT open a ticket.
                $table->string('action', 20)->default('viewed');
                $table->timestamp('created_at')->nullable();

                $table->index(['article_id', 'action']);
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('hd_article_feedback')) {
            Schema::create('hd_article_feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('article_id')->constrained('hd_articles')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('helpful');
                $table->string('comment', 500)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['article_id', 'helpful']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_article_feedback');
        Schema::dropIfExists('hd_article_views');

        if (Schema::hasTable('hd_articles') && DB::getDriverName() === 'mysql') {
            // Drop the FULLTEXT index before dropping the table to avoid
            // accidental leftover rows in some MySQL variants.
            $exists = DB::select(
                'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                ['hd_articles', 'hd_articles_title_content_fulltext']
            );
            if (($exists[0]->c ?? 0) > 0) {
                DB::statement('ALTER TABLE hd_articles DROP INDEX hd_articles_title_content_fulltext');
            }
        }

        Schema::dropIfExists('hd_articles');
    }
};
