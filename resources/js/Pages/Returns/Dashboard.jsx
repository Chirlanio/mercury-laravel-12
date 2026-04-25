import { Head, Link } from '@inertiajs/react';
import {
    BarChart, Bar, PieChart, Pie, Cell, LineChart, Line,
    XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';
import {
    ArrowPathRoundedSquareIcon,
    ChartBarIcon,
    ClockIcon,
    CheckCircleIcon,
    TruckIcon,
    TagIcon,
    ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import PageHeader from '@/Components/Shared/PageHeader';
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
    const byCategory = analytics.by_category || [];
    const byStatus = analytics.by_status || [];
    const byType = analytics.by_type || [];
    const timeline = analytics.timeline || [];
    const performance = analytics.performance || {};

    const statusPieData = byStatus.map((s) => ({
        name: s.label,
        value: s.count,
        fill: STATUS_HEX[s.color] || STATUS_HEX.gray,
    }));

    const categoryPieData = byCategory.map((c, i) => ({
        name: c.label,
        value: c.count,
        fill: PIE_COLORS[i % PIE_COLORS.length],
    }));

    const typeBarData = byType.map((t) => ({
        name: t.label,
        count: t.count,
        fill: STATUS_HEX[t.color] || STATUS_HEX.gray,
    }));

    const cards = [
        {
            label: 'Total (não excluídas)',
            value: statistics.total || 0,
            format: 'number',
            icon: ArrowPathRoundedSquareIcon,
            color: 'indigo',
        },
        {
            label: 'Aguardando aprovação',
            value: statistics.pending_approval || 0,
            format: 'number',
            icon: ClockIcon,
            color: 'yellow',
        },
        {
            label: 'Aguardando produto',
            value: statistics.awaiting_product || 0,
            format: 'number',
            icon: TruckIcon,
            color: 'orange',
        },
        {
            label: 'Em processamento',
            value: statistics.processing || 0,
            format: 'number',
            color: 'purple',
        },
        {
            label: 'Concluídas este mês',
            value: statistics.completed_this_month_amount || 0,
            format: 'currency',
            icon: CheckCircleIcon,
            color: 'green',
        },
        {
            label: 'Taxa de aprovação',
            value: performance.approval_rate || 0,
            format: 'percentage',
            icon: ChartBarIcon,
            color: 'teal',
            sub:
                performance.avg_days_to_complete > 0
                    ? `tempo médio: ${performance.avg_days_to_complete} dia(s)`
                    : null,
        },
    ];

    return (
        <>
            <Head title="Dashboard de Devoluções" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Dashboard de Devoluções"
                        subtitle="Visão analítica das solicitações de troca, estorno e crédito do e-commerce"
                        scopeBadge={isStoreScoped && scopedStoreCode ? `escopo: loja ${scopedStoreCode}` : null}
                        actions={[
                            { type: 'back', label: 'Voltar para Devoluções', href: route('returns.index') },
                        ]}
                    />

                    <StatisticsGrid cards={cards} cols={6} />

                    {/* Status + Categoria */}
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
                                    description="Ainda não há devoluções registradas."
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
                                    Distribuição por Categoria
                                </h2>
                            </div>
                            {categoryPieData.length === 0 ? (
                                <EmptyState
                                    title="Sem dados"
                                    description="Ainda não há devoluções registradas."
                                    compact
                                />
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <PieChart>
                                        <Pie
                                            data={categoryPieData}
                                            dataKey="value"
                                            nameKey="name"
                                            cx="50%"
                                            cy="50%"
                                            outerRadius={95}
                                            label={(entry) => `${entry.value}`}
                                        >
                                            {categoryPieData.map((entry, index) => (
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

                    {/* Timeline 12 meses */}
                    <div className="bg-white shadow-sm rounded-lg p-6 mb-6">
                        <div className="flex items-center gap-2 mb-4">
                            <ChartBarIcon className="h-5 w-5 text-indigo-600" />
                            <h2 className="text-lg font-semibold text-gray-900">
                                Evolução dos últimos 12 meses
                            </h2>
                        </div>
                        {timeline.length === 0 ? (
                            <EmptyState title="Sem dados" compact />
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

                    {/* Tipo (barra) */}
                    <div className="bg-white shadow-sm rounded-lg p-6 mb-6">
                        <div className="flex items-center gap-2 mb-4">
                            <ChartBarIcon className="h-5 w-5 text-indigo-600" />
                            <h2 className="text-lg font-semibold text-gray-900">
                                Distribuição por Tipo
                            </h2>
                        </div>
                        {typeBarData.length === 0 ? (
                            <EmptyState title="Sem dados" compact />
                        ) : (
                            <ResponsiveContainer width="100%" height={250}>
                                <BarChart data={typeBarData}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                                    <YAxis tick={{ fontSize: 11 }} />
                                    <Tooltip formatter={formatTooltipValue} />
                                    <Bar dataKey="count" name="Quantidade" radius={[4, 4, 0, 0]}>
                                        {typeBarData.map((entry, i) => (
                                            <Cell key={i} fill={entry.fill} />
                                        ))}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </div>

                    {/* Performance */}
                    <div className="bg-white shadow-sm rounded-lg p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">
                            Métricas de Performance
                        </h2>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <MetricCard
                                label="Tempo médio criação → conclusão"
                                value={
                                    performance.avg_days_to_complete > 0
                                        ? `${performance.avg_days_to_complete} dias`
                                        : '—'
                                }
                                sub={
                                    performance.avg_hours_to_complete > 0
                                        ? `${performance.avg_hours_to_complete} h`
                                        : null
                                }
                            />
                            <MetricCard
                                label="Taxa de aprovação"
                                value={`${performance.approval_rate || 0}%`}
                                sub={`${performance.completed_count || 0} concluídas de ${
                                    performance.processed_count || 0
                                } processadas`}
                            />
                            <MetricCard
                                label="Canceladas"
                                value={statistics.cancelled || 0}
                            />
                            <MetricCard
                                label="Valor concluído no mês"
                                value={BRL(statistics.completed_this_month_amount || 0)}
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
