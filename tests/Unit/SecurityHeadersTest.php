<?php

namespace Tests\Unit;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    protected SecurityHeaders $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SecurityHeaders();
    }

    public function test_adds_x_frame_options_header(): void
    {
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function test_adds_x_content_type_options_header(): void
    {
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_adds_xss_protection_header(): void
    {
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
    }

    public function test_adds_referrer_policy_header(): void
    {
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertNotNull($response->headers->get('Referrer-Policy'));
    }

    public function test_adds_permissions_policy_header(): void
    {
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertNotNull($response->headers->get('Permissions-Policy'));
    }
}
