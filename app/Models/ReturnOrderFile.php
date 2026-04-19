<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnOrderFile extends Model
{
    protected $table = 'return_order_files';

    protected $fillable = [
        'return_order_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function returnOrder(): BelongsTo
    {
        return $this->belongsTo(ReturnOrder::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
