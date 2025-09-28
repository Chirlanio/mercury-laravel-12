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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    public function getIconClassAttribute(): string
    {
        return $this->icon;
    }

    public function getIsMainMenuAttribute(): bool
    {
        return in_array($this->name, [
            'Home', 'Usuário', 'Produto', 'Planejamento', 'Financeiro',
            'Comercial', 'Delivery', 'E-commerce', 'Configurações'
        ]);
    }

    public function getIsUtilityMenuAttribute(): bool
    {
        return in_array($this->name, [
            'FAQ\'s', 'Movidesk', 'Biblioteca de Processos', 'Escola Digital'
        ]);
    }

    public function getIsHrMenuAttribute(): bool
    {
        return in_array($this->name, [
            'Pessoas & Cultura', 'Departamento Pessoal'
        ]);
    }

    public function getIsSystemMenuAttribute(): bool
    {
        return in_array($this->name, ['Dashboard\'s', 'Qualidade', 'Sair']);
    }

    public static function getGroupedOptions(): array
    {
        return [
            'Menu Principal' => static::active()
                ->whereIn('name', ['Home', 'Usuário', 'Produto', 'Planejamento', 'Financeiro', 'Comercial', 'Delivery', 'E-commerce'])
                ->orderBy('order')
                ->pluck('name', 'id')->toArray(),
            'Recursos Humanos' => static::active()
                ->whereIn('name', ['Pessoas & Cultura', 'Departamento Pessoal'])
                ->orderBy('order')
                ->pluck('name', 'id')->toArray(),
            'Utilidades' => static::active()
                ->whereIn('name', ['FAQ\'s', 'Movidesk', 'Biblioteca de Processos', 'Escola Digital'])
                ->orderBy('order')
                ->pluck('name', 'id')->toArray(),
            'Sistema' => static::active()
                ->whereIn('name', ['Dashboard\'s', 'Qualidade', 'Configurações', 'Sair'])
                ->orderBy('order')
                ->pluck('name', 'id')->toArray(),
        ];
    }

    public function scopeMainMenu($query)
    {
        return $query->whereIn('name', [
            'Home', 'Usuário', 'Produto', 'Planejamento', 'Financeiro',
            'Comercial', 'Delivery', 'E-commerce'
        ]);
    }

    public function scopeUtilityMenu($query)
    {
        return $query->whereIn('name', [
            'FAQ\'s', 'Movidesk', 'Biblioteca de Processos', 'Escola Digital'
        ]);
    }

    public function scopeHrMenu($query)
    {
        return $query->whereIn('name', [
            'Pessoas & Cultura', 'Departamento Pessoal'
        ]);
    }

    public function scopeSystemMenu($query)
    {
        return $query->whereIn('name', ['Dashboard\'s', 'Qualidade', 'Configurações', 'Sair']);
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
