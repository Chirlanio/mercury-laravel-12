<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuditLogService
{
    /**
     * Registra uma atividade de auditoria
     */
    public function log(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null
    ): ?ActivityLog {
        try {
            return ActivityLog::log(
                action: $action,
                description: $description,
                model: $model,
                oldValues: $oldValues,
                newValues: $newValues,
                user: $user
            );
        } catch (\Exception $e) {
            Log::error('Erro ao registrar auditoria', [
                'action' => $action,
                'description' => $description,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Registra login de usuário
     */
    public function logLogin(User $user): ?ActivityLog
    {
        return $this->log(
            action: 'login',
            description: "Usuário {$user->name} fez login no sistema",
            user: $user
        );
    }

    /**
     * Registra logout de usuário
     */
    public function logLogout(?User $user = null): ?ActivityLog
    {
        $user = $user ?? Auth::user();

        if (!$user) {
            return null;
        }

        return $this->log(
            action: 'logout',
            description: "Usuário {$user->name} fez logout do sistema",
            user: $user
        );
    }

    /**
     * Registra criação de modelo
     */
    public function logModelCreated(Model $model, ?User $user = null): ?ActivityLog
    {
        $modelName = class_basename($model);
        $description = "Criou um novo {$modelName}";

        if (method_exists($model, 'getDescriptiveAttribute')) {
            $description .= ": {$model->getDescriptiveAttribute()}";
        } else if (isset($model->name)) {
            $description .= ": {$model->name}";
        } else if (isset($model->title)) {
            $description .= ": {$model->title}";
        }

        return $this->log(
            action: 'create',
            description: $description,
            model: $model,
            newValues: $model->toArray(),
            user: $user
        );
    }

    /**
     * Registra atualização de modelo
     */
    public function logModelUpdated(Model $model, array $oldValues, ?User $user = null): ?ActivityLog
    {
        $modelName = class_basename($model);
        $description = "Atualizou {$modelName}";

        if (method_exists($model, 'getDescriptiveAttribute')) {
            $description .= ": {$model->getDescriptiveAttribute()}";
        } else if (isset($model->name)) {
            $description .= ": {$model->name}";
        } else if (isset($model->title)) {
            $description .= ": {$model->title}";
        }

        return $this->log(
            action: 'update',
            description: $description,
            model: $model,
            oldValues: $oldValues,
            newValues: $model->toArray(),
            user: $user
        );
    }

    /**
     * Registra deleção de modelo
     */
    public function logModelDeleted(Model $model, ?User $user = null): ?ActivityLog
    {
        $modelName = class_basename($model);
        $description = "Deletou {$modelName}";

        if (method_exists($model, 'getDescriptiveAttribute')) {
            $description .= ": {$model->getDescriptiveAttribute()}";
        } else if (isset($model->name)) {
            $description .= ": {$model->name}";
        } else if (isset($model->title)) {
            $description .= ": {$model->title}";
        }

        return $this->log(
            action: 'delete',
            description: $description,
            model: $model,
            oldValues: $model->toArray(),
            user: $user
        );
    }

    /**
     * Registra acesso a recurso
     */
    public function logResourceAccess(string $resource, ?Model $model = null, ?User $user = null): ?ActivityLog
    {
        $description = "Acessou {$resource}";

        if ($model) {
            $modelName = class_basename($model);
            $description .= " ({$modelName} ID: {$model->getKey()})";
        }

        return $this->log(
            action: 'access',
            description: $description,
            model: $model,
            user: $user
        );
    }

    /**
     * Registra tentativa de acesso negado
     */
    public function logAccessDenied(string $resource, string $reason = '', ?User $user = null): ?ActivityLog
    {
        $description = "Tentativa de acesso negado a {$resource}";

        if ($reason) {
            $description .= " - Motivo: {$reason}";
        }

        return $this->log(
            action: 'access_denied',
            description: $description,
            user: $user
        );
    }

    /**
     * Registra ação personalizada
     */
    public function logCustomAction(
        string $action,
        string $description,
        ?Model $model = null,
        ?array $metadata = null,
        ?User $user = null
    ): ?ActivityLog {
        return $this->log(
            action: $action,
            description: $description,
            model: $model,
            newValues: $metadata,
            user: $user
        );
    }

    /**
     * Obtém estatísticas de auditoria
     */
    public function getAuditStatistics(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        $logs = ActivityLog::where('created_at', '>=', $startDate)->get();

        return [
            'total_actions' => $logs->count(),
            'unique_users' => $logs->pluck('user_id')->unique()->filter()->count(),
            'actions_by_type' => $logs->groupBy('action')->map->count(),
            'actions_by_day' => $logs->groupBy(function ($log) {
                return $log->created_at->format('Y-m-d');
            })->map->count(),
            'most_active_users' => $logs->groupBy('user_id')
                ->filter(function ($group, $userId) {
                    return $userId !== null;
                })
                ->map->count()
                ->sortDesc()
                ->take(10),
            'actions_per_hour' => $logs->groupBy(function ($log) {
                return $log->created_at->format('H');
            })->map->count(),
        ];
    }

    /**
     * Limpa logs antigos
     */
    public function cleanupOldLogs(int $olderThanDays = 90): int
    {
        $cutoffDate = Carbon::now()->subDays($olderThanDays);

        return ActivityLog::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * Exporta logs para array
     */
    public function exportLogs(array $filters = []): Collection
    {
        $query = ActivityLog::with('user');

        // Aplicar filtros
        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->orderBy('created_at', 'desc')->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'data_hora' => $log->created_at->format('d/m/Y H:i:s'),
                'usuario' => $log->user ? $log->user->name : 'Sistema',
                'email_usuario' => $log->user ? $log->user->email : '',
                'acao' => $log->action,
                'descricao' => $log->description,
                'endereco_ip' => $log->ip_address,
                'metodo_http' => $log->method,
                'url' => $log->url,
                'modelo_tipo' => $log->model_type,
                'modelo_id' => $log->model_id,
                'possui_alteracoes' => $log->has_changes ? 'Sim' : 'Não',
            ];
        });
    }

    /**
     * Verifica se um usuário tem atividade suspeita
     */
    public function detectSuspiciousActivity(User $user, int $timeWindowMinutes = 5): array
    {
        $startTime = Carbon::now()->subMinutes($timeWindowMinutes);

        $recentLogs = ActivityLog::where('user_id', $user->id)
            ->where('created_at', '>=', $startTime)
            ->get();

        $suspiciousPatterns = [];

        // Muitas ações em pouco tempo
        if ($recentLogs->count() > 20) {
            $suspiciousPatterns[] = "Muitas ações em {$timeWindowMinutes} minutos ({$recentLogs->count()} ações)";
        }

        // Múltiplos IPs diferentes
        $uniqueIps = $recentLogs->pluck('ip_address')->unique()->filter()->count();
        if ($uniqueIps > 2) {
            $suspiciousPatterns[] = "Múltiplos endereços IP ({$uniqueIps} IPs diferentes)";
        }

        // Muitas tentativas de acesso negado
        $accessDeniedCount = $recentLogs->where('action', 'access_denied')->count();
        if ($accessDeniedCount > 5) {
            $suspiciousPatterns[] = "Muitas tentativas de acesso negado ({$accessDeniedCount} tentativas)";
        }

        return $suspiciousPatterns;
    }
}