import { Head, router } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    BarChart, Bar, PieChart, Pie, Cell, LineChart, Line,
    XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
    LabelList,
} from 'recharts';
import {
    RectangleStackIcon,
    TruckIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ChartBarIcon,
    ArrowsRightLeftIcon,
    CalendarDaysIcon,
} from '@heroicons/react/24/outline';
import PageHeader from '@/Components/Shared/PageHeader';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import EmptyState from '@/Components/Shared/EmptyState';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';

const STATUS_HEX = {
    gray: '#9ca3af',
    warning: '#eab308',
    info: '#3b82f6',
    purple: '#a855f7',
    indigo: '#6366f1',
    success: '#22c55e',
    orange: '#f97316',
    danger: '#ef4444',
};

const PIE_COLORS = [
    '#4338ca', '#0891b2', '#059669', '#d97706', '#dc2626',
    '#7c3aed', '#db2777', '#ea580c', '#65a30d', '#6366f1',
];

// Cor da barra de aderência conforme percentual
const adherenceColor = (pct) => {
    if (pct >= 90) return '#10b981';   // verde
    if (pct >= 70) return '#f59e0b';   // amarelo
    return '#ef4444';                   // vermelho
};

// Presets de período para os botões rápidos
const PRESETS = [
    { key: '30d', label: 'Últimos 30 dias', days: 30 },
    { key: '60d', label: 'Últimos 60 dias', days: 60 },
    { key: '90d', label: '90 dias', days: 90 },
    { key: '180d', label: '180 dias', days: 180 },
    { key: 'mtd', label: 'Mês atual' },
    { key: 'lastMonth', label: 'Mês passado' },
    { key: 'ytd', label: 'Ano atual' },
];

const fmtIso = (d) => d.toISOString().slice(0, 10);

const computePresetRange = (key) => {
    const today = new Date();
    today.setHours(12, 0, 0, 0); // evita drift por timezone
    if (key === 'mtd') {
        const from = new Date(today.getFullYear(), today.getMonth(), 1);
        return { date_from: fmtIso(from), date_to: fmtIso(today) };
    }
    if (key === 'lastMonth') {
        const from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const to = new Date(today.getFullYear(), today.getMonth(), 0);
        return { date_from: fmtIso(from), date_to: fmtIso(to) };
    }
    if (key === 'ytd') {
        const from = new Date(today.getFullYear(), 0, 1);
        return { date_from: fmtIso(from), date_to: fmtIso(today) };
    }
    const days = PRESETS.find((p) => p.key === key)?.days ?? 90;
    const from = new Date(today);
    from.setDate(from.getDate() - days + 1);
    return { date_from: fmtIso(from), date_to: fmtIso(today) };
};

