<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search');
        $action = $request->get('action');
        $userId = $request->get('user_id');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        // Validar campos de ordenação permitidos
        $allowedSortFields = ['created_at', 'action', 'user_id', 'description'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }

        // Validar direção da ordenação
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $query = ActivityLog::with('user:id,name,email');

        // Aplicar filtros
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($action) {
            $query->where('action', $action);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        // Aplicar ordenação
        $query->orderBy($sortField, $sortDirection);

        $logs = $query->paginate($perPage);

        // Obter dados para filtros
        $actions = ActivityLog::distinct()->pluck('action')->filter()->sort()->values();
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();

        // Estatísticas rápidas
        $stats = [
            'total_today' => ActivityLog::whereDate('created_at', today())->count(),
            'total_week' => ActivityLog::where('created_at', '>=', now()->startOfWeek())->count(),
            'total_month' => ActivityLog::where('created_at', '>=', now()->startOfMonth())->count(),
            'unique_users_today' => ActivityLog::whereDate('created_at', today())
                ->distinct('user_id')->count('user_id'),
        ];

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $logs->through(function ($log) {
                return [
                    'id' => $log->id,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ] : null,
                    'action' => $log->action,
                    'description' => $log->description,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'url' => $log->url,
                    'method' => $log->method,
                    'created_at' => $log->created_at,
                    'has_changes' => $log->has_changes,
                    'changes' => $log->getChanges(),
                ];
            }),
            'filters' => [
                'search' => $search,
                'action' => $action,
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
            ],
            'actions' => $actions,
            'users' => $users,
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, ActivityLog $log)
    {
        $log->load('user:id,name,email');

        return Inertia::render('ActivityLogs/Show', [
            'log' => [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'action' => $log->action,
                'model_type' => $log->model_type,
                'model_id' => $log->model_id,
                'description' => $log->description,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'url' => $log->url,
                'method' => $log->method,
                'created_at' => $log->created_at,
                'has_changes' => $log->has_changes,
                'changes' => $log->getChanges(),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,excel,json',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'action' => 'nullable|string',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $format = $request->get('format', 'csv');
        $filters = $request->only(['start_date', 'end_date', 'action', 'user_id']);

        $auditService = app(\App\Services\AuditLogService::class);
        $logs = $auditService->exportLogs($filters);

        // Verificar limite de registros
        $maxRecords = config('audit.export.max_records', 10000);
        if ($logs->count() > $maxRecords) {
            return response()->json([
                'error' => "Muitos registros encontrados ({$logs->count()}). Limite máximo: {$maxRecords}. Por favor, use filtros mais específicos."
            ], 422);
        }

        $filename = 'activity_logs_' . now()->format('Y-m-d_H-i-s');

        switch ($format) {
            case 'csv':
                return $this->exportToCsv($logs, $filename);
            case 'excel':
                return $this->exportToExcel($logs, $filename);
            case 'json':
                return $this->exportToJson($logs, $filename);
            default:
                return response()->json(['error' => 'Formato não suportado'], 400);
        }
    }

    private function exportToCsv($logs, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');

            // Header do CSV
            fputcsv($file, [
                'ID',
                'Data/Hora',
                'Usuário',
                'Email do Usuário',
                'Ação',
                'Descrição',
                'Endereço IP',
                'Método HTTP',
                'URL',
                'Tipo do Modelo',
                'ID do Modelo',
                'Possui Alterações'
            ]);

            // Dados
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log['id'],
                    $log['data_hora'],
                    $log['usuario'],
                    $log['email_usuario'],
                    $log['acao'],
                    $log['descricao'],
                    $log['endereco_ip'],
                    $log['metodo_http'],
                    $log['url'],
                    $log['modelo_tipo'],
                    $log['modelo_id'],
                    $log['possui_alteracoes']
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportToExcel($logs, $filename)
    {
        // Para implementar Excel, seria necessário instalar uma biblioteca como PhpSpreadsheet
        // Por enquanto, vamos exportar como CSV com extensão .xlsx
        return $this->exportToCsv($logs, $filename . '_excel_format');
    }

    private function exportToJson($logs, $filename)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}.json\"",
        ];

        return response()->json([
            'exported_at' => now()->toISOString(),
            'total_records' => $logs->count(),
            'data' => $logs
        ], 200, $headers);
    }

    public function cleanup(Request $request)
    {
        $request->validate([
            'older_than_days' => 'required|integer|min:1|max:365'
        ]);

        $olderThanDays = $request->get('older_than_days');
        $cutoffDate = Carbon::now()->subDays($olderThanDays);

        $deletedCount = ActivityLog::where('created_at', '<', $cutoffDate)->delete();

        return response()->json([
            'message' => "Foram removidos {$deletedCount} registros de log mais antigos que {$olderThanDays} dias."
        ]);
    }
}
