<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Atividade de relacionamento com cliente (gift/event/contact/note/other).
 *
 * Feed CRM-light registrado por Marketing. Independente de o cliente estar
 * classificado como VIP — a tabela permite registrar atividades para
 * qualquer cliente. Soft delete para auditoria.
 */
class CustomerVipActivity extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'customer_vip_activities';

    protected $fillable = [
        'customer_id',
        'type',
        'title',
        'description',
        'occurred_at',
        'created_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'date:Y-m-d',
        'metadata' => 'array',
    ];

    public const TYPE_GIFT = 'gift';

    public const TYPE_EVENT = 'event';

    public const TYPE_CONTACT = 'contact';

    public const TYPE_NOTE = 'note';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_GIFT,
        self::TYPE_EVENT,
        self::TYPE_CONTACT,
        self::TYPE_NOTE,
        self::TYPE_OTHER,
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeBetween(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('occurred_at', [$start, $end]);
    }
}
