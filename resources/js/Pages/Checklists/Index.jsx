import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import DataTable from '@/Components/DataTable';
import ActionButtons from '@/Components/ActionButtons';
import ChecklistCreateModal from '@/Components/Modals/ChecklistCreateModal';
import ChecklistViewModal from '@/Components/Modals/ChecklistViewModal';
import ChecklistEditModal from '@/Components/Modals/ChecklistEditModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import PageHeader from '@/Components/Shared/PageHeader';
import Button from '@/Components/Button';
import {
    MagnifyingGlassIcon,
    ClipboardDocumentCheckIcon,
    FunnelIcon,
    XMarkIcon,
    CheckCircleIcon,
    ClockIcon,
    ArrowPathIcon,
    ChartBarIcon,
} from '@heroicons/react/24/outline';

export default function Index({ checklists, stats, stores = [], filters = {} }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'view', 'edit', 'delete']);
    
    // Filter state
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [storeFilter, setStoreFilter] = useState(filters.store_id || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [processing, setProcessing] = useState(false);

    const applyFilters = () => {
        router.get('/checklists', {
            search: search || undefined,
            status: statusFilter || undefined,
            store_id: storeFilter || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatusFilter('');
        setStoreFilter('');
        setDateFrom('');
        setDateTo('');
        router.get('/checklists', {}, { preserveState: true });
    };

    const hasActiveFilters = search || statusFilter || storeFilter || dateFrom || dateTo;

    const handleDelete = () => {
        if (!selected) return;
        setProcessing(true);
        router.delete(`/checklists/${selected.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal('delete');
                setProcessing(false);
            },
            onError: () => {
                setProcessing(false);
            },
        });
    };

    const columns = [
        {
            label: 'Loja',
            field: 'store.name',
            sortable: false,
            render: (row) => (
                <div>
                    <div className="font-medium text-gray-900">{row.store?.name}</div>
                    <div className="text-xs text-gray-500">{row.store?.code}</div>
                </div>
            ),
        },
        {
            label: 'Aplicador',
            field: 'applicator.name',
            sortable: false,
            render: (row) => row.applicator?.name || '-',
        },
        {
            label: 'Status',
            field: 'status',
            sortable: true,
            render: (row) => {
                const statusMap = {
                    pending: { label: 'Pendente', variant: 'warning', icon: ClockIcon },
                    in_progress: { label: 'Em Andamento', variant: 'info', icon: ArrowPathIcon },
                    completed: { label: 'Concluído', variant: 'success', icon: CheckCircleIcon },
                };
                const config = statusMap[row.status] || statusMap.pending;
                return (
                    <StatusBadge variant={config.variant} icon={config.icon}>
                        {config.label}
                    </StatusBadge>
                );
            },
        },
        {
            label: 'Progresso',
            field: 'progress',
            sortable: false,
            render: (row) => {
                const { answered, total } = row.progress || { answered: 0, total: 0 };
                const pct = total > 0 ? Math.round((answered / total) * 100) : 0;
                return (
                    <div className="flex items-center gap-2 min-w-[120px]">
                        <div className="flex-1 bg-gray-200 rounded-full h-1.5">
                            <div
                                className="bg-indigo-600 h-1.5 rounded-full transition-all"
                                style={{ width: `${pct}%` }}
                            />
                        </div>
                        <span className="text-[10px] font-medium text-gray-500 whitespace-nowrap">{answered}/{total}</span>
                    </div>
                );
            },
        },
        {
            label: 'Conformidade',
            field: 'score_percentage',
            sortable: true,
            render: (row) => {
                if (row.score_percentage === null || row.score_percentage === undefined) {
                    return <span className="text-gray-400">-</span>;
                }
                const variants = {
                    green: 'success',
                    blue: 'info',
                    yellow: 'warning',
                    red: 'danger',
                };
                const variant = row.performance ? variants[row.performance.color] : 'gray';
                return (
                    <StatusBadge variant={variant}>
                        {Number(row.score_percentage).toFixed(1)}%
                    </StatusBadge>
                );
            },
        },
        {
            label: 'Data',
            field: 'created_at',
            sortable: true,
            render: (row) => <span className="text-gray-600 text-sm">{row.created_at}</span>,
        },
        {
            label: 'Ações',
            field: 'actions',
            sortable: false,
            render: (row) => (
                <ActionButtons
                    onView={() => openModal('view', row)}
                    onEdit={hasPermission(PERMISSIONS.EDIT_CHECKLISTS) && row.status !== 'completed' ? () => openModal('edit', row) : undefined}
                    onDelete={hasPermission(PERMISSIONS.DELETE_CHECKLISTS) && row.status === 'pending' ? () => openModal('delete', row) : undefined}
                />
            ),
        },
    ];

    const statsCards = [
        { label: 'Total', value: stats.total, color: 'gray', icon: ClipboardDocumentCheckIcon },
        { label: 'Pendentes', value: stats.pending, color: 'yellow', icon: ClockIcon },
        { label: 'Em Andamento', value: stats.in_progress, color: 'blue', icon: ArrowPathIcon },
        { label: 'Concluídos', value: stats.completed, color: 'green', icon: CheckCircleIcon },
        { label: 'Conformidade Média', value: stats.avg_score, format: 'percentage', color: 'indigo', icon: ChartBarIcon },
    ];

    return (
        <>
            <Head title="Checklists de Qualidade" />
            
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Checklists de Qualidade"
                        subtitle="Gestão e acompanhamento de auditorias de qualidade nas lojas."
                        icon={ClipboardDocumentCheckIcon}
                        actions={[
                            {
                                type: 'create',
                                label: 'Novo Checklist',
                                onClick: () => openModal('create'),
                                visible: hasPermission(PERMISSIONS.CREATE_CHECKLISTS),
                            },
                        ]}
                    />


                    {/* Statistics */}
                    <StatisticsGrid cards={statsCards} />

                    {/* Filters */}
                    <div className="bg-white shadow-sm border border-gray-200 rounded-xl p-5 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                            <div className="relative">
                                <MagnifyingGlassIcon className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Buscar loja..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    className="pl-9 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Todos os Status</option>
                                <option value="pending">Pendente</option>
                                <option value="in_progress">Em Andamento</option>
                                <option value="completed">Concluído</option>
                            </select>
                            {stores.length > 0 && (
                                <select
                                    value={storeFilter}
                                    onChange={(e) => setStoreFilter(e.target.value)}
                                    className="rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">Todas as Lojas</option>
                                    {stores.map((store) => (
                                        <option key={store.id} value={store.id}>{store.code} - {store.name}</option>
                                    ))}
                                </select>
                            )}
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                            <input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                            <div className="flex gap-2">
                                <Button
                                    variant="primary"
                                    onClick={applyFilters}
                                    className="flex-1"
                                    icon={FunnelIcon}
                                >
                                    Filtrar
                                </Button>
                                {hasActiveFilters && (
                                    <Button
                                        variant="light"
                                        onClick={clearFilters}
                                        icon={XMarkIcon}
                                        iconOnly
                                    />
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Data Table */}
                    <div className="bg-white shadow-sm border border-gray-200 rounded-xl overflow-hidden">
                        <DataTable
                            data={checklists}
                            columns={columns}
                            searchable={false}
                            emptyMessage="Nenhum checklist encontrado."
                            onRowClick={(row) => openModal('view', row)}
                        />
                    </div>
                </div>
            </div>

            {/* Modals */}
            <ChecklistCreateModal
                show={modals.create}
                onClose={() => closeModal('create')}
                stores={stores}
                onSuccess={() => {
                    closeModal('create');
                    router.reload();
                }}
            />

            {selected && (
                <>
                    <ChecklistViewModal
                        show={modals.view}
                        onClose={() => closeModal('view')}
                        checklistId={selected.id}
                    />

                    <ChecklistEditModal
                        show={modals.edit}
                        onClose={() => closeModal('edit')}
                        checklistId={selected.id}
                        onSuccess={() => {
                            closeModal('edit');
                            router.reload();
                        }}
                    />

                    <DeleteConfirmModal
                        show={modals.delete}
                        onClose={() => closeModal('delete')}
                        onConfirm={handleDelete}
                        itemType="Checklist"
                        itemName={`da loja ${selected.store?.name}`}
                        warningMessage="Apenas checklists pendentes podem ser excluídos. Esta ação é irreversível."
                        processing={processing}
                        details={[
                            { label: 'Data', value: selected.created_at },
                            { label: 'Aplicador', value: selected.applicator?.name },
                        ]}
                    />
                </>
            )}
        </>
    );
}
