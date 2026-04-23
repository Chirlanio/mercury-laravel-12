<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerSyncLog;
use App\Services\Customers\CustomerSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza clientes da view CIGAM `msl_dcliente_` para a tabela
 * `customers` do tenant. Segue o mesmo padrão do ProductSyncService:
 *
 *  - isAvailable()  : testa a conexão postgres antes de iniciar
 *  - start(...)     : cria CustomerSyncLog e retorna o id
 *  - processChunk(..): processa N registros via keyset (cigam_code)
 *                      retornando has_more/last_code para a UI pollar
 *
 * Chave de upsert: `cigam_code` = (codigo_cliente + digito_cliente
 * concatenados) — natural do CIGAM, estável.
 *
 * Sanitização é 100% via CustomerSanitizer (estático e testável
 * separadamente). Este service faz só IO + coordenação.
 */
class CustomerSyncService
{
    /**
     * Testa a conexão com CIGAM antes de iniciar um sync — evita
     * enfileirar jobs que vão falhar e logar log inútil.
     *
     * Timeout de 15s porque o CIGAM é um PostgreSQL remoto fora da rede
     * local (infra CIGAM hospedada externamente); 3s-5s era agressivo
     * demais e dava falso-negativo em horários de pico.
     */
    public function isAvailable(): bool
    {
        try {
            $config = config('database.connections.cigam');
            if (empty($config['host']) || empty($config['database'])) {
                return false;
            }

            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;connect_timeout=15',
                $config['host'],
                $config['port'] ?? '5432',
                $config['database'],
            );

            new \PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', [
                \PDO::ATTR_TIMEOUT => 15,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Inicia um novo log de sync. Conta total de registros na view pra
     * barra de progresso.
     */
    public function start(string $syncType = 'full', ?int $userId = null): CustomerSyncLog
    {
        $log = CustomerSyncLog::create([
            'sync_type' => $syncType,
            'status' => 'running',
            'started_at' => now(),
            'started_by_user_id' => $userId,
        ]);

        try {
            $total = (int) DB::connection('cigam')
                ->table('msl_dcliente_')
                ->count();
            $log->update(['total_records' => $total]);
        } catch (\Throwable $e) {
            // Count pode falhar em views muito grandes — não bloqueia
            Log::warning('CustomerSync: count falhou', ['error' => $e->getMessage()]);
        }

        return $log->fresh();
    }

    /**
     * Processa um chunk por keyset pagination (cigam_code > last_code).
     * Retorna {processed, inserted, updated, has_more, last_code}.
     *
     * @return array{
     *   processed: int, inserted: int, updated: int, skipped: int,
     *   has_more: bool, last_code: ?string, cancelled: bool
     * }
     */
    public function processChunk(int $logId, ?string $lastCode = null, int $size = 500): array
    {
        set_time_limit(120);

        $log = CustomerSyncLog::findOrFail($logId);

        if ($log->status === 'cancelled') {
            return [
                'processed' => 0, 'inserted' => 0, 'updated' => 0,
                'skipped' => 0, 'has_more' => false, 'last_code' => $lastCode,
                'cancelled' => true,
            ];
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        try {
            // Keyset: seleciona próximo bloco ORDER BY cigam_code
            $query = DB::connection('cigam')
                ->table('msl_dcliente_')
                ->selectRaw("
                    codigo_cliente, digito_cliente, nome_completo,
                    ddd_telefone, telefone, ddd_celular, celular, email,
                    endereco, numero, complemento, bairro, uf, cidade, cep,
                    tip_pessoa, data_cadastramento, data_aniversario, sexo,
                    (codigo_cliente::text || '-' || COALESCE(digito_cliente::text, '')) AS cigam_code
                ")
                ->orderByRaw("(codigo_cliente::text || '-' || COALESCE(digito_cliente::text, ''))");

            if ($lastCode !== null) {
                $query->whereRaw(
                    "(codigo_cliente::text || '-' || COALESCE(digito_cliente::text, '')) > ?",
                    [$lastCode],
                );
            }

            $rows = $query->limit($size)->get();

            if ($rows->isEmpty()) {
                $log->update(['status' => 'completed', 'completed_at' => now()]);

                return [
                    'processed' => 0, 'inserted' => 0, 'updated' => 0,
                    'skipped' => 0, 'has_more' => false, 'last_code' => $lastCode,
                    'cancelled' => false,
                ];
            }

            $currentLastCode = $lastCode;

            foreach ($rows as $row) {
                $currentLastCode = $row->cigam_code;

                try {
                    $result = $this->upsertCustomer($row);
                    if ($result === 'inserted') {
                        $inserted++;
                    } elseif ($result === 'updated') {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = [
                        'cigam_code' => $row->cigam_code,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $processedThisChunk = $rows->count();
            $hasMore = $processedThisChunk === $size;

            $log->update([
                'processed_records' => $log->processed_records + $processedThisChunk,
                'inserted_records' => $log->inserted_records + $inserted,
                'updated_records' => $log->updated_records + $updated,
                'skipped_records' => $log->skipped_records + $skipped,
                'error_count' => $log->error_count + count($errors),
                'error_details' => empty($errors)
                    ? $log->error_details
                    : array_merge($log->error_details ?? [], $errors),
                'status' => $hasMore ? 'running' : 'completed',
                'completed_at' => $hasMore ? null : now(),
            ]);

            return [
                'processed' => $processedThisChunk,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'has_more' => $hasMore,
                'last_code' => $currentLastCode,
                'cancelled' => false,
            ];
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_count' => $log->error_count + 1,
                'error_details' => array_merge($log->error_details ?? [], [
                    ['fatal' => $e->getMessage()],
                ]),
            ]);

            throw $e;
        }
    }

    /**
     * Upsert de 1 cliente com sanitização. Retorna 'inserted' | 'updated'
     * | 'skipped' (skipped quando não há mudanças detectáveis).
     *
     * Exposto público para testes unitários.
     */
    public function upsertCustomer(object $row): string
    {
        $cigamCode = trim((string) $row->cigam_code);
        if ($cigamCode === '' || $cigamCode === '-') {
            return 'skipped';
        }

        $attributes = [
            'name' => CustomerSanitizer::normalizeName($row->nome_completo ?? null),
            'cpf' => CustomerSanitizer::normalizeCpf($row->digito_cliente ?? null),
            'person_type' => CustomerSanitizer::normalizePersonType($row->tip_pessoa ?? null),
            'gender' => CustomerSanitizer::normalizeGender($row->sexo ?? null),
            'email' => CustomerSanitizer::normalizeEmail($row->email ?? null),
            'phone' => CustomerSanitizer::normalizePhone(
                $row->ddd_telefone ?? null,
                $row->telefone ?? null,
            ),
            'mobile' => CustomerSanitizer::normalizePhone(
                $row->ddd_celular ?? null,
                $row->celular ?? null,
            ),
            'address' => CustomerSanitizer::normalizeText($row->endereco ?? null, 250),
            'number' => CustomerSanitizer::normalizeText($row->numero ?? null, 20),
            'complement' => CustomerSanitizer::normalizeText($row->complemento ?? null, 100),
            'neighborhood' => CustomerSanitizer::normalizeText($row->bairro ?? null, 100),
            'city' => CustomerSanitizer::normalizeText($row->cidade ?? null, 100),
            'state' => CustomerSanitizer::normalizeState($row->uf ?? null),
            'zipcode' => CustomerSanitizer::normalizeZipcode($row->cep ?? null),
            'birth_date' => CustomerSanitizer::normalizeDate($row->data_aniversario ?? null),
            'registered_at' => CustomerSanitizer::normalizeDate($row->data_cadastramento ?? null),
            'is_active' => true,
            'synced_at' => now(),
        ];

        // Nome obrigatório — se não veio (CIGAM sujo), pula
        if (! $attributes['name']) {
            return 'skipped';
        }

        $existing = Customer::where('cigam_code', $cigamCode)->first();
        if ($existing) {
            $existing->update($attributes);

            return $existing->wasChanged() ? 'updated' : 'skipped';
        }

        Customer::create(array_merge(['cigam_code' => $cigamCode], $attributes));

        return 'inserted';
    }

    /**
     * Cancela um sync em andamento. O próximo processChunk vai detectar
     * o status e abortar.
     */
    public function cancel(int $logId): void
    {
        CustomerSyncLog::where('id', $logId)
            ->whereIn('status', ['pending', 'running'])
            ->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);
    }
}
