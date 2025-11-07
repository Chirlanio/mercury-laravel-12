<?php

namespace Tests\Feature\Middleware;

use App\Enums\Role;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testa se middleware bloqueia usuário não autenticado
     */
    public function test_middleware_blocks_unauthenticated_user(): void
    {
        $request = Request::create('/test', 'GET');
        $middleware = new PermissionMiddleware();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Não autenticado.');

        $middleware->handle($request, function () {
            return new Response('OK');
        }, 'users.view');
    }

    /**
     * Testa se middleware bloqueia usuário sem permissão
     */
    public function test_middleware_blocks_user_without_permission(): void
    {
        $user = User::factory()->create(['role' => Role::USER]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new PermissionMiddleware();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Você não tem permissão para acessar este recurso.');

        $middleware->handle($request, function () {
            return new Response('OK');
        }, 'users.manage_roles');
    }

    /**
     * Testa se middleware permite usuário com permissão
     */
    public function test_middleware_allows_user_with_permission(): void
    {
        $user = User::factory()->create(['role' => Role::SUPER_ADMIN]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new PermissionMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'users.view');

        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Testa lógica OR de múltiplas permissões
     */
    public function test_middleware_allows_user_with_one_of_multiple_permissions(): void
    {
        // Admin não tem users.manage_roles, mas tem users.view
        $user = User::factory()->create(['role' => Role::ADMIN]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new PermissionMiddleware();

        // Passar múltiplas permissões - deve passar se tiver pelo menos uma
        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'users.manage_roles', 'users.view');

        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Testa se Super Admin tem todas as permissões
     */
    public function test_super_admin_has_all_permissions(): void
    {
        $user = User::factory()->create(['role' => Role::SUPER_ADMIN]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new PermissionMiddleware();

        // Testar várias permissões
        $permissions = [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.manage_roles',
            'admin.access',
            'system.manage',
        ];

        foreach ($permissions as $permission) {
            $response = $middleware->handle($request, function () {
                return new Response('OK');
            }, $permission);

            $this->assertEquals('OK', $response->getContent(), "Super Admin deve ter permissão: {$permission}");
        }
    }

    /**
     * Testa resposta JSON para requisições API
     */
    public function test_middleware_returns_json_for_api_requests(): void
    {
        $user = User::factory()->create(['role' => Role::USER]);
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);

        $middleware = new PermissionMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'admin.access');

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Você não tem permissão para acessar este recurso.', $content['message']);
    }

    /**
     * Testa hierarquia de permissões: ADMIN tem permissões de USER
     */
    public function test_admin_has_user_permissions(): void
    {
        $user = User::factory()->create(['role' => Role::ADMIN]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new PermissionMiddleware();

        // Admin deve ter permissões básicas de usuário
        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'profile.view_own', 'profile.edit_own', 'dashboard.access');

        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Testa que USER não tem permissões administrativas
     */
    public function test_user_does_not_have_admin_permissions(): void
    {
        $user = User::factory()->create(['role' => Role::USER]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new PermissionMiddleware();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $middleware->handle($request, function () {
            return new Response('OK');
        }, 'admin.access');
    }

    /**
     * Testa que SUPPORT não tem permissões de edição
     */
    public function test_support_cannot_edit_or_delete(): void
    {
        $user = User::factory()->create(['role' => Role::SUPPORT]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new PermissionMiddleware();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $middleware->handle($request, function () {
            return new Response('OK');
        }, 'users.edit');
    }

    /**
     * Testa que SUPPORT tem permissões de visualização
     */
    public function test_support_can_view(): void
    {
        $user = User::factory()->create(['role' => Role::SUPPORT]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new PermissionMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'users.view', 'dashboard.access', 'logs.view');

        $this->assertEquals('OK', $response->getContent());
    }
}
