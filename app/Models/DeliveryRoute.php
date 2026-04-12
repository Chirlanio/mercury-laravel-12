<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryRoute extends Model
{
    use Auditable;

    // Statuses
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_ROUTE = 'in_route';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_IN_ROUTE => 'Em Rota',
        self::STATUS_COMPLETED => 'Concluída',
        self::STATUS_CANCELLED => 'Cancelada',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => 'warning',
        self::STATUS_IN_ROUTE => 'info',
        self::STATUS_COMPLETED => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    public const VALID_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_IN_ROUTE, self::STATUS_CANCELLED],
        self::STATUS_IN_ROUTE => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'route_number', 'driver_id', 'date_route', 'status',
        'notes', 'created_by_user_id', 'updated_by_user_id',
    ];

    protected $casts = [
        'date_route' => 'date',
    ];

    // State machine

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$this->status] ?? []);
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

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryRouteItem::class, 'route_id')->orderBy('sequence_order');
    }

    // Scopes

    public function scopeForDriver(Builder $query, int $driverId): Builder
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date_route', $date);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED]);
    }

    // Helpers

    public static function generateRouteNumber(string $date): string
    {
        $dateFormatted = str_replace('-', '', $date);
        $lastRoute = self::where('route_number', 'like', "RT-{$dateFormatted}-%")->orderByDesc('route_number')->first();

        $sequence = 1;
        if ($lastRoute) {
            $parts = explode('-', $lastRoute->route_number);
            $sequence = ((int) end($parts)) + 1;
        }

        return 'RT-'.$dateFormatted.'-'.str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }
}
