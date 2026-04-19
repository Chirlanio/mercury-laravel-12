<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'budget_upload_id',
        'event',
        'from_active',
        'to_active',
        'note',
        'changed_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'from_active' => 'boolean',
        'to_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function upload(): BelongsTo
    {
        return $this->belongsTo(BudgetUpload::class, 'budget_upload_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
