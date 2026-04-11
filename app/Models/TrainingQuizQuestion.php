<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingQuizQuestion extends Model
{
    public $timestamps = false;

    // Question types
    public const TYPE_SINGLE = 'single';

    public const TYPE_MULTIPLE = 'multiple';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_OPEN_TEXT = 'open_text';

    public const TYPE_LABELS = [
        self::TYPE_SINGLE => 'Escolha Única',
        self::TYPE_MULTIPLE => 'Múltipla Escolha',
        self::TYPE_BOOLEAN => 'Verdadeiro/Falso',
        self::TYPE_OPEN_TEXT => 'Resposta Aberta',
    ];

    protected $fillable = [
        'quiz_id', 'question_text', 'question_type',
        'sort_order', 'points', 'explanation',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'points' => 'integer',
    ];

    // Relationships

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(TrainingQuiz::class, 'quiz_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(TrainingQuizOption::class, 'question_id')->orderBy('sort_order');
    }

    // Accessors

    public function getCorrectOptionIdsAttribute(): array
    {
        return $this->options()->where('is_correct', true)->pluck('id')->toArray();
    }
}
