<?php

namespace App\Services\Integrations\Drivers;

use App\Contracts\Integration\IntegrationDriver;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseDriver implements IntegrationDriver
{
    protected TenantIntegration $integration;
    protected string $connectionName;

    public function initialize(TenantIntegration $integration): void
    {
        $this->integration = $integration;
        $this->connectionName = "integration_{$integration->id}";
        $this->configureConnection();
    }

    protected function configureConnection(): void
    {
        $config = $this->integration->config;

        if (! $config) {
            return;
        }

        Config::set("database.connections.{$this->connectionName}", [
            'driver' => $config['db_driver'] ?? 'pgsql',
            'host' => $config['db_host'] ?? '127.0.0.1',
            'port' => $config['db_port'] ?? '5432',
            'database' => $config['db_database'] ?? '',
            'username' => $config['db_username'] ?? '',
            'password' => $config['db_password'] ?? '',
            'charset' => $config['db_charset'] ?? 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => $config['db_schema'] ?? 'public',
            'sslmode' => $config['db_sslmode'] ?? 'prefer',
            'options' => [
                \PDO::ATTR_TIMEOUT => (int) ($config['db_timeout'] ?? 10),
            ],
        ]);
    }

    public function testConnection(): array
    {
        try {
            DB::connection($this->connectionName)->getPdo();
            DB::purge($this->connectionName);

            return ['success' => true, 'message' => 'Conexão estabelecida com sucesso.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Falha na conexão: ' . $e->getMessage()];
        }
    }

    public function pull(array $options = []): array
    {
        $result = ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];

        $config = $this->integration->config;
        $table = $options['table'] ?? ($config['default_table'] ?? null);
        $query = $options['query'] ?? ($config['default_query'] ?? null);

        if (! $table && ! $query) {
            $result['errors'][] = 'Nenhuma tabela ou query especificada.';
            return $result;
        }

        try {
            $connection = DB::connection($this->connectionName);

            if ($query) {
                $records = $connection->select($query);
            } else {
                $builder = $connection->table($table);

                if (isset($options['where'])) {
                    foreach ($options['where'] as $column => $value) {
                        $builder->where($column, $value);
                    }
                }

                if (isset($options['date_column'], $options['date_from'])) {
                    $builder->where($options['date_column'], '>=', $options['date_from']);
                }
                if (isset($options['date_column'], $options['date_to'])) {
                    $builder->where($options['date_column'], '<=', $options['date_to']);
                }

                $records = $builder->limit($options['limit'] ?? 10000)->get();
            }

            $result['processed'] = count($records);

            DB::purge($this->connectionName);
        } catch (\Exception $e) {
            $result['errors'][] = 'Erro ao consultar dados: ' . $e->getMessage();
            Log::error("DatabaseDriver pull error: {$e->getMessage()}", [
                'integration_id' => $this->integration->id,
            ]);
        }

        return $result;
    }

    public function push(array $options = []): array
    {
        return ['processed' => 0, 'success' => 0, 'failed' => 0, 'errors' => ['Push não suportado pelo driver de banco de dados.']];
    }

    public function getAvailableResources(): array
    {
        try {
            $connection = DB::connection($this->connectionName);
            $driver = $connection->getDriverName();

            $tables = match ($driver) {
                'pgsql' => $connection->select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' UNION SELECT viewname AS table_name FROM pg_views WHERE schemaname = 'public'"),
                'mysql', 'mariadb' => $connection->select('SHOW TABLES'),
                default => [],
            };

            $resources = [];
            foreach ($tables as $table) {
                $name = (array) $table;
                $tableName = reset($name);
                $resources[$tableName] = $tableName;
            }

            DB::purge($this->connectionName);

            return $resources;
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function configSchema(): array
    {
        return [
            ['name' => 'db_driver', 'label' => 'Driver', 'type' => 'select', 'options' => ['pgsql' => 'PostgreSQL', 'mysql' => 'MySQL', 'sqlsrv' => 'SQL Server'], 'required' => true],
            ['name' => 'db_host', 'label' => 'Host', 'type' => 'text', 'required' => true],
            ['name' => 'db_port', 'label' => 'Porta', 'type' => 'text', 'required' => true],
            ['name' => 'db_database', 'label' => 'Banco de dados', 'type' => 'text', 'required' => true],
            ['name' => 'db_username', 'label' => 'Usuário', 'type' => 'text', 'required' => true],
            ['name' => 'db_password', 'label' => 'Senha', 'type' => 'password', 'required' => true],
            ['name' => 'db_schema', 'label' => 'Schema', 'type' => 'text', 'required' => false, 'default' => 'public'],
            ['name' => 'db_timeout', 'label' => 'Timeout (segundos)', 'type' => 'number', 'required' => false, 'default' => 10],
            ['name' => 'default_table', 'label' => 'Tabela padrão', 'type' => 'text', 'required' => false],
        ];
    }

    public static function validateConfig(array $config): array
    {
        $required = ['db_driver', 'db_host', 'db_port', 'db_database', 'db_username'];

        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new \InvalidArgumentException("Campo obrigatório: {$field}");
            }
        }

        return $config;
    }
}
