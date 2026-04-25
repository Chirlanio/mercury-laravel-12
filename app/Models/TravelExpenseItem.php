<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de prestação de contas — substitui adms_travel_expense_reimbursements
 * da v1. Cada linha é um gasto durante a viagem (almoço, taxi, hotel, etc)
 * com tipo, valor, data, NF/recibo opcional e comprovante anexado.
 *
 * Comprovante:
 *  - attachment_path: caminho relativo no disk 'tenant_assets' sob
 *    travel-expenses/{ulid}/{filename}
 *  - O upload em si é gerenciado por TravelExpenseAccountabilityService —
 *    o Model só armazena o caminho.
 *
 * Soft delete manual.
 */
class TravelExpenseItem extends Model
{
    use Auditable;

    protected $table = 'travel_expense_items';

    protected $fillable = [
        'travel_expense_id',
        'type_expense_id',
        'expense_date',
        'value',
        'invoice_number',
        'description',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_at',
        'deleted_by_user_id',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'value' => 'decimal:2',
        'attachment_size' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function getHasAttachmentAttribute(): bool
    {
        return ! empty($this->attachment_path);
    }

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    /**
     * Tamanho formatado em KB/MB para exibição.
     */
    public function getAttachmentSizeFormattedAttribute(): ?string
    {
        if (! $this->attachment_size) {
            return null;
        }

        $bytes = (int) $this->attachment_size;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('travel_expense_items.deleted_at');
    }

    public function travelExpense(): BelongsTo
    {
        return $this->belongsTo(TravelExpense::class);
    }

    public function typeExpense(): BelongsTo
    {
        return $this->belongsTo(TypeExpense::class);
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
}
