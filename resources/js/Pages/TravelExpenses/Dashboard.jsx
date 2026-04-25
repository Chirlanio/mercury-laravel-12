import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ChartBarIcon,
    PaperAirplaneIcon,
    BanknotesIcon,
    ArrowTrendingUpIcon,
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

const TYPE_COLOR_MAP = {
    orange: '#f97316',
    blue: '#3b82f6',
    purple: '#8b5cf6',
    red: '#ef4444',
    gray: '#6b7280',
    green: '#10b981',
    yellow: '#eab308',
    indigo: '#6366f1',
    pink: '#ec4899',
    teal: '#14b8a6',
};

export default function Dashboard({
    analytics = {},
    period = 12,
    isStoreScoped = false,
    scopedStoreCode = null,
    permissions: serverPerms = {},
}) {
    const [months, setMonths] = useState(period);

    const summary = analytics.summary ?? {};
    const monthly = analytics.monthly ?? [];
    const topDestinations = analytics.top_destinations ?? [];
    const byType = analytics.by_type ?? [];
    const topBeneficiaries = analytics.top_beneficiaries ?? [];

    const handlePeriodChange = (newMonths) => {
        setMonths(newMonths);
        router.get(route('travel-expenses.dashboard'), { months: newMonths }, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const pieData = byType.map((t, idx) => ({
        name: t.name,
        value: t.total_value,
        count: t.count,
        fill: TYPE_COLOR_MAP[t.color] ?? PALETTE[idx % PALETTE.length],
    }));

    const summaryCards = [
        {
            label: 'Total de viagens',
            value: summary.count ?? 0,
            format: 'number',
            icon: PaperAirplaneIcon,
            color: 'indigo',
            sub: summary.period_label,
        },
        {
            label: 'Valor total',
            value: summary.total_value ?? 0,
            format: 'currency',
            icon: BanknotesIcon,
            color: 'teal',
        },
        {
            label: 'Ticket médio',
            value: summary.avg_ticket ?? 0,
            format: 'currency',
            icon: ArrowTrendingUpIcon,
            color: 'success',
        },
    ];

    return (
        <>
            <Head title="Dashboard de Verbas de Viagem" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Dashboard de Verbas de Viagem"
                        subtitle="Análise de gastos com viagens corporativas"
                        icon={ChartBarIcon}
                        scopeBadge={isStoreScoped && scopedStoreCode ? `Escopo: ${scopedStoreCode}` : null}
                        actions={[
                            { type: 'back', label: 'Voltar para listagem', href: route('travel-expenses.index') },
                            {
                                type: 'download',
                                download: route('travel-expenses.export'),
                                visible: serverPerms.export ?? false,
                            },
                        ]}
                    />

                    {/* Filtro de período */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6 flex items-center justify-between gap-3">
                        <div className="text-sm text-gray-600">
                            <strong>Período:</strong> considerando verbas <em>aprovadas</em> e <em>finalizadas</em> com saída nos últimos {months} meses.
                        </div>
                        <div className="flex items-center gap-2">
                            <label htmlFor="months" className="text-xs text-gray-500">Janela</label>
                            <select
                                id="months"
                                value={months}
                                onChange={(e) => handlePeriodChange(Number(e.target.value))}
                                className="text-sm rounded-md border-gray-300"
                            >
                                <option value={3}>3 meses</option>
                                <option value={6}>6 meses</option>
                                <option value={12}>12 meses</option>
                                <option value={24}>24 meses</option>
                            </select>
                        </div>
                    </div>

                    {/* Resumo */}
                    <div className="mb-6">
                        <StatisticsGrid cards={summaryCards} cols={3} />
                    </div>

                    {/* Grid 2x2 de gráficos */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* 1. Gasto mensal */}
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <h2 className="text-sm font-semibold text-gray-700 uppercase mb-3">
                                Gasto mensal (R$)
                            </h2>
                            {monthly.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-12">Sem dados</p>
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <LineChart data={monthly}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis dataKey="month_label" fontSize={11} />
                                        <YAxis fontSize={11} tickFormatter={(v) => `R$ ${(v / 1000).toFixed(0)}k`} />
                                        <Tooltip
                                            formatter={(value, name) => [
                                                name === 'total_value' ? formatCurrency(value) : value,
                                                name === 'total_value' ? 'Total' : 'Quantidade',
                                            ]}
                                        />
                                        <Legend />
                                        <Line
                                            type="monotone"
                                            dataKey="total_value"
                                            name="Total (R$)"
                                            stroke="#4f46e5"
                                            strokeWidth={2}
                                            dot={{ r: 4 }}
                                        />
                                    </LineChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        {/* 2. Distribuição por tipo de despesa */}
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <h2 className="text-sm font-semibold text-gray-700 uppercase mb-3">
                                Despesas por tipo
                            </h2>
                            {pieData.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-12">Sem dados</p>
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <PieChart>
                                        <Pie
                                            data={pieData}
                                            cx="50%"
                                            cy="50%"
                                            outerRadius={90}
                                            label={(e) => e.name}
                                            labelLine={false}
                                            dataKey="value"
                                        >
                                            {pieData.map((entry, idx) => (
                                                <Cell key={idx} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                        <Tooltip formatter={(value) => formatCurrency(value)} />
                                        <Legend />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        {/* 3. Top 10 destinos */}
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <h2 className="text-sm font-semibold text-gray-700 uppercase mb-3">
                                Top 10 destinos (volume)
                            </h2>
                            {topDestinations.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-12">Sem dados</p>
                            ) : (
                                <ResponsiveContainer width="100%" height={320}>
                                    <BarChart data={topDestinations} layout="vertical" margin={{ left: 8, right: 16 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis type="number" fontSize={11} allowDecimals={false} />
                                        <YAxis
                                            type="category"
                                            dataKey="destination"
                                            fontSize={11}
                                            width={140}
                                            interval={0}
                                        />
                                        <Tooltip
                                            formatter={(value, name) => [
                                                name === 'total_value' ? formatCurrency(value) : value,
                                                name === 'total_value' ? 'Total' : 'Viagens',
                                            ]}
                                        />
                                        <Bar dataKey="count" name="Viagens" fill="#6366f1" />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        {/* 4. Top 10 beneficiados */}
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <h2 className="text-sm font-semibold text-gray-700 uppercase mb-3">
                                Top 10 beneficiados (R$)
                            </h2>
                            {topBeneficiaries.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-12">Sem dados</p>
                            ) : (
                                <ResponsiveContainer width="100%" height={320}>
                                    <BarChart data={topBeneficiaries} layout="vertical" margin={{ left: 8, right: 16 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis type="number" fontSize={11} tickFormatter={(v) => `R$ ${(v / 1000).toFixed(0)}k`} />
                                        <YAxis type="category" dataKey="name" fontSize={11} width={150} interval={0} />
                                        <Tooltip formatter={(value) => formatCurrency(value)} />
                                        <Bar dataKey="total_value" name="Total" fill="#10b981" />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
}
