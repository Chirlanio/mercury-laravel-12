<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCourseContent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'course_id', 'content_id', 'sort_order', 'is_required',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_required' => 'boolean',
    ];

    // Relationships

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(TrainingContent::class, 'content_id');
    }
}
