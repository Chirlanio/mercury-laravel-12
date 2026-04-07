<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TenantIntegration extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'name',
        'provider',
        'type',
        'driver',
        'config',
        'is_active',
        'last_sync_at',
        'last_sync_status',
        'last_sync_message',
        'sync_schedule',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'config',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function setConfigAttribute($value): void
    {
        $this->attributes['config'] = Crypt::encryptString(
            is_string($value) ? $value : json_encode($value)
        );
    }

    public function getConfigAttribute($value): ?array
    {
        if (! $value) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($value), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function syncLogs()
    {
        return $this->hasMany(IntegrationSyncLog::class, 'integration_id');
    }

    public function markSyncSuccess(string $message = ''): void
    {
        $this->update([
            'last_sync_at' => now(),
            'last_sync_status' => 'success',
            'last_sync_message' => $message,
        ]);
    }

    public function markSyncError(string $message): void
    {
        $this->update([
            'last_sync_at' => now(),
            'last_sync_status' => 'error',
            'last_sync_message' => $message,
        ]);
    }
}
