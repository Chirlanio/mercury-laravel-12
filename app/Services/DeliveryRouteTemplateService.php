<?php

namespace App\Services;

use App\Models\DeliveryRoute;
use App\Models\DeliveryRouteTemplate;
use App\Models\DeliveryRouteTemplateStop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeliveryRouteTemplateService
{
    public function __construct(
        private DeliveryRouteService $routeService,
    ) {}

    /**
     * Create a new route template.
     */
    public function createTemplate(string $name, ?int $driverId, ?string $notes, array $stops, ?array $startPoint, int $userId): DeliveryRouteTemplate
    {
        return DB::transaction(function () use ($name, $driverId, $notes, $stops, $startPoint, $userId) {
            $template = DeliveryRouteTemplate::create([
                'name' => $name,
                'driver_id' => $driverId,
                'notes' => $notes,
                'start_point_lat' => $startPoint['lat'] ?? null,
                'start_point_lng' => $startPoint['lng'] ?? null,
                'is_active' => true,
                'created_by_user_id' => $userId,
            ]);

            foreach ($stops as $index => $stop) {
                DeliveryRouteTemplateStop::create([
                    'template_id' => $template->id,
                    'sequence_order' => $index,
                    'neighborhood' => $stop['neighborhood'] ?? null,
                    'address' => $stop['address'] ?? null,
                    'reference_name' => $stop['reference_name'] ?? null,
                    'latitude' => $stop['lat'] ?? null,
                    'longitude' => $stop['lng'] ?? null,
                ]);
            }

            return $template->load('stops');
        });
    }

    /**
     * Create a template from an existing completed/pending route.
     */
    public function createTemplateFromRoute(DeliveryRoute $route, string $name, int $userId): DeliveryRouteTemplate
    {
        $route->load('items.delivery', 'driver');

        $startConfig = config('delivery.default_start_point');

        return DB::transaction(function () use ($route, $name, $userId, $startConfig) {
            $template = DeliveryRouteTemplate::create([
                'name' => $name,
                'driver_id' => $route->driver_id,
                'notes' => $route->notes,
                'start_point_lat' => $startConfig['lat'],
                'start_point_lng' => $startConfig['lng'],
                'is_active' => true,
                'created_by_user_id' => $userId,
            ]);

            foreach ($route->items as $item) {
                DeliveryRouteTemplateStop::create([
                    'template_id' => $template->id,
                    'sequence_order' => $item->sequence_order,
                    'neighborhood' => $item->delivery->neighborhood,
                    'address' => $item->address ?? $item->delivery->address,
                    'reference_name' => $item->client_name ?? $item->delivery->client_name,
                    'latitude' => $item->delivery->latitude,
                    'longitude' => $item->delivery->longitude,
                ]);
            }

            return $template->load('stops', 'driver');
        });
    }

    /**
     * Create a new route from a template.
     */
    public function createRouteFromTemplate(DeliveryRouteTemplate $template, string $dateRoute, array $deliveryIds, int $userId): DeliveryRoute
    {
        return $this->routeService->createRoute(
            $template->driver_id,
            $dateRoute,
            $deliveryIds,
            $template->notes,
            $userId,
        );
    }

    /**
     * List active templates.
     */
    public function listTemplates(): Collection
    {
        if (! Schema::hasTable('delivery_route_templates')) {
            return collect();
        }

        return DeliveryRouteTemplate::active()
            ->with('driver')
            ->withCount('stops')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'driver_id' => $t->driver_id,
                'driver_name' => $t->driver?->name,
                'stops_count' => $t->stops_count,
                'notes' => $t->notes,
                'created_at' => $t->created_at->format('d/m/Y'),
            ]);
    }

    /**
     * Deactivate a template (soft-delete).
     */
    public function deleteTemplate(DeliveryRouteTemplate $template): bool
    {
        return $template->update(['is_active' => false]);
    }
}
