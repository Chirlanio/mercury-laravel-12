<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DamagedProductPhoto extends Model
{
    use Auditable;

    protected $fillable = [
        'damaged_product_id',
        'filename',
        'original_filename',
        'file_path',
        'file_size',
        'mime_type',
        'caption',
        'sort_order',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'damaged_product_id' => 'integer',
        'file_size' => 'integer',
        'sort_order' => 'integer',
    ];

    public function damagedProduct(): BelongsTo
    {
        return $this->belongsTo(DamagedProduct::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }
}
