import { Head, router } from '@inertiajs/react';
import { PlusIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import CreateModal from '@/Components/StockAudits/CreateModal';
import DetailModal from '@/Components/StockAudits/DetailModal';
import EditModal from '@/Components/StockAudits/EditModal';
import TransitionModal from '@/Components/StockAudits/TransitionModal';
import { useState } from 'react';
import {
    DocumentMagnifyingGlassIcon, ClockIcon, ClipboardDocumentListIcon,
    ArrowPathIcon, CheckCircleIcon, XCircleIcon,
} from '@heroicons/react/24/outline';

const STATUS_VARIANT = {
    draft: 'gray',
    awaiting_authorization: 'warning',
    counting: 'info',
    reconciliation: 'indigo',
    finished: 'success',
    cancelled: 'danger',
};

const STATUS_ICON = {
    draft: ClipboardDocumentListIcon,
    awaiting_authorization: ClockIcon,
    counting: ArrowPathIcon,
    reconciliation: DocumentMagnifyingGlassIcon,
    finished: CheckCircleIcon,
    cancelled: XCircleIcon,
};

const STATUS_COLOR = {
    draft: 'gray',
    awaiting_authorization: 'yellow',
    counting: 'blue',
    reconciliation: 'indigo',
    finished: 'green',
    cancelled: 'red',
};

export default function Index({
    audits, filters = {}, statusOptions = {}, statusCounts = {}, typeOptions = {}, stores = [],
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_STOCK_AUDITS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_STOCK_AUDITS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_STOCK_AUDITS);

    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'detail', 'edit', 'transition']);
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
        router.visit(route('stock-audits.index'), { preserveState: true, preserveScroll: true });
    };

    const hasActiveFilters = filters.status || filters.audit_type || filters.store_id;

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(route('stock-audits.destroy', deleteTarget.id), {
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    const formatAccuracy = (value) => {
        if (value == null) return '-';
        return `${Number(value).toFixed(1)}%`;
    };

    // Estatísticas por status
    const statisticsCards = ['draft', 'awaiting_authorization', 'counting', 'reconciliation', 'finished', 'cancelled'].map((status) => {
        const raw = statusCounts[status];
        const count = typeof raw === 'object' ? (raw?.count ?? 0) : (raw ?? 0);
        const label = typeof raw === 'object' ? raw?.label : (statusOptions[status] || status);

        return {
            label,
            value: count,
            format: 'number',
            icon: STATUS_ICON[status],
            color: STATUS_COLOR[status] || 'gray',
        };
    });

    const columns = [
        {
            field: 'id', label: 'ID', sortable: true,
            render: (a) => <span className="font-medium text-gray-900">#{a.id}</span>,
        },
        { field: 'store', label: 'Loja', render: (a) => a.store?.name || '-' },
        { field: 'audit_type', label: 'Tipo', sortable: true, render: (a) => a.type_label || '-' },
        {
            field: 'status', label: 'Status', sortable: true,
            render: (a) => (
                <StatusBadge variant={STATUS_VARIANT[a.status] || 'gray'} dot>
                    {a.status_label || statusOptions[a.status] || a.status}
                </StatusBadge>
            ),
        },
        {
            field: 'accuracy_percentage', label: 'Acurácia', sortable: true,
            render: (a) => (
                <span className={`text-sm font-semibold ${
                    a.accuracy_percentage != null
                        ? a.accuracy_percentage >= 95 ? 'text-green-600'
                        : a.accuracy_percentage >= 80 ? 'text-yellow-600'
                        : 'text-red-600'
                        : 'text-gray-400'
                }`}>
                    {formatAccuracy(a.accuracy_percentage)}
                </span>
            ),
        },
        { field: 'items_count', label: 'Itens', render: (a) => a.items_count ?? '-' },
        { field: 'created_at', label: 'Criado em', sortable: true },
        {
            field: 'actions', label: 'Ações',
            render: (a) => (
                <ActionButtons
                    onView={() => openModal('detail', a)}
                    onEdit={canEdit && a.status === 'draft' ? () => openModal('edit', a) : null}
                    onDelete={canDelete && a.status === 'draft' ? () => setDeleteTarget(a) : null}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Auditorias de Estoque" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">Auditorias de Estoque</h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Inventário com contagem, conciliação e relatórios
                                </p>
                            </div>
                            {canCreate && (
                                <Button variant="primary" onClick={() => openModal('create')} icon={PlusIcon}>
                                    Nova Auditoria
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Estatísticas */}
                    <StatisticsGrid cards={statisticsCards} cols={6} />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select value={filters.status || ''} onChange={(e) => applyFilter('status', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Todos os Status</option>
                                    {Object.entries(statusOptions).map(([key, label]) => (
                                        <option key={key} value={key}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                                <select value={filters.audit_type || ''} onChange={(e) => applyFilter('audit_type', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Todos os Tipos</option>
                                    {Object.entries(typeOptions).map(([key, label]) => (
                                        <option key={key} value={key}>{label}</option>
                                    ))}
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
                                <Button variant="secondary" size="sm" className="h-[42px] w-[150px]"
                                    onClick={clearFilters} disabled={!hasActiveFilters} icon={XMarkIcon}>
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={audits} columns={columns}
                        searchPlaceholder="Buscar auditoria..."
                        emptyMessage="Nenhuma auditoria encontrada"
                        onRowClick={(a) => openModal('detail', a)}
                        perPageOptions={[15, 25, 50]}
                    />
                </div>
            </div>

            {/* Modais */}
            {modals.create && (
                <CreateModal
                    show={true}
                    stores={stores}
                    typeOptions={typeOptions}
                    onClose={() => closeModal('create')}
                    onSuccess={() => { closeModal('create'); router.reload(); }}
                />
            )}

            {modals.detail && selected && (
                <DetailModal
                    show={true}
                    auditId={selected.id}
                    canEdit={canEdit}
                    onClose={() => closeModal('detail')}
                    onEdit={(audit) => { closeModal('detail', false); openModal('edit', audit); }}
                    onTransition={(audit, newStatus) => {
                        closeModal('detail', false);
                        openModal('transition', { audit, newStatus });
                    }}
                />
            )}

            {modals.edit && selected && (
                <EditModal
                    show={true}
                    audit={selected}
                    stores={stores}
                    typeOptions={typeOptions}
                    onClose={() => closeModal('edit')}
                    onSuccess={() => { closeModal('edit'); router.reload(); }}
                />
            )}

            {modals.transition && selected && (
                <TransitionModal
                    show={true}
                    data={selected}
                    statusOptions={statusOptions}
                    onClose={() => { closeModal('transition'); router.reload(); }}
                />
            )}

            {/* Delete Confirm */}
            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleConfirmDelete}
                itemType="auditoria"
                itemName={deleteTarget ? `#${deleteTarget.id}` : ''}
                details={[
                    { label: 'Loja', value: deleteTarget?.store?.name },
                    { label: 'Tipo', value: deleteTarget?.type_label },
                ]}
                processing={deleting}
            />
        </>
    );
}
