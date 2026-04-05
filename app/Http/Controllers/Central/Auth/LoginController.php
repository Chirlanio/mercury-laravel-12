<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return Inertia::render('Central/Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('central')->attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::guard('central')->user();

            if (! $user->is_active) {
                Auth::guard('central')->logout();
                return back()->withErrors(['email' => 'Conta desativada.']);
            }

            $request->session()->regenerate();

            // Use relative path to avoid cross-domain CORS issues
            return redirect()->intended('/admin');
        }

        return back()->withErrors([
            'email' => 'Credenciais invalidas.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('central')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
