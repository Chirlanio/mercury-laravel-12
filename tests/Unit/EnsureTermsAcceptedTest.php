<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Http\Middleware\EnsureTermsAccepted;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class EnsureTermsAcceptedTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected EnsureTermsAccepted $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->middleware = new EnsureTermsAccepted();
    }

    public function test_unauthenticated_request_passes_through(): void
    {
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => null);

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_user_with_accepted_current_terms_passes_through(): void
    {
        $this->adminUser->update([
            'terms_accepted_at' => now(),
            'terms_version' => EnsureTermsAccepted::TERMS_VERSION,
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $this->adminUser);

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_user_without_accepted_terms_is_redirected(): void
    {
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $this->adminUser);
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertTrue($response->isRedirection());
    }

    public function test_user_with_old_terms_version_is_redirected(): void
    {
        $this->adminUser->update([
            'terms_accepted_at' => now(),
            'terms_version' => '0.1', // Old version
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $this->adminUser);
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertTrue($response->isRedirection());
    }

    public function test_terms_version_constant_is_defined(): void
    {
        $this->assertNotEmpty(EnsureTermsAccepted::TERMS_VERSION);
    }

    public function test_whitelisted_routes_pass_through_without_terms(): void
    {
        $request = Request::create('/terms', 'GET');
        $request->setUserResolver(fn () => $this->adminUser);

        // Simulate route name
        $route = new \Illuminate\Routing\Route('GET', '/terms', []);
        $route->name('terms.show');
        $request->setRouteResolver(fn () => $route);

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }
}
