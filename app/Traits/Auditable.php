<?php

namespace App\Traits;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Campos que devem ser ignorados na auditoria
     */
    protected $auditIgnore = [
        'updated_at',
        'created_at',
        'deleted_at',
        'remember_token',
        'password',
        'password_confirmation',
        'email_verified_at',
    ];

    /**
     * Se deve fazer auditoria deste modelo
     */
    protected $auditEnabled = true;

    /**
     * Boot da trait
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            if ($model->shouldAudit()) {
                $model->logCreated();
            }
        });

        static::updated(function (Model $model) {
            if ($model->shouldAudit() && $model->hasAuditableChanges()) {
                $model->logUpdated();
            }
        });

        static::deleted(function (Model $model) {
            if ($model->shouldAudit()) {
                $model->logDeleted();
            }
        });
    }

    /**
     * Verifica se deve fazer auditoria
     */
    protected function shouldAudit(): bool
    {
        // Não auditar se auditoria está desabilitada globalmente
        if (!config('audit.enabled', true)) {
            return false;
        }

        // Não auditar se está desabilitada para este modelo
        if (!$this->auditEnabled) {
            return false;
        }

        // Não auditar se não há usuário autenticado (exceto para alguns casos especiais)
        if (!Auth::check() && !in_array(get_class($this), config('audit.allow_without_auth', []))) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se há mudanças auditáveis
     */
    protected function hasAuditableChanges(): bool
    {
        $changes = $this->getDirty();

        // Remove campos ignorados
        $auditableChanges = array_diff_key($changes, array_flip($this->getAuditIgnore()));

        return !empty($auditableChanges);
    }

    /**
     * Registra criação do modelo
     */
    protected function logCreated(): void
    {
        $auditService = app(AuditLogService::class);

        $auditService->logModelCreated(
            model: $this,
            user: Auth::user()
        );
    }

    /**
     * Registra atualização do modelo
     */
    protected function logUpdated(): void
    {
        $auditService = app(AuditLogService::class);

        $originalValues = $this->getAuditableOriginal();

        $auditService->logModelUpdated(
            model: $this,
            oldValues: $originalValues,
            user: Auth::user()
        );
    }

    /**
     * Registra deleção do modelo
     */
    protected function logDeleted(): void
    {
        $auditService = app(AuditLogService::class);

        $auditService->logModelDeleted(
            model: $this,
            user: Auth::user()
        );
    }

    /**
     * Obtém os valores originais auditáveis
     */
    protected function getAuditableOriginal(): array
    {
        $original = $this->getOriginal();
        $auditIgnore = $this->getAuditIgnore();

        return array_diff_key($original, array_flip($auditIgnore));
    }

    /**
     * Obtém os valores atuais auditáveis
     */
    protected function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();
        $auditIgnore = $this->getAuditIgnore();

        return array_diff_key($attributes, array_flip($auditIgnore));
    }

    /**
     * Obtém a lista de campos a serem ignorados
     */
    protected function getAuditIgnore(): array
    {
        return array_merge(
            $this->auditIgnore,
            $this->getHidden(),
            property_exists($this, 'auditExclude') ? $this->auditExclude : []
        );
    }

    /**
     * Define campos adicionais para ignorar na auditoria
     */
    public function setAuditIgnore(array $fields): self
    {
        $this->auditIgnore = array_merge($this->auditIgnore, $fields);
        return $this;
    }

    /**
     * Habilita auditoria para este modelo
     */
    public function enableAudit(): self
    {
        $this->auditEnabled = true;
        return $this;
    }

    /**
     * Desabilita auditoria para este modelo
     */
    public function disableAudit(): self
    {
        $this->auditEnabled = false;
        return $this;
    }

    /**
     * Executa uma ação sem auditoria
     */
    public function withoutAudit(callable $callback)
    {
        $originalState = $this->auditEnabled;
        $this->auditEnabled = false;

        try {
            return $callback();
        } finally {
            $this->auditEnabled = $originalState;
        }
    }

    /**
     * Atributo descritivo para logs (pode ser sobrescrito)
     */
    public function getDescriptiveAttribute(): string
    {
        if (isset($this->name)) {
            return $this->name;
        }

        if (isset($this->title)) {
            return $this->title;
        }

        if (isset($this->email)) {
            return $this->email;
        }

        return "ID: {$this->getKey()}";
    }

    /**
     * Registra uma ação customizada para este modelo
     */
    public function logCustomAction(string $action, string $description, ?array $metadata = null): void
    {
        if (!$this->shouldAudit()) {
            return;
        }

        $auditService = app(AuditLogService::class);

        $auditService->logCustomAction(
            action: $action,
            description: $description,
            model: $this,
            metadata: $metadata,
            user: Auth::user()
        );
    }

    /**
     * Registra acesso a este modelo
     */
    public function logAccess(string $context = 'visualização'): void
    {
        if (!$this->shouldAudit()) {
            return;
        }

        $auditService = app(AuditLogService::class);

        $auditService->logResourceAccess(
            resource: $context . ' de ' . class_basename($this),
            model: $this,
            user: Auth::user()
        );
    }
}