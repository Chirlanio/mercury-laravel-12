<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLevelPage extends Model
{
    use HasFactory;

    protected $table = 'access_level_pages';

    protected $fillable = [
        'permission',
        'order',
        'dropdown',
        'lib_menu',
        'menu_id',
        'access_level_id',
        'page_id',
    ];

    protected $casts = [
        'permission' => 'boolean',
        'order' => 'integer',
        'dropdown' => 'boolean',
        'lib_menu' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function accessLevel(): BelongsTo
    {
        return $this->belongsTo(AccessLevel::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function scopeWithPermission($query)
    {
        return $query->where('permission', true);
    }

    public function scopeWithoutPermission($query)
    {
        return $query->where('permission', false);
    }

    public function scopeDropdown($query)
    {
        return $query->where('dropdown', true);
    }

    public function scopeNonDropdown($query)
    {
        return $query->where('dropdown', false);
    }

    public function scopeLibMenu($query)
    {
        return $query->where('lib_menu', true);
    }

    public function scopeNonLibMenu($query)
    {
        return $query->where('lib_menu', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeByAccessLevel($query, $accessLevelId)
    {
        return $query->where('access_level_id', $accessLevelId);
    }

    public function scopeByMenu($query, $menuId)
    {
        return $query->where('menu_id', $menuId);
    }

    public function scopeByPage($query, $pageId)
    {
        return $query->where('page_id', $pageId);
    }

    public function getIsAuthorizedAttribute(): bool
    {
        return $this->permission;
    }

    public function getIsDropdownItemAttribute(): bool
    {
        return $this->dropdown;
    }

    public function getIsMenuLibraryAttribute(): bool
    {
        return $this->lib_menu;
    }

    public static function getPermissionOptions(): array
    {
        return [
            true => 'Permitido',
            false => 'Negado',
        ];
    }

    public static function getDropdownOptions(): array
    {
        return [
            true => 'Sim',
            false => 'Não',
        ];
    }

    public static function getLibMenuOptions(): array
    {
        return [
            true => 'Sim',
            false => 'Não',
        ];
    }

    public function grantPermission(): bool
    {
        $this->permission = true;
        return $this->save();
    }

    public function revokePermission(): bool
    {
        $this->permission = false;
        return $this->save();
    }

    public function enableDropdown(): bool
    {
        $this->dropdown = true;
        return $this->save();
    }

    public function disableDropdown(): bool
    {
        $this->dropdown = false;
        return $this->save();
    }

    public function enableLibMenu(): bool
    {
        $this->lib_menu = true;
        return $this->save();
    }

    public function disableLibMenu(): bool
    {
        $this->lib_menu = false;
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
        $maxOrder = static::where('access_level_id', $this->access_level_id)->max('order');
        if ($this->order < $maxOrder) {
            $this->order++;
            return $this->save();
        }
        return false;
    }

    public static function getForAccessLevel($accessLevelId): \Illuminate\Database\Eloquent\Collection
    {
        return static::with(['menu', 'page'])
            ->byAccessLevel($accessLevelId)
            ->withPermission()
            ->ordered()
            ->get();
    }

    public static function getMenuStructure($accessLevelId): array
    {
        $accessLevelPages = static::getForAccessLevel($accessLevelId);

        $structure = [];
        foreach ($accessLevelPages as $alp) {
            $menuName = $alp->menu->name ?? 'Sem Menu';

            if (!isset($structure[$menuName])) {
                $structure[$menuName] = [
                    'menu' => $alp->menu,
                    'pages' => []
                ];
            }

            $structure[$menuName]['pages'][] = $alp;
        }

        return $structure;
    }
}
