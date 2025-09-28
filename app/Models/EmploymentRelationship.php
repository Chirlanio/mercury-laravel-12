<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmploymentRelationship extends Model
{
    use HasFactory;

    protected $table = 'employment_relationships';

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all employees with this employment relationship
     * Note: This would require adding employment_relationship_id to employees table
     */
    // public function employees(): HasMany
    // {
    //     return $this->hasMany(Employee::class);
    // }

    /**
     * Get all employment relationships as options for select
     */
    public static function getOptions(): array
    {
        return static::pluck('name', 'id')->toArray();
    }

    /**
     * Get employment relationship by name
     */
    public static function getByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }

    /**
     * Check if this is a permanent employment relationship
     */
    public function getIsPermanentAttribute(): bool
    {
        return str_contains(strtolower($this->name), 'efetivo');
    }

    /**
     * Check if this is a temporary employment relationship
     */
    public function getIsTemporaryAttribute(): bool
    {
        return str_contains(strtolower($this->name), 'temporário');
    }

    /**
     * Check if this is an internship
     */
    public function getIsInternshipAttribute(): bool
    {
        return str_contains(strtolower($this->name), 'estagiário');
    }

    /**
     * Check if this is an apprenticeship
     */
    public function getIsApprenticeshipAttribute(): bool
    {
        return str_contains(strtolower($this->name), 'aprendiz');
    }

    /**
     * Get employment relationship types grouped by category
     */
    public static function getGroupedOptions(): array
    {
        return [
            'Efetivos' => static::where('name', 'like', '%efetivo%')->pluck('name', 'id')->toArray(),
            'Temporários' => static::where('name', 'like', '%temporário%')->pluck('name', 'id')->toArray(),
            'Estágios/Aprendizagem' => static::whereIn('name', ['Estagiário', 'Jovem aprendiz'])->pluck('name', 'id')->toArray(),
        ];
    }

    /**
     * Scope to get permanent relationships
     */
    public function scopePermanent($query)
    {
        return $query->where('name', 'like', '%efetivo%');
    }

    /**
     * Scope to get temporary relationships
     */
    public function scopeTemporary($query)
    {
        return $query->where('name', 'like', '%temporário%');
    }

    /**
     * Scope to get internship/apprenticeship relationships
     */
    public function scopeTraining($query)
    {
        return $query->whereIn('name', ['Estagiário', 'Jovem aprendiz']);
    }

    /**
     * Get all available employment relationship types
     */
    public static function getTypes(): array
    {
        return [
            'permanent' => 'Colaborador efetivo',
            'temporary' => 'Colaborador temporário',
            'internship' => 'Estagiário',
            'apprenticeship' => 'Jovem aprendiz',
        ];
    }
}
