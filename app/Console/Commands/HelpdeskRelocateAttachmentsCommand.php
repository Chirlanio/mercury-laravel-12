<?php

namespace App\Console\Commands;

use App\Models\HdAttachment;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Migrates helpdesk attachments from the public disk (legacy) to the private
 * local disk. Moves the file on disk and updates the file_path column.
 *
 * Runs per-tenant using Tenant::run() for context isolation. Follows the same
 * pattern as HelpdeskSlaMonitorCommand.
 *
 *   php artisan helpdesk:relocate-attachments --dry-run
 *   php artisan helpdesk:relocate-attachments --tenant=acme
 */
class HelpdeskRelocateAttachmentsCommand extends Command
{
    protected $signature = 'helpdesk:relocate-attachments
        {--dry-run : Report what would be moved without touching files}
        {--tenant= : Only run for a specific tenant id}';

    protected $description = 'Relocate legacy helpdesk attachments from the public disk to the private local disk.';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $tenants = $tenantId
            ? Tenant::query()->where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun
            ? '[DRY-RUN] Nada será modificado em disco ou no banco.'
            : 'Modo ativo: arquivos e banco serão atualizados.');

        $totalMoved = 0;
        $totalSkipped = 0;

        foreach ($tenants as $tenant) {
            $this->line("→ Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($dryRun, &$totalMoved, &$totalSkipped) {
                    if (! Schema::hasTable('hd_attachments')) {
                        $this->warn('  Tabela hd_attachments não encontrada (execute migrations).');

                        return;
                    }

                    [$moved, $skipped] = $this->processCurrentContext($dryRun);
                    $totalMoved += $moved;
                    $totalSkipped += $skipped;
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'Base table or view not found')) {
                    $this->warn('  Tabelas do helpdesk não encontradas.');
                } else {
                    $this->error("  Erro: {$e->getMessage()}");
                    Log::error('HelpdeskRelocateAttachmentsCommand tenant error', [
                        'tenant' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Total: {$totalMoved} movido(s), {$totalSkipped} ignorado(s).");

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int} [moved, skipped]
     */
    private function processCurrentContext(bool $dryRun): array
    {
        // Legacy attachments live at paths starting with "helpdesk-tickets/".
        $legacy = HdAttachment::query()
            ->where('file_path', 'like', 'helpdesk-tickets/%')
            ->get();

        if ($legacy->isEmpty()) {
            $this->line('  Nenhum anexo legado encontrado.');

            return [0, 0];
        }

        $public = Storage::disk('public');
        $local = Storage::disk('local');

        $moved = 0;
        $skipped = 0;

        foreach ($legacy as $attachment) {
            $oldPath = $attachment->file_path;

            if (! $public->exists($oldPath)) {
                $this->warn("  #{$attachment->id}: arquivo inexistente em public ({$oldPath}) — ignorado.");
                $skipped++;

                continue;
            }

            // helpdesk-tickets/{ticket}/{file} → helpdesk/tickets/{ticket}/{file}
            $newPath = 'helpdesk/tickets/'.substr($oldPath, strlen('helpdesk-tickets/'));

            if ($local->exists($newPath)) {
                $this->warn("  #{$attachment->id}: destino já existe em local ({$newPath}) — ignorado.");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("  #{$attachment->id}: moveria {$oldPath} → {$newPath}");
                $moved++;

                continue;
            }

            $contents = $public->get($oldPath);
            $local->put($newPath, $contents);
            $public->delete($oldPath);
            $attachment->update(['file_path' => $newPath]);

            $this->line("  #{$attachment->id}: movido para {$newPath}");
            $moved++;
        }

        $this->line("  Resultado do tenant: {$moved} movido(s), {$skipped} ignorado(s).");

        return [$moved, $skipped];
    }
}
