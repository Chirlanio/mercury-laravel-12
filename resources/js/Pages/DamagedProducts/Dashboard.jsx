import { Head } from '@inertiajs/react';
import {
    BarChart, Bar, PieChart, Pie, Cell, LineChart, Line,
    XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';
import {
    ExclamationTriangleIcon,
    ChartBarIcon,
    LinkIcon,
    CheckCircleIcon,
    ClockIcon,
    BuildingStorefrontIcon,
    TagIcon,
    ArrowsRightLeftIcon,
} from '@heroicons/react/24/outline';
import PageHeader from '@/Components/Shared/PageHeader';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import EmptyState from '@/Components/Shared/EmptyState';

// Mapa cor-do-enum → hex (paridade com Reversals/Dashboard).
const STATUS_HEX = {
    gray: '#9ca3af',
    info: '#3b82f6',
    warning: '#eab308',
    success: '#22c55e',
    danger: '#ef4444',
    purple: '#a855f7',
    orange: '#f97316',
};

const PIE_COLORS = [
    '#4338ca', '#0891b2', '#059669', '#d97706', '#dc2626',
    '#7c3aed', '#db2777', '#ea580c', '#65a30d', '#6366f1',
];

const formatNumber = (value) => new Intl.NumberFormat('pt-BR').format(value || 0);

export default function Dashboard({
    statistics = {},
    analytics = {},
    isStoreScoped = false,
    scopedStoreCode = null,
}) {
    const byStatus = analytics.by_status || [];
    const byDamageType = analytics.by_damage_type || [];
    const byStore = analytics.by_store || [];
    const timeline = analytics.timeline || [];
    const performance = analytics.performance || {};

    const statusPieData = byStatus.map((s) => ({
        name: s.label,
        value: s.count,
        fill: STATUS_HEX[s.color] || STATUS_HEX.gray,
    }));

    const damageTypePieData = byDamageType.map((d, i) => ({
        name: d.damage_type,
        value: d.count,
        fill: PIE_COLORS[i % PIE_COLORS.length],
    }));

    const cards = [
        {
            label: 'Total de registros',
            value: statistics.total || 0,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: 'indigo',
        },
        {
            label: 'Em aberto',
            value: statistics.open || 0,
            format: 'number',
            icon: ClockIcon,
            color: 'gray',
        },
        {
            label: 'Com match sugerido',
            value: statistics.matched || 0,
            format: 'number',
            icon: LinkIcon,
            color: 'blue',
        },
        {
            label: 'Resolvidos',
            value: statistics.resolved || 0,
            format: 'number',
            icon: CheckCircleIcon,
            color: 'green',
            sub: `${statistics.resolution_rate || 0}% de resolução`,
        },
        {
            label: 'Transferências geradas',
            value: performance.transfers_generated || 0,
            format: 'number',
            icon: ArrowsRightLeftIcon,
            color: 'teal',
        },
    ];

    return (
        <>
            <Head title="Dashboard de Produtos Avariados" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Dashboard de Produtos Avariados"
                        subtitle="Visão analítica de pares trocados, avarias e matching entre lojas"
                        icon={ChartBarIcon}
                        scopeBadge={isStoreScoped && scopedStoreCode ? `escopo: loja ${scopedStoreCode}` : null}
                        actions={[
                            { type: 'back', label: 'Voltar para listagem', href: route('damaged-products.index') },
                        ]}
                    />

                    <StatisticsGrid cards={cards} cols={5} className="mb-6" />

                    {/* Pizza por status + Pizza por tipo de avaria */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <div className="flex items-center gap-2 mb-4">
                                <ChartBarIcon className="h-5 w-5 text-indigo-600" />
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Distribuição por status
                                </h2>
                            </div>
                            {statusPieData.length === 0 ? (
                                <EmptyState
                                    title="Sem dados"
                                    description="Ainda não há produtos avariados registrados."
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
                                        <Tooltip formatter={formatNumber} />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <div className="flex items-center gap-2 mb-4">
                                <TagIcon className="h-5 w-5 text-indigo-600" />
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Top tipos de avaria
                                </h2>
                            </div>
                            {damageTypePieData.length === 0 ? (
                                <EmptyState
                                    title="Sem dados"
                                    description="Ainda não há registros classificados por tipo de avaria."
                                    compact
                                />
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <PieChart>
                                        <Pie
                                            data={damageTypePieData}
                                            dataKey="value"
                                            nameKey="name"
                                            cx="50%"
                                            cy="50%"
                                            outerRadius={95}
                                            label={(entry) => `${entry.value}`}
                                        >
                                            {damageTypePieData.map((entry, index) => (
                                                <Cell key={index} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                        <Tooltip formatter={formatNumber} />
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

                    {/* Linha temporal — últimos 12 meses (criados vs resolvidos) */}
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
                                description="Ainda não há registros no período."
                                compact
                            />
                        ) : (
                            <ResponsiveContainer width="100%" height={300}>
                                <LineChart data={timeline}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="label" tick={{ fontSize: 11 }} />
                                    <YAxis
                                        tick={{ fontSize: 11 }}
                                        label={{
                                            value: 'Quantidade',
                                            angle: -90,
                                            position: 'insideLeft',
                                            style: { fontSize: 11 },
                                        }}
                                    />
                                    <Tooltip formatter={formatNumber} />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Line
                                        type="monotone"
                                        dataKey="created"
                                        name="Criados"
                                        stroke="#4338ca"
                                        strokeWidth={2}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="resolved"
                                        name="Resolvidos"
                                        stroke="#059669"
                                        strokeWidth={2}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        )}
                    </div>

                    {/* Top 10 lojas — só pra admin (sem scoping) */}
                    {!isStoreScoped && (
                        <div className="bg-white shadow-sm rounded-lg p-6 mb-6">
                            <div className="flex items-center gap-2 mb-4">
                                <BuildingStorefrontIcon className="h-5 w-5 text-indigo-600" />
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Top lojas com mais registros
                                </h2>
                            </div>
                            {byStore.length === 0 ? (
                                <EmptyState
                                    title="Sem dados"
                                    description="Ainda não há registros distribuídos por loja."
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
                                        <Tooltip formatter={formatNumber} />
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

                    {/* Métricas de performance */}
                    <div className="bg-white shadow-sm rounded-lg p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">
                            Métricas de performance
                        </h2>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <MetricCard
                                label="Tempo médio criação → resolução"
                                value={
                                    performance.avg_days_to_resolve > 0
                                        ? `${performance.avg_days_to_resolve} dias`
                                        : '—'
                                }
                                sub={
                                    performance.avg_hours_to_resolve > 0
                                        ? `${performance.avg_hours_to_resolve} h`
                                        : null
                                }
                            />
                            <MetricCard
                                label="Taxa de resolução"
                                value={`${statistics.resolution_rate || 0}%`}
                                sub={`${statistics.resolved || 0} de ${statistics.total || 0} registros`}
                            />
                            <MetricCard
                                label="Score médio dos matches"
                                value={performance.avg_match_score > 0 ? performance.avg_match_score : '—'}
                                sub="entre matches aceitos"
                            />
                            <MetricCard
                                label="Transferências geradas"
                                value={performance.transfers_generated || 0}
                                sub="tipo damage_match"
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
