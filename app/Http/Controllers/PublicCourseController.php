<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Central\GoogleAuthController;
use App\Models\TrainingCourse;
use App\Models\TrainingCourseEnrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PublicCourseController extends Controller
{
    // ==========================================
    // Catálogo público
    // ==========================================

    public function catalog()
    {
        $courses = TrainingCourse::active()
            ->published()
            ->where('visibility', TrainingCourse::VISIBILITY_PUBLIC)
            ->with(['subject', 'facilitator'])
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'description' => $c->description,
                'thumbnail_path' => $c->thumbnail_path,
                'estimated_duration_minutes' => $c->estimated_duration_minutes,
                'content_count' => $c->contents()->count(),
                'enrollment_count' => $c->enrollments()->count(),
                'subject' => $c->subject ? ['name' => $c->subject->name] : null,
                'facilitator' => $c->facilitator ? ['name' => $c->facilitator->name] : null,
                'certificate_on_completion' => $c->certificate_on_completion,
            ]);

        $user = Auth::user();
        $enrolledIds = [];
        if ($user) {
            $enrolledIds = TrainingCourseEnrollment::where('user_id', $user->id)
                ->pluck('course_id')
                ->toArray();
        }

        // Build Google redirect URL (central domain)
        $tenantId = tenant('id');
        $googleRedirectUrl = null;
        if ($tenantId) {
            $googleRedirectUrl = $this->buildGoogleRedirectUrl('training', $tenantId, route('public.courses.catalog'));
        }

        return Inertia::render('Trainings/PublicCatalog', [
            'courses' => $courses,
            'enrolledIds' => $enrolledIds,
            'authenticated' => Auth::check(),
            'userName' => $user?->name,
            'googleRedirectUrl' => $googleRedirectUrl,
        ]);
    }

    // ==========================================
    // Google OAuth cross-domain login
    // ==========================================

    public function googleLogin(Request $request)
    {
        $token = $request->query('token');
        $intended = $request->query('intended', '/cursos');

        if (! $token) {
            return redirect('/cursos')->with('error', 'Token de login inválido.');
        }

        $payload = GoogleAuthController::verifyLoginToken($token);
        if (! $payload) {
            return redirect('/cursos')->with('error', 'Token expirado ou inválido. Tente fazer login novamente.');
        }

        $user = User::find($payload['user_id']);
        if (! $user) {
            return redirect('/cursos')->with('error', 'Usuário não encontrado.');
        }

        Auth::login($user, remember: true);

        return redirect($intended);
    }

    // ==========================================
    // Inscrição pública
    // ==========================================

    public function enroll(TrainingCourse $trainingCourse)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'Faça login para se inscrever.'], 401);
        }

        if ($trainingCourse->status !== TrainingCourse::STATUS_PUBLISHED) {
            return response()->json(['error' => 'Curso não disponível.'], 422);
        }

        if ($trainingCourse->visibility !== TrainingCourse::VISIBILITY_PUBLIC) {
            return response()->json(['error' => 'Este curso não está disponível publicamente.'], 403);
        }

        $enrollment = TrainingCourseEnrollment::firstOrCreate(
            ['course_id' => $trainingCourse->id, 'user_id' => $user->id],
            [
                'status' => TrainingCourseEnrollment::STATUS_ENROLLED,
                'enrolled_at' => now(),
            ]
        );

        return response()->json([
            'enrollment' => $enrollment,
            'message' => 'Inscrição realizada com sucesso.',
        ]);
    }

    // ==========================================
    // Helpers
    // ==========================================

    protected function buildGoogleRedirectUrl(string $context, string $tenantId, string $intended): string
    {
        $centralDomain = config('tenancy.central_domains', ['localhost'])[0] ?? 'localhost';
        $routeName = $context === 'training' ? 'google.training.redirect' : 'google.experience.redirect';

        // Build URL manually for central domain
        $path = $context === 'training'
            ? '/mercury/public-training/google-redirect'
            : '/mercury/public-experience-evaluation/google-redirect';

        $query = http_build_query([
            'tenant' => $tenantId,
            'intended' => $intended,
        ]);

        return "http://{$centralDomain}{$path}?{$query}";
    }
}
