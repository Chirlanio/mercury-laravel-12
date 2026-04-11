<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperienceResponse extends Model
{
    protected $fillable = [
        'evaluation_id', 'question_id', 'form_type',
        'response_text', 'rating_value', 'yes_no_value',
    ];

    protected $casts = [
        'rating_value' => 'integer',
        'yes_no_value' => 'boolean',
    ];

    // Relationships

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ExperienceEvaluation::class, 'evaluation_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ExperienceQuestion::class, 'question_id');
    }

    // Accessors

    public function getDisplayValueAttribute(): string
    {
        if ($this->rating_value !== null) {
            return "{$this->rating_value}/5";
        }
        if ($this->yes_no_value !== null) {
            return $this->yes_no_value ? 'Sim' : 'Nao';
        }

        return $this->response_text ?? '-';
    }
}
