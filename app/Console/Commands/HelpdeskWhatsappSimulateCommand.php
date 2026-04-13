<?php

namespace App\Console\Commands;

use App\Models\HdChannel;
use App\Models\HdChatSession;
use App\Models\HdTicket;
use App\Models\Tenant;
use App\Services\HelpdeskIntakeService;
use Illuminate\Console\Command;

/**
 * Simulates inbound WhatsApp messages through the full intake pipeline
 * without needing Evolution, curl, or the queue worker.
 *
 * One-shot mode:
 *   php artisan helpdesk:whatsapp:simulate --tenant=meia-sola --from=5585987460451 --message="oi"
 *
 * Interactive REPL (recommended for validating the state machine):
 *   php artisan helpdesk:whatsapp:simulate --tenant=meia-sola --from=5585987460451
 *   > oi
 *   [driver reply printed]
 *   > 1
 *   [driver reply printed]
 *   ...
 *   > /state      (show the current session + ticket state)
 *   > /reset      (delete the session and start fresh)
 *   > /close      (close all open tickets for this contact — test helper only)
 *   > /exit       (quit)
 *
 * The command calls HelpdeskIntakeService::handle('whatsapp', ...) directly,
 * so it exercises the exact same code path as the real webhook job —
 * WhatsappIntakeDriver state machine, HelpdeskService::createTicket,
 * hd_ticket_channels persistence, etc. The only thing it skips is the
 * outbound Evolution sendText (which is already a no-op in tests/dev with
 * EVOLUTION_FAKE=true).
 */
class HelpdeskWhatsappSimulateCommand extends Command
{
    protected $signature = 'helpdesk:whatsapp:simulate
        {--tenant= : Tenant id (required)}
        {--from= : External contact / phone number (required)}
        {--message= : One-shot message. Omit for interactive REPL mode.}';

    protected $description = 'Simulates an inbound WhatsApp message through the helpdesk intake pipeline.';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $from = $this->option('from');

        if (! $tenantId || ! $from) {
            $this->error('Uso: --tenant=<id> --from=<numero> [--message="<texto>"]');

            return self::INVALID;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' não encontrado.");

            return self::FAILURE;
        }

        $oneShot = $this->option('message');

