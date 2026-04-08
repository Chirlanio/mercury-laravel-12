import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import Button from '@/Components/Button';
import StatisticsCards from '@/Components/Movements/StatisticsCards';
import SyncModal from '@/Components/Movements/SyncModal';
import ViewModal from '@/Components/Movements/ViewModal';

export default function Index({ auth, movements, stores, movementTypes, filters, cigamAvailable, cigamUnavailableReason }) {
    const { hasPermission } = usePermissions();
    const canSync = hasPermission(PERMISSIONS.SYNC_MOVEMENTS);

    const [isSyncOpen, setIsSyncOpen] = useState(false);
    const [selectedMovement, setSelectedMovement] = useState(null);

    const [localFilters, setLocalFilters] = useState({
        date_start: filters.date_start || '',
        date_end: filters.date_end || '',
        store_code: filters.store_code || '',
        movement_code: filters.movement_code || '',
        search: filters.search || '',
    });

    const applyFilters = () => {
        router.get('/movements', localFilters, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        const today = new Date().toISOString().split('T')[0];
        setLocalFilters({ date_start: today, date_end: today, store_code: '', movement_code: '', search: '' });
        router.get('/movements', {}, { preserveState: true, preserveScroll: true });
    };

    const handleFilterChange = (key, value) => {
        setLocalFilters(prev => ({ ...prev, [key]: value }));
    };

    const fmt = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0);

    return (
        <>
            <Head title="Movimentações Diárias" />

            <div className="py-6">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Movimentações Diárias</h1>
                            <p className="mt-1 text-sm text-gray-600">Dados granulares do CIGAM - fonte de verdade para vendas e estoque</p>
                        </div>
                        {canSync && (
                            <Button variant="primary" onClick={() => setIsSyncOpen(true)}>
                                Sincronizar
                            </Button>
                        )}
                    </div>

                    {/* Statistics */}
                    <StatisticsCards date={localFilters.date_start} />

                    {/* Filters */}
                    <div className="bg-white rounded-lg shadow p-4 mb-6">
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Data Início</label>
                                <input type="date" value={localFilters.date_start}
                                    onChange={(e) => handleFilterChange('date_start', e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Data Fim</label>
                                <input type="date" value={localFilters.date_end}
                                    onChange={(e) => handleFilterChange('date_end', e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Loja</label>
                                <select value={localFilters.store_code}
                                    onChange={(e) => handleFilterChange('store_code', e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Todas</option>
                                    {stores.map(s => <option key={s.code} value={s.code}>{s.code} - {s.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Tipo</label>
                                <select value={localFilters.movement_code}
                                    onChange={(e) => handleFilterChange('movement_code', e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Todos</option>
                                    {movementTypes.map(t => <option key={t.code} value={t.code}>{t.code} - {t.description}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Busca</label>
                                <input type="text" value={localFilters.search}
                                    onChange={(e) => handleFilterChange('search', e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    placeholder="NF, barcode, ref, CPF..."
                                    className="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div className="flex items-end gap-2">
                                <Button variant="primary" size="sm" onClick={applyFilters}>Filtrar</Button>
                                <Button variant="outline" size="sm" onClick={clearFilters}>Limpar</Button>
                            </div>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="bg-white rounded-lg shadow overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                        <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hora</th>
                                        <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loja</th>
                                        <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">NF</th>
                                        <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ref/Tam</th>
                                        <th className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qtde</th>
                                        <th className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Vlr. Realizado</th>
                                        <th className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Vlr. Líquido</th>
                                        <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                        <th className="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">E/S</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {movements.data && movements.data.length > 0 ? movements.data.map((m) => (
                                        <tr key={m.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => setSelectedMovement(m)}>
                                            <td className="px-3 py-2 text-sm text-gray-900 whitespace-nowrap">{m.movement_date}</td>
                                            <td className="px-3 py-2 text-sm text-gray-500 whitespace-nowrap">{m.movement_time}</td>
                                            <td className="px-3 py-2 text-sm text-gray-900 whitespace-nowrap">{m.store_code}</td>
                                            <td className="px-3 py-2 text-sm text-gray-500 whitespace-nowrap">{m.invoice_number || '-'}</td>
                                            <td className="px-3 py-2 text-sm text-gray-500 truncate max-w-[150px]" title={m.ref_size}>{m.ref_size || '-'}</td>
                                            <td className="px-3 py-2 text-sm text-gray-900 text-right whitespace-nowrap">{m.quantity}</td>
                                            <td className="px-3 py-2 text-sm text-gray-900 text-right whitespace-nowrap font-mono">{fmt(m.realized_value)}</td>
                                            <td className={`px-3 py-2 text-sm text-right whitespace-nowrap font-mono font-medium ${m.net_value < 0 ? 'text-red-600' : 'text-gray-900'}`}>
                                                {fmt(m.net_value)}
                                            </td>
                                            <td className="px-3 py-2 text-xs text-gray-500 whitespace-nowrap">{m.movement_type}</td>
                                            <td className="px-3 py-2 text-center">
                                                <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${
                                                    m.entry_exit === 'E' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
                                                }`}>
                                                    {m.entry_exit === 'E' ? 'Entrada' : 'Saída'}
                                                </span>
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={10} className="px-6 py-12 text-center text-gray-500">
                                                {movements.total === 0
                                                    ? 'Nenhuma movimentação encontrada. Execute uma sincronização para importar dados do CIGAM.'
                                                    : 'Nenhum resultado para os filtros selecionados.'
                                                }
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {movements.last_page > 1 && (
                            <div className="px-4 py-3 bg-gray-50 border-t flex items-center justify-between">
                                <p className="text-sm text-gray-500">
                                    Mostrando {movements.from}-{movements.to} de {new Intl.NumberFormat('pt-BR').format(movements.total)} registros
                                </p>
                                <div className="flex gap-1">
                                    {movements.links.filter(l => l.url).map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() => router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                            className={`px-3 py-1 text-sm rounded ${
                                                link.active
                                                    ? 'bg-indigo-600 text-white'
                                                    : 'bg-white text-gray-700 hover:bg-gray-100 border'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Modals */}
            <SyncModal
                isOpen={isSyncOpen}
                onClose={(shouldRefresh) => {
                    setIsSyncOpen(false);
                    if (shouldRefresh) {
                        router.reload({ preserveScroll: true });
                    }
                }}
                cigamAvailable={cigamAvailable}
                cigamUnavailableReason={cigamUnavailableReason}
            />

            <ViewModal
                isOpen={!!selectedMovement}
                onClose={() => setSelectedMovement(null)}
                movement={selectedMovement}
            />
        </>
    );
}
