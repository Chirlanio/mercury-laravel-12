<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageGroup extends Model
{
    use HasFactory;

    protected $table = 'page_groups';

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function activePages(): HasMany
    {
        return $this->hasMany(Page::class)->where('is_active', true);
    }

    public static function getOptions(): array
    {
        return static::orderBy('name')->pluck('name', 'id')->toArray();
    }

    public function getPagesCountAttribute(): int
    {
        return $this->pages()->count();
    }

    public function getActivePagesCountAttribute(): int
    {
        return $this->activePages()->count();
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    public function scopeWithPagesCount($query)
    {
        return $query->withCount('pages');
    }

    public function canBeDeleted(): bool
    {
        return $this->pages()->count() === 0;
    }
}
