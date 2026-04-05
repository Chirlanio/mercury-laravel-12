<?php

namespace App\Services\Integrations\Drivers;

use App\Contracts\Integration\IntegrationDriver;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RestApiDriver implements IntegrationDriver
{
    protected TenantIntegration $integration;

    public function initialize(TenantIntegration $integration): void
    {
        $this->integration = $integration;
    }

    protected function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $config = $this->integration->config;

        $client = Http::baseUrl($config['base_url'] ?? '')
            ->timeout((int) ($config['timeout'] ?? 30));

        // Authentication
        $authType = $config['auth_type'] ?? 'none';

        return match ($authType) {
            'bearer' => $client->withToken($config['auth_token'] ?? ''),
            'basic' => $client->withBasicAuth($config['auth_username'] ?? '', $config['auth_password'] ?? ''),
            'api_key' => $client->withHeaders([$config['auth_header'] ?? 'X-API-Key' => $config['auth_token'] ?? '']),
            default => $client,
        };
    }

    public function testConnection(): array
    {
        try {
            $config = $this->integration->config;
            $healthEndpoint = $config['health_endpoint'] ?? '/';

            $response = $this->httpClient()->get($healthEndpoint);

            if ($response->successful()) {
                return ['success' => true, 'message' => "Conexão OK (HTTP {$response->status()})"];
            }

            return ['success' => false, 'message' => "HTTP {$response->status()}: {$response->body()}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Falha na conexão: ' . $e->getMessage()];
        }
    }

    public function pull(array $options = []): array
    {
        $result = ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
        $config = $this->integration->config;

        $endpoint = $options['endpoint'] ?? ($config['pull_endpoint'] ?? null);

        if (! $endpoint) {
            $result['errors'][] = 'Nenhum endpoint configurado para pull.';
            return $result;
        }

        try {
            $params = array_filter([
                'page' => $options['page'] ?? 1,
                'per_page' => $options['per_page'] ?? 100,
                'date_from' => $options['date_from'] ?? null,
                'date_to' => $options['date_to'] ?? null,
            ]);

            $response = $this->httpClient()->get($endpoint, $params);

            if (! $response->successful()) {
                $result['errors'][] = "HTTP {$response->status()}: {$response->body()}";
                return $result;
            }

            $data = $response->json();
            $records = $data['data'] ?? $data;

            if (is_array($records)) {
                $result['processed'] = count($records);
            }
        } catch (\Exception $e) {
            $result['errors'][] = 'Erro no pull: ' . $e->getMessage();
            Log::error("RestApiDriver pull error: {$e->getMessage()}", [
                'integration_id' => $this->integration->id,
            ]);
        }

        return $result;
    }

    public function push(array $options = []): array
    {
        $result = ['processed' => 0, 'success' => 0, 'failed' => 0, 'errors' => []];
        $config = $this->integration->config;

        $endpoint = $options['endpoint'] ?? ($config['push_endpoint'] ?? null);

        if (! $endpoint) {
            $result['errors'][] = 'Nenhum endpoint configurado para push.';
            return $result;
        }

        $data = $options['data'] ?? [];

        try {
            $response = $this->httpClient()->post($endpoint, $data);

            if ($response->successful()) {
                $result['processed'] = count($data);
                $result['success'] = count($data);
            } else {
                $result['failed'] = count($data);
                $result['errors'][] = "HTTP {$response->status()}: {$response->body()}";
            }
        } catch (\Exception $e) {
            $result['errors'][] = 'Erro no push: ' . $e->getMessage();
        }

        return $result;
    }

    public function getAvailableResources(): array
    {
        $config = $this->integration->config;

        return $config['resources'] ?? [
            'sales' => 'Vendas',
            'products' => 'Produtos',
            'employees' => 'Funcionários',
            'stores' => 'Lojas',
        ];
    }

    public static function configSchema(): array
    {
        return [
            ['name' => 'base_url', 'label' => 'URL Base', 'type' => 'text', 'required' => true, 'placeholder' => 'https://api.exemplo.com'],
            ['name' => 'auth_type', 'label' => 'Autenticação', 'type' => 'select', 'options' => ['none' => 'Nenhuma', 'bearer' => 'Bearer Token', 'basic' => 'Basic Auth', 'api_key' => 'API Key'], 'required' => true],
            ['name' => 'auth_token', 'label' => 'Token/API Key', 'type' => 'password', 'required' => false],
            ['name' => 'auth_username', 'label' => 'Usuário (Basic Auth)', 'type' => 'text', 'required' => false],
            ['name' => 'auth_password', 'label' => 'Senha (Basic Auth)', 'type' => 'password', 'required' => false],
            ['name' => 'auth_header', 'label' => 'Header da API Key', 'type' => 'text', 'required' => false, 'default' => 'X-API-Key'],
            ['name' => 'health_endpoint', 'label' => 'Endpoint de saúde', 'type' => 'text', 'required' => false, 'default' => '/'],
            ['name' => 'pull_endpoint', 'label' => 'Endpoint de Pull', 'type' => 'text', 'required' => false],
            ['name' => 'push_endpoint', 'label' => 'Endpoint de Push', 'type' => 'text', 'required' => false],
            ['name' => 'timeout', 'label' => 'Timeout (segundos)', 'type' => 'number', 'required' => false, 'default' => 30],
        ];
    }

    public static function validateConfig(array $config): array
    {
        if (empty($config['base_url'])) {
            throw new \InvalidArgumentException('URL Base é obrigatória.');
        }

        if (! filter_var($config['base_url'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('URL Base inválida.');
        }

        return $config;
    }
}
