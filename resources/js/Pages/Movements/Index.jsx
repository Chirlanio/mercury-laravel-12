import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsCards from '@/Components/Movements/StatisticsCards';
import SyncModal from '@/Components/Movements/SyncModal';
import SyncLogsModal from '@/Components/Movements/SyncLogsModal';
import ViewModal from '@/Components/Movements/ViewModal';
import {
    ArrowPathIcon, ClockIcon, MagnifyingGlassIcon, XMarkIcon,
} from '@heroicons/react/24/outline';

export default function Index({ movements, stores, movementTypes, filters, cigamAvailable, cigamUnavailableReason }) {
    const { hasPermission } = usePermissions();
    const canSync = hasPermission(PERMISSIONS.SYNC_MOVEMENTS);
    const { modals, selected, openModal, closeModal } = useModalManager(['sync', 'syncLogs', 'view']);

    const [localFilters, setLocalFilters] = useState({
        date_start: filters.date_start || '',
        date_end: filters.date_end || '',
        store_code: filters.store_code || '',
        movement_code: filters.movement_code || '',
        entry_exit: filters.entry_exit || '',
        cpf_consultant: filters.cpf_consultant || '',
        cpf_customer: filters.cpf_customer || '',
        sync_status: filters.sync_status || '',
        search: filters.search || '',
    });

    const applyFilters = () => {
        router.get('/movements', localFilters, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        const today = new Date().toISOString().split('T')[0];
        setLocalFilters({
            date_start: today, date_end: today,
            store_code: '', movement_code: '', entry_exit: '',
            cpf_consultant: '', cpf_customer: '', sync_status: '',
            search: '',
        });
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
                        <div className="flex gap-2">
                            {canSync && (
                                <>
                                    <Button variant="outline" onClick={() => openModal('syncLogs')} icon={ClockIcon}>
                                        Histórico
                                    </Button>
                                    <Button variant="primary" onClick={() => openModal('sync')} icon={ArrowPathIcon}>
                                        Sincronizar
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>

                    {/* Estatísticas */}
                    <StatisticsCards date={localFilters.date_start} />

                    {/* Filtros */}
                    <div className="bg-white rounded-lg shadow-sm p-4 mb-6">
                        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
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
                                <label className="block text-xs text-gray-500 mb-1">Entrada/Saída</label>
                                <select value={localFilters.entry_exit}
                                    onChange={(e) => handleFilterChange('entry_exit', e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Ambos</option>
                                    <option value="E">Entrada</option>
                                    <option value="S">Saída</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">CPF Consultor</label>
                                <input type="text" value={localFilters.cpf_consultant}
                                    onChange={(e) => handleFilterChange('cpf_consultant', e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    placeholder="Somente números"
                                    className="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">CPF Cliente</label>
                                <input type="text" value={localFilters.cpf_customer}
                                    onChange={(e) => handleFilterChange('cpf_customer', e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    placeholder="Somente números"
                                    className="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Status Sync</label>
                                <select value={localFilters.sync_status}
                                    onChange={(e) => handleFilterChange('sync_status', e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Todos</option>
                                    <option value="synced">Sincronizados</option>
                                    <option value="pending">Pendentes</option>
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
                                <Button variant="primary" size="sm" onClick={applyFilters} icon={MagnifyingGlassIcon}>Filtrar</Button>
                                <Button variant="outline" size="sm" onClick={clearFilters} icon={XMarkIcon}>Limpar</Button>
                            </div>
                        </div>
                    </div>

                    {/* Tabela */}
                    <div className="bg-white rounded-lg shadow-sm overflow-hidden">
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
                                    {movements.data?.length > 0 ? movements.data.map((m) => (
                                        <tr key={m.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => openModal('view', m)}>
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
                                                <StatusBadge variant={m.entry_exit === 'E' ? 'emerald' : 'warning'} size="sm">
                                                    {m.entry_exit === 'E' ? 'Entrada' : 'Saída'}
                                                </StatusBadge>
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

                        {/* Paginação */}
                        {movements.last_page > 1 && (
                            <div className="px-4 py-3 bg-gray-50 border-t flex items-center justify-between">
                                <p className="text-sm text-gray-500">
                                    Mostrando {movements.from}-{movements.to} de {new Intl.NumberFormat('pt-BR').format(movements.total)} registros
                                </p>
                                <div className="flex gap-1">
                                    {movements.links.filter(l => l.url).map((link, i) => (
                                        <button key={i}
                                            onClick={() => router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                            className={`px-3 py-1 text-sm rounded ${
                                                link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100 border'
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

            {/* Modais */}
            <SyncModal
                show={modals.sync}
                onClose={(shouldRefresh) => {
                    closeModal('sync');
                    if (shouldRefresh) router.reload({ preserveScroll: true });
                }}
                cigamAvailable={cigamAvailable}
                cigamUnavailableReason={cigamUnavailableReason}
            />

            <SyncLogsModal
                show={modals.syncLogs}
                onClose={() => closeModal('syncLogs')}
            />

            <ViewModal
                show={modals.view && selected !== null}
                onClose={() => closeModal('view')}
                movement={selected}
            />
        </>
    );
}
