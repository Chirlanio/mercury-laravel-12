import { Head, router } from "@inertiajs/react";
import { useState, useEffect } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Modal from "@/Components/Modal";
import EmployeeModal from "@/Components/EmployeeModal";
import EmployeeCreateModal from "@/Components/EmployeeCreateModal";
import EmployeeEditModal from "@/Components/EmployeeEditModal";
import DataTable from "@/Components/DataTable";
import EmployeeAvatar from "@/Components/EmployeeAvatar";
import Button from "@/Components/Button";
import ExportAllEventsModal from "@/Components/ExportAllEventsModal";

export default function Index({ auth, employees, positions, stores, statuses, filters }) {
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
            render: (employee) => (
                <span className={`inline-flex px-2 text-xs font-semibold rounded-full ${
                    employee.is_active
                        ? 'bg-green-100 text-green-800'
                        : 'bg-red-100 text-red-800'
                }`}>
                    {employee.status}
                </span>
            )
        },
        {
            label: "Características",
            field: "characteristics",
            sortable: false,
            render: (employee) => (
                <div className="flex flex-wrap gap-1">
                    {employee.is_pcd && (
                        <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            PcD
                        </span>
                    )}
                    {employee.is_apprentice && (
                        <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                            Aprendiz
                        </span>
                    )}
                    {!employee.is_pcd && !employee.is_apprentice && (
                        <span className="text-xs text-gray-400">—</span>
                    )}
                </div>
            )
        },
        {
            label: "Ações",
            field: "actions",
            sortable: false,
            render: (employee) => (
                <div className="flex space-x-2">
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            viewEmployee(employee);
                        }}
                        variant="secondary"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        )}
                        title="Visualizar detalhes do funcionário"
                    />
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            editEmployee(employee);
                        }}
                        variant="warning"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        )}
                        title="Editar funcionário"
                    />
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            openDeleteModal(employee);
                        }}
                        variant="danger"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        )}
                        title="Excluir funcionário"
                    />
                </div>
            )
        }
    ];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Funcionários" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    )}
                                >
                                    Exportar Eventos
                                </Button>
                                <Button
                                    variant="success"
                                    onClick={() => {
                                        const currentUrl = new URL(window.location);
                                        window.location.href = `/employees/export${currentUrl.search}`;
                                    }}
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    )}
                                >
                                    Exportar
                                </Button>
                                <Button
                                    variant="primary"
                                    onClick={openCreateModal}
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    )}
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
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    )}
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
            />

            {/* Employee Edit Modal */}
            <EmployeeEditModal
                show={isEditModalOpen && selectedEmployee !== null}
                onClose={closeEditModal}
                onSuccess={handleEmployeeUpdated}
                employee={selectedEmployee}
                positions={positions}
                stores={stores}
            />

            {/* Delete Confirmation Modal */}
            <Modal show={isDeleteModalOpen} onClose={closeDeleteModal} title="Confirmar Exclusão" maxWidth="85vw">
                <div className="space-y-6">
                    <div className="flex items-start space-x-4">
                        <div className="flex-shrink-0">
                            <svg className="h-12 w-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
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
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            )}
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
        </AuthenticatedLayout>
    );
}
