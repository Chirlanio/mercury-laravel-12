<?php

namespace App\Console\Commands;

use App\Enums\RelocationPriority;
use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\RelocationItem;
use App\Models\RelocationStatusHistory;
use App\Models\RelocationType;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa remanejos históricos do banco legado pra o tenant especificado.
 *
 * Origem: tabelas adms_relocations + adms_relocation_items + status no
 * banco MySQL `legacy` (configurado via LEGACY_DB_* no .env). Pré-requisito:
 * o dump u401878354_meiaso26_bd_me.sql precisa estar importado num MySQL
 * local apontado pela connection 'legacy' em config/database.php.
 *
 * Idempotente: cada registro v1 ganha legacy_id no v2. Re-rodar não
 * duplica — pula registros já importados.
 *
 * Uso:
 *   php artisan relocations:import-from-legacy --tenant=meia-sola --dry-run
 *   php artisan relocations:import-from-legacy --tenant=meia-sola --limit=10
 *   php artisan relocations:import-from-legacy --tenant=meia-sola
 */
class RelocationsImportFromLegacyCommand extends Command
{
    protected $signature = 'relocations:import-from-legacy
        {--tenant= : ID do tenant destino (obrigatório)}
        {--dry-run : Não persiste nada — só relata o que seria importado}
        {--limit= : Limita quantidade de remanejos a processar (útil pra teste)}
        {--connection=legacy : Nome da conexão configurada com o banco antigo}';

    protected $description = 'Importa remanejos históricos do banco legado (adms_relocations) pro tenant atual.';

    /** @var array<int, string> Mapa adms_status_relocations.id → RelocationStatus */
    private const STATUS_MAP = [
        1 => 'requested',     // Pendente
        2 => 'in_separation', // Iniciado
        3 => 'completed',     // Finalizado (pode virar 'partial' se items divergem)
        4 => 'cancelled',     // Cancelado
    ];

    /** @var array<string, string> Mapa enum priority v1 → v2 */
    private const PRIORITY_MAP = [
        'Baixa' => 'low',
        'Normal' => 'normal',
        'Alta' => 'high',
    ];

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        if (! $tenantId) {
            $this->error('Opção --tenant é obrigatória.');
            return self::FAILURE;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' não encontrado.");
            return self::FAILURE;
        }

        $connection = $this->option('connection');
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        // Testa conexão legacy antes de entrar no contexto do tenant
        try {
            DB::connection($connection)->getPdo();
        } catch (\Throwable $e) {
            $this->error("Falha ao conectar em '{$connection}': {$e->getMessage()}");
            $this->line('Configure LEGACY_DB_* no .env e importe o dump SQL num MySQL local.');
            return self::FAILURE;
        }

        $this->info("Tenant: {$tenant->id}");
        $this->info("Conexão legada: {$connection}");
        if ($dryRun) {
            $this->warn('DRY RUN — nada será salvo.');
        }

        $exitCode = self::SUCCESS;

        try {
            $tenant->run(function () use ($connection, $dryRun, $limit, &$exitCode) {
                $exitCode = $this->runImport($connection, $dryRun, $limit);
            });
        } catch (\Throwable $e) {
            $this->error('Falha durante a importação: '.$e->getMessage());
            return self::FAILURE;
        }

