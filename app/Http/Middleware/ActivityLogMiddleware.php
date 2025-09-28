<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Só registrar para usuários autenticados e métodos que alteram dados
        if (auth()->check() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->logActivity($request, $response);
        }

        return $response;
    }

    private function logActivity(Request $request, Response $response): void
    {
        try {
            // Não registrar algumas rotas sensíveis
            $skipRoutes = [
                'logout',
                'password',
                'csrf',
                '_ignition',
            ];

            $route = $request->route();
            $routeName = $route?->getName() ?? '';

            foreach ($skipRoutes as $skipRoute) {
                if (str_contains($routeName, $skipRoute) || str_contains($request->path(), $skipRoute)) {
                    return;
                }
            }

            $action = $this->getActionFromRequest($request);
            $description = $this->getDescriptionFromRequest($request, $response);

            // Só registrar se foi bem-sucedido (códigos 2xx)
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                ActivityLog::log(
                    action: $action,
                    description: $description
                );
            }
        } catch (\Exception $e) {
            // Silenciosamente falhar para não quebrar a aplicação
            \Log::error('Erro ao registrar log de atividade: ' . $e->getMessage());
        }
    }

    private function getActionFromRequest(Request $request): string
    {
        $method = $request->method();
        $route = $request->route();
        $routeName = $route?->getName() ?? '';

        // Mapear ações baseadas no nome da rota
        if (str_contains($routeName, 'store')) {
            return 'create';
        }

        if (str_contains($routeName, 'update')) {
            return 'update';
        }

        if (str_contains($routeName, 'destroy')) {
            return 'delete';
        }

        if (str_contains($routeName, 'login')) {
            return 'login';
        }

        if (str_contains($routeName, 'logout')) {
            return 'logout';
        }

        // Mapear por método HTTP
        return match($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'action',
        };
    }

    private function getDescriptionFromRequest(Request $request, Response $response): string
    {
        $route = $request->route();
        $routeName = $route?->getName() ?? '';
        $method = $request->method();

        // Descrições específicas baseadas na rota
        if (str_contains($routeName, 'users.store')) {
            return 'Criou um novo usuário';
        }

        if (str_contains($routeName, 'users.update')) {
            $userId = $route?->parameter('user');
            return "Atualizou o usuário ID: {$userId}";
        }

        if (str_contains($routeName, 'users.destroy')) {
            $userId = $route?->parameter('user');
            return "Deletou o usuário ID: {$userId}";
        }

        if (str_contains($routeName, 'users.updateRole')) {
            $userId = $route?->parameter('user');
            return "Alterou o nível de acesso do usuário ID: {$userId}";
        }

        if (str_contains($routeName, 'profile.update')) {
            return 'Atualizou o próprio perfil';
        }

        if (str_contains($routeName, 'login')) {
            return 'Fez login no sistema';
        }

        if (str_contains($routeName, 'logout')) {
            return 'Fez logout do sistema';
        }

        // Descrição genérica
        $path = $request->path();
        return match($method) {
            'POST' => "Criou um novo recurso em {$path}",
            'PUT', 'PATCH' => "Atualizou um recurso em {$path}",
            'DELETE' => "Deletou um recurso em {$path}",
            default => "Executou uma ação em {$path}",
        };
    }
}
