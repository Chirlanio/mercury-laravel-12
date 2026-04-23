<?php

namespace App\Models;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cupom de desconto — solicitação + provisionamento manual pelo e-commerce.
 *
 * CPF: armazenado com cast `encrypted` (Laravel Crypt, reversível — necessário
 * pra exibição mascarada em modais). Adicionalmente, `cpf_hash` é um HMAC-SHA256
 * determinístico (baseado em `app.key`) usado para busca exata + validação
 * de unicidade sem expor o CPF em claro. O hash é recalculado automaticamente
 * a cada set de `cpf` via mutator.
 *
 * Unicidade (1 cupom ativo por beneficiário) é validada em CouponService, não
 * via unique constraint no banco. Chave varia por tipo:
 *   - consultor/ms_indica: (cpf_hash, type, store_code)
 *   - influencer:          (cpf_hash, type)
 *
 * Soft delete manual (padrão PurchaseOrder/Reversal/Return).
 */
class Coupon extends Model
{
    use Auditable;

    protected $table = 'coupons';

    protected $fillable = [
        'type',
        'status',
        'employee_id',
        'store_code',
        'influencer_name',
        'cpf',
        'cpf_hash',
        'social_media_id',
        'social_media_link',
        'city',
        'suggested_coupon',
        'coupon_site',
        'campaign_name',
        'valid_from',
        'valid_until',
        'usage_count',
        'max_uses',
        'last_used_at',
        'requested_at',
        'issued_at',
        'activated_at',
        'expired_at',
        'cancelled_at',
        'cancelled_reason',
        'notes',
        'created_by_user_id',
        'issued_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'deleted_reason',
    ];

    protected $casts = [
        'type' => CouponType::class,
        'status' => CouponStatus::class,
        // CPF NÃO usa cast 'encrypted' — encriptação/decriptação é controlada
        // manualmente em setCpfAttribute/getCpfAttribute pra coordenar com
        // o recálculo automático de cpf_hash.
        'valid_from' => 'date',
        'valid_until' => 'date',
        'requested_at' => 'datetime',
        'issued_at' => 'datetime',
        'activated_at' => 'datetime',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_used_at' => 'datetime',
        'deleted_at' => 'datetime',
        'usage_count' => 'integer',
        'max_uses' => 'integer',
    ];

    // ------------------------------------------------------------------
    // CPF mutator — recalcula cpf_hash automaticamente
    // ------------------------------------------------------------------

    /**
     * Ao setar cpf, encripta manualmente (Crypt::encryptString) e
     * recalcula o cpf_hash determinístico.
     */
    public function setCpfAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['cpf'] = null;
            $this->attributes['cpf_hash'] = null;

            return;
        }

        $this->attributes['cpf'] = encrypt($value);
        $this->attributes['cpf_hash'] = self::hashCpf($value);
    }

    /**
     * Ao ler cpf, decripta. Retorna null se o valor armazenado for
     * inválido/corrompido (nunca lança — evita quebrar listagens).
     */
    public function getCpfAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Gera o hash determinístico do CPF. Remove não-dígitos antes (aceita
     * tanto "123.456.789-00" quanto "12345678900"). Secret é a app.key
     * (nunca expor) — se a app.key mudar, todos os hashes se invalidam
     * e uma re-indexação é necessária.
     */
    public static function hashCpf(string $cpf): string
    {
        $digits = preg_replace('/\D/', '', $cpf);

        return hash_hmac('sha256', $digits, config('app.key'));
    }

    /**
     * Retorna o CPF mascarado (XXX.XXX.XXX-XX ou vazio).
     * Usado pela exibição em modais/exports.
     */
    public function getMaskedCpfAttribute(): string
    {
        if (! $this->cpf) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $this->cpf);
        if (strlen($digits) !== 11) {
            return $this->cpf;
        }

        return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
    }

    // ------------------------------------------------------------------
    // State machine helpers
    // ------------------------------------------------------------------

    public function canTransitionTo(CouponStatus|string $target): bool
    {
        $target = $target instanceof CouponStatus ? $target : CouponStatus::from($target);

        return $this->status->canTransitionTo($target);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isActive(): bool
    {
        return in_array($this->status, CouponStatus::active(), true);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type->label();
    }

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    /**
     * Nome amigável do beneficiário (Employee.name ou influencer_name).
     */
    public function getBeneficiaryNameAttribute(): string
    {
        if ($this->type === CouponType::INFLUENCER) {
            return $this->influencer_name ?? '';
        }

        return $this->employee?->name ?? '';
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeForStore(Builder $query, string $storeCode): Builder
    {
        return $query->where('store_code', $storeCode);
    }

    public function scopeForType(Builder $query, CouponType|string $type): Builder
    {
        return $query->where('type', $type instanceof CouponType ? $type->value : $type);
    }

    public function scopeForStatus(Builder $query, CouponStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof CouponStatus ? $status->value : $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (CouponStatus $s) => $s->value,
            CouponStatus::active()
        ));
    }

    public function scopeForCpfHash(Builder $query, string $cpfHash): Builder
    {
        return $query->where('cpf_hash', $cpfHash);
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('coupons.deleted_at');
    }

    public function scopeExpiring(Builder $query, int $daysAhead = 7): Builder
    {
        return $query->whereNotNull('valid_until')
            ->whereBetween('valid_until', [now()->toDateString(), now()->addDays($daysAhead)->toDateString()]);
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

    public function socialMedia(): BelongsTo
    {
        return $this->belongsTo(SocialMedia::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(CouponStatusHistory::class)->orderByDesc('created_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
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
