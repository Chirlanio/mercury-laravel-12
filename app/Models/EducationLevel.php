<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationLevel extends Model
{
    use HasFactory;

    protected $table = 'education_levels';

    protected $fillable = [
        'description_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public static function getOptions(): array
    {
        return static::active()->pluck('description_name', 'id')->toArray();
    }

    public static function getByName(string $name): ?self
    {
        return static::where('description_name', $name)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function getIsBasicEducationAttribute(): bool
    {
        return str_contains(strtolower($this->description_name), 'fundamental');
    }

    public function getIsSecondaryEducationAttribute(): bool
    {
        return str_contains(strtolower($this->description_name), 'médio');
    }

    public function getIsHigherEducationAttribute(): bool
    {
        return str_contains(strtolower($this->description_name), 'superior');
    }

    public function getIsPostGraduateAttribute(): bool
    {
        return str_contains(strtolower($this->description_name), 'pós-graduação') ||
               str_contains(strtolower($this->description_name), 'mestrado') ||
               str_contains(strtolower($this->description_name), 'doutorado');
    }

    public function getIsCompleteAttribute(): bool
    {
        return str_contains(strtolower($this->description_name), 'completo') ||
               in_array(strtolower($this->description_name), ['mestrado', 'doutorado']);
    }

    public function getIsIncompleteAttribute(): bool
    {
        return str_contains(strtolower($this->description_name), 'incompleto');
    }

    public static function getGroupedOptions(): array
    {
        return [
            'Educação Básica' => static::active()
                ->where('description_name', 'like', '%fundamental%')
                ->pluck('description_name', 'id')->toArray(),
            'Ensino Médio' => static::active()
                ->where('description_name', 'like', '%médio%')
                ->pluck('description_name', 'id')->toArray(),
            'Ensino Superior' => static::active()
                ->where('description_name', 'like', '%superior%')
                ->pluck('description_name', 'id')->toArray(),
            'Pós-Graduação' => static::active()
                ->where(function ($query) {
                    $query->where('description_name', 'like', '%pós-graduação%')
                          ->orWhereIn('description_name', ['Mestrado', 'Doutorado']);
                })
                ->pluck('description_name', 'id')->toArray(),
        ];
    }

    public function scopeBasicEducation($query)
    {
        return $query->where('description_name', 'like', '%fundamental%');
    }

    public function scopeSecondaryEducation($query)
    {
        return $query->where('description_name', 'like', '%médio%');
    }

    public function scopeHigherEducation($query)
    {
        return $query->where('description_name', 'like', '%superior%');
    }

    public function scopePostGraduate($query)
    {
        return $query->where(function ($query) {
            $query->where('description_name', 'like', '%pós-graduação%')
                  ->orWhereIn('description_name', ['Mestrado', 'Doutorado']);
        });
    }

    public function scopeComplete($query)
    {
        return $query->where(function ($query) {
            $query->where('description_name', 'like', '%completo%')
                  ->orWhereIn('description_name', ['Mestrado', 'Doutorado']);
        });
    }

    public function scopeIncomplete($query)
    {
        return $query->where('description_name', 'like', '%incompleto%');
    }

    public static function getTypes(): array
    {
        return [
            'basic_incomplete' => 'Ensino Fundamental Incompleto',
            'basic_complete' => 'Ensino Fundamental Completo',
            'secondary_incomplete' => 'Ensino Médio Incompleto',
            'secondary_complete' => 'Ensino Médio Completo',
            'higher_incomplete' => 'Educação Superior Incompleto',
            'higher_complete' => 'Educação Superior Completo',
            'postgraduate_incomplete' => 'Pós-Graduação Incompleto',
            'postgraduate_complete' => 'Pós-Graduação Completo',
            'masters' => 'Mestrado',
            'doctorate' => 'Doutorado',
        ];
    }
}
