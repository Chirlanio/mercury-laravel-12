<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cliente — alimentado pela sincronização do CIGAM (view msl_dcliente_).
 *
 * Fonte de verdade é o CIGAM — escrita direta em `customers` deve
 * acontecer APENAS via CustomerSyncService. O módulo no Mercury é
 * majoritariamente read-only; operações de edição ficam no ERP.
 */
class Customer extends Model
{
    use Auditable, HasFactory;

    protected $table = 'customers';

    protected $fillable = [
        'cigam_code',
        'name',
        'cpf',
        'person_type',
        'gender',
        'email',
        'phone',
        'mobile',
        'address',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'zipcode',
        'birth_date',
        'registered_at',
        'is_active',
        'synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'birth_date' => 'date',
        'registered_at' => 'date',
        'synced_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getFormattedCpfAttribute(): ?string
    {
        if (! $this->cpf) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $this->cpf);
        if (strlen($digits) === 11) {
            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9);
        }
        if (strlen($digits) === 14) {
            return substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5, 3).'/'.substr($digits, 8, 4).'-'.substr($digits, 12);
        }

        return $this->cpf;
    }

    public function getFormattedMobileAttribute(): ?string
    {
        if (! $this->mobile) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $this->mobile);
        if (strlen($digits) === 11) {
            return '('.substr($digits, 0, 2).') '.substr($digits, 2, 5).'-'.substr($digits, 7);
        }
        if (strlen($digits) === 10) {
            return '('.substr($digits, 0, 2).') '.substr($digits, 2, 4).'-'.substr($digits, 6);
        }

        return $this->mobile;
    }

    public function getPrimaryContactAttribute(): ?string
    {
        return $this->mobile ?: $this->phone;
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $digits = preg_replace('/\D/', '', $term);
        $term = trim($term);

        return $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");

            if ($digits !== '' && strlen($digits) >= 3) {
                $q->orWhere('cpf', 'like', "%{$digits}%")
                    ->orWhere('mobile', 'like', "%{$digits}%")
                    ->orWhere('phone', 'like', "%{$digits}%")
                    ->orWhere('cigam_code', 'like', "%{$digits}%");
            }
        });
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function consignments(): HasMany
    {
        return $this->hasMany(Consignment::class);
    }
}