        return $exitCode;
    }

    private function runImport(string $connection, bool $dryRun, ?int $limit): int
    {
        // Pré-resolve recursos do v2: lookup de stores por code, tipo
        // padrão pra importação histórica, user "system" pra audit.
        $stores = Store::pluck('id', 'code')->all();
        if (empty($stores)) {
            $this->error('Nenhuma loja cadastrada no tenant — importe lojas antes.');
            return self::FAILURE;
        }

        $type = $this->resolveImportType($dryRun);
        $actor = $this->resolveImportActor();
        if (! $actor) {
            $this->error('Não foi possível resolver um usuário pra audit. Cadastre ao menos 1 admin.');
            return self::FAILURE;
        }

        // Carrega remanejos legados ordenados por id (cronológico)
        $query = DB::connection($connection)
            ->table('adms_relocations')
            ->orderBy('id');
        if ($limit) {
            $query->limit($limit);
        }
        $legacyRelocations = $query->get();

        $total = $legacyRelocations->count();
        if ($total === 0) {
            $this->warn('Nenhum remanejo encontrado em adms_relocations.');
            return self::SUCCESS;
        }

        $this->info("Total de remanejos legados: {$total}");
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %message%');
        $bar->start();

        $stats = [
            'imported' => 0,
            'skipped_existing' => 0,
            'skipped_invalid_store' => 0,
            'items_imported' => 0,
            'items_skipped' => 0,
        ];

        foreach ($legacyRelocations as $legacy) {
            $bar->setMessage("#{$legacy->id} {$legacy->relocation_name}");

            // Idempotência: se já foi importado, pula
            if (Relocation::where('legacy_id', $legacy->id)->exists()) {
                $stats['skipped_existing']++;
                $bar->advance();
                continue;
            }

            $originId = $stores[$legacy->source_store_id] ?? null;
            $destId = $stores[$legacy->destination_store_id] ?? null;
            if (! $originId || ! $destId) {
                $stats['skipped_invalid_store']++;
                $bar->advance();
                continue;
            }

            $itemsLegacy = DB::connection($connection)
                ->table('adms_relocation_items')
                ->where('adms_relocation_id', $legacy->id)
                ->get();

            $statusValue = self::STATUS_MAP[$legacy->adms_sit_relocation_id] ?? 'requested';
            // Refina completed → partial se algum item recebeu menos que solicitado
            if ($statusValue === 'completed' && $itemsLegacy->isNotEmpty()) {
                $hasShortage = $itemsLegacy->contains(fn ($it) => (int) ($it->amount_received ?? 0) < (int) $it->quantity_requested);
                if ($hasShortage) {
                    $statusValue = 'partial';
                }
            }

            $priority = self::PRIORITY_MAP[$legacy->priority] ?? 'normal';

            if ($dryRun) {
                $stats['imported']++;
                $stats['items_imported'] += $itemsLegacy->count();
                $bar->advance();
                continue;
            }

            DB::transaction(function () use ($legacy, $originId, $destId, $statusValue, $priority, $itemsLegacy, $type, $actor, &$stats) {
                $relocation = Relocation::create([
                    'legacy_id' => $legacy->id,
                    'ulid' => (string) Str::ulid(),
                    'relocation_type_id' => $type->id,
                    'origin_store_id' => $originId,
                    'destination_store_id' => $destId,
                    'title' => $legacy->relocation_name ?: "Remanejo legado #{$legacy->id}",
                    'observations' => "[Importado do sistema legado adms_relocations.id={$legacy->id}]",
                    'priority' => $priority,
                    'deadline_days' => max(1, (int) ($legacy->deadline ?? 3)),
                    'status' => $statusValue,
                    'created_at' => $legacy->created_at,
                    'updated_at' => $legacy->updated_at ?? $legacy->created_at,
                    // Timestamps de transição derivados (aproximação) — só
                    // setamos os que fazem sentido pra status terminal/atual.
                    'requested_at' => $legacy->created_at,
                    'approved_at' => in_array($statusValue, ['in_separation', 'completed', 'partial', 'cancelled'], true) ? $legacy->created_at : null,
                    'separated_at' => in_array($statusValue, ['in_separation', 'completed', 'partial'], true) ? $legacy->created_at : null,
                    'in_transit_at' => in_array($statusValue, ['completed', 'partial'], true) ? ($legacy->updated_at ?? $legacy->created_at) : null,
                    'completed_at' => in_array($statusValue, ['completed', 'partial'], true) ? ($legacy->updated_at ?? $legacy->created_at) : null,
                    'cancelled_at' => $statusValue === 'cancelled' ? ($legacy->updated_at ?? $legacy->created_at) : null,
                    'created_by_user_id' => $actor->id,
                    'updated_by_user_id' => $actor->id,
                ]);

                // History inicial — replica timeline básica
                RelocationStatusHistory::create([
                    'relocation_id' => $relocation->id,
                    'from_status' => null,
                    'to_status' => 'draft',
                    'changed_by_user_id' => $actor->id,
                    'note' => 'Importado do sistema legado',
                    'created_at' => $legacy->created_at,
                ]);
                if ($statusValue !== 'draft') {
                    RelocationStatusHistory::create([
                        'relocation_id' => $relocation->id,
                        'from_status' => 'draft',
                        'to_status' => $statusValue,
                        'changed_by_user_id' => $actor->id,
                        'note' => 'Status inferido do legacy adms_sit_relocation_id='.$legacy->adms_sit_relocation_id,
                        'created_at' => $legacy->updated_at ?? $legacy->created_at,
                    ]);
                }

                // Importa items
                foreach ($itemsLegacy as $itLegacy) {
                    if (RelocationItem::where('legacy_id', $itLegacy->id)->exists()) {
                        $stats['items_skipped']++;
                        continue;
                    }
                    $qtyRequested = (int) $itLegacy->quantity_requested;
                    $qtyReceived = (int) ($itLegacy->amount_received ?? 0);
                    // qty_separated não existe no v1 — assume = received quando
                    // status é completed/partial; senão zero.
                    $qtySeparated = in_array($statusValue, ['in_separation', 'completed', 'partial'], true)
                        ? max($qtyReceived, 0)
                        : 0;

                    RelocationItem::create([
                        'legacy_id' => $itLegacy->id,
                        'relocation_id' => $relocation->id,
                        'product_reference' => $itLegacy->product_reference,
                        'product_name' => null,
                        'product_color' => null,
                        'size' => $itLegacy->size,
                        'barcode' => null,
                        'qty_requested' => $qtyRequested,
                        'qty_separated' => $qtySeparated,
                        'qty_received' => $qtyReceived,
                        'observations' => $itLegacy->observations,
                        'created_at' => $itLegacy->created,
                        'updated_at' => $itLegacy->modified ?? $itLegacy->created,
                    ]);
                    $stats['items_imported']++;
                }

                // Invoice number do header se houver consenso entre items
                $invoices = $itemsLegacy->pluck('invoice')->filter()->unique()->values();
                if ($invoices->count() === 1) {
                    $relocation->update([
                        'invoice_number' => (string) $invoices->first(),
                        'invoice_date' => $legacy->updated_at ?? $legacy->created_at,
                    ]);
                }

                $stats['imported']++;
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Métrica', 'Total'],
            [
                ['Remanejos importados', $stats['imported']],
                ['Remanejos pulados (já existiam)', $stats['skipped_existing']],
                ['Remanejos pulados (loja inexistente)', $stats['skipped_invalid_store']],
                ['Items importados', $stats['items_imported']],
                ['Items pulados (já existiam)', $stats['items_skipped']],
            ],
        );

        if ($dryRun) {
            $this->warn('DRY RUN — nenhum dado foi persistido.');
        }

        return self::SUCCESS;
    }

    /**
     * Tipo "Histórico Legado" — usado em todos os remanejos importados pra
     * separar do fluxo operacional novo. Cria se não existir.
     */
    private function resolveImportType(bool $dryRun): RelocationType
    {
        if ($dryRun) {
            $existing = RelocationType::where('code', 'LEGADO')->first();
            if ($existing) return $existing;
            // Mock pra dry-run — não persiste
            $type = new RelocationType([
                'code' => 'LEGADO',
                'name' => 'Histórico Legado',
                'is_active' => true,
                'sort_order' => 99,
            ]);
            $type->id = 0;
            return $type;
        }

        return RelocationType::firstOrCreate(
            ['code' => 'LEGADO'],
            [
                'name' => 'Histórico Legado',
                'is_active' => true,
                'sort_order' => 99,
            ],
        );
    }

    /**
     * Resolve um usuário pra usar como ator nos audit fields. Prefere
     * super-admin (lower id), fallback pro primeiro user ativo.
     */
    private function resolveImportActor(): ?User
    {
        return User::query()->orderBy('id')->first();
    }
}
