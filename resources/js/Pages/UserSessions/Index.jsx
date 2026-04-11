import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import {
    WifiIcon, MagnifyingGlassIcon, XMarkIcon,
} from '@heroicons/react/24/outline';

export default function Index({ sessions, stores = [], filters = {}, onlineCount = 0 }) {
    const [search, setSearch] = useState(filters.search || '');
    const [storeId, setStoreId] = useState(filters.store_id || '');

    const applyFilters = () => {
        router.get(route('user-sessions.index'), {
            search: search || undefined,
            store_id: storeId || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch(''); setStoreId('');
        router.get(route('user-sessions.index'), {}, { preserveState: true });
    };

    const hasActiveFilters = search || storeId;

    return (
        <>
            <Head title="Usuários Online" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">Usuários Online</h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Monitore sessões ativas dos usuários do sistema
                                </p>
                            </div>
                            <StatusBadge variant="success" size="lg" icon={WifiIcon} dot>
                                {onlineCount} online
                            </StatusBadge>
                        </div>
                    </div>

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                            <div className="relative">
                                <MagnifyingGlassIcon className="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                <input type="text" placeholder="Buscar por nome ou email..." value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    className="pl-10 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <select value={storeId} onChange={(e) => setStoreId(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Todas as Lojas</option>
                                {stores.map((s) => <option key={s.id} value={s.id}>{s.code} - {s.name}</option>)}
                            </select>
                            <Button variant="primary" size="sm" onClick={applyFilters} icon={MagnifyingGlassIcon}>
                                Filtrar
                            </Button>
                            <Button variant="outline" size="sm" onClick={clearFilters} disabled={!hasActiveFilters} icon={XMarkIcon}>
                                Limpar
                            </Button>
                        </div>
                    </div>

                    {/* Tabela */}
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuário</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loja</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Página Atual</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Última Atividade</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {sessions.data?.length > 0 ? (
                                    sessions.data.map((session) => (
                                        <tr key={session.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center">
                                                    <div className="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-medium text-sm">
                                                        {session.user?.name?.charAt(0)?.toUpperCase()}
                                                    </div>
                                                    <div className="ml-3">
                                                        <div className="text-sm font-medium text-gray-900">{session.user?.name}</div>
                                                        <div className="text-sm text-gray-500">{session.user?.email}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {session.store?.name || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                                {session.ip_address || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {session.current_page || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {session.last_activity_at}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <StatusBadge
                                                    variant={session.idle_status === 'active' ? 'success' : 'warning'}
                                                    dot
                                                >
                                                    {session.idle_status === 'active' ? 'Ativo' : 'Inativo'}
                                                </StatusBadge>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                                            Nenhum usuário online no momento.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
