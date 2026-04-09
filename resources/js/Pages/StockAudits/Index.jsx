import PageHeader from '@/Components/PageHeader';
import { Head, router } from '@inertiajs/react';
import {
    PlusIcon, MagnifyingGlassIcon, ClipboardDocumentCheckIcon,
} from '@heroicons/react/24/outline';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import ActionButtons from '@/Components/ActionButtons';
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

    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [typeFilter, setTypeFilter] = useState(filters.audit_type || '');
    const [storeFilter, setStoreFilter] = useState(filters.store_id || '');

    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showDetailModal, setShowDetailModal] = useState(false);
    const [detailId, setDetailId] = useState(null);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editData, setEditData] = useState(null);
    const [showTransitionModal, setShowTransitionModal] = useState(false);
    const [transitionData, setTransitionData] = useState(null);

    const applyFilters = () => {
        router.get(route('stock-audits.index'), {
            search: search || undefined,
            status: statusFilter || undefined,
            audit_type: typeFilter || undefined,
            store_id: storeFilter || undefined,
        }, { preserveState: true });
    };

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

    return (
        <>
            <Head title="Auditorias de Estoque" />
            <PageHeader>
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        <ClipboardDocumentCheckIcon className="inline h-6 w-6 mr-2 text-indigo-600" />
                        Auditorias de Estoque
                    </h2>
                    <div className="flex items-center space-x-3">
                        {canCreate && (
                            <button
                                onClick={() => setShowCreateModal(true)}
                                className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                            >
                                <PlusIcon className="h-4 w-4 mr-2" />
                                Nova Auditoria
                            </button>
                        )}
                    </div>
                </div>
            </PageHeader>

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Status Cards */}
                    <StatusCards
                        statusCounts={statusCounts}
                        statusOptions={statusOptions}
                        onFilter={setStatusFilter}
                        activeFilter={statusFilter}
                        onApply={applyFilters}
                    />

                    {/* Filtros */}
                    <div className="bg-white shadow rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-5 gap-4">
                            <div className="relative">
                                <MagnifyingGlassIcon className="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Buscar auditoria..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    className="pl-10 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Todos os Status</option>
                                {Object.entries(statusOptions).map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                            </select>
                            <select
                                value={typeFilter}
                                onChange={(e) => setTypeFilter(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Todos os Tipos</option>
                                {Object.entries(typeOptions).map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                            </select>
                            <select
                                value={storeFilter}
                                onChange={(e) => setStoreFilter(e.target.value)}
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
                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Loja</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                    <th className="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Acurácia</th>
                                    <th className="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Itens</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Criado em</th>
                                    <th className="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {audits.data && audits.data.length > 0 ? (
                                    audits.data.map((audit) => (
                                        <tr
                                            key={audit.id}
                                            className="hover:bg-gray-50 cursor-pointer"
                                            onClick={() => openDetail(audit.id)}
                                        >
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                #{audit.id}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {audit.store?.name || '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {audit.type_label || '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${audit.status_color || STATUS_COLORS[audit.status] || 'bg-gray-100 text-gray-800'}`}>
                                                    <span className={`h-1.5 w-1.5 rounded-full mr-1.5 ${STATUS_STYLES[audit.status]?.dot || 'bg-gray-400'}`} />
                                                    {audit.status_label || statusOptions[audit.status] || audit.status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-center">
                                                <span className={`text-sm font-semibold ${
                                                    audit.accuracy_percentage != null
                                                        ? audit.accuracy_percentage >= 95
                                                            ? 'text-green-600'
                                                            : audit.accuracy_percentage >= 80
                                                            ? 'text-yellow-600'
                                                            : 'text-red-600'
                                                        : 'text-gray-400'
                                                }`}>
                                                    {formatAccuracy(audit.accuracy_percentage)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-500">
                                                {audit.items_count ?? '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {audit.created_at || '-'}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <ActionButtons
                                                    onView={() => openDetail(audit.id)}
                                                    onEdit={canEdit && audit.status === 'draft' ? () => openEdit(audit) : null}
                                                    onDelete={canDelete && audit.status === 'draft' ? () => handleDelete(audit) : null}
                                                />
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="8" className="px-4 py-12 text-center text-gray-500">
                                            Nenhuma auditoria encontrada.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>

                        {/* Paginação */}
                        {audits.last_page > 1 && (
                            <div className="px-4 py-3 border-t border-gray-200 flex justify-between items-center">
                                <span className="text-sm text-gray-700">
                                    {audits.from} a {audits.to} de {audits.total}
                                </span>
                                <div className="flex space-x-1">
                                    {audits.links.map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() => link.url && router.get(link.url)}
                                            disabled={!link.url}
                                            className={`px-3 py-1 text-sm rounded ${
                                                link.active
                                                    ? 'bg-indigo-600 text-white'
                                                    : link.url
                                                    ? 'bg-white text-gray-700 hover:bg-gray-50 border'
                                                    : 'bg-gray-100 text-gray-400 cursor-not-allowed'
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

// ============================================================
// STATUS CARDS
// ============================================================
function StatusCards({ statusCounts, statusOptions, onFilter, activeFilter, onApply }) {
    const allStatuses = ['draft', 'awaiting_authorization', 'counting', 'reconciliation', 'finished', 'cancelled'];

    return (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            {allStatuses.map((status) => {
                const data = statusCounts[status] || { label: STATUS_STYLES[status]?.label || statusOptions[status] || status, count: 0 };
                const style = STATUS_STYLES[status] || {};
                const isActive = activeFilter === status;
                const count = typeof data === 'object' ? (data.count ?? 0) : (data ?? 0);
                const label = typeof data === 'object' ? data.label : (STATUS_STYLES[status]?.label || statusOptions[status] || status);

                return (
                    <button
                        key={status}
                        onClick={() => { onFilter(isActive ? '' : status); setTimeout(onApply, 0); }}
                        className={`rounded-lg p-4 border-2 text-left transition ${
                            isActive ? 'ring-2 ring-indigo-500 border-indigo-300' : 'border-transparent'
                        } ${style.bg}`}
                    >
                        <p className={`text-xs font-medium uppercase truncate ${style.text}`}>{label}</p>
                        <p className={`text-2xl font-bold mt-1 ${style.text}`}>{count}</p>
                    </button>
                );
            })}
        </div>
    );
}
