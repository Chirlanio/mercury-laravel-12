<?php

namespace Tests\Feature\Middleware;

use App\Enums\Role;
use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testa se middleware redireciona usuário não autenticado para login
     */
    public function test_middleware_redirects_unauthenticated_user(): void
    {
        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'admin');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->isRedirect(route('login')));
    }

    /**
     * Testa se middleware bloqueia usuário com role inferior
     */
    public function test_middleware_blocks_user_with_insufficient_role(): void
    {
        $user = User::factory()->create(['role' => Role::USER]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Acesso negado. Você não tem permissão para acessar esta área.');

        $middleware->handle($request, function () {
            return new Response('OK');
        }, 'admin');
    }

    /**
     * Testa se middleware permite usuário com role exata
     */
    public function test_middleware_allows_user_with_exact_role(): void
    {
        $user = User::factory()->create(['role' => Role::ADMIN]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'admin');

        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Testa hierarquia: Super Admin pode acessar área de Admin
     */
    public function test_super_admin_can_access_admin_area(): void
    {
        $user = User::factory()->create(['role' => Role::SUPER_ADMIN]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'admin');

        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Testa hierarquia: Super Admin pode acessar área de Support
     */
    public function test_super_admin_can_access_support_area(): void
    {
        $user = User::factory()->create(['role' => Role::SUPER_ADMIN]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'support');

        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Testa hierarquia: Super Admin pode acessar área de User
     */
    public function test_super_admin_can_access_user_area(): void
    {
        $user = User::factory()->create(['role' => Role::SUPER_ADMIN]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'user');

        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Testa hierarquia: Admin pode acessar área de Support
     */
    public function test_admin_can_access_support_area(): void
    {
        $user = User::factory()->create(['role' => Role::ADMIN]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'support');

        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Testa hierarquia: Admin pode acessar área de User
     */
    public function test_admin_can_access_user_area(): void
    {
        $user = User::factory()->create(['role' => Role::ADMIN]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'user');

        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Testa hierarquia: Admin não pode acessar área de Super Admin
     */
    public function test_admin_cannot_access_super_admin_area(): void
    {
        $user = User::factory()->create(['role' => Role::ADMIN]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $middleware->handle($request, function () {
            return new Response('OK');
        }, 'super_admin');
    }

    /**
     * Testa hierarquia: Support pode acessar área de User
     */
    public function test_support_can_access_user_area(): void
    {
        $user = User::factory()->create(['role' => Role::SUPPORT]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'user');

        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Testa hierarquia: Support não pode acessar área de Admin
     */
    public function test_support_cannot_access_admin_area(): void
    {
        $user = User::factory()->create(['role' => Role::SUPPORT]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $middleware->handle($request, function () {
            return new Response('OK');
        }, 'admin');
    }

    /**
     * Testa que User só pode acessar área de User
     */
    public function test_user_can_only_access_user_area(): void
    {
        $user = User::factory()->create(['role' => Role::USER]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        // Deve permitir acesso a área de user
        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'user');

        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Testa que User não pode acessar área de Support
     */
    public function test_user_cannot_access_support_area(): void
    {
        $user = User::factory()->create(['role' => Role::USER]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $middleware->handle($request, function () {
            return new Response('OK');
        }, 'support');
    }

    /**
     * Testa conversão de string para Role enum
     */
    public function test_middleware_converts_string_to_role_enum(): void
    {
        $user = User::factory()->create(['role' => Role::ADMIN]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        // Passar string, deve converter para enum
        $response = $middleware->handle($request, function () {
            return new Response('OK');
        }, 'admin');

        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Testa role inválida
     */
    public function test_middleware_throws_exception_for_invalid_role(): void
    {
        $user = User::factory()->create(['role' => Role::USER]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $middleware = new RoleMiddleware();

        $this->expectException(\ValueError::class);

        $middleware->handle($request, function () {
            return new Response('OK');
        }, 'invalid_role');
    }

    /**
     * Testa a ordem hierárquica completa
     */
    public function test_role_hierarchy_order(): void
    {
        $roles = [
            Role::SUPER_ADMIN,
            Role::ADMIN,
            Role::SUPPORT,
            Role::USER,
        ];

        // Super Admin deve acessar tudo
        $superAdmin = User::factory()->create(['role' => Role::SUPER_ADMIN]);
        $this->actingAs($superAdmin);

        foreach ($roles as $role) {
            $request = Request::create('/test', 'GET');
            $middleware = new RoleMiddleware();

            $response = $middleware->handle($request, function () {
                return new Response('OK');
            }, $role->value);

            $this->assertEquals('OK', $response->getContent(), "Super Admin deve acessar role: {$role->value}");
        }
    }
}
