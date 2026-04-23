<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail de transições da state machine da Consignação. Uma linha
 * por transição, gravada por ConsignmentTransitionService. Alimenta a
 * timeline do modal de detalhes (StandardModal.Timeline).
 *
 * `from_status` é null na criação inicial (draft). `context` carrega
 * metadados arbitrários (override de bloqueio, justificativa, payload
 * de reconciliação CIGAM, etc.).
 */
class ConsignmentStatusHistory extends Model
{
    protected $table = 'consignment_status_histories';

    /**
     * Apenas created_at — transições são imutáveis após gravadas.
     */
    public $timestamps = false;

    protected $fillable = [
        'consignment_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'note',
        'context',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'context' => 'array',
    ];

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
