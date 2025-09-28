<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PositionLevel extends Model
{
    use HasFactory;

    protected $table = 'position_levels';

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'position_level_id');
    }

    public static function getOptions(): array
    {
        return static::pluck('name', 'id')->toArray();
    }

    public static function getByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    public function getIsManagerialAttribute(): bool
    {
        return $this->name === 'Gerencial';
    }

    public function getIsOperationalAttribute(): bool
    {
        return $this->name === 'Operacional';
    }

    public function getIsApprenticeAttribute(): bool
    {
        return $this->name === 'Aprendiz';
    }

    public function scopeManagerial($query)
    {
        return $query->where('name', 'Gerencial');
    }

    public function scopeOperational($query)
    {
        return $query->where('name', 'Operacional');
    }

    public function scopeApprentice($query)
    {
        return $query->where('name', 'Aprendiz');
    }

    public static function getGroupedOptions(): array
    {
        return [
            'Liderança' => static::managerial()->pluck('name', 'id')->toArray(),
            'Execução' => static::operational()->pluck('name', 'id')->toArray(),
            'Desenvolvimento' => static::apprentice()->pluck('name', 'id')->toArray(),
        ];
    }

    public static function getTypes(): array
    {
        return [
            'managerial' => 'Gerencial',
            'operational' => 'Operacional',
            'apprentice' => 'Aprendiz',
        ];
    }

    public function getDescription(): string
    {
        return match ($this->name) {
            'Gerencial' => 'Posições de liderança e tomada de decisão estratégica',
            'Operacional' => 'Posições de execução e operação das atividades',
            'Aprendiz' => 'Posições de desenvolvimento e aprendizagem',
            default => 'Nível de cargo não especificado',
        };
    }

    public function getResponsibilities(): array
    {
        return match ($this->name) {
            'Gerencial' => [
                'Tomada de decisões estratégicas',
                'Gestão de equipes',
                'Planejamento e supervisão',
                'Responsabilidade por resultados'
            ],
            'Operacional' => [
                'Execução de tarefas diárias',
                'Operação de processos',
                'Cumprimento de metas',
                'Suporte às atividades'
            ],
            'Aprendiz' => [
                'Aprendizagem prática',
                'Desenvolvimento de habilidades',
                'Suporte às atividades básicas',
                'Crescimento profissional'
            ],
            default => ['Responsabilidades não definidas'],
        };
    }

    public function getLevel(): int
    {
        return match ($this->name) {
            'Gerencial' => 3,
            'Operacional' => 2,
            'Aprendiz' => 1,
            default => 0,
        };
    }

    public function getIsSeniorLevel(): bool
    {
        return $this->getLevel() >= 3;
    }

    public function getIsJuniorLevel(): bool
    {
        return $this->getLevel() <= 1;
    }

    public function canManageLevel(PositionLevel $level): bool
    {
        return $this->getLevel() > $level->getLevel();
    }

    public static function getHierarchy(): array
    {
        return [
            3 => 'Gerencial',
            2 => 'Operacional',
            1 => 'Aprendiz',
        ];
    }

    public function getEmployeeCount(): int
    {
        return $this->employees()->count();
    }

    public function getActiveEmployeeCount(): int
    {
        return $this->employees()->active()->count();
    }
}
