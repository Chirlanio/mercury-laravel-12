<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationSyncLog extends Model
{
    protected $fillable = [
        'integration_id',
        'tenant_id',
        'direction',
        'status',
        'records_processed',
        'records_created',
        'records_updated',
        'records_failed',
        'error_messages',
        'started_at',
        'finished_at',
        'triggered_by',
    ];

    protected $casts = [
        'error_messages' => 'json',
        'records_processed' => 'integer',
        'records_created' => 'integer',
        'records_updated' => 'integer',
        'records_failed' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function integration()
    {
        return $this->belongsTo(TenantIntegration::class, 'integration_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
