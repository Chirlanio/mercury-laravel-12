import { Head, router } from '@inertiajs/react';
import {
    PlusIcon, XMarkIcon,
} from '@heroicons/react/24/outline';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import CreateModal from '@/Components/StockAudits/CreateModal';
import DetailModal from '@/Components/StockAudits/DetailModal';
import EditModal from '@/Components/StockAudits/EditModal';
import TransitionModal from '@/Components/StockAudits/TransitionModal';

const STATUS_STYLES = {
    draft:                  { bg: 'bg-gray-100',   text: 'text-gray-800',   dot: 'bg-gray-400',   label: 'Rascunho' },
    awaiting_authorization: { bg: 'bg-yellow-100', text: 'text-yellow-800', dot: 'bg-yellow-500', label: 'Aguardando Autorização' },
    counting:               { bg: 'bg-blue-100',   text: 'text-blue-800',   dot: 'bg-blue-500',   label: 'Em Contagem' },
    reconciliation:         { bg: 'bg-indigo-100', text: 'text-indigo-800', dot: 'bg-indigo-500', label: 'Reconciliação' },
    finished:               { bg: 'bg-green-100',  text: 'text-green-800',  dot: 'bg-green-500',  label: 'Finalizada' },
    cancelled:              { bg: 'bg-red-100',    text: 'text-red-800',    dot: 'bg-red-500',    label: 'Cancelada' },
};

const STATUS_COLORS = {
    draft:                  'bg-gray-100 text-gray-800',
    awaiting_authorization: 'bg-yellow-100 text-yellow-800',
    counting:               'bg-blue-100 text-blue-800',
    reconciliation:         'bg-indigo-100 text-indigo-800',
    finished:               'bg-green-100 text-green-800',
    cancelled:              'bg-red-100 text-red-800',
};

