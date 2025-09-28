<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    use HasFactory;

    protected $table = 'sectors';

    protected $fillable = [
        'sector_name',
        'area_manager_id',
        'sector_manager_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function areaManager(): BelongsTo
    {
        return $this->belongsTo(Manager::class, 'area_manager_id');
    }

    public function sectorManager(): BelongsTo
    {
        return $this->belongsTo(Manager::class, 'sector_manager_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public static function getOptions(): array
    {
        return static::active()->pluck('sector_name', 'id')->toArray();
    }

    public static function getByName(string $name): ?self
    {
        return static::where('sector_name', $name)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeByAreaManager($query, $managerId)
    {
        return $query->where('area_manager_id', $managerId);
    }

    public function scopeBySectorManager($query, $managerId)
    {
        return $query->where('sector_manager_id', $managerId);
    }

    public function getIsOperationalAttribute(): bool
    {
        return in_array($this->sector_name, ['Qualidade', 'Lojas', 'Logística']);
    }

    public function getIsAdministrativeAttribute(): bool
    {
        return in_array($this->sector_name, ['Controladoria', 'Financeiro', 'Recursos Humanos']);
    }

    public function getIsCommercialAttribute(): bool
    {
        return in_array($this->sector_name, ['E-commerce', 'Marketing', 'Lojas']);
    }

    public function getIsTechnicalAttribute(): bool
    {
        return in_array($this->sector_name, ['Tecnologia da Informação', 'Qualidade']);
    }

    public static function getGroupedOptions(): array
    {
        return [
            'Operacional' => static::whereIn('sector_name', ['Qualidade', 'Lojas', 'Logística'])
                ->active()->pluck('sector_name', 'id')->toArray(),
            'Administrativo' => static::whereIn('sector_name', ['Controladoria', 'Financeiro', 'Recursos Humanos'])
                ->active()->pluck('sector_name', 'id')->toArray(),
            'Comercial' => static::whereIn('sector_name', ['E-commerce', 'Marketing'])
                ->active()->pluck('sector_name', 'id')->toArray(),
            'Técnico' => static::whereIn('sector_name', ['Tecnologia da Informação'])
                ->active()->pluck('sector_name', 'id')->toArray(),
        ];
    }

    public function scopeOperational($query)
    {
        return $query->whereIn('sector_name', ['Qualidade', 'Lojas', 'Logística']);
    }

    public function scopeAdministrative($query)
    {
        return $query->whereIn('sector_name', ['Controladoria', 'Financeiro', 'Recursos Humanos']);
    }

    public function scopeCommercial($query)
    {
        return $query->whereIn('sector_name', ['E-commerce', 'Marketing']);
    }

    public function scopeTechnical($query)
    {
        return $query->whereIn('sector_name', ['Tecnologia da Informação']);
    }

    public static function getTypes(): array
    {
        return [
            'operational' => 'Operacional',
            'administrative' => 'Administrativo',
            'commercial' => 'Comercial',
            'technical' => 'Técnico',
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

    public function getEmployeeCount(): int
    {
        return $this->employees()->count();
    }

    public function getActiveEmployeeCount(): int
    {
        return $this->employees()->active()->count();
    }

    public function hasAreaManager(): bool
    {
        return !is_null($this->area_manager_id) && $this->areaManager()->exists();
    }

    public function hasSectorManager(): bool
    {
        return !is_null($this->sector_manager_id) && $this->sectorManager()->exists();
    }

    public function getManagementStructure(): array
    {
        return [
            'area_manager' => $this->areaManager,
            'sector_manager' => $this->sectorManager,
            'employees_count' => $this->getEmployeeCount(),
            'active_employees_count' => $this->getActiveEmployeeCount(),
        ];
    }

    public function changeAreaManager(Manager $manager): bool
    {
        $this->area_manager_id = $manager->id;
        return $this->save();
    }

    public function changeSectorManager(Manager $manager): bool
    {
        $this->sector_manager_id = $manager->id;
        return $this->save();
    }

    public static function getHierarchy(): array
    {
        return static::with(['areaManager', 'sectorManager'])
                    ->active()
                    ->get()
                    ->groupBy(function ($sector) {
                        if ($sector->is_operational) return 'Operacional';
                        if ($sector->is_administrative) return 'Administrativo';
                        if ($sector->is_commercial) return 'Comercial';
                        if ($sector->is_technical) return 'Técnico';
                        return 'Outros';
                    });
    }

    public function canBeAccessedBy(Manager $manager): bool
    {
        return $this->area_manager_id === $manager->id ||
               $this->sector_manager_id === $manager->id;
    }
}
