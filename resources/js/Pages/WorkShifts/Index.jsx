import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Modal from "@/Components/Modal";
import DataTable from "@/Components/DataTable";
import Button from "@/Components/Button";
import WorkShiftCreateModal from "@/Components/WorkShiftCreateModal";
import WorkShiftViewModal from "@/Components/WorkShiftViewModal";
import WorkShiftEditModal from "@/Components/WorkShiftEditModal";
import WorkShiftExportModal from "@/Components/WorkShiftExportModal";

export default function Index({ auth, workShifts, employees, stores, types, filters }) {
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isExportModalOpen, setIsExportModalOpen] = useState(false);
    const [selectedWorkShift, setSelectedWorkShift] = useState(null);
    const [workShiftToDelete, setWorkShiftToDelete] = useState(null);

    const openCreateModal = () => {
        setIsCreateModalOpen(true);
    };

    const closeCreateModal = () => {
        setIsCreateModalOpen(false);
    };

    const handleWorkShiftCreated = () => {
        router.reload();
    };

    const viewWorkShift = async (workShift) => {
        try {
            const response = await fetch(`/work-shifts/${workShift.id}`);
            const data = await response.json();
            setSelectedWorkShift(data.workShift);
            setIsViewModalOpen(true);
        } catch (error) {
            console.error('Erro ao carregar dados da jornada:', error);
        }
    };

    const closeViewModal = () => {
        setIsViewModalOpen(false);
        setSelectedWorkShift(null);
    };

    const editWorkShift = async (workShift) => {
        try {
            const response = await fetch(`/work-shifts/${workShift.id}/edit`);
            const data = await response.json();
            setSelectedWorkShift(data.workShift);
            setIsEditModalOpen(true);
        } catch (error) {
            console.error('Erro ao carregar dados da jornada para edição:', error);
        }
    };

    const handleEditFromView = async (workShift) => {
        closeViewModal();
        await editWorkShift(workShift);
    };

    const closeEditModal = () => {
        setIsEditModalOpen(false);
        setSelectedWorkShift(null);
    };

    const handleWorkShiftUpdated = () => {
        closeEditModal();
        router.reload();
    };

    const openDeleteModal = (workShift) => {
        setWorkShiftToDelete(workShift);
        setIsDeleteModalOpen(true);
    };

    const closeDeleteModal = () => {
        setIsDeleteModalOpen(false);
        setWorkShiftToDelete(null);
    };

    const deleteWorkShift = () => {
        if (!workShiftToDelete) return;

        router.delete(`/work-shifts/${workShiftToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeDeleteModal();
            },
            onError: (errors) => {
                console.error('Erro ao excluir jornada:', errors);
            }
        });
    };

    const openExportModal = () => {
        setIsExportModalOpen(true);
    };

    const closeExportModal = () => {
        setIsExportModalOpen(false);
    };

    const columns = [
        {
            field: 'employee_name',
            label: 'Funcionário',
            sortable: true,
            render: (workShift) => (
                <span className="font-medium text-gray-900 dark:text-gray-100">
                    {workShift.employee_short_name || workShift.employee_name}
                </span>
            ),
        },
        {
            field: 'date',
            label: 'Data',
            sortable: true,
        },
        {
            field: 'start_time',
            label: 'Hora Início',
            sortable: true,
        },
        {
            field: 'end_time',
            label: 'Hora Término',
            sortable: true,
        },
        {
            field: 'type_label',
            label: 'Tipo',
            sortable: false,
            render: (workShift) => {
                const colors = {
                    'Abertura': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                    'Fechamento': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                    'Integral': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                    'Compensar': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                };
                return (
                    <span className={`px-2 py-1 text-xs font-semibold rounded-full ${colors[workShift.type_label] || 'bg-gray-100 text-gray-800'}`}>
                        {workShift.type_label}
                    </span>
                );
            },
        },
        {
            field: 'actions',
            label: 'Ações',
            sortable: false,
            render: (workShift) => (
                <div className="flex space-x-2">
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            viewWorkShift(workShift);
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
                        title="Visualizar detalhes da jornada"
                    />
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            editWorkShift(workShift);
                        }}
                        variant="warning"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        )}
                        title="Editar jornada"
                    />
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            openDeleteModal(workShift);
                        }}
                        variant="danger"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        )}
                        title="Excluir jornada"
                    />
                </div>
            ),
        },
    ];


    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Controle de Jornada" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Controle de Jornada
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie e visualize as jornadas de trabalho dos funcionários
                                </p>
                            </div>
                            <div className="flex gap-3">
                                <Button
                                    variant="secondary"
                                    onClick={openExportModal}
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
                                    Nova Jornada
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

                            {/* Filtro de Tipo */}
                            <div>
                                <label htmlFor="type-filter" className="block text-sm font-medium text-gray-700 mb-2">
                                    Filtrar por Tipo
                                </label>
                                <select
                                    id="type-filter"
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={filters.type || ''}
                                    onChange={(e) => {
                                        const currentUrl = new URL(window.location);
                                        if (e.target.value) {
                                            currentUrl.searchParams.set('type', e.target.value);
                                        } else {
                                            currentUrl.searchParams.delete('type');
                                        }
                                        currentUrl.searchParams.delete('page');
                                        router.visit(currentUrl.toString(), {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <option value="">Todos os tipos</option>
                                    {types.map((type) => (
                                        <option key={type.value} value={type.value}>
                                            {type.label}
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
                                        router.visit('/work-shifts', {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                    disabled={!((filters.store && filters.store !== '') || (filters.type && filters.type !== ''))}
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
                        data={workShifts}
                        columns={columns}
                        searchPlaceholder="Buscar por funcionário..."
                        emptyMessage="Nenhuma jornada encontrada"
                        perPageOptions={[15, 25, 50, 100]}
                    />
                </div>
            </div>

            {/* Modal de Cadastro */}
            <WorkShiftCreateModal
                isOpen={isCreateModalOpen}
                onClose={closeCreateModal}
                onSuccess={handleWorkShiftCreated}
                employees={employees}
            />

            {/* Modal de Visualização */}
            <WorkShiftViewModal
                isOpen={isViewModalOpen && selectedWorkShift !== null}
                onClose={closeViewModal}
                workShift={selectedWorkShift}
                onEdit={handleEditFromView}
            />

            {/* Modal de Edição */}
            <WorkShiftEditModal
                isOpen={isEditModalOpen && selectedWorkShift !== null}
                onClose={closeEditModal}
                onSuccess={handleWorkShiftUpdated}
                workShift={selectedWorkShift}
                employees={employees}
            />

            {/* Modal de Confirmação de Exclusão */}
            <Modal show={isDeleteModalOpen} onClose={closeDeleteModal}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Confirmar Exclusão
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Tem certeza que deseja excluir esta jornada? Esta ação não pode ser desfeita.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <Button
                            type="button"
                            onClick={closeDeleteModal}
                            className="bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            onClick={deleteWorkShift}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Excluir
                        </Button>
                    </div>
                </div>
            </Modal>

            {/* Modal de Exportação */}
            <WorkShiftExportModal
                show={isExportModalOpen}
                onClose={closeExportModal}
                employees={employees}
                stores={stores}
                types={types}
                currentFilters={filters}
            />
        </AuthenticatedLayout>
    );
}
