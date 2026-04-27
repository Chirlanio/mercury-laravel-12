<?php

namespace App\Console\Commands;

use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\RelocationStatusHistory;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cancela silenciosamente drafts abandonados há mais de N dias (default 30).
 *
 * "Silenciosamente" = sem dispatch de RelocationStatusChanged (não notifica
 * stakeholders nem abre Helpdesk). Apenas grava history dizendo que foi
 * auto-cancelado pelo sistema.
 *
 * Schedule sugerido: dailyAt 02:00 (fora de horário comercial).
 */
class RelocationsAutoCancelStaleCommand extends Command
{
    protected $signature = 'relocations:auto-cancel-stale
                            {--days=30 : Quantos dias de inatividade antes de cancelar drafts}
                            {--dry-run : Lista o que seria cancelado sem persistir}';

    protected $description = 'Cancela drafts de remanejo abandonados há mais de N dias (silenciosamente, sem notificações).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Relocations Auto-Cancel Stale — drafts > {$days} dias".($dryRun ? ' (DRY-RUN)' : ''));

        $tenants = Tenant::all();
        $totalCancelled = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($days, $dryRun, &$totalCancelled) {
                    if (! Schema::hasTable('relocations')) {
                        $this->warn('  relocations não encontrada — pulando');
                        return;
                    }

                    $threshold = now()->subDays($days);

                    $stale = Relocation::query()
                        ->where('status', RelocationStatus::DRAFT->value)
                        ->where('created_at', '<', $threshold)
                        ->whereNull('deleted_at')
                        ->get(['id', 'ulid', 'title', 'created_at']);

                    if ($stale->isEmpty()) {
                        $this->line('  Nenhum draft estagnado');
                        return;
                    }

                    foreach ($stale as $r) {
                        $age = (int) $r->created_at->diffInDays(now());
                        $this->line("  • #{$r->id} [{$r->ulid}] '".($r->title ?: '(sem título)')."' — {$age} dias");

                        if ($dryRun) continue;

                        DB::transaction(function () use ($r) {
                            $r->update([
                                'status' => RelocationStatus::CANCELLED->value,
                                'cancelled_at' => now(),
                                'cancelled_reason' => 'Cancelado automaticamente: draft abandonado.',
                            ]);

                            // History sem dispatch de evento — auto-cancel é silencioso
                            RelocationStatusHistory::create([
                                'relocation_id' => $r->id,
                                'from_status' => RelocationStatus::DRAFT->value,
                                'to_status' => RelocationStatus::CANCELLED->value,
                                'changed_by_user_id' => null, // sistema
                                'note' => 'Auto-cancelamento: draft abandonado por mais de '.now()->diffInDays($r->created_at).' dias.',
                                'created_at' => now(),
                            ]);
                        });

                        $totalCancelled++;
                    }

                    $this->line("  {$stale->count()} draft(s) cancelado(s)");
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Total: {$totalCancelled} draft(s) auto-cancelado(s).");

        return self::SUCCESS;
    }
}
