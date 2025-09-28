<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccessLevel extends Model
{
    use HasFactory;

    protected $table = 'access_levels';

    protected $fillable = [
        'name',
        'order',
        'color_theme_id',
    ];

    protected $casts = [
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function colorTheme(): BelongsTo
    {
        return $this->belongsTo(ColorTheme::class);
    }

    public function accessLevelPages(): HasMany
    {
        return $this->hasMany(AccessLevelPage::class);
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'access_level_pages')
                    ->withPivot(['permission', 'order', 'dropdown', 'lib_menu', 'menu_id'])
                    ->withTimestamps();
    }

    public function authorizedPages(): BelongsToMany
    {
        return $this->pages()->wherePivot('permission', true);
    }

    public static function getOptions(): array
    {
        return static::orderBy('order')->pluck('name', 'id')->toArray();
    }

    public static function getByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeAdministrative($query)
    {
        return $query->whereIn('name', ['Super Administrador', 'Administrador', 'Suporte']);
    }

    public function scopeOperational($query)
    {
        return $query->whereIn('name', [
            'Operações', 'Logistica', 'Logistica Nível 1', 'Qualidade',
            'Estoque', 'Motorista'
        ]);
    }

    public function scopeFinancial($query)
    {
        return $query->whereIn('name', [
            'Financeiro', 'Financeiro Nível 1', 'Tesouraria', 'Contábil'
        ]);
    }

    public function scopeHumanResources($query)
    {
        return $query->whereIn('name', [
            'Pessoas & Cultura', 'Departamento Pessoal', 'Departamento Pessoal Nível 1'
        ]);
    }

    public function scopeCommercial($query)
    {
        return $query->whereIn('name', [
            'Supervisão Comercial', 'E-commerce', 'Marketing', 'Loja'
        ]);
    }

    public function scopeManagement($query)
    {
        return $query->whereIn('name', ['Gerencial', 'Planejamento']);
    }

    public function getIsAdministrativeAttribute(): bool
    {
        return in_array($this->name, ['Super Administrador', 'Administrador', 'Suporte']);
    }

    public function getIsOperationalAttribute(): bool
    {
        return in_array($this->name, [
            'Operações', 'Logistica', 'Logistica Nível 1', 'Qualidade',
            'Estoque', 'Motorista'
        ]);
    }

    public function getIsFinancialAttribute(): bool
    {
        return in_array($this->name, [
            'Financeiro', 'Financeiro Nível 1', 'Tesouraria', 'Contábil'
        ]);
    }

    public function getIsHumanResourcesAttribute(): bool
    {
        return in_array($this->name, [
            'Pessoas & Cultura', 'Departamento Pessoal', 'Departamento Pessoal Nível 1'
        ]);
    }

    public function getIsCommercialAttribute(): bool
    {
        return in_array($this->name, [
            'Supervisão Comercial', 'E-commerce', 'Marketing', 'Loja'
        ]);
    }

    public function getIsManagementAttribute(): bool
    {
        return in_array($this->name, ['Gerencial', 'Planejamento']);
    }

    public function getIsSuperAdminAttribute(): bool
    {
        return $this->name === 'Super Administrador';
    }

    public function getIsLevel1Attribute(): bool
    {
        return str_contains($this->name, 'Nível 1');
    }

    public function getColorAttribute(): string
    {
        return $this->colorTheme->color ?? '#6B7280';
    }

    public function getColorClassAttribute(): string
    {
        return $this->colorTheme->bootstrap_class ?? 'secondary';
    }

    public static function getGroupedOptions(): array
    {
        return [
            'Administrativo' => static::administrative()->pluck('name', 'id')->toArray(),
            'Operacional' => static::operational()->pluck('name', 'id')->toArray(),
            'Financeiro' => static::financial()->pluck('name', 'id')->toArray(),
            'Recursos Humanos' => static::humanResources()->pluck('name', 'id')->toArray(),
            'Comercial' => static::commercial()->pluck('name', 'id')->toArray(),
            'Gerencial' => static::management()->pluck('name', 'id')->toArray(),
            'Outros' => static::whereNotIn('name', [
                'Super Administrador', 'Administrador', 'Suporte',
                'Operações', 'Logistica', 'Logistica Nível 1', 'Qualidade', 'Estoque', 'Motorista',
                'Financeiro', 'Financeiro Nível 1', 'Tesouraria', 'Contábil',
                'Pessoas & Cultura', 'Departamento Pessoal', 'Departamento Pessoal Nível 1',
                'Supervisão Comercial', 'E-commerce', 'Marketing', 'Loja',
                'Gerencial', 'Planejamento'
            ])->pluck('name', 'id')->toArray(),
        ];
    }

    public static function getCategories(): array
    {
        return [
            'administrative' => 'Administrativo',
            'operational' => 'Operacional',
            'financial' => 'Financeiro',
            'human_resources' => 'Recursos Humanos',
            'commercial' => 'Comercial',
            'management' => 'Gerencial',
            'other' => 'Outros',
        ];
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

    public static function reorderLevels(array $levelIds): void
    {
        foreach ($levelIds as $index => $levelId) {
            static::where('id', $levelId)->update(['order' => $index + 1]);
        }
    }

    public function hasPermissionToPage($pageId): bool
    {
        return $this->accessLevelPages()
                    ->where('page_id', $pageId)
                    ->where('permission', true)
                    ->exists();
    }

    public function getAuthorizedMenuStructure(): array
    {
        return AccessLevelPage::getMenuStructure($this->id);
    }
}
