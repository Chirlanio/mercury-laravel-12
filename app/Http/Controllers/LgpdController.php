<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureTermsAccepted;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LgpdController extends Controller
{
    public function showTerms()
    {
        $user = auth()->user();

        return Inertia::render('Lgpd/Terms', [
            'termsVersion' => EnsureTermsAccepted::TERMS_VERSION,
            'hasAccepted' => $user && $user->terms_accepted_at && $user->terms_version === EnsureTermsAccepted::TERMS_VERSION,
            'tenant' => tenant() ? [
                'name' => tenant()->name,
            ] : null,
        ]);
    }

    public function acceptTerms(Request $request)
    {
        $user = $request->user();

        $user->update([
            'terms_accepted_at' => now(),
            'terms_version' => EnsureTermsAccepted::TERMS_VERSION,
            'terms_ip' => $request->ip(),
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Termos aceitos com sucesso.');
    }

    public function showPrivacy()
    {
        return Inertia::render('Lgpd/Privacy', [
            'tenant' => tenant() ? ['name' => tenant()->name] : null,
        ]);
    }

    /**
     * Export all personal data for the authenticated user (LGPD Art. 18).
     */
    public function exportMyData(Request $request)
    {
        $user = $request->user();

        $data = [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value ?? $user->role,
                'created_at' => $user->created_at->toIso8601String(),
                'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
            ],
            'exported_at' => now()->toIso8601String(),
            'tenant' => tenant()?->name,
        ];

        $filename = 'meus-dados-' . now()->format('Y-m-d') . '.json';

        return response()->json($data)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->header('Content-Type', 'application/json');
    }

    /**
     * Request account deletion (LGPD Art. 18 - right to deletion).
     */
    public function requestDeletion(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
            'confirm' => 'required|accepted',
        ]);

        $user = $request->user();

        // Anonymize instead of hard-delete to preserve referential integrity
        $user->update([
            'name' => 'Usuário Removido',
            'email' => "deleted_{$user->id}@removed.local",
            'username' => "deleted_{$user->id}",
            'password' => bcrypt(\Illuminate\Support\Str::random(64)),
            'avatar' => null,
            'terms_accepted_at' => null,
            'terms_version' => null,
            'remember_token' => null,
        ]);

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('info', 'Seus dados foram anonimizados conforme solicitado.');
    }
}
