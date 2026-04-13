<?php

namespace App\Console\Commands;

use App\Models\HdAiClassificationCorrection;
use App\Models\HdCategory;
use App\Models\HdDepartment;
use App\Models\HdTicket;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Reports AI classification accuracy per tenant.
 *
 * The accuracy metric compares what the AI suggested at ticket creation
 * time (ai_category_id snapshotted in the ticket + any corrections logged
 * in hd_ai_classification_corrections) against the final category_id on
 * the ticket. A ticket where the human kept the AI's suggested category
 * counts as "kept"; one where they changed it counts as "corrected".
 *
 * Output:
 *   - Overall totals
 *   - Per-department breakdown
 *   - Top corrected categories (which ones the AI gets wrong most often)
 *
 * Usage:
 *   php artisan helpdesk:ai:accuracy-report
 *   php artisan helpdesk:ai:accuracy-report --tenant=meia-sola
 *   php artisan helpdesk:ai:accuracy-report --since=30days
 *   php artisan helpdesk:ai:accuracy-report --since=2026-01-01
 *
 * Pure reporting — no DB writes, no external API calls.
 */
class HelpdeskAiAccuracyReportCommand extends Command
{
    protected $signature = 'helpdesk:ai:accuracy-report
        {--tenant= : Only report on a specific tenant id}
        {--since= : Only include tickets created on or after this date (YYYY-MM-DD or "Ndays")}';

    protected $description = 'Reports AI classification accuracy using hd_ai_classification_corrections feedback.';

    public function handle(): int
    {
        $since = $this->parseSince();
        if ($since) {
            $this->line('Recortando a partir de: '.$since->format('Y-m-d'));
        }

        $tenantId = $this->option('tenant');
        $tenants = $tenantId
            ? Tenant::query()->where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->line('');
            $this->info("━━━ Tenant: {$tenant->id} ━━━");

            try {
                $tenant->run(function () use ($since) {
                    if (! Schema::hasTable('hd_tickets')) {
                        $this->warn('  Tabelas do helpdesk não encontradas.');

                        return;
                    }
                    if (! Schema::hasTable('hd_ai_classification_corrections')) {
                        $this->warn('  Tabela hd_ai_classification_corrections não existe (execute migrations).');

                        return;
                    }

                    $this->reportForCurrentTenant($since);
                });
            } catch (\Throwable $e) {
                $this->error('  Erro: '.$e->getMessage());
                Log::error('HelpdeskAiAccuracyReportCommand tenant error', [
                    'tenant' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->line('');

        return self::SUCCESS;
    }

    protected function reportForCurrentTenant(?Carbon $since): void
    {
        // Tickets that had an AI suggestion. These are the candidates for
        // measuring accuracy — we can only judge the AI on tickets it
        // actually classified.
        $ticketQuery = HdTicket::query()
            ->whereNotNull('ai_category_id')
            ->whereNotNull('ai_classified_at');

        if ($since) {
            $ticketQuery->where('created_at', '>=', $since);
        }

        $tickets = $ticketQuery->get([
            'id', 'department_id', 'category_id', 'ai_category_id',
            'priority', 'ai_priority', 'ai_confidence',
        ]);

        if ($tickets->isEmpty()) {
            $this->line('  Nenhum ticket com classificação IA no período.');

            return;
        }

        $total = $tickets->count();
        $categoryKept = $tickets->filter(fn ($t) => $t->category_id === $t->ai_category_id)->count();
        $categoryCorrected = $total - $categoryKept;
        $priorityKept = $tickets->filter(fn ($t) => $t->ai_priority !== null && $t->priority === $t->ai_priority)->count();
        $priorityRelevant = $tickets->filter(fn ($t) => $t->ai_priority !== null)->count();

        $catAcc = $total > 0 ? round(($categoryKept / $total) * 100, 1) : 0;
        $priAcc = $priorityRelevant > 0 ? round(($priorityKept / $priorityRelevant) * 100, 1) : 0;

        $avgConfidence = round($tickets->avg('ai_confidence') ?? 0, 2);

        $this->line('');
        $this->line('  Visão geral:');
        $this->table(['Métrica', 'Valor'], [
            ['Tickets com IA', $total],
            ['Categoria mantida', $categoryKept],
            ['Categoria corrigida', $categoryCorrected],
            ['Acurácia de categoria', $catAcc.'%'],
            ['Prioridade mantida (n='.$priorityRelevant.')', $priAcc.'%'],
            ['Confiança média', $avgConfidence],
        ]);

        // Per-department breakdown
        $this->line('');
        $this->line('  Por departamento:');

        $byDept = $tickets->groupBy('department_id');
        $deptRows = [];
        foreach ($byDept as $deptId => $deptTickets) {
            $deptTotal = $deptTickets->count();
            $deptKept = $deptTickets->filter(fn ($t) => $t->category_id === $t->ai_category_id)->count();
            $deptAcc = $deptTotal > 0 ? round(($deptKept / $deptTotal) * 100, 1) : 0;
            $deptName = HdDepartment::find($deptId)?->name ?? "#{$deptId}";
            $deptRows[] = [$deptName, $deptTotal, $deptKept, ($deptTotal - $deptKept), $deptAcc.'%'];
        }
        usort($deptRows, fn ($a, $b) => $b[1] <=> $a[1]);
        $this->table(['Departamento', 'Total', 'Mantido', 'Corrigido', 'Acurácia'], $deptRows);

        // Top corrected categories — what did the AI suggest that humans
        // kept changing? Filtered through the corrections log so we know
        // the direction of the change.
        $this->line('');
        $this->line('  Top categorias mais corrigidas (feedback loop):');

        $correctionsQuery = HdAiClassificationCorrection::query();
        if ($since) {
            $correctionsQuery->where('created_at', '>=', $since);
        }
        $corrections = $correctionsQuery->get(['original_ai_category_id', 'corrected_category_id']);

        if ($corrections->isEmpty()) {
            $this->line('    (sem correções registradas no período)');

            return;
        }

        $catCorrectionCount = [];
        foreach ($corrections as $c) {
            if ($c->original_ai_category_id === null) {
                continue;
            }
            // Only count entries where the corrected category differs
            // from the AI's original suggestion — ignore priority-only
            // corrections in this particular breakdown.
            if ($c->corrected_category_id !== null && $c->corrected_category_id !== $c->original_ai_category_id) {
                $catCorrectionCount[$c->original_ai_category_id] =
                    ($catCorrectionCount[$c->original_ai_category_id] ?? 0) + 1;
            }
        }

        if (empty($catCorrectionCount)) {
            $this->line('    (sem correções de categoria no período)');

            return;
        }

        arsort($catCorrectionCount);
        $top = array_slice($catCorrectionCount, 0, 5, true);

        $topRows = [];
        foreach ($top as $catId => $count) {
            $name = HdCategory::find($catId)?->name ?? "#{$catId}";
            $topRows[] = [$name, $count];
        }
        $this->table(['Categoria sugerida pela IA', 'Vezes corrigida'], $topRows);
    }

    /**
     * Parse the --since option. Accepts "Ndays" or "YYYY-MM-DD" format.
     * Returns null when the option is missing.
     */
    protected function parseSince(): ?Carbon
    {
        $since = $this->option('since');
        if (! $since) {
            return null;
        }

        if (preg_match('/^(\d+)days?$/', $since, $m)) {
            return now()->subDays((int) $m[1]);
        }

        try {
            return Carbon::parse($since);
        } catch (\Throwable $e) {
            $this->error("--since inválido: {$since}. Use YYYY-MM-DD ou Ndays.");
            exit(self::INVALID);
        }
    }
}
