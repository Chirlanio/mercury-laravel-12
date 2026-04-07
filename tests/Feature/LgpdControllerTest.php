<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Controllers\LgpdController;
use App\Http\Middleware\EnsureTermsAccepted;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class LgpdControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    // ==========================================
    // Accept Terms (direct controller test)
    // ==========================================

    public function test_user_can_accept_terms(): void
    {
        $this->actingAs($this->adminUser);

        $controller = new LgpdController();
        $request = Request::create('/terms/accept', 'POST');
        $request->setUserResolver(fn () => $this->adminUser);

        $controller->acceptTerms($request);

        $this->adminUser->refresh();
        $this->assertNotNull($this->adminUser->terms_accepted_at);
        $this->assertEquals(EnsureTermsAccepted::TERMS_VERSION, $this->adminUser->terms_version);
        $this->assertNotNull($this->adminUser->terms_ip);
    }

    public function test_accept_terms_sets_correct_version(): void
    {
        $this->actingAs($this->regularUser);

        $controller = new LgpdController();
        $request = Request::create('/terms/accept', 'POST');
        $request->setUserResolver(fn () => $this->regularUser);

        $controller->acceptTerms($request);

        $this->regularUser->refresh();
        $this->assertEquals(EnsureTermsAccepted::TERMS_VERSION, $this->regularUser->terms_version);
    }

    // ==========================================
    // Data Export (direct controller test)
    // ==========================================

    public function test_user_can_export_their_data(): void
    {
        $this->actingAs($this->adminUser);

        $controller = new LgpdController();
        $request = Request::create('/lgpd/export', 'GET');
        $request->setUserResolver(fn () => $this->adminUser);

        $response = $controller->exportMyData($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $this->assertNotNull($response->headers->get('Content-Disposition'));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($this->adminUser->name, $data['user']['name']);
        $this->assertEquals($this->adminUser->email, $data['user']['email']);
        $this->assertArrayHasKey('exported_at', $data);
    }

    public function test_export_includes_correct_user_fields(): void
    {
        $this->actingAs($this->regularUser);

        $controller = new LgpdController();
        $request = Request::create('/lgpd/export', 'GET');
        $request->setUserResolver(fn () => $this->regularUser);

        $response = $controller->exportMyData($request);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('name', $data['user']);
        $this->assertArrayHasKey('email', $data['user']);
        $this->assertArrayHasKey('role', $data['user']);
        $this->assertArrayHasKey('created_at', $data['user']);
    }

    // ==========================================
    // Account Deletion (Anonymization)
    // ==========================================

    public function test_user_can_request_account_deletion(): void
    {
        $user = User::factory()->create([
            'role' => Role::USER->value,
            'password' => bcrypt('password'),
            'access_level_id' => 4,
        ]);

        $this->actingAs($user);

        $controller = new LgpdController();
        $request = Request::create('/lgpd/delete', 'POST', [
            'password' => 'password',
            'confirm' => true,
        ]);
        $request->setUserResolver(fn () => $user);

        // Simulate session for auth guard
        $request->setLaravelSession(app('session.store'));

        $controller->requestDeletion($request);

        $user->refresh();
        $this->assertEquals('Usuário Removido', $user->name);
        $this->assertStringStartsWith('deleted_', $user->email);
        $this->assertStringStartsWith('deleted_', $user->username);
        $this->assertNull($user->avatar);
        $this->assertNull($user->terms_accepted_at);
    }

    public function test_account_deletion_requires_correct_password(): void
    {
        $this->actingAs($this->regularUser);

        $controller = new LgpdController();
        $request = Request::create('/lgpd/delete', 'POST', [
            'password' => 'wrong-password',
            'confirm' => true,
        ]);
        $request->setUserResolver(fn () => $this->regularUser);
        $request->setLaravelSession(app('session.store'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $controller->requestDeletion($request);
    }

    public function test_account_deletion_requires_confirmation(): void
    {
        $this->actingAs($this->regularUser);

        $controller = new LgpdController();
        $request = Request::create('/lgpd/delete', 'POST', [
            'password' => 'password',
        ]);
        $request->setUserResolver(fn () => $this->regularUser);
        $request->setLaravelSession(app('session.store'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $controller->requestDeletion($request);
    }

    public function test_anonymized_user_has_no_real_data(): void
    {
        $originalName = 'Test User For Deletion';
        $originalEmail = 'test-delete@example.com';

        $user = User::factory()->create([
            'name' => $originalName,
            'email' => $originalEmail,
            'role' => Role::USER->value,
            'password' => bcrypt('password'),
            'access_level_id' => 4,
            'terms_accepted_at' => now(),
            'terms_version' => '1.0',
        ]);

        $this->actingAs($user);

        $controller = new LgpdController();
        $request = Request::create('/lgpd/delete', 'POST', [
            'password' => 'password',
            'confirm' => true,
        ]);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));

        $controller->requestDeletion($request);

        $user->refresh();
        $this->assertNotEquals($originalName, $user->name);
        $this->assertNotEquals($originalEmail, $user->email);
        $this->assertNull($user->terms_accepted_at);
        $this->assertNull($user->terms_version);
    }
}
