import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { WifiIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import { useState } from 'react';

export default function Index({ sessions, stores = [], filters = {}, onlineCount = 0 }) {
    const [search, setSearch] = useState(filters.search || '');
    const [storeId, setStoreId] = useState(filters.store_id || '');

    const applyFilters = () => {
        router.get(route('user-sessions.index'), {
            search: search || undefined,
            store_id: storeId || undefined,
        }, { preserveState: true });
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') applyFilters();
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Usuários Online
                    </h2>
                    <div className="flex items-center space-x-2 bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                        <WifiIcon className="h-4 w-4" />
                        <span>{onlineCount} online</span>
                    </div>
                </div>
            }
        >
            <Head title="Usuários Online" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Filtros */}
                    <div className="bg-white shadow rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div className="relative">
                                <MagnifyingGlassIcon className="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Buscar por nome ou email..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={handleKeyDown}
                                    className="pl-10 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <select
                                value={storeId}
                                onChange={(e) => setStoreId(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Todas as Lojas</option>
                                {stores.map((store) => (
                                    <option key={store.id} value={store.id}>
                                        {store.code} - {store.name}
                                    </option>
                                ))}
                            </select>
                            <button
                                onClick={applyFilters}
                                className="inline-flex justify-center items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                            >
                                Filtrar
                            </button>
                        </div>
                    </div>

                    {/* Tabela */}
                    <div className="bg-white shadow rounded-lg overflow-hidden">
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
                                {sessions.data && sessions.data.length > 0 ? (
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
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    session.idle_status === 'active'
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-yellow-100 text-yellow-800'
                                                }`}>
                                                    <span className={`w-1.5 h-1.5 rounded-full mr-1.5 ${
                                                        session.idle_status === 'active' ? 'bg-green-400' : 'bg-yellow-400'
                                                    }`} />
                                                    {session.idle_status === 'active' ? 'Ativo' : 'Inativo'}
                                                </span>
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
        </AuthenticatedLayout>
    );
}
