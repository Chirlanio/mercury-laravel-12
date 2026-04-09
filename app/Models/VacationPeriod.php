<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VacationPeriod extends Model
{
    use Auditable;

    protected $fillable = [
        'employee_id',
        'date_start_acq',
        'date_end_acq',
        'date_limit_concessive',
        'days_entitled',
        'days_taken',
        'sell_days',
        'absences_count',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'date_start_acq' => 'date',
        'date_end_acq' => 'date',
        'date_limit_concessive' => 'date',
    ];

    // ==========================================
    // Status constants
    // ==========================================

    public const STATUS_ACQUIRING = 'acquiring';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_PARTIALLY_TAKEN = 'partially_taken';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LOST = 'lost';

    public const STATUS_LABELS = [
        'acquiring' => 'Em Aquisição',
        'available' => 'Disponível',
        'partially_taken' => 'Parcialmente Gozado',
        'settled' => 'Quitado',
        'expired' => 'Vencido',
        'lost' => 'Perdido',
    ];

    public const STATUS_COLORS = [
        'acquiring' => 'blue',
        'available' => 'green',
        'partially_taken' => 'yellow',
        'settled' => 'gray',
        'expired' => 'red',
        'lost' => 'red',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function vacations(): HasMany
    {
        return $this->hasMany(Vacation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ==========================================
    // Accessors
    // ==========================================

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getDaysBalanceAttribute(): int
    {
        $pendingDays = $this->vacations()
            ->whereNotIn('status', [Vacation::STATUS_CANCELLED, Vacation::STATUS_REJECTED_MANAGER, Vacation::STATUS_REJECTED_RH])
            ->whereNotIn('status', [Vacation::STATUS_COMPLETED])
            ->sum('days_quantity');

        return $this->days_entitled - $this->days_taken - $this->sell_days - $pendingDays;
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->date_start_acq->format('d/m/Y').' a '.$this->date_end_acq->format('d/m/Y');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->date_limit_concessive->isPast() && in_array($this->status, [self::STATUS_AVAILABLE, self::STATUS_PARTIALLY_TAKEN]);
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeWithBalance($query)
    {
        return $query->whereIn('status', [self::STATUS_AVAILABLE, self::STATUS_PARTIALLY_TAKEN, self::STATUS_EXPIRED]);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_ACQUIRING, self::STATUS_AVAILABLE, self::STATUS_PARTIALLY_TAKEN]);
    }
}
