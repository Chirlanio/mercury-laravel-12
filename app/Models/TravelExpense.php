<?php

namespace App\Models;

use App\Enums\AccountabilityStatus;
use App\Enums\TravelExpenseStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solicitação de verba de viagem — substitui adms_travel_expenses da v1.
 *
 * Dual status:
 *  - $status (TravelExpenseStatus) — fluxo de aprovação da solicitação
 *  - $accountability_status (AccountabilityStatus) — fluxo da prestação
 *    de contas, paralelo e independente. Só sai de PENDING quando a verba
 *    é aprovada (TravelExpenseStatus::APPROVED).
 *
 * CPF (do beneficiário, pra fins de pagamento PIX/depósito):
 *  - cpf_encrypted: encriptado manualmente via Crypt::encryptString
 *  - cpf_hash: HMAC-SHA256 determinístico (busca/dedup sem expor claro)
 *  - Mutator setCpfAttribute recalcula automaticamente o hash
 *
 * Pagamento dual (XOR validado em TravelExpenseService):
 *  - bancário: bank_id + bank_branch + bank_account
 *  - PIX: pix_type_id + pix_key (encriptada)
 *
 * Soft delete manual (padrão Reversal/Coupon/Return).
 */
class TravelExpense extends Model
{
    use Auditable;
    use HasUlids;

    protected $table = 'travel_expenses';

    /** Coluna ULID pública (não substitui id como PK) */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** Trait HasUlids tenta usar `ulid` como route key */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected $fillable = [
        'ulid',
        'employee_id',
        'store_code',
        'origin',
        'destination',
        'initial_date',
        'end_date',
        'daily_rate',
        'days_count',
        'value',
        'client_name',
        'cpf_encrypted',
        'cpf_hash',
        'bank_id',
        'bank_branch',
        'bank_account',
        'pix_type_id',
        'pix_key_encrypted',
        'description',
        'internal_notes',
        'status',
        'submitted_at',
        'approved_at',
        'approver_user_id',
        'rejected_at',
        'rejection_reason',
        'finalized_at',
        'cancelled_at',
        'cancelled_reason',
        'accountability_status',
        'accountability_submitted_at',
        'accountability_approved_at',
        'accountability_rejected_at',
        'accountability_rejection_reason',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    /**
     * Defaults aplicados ao instanciar (independente de DB defaults — necessário
     * pra que o cast enum tenha um valor válido em memória antes do save).
     */
    protected $attributes = [
        'status' => 'draft',
        'accountability_status' => 'pending',
    ];

    protected $casts = [
        'status' => TravelExpenseStatus::class,
        'accountability_status' => AccountabilityStatus::class,
        'initial_date' => 'date',
        'end_date' => 'date',
        'daily_rate' => 'decimal:2',
        'days_count' => 'integer',
        'value' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'finalized_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'accountability_submitted_at' => 'datetime',
        'accountability_approved_at' => 'datetime',
        'accountability_rejected_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // CPF — encryption manual + cpf_hash determinístico (padrão Coupons)
    // ------------------------------------------------------------------

    /**
     * Atributo virtual `cpf` — escreve em cpf_encrypted e recalcula cpf_hash.
     * Eloquent não tem coluna `cpf`, então usamos cpf_encrypted como storage.
     */
    public function setCpfAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['cpf_encrypted'] = null;
            $this->attributes['cpf_hash'] = null;

            return;
        }

        $this->attributes['cpf_encrypted'] = encrypt($value);
        $this->attributes['cpf_hash'] = self::hashCpf($value);
    }

