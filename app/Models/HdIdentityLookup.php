<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit record for a single identity resolution attempt during
 * helpdesk intake. Never updated after creation — use ::create(), never
 * ->save() on an existing instance.
 */
class HdIdentityLookup extends Model
{
    /** @var string */
    public const UPDATED_AT = null;

    protected $fillable = [
        'channel_id', 'external_contact', 'method', 'matched',
        'employee_id', 'attempt', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'matched' => 'boolean',
        'attempt' => 'integer',
        'created_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(HdChannel::class, 'channel_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
