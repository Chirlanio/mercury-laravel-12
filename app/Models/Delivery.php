<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use Auditable;

    // Statuses
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_AWAITING_PICKUP = 'awaiting_pickup';

    public const STATUS_IN_ROUTE = 'in_route';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_REQUESTED => 'Solicitado',
        self::STATUS_COLLECTED => 'Coletado',
        self::STATUS_AWAITING_PICKUP => 'Aguardando Coleta',
        self::STATUS_IN_ROUTE => 'Em Rota',
        self::STATUS_DELIVERED => 'Entregue',
        self::STATUS_RETURNED => 'Devolvido',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    public const STATUS_COLORS = [
        self::STATUS_REQUESTED => 'gray',
        self::STATUS_COLLECTED => 'info',
        self::STATUS_AWAITING_PICKUP => 'warning',
        self::STATUS_IN_ROUTE => 'purple',
        self::STATUS_DELIVERED => 'success',
        self::STATUS_RETURNED => 'orange',
        self::STATUS_CANCELLED => 'danger',
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_DELIVERED,
        self::STATUS_RETURNED,
        self::STATUS_CANCELLED,
    ];

    // Fluxo principal (próximo passo único)
    public const NEXT_STATUS = [
        self::STATUS_REQUESTED => self::STATUS_COLLECTED,
        self::STATUS_COLLECTED => self::STATUS_AWAITING_PICKUP,
        self::STATUS_AWAITING_PICKUP => null, // in_route via rota do motorista
        self::STATUS_IN_ROUTE => null,        // delivered/returned via dashboard do motorista
        self::STATUS_DELIVERED => null,
        self::STATUS_RETURNED => null,
        self::STATUS_CANCELLED => null,
    ];

    public const NEXT_STATUS_LABELS = [
        self::STATUS_REQUESTED => 'Marcar como Coletado',
        self::STATUS_COLLECTED => 'Pronto para Rota',
    ];

    // Todas as transições válidas (inclui cancelamento, ações do motorista e ações da logística)
    public const VALID_TRANSITIONS = [
        self::STATUS_REQUESTED => [self::STATUS_COLLECTED, self::STATUS_DELIVERED, self::STATUS_RETURNED, self::STATUS_CANCELLED],
        self::STATUS_COLLECTED => [self::STATUS_AWAITING_PICKUP, self::STATUS_DELIVERED, self::STATUS_RETURNED, self::STATUS_CANCELLED],
        self::STATUS_AWAITING_PICKUP => [self::STATUS_IN_ROUTE, self::STATUS_DELIVERED, self::STATUS_RETURNED, self::STATUS_CANCELLED],
        self::STATUS_IN_ROUTE => [self::STATUS_DELIVERED, self::STATUS_RETURNED, self::STATUS_CANCELLED],
        self::STATUS_DELIVERED => [],
        self::STATUS_RETURNED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'store_id', 'employee_id', 'client_name', 'invoice_number',
        'address', 'latitude', 'longitude', 'geocoded_at',
        'neighborhood', 'contact_phone',
        'sale_value', 'payment_method', 'installments',
        'products_qty', 'exit_point',
        'needs_card_machine', 'is_exchange', 'is_gift',
        'status', 'return_reason_id', 'observations',
        'created_by_user_id', 'updated_by_user_id',
        'deleted_at', 'deleted_by_user_id',
    ];

    protected $casts = [
        'sale_value' => 'decimal:2',
        'needs_card_machine' => 'boolean',
        'is_exchange' => 'boolean',
        'is_gift' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'geocoded_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // State machine

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$this->status] ?? []);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES);
    }

    // Accessors

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    // Relationships

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'code');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function returnReason(): BelongsTo
    {
        return $this->belongsTo(DeliveryReturnReason::class, 'return_reason_id');
    }

    public function routeItems(): HasMany
    {
        return $this->hasMany(DeliveryRouteItem::class, 'delivery_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeForStore(Builder $query, string $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeAvailableForRoute(Builder $query): Builder
    {
        return $query->active()
            ->whereIn('status', [self::STATUS_REQUESTED, self::STATUS_COLLECTED, self::STATUS_AWAITING_PICKUP])
            ->whereDoesntHave('routeItems', fn ($q) => $q->whereHas('route', fn ($rq) => $rq->whereIn('status', ['pending', 'in_route'])));
    }
}
