<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Manager extends Model
{
    use HasFactory;

    protected $table = 'managers';

    protected $fillable = [
        'name',
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function getOptions(): array
    {
        return static::active()->pluck('name', 'id')->toArray();
    }

    public static function getByEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }

    public static function getByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function getInitials(): string
    {
        $words = explode(' ', $this->name);
        $initials = '';

        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $initials .= strtoupper(substr($word, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }

        return $initials ?: strtoupper(substr($this->name, 0, 2));
    }

    public function getAvatarUrlAttribute(): string
    {
        $initials = $this->getInitials();
        return "https://ui-avatars.com/api/?name={$initials}&background=8b5cf6&color=ffffff&size=200";
    }

    public function getFirstNameAttribute(): string
    {
        return explode(' ', $this->name)[0];
    }

    public function getLastNameAttribute(): string
    {
        $nameParts = explode(' ', $this->name);
        return count($nameParts) > 1 ? end($nameParts) : '';
    }

    public function getDomainAttribute(): string
    {
        return substr(strrchr($this->email, "@"), 1);
    }

    public function getIsCompanyEmailAttribute(): bool
    {
        return str_contains($this->email, '@meiasola.com.br');
    }

    public static function getGroupedOptions(): array
    {
        return [
            'Ativos' => static::active()->pluck('name', 'id')->toArray(),
            'Inativos' => static::inactive()->pluck('name', 'id')->toArray(),
        ];
    }

    public function scopeByDomain($query, string $domain)
    {
        return $query->where('email', 'like', "%@{$domain}");
    }

    public function scopeCompanyEmail($query)
    {
        return $query->where('email', 'like', '%@meiasola.com.br');
    }

    public static function getTypes(): array
    {
        return [
            'active' => 'Ativo',
            'inactive' => 'Inativo',
        ];
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
}
