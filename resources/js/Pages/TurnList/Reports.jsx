import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    ChartBarIcon,
    UserGroupIcon,
    ClockIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    BuildingStorefrontIcon,
} from '@heroicons/react/24/outline';
import {
    ResponsiveContainer,
    LineChart, Line,
    BarChart, Bar,
    PieChart, Pie, Cell,
    XAxis, YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
} from 'recharts';
import PageHeader from '@/Components/Shared/PageHeader';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';

const PALETTE = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#3b82f6', '#ec4899', '#14b8a6', '#f97316', '#6b7280'];

const COLOR_HEX = {
    success: '#10b981',
    info:    '#3b82f6',
    warning: '#f59e0b',
    danger:  '#ef4444',
    purple:  '#8b5cf6',
    gray:    '#6b7280',
};

const PERIOD_OPTIONS = [
    { value: 'today', label: 'Hoje' },
    { value: 'week',  label: 'Semana' },
    { value: 'month', label: 'Mês' },
    { value: 'custom', label: 'Custom' },
];

export default function Reports({
    storeCode,
    isStoreScoped,
    stores = [],
    report,
    filters = {},
}) {
    const [period, setPeriod] = useState(filters.period ?? 'month');
    const [fromDate, setFromDate] = useState(filters.from ?? '');
    const [toDate, setToDate] = useState(filters.to ?? '');
    const [selectedStore, setSelectedStore] = useState(storeCode ?? '');

    const apply = (overrides = {}) => {
        router.get(route('turn-list.reports'), {
            period: overrides.period ?? period,
            from: overrides.from ?? fromDate ?? undefined,
            to: overrides.to ?? toDate ?? undefined,
            store: overrides.store ?? selectedStore ?? undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    };

    // ──────────────────────────────────────────────────────
    // Cards
    // ──────────────────────────────────────────────────────
    const summary = report?.summary ?? {};
    const summaryCards = useMemo(() => ([
        {
            label: 'Atendimentos',
            value: summary.total_attendances ?? 0,
            format: 'number',
            icon: UserGroupIcon,
            color: 'indigo',
            sub: `${summary.total_employees ?? 0} consultoras`,
        },
        {
            label: 'Tempo médio',
            value: formatDuration(summary.avg_duration_seconds ?? 0),
            icon: ClockIcon,
            color: 'info',
        },
        {
            label: '% Conversão',
            value: (summary.conversion_rate ?? 0).toString().replace('.', ',') + '%',
            icon: CheckCircleIcon,
            color: 'success',
            sub: `${summary.total_conversions ?? 0} de ${summary.total_attendances ?? 0}`,
        },
        {
            label: 'Pausas excedidas',
            value: (summary.exceeded_breaks_pct ?? 0).toString().replace('.', ',') + '%',
            icon: ExclamationTriangleIcon,
            color: (summary.exceeded_breaks_pct ?? 0) > 20 ? 'danger' : 'warning',
            sub: `${summary.exceeded_breaks ?? 0} de ${summary.total_breaks ?? 0}`,
        },
    ]), [summary]);

    // ──────────────────────────────────────────────────────
    // Pie outcomes
    // ──────────────────────────────────────────────────────
    const pieData = useMemo(() => {
        return (report?.by_outcome ?? []).map((o, idx) => ({
            name: o.name,
            value: o.count,
            fill: COLOR_HEX[o.color] ?? PALETTE[idx % PALETTE.length],
            is_conversion: o.is_conversion,
        }));
    }, [report]);

    // ──────────────────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────────────────
    return (
        <>
            <Head title="Relatórios — Lista da Vez" />

            <div className="py-6 sm:py-12">
                <div className="max-w-full mx-auto px-3 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Relatórios — Lista da Vez"
                        subtitle="Conversão, top consultoras, distribuição por outcome e pausas"
                        icon={ChartBarIcon}
                        scopeBadge={isStoreScoped && storeCode ? `Escopo: ${storeCode}` : null}
                        actions={[
                            { type: 'back', label: 'Voltar', href: route('turn-list.index') },
                        ]}
                    />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Período</label>
                                <select
                                    value={period}
                                    onChange={(e) => { const v = e.target.value; setPeriod(v); apply({ period: v }); }}
                                    className="w-full text-sm rounded-md border-gray-300"
                                >
                                    {PERIOD_OPTIONS.map((p) => (
                                        <option key={p.value} value={p.value}>{p.label}</option>
                                    ))}
                                </select>
                            </div>
                            {period === 'custom' && (
                                <>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1">De</label>
                                        <input
                                            type="date"
                                            value={fromDate}
                                            onChange={(e) => { const v = e.target.value; setFromDate(v); apply({ from: v }); }}
                                            className="w-full text-sm rounded-md border-gray-300"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1">Até</label>
                                        <input
                                            type="date"
                                            value={toDate}
                                            onChange={(e) => { const v = e.target.value; setToDate(v); apply({ to: v }); }}
                                            className="w-full text-sm rounded-md border-gray-300"
                                        />
                                    </div>
                                </>
                            )}
                            {!isStoreScoped && stores.length > 1 && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">
                                        <BuildingStorefrontIcon className="h-4 w-4 inline mr-1" />
                                        Loja
                                    </label>
                                    <select
                                        value={selectedStore}
                                        onChange={(e) => { const v = e.target.value; setSelectedStore(v); apply({ store: v }); }}
                                        className="w-full text-sm rounded-md border-gray-300"
                                    >
                                        <option value="">Todas</option>
                                        {stores.map((s) => (
                                            <option key={s.code} value={s.code}>{s.code} — {s.name}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                        </div>
                        {report?.period && (
                            <p className="text-xs text-gray-500 mt-3">
                                Janela: {formatDate(report.period.from)} a {formatDate(report.period.to)} ({report.period.label})
                            </p>
                        )}
                    </div>

                    {!report ? (
                        <div className="bg-white shadow-sm rounded-lg p-8 text-center text-gray-400">
                            Selecione uma loja para visualizar os relatórios.
                        </div>
                    ) : (
                        <>
                            <div className="mb-6">
                                <StatisticsGrid cards={summaryCards} cols={4} />
                            </div>

                            <div className="grid grid-cols-1 xl:grid-cols-2 gap-4 sm:gap-6">
                                {/* 1. Atendimentos por dia */}
                                <ChartCard title="Atendimentos por dia">
                                    {(report.by_day?.length ?? 0) === 0 ? (
                                        <Empty />
                                    ) : (
                                        <ResponsiveContainer width="100%" height={280}>
                                            <LineChart data={report.by_day}>
                                                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                                <XAxis dataKey="day_label" fontSize={11} />
                                                <YAxis fontSize={11} allowDecimals={false} />
                                                <Tooltip />
                                                <Legend />
                                                <Line
                                                    type="monotone"
                                                    dataKey="attendances"
                                                    name="Atendimentos"
                                                    stroke="#4f46e5"
                                                    strokeWidth={2}
                                                    dot={{ r: 3 }}
                                                />
                                            </LineChart>
                                        </ResponsiveContainer>
                                    )}
                                </ChartCard>

                                {/* 2. Distribuição por outcome (pizza) */}
                                <ChartCard title="Distribuição por resultado">
                                    {pieData.length === 0 ? (
                                        <Empty />
                                    ) : (
                                        <ResponsiveContainer width="100%" height={280}>
                                            <PieChart>
                                                <Pie
                                                    data={pieData}
                                                    cx="50%"
                                                    cy="50%"
                                                    outerRadius={90}
                                                    label={(e) => `${e.name} (${e.value})`}
                                                    labelLine={false}
                                                    dataKey="value"
                                                >
                                                    {pieData.map((entry, idx) => (
                                                        <Cell key={idx} fill={entry.fill} />
                                                    ))}
                                                </Pie>
                                                <Tooltip />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    )}
                                </ChartCard>

                                {/* 3. Top 10 consultoras (barra horizontal) */}
                                <ChartCard title="Top 10 consultoras (volume)">
                                    {(report.top_employees?.length ?? 0) === 0 ? (
                                        <Empty />
                                    ) : (
                                        <ResponsiveContainer width="100%" height={320}>
                                            <BarChart data={report.top_employees} layout="vertical" margin={{ left: 8, right: 16 }}>
                                                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                                <XAxis type="number" fontSize={11} allowDecimals={false} />
                                                <YAxis
                                                    type="category"
                                                    dataKey="short_name"
                                                    fontSize={11}
                                                    width={140}
                                                    interval={0}
                                                />
                                                <Tooltip
                                                    formatter={(value, name) => [value, name === 'attendances' ? 'Atendimentos' : name]}
                                                    labelFormatter={(label, payload) => {
                                                        const row = payload?.[0]?.payload;
                                                        return row?.name ?? label;
                                                    }}
                                                />
                                                <Bar dataKey="attendances" name="Atendimentos" fill="#6366f1" />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    )}
                                </ChartCard>

                                {/* 4. Pico de atendimentos por hora */}
                                <ChartCard title="Pico por hora do dia">
                                    {(report.by_hour?.length ?? 0) === 0 ? (
                                        <Empty />
                                    ) : (
                                        <ResponsiveContainer width="100%" height={280}>
                                            <BarChart data={report.by_hour}>
                                                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                                <XAxis dataKey="hour_label" fontSize={11} interval={1} />
                                                <YAxis fontSize={11} allowDecimals={false} />
                                                <Tooltip />
                                                <Bar dataKey="attendances" name="Atendimentos" fill="#10b981" />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    )}
                                </ChartCard>
                            </div>

                            {/* Tabela: detalhes top consultoras */}
                            {(report.top_employees?.length ?? 0) > 0 && (
                                <div className="mt-6 bg-white shadow-sm rounded-lg p-4">
                                    <h3 className="text-sm font-semibold text-gray-700 uppercase mb-3">
                                        Top consultoras — detalhe
                                    </h3>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full text-sm">
                                            <thead className="bg-gray-50 text-gray-600 uppercase text-xs">
                                                <tr>
                                                    <th className="px-3 py-2 text-left">#</th>
                                                    <th className="px-3 py-2 text-left">Consultora</th>
                                                    <th className="px-3 py-2 text-right">Atendimentos</th>
                                                    <th className="px-3 py-2 text-right">Conversões</th>
                                                    <th className="px-3 py-2 text-right">% Conversão</th>
                                                    <th className="px-3 py-2 text-right">Tempo médio</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-200">
                                                {report.top_employees.map((emp, idx) => (
                                                    <tr key={emp.employee_id}>
                                                        <td className="px-3 py-2 text-gray-500">{idx + 1}</td>
                                                        <td className="px-3 py-2 font-medium">{emp.name}</td>
                                                        <td className="px-3 py-2 text-right tabular-nums">{emp.attendances}</td>
                                                        <td className="px-3 py-2 text-right tabular-nums">{emp.conversions}</td>
                                                        <td className="px-3 py-2 text-right tabular-nums">{emp.conversion_rate.toString().replace('.', ',')}%</td>
                                                        <td className="px-3 py-2 text-right tabular-nums">{formatDuration(emp.avg_duration_seconds)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            {/* Pausas */}
                            {(report.break_stats?.length ?? 0) > 0 && (
                                <div className="mt-6 bg-white shadow-sm rounded-lg p-4">
                                    <h3 className="text-sm font-semibold text-gray-700 uppercase mb-3">
                                        Pausas no período
                                    </h3>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        {report.break_stats.map((b) => (
                                            <div key={b.type_id} className="border border-gray-200 rounded-lg p-3">
                                                <div className="font-semibold text-gray-900">{b.type_name}</div>
                                                <div className="text-xs text-gray-500 mb-2">
                                                    Tempo máximo: {b.max_duration_minutes} min
                                                </div>
                                                <div className="grid grid-cols-3 gap-2 text-sm">
                                                    <div>
                                                        <div className="text-xs text-gray-500">Total</div>
                                                        <div className="font-semibold">{b.count}</div>
                                                    </div>
                                                    <div>
                                                        <div className="text-xs text-gray-500">Tempo médio</div>
                                                        <div className="font-semibold">{formatDuration(b.avg_duration_seconds)}</div>
                                                    </div>
                                                    <div>
                                                        <div className="text-xs text-gray-500">Excedidas</div>
                                                        <div className={`font-semibold ${b.exceeded_pct > 20 ? 'text-red-700' : ''}`}>
                                                            {b.exceeded_count} ({b.exceeded_pct.toString().replace('.', ',')}%)
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

// ───────────────────────────────────────────────────────────────────
// Helpers
// ───────────────────────────────────────────────────────────────────
function ChartCard({ title, children }) {
    return (
        <div className="bg-white shadow-sm rounded-lg p-4">
            <h2 className="text-sm font-semibold text-gray-700 uppercase mb-3">{title}</h2>
            {children}
        </div>
    );
}

function Empty() {
    return <p className="text-sm text-gray-400 text-center py-12">Sem dados</p>;
}

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(`${iso}T00:00:00`);
    return d.toLocaleDateString('pt-BR');
}

function formatDuration(seconds) {
    const s = Math.max(0, Math.floor(seconds || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    if (h > 0) return `${h}h ${String(m).padStart(2, '0')}m`;
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
}
