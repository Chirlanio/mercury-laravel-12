<?php

namespace App\Http\Controllers;

use App\Models\HdSatisfactionSurvey;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Public-facing CSAT survey endpoints. Accessed via a Laravel signed
 * URL (temporarySignedRoute) so the requester doesn't need to be logged
 * in to respond — they just click the link in their email/WhatsApp and
 * land on a minimal form with 5 star buttons.
 *
 * The signed middleware is applied at the route level.
 */
class HdCsatController extends Controller
{
    public function show(Request $request, string $token)
    {
        $survey = HdSatisfactionSurvey::where('signed_token', $token)->with('ticket')->first();

        if (! $survey) {
            return Inertia::render('Helpdesk/Csat/Expired', [
                'reason' => 'not_found',
            ]);
        }

        if ($survey->isExpired()) {
            return Inertia::render('Helpdesk/Csat/Expired', [
                'reason' => 'expired',
                'expires_at' => $survey->expires_at->format('d/m/Y'),
            ]);
        }

        if ($survey->isSubmitted()) {
            return Inertia::render('Helpdesk/Csat/Submitted', [
                'rating' => $survey->rating,
                'already_submitted' => true,
            ]);
        }

        return Inertia::render('Helpdesk/Csat/Show', [
            'token' => $survey->signed_token,
            'ticket' => [
                'id' => $survey->ticket?->id,
                'title' => $survey->ticket?->title,
            ],
            'expires_at' => $survey->expires_at->format('d/m/Y'),
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $survey = HdSatisfactionSurvey::where('signed_token', $token)->first();

        if (! $survey || $survey->isExpired()) {
            return Inertia::render('Helpdesk/Csat/Expired', [
                'reason' => $survey ? 'expired' : 'not_found',
            ]);
        }

        if ($survey->isSubmitted()) {
            return Inertia::render('Helpdesk/Csat/Submitted', [
                'rating' => $survey->rating,
                'already_submitted' => true,
            ]);
        }

        $survey->update([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'submitted_at' => now(),
        ]);

        return Inertia::render('Helpdesk/Csat/Submitted', [
            'rating' => $survey->rating,
            'already_submitted' => false,
        ]);
    }
}
