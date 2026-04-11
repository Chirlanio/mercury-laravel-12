import { Head, router } from "@inertiajs/react";
import { useState, useEffect } from "react";
import { usePermissions, PERMISSIONS } from "@/Hooks/usePermissions";
import useModalManager from "@/Hooks/useModalManager";
import EmployeeModal from "@/Components/EmployeeModal";
import DeleteConfirmModal from "@/Components/Shared/DeleteConfirmModal";
import EmployeeCreateModal from "@/Components/EmployeeCreateModal";
import EmployeeEditModal from "@/Components/EmployeeEditModal";
import ExportAllEventsModal from "@/Components/ExportAllEventsModal";
import DataTable from "@/Components/DataTable";
import EmployeeAvatar from "@/Components/EmployeeAvatar";
import Button from "@/Components/Button";
import ActionButtons from "@/Components/ActionButtons";
import StatusBadge from "@/Components/Shared/StatusBadge";
import StatisticsGrid from "@/Components/Shared/StatisticsGrid";
import {
    CalendarDaysIcon,
    DocumentArrowDownIcon,
    PlusIcon,
    XMarkIcon,
    UsersIcon,
    CheckCircleIcon,
    UserMinusIcon,
    UserGroupIcon,
    SunIcon,
    FunnelIcon,
} from "@heroicons/react/24/outline";

const STATUS_VARIANT_MAP = {
    'Ativo': 'success',
    'Férias': 'info',
    'Licença': 'warning',
    'Inativo': 'danger',
    'Pendente': 'gray',
};

