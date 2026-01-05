<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Menu extends Model
{
    use HasFactory;

    protected $table = 'menus';

    protected $fillable = [
        'name',
        'icon',
        'order',
        'is_active',
        'parent_id',
        'type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'parent_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relacionamentos
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->where('is_active', true)->orderBy('order');
    }

    public function allChildren()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('order');
    }

    public static function getOptions(): array
    {
        return static::active()->orderBy('order')->pluck('name', 'id')->toArray();
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeParentMenus($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeSubmenus($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function getIconClassAttribute(): string
    {
        return $this->icon;
    }

    public function getIsMainMenuAttribute(): bool
    {
        return $this->type === 'main';
    }

    public function getIsUtilityMenuAttribute(): bool
    {
        return $this->type === 'utility';
    }

    public function getIsHrMenuAttribute(): bool
    {
        return $this->type === 'hr';
    }

    public function getIsSystemMenuAttribute(): bool
    {
        return $this->type === 'system';
    }

    public static function getGroupedOptions(): array
    {
        return [
            'Menu Principal' => static::active()
                ->where('type', 'main')
                ->orderBy('order')
                ->pluck('name', 'id')->toArray(),
            'Recursos Humanos' => static::active()
                ->where('type', 'hr')
                ->orderBy('order')
                ->pluck('name', 'id')->toArray(),
            'Utilidades' => static::active()
                ->where('type', 'utility')
                ->orderBy('order')
                ->pluck('name', 'id')->toArray(),
            'Sistema' => static::active()
                ->where('type', 'system')
                ->orderBy('order')
                ->pluck('name', 'id')->toArray(),
        ];
    }

    public function scopeMainMenu($query)
    {
        return $query->where('type', 'main');
    }

    public function scopeUtilityMenu($query)
    {
        return $query->where('type', 'utility');
    }

    public function scopeHrMenu($query)
    {
        return $query->where('type', 'hr');
    }

    public function scopeSystemMenu($query)
    {
        return $query->where('type', 'system');
    }

    public static function getTypes(): array
    {
        return [
            'main' => 'Menu Principal',
            'hr' => 'Recursos Humanos',
            'utility' => 'Utilidades',
            'system' => 'Sistema',
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

    public function moveUp(): bool
    {
        if ($this->order > 1) {
            $this->order--;
            return $this->save();
        }
        return false;
    }

    public function moveDown(): bool
    {
        $maxOrder = static::max('order');
        if ($this->order < $maxOrder) {
            $this->order++;
            return $this->save();
        }
        return false;
    }

    public static function reorderMenus(array $menuIds): void
    {
        foreach ($menuIds as $index => $menuId) {
            static::where('id', $menuId)->update(['order' => $index + 1]);
        }
    }
}
