import PageHeader from '@/Components/PageHeader';
import StatCard from '@/Components/Dashboard/StatCard';
import SimpleChart from '@/Components/Dashboard/SimpleChart';
import RecentActivities from '@/Components/Dashboard/RecentActivities';
import AlertCard from '@/Components/Dashboard/AlertCard';
import TopUsers from '@/Components/Dashboard/TopUsers';
import { Head, Link } from '@inertiajs/react';
import {
    UsersIcon,
    UserPlusIcon,
    ChartBarIcon,
    ClockIcon,
    CheckCircleIcon,
    WifiIcon,
    CurrencyDollarIcon,
    ArrowsRightLeftIcon,
    ClipboardDocumentCheckIcon,
    BanknotesIcon,
    TruckIcon,
    ExclamationTriangleIcon,
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

    return (
        <>
            <Head title="Dashboard" />
            <PageHeader>
                <div className="flex justify-between items-center">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Dashboard
                        </h2>
                        <p className="text-sm text-gray-600 mt-1">
                            {getCurrentGreeting()}! Aqui está um resumo do seu sistema.
                        </p>
                    </div>
                    <div className="flex items-center space-x-2 text-sm text-gray-500">
                        <ClockIcon className="h-4 w-4" />
                        <span>Atualizado agora</span>
                    </div>
                </div>
            </PageHeader>

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Alertas */}
                    {alerts && alerts.length > 0 && (
                        <div className="mb-6">
                            <AlertCard alerts={alerts} />
                        </div>
                    )}

                    {/* Cards de Estatísticas - Linha 1: Gerais */}
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-5">
                        <StatCard
                            title="Total de Usuários"
                            value={stats.total_users}
                            previousValue={stats.total_users - stats.new_users_this_month}
                            icon={UsersIcon}
                            color="blue"
                        />
                        <StatCard
                            title="Novos Usuários (Hoje)"
                            value={stats.new_users_today}
                            icon={UserPlusIcon}
                            color="green"
                        />
                        <StatCard
                            title="Atividades (Hoje)"
                            value={activityStats.total_activities_today}
                            icon={ChartBarIcon}
                            color="purple"
                        />
                        <StatCard
                            title="Usuários Online"
                            value={usersOnlineSummary?.online_count ?? 0}
                            icon={WifiIcon}
                            color="green"
                        />
                    </div>

                    {/* Cards de Estatísticas - Linha 2: Módulos */}
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                        {salesSummary && (
                            <StatCard
                                title="Vendas do Mês"
                                value={salesSummary.current_month_total}
                                previousValue={salesSummary.last_month_total}
                                icon={CurrencyDollarIcon}
                                color="green"
                                format="currency"
                            />
                        )}
                        {transfersSummary && (
                            <StatCard
                                title="Transferências Pendentes"
                                value={transfersSummary.pending_count}
                                icon={ArrowsRightLeftIcon}
                                color="yellow"
                            />
                        )}
                        {stockSummary && (
                            <StatCard
                                title="Ajustes Pendentes"
                                value={stockSummary.pending_count}
                                icon={ClipboardDocumentCheckIcon}
                                color="indigo"
                            />
                        )}
                        {paymentsSummary && (
                            <StatCard
                                title="Pagamentos Vencidos"
                                value={paymentsSummary.overdue_count}
                                icon={BanknotesIcon}
                                color="red"
                            />
                        )}
                    </div>

                    {/* Gráficos */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        {salesSummary ? (
                            <SimpleChart
                                data={salesChartData}
                                title="Vendas (Últimos 7 dias)"
                                type="bar"
                                color="green"
                                format="currency"
                            />
                        ) : (
                            <SimpleChart
                                data={userChartData}
                                title="Novos Usuários (Últimos 7 dias)"
                                type="line"
                                color="blue"
                            />
                        )}
                        <SimpleChart
                            data={activityChartData}
                            title="Atividades do Sistema (Últimos 7 dias)"
                            type="line"
                            color="green"
                        />
                    </div>

                    {/* Resumo dos Módulos */}
                    {(transfersSummary || stockSummary || paymentsSummary) && (
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                            {transfersSummary && (
                                <div className="bg-white shadow rounded-lg p-6">
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-lg font-medium text-gray-900">
                                            Transferências
                                        </h3>
                                        <TruckIcon className="h-5 w-5 text-gray-400" />
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
                                <div className="bg-white shadow rounded-lg p-6">
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-lg font-medium text-gray-900">
                                            Ajustes de Estoque
                                        </h3>
                                        <ClipboardDocumentCheckIcon className="h-5 w-5 text-gray-400" />
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
                                <div className="bg-white shadow rounded-lg p-6">
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-lg font-medium text-gray-900">
                                            Ordens de Pagamento
                                        </h3>
                                        <BanknotesIcon className="h-5 w-5 text-gray-400" />
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
                        <div className="bg-white shadow rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Tipos de Ação (30 dias)
                            </h3>
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
                                <p className="text-gray-500 text-center py-4">
                                    Nenhum dado disponível
                                </p>
                            )}
                        </div>

                        <div className="bg-white shadow rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Horários de Pico (7 dias)
                            </h3>
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
                                <p className="text-gray-500 text-center py-4">
                                    Dados insuficientes
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Seção inferior: Atividades Recentes e Usuários Mais Ativos */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <RecentActivities activities={recentActivities} />
                        <TopUsers users={topUsers} />
                    </div>

                    {/* Informações adicionais */}
                    <div className="mt-8 bg-gray-50 rounded-lg p-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                            <div>
                                <h4 className="text-lg font-semibold text-gray-900">
                                    {stats.new_users_this_week}
                                </h4>
                                <p className="text-sm text-gray-600">Novos usuários esta semana</p>
                            </div>
                            <div>
                                <h4 className="text-lg font-semibold text-gray-900">
                                    {activityStats.total_activities_week}
                                </h4>
                                <p className="text-sm text-gray-600">Atividades esta semana</p>
                            </div>
                            <div>
                                <h4 className="text-lg font-semibold text-gray-900">
                                    {stats.new_users_this_month}
                                </h4>
                                <p className="text-sm text-gray-600">Novos usuários este mês</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