        return $tenant->run(function () use ($from, $oneShot) {
            // Sanity check: channel must exist and be active.
            $channel = HdChannel::findBySlug('whatsapp');
            if (! $channel) {
                $this->error('Canal whatsapp não existe neste tenant. Rode php artisan tenants:migrate.');

                return self::FAILURE;
            }
            if (! $channel->is_active) {
                $this->warn('Canal whatsapp está inativo. Ativando para a sessão…');
                $channel->update(['is_active' => true]);
            }

            if ($oneShot !== null) {
                $this->sendOne($from, (string) $oneShot);

                return self::SUCCESS;
            }

            $this->info("Modo interativo — tenant={$this->option('tenant')} from={$from}");
            $this->line('Comandos: /state · /reset · /close · /exit');
            $this->line('');

            // Heads-up about pre-existing state so the user isn't confused
            // when re-entry kicks in unexpectedly.
            $openTickets = HdTicket::query()
                ->whereNotIn('status', HdTicket::TERMINAL_STATUSES)
                ->whereHas('ticketChannels', fn ($q) => $q->where('external_contact', $from))
                ->get(['id', 'title', 'status']);

            if ($openTickets->isNotEmpty()) {
                $this->warn('Atenção: este contato já tem chamado(s) aberto(s):');
                foreach ($openTickets as $t) {
                    $this->line("  #{$t->id} [{$t->status}] {$t->title}");
                }
                $this->line('Novas mensagens serão anexadas a eles (regra de re-entry).');
                $this->line('Use /close para encerrá-los e testar o fluxo do zero, ou mude --from para um número novo.');
                $this->line('');
            }

            $activeSession = HdChatSession::where('external_contact', $from)->first();
            if ($activeSession && ! $activeSession->isExpired()) {
                $this->warn("Já existe sessão ativa no step='{$activeSession->step}'. Use /reset para limpar.");
                $this->line('');
            }

            while (true) {
                $message = $this->ask('você');

                if ($message === null) {
                    break;
                }

                $trimmed = trim($message);
                if ($trimmed === '') {
                    continue;
                }

                if ($trimmed === '/exit' || $trimmed === '/quit') {
                    $this->line('Saindo.');
                    break;
                }

                if ($trimmed === '/state') {
                    $this->showState($from);
                    continue;
                }

                if ($trimmed === '/reset') {
                    $deleted = HdChatSession::where('external_contact', $from)->delete();
                    $this->line("Sessão resetada ({$deleted} registro(s) removido(s)).");
                    continue;
                }

                if ($trimmed === '/close') {
                    $closed = HdTicket::query()
                        ->whereNotIn('status', HdTicket::TERMINAL_STATUSES)
                        ->whereHas('ticketChannels', fn ($q) => $q->where('external_contact', $from))
                        ->update([
                            'status' => 'closed',
                            'closed_at' => now(),
                        ]);
                    HdChatSession::where('external_contact', $from)->delete();
                    $this->line("{$closed} chamado(s) fechado(s) e sessão limpa. Pronto para testar do zero.");
                    continue;
                }

                $this->sendOne($from, $trimmed);
            }

            return self::SUCCESS;
        });
    }

    protected function sendOne(string $from, string $message): void
    {
        /** @var HelpdeskIntakeService $intake */
        $intake = app(HelpdeskIntakeService::class);

        try {
            $step = $intake->handle(
                channelSlug: 'whatsapp',
                payload: ['message' => $message],
                context: [
                    'external_contact' => $from,
                    'external_id' => 'sim-'.uniqid(),
                    'push_name' => 'Simulador',
                    'instance' => 'simulator',
                ],
            );
        } catch (\Throwable $e) {
            $this->error('Driver lançou exceção: '.$e->getMessage());
            $this->line($e->getTraceAsString());

            return;
        }

        $this->line('');
        $this->line('<fg=cyan>atendente virtual</>');
        foreach (explode("\n", $step->prompt) as $line) {
            $this->line('  '.$line);
        }
        $this->line('');

        if ($step->isComplete) {
            $this->info("✓ Chamado #{$step->ticketId} criado.");
            $this->showTicket($step->ticketId);
        }
    }

    protected function showState(string $from): void
    {
        $session = HdChatSession::where('external_contact', $from)->first();

        if (! $session) {
            $this->line('  (sem sessão ativa)');
        } else {
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['step', $session->step],
                    ['context', json_encode($session->context, JSON_UNESCAPED_UNICODE)],
                    ['expires_at', (string) $session->expires_at],
                    ['ticket_id', $session->ticket_id ?? '(null)'],
                ],
            );
        }

        // Also list open tickets for this contact.
        $openTickets = HdTicket::query()
            ->whereNotIn('status', HdTicket::TERMINAL_STATUSES)
            ->whereHas('ticketChannels', fn ($q) => $q->where('external_contact', $from))
            ->get(['id', 'title', 'status']);

        if ($openTickets->isNotEmpty()) {
            $this->line('Tickets abertos para este contato:');
            foreach ($openTickets as $t) {
                $this->line("  #{$t->id} [{$t->status}] {$t->title}");
            }
        }
    }

    protected function showTicket(int $ticketId): void
    {
        $ticket = HdTicket::with(['department', 'category', 'ticketChannels'])->find($ticketId);
        if (! $ticket) {
            return;
        }

        $channelRow = $ticket->ticketChannels->first();

        $this->table(['Campo', 'Valor'], [
            ['id', $ticket->id],
            ['title', $ticket->title],
            ['source', $ticket->source],
            ['department', $ticket->department?->name ?? '-'],
            ['category', $ticket->category?->name ?? '-'],
            ['priority', $ticket->priority],
            ['sla_due_at', (string) $ticket->sla_due_at],
            ['requester_id', $ticket->requester_id ?? '(null)'],
            ['channel_contact', $channelRow?->external_contact ?? '-'],
        ]);
    }
}
