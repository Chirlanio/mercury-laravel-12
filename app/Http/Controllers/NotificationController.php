<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * JSON: payload do dropdown (últimas N notificações) + unread_count.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = Auth::user();
        $limit = min(max((int) $request->integer('limit', 10), 1), 30);

        $notifications = $user->notifications()
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $n) => $this->transform($n));

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * JSON: unread count only (polling leve pro badge).
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => Auth::user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Marca uma notificação como lida.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json(['message' => 'Notificação não encontrada.'], 404);
        }

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Marca todas como lidas.
     */
    public function markAllAsRead(): JsonResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['unread_count' => 0]);
    }

    /**
     * Normaliza shape do payload pro frontend.
     */
    protected function transform(DatabaseNotification $n): array
    {
        $data = $n->data ?? [];

        return [
            'id' => $n->id,
            'type' => $data['type'] ?? class_basename($n->type),
            'title' => $data['title'] ?? $this->fallbackTitle($data),
            'message' => $data['message'] ?? null,
            'url' => $data['url'] ?? null,
            'data' => $data,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
            'created_at_human' => $n->created_at?->diffForHumans(),
        ];
    }

    protected function fallbackTitle(array $data): string
    {
        foreach (['subject', 'name', 'order_number', 'ticket_title'] as $key) {
            if (! empty($data[$key])) {
                return (string) $data[$key];
            }
        }

        return 'Notificação';
    }
}
