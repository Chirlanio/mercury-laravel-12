import { Head, Link } from '@inertiajs/react';
import {
    BarChart, Bar, PieChart, Pie, Cell, LineChart, Line,
    XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';
import {
    ArrowLeftIcon,
    ArrowUturnLeftIcon,
    ChartBarIcon,
    ClockIcon,
    CheckCircleIcon,
    CurrencyDollarIcon,
    BuildingStorefrontIcon,
    TagIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import EmptyState from '@/Components/Shared/EmptyState';

const STATUS_HEX = {
    warning: '#eab308',
    info: '#3b82f6',
    purple: '#a855f7',
    danger: '#ef4444',
    success: '#22c55e',
    orange: '#f97316',
    gray: '#9ca3af',
};

const PIE_COLORS = [
    '#4338ca', '#0891b2', '#059669', '#d97706', '#dc2626',
    '#7c3aed', '#db2777', '#ea580c', '#65a30d', '#6366f1',
];

const BRL = (value) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));

const formatTooltipValue = (value, name) => {
    if (name === 'total_amount' || name === 'Valor') return BRL(value);
    return new Intl.NumberFormat('pt-BR').format(value);
};

export default function Dashboard({
    statistics = {},
    analytics = {},
    isStoreScoped = false,
    scopedStoreCode = null,
}) {
    const byReason = analytics.by_reason || [];
    const byStore = analytics.by_store || [];
    const byStatus = analytics.by_status || [];
    const timeline = analytics.timeline || [];
    const performance = analytics.performance || {};

    const statusPieData = byStatus.map((s) => ({
        name: s.label,
        value: s.count,
        fill: STATUS_HEX[s.color] || STATUS_HEX.gray,
    }));

    const reasonPieData = byReason.map((r, i) => ({
        name: r.reason,
        value: r.count,
        fill: PIE_COLORS[i % PIE_COLORS.length],
    }));

    const cards = [
        {
            label: 'Total (não excluídos)',
            value: statistics.total || 0,
            format: 'number',
            icon: ArrowUturnLeftIcon,
            color: 'indigo',
            sub: BRL(statistics.total_amount || 0),
        },
        {
            label: 'Aguardando aprovação',
            value: statistics.pending_approval || 0,
            format: 'number',
            icon: ClockIcon,
            color: 'yellow',
        },
        {
            label: 'Aguardando financeira',
            value: statistics.pending_finance || 0,
            format: 'number',
            icon: CurrencyDollarIcon,
            color: 'orange',
        },
        {
            label: 'Estornado este mês',
            value: statistics.reversed_this_month_amount || 0,
            format: 'currency',
            icon: CheckCircleIcon,
            color: 'green',
        },
        {
            label: 'Taxa de autorização',
            value: performance.authorization_rate || 0,
            format: 'percentage',
            icon: ChartBarIcon,
            color: 'teal',
            sub:
                performance.avg_days_to_reverse > 0
                    ? `tempo médio: ${performance.avg_days_to_reverse} dia(s)`
                    : null,
        },
    ];

    return (
        <>
            <Head title="Dashboard de Estornos" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">
                                Dashboard de Estornos
                            </h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Visão analítica das solicitações de estorno
                                {isStoreScoped && scopedStoreCode && (
                                    <span className="ml-2 text-xs font-medium text-indigo-600">
                                        (escopo: loja {scopedStoreCode})
                                    </span>
                                )}
                            </p>
                        </div>
                        <Link href={route('reversals.index')}>
                            <Button variant="secondary" icon={ArrowLeftIcon}>
                                Voltar para Estornos
                            </Button>
                        </Link>
                    </div>

                    <StatisticsGrid cards={cards} />

                    {/* Gráfico 1: Status distribution + Motivos (pizza lado a lado) */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <div className="flex items-center gap-2 mb-4">
                                <ChartBarIcon className="h-5 w-5 text-indigo-600" />
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Distribuição por Status
                                </h2>
                            </div>
                            {statusPieData.length === 0 ? (
                                <EmptyState
                                    title="Sem dados"
                                    description="Ainda não há estornos registrados."
                                    compact
                                />
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <PieChart>
                                        <Pie
                                            data={statusPieData}
                                            dataKey="value"
                                            nameKey="name"
                                            cx="50%"
                                            cy="50%"
                                            outerRadius={95}
                                            label={(entry) => `${entry.name}: ${entry.value}`}
                                        >
                                            {statusPieData.map((entry, index) => (
                                                <Cell key={index} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                        <Tooltip formatter={formatTooltipValue} />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <div className="flex items-center gap-2 mb-4">
                                <TagIcon className="h-5 w-5 text-indigo-600" />
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Top Motivos de Estorno
                                </h2>
                            </div>
                            {reasonPieData.length === 0 ? (
                                <EmptyState
                                    title="Sem dados"
                                    description="Ainda não há estornos registrados."
                                    compact
                                />
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <PieChart>
                                        <Pie
                                            data={reasonPieData}
                                            dataKey="value"
                                            nameKey="name"
                                            cx="50%"
                                            cy="50%"
                                            outerRadius={95}
                                            label={(entry) => `${entry.value}`}
                                        >
                                            {reasonPieData.map((entry, index) => (
                                                <Cell key={index} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                        <Tooltip formatter={formatTooltipValue} />
                                        <Legend
                                            layout="vertical"
                                            align="right"
                                            verticalAlign="middle"
                                            wrapperStyle={{ fontSize: 12 }}
                                        />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </div>
                    </div>

                    {/* Gráfico 2: Linha temporal (últimos 12 meses) */}
                    <div className="bg-white shadow-sm rounded-lg p-6 mb-6">
                        <div className="flex items-center gap-2 mb-4">
                            <ChartBarIcon className="h-5 w-5 text-indigo-600" />
                            <h2 className="text-lg font-semibold text-gray-900">
                                Evolução dos últimos 12 meses
                            </h2>
                        </div>
                        {timeline.length === 0 ? (
                            <EmptyState
                                title="Sem dados"
                                description="Ainda não há estornos no período."
                                compact
                            />
                        ) : (
                            <ResponsiveContainer width="100%" height={300}>
                                <LineChart data={timeline}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="label" tick={{ fontSize: 11 }} />
                                    <YAxis
                                        yAxisId="count"
                                        tick={{ fontSize: 11 }}
                                        label={{
                                            value: 'Quantidade',
                                            angle: -90,
                                            position: 'insideLeft',
                                            style: { fontSize: 11 },
                                        }}
                                    />
                                    <YAxis
                                        yAxisId="amount"
                                        orientation="right"
                                        tick={{ fontSize: 11 }}
                                        tickFormatter={(v) =>
                                            v >= 1000 ? `${(v / 1000).toFixed(0)}k` : v
                                        }
                                        label={{
                                            value: 'Valor (R$)',
                                            angle: 90,
                                            position: 'insideRight',
                                            style: { fontSize: 11 },
                                        }}
                                    />
                                    <Tooltip formatter={formatTooltipValue} />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Line
                                        yAxisId="count"
                                        type="monotone"
                                        dataKey="count"
                                        name="Quantidade"
                                        stroke="#4338ca"
                                        strokeWidth={2}
                                    />
                                    <Line
                                        yAxisId="amount"
                                        type="monotone"
                                        dataKey="total_amount"
                                        name="Valor"
                                        stroke="#059669"
                                        strokeWidth={2}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        )}
                    </div>

                    {/* Gráfico 3: Barra por loja */}
                    {!isStoreScoped && (
                        <div className="bg-white shadow-sm rounded-lg p-6 mb-6">
                            <div className="flex items-center gap-2 mb-4">
                                <BuildingStorefrontIcon className="h-5 w-5 text-indigo-600" />
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Top lojas com mais estornos
                                </h2>
                            </div>
                            {byStore.length === 0 ? (
                                <EmptyState
                                    title="Sem dados"
                                    description="Ainda não há estornos por loja."
                                    compact
                                />
                            ) : (
                                <ResponsiveContainer
                                    width="100%"
                                    height={Math.max(300, byStore.length * 32 + 60)}
                                >
                                    <BarChart
                                        data={byStore}
                                        layout="vertical"
                                        margin={{ left: 90 }}
                                    >
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis type="number" tick={{ fontSize: 11 }} />
                                        <YAxis
                                            type="category"
                                            dataKey="store_name"
                                            tick={{ fontSize: 11 }}
                                            width={120}
                                        />
                                        <Tooltip formatter={formatTooltipValue} />
                                        <Bar
                                            dataKey="count"
                                            name="Quantidade"
                                            fill="#4338ca"
                                            radius={[0, 4, 4, 0]}
                                        />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </div>
                    )}

                    {/* Métricas de performance (tempo médio, taxa, etc.) */}
                    <div className="bg-white shadow-sm rounded-lg p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">
                            Métricas de Performance
                        </h2>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <MetricCard
                                label="Tempo médio criação → estorno"
                                value={
                                    performance.avg_days_to_reverse > 0
                                        ? `${performance.avg_days_to_reverse} dias`
                                        : '—'
                                }
                                sub={
                                    performance.avg_hours_to_reverse > 0
                                        ? `${performance.avg_hours_to_reverse} h`
                                        : null
                                }
                            />
                            <MetricCard
                                label="Taxa de autorização"
                                value={`${performance.authorization_rate || 0}%`}
                                sub={`${performance.reversed_count || 0} estornados de ${
                                    performance.processed_count || 0
                                } processados`}
                            />
                            <MetricCard
                                label="Total estornado"
                                value={BRL(statistics.total_amount || 0)}
                                sub={`${statistics.total || 0} solicitações`}
                            />
                            <MetricCard
                                label="Neste mês"
                                value={BRL(statistics.reversed_this_month_amount || 0)}
                                sub="valor estornado"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

function MetricCard({ label, value, sub }) {
    return (
        <div className="bg-gray-50 rounded-lg p-4 border border-gray-100">
            <p className="text-xs text-gray-500 uppercase font-semibold">{label}</p>
            <p className="text-2xl font-bold text-indigo-700 mt-1">{value}</p>
            {sub && <p className="text-xs text-gray-500 mt-1">{sub}</p>}
        </div>
    );
}
