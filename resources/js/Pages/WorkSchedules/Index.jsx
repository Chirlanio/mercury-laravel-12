import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import { usePermissions, PERMISSIONS } from "@/Hooks/usePermissions";
import useModalManager from "@/Hooks/useModalManager";
import DataTable from "@/Components/DataTable";
import Button from "@/Components/Button";
import ActionButtons from "@/Components/ActionButtons";
import PageHeader from "@/Components/Shared/PageHeader";
import StatusBadge from "@/Components/Shared/StatusBadge";
import StatisticsGrid from "@/Components/Shared/StatisticsGrid";
import DeleteConfirmModal from "@/Components/Shared/DeleteConfirmModal";
import WorkScheduleCreateModal from "@/Components/WorkScheduleCreateModal";
import WorkScheduleEditModal from "@/Components/WorkScheduleEditModal";
import WorkScheduleViewModal from "@/Components/WorkScheduleViewModal";
import WorkScheduleAssignModal from "@/Components/WorkScheduleAssignModal";
import {
    XMarkIcon, UserPlusIcon, DocumentDuplicateIcon,
    CalendarDaysIcon, CheckCircleIcon, XCircleIcon, UsersIcon,
} from "@heroicons/react/24/outline";

export default function Index({ schedules, stats, filters }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'view', 'edit', 'assign']);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const viewSchedule = async (schedule) => {
        try {
            const response = await fetch(`/work-schedules/${schedule.id}`);
            const data = await response.json();
            openModal('view', data.schedule);
        } catch (error) {
            console.error('Erro ao carregar escala:', error);
        }
    };

    const editSchedule = async (schedule) => {
        try {
            const response = await fetch(`/work-schedules/${schedule.id}/edit`);
            const data = await response.json();
            openModal('edit', data.schedule);
        } catch (error) {
            console.error('Erro ao carregar escala para edição:', error);
        }
    };

    const handleEditFromView = async (schedule) => {
        closeModal('view', false);
        await editSchedule(schedule);
    };

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(`/work-schedules/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    const duplicateSchedule = (schedule) => {
        router.post(`/work-schedules/${schedule.id}/duplicate`, {}, { preserveScroll: true });
    };

    const statisticsCards = [
        { label: 'Total', value: stats.total, format: 'number', icon: CalendarDaysIcon, color: 'indigo' },
        { label: 'Ativas', value: stats.active, format: 'number', icon: CheckCircleIcon, color: 'green' },
        { label: 'Inativas', value: stats.inactive, format: 'number', icon: XCircleIcon, color: 'red' },
        { label: 'Funcionários Atribuídos', value: stats.assigned_employees, format: 'number', icon: UsersIcon, color: 'blue' },
    ];

    const columns = [
        {
            field: 'name', label: 'Nome', sortable: true,
            render: (s) => (
                <div className="flex items-center gap-2">
                    <span className="font-medium text-gray-900">{s.name}</span>
                    {s.is_default && <StatusBadge variant="indigo" size="sm">Padrão</StatusBadge>}
                </div>
            ),
        },
        {
            field: 'weekly_hours', label: 'Horas Semanais', sortable: true,
            render: (s) => <span className="font-medium text-gray-900">{s.weekly_hours}</span>,
        },
        {
            field: 'work_days_label', label: 'Dias',
            render: (s) => <StatusBadge variant="info">{s.work_days_label}</StatusBadge>,
        },
        {
            field: 'employee_count', label: 'Funcionários',
            render: (s) => (
                <StatusBadge variant={s.employee_count > 0 ? 'success' : 'gray'}>{s.employee_count}</StatusBadge>
            ),
        },
        {
            field: 'is_active', label: 'Status', sortable: true,
            render: (s) => (
                <StatusBadge variant={s.is_active ? 'success' : 'danger'}>{s.is_active ? 'Ativa' : 'Inativa'}</StatusBadge>
            ),
        },
        {
            field: 'actions', label: 'Ações',
            render: (s) => (
                <ActionButtons
                    onView={() => viewSchedule(s)}
                    onEdit={() => editSchedule(s)}
                    onDelete={() => setDeleteTarget(s)}
                >
                    <ActionButtons.Custom variant="primary" icon={UserPlusIcon} title="Atribuir Funcionário"
                        onClick={() => openModal('assign', s)} />
                    <ActionButtons.Custom variant="secondary" icon={DocumentDuplicateIcon} title="Duplicar"
                        onClick={() => duplicateSchedule(s)} />
                </ActionButtons>
            ),
        },
    ];

    return (
        <>
            <Head title="Escalas de Trabalho" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Escalas de Trabalho"
                        subtitle="Gerencie templates de escalas e atribua funcionários"
                        actions={[
                            {
                                type: 'create',
                                label: 'Nova Escala',
                                onClick: () => openModal('create'),
                            },
                        ]}
                    />

                    <StatisticsGrid cards={statisticsCards} cols={4} />

                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                            <div>
                                <label htmlFor="status-filter" className="block text-sm font-medium text-gray-700 mb-2">Filtrar por Status</label>
                                <select id="status-filter"
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={filters.status || ''}
                                    onChange={(e) => {
                                        const url = new URL(window.location);
                                        if (e.target.value) url.searchParams.set('status', e.target.value);
                                        else url.searchParams.delete('status');
                                        url.searchParams.delete('page');
                                        router.visit(url.toString(), { preserveState: true, preserveScroll: true });
                                    }}>
                                    <option value="">Todos</option>
                                    <option value="active">Ativas</option>
                                    <option value="inactive">Inativas</option>
                                </select>
                            </div>
                            <div>
                                <Button variant="secondary" size="sm" className="h-[42px] w-[150px]"
                                    onClick={() => router.visit('/work-schedules', { preserveState: true, preserveScroll: true })}
                                    disabled={!filters.status} icon={XMarkIcon}>
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    <DataTable data={schedules} columns={columns}
                        searchPlaceholder="Buscar por nome da escala..."
                        emptyMessage="Nenhuma escala encontrada" perPageOptions={[15, 25, 50]} />
                </div>
            </div>

            <WorkScheduleCreateModal
                show={modals.create}
                onClose={() => closeModal('create')}
                onSuccess={() => { closeModal('create'); router.reload(); }}
            />

            <WorkScheduleViewModal
                show={modals.view && selected !== null}
                onClose={() => closeModal('view')}
                schedule={selected}
                onEdit={handleEditFromView}
                onAssign={(s) => { closeModal('view', false); openModal('assign', s); }}
            />

            <WorkScheduleEditModal
                show={modals.edit && selected !== null}
                onClose={() => closeModal('edit')}
                onSuccess={() => { closeModal('edit'); router.reload(); }}
                schedule={selected}
            />

            <WorkScheduleAssignModal
                show={modals.assign && selected !== null}
                onClose={() => closeModal('assign')}
                onSuccess={() => { closeModal('assign'); router.reload(); }}
                schedule={selected}
            />

            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleConfirmDelete}
                itemType="escala"
                itemName={deleteTarget?.name}
                details={[
                    { label: 'Horas Semanais', value: deleteTarget?.weekly_hours },
                    { label: 'Funcionários', value: deleteTarget?.employee_count?.toString() },
                ]}
                processing={deleting}
            />
        </>
    );
}
