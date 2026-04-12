<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Geocoding Configuration
    |--------------------------------------------------------------------------
    */

    'geocoding' => [
        'nominatim_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org/search'),
        'default_city' => env('GEOCODING_CITY', 'Fortaleza'),
        'default_state' => env('GEOCODING_STATE', 'Ceará'),
        'default_country' => env('GEOCODING_COUNTRY', 'Brasil'),

        // Região Metropolitana de Fortaleza (19 municípios)
        // Cobre: Fortaleza, Caucaia, Maracanaú, Maranguape, Pacatuba,
        // Eusébio, Aquiraz, Itaitinga, Guaiúba, Horizonte, Pacajus,
        // Cascavel, Chorozinho, São Gonçalo do Amarante, Paraipaba, etc.
        'bounds' => [
            'lat_min' => (float) env('GEOCODING_LAT_MIN', -4.25),
            'lat_max' => (float) env('GEOCODING_LAT_MAX', -3.40),
            'lng_min' => (float) env('GEOCODING_LNG_MIN', -39.05),
            'lng_max' => (float) env('GEOCODING_LNG_MAX', -38.05),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Optimization (OSRM)
    |--------------------------------------------------------------------------
    */

    'optimization' => [
        'osrm_trip_url' => env('OSRM_TRIP_URL', 'https://router.project-osrm.org/trip/v1/driving/'),
        'osrm_route_url' => env('OSRM_ROUTE_URL', 'https://router.project-osrm.org/route/v1/driving/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Start Point (Centro de Distribuição)
    |--------------------------------------------------------------------------
    */

    'default_start_point' => [
        'lat' => (float) env('DELIVERY_START_LAT', -3.7277),
        'lng' => (float) env('DELIVERY_START_LNG', -38.5274),
        'name' => env('DELIVERY_START_NAME', 'CD - Meia Sola'),
        'address' => env('DELIVERY_START_ADDRESS', 'Av. Dom Manuel, 621, Centro, Fortaleza - CE'),
    ],

];
