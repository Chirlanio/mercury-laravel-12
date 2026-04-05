<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class CentralActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(CentralUser::class, 'user_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Log a central admin action.
     */
    public static function log(string $action, string $description, ?string $tenantId = null, ?array $metadata = null): static
    {
        return static::create([
            'user_id' => Auth::guard('central')->id(),
            'tenant_id' => $tenantId,
            'action' => $action,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
