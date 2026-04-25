import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import useModalManager from "@/Hooks/useModalManager";
import DataTable from "@/Components/DataTable";
import Button from "@/Components/Button";
import ActionButtons from "@/Components/ActionButtons";
import WorkShiftCreateModal from "@/Components/WorkShiftCreateModal";
import WorkShiftViewModal from "@/Components/WorkShiftViewModal";
import WorkShiftEditModal from "@/Components/WorkShiftEditModal";
import WorkShiftExportModal from "@/Components/WorkShiftExportModal";
import DeleteConfirmModal from "@/Components/Shared/DeleteConfirmModal";
import PageHeader from "@/Components/Shared/PageHeader";
import StatusBadge from "@/Components/Shared/StatusBadge";
import StatisticsGrid from "@/Components/Shared/StatisticsGrid";
import {
    XMarkIcon,
    ClockIcon,
    CalendarDaysIcon,
    FunnelIcon,
    SunIcon,
    MoonIcon,
    ArrowsRightLeftIcon,
    UserGroupIcon,
} from "@heroicons/react/24/outline";

const TYPE_CONFIG_MAP = {
    'Abertura': { variant: 'info', icon: SunIcon },
    'Fechamento': { variant: 'warning', icon: MoonIcon },
    'Integral': { variant: 'success', icon: ClockIcon },
    'Compensar': { variant: 'danger', icon: ArrowsRightLeftIcon },
};