    public function getCpfAttribute(): ?string
    {
        $encrypted = $this->attributes['cpf_encrypted'] ?? null;
        if ($encrypted === null || $encrypted === '') {
            return null;
        }

        try {
            return decrypt($encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function hashCpf(string $cpf): string
    {
        $digits = preg_replace('/\D/', '', $cpf);

        return hash_hmac('sha256', $digits, config('app.key'));
    }

    public function getMaskedCpfAttribute(): string
    {
        $cpf = $this->cpf;
        if (! $cpf) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $cpf);
        if (strlen($digits) !== 11) {
            return $cpf;
        }

        return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
    }

    // ------------------------------------------------------------------
    // PIX — também encriptada (mesma chave do CPF: app.key via Crypt)
    // ------------------------------------------------------------------

    public function setPixKeyAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['pix_key_encrypted'] = null;

            return;
        }

        $this->attributes['pix_key_encrypted'] = encrypt($value);
    }

    public function getPixKeyAttribute(): ?string
    {
        $encrypted = $this->attributes['pix_key_encrypted'] ?? null;
        if ($encrypted === null || $encrypted === '') {
            return null;
        }

        try {
            return decrypt($encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // State machine helpers
    // ------------------------------------------------------------------

    public function canTransitionTo(TravelExpenseStatus|string $target): bool
    {
        $target = $target instanceof TravelExpenseStatus ? $target : TravelExpenseStatus::from($target);

        return $this->status->canTransitionTo($target);
    }

    public function canAccountabilityTransitionTo(AccountabilityStatus|string $target): bool
    {
        $target = $target instanceof AccountabilityStatus ? $target : AccountabilityStatus::from($target);

        return $this->accountability_status->canTransitionTo($target);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isActive(): bool
    {
        return in_array($this->status, TravelExpenseStatus::active(), true);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getAccountabilityStatusLabelAttribute(): string
    {
        return $this->accountability_status->label();
    }

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    /**
     * Soma do valor lançado na prestação de contas (somente itens não deletados).
     */
    public function getAccountedValueAttribute(): float
    {
        return (float) $this->items()->sum('value');
    }

    /**
     * Saldo: positivo = sobrou (usuário deve devolver), negativo = a reembolsar.
     */
    public function getBalanceAttribute(): float
    {
        return (float) $this->value - $this->accounted_value;
    }

    /**
     * Quantos dias passaram desde o fim da viagem sem prestação de contas.
     */
    public function getDaysSinceEndAttribute(): int
    {
        if (! $this->end_date) {
            return 0;
        }

        return (int) $this->end_date->diffInDays(now(), false);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeForStore(Builder $query, string $storeCode): Builder
    {
        return $query->where('store_code', $storeCode);
    }

    public function scopeForStatus(Builder $query, TravelExpenseStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof TravelExpenseStatus ? $status->value : $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (TravelExpenseStatus $s) => $s->value,
            TravelExpenseStatus::active()
        ));
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('travel_expenses.deleted_at');
    }

    /**
     * Scope de visibilidade — usuário sem MANAGE_TRAVEL_EXPENSES só vê
     * verbas onde é solicitante (created_by_user_id) ou beneficiado
     * (employee_id). Aplicado via TravelExpenseService::scopedQuery().
     */
    public function scopeOwnedOrBeneficiary(Builder $query, int $userId, ?int $employeeId = null): Builder
    {
        return $query->where(function (Builder $q) use ($userId, $employeeId) {
            $q->where('created_by_user_id', $userId);
            if ($employeeId !== null) {
                $q->orWhere('employee_id', $employeeId);
            }
        });
    }

    public function scopeAccountabilityOverdue(Builder $query, int $daysOverdue = 3): Builder
    {
        return $query
            ->forStatus(TravelExpenseStatus::APPROVED)
            ->whereNotIn('accountability_status', [
                AccountabilityStatus::SUBMITTED->value,
                AccountabilityStatus::APPROVED->value,
            ])
            ->where('end_date', '<=', now()->subDays($daysOverdue)->toDateString());
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_code', 'code');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function pixType(): BelongsTo
    {
        return $this->belongsTo(TypeKeyPix::class, 'pix_type_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TravelExpenseItem::class)->whereNull('deleted_at');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TravelExpenseStatusHistory::class)->orderByDesc('created_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
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
