<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExperienceQuestion extends Model
{
    public $timestamps = false;

    // Form types
    public const FORM_EMPLOYEE = 'employee';

    public const FORM_MANAGER = 'manager';

    // Question types
    public const TYPE_RATING = 'rating';

    public const TYPE_TEXT = 'text';

    public const TYPE_YES_NO = 'yes_no';

    protected $fillable = [
        'milestone', 'form_type', 'question_order',
        'question_text', 'question_type',
        'is_required', 'is_active',
    ];

    protected $casts = [
        'question_order' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForMilestone(Builder $query, string $milestone): Builder
    {
        return $query->where('milestone', $milestone);
    }

    public function scopeForFormType(Builder $query, string $formType): Builder
    {
        return $query->where('form_type', $formType);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('question_order');
    }
}
