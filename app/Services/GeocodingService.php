<?php

namespace App\Services;

use App\Models\Delivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    /**
     * Geocode an address string using Nominatim (OpenStreetMap).
     * Usa busca estruturada para maior precisão + validação de bounding box.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocodeAddress(string $address, ?string $neighborhood = null, ?string $city = null): ?array
    {
        $city = $city ?? config('delivery.geocoding.default_city');

        // Tentativa 1: busca estruturada (mais precisa)
        $result = $this->searchStructured($address, $neighborhood, $city);
        if ($result && $this->isWithinBounds($result['lat'], $result['lng'])) {
            return $result;
        }

        // Tentativa 2: busca estruturada sem bairro (bairro pode confundir o Nominatim)
        if ($neighborhood) {
            sleep(1);
            $result = $this->searchStructured($address, null, $city);
            if ($result && $this->isWithinBounds($result['lat'], $result['lng'])) {
                return $result;
            }
        }

        // Tentativa 3: query livre com rua + bairro + cidade
        sleep(1);
        $result = $this->searchFreeForm($address, $neighborhood, $city);
        if ($result && $this->isWithinBounds($result['lat'], $result['lng'])) {
            return $result;
        }

        // Tentativa 4: fallback apenas bairro + cidade (centroide do bairro)
        if ($neighborhood) {
            sleep(1);
            $result = $this->searchFreeForm(null, $neighborhood, $city);
            if ($result && $this->isWithinBounds($result['lat'], $result['lng'])) {
                Log::info('Geocoding fallback to neighborhood centroid', [
                    'address' => $address,
                    'neighborhood' => $neighborhood,
                ]);

                return $result;
            }
        }

        Log::warning('Geocoding failed: all attempts exhausted or outside bounds', [
            'address' => $address,
            'neighborhood' => $neighborhood,
        ]);

        return null;
    }

    /**
     * Busca estruturada do Nominatim (street/city/state separados).
     */
    private function searchStructured(string $street, ?string $neighborhood, string $city): ?array
    {
        $params = [
            'street' => $street,
            'city' => $neighborhood ? "{$neighborhood}, {$city}" : $city,
            'state' => config('delivery.geocoding.default_state'),
            'country' => config('delivery.geocoding.default_country'),
            'format' => 'json',
            'limit' => 1,
        ];

        return $this->doRequest($params);
    }

    /**
     * Busca livre (query string) no Nominatim.
     */
    private function searchFreeForm(?string $address, ?string $neighborhood, string $city): ?array
    {
        $parts = array_filter([$address, $neighborhood, $city, config('delivery.geocoding.default_state'), config('delivery.geocoding.default_country')]);
        $query = implode(', ', $parts);

        return $this->doRequest(['q' => $query, 'format' => 'json', 'limit' => 1, 'countrycodes' => 'br']);
    }

    /**
     * Executa a request para o Nominatim.
     */
    private function doRequest(array $params): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mercury-DeliverySystem/1.0 (delivery@grupomeiasola.com.br)',
            ])
                ->withOptions(['verify' => config('app.env') === 'production'])
                ->timeout(10)
                ->get(config('delivery.geocoding.nominatim_url'), $params);

            if ($response->successful() && ! empty($response->json())) {
                $result = $response->json()[0];

                return [
                    'lat' => (float) $result['lat'],
                    'lng' => (float) $result['lon'],
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Geocoding request failed', ['params' => $params, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Valida se as coordenadas estão dentro do bounding box configurado.
     */
    private function isWithinBounds(float $lat, float $lng): bool
    {
        $bounds = config('delivery.geocoding.bounds');

        return $lat >= $bounds['lat_min']
            && $lat <= $bounds['lat_max']
            && $lng >= $bounds['lng_min']
            && $lng <= $bounds['lng_max'];
    }

    /**
     * Geocode a delivery and save coordinates.
     */
    public function geocodeDelivery(Delivery $delivery): bool
    {
        if (! $delivery->address && ! $delivery->neighborhood) {
            return false;
        }

        $coords = $this->geocodeAddress(
            $delivery->address ?? '',
            $delivery->neighborhood,
        );

        if (! $coords) {
            return false;
        }

        $delivery->update([
            'latitude' => $coords['lat'],
            'longitude' => $coords['lng'],
            'geocoded_at' => now(),
        ]);

        return true;
    }

    /**
     * Geocode deliveries in batch (respects Nominatim rate limit of 1 req/s).
     */
    public function geocodeBatch($deliveries): array
    {
        $success = 0;
        $failed = 0;

        foreach ($deliveries as $delivery) {
            if ($this->geocodeDelivery($delivery)) {
                $success++;
            } else {
                $failed++;
            }

            // Nominatim rate limit: 1 request per second
            sleep(1);
        }

        return ['success' => $success, 'failed' => $failed];
    }
}
