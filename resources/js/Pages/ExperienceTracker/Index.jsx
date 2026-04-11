import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ClipboardDocumentCheckIcon,
    PlusIcon,
    ExclamationTriangleIcon,
    ClockIcon,
    CheckCircleIcon,
    FunnelIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import EvaluationDetailModal from '@/Components/ExperienceTracker/EvaluationDetailModal';
import EvaluationFormModal from '@/Components/ExperienceTracker/EvaluationFormModal';

const STATUS_VARIANTS = {
    completed: 'success',
    partial: 'warning',
    pending: 'gray',
};

export default function Index({ evaluations, filters, milestoneOptions, stores, stats }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'detail']);
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState({
        search: filters?.search || '',
        milestone: filters?.milestone || '',
        store_id: filters?.store_id || '',
        status: filters?.status || '',
    });

    const canManage = hasPermission(PERMISSIONS.MANAGE_EXPERIENCE_TRACKER);
    const canFill = hasPermission(PERMISSIONS.FILL_EXPERIENCE_EVALUATION);

    const statisticsCards = [
        { label: 'Pendentes', value: stats?.total_pending ?? 0, icon: ClockIcon, color: 'yellow' },
        { label: 'Próximo Prazo', value: stats?.near_deadline ?? 0, icon: ExclamationTriangleIcon, color: 'orange' },
        { label: 'Vencidas', value: stats?.overdue ?? 0, icon: ExclamationTriangleIcon, color: 'red' },
        { label: 'Concluídas (mês)', value: stats?.completed_month ?? 0, icon: CheckCircleIcon, color: 'green' },
    ];

    const columns = [
        {
            key: 'employee',
            label: 'Colaborador',
            render: (row) => row.employee?.name || '-',
        },
        {
            key: 'milestone_label',
            label: 'Marco',
            render: (row) => <StatusBadge variant="info">{row.milestone_label}</StatusBadge>,
        },
        { key: 'store_name', label: 'Loja' },
        { key: 'milestone_date', label: 'Prazo' },
        {
            key: 'manager_status',
            label: 'Gestor',
            render: (row) => (
                <StatusBadge variant={row.manager_status === 'completed' ? 'success' : 'gray'}>
                    {row.manager_status === 'completed' ? 'Respondido' : 'Pendente'}
                </StatusBadge>
            ),
        },
        {
            key: 'employee_status',
            label: 'Colaborador',
            render: (row) => (
                <StatusBadge variant={row.employee_status === 'completed' ? 'success' : 'gray'}>
                    {row.employee_status === 'completed' ? 'Respondido' : 'Pendente'}
                </StatusBadge>
            ),
        },
        {
            key: 'overall_status',
            label: 'Status',
            render: (row) => (
                <div className="flex items-center gap-1">
                    <StatusBadge variant={STATUS_VARIANTS[row.overall_status]}>{row.overall_status_label}</StatusBadge>
                    {row.is_overdue && <ExclamationTriangleIcon className="w-4 h-4 text-red-500" title="Vencida" />}
                </div>
            ),
        },
        {
            key: 'actions',
            label: 'Ações',
            render: (row) => (
                <ActionButtons
                    onView={() => openModal('detail', row)}
                />
            ),
        },
    ];

    const applyFilters = () => {
        router.get(route('experience-tracker.index'), {
            ...Object.fromEntries(Object.entries(localFilters).filter(([_, v]) => v !== '')),
        }, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        setLocalFilters({ search: '', milestone: '', store_id: '', status: '' });
        router.get(route('experience-tracker.index'), {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Avaliação de Experiência" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Avaliação de Experiência</h1>
                            <p className="mt-1 text-sm text-gray-500">Acompanhamento do período de experiência (45/90 dias)</p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Button variant="outline" size="sm" icon={FunnelIcon} onClick={() => setShowFilters(!showFilters)}>
                                Filtros
                            </Button>
                            {canManage && (
                                <Button variant="primary" size="sm" icon={PlusIcon} onClick={() => openModal('create')}>
                                    Nova Avaliação
                                </Button>
                            )}
                        </div>
                    </div>

                    <StatisticsGrid cards={statisticsCards} />

                    {showFilters && (
                        <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Busca</label>
                                    <input type="text" placeholder="Nome do colaborador..."
                                        className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={localFilters.search} onChange={e => setLocalFilters(f => ({ ...f, search: e.target.value }))} />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Marco</label>
                                    <select className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={localFilters.milestone} onChange={e => setLocalFilters(f => ({ ...f, milestone: e.target.value }))}>
                                        <option value="">Todos</option>
                                        {Object.entries(milestoneOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Loja</label>
                                    <select className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={localFilters.store_id} onChange={e => setLocalFilters(f => ({ ...f, store_id: e.target.value }))}>
                                        <option value="">Todas</option>
                                        {stores?.map(s => <option key={s.code} value={s.code}>{s.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                    <select className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={localFilters.status} onChange={e => setLocalFilters(f => ({ ...f, status: e.target.value }))}>
                                        <option value="">Todos</option>
                                        <option value="pending">Pendente</option>
                                        <option value="completed">Concluído</option>
                                        <option value="overdue">Vencida</option>
                                    </select>
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 mt-4">
                                <Button variant="light" size="xs" icon={XMarkIcon} onClick={clearFilters}>Limpar</Button>
                                <Button variant="primary" size="xs" onClick={applyFilters}>Aplicar</Button>
                            </div>
                        </div>
                    )}

                    <DataTable data={evaluations} columns={columns} emptyMessage="Nenhuma avaliação encontrada." />
                </div>
            </div>

            <EvaluationFormModal
                show={modals.create} onClose={() => closeModal('create')}
                onSuccess={() => { closeModal('create'); router.reload(); }}
                stores={stores}
            />

            {selected && modals.detail && (
                <EvaluationDetailModal
                    show={modals.detail} onClose={() => closeModal('detail')}
                    evaluationId={selected.id} canFill={canFill}
                />
            )}
        </>
    );
}
