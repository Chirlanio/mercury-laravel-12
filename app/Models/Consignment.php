<?php

namespace App\Models;

use App\Enums\ConsignmentStatus;
use App\Enums\ConsignmentType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Consignação — envio de produtos para Cliente, Influencer ou E-commerce
 * com prazo de retorno. Paridade v1 (adms_consignments) adaptada:
 *
 *  - State machine em ConsignmentStatus (mutação exclusiva via
 *    ConsignmentTransitionService)
 *  - NF saída (movement_code=20) + NF retorno (21) vinculadas via
 *    snapshot composite (store_code + invoice_number + date)
 *  - FK NOT NULL em products/variants nos items (regra M8)
 *  - Bloqueio por overdue em ensureRecipientEligibility (regra M9)
 *
 * Soft delete manual (convenção do projeto — mesma dos módulos recentes).
 */
class Consignment extends Model
{
    use Auditable, HasFactory;

    protected $table = 'consignments';

    protected $fillable = [
        'uuid',
        'type',
        'store_id',
        'employee_id',
        'customer_id',
        'recipient_name',
        'recipient_document',
        'recipient_document_clean',
        'recipient_phone',
        'recipient_email',
        'outbound_invoice_number',
        'outbound_invoice_date',
        'outbound_store_code',
        'outbound_total_value',
        'outbound_items_count',
        'returned_total_value',
        'returned_items_count',
        'sold_total_value',
        'sold_items_count',
        'lost_total_value',
        'lost_items_count',
        'expected_return_date',
        'return_period_days',
        'status',
        'issued_at',
        'completed_at',
        'cancelled_at',
        'cancelled_reason',
        'notes',
        'created_by_user_id',
        'completed_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'type' => ConsignmentType::class,
        'status' => ConsignmentStatus::class,
        'outbound_invoice_date' => 'date',
        'expected_return_date' => 'date',
        'issued_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'deleted_at' => 'datetime',
        'outbound_total_value' => 'decimal:2',
        'returned_total_value' => 'decimal:2',
        'sold_total_value' => 'decimal:2',
        'lost_total_value' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Consignment $c): void {
            if (empty($c->uuid)) {
                $c->uuid = (string) Str::uuid();
            }
        });
    }

    // ------------------------------------------------------------------
    // State machine helpers
    // ------------------------------------------------------------------

    public function canTransitionTo(ConsignmentStatus|string $target): bool
    {
        $target = $target instanceof ConsignmentStatus ? $target : ConsignmentStatus::from($target);

        return $this->status->canTransitionTo($target);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->isTerminal() || $this->status === ConsignmentStatus::DRAFT) {
            return false;
        }

        return $this->expected_return_date?->isPast() ?? false;
    }

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeForStore(Builder $query, int|Store $store): Builder
    {
        $id = $store instanceof Store ? $store->id : $store;

        return $query->where('store_id', $id);
    }

    public function scopeForType(Builder $query, ConsignmentType|string $type): Builder
    {
        return $query->where('type', $type instanceof ConsignmentType ? $type->value : $type);
    }

    public function scopeForStatus(Builder $query, ConsignmentStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof ConsignmentStatus ? $status->value : $status);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (ConsignmentStatus $s) => $s->value,
            ConsignmentStatus::openStates()
        ));
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', ConsignmentStatus::OVERDUE->value);
    }

    public function scopeForRecipientDocument(Builder $query, string $documentClean): Builder
    {
        return $query->where('recipient_document_clean', preg_replace('/\D/', '', $documentClean));
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('consignments.deleted_at');
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ConsignmentReturn::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ConsignmentStatusHistory::class)->orderByDesc('created_at');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }
}
