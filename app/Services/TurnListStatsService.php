<?php

namespace App\Services;

use App\Enums\TurnListAttendanceStatus;
use App\Models\TurnListAttendance;
use App\Models\TurnListBreak;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Agregações estatísticas da Lista da Vez.
 *
 * Substitui a tabela `ldv_attendance_history` da v1 (não criada na v2 —
 * decisão deliberada): em vez de manter dados redundantes via trigger,
 * agregamos on-demand das mesmas tabelas operacionais com cache 5min.
 *
 * Períodos aceitos: 'today' | 'week' | 'month' | 'custom' (com from/to
 * passados explicitamente).
 *
 * Retorno padrão:
 *  - summary: total_attendances, total_employees, avg_duration_seconds,
 *    conversion_rate, total_breaks, exceeded_breaks_pct
 *  - top_employees: top 10 por volume com duration média e conversion %
 *  - by_outcome: contagem e % por outcome (entra na pizza)
 *  - by_day: série temporal de atendimentos por dia (linha)
 *  - by_hour: pico de atendimentos por hora do dia (0-23)
 *  - break_stats: total, tempo médio, % excedidas por tipo
 */
class TurnListStatsService
{
    /**
     * Cache TTL em segundos. Curto pra refletir mudanças do dia, mas
     * o suficiente pra evitar agregação a cada acesso à página.
     */
    public const CACHE_TTL = 300;

    /**
     * @return array{
     *   summary: array,
     *   top_employees: array<int, array>,
     *   by_outcome: array<int, array>,
     *   by_day: array<int, array>,
     *   by_hour: array<int, array>,
     *   break_stats: array<int, array>,
     *   period: array{from: string, to: string, label: string},
     * }
     */
    public function getReport(?string $storeCode, string $period = 'month', ?string $from = null, ?string $to = null): array
    {
        [$start, $end, $label] = $this->resolvePeriod($period, $from, $to);

        $cacheKey = sprintf(
            'turn-list:stats:%s:%s:%s',
            $storeCode ?? 'all',
            $start->toDateString(),
            $end->toDateString(),
        );

        return Cache::store('array')->remember($cacheKey, self::CACHE_TTL, function () use ($storeCode, $start, $end, $label) {
            return [
                'summary' => $this->summary($storeCode, $start, $end),
                'top_employees' => $this->topEmployees($storeCode, $start, $end),
                'by_outcome' => $this->byOutcome($storeCode, $start, $end),
                'by_day' => $this->byDay($storeCode, $start, $end),
                'by_hour' => $this->byHour($storeCode, $start, $end),
                'break_stats' => $this->breakStats($storeCode, $start, $end),
                'period' => [
                    'from' => $start->toDateString(),
                    'to' => $end->toDateString(),
                    'label' => $label,
                ],
            ];
        });
    }

