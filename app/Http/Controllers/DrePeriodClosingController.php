<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\DRE\CloseDrePeriodRequest;
use App\Http\Requests\DRE\ReopenDrePeriodRequest;
use App\Models\DrePeriodClosing;
use App\Models\User;
use App\Notifications\DrePeriodReopenedNotification;
use App\Services\DRE\DrePeriodClosingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de fechamentos de período da DRE (playbook prompt #11).
 *
 * Rotas:
 *   - GET    /dre/periods                   → index  (lista fechamentos + botão fechar/reabrir)
 *   - POST   /dre/periods                   → store  (fecha período)
 *   - GET    /dre/periods/{period}/preview  → JSON diffs (preview antes de reabrir)
 *   - PATCH  /dre/periods/{period}/reopen   → reabre (obriga justificativa)
 *
 * Autorização: `permission:dre.manage_periods` (só admin/super admin).
 */
class DrePeriodClosingController extends Controller
{
    public function __construct(private readonly DrePeriodClosingService $service)
    {
    }

    public function index(): Response
    {
        $periods = DrePeriodClosing::query()
            ->with(['closedBy:id,name', 'reopenedBy:id,name'])
            ->orderByDesc('closed_up_to_date')
            ->limit(50)
            ->get()
            ->map(fn (DrePeriodClosing $p) => [
                'id' => $p->id,
                'closed_up_to_date' => $p->closed_up_to_date?->format('Y-m-d'),
                'closed_at' => $p->closed_at?->toIso8601String(),
                'closed_by' => $p->closedBy?->name,
                'notes' => $p->notes,
                'reopened_at' => $p->reopened_at?->toIso8601String(),
                'reopened_by' => $p->reopenedBy?->name,
                'reopen_reason' => $p->reopen_reason,
                'is_active' => $p->reopened_at === null,
            ]);

        $last = $periods->first(fn ($p) => $p['is_active']);

        return Inertia::render('DRE/Periods/Index', [
            'periods' => $periods,
            'lastActiveId' => $last['id'] ?? null,
            'lastClosedUpTo' => DrePeriodClosing::lastClosedUpTo(),
        ]);
    }

    public function store(CloseDrePeriodRequest $request): RedirectResponse
    {
        $user = $request->user();
        \assert($user instanceof User);

        $closing = $this->service->close(
            closedUpToDate: Carbon::parse($request->input('closed_up_to_date')),
            closedBy: $user,
            notes: $request->input('notes'),
        );

        return redirect()
            ->route('dre.periods.index')
            ->with('success', "Período até {$closing->closed_up_to_date->format('d/m/Y')} foi fechado.");
    }

    /**
     * Preview de diffs antes de reabrir — a UI chama via axios/fetch e
     * mostra ao usuário para ele confirmar antes de enviar o PATCH.
     */
    public function previewReopen(Request $request, DrePeriodClosing $period): JsonResponse
    {
        abort_unless(
            $request->user()?->hasPermissionTo(Permission::MANAGE_DRE_PERIODS->value),
            403
        );

        $report = $this->service->previewReopenDiffs($period);

        return response()->json($report->toArray());
    }

    public function reopen(ReopenDrePeriodRequest $request, DrePeriodClosing $period): RedirectResponse
    {
        $user = $request->user();
        \assert($user instanceof User);

        $report = $this->service->reopen(
            closing: $period,
            reopenedBy: $user,
            reason: (string) $request->input('reason'),
        );

        // Notifica outros usuários com MANAGE_DRE_PERIODS.
        $recipients = User::query()
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $u) => $u->id !== $user->id
                && $u->hasPermissionTo(Permission::MANAGE_DRE_PERIODS->value));

        if ($recipients->isNotEmpty()) {
            Notification::send(
                $recipients,
                new DrePeriodReopenedNotification(
                    closing: $period->fresh(),
                    reopenedBy: $user,
                    reason: (string) $request->input('reason'),
                    report: $report,
                ),
            );
        }

        return redirect()
            ->route('dre.periods.index')
            ->with('success', sprintf(
                'Fechamento reaberto. %d diferenças detectadas.',
                count($report->diffs),
            ));
    }
}
