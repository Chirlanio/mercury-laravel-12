<?php

namespace App\Services\Integrations\Drivers;

use App\Contracts\Integration\IntegrationDriver;
use App\Models\Employee;
use App\Models\Sale;
use App\Models\Store;
use App\Models\TenantIntegration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CigamSalesDriver implements IntegrationDriver
{
    protected TenantIntegration $integration;
    protected string $connectionName;
    protected array $storeCodeMap = [];
    protected array $employeeCpfMap = [];

    public function initialize(TenantIntegration $integration): void
    {
        $this->integration = $integration;
        $this->connectionName = "cigam_sales_{$integration->id}";
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
            'options' => [\PDO::ATTR_TIMEOUT => 10],
        ]);
    }

    public function testConnection(): array
    {
        try {
            $connection = DB::connection($this->connectionName);
            $connection->getPdo();

            // Verify CIGAM views exist
            $config = $this->integration->config;
            $table = $config['sales_table'] ?? 'msl_fmovimentodiario_';

            $count = $connection->table($table)->limit(1)->count();
            DB::purge($this->connectionName);

            return ['success' => true, 'message' => "Conexão CIGAM ok. Tabela '{$table}' acessível."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Falha na conexão CIGAM: ' . $e->getMessage()];
        }
    }

    public function pull(array $options = []): array
    {
        $result = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $testResult = $this->testConnection();
        if (! $testResult['success']) {
            $result['errors'][] = $testResult['message'];
            return $result;
        }

        $this->loadMappings();

        $config = $this->integration->config;
        $table = $config['sales_table'] ?? 'msl_fmovimentodiario_';

        $dateFrom = $options['date_from'] ?? Carbon::now()->subDays(7)->toDateString();
        $dateTo = $options['date_to'] ?? Carbon::now()->toDateString();
        $storeId = $options['store_id'] ?? null;

        try {
            $query = DB::connection($this->connectionName)
                ->table($table)
                ->select(
                    DB::raw("data AS date_sales"),
                    DB::raw("cod_lojas AS store_code"),
                    DB::raw("cpf_consultora AS cpf"),
                    DB::raw("SUM(CASE WHEN controle = 2 THEN valor_realizado WHEN controle = 6 AND ent_sai = 'E' THEN -valor_realizado ELSE 0 END) AS total_sales"),
                    DB::raw("SUM(CASE WHEN controle = 2 THEN qtde WHEN controle = 6 AND ent_sai = 'E' THEN -qtde ELSE 0 END) AS qtde_total")
                )
                ->where(function ($q) {
                    $q->where('controle', 2)
                      ->orWhere(function ($q2) {
                          $q2->where('controle', 6)->where('ent_sai', 'E');
                      });
                })
                ->whereBetween('data', [$dateFrom, $dateTo])
                ->groupBy('data', 'cod_lojas', 'cpf_consultora');

            if ($storeId) {
                $store = Store::find($storeId);
                if ($store) {
                    $query->where('cod_lojas', $store->code);
                }
            }

            $records = $query->get();
            $result['processed'] = $records->count();

            $skippedCpfs = [];
            $skippedStores = [];

            foreach ($records as $record) {
                try {
                    $resolvedStoreId = $this->storeCodeMap[$record->store_code] ?? null;
                    $resolvedEmployeeId = $this->employeeCpfMap[$record->cpf] ?? null;

                    if (! $resolvedEmployeeId) {
                        if (! in_array($record->cpf, $skippedCpfs)) {
                            $skippedCpfs[] = $record->cpf;
                        }
                        continue;
                    }

                    if (! $resolvedStoreId) {
                        if (! in_array($record->store_code, $skippedStores)) {
                            $skippedStores[] = $record->store_code;
                        }
                        continue;
                    }

                    $existing = Sale::where('store_id', $resolvedStoreId)
                        ->where('employee_id', $resolvedEmployeeId)
                        ->where('date_sales', $record->date_sales)
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'total_sales' => $record->total_sales,
                            'qtde_total' => (int) $record->qtde_total,
                            'source' => 'cigam',
                            'user_hash' => md5($record->cpf . $record->store_code . $record->date_sales),
                            'updated_by_user_id' => auth()->id(),
                        ]);
                        $result['updated']++;
                    } else {
                        Sale::create([
                            'store_id' => $resolvedStoreId,
                            'employee_id' => $resolvedEmployeeId,
                            'date_sales' => $record->date_sales,
                            'total_sales' => $record->total_sales,
                            'qtde_total' => (int) $record->qtde_total,
                            'source' => 'cigam',
                            'user_hash' => md5($record->cpf . $record->store_code . $record->date_sales),
                            'created_by_user_id' => auth()->id(),
                            'updated_by_user_id' => auth()->id(),
                        ]);
                        $result['created']++;
                    }
                } catch (\Exception $e) {
                    $result['failed']++;
                    Log::warning("CIGAM sales sync record error: {$e->getMessage()}");
                }
            }

            if (count($skippedCpfs) > 0) {
                $result['errors'][] = count($skippedCpfs) . ' CPFs de funcionários não cadastrados.';
            }
            if (count($skippedStores) > 0) {
                $result['errors'][] = count($skippedStores) . ' códigos de lojas não cadastrados.';
            }

            DB::purge($this->connectionName);
        } catch (\Exception $e) {
            $result['errors'][] = 'Erro na sincronização: ' . $e->getMessage();
            Log::error("CigamSalesDriver pull error: {$e->getMessage()}");
        }

        return $result;
    }

    public function push(array $options = []): array
    {
        return ['processed' => 0, 'success' => 0, 'failed' => 0, 'errors' => ['CIGAM não suporta push de vendas.']];
    }

    protected function loadMappings(): void
    {
        $this->storeCodeMap = Store::pluck('id', 'code')->toArray();
        $this->employeeCpfMap = Employee::pluck('id', 'cpf')->toArray();
    }

    public function getAvailableResources(): array
    {
        return [
            'sales' => 'Vendas (msl_fmovimentodiario_)',
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
            ['name' => 'sales_table', 'label' => 'Tabela de vendas', 'type' => 'text', 'required' => false, 'default' => 'msl_fmovimentodiario_'],
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
