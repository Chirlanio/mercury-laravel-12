<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\RelocationOverdueAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Alerta diário (09:00) de remanejos atrasados pela deadline.
 *
 * Critério overdue: `approved_at + deadline_days < now()` E status ainda
 * em (approved, in_separation, in_transit). Solicitante (creator) e
 * planejamento (APPROVE_RELOCATIONS) recebem 1 mail consolidado por
 * pessoa, evitando flood.
 */
class RelocationsOverdueAlertCommand extends Command
{
    protected $signature = 'relocations:overdue-alert';

    protected $description = 'Alerta diário sobre remanejos com deadline vencida (consolidado por destinatário).';

    public function handle(): int
    {
        $this->info('Relocations Overdue Alert — varrendo tenants...');

        $tenants = Tenant::all();
        $totalSent = 0;
        $totalRelocations = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use (&$totalSent, &$totalRelocations) {
                    if (! Schema::hasTable('relocations')) {
                        $this->warn('  relocations não encontrada — pulando');
                        return;
                    }

                    $overdue = Relocation::query()
                        ->overdue()
                        ->notDeleted()
                        ->with(['originStore:id,code', 'destinationStore:id,code'])
                        ->get();

                    if ($overdue->isEmpty()) {
                        $this->line('  Sem remanejos atrasados');
                        return;
                    }

                    $totalRelocations += $overdue->count();

                    // Indexa por destinatário (creator + planejamento)
                    $planejamento = User::query()
                        ->whereHas('roles.permissions', fn ($p) => $p->where('slug', Permission::APPROVE_RELOCATIONS->value))
                        ->get();

                    $byUser = []; // user_id => [User, [items]]

                    foreach ($overdue as $r) {
                        $payload = [
                            'id' => $r->id,
                            'ulid' => $r->ulid,
                            'title' => $r->title,
                            'days_overdue' => $r->approved_at
                                ? (int) max(0, now()->diffInDays($r->approved_at->copy()->addDays($r->deadline_days)) - 0)
                                : 0,
                            'origin_code' => $r->originStore?->code ?? '—',
                            'destination_code' => $r->destinationStore?->code ?? '—',
                            'priority_label' => $r->priority?->label() ?? '—',
                            'status_label' => $r->status?->label() ?? '—',
                        ];

                        // Recalcula days_overdue corretamente (dias passados além do prazo)
                        if ($r->approved_at && $r->deadline_days) {
                            $deadline = $r->approved_at->copy()->addDays($r->deadline_days);
                            $payload['days_overdue'] = (int) $deadline->diffInDays(now());
                        }

                        // Solicitante
                        if ($r->created_by_user_id) {
                            $byUser[$r->created_by_user_id]['items'][] = $payload;
                        }
                        // Planejamento
                        foreach ($planejamento as $u) {
                            $byUser[$u->id]['user'] = $u;
                            $byUser[$u->id]['items'][] = $payload;
                        }
                    }

                    // Resolve User instâncias do solicitante (caso ainda não estejam no map)
                    $needsLookup = array_filter(array_keys($byUser), fn ($id) => empty($byUser[$id]['user']));
                    if (! empty($needsLookup)) {
                        $users = User::whereIn('id', $needsLookup)->get()->keyBy('id');
                        foreach ($needsLookup as $id) {
                            if ($u = $users->get($id)) {
                                $byUser[$id]['user'] = $u;
                            }
                        }
                    }

                    foreach ($byUser as $entry) {
                        if (empty($entry['user']) || empty($entry['items'])) continue;
                        // Dedup por id (creator pode ter perms do planejamento)
                        $unique = collect($entry['items'])->unique('id')->values()->all();
                        Notification::send($entry['user'], new RelocationOverdueAlertNotification($unique));
                        $totalSent++;
                    }

                    $this->line("  {$overdue->count()} atrasados · ".count($byUser).' destinatário(s)');
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Total: {$totalRelocations} remanejos atrasados · {$totalSent} alertas enviados.");

        return self::SUCCESS;
    }
}
