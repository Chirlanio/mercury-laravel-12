<?php

namespace App\Services\Integrations\Drivers;

use App\Contracts\Integration\IntegrationDriver;
use App\Models\TenantIntegration;
use Illuminate\Support\Str;

class WebhookDriver implements IntegrationDriver
{
    protected TenantIntegration $integration;

    public function initialize(TenantIntegration $integration): void
    {
        $this->integration = $integration;
    }

    public function testConnection(): array
    {
        return ['success' => true, 'message' => 'Webhook está ativo e pronto para receber dados.'];
    }

    public function pull(array $options = []): array
    {
        return [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => ['Webhook é passivo — dados são recebidos via POST no endpoint configurado.'],
        ];
    }

    public function push(array $options = []): array
    {
        return [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => ['Push não suportado pelo driver webhook.'],
        ];
    }

    public function getAvailableResources(): array
    {
        $config = $this->integration->config;

        return $config['resources'] ?? [
            'sales' => 'Vendas',
            'products' => 'Produtos',
        ];
    }

    /**
     * Process an incoming webhook payload.
     */
    public function processWebhook(array $payload): array
    {
        $config = $this->integration->config;
        $secret = $config['webhook_secret'] ?? null;

        return [
            'accepted' => true,
            'resource' => $payload['resource'] ?? 'unknown',
            'records' => count($payload['data'] ?? []),
        ];
    }

    public static function configSchema(): array
    {
        return [
            ['name' => 'webhook_secret', 'label' => 'Secret do Webhook', 'type' => 'password', 'required' => true, 'help' => 'Chave usada para validar requisições.'],
            ['name' => 'api_key', 'label' => 'API Key (gerada automaticamente)', 'type' => 'text', 'required' => false, 'readonly' => true],
            ['name' => 'allowed_ips', 'label' => 'IPs permitidos (separados por vírgula)', 'type' => 'text', 'required' => false],
        ];
    }

    public static function validateConfig(array $config): array
    {
        if (empty($config['webhook_secret'])) {
            $config['webhook_secret'] = Str::random(32);
        }

        if (empty($config['api_key'])) {
            $config['api_key'] = Str::random(64);
        }

        return $config;
    }
}
