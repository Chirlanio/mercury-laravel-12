<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HdAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id', 'interaction_id', 'original_filename',
        'stored_filename', 'file_path', 'mime_type',
        'size_bytes', 'uploaded_by_user_id',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HdTicket::class, 'ticket_id');
    }

    public function interaction(): BelongsTo
    {
        return $this->belongsTo(HdInteraction::class, 'interaction_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 0).' KB';
    }
}
