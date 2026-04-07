<?php

namespace Tests\Unit;

use App\Contracts\Integration\IntegrationDriver;
use App\Models\TenantIntegration;
use App\Services\Integrations\Drivers\DatabaseDriver;
use App\Services\Integrations\Drivers\RestApiDriver;
use App\Services\Integrations\Drivers\WebhookDriver;
use App\Services\Integrations\IntegrationManager;
use Tests\TestCase;

class IntegrationManagerTest extends TestCase
{
    protected IntegrationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new IntegrationManager();
    }

    public function test_available_drivers_returns_all_registered_drivers(): void
    {
        $drivers = IntegrationManager::availableDrivers();

        $this->assertArrayHasKey('database', $drivers);
        $this->assertArrayHasKey('rest_api', $drivers);
        $this->assertArrayHasKey('webhook', $drivers);
        $this->assertArrayHasKey('cigam_sales', $drivers);
        $this->assertArrayHasKey('cigam_products', $drivers);
    }

    public function test_each_driver_has_config_schema(): void
    {
        $drivers = IntegrationManager::availableDrivers();

        foreach ($drivers as $key => $driver) {
            $this->assertArrayHasKey('config_schema', $driver, "Driver '{$key}' missing config_schema");
            $this->assertIsArray($driver['config_schema'], "Driver '{$key}' config_schema should be array");
        }
    }

    public function test_provider_presets_returns_all_presets(): void
    {
        $presets = IntegrationManager::providerPresets();

        $this->assertArrayHasKey('cigam', $presets);
        $this->assertArrayHasKey('sap', $presets);
        $this->assertArrayHasKey('totvs', $presets);
        $this->assertArrayHasKey('custom', $presets);
    }

    public function test_provider_presets_have_required_fields(): void
    {
        $presets = IntegrationManager::providerPresets();

        foreach ($presets as $key => $preset) {
            $this->assertArrayHasKey('name', $preset, "Preset '{$key}' missing name");
            $this->assertArrayHasKey('drivers', $preset, "Preset '{$key}' missing drivers");
            $this->assertArrayHasKey('description', $preset, "Preset '{$key}' missing description");
            $this->assertIsArray($preset['drivers'], "Preset '{$key}' drivers should be array");
        }
    }

    public function test_get_config_schema_for_valid_driver(): void
    {
        $schema = IntegrationManager::getConfigSchema('database');

        $this->assertNotEmpty($schema);
        $this->assertIsArray($schema);

        $fieldNames = array_column($schema, 'name');
        $this->assertContains('db_host', $fieldNames);
        $this->assertContains('db_database', $fieldNames);
    }

    public function test_get_config_schema_for_invalid_driver_returns_empty(): void
    {
        $schema = IntegrationManager::getConfigSchema('nonexistent');

        $this->assertEmpty($schema);
    }

    public function test_resolve_throws_for_unknown_driver(): void
    {
        $integration = new TenantIntegration();
        $integration->driver = 'nonexistent_driver';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver desconhecido: nonexistent_driver');

        $this->manager->resolve($integration);
    }

    public function test_register_custom_driver(): void
    {
        IntegrationManager::registerDriver('test_driver', WebhookDriver::class);

        $schema = IntegrationManager::getConfigSchema('test_driver');
        $this->assertNotEmpty($schema);

        $drivers = IntegrationManager::availableDrivers();
        $this->assertArrayHasKey('test_driver', $drivers);
    }

    public function test_database_driver_config_schema_has_required_fields(): void
    {
        $schema = DatabaseDriver::configSchema();
        $fieldNames = array_column($schema, 'name');

        $this->assertContains('db_driver', $fieldNames);
        $this->assertContains('db_host', $fieldNames);
        $this->assertContains('db_port', $fieldNames);
        $this->assertContains('db_database', $fieldNames);
        $this->assertContains('db_username', $fieldNames);
        $this->assertContains('db_password', $fieldNames);
    }

    public function test_rest_api_driver_config_schema_has_required_fields(): void
    {
        $schema = RestApiDriver::configSchema();
        $fieldNames = array_column($schema, 'name');

        $this->assertContains('base_url', $fieldNames);
        $this->assertContains('auth_type', $fieldNames);
    }

    public function test_webhook_driver_config_schema_has_required_fields(): void
    {
        $schema = WebhookDriver::configSchema();
        $fieldNames = array_column($schema, 'name');

        $this->assertContains('webhook_secret', $fieldNames);
    }

    public function test_webhook_driver_validate_config_generates_secrets(): void
    {
        $config = WebhookDriver::validateConfig([]);

        $this->assertNotEmpty($config['webhook_secret']);
        $this->assertNotEmpty($config['api_key']);
        $this->assertEquals(32, strlen($config['webhook_secret']));
        $this->assertEquals(64, strlen($config['api_key']));
    }

    public function test_webhook_driver_validate_config_preserves_existing_secrets(): void
    {
        $config = WebhookDriver::validateConfig([
            'webhook_secret' => 'my-custom-secret',
            'api_key' => 'my-custom-key',
        ]);

        $this->assertEquals('my-custom-secret', $config['webhook_secret']);
        $this->assertEquals('my-custom-key', $config['api_key']);
    }

    public function test_webhook_driver_test_connection_returns_success(): void
    {
        $driver = new WebhookDriver();
        $integration = new TenantIntegration();
        $integration->config = ['webhook_secret' => 'test'];
        $driver->initialize($integration);

        $result = $driver->testConnection();

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['message']);
    }

    public function test_webhook_driver_pull_returns_passive_message(): void
    {
        $driver = new WebhookDriver();
        $integration = new TenantIntegration();
        $integration->config = ['webhook_secret' => 'test'];
        $driver->initialize($integration);

        $result = $driver->pull();

        $this->assertEquals(0, $result['processed']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_webhook_driver_process_webhook_counts_records(): void
    {
        $driver = new WebhookDriver();
        $integration = new TenantIntegration();
        $integration->config = ['webhook_secret' => 'test'];
        $driver->initialize($integration);

        $result = $driver->processWebhook([
            'resource' => 'sales',
            'data' => [
                ['date' => '2026-04-01', 'value' => 100],
                ['date' => '2026-04-02', 'value' => 200],
            ],
        ]);

        $this->assertTrue($result['accepted']);
        $this->assertEquals('sales', $result['resource']);
        $this->assertEquals(2, $result['records']);
    }
}
