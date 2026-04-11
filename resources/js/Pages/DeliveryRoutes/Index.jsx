import { Head, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import {
    MapIcon,
    PlusIcon,
    FunnelIcon,
    XMarkIcon,
    PlayIcon,
    PrinterIcon,
    TruckIcon,
    CheckCircleIcon,
    XCircleIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

export default function Index({ routes, filters, statusOptions, drivers, availableDeliveries }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'detail']);
    const canManage = hasPermission(PERMISSIONS.MANAGE_ROUTES);

    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState({
        search: filters?.search || '', driver_id: filters?.driver_id || '',
        status: filters?.status || '', date_from: filters?.date_from || '', date_to: filters?.date_to || '',
    });

    // Create form
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [createData, setCreateData] = useState({ driver_id: '', date_route: '', notes: '', delivery_ids: [] });

    // Detail
    const [detailData, setDetailData] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);

    useEffect(() => {
        if (selected && modals.detail) {
            setDetailLoading(true);
            fetch(route('delivery-routes.show', selected.id), { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => { setDetailData(data.route); setDetailLoading(false); })
                .catch(() => setDetailLoading(false));
        }
    }, [selected, modals.detail]);

    const columns = [
        { field: 'route_number', label: 'Nº Rota', sortable: true },
        { key: 'driver_name', label: 'Motorista', render: (row) => row.driver_name || '-' },
        { key: 'date_route', label: 'Data', render: (row) => row.date_route },
        { key: 'items_count', label: 'Entregas', render: (row) => row.items_count },
        {
            key: 'status', label: 'Status',
            render: (row) => <StatusBadge variant={row.status_color}>{row.status_label}</StatusBadge>,
        },
        {
            key: 'actions', label: 'Ações',
            render: (row) => (
                <ActionButtons onView={() => openModal('detail', row)}>
                    {canManage && row.status === 'pending' && (
                        <ActionButtons.Custom variant="success" icon={PlayIcon} title="Iniciar Rota"
                            onClick={() => handleStartRoute(row.id)} />
                    )}
                    {row.status !== 'cancelled' && (
                        <ActionButtons.Custom variant="outline" icon={PrinterIcon} title="Imprimir Manifesto"
                            onClick={() => window.open(route('delivery-routes.print', row.id), '_blank')} />
                    )}
                </ActionButtons>
            ),
        },
    ];

    const applyFilters = () => {
        router.get(route('delivery-routes.index'), {
            ...Object.fromEntries(Object.entries(localFilters).filter(([_, v]) => v !== '')),
        }, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        setLocalFilters({ search: '', driver_id: '', status: '', date_from: '', date_to: '' });
        router.get(route('delivery-routes.index'), {}, { preserveState: true, preserveScroll: true });
    };

    const handleCreate = (e) => {
        e.preventDefault();
        if (createData.delivery_ids.length === 0) {
            setErrors({ delivery_ids: 'Selecione pelo menos uma entrega.' });
            return;
        }
        setProcessing(true);
        setErrors({});
        router.post(route('delivery-routes.store'), createData, {
            preserveScroll: true,
            onSuccess: () => { setProcessing(false); closeModal('create'); },
            onError: (errs) => { setProcessing(false); setErrors(errs); },
        });
    };

    const toggleDelivery = (id) => {
        setCreateData(prev => ({
            ...prev,
            delivery_ids: prev.delivery_ids.includes(id)
                ? prev.delivery_ids.filter(d => d !== id)
                : [...prev.delivery_ids, id],
        }));
    };

    const handleStartRoute = async (routeId) => {
        const res = await fetch(route('delivery-routes.start', routeId), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        });
        if (res.ok) router.reload();
    };

    const handleCancelRoute = async (routeId) => {
        const res = await fetch(route('delivery-routes.cancel', routeId), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        });
        if (res.ok) { closeModal('detail'); router.reload(); }
    };

    return (
        <>
            <Head title="Rotas de Entrega" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Rotas de Entrega</h1>
                            <p className="mt-1 text-sm text-gray-500">Gestão de rotas e motoristas</p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Button variant="outline" size="sm" icon={FunnelIcon} onClick={() => setShowFilters(!showFilters)}>Filtros</Button>
                            {canManage && (
                                <Button variant="primary" size="sm" icon={PlusIcon} onClick={() => {
                                    setCreateData({ driver_id: '', date_route: '', notes: '', delivery_ids: [] });
                                    setErrors({});
                                    openModal('create');
                                }}>Nova Rota</Button>
                            )}
                        </div>
                    </div>

                    {showFilters && (
                        <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                            <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Busca</label>
                                    <input type="text" placeholder="Nº rota, motorista..."
                                        className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={localFilters.search} onChange={e => setLocalFilters(f => ({ ...f, search: e.target.value }))} />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Motorista</label>
                                    <select className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={localFilters.driver_id} onChange={e => setLocalFilters(f => ({ ...f, driver_id: e.target.value }))}>
                                        <option value="">Todos</option>
                                        {drivers?.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                    <select className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={localFilters.status} onChange={e => setLocalFilters(f => ({ ...f, status: e.target.value }))}>
                                        <option value="">Todos</option>
                                        {Object.entries(statusOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">De</label>
                                    <input type="date" className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={localFilters.date_from} onChange={e => setLocalFilters(f => ({ ...f, date_from: e.target.value }))} />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Até</label>
                                    <input type="date" className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={localFilters.date_to} onChange={e => setLocalFilters(f => ({ ...f, date_to: e.target.value }))} />
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 mt-4">
                                <Button variant="light" size="xs" icon={XMarkIcon} onClick={clearFilters}>Limpar</Button>
                                <Button variant="primary" size="xs" onClick={applyFilters}>Aplicar</Button>
                            </div>
                        </div>
                    )}

                    <DataTable data={routes} columns={columns} emptyMessage="Nenhuma rota encontrada." />
                </div>
            </div>

            {/* Create Route Modal */}
            <StandardModal show={modals.create} onClose={() => closeModal('create')}
                title="Nova Rota" headerColor="bg-indigo-600" headerIcon={<MapIcon className="h-5 w-5" />}
                maxWidth="4xl" onSubmit={handleCreate}
                footer={<StandardModal.Footer onCancel={() => closeModal('create')} onSubmit="submit"
                    submitLabel={`Criar Rota (${createData.delivery_ids.length} entregas)`} processing={processing} />}>
                <StandardModal.Section title="Configuração">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Motorista *" />
                            <select className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={createData.driver_id} onChange={e => setCreateData(p => ({ ...p, driver_id: e.target.value }))}>
                                <option value="">Selecione...</option>
                                {drivers?.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                            </select>
                            <InputError message={errors.driver_id} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Data da Rota *" />
                            <input type="date" className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={createData.date_route} onChange={e => setCreateData(p => ({ ...p, date_route: e.target.value }))} />
                            <InputError message={errors.date_route} className="mt-1" />
                        </div>
                    </div>
                    <div className="mt-4">
                        <InputLabel value="Observações" />
                        <textarea className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            rows={2} value={createData.notes} onChange={e => setCreateData(p => ({ ...p, notes: e.target.value }))} />
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title={`Entregas Disponíveis (${availableDeliveries?.length || 0})`}>
                    {errors.delivery_ids && <p className="text-sm text-red-500 mb-2">{errors.delivery_ids}</p>}
                    {availableDeliveries?.length > 0 ? (
                        <div className="space-y-2 max-h-64 overflow-y-auto">
                            {availableDeliveries.map(d => (
                                <label key={d.id} className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                                    createData.delivery_ids.includes(d.id) ? 'bg-indigo-50 border-indigo-300' : 'bg-white border-gray-200 hover:bg-gray-50'
                                }`}>
                                    <Checkbox checked={createData.delivery_ids.includes(d.id)} onChange={() => toggleDelivery(d.id)} />
                                    <div className="flex-1 min-w-0">
                                        <span className="text-sm font-medium text-gray-900">{d.client_name}</span>
                                        <div className="flex items-center gap-2 text-xs text-gray-500 mt-0.5">
                                            <span>{d.store_name}</span>
                                            {d.address && <span className="truncate max-w-[200px]">· {d.address}</span>}
                                        </div>
                                    </div>
                                    <StatusBadge variant="gray">{d.status_label}</StatusBadge>
                                </label>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-gray-500 text-center py-4">Nenhuma entrega disponível para roteirização.</p>
                    )}
                </StandardModal.Section>
            </StandardModal>

            {/* Detail Modal */}
            {selected && modals.detail && (
                <StandardModal show={modals.detail} onClose={() => { closeModal('detail'); setDetailData(null); }}
                    title={detailData?.route_number || 'Detalhes da Rota'}
                    subtitle={detailData?.driver_name}
                    headerColor="bg-gray-700" headerIcon={<MapIcon className="h-5 w-5" />}
                    headerBadges={detailData ? [{ text: detailData.status_label, className: 'bg-white/20 text-white' }] : []}
                    headerActions={canManage && detailData?.transition_labels && Object.keys(detailData.transition_labels).length > 0 ? (
                        <div className="flex items-center gap-2">
                            {Object.entries(detailData.transition_labels).map(([status, label]) => (
                                status === 'cancelled'
                                    ? <Button key={status} variant="danger" size="xs" onClick={() => handleCancelRoute(selected.id)}>{label}</Button>
                                    : <Button key={status} variant="light" size="xs" onClick={() => handleStartRoute(selected.id)}>{label}</Button>
                            ))}
                        </div>
                    ) : null}
                    maxWidth="4xl" loading={detailLoading}
                    footer={<StandardModal.Footer onCancel={() => { closeModal('detail'); setDetailData(null); }} cancelLabel="Fechar"
                        extraButtons={[
                            <a key="print" href={route('delivery-routes.print', selected.id)} target="_blank">
                                <Button variant="outline" size="sm" icon={PrinterIcon}>Manifesto</Button>
                            </a>,
                        ]} />}>
                    {detailData && (
                        <>
                            <StandardModal.Section title="Informações">
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <StandardModal.Field label="Data" value={detailData.date_route} />
                                    <StandardModal.Field label="Total Entregas" value={detailData.total_items} />
                                    <StandardModal.Field label="Entregues" value={detailData.delivered_count} />
                                    <StandardModal.Field label="Progresso" value={detailData.total_items > 0 ? `${Math.round((detailData.delivered_count / detailData.total_items) * 100)}%` : '0%'} />
                                </div>
                                {detailData.notes && <div className="mt-3 p-3 bg-amber-50 rounded-md text-sm text-amber-800">{detailData.notes}</div>}
                            </StandardModal.Section>

                            <StandardModal.Section title={`Entregas (${detailData.items?.length || 0})`}>
                                <div className="space-y-2 max-h-80 overflow-y-auto">
                                    {detailData.items?.map(item => (
                                        <div key={item.id} className={`flex items-center justify-between p-3 rounded-lg border ${
                                            item.is_delivered ? 'bg-green-50 border-green-200' : 'bg-white border-gray-200'
                                        }`}>
                                            <div className="flex items-center gap-3">
                                                <span className="text-xs font-bold text-gray-400 w-6 text-center">{item.sequence}</span>
                                                {item.is_delivered
                                                    ? <CheckCircleIcon className="w-5 h-5 text-green-500 flex-shrink-0" />
                                                    : <TruckIcon className="w-5 h-5 text-gray-400 flex-shrink-0" />}
                                                <div>
                                                    <span className="text-sm font-medium text-gray-900">{item.client_name}</span>
                                                    <div className="text-xs text-gray-500">{item.address || '-'}</div>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <StatusBadge variant={item.delivery_status === 'delivered' ? 'success' : item.delivery_status === 'returned' ? 'orange' : 'gray'}>
                                                    {item.delivery_status_label}
                                                </StatusBadge>
                                                {item.delivered_at && <div className="text-xs text-gray-500 mt-1">{item.delivered_at}</div>}
                                                {item.received_by && <div className="text-xs text-gray-400">Recebido: {item.received_by}</div>}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </StandardModal.Section>

                            <StandardModal.Section title="Registro">
                                <div className="grid grid-cols-2 gap-4">
                                    <StandardModal.MiniField label="Criado por" value={detailData.created_by || '-'} />
                                    <StandardModal.MiniField label="Data" value={detailData.created_at} />
                                </div>
                            </StandardModal.Section>
                        </>
                    )}
                </StandardModal>
            )}
        </>
    );
}
