<?php

namespace App\Services\Integrations;

use App\Contracts\Integration\IntegrationDriver;
use App\Models\TenantIntegration;
use App\Services\Integrations\Drivers\DatabaseDriver;
use App\Services\Integrations\Drivers\RestApiDriver;
use App\Services\Integrations\Drivers\WebhookDriver;
use App\Services\Integrations\Drivers\CigamSalesDriver;
use App\Services\Integrations\Drivers\CigamProductDriver;

class IntegrationManager
{
    /**
     * Map of driver identifiers to their implementing classes.
     */
    protected static array $drivers = [
        'database' => DatabaseDriver::class,
        'rest_api' => RestApiDriver::class,
        'webhook' => WebhookDriver::class,
        'cigam_sales' => CigamSalesDriver::class,
        'cigam_products' => CigamProductDriver::class,
    ];

    /**
     * Provider presets with recommended driver and default config.
     */
    protected static array $providerPresets = [
        'cigam' => [
            'name' => 'CIGAM ERP',
            'drivers' => ['cigam_sales', 'cigam_products', 'database'],
            'description' => 'Integração com CIGAM ERP via banco de dados PostgreSQL ou API.',
        ],
        'sap' => [
            'name' => 'SAP',
            'drivers' => ['rest_api', 'database'],
            'description' => 'Integração com SAP via API REST ou conexão direta ao banco.',
        ],
        'totvs' => [
            'name' => 'TOTVS Protheus',
            'drivers' => ['rest_api', 'database'],
            'description' => 'Integração com TOTVS Protheus.',
        ],
        'custom' => [
            'name' => 'Personalizado',
            'drivers' => ['database', 'rest_api', 'webhook'],
            'description' => 'Integração personalizada com sistema externo.',
        ],
    ];

    /**
     * Resolve and initialize a driver for the given integration.
     */
    public function resolve(TenantIntegration $integration): IntegrationDriver
    {
        $driverKey = $integration->driver;

        if (! isset(static::$drivers[$driverKey])) {
            throw new \InvalidArgumentException("Driver desconhecido: {$driverKey}");
        }

        $driverClass = static::$drivers[$driverKey];
        $driver = app($driverClass);
        $driver->initialize($integration);

        return $driver;
    }

    /**
     * Register a custom driver.
     */
    public static function registerDriver(string $key, string $driverClass): void
    {
        static::$drivers[$key] = $driverClass;
    }

    /**
     * Get available drivers.
     */
    public static function availableDrivers(): array
    {
        return array_map(function ($driverClass) {
            return [
                'config_schema' => $driverClass::configSchema(),
            ];
        }, static::$drivers);
    }

    /**
     * Get provider presets.
     */
    public static function providerPresets(): array
    {
        return static::$providerPresets;
    }

    /**
     * Get config schema for a specific driver.
     */
    public static function getConfigSchema(string $driverKey): array
    {
        if (! isset(static::$drivers[$driverKey])) {
            return [];
        }

        return static::$drivers[$driverKey]::configSchema();
    }
}
