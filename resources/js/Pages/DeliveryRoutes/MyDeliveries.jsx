import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    TruckIcon,
    MapIcon,
    CheckCircleIcon,
    ArrowPathIcon,
    MagnifyingGlassIcon,
    ChevronDownIcon,
    ChevronUpIcon,
} from '@heroicons/react/24/outline';
import DataTable from '@/Components/DataTable';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';

export default function MyDeliveries({ stats, routes, driverName, filters }) {
    const [expandedRoute, setExpandedRoute] = useState(null);
    const [search, setSearch] = useState(filters?.search || '');

    const statisticsCards = [
        { label: 'Total Rotas', value: stats?.total_routes ?? 0, icon: MapIcon, color: 'indigo' },
        { label: 'Concluídas', value: stats?.completed_routes ?? 0, icon: CheckCircleIcon, color: 'green' },
        { label: 'Entregas', value: stats?.total_items ?? 0, icon: TruckIcon, color: 'blue' },
        { label: 'Entregues', value: stats?.delivered ?? 0, icon: CheckCircleIcon, color: 'emerald' },
        { label: 'Devolvidas', value: stats?.returned ?? 0, icon: ArrowPathIcon, color: 'orange' },
        { label: 'Taxa de Entrega', value: stats?.delivery_rate ?? 0, format: 'percentage', icon: TruckIcon, color: 'purple' },
    ];

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('my-deliveries.index'), { search }, { preserveState: true });
    };

    return (
        <>
            <Head title="Minhas Entregas" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6">
                        <h1 className="text-2xl font-bold text-gray-900">Minhas Entregas</h1>
                        <p className="mt-1 text-sm text-gray-500">Histórico de rotas e entregas de {driverName}</p>
                    </div>

                    <StatisticsGrid cards={statisticsCards} />

                    {/* Search */}
                    <form onSubmit={handleSearch} className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="flex items-center gap-3">
                            <div className="flex-1 relative">
                                <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input type="text" placeholder="Buscar por número da rota ou cliente..."
                                    className="w-full pl-9 pr-3 py-2 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={search} onChange={e => setSearch(e.target.value)} />
                            </div>
                            <button type="submit"
                                className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                                Buscar
                            </button>
                            {search && (
                                <button type="button" onClick={() => { setSearch(''); router.get(route('my-deliveries.index')); }}
                                    className="px-3 py-2 text-sm text-gray-600 hover:text-gray-800">
                                    Limpar
                                </button>
                            )}
                        </div>
                    </form>

                    {/* Routes list */}
                    <div className="space-y-3">
                        {routes?.data?.length > 0 ? routes.data.map(r => (
                            <div key={r.id} className="bg-white rounded-lg shadow-sm overflow-hidden">
                                {/* Route header */}
                                <button
                                    onClick={() => setExpandedRoute(expandedRoute === r.id ? null : r.id)}
                                    className="w-full flex items-center justify-between p-4 hover:bg-gray-50 transition-colors"
                                >
                                    <div className="flex items-center gap-4">
                                        <div className="text-left">
                                            <span className="text-sm font-semibold text-gray-900">{r.route_number}</span>
                                            <p className="text-xs text-gray-500">{r.date}</p>
                                        </div>
                                        <StatusBadge variant={r.status_color}>{r.status_label}</StatusBadge>
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <div className="text-right">
                                            <span className="text-sm font-medium text-gray-900">{r.delivered}/{r.total_items}</span>
                                            <p className="text-xs text-gray-500">entregas</p>
                                        </div>
                                        {expandedRoute === r.id
                                            ? <ChevronUpIcon className="w-4 h-4 text-gray-400" />
                                            : <ChevronDownIcon className="w-4 h-4 text-gray-400" />}
                                    </div>
                                </button>

                                {/* Route items (expanded) */}
                                {expandedRoute === r.id && r.items?.length > 0 && (
                                    <div className="border-t border-gray-100 px-4 pb-4">
                                        <div className="divide-y divide-gray-100">
                                            {r.items.map((item, i) => (
                                                <div key={i} className="flex items-center justify-between py-3">
                                                    <div>
                                                        <span className="text-sm font-medium text-gray-900">{item.client_name}</span>
                                                        {item.address && <p className="text-xs text-gray-500">{item.address}</p>}
                                                        {item.received_by && <p className="text-xs text-gray-400">Recebido: {item.received_by}</p>}
                                                    </div>
                                                    <div className="text-right">
                                                        <StatusBadge variant={
                                                            item.delivery_status === 'delivered' ? 'success' :
                                                            item.delivery_status === 'returned' ? 'orange' : 'gray'
                                                        }>
                                                            {item.delivery_status_label}
                                                        </StatusBadge>
                                                        {item.delivered_at && <p className="text-xs text-gray-400 mt-0.5">{item.delivered_at}</p>}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )) : (
                            <div className="text-center py-12 bg-white rounded-lg shadow-sm">
                                <TruckIcon className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                                <p className="text-gray-500">Nenhuma entrega encontrada.</p>
                            </div>
                        )}
                    </div>

                    {/* Pagination */}
                    {routes?.links?.length > 3 && (
                        <div className="flex justify-center gap-1 mt-6">
                            {routes.links.filter(l => l.url).map((link, i) => (
                                <button key={i}
                                    onClick={() => router.visit(link.url, { preserveState: true })}
                                    className={`px-3 py-2 text-sm rounded-md ${
                                        link.active ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-100'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
