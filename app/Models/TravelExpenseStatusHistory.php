<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha do audit-trail de transições de status. Capa tanto transições do
 * TravelExpenseStatus quanto do AccountabilityStatus — `kind` distingue:
 *  - 'expense'        → from/to são valores de TravelExpenseStatus
 *  - 'accountability' → from/to são valores de AccountabilityStatus
 *
 * Imutável após criada (sem updated_at). Usada na timeline do modal de
 * detalhes (StandardModal.Timeline).
 */
class TravelExpenseStatusHistory extends Model
{
    public const KIND_EXPENSE = 'expense';
    public const KIND_ACCOUNTABILITY = 'accountability';

    public $timestamps = false;

    protected $table = 'travel_expense_status_histories';

    protected $fillable = [
        'travel_expense_id',
        'kind',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'note',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function scopeExpenseKind(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_EXPENSE);
    }

    public function scopeAccountabilityKind(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_ACCOUNTABILITY);
    }

    public function travelExpense(): BelongsTo
    {
        return $this->belongsTo(TravelExpense::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
