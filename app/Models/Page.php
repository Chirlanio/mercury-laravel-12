<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Page extends Model
{
    use HasFactory;

    protected $table = 'pages';

    protected $fillable = [
        'controller',
        'method',
        'menu_controller',
        'menu_method',
        'route',
        'page_name',
        'notes',
        'is_public',
        'icon',
        'page_group_id',
        'is_active',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pageGroup(): BelongsTo
    {
        return $this->belongsTo(PageGroup::class);
    }

    public function accessLevelPages(): HasMany
    {
        return $this->hasMany(AccessLevelPage::class);
    }

    public function accessLevels(): BelongsToMany
    {
        return $this->belongsToMany(AccessLevel::class, 'access_level_pages')
                    ->withPivot(['permission', 'order', 'dropdown', 'lib_menu', 'menu_id'])
                    ->withTimestamps();
    }

    public function authorizedAccessLevels(): BelongsToMany
    {
        return $this->accessLevels()->wherePivot('permission', true);
    }

    public static function getOptions(): array
    {
        return static::active()->pluck('page_name', 'id')->toArray();
    }

    public static function getByController(string $controller, string $method = null): ?\Illuminate\Database\Eloquent\Collection
    {
        $query = static::where('controller', $controller);

        if ($method) {
            $query->where('method', $method);
        }

        return $query->get();
    }

    public static function getByRoute(string $controller, string $method): ?self
    {
        return static::where('controller', $controller)
                    ->where('method', $method)
                    ->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_public', false);
    }

    public function scopeByGroup($query, $groupId)
    {
        return $query->where('page_group_id', $groupId);
    }

    public function scopeByController($query, string $controller)
    {
        return $query->where('controller', $controller);
    }

    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', $method);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('page_name', 'asc');
    }

    /**
     * Get the controller@method string (legacy format for display)
     */
    public function getControllerMethodAttribute(): string
    {
        return $this->controller . '@' . $this->method;
    }

    /**
     * Get the menu_controller@menu_method string (legacy format for display)
     */
    public function getMenuRouteAttribute(): string
    {
        return $this->menu_controller . '@' . $this->menu_method;
    }

    public function getFullNameAttribute(): string
    {
        return $this->pageGroup->name . ' - ' . $this->page_name;
    }

    public function getIsListingAttribute(): bool
    {
        return $this->pageGroup->name === 'Listar' || str_contains(strtolower($this->page_name), 'listar');
    }

    public function getIsCreatingAttribute(): bool
    {
        return $this->pageGroup->name === 'Cadastrar' || str_contains(strtolower($this->page_name), 'cadastrar');
    }

    public function getIsEditingAttribute(): bool
    {
        return $this->pageGroup->name === 'Editar' || str_contains(strtolower($this->page_name), 'editar');
    }

    public function getIsDeletingAttribute(): bool
    {
        return $this->pageGroup->name === 'Apagar' || str_contains(strtolower($this->page_name), 'apagar');
    }

    public function getIsViewingAttribute(): bool
    {
        return $this->pageGroup->name === 'Visualizar' || str_contains(strtolower($this->page_name), 'visualizar');
    }

    public function getIsSearchingAttribute(): bool
    {
        return $this->pageGroup->name === 'Pesquisar' || str_contains(strtolower($this->page_name), 'pesquisar');
    }

    public static function getGroupedOptions(): array
    {
        $pages = static::with('pageGroup')->active()->get();
        $grouped = [];

        foreach ($pages as $page) {
            $groupName = $page->pageGroup->name ?? 'Outros';
            if (!isset($grouped[$groupName])) {
                $grouped[$groupName] = [];
            }
            $grouped[$groupName][$page->id] = $page->page_name;
        }

        return $grouped;
    }

    public static function getByGroupType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereHas('pageGroup', function ($query) use ($type) {
            $query->where('name', $type);
        })->active()->get();
    }

    public static function getCrudPages(): array
    {
        return [
            'listing' => static::getByGroupType('Listar'),
            'creating' => static::getByGroupType('Cadastrar'),
            'editing' => static::getByGroupType('Editar'),
            'deleting' => static::getByGroupType('Apagar'),
            'viewing' => static::getByGroupType('Visualizar'),
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

    public function makePublic(): bool
    {
        $this->is_public = true;
        return $this->save();
    }

    public function makePrivate(): bool
    {
        $this->is_public = false;
        return $this->save();
    }

    public function hasAccessLevel($accessLevelId): bool
    {
        return $this->accessLevelPages()
                    ->where('access_level_id', $accessLevelId)
                    ->where('permission', true)
                    ->exists();
    }

    public function getAuthorizedAccessLevelsList(): array
    {
        return $this->authorizedAccessLevels()->pluck('name', 'id')->toArray();
    }

    public function canBeAccessedBy(AccessLevel $accessLevel): bool
    {
        if ($this->is_public) {
            return true;
        }

        return $this->hasAccessLevel($accessLevel->id);
    }

    public static function getControllerMethods(): array
    {
        return static::select('controller', 'method')
                    ->distinct()
                    ->get()
                    ->groupBy('controller')
                    ->map(function ($pages) {
                        return $pages->pluck('method')->toArray();
                    })
                    ->toArray();
    }

    public function getAccessLevelCount(): int
    {
        return $this->authorizedAccessLevels()->count();
    }

    public function isAccessibleToAll(): bool
    {
        return $this->is_public || $this->getAccessLevelCount() === AccessLevel::count();
    }
}
