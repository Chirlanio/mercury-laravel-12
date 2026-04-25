import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowsRightLeftIcon, XMarkIcon, ExclamationTriangleIcon,
    TruckIcon, CheckCircleIcon, XCircleIcon,
} from '@heroicons/react/24/outline';
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/react/24/solid';
import { useState, useMemo } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import { useConfirm } from '@/Hooks/useConfirm';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import PageHeader from '@/Components/Shared/PageHeader';
import StatusBadge from '@/Components/Shared/StatusBadge';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import StatisticsCards from '@/Components/Transfers/StatisticsCards';
import DetailModal from '@/Components/Transfers/DetailModal';
import EditModal from '@/Components/Transfers/EditModal';

const STATUS_VARIANTS = {
    pending: 'warning', in_transit: 'info', delivered: 'indigo', confirmed: 'success', cancelled: 'danger',
};

export default function Index({ transfers, stores = [], filters = {}, statusOptions = {}, typeOptions = {} }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_TRANSFERS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_TRANSFERS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_TRANSFERS);

    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'detail', 'edit']);
    const { confirm, ConfirmDialogComponent } = useConfirm();
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const applyFilter = (key, value) => {
        const currentUrl = new URL(window.location);
        if (value) currentUrl.searchParams.set(key, value);
        else currentUrl.searchParams.delete(key);
        currentUrl.searchParams.delete('page');
        router.visit(currentUrl.toString(), { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        router.visit(route('transfers.index'), { preserveState: true, preserveScroll: true });
    };

    const hasActiveFilters = filters.status || filters.store_id || filters.transfer_type;

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(route('transfers.destroy', deleteTarget.id), {
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    const openEdit = (transfer) => {
        closeModal('detail', false);
        openModal('edit', transfer);
    };

    const handleConfirmPickup = async (transfer) => {
        const confirmed = await confirm({
            title: 'Confirmar Coleta',
            message: `Confirmar coleta da transferência #${transfer.id}?`,
            confirmText: 'Confirmar',
            type: 'info',
        });
        if (confirmed) router.post(route('transfers.confirm-pickup', transfer.id));
    };

    const handleCancel = async (transfer) => {
        const confirmed = await confirm({
            title: 'Cancelar Transferência',
            message: `Tem certeza que deseja cancelar a transferência #${transfer.id}?`,
            confirmText: 'Sim, Cancelar',
            type: 'danger',
        });
        if (confirmed) router.post(route('transfers.cancel', transfer.id));
    };

    const columns = [
        {
            field: 'id', label: 'ID', sortable: true,
            render: (t) => <span className="font-medium text-gray-900">#{t.id}</span>,
        },
        { field: 'origin_store', label: 'Origem', render: (t) => t.origin_store?.name || '-' },
        { field: 'destination_store', label: 'Destino', render: (t) => t.destination_store?.name || '-' },
        { field: 'transfer_type', label: 'Tipo', sortable: true, render: (t) => t.type_label },
        {
            field: 'invoice_number', label: 'NF',
            render: (t) => <span className="font-mono">{t.invoice_number || '-'}</span>,
        },
        { field: 'volumes_qty', label: 'Vol/Prod', render: (t) => `${t.volumes_qty ?? '-'} / ${t.products_qty ?? '-'}` },
        {
            field: 'status', label: 'Status', sortable: true,
            render: (t) => <StatusBadge variant={STATUS_VARIANTS[t.status] || 'gray'}>{t.status_label}</StatusBadge>,
        },
        { field: 'created_at', label: 'Data', sortable: true },
        {
            field: 'actions', label: 'Ações',
            render: (transfer) => (
                <ActionButtons
                    onView={() => openModal('detail', transfer)}
                    onEdit={canEdit && transfer.status === 'pending' ? () => openEdit(transfer) : null}
                    onDelete={canDelete && transfer.status === 'pending' ? () => setDeleteTarget(transfer) : null}
                >
                    {canEdit && transfer.status === 'pending' && (
                        <ActionButtons.Custom variant="info" icon={TruckIcon} title="Confirmar Coleta"
                            onClick={() => handleConfirmPickup(transfer)} />
                    )}
                    {canEdit && transfer.status === 'in_transit' && (
                        <ActionButtons.Custom variant="success" icon={CheckCircleIcon} title="Confirmar Entrega"
                            onClick={() => openModal('detail', transfer)} />
                    )}
                    {canEdit && transfer.status === 'delivered' && (
                        <ActionButtons.Custom variant="success" icon={CheckCircleSolid} title="Confirmar Recebimento"
                            onClick={() => openModal('detail', transfer)} />
                    )}
                    {canEdit && !['confirmed', 'cancelled', 'pending'].includes(transfer.status) && (
                        <ActionButtons.Custom variant="danger" icon={XCircleIcon} title="Cancelar"
                            onClick={() => handleCancel(transfer)} />
                    )}
                </ActionButtons>
            ),
        },
    ];

    return (
        <>
            <Head title="Transferências" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Transferências"
                        subtitle="Gerencie transferências entre lojas"
                        actions={[
                            {
                                type: 'create',
                                label: 'Nova Transferência',
                                onClick: () => openModal('create'),
                                visible: canCreate,
                            },
                        ]}
                    />

                    {/* Estatísticas */}
                    <StatisticsCards filters={{ store_id: filters.store_id, status: filters.status, transfer_type: filters.transfer_type }} />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select value={filters.status || ''} onChange={(e) => applyFilter('status', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Todos os Status</option>
                                    {Object.entries(statusOptions).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Loja</label>
                                <select value={filters.store_id || ''} onChange={(e) => applyFilter('store_id', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Todas as Lojas</option>
                                    {stores.map((s) => <option key={s.id} value={s.id}>{s.code} - {s.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                                <select value={filters.transfer_type || ''} onChange={(e) => applyFilter('transfer_type', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Todos os Tipos</option>
                                    {Object.entries(typeOptions).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                                </select>
                            </div>
                            <div>
                                <Button variant="secondary" size="sm" className="h-[42px] w-[150px]"
                                    onClick={clearFilters} disabled={!hasActiveFilters} icon={XMarkIcon}>
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={transfers} columns={columns}
                        searchPlaceholder="Buscar NF, observações..."
                        emptyMessage="Nenhuma transferência encontrada"
                        onRowClick={(t) => openModal('detail', t)}
                        perPageOptions={[15, 25, 50]}
                    />
                </div>
            </div>

            {/* Modal Criar */}
            {modals.create && (
                <CreateModal stores={stores} typeOptions={typeOptions} onClose={() => closeModal('create')} />
            )}

            {/* Detail Modal */}
            <DetailModal
                transferId={modals.detail ? selected?.id : null}
                onClose={() => closeModal('detail')}
                onRefresh={() => { closeModal('detail'); router.reload(); }}
                onEdit={() => { if (selected) openEdit(selected); }}
            />

            {/* Edit Modal */}
            <EditModal
                isOpen={modals.edit} onClose={() => closeModal('edit')}
                onSuccess={() => { closeModal('edit'); router.reload(); }}
                transfer={selected} stores={stores} typeOptions={typeOptions}
            />

            {/* Delete Confirm */}
            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleConfirmDelete}
                itemType="transferência"
                itemName={deleteTarget ? `#${deleteTarget.id}` : ''}
                details={[
                    { label: 'Origem', value: deleteTarget?.origin_store?.name },
                    { label: 'Destino', value: deleteTarget?.destination_store?.name },
                ]}
                processing={deleting}
            />

            <ConfirmDialogComponent />
        </>
    );
}

function CreateModal({ stores, typeOptions, onClose }) {
    const form = useForm({
        origin_store_id: '', destination_store_id: '', invoice_number: '',
        volumes_qty: '', products_qty: '', transfer_type: 'transfer', observations: '',
    });

    const sameStoreError = useMemo(() => {
        if (form.data.origin_store_id && form.data.destination_store_id &&
            String(form.data.origin_store_id) === String(form.data.destination_store_id)) {
            return 'A loja de destino não pode ser a mesma da origem.';
        }
        return null;
    }, [form.data.origin_store_id, form.data.destination_store_id]);

    const handleSubmit = () => {
        if (sameStoreError) return;
        form.post(route('transfers.store'), { onSuccess: () => onClose() });
    };

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title="Nova Transferência"
            headerColor="bg-indigo-600"
            headerIcon={<ArrowsRightLeftIcon className="h-5 w-5" />}
            maxWidth="2xl"
            onSubmit={handleSubmit}
            errorMessage={form.errors.transfer}
            footer={
                <StandardModal.Footer onCancel={onClose} onSubmit="submit"
                    submitLabel="Criar Transferência" processing={form.processing}
                    disabled={!!sameStoreError} />
            }
        >
            <FormSection title="Lojas" cols={2}>
                <div>
                    <InputLabel value="Loja Origem *" />
                    <select value={form.data.origin_store_id} onChange={(e) => form.setData('origin_store_id', e.target.value)}
                        className={`mt-1 w-full rounded-md shadow-sm sm:text-sm ${sameStoreError ? 'border-red-400' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'}`}
                        required>
                        <option value="">Selecione a loja de origem</option>
                        {stores.map((s) => <option key={s.id} value={s.id}>{s.code} - {s.name}</option>)}
                    </select>
                    <InputError message={form.errors.origin_store_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Loja Destino *" />
                    <select value={form.data.destination_store_id} onChange={(e) => form.setData('destination_store_id', e.target.value)}
                        className={`mt-1 w-full rounded-md shadow-sm sm:text-sm ${sameStoreError ? 'border-red-400' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'}`}
                        required>
                        <option value="">Selecione a loja de destino</option>
                        {stores.map((s) => <option key={s.id} value={s.id}>{s.code} - {s.name}</option>)}
                    </select>
                    <InputError message={form.errors.destination_store_id} className="mt-1" />
                </div>
                {sameStoreError && (
                    <div className="col-span-full flex items-center gap-2 p-2.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                        <ExclamationTriangleIcon className="h-4 w-4 text-red-500 shrink-0" />
                        {sameStoreError}
                    </div>
                )}
            </FormSection>

            <FormSection title="Detalhes da Transferência" cols={3}>
                <div className="col-span-full">
                    <InputLabel value="Tipo *" />
                    <select value={form.data.transfer_type} onChange={(e) => form.setData('transfer_type', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        {Object.entries(typeOptions).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                    </select>
                    <InputError message={form.errors.transfer_type} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Nº Nota Fiscal" />
                    <TextInput className="mt-1 w-full" value={form.data.invoice_number}
                        onChange={(e) => form.setData('invoice_number', e.target.value)} placeholder="Ex: 12345" />
                </div>
                <div>
                    <InputLabel value="Volumes" />
                    <TextInput type="number" min="0" className="mt-1 w-full" value={form.data.volumes_qty}
                        onChange={(e) => form.setData('volumes_qty', e.target.value)} placeholder="0" />
                </div>
                <div>
                    <InputLabel value="Produtos" />
                    <TextInput type="number" min="0" className="mt-1 w-full" value={form.data.products_qty}
                        onChange={(e) => form.setData('products_qty', e.target.value)} placeholder="0" />
                </div>
            </FormSection>

            <FormSection title="Observações" cols={1}>
                <div>
                    <textarea value={form.data.observations} onChange={(e) => form.setData('observations', e.target.value)}
                        rows={3} maxLength={1000} placeholder="Observações adicionais sobre a transferência..."
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                    <p className="text-xs text-gray-400 mt-1 text-right">{(form.data.observations || '').length}/1000</p>
                </div>
            </FormSection>
        </StandardModal>
    );
}