export default function Index({ employees, stats, positions, stores, statuses, educationLevels, filters }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['view', 'create', 'edit', 'exportEvents', 'delete']);
    const [deleting, setDeleting] = useState(false);
    const [editEmployee, setEditEmployee] = useState(null);
    const [eventTypes, setEventTypes] = useState([]);
    
    // Filter state
    const [storeFilter, setStoreFilter] = useState(filters.store || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');

    useEffect(() => {
        const fetchEventTypes = async () => {
            try {
                const response = await fetch('/employees/1/events');
                const data = await response.json();
                setEventTypes(data.event_types);
            } catch (error) {
                console.error('Erro ao carregar tipos de eventos:', error);
            }
        };
        fetchEventTypes();
    }, []);

    const applyFilters = () => {
        router.get('/employees', {
            store: storeFilter || undefined,
            status: statusFilter || undefined,
            search: filters.search,
        }, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        setStoreFilter('');
        setStatusFilter('');
        router.get('/employees', {}, { preserveState: true, preserveScroll: true });
    };

    const handleEdit = async (employee) => {
        try {
            const response = await fetch(`/employees/${employee.id}/edit`);
            const data = await response.json();
            setEditEmployee(data.employee);
            openModal('edit');
        } catch (error) {
            console.error('Erro ao carregar dados do funcionário para edição:', error);
        }
    };

    const handleEditFromView = async (employee) => {
        closeModal('view');
        await handleEdit(employee);
    };

    const handleConfirmDelete = () => {
        if (!selected) return;
        setDeleting(true);
        router.delete(`/employees/${selected.id}`, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => { 
                closeModal('delete');
                setDeleting(false); 
            },
            onError: () => setDeleting(false),
        });
    };

    const handleCreated = () => {
        closeModal('create');
        router.reload();
    };

    const handleUpdated = () => {
        setEditEmployee(null);
        closeModal('edit');
        router.reload();
    };

    const columns = [
        {
            label: "Funcionário",
            field: "name",
            sortable: true,
            render: (employee) => (
                <div className="flex items-center">
                    <div className="flex-shrink-0 mr-4">
                        <EmployeeAvatar employee={employee} size="md" />
                    </div>
                    <div>
                        <div className="text-sm font-medium text-gray-900">{employee.name}</div>
                        <div className="text-sm text-gray-500">{employee.short_name}</div>
                    </div>
                </div>
            ),
        },
        {
            label: "Cargo/Nível",
            field: "position",
            sortable: false,
            render: (employee) => (
                <div>
                    <div className="text-sm text-gray-900">{employee.position || 'Não informado'}</div>
                    {employee.level && (
                        <div className="text-xs text-gray-500 font-medium">Nível: {employee.level}</div>
                    )}
                </div>
            ),
        },
        {
            label: "Admissão",
            field: "admission_date",
            sortable: true,
            render: (employee) => (
                <div>
                    <div className="text-sm text-gray-900">{employee.admission_date || 'Não informado'}</div>
                    {employee.years_of_service !== null && (
                        <div className="text-xs text-gray-500 font-medium">
                            {employee.years_of_service} {employee.years_of_service === 1 ? 'ano' : 'anos'} de casa
                        </div>
                    )}
                </div>
            ),
        },
        {
            label: "Status",
            field: "status",
            sortable: false,
            render: (employee) => (
                <StatusBadge variant={STATUS_VARIANT_MAP[employee.status] || 'gray'} dot>
                    {employee.status}
                </StatusBadge>
            ),
        },
        {
            label: "Ações",
            field: "actions",
            sortable: false,
            render: (employee) => (
                <ActionButtons
                    onView={() => openModal('view', employee)}
                    onEdit={hasPermission(PERMISSIONS.EDIT_USERS) ? () => handleEdit(employee) : undefined}
                    onDelete={hasPermission(PERMISSIONS.DELETE_USERS) ? () => openModal('delete', employee) : undefined}
                />
            ),
        },
    ];

    const statsCards = [
        { label: 'Total', value: stats.total, color: 'gray', icon: UsersIcon },
        { label: 'Ativos', value: stats.active, color: 'green', icon: CheckCircleIcon },
        { label: 'Em Férias', value: stats.vacation, color: 'blue', icon: SunIcon },
        { label: 'Inativos', value: stats.inactive, color: 'red', icon: UserMinusIcon },
        { label: 'PCD', value: stats.pcd, color: 'indigo', icon: UserGroupIcon },
    ];

    return (
        <>
            <Head title="Funcionários" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                                <UsersIcon className="h-8 w-8 text-indigo-600" />
                                Gestão de Funcionários
                            </h1>
                            <p className="mt-1 text-sm text-gray-500">
                                Cadastro, visualização e acompanhamento do ciclo de vida dos colaboradores.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {hasPermission(PERMISSIONS.VIEW_USERS) && (
                                <Button
                                    variant="secondary"
                                    onClick={() => openModal('exportEvents')}
                                    icon={CalendarDaysIcon}
                                >
                                    Eventos
                                </Button>
                            )}
                            {hasPermission(PERMISSIONS.VIEW_USERS) && (
                                <Button
                                    variant="success"
                                    onClick={() => {
                                        const currentUrl = new URL(window.location);
                                        window.location.href = `/employees/export${currentUrl.search}`;
                                    }}
                                    icon={DocumentArrowDownIcon}
                                >
                                    Exportar
                                </Button>
                            )}
                            {hasPermission(PERMISSIONS.CREATE_USERS) && (
                                <Button
                                    variant="primary"
                                    onClick={() => openModal('create')}
                                    icon={PlusIcon}
                                >
                                    Novo Funcionário
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Statistics */}
                    <StatisticsGrid cards={statsCards} />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm border border-gray-200 rounded-xl p-5 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label htmlFor="store-filter" className="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">
                                    Loja
                                </label>
                                <select
                                    id="store-filter"
                                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    value={storeFilter}
                                    onChange={(e) => setStoreFilter(e.target.value)}
                                >
                                    <option value="">Todas as lojas</option>
                                    {stores.map((store) => (
                                        <option key={store.code} value={store.code}>{store.code} - {store.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label htmlFor="status-filter" className="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">
                                    Status
                                </label>
                                <select
                                    id="status-filter"
                                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    value={statusFilter}
                                    onChange={(e) => setStatusFilter(e.target.value)}
                                >
                                    <option value="">Todos os status</option>
                                    {statuses.map((status) => (
                                        <option key={status.id} value={status.id}>{status.name}</option>
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
                                {(storeFilter || statusFilter) && (
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
                            data={employees}
                            columns={columns}
                            searchPlaceholder="Pesquisar funcionários por nome..."
                            emptyMessage="Nenhum funcionário encontrado."
                            onRowClick={(row) => openModal('view', row)}
                            perPageOptions={[15, 25, 50, 100]}
                        />
                    </div>
                </div>
            </div>

            {/* Employee View Modal */}
            <EmployeeModal
                show={modals.view}
                onClose={() => closeModal('view')}
                employeeId={selected?.id}
                onEdit={hasPermission(PERMISSIONS.EDIT_USERS) ? handleEditFromView : undefined}
                positions={positions}
                stores={stores}
            />

            {/* Employee Create Modal */}
            <EmployeeCreateModal
                show={modals.create}
                onClose={() => closeModal('create')}
                onSuccess={handleCreated}
                positions={positions}
                stores={stores}
                educationLevels={educationLevels}
            />

            {/* Employee Edit Modal */}
            <EmployeeEditModal
                show={modals.edit && editEmployee !== null}
                onClose={() => { setEditEmployee(null); closeModal('edit'); }}
                onSuccess={handleUpdated}
                employee={editEmployee}
                positions={positions}
                stores={stores}
                statuses={statuses}
                educationLevels={educationLevels}
            />

            {/* Export All Events Modal */}
            <ExportAllEventsModal
                show={modals.exportEvents}
                onClose={() => closeModal('exportEvents')}
                eventTypes={eventTypes}
                stores={stores}
            />

            {/* Delete Confirm Modal */}
            <DeleteConfirmModal
                show={modals.delete}
                onClose={() => closeModal('delete')}
                onConfirm={handleConfirmDelete}
                itemType="funcionário"
                itemName={selected?.name}
                details={[
                    { label: 'Cargo', value: selected?.position },
                    { label: 'Status', value: selected?.status },
                ]}
                processing={deleting}
            />
        </>
    );
}
