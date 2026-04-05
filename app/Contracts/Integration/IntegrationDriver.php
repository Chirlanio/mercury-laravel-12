<?php

namespace App\Contracts\Integration;

use App\Models\TenantIntegration;

interface IntegrationDriver
{
    /**
     * Initialize the driver with the integration configuration.
     */
    public function initialize(TenantIntegration $integration): void;

    /**
     * Test the connection to the external system.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;

    /**
     * Pull data from the external system into Mercury.
     *
     * @param array $options Sync options (date range, filters, etc.)
     * @return array{processed: int, created: int, updated: int, failed: int, errors: array}
     */
    public function pull(array $options = []): array;

    /**
     * Push data from Mercury to the external system.
     *
     * @param array $options Sync options (resource type, filters, etc.)
     * @return array{processed: int, success: int, failed: int, errors: array}
     */
    public function push(array $options = []): array;

    /**
     * Get available resources that can be synced.
     *
     * @return array<string, string> ['resource_key' => 'Human-readable name']
     */
    public function getAvailableResources(): array;

    /**
     * Get the configuration schema for this driver.
     *
     * @return array Field definitions for the configuration form
     */
    public static function configSchema(): array;

    /**
     * Validate the configuration values.
     *
     * @param array $config
     * @return array Validated config
     * @throws \InvalidArgumentException
     */
    public static function validateConfig(array $config): array;
}
