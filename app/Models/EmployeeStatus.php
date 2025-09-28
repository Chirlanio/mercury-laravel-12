<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeStatus extends Model
{
    use HasFactory;

    protected $table = 'employee_statuses';

    protected $fillable = [
        'description_name',
        'color_theme_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function colorTheme(): BelongsTo
    {
        return $this->belongsTo(ColorTheme::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public static function getOptions(): array
    {
        return static::pluck('description_name', 'id')->toArray();
    }

    public static function getByName(string $name): ?self
    {
        return static::where('description_name', $name)->first();
    }

    public static function getPending(): ?self
    {
        return static::where('description_name', 'Pendente')->first();
    }

    public static function getActive(): ?self
    {
        return static::where('description_name', 'Ativo')->first();
    }

    public static function getInactive(): ?self
    {
        return static::where('description_name', 'Inativo')->first();
    }

    public static function getVacation(): ?self
    {
        return static::where('description_name', 'Férias')->first();
    }

    public static function getLeave(): ?self
    {
        return static::where('description_name', 'Licença')->first();
    }

    public function isPending(): bool
    {
        return strtolower($this->description_name) === 'pendente';
    }

    public function isActive(): bool
    {
        return strtolower($this->description_name) === 'ativo';
    }

    public function isInactive(): bool
    {
        return strtolower($this->description_name) === 'inativo';
    }

    public function isOnVacation(): bool
    {
        return strtolower($this->description_name) === 'férias';
    }

    public function isOnLeave(): bool
    {
        return strtolower($this->description_name) === 'licença';
    }

    public function isWorkingStatus(): bool
    {
        return $this->isActive();
    }

    public function isUnavailableStatus(): bool
    {
        return $this->isOnVacation() || $this->isOnLeave() || $this->isInactive();
    }

    public function getColorCode(): ?string
    {
        return $this->colorTheme?->color_code;
    }

    public function getColorName(): ?string
    {
        return $this->colorTheme?->color_name;
    }

    public function hasColorTheme(): bool
    {
        return !is_null($this->color_theme_id) && $this->colorTheme()->exists();
    }

    public function changeColorTheme(ColorTheme $colorTheme): bool
    {
        $this->color_theme_id = $colorTheme->id;
        return $this->save();
    }

    public function getEmployeesCount(): int
    {
        return $this->employees()->count();
    }

    public function getActiveEmployeesCount(): int
    {
        return $this->employees()->active()->count();
    }

    public static function getWithColors(): array
    {
        return static::with('colorTheme')
                    ->get()
                    ->mapWithKeys(function ($employeeStatus) {
                        return [
                            $employeeStatus->id => [
                                'description_name' => $employeeStatus->description_name,
                                'color_code' => $employeeStatus->getColorCode(),
                                'color_name' => $employeeStatus->getColorName(),
                                'is_working' => $employeeStatus->isWorkingStatus(),
                                'is_unavailable' => $employeeStatus->isUnavailableStatus(),
                            ]
                        ];
                    })
                    ->toArray();
    }

    public static function getStatusTypes(): array
    {
        return [
            'working' => ['Ativo'],
            'pending' => ['Pendente'],
            'unavailable' => ['Férias', 'Licença'],
            'inactive' => ['Inativo'],
        ];
    }

    public function scopeWorking($query)
    {
        return $query->where('description_name', 'Ativo');
    }

    public function scopePending($query)
    {
        return $query->where('description_name', 'Pendente');
    }

    public function scopeUnavailable($query)
    {
        return $query->whereIn('description_name', ['Férias', 'Licença']);
    }

    public function scopeInactive($query)
    {
        return $query->where('description_name', 'Inativo');
    }
}
