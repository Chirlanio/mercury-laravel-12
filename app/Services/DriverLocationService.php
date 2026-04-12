<?php

namespace App\Services;

use App\Models\DriverLocation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DriverLocationService
{
    /**
     * Record a driver's GPS location.
     * Rate-limited: ignores if last record for this driver is < 15 seconds old.
     */
    public function recordLocation(
        int $driverId,
        ?int $routeId,
        float $lat,
        float $lng,
        ?float $speed = null,
        ?float $heading = null,
        ?float $accuracy = null,
    ): ?DriverLocation {
        if (! Schema::hasTable('driver_locations')) {
            return null;
        }

        // Rate limit: skip if last record is too recent
        $lastRecord = DriverLocation::where('driver_id', $driverId)
            ->orderByDesc('recorded_at')
            ->first();

        if ($lastRecord && $lastRecord->recorded_at->diffInSeconds(now()) < 15) {
            return null;
        }

        return DriverLocation::create([
            'driver_id' => $driverId,
            'route_id' => $routeId,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed' => $speed,
            'heading' => $heading,
            'accuracy' => $accuracy,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Get the latest position for a driver.
     */
    public function getLatestPosition(int $driverId): ?DriverLocation
    {
        if (! Schema::hasTable('driver_locations')) {
            return null;
        }

        return DriverLocation::where('driver_id', $driverId)
            ->orderByDesc('recorded_at')
            ->first();
    }

    /**
     * Get the location track for a route (max 500 points).
     */
    public function getRouteTrack(int $routeId, ?Carbon $since = null): Collection
    {
        if (! Schema::hasTable('driver_locations')) {
            return collect();
        }

        $query = DriverLocation::forRoute($routeId)->orderBy('recorded_at');

        if ($since) {
            $query->where('recorded_at', '>', $since);
        }

        return $query->limit(500)->get();
    }

    /**
     * Cleanup old location records.
     */
    public function cleanupOldLocations(int $olderThanDays = 30): int
    {
        return DriverLocation::where('recorded_at', '<', now()->subDays($olderThanDays))->delete();
    }
}
