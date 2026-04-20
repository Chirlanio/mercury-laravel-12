import RecentActivities from '@/Components/Dashboard/RecentActivities';
import AlertCard from '@/Components/Dashboard/AlertCard';
import TopUsers from '@/Components/Dashboard/TopUsers';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import EmptyState from '@/Components/Shared/EmptyState';
import {
    BarChart,
    Bar,
    Cell,
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';
import { Head, Link } from '@inertiajs/react';

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
import {
    UsersIcon,
    UserPlusIcon,
    ChartBarIcon,
    ClockIcon,
    WifiIcon,
    CurrencyDollarIcon,
    ArrowsRightLeftIcon,
    ClipboardDocumentCheckIcon,
    BanknotesIcon,
    TruckIcon,
} from '@heroicons/react/24/outline';

export default function Dashboard({
    stats = {},
    activityStats = {},
    recentActivities = [],
    userChartData = [],
    activityChartData = [],
    actionDistribution = [],
    topUsers = [],
    alerts = [],
    peakHours = [],
    salesSummary = null,
    salesChartData = [],
    usersOnlineSummary = null,
    transfersSummary = null,
    stockSummary = null,
    paymentsSummary = null,
}) {
    const getCurrentGreeting = () => {
        const hour = new Date().getHours();
        if (hour < 12) return 'Bom dia';
        if (hour < 18) return 'Boa tarde';
        return 'Boa noite';
    };

    const calcVariation = (current, previous) => {
        if (!previous || previous <= 0) return null;
        return ((current - previous) / previous) * 100;
    };

    const previousUsersBase =
        (stats.total_users || 0) - (stats.new_users_this_month || 0);

    const generalCards = [
        {
            label: 'Total de Usuários',
            value: stats.total_users || 0,
            format: 'number',
            icon: UsersIcon,
            color: 'blue',
            variation: calcVariation(stats.total_users, previousUsersBase),
        },
        {
            label: 'Novos Usuários (Hoje)',
            value: stats.new_users_today || 0,
            format: 'number',
            icon: UserPlusIcon,
            color: 'green',
        },
        {
            label: 'Atividades (Hoje)',
            value: activityStats.total_activities_today || 0,
            format: 'number',
            icon: ChartBarIcon,
            color: 'purple',
        },
        {
            label: 'Usuários Online',
            value: usersOnlineSummary?.online_count ?? 0,
            format: 'number',
            icon: WifiIcon,
            color: 'teal',
        },
    ];

    const moduleCards = [
        salesSummary && {
            label: 'Vendas do Mês',
            value: salesSummary.current_month_total || 0,
            format: 'currency',
            icon: CurrencyDollarIcon,
            color: 'green',
            variation: calcVariation(
                salesSummary.current_month_total,
                salesSummary.last_month_total
            ),
        },
        transfersSummary && {
            label: 'Transferências Pendentes',
            value: transfersSummary.pending_count || 0,
            format: 'number',
            icon: ArrowsRightLeftIcon,
            color: 'yellow',
        },
        stockSummary && {
            label: 'Ajustes Pendentes',
            value: stockSummary.pending_count || 0,
            format: 'number',
            icon: ClipboardDocumentCheckIcon,
            color: 'indigo',
        },
        paymentsSummary && {
            label: 'Pagamentos Vencidos',
            value: paymentsSummary.overdue_count || 0,
            format: 'number',
            icon: BanknotesIcon,
            color: 'red',
        },
    ].filter(Boolean);

    return (
        <>
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">
                                Dashboard
                            </h1>
                            <p className="mt-1 text-sm text-gray-600">
                                {getCurrentGreeting()}! Aqui está um resumo do seu sistema.
                            </p>
                        </div>
                        <div className="flex items-center space-x-2 text-sm text-gray-500">
                            <ClockIcon className="h-4 w-4" />
                            <span>Atualizado agora</span>
                        </div>
                    </div>

                    {/* Alertas */}
                    {alerts && alerts.length > 0 && (
                        <div className="mb-6">
                            <AlertCard alerts={alerts} />
                        </div>
                    )}

                    {/* KPIs gerais */}
                    <StatisticsGrid cards={generalCards} cols={4} />

                    {/* KPIs de módulos */}
                    {moduleCards.length > 0 && (
                        <StatisticsGrid cards={moduleCards} cols={4} />
                    )}

                    {/* Gráficos */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        {salesSummary ? (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <div className="flex items-center gap-2 mb-4">
                                    <CurrencyDollarIcon className="h-5 w-5 text-green-600" />
                                    <h2 className="text-lg font-semibold text-gray-900">
                                        Vendas (Últimos 7 dias)
                                    </h2>
                                </div>
                                {salesChartData.length === 0 ? (
                                    <EmptyState title="Sem dados" compact />
                                ) : (
                                    (() => {
                                        const maxValue = Math.max(
                                            ...salesChartData.map((d) => Number(d.value) || 0)
                                        );
                                        return (
                                            <ResponsiveContainer width="100%" height={280}>
                                                <BarChart data={salesChartData}>
                                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                                    <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                                                    <YAxis
                                                        tick={{ fontSize: 11 }}
                                                        tickFormatter={(v) =>
                                                            v >= 1000 ? `${(v / 1000).toFixed(0)}k` : v
                                                        }
                                                    />
                                                    <Tooltip
                                                        formatter={(v) => BRL.format(Number(v || 0))}
                                                    />
                                                    <Bar
                                                        dataKey="value"
                                                        name="Vendas"
                                                        radius={[4, 4, 0, 0]}
                                                    >
                                                        {salesChartData.map((entry, index) => (
                                                            <Cell
                                                                key={index}
                                                                fill={
                                                                    maxValue > 0 &&
                                                                    Number(entry.value) === maxValue
                                                                        ? '#15803d'
                                                                        : '#86efac'
                                                                }
                                                            />
                                                        ))}
                                                    </Bar>
                                                </BarChart>
                                            </ResponsiveContainer>
                                        );
                                    })()
                                )}
                            </div>
                        ) : (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <div className="flex items-center gap-2 mb-4">
                                    <UserPlusIcon className="h-5 w-5 text-blue-600" />
                                    <h2 className="text-lg font-semibold text-gray-900">
                                        Novos Usuários (Últimos 7 dias)
                                    </h2>
                                </div>
                                {userChartData.length === 0 ? (
                                    <EmptyState title="Sem dados" compact />
                                ) : (
                                    <ResponsiveContainer width="100%" height={280}>
                                        <LineChart data={userChartData}>
                                            <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                            <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                                            <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
                                            <Tooltip />
                                            <Line
                                                type="monotone"
                                                dataKey="users"
                                                name="Novos usuários"
                                                stroke="#3b82f6"
                                                strokeWidth={2}
                                                dot={{ r: 3 }}
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                )}
                            </div>
                        )}

                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <div className="flex items-center gap-2 mb-4">
                                <ChartBarIcon className="h-5 w-5 text-indigo-600" />
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Atividades do Sistema (Últimos 7 dias)
                                </h2>
                            </div>
                            {activityChartData.length === 0 ? (
                                <EmptyState title="Sem dados" compact />
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <LineChart data={activityChartData}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                                        <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
                                        <Tooltip />
                                        <Line
                                            type="monotone"
                                            dataKey="activities"
                                            name="Atividades"
                                            stroke="#4338ca"
                                            strokeWidth={2}
                                            dot={{ r: 3 }}
                                        />
                                    </LineChart>
                                </ResponsiveContainer>
                            )}
                        </div>
                    </div>

                    {/* Resumo dos Módulos */}
                    {(transfersSummary || stockSummary || paymentsSummary) && (
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                            {transfersSummary && (
                                <div className="bg-white shadow-sm rounded-lg p-6">
                                    <div className="flex items-center gap-2 mb-4">
                                        <TruckIcon className="h-5 w-5 text-yellow-600" />
                                        <h2 className="text-lg font-semibold text-gray-900">
                                            Transferências
                                        </h2>
                                    </div>
                                    <div className="space-y-3">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Pendentes</span>
                                            <span className="text-sm font-semibold text-yellow-600">
                                                {transfersSummary.pending_count}
                                            </span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Em Rota</span>
                                            <span className="text-sm font-semibold text-blue-600">
                                                {transfersSummary.in_transit_count}
                                            </span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Confirmadas (mês)</span>
                                            <span className="text-sm font-semibold text-green-600">
                                                {transfersSummary.completed_this_month}
                                            </span>
                                        </div>
                                    </div>
                                    <Link
                                        href={route('transfers.index')}
                                        className="mt-4 block text-center text-sm text-indigo-600 hover:text-indigo-800"
                                    >
                                        Ver todas →
                                    </Link>
                                </div>
                            )}

                            {stockSummary && (
                                <div className="bg-white shadow-sm rounded-lg p-6">
                                    <div className="flex items-center gap-2 mb-4">
                                        <ClipboardDocumentCheckIcon className="h-5 w-5 text-indigo-600" />
                                        <h2 className="text-lg font-semibold text-gray-900">
                                            Ajustes de Estoque
                                        </h2>
                                    </div>
                                    <div className="space-y-3">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Pendentes</span>
                                            <span className="text-sm font-semibold text-yellow-600">
                                                {stockSummary.pending_count}
                                            </span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Em Análise</span>
                                            <span className="text-sm font-semibold text-blue-600">
                                                {stockSummary.under_analysis_count}
                                            </span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Ajustados (mês)</span>
                                            <span className="text-sm font-semibold text-green-600">
                                                {stockSummary.adjusted_this_month}
                                            </span>
                                        </div>
                                    </div>
                                    <Link
                                        href={route('stock-adjustments.index')}
                                        className="mt-4 block text-center text-sm text-indigo-600 hover:text-indigo-800"
                                    >
                                        Ver todos →
                                    </Link>
                                </div>
                            )}

                            {paymentsSummary && (
                                <div className="bg-white shadow-sm rounded-lg p-6">
                                    <div className="flex items-center gap-2 mb-4">
                                        <BanknotesIcon className="h-5 w-5 text-red-600" />
                                        <h2 className="text-lg font-semibold text-gray-900">
                                            Ordens de Pagamento
                                        </h2>
                                    </div>
                                    <div className="space-y-3">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Solicitação</span>
                                            <span className="text-sm font-semibold text-gray-700">
                                                {paymentsSummary.by_status?.backlog?.count ?? 0}
                                            </span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Reg. Fiscal</span>
                                            <span className="text-sm font-semibold text-blue-600">
                                                {paymentsSummary.by_status?.doing?.count ?? 0}
                                            </span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Lançado</span>
                                            <span className="text-sm font-semibold text-yellow-600">
                                                {paymentsSummary.by_status?.waiting?.count ?? 0}
                                            </span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600">Pago</span>
                                            <span className="text-sm font-semibold text-green-600">
                                                {paymentsSummary.by_status?.done?.count ?? 0}
                                            </span>
                                        </div>
                                        {paymentsSummary.overdue_count > 0 && (
                                            <div className="flex justify-between items-center pt-2 border-t">
                                                <span className="text-sm text-red-600 font-medium">Vencidos</span>
                                                <span className="text-sm font-semibold text-red-600">
                                                    {paymentsSummary.overdue_count}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                    <Link
                                        href={route('order-payments.index')}
                                        className="mt-4 block text-center text-sm text-indigo-600 hover:text-indigo-800"
                                    >
                                        Ver todas →
                                    </Link>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Distribuição de Ações e Picos de Atividade */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <div className="flex items-center gap-2 mb-4">
                                <ChartBarIcon className="h-5 w-5 text-blue-600" />
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Tipos de Ação (30 dias)
                                </h2>
                            </div>
                            {actionDistribution && actionDistribution.length > 0 ? (
                                <div className="space-y-3">
                                    {actionDistribution.slice(0, 5).map((action) => {
                                        const maxCount = actionDistribution[0]?.count || 1;
                                        const percentage = (action.count / maxCount) * 100;

                                        return (
                                            <div key={action.action} className="flex items-center justify-between">
                                                <div className="flex items-center space-x-3">
                                                    <div className="w-3 h-3 bg-blue-500 rounded-full" />
                                                    <span className="text-sm font-medium text-gray-900">
                                                        {action.label}
                                                    </span>
                                                </div>
                                                <div className="flex items-center space-x-3">
                                                    <div className="w-24 bg-gray-200 rounded-full h-2">
                                                        <div
                                                            className="bg-blue-500 h-2 rounded-full transition-all duration-300"
                                                            style={{ width: `${percentage}%` }}
                                                        />
                                                    </div>
                                                    <span className="text-sm text-gray-600 w-8 text-right">
                                                        {action.count}
                                                    </span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <EmptyState title="Nenhum dado disponível" compact />
                            )}
                        </div>

                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <div className="flex items-center gap-2 mb-4">
                                <ClockIcon className="h-5 w-5 text-orange-600" />
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Horários de Pico (7 dias)
                                </h2>
                            </div>
                            {peakHours && peakHours.length > 0 ? (
                                <div className="space-y-4">
                                    {peakHours.map((peak, index) => (
                                        <div key={peak.hour} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <div className="flex items-center space-x-3">
                                                <div className={`w-2 h-2 rounded-full ${
                                                    index === 0 ? 'bg-yellow-500' :
                                                    index === 1 ? 'bg-blue-500' :
                                                    'bg-green-500'
                                                }`} />
                                                <span className="text-sm font-medium text-gray-900">
                                                    {peak.hour}
                                                </span>
                                            </div>
                                            <span className="text-sm text-gray-600 font-semibold">
                                                {peak.count} atividades
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <EmptyState title="Dados insuficientes" compact />
                            )}
                        </div>
                    </div>

                    {/* Seção inferior: Atividades Recentes e Usuários Mais Ativos */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <RecentActivities activities={recentActivities} />
                        <TopUsers users={topUsers} />
                    </div>

                    {/* Informações adicionais */}
                    <StatisticsGrid
                        cols={3}
                        cards={[
                            {
                                label: 'Novos usuários esta semana',
                                value: stats.new_users_this_week || 0,
                                format: 'number',
                                icon: UserPlusIcon,
                                color: 'blue',
                            },
                            {
                                label: 'Atividades esta semana',
                                value: activityStats.total_activities_week || 0,
                                format: 'number',
                                icon: ChartBarIcon,
                                color: 'purple',
                            },
                            {
                                label: 'Novos usuários este mês',
                                value: stats.new_users_this_month || 0,
                                format: 'number',
                                icon: UsersIcon,
                                color: 'indigo',
                            },
                        ]}
                    />
                </div>
            </div>
        </>
    );
}
