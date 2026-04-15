<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail de transições de status de ordens de compra. Imutável — sem
 * updated_at e sem trait Auditable (ele próprio é o audit trail).
 */
class PurchaseOrderStatusHistory extends Model
{
    protected $table = 'purchase_order_status_history';

    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'note',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
