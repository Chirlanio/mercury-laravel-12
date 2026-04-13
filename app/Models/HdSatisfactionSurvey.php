<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HdSatisfactionSurvey extends Model
{
    protected $fillable = [
        'ticket_id',
        'requester_id',
        'resolved_by_user_id',
        'department_id',
        'category_id',
        'rating',
        'comment',
        'signed_token',
        'sent_via',
        'sent_at',
        'submitted_at',
        'expires_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'sent_at' => 'datetime',
        'submitted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HdTicket::class, 'ticket_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HdDepartment::class, 'department_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HdCategory::class, 'category_id');
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereNotNull('submitted_at');
    }

    public function scopeAlive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Generate a fresh random signed token. 40 chars of Str::random()
     * is URL-safe and gives enough entropy to prevent brute force.
     */
    public static function generateToken(): string
    {
        return Str::random(40);
    }
}
