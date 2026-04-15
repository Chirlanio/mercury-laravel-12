<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderPayment extends Model
{
    use Auditable;

    protected $fillable = [
        'area_id',
        'cost_center_id',
        'brand_id',
        'supplier_id',
        'purchase_order_id',
        'store_id',
        'manager_id',
        'description',
        'total_value',
        'date_payment',
        'payment_type',
        'installments',
        'bank_name',
        'agency',
        'checking_account',
        'type_account',
        'name_supplier',
        'document_number_supplier',
        'pix_key_type',
        'pix_key',
        'advance',
        'advance_amount',
        'advance_paid',
        'diff_payment_advance',
        'number_nf',
        'launch_number',
        'proof',
        'payment_prepared',
        'status',
        'date_paid',
        'has_allocation',
        'file_name',
        'observations',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
        'delete_reason',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'advance_amount' => 'decimal:2',
        'diff_payment_advance' => 'decimal:2',
        'date_payment' => 'date',
        'date_paid' => 'date',
        'deleted_at' => 'datetime',
        'advance' => 'boolean',
        'advance_paid' => 'boolean',
        'proof' => 'boolean',
        'payment_prepared' => 'boolean',
        'has_allocation' => 'boolean',
    ];

    // ==========================================
    // Status constants & labels
    // ==========================================

    public const STATUS_BACKLOG = 'backlog';
    public const STATUS_DOING = 'doing';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_DONE = 'done';

    public const STATUS_LABELS = [
        'backlog' => 'Solicitação',
        'doing' => 'Reg. Fiscal',
        'waiting' => 'Lançado',
        'done' => 'Pago',
    ];

    public const STATUS_COLORS = [
        'backlog' => 'gray',
        'doing' => 'blue',
        'waiting' => 'yellow',
        'done' => 'green',
    ];

    public const VALID_TRANSITIONS = [
        'backlog' => ['doing'],
        'doing' => ['backlog', 'waiting'],
        'waiting' => ['doing', 'done'],
        'done' => ['waiting'],
    ];

    public const PAYMENT_TYPES = [
        'PIX' => 'PIX',
        'Transferência' => 'Transferência Bancária',
        'Boleto' => 'Boleto',
        'Dinheiro' => 'Dinheiro',
        'Cartão' => 'Cartão',
        'Depósito' => 'Depósito',
    ];

    public const PIX_KEY_TYPES = [
        'cpf_cnpj' => 'CPF/CNPJ',
        'email' => 'E-mail',
        'phone' => 'Telefone',
        'random' => 'Chave Aleatória',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function installmentItems(): HasMany
    {
        return $this->hasMany(OrderPaymentInstallment::class, 'order_payment_id')
            ->orderBy('installment_number');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrderPaymentAllocation::class, 'order_payment_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderPaymentStatusHistory::class, 'order_payment_id')
            ->orderByDesc('created_at');
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

    public function getFormattedTotalAttribute(): string
    {
        return 'R$ ' . number_format($this->total_value, 2, ',', '.');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->date_payment
            && $this->date_payment->isPast()
            && $this->status !== self::STATUS_DONE;
    }

    public function getIsDeletedAttribute(): bool
    {
        return $this->deleted_at !== null;
    }

    // ==========================================
    // Status workflow
    // ==========================================

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$this->status] ?? []);
    }

    public function isForwardTransition(string $newStatus): bool
    {
        $order = [self::STATUS_BACKLOG, self::STATUS_DOING, self::STATUS_WAITING, self::STATUS_DONE];
        $currentIndex = array_search($this->status, $order);
        $newIndex = array_search($newStatus, $order);

        return $newIndex > $currentIndex;
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('date_payment', '<', today())
            ->where('status', '!=', self::STATUS_DONE)
            ->whereNull('deleted_at');
    }

    public function scopeForMonth($query, $month, $year)
    {
        return $query->whereMonth('created_at', $month)
            ->whereYear('created_at', $year);
    }
}
