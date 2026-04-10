import { Head, router } from "@inertiajs/react";
import { useState, useEffect } from "react";
import Modal from "@/Components/Modal";
import EmployeeModal from "@/Components/EmployeeModal";
import EmployeeCreateModal from "@/Components/EmployeeCreateModal";
import EmployeeEditModal from "@/Components/EmployeeEditModal";
import DataTable from "@/Components/DataTable";
import EmployeeAvatar from "@/Components/EmployeeAvatar";
import Button from "@/Components/Button";
import ActionButtons from "@/Components/ActionButtons";
import ExportAllEventsModal from "@/Components/ExportAllEventsModal";
import {
    CalendarDaysIcon, DocumentArrowDownIcon, PlusIcon,
    XMarkIcon, TrashIcon, ExclamationTriangleIcon,
} from "@heroicons/react/24/outline";

export default function Index({ auth, employees, positions, stores, statuses, educationLevels, filters }) {
    const [selectedEmployeeId, setSelectedEmployeeId] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [selectedEmployee, setSelectedEmployee] = useState(null);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [employeeToDelete, setEmployeeToDelete] = useState(null);
    const [isExportAllEventsModalOpen, setIsExportAllEventsModalOpen] = useState(false);
    const [eventTypes, setEventTypes] = useState([]);

    useEffect(() => {
        // Fetch event types
        const fetchEventTypes = async () => {
            try {
                const response = await fetch('/employees/1/events'); // Using any employee to get event types
                const data = await response.json();
                setEventTypes(data.event_types);
            } catch (error) {
                console.error('Erro ao carregar tipos de eventos:', error);
            }
        };

        fetchEventTypes();
    }, []);

    const viewEmployee = (employee) => {
        setSelectedEmployeeId(employee.id);
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setSelectedEmployeeId(null);
    };

    const openCreateModal = () => {
        setIsCreateModalOpen(true);
    };

    const closeCreateModal = () => {
        setIsCreateModalOpen(false);
    };

    const handleEmployeeCreated = () => {
        // Recarregar a página para mostrar o novo funcionário
        router.reload();
    };

    const editEmployee = async (employee) => {
        try {
            // Buscar os dados completos do funcionário para edição
            const response = await fetch(`/employees/${employee.id}/edit`);
            const data = await response.json();

            setSelectedEmployee(data.employee);
            setIsEditModalOpen(true);
        } catch (error) {
            console.error('Erro ao carregar dados do funcionário para edição:', error);
        }
    };

    const handleEditFromModal = async (employee) => {
        // Fechar modal de visualização
        closeModal();
        // Abrir modal de edição
        await editEmployee(employee);
    };

    const closeEditModal = () => {
        setIsEditModalOpen(false);
        setSelectedEmployee(null);
    };

    const handleEmployeeUpdated = () => {
        console.log('handleEmployeeUpdated called');
        // Fechar modal primeiro
        closeEditModal();
        // Recarregar a página para mostrar as alterações
        router.reload();
    };

    const openDeleteModal = (employee) => {
        setEmployeeToDelete(employee);
        setIsDeleteModalOpen(true);
    };

    const closeDeleteModal = () => {
        setIsDeleteModalOpen(false);
        setEmployeeToDelete(null);
    };

    const handleDeleteEmployee = () => {
        if (!employeeToDelete) return;

        router.delete(`/employees/${employeeToDelete.id}`, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                console.log('Funcionário deletado com sucesso');
                closeDeleteModal();
            },
            onError: (errors) => {
                console.error('Erro ao deletar funcionário:', errors);
            }
        });
    };

    // Definição das colunas da tabela
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
                        <div className="text-sm font-medium text-gray-900">
                            {employee.name}
                        </div>
                        <div className="text-sm text-gray-500">
                            {employee.short_name}
                        </div>
                    </div>
                </div>
            )
        },
        {
            label: "Cargo/Nível",
            field: "position",
            sortable: false,
            render: (employee) => (
                <div>
                    <div className="text-sm text-gray-900">
                        {employee.position || 'Não informado'}
                    </div>
                    {employee.level && (
                        <div className="text-sm text-gray-500">
                            Nível: {employee.level}
                        </div>
                    )}
                </div>
            )
        },
        {
            label: "Admissão",
            field: "admission_date",
            sortable: true,
            render: (employee) => (
                <div>
                    <div className="text-sm text-gray-900">
                        {employee.admission_date || 'Não informado'}
                    </div>
                    {employee.years_of_service !== null && (
                        <div className="text-sm text-gray-500">
                            {employee.years_of_service} {employee.years_of_service === 1 ? 'ano' : 'anos'}
                        </div>
                    )}
                </div>
            )
        },
        {
            label: "Status",
            field: "status",
            sortable: false,
            render: (employee) => {
                const statusColors = {
                    'Ativo': 'bg-green-100 text-green-800',
                    'Férias': 'bg-blue-100 text-blue-800',
                    'Licença': 'bg-yellow-100 text-yellow-800',
                    'Inativo': 'bg-red-100 text-red-800',
                    'Pendente': 'bg-gray-100 text-gray-800',
                };
                return (
                    <span className={`inline-flex px-2 text-xs font-semibold rounded-full ${
                        statusColors[employee.status] || 'bg-red-100 text-red-800'
                    }`}>
                        {employee.status}
                    </span>
                );
            }
        },
        {
            label: "Ações",
            field: "actions",
            sortable: false,
            render: (employee) => (
                <ActionButtons
                    onView={() => viewEmployee(employee)}
                    onEdit={() => editEmployee(employee)}
                    onDelete={() => openDeleteModal(employee)}
                />
            )
        }
    ];

    return (
        <>
            <Head title="Funcionários" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Funcionários
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie e visualize informações dos funcionários
                                </p>
                            </div>
                            <div className="flex gap-3">
                                <Button
                                    variant="secondary"
                                    onClick={() => setIsExportAllEventsModalOpen(true)}
                                    icon={CalendarDaysIcon}
                                >
                                    Exportar Eventos
                                </Button>
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
                                <Button
                                    variant="primary"
                                    onClick={openCreateModal}
                                    icon={PlusIcon}
                                >
                                    Novo Funcionário
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            {/* Filtro de Loja */}
                            <div>
                                <label htmlFor="store-filter" className="block text-sm font-medium text-gray-700 mb-2">
                                    Filtrar por Loja
                                </label>
                                <select
                                    id="store-filter"
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={filters.store || ''}
                                    onChange={(e) => {
                                        const currentUrl = new URL(window.location);
                                        if (e.target.value) {
                                            currentUrl.searchParams.set('store', e.target.value);
                                        } else {
                                            currentUrl.searchParams.delete('store');
                                        }
                                        currentUrl.searchParams.delete('page');
                                        router.visit(currentUrl.toString(), {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <option value="">Todas as lojas</option>
                                    {stores.map((store) => (
                                        <option key={store.code} value={store.code}>
                                            {store.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Filtro de Status */}
                            <div>
                                <label htmlFor="status-filter" className="block text-sm font-medium text-gray-700 mb-2">
                                    Filtrar por Status
                                </label>
                                <select
                                    id="status-filter"
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={filters.status || ''}
                                    onChange={(e) => {
                                        const currentUrl = new URL(window.location);
                                        if (e.target.value) {
                                            currentUrl.searchParams.set('status', e.target.value);
                                        } else {
                                            currentUrl.searchParams.delete('status');
                                        }
                                        currentUrl.searchParams.delete('page');
                                        router.visit(currentUrl.toString(), {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <option value="">Todos os status</option>
                                    {statuses.map((status) => (
                                        <option key={status.id} value={status.id}>
                                            {status.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Botão Limpar Filtros */}
                            <div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="h-[42px] w-[150px]"
                                    onClick={() => {
                                        router.visit('/employees', {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                    disabled={!((filters.store && filters.store !== '') || (filters.status !== null && filters.status !== ''))}
                                    icon={XMarkIcon}
                                >
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={employees}
                        columns={columns}
                        searchPlaceholder="Pesquisar funcionários por nome..."
                        emptyMessage="Nenhum funcionário encontrado"
                        onRowClick={viewEmployee}
                        perPageOptions={[15, 25, 50, 100]}
                    />
                </div>
            </div>

            {/* Employee View Modal */}
            <EmployeeModal
                show={isModalOpen}
                onClose={closeModal}
                employeeId={selectedEmployeeId}
                onEdit={handleEditFromModal}
                positions={positions}
                stores={stores}
            />

            {/* Employee Create Modal */}
            <EmployeeCreateModal
                show={isCreateModalOpen}
                onClose={closeCreateModal}
                onSuccess={handleEmployeeCreated}
                positions={positions}
                stores={stores}
                educationLevels={educationLevels}
            />

            {/* Employee Edit Modal */}
            <EmployeeEditModal
                show={isEditModalOpen && selectedEmployee !== null}
                onClose={closeEditModal}
                onSuccess={handleEmployeeUpdated}
                employee={selectedEmployee}
                positions={positions}
                stores={stores}
                statuses={statuses}
                educationLevels={educationLevels}
            />

            {/* Delete Confirmation Modal */}
            <Modal show={isDeleteModalOpen} onClose={closeDeleteModal} title="Confirmar Exclusão" maxWidth="85vw">
                <div className="space-y-6">
                    <div className="flex items-start space-x-4">
                        <div className="flex-shrink-0">
                            <ExclamationTriangleIcon className="h-12 w-12 text-red-600" />
                        </div>
                        <div className="flex-1">
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                Tem certeza que deseja excluir este funcionário?
                            </h3>
                            {employeeToDelete && (
                                <div className="text-sm text-gray-600 space-y-1">
                                    <p><strong>Nome:</strong> {employeeToDelete.name}</p>
                                    <p><strong>Cargo:</strong> {employeeToDelete.position}</p>
                                </div>
                            )}
                            <p className="mt-4 text-sm text-red-600 font-semibold">
                                Esta ação não pode ser desfeita. Todos os dados do funcionário serão permanentemente excluídos.
                            </p>
                        </div>
                    </div>

                    <div className="flex justify-end space-x-3 pt-4 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeDeleteModal}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="danger"
                            onClick={handleDeleteEmployee}
                            icon={TrashIcon}
                        >
                            Excluir Funcionário
                        </Button>
                    </div>
                </div>
            </Modal>

            {/* Export All Events Modal */}
            <ExportAllEventsModal
                show={isExportAllEventsModalOpen}
                onClose={() => setIsExportAllEventsModalOpen(false)}
                eventTypes={eventTypes}
                stores={stores}
            />
        </>
    );
}
