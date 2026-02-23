<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Store extends Model
{
    use HasFactory, Auditable;

    const ECOMMERCE_CODE = 'Z441';

    protected $table = 'stores';

    protected $fillable = [
        'code',
        'name',
        'cnpj',
        'company_name',
        'state_registration',
        'address',
        'network_id',
        'manager_id',
        'store_order',
        'network_order',
        'supervisor_id',
        'status_id'
    ];

    protected $casts = [
        'network_id' => 'integer',
        'manager_id' => 'integer',
        'store_order' => 'integer',
        'network_order' => 'integer',
        'supervisor_id' => 'integer',
        'status_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all employees for this store
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'store_id', 'code');
    }

    /**
     * Get the store manager (employee)
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Get the store supervisor (employee)
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /**
     * Get the network for this store
     */
    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class);
    }

    /**
     * Get the status for this store
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Scope to get only active stores
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get stores by network
     */
    public function scopeByNetwork($query, int $networkId)
    {
        return $query->where('network_id', $networkId);
    }

    /**
     * Get stores ordered by store_order
     */
    public function scopeOrderedByStore($query)
    {
        return $query->orderBy('store_order');
    }

    /**
     * Get stores ordered by network_order
     */
    public function scopeOrderedByNetwork($query)
    {
        return $query->orderBy('network_order')->orderBy('store_order');
    }

    /**
     * Check if store is active
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status_id === 1;
    }

    /**
     * Get formatted CNPJ
     */
    public function getFormattedCnpjAttribute(): string
    {
        $cnpj = $this->cnpj;
        if (strlen($cnpj) === 14) {
            return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
        }
        return $cnpj;
    }

    /**
     * Get store display name with code
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /**
     * Get network name based on network_id
     */
    public function getNetworkNameAttribute(): string
    {
        $networks = [
            1 => 'Arezzo',
            2 => 'Anacapri',
            3 => 'Meia Sola',
            4 => 'Schutz',
            5 => 'Outlet',
            6 => 'E-Commerce',
            7 => 'Operacional',
            8 => 'Arezzo Brizza',
        ];

        return $networks[$this->network_id] ?? 'Não definida';
    }

    /**
     * Get employees count
     */
    public function getEmployeesCountAttribute(): int
    {
        return $this->employees()->count();
    }
}
