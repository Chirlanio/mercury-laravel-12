<?php

namespace App\Models;

use App\Enums\DamagedProductStatus;
use App\Enums\FootSide;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Registro de produto avariado (par trocado, par com avaria, ou ambos).
 *
 * Booleans `is_mismatched` e `is_damaged` podem ser combinados — alguns casos
 * têm par trocado E avaria simultaneamente (raro mas possível).
 *
 * Engine de matching (DamagedProductMatchingService) cruza pares quando:
 *  - status = open
 *  - mesma product_reference
 *  - lojas distintas
 *  - regras de marca/rede compatíveis (bidirecional via NetworkBrandRule)
 *  - condições específicas por tipo (sizes invertidas pra mismatched_pair,
 *    pés opostos pra damaged_complement)
 */
class DamagedProduct extends Model
{
    use Auditable;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'damaged_products';

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected $fillable = [
        'ulid',
        'store_id',
        'product_id',
        'product_reference',
        'product_name',
        'product_color',
        'brand_cigam_code',
        'product_size',
        'is_mismatched',
        'is_damaged',
        'mismatched_foot',
        'mismatched_actual_size',
        'mismatched_expected_size',
        'damage_type_id',
        'damaged_foot',
        'damage_description',
        'is_repairable',
        'estimated_repair_cost',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'cancelled_by_user_id',
        'cancel_reason',
        'cancelled_at',
        'resolved_at',
        'expires_at',
    ];

    /**
     * Defaults aplicados ao instanciar — necessário pra que o cast enum
     * tenha um valor válido em memória antes do save (gotcha Laravel 12).
     */
    protected $attributes = [
        'status' => 'open',
        'is_mismatched' => false,
        'is_damaged' => false,
        'is_repairable' => false,
    ];

    protected $casts = [
        'status' => DamagedProductStatus::class,
        'mismatched_foot' => FootSide::class,
        'damaged_foot' => FootSide::class,
        'is_mismatched' => 'boolean',
        'is_damaged' => 'boolean',
        'is_repairable' => 'boolean',
        'estimated_repair_cost' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'resolved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function damageType(): BelongsTo
    {
        return $this->belongsTo(DamageType::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'brand_cigam_code', 'cigam_code');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(DamagedProductPhoto::class)->orderBy('sort_order');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DamagedProductStatusHistory::class)->orderBy('created_at');
    }

    /**
     * Matches em que este produto aparece como product_a OU product_b.
     */
    public function matches(): HasMany
    {
        return $this->hasMany(DamagedProductMatch::class, 'product_a_id')
            ->orWhere('product_b_id', $this->id);
    }

    public function matchesAsA(): HasMany
    {
        return $this->hasMany(DamagedProductMatch::class, 'product_a_id');
    }

    public function matchesAsB(): HasMany
    {
        return $this->hasMany(DamagedProductMatch::class, 'product_b_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', DamagedProductStatus::OPEN->value);
    }

    /**
     * Estados elegíveis pra matching (engine ignora resolved/cancelled e
     * também transfer_requested — esse já tem match aceito vinculado).
     */
    public function scopeMatchable(Builder $query): Builder
    {
        return $query->where('status', DamagedProductStatus::OPEN->value);
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeNotFinal(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            DamagedProductStatus::RESOLVED->value,
            DamagedProductStatus::CANCELLED->value,
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function isFinal(): bool
    {
        return $this->status?->isFinal() ?? false;
    }

    public function isOpen(): bool
    {
        return $this->status === DamagedProductStatus::OPEN;
    }

    /**
     * Inclui matches PENDING contando este produto, em qualquer lado.
     */
    public function pendingMatchesCount(): int
    {
        return DamagedProductMatch::query()
            ->forProduct($this->id)
            ->pending()
            ->count();
    }
}