export default function Index({
    audits, filters = {}, statusOptions = {}, statusCounts = {}, typeOptions = {}, stores = [],
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_STOCK_AUDITS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_STOCK_AUDITS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_STOCK_AUDITS);

    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showDetailModal, setShowDetailModal] = useState(false);
    const [detailId, setDetailId] = useState(null);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editData, setEditData] = useState(null);
    const [showTransitionModal, setShowTransitionModal] = useState(false);
    const [transitionData, setTransitionData] = useState(null);

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
        router.visit(route('stock-audits.index'), { preserveState: true, preserveScroll: true });
    };

    const hasActiveFilters = filters.status || filters.audit_type || filters.store_id;

    const openDetail = (id) => {
        setDetailId(id);
        setShowDetailModal(true);
    };

    const openEdit = (audit) => {
        setEditData(audit);
        setShowEditModal(true);
    };

    const handleDelete = (audit) => {
        if (confirm(`Tem certeza que deseja excluir a auditoria #${audit.id}?`)) {
            router.delete(route('stock-audits.destroy', audit.id));
        }
    };

    const formatCurrency = (value) => {
        if (value == null) return '-';
        return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };

    const formatAccuracy = (value) => {
        if (value == null) return '-';
        return `${Number(value).toFixed(1)}%`;
    };

    const columns = [
        {
            field: 'id',
            label: 'ID',
            sortable: true,
            render: (audit) => <span className="font-medium text-gray-900">#{audit.id}</span>,
        },
        {
            field: 'store',
            label: 'Loja',
            render: (audit) => audit.store?.name || '-',
        },
        {
            field: 'audit_type',
            label: 'Tipo',
            sortable: true,
            render: (audit) => audit.type_label || '-',
        },
        {
            field: 'status',
            label: 'Status',
            sortable: true,
            render: (audit) => (
                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[audit.status] || 'bg-gray-100 text-gray-800'}`}>
                    <span className={`h-1.5 w-1.5 rounded-full mr-1.5 ${STATUS_STYLES[audit.status]?.dot || 'bg-gray-400'}`} />
                    {audit.status_label || statusOptions[audit.status] || audit.status}
                </span>
            ),
        },
        {
            field: 'accuracy_percentage',
            label: 'Acurácia',
            sortable: true,
            render: (audit) => (
                <span className={`text-sm font-semibold ${
                    audit.accuracy_percentage != null
                        ? audit.accuracy_percentage >= 95 ? 'text-green-600'
                        : audit.accuracy_percentage >= 80 ? 'text-yellow-600'
                        : 'text-red-600'
                        : 'text-gray-400'
                }`}>
                    {formatAccuracy(audit.accuracy_percentage)}
                </span>
            ),
        },
        {
            field: 'items_count',
            label: 'Itens',
            render: (audit) => audit.items_count ?? '-',
        },
        {
            field: 'created_at',
            label: 'Criado em',
            sortable: true,
        },
        {
            field: 'actions',
            label: 'Ações',
            render: (audit) => (
                <ActionButtons
                    onView={() => openDetail(audit.id)}
                    onEdit={canEdit && audit.status === 'draft' ? () => openEdit(audit) : null}
                    onDelete={canDelete && audit.status === 'draft' ? () => handleDelete(audit) : null}
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
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Auditorias de Estoque
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Inventário com contagem, conciliação e relatórios
                                </p>
                            </div>
                            {canCreate && (
                                <Button
                                    variant="primary"
                                    onClick={() => setShowCreateModal(true)}
                                    icon={PlusIcon}
                                >
                                    Nova Auditoria
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Stats Cards */}
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                        {['draft', 'awaiting_authorization', 'counting', 'reconciliation', 'finished', 'cancelled'].map((status) => {
                            const style = STATUS_STYLES[status] || {};
                            const count = typeof statusCounts[status] === 'object'
                                ? (statusCounts[status]?.count ?? 0)
                                : (statusCounts[status] ?? 0);
                            const label = typeof statusCounts[status] === 'object'
                                ? statusCounts[status]?.label
                                : (style.label || statusOptions[status] || status);

                            return (
                                <div key={status} className="bg-white shadow-sm rounded-lg p-4">
                                    <div className="text-sm font-medium text-gray-500">{label}</div>
                                    <div className={`text-2xl font-bold ${style.text || 'text-gray-900'}`}>{count}</div>
                                </div>
                            );
                        })}
                    </div>

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
                                <label className="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                                <select
                                    value={filters.audit_type || ''}
                                    onChange={(e) => applyFilter('audit_type', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos os Tipos</option>
                                    {Object.entries(typeOptions).map(([key, label]) => (
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
                        data={audits}
                        columns={columns}
                        searchPlaceholder="Buscar auditoria..."
                        emptyMessage="Nenhuma auditoria encontrada"
                        onRowClick={(audit) => openDetail(audit.id)}
                        perPageOptions={[15, 25, 50]}
                    />
                </div>
            </div>

            {/* Create Modal */}
            {showCreateModal && (
                <CreateModal
                    stores={stores}
                    typeOptions={typeOptions}
                    onClose={() => setShowCreateModal(false)}
                />
            )}

            {/* Detail Modal */}
            {showDetailModal && detailId && (
                <DetailModal
                    auditId={detailId}
                    canEdit={canEdit}
                    onClose={() => { setShowDetailModal(false); setDetailId(null); }}
                    onEdit={(audit) => { setShowDetailModal(false); openEdit(audit); }}
                    onTransition={(audit, newStatus) => {
                        setShowDetailModal(false);
                        setTransitionData({ audit, newStatus });
                        setShowTransitionModal(true);
                    }}
                />
            )}

            {/* Edit Modal */}
            {showEditModal && editData && (
                <EditModal
                    audit={editData}
                    stores={stores}
                    typeOptions={typeOptions}
                    onClose={() => { setShowEditModal(false); setEditData(null); }}
                    onSuccess={() => { setShowEditModal(false); setEditData(null); router.reload(); }}
                />
            )}

            {/* Transition Modal */}
            {showTransitionModal && transitionData && (
                <TransitionModal
                    data={transitionData}
                    statusOptions={statusOptions}
                    onClose={() => { setShowTransitionModal(false); setTransitionData(null); }}
                />
            )}
        </>
    );
}

