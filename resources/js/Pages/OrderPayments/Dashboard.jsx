import { Head, Link } from '@inertiajs/react';
import {
    BarChart, Bar, PieChart, Pie, Cell, LineChart, Line,
    XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';
import {
    ArrowLeftIcon,
    BanknotesIcon,
    ChartBarIcon,
    ClockIcon,
    CheckCircleIcon,
    CurrencyDollarIcon,
    ExclamationTriangleIcon,
    BuildingOfficeIcon,
    UserGroupIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import EmptyState from '@/Components/Shared/EmptyState';

const STATUS_HEX = {
    backlog: '#9ca3af',
    doing: '#3b82f6',
    waiting: '#eab308',
    done: '#22c55e',
};

const PIE_COLORS = [
    '#4338ca', '#0891b2', '#059669', '#d97706', '#dc2626',
    '#7c3aed', '#db2777', '#ea580c', '#65a30d', '#6366f1',
];

const BRL = (v) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v || 0));

const formatTooltipCurrency = (value) => BRL(value);

export default function Dashboard({ statistics = {}, analytics = {} }) {
    const byStatus = statistics.by_status || {};
    const overdue = statistics.overdue || { count: 0, total: 0 };
    const monthlyFlow = statistics.monthly_flow || [];
    const installments = statistics.installments || { overdue: 0, upcoming: 0, paid: 0 };

    const byArea = analytics.by_area || [];
    const bySupplier = analytics.by_supplier || [];
    const monthlyDetailed = analytics.monthly_detailed || [];

    // Pie: distribuição por status (total em R$)
    const statusPieData = Object.entries(byStatus).map(([key, s]) => ({
        name: s.label,
        value: s.total,
        count: s.count,
        fill: STATUS_HEX[key] || '#9ca3af',
    }));

    // Pie: top fornecedores
    const supplierPieData = bySupplier.map((s, i) => ({
        name: s.supplier,
        value: s.total,
        fill: PIE_COLORS[i % PIE_COLORS.length],
    }));

    // Soma total de OPs ativas (não deletadas, qualquer status)
    const totalActive = Object.values(byStatus).reduce((s, st) => s + (st.count || 0), 0);
    const totalAmount = Object.values(byStatus).reduce((s, st) => s + (st.total || 0), 0);

    const cards = [
        {
            label: 'Total de OPs',
            value: totalActive,
            format: 'number',
            icon: BanknotesIcon,
            color: 'indigo',
            sub: BRL(totalAmount),
        },
        {
            label: 'Solicitação',
            value: byStatus.backlog?.count || 0,
            format: 'number',
            icon: ClockIcon,
            color: 'gray',
            sub: BRL(byStatus.backlog?.total || 0),
        },
        {
            label: 'Reg. Fiscal',
            value: byStatus.doing?.count || 0,
            format: 'number',
            icon: ChartBarIcon,
            color: 'blue',
            sub: BRL(byStatus.doing?.total || 0),
        },
        {
            label: 'Lançado',
            value: byStatus.waiting?.count || 0,
            format: 'number',
            icon: CurrencyDollarIcon,
            color: 'yellow',
            sub: BRL(byStatus.waiting?.total || 0),
        },
        {
            label: 'Pago',
            value: byStatus.done?.count || 0,
            format: 'number',
            icon: CheckCircleIcon,
            color: 'green',
            sub: BRL(byStatus.done?.total || 0),
        },
        {
            label: 'Vencidas',
            value: overdue.count || 0,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: overdue.count > 0 ? 'red' : 'gray',
            sub: BRL(overdue.total || 0),
        },
    ];

    return (
        <>
            <Head title="Dashboard de Ordens de Pagamento" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">
                                Dashboard de Ordens de Pagamento
                            </h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Visão analítica do fluxo de pagamentos — por status, área, fornecedor e mês.
                            </p>
                        </div>
                        <Link href={route('order-payments.index')}>
                            <Button variant="secondary" icon={ArrowLeftIcon}>
                                Voltar para Ordens
                            </Button>
                        </Link>
                    </div>

                    <StatisticsGrid cards={cards} cols={6} />

                    {/* Parcelas — card com 3 métricas laterais */}
                    <div className="mt-6 bg-white shadow-sm rounded-lg p-6">
                        <div className="flex items-center gap-2 mb-4">
                            <ClockIcon className="h-5 w-5 text-indigo-600" />
                            <h2 className="text-lg font-semibold text-gray-900">Parcelas</h2>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="bg-red-50 rounded p-4">
                                <p className="text-xs uppercase font-semibold text-red-700">Vencidas</p>
                                <p className="text-2xl font-bold text-red-900 mt-1">{installments.overdue}</p>
                            </div>
                            <div className="bg-yellow-50 rounded p-4">
                                <p className="text-xs uppercase font-semibold text-yellow-700">Próximas (30 dias)</p>
                                <p className="text-2xl font-bold text-yellow-900 mt-1">{installments.upcoming}</p>
                            </div>
                            <div className="bg-green-50 rounded p-4">
                                <p className="text-xs uppercase font-semibold text-green-700">Pagas</p>
                                <p className="text-2xl font-bold text-green-900 mt-1">{installments.paid}</p>
                            </div>
                        </div>
                    </div>

                    {/* Gráfico: status × fornecedores (pizza lado a lado) */}
                    <div className="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <div className="flex items-center gap-2 mb-4">
                                <ChartBarIcon className="h-5 w-5 text-indigo-600" />
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Distribuição por Status (valor)
                                </h2>
                            </div>
                            {statusPieData.every(s => s.value === 0) ? (
                                <EmptyState title="Sem dados" description="Nenhuma OP ativa registrada." compact />
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <PieChart>
                                        <Pie data={statusPieData} dataKey="value" nameKey="name"
                                            cx="50%" cy="50%" outerRadius={95}
                                            label={(entry) => `${entry.name}: ${entry.count}`}>
                                            {statusPieData.map((entry, i) => (
                                                <Cell key={i} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                        <Tooltip formatter={formatTooltipCurrency} />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <div className="flex items-center gap-2 mb-4">
                                <UserGroupIcon className="h-5 w-5 text-indigo-600" />
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Top 10 Fornecedores
                                </h2>
                            </div>
                            {supplierPieData.length === 0 ? (
                                <EmptyState title="Sem dados" description="Nenhum fornecedor registrado em OP." compact />
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <PieChart>
                                        <Pie data={supplierPieData} dataKey="value" nameKey="name"
                                            cx="50%" cy="50%" outerRadius={95}
                                            label={(entry) => BRL(entry.value)}>
                                            {supplierPieData.map((entry, i) => (
                                                <Cell key={i} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                        <Tooltip formatter={formatTooltipCurrency} />
                                        <Legend layout="vertical" align="right" verticalAlign="middle"
                                            wrapperStyle={{ fontSize: 11 }} />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </div>
                    </div>

                    {/* Fluxo mensal — últimos 12 meses */}
                    <div className="mt-6 bg-white shadow-sm rounded-lg p-6">
                        <div className="flex items-center gap-2 mb-4">
                            <ChartBarIcon className="h-5 w-5 text-indigo-600" />
                            <h2 className="text-lg font-semibold text-gray-900">
                                Fluxo Mensal (últimos 12 meses)
                            </h2>
                        </div>
                        {monthlyDetailed.length === 0 ? (
                            <EmptyState title="Sem dados" description="Nenhuma OP no período." compact />
                        ) : (
                            <ResponsiveContainer width="100%" height={320}>
                                <LineChart data={monthlyDetailed} margin={{ top: 10, right: 24, left: 0, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="month" tick={{ fontSize: 11 }} />
                                    <YAxis tick={{ fontSize: 11 }}
                                        tickFormatter={(v) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : v} />
                                    <Tooltip formatter={formatTooltipCurrency} />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Line type="monotone" dataKey="total" name="Total criado" stroke="#6366f1"
                                        strokeWidth={2} dot={{ r: 3 }} />
                                    <Line type="monotone" dataKey="paid" name="Pago" stroke="#22c55e"
                                        strokeWidth={2} dot={{ r: 3 }} />
                                </LineChart>
                            </ResponsiveContainer>
                        )}
                    </div>

                    {/* Por Área — bar chart */}
                    <div className="mt-6 bg-white shadow-sm rounded-lg p-6">
                        <div className="flex items-center gap-2 mb-4">
                            <BuildingOfficeIcon className="h-5 w-5 text-indigo-600" />
                            <h2 className="text-lg font-semibold text-gray-900">
                                Distribuição por Área
                            </h2>
                        </div>
                        {byArea.length === 0 ? (
                            <EmptyState title="Sem dados" description="Nenhuma OP associada a área." compact />
                        ) : (
                            <ResponsiveContainer width="100%" height={280}>
                                <BarChart data={byArea}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="area" tick={{ fontSize: 11 }} />
                                    <YAxis tick={{ fontSize: 11 }}
                                        tickFormatter={(v) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : v} />
                                    <Tooltip formatter={formatTooltipCurrency} />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Bar dataKey="total" name="Valor total" fill="#6366f1" radius={[3, 3, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
