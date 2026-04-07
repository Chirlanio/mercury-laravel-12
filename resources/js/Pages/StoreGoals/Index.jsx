import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import StatisticsCards from '@/Components/StoreGoals/StatisticsCards';
import CreateModal from '@/Components/StoreGoals/CreateModal';
import EditModal from '@/Components/StoreGoals/EditModal';
import ViewModal from '@/Components/StoreGoals/ViewModal';
import ImportModal from '@/Components/StoreGoals/ImportModal';
import ConsultantRankingModal from '@/Components/StoreGoals/ConsultantRankingModal';
import ConfirmSalesModal from '@/Components/StoreGoals/ConfirmSalesModal';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import { useConfirm } from '@/Hooks/useConfirm';

export default function Index({ auth, goals, stores, filters }) {
    const { hasPermission } = usePermissions();
    const { confirm, ConfirmDialogComponent } = useConfirm();

    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [isImportModalOpen, setIsImportModalOpen] = useState(false);
    const [isRankingModalOpen, setIsRankingModalOpen] = useState(false);
    const [isConfirmSalesOpen, setIsConfirmSalesOpen] = useState(false);
    const [confirmGoalId, setConfirmGoalId] = useState(null);
    const [selectedGoal, setSelectedGoal] = useState(null);
    const [viewGoalId, setViewGoalId] = useState(null);

    const currentMonth = filters.month || new Date().getMonth() + 1;
    const currentYear = filters.year || new Date().getFullYear();

    const months = [
        { value: 1, label: 'Janeiro' }, { value: 2, label: 'Fevereiro' },
        { value: 3, label: 'Março' }, { value: 4, label: 'Abril' },
        { value: 5, label: 'Maio' }, { value: 6, label: 'Junho' },
        { value: 7, label: 'Julho' }, { value: 8, label: 'Agosto' },
        { value: 9, label: 'Setembro' }, { value: 10, label: 'Outubro' },
        { value: 11, label: 'Novembro' }, { value: 12, label: 'Dezembro' },
    ];

    const thisYear = new Date().getFullYear();
    const years = Array.from({ length: thisYear - 2019 }, (_, i) => thisYear + 1 - i);

    const handleFilterChange = (key, value) => {
        const params = { month: currentMonth, year: currentYear };
        if (filters.store_id) params.store_id = filters.store_id;

        if (value) {
            params[key] = value;
        } else {
            delete params[key];
        }

        router.get('/store-goals', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        router.get('/store-goals', {}, { preserveState: true, preserveScroll: true });
    };

    const handleCreated = () => {
        setIsCreateModalOpen(false);
        router.reload();
    };

    const handleUpdated = () => {
        setIsEditModalOpen(false);
        setSelectedGoal(null);
        router.reload();
    };

    const handleEdit = (goal) => {
        setSelectedGoal(goal);
        setIsEditModalOpen(true);
    };

    const handleView = (goalId) => {
        setViewGoalId(goalId);
        setIsViewModalOpen(true);
    };

    const handleDelete = async (goal) => {
        const confirmed = await confirm({
            title: 'Excluir Meta',
            message: `Tem certeza que deseja excluir a meta de ${goal.store_name} - ${goal.period_label}? As metas individuais dos consultores também serão removidas.`,
            confirmText: 'Sim, Excluir',
            cancelText: 'Cancelar',
            type: 'danger',
        });

        if (!confirmed) return;

        router.delete(`/store-goals/${goal.id}`, {
            preserveScroll: true,
        });
    };

    const formatCurrency = (value) => {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
    };

    return (
        <>
            <Head title="Metas de Loja" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">Metas de Loja</h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie as metas de vendas por loja e período
                                </p>
                            </div>
                            <div className="flex gap-2 flex-wrap">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setIsRankingModalOpen(true)}
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                    )}
                                >
                                    Ranking
                                </Button>
                                <div className="relative group">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        icon={({ className }) => (
                                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        )}
                                    >
                                        Exportar
                                    </Button>
                                    <div className="absolute right-0 mt-1 w-48 bg-white rounded-md shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-10">
                                        <a
                                            href={`/store-goals/export/stores?month=${currentMonth}&year=${currentYear}`}
                                            className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                        >
                                            Por Loja (CSV)
                                        </a>
                                        <a
                                            href={`/store-goals/export/consultants?month=${currentMonth}&year=${currentYear}`}
                                            className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                        >
                                            Por Consultor (CSV)
                                        </a>
                                    </div>
                                </div>
                                {hasPermission(PERMISSIONS.CREATE_STORE_GOALS) && (
                                    <>
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => setIsImportModalOpen(true)}
                                            icon={({ className }) => (
                                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                                </svg>
                                            )}
                                        >
                                            Importar
                                        </Button>
                                        <Button
                                            variant="primary"
                                            size="sm"
                                            onClick={() => setIsCreateModalOpen(true)}
                                            icon={({ className }) => (
                                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                </svg>
                                            )}
                                        >
                                            Nova Meta
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Stats */}
                    <StatisticsCards
                        month={currentMonth}
                        year={currentYear}
                        storeId={filters.store_id}
                    />

                    {/* Filters */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Loja</label>
                                <select
                                    value={filters.store_id || ''}
                                    onChange={(e) => handleFilterChange('store_id', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todas as lojas</option>
                                    {stores.map(s => (
                                        <option key={s.id} value={s.id}>{s.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Mês</label>
                                <select
                                    value={currentMonth}
                                    onChange={(e) => handleFilterChange('month', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    {months.map(m => (
                                        <option key={m.value} value={m.value}>{m.label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Ano</label>
                                <select
                                    value={currentYear}
                                    onChange={(e) => handleFilterChange('year', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    {years.map(y => (
                                        <option key={y} value={y}>{y}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="h-[42px] w-[150px]"
                                    onClick={clearFilters}
                                    disabled={!filters.store_id}
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

                    {/* Table */}
                    <div className="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loja</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Meta</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Super Meta</th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Dias Úteis</th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Consultores</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {goals.length > 0 ? goals.map((goal) => (
                                        <tr key={goal.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="text-sm font-medium text-gray-900">{goal.store_name}</span>
                                                    {goal.has_confirmed_sales && (
                                                        <span className="inline-flex items-center" title="Vendas confirmadas">
                                                            <svg className="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                                            </svg>
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className="text-sm text-gray-600">{goal.period_label}</span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right">
                                                <span className="text-sm font-medium text-gray-900">{formatCurrency(goal.goal_amount)}</span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right">
                                                <span className="text-sm text-gray-500">{formatCurrency(goal.super_goal)}</span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-center">
                                                <span className="text-sm text-gray-600">{goal.business_days}</span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-center">
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                    {goal.consultant_goals_count}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right">
                                                <div className="flex justify-end gap-2">
                                                    <button
                                                        onClick={() => handleView(goal.id)}
                                                        className="text-indigo-600 hover:text-indigo-900"
                                                        title="Visualizar"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </button>
                                                    {hasPermission(PERMISSIONS.EDIT_STORE_GOALS) && (
                                                        <button
                                                            onClick={() => { setConfirmGoalId(goal.id); setIsConfirmSalesOpen(true); }}
                                                            className={`${goal.has_confirmed_sales ? 'text-emerald-600 hover:text-emerald-900' : 'text-gray-400 hover:text-gray-600'}`}
                                                            title="Confirmar Vendas"
                                                        >
                                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        </button>
                                                    )}
                                                    {hasPermission(PERMISSIONS.EDIT_STORE_GOALS) && (
                                                        <button
                                                            onClick={() => handleEdit(goal)}
                                                            className="text-amber-600 hover:text-amber-900"
                                                            title="Editar"
                                                        >
                                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                        </button>
                                                    )}
                                                    {hasPermission(PERMISSIONS.DELETE_STORE_GOALS) && (
                                                        <button
                                                            onClick={() => handleDelete(goal)}
                                                            className="text-red-600 hover:text-red-900"
                                                            title="Excluir"
                                                        >
                                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={7} className="px-6 py-12 text-center text-gray-500">
                                                <p className="text-sm">Nenhuma meta encontrada para o período selecionado.</p>
                                                {hasPermission(PERMISSIONS.CREATE_STORE_GOALS) && (
                                                    <Button
                                                        variant="primary"
                                                        size="sm"
                                                        className="mt-3"
                                                        onClick={() => setIsCreateModalOpen(true)}
                                                    >
                                                        Criar Meta
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modals */}
            <CreateModal
                isOpen={isCreateModalOpen}
                onClose={() => setIsCreateModalOpen(false)}
                onSuccess={handleCreated}
                stores={stores}
            />

            <EditModal
                isOpen={isEditModalOpen}
                onClose={() => { setIsEditModalOpen(false); setSelectedGoal(null); }}
                onSuccess={handleUpdated}
                goal={selectedGoal}
            />

            <ViewModal
                isOpen={isViewModalOpen}
                onClose={() => { setIsViewModalOpen(false); setViewGoalId(null); }}
                goalId={viewGoalId}
            />

            <ImportModal
                isOpen={isImportModalOpen}
                onClose={() => setIsImportModalOpen(false)}
                onSuccess={() => { setIsImportModalOpen(false); router.reload(); }}
            />

            <ConsultantRankingModal
                isOpen={isRankingModalOpen}
                onClose={() => setIsRankingModalOpen(false)}
                month={currentMonth}
                year={currentYear}
                storeId={filters.store_id}
            />

            <ConfirmSalesModal
                isOpen={isConfirmSalesOpen}
                onClose={() => { setIsConfirmSalesOpen(false); setConfirmGoalId(null); }}
                onSuccess={(msg) => {
                    setIsConfirmSalesOpen(false);
                    setConfirmGoalId(null);
                    router.reload({ only: ['goals'] });
                }}
                storeGoalId={confirmGoalId}
            />

            <ConfirmDialogComponent />
        </>
    );
}
