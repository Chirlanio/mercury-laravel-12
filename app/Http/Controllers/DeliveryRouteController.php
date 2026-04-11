<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\DeliveryRouteItem;
use App\Models\Driver;
use App\Services\DeliveryManifestService;
use App\Services\DeliveryRouteService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryRouteController extends Controller
{
    public function __construct(
        private DeliveryRouteService $routeService,
        private DeliveryManifestService $manifestService,
    ) {}

    public function index(Request $request)
    {
        $query = DeliveryRoute::with(['driver', 'createdBy'])
            ->withCount('items')
            ->latest();

        if ($request->filled('driver_id')) {
            $query->forDriver($request->driver_id);
        }
        if ($request->filled('status')) {
            $query->forStatus($request->status);
        }
        if ($request->filled('date_from')) {
            $query->where('date_route', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('date_route', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('route_number', 'like', "%{$search}%")
                    ->orWhereHas('driver', fn ($dq) => $dq->where('name', 'like', "%{$search}%"));
            });
        }

        $routes = $query->paginate(20)->through(fn ($r) => [
            'id' => $r->id,
            'route_number' => $r->route_number,
            'driver_name' => $r->driver?->name,
            'date_route' => $r->date_route->format('d/m/Y'),
            'date_route_raw' => $r->date_route->format('Y-m-d'),
            'items_count' => $r->items_count,
            'status' => $r->status,
            'status_label' => $r->status_label,
            'status_color' => $r->status_color,
            'created_by' => $r->createdBy?->name,
            'created_at' => $r->created_at->format('d/m/Y H:i'),
        ]);

        return Inertia::render('DeliveryRoutes/Index', [
            'routes' => $routes,
            'filters' => $request->only(['search', 'driver_id', 'status', 'date_from', 'date_to']),
            'statusOptions' => DeliveryRoute::STATUS_LABELS,
            'drivers' => Driver::active()->orderBy('name')->get(['id', 'name']),
            'availableDeliveries' => Delivery::availableForRoute()
                ->with('store')
                ->latest()
                ->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'client_name' => $d->client_name,
                    'address' => $d->address,
                    'store_name' => $d->store?->name,
                    'status_label' => $d->status_label,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'date_route' => 'required|date',
            'delivery_ids' => 'required|array|min:1',
            'delivery_ids.*' => 'exists:deliveries,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $route = $this->routeService->createRoute(
            $validated['driver_id'],
            $validated['date_route'],
            $validated['delivery_ids'],
            $validated['notes'] ?? null,
            auth()->id(),
        );

        return redirect()->route('delivery-routes.index')
            ->with('success', "Rota {$route->route_number} criada com {$route->items->count()} entregas.");
    }

    public function show(DeliveryRoute $deliveryRoute)
    {
        $deliveryRoute->load(['driver', 'items.delivery.store', 'createdBy']);

        $deliveredCount = $deliveryRoute->items->where('delivered_at', '!=', null)->count();

        return response()->json([
            'route' => [
                'id' => $deliveryRoute->id,
                'route_number' => $deliveryRoute->route_number,
                'driver_name' => $deliveryRoute->driver?->name,
                'date_route' => $deliveryRoute->date_route->format('d/m/Y'),
                'status' => $deliveryRoute->status,
                'status_label' => $deliveryRoute->status_label,
                'status_color' => $deliveryRoute->status_color,
                'notes' => $deliveryRoute->notes,
                'total_items' => $deliveryRoute->items->count(),
                'delivered_count' => $deliveredCount,
                'valid_transitions' => DeliveryRoute::VALID_TRANSITIONS[$deliveryRoute->status] ?? [],
                'transition_labels' => collect(DeliveryRoute::VALID_TRANSITIONS[$deliveryRoute->status] ?? [])
                    ->mapWithKeys(fn ($s) => [$s => DeliveryRoute::STATUS_LABELS[$s]])
                    ->toArray(),
                'items' => $deliveryRoute->items->map(fn ($item) => [
                    'id' => $item->id,
                    'sequence' => $item->sequence_order + 1,
                    'client_name' => $item->client_name,
                    'address' => $item->address,
                    'contact_phone' => $item->delivery->contact_phone,
                    'store_name' => $item->delivery->store?->name,
                    'delivery_status' => $item->delivery->status,
                    'delivery_status_label' => $item->delivery->status_label,
                    'is_delivered' => $item->is_delivered,
                    'delivered_at' => $item->delivered_at?->format('d/m/Y H:i'),
                    'received_by' => $item->received_by,
                    'delivery_notes' => $item->delivery_notes,
                ]),
                'created_by' => $deliveryRoute->createdBy?->name,
                'created_at' => $deliveryRoute->created_at->format('d/m/Y H:i'),
            ],
        ]);
    }

    public function startRoute(DeliveryRoute $deliveryRoute)
    {
        if (! $this->routeService->startRoute($deliveryRoute)) {
            return response()->json(['error' => 'Não é possível iniciar esta rota.'], 422);
        }

        return response()->json(['message' => 'Rota iniciada com sucesso.']);
    }

    public function completeItem(Request $request, DeliveryRoute $deliveryRoute, DeliveryRouteItem $item)
    {
        if ($item->route_id !== $deliveryRoute->id) {
            return response()->json(['error' => 'Item não pertence a esta rota.'], 422);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:delivered,returned',
            'received_by' => 'nullable|string|max:255',
            'delivery_notes' => 'nullable|string|max:1000',
        ]);

        $deliveryStatus = $validated['status'] === 'delivered'
            ? Delivery::STATUS_DELIVERED
            : Delivery::STATUS_RETURNED;

        $success = $this->routeService->completeItem(
            $item,
            $deliveryStatus,
            $validated['received_by'] ?? null,
            $validated['delivery_notes'] ?? null,
        );

        if (! $success) {
            return response()->json(['error' => 'Não foi possível atualizar esta entrega.'], 422);
        }

        return response()->json(['message' => 'Entrega atualizada com sucesso.']);
    }

    public function cancel(DeliveryRoute $deliveryRoute)
    {
        if (! $deliveryRoute->canTransitionTo(DeliveryRoute::STATUS_CANCELLED)) {
            return response()->json(['error' => 'Não é possível cancelar esta rota.'], 422);
        }

        $deliveryRoute->update(['status' => DeliveryRoute::STATUS_CANCELLED]);

        return response()->json(['message' => 'Rota cancelada.']);
    }

    public function driverDashboard()
    {
        $user = auth()->user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (! $driver) {
            return Inertia::render('DeliveryRoutes/DriverDashboard', [
                'route' => null,
                'items' => [],
                'driverName' => $user->name,
            ]);
        }

        $data = $this->routeService->getDriverDashboard($driver->id);

        return Inertia::render('DeliveryRoutes/DriverDashboard', [
            'route' => $data['route'],
            'items' => $data['items'],
            'driverName' => $driver->name,
        ]);
    }

    public function printManifest(DeliveryRoute $deliveryRoute)
    {
        return $this->manifestService->generate($deliveryRoute);
    }
}
