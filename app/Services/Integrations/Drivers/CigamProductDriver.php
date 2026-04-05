<?php

namespace App\Services\Integrations\Drivers;

use App\Contracts\Integration\IntegrationDriver;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CigamProductDriver implements IntegrationDriver
{
    protected TenantIntegration $integration;
    protected string $connectionName;

    public function initialize(TenantIntegration $integration): void
    {
        $this->integration = $integration;
        $this->connectionName = "cigam_products_{$integration->id}";
        $this->configureConnection();
    }

    protected function configureConnection(): void
    {
        $config = $this->integration->config;

        if (! $config) {
            return;
        }

        Config::set("database.connections.{$this->connectionName}", [
            'driver' => 'pgsql',
            'host' => $config['db_host'] ?? '127.0.0.1',
            'port' => $config['db_port'] ?? '5432',
            'database' => $config['db_database'] ?? 'cigam',
            'username' => $config['db_username'] ?? '',
            'password' => $config['db_password'] ?? '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => $config['db_schema'] ?? 'public',
            'sslmode' => 'prefer',
            'options' => [\PDO::ATTR_TIMEOUT => 30],
        ]);
    }

    public function testConnection(): array
    {
        try {
            $connection = DB::connection($this->connectionName);
            $connection->getPdo();

            $config = $this->integration->config;
            $table = $config['products_view'] ?? 'msl_produtos_';

            $count = $connection->table($table)->limit(1)->count();
            DB::purge($this->connectionName);

            return ['success' => true, 'message' => "Conexão CIGAM ok. View '{$table}' acessível."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Falha na conexão CIGAM: ' . $e->getMessage()];
        }
    }

    public function pull(array $options = []): array
    {
        // Product sync is chunked and managed by the existing ProductSyncService
        // This driver provides the connection; the controller orchestrates the sync
        $result = ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];

        $testResult = $this->testConnection();
        if (! $testResult['success']) {
            $result['errors'][] = $testResult['message'];
            return $result;
        }

        $config = $this->integration->config;
        $productsView = $config['products_view'] ?? 'msl_produtos_';

        try {
            $connection = DB::connection($this->connectionName);
            $count = $connection->table($productsView)->count();
            $result['processed'] = $count;

            DB::purge($this->connectionName);

            return $result;
        } catch (\Exception $e) {
            $result['errors'][] = 'Erro ao acessar produtos CIGAM: ' . $e->getMessage();
            Log::error("CigamProductDriver pull error: {$e->getMessage()}");
            return $result;
        }
    }

    public function push(array $options = []): array
    {
        return ['processed' => 0, 'success' => 0, 'failed' => 0, 'errors' => ['CIGAM não suporta push de produtos.']];
    }

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    public function getAvailableResources(): array
    {
        return [
            'products' => 'Produtos (msl_produtos_)',
            'prices' => 'Preços (msl_prod_valor_)',
            'suppliers' => 'Fornecedores (msl_dfornecedor_)',
        ];
    }

    public static function configSchema(): array
    {
        return [
            ['name' => 'db_host', 'label' => 'Host PostgreSQL', 'type' => 'text', 'required' => true],
            ['name' => 'db_port', 'label' => 'Porta', 'type' => 'text', 'required' => true, 'default' => '5432'],
            ['name' => 'db_database', 'label' => 'Banco de dados', 'type' => 'text', 'required' => true],
            ['name' => 'db_username', 'label' => 'Usuário', 'type' => 'text', 'required' => true],
            ['name' => 'db_password', 'label' => 'Senha', 'type' => 'password', 'required' => true],
            ['name' => 'db_schema', 'label' => 'Schema', 'type' => 'text', 'required' => false, 'default' => 'public'],
            ['name' => 'products_view', 'label' => 'View de produtos', 'type' => 'text', 'required' => false, 'default' => 'msl_produtos_'],
            ['name' => 'prices_view', 'label' => 'View de preços', 'type' => 'text', 'required' => false, 'default' => 'msl_prod_valor_'],
        ];
    }

    public static function validateConfig(array $config): array
    {
        $required = ['db_host', 'db_port', 'db_database', 'db_username'];

        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new \InvalidArgumentException("Campo obrigatório: {$field}");
            }
        }

        return $config;
    }
}
