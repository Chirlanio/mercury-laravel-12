import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import Modal from "@/Components/Modal";
import DataTable from "@/Components/DataTable";
import Button from "@/Components/Button";
import ActionButtons from "@/Components/ActionButtons";
import WorkScheduleCreateModal from "@/Components/WorkScheduleCreateModal";
import WorkScheduleEditModal from "@/Components/WorkScheduleEditModal";
import WorkScheduleViewModal from "@/Components/WorkScheduleViewModal";
import WorkScheduleAssignModal from "@/Components/WorkScheduleAssignModal";
import { PlusIcon, XMarkIcon, UserPlusIcon, DocumentDuplicateIcon } from "@heroicons/react/24/outline";

export default function Index({ auth, schedules, stats, filters }) {
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
    const [selectedSchedule, setSelectedSchedule] = useState(null);
    const [scheduleToDelete, setScheduleToDelete] = useState(null);
    const [deleteError, setDeleteError] = useState(null);

    const handleCreated = () => {
        setIsCreateModalOpen(false);
        router.reload();
    };

    const viewSchedule = async (schedule) => {
        try {
            const response = await fetch(`/work-schedules/${schedule.id}`);
            const data = await response.json();
            setSelectedSchedule(data.schedule);
            setIsViewModalOpen(true);
        } catch (error) {
            console.error('Erro ao carregar escala:', error);
        }
    };

    const editSchedule = async (schedule) => {
        try {
            const response = await fetch(`/work-schedules/${schedule.id}/edit`);
            const data = await response.json();
            setSelectedSchedule(data.schedule);
            setIsEditModalOpen(true);
        } catch (error) {
            console.error('Erro ao carregar escala para edição:', error);
        }
    };

    const handleEditFromView = async (schedule) => {
        setIsViewModalOpen(false);
        await editSchedule(schedule);
    };

    const handleUpdated = () => {
        setIsEditModalOpen(false);
        setSelectedSchedule(null);
        router.reload();
    };

    const openDeleteModal = (schedule) => {
        setScheduleToDelete(schedule);
        setDeleteError(null);
        setIsDeleteModalOpen(true);
    };

    const deleteSchedule = () => {
        if (!scheduleToDelete) return;

        router.delete(`/work-schedules/${scheduleToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsDeleteModalOpen(false);
                setScheduleToDelete(null);
                setDeleteError(null);
            },
            onError: (errors) => {
                setDeleteError(errors.general || 'Erro ao excluir escala.');
            }
        });
    };

    const duplicateSchedule = (schedule) => {
        router.post(`/work-schedules/${schedule.id}/duplicate`, {}, {
            preserveScroll: true,
        });
    };

    const openAssignModal = (schedule) => {
        setSelectedSchedule(schedule);
        setIsAssignModalOpen(true);
    };

    const handleAssigned = () => {
        setIsAssignModalOpen(false);
        setSelectedSchedule(null);
        router.reload();
    };

    const columns = [
        {
            field: 'name',
            label: 'Nome',
            sortable: true,
            render: (schedule) => (
                <div>
                    <span className="font-medium text-gray-900 dark:text-gray-100">
                        {schedule.name}
                    </span>
                    {schedule.is_default && (
                        <span className="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">
                            Padrão
                        </span>
                    )}
                </div>
            ),
        },
        {
            field: 'weekly_hours',
            label: 'Horas Semanais',
            sortable: true,
            render: (schedule) => (
                <span className="text-gray-900 dark:text-gray-100 font-medium">
                    {schedule.weekly_hours}
                </span>
            ),
        },
        {
            field: 'work_days_label',
            label: 'Dias',
            sortable: false,
            render: (schedule) => (
                <span className="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                    {schedule.work_days_label}
                </span>
            ),
        },
        {
            field: 'employee_count',
            label: 'Funcionários',
            sortable: false,
            render: (schedule) => (
                <span className={`px-2 py-1 text-xs font-semibold rounded-full ${
                    schedule.employee_count > 0
                        ? 'bg-green-100 text-green-800'
                        : 'bg-gray-100 text-gray-800'
                }`}>
                    {schedule.employee_count}
                </span>
            ),
        },
        {
            field: 'is_active',
            label: 'Status',
            sortable: true,
            render: (schedule) => (
                <span className={`px-2 py-1 text-xs font-semibold rounded-full ${
                    schedule.is_active
                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                }`}>
                    {schedule.is_active ? 'Ativa' : 'Inativa'}
                </span>
            ),
        },
        {
            field: 'actions',
            label: 'Ações',
            sortable: false,
            render: (schedule) => (
                <ActionButtons
                    onView={() => viewSchedule(schedule)}
                    onEdit={() => editSchedule(schedule)}
                    onDelete={() => openDeleteModal(schedule)}
                >
                    <ActionButtons.Custom
                        variant="primary"
                        icon={UserPlusIcon}
                        title="Atribuir Funcionário"
                        onClick={() => openAssignModal(schedule)}
                    />
                    <ActionButtons.Custom
                        variant="secondary"
                        icon={DocumentDuplicateIcon}
                        title="Duplicar"
                        onClick={() => duplicateSchedule(schedule)}
                    />
                </ActionButtons>
            ),
        },
    ];

    return (
        <>
            <Head title="Escalas de Trabalho" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Escalas de Trabalho
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie templates de escalas e atribua funcionários
                                </p>
                            </div>
                            <Button
                                variant="primary"
                                onClick={() => setIsCreateModalOpen(true)}
                                icon={PlusIcon}
                            >
                                Nova Escala
                            </Button>
                        </div>
                    </div>

                    {/* Stats Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <div className="text-sm font-medium text-gray-500">Total</div>
                            <div className="text-2xl font-bold text-gray-900">{stats.total}</div>
                        </div>
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <div className="text-sm font-medium text-gray-500">Ativas</div>
                            <div className="text-2xl font-bold text-green-600">{stats.active}</div>
                        </div>
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <div className="text-sm font-medium text-gray-500">Inativas</div>
                            <div className="text-2xl font-bold text-red-600">{stats.inactive}</div>
                        </div>
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <div className="text-sm font-medium text-gray-500">Funcionários Atribuídos</div>
                            <div className="text-2xl font-bold text-indigo-600">{stats.assigned_employees}</div>
                        </div>
                    </div>

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
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
                                    <option value="">Todos</option>
                                    <option value="active">Ativas</option>
                                    <option value="inactive">Inativas</option>
                                </select>
                            </div>
                            <div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="h-[42px] w-[150px]"
                                    onClick={() => {
                                        router.visit('/work-schedules', {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                    disabled={!filters.status}
                                    icon={XMarkIcon}
                                >
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={schedules}
                        columns={columns}
                        searchPlaceholder="Buscar por nome da escala..."
                        emptyMessage="Nenhuma escala encontrada"
                        perPageOptions={[15, 25, 50]}
                    />
                </div>
            </div>

            {/* Modal de Cadastro */}
            <WorkScheduleCreateModal
                isOpen={isCreateModalOpen}
                onClose={() => setIsCreateModalOpen(false)}
                onSuccess={handleCreated}
            />

            {/* Modal de Visualização */}
            <WorkScheduleViewModal
                isOpen={isViewModalOpen && selectedSchedule !== null}
                onClose={() => { setIsViewModalOpen(false); setSelectedSchedule(null); }}
                schedule={selectedSchedule}
                onEdit={handleEditFromView}
                onAssign={(schedule) => { setIsViewModalOpen(false); openAssignModal(schedule); }}
            />

            {/* Modal de Edição */}
            <WorkScheduleEditModal
                isOpen={isEditModalOpen && selectedSchedule !== null}
                onClose={() => { setIsEditModalOpen(false); setSelectedSchedule(null); }}
                onSuccess={handleUpdated}
                schedule={selectedSchedule}
            />

            {/* Modal de Atribuição */}
            <WorkScheduleAssignModal
                isOpen={isAssignModalOpen && selectedSchedule !== null}
                onClose={() => { setIsAssignModalOpen(false); setSelectedSchedule(null); }}
                onSuccess={handleAssigned}
                schedule={selectedSchedule}
            />

            {/* Modal de Confirmação de Exclusão */}
            <Modal show={isDeleteModalOpen} onClose={() => { setIsDeleteModalOpen(false); setScheduleToDelete(null); setDeleteError(null); }}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Confirmar Exclusão
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Tem certeza que deseja excluir a escala <strong>{scheduleToDelete?.name}</strong>? Esta ação não pode ser desfeita.
                    </p>
                    {deleteError && (
                        <div className="mt-3 p-3 bg-red-50 border border-red-200 rounded-md">
                            <p className="text-sm text-red-600">{deleteError}</p>
                        </div>
                    )}
                    <div className="mt-6 flex justify-end gap-3">
                        <Button
                            type="button"
                            onClick={() => { setIsDeleteModalOpen(false); setScheduleToDelete(null); setDeleteError(null); }}
                            className="bg-gray-200 text-gray-800 hover:bg-gray-300"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            onClick={deleteSchedule}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Excluir
                        </Button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
