<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    // ==========================================
    // Training: redirect + callback
    // ==========================================

    public function redirectTraining(Request $request)
    {
        return $this->redirectToGoogle($request, 'training');
    }

    public function callbackTraining(Request $request)
    {
        return $this->handleCallback($request, 'training');
    }

    // ==========================================
    // Experience Evaluation: redirect + callback
    // ==========================================

    public function redirectExperience(Request $request)
    {
        return $this->redirectToGoogle($request, 'experience');
    }

    public function callbackExperience(Request $request)
    {
        return $this->handleCallback($request, 'experience');
    }

    // ==========================================
    // Shared logic
    // ==========================================

    protected function redirectToGoogle(Request $request, string $context): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $tenant = $request->query('tenant');
        $intended = $request->query('intended', '/');

        if (! $tenant) {
            abort(400, 'Parâmetro tenant é obrigatório.');
        }

        $callbackRoute = $context === 'training'
            ? 'google.training.callback'
            : 'google.experience.callback';

        $callbackUrl = route($callbackRoute);

        // Encode tenant + intended in state parameter
        $state = base64_encode(json_encode([
            'tenant' => $tenant,
            'intended' => $intended,
        ]));

        session(['google_oauth_state' => $state]);

        return Socialite::driver('google')
            ->redirectUrl($callbackUrl)
            ->with(['state' => $state])
            ->redirect();
    }

    protected function handleCallback(Request $request, string $context): \Illuminate\Http\RedirectResponse
    {
        // Recover state
        $stateRaw = $request->query('state', session('google_oauth_state'));
        $state = json_decode(base64_decode($stateRaw), true);

        if (! $state || empty($state['tenant'])) {
            return redirect('/')->with('error', 'Estado inválido. Tente novamente.');
        }

        $tenantId = $state['tenant'];
        $intended = $state['intended'] ?? '/cursos';

        // Get Google user
        $callbackRoute = $context === 'training'
            ? 'google.training.callback'
            : 'google.experience.callback';

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route($callbackRoute))
                ->stateless()
                ->user();
        } catch (\Exception $e) {
            Log::error('Google OAuth failed', ['error' => $e->getMessage()]);

            return $this->redirectToTenant($tenantId, $intended, 'Erro ao autenticar com Google.');
        }

        // Find the tenant
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return redirect('/')->with('error', 'Tenant não encontrado.');
        }

        // Run within tenant context: create/find user
        $userId = null;
        $tenant->run(function () use ($googleUser, &$userId) {
            $user = User::where('google_id', $googleUser->getId())->first();

            if (! $user) {
                $user = User::where('email', $googleUser->getEmail())->first();

                if ($user) {
                    $user->update(['google_id' => $googleUser->getId()]);
                } else {
                    $user = User::create([
                        'name' => $googleUser->getName(),
                        'email' => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar(),
                        'role' => \App\Enums\Role::USER->value,
                        'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                    ]);
                }
            }

            $userId = $user->id;
        });

        // Generate signed login token for tenant domain
        $token = $this->generateLoginToken($tenantId, $userId);

        // Build tenant URL
        $tenantDomain = $tenant->domains()->first()?->domain ?? "{$tenantId}.localhost";
        $loginUrl = "http://{$tenantDomain}/auth/google-login?token={$token}&intended=".urlencode($intended);

        return redirect($loginUrl);
    }

    // ==========================================
    // Signed token
    // ==========================================

    protected function generateLoginToken(string $tenantId, int $userId): string
    {
        $payload = json_encode([
            'tenant' => $tenantId,
            'user_id' => $userId,
            'expires' => now()->addMinutes(5)->timestamp,
        ]);

        $encoded = base64_encode($payload);
        $signature = hash_hmac('sha256', $encoded, config('app.key'));

        return $encoded.'.'.$signature;
    }

    public static function verifyLoginToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;

        $expectedSignature = hash_hmac('sha256', $encoded, config('app.key'));
        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode(base64_decode($encoded), true);
        if (! $payload || ($payload['expires'] ?? 0) < now()->timestamp) {
            return null;
        }

        return $payload;
    }

    protected function redirectToTenant(string $tenantId, string $intended, string $error): \Illuminate\Http\RedirectResponse
    {
        $tenant = Tenant::find($tenantId);
        $domain = $tenant?->domains()->first()?->domain ?? "{$tenantId}.localhost";

        return redirect("http://{$domain}{$intended}")->with('error', $error);
    }
}
