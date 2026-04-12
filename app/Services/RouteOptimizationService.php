<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RouteOptimizationService
{
    /**
     * Optimize delivery order using OSRM Trip API (free), fallback to nearest-neighbor.
     *
     * @param  array  $points  Array of [id, lat, lng]
     * @param  array|null  $startPoint  [lat, lng] of starting location (store/CD)
     * @return array{order: int[], distance: float, duration: float}
     */
    public function optimize(array $points, ?array $startPoint = null): array
    {
        if (count($points) <= 1) {
            return [
                'order' => array_column($points, 'id'),
                'distance' => 0,
                'duration' => 0,
            ];
        }

        // Filter points without coordinates
        $validPoints = array_values(array_filter($points, fn ($p) => $p['lat'] && $p['lng']));

        if (count($validPoints) < 2) {
            return [
                'order' => array_column($points, 'id'),
                'distance' => 0,
                'duration' => 0,
            ];
        }

        // Try OSRM first
        $result = $this->optimizeWithOSRM($validPoints, $startPoint);

        if ($result) {
            return $result;
        }

        // Fallback to nearest-neighbor
        return $this->optimizeNearestNeighbor($validPoints, $startPoint);
    }

    /**
     * Get route geometry (GeoJSON) for map display.
     *
     * @param  array  $orderedCoords  Array of [lat, lng]
     * @return array|null GeoJSON geometry
     */
    public function getRouteGeometry(array $orderedCoords): ?array
    {
        if (count($orderedCoords) < 2) {
            return null;
        }

        $coords = implode(';', array_map(fn ($c) => "{$c['lng']},{$c['lat']}", $orderedCoords));
        $url = config('delivery.optimization.osrm_route_url').$coords;

        $response = $this->osrmRequest($url, [
            'overview' => 'full',
            'geometries' => 'geojson',
        ]);

        if ($response && $response->successful()) {
            $data = $response->json();
            if (! empty($data['routes'][0]['geometry'])) {
                return [
                    'geometry' => $data['routes'][0]['geometry'],
                    'distance' => $data['routes'][0]['distance'] ?? 0,
                    'duration' => $data['routes'][0]['duration'] ?? 0,
                ];
            }
        }

        // Fallback: simple line between points
        return [
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => array_map(fn ($c) => [$c['lng'], $c['lat']], $orderedCoords),
            ],
            'distance' => 0,
            'duration' => 0,
        ];
    }

    /**
     * Execute a rate-limited OSRM request (1 req/sec via cache lock).
     */
    private function osrmRequest(string $url, array $params = []): ?Response
    {
        try {
            return Cache::lock('osrm_api_lock', 3)->block(10, function () use ($url, $params) {
                $response = Http::withOptions(['verify' => config('app.env') === 'production'])
                    ->timeout(15)
                    ->get($url, $params);

                // Garantir intervalo mínimo de 1s entre requests (OSRM public rate limit)
                usleep(1100000);

                return $response;
            });
        } catch (\Exception $e) {
            Log::warning('OSRM request failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Optimize using OSRM Trip API.
     */
    private function optimizeWithOSRM(array $points, ?array $startPoint): ?array
    {
        $allPoints = $points;
        if ($startPoint) {
            array_unshift($allPoints, ['id' => 'start', 'lat' => $startPoint[0], 'lng' => $startPoint[1]]);
        }

        $coords = implode(';', array_map(fn ($p) => "{$p['lng']},{$p['lat']}", $allPoints));
        $url = config('delivery.optimization.osrm_trip_url').$coords;

        $response = $this->osrmRequest($url, [
            'roundtrip' => 'false',
            'source' => 'first',
            'geometries' => 'geojson',
        ]);

        if (! $response || ! $response->successful()) {
            return null;
        }

        $data = $response->json();

        if (! empty($data['waypoints'])) {
            // OSRM Trip API:
            // - waypoints[] é retornado na MESMA ORDEM dos pontos de entrada
            // - waypoints[i].waypoint_index = posição deste ponto na viagem OTIMIZADA
            // - trips_index = qual viagem (sempre 0 para viagem única)
            //
            // Para obter a ordem otimizada:
            // 1. Parear cada waypoint com seu ponto de entrada (pelo índice do array)
            // 2. Ordenar por waypoint_index (posição na viagem)
            $orderedIds = collect($data['waypoints'])
                ->map(fn ($wp, $inputIdx) => [
                    'point' => $allPoints[$inputIdx] ?? null,
                    'trip_position' => $wp['waypoint_index'],
                ])
                ->filter(fn ($item) => $item['point'] !== null && $item['point']['id'] !== 'start')
                ->sortBy('trip_position')
                ->pluck('point.id')
                ->values()
                ->toArray();

            if (empty($orderedIds)) {
                $orderedIds = array_column($points, 'id');
            }

            return [
                'order' => $orderedIds,
                'distance' => $data['trips'][0]['distance'] ?? 0,
                'duration' => $data['trips'][0]['duration'] ?? 0,
            ];
        }

        return null;
    }

    /**
     * Nearest-neighbor algorithm (fallback, O(n²)).
     */
    private function optimizeNearestNeighbor(array $points, ?array $startPoint): array
    {
        $remaining = $points;
        $ordered = [];
        $totalDistance = 0;

        $currentLat = $startPoint ? $startPoint[0] : $remaining[0]['lat'];
        $currentLng = $startPoint ? $startPoint[1] : $remaining[0]['lng'];

        if (! $startPoint) {
            $ordered[] = array_shift($remaining);
            $currentLat = $ordered[0]['lat'];
            $currentLng = $ordered[0]['lng'];
        }

        while (! empty($remaining)) {
            $nearestIdx = 0;
            $nearestDist = PHP_FLOAT_MAX;

            foreach ($remaining as $idx => $point) {
                $dist = $this->haversineDistance($currentLat, $currentLng, $point['lat'], $point['lng']);
                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearestIdx = $idx;
                }
            }

            $nearest = $remaining[$nearestIdx];
            unset($remaining[$nearestIdx]);
            $remaining = array_values($remaining);

            $ordered[] = $nearest;
            $totalDistance += $nearestDist;
            $currentLat = $nearest['lat'];
            $currentLng = $nearest['lng'];
        }

        return [
            'order' => array_column($ordered, 'id'),
            'distance' => round($totalDistance, 2),
            'duration' => 0, // Can't estimate without road network
        ];
    }

    /**
     * Calculate Haversine distance between two points (in meters).
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
