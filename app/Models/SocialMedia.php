<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialMedia extends Model
{
    use HasFactory;

    protected $table = 'social_media';

    protected $fillable = [
        'name',
        'icon',
        'link_type',
        'link_placeholder',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Valida se um link combina com o tipo configurado pra essa rede social.
     * Aceita nulo (link é opcional). Normaliza usernames com/sem @.
     */
    public function validateLink(?string $link): bool
    {
        if ($link === null || trim($link) === '') {
            return true; // link é opcional
        }

        $link = trim($link);

        if ($this->link_type === 'username') {
            // Aceita @user, user, ou URL completa da plataforma
            return (bool) preg_match('/^@?[A-Za-z0-9_.]+$/', $link)
                || (bool) preg_match('~^https?://~i', $link);
        }

        // url ou fallback
        return (bool) preg_match('~^https?://~i', $link);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
