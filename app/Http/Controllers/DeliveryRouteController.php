<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryReturnReason;
use App\Models\DeliveryRoute;
use App\Models\DeliveryRouteItem;
use App\Models\DeliveryRouteTemplate;
use App\Models\Driver;
use App\Services\DeliveryManifestService;
use App\Services\DeliveryRouteService;
use App\Services\DeliveryRouteTemplateService;
use App\Services\DriverLocationService;
use App\Services\RouteOptimizationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryRouteController extends Controller
{
    public function __construct(
        private DeliveryRouteService $routeService,
        private DeliveryManifestService $manifestService,
        private RouteOptimizationService $optimizationService,
        private DeliveryRouteTemplateService $templateService,
        private DriverLocationService $locationService,
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

        $startConfig = config('delivery.default_start_point');
        $defaultStart = [
            'name' => $startConfig['name'],
            'address' => $startConfig['address'],
            'lat' => $startConfig['lat'],
            'lng' => $startConfig['lng'],
        ];

        return Inertia::render('DeliveryRoutes/Index', [
            'routes' => $routes,
            'filters' => $request->only(['search', 'driver_id', 'status', 'date_from', 'date_to']),
            'statusOptions' => DeliveryRoute::STATUS_LABELS,
            'drivers' => Driver::active()->orderBy('name')->get(['id', 'name']),
            'startPoint' => $defaultStart,
            'templates' => $this->templateService->listTemplates(),
            'returnReasons' => \Illuminate\Support\Facades\Schema::hasTable('delivery_return_reasons')
                ? DeliveryReturnReason::active()->orderBy('name')->get(['id', 'code', 'name'])
                : collect(),
            'availableDeliveries' => Delivery::availableForRoute()
                ->with('store')
                ->latest()
                ->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'client_name' => $d->client_name,
                    'address' => $d->address,
                    'neighborhood' => $d->neighborhood,
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
        $deliveryRoute->load(['driver', 'items.delivery.store', 'items.delivery.returnReason', 'createdBy']);

        $deliveredCount = $deliveryRoute->items->where('delivered_at', '!=', null)->count();

        return response()->json([
            'route' => [
                'id' => $deliveryRoute->id,
                'route_number' => $deliveryRoute->route_number,
                'driver_id' => $deliveryRoute->driver_id,
                'driver_name' => $deliveryRoute->driver?->name,
                'date_route' => $deliveryRoute->date_route->format('d/m/Y'),
                'date_route_raw' => $deliveryRoute->date_route->format('Y-m-d'),
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
                    'delivery_id' => $item->delivery_id,
                    'return_reason' => $item->delivery->returnReason?->name,
                    'lat' => $item->delivery->latitude ? (float) $item->delivery->latitude : null,
                    'lng' => $item->delivery->longitude ? (float) $item->delivery->longitude : null,
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
            'return_reason_id' => 'nullable|integer',
        ]);

        $deliveryStatus = $validated['status'] === 'delivered'
            ? Delivery::STATUS_DELIVERED
            : Delivery::STATUS_RETURNED;

        $success = $this->routeService->completeItem(
            $item,
            $deliveryStatus,
            $validated['received_by'] ?? null,
            $validated['delivery_notes'] ?? null,
            $validated['return_reason_id'] ?? null,
        );

        if (! $success) {
            return response()->json(['error' => 'Não foi possível atualizar esta entrega.'], 422);
        }

        return response()->json(['message' => 'Entrega atualizada com sucesso.']);
    }

    public function update(Request $request, DeliveryRoute $deliveryRoute)
    {
        if (in_array($deliveryRoute->status, [DeliveryRoute::STATUS_COMPLETED, DeliveryRoute::STATUS_CANCELLED])) {
            return response()->json(['error' => 'Não é possível editar uma rota concluída ou cancelada.'], 422);
        }

        $validated = $request->validate([
            'driver_id' => 'nullable|exists:drivers,id',
            'date_route' => 'nullable|date',
            'delivery_ids' => 'nullable|array|min:1',
            'delivery_ids.*' => 'exists:deliveries,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $route = $this->routeService->editRoute(
            $deliveryRoute,
            $validated['driver_id'] ?? null,
            $validated['date_route'] ?? null,
            $validated['delivery_ids'] ?? null,
            $validated['notes'] ?? null,
            auth()->id(),
        );

        return response()->json(['message' => 'Rota atualizada com sucesso.']);
    }

    public function cancel(DeliveryRoute $deliveryRoute)
    {
        if (! $this->routeService->cancelRoute($deliveryRoute)) {
            return response()->json(['error' => 'Não é possível cancelar esta rota.'], 422);
        }

        return response()->json(['message' => 'Rota cancelada. Entregas liberadas para nova roteirização.']);
    }

    public function statistics()
    {
        return response()->json($this->routeService->getStatistics());
    }

    public function driverDashboard()
    {
        $user = auth()->user();
        $driver = Driver::where('user_id', $user->id)->first();

        $returnReasons = \Illuminate\Support\Facades\Schema::hasTable('delivery_return_reasons')
            ? DeliveryReturnReason::active()->orderBy('name')->get(['id', 'code', 'name'])
            : collect();

        if (! $driver) {
            return Inertia::render('DeliveryRoutes/DriverDashboard', [
                'route' => null,
                'items' => [],
                'history' => [],
                'driverName' => $user->name,
                'returnReasons' => $returnReasons,
            ]);
        }

        $data = $this->routeService->getDriverDashboard($driver->id);

        return Inertia::render('DeliveryRoutes/DriverDashboard', [
            'route' => $data['route'],
            'items' => $data['items'],
            'history' => $data['history'] ?? [],
            'driverName' => $driver->name,
            'returnReasons' => $returnReasons,
        ]);
    }

    public function optimizePreview(Request $request)
    {
        $validated = $request->validate([
            'delivery_ids' => 'required|array|min:2',
            'delivery_ids.*' => 'exists:deliveries,id',
            'start_lat' => 'nullable|numeric',
            'start_lng' => 'nullable|numeric',
        ]);

        // Buscar TODAS as entregas selecionadas
        $allDeliveries = Delivery::whereIn('id', $validated['delivery_ids'])
            ->get(['id', 'latitude', 'longitude', 'client_name', 'address']);

        // Separar geocodificadas das sem coordenadas
        $geocoded = $allDeliveries->filter(fn ($d) => $d->latitude && $d->longitude);
        $notGeocoded = $allDeliveries->filter(fn ($d) => ! $d->latitude || ! $d->longitude);

        if ($geocoded->count() < 2) {
            return response()->json([
                'error' => 'É necessário pelo menos 2 entregas geocodificadas para otimizar.',
                'geocoded_count' => $geocoded->count(),
                'total_count' => $allDeliveries->count(),
            ], 422);
        }

        $points = $geocoded->map(fn ($d) => [
            'id' => $d->id,
            'lat' => (float) $d->latitude,
            'lng' => (float) $d->longitude,
        ])->values()->toArray();

        // Ponto inicial: coordenadas enviadas pelo frontend (CD)
        $startPoint = null;
        if (! empty($validated['start_lat']) && ! empty($validated['start_lng'])) {
            $startPoint = [(float) $validated['start_lat'], (float) $validated['start_lng']];
        }

        $result = $this->optimizationService->optimize($points, $startPoint);

        // Ordem final: geocodificadas otimizadas + sem coordenadas no final
        $fullOrder = array_merge($result['order'], $notGeocoded->pluck('id')->toArray());

        // Get route geometry for map (incluindo ponto de saída)
        $orderedCoords = collect($result['order'])
            ->map(fn ($id) => $geocoded->firstWhere('id', $id))
            ->filter()
            ->map(fn ($d) => ['lat' => (float) $d->latitude, 'lng' => (float) $d->longitude])
            ->values()
            ->toArray();

        if ($startPoint) {
            array_unshift($orderedCoords, ['lat' => $startPoint[0], 'lng' => $startPoint[1]]);
        }

        $geometry = $this->optimizationService->getRouteGeometry($orderedCoords);

        // Build ordered delivery list (todas, com sequência contínua)
        $orderedDeliveries = collect($fullOrder)->map(function ($id, $index) use ($allDeliveries) {
            $d = $allDeliveries->firstWhere('id', $id);

            return $d ? [
                'id' => $d->id,
                'sequence' => $index + 1,
                'client_name' => $d->client_name,
                'address' => $d->address,
                'lat' => $d->latitude ? (float) $d->latitude : null,
                'lng' => $d->longitude ? (float) $d->longitude : null,
            ] : null;
        })->filter()->values();

        return response()->json([
            'order' => $fullOrder,
            'deliveries' => $orderedDeliveries,
            'start_point' => $startPoint ? ['lat' => $startPoint[0], 'lng' => $startPoint[1]] : null,
            'geometry' => $geometry,
            'distance_km' => round(($geometry['distance'] ?? $result['distance']) / 1000, 1),
            'duration_min' => round(($geometry['duration'] ?? $result['duration']) / 60, 0),
            'geocoded_count' => $geocoded->count(),
            'not_geocoded_count' => $notGeocoded->count(),
        ]);
    }

    public function optimizeRoute(DeliveryRoute $deliveryRoute)
    {
        $items = $deliveryRoute->items()->with('delivery')->get();
        $points = $items
            ->filter(fn ($item) => $item->delivery->latitude && $item->delivery->longitude)
            ->map(fn ($item) => [
                'id' => $item->id,
                'lat' => (float) $item->delivery->latitude,
                'lng' => (float) $item->delivery->longitude,
            ])->values()->toArray();

        if (count($points) < 2) {
            return response()->json(['error' => 'Entregas insuficientes com coordenadas.'], 422);
        }

        $result = $this->optimizationService->optimize($points);

        // Reorder items
        foreach ($result['order'] as $index => $itemId) {
            DeliveryRouteItem::where('id', $itemId)->update(['sequence_order' => $index]);
        }

        return response()->json(['message' => 'Rota otimizada com sucesso.']);
    }

    public function myDeliveries(Request $request)
    {
        $user = auth()->user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (! $driver) {
            return Inertia::render('DeliveryRoutes/MyDeliveries', [
                'stats' => ['total_routes' => 0, 'completed_routes' => 0, 'total_items' => 0, 'delivered' => 0, 'returned' => 0, 'delivery_rate' => 0],
                'routes' => ['data' => [], 'links' => [], 'total' => 0],
                'driverName' => $user->name,
            ]);
        }

        $data = $this->routeService->getDriverHistory($driver->id, $request->get('search'));

        return Inertia::render('DeliveryRoutes/MyDeliveries', [
            'stats' => $data['stats'],
            'routes' => $data['routes'],
            'driverName' => $driver->name,
            'filters' => $request->only('search'),
        ]);
    }

    // Route Templates

    public function listTemplates()
    {
        return response()->json($this->templateService->listTemplates());
    }

    public function showTemplate(DeliveryRouteTemplate $template)
    {
        $template->load('stops', 'driver');

        return response()->json([
            'id' => $template->id,
            'name' => $template->name,
            'driver_id' => $template->driver_id,
            'driver_name' => $template->driver?->name,
            'notes' => $template->notes,
            'start_point_lat' => $template->start_point_lat,
            'start_point_lng' => $template->start_point_lng,
            'stops' => $template->stops->map(fn ($s) => [
                'sequence' => $s->sequence_order + 1,
                'neighborhood' => $s->neighborhood,
                'address' => $s->address,
                'reference_name' => $s->reference_name,
                'lat' => $s->latitude ? (float) $s->latitude : null,
                'lng' => $s->longitude ? (float) $s->longitude : null,
            ]),
        ]);
    }

    public function saveAsTemplate(Request $request, DeliveryRoute $deliveryRoute)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $template = $this->templateService->createTemplateFromRoute(
            $deliveryRoute,
            $validated['name'],
            auth()->id(),
        );

        return response()->json([
            'message' => "Template '{$template->name}' criado com {$template->stops->count()} paradas.",
            'template_id' => $template->id,
        ]);
    }

    public function createFromTemplate(Request $request, DeliveryRouteTemplate $template)
    {
        $validated = $request->validate([
            'date_route' => 'required|date',
            'delivery_ids' => 'required|array|min:1',
            'delivery_ids.*' => 'exists:deliveries,id',
        ]);

        $route = $this->templateService->createRouteFromTemplate(
            $template,
            $validated['date_route'],
            $validated['delivery_ids'],
            auth()->id(),
        );

        return redirect()->route('delivery-routes.index')
            ->with('success', "Rota {$route->route_number} criada a partir do template '{$template->name}'.");
    }

    public function deleteTemplate(DeliveryRouteTemplate $template)
    {
        $this->templateService->deleteTemplate($template);

        return response()->json(['message' => 'Template desativado com sucesso.']);
    }

    // GPS Tracking

    public function storeDriverLocation(Request $request)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'accuracy' => 'nullable|numeric|min:0',
        ]);

        $driver = Driver::where('user_id', auth()->id())->first();
        if (! $driver) {
            return response()->json(['error' => 'Motorista não encontrado.'], 403);
        }

        $activeRoute = DeliveryRoute::forDriver($driver->id)
            ->forDate(now()->toDateString())
            ->forStatus(DeliveryRoute::STATUS_IN_ROUTE)
            ->first();

        $this->locationService->recordLocation(
            $driver->id,
            $activeRoute?->id,
            $validated['latitude'],
            $validated['longitude'],
            $validated['speed'] ?? null,
            $validated['heading'] ?? null,
            $validated['accuracy'] ?? null,
        );

        return response()->json(['recorded' => true]);
    }

    public function getRouteTracking(Request $request, DeliveryRoute $deliveryRoute)
    {
        $since = $request->get('since') ? Carbon::parse($request->get('since')) : null;
        $latest = $this->locationService->getLatestPosition($deliveryRoute->driver_id);
        $track = $this->locationService->getRouteTrack($deliveryRoute->id, $since);

        return response()->json([
            'driver_position' => $latest ? [
                'lat' => (float) $latest->latitude,
                'lng' => (float) $latest->longitude,
                'speed' => $latest->speed,
                'heading' => $latest->heading,
                'recorded_at' => $latest->recorded_at->toIso8601String(),
            ] : null,
            'track' => $track->map(fn ($l) => [
                'lat' => (float) $l->latitude,
                'lng' => (float) $l->longitude,
                'recorded_at' => $l->recorded_at->toIso8601String(),
            ]),
        ]);
    }

    public function printManifest(DeliveryRoute $deliveryRoute)
    {
        return $this->manifestService->generate($deliveryRoute);
    }
}
