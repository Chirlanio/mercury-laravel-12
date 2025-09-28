import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ auth, logs = { data: [], links: [] }, filters = {}, actions = [], users = [], stats = {} }) {
    const [showFilters, setShowFilters] = useState(false);

    const getActionBadgeColor = (action) => {
        const colors = {
            create: 'bg-green-100 text-green-800',
            update: 'bg-blue-100 text-blue-800',
            delete: 'bg-red-100 text-red-800',
            login: 'bg-indigo-100 text-indigo-800',
            logout: 'bg-gray-100 text-gray-800',
        };
        return colors[action] || 'bg-gray-100 text-gray-800';
    };

    const formatTimeAgo = (dateString) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) return 'Agora mesmo';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m atrás`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h atrás`;
        if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d atrás`;

        return date.toLocaleDateString('pt-BR');
    };

    const columns = [
        {
            label: 'Data/Hora',
            field: 'created_at',
            sortable: true,
            render: (log) => (
                <div>
                    <div className="text-sm font-medium text-gray-900">
                        {new Date(log.created_at).toLocaleDateString('pt-BR')}
                    </div>
                    <div className="text-xs text-gray-500">
                        {new Date(log.created_at).toLocaleTimeString('pt-BR')}
                    </div>
                    <div className="text-xs text-gray-400">
                        {formatTimeAgo(log.created_at)}
                    </div>
                </div>
            )
        },
        {
            label: 'Usuário',
            field: 'user_id',
            sortable: true,
            render: (log) => (
                log.user ? (
                    <div>
                        <div className="text-sm font-medium text-gray-900">
                            {log.user.name}
                        </div>
                        <div className="text-xs text-gray-500">
                            {log.user.email}
                        </div>
                    </div>
                ) : (
                    <span className="text-sm text-gray-400">Sistema</span>
                )
            )
        },
        {
            label: 'Ação',
            field: 'action',
            sortable: true,
            render: (log) => (
                <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getActionBadgeColor(log.action)}`}>
                    {log.action}
                </span>
            )
        },
        {
            label: 'Descrição',
            field: 'description',
            sortable: true,
            render: (log) => (
                <div className="max-w-md">
                    <div className="text-sm text-gray-900 truncate">
                        {log.description}
                    </div>
                    {log.has_changes && (
                        <div className="text-xs text-indigo-600 mt-1">
                            Com alterações
                        </div>
                    )}
                </div>
            )
        },
        {
            label: 'IP / Método',
            field: 'ip_address',
            sortable: false,
            render: (log) => (
                <div>
                    <div className="text-sm text-gray-900">
                        {log.ip_address || '-'}
                    </div>
                    {log.method && (
                        <div className="text-xs text-gray-500">
                            {log.method}
                        </div>
                    )}
                </div>
            )
        },
        {
            label: 'Ações',
            field: 'actions',
            sortable: false,
            render: (log) => (
                <button
                    onClick={() => router.visit(`/activity-logs/${log.id}`)}
                    className="text-indigo-600 hover:text-indigo-900 text-sm"
                >
                    Ver detalhes
                </button>
            )
        }
    ];

    const clearFilters = () => {
        router.visit('/activity-logs', {
            preserveState: false,
        });
    };

    const applyFilter = (field, value) => {
        const currentUrl = new URL(window.location);
        if (value) {
            currentUrl.searchParams.set(field, value);
        } else {
            currentUrl.searchParams.delete(field);
        }
        currentUrl.searchParams.delete('page'); // Reset para primeira página

        router.visit(currentUrl.toString(), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Logs de Atividade" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header com estatísticas */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center mb-4">
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900">
                                    Logs de Atividade
                                </h2>
                                <p className="mt-1 text-sm text-gray-600">
                                    Histórico de atividades dos usuários no sistema.
                                </p>
                            </div>
                            <button
                                onClick={() => setShowFilters(!showFilters)}
                                className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition-colors"
                            >
                                {showFilters ? 'Ocultar Filtros' : 'Mostrar Filtros'}
                            </button>
                        </div>

                        {/* Estatísticas rápidas */}
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div className="bg-white p-4 rounded-lg shadow-sm border">
                                <div className="text-2xl font-bold text-gray-900">{stats.total_today || 0}</div>
                                <div className="text-sm text-gray-600">Hoje</div>
                            </div>
                            <div className="bg-white p-4 rounded-lg shadow-sm border">
                                <div className="text-2xl font-bold text-gray-900">{stats.total_week || 0}</div>
                                <div className="text-sm text-gray-600">Esta semana</div>
                            </div>
                            <div className="bg-white p-4 rounded-lg shadow-sm border">
                                <div className="text-2xl font-bold text-gray-900">{stats.total_month || 0}</div>
                                <div className="text-sm text-gray-600">Este mês</div>
                            </div>
                            <div className="bg-white p-4 rounded-lg shadow-sm border">
                                <div className="text-2xl font-bold text-gray-900">{stats.unique_users_today || 0}</div>
                                <div className="text-sm text-gray-600">Usuários únicos hoje</div>
                            </div>
                        </div>

                        {/* Filtros avançados */}
                        {showFilters && (
                            <div className="bg-white p-6 rounded-lg shadow-sm border mb-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Ação
                                        </label>
                                        <select
                                            value={filters.action || ''}
                                            onChange={(e) => applyFilter('action', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Todas as ações</option>
                                            {actions.map(action => (
                                                <option key={action} value={action}>
                                                    {action}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Usuário
                                        </label>
                                        <select
                                            value={filters.user_id || ''}
                                            onChange={(e) => applyFilter('user_id', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Todos os usuários</option>
                                            {users.map(user => (
                                                <option key={user.id} value={user.id}>
                                                    {user.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Data inicial
                                        </label>
                                        <input
                                            type="date"
                                            value={filters.start_date || ''}
                                            onChange={(e) => applyFilter('start_date', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Data final
                                        </label>
                                        <input
                                            type="date"
                                            value={filters.end_date || ''}
                                            onChange={(e) => applyFilter('end_date', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                </div>

                                <div className="mt-4 flex space-x-4">
                                    <button
                                        onClick={clearFilters}
                                        className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded transition-colors"
                                    >
                                        Limpar Filtros
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>

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
        </AuthenticatedLayout>
    );
}