<?php

namespace App\Http\Controllers;

use App\Models\UserSession;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserSessionController extends Controller
{
    public function index(Request $request)
    {
        UserSession::markInactiveSessions();

        $query = UserSession::with(['user:id,name,email,avatar', 'store:id,code,name'])
            ->online()
            ->latest('last_activity_at');

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $sessions = $query->paginate(20)->through(fn ($session) => [
            'id' => $session->id,
            'user' => $session->user ? [
                'id' => $session->user->id,
                'name' => $session->user->name,
                'email' => $session->user->email,
                'avatar_url' => $session->user->avatar_url,
            ] : null,
            'store' => $session->store ? [
                'id' => $session->store->id,
                'name' => $session->store->display_name,
            ] : null,
            'ip_address' => $session->ip_address,
            'current_page' => $session->current_page,
            'last_activity_at' => $session->last_activity_at->diffForHumans(),
            'logged_in_at' => $session->logged_in_at->format('d/m/Y H:i'),
            'idle_status' => $session->idle_status,
        ]);

        $stores = Store::active()->orderedByStore()->get(['id', 'code', 'name']);

        return Inertia::render('UserSessions/Index', [
            'sessions' => $sessions,
            'stores' => $stores,
            'filters' => $request->only(['search', 'store_id']),
            'onlineCount' => UserSession::online()->count(),
        ]);
    }

    public function heartbeat(Request $request)
    {
        $user = $request->user();

        $session = UserSession::where('user_id', $user->id)
            ->where('is_online', true)
            ->latest('logged_in_at')
            ->first();

        if ($session) {
            if ($session->last_activity_at->diffInSeconds(now()) < 60) {
                return response()->json(['status' => 'ok']);
            }

            $session->update([
                'last_activity_at' => now(),
                'ip_address' => $request->ip(),
                'current_page' => $request->input('current_page'),
                'idle_status' => 'active',
                'idle_since' => null,
            ]);
        } else {
            UserSession::create([
                'user_id' => $user->id,
                'store_id' => $user->employee?->store_id ? Store::where('code', $user->employee->store_id)->value('id') : null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'logged_in_at' => now(),
                'last_activity_at' => now(),
                'is_online' => true,
                'current_page' => $request->input('current_page'),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
