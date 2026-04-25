<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TypeExpense extends Model
{
    protected $table = 'type_expenses';

    protected $fillable = [
        'name',
        'icon',
        'color',
        'accounting_class_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Vínculo opcional com a conta contábil. Quando preenchido, cada item
     * de prestação herda essa classe pra alimentar a matriz DRE no futuro.
     */
    public function accountingClass(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AccountingClass::class, 'accounting_class_id');
    }
}
