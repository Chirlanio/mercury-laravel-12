import { Head, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import {
    TruckIcon,
    PlusIcon,
    FunnelIcon,
    XMarkIcon,
    MapPinIcon,
    PhoneIcon,
    CurrencyDollarIcon,
    ClockIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import { maskMoney, parseMoney, maskPhone } from '@/Hooks/useMasks';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

export default function Index({ deliveries, filters, statusOptions, statusCounts, stores, paymentTypes, neighborhoods, employees }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'detail', 'edit']);
    // maskMoney e parseMoney importados diretamente
    const [stats, setStats] = useState(null);
    const [statsLoading, setStatsLoading] = useState(true);
    const [showFilters, setShowFilters] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [localFilters, setLocalFilters] = useState({
        search: filters?.search || '', status: filters?.status || '',
        store_id: filters?.store_id || '', date_from: filters?.date_from || '', date_to: filters?.date_to || '',
    });

    const canCreate = hasPermission(PERMISSIONS.CREATE_DELIVERIES);
    const canEdit = hasPermission(PERMISSIONS.EDIT_DELIVERIES);
    const canDelete = hasPermission(PERMISSIONS.DELETE_DELIVERIES);

    // Form state
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [formData, setFormData] = useState({
        store_id: '', employee_id: '', client_name: '', invoice_number: '',
        address: '', neighborhood: '', contact_phone: '',
        sale_value: '', payment_method: '', installments: 1,
        products_qty: 1, exit_point: '',
        needs_card_machine: false, is_exchange: false, is_gift: false, observations: '',
    });

    const setField = (field, value) => setFormData(prev => ({ ...prev, [field]: value }));

    useEffect(() => {
        fetch(route('deliveries.statistics'), { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => { setStats(data); setStatsLoading(false); })
            .catch(() => setStatsLoading(false));
    }, []);

    // Load detail data
    const [detailData, setDetailData] = useState(null);
    useEffect(() => {
        if (selected && (modals.detail || modals.edit)) {
            fetch(route('deliveries.show', selected.id), { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    setDetailData(data.delivery);
                    if (modals.edit) {
                        setFormData({
                            store_id: data.delivery.store_id || '',
                            employee_id: data.delivery.employee_id || '',
                            client_name: data.delivery.client_name || '',
                            invoice_number: data.delivery.invoice_number || '',
                            address: data.delivery.address || '',
                            neighborhood: data.delivery.neighborhood || '',
                            contact_phone: data.delivery.contact_phone || '',
                            sale_value: data.delivery.sale_value ? Number(data.delivery.sale_value).toFixed(2).replace('.', ',') : '',
                            payment_method: data.delivery.payment_method || '',
                            installments: data.delivery.installments || 1,
                            products_qty: data.delivery.products_qty || 1,
                            exit_point: data.delivery.exit_point || '',
                            needs_card_machine: data.delivery.needs_card_machine || false,
                            is_exchange: data.delivery.is_exchange || false,
                            is_gift: data.delivery.is_gift || false,
                            observations: data.delivery.observations || '',
                        });
                    }
                });
        }
    }, [selected, modals.detail, modals.edit]);

    const statisticsCards = [
        { label: 'Total', value: stats?.total ?? 0, icon: TruckIcon, color: 'indigo' },
        { label: 'Solicitadas', value: stats?.by_status?.requested?.count ?? 0, icon: ClockIcon, color: 'gray' },
        { label: 'Em Rota', value: stats?.by_status?.in_route?.count ?? 0, icon: MapPinIcon, color: 'purple' },
        { label: 'Entregues', value: stats?.by_status?.delivered?.count ?? 0, icon: TruckIcon, color: 'green' },
    ];

    const columns = [
        { field: 'client_name', label: 'Cliente', sortable: true },
        { key: 'store_name', label: 'Loja', render: (row) => row.store_name || '-' },
        {
            key: 'address', label: 'Endereço',
            render: (row) => (
                <div className="max-w-[200px] truncate text-xs text-gray-500" title={row.address}>
                    {row.address || '-'}
                </div>
            ),
        },
        {
            key: 'sale_value', label: 'Valor',
            render: (row) => row.sale_value ? `R$ ${Number(row.sale_value).toFixed(2).replace('.', ',')}` : '-',
        },
        {
            key: 'status', label: 'Status',
            render: (row) => <StatusBadge variant={row.status_color}>{row.status_label}</StatusBadge>,
        },
        { key: 'created_at', label: 'Data', render: (row) => row.created_at },
        {
            key: 'actions', label: 'Ações',
            render: (row) => (
                <ActionButtons
                    onView={() => openModal('detail', row)}
                    onEdit={canEdit && !['delivered', 'returned', 'cancelled'].includes(row.status) ? () => openModal('edit', row) : undefined}
                    onDelete={canDelete && !['delivered', 'returned', 'cancelled'].includes(row.status) ? () => setDeleteTarget(row) : undefined}
                />
            ),
        },
    ];

    const applyFilters = () => {
        router.get(route('deliveries.index'), {
            ...Object.fromEntries(Object.entries(localFilters).filter(([_, v]) => v !== '')),
        }, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        setLocalFilters({ search: '', status: '', store_id: '', date_from: '', date_to: '' });
        router.get(route('deliveries.index'), {}, { preserveState: true, preserveScroll: true });
    };

    const handleCreate = (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});
        const data = { ...formData, sale_value: formData.sale_value ? parseMoney(formData.sale_value) : null };
        router.post(route('deliveries.store'), data, {
            preserveScroll: true,
            onSuccess: () => { setProcessing(false); closeModal('create'); },
            onError: (errs) => { setProcessing(false); setErrors(errs); },
        });
    };

    const handleUpdate = (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});
        const data = { ...formData, sale_value: formData.sale_value ? parseMoney(formData.sale_value) : null };
        router.put(route('deliveries.update', selected.id), data, {
            preserveScroll: true,
            onSuccess: () => { setProcessing(false); closeModal('edit'); },
            onError: (errs) => { setProcessing(false); setErrors(errs); },
        });
    };

    const handleDelete = () => {
        router.delete(route('deliveries.destroy', deleteTarget.id), {
            onSuccess: () => setDeleteTarget(null),
        });
    };

    const handleTransition = async (deliveryId, newStatus) => {
        await fetch(route('deliveries.status', deliveryId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ status: newStatus }),
        });
        // Reload detail
        const res = await fetch(route('deliveries.show', deliveryId), { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        setDetailData(data.delivery);
    };

    const resetForm = () => {
        setFormData({
            store_id: '', client_name: '', address: '', neighborhood: '', contact_phone: '',
            sale_value: '', payment_method: '', installments: 1,
            needs_card_machine: false, is_exchange: false, is_gift: false, observations: '',
        });
        setErrors({});
    };

    // Form fields JSX (reused in create and edit)
    const formFields = (
        <>
            <StandardModal.Section title="Dados da Entrega">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Loja *" />
                        <select className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={formData.store_id} onChange={e => { setField('store_id', e.target.value); setField('employee_id', ''); }}>
                            <option value="">Selecione...</option>
                            {stores?.map(s => <option key={s.code} value={s.code}>{s.name}</option>)}
                        </select>
                        <InputError message={errors.store_id} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Consultor(a) / Responsável" />
                        <select className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={formData.employee_id} onChange={e => setField('employee_id', e.target.value)}>
                            <option value="">Selecione...</option>
                            {employees?.filter(e => !formData.store_id || e.store_id === formData.store_id)
                                .map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Nota Fiscal *" />
                        <TextInput className="mt-1 block w-full" value={formData.invoice_number}
                            onChange={e => setField('invoice_number', e.target.value)} required />
                        <InputError message={errors.invoice_number} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Ponto de Saída" />
                        <select className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={formData.exit_point} onChange={e => setField('exit_point', e.target.value)}>
                            <option value="">Selecione...</option>
                            <option value="CD">CD (Centro de Distribuição)</option>
                            {stores?.map(s => <option key={s.code} value={s.name}>{s.name}</option>)}
                        </select>
                    </div>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Cliente">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Nome do Cliente *" />
                        <TextInput className="mt-1 block w-full" value={formData.client_name}
                            onChange={e => setField('client_name', e.target.value)} required />
                        <InputError message={errors.client_name} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Telefone" />
                        <TextInput className="mt-1 block w-full" value={formData.contact_phone}
                            onChange={e => setField('contact_phone', maskPhone(e.target.value))} placeholder="(00) 00000-0000" />
                    </div>
                    <div>
                        <InputLabel value="Endereço" />
                        <TextInput className="mt-1 block w-full" value={formData.address}
                            onChange={e => setField('address', e.target.value)} />
                    </div>
                    <div>
                        <InputLabel value="Bairro" />
                        <select className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={formData.neighborhood} onChange={e => setField('neighborhood', e.target.value)}>
                            <option value="">Selecione...</option>
                            {neighborhoods?.map(n => <option key={n.id} value={n.name}>{n.name}</option>)}
                        </select>
                    </div>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Venda">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <InputLabel value="Valor da Venda *" />
                        <TextInput className="mt-1 block w-full" value={formData.sale_value}
                            onChange={e => setField('sale_value', maskMoney(e.target.value))} placeholder="0,00" required />
                        <InputError message={errors.sale_value} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Qtd. Produtos *" />
                        <TextInput type="number" className="mt-1 block w-full" value={formData.products_qty}
                            onChange={e => setField('products_qty', e.target.value)} min={1} required />
                        <InputError message={errors.products_qty} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Forma de Pagamento *" />
                        <select className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required
                            value={formData.payment_method} onChange={e => {
                                setField('payment_method', e.target.value);
                                // Auto-setar parcelas para tipos que não parcelam
                                if (!['Cartão', 'Boleto'].includes(e.target.value)) {
                                    setField('installments', 1);
                                }
                                // Auto-marcar maquininha para Cartão
                                if (e.target.value === 'Cartão') {
                                    setField('needs_card_machine', true);
                                }
                            }}>
                            <option value="">Selecione...</option>
                            {paymentTypes?.map(pt => <option key={pt.id} value={pt.name}>{pt.name}</option>)}
                        </select>
                        <InputError message={errors.payment_method} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Parcelas *" />
                        <TextInput type="number" className="mt-1 block w-full" value={formData.installments}
                            onChange={e => setField('installments', e.target.value)} min={1} required
                            disabled={!['Cartão', 'Boleto'].includes(formData.payment_method)} />
                        <InputError message={errors.installments} className="mt-1" />
                        {!['Cartão', 'Boleto'].includes(formData.payment_method) && formData.payment_method && (
                            <p className="text-xs text-gray-400 mt-1">Parcelamento não disponível para {formData.payment_method}.</p>
                        )}
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-6 mt-4">
                    <label className="flex items-center gap-2">
                        <Checkbox checked={formData.needs_card_machine}
                            onChange={e => setField('needs_card_machine', e.target.checked)} />
                        <span className="text-sm text-gray-700">Precisa de maquininha</span>
                    </label>
                    <label className="flex items-center gap-2">
                        <Checkbox checked={formData.is_exchange}
                            onChange={e => setField('is_exchange', e.target.checked)} />
                        <span className="text-sm text-gray-700">É troca</span>
                    </label>
                    <label className="flex items-center gap-2">
                        <Checkbox checked={formData.is_gift}
                            onChange={e => setField('is_gift', e.target.checked)} />
                        <span className="text-sm text-gray-700">É presente</span>
                    </label>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Observações">
                <textarea className="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows={3} value={formData.observations} onChange={e => setField('observations', e.target.value)}
                    placeholder="Observações sobre a entrega..." maxLength={2000} />
            </StandardModal.Section>
        </>
    );

    return (
        <>
            <Head title="Entregas" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Entregas</h1>
                            <p className="mt-1 text-sm text-gray-500">Gestão de entregas e solicitações</p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Button variant="outline" size="sm" icon={FunnelIcon} onClick={() => setShowFilters(!showFilters)}>
                                Filtros
                            </Button>
                            {canCreate && (
                                <Button variant="primary" size="sm" icon={PlusIcon} onClick={() => { resetForm(); openModal('create'); }}>
                                    <span className="sm:hidden">Nova</span>
                                    <span className="hidden sm:inline">Nova Entrega</span>
                                </Button>
                            )}
                        </div>
                    </div>

                    <StatisticsGrid cards={statisticsCards} loading={statsLoading} />

                    {/* Filtros */}
                    {showFilters && (
                        <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                            <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Busca</label>
                                    <input type="text" placeholder="Cliente, endereço..."
                                        className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={localFilters.search} onChange={e => setLocalFilters(f => ({ ...f, search: e.target.value }))} />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                    <select className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={localFilters.status} onChange={e => setLocalFilters(f => ({ ...f, status: e.target.value }))}>
                                        <option value="">Todos</option>
                                        {Object.entries(statusOptions).map(([k, v]) => (
                                            <option key={k} value={k}>{v} ({statusCounts?.[k] ?? 0})</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Loja</label>
                                    <select className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={localFilters.store_id} onChange={e => setLocalFilters(f => ({ ...f, store_id: e.target.value }))}>
                                        <option value="">Todas</option>
                                        {stores?.map(s => <option key={s.code} value={s.code}>{s.name}</option>)}
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

                    <DataTable data={deliveries} columns={columns} emptyMessage="Nenhuma entrega encontrada." />
                </div>
            </div>

            {/* Create Modal */}
            <StandardModal show={modals.create} onClose={() => closeModal('create')}
                title="Nova Entrega" headerColor="bg-indigo-600" headerIcon={<TruckIcon className="h-5 w-5" />}
                maxWidth="3xl" onSubmit={handleCreate}
                footer={<StandardModal.Footer onCancel={() => closeModal('create')} onSubmit="submit" submitLabel="Criar" processing={processing} />}>
                {formFields}
            </StandardModal>

            {/* Detail Modal */}
            {selected && modals.detail && detailData && (
                <StandardModal show={modals.detail} onClose={() => { closeModal('detail'); setDetailData(null); }}
                    title={detailData.client_name} subtitle={detailData.store_name}
                    headerColor="bg-gray-700" headerIcon={<TruckIcon className="h-5 w-5" />}
                    headerBadges={[{ text: detailData.status_label, className: 'bg-white/20 text-white' }]}
                    maxWidth="3xl" footer={
                        <StandardModal.Footer onCancel={() => { closeModal('detail'); setDetailData(null); }} cancelLabel="Fechar"
                            extraButtons={canEdit ? [
                                detailData.next_status && detailData.next_status_label && (
                                    <Button key="next" variant="success" size="sm" onClick={() => handleTransition(selected.id, detailData.next_status)}>
                                        {detailData.next_status_label}
                                    </Button>
                                ),
                                detailData.can_cancel && (
                                    <Button key="cancel" variant="danger" size="sm" onClick={() => handleTransition(selected.id, 'cancelled')}>
                                        Cancelar Entrega
                                    </Button>
                                ),
                            ].filter(Boolean) : []}
                        />
                    }>
                    <StandardModal.Section title="Informações">
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <StandardModal.Field label="Endereço" value={detailData.address || '-'} icon={MapPinIcon} />
                            <StandardModal.Field label="Bairro" value={detailData.neighborhood || '-'} />
                            <StandardModal.Field label="Telefone" value={detailData.contact_phone || '-'} icon={PhoneIcon} />
                            <StandardModal.Field label="Valor" value={detailData.sale_value ? `R$ ${Number(detailData.sale_value).toFixed(2).replace('.', ',')}` : '-'} icon={CurrencyDollarIcon} />
                            <StandardModal.Field label="Pagamento" value={detailData.payment_method || '-'} />
                            <StandardModal.Field label="Parcelas" value={detailData.installments || '-'} />
                        </div>
                    </StandardModal.Section>
                    <StandardModal.Section title="Detalhes">
                        <div className="flex flex-wrap gap-3">
                            {detailData.needs_card_machine && <StatusBadge variant="warning">Maquininha</StatusBadge>}
                            {detailData.is_exchange && <StatusBadge variant="info">Troca</StatusBadge>}
                            {detailData.is_gift && <StatusBadge variant="purple">Presente</StatusBadge>}
                        </div>
                        {detailData.observations && (
                            <div className="mt-3 p-3 bg-amber-50 rounded-md text-sm text-amber-800">{detailData.observations}</div>
                        )}
                    </StandardModal.Section>
                    {detailData.route && (
                        <StandardModal.Section title="Rota">
                            <div className="grid grid-cols-2 gap-4">
                                <StandardModal.Field label="Número da Rota" value={detailData.route.route_number} />
                                <StandardModal.Field label="Motorista" value={detailData.route.driver_name || '-'} />
                            </div>
                        </StandardModal.Section>
                    )}
                    <StandardModal.Section title="Fluxo da Entrega">
                        <div className="flex items-center gap-1 flex-wrap">
                            {['requested', 'collected', 'awaiting_pickup', 'in_route', 'delivered'].map((step, i) => {
                                const labels = { requested: 'Solicitado', collected: 'Coletado', awaiting_pickup: 'Pronto p/ Rota', in_route: 'Em Rota', delivered: 'Entregue' };
                                const isCurrent = detailData.status === step;
                                const isPast = ['requested', 'collected', 'awaiting_pickup', 'in_route', 'delivered']
                                    .indexOf(detailData.status) > i;
                                const isCancelled = detailData.status === 'cancelled';
                                const isReturned = detailData.status === 'returned';
                                return (
                                    <div key={step} className="flex items-center gap-1">
                                        {i > 0 && <div className={`w-4 h-0.5 ${isPast ? 'bg-green-400' : 'bg-gray-200'}`} />}
                                        <span className={`text-xs px-2 py-1 rounded-full font-medium ${
                                            isCurrent ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-400' :
                                            isPast ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'
                                        }`}>{labels[step]}</span>
                                    </div>
                                );
                            })}
                            {detailData.status === 'cancelled' && (
                                <div className="flex items-center gap-1 ml-2">
                                    <div className="w-4 h-0.5 bg-red-300" />
                                    <span className="text-xs px-2 py-1 rounded-full font-medium bg-red-100 text-red-700 ring-2 ring-red-400">Cancelado</span>
                                </div>
                            )}
                            {detailData.status === 'returned' && (
                                <div className="flex items-center gap-1 ml-2">
                                    <div className="w-4 h-0.5 bg-orange-300" />
                                    <span className="text-xs px-2 py-1 rounded-full font-medium bg-orange-100 text-orange-700 ring-2 ring-orange-400">Devolvido</span>
                                </div>
                            )}
                        </div>
                        {detailData.status === 'awaiting_pickup' && (
                            <p className="text-xs text-indigo-600 mt-2">Próximo passo: adicionar a uma rota de entrega para atribuir ao motorista.</p>
                        )}
                        {detailData.status === 'in_route' && (
                            <p className="text-xs text-purple-600 mt-2">O motorista está realizando a entrega via Painel do Motorista.</p>
                        )}
                    </StandardModal.Section>

                    <StandardModal.Section title="Registro">
                        <div className="grid grid-cols-2 gap-4">
                            <StandardModal.MiniField label="Criado por" value={detailData.created_by || '-'} />
                            <StandardModal.MiniField label="Data" value={detailData.created_at} />
                        </div>
                    </StandardModal.Section>
                </StandardModal>
            )}

            {/* Edit Modal */}
            {selected && modals.edit && (
                <StandardModal show={modals.edit} onClose={() => closeModal('edit')}
                    title="Editar Entrega" headerColor="bg-yellow-600" headerIcon={<TruckIcon className="h-5 w-5" />}
                    maxWidth="3xl" onSubmit={handleUpdate}
                    footer={<StandardModal.Footer onCancel={() => closeModal('edit')} onSubmit="submit" submitLabel="Atualizar" processing={processing} />}>
                    {formFields}
                </StandardModal>
            )}

            {/* Delete Confirm */}
            <DeleteConfirmModal show={deleteTarget !== null} onClose={() => setDeleteTarget(null)}
                onConfirm={handleDelete} itemType="entrega" itemName={deleteTarget?.client_name} />
        </>
    );
}
