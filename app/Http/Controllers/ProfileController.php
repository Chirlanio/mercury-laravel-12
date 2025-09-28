<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ImageUploadService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validatedData = $request->validated();

        // Remover avatar se solicitado
        if ($request->boolean('remove_avatar') && $user->avatar) {
            try {
                $imageUploadService = app(ImageUploadService::class);
                $imageUploadService->deleteFile($user->avatar);
                $user->avatar = null;
            } catch (\Exception $e) {
                return back()->withErrors(['avatar' => 'Erro ao remover a foto: ' . $e->getMessage()]);
            }
        }
        // Upload do avatar se fornecido
        elseif ($request->hasFile('avatar')) {
            try {
                $imageUploadService = app(ImageUploadService::class);
                $avatarPath = $imageUploadService->uploadUserAvatar(
                    $request->file('avatar'),
                    $user->avatar // Remove avatar antigo
                );
                $user->avatar = $avatarPath;
            } catch (\Exception $e) {
                return back()->withErrors(['avatar' => 'Erro no upload da imagem: ' . $e->getMessage()]);
            }
        }

        // Atualizar outros campos
        $user->fill([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('success', 'Perfil atualizado com sucesso!');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
