<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\DeliveryRouteItem;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;

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
                $item->delivery->update(['status' => Delivery::STATUS_IN_ROUTE]);
            }
        });

        return true;
    }

    /**
     * Complete a delivery item within a route.
     */
    public function completeItem(DeliveryRouteItem $item, string $status, ?string $receivedBy, ?string $notes): bool
    {
        $delivery = $item->delivery;

        if ($delivery->isTerminal()) {
            return false;
        }

        $item->update([
            'delivered_at' => now(),
            'received_by' => $receivedBy,
            'delivery_notes' => $notes,
        ]);

        $delivery->update(['status' => $status]);

        // Check if all items in route are completed
        $route = $item->route;
        $pendingItems = $route->items()->whereNull('delivered_at')->count();

        if ($pendingItems === 0 && $route->status === DeliveryRoute::STATUS_IN_ROUTE) {
            $route->update(['status' => DeliveryRoute::STATUS_COMPLETED]);
        }

        return true;
    }

    /**
     * Get driver dashboard data for today.
     */
    public function getDriverDashboard(int $driverId): array
    {
        $today = now()->toDateString();

        $route = DeliveryRoute::with(['items.delivery', 'driver'])
            ->forDriver($driverId)
            ->forDate($today)
            ->whereIn('status', [DeliveryRoute::STATUS_PENDING, DeliveryRoute::STATUS_IN_ROUTE])
            ->first();

        if (! $route) {
            return ['route' => null, 'items' => []];
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
        ]);

        $deliveredCount = $items->where('is_delivered', true)->count();

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
        ];
    }
}
