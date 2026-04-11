import DataTable from '@/Components/DataTable';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    FunnelIcon, XMarkIcon, EyeIcon,
    CalendarDaysIcon, UsersIcon, ClockIcon, ChartBarIcon,
} from '@heroicons/react/24/outline';

const ACTION_VARIANT = {
    create: 'success', update: 'info', delete: 'danger',
    login: 'indigo', logout: 'gray',
};

export default function Index({ logs = { data: [], links: [] }, filters = {}, actions = [], users = [], stats = {} }) {
    const [showFilters, setShowFilters] = useState(false);

    const formatTimeAgo = (dateString) => {
        const diffInSeconds = Math.floor((new Date() - new Date(dateString)) / 1000);
        if (diffInSeconds < 60) return 'Agora mesmo';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m atrás`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h atrás`;
        if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d atrás`;
        return new Date(dateString).toLocaleDateString('pt-BR');
    };

    const applyFilter = (field, value) => {
        const currentUrl = new URL(window.location);
        if (value) currentUrl.searchParams.set(field, value);
        else currentUrl.searchParams.delete(field);
        currentUrl.searchParams.delete('page');
        router.visit(currentUrl.toString(), { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        router.visit('/activity-logs', { preserveState: false });
    };

    const hasActiveFilters = filters.action || filters.user_id || filters.start_date || filters.end_date;

    const statisticsCards = [
        { label: 'Hoje', value: stats.total_today || 0, format: 'number', icon: CalendarDaysIcon, color: 'indigo' },
        { label: 'Esta Semana', value: stats.total_week || 0, format: 'number', icon: ChartBarIcon, color: 'blue' },
        { label: 'Este Mês', value: stats.total_month || 0, format: 'number', icon: ClockIcon, color: 'green' },
        { label: 'Usuários Únicos Hoje', value: stats.unique_users_today || 0, format: 'number', icon: UsersIcon, color: 'purple' },
    ];

    const columns = [
        {
            label: 'Data/Hora', field: 'created_at', sortable: true,
            render: (log) => (
                <div>
                    <div className="text-sm font-medium text-gray-900">{new Date(log.created_at).toLocaleDateString('pt-BR')}</div>
                    <div className="text-xs text-gray-500">{new Date(log.created_at).toLocaleTimeString('pt-BR')}</div>
                    <div className="text-xs text-gray-400">{formatTimeAgo(log.created_at)}</div>
                </div>
            ),
        },
        {
            label: 'Usuário', field: 'user_id', sortable: true,
            render: (log) => log.user ? (
                <div>
                    <div className="text-sm font-medium text-gray-900">{log.user.name}</div>
                    <div className="text-xs text-gray-500">{log.user.email}</div>
                </div>
            ) : <span className="text-sm text-gray-400">Sistema</span>,
        },
        {
            label: 'Ação', field: 'action', sortable: true,
            render: (log) => (
                <StatusBadge variant={ACTION_VARIANT[log.action] || 'gray'}>{log.action}</StatusBadge>
            ),
        },
        {
            label: 'Descrição', field: 'description', sortable: true,
            render: (log) => (
                <div className="max-w-md">
                    <div className="text-sm text-gray-900 truncate">{log.description}</div>
                    {log.has_changes && <div className="text-xs text-indigo-600 mt-1">Com alterações</div>}
                </div>
            ),
        },
        {
            label: 'IP / Método', field: 'ip_address',
            render: (log) => (
                <div>
                    <div className="text-sm text-gray-900">{log.ip_address || '-'}</div>
                    {log.method && <div className="text-xs text-gray-500">{log.method}</div>}
                </div>
            ),
        },
        {
            label: 'Ações', field: 'actions',
            render: (log) => (
                <Button onClick={(e) => { e.stopPropagation(); router.visit(`/activity-logs/${log.id}`); }}
                    variant="secondary" size="sm" iconOnly icon={EyeIcon} title="Ver detalhes" />
            ),
        },
    ];

    return (
        <>
            <Head title="Logs de Atividade" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">Logs de Atividade</h1>
                                <p className="mt-1 text-sm text-gray-600">Histórico de atividades dos usuários no sistema</p>
                            </div>
                            <Button variant={showFilters ? 'primary' : 'outline'} onClick={() => setShowFilters(!showFilters)} icon={FunnelIcon}>
                                {showFilters ? 'Ocultar Filtros' : 'Filtros'}
                            </Button>
                        </div>
                    </div>

                    {/* Estatísticas */}
                    <StatisticsGrid cards={statisticsCards} cols={4} />

                    {/* Filtros */}
                    {showFilters && (
                        <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Ação</label>
                                    <select value={filters.action || ''} onChange={(e) => applyFilter('action', e.target.value)}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Todas as ações</option>
                                        {actions.map(a => <option key={a} value={a}>{a}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Usuário</label>
                                    <select value={filters.user_id || ''} onChange={(e) => applyFilter('user_id', e.target.value)}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Todos os usuários</option>
                                        {users.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Data Inicial</label>
                                    <input type="date" value={filters.start_date || ''} onChange={(e) => applyFilter('start_date', e.target.value)}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Data Final</label>
                                    <input type="date" value={filters.end_date || ''} onChange={(e) => applyFilter('end_date', e.target.value)}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <Button variant="outline" size="sm" onClick={clearFilters} disabled={!hasActiveFilters} icon={XMarkIcon}>
                                        Limpar Filtros
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* DataTable */}
                    <DataTable
                        data={logs}
                        columns={columns}
                        searchable={true}
                        searchPlaceholder="Buscar logs..."
                        perPageOptions={[25, 50, 100, 200]}
                        emptyMessage="Nenhum log encontrado"
                    />
                </div>
            </div>
        </>
    );
}
