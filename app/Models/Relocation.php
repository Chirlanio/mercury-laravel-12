<?php

namespace App\Models;

use App\Enums\RelocationPriority;
use App\Enums\RelocationStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solicitação de remanejo (transferência entre lojas).
 *
 * State machine em RelocationStatus. Transições aplicadas exclusivamente
 * via RelocationTransitionService — nunca mutar status direto.
 *
 * Soft delete manual (deleted_at + deleted_by_user_id + deleted_reason),
 * padrão consolidado (Reversals, PurchaseOrders, Vacancy).
 *
 * ULID público (`ulid`) é usado em rotas; o id interno fica protegido.
 */
class Relocation extends Model
{
    use Auditable;

    protected $fillable = [
        'ulid',
        'relocation_type_id',
        'origin_store_id',
        'destination_store_id',
        'title',
        'observations',
        'priority',
        'deadline_days',
        'status',
        'requested_at',
        'approved_at',
        'separated_at',
        'in_transit_at',
        'completed_at',
        'rejected_at',
        'rejected_reason',
        'cancelled_at',
        'cancelled_reason',
        'invoice_number',
        'invoice_date',
        'transfer_id',
        'cigam_dispatched_at',
        'cigam_received_at',
        'helpdesk_ticket_id',
        'created_by_user_id',
        'approved_by_user_id',
        'separated_by_user_id',
        'received_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'status' => RelocationStatus::class,
        'priority' => RelocationPriority::class,
        'deadline_days' => 'integer',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'separated_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'invoice_date' => 'date',
        'cigam_dispatched_at' => 'datetime',
        'cigam_received_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Defaults pra instâncias novas (mesmo antes de save) — sem isso o
     * cast enum retorna null e quebra helpers como canTransitionTo().
     * Padrão observado em DamagedProducts e TurnList (memory).
     */
    protected $attributes = [
        'status' => 'draft',
        'priority' => 'normal',
    ];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    // ------------------------------------------------------------------
    // State machine helpers
    // ------------------------------------------------------------------

    public function canTransitionTo(RelocationStatus|string $target): bool
    {
        $target = $target instanceof RelocationStatus
            ? $target
            : RelocationStatus::from($target);

        return $this->status->canTransitionTo($target);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isPreTransit(): bool
    {
        return $this->status->isPreTransit();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getPriorityLabelAttribute(): string
    {
        return $this->priority->label();
    }

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    /**
     * Soma de qty_requested em todos os itens. Útil pra dashboard.
     */
    public function getTotalRequestedAttribute(): int
    {
        return (int) $this->items()->sum('qty_requested');
    }

    public function getTotalReceivedAttribute(): int
    {
        return (int) $this->items()->sum('qty_received');
    }

    /**
     * Percentual de atendimento (0-100). Quando não há itens, 0.
     */
    public function getFulfillmentPercentageAttribute(): float
    {
        $req = $this->total_requested;
        if ($req <= 0) {
            return 0.0;
        }

        return round(($this->total_received / $req) * 100, 2);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Scoping bilateral: vê remanejos onde a loja é ORIGEM ou DESTINO.
     * Diferente da v1 que filtrava só por origem.
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where(function (Builder $q) use ($storeId) {
            $q->where('origin_store_id', $storeId)
                ->orWhere('destination_store_id', $storeId);
        });
    }

    public function scopeForOriginStore(Builder $query, int $storeId): Builder
    {
        return $query->where('origin_store_id', $storeId);
    }

    public function scopeForDestinationStore(Builder $query, int $storeId): Builder
    {
        return $query->where('destination_store_id', $storeId);
    }

    public function scopeForStatus(Builder $query, RelocationStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof RelocationStatus ? $status->value : $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (RelocationStatus $s) => $s->value,
            RelocationStatus::active()
        ));
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('relocations.deleted_at');
    }

    public function scopeForMonth(Builder $query, int $month, int $year): Builder
    {
        return $query->whereMonth('created_at', $month)->whereYear('created_at', $year);
    }

    /**
     * Remanejos in_transit cuja saída na ORIGEM ainda não foi confirmada
     * via CIGAM (movement_code=5 + entry_exit='S' + invoice_number).
     */
    public function scopePendingCigamDispatchMatch(Builder $query): Builder
    {
        return $query->where('status', RelocationStatus::IN_TRANSIT->value)
            ->whereNotNull('invoice_number')
            ->whereNull('cigam_dispatched_at');
    }

    /**
     * Remanejos in_transit cuja entrada no DESTINO ainda não foi confirmada
     * via CIGAM (movement_code=5 + entry_exit='E' + invoice_number).
     */
    public function scopePendingCigamReceiveMatch(Builder $query): Builder
    {
        return $query->where('status', RelocationStatus::IN_TRANSIT->value)
            ->whereNotNull('invoice_number')
            ->whereNull('cigam_received_at');
    }

    /**
     * Remanejos com pelo menos uma das pontas pendentes — usado pelo
     * command relocations:cigam-match (every 15min).
     */
    public function scopePendingCigamMatch(Builder $query): Builder
    {
        return $query->where('status', RelocationStatus::IN_TRANSIT->value)
            ->whereNotNull('invoice_number')
            ->where(function (Builder $q) {
                $q->whereNull('cigam_dispatched_at')
                    ->orWhereNull('cigam_received_at');
            });
    }

    /**
     * Remanejos atrasados pela deadline (deadline_days a partir de
     * approved_at). Usado pelo command relocations:overdue-alert.
     *
     * Otimização: invertendo a aritmética pra `approved_at < now() -
     * INTERVAL deadline_days DAY`. Isso ainda invalida o índice de
     * approved_at por causa do `deadline_days` na expressão, mas como
     * filtramos primeiro por `status IN (...)` (que tem índice de status)
     * o set já é pequeno antes do whereRaw. Em datasets grandes ainda
     * vale considerar coluna `deadline_at` materializada (backlog).
     *
     * Sintaxe da soma de dias varia por driver:
     *   - MySQL:  DATE_SUB(NOW(), INTERVAL deadline_days DAY)
     *   - SQLite: datetime('now', '-' || deadline_days || ' days')
     */
    public function scopeOverdue(Builder $query): Builder
    {
        $query->whereIn('status', [
            RelocationStatus::APPROVED->value,
            RelocationStatus::IN_SEPARATION->value,
            RelocationStatus::IN_TRANSIT->value,
        ])
            ->whereNotNull('approved_at')
            ->whereNotNull('deadline_days');

        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return $query->whereRaw(
                "approved_at < datetime('now', '-' || deadline_days || ' days')"
            );
        }

        // MySQL/MariaDB — comparação direta com approved_at usa o índice
        // (status, approved_at) quando existir.
        return $query->whereRaw('approved_at < DATE_SUB(NOW(), INTERVAL deadline_days DAY)');
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function items(): HasMany
    {
        return $this->hasMany(RelocationItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(RelocationStatusHistory::class)->orderByDesc('created_at');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(RelocationType::class, 'relocation_type_id');
    }

    public function originStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'origin_store_id');
    }

    public function destinationStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'destination_store_id');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function separatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'separated_by_user_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
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
