<?php

namespace App\Models;

use App\Enums\RelocationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail de transições de status de remanejo. Linhas gravadas pelo
 * RelocationTransitionService — nunca instanciar direto em controllers.
 */
class RelocationStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'relocation_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'note',
        'created_at',
    ];

    protected $casts = [
        'from_status' => RelocationStatus::class,
        'to_status' => RelocationStatus::class,
        'created_at' => 'datetime',
    ];

    public function relocation(): BelongsTo
    {
        return $this->belongsTo(Relocation::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
