<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Store;
use App\Models\TurnListAttendance;
use App\Models\TurnListAttendanceOutcome;
use App\Models\TurnListBreak;
use App\Models\TurnListBreakType;
use App\Models\TurnListStoreSetting;
use App\Models\User;
use App\Services\TurnListAttendanceService;
use App\Services\TurnListBoardService;
use App\Services\TurnListBreakService;
use App\Services\TurnListQueueService;
use App\Services\TurnListStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TurnListController extends Controller
{
    public function __construct(
        protected TurnListBoardService $boardService,
        protected TurnListQueueService $queueService,
        protected TurnListAttendanceService $attendanceService,
        protected TurnListBreakService $breakService,
        protected TurnListStatsService $statsService,
    ) {}

    /**
     * Página de relatórios (Inertia).
     */
    public function reports(Request $request): Response
    {
        $user = $request->user();
        $storeCode = $this->resolveStoreCode($user, $request);
        $period = $request->query('period', 'month');
        $from = $request->query('from');
        $to = $request->query('to');

        return Inertia::render('TurnList/Reports', [
            'storeCode' => $storeCode,
            'isStoreScoped' => $this->isStoreScoped($user),
            'stores' => $this->resolveAvailableStores($user),
            'report' => $storeCode || ! $this->isStoreScoped($user)
                ? $this->statsService->getReport($storeCode, $period, $from, $to)
                : null,
            'filters' => [
                'period' => $period,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    /**
     * Página principal — render do board (Inertia).
     * Polling subsequente usa /turn-list/board (JSON).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $storeCode = $this->resolveStoreCode($user, $request);

        return Inertia::render('TurnList/Index', [
            'storeCode' => $storeCode,
            'isStoreScoped' => $this->isStoreScoped($user),
            'stores' => $this->resolveAvailableStores($user),
            'breakTypes' => TurnListBreakType::active()->orderBy('sort_order')->get(['id', 'name', 'max_duration_minutes', 'color', 'icon']),
            'outcomes' => TurnListAttendanceOutcome::active()->orderBy('sort_order')->get([
                'id', 'name', 'description', 'color', 'icon', 'is_conversion', 'restore_queue_position',
            ]),
            'storeSetting' => $storeCode ? [
                'return_to_position' => TurnListStoreSetting::returnToPositionFor($storeCode),
            ] : null,
            'permissions' => [
                'operate' => $user->hasPermissionTo(Permission::OPERATE_TURN_LIST->value),
                'manage' => $user->hasPermissionTo(Permission::MANAGE_TURN_LIST->value),
                'reports' => $user->hasPermissionTo(Permission::VIEW_TURN_LIST_REPORTS->value),
            ],
        ]);
    }

    /**
     * Snapshot JSON do board — usado pelo polling silencioso (30s).
     * Não passa pelo Inertia para não disparar re-render do shell.
     */
    public function board(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeCode = $this->resolveStoreCode($user, $request);

        if (! $storeCode) {
            return response()->json(['error' => 'Loja não definida.'], 422);
        }

        return response()->json([
            'store_code' => $storeCode,
            'board' => $this->boardService->getBoard($storeCode),
            'fetched_at' => now()->toIso8601String(),
        ]);
    }

    // ==================================================================
    // Queue
    // ==================================================================

    public function enterQueue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'store_code' => 'required|string|max:10',
        ]);

        $this->ensureCanOperateStore($request->user(), $data['store_code']);

        try {
            $this->queueService->enter($data['employee_id'], $data['store_code'], $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back();
    }

    public function leaveQueue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'store_code' => 'required|string|max:10',
        ]);

        $this->ensureCanOperateStore($request->user(), $data['store_code']);

        $this->queueService->leave($data['employee_id'], $data['store_code']);

        return redirect()->back();
    }

    public function reorderQueue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'store_code' => 'required|string|max:10',
            'new_position' => 'required|integer|min:1',
        ]);

        $this->ensureCanOperateStore($request->user(), $data['store_code']);

        try {
            $this->queueService->reorder($data['employee_id'], $data['store_code'], $data['new_position']);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back();
    }

    // ==================================================================
    // Attendance
    // ==================================================================

    public function startAttendance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'store_code' => 'required|string|max:10',
        ]);

        $this->ensureCanOperateStore($request->user(), $data['store_code']);

        try {
            $this->attendanceService->start($data['employee_id'], $data['store_code'], $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back();
    }

    public function finishAttendance(TurnListAttendance $attendance, Request $request): RedirectResponse
    {
        $this->ensureCanOperateStore($request->user(), $attendance->store_code);

        $data = $request->validate([
            'outcome_id' => 'required|integer|exists:turn_list_attendance_outcomes,id',
            'return_to_queue' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->attendanceService->finish(
                $attendance,
                (int) $data['outcome_id'],
                $data['return_to_queue'] ?? true,
                $data['notes'] ?? null,
                $request->user(),
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back();
    }

    // ==================================================================
    // Break
    // ==================================================================

    public function startBreak(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'store_code' => 'required|string|max:10',
            'break_type_id' => 'required|integer|exists:turn_list_break_types,id',
        ]);

        $this->ensureCanOperateStore($request->user(), $data['store_code']);

        try {
            $this->breakService->start(
                $data['employee_id'],
                $data['store_code'],
                (int) $data['break_type_id'],
                $request->user(),
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back();
    }

    public function finishBreak(TurnListBreak $break, Request $request): RedirectResponse
    {
        $this->ensureCanOperateStore($request->user(), $break->store_code);

        try {
            $this->breakService->finish($break, $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back();
    }

    // ==================================================================
    // Settings (toggle return_to_position por loja)
    // ==================================================================

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_code' => 'required|string|max:10',
            'return_to_position' => 'required|boolean',
        ]);

        if (! $request->user()->hasPermissionTo(Permission::MANAGE_TURN_LIST->value)) {
            abort(403);
        }

        TurnListStoreSetting::updateOrCreate(
            ['store_code' => $data['store_code']],
            [
                'return_to_position' => (bool) $data['return_to_position'],
                'updated_by_user_id' => $request->user()->id,
            ],
        );

        return redirect()->back();
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * Resolve a loja a operar. Usuários com MANAGE podem trocar via query
     * `?store=`; demais ficam fixos na própria loja.
     */
    protected function resolveStoreCode(?User $user, Request $request): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->hasPermissionTo(Permission::MANAGE_TURN_LIST->value)) {
            return $request->query('store') ?: ($user->store_id ?: null);
        }

        return $user->store_id ?: null;
    }

    protected function isStoreScoped(?User $user): bool
    {
        return $user && ! $user->hasPermissionTo(Permission::MANAGE_TURN_LIST->value);
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    protected function resolveAvailableStores(?User $user): array
    {
        if (! $user) {
            return [];
        }

        // Sem MANAGE → só própria loja
        if (! $user->hasPermissionTo(Permission::MANAGE_TURN_LIST->value)) {
            $store = $user->store_id ? Store::where('code', $user->store_id)->first(['code', 'name']) : null;

            return $store ? [['code' => $store->code, 'name' => $store->name]] : [];
        }

        return Store::query()
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn (Store $s) => ['code' => $s->code, 'name' => $s->name])
            ->all();
    }

    /**
     * Garante que o user pode operar nesta loja (MANAGE = qualquer; OPERATE
     * = só a própria).
     */
    protected function ensureCanOperateStore(?User $user, string $storeCode): void
    {
        if (! $user) {
            abort(403);
        }

        if ($user->hasPermissionTo(Permission::MANAGE_TURN_LIST->value)) {
            return;
        }

        if ($user->store_id !== $storeCode) {
            abort(403, 'Você só pode operar a Lista da Vez da sua própria loja.');
        }
    }
}
