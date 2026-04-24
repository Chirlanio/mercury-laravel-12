import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    ChartBarIcon,
    ArchiveBoxIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    ArrowPathIcon,
    CheckCircleIcon,
    NoSymbolIcon,
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
import Button from '@/Components/Button';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';

const TYPE_COLORS = {
    cliente: '#3b82f6',
    influencer: '#a855f7',
    ecommerce: '#14b8a6',
};

const formatCurrency = (v) => `R$ ${Number(v || 0).toFixed(2).replace('.', ',')}`;

/**
 * Dashboard analítico de Consignações — 4 gráficos responsivos (recharts):
 *  1. Evolução mensal (line) — últimos 12 meses, volume e valor
 *  2. Distribuição por tipo (pie) — Cliente/Influencer/E-commerce
 *  3. Top 10 destinatários (bar horizontal)
 *  4. Taxa de retorno por consultor (bar vertical com % de retorno)
 *
 * Mobile-first: ResponsiveContainer com altura fixa por breakpoint,
 * grid de gráficos colapsa em 1 coluna no mobile.
 */
export default function Dashboard({
    analytics = {},
    statistics = {},
    isStoreScoped,
    scopedStoreId,
}) {
    const byMonth = analytics.by_month || [];
    const byType = analytics.by_type || [];
    const byRecipient = analytics.by_recipient || [];
    const byEmployee = analytics.by_employee || [];

    const pieData = byType.map((t) => ({
        name: t.label,
        value: t.total,
        fill: TYPE_COLORS[t.type] || '#9ca3af',
    }));

    const statsCards = [
        {
            label: 'Total',
            value: statistics.total ?? 0,
            format: 'number',
            icon: ArchiveBoxIcon,
            color: 'gray',
        },
        {
            label: 'Pendentes',
            value: statistics.pending ?? 0,
            format: 'number',
            icon: ClockIcon,
            color: 'info',
        },
        {
            label: 'Parciais',
            value: statistics.partially_returned ?? 0,
            format: 'number',
            icon: ArrowPathIcon,
            color: 'warning',
        },
        {
            label: 'Em atraso',
            value: statistics.overdue ?? 0,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: 'danger',
        },
        {
            label: 'Finalizadas',
            value: statistics.completed ?? 0,
            format: 'number',
            icon: CheckCircleIcon,
            color: 'success',
        },
        {
            label: 'Canceladas',
            value: statistics.cancelled ?? 0,
            format: 'number',
            icon: NoSymbolIcon,
            color: 'gray',
        },
    ];

    return (
        <>
            <Head title="Dashboard de Consignações" />

            <div className="py-6 sm:py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
                        <div>
                            <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 flex items-center gap-2">
                                <ChartBarIcon className="h-7 w-7 text-indigo-600" />
                                Dashboard de Consignações
                            </h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Visão analítica de remessas, taxa de retorno e performance por consultor
                                {isStoreScoped && (
                                    <span className="ml-2 text-xs font-medium text-indigo-600">
                                        (escopo: sua loja)
                                    </span>
                                )}
                            </p>
                        </div>
                        <Link href={route('consignments.index')} className="shrink-0">
                            <Button variant="secondary" icon={ArrowLeftIcon} className="min-h-[44px] w-full sm:w-auto">
                                Voltar à listagem
                            </Button>
                        </Link>
                    </div>

                    {/* KPIs */}
                    <StatisticsGrid cards={statsCards} cols={6} />

                    {/* Gráficos — mobile 1 col, lg 2 cols */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                        {/* 1. Evolução mensal */}
                        <div className="bg-white rounded-lg shadow-sm p-4">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">
                                Evolução mensal (últimos 12 meses)
                            </h3>
                            {byMonth.length === 0 ? (
                                <div className="h-64 flex items-center justify-center text-sm text-gray-500">
                                    Sem dados no período.
                                </div>
                            ) : (
                                <ResponsiveContainer width="100%" height={320}>
                                    <LineChart data={byMonth}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis dataKey="month" tick={{ fontSize: 11 }} />
                                        <YAxis tick={{ fontSize: 11 }} />
                                        <Tooltip
                                            formatter={(value, name) => {
                                                if (name === 'Valor') return [formatCurrency(value), name];
                                                return [value, name];
                                            }}
                                        />
                                        <Legend wrapperStyle={{ fontSize: 12 }} />
                                        <Line type="monotone" dataKey="total" name="Qtd" stroke="#6366f1" strokeWidth={2} dot={{ r: 3 }} />
                                    </LineChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        {/* 2. Distribuição por tipo */}
                        <div className="bg-white rounded-lg shadow-sm p-4">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">
                                Distribuição por tipo
                            </h3>
                            {pieData.length === 0 ? (
                                <div className="h-64 flex items-center justify-center text-sm text-gray-500">
                                    Sem dados.
                                </div>
                            ) : (
                                <ResponsiveContainer width="100%" height={320}>
                                    <PieChart>
                                        <Pie
                                            data={pieData}
                                            dataKey="value"
                                            nameKey="name"
                                            cx="50%"
                                            cy="50%"
                                            outerRadius={100}
                                            label={(entry) => `${entry.name}: ${entry.value}`}
                                            labelLine={false}
                                        >
                                            {pieData.map((entry, idx) => (
                                                <Cell key={idx} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                        <Legend wrapperStyle={{ fontSize: 12 }} />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        {/* 3. Top 10 destinatários */}
                        <div className="bg-white rounded-lg shadow-sm p-4">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">
                                Top 10 destinatários (por volume)
                            </h3>
                            {byRecipient.length === 0 ? (
                                <div className="h-64 flex items-center justify-center text-sm text-gray-500">
                                    Sem dados.
                                </div>
                            ) : (
                                <ResponsiveContainer width="100%" height={320}>
                                    <BarChart
                                        data={byRecipient}
                                        layout="vertical"
                                        margin={{ left: 80 }}
                                    >
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis type="number" tick={{ fontSize: 11 }} />
                                        <YAxis type="category" dataKey="name" tick={{ fontSize: 11 }} width={110} />
                                        <Tooltip />
                                        <Bar dataKey="total" fill="#6366f1" name="Consignações" />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        {/* 4. Performance por consultor — retorno vs conversão em venda */}
                        <div className="bg-white rounded-lg shadow-sm p-4">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">
                                Performance por consultor(a) — retorno × conversão
                            </h3>
                            <p className="text-xs text-gray-500 mb-3">
                                <span className="text-teal-600 font-medium">Taxa de retorno</span>: % do valor consignado que voltou fisicamente.
                                {' '}<span className="text-blue-600 font-medium">Taxa de conversão</span>: % do valor consignado que virou venda.
                                {' '}O restante ainda está pendente ou foi perdido.
                            </p>
                            {byEmployee.length === 0 ? (
                                <div className="h-64 flex items-center justify-center text-sm text-gray-500">
                                    Sem dados.
                                </div>
                            ) : (
                                <ResponsiveContainer width="100%" height={320}>
                                    <BarChart data={byEmployee}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis
                                            dataKey="name"
                                            tick={{ fontSize: 11 }}
                                            angle={-30}
                                            textAnchor="end"
                                            height={80}
                                        />
                                        <YAxis tick={{ fontSize: 11 }} label={{ value: '%', angle: -90, position: 'insideLeft', style: { fontSize: 11 } }} />
                                        <Tooltip
                                            formatter={(value, name) => {
                                                if (name === 'return_rate') return [`${value}%`, 'Retorno'];
                                                if (name === 'conversion_rate') return [`${value}%`, 'Conversão'];
                                                return [value, name];
                                            }}
                                            labelFormatter={(label, payload) => {
                                                const total = payload?.[0]?.payload?.total;
                                                return total ? `${label} (${total} consig.)` : label;
                                            }}
                                        />
                                        <Legend wrapperStyle={{ fontSize: 11 }} formatter={(value) => (
                                            value === 'return_rate' ? 'Retorno' : value === 'conversion_rate' ? 'Conversão' : value
                                        )} />
                                        <Bar dataKey="return_rate" fill="#14b8a6" name="return_rate" />
                                        <Bar dataKey="conversion_rate" fill="#3b82f6" name="conversion_rate" />
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
