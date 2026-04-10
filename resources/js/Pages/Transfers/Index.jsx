import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowsRightLeftIcon, PlusIcon, XMarkIcon, ExclamationTriangleIcon,
    TruckIcon, CheckCircleIcon, XCircleIcon,
} from '@heroicons/react/24/outline';
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/react/24/solid';
import { useState, useMemo } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StatisticsCards from '@/Components/Transfers/StatisticsCards';
import DetailModal from '@/Components/Transfers/DetailModal';
import EditModal from '@/Components/Transfers/EditModal';

const STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800',
    in_transit: 'bg-blue-100 text-blue-800',
    delivered: 'bg-indigo-100 text-indigo-800',
    confirmed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

export default function Index({ transfers, stores = [], filters = {}, statusOptions = {}, typeOptions = {} }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_TRANSFERS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_TRANSFERS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_TRANSFERS);

    const [showCreateModal, setShowCreateModal] = useState(false);
    const [detailTransferId, setDetailTransferId] = useState(null);
    const [editTransfer, setEditTransfer] = useState(null);
    const [showEditModal, setShowEditModal] = useState(false);

    const applyFilter = (key, value) => {
        const currentUrl = new URL(window.location);
        if (value) {
            currentUrl.searchParams.set(key, value);
        } else {
            currentUrl.searchParams.delete(key);
        }
        currentUrl.searchParams.delete('page');
        router.visit(currentUrl.toString(), { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        router.visit(route('transfers.index'), { preserveState: true, preserveScroll: true });
    };

    const hasActiveFilters = filters.status || filters.store_id || filters.transfer_type;

    const handleDelete = (transfer) => {
        if (confirm(`Tem certeza que deseja excluir a transferência #${transfer.id}?`)) {
            router.delete(route('transfers.destroy', transfer.id));
        }
    };

    const openEdit = (transfer) => {
        setEditTransfer(transfer);
        setShowEditModal(true);
    };

    const handleConfirmPickup = (transfer) => {
        if (confirm(`Confirmar coleta da transferência #${transfer.id}?`)) {
            router.post(route('transfers.confirm-pickup', transfer.id));
        }
    };

    const handleCancel = (transfer) => {
        if (confirm(`Tem certeza que deseja cancelar a transferência #${transfer.id}?`)) {
            router.post(route('transfers.cancel', transfer.id));
        }
    };

    const columns = [
        {
            field: 'id',
            label: 'ID',
            sortable: true,
            render: (transfer) => (
                <span className="font-medium text-gray-900">#{transfer.id}</span>
            ),
        },
        {
            field: 'origin_store',
            label: 'Origem',
            render: (transfer) => transfer.origin_store?.name || '-',
        },
        {
            field: 'destination_store',
            label: 'Destino',
            render: (transfer) => transfer.destination_store?.name || '-',
        },
        {
            field: 'transfer_type',
            label: 'Tipo',
            sortable: true,
            render: (transfer) => transfer.type_label,
        },
        {
            field: 'invoice_number',
            label: 'NF',
            render: (transfer) => (
                <span className="font-mono">{transfer.invoice_number || '-'}</span>
            ),
        },
        {
            field: 'volumes_qty',
            label: 'Vol/Prod',
            render: (transfer) => `${transfer.volumes_qty ?? '-'} / ${transfer.products_qty ?? '-'}`,
        },
        {
            field: 'status',
            label: 'Status',
            sortable: true,
            render: (transfer) => (
                <span className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[transfer.status] || 'bg-gray-100 text-gray-800'}`}>
                    {transfer.status_label}
                </span>
            ),
        },
        {
            field: 'created_at',
            label: 'Data',
            sortable: true,
        },
        {
            field: 'actions',
            label: 'Ações',
            render: (transfer) => (
                <ActionButtons
                    onView={() => setDetailTransferId(transfer.id)}
                    onEdit={canEdit && transfer.status === 'pending' ? () => openEdit(transfer) : null}
                    onDelete={canDelete && transfer.status === 'pending' ? () => handleDelete(transfer) : null}
                >
                    {canEdit && transfer.status === 'pending' && (
                        <ActionButtons.Custom variant="info" icon={TruckIcon} title="Confirmar Coleta"
                            onClick={() => handleConfirmPickup(transfer)} />
                    )}
                    {canEdit && transfer.status === 'in_transit' && (
                        <ActionButtons.Custom variant="success" icon={CheckCircleIcon} title="Confirmar Entrega"
                            onClick={() => setDetailTransferId(transfer.id)} />
                    )}
                    {canEdit && transfer.status === 'delivered' && (
                        <ActionButtons.Custom variant="success" icon={CheckCircleSolid} title="Confirmar Recebimento"
                            onClick={() => setDetailTransferId(transfer.id)} />
                    )}
                    {canEdit && !['confirmed', 'cancelled'].includes(transfer.status) && transfer.status !== 'pending' && (
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
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Transferências
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie transferências entre lojas
                                </p>
                            </div>
                            {canCreate && (
                                <Button
                                    variant="primary"
                                    onClick={() => setShowCreateModal(true)}
                                    icon={PlusIcon}
                                >
                                    Nova Transferência
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Statistics Cards */}
                    <StatisticsCards filters={{ store_id: filters.store_id, status: filters.status, transfer_type: filters.transfer_type }} />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select
                                    value={filters.status || ''}
                                    onChange={(e) => applyFilter('status', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos os Status</option>
                                    {Object.entries(statusOptions).map(([key, label]) => (
                                        <option key={key} value={key}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Loja</label>
                                <select
                                    value={filters.store_id || ''}
                                    onChange={(e) => applyFilter('store_id', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todas as Lojas</option>
                                    {stores.map((store) => (
                                        <option key={store.id} value={store.id}>
                                            {store.code} - {store.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                                <select
                                    value={filters.transfer_type || ''}
                                    onChange={(e) => applyFilter('transfer_type', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos os Tipos</option>
                                    {Object.entries(typeOptions).map(([key, label]) => (
                                        <option key={key} value={key}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="h-[42px] w-[150px]"
                                    onClick={clearFilters}
                                    disabled={!hasActiveFilters}
                                    icon={XMarkIcon}
                                >
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={transfers}
                        columns={columns}
                        searchPlaceholder="Buscar NF, observações..."
                        emptyMessage="Nenhuma transferência encontrada"
                        onRowClick={(transfer) => setDetailTransferId(transfer.id)}
                        perPageOptions={[15, 25, 50]}
                    />
                </div>
            </div>

            {/* Modal Criar */}
            {showCreateModal && (
                <CreateModal
                    stores={stores}
                    typeOptions={typeOptions}
                    onClose={() => setShowCreateModal(false)}
                />
            )}

            {/* Detail Modal */}
            <DetailModal
                transferId={detailTransferId}
                onClose={() => setDetailTransferId(null)}
                onRefresh={() => { setDetailTransferId(null); router.reload(); }}
                onEdit={() => {
                    const t = transfers.data?.find(t => t.id === detailTransferId);
                    if (t) openEdit(t);
                }}
            />

            {/* Edit Modal */}
            <EditModal
                isOpen={showEditModal}
                onClose={() => { setShowEditModal(false); setEditTransfer(null); }}
                onSuccess={() => { setShowEditModal(false); setEditTransfer(null); router.reload(); }}
                transfer={editTransfer}
                stores={stores}
                typeOptions={typeOptions}
            />
        </>
    );
}

function CreateModal({ stores, typeOptions, onClose }) {
    const form = useForm({
        origin_store_id: '',
        destination_store_id: '',
        invoice_number: '',
        volumes_qty: '',
        products_qty: '',
        transfer_type: 'transfer',
        observations: '',
    });

    // Validação reativa: lojas iguais
    const sameStoreError = useMemo(() => {
        if (form.data.origin_store_id && form.data.destination_store_id &&
            String(form.data.origin_store_id) === String(form.data.destination_store_id)) {
            return 'A loja de destino não pode ser a mesma da origem.';
        }
        return null;
    }, [form.data.origin_store_id, form.data.destination_store_id]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (sameStoreError) return;
        form.post(route('transfers.store'), { onSuccess: () => onClose() });
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex min-h-full items-start justify-center p-4 pt-8">
                <div className="fixed inset-0 bg-gray-500/75 transition-opacity" onClick={onClose} />
                <div className="relative w-full max-w-2xl bg-white rounded-xl shadow-2xl max-h-[95vh] flex flex-col">
                    <div className="bg-indigo-600 rounded-t-xl px-6 py-4 flex justify-between items-center shrink-0">
                        <h3 className="text-lg font-semibold text-white">
                            <ArrowsRightLeftIcon className="inline h-5 w-5 mr-2" />
                            Nova Transferência
                        </h3>
                        <button onClick={onClose} className="text-white/70 hover:text-white text-2xl">&times;</button>
                    </div>

                    <form onSubmit={handleSubmit} className="flex flex-col flex-1 min-h-0">
                    <div className="p-6 space-y-5 overflow-y-auto flex-1">
                        {/* Erro geral do backend */}
                        {form.errors.transfer && (
                            <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700 flex items-start gap-2">
                                <ExclamationTriangleIcon className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
                                <span>{form.errors.transfer}</span>
                            </div>
                        )}

                        {/* Card 1: Lojas */}
                        <FormCard title="Lojas">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Loja Origem *</label>
                                    <select
                                        value={form.data.origin_store_id}
                                        onChange={(e) => form.setData('origin_store_id', e.target.value)}
                                        className={`w-full rounded-md shadow-sm sm:text-sm ${sameStoreError ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'}`}
                                        required
                                    >
                                        <option value="">Selecione a loja de origem</option>
                                        {stores.map((s) => (
                                            <option key={s.id} value={s.id}>{s.code} - {s.name}</option>
                                        ))}
                                    </select>
                                    {form.errors.origin_store_id && (
                                        <p className="mt-1 text-sm text-red-600">{form.errors.origin_store_id}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Loja Destino *</label>
                                    <select
                                        value={form.data.destination_store_id}
                                        onChange={(e) => form.setData('destination_store_id', e.target.value)}
                                        className={`w-full rounded-md shadow-sm sm:text-sm ${sameStoreError ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'}`}
                                        required
                                    >
                                        <option value="">Selecione a loja de destino</option>
                                        {stores.map((s) => (
                                            <option key={s.id} value={s.id}>{s.code} - {s.name}</option>
                                        ))}
                                    </select>
                                    {form.errors.destination_store_id && (
                                        <p className="mt-1 text-sm text-red-600">{form.errors.destination_store_id}</p>
                                    )}
                                </div>
                            </div>

                            {/* Feedback visual: lojas iguais */}
                            {sameStoreError && (
                                <div className="mt-3 p-2.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 flex items-center gap-2">
                                    <ExclamationTriangleIcon className="h-4 w-4 text-red-500 shrink-0" />
                                    {sameStoreError}
                                </div>
                            )}
                        </FormCard>

                        {/* Card 2: Detalhes */}
                        <FormCard title="Detalhes da Transferência">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="md:col-span-3">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                                    <select
                                        value={form.data.transfer_type}
                                        onChange={(e) => form.setData('transfer_type', e.target.value)}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        {Object.entries(typeOptions).map(([key, label]) => (
                                            <option key={key} value={key}>{label}</option>
                                        ))}
                                    </select>
                                    {form.errors.transfer_type && (
                                        <p className="mt-1 text-sm text-red-600">{form.errors.transfer_type}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Nº Nota Fiscal</label>
                                    <input
                                        type="text"
                                        value={form.data.invoice_number}
                                        onChange={(e) => form.setData('invoice_number', e.target.value)}
                                        placeholder="Ex: 12345"
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Volumes</label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={form.data.volumes_qty}
                                        onChange={(e) => form.setData('volumes_qty', e.target.value)}
                                        placeholder="0"
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Produtos</label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={form.data.products_qty}
                                        onChange={(e) => form.setData('products_qty', e.target.value)}
                                        placeholder="0"
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </div>
                            </div>
                        </FormCard>

                        {/* Card 3: Observações */}
                        <FormCard title="Observações">
                            <textarea
                                value={form.data.observations}
                                onChange={(e) => form.setData('observations', e.target.value)}
                                rows={3}
                                maxLength={1000}
                                placeholder="Observações adicionais sobre a transferência..."
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                            <p className="text-xs text-gray-400 mt-1 text-right">{(form.data.observations || '').length}/1000</p>
                        </FormCard>

                        </div>
                        {/* Ações - footer fixo */}
                        <div className="flex justify-end space-x-3 px-6 py-4 border-t bg-gray-50 rounded-b-xl shrink-0">
                            <button
                                type="button"
                                onClick={onClose}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing || !!sameStoreError}
                                className="px-6 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {form.processing ? 'Salvando...' : 'Criar Transferência'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

function FormCard({ title, children }) {
    return (
        <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div className="bg-gray-50 px-4 py-2.5 border-b border-gray-200">
                <h4 className="text-sm font-semibold text-gray-700">{title}</h4>
            </div>
            <div className="p-4">{children}</div>
        </div>
    );
}
