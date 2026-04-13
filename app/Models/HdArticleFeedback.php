<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Helpful/not-helpful thumbs-up feedback on an article. Separate from
 * HdArticleView because views are unauthenticated log entries while
 * feedback is an explicit user gesture.
 */
class HdArticleFeedback extends Model
{
    protected $table = 'hd_article_feedback';

    public const UPDATED_AT = null;

    protected $fillable = ['article_id', 'user_id', 'helpful', 'comment', 'created_at'];

    protected $casts = [
        'helpful' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(HdArticle::class, 'article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
