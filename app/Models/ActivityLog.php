<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'description',
        'ip_address',
        'user_agent',
        'url',
        'method',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relacionamento com o usuário que executou a ação
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relacionamento polimórfico com o modelo que foi alterado
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Método estático para registrar uma atividade
     */
    public static function log(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null
    ): self {
        $request = request();

        return self::create([
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
        ]);
    }

    /**
     * Scope para filtrar por ação
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope para filtrar por usuário
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para filtrar por modelo
     */
    public function scopeForModel($query, string $modelType, ?int $modelId = null)
    {
        $query = $query->where('model_type', $modelType);

        if ($modelId) {
            $query->where('model_id', $modelId);
        }

        return $query;
    }

    /**
     * Scope para filtrar por período
     */
    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Accessor para formatar a descrição
     */
    public function getFormattedDescriptionAttribute(): string
    {
        return $this->description;
    }

    /**
     * Accessor para verificar se tem dados antigos
     */
    public function getHasChangesAttribute(): bool
    {
        return !empty($this->old_values) || !empty($this->new_values);
    }

    /**
     * Método para obter as mudanças formatadas
     */
    public function getChanges(): array
    {
        $changes = [];

        if ($this->old_values && $this->new_values) {
            $oldValues = $this->old_values;
            $newValues = $this->new_values;

            foreach ($newValues as $key => $newValue) {
                $oldValue = $oldValues[$key] ?? null;

                if ($oldValue !== $newValue) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            }
        }

        return $changes;
    }
}
