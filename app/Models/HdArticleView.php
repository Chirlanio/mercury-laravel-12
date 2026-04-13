<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable log of article views and deflection events.
 *
 *   action = 'viewed'    — user opened the article
 *   action = 'deflected' — user said "this solved my problem, no ticket needed"
 *
 *   source = 'admin' | 'intake_form' | 'intake_whatsapp' | 'direct_link'
 */
class HdArticleView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['article_id', 'user_id', 'source', 'action', 'created_at'];

    protected $casts = [
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
