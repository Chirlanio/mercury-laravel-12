<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingQuizOption extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'question_id', 'option_text', 'is_correct', 'sort_order',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships

    public function question(): BelongsTo
    {
        return $this->belongsTo(TrainingQuizQuestion::class, 'question_id');
    }
}
