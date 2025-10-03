<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'employees';

    protected $fillable = [
        'name',
        'short_name',
        'profile_image',
        'cpf',
        'admission_date',
        'dismissal_date',
        'position_id',
        'site_coupon',
        'store_id',
        'education_level_id',
        'gender_id',
        'birth_date',
        'area_id',
        'is_pcd',
        'is_apprentice',
        'level',
        'status_id',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'dismissal_date' => 'date',
        'birth_date' => 'date',
        'is_pcd' => 'boolean',
        'is_apprentice' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'cpf', // Sensitive data
    ];

    /**
     * Get employee age
     */
    public function getAgeAttribute(): int
    {
        return $this->birth_date->age;
    }

    /**
     * Get years of service
     */
    public function getYearsOfServiceAttribute(): int
    {
        $endDate = $this->dismissal_date ?? Carbon::now();
        return $this->admission_date->diffInYears($endDate);
    }

    /**
     * Check if employee is active
     */
    public function getIsActiveAttribute(): bool
    {
        return is_null($this->dismissal_date);
    }

    /**
     * Get formatted CPF
     */
    public function getFormattedCpfAttribute(): string
    {
        $cpf = $this->cpf;
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }

    /**
     * Get profile image URL
     */
    public function getProfileImageUrlAttribute(): ?string
    {
        if (!$this->profile_image) {
            return null;
        }

        return asset('storage/employees/' . $this->profile_image);
    }

    /**
     * Get default avatar with initials
     */
    public function getDefaultAvatarUrlAttribute(): string
    {
        $initials = $this->getInitials();
        return "https://ui-avatars.com/api/?name={$initials}&background=6366f1&color=ffffff&size=200";
    }

    /**
     * Get employee initials
     */
    public function getInitials(): string
    {
        $words = explode(' ', $this->name);
        $initials = '';

        foreach ($words as $word) {
            if (strlen($word) > 2) { // Skip small words like "da", "de", etc.
                $initials .= strtoupper(substr($word, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }

        return $initials ?: strtoupper(substr($this->name, 0, 2));
    }

    /**
     * Get avatar URL (profile image or default)
     */
    public function getAvatarUrlAttribute(): string
    {
        return $this->profile_image_url ?? $this->default_avatar_url;
    }

    /**
     * Scope to get active employees
     */
    public function scopeActive($query)
    {
        return $query->whereNull('dismissal_date');
    }

    /**
     * Scope to get inactive employees
     */
    public function scopeInactive($query)
    {
        return $query->whereNotNull('dismissal_date');
    }

    /**
     * Scope to filter by level
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope to filter by store
     */
    public function scopeByStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Get all history entries for this employee
     */
    public function histories(): HasMany
    {
        return $this->hasMany(EmployeeHistory::class);
    }

    /**
     * Get all employment contracts for this employee
     */
    public function employmentContracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }

    /**
     * Get the current employment contract
     */
    public function currentContract(): HasOne
    {
        return $this->hasOne(EmploymentContract::class)
                    ->where(function ($query) {
                        $query->whereNull('end_date')
                              ->orWhere('end_date', '>', Carbon::now());
                    })
                    ->orderBy('start_date', 'desc');
    }

    /**
     * Get the latest employment contract
     */
    public function latestContract(): HasOne
    {
        return $this->hasOne(EmploymentContract::class)
                    ->orderBy('start_date', 'desc');
    }

    /**
     * Get the education level for this employee
     */
    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    /**
     * Get the position for this employee
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the store for this employee
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'code');
    }

    /**
     * Get all available levels
     */
    public static function getLevels(): array
    {
        return [
            'Junior' => 'Júnior',
            'Pleno' => 'Pleno',
            'Senior' => 'Sênior',
        ];
    }
}
