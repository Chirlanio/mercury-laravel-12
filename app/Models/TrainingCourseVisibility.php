<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCourseVisibility extends Model
{
    // Target types
    public const TARGET_STORE = 'store';

    public const TARGET_ROLE = 'role';

    public const TARGET_USER = 'user';

    protected $table = 'training_course_visibility';

    protected $fillable = [
        'course_id', 'target_type', 'target_id',
    ];

    // Relationships

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }
}
