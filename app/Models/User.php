<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Enums\Permission;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nickname',
        'email',
        'username',
        'password',
        'role',
        'avatar',
        'access_level_id',
        'store_id',
        'area_id',
        'status_id',
        'email_confirmation_key',
        'unsubscribe_key',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['avatar_url'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    public function hasPermission(Role $requiredRole): bool
    {
        return $this->role->hasPermission($requiredRole);
    }

    public function hasPermissionTo(Permission|string $permission): bool
    {
        return $this->role->hasPermissionTo($permission);
    }

    public function canEditUser(User $targetUser): bool
    {
        return $this->role->canEditUser($this, $targetUser);
    }

    public function canManageRole(Role $targetRole): bool
    {
        return $this->role->canManageRole($targetRole);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN;
    }

    public function isSupport(): bool
    {
        return $this->role === Role::SUPPORT;
    }

    public function isUser(): bool
    {
        return $this->role === Role::USER;
    }

    /**
     * Atributo descritivo para logs de auditoria
     */
    public function getDescriptiveAttribute(): string
    {
        return "{$this->name} ({$this->email})";
    }

    /**
     * Obtém a URL completa do avatar do usuário
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/avatars/' . $this->avatar);
        }

        // Retorna avatar padrão baseado nas iniciais do nome
        return $this->getDefaultAvatarUrl();
    }

    /**
     * Obtém avatar padrão com iniciais
     */
    public function getDefaultAvatarUrl(): string
    {
        $initials = $this->getInitials();
        $backgroundColor = $this->getAvatarBackgroundColor();

        // Usar serviço de avatar com iniciais (UI Avatars ou similar)
        return "https://ui-avatars.com/api/?name={$initials}&size=200&background={$backgroundColor}&color=ffffff&bold=true";
    }

    /**
     * Obtém as iniciais do nome do usuário
     */
    public function getInitials(): string
    {
        $words = explode(' ', $this->name);
        $initials = '';

        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper(substr($word, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }

        return $initials ?: 'U';
    }

    /**
     * Gera cor de fundo baseada no nome do usuário
     */
    private function getAvatarBackgroundColor(): string
    {
        $colors = [
            '3B82F6', '8B5CF6', 'EF4444', 'F59E0B',
            '10B981', 'F97316', '6366F1', 'EC4899',
            '84CC16', '06B6D4', 'F59E0B', '8B5CF6'
        ];

        $index = crc32($this->name) % count($colors);
        return $colors[abs($index)];
    }

    /**
     * Verifica se o usuário tem avatar personalizado
     */
    public function hasCustomAvatar(): bool
    {
        return !empty($this->avatar) && file_exists(storage_path('app/public/' . $this->avatar));
    }

    /**
     * Remove o avatar atual do usuário
     */
    public function removeAvatar(): bool
    {
        if ($this->avatar && file_exists(storage_path('app/public/' . $this->avatar))) {
            unlink(storage_path('app/public/' . $this->avatar));
        }

        $this->avatar = null;
        return $this->save();
    }

    /**
     * Get the store that the user belongs to
     */
    public function store(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Store::class, 'store_id', 'code');
    }

    /**
     * Get the access level that the user belongs to
     */
    public function accessLevel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\AccessLevel::class);
    }
}