export default function Dashboard({
    statistics = {},
    analytics = {},
    filters = {},
    isStoreScoped = false,
}) {
    const dateFrom = filters.date_from || '';
    const dateTo = filters.date_to || '';

    const applyFilters = (params) => {
        router.get(route('relocations.dashboard'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const applyPreset = (presetKey) => applyFilters(computePresetRange(presetKey));

    const onCustomChange = (key, value) => {
        const params = { date_from: dateFrom, date_to: dateTo, [key]: value };
        // Se trocar from > to, alinha
        if (params.date_from && params.date_to && params.date_from > params.date_to) {
            if (key === 'date_from') params.date_to = params.date_from;
            else params.date_from = params.date_to;
        }
        applyFilters(params);
    };

    // Detecta qual preset está ativo (se houver)
    const activePreset = useMemo(() => {
        if (!dateFrom || !dateTo) return null;
        for (const p of PRESETS) {
            const range = computePresetRange(p.key);
            if (range.date_from === dateFrom && range.date_to === dateTo) return p.key;
        }
        return null;
    }, [dateFrom, dateTo]);

    const periodLabel = useMemo(() => {
        if (!dateFrom || !dateTo) return null;
        const from = new Date(`${dateFrom}T00:00:00`).toLocaleDateString('pt-BR');
        const to = new Date(`${dateTo}T00:00:00`).toLocaleDateString('pt-BR');
        return `${from} a ${to}`;
    }, [dateFrom, dateTo]);

    const timeline = analytics.timeline || [];
    const byStatus = analytics.by_status || [];
    const byOrigin = analytics.by_origin || [];
    const byDestination = analytics.by_destination || [];
    const byType = analytics.by_type || [];
    const topProducts = analytics.top_products || [];
    const performance = analytics.performance || {};

    const statusPieData = byStatus.map((s) => ({
        name: s.label,
        value: s.count,
        fill: STATUS_HEX[s.color] || STATUS_HEX.gray,
    }));

    const typePieData = byType.map((t, i) => ({
        name: t.type_name,
        value: t.count,
        fill: PIE_COLORS[i % PIE_COLORS.length],
    }));

    const cards = [
        {
            label: 'Total (não excluídos)',
            value: statistics.total || 0,
            format: 'number',
            icon: RectangleStackIcon,
            color: 'indigo',
        },
        {
            label: 'Em trânsito agora',
            value: statistics.in_transit || 0,
            format: 'number',
            icon: TruckIcon,
            color: 'blue',
            sub: performance.avg_hours_cigam_transit > 0
                ? `Média ${performance.avg_hours_cigam_transit}h CIGAM`
                : null,
        },
        {
            label: 'Atrasados (overdue)',
            value: statistics.overdue || 0,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: 'red',
        },
        {
            label: 'Match CIGAM completo',
            value: performance.cigam_matched_rate || 0,
            format: 'percentage',
            icon: ArrowsRightLeftIcon,
            color: 'green',
            sub: 'saída + entrada confirmadas',
        },
        {
            label: 'Tempo médio até despacho',
            value: performance.avg_days_to_dispatch || 0,
            format: 'number',
            icon: ChartBarIcon,
            color: 'purple',
            sub: 'dias entre aprovação → trânsito',
        },
    ];

    return (
        <>
            <Head title="Dashboard de Remanejos" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <PageHeader
                        title="Dashboard de Remanejos"
                        subtitle={periodLabel
                            ? `Período: ${periodLabel}`
                            : 'Aderência das lojas, tempo de trânsito e ranking de produtos'
                        }
                        icon={ChartBarIcon}
                        scopeBadge={isStoreScoped ? 'escopo: sua loja (origem ou destino)' : null}
                        actions={[
                            {
                                type: 'back',
                                label: 'Voltar à listagem',
                                href: route('relocations.index'),
                            },
                        ]}
                    />

                    {/* Filtro de período */}
                    <div className="bg-white shadow-sm rounded-lg p-4">
                        <div className="flex items-start justify-between gap-4 flex-wrap">
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-2">
                                    <CalendarDaysIcon className="h-4 w-4 text-gray-500" />
                                    <span className="text-xs font-semibold text-gray-700 uppercase tracking-wide">
                                        Período
                                    </span>
                                </div>
                                <div className="flex flex-wrap gap-1.5">
                                    {PRESETS.map((p) => {
                                        const isActive = activePreset === p.key;
                                        return (
                                            <button
                                                key={p.key}
                                                type="button"
                                                onClick={() => applyPreset(p.key)}
                                                className={`px-3 py-1.5 text-xs font-medium rounded-md border transition-colors ${
                                                    isActive
                                                        ? 'bg-indigo-600 text-white border-indigo-600'
                                                        : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                                }`}
                                            >
                                                {p.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex items-end gap-2 shrink-0">
                                <div>
                                    <InputLabel value="De" className="text-xs" />
                                    <input
                                        type="date"
                                        value={dateFrom}
                                        onChange={(e) => onCustomChange('date_from', e.target.value)}
                                        className="block rounded-md border-gray-300 shadow-sm sm:text-sm"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Até" className="text-xs" />
                                    <input
                                        type="date"
                                        value={dateTo}
                                        onChange={(e) => onCustomChange('date_to', e.target.value)}
                                        className="block rounded-md border-gray-300 shadow-sm sm:text-sm"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <StatisticsGrid cards={cards} cols={5} />

                    {/* Linha temporal — 12 meses */}
                    <div className="bg-white rounded-lg shadow-sm p-4">
                        <h3 className="text-sm font-semibold text-gray-700 mb-3">
                            Volume mensal — últimos 12 meses
                        </h3>
                        {timeline.length === 0 ? (
                            <EmptyState compact title="Sem dados no período" />
                        ) : (
                            <ResponsiveContainer width="100%" height={260}>
                                <LineChart data={timeline}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="label" tick={{ fontSize: 11 }} />
                                    <YAxis tick={{ fontSize: 11 }} />
                                    <Tooltip
                                        formatter={(v) => [v, 'Remanejos']}
                                        labelStyle={{ color: '#111' }}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="count"
                                        stroke="#4338ca"
                                        strokeWidth={2}
                                        dot={{ fill: '#4338ca', r: 4 }}
                                        activeDot={{ r: 6 }}
                                        name="Remanejos"
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        )}
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Aderência por loja origem */}
                        <div className="bg-white rounded-lg shadow-sm p-4">
                            <h3 className="text-sm font-semibold text-gray-700 mb-1">
                                Aderência da loja origem
                            </h3>
                            <p className="text-xs text-gray-500 mb-3">
                                % despachado via CIGAM (movement_code=5+S) sobre o solicitado
                            </p>
                            {byOrigin.length === 0 ? (
                                <EmptyState compact title="Sem origens registradas" />
                            ) : (
                                <ResponsiveContainer width="100%" height={300}>
                                    <BarChart
                                        data={byOrigin}
                                        layout="vertical"
                                        margin={{ left: 60, right: 50 }}
                                    >
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis type="number" domain={[0, 100]} tick={{ fontSize: 11 }} />
                                        <YAxis
                                            dataKey="store_code"
                                            type="category"
                                            tick={{ fontSize: 11, fontFamily: 'monospace' }}
                                            width={50}
                                        />
                                        <Tooltip
                                            formatter={(value, name, ctx) => {
                                                if (name === 'adherence') {
                                                    return [`${value}%`, 'Aderência'];
                                                }
                                                return [value, name];
                                            }}
                                            labelFormatter={(code) => {
                                                const row = byOrigin.find((r) => r.store_code === code);
                                                return row ? `${code} — ${row.store_name}` : code;
                                            }}
                                            content={({ active, payload, label }) => {
                                                if (!active || !payload?.length) return null;
                                                const row = byOrigin.find((r) => r.store_code === label);
                                                if (!row) return null;
                                                return (
                                                    <div className="bg-white border border-gray-200 rounded shadow p-2 text-xs">
                                                        <div className="font-semibold">{label} — {row.store_name}</div>
                                                        <div className="text-gray-600 mt-1 space-y-0.5">
                                                            <div>Aderência: <span className="font-bold" style={{color: adherenceColor(row.adherence)}}>{row.adherence}%</span></div>
                                                            <div>Entrega: <span className="font-medium">{row.delivery_rate ?? 0}%</span> ({row.completed_count}/{row.relocations_count})</div>
                                                            {row.dispatch_accuracy !== null && (
                                                                <div>Acurácia despacho: <span className="font-medium">{row.dispatch_accuracy}%</span></div>
                                                            )}
                                                            {row.avg_separation_hours !== null && (
                                                                <div>Tempo médio separação: <span className="font-medium">{row.avg_separation_hours}h</span></div>
                                                            )}
                                                            <div className="border-t border-gray-100 pt-0.5 mt-1">
                                                                <div>Solicitado: {row.total_requested} un</div>
                                                                <div>Despachado: {row.total_dispatched} un</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            }}
                                        />
                                        <Bar dataKey="adherence" radius={[0, 4, 4, 0]}>
                                            {byOrigin.map((entry, i) => (
                                                <Cell key={i} fill={adherenceColor(entry.adherence)} />
                                            ))}
                                            <LabelList
                                                dataKey="adherence"
                                                position="right"
                                                formatter={(v) => `${v}%`}
                                                style={{ fontSize: 10, fill: '#374151' }}
                                            />
                                        </Bar>
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        {/* Distribuição por status */}
                        <div className="bg-white rounded-lg shadow-sm p-4">
                            <h3 className="text-sm font-semibold text-gray-700 mb-3">
                                Distribuição por status
                            </h3>
                            {statusPieData.length === 0 ? (
                                <EmptyState compact title="Sem registros" />
                            ) : (
                                <ResponsiveContainer width="100%" height={300}>
                                    <PieChart>
                                        <Pie
                                            data={statusPieData}
                                            dataKey="value"
                                            nameKey="name"
                                            cx="50%"
                                            cy="50%"
                                            outerRadius={100}
                                            label={(entry) => `${entry.name}: ${entry.value}`}
                                            labelLine={false}
                                        >
                                            {statusPieData.map((entry, i) => (
                                                <Cell key={i} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        {/* Top produtos */}
                        <div className="bg-white rounded-lg shadow-sm p-4">
                            <h3 className="text-sm font-semibold text-gray-700 mb-1">
                                Top 10 produtos remanejados
                            </h3>
                            <p className="text-xs text-gray-500 mb-3">
                                Por quantidade total solicitada
                            </p>
                            {topProducts.length === 0 ? (
                                <EmptyState compact title="Sem itens" />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-xs">
                                        <thead className="bg-gray-50 uppercase text-gray-600">
                                            <tr>
                                                <th className="px-2 py-2 text-left">Referência</th>
                                                <th className="px-2 py-2 text-left">Produto</th>
                                                <th className="px-2 py-2 text-right">Qtd. total</th>
                                                <th className="px-2 py-2 text-right">Remanejos</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200">
                                            {topProducts.map((p, i) => (
                                                <tr key={i}>
                                                    <td className="px-2 py-2 font-mono">{p.product_reference}</td>
                                                    <td className="px-2 py-2 truncate max-w-[200px]">
                                                        {p.product_name || <span className="text-gray-400">—</span>}
                                                    </td>
                                                    <td className="px-2 py-2 text-right tabular-nums font-semibold">
                                                        {p.total_qty}
                                                    </td>
                                                    <td className="px-2 py-2 text-right tabular-nums text-gray-600">
                                                        {p.relocations_count}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>

                        {/* Distribuição por tipo */}
                        <div className="bg-white rounded-lg shadow-sm p-4">
                            <h3 className="text-sm font-semibold text-gray-700 mb-3">
                                Por tipo de remanejo
                            </h3>
                            {typePieData.length === 0 ? (
                                <EmptyState compact title="Sem dados" />
                            ) : (
                                <ResponsiveContainer width="100%" height={300}>
                                    <PieChart>
                                        <Pie
                                            data={typePieData}
                                            dataKey="value"
                                            nameKey="name"
                                            cx="50%"
                                            cy="50%"
                                            outerRadius={100}
                                            label={(entry) => `${entry.name}: ${entry.value}`}
                                            labelLine={false}
                                        >
                                            {typePieData.map((entry, i) => (
                                                <Cell key={i} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                        <Legend wrapperStyle={{ fontSize: 11 }} />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </div>
                    </div>

                    {/* Ranking detalhado de lojas origem */}
                    {byOrigin.length > 0 && (
                        <div className="bg-white rounded-lg shadow-sm p-4">
                            <h3 className="text-sm font-semibold text-gray-700 mb-1">
                                Performance da loja origem
                            </h3>
                            <p className="text-xs text-gray-500 mb-3">
                                Aderência (% unidades despachadas), entrega (% remanejos completos),
                                acurácia (% sem divergência de NF) e tempo médio de separação.
                            </p>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-gray-50 text-[11px] uppercase text-gray-600">
                                        <tr>
                                            <th className="px-3 py-2 text-left w-10">#</th>
                                            <th className="px-3 py-2 text-left">Loja</th>
                                            <th className="px-3 py-2 text-right">Total</th>
                                            <th className="px-3 py-2 text-right">Entregues</th>
                                            <th className="px-3 py-2 text-right">Aderência</th>
                                            <th className="px-3 py-2 text-right">Acurácia despacho</th>
                                            <th className="px-3 py-2 text-right">Tempo médio sep.</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {[...byOrigin]
                                            .sort((a, b) => (b.adherence ?? 0) - (a.adherence ?? 0))
                                            .map((row, idx) => {
                                                const medal = idx === 0 ? '🥇' : idx === 1 ? '🥈' : idx === 2 ? '🥉' : '';
                                                return (
                                                    <tr key={row.store_code} className={idx < 3 ? 'bg-amber-50/30' : ''}>
                                                        <td className="px-3 py-2 text-gray-500">
                                                            {medal || `${idx + 1}º`}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <div className="font-mono text-xs font-semibold">{row.store_code}</div>
                                                            <div className="text-xs text-gray-500">{row.store_name}</div>
                                                        </td>
                                                        <td className="px-3 py-2 text-right tabular-nums text-gray-700">
                                                            {row.relocations_count}
                                                        </td>
                                                        <td className="px-3 py-2 text-right tabular-nums text-gray-700">
                                                            {row.completed_count}
                                                            <span className="text-gray-400 text-[10px] ml-1">
                                                                ({row.delivery_rate ?? 0}%)
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2 text-right tabular-nums">
                                                            <span className="font-bold" style={{ color: adherenceColor(row.adherence) }}>
                                                                {row.adherence}%
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2 text-right tabular-nums">
                                                            {row.dispatch_accuracy !== null ? (
                                                                <span className="font-medium" style={{ color: adherenceColor(row.dispatch_accuracy) }}>
                                                                    {row.dispatch_accuracy}%
                                                                </span>
                                                            ) : (
                                                                <span className="text-gray-400">—</span>
                                                            )}
                                                            {row.discrepancy_count > 0 && (
                                                                <div className="text-[10px] text-amber-700">
                                                                    {row.discrepancy_count} divergência(s)
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2 text-right tabular-nums text-gray-700">
                                                            {row.avg_separation_hours !== null
                                                                ? `${row.avg_separation_hours}h`
                                                                : <span className="text-gray-400">—</span>
                                                            }
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Top destinos */}
                    {byDestination.length > 0 && (
                        <div className="bg-white rounded-lg shadow-sm p-4">
                            <h3 className="text-sm font-semibold text-gray-700 mb-3">
                                Top 10 lojas destino (volume)
                            </h3>
                            <ResponsiveContainer width="100%" height={260}>
                                <BarChart data={byDestination} margin={{ bottom: 30 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis
                                        dataKey="store_code"
                                        tick={{ fontSize: 11, fontFamily: 'monospace' }}
                                        angle={-30}
                                        textAnchor="end"
                                    />
                                    <YAxis tick={{ fontSize: 11 }} />
                                    <Tooltip
                                        labelFormatter={(code) => {
                                            const row = byDestination.find((r) => r.store_code === code);
                                            return row ? `${code} — ${row.store_name}` : code;
                                        }}
                                    />
                                    <Bar dataKey="count" fill="#4338ca" radius={[4, 4, 0, 0]} name="Remanejos">
                                        <LabelList dataKey="count" position="top" style={{ fontSize: 10, fill: '#374151' }} />
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
