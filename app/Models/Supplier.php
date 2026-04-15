<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'codigo_for',
        'cnpj',
        'razao_social',
        'nome_fantasia',
        'contact',
        'email',
        'payment_terms_default',
        'address',
        'city',
        'state',
        'zip',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class, 'supplier_id');
    }

    public function orderPayments(): HasMany
    {
        return $this->hasMany(OrderPayment::class, 'supplier_id');
    }

    // Accessors

    public function getFormattedCnpjAttribute(): string
    {
        $v = preg_replace('/\D/', '', $this->cnpj ?? '');
        if (strlen($v) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $v);
        }
        if (strlen($v) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $v);
        }
        return $this->cnpj ?? '';
    }

    public function getFormattedContactAttribute(): string
    {
        $v = preg_replace('/\D/', '', $this->contact ?? '');
        if (strlen($v) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $v);
        }
        if (strlen($v) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $v);
        }
        return $this->contact ?? '';
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('razao_social', 'like', "%{$search}%")
                ->orWhere('nome_fantasia', 'like', "%{$search}%")
                ->orWhere('cnpj', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }
}
