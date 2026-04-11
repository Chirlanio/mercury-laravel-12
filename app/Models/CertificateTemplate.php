<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    use Auditable;

    // Available placeholders for HTML templates
    public const PLACEHOLDERS = [
        '{{participant_name}}',
        '{{training_title}}',
        '{{training_date}}',
        '{{duration}}',
        '{{subject}}',
        '{{facilitator_name}}',
        '{{certificate_code}}',
    ];

    protected $fillable = [
        'name', 'html_template', 'background_image',
        'is_default', 'is_active',
        'created_by_user_id', 'updated_by_user_id',
        'deleted_at', 'deleted_by_user_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // Relationships

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class, 'certificate_template_id');
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

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    // Accessors

    public function getIsDeletedAttribute(): bool
    {
        return ! is_null($this->deleted_at);
    }

    // Methods

    public function renderHtml(array $data): string
    {
        $html = $this->html_template;

        foreach ($data as $placeholder => $value) {
            $html = str_replace('{{'.$placeholder.'}}', e($value), $html);
        }

        return $html;
    }
}
