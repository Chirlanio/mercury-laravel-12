<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\DeliveryRouteItem;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeliveryRouteService
{
    /**
     * Create a route with deliveries.
     */
    public function createRoute(int $driverId, string $dateRoute, array $deliveryIds, ?string $notes, int $userId): DeliveryRoute
    {
        return DB::transaction(function () use ($driverId, $dateRoute, $deliveryIds, $notes, $userId) {
            $route = DeliveryRoute::create([
                'route_number' => DeliveryRoute::generateRouteNumber($dateRoute),
                'driver_id' => $driverId,
                'date_route' => $dateRoute,
                'status' => DeliveryRoute::STATUS_PENDING,
                'notes' => $notes,
                'created_by_user_id' => $userId,
            ]);

            foreach ($deliveryIds as $index => $deliveryId) {
                $delivery = Delivery::findOrFail($deliveryId);

                DeliveryRouteItem::create([
                    'route_id' => $route->id,
                    'delivery_id' => $deliveryId,
                    'sequence_order' => $index,
                    'client_name' => $delivery->client_name,
                    'address' => $delivery->address,
                    'created_by_user_id' => $userId,
                    'created_at' => now(),
                ]);

                // Update delivery status
                if ($delivery->status !== Delivery::STATUS_IN_ROUTE) {
                    $delivery->update(['status' => Delivery::STATUS_AWAITING_PICKUP]);
                }
            }

            return $route->load('items.delivery', 'driver');
        });
    }

    /**
     * Start a route (pending → in_route).
     */
    public function startRoute(DeliveryRoute $route): bool
    {
        if (! $route->canTransitionTo(DeliveryRoute::STATUS_IN_ROUTE)) {
            return false;
        }

        $route->update(['status' => DeliveryRoute::STATUS_IN_ROUTE]);

        // Update all deliveries to in_route
        $route->items()->with('delivery')->get()->each(function ($item) {
            if (! $item->delivery->isTerminal()) {
                $oldStatus = $item->delivery->status;
                $item->delivery->update(['status' => Delivery::STATUS_IN_ROUTE]);
                $item->delivery->logCustomAction('status_transition',
                    "Status alterado de '".Delivery::STATUS_LABELS[$oldStatus]."' para '".Delivery::STATUS_LABELS[Delivery::STATUS_IN_ROUTE]."' (rota iniciada)",
                    ['old_status' => $oldStatus, 'new_status' => Delivery::STATUS_IN_ROUTE]
                );
            }
        });

        return true;
    }

    /**
     * Complete a delivery item within a route.
     */
    public function completeItem(DeliveryRouteItem $item, string $status, ?string $receivedBy, ?string $notes, ?int $returnReasonId = null): bool
    {
        $delivery = $item->delivery;

        if ($delivery->isTerminal()) {
            return false;
        }

        $oldStatus = $delivery->status;

        $item->update([
            'delivered_at' => now(),
            'received_by' => $receivedBy,
            'delivery_notes' => $notes,
        ]);

        $updateData = ['status' => $status];
        if (Schema::hasColumn('deliveries', 'return_reason_id')) {
            $updateData['return_reason_id'] = $status === Delivery::STATUS_RETURNED ? $returnReasonId : null;
        }
        $delivery->update($updateData);

        $delivery->logCustomAction('status_transition',
            "Status alterado de '".Delivery::STATUS_LABELS[$oldStatus]."' para '".Delivery::STATUS_LABELS[$status]."' (item completado)",
            ['old_status' => $oldStatus, 'new_status' => $status]
        );

        // Check if all items in route are completed
        $route = $item->route;
        $pendingItems = $route->items()->whereNull('delivered_at')->count();

        if ($pendingItems === 0 && $route->status === DeliveryRoute::STATUS_IN_ROUTE) {
            $route->update(['status' => DeliveryRoute::STATUS_COMPLETED]);
        }

        return true;
    }

    /**
     * Cancel a route and release deliveries back to awaiting_pickup.
     */
    public function cancelRoute(DeliveryRoute $route): bool
    {
        if (! $route->canTransitionTo(DeliveryRoute::STATUS_CANCELLED)) {
            return false;
        }

        return DB::transaction(function () use ($route) {
            // Release non-terminal deliveries back to awaiting_pickup
            $route->items()->with('delivery')->get()->each(function ($item) {
                if (! $item->delivery->isTerminal()) {
                    $oldStatus = $item->delivery->status;
                    $item->delivery->update(['status' => Delivery::STATUS_AWAITING_PICKUP]);
                    $item->delivery->logCustomAction('status_transition',
                        "Status alterado de '".Delivery::STATUS_LABELS[$oldStatus]."' para '".Delivery::STATUS_LABELS[Delivery::STATUS_AWAITING_PICKUP]."' (rota cancelada)",
                        ['old_status' => $oldStatus, 'new_status' => Delivery::STATUS_AWAITING_PICKUP]
                    );
                }
            });

            $route->update(['status' => DeliveryRoute::STATUS_CANCELLED]);

            return true;
        });
    }

    /**
     * Edit a route: change driver, date, and/or deliveries.
     */
    public function editRoute(DeliveryRoute $route, ?int $driverId, ?string $dateRoute, ?array $deliveryIds, ?string $notes, ?int $userId = null): DeliveryRoute
    {
        return DB::transaction(function () use ($route, $driverId, $dateRoute, $deliveryIds, $notes, $userId) {
            // Update route fields
            $updates = [];
            if ($userId !== null) {
                $updates['updated_by_user_id'] = $userId;
            }
            if ($driverId !== null) {
                $updates['driver_id'] = $driverId;
            }
            if ($dateRoute !== null) {
                $updates['date_route'] = $dateRoute;
            }
            if ($notes !== null) {
                $updates['notes'] = $notes;
            }
            if (! empty($updates)) {
                $route->update($updates);
            }

            // Update deliveries if provided
            if ($deliveryIds !== null) {
                $currentIds = $route->items()->pluck('delivery_id')->toArray();
                $newIds = $deliveryIds;

                // Remove items no longer in route → release deliveries
                $removedIds = array_diff($currentIds, $newIds);
                if (! empty($removedIds)) {
                    $route->items()->whereIn('delivery_id', $removedIds)->delete();
                    Delivery::whereIn('id', $removedIds)
                        ->whereNotIn('status', Delivery::TERMINAL_STATUSES)
                        ->update(['status' => Delivery::STATUS_AWAITING_PICKUP]);
                }

                // Add new items
                $addedIds = array_diff($newIds, $currentIds);
                $maxOrder = $route->items()->max('sequence_order') ?? -1;
                foreach ($addedIds as $deliveryId) {
                    $delivery = Delivery::find($deliveryId);
                    if (! $delivery) {
                        continue;
                    }
                    $maxOrder++;
                    DeliveryRouteItem::create([
                        'route_id' => $route->id,
                        'delivery_id' => $deliveryId,
                        'sequence_order' => $maxOrder,
                        'client_name' => $delivery->client_name,
                        'address' => $delivery->address,
                        'created_at' => now(),
                    ]);
                    if (! $delivery->isTerminal()) {
                        $delivery->update(['status' => Delivery::STATUS_AWAITING_PICKUP]);
                    }
                }

                // Reorder
                $route->items()->orderBy('sequence_order')->get()->each(function ($item, $index) {
                    $item->update(['sequence_order' => $index]);
                });
            }

            return $route->fresh()->load('items.delivery', 'driver');
        });
    }

    /**
     * Get route statistics.
     */
    public function getStatistics(): array
    {
        $total = DeliveryRoute::count();
        $totalItems = DeliveryRouteItem::count();
        $completedRoutes = DeliveryRoute::forStatus(DeliveryRoute::STATUS_COMPLETED)->count();
        $deliveredItems = DeliveryRouteItem::whereNotNull('delivered_at')->count();

        return [
            'total_routes' => $total,
            'total_deliveries' => $totalItems,
            'avg_per_route' => $total > 0 ? round($totalItems / $total, 1) : 0,
            'completed_routes' => $completedRoutes,
            'in_route' => DeliveryRoute::forStatus(DeliveryRoute::STATUS_IN_ROUTE)->count(),
            'pending' => DeliveryRoute::forStatus(DeliveryRoute::STATUS_PENDING)->count(),
            'delivered_items' => $deliveredItems,
            'delivery_rate' => $totalItems > 0 ? round(($deliveredItems / $totalItems) * 100, 1) : 0,
        ];
    }

    /**
     * Get driver dashboard: today's route + completed deliveries history.
     */
    public function getDriverDashboard(int $driverId): array
    {
        $today = now()->toDateString();

        // Rota de hoje (in_route > pending)
        $route = DeliveryRoute::with(['items.delivery', 'driver'])
            ->forDriver($driverId)
            ->forDate($today)
            ->whereIn('status', [DeliveryRoute::STATUS_IN_ROUTE, DeliveryRoute::STATUS_PENDING])
            ->orderByRaw("CASE WHEN status = 'in_route' THEN 0 ELSE 1 END")
            ->first();

        if (! $route) {
            $completedRoutes = DeliveryRoute::with(['items.delivery'])
                ->forDriver($driverId)
                ->forStatus(DeliveryRoute::STATUS_COMPLETED)
                ->orderByDesc('date_route')
                ->limit(10)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'route_number' => $r->route_number,
                    'date' => $r->date_route->format('d/m/Y'),
                    'total_items' => $r->items->count(),
                    'delivered' => $r->items->whereNotNull('delivered_at')->count(),
                ]);

            return ['route' => null, 'items' => [], 'history' => $completedRoutes->values()];
        }

        $items = $route->items->map(fn ($item) => [
            'id' => $item->id,
            'sequence' => $item->sequence_order + 1,
            'client_name' => $item->client_name ?? $item->delivery->client_name,
            'address' => $item->address ?? $item->delivery->address,
            'contact_phone' => $item->delivery->contact_phone,
            'sale_value' => $item->delivery->sale_value,
            'needs_card_machine' => $item->delivery->needs_card_machine,
            'is_exchange' => $item->delivery->is_exchange,
            'is_gift' => $item->delivery->is_gift,
            'delivery_status' => $item->delivery->status,
            'delivery_status_label' => $item->delivery->status_label,
            'is_delivered' => $item->is_delivered,
            'delivered_at' => $item->delivered_at?->format('H:i'),
            'received_by' => $item->received_by,
            'lat' => $item->delivery->latitude ? (float) $item->delivery->latitude : null,
            'lng' => $item->delivery->longitude ? (float) $item->delivery->longitude : null,
        ]);

        $deliveredCount = $items->where('is_delivered', true)->count();

        // Histórico de rotas concluídas (últimas 10)
        $completedRoutes = DeliveryRoute::with(['items.delivery'])
            ->forDriver($driverId)
            ->forStatus(DeliveryRoute::STATUS_COMPLETED)
            ->orderByDesc('date_route')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'route_number' => $r->route_number,
                'date' => $r->date_route->format('d/m/Y'),
                'total_items' => $r->items->count(),
                'delivered' => $r->items->whereNotNull('delivered_at')->count(),
            ]);

        return [
            'route' => [
                'id' => $route->id,
                'route_number' => $route->route_number,
                'status' => $route->status,
                'status_label' => $route->status_label,
                'driver_name' => $route->driver->name,
                'date' => $route->date_route->format('d/m/Y'),
                'total_items' => $items->count(),
                'delivered_count' => $deliveredCount,
            ],
            'items' => $items->values(),
            'history' => $completedRoutes->values(),
        ];
    }

    /**
     * Get full delivery history for a driver with stats.
     */
    public function getDriverHistory(int $driverId, ?string $search = null): array
    {
        $routesQuery = DeliveryRoute::with(['items.delivery'])
            ->forDriver($driverId)
            ->whereNot('status', DeliveryRoute::STATUS_CANCELLED)
            ->latest('date_route');

        $allRoutes = (clone $routesQuery)->get();

        // Stats
        $totalRoutes = $allRoutes->count();
        $totalItems = $allRoutes->sum(fn ($r) => $r->items->count());
        $deliveredItems = $allRoutes->sum(fn ($r) => $r->items->whereNotNull('delivered_at')->where(fn ($i) => $i->delivery->status === Delivery::STATUS_DELIVERED)->count());
        $returnedItems = $allRoutes->sum(fn ($r) => $r->items->whereNotNull('delivered_at')->where(fn ($i) => $i->delivery->status === Delivery::STATUS_RETURNED)->count());

        $stats = [
            'total_routes' => $totalRoutes,
            'completed_routes' => $allRoutes->where('status', DeliveryRoute::STATUS_COMPLETED)->count(),
            'total_items' => $totalItems,
            'delivered' => $deliveredItems,
            'returned' => $returnedItems,
            'delivery_rate' => $totalItems > 0 ? round(($deliveredItems / $totalItems) * 100, 1) : 0,
        ];

        // Paginated routes with search
        $query = DeliveryRoute::with(['items.delivery'])
            ->forDriver($driverId)
            ->whereNot('status', DeliveryRoute::STATUS_CANCELLED)
            ->latest('date_route');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('route_number', 'like', "%{$search}%")
                    ->orWhereHas('items', fn ($iq) => $iq->where('client_name', 'like', "%{$search}%"));
            });
        }

        $routes = $query->paginate(10)->through(fn ($r) => [
            'id' => $r->id,
            'route_number' => $r->route_number,
            'date' => $r->date_route->format('d/m/Y'),
            'status' => $r->status,
            'status_label' => $r->status_label,
            'status_color' => $r->status_color,
            'total_items' => $r->items->count(),
            'delivered' => $r->items->whereNotNull('delivered_at')->count(),
            'items' => $r->items->map(fn ($item) => [
                'client_name' => $item->client_name,
                'address' => $item->address,
                'delivery_status' => $item->delivery->status,
                'delivery_status_label' => $item->delivery->status_label,
                'delivered_at' => $item->delivered_at?->format('d/m/Y H:i'),
                'received_by' => $item->received_by,
            ]),
        ]);

        return ['stats' => $stats, 'routes' => $routes];
    }
}