    /**
     * @return array{from: \Carbon\Carbon, to: \Carbon\Carbon, label: string}
     */
    protected function resolvePeriod(string $period, ?string $from, ?string $to): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Hoje'],
            'week'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'Esta semana'],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'Este mês'],
            'custom' => [
                $from ? \Carbon\Carbon::parse($from)->startOfDay() : $now->copy()->subDays(30)->startOfDay(),
                $to ? \Carbon\Carbon::parse($to)->endOfDay() : $now->copy()->endOfDay(),
                'Período customizado',
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'Este mês'],
        };
    }

    protected function baseQuery(?string $storeCode, $start, $end)
    {
        return TurnListAttendance::query()
            ->finished()
            ->when($storeCode, fn ($q) => $q->where('store_code', $storeCode))
            ->whereBetween('started_at', [$start, $end]);
    }

    protected function summary(?string $storeCode, $start, $end): array
    {
        $base = $this->baseQuery($storeCode, $start, $end);

        $row = (clone $base)
            ->selectRaw('COUNT(*) as total, COUNT(DISTINCT employee_id) as employees, COALESCE(AVG(duration_seconds), 0) as avg_duration')
            ->first();

        // % conversão: outcomes com is_conversion=1 / total
        $conversions = (clone $base)
            ->whereHas('outcome', fn ($q) => $q->where('is_conversion', true))
            ->count();

        $totalAtt = (int) ($row->total ?? 0);
        $conversionRate = $totalAtt > 0 ? round(($conversions / $totalAtt) * 100, 1) : 0;

        // Pausas no período
        $breakBase = TurnListBreak::query()
            ->where('status', TurnListAttendanceStatus::FINISHED->value)
            ->when($storeCode, fn ($q) => $q->where('store_code', $storeCode))
            ->whereBetween('started_at', [$start, $end]);

        $totalBreaks = (clone $breakBase)->count();

        // Pausas excedidas: duration > break_type.max_duration_minutes * 60
        $exceededBreaks = (clone $breakBase)
            ->join('turn_list_break_types', 'turn_list_break_types.id', '=', 'turn_list_breaks.break_type_id')
            ->whereRaw('turn_list_breaks.duration_seconds > turn_list_break_types.max_duration_minutes * 60')
            ->count();

        $exceededPct = $totalBreaks > 0 ? round(($exceededBreaks / $totalBreaks) * 100, 1) : 0;

        return [
            'total_attendances' => $totalAtt,
            'total_employees' => (int) ($row->employees ?? 0),
            'avg_duration_seconds' => (int) ($row->avg_duration ?? 0),
            'total_conversions' => $conversions,
            'conversion_rate' => $conversionRate,
            'total_breaks' => $totalBreaks,
            'exceeded_breaks' => $exceededBreaks,
            'exceeded_breaks_pct' => $exceededPct,
        ];
    }

    /**
     * Top 10 consultoras por volume de atendimento. Inclui conversion rate
     * individual e duration média.
     */
    protected function topEmployees(?string $storeCode, $start, $end): array
    {
        $base = $this->baseQuery($storeCode, $start, $end);

        $rows = (clone $base)
            ->select(
                'employee_id',
                DB::raw('COUNT(*) as attendances'),
                DB::raw('COALESCE(AVG(duration_seconds), 0) as avg_duration'),
            )
            ->groupBy('employee_id')
            ->orderByDesc('attendances')
            ->limit(10)
            ->with('employee:id,name,short_name')
            ->get();

        // Calcula conversion por consultora separadamente (evita JOIN complexo)
        $employeeIds = $rows->pluck('employee_id')->all();
        $conversions = (clone $base)
            ->whereIn('employee_id', $employeeIds)
            ->whereHas('outcome', fn ($q) => $q->where('is_conversion', true))
            ->select('employee_id', DB::raw('COUNT(*) as conversions'))
            ->groupBy('employee_id')
            ->pluck('conversions', 'employee_id');

        return $rows->map(function ($r) use ($conversions) {
            $att = (int) $r->attendances;
            $conv = (int) ($conversions[$r->employee_id] ?? 0);

            return [
                'employee_id' => $r->employee_id,
                'name' => $r->employee?->name ?? '—',
                'short_name' => $r->employee?->short_name,
                'attendances' => $att,
                'conversions' => $conv,
                'conversion_rate' => $att > 0 ? round(($conv / $att) * 100, 1) : 0,
                'avg_duration_seconds' => (int) $r->avg_duration,
            ];
        })->all();
    }

    /**
     * Distribuição por outcome para a pizza. Inclui flag is_conversion
     * pra colorir.
     */
    protected function byOutcome(?string $storeCode, $start, $end): array
    {
        return $this->baseQuery($storeCode, $start, $end)
            ->whereNotNull('outcome_id')
            ->select('outcome_id', DB::raw('COUNT(*) as count'))
            ->groupBy('outcome_id')
            ->with('outcome:id,name,color,is_conversion,restore_queue_position')
            ->get()
            ->map(fn ($r) => [
                'outcome_id' => $r->outcome_id,
                'name' => $r->outcome?->name ?? 'Sem outcome',
                'color' => $r->outcome?->color ?? 'gray',
                'is_conversion' => (bool) ($r->outcome?->is_conversion ?? false),
                'restore_queue_position' => (bool) ($r->outcome?->restore_queue_position ?? false),
                'count' => (int) $r->count,
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Série diária de atendimentos no período (linha do gráfico).
     */
    protected function byDay(?string $storeCode, $start, $end): array
    {
        $rows = $this->baseQuery($storeCode, $start, $end)
            ->select(
                DB::raw("DATE(started_at) as day"),
                DB::raw('COUNT(*) as attendances'),
                DB::raw('COALESCE(AVG(duration_seconds), 0) as avg_duration'),
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        // Preenche dias sem registros com zero (série contínua)
        $series = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $day = $cursor->toDateString();
            $row = $rows->get($day);
            $series[] = [
                'day' => $day,
                'day_label' => $cursor->locale('pt_BR')->isoFormat('DD/MM'),
                'attendances' => (int) ($row->attendances ?? 0),
                'avg_duration_seconds' => (int) ($row->avg_duration ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Distribuição por hora do dia (0-23). Útil pra entender pico de
     * atendimentos.
     */
    protected function byHour(?string $storeCode, $start, $end): array
    {
        $rows = $this->baseQuery($storeCode, $start, $end)
            ->select(
                DB::raw("CAST(strftime('%H', started_at) AS INTEGER) as hour"),
                DB::raw('COUNT(*) as attendances'),
            )
            ->groupBy('hour')
            ->get()
            ->keyBy('hour');

        // Em MySQL strftime não existe — usa HOUR()
        if (DB::connection()->getDriverName() === 'mysql') {
            $rows = $this->baseQuery($storeCode, $start, $end)
                ->select(
                    DB::raw("HOUR(started_at) as hour"),
                    DB::raw('COUNT(*) as attendances'),
                )
                ->groupBy('hour')
                ->get()
                ->keyBy('hour');
        }

        $series = [];
        for ($h = 0; $h <= 23; $h++) {
            $row = $rows->get($h);
            $series[] = [
                'hour' => $h,
                'hour_label' => sprintf('%02dh', $h),
                'attendances' => (int) ($row->attendances ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Stats agregadas de pausas por tipo: contagem, tempo médio,
     * % excedidas.
     */
    protected function breakStats(?string $storeCode, $start, $end): array
    {
        $rows = TurnListBreak::query()
            ->where('status', TurnListAttendanceStatus::FINISHED->value)
            ->when($storeCode, fn ($q) => $q->where('store_code', $storeCode))
            ->whereBetween('started_at', [$start, $end])
            ->join('turn_list_break_types', 'turn_list_break_types.id', '=', 'turn_list_breaks.break_type_id')
            ->select(
                'turn_list_break_types.id as type_id',
                'turn_list_break_types.name as type_name',
                'turn_list_break_types.color as color',
                'turn_list_break_types.max_duration_minutes',
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(AVG(turn_list_breaks.duration_seconds), 0) as avg_duration'),
                DB::raw('SUM(CASE WHEN turn_list_breaks.duration_seconds > turn_list_break_types.max_duration_minutes * 60 THEN 1 ELSE 0 END) as exceeded_count'),
            )
            ->groupBy('type_id', 'type_name', 'color', 'max_duration_minutes')
            ->get();

        return $rows->map(function ($r) {
            $count = (int) $r->count;

            return [
                'type_id' => $r->type_id,
                'type_name' => $r->type_name,
                'color' => $r->color,
                'max_duration_minutes' => (int) $r->max_duration_minutes,
                'count' => $count,
                'avg_duration_seconds' => (int) $r->avg_duration,
                'exceeded_count' => (int) $r->exceeded_count,
                'exceeded_pct' => $count > 0 ? round(($r->exceeded_count / $count) * 100, 1) : 0,
            ];
        })->all();
    }
}
