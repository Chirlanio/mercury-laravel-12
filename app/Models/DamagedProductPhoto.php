<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamagedProductPhoto extends Model
{
    use Auditable;

    protected $fillable = [
        'damaged_product_id',
        'filename',
        'original_filename',
        'file_path',
        'file_size',
        'mime_type',
        'caption',
        'sort_order',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'damaged_product_id' => 'integer',
        'file_size' => 'integer',
        'sort_order' => 'integer',
    ];

    public function damagedProduct(): BelongsTo
    {
        return $this->belongsTo(DamagedProduct::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * URL pública da foto.
     *
     * Usa tenant_asset() (não asset('storage/...')) porque as fotos são
     * salvas no disk='public' DENTRO do contexto tenant — ou seja, em
     * storage/tenant{id}/app/public/damaged-products/... (não no central
     * storage/app/public/avatars/...).
     *
     * O asset_helper_tenancy: true da config tenancy reescreve
     *   asset('storage/foo') → /tenancy/assets/storage/foo  (404 — path
     *   literal "storage/foo" não existe no tenant disk)
     * já o tenant_asset($path) gera o path direto:
     *   tenant_asset('foo') → /tenancy/assets/foo (resolve correto)
     *
     * User::$avatar_url etc usam asset('storage/avatars/...') porque
     * avatars são salvos no central (storage/app/public/avatars/) via
     * symlink public/storage — esse caso não passa pelo TenantAssetsController.
     */
    public function getUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return tenant_asset($this->file_path);
    }
}
