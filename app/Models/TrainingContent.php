<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TrainingContent extends Model
{
    use Auditable;

    // Content types
    public const TYPE_VIDEO = 'video';

    public const TYPE_AUDIO = 'audio';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_LINK = 'link';

    public const TYPE_TEXT = 'text';

    public const TYPE_LABELS = [
        self::TYPE_VIDEO => 'Video',
        self::TYPE_AUDIO => 'Audio',
        self::TYPE_DOCUMENT => 'Documento',
        self::TYPE_LINK => 'Link',
        self::TYPE_TEXT => 'Texto',
    ];

    public const TYPE_ICONS = [
        self::TYPE_VIDEO => 'VideoCameraIcon',
        self::TYPE_AUDIO => 'MusicalNoteIcon',
        self::TYPE_DOCUMENT => 'DocumentTextIcon',
        self::TYPE_LINK => 'LinkIcon',
        self::TYPE_TEXT => 'DocumentIcon',
    ];

    public const TYPE_COLORS = [
        self::TYPE_VIDEO => 'purple',
        self::TYPE_AUDIO => 'blue',
        self::TYPE_DOCUMENT => 'red',
        self::TYPE_LINK => 'green',
        self::TYPE_TEXT => 'gray',
    ];

    // File upload limits (bytes)
    public const MAX_SIZE_VIDEO = 500 * 1024 * 1024; // 500MB

    public const MAX_SIZE_AUDIO = 100 * 1024 * 1024; // 100MB

    public const MAX_SIZE_DOCUMENT = 50 * 1024 * 1024; // 50MB

    // Allowed extensions
    public const EXTENSIONS = [
        self::TYPE_VIDEO => ['mp4', 'webm', 'ogg'],
        self::TYPE_AUDIO => ['mp3', 'wav', 'ogg', 'm4a'],
        self::TYPE_DOCUMENT => ['pdf', 'ppt', 'pptx', 'doc', 'docx'],
    ];

    protected $fillable = [
        'hash_id', 'title', 'description', 'content_type',
        'file_path', 'file_name', 'file_size', 'file_mime_type',
        'external_url', 'text_content', 'duration_seconds',
        'thumbnail_path', 'category_id', 'is_active',
        'created_by_user_id', 'updated_by_user_id',
        'deleted_at', 'deleted_by_user_id',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration_seconds' => 'integer',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // Boot

    protected static function boot()
    {
        parent::boot();

        static::creating(function (TrainingContent $content) {
            if (empty($content->hash_id)) {
                $content->hash_id = (string) Str::uuid();
            }
        });
    }

    // Accessors

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->content_type] ?? $this->content_type;
    }

    public function getTypeIconAttribute(): string
    {
        return self::TYPE_ICONS[$this->content_type] ?? 'DocumentIcon';
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPE_COLORS[$this->content_type] ?? 'gray';
    }

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    public function getFileSizeFormattedAttribute(): ?string
    {
        if (! $this->file_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->file_size;
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i > 0 ? 1 : 0, ',', '.').' '.$units[$i];
    }

    public function getDurationFormattedAttribute(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }

        $hours = intdiv($this->duration_seconds, 3600);
        $minutes = intdiv($this->duration_seconds % 3600, 60);
        $seconds = $this->duration_seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh%02dmin', $hours, $minutes);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function getIsFileTypeAttribute(): bool
    {
        return in_array($this->content_type, [self::TYPE_VIDEO, self::TYPE_AUDIO, self::TYPE_DOCUMENT]);
    }

    // Relationships

    public function category(): BelongsTo
    {
        return $this->belongsTo(TrainingContentCategory::class, 'category_id');
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

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('content_type', $type);
    }

    public function scopeForCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }
}
