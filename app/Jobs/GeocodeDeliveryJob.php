<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Services\GeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeocodeDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 5;

    public function __construct(
        public int $deliveryId,
    ) {}

    public function handle(GeocodingService $service): void
    {
        $delivery = Delivery::find($this->deliveryId);

        if (! $delivery || $delivery->geocoded_at) {
            return;
        }

        $service->geocodeDelivery($delivery);
    }
}
