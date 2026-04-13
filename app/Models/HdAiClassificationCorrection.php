<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit record for technician overrides of AI-suggested
 * classification. Fuel for measuring AI accuracy over time.
 */
class HdAiClassificationCorrection extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'original_ai_category_id',
        'original_ai_priority',
        'original_ai_confidence',
        'original_ai_model',
        'corrected_category_id',
        'corrected_priority',
        'corrected_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'original_ai_confidence' => 'decimal:2',
        'original_ai_priority' => 'integer',
        'corrected_priority' => 'integer',
        'created_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HdTicket::class, 'ticket_id');
    }

    public function originalCategory(): BelongsTo
    {
        return $this->belongsTo(HdCategory::class, 'original_ai_category_id');
    }

    public function correctedCategory(): BelongsTo
    {
        return $this->belongsTo(HdCategory::class, 'corrected_category_id');
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by_user_id');
    }
}
