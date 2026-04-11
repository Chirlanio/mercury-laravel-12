<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperienceNotification extends Model
{
    public $timestamps = false;

    // Notification types
    public const TYPE_CREATED = 'created';

    public const TYPE_REMINDER_5D = 'reminder_5d';

    public const TYPE_REMINDER_DUE = 'reminder_due';

    public const TYPE_OVERDUE = 'overdue';

    protected $fillable = [
        'evaluation_id', 'notification_type',
        'recipient_type', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    // Relationships

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ExperienceEvaluation::class, 'evaluation_id');
    }
}
