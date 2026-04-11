import { Head, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import {
    ClipboardDocumentCheckIcon,
    PlusIcon,
    ExclamationTriangleIcon,
    ClockIcon,
    CheckCircleIcon,
    FunnelIcon,
    XMarkIcon,
    ShieldCheckIcon,
    ArrowTrendingUpIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import LoadingSpinner from '@/Components/Shared/LoadingSpinner';
import EvaluationDetailModal from '@/Components/ExperienceTracker/EvaluationDetailModal';
import EvaluationFormModal from '@/Components/ExperienceTracker/EvaluationFormModal';

const TABS = [
    { key: 'evaluations', label: 'Avaliações', icon: ClipboardDocumentCheckIcon },
    { key: 'compliance', label: 'Compliance', icon: ShieldCheckIcon },
    { key: 'evolution', label: 'Evolução', icon: ArrowTrendingUpIcon },
];

const STATUS_VARIANTS = {
    completed: 'success',
    partial: 'warning',
    pending: 'gray',
};

export default function Index({ evaluations, filters, milestoneOptions, stores, stats }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'detail']);
    const [activeTab, setActiveTab] = useState('evaluations');
    const [complianceData, setComplianceData] = useState(null);
    const [complianceLoading, setComplianceLoading] = useState(false);
    const [evolutionData, setEvolutionData] = useState(null);
    const [evolutionLoading, setEvolutionLoading] = useState(false);

    useEffect(() => {
        if (activeTab === 'compliance' && !complianceData && !complianceLoading) {
            setComplianceLoading(true);
            fetch(route('experience-tracker.compliance'), { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => { setComplianceData(data.compliance); setComplianceLoading(false); })
                .catch(() => setComplianceLoading(false));
        }
        if (activeTab === 'evolution' && !evolutionData && !evolutionLoading) {
            setEvolutionLoading(true);
            fetch(route('experience-tracker.evolution'), { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => { setEvolutionData(data.evolution); setEvolutionLoading(false); })
                .catch(() => setEvolutionLoading(false));
        }
    }, [activeTab]);
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
                        {activeTab === 'evaluations' && (
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
                        )}
                    </div>

                    <StatisticsGrid cards={statisticsCards} />

                    {/* Tabs */}
                    <div className="border-b border-gray-200 mb-6">
                        <nav className="-mb-px flex space-x-8">
                            {TABS.map(tab => {
                                const Icon = tab.icon;
                                return (
                                    <button key={tab.key} onClick={() => setActiveTab(tab.key)}
                                        className={`flex items-center gap-2 py-3 px-1 border-b-2 text-sm font-medium transition-colors ${
                                            activeTab === tab.key
                                                ? 'border-indigo-500 text-indigo-600'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                        }`}>
                                        <Icon className="w-4 h-4" />
                                        {tab.label}
                                    </button>
                                );
                            })}
                        </nav>
                    </div>

                    {activeTab === 'evaluations' && showFilters && (
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

                    {activeTab === 'evaluations' && (
                        <DataTable data={evaluations} columns={columns} emptyMessage="Nenhuma avaliação encontrada." />
                    )}

                    {activeTab === 'compliance' && (
                        complianceLoading ? <LoadingSpinner size="lg" label="Carregando compliance..." fullPage /> : (
                            <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loja</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Marco</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Concluídas</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Vencidas</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Taxa (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {complianceData?.length > 0 ? complianceData.map((row, i) => (
                                            <tr key={i}>
                                                <td className="px-6 py-4 text-sm text-gray-900">{row.store_name}</td>
                                                <td className="px-6 py-4"><StatusBadge variant="info">{row.milestone} dias</StatusBadge></td>
                                                <td className="px-6 py-4 text-center text-sm">{row.total}</td>
                                                <td className="px-6 py-4 text-center text-sm text-green-600 font-medium">{row.completed}</td>
                                                <td className="px-6 py-4 text-center text-sm text-red-600 font-medium">{row.overdue}</td>
                                                <td className="px-6 py-4 text-center">
                                                    <span className={`text-sm font-medium ${row.fill_rate >= 80 ? 'text-green-600' : row.fill_rate >= 50 ? 'text-yellow-600' : 'text-red-600'}`}>
                                                        {row.fill_rate}%
                                                    </span>
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr><td colSpan={6} className="px-6 py-12 text-center text-gray-500">Nenhum dado de compliance encontrado.</td></tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )
                    )}

                    {activeTab === 'evolution' && (
                        evolutionLoading ? <LoadingSpinner size="lg" label="Carregando evolução..." fullPage /> : (
                            <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Colaborador</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loja</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Média 45d</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Média 90d</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Variação</th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Recomendação</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {evolutionData?.length > 0 ? evolutionData.map((row, i) => (
                                            <tr key={i}>
                                                <td className="px-6 py-4 text-sm font-medium text-gray-900">{row.employee_name}</td>
                                                <td className="px-6 py-4 text-sm text-gray-500">{row.store_name}</td>
                                                <td className="px-6 py-4 text-center text-sm">{row.avg_45}</td>
                                                <td className="px-6 py-4 text-center text-sm">{row.avg_90}</td>
                                                <td className="px-6 py-4 text-center">
                                                    {row.variation !== null && (
                                                        <span className={`text-sm font-medium ${row.variation > 0 ? 'text-green-600' : row.variation < 0 ? 'text-red-600' : 'text-gray-500'}`}>
                                                            {row.variation > 0 ? '+' : ''}{row.variation}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <StatusBadge variant={row.recommendation === 'yes' ? 'success' : row.recommendation === 'no' ? 'danger' : 'gray'}>
                                                        {row.recommendation === 'yes' ? 'Sim' : row.recommendation === 'no' ? 'Não' : 'Pendente'}
                                                    </StatusBadge>
                                                </td>
                                            </tr>
                                        )) : (
                                            <tr><td colSpan={6} className="px-6 py-12 text-center text-gray-500">Nenhum colaborador com ambas avaliações (45 e 90 dias) concluídas.</td></tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )
                    )}
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
