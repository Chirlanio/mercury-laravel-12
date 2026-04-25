import { Head, router } from '@inertiajs/react';
import { ArrowsUpDownIcon, XMarkIcon, ChartBarIcon, ClockIcon, CheckCircleIcon, XCircleIcon, ArrowPathIcon } from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import { useConfirm } from '@/Hooks/useConfirm';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import PageHeader from '@/Components/Shared/PageHeader';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import CreateMovementModal from './CreateMovementModal';
import EditMovementModal from './EditMovementModal';
import MovementDetailModal from './MovementDetailModal';
import TransitionModal from './TransitionModal';

const STATUS_VARIANTS = {
    pending: 'warning',
    in_progress: 'info',
    completed: 'success',
    cancelled: 'danger',
};

const TYPE_VARIANTS = {
    dismissal: 'danger',
    promotion: 'purple',
    transfer: 'info',
    reactivation: 'success',
};

export default function Index({ movements, filters = {}, statusOptions = {}, statusCounts = {}, typeOptions = {}, typeCounts = {}, selects = {} }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_PERSONNEL_MOVEMENTS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_PERSONNEL_MOVEMENTS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_PERSONNEL_MOVEMENTS);

    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'edit', 'detail', 'transition']);
    const { confirm, ConfirmDialogComponent } = useConfirm();

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
        router.visit(route('personnel-movements.index'), { preserveState: true, preserveScroll: true });
    };

    const hasActiveFilters = filters.type || filters.status || filters.store_id;

    const handleDelete = async (movement) => {
        const confirmed = await confirm({
            title: 'Excluir Movimentação',
            message: `Tem certeza que deseja excluir a movimentação de ${movement.type_label} do funcionário ${movement.employee_name}?`,
            confirmText: 'Sim, Excluir',
            cancelText: 'Cancelar',
            type: 'danger',
        });

        if (!confirmed) return;
        router.delete(route('personnel-movements.destroy', movement.id), { preserveState: true, preserveScroll: true });
    };

    const openTransition = (movement) => {
        fetch(route('personnel-movements.show', movement.id))
            .then(res => res.json())
            .then(data => {
                openModal('transition', data.movement);
            });
    };

    const columns = [
        {
            field: 'employee_name',
            label: 'Funcionário',
            sortable: true,
            render: (row) => (
                <div>
                    <div className="font-medium text-gray-900">{row.employee_name}</div>
                    <div className="text-xs text-gray-500">{row.store_name}</div>
                </div>
            ),
        },
        {
            field: 'type',
            label: 'Tipo',
            sortable: true,
            render: (row) => (
                <StatusBadge variant={TYPE_VARIANTS[row.type] || 'gray'} size="sm">
                    {row.type_label}
                </StatusBadge>
            ),
        },
        {
            field: 'effective_date',
            label: 'Data Efetiva',
            sortable: true,
            render: (row) => <span className="text-sm">{row.effective_date || '—'}</span>,
        },
        {
            field: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => (
                <StatusBadge variant={STATUS_VARIANTS[row.status] || 'gray'}>
                    {row.status_label}
                </StatusBadge>
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
            render: (row) => (
                <ActionButtons
                    onView={() => openModal('detail', row)}
                    onEdit={canEdit && row.status === 'pending' ? () => openModal('edit', row) : null}
                    onDelete={canDelete && row.status === 'pending' ? () => handleDelete(row) : null}
                >
                    {canEdit && row.status !== 'completed' && row.status !== 'cancelled' && (
                        <ActionButtons.Custom
                            variant="info"
                            icon={ArrowPathIcon}
                            title="Alterar Status"
                            onClick={() => openTransition(row)}
                        />
                    )}
                </ActionButtons>
            ),
        },
    ];

    return (
        <>
            <Head title="Movimentação de Pessoal" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Movimentação de Pessoal"
                        subtitle="Desligamento, promoção, transferência e reativação de funcionários"
                        actions={[
                            {
                                type: 'create',
                                label: 'Nova Movimentação',
                                onClick: () => openModal('create'),
                                visible: canCreate,
                            },
                        ]}
                    />

                    {/* Statistics Cards */}
                    <StatisticsGrid
                        cards={[
                            {
                                label: 'Total de Movimentações',
                                value: Object.values(statusCounts).reduce((s, v) => s + (v || 0), 0),
                                format: 'number',
                                icon: ChartBarIcon,
                                color: 'indigo',
                                sub: Object.entries(typeCounts).filter(([, v]) => v > 0).map(([, v]) => v).join(' + ') ? `${Object.values(typeCounts).reduce((s, v) => s + (v || 0), 0)} registros` : undefined,
                                active: !filters.status,
                                onClick: () => applyFilter('status', ''),
                            },
                            {
                                label: statusOptions.pending || 'Pendente',
                                value: statusCounts.pending || 0,
                                format: 'number',
                                icon: ClockIcon,
                                color: 'yellow',
                                sub: statusCounts.in_progress ? `${statusCounts.in_progress} em andamento` : undefined,
                                active: filters.status === 'pending',
                                onClick: () => applyFilter('status', filters.status === 'pending' ? '' : 'pending'),
                            },
                            {
                                label: statusOptions.completed || 'Concluído',
                                value: statusCounts.completed || 0,
                                format: 'number',
                                icon: CheckCircleIcon,
                                color: 'green',
                                active: filters.status === 'completed',
                                onClick: () => applyFilter('status', filters.status === 'completed' ? '' : 'completed'),
                            },
                            {
                                label: statusOptions.cancelled || 'Cancelado',
                                value: statusCounts.cancelled || 0,
                                format: 'number',
                                icon: XCircleIcon,
                                color: 'red',
                                active: filters.status === 'cancelled',
                                onClick: () => applyFilter('status', filters.status === 'cancelled' ? '' : 'cancelled'),
                            },
                        ]}
                    />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                                <select
                                    value={filters.type || ''}
                                    onChange={(e) => applyFilter('type', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos os Tipos</option>
                                    {Object.entries(typeOptions).map(([k, v]) => (
                                        <option key={k} value={k}>{v} ({typeCounts[k] || 0})</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select
                                    value={filters.status || ''}
                                    onChange={(e) => applyFilter('status', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos os Status</option>
                                    {Object.entries(statusOptions).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
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
                                    {(selects.stores || []).map((s) => (
                                        <option key={s.id} value={s.code}>{s.code} - {s.name}</option>
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
                        data={movements}
                        columns={columns}
                        searchPlaceholder="Buscar funcionário..."
                        emptyMessage="Nenhuma movimentação encontrada"
                        onRowClick={(row) => openModal('detail', row)}
                        perPageOptions={[15, 25, 50]}
                    />
                </div>
            </div>

            {/* Modal Criar */}
            {modals.create && (
                <CreateMovementModal
                    show={modals.create}
                    onClose={() => closeModal('create')}
                    selects={selects}
                />
            )}

            {/* Edit Modal */}
            {modals.edit && selected && (
                <EditMovementModal
                    show={modals.edit}
                    onClose={() => { closeModal('edit'); router.reload(); }}
                    movementId={selected.id}
                    selects={selects}
                />
            )}

            {/* Detail Modal */}
            {modals.detail && selected && (
                <MovementDetailModal
                    show={modals.detail}
                    onClose={() => closeModal('detail')}
                    movementId={selected.id}
                    canEdit={canEdit}
                />
            )}

            {/* Transition Modal */}
            {modals.transition && selected && (
                <TransitionModal
                    show={modals.transition}
                    onClose={() => closeModal('transition')}
                    movement={selected}
                />
            )}

            <ConfirmDialogComponent />
        </>
    );
}
