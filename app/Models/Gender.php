<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gender extends Model
{
    use HasFactory;

    protected $table = 'genders';

    protected $fillable = [
        'description_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public static function getOptions(): array
    {
        return static::active()->pluck('description_name', 'id')->toArray();
    }

    public static function getByName(string $name): ?self
    {
        return static::where('description_name', $name)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function activate(): bool
    {
        $this->is_active = true;
        return $this->save();
    }

    public function deactivate(): bool
    {
        $this->is_active = false;
        return $this->save();
    }

    public function getEmployeeCount(): int
    {
        return $this->employees()->count();
    }

    public function getActiveEmployeeCount(): int
    {
        return $this->employees()->active()->count();
    }

    public function isFemale(): bool
    {
        return strtolower($this->description_name) === 'feminino';
    }

    public function isMale(): bool
    {
        return strtolower($this->description_name) === 'masculino';
    }
}