export default function Index({ workShifts, stats, employees, stores, types, filters }) {
    const { modals, selected, openModal, closeModal } = useModalManager(['view', 'create', 'edit', 'export', 'delete']);
    const [processing, setProcessing] = useState(false);
    
    // Filter state
    const [storeFilter, setStoreFilter] = useState(filters.store || '');
    const [typeFilter, setTypeFilter] = useState(filters.type || '');
    const [search, setSearch] = useState(filters.search || '');

    const applyFilters = () => {
        router.get('/work-shifts', {
            store: storeFilter || undefined,
            type: typeFilter || undefined,
            search: search || undefined,
        }, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        setStoreFilter('');
        setTypeFilter('');
        setSearch('');
        router.get('/work-shifts', {}, { preserveState: true, preserveScroll: true });
    };

    const handleView = async (workShift) => {
        try {
            const response = await fetch(`/work-shifts/${workShift.id}`);
            const data = await response.json();
            openModal('view', data.workShift);
        } catch (error) {
            console.error('Erro ao carregar dados da jornada:', error);
        }
    };

    const handleEdit = async (workShift) => {
        try {
            const response = await fetch(`/work-shifts/${workShift.id}/edit`);
            const data = await response.json();
            openModal('edit', data.workShift);
        } catch (error) {
            console.error('Erro ao carregar dados da jornada para edição:', error);
        }
    };

    const handleEditFromView = async (workShift) => {
        closeModal('view');
        await handleEdit(workShift);
    };

    const handleDelete = () => {
        if (!selected) return;
        setProcessing(true);
        router.delete(`/work-shifts/${selected.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal('delete');
                setProcessing(false);
            },
            onError: () => setProcessing(false),
        });
    };

    const columns = [
        {
            field: 'employee_name',
            label: 'Funcionário',
            sortable: true,
            render: (row) => (
                <div className="flex flex-col">
                    <span className="font-medium text-gray-900">{row.employee_short_name || row.employee_name}</span>
                    <span className="text-[10px] text-gray-400 font-bold uppercase">{row.employee_name}</span>
                </div>
            ),
        },
        {
            field: 'date',
            label: 'Data',
            sortable: true,
            render: (row) => <span className="text-gray-600 font-medium">{row.date}</span>
        },
        {
            field: 'period',
            label: 'Horário',
            sortable: false,
            render: (row) => (
                <div className="flex items-center gap-2">
                    <span className="text-gray-900 font-bold">{row.start_time}</span>
                    <span className="text-gray-300">—</span>
                    <span className="text-gray-900 font-bold">{row.end_time}</span>
                </div>
            ),
        },
        {
            field: 'type_label',
            label: 'Tipo',
            sortable: false,
            render: (row) => {
                const config = TYPE_CONFIG_MAP[row.type_label] || { variant: 'gray', icon: ClockIcon };
                return (
                    <StatusBadge variant={config.variant} icon={config.icon} dot>
                        {row.type_label}
                    </StatusBadge>
                );
            },
        },
        {
            field: 'actions',
            label: 'Ações',
            sortable: false,
            render: (row) => (
                <ActionButtons
                    onView={() => handleView(row)}
                    onEdit={() => handleEdit(row)}
                    onDelete={() => openModal('delete', row)}
                />
            ),
        },
    ];

    const statsCards = [
        { label: 'Total Registros', value: stats.total, color: 'gray', icon: UserGroupIcon },
        { label: 'Abertura', value: stats.abertura, color: 'blue', icon: SunIcon },
        { label: 'Integral', value: stats.integral, color: 'green', icon: ClockIcon },
        { label: 'Fechamento', value: stats.fechamento, color: 'purple', icon: MoonIcon },
        { label: 'Compensar', value: stats.compensar, color: 'red', icon: ArrowsRightLeftIcon },
    ];

    return (
        <>
            <Head title="Controle de Jornada" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Controle de Jornada"
                        icon={CalendarDaysIcon}
                        subtitle="Gerencie e visualize as jornadas de trabalho dos funcionários."
                        actions={[
                            {
                                type: 'download',
                                onClick: () => openModal('export'),
                                title: 'Exportar jornadas (XLSX/PDF)',
                            },
                            {
                                type: 'create',
                                label: 'Nova Jornada',
                                onClick: () => openModal('create'),
                            },
                        ]}
                    />

                    {/* Statistics */}
                    <StatisticsGrid cards={statsCards} />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm border border-gray-200 rounded-xl p-5 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Funcionário</label>
                                <div className="relative">
                                    <FunnelIcon className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                    <input
                                        type="text"
                                        placeholder="Pesquisar..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                        className="pl-9 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Loja</label>
                                <select
                                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    value={storeFilter}
                                    onChange={(e) => setStoreFilter(e.target.value)}
                                >
                                    <option value="">Todas as lojas</option>
                                    {stores.map((store) => (
                                        <option key={store.code} value={store.code}>{store.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Tipo</label>
                                <select
                                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    value={typeFilter}
                                    onChange={(e) => setTypeFilter(e.target.value)}
                                >
                                    <option value="">Todos os tipos</option>
                                    {types.map((type) => (
                                        <option key={type.value} value={type.value}>{type.label}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-end gap-2">
                                <Button
                                    variant="primary"
                                    onClick={applyFilters}
                                    className="flex-1"
                                    icon={FunnelIcon}
                                >
                                    Filtrar
                                </Button>
                                {(storeFilter || typeFilter || search) && (
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

                    {/* DataTable */}
                    <div className="bg-white shadow-sm border border-gray-200 rounded-xl overflow-hidden">
                        <DataTable
                            data={workShifts}
                            columns={columns}
                            searchable={false}
                            emptyMessage="Nenhuma jornada encontrada."
                            onRowClick={handleView}
                            perPageOptions={[15, 25, 50, 100]}
                        />
                    </div>
                </div>
            </div>

            {/* Modals */}
            <WorkShiftCreateModal
                show={modals.create}
                onClose={() => closeModal('create')}
                onSuccess={() => { closeModal('create'); router.reload(); }}
                employees={employees}
            />

            <WorkShiftViewModal
                show={modals.view && selected !== null}
                onClose={() => closeModal('view')}
                workShift={selected}
                onEdit={handleEditFromView}
            />

            <WorkShiftEditModal
                show={modals.edit && selected !== null}
                onClose={() => closeModal('edit')}
                onSuccess={() => { closeModal('edit'); router.reload(); }}
                workShift={selected}
                employees={employees}
            />

            <DeleteConfirmModal
                show={modals.delete}
                onClose={() => closeModal('delete')}
                onConfirm={handleDelete}
                itemType="jornada"
                itemName={selected ? `de ${selected.employee_short_name || selected.employee_name} em ${selected.date}` : ''}
                processing={processing}
            />

            <WorkShiftExportModal
                show={modals.export}
                onClose={() => closeModal('export')}
                employees={employees}
                stores={stores}
                types={types}
                currentFilters={filters}
            />
        </>
    );
}
