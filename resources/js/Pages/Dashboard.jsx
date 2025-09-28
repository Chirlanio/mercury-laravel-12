import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatCard from '@/Components/Dashboard/StatCard';
import SimpleChart from '@/Components/Dashboard/SimpleChart';
import RecentActivities from '@/Components/Dashboard/RecentActivities';
import AlertCard from '@/Components/Dashboard/AlertCard';
import TopUsers from '@/Components/Dashboard/TopUsers';
import { Head } from '@inertiajs/react';
import {
    UsersIcon,
    UserPlusIcon,
    ChartBarIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon
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
    peakHours = []
}) {
    const getCurrentGreeting = () => {
        const hour = new Date().getHours();
        if (hour < 12) return 'Bom dia';
        if (hour < 18) return 'Boa tarde';
        return 'Boa noite';
    };

    return (
        <AuthenticatedLayout
            header={
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
            }
        >
            <Head title="Dashboard" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Alertas */}
                    {alerts && alerts.length > 0 && (
                        <div className="mb-6">
                            <AlertCard alerts={alerts} />
                        </div>
                    )}

                    {/* Cards de Estatísticas */}
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
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
                            title="Usuários Ativos (Hoje)"
                            value={activityStats.unique_active_users_today}
                            icon={CheckCircleIcon}
                            color="indigo"
                        />
                    </div>

                    {/* Gráficos */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <SimpleChart
                            data={userChartData}
                            title="Novos Usuários (Últimos 7 dias)"
                            type="line"
                            color="blue"
                        />
                        <SimpleChart
                            data={activityChartData}
                            title="Atividades do Sistema (Últimos 7 dias)"
                            type="line"
                            color="green"
                        />
                    </div>

                    {/* Distribuição de Ações e Picos de Atividade */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <div className="bg-white shadow rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Tipos de Ação (30 dias)
                            </h3>
                            {actionDistribution && actionDistribution.length > 0 ? (
                                <div className="space-y-3">
                                    {actionDistribution.slice(0, 5).map((action, index) => {
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
        </AuthenticatedLayout>
    );
}
