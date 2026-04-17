<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaneiaMessage extends Model
{
    use HasFactory;

    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'taneia_conversation_id',
        'role',
        'content',
        'sources',
        'rating',
    ];

    protected $casts = [
        'sources' => 'array',
        'rating' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(TaneiaConversation::class, 'taneia_conversation_id');
    }
}
