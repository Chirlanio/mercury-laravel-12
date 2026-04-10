import PageHeader from '@/Components/PageHeader';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import DataTable from '@/Components/DataTable';
import ChecklistCreateModal from '@/Components/Modals/ChecklistCreateModal';
import ChecklistViewModal from '@/Components/Modals/ChecklistViewModal';
import ChecklistEditModal from '@/Components/Modals/ChecklistEditModal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import {
    PlusIcon,
    MagnifyingGlassIcon,
    ClipboardDocumentCheckIcon,
    EyeIcon,
    PencilSquareIcon,
    TrashIcon,
    FunnelIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';

const STATUS_CONFIG = {
    pending: { label: 'Pendente', bg: 'bg-yellow-100', text: 'text-yellow-800' },
    in_progress: { label: 'Em Andamento', bg: 'bg-blue-100', text: 'text-blue-800' },
    completed: { label: 'Concluído', bg: 'bg-green-100', text: 'text-green-800' },
};

const PERFORMANCE_COLORS = {
    green: 'bg-green-100 text-green-800',
    blue: 'bg-blue-100 text-blue-800',
    yellow: 'bg-yellow-100 text-yellow-800',
    red: 'bg-red-100 text-red-800',
};

export default function Index({ checklists, stats, stores = [], filters = {} }) {
    const { hasPermission } = usePermissions();
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isViewOpen, setIsViewOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [selectedChecklist, setSelectedChecklist] = useState(null);
    const [deleteError, setDeleteError] = useState(null);

    // Filter state
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [storeFilter, setStoreFilter] = useState(filters.store_id || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');

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

    const handleView = (checklist) => {
        setSelectedChecklist(checklist);
        setIsViewOpen(true);
    };

    const handleEdit = (checklist) => {
        setSelectedChecklist(checklist);
        setIsEditOpen(true);
    };

    const handleDeleteConfirm = (checklist) => {
        setSelectedChecklist(checklist);
        setDeleteError(null);
        setIsDeleteOpen(true);
    };

    const handleDelete = () => {
        if (!selectedChecklist) return;
        router.delete(`/checklists/${selectedChecklist.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsDeleteOpen(false);
                setSelectedChecklist(null);
            },
            onError: (errors) => {
                setDeleteError(errors.general || 'Erro ao excluir checklist.');
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
                const config = STATUS_CONFIG[row.status] || STATUS_CONFIG.pending;
                return (
                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.bg} ${config.text}`}>
                        {config.label}
                    </span>
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
                    <div className="flex items-center gap-2">
                        <div className="w-20 bg-gray-200 rounded-full h-2">
                            <div
                                className="bg-indigo-600 h-2 rounded-full transition-all"
                                style={{ width: `${pct}%` }}
                            />
                        </div>
                        <span className="text-xs text-gray-600">{answered}/{total}</span>
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
                const perf = row.performance;
                const colorClass = perf ? PERFORMANCE_COLORS[perf.color] || '' : '';
                return (
                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colorClass}`}>
                        {Number(row.score_percentage).toFixed(1)}%
                    </span>
                );
            },
        },
        {
            label: 'Data',
            field: 'created_at',
            sortable: true,
            render: (row) => row.created_at,
        },
        {
            label: 'Ações',
            field: 'actions',
            sortable: false,
            render: (row) => (
                <div className="flex items-center gap-1">
                    <button
                        onClick={(e) => { e.stopPropagation(); handleView(row); }}
                        className="p-1 text-gray-500 hover:text-indigo-600 rounded"
                        title="Visualizar"
                    >
                        <EyeIcon className="h-4 w-4" />
                    </button>
                    {hasPermission(PERMISSIONS.EDIT_CHECKLISTS) && row.status !== 'completed' && (
                        <button
                            onClick={(e) => { e.stopPropagation(); handleEdit(row); }}
                            className="p-1 text-gray-500 hover:text-blue-600 rounded"
                            title="Responder"
                        >
                            <PencilSquareIcon className="h-4 w-4" />
                        </button>
                    )}
                    {hasPermission(PERMISSIONS.DELETE_CHECKLISTS) && row.status === 'pending' && (
                        <button
                            onClick={(e) => { e.stopPropagation(); handleDeleteConfirm(row); }}
                            className="p-1 text-gray-500 hover:text-red-600 rounded"
                            title="Excluir"
                        >
                            <TrashIcon className="h-4 w-4" />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Checklists" />
            <PageHeader>
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 flex items-center gap-2">
                        <ClipboardDocumentCheckIcon className="h-6 w-6" />
                        Checklists de Qualidade
                    </h2>
                    {hasPermission(PERMISSIONS.CREATE_CHECKLISTS) && (
                        <button
                            onClick={() => setIsCreateOpen(true)}
                            className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition"
                        >
                            <PlusIcon className="h-4 w-4 mr-2" />
                            Novo Checklist
                        </button>
                    )}
                </div>
            </PageHeader>

            <div className="py-6">
                <div className="mx-auto max-w-full px-4 sm:px-6 lg:px-8">
                    {/* Statistics Cards */}
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                        <StatCard label="Total" value={stats.total} color="gray" />
                        <StatCard label="Pendentes" value={stats.pending} color="yellow" />
                        <StatCard label="Em Andamento" value={stats.in_progress} color="blue" />
                        <StatCard label="Concluídos" value={stats.completed} color="green" />
                        <StatCard label="Média Conformidade" value={`${stats.avg_score}%`} color="indigo" />
                    </div>

                    {/* Filters */}
                    <div className="bg-white shadow rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                            <div className="relative">
                                <MagnifyingGlassIcon className="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Buscar loja..."
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
                                <option value="pending">Pendente</option>
                                <option value="in_progress">Em Andamento</option>
                                <option value="completed">Concluído</option>
                            </select>
                            {stores.length > 0 && (
                                <select
                                    value={storeFilter}
                                    onChange={(e) => setStoreFilter(e.target.value)}
                                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">Todas as Lojas</option>
                                    {stores.map((store) => (
                                        <option key={store.id} value={store.id}>{store.name}</option>
                                    ))}
                                </select>
                            )}
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="Data início"
                            />
                            <input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="Data fim"
                            />
                            <div className="flex gap-2">
                                <button
                                    onClick={applyFilters}
                                    className="inline-flex items-center px-3 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition"
                                >
                                    <FunnelIcon className="h-4 w-4 mr-1" />
                                    Filtrar
                                </button>
                                {hasActiveFilters && (
                                    <button
                                        onClick={clearFilters}
                                        className="inline-flex items-center px-3 py-2 bg-gray-200 text-gray-700 text-sm rounded-md hover:bg-gray-300 transition"
                                    >
                                        <XMarkIcon className="h-4 w-4 mr-1" />
                                        Limpar
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Data Table */}
                    <div className="bg-white shadow rounded-lg overflow-hidden">
                        <DataTable
                            data={checklists}
                            columns={columns}
                            searchable={false}
                            emptyMessage="Nenhum checklist encontrado"
                            onRowClick={handleView}
                        />
                    </div>
                </div>
            </div>

            {/* Modals */}
            <ChecklistCreateModal
                show={isCreateOpen}
                onClose={() => setIsCreateOpen(false)}
                stores={stores}
                onSuccess={() => {
                    setIsCreateOpen(false);
                    router.reload();
                }}
            />

            {selectedChecklist && (
                <>
                    <ChecklistViewModal
                        show={isViewOpen}
                        onClose={() => { setIsViewOpen(false); setSelectedChecklist(null); }}
                        checklistId={selectedChecklist.id}
                    />

                    <ChecklistEditModal
                        show={isEditOpen}
                        onClose={() => { setIsEditOpen(false); setSelectedChecklist(null); }}
                        checklistId={selectedChecklist.id}
                        onSuccess={() => {
                            setIsEditOpen(false);
                            setSelectedChecklist(null);
                            router.reload();
                        }}
                    />
                </>
            )}

            <ConfirmDialog
                show={isDeleteOpen}
                onClose={() => setIsDeleteOpen(false)}
                onConfirm={handleDelete}
                title="Excluir Checklist"
                message={deleteError || "Tem certeza que deseja excluir este checklist? Esta ação não pode ser desfeita."}
                confirmText="Excluir"
                type="danger"
            />
        </>
    );
}

function StatCard({ label, value, color }) {
    const colorClasses = {
        gray: 'bg-gray-50 border-gray-200',
        yellow: 'bg-yellow-50 border-yellow-200',
        blue: 'bg-blue-50 border-blue-200',
        green: 'bg-green-50 border-green-200',
        indigo: 'bg-indigo-50 border-indigo-200',
    };

    return (
        <div className={`rounded-lg border p-4 ${colorClasses[color] || colorClasses.gray}`}>
            <p className="text-sm text-gray-600">{label}</p>
            <p className="text-2xl font-bold text-gray-900">{value}</p>
        </div>
    );
}
