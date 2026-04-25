import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import PageHeader from '@/Components/Shared/PageHeader';
import StatusBadge from '@/Components/Shared/StatusBadge';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import StatisticsCards from '@/Components/StoreGoals/StatisticsCards';
import CreateModal from '@/Components/StoreGoals/CreateModal';
import EditModal from '@/Components/StoreGoals/EditModal';
import ViewModal from '@/Components/StoreGoals/ViewModal';
import ImportModal from '@/Components/StoreGoals/ImportModal';
import ConsultantRankingModal from '@/Components/StoreGoals/ConsultantRankingModal';
import ConfirmSalesModal from '@/Components/StoreGoals/ConfirmSalesModal';
import {
    BuildingStorefrontIcon, UsersIcon,
    XMarkIcon, CheckCircleIcon,
} from '@heroicons/react/24/outline';
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/react/24/solid';

export default function Index({ goals, stores, filters }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager([
        'create', 'edit', 'view', 'import', 'ranking', 'confirmSales',
    ]);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

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
        if (value) params[key] = value; else delete params[key];
        router.get('/store-goals', params, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        router.get('/store-goals', {}, { preserveState: true, preserveScroll: true });
    };

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(`/store-goals/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    const formatCurrency = (value) =>
        new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);

    return (
        <>
            <Head title="Metas de Loja" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Metas de Loja"
                        subtitle="Gerencie as metas de vendas por loja e período"
                        actions={[
                            {
                                type: 'reports',
                                label: 'Ranking',
                                onClick: () => openModal('ranking'),
                                title: 'Ranking de consultores no período',
                            },
                            {
                                type: 'download',
                                items: [
                                    {
                                        label: 'Por Loja (CSV)',
                                        icon: BuildingStorefrontIcon,
                                        download: `/store-goals/export/stores?month=${currentMonth}&year=${currentYear}`,
                                    },
                                    {
                                        label: 'Por Consultor (CSV)',
                                        icon: UsersIcon,
                                        download: `/store-goals/export/consultants?month=${currentMonth}&year=${currentYear}`,
                                    },
                                ],
                            },
                            {
                                type: 'import',
                                onClick: () => openModal('import'),
                                visible: hasPermission(PERMISSIONS.CREATE_STORE_GOALS),
                            },
                            {
                                type: 'create',
                                label: 'Nova Meta',
                                onClick: () => openModal('create'),
                                visible: hasPermission(PERMISSIONS.CREATE_STORE_GOALS),
                            },
                        ]}
                    />

                    {/* Estatísticas */}
                    <StatisticsCards month={currentMonth} year={currentYear} storeId={filters.store_id} />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Loja</label>
                                <select value={filters.store_id || ''} onChange={(e) => handleFilterChange('store_id', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Todas as lojas</option>
                                    {stores.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Mês</label>
                                <select value={currentMonth} onChange={(e) => handleFilterChange('month', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    {months.map(m => <option key={m.value} value={m.value}>{m.label}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Ano</label>
                                <select value={currentYear} onChange={(e) => handleFilterChange('year', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    {years.map(y => <option key={y} value={y}>{y}</option>)}
                                </select>
                            </div>
                            <div>
                                <Button variant="secondary" size="sm" className="h-[42px] w-[150px]"
                                    onClick={clearFilters} disabled={!filters.store_id} icon={XMarkIcon}>
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Tabela */}
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
                                                        <CheckCircleSolid className="w-4 h-4 text-emerald-500" title="Vendas confirmadas" />
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{goal.period_label}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">{formatCurrency(goal.goal_amount)}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">{formatCurrency(goal.super_goal)}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">{goal.business_days}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-center">
                                                <StatusBadge variant="indigo">{goal.consultant_goals_count}</StatusBadge>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right">
                                                <ActionButtons
                                                    onView={() => openModal('view', goal)}
                                                    onEdit={hasPermission(PERMISSIONS.EDIT_STORE_GOALS) ? () => openModal('edit', goal) : null}
                                                    onDelete={hasPermission(PERMISSIONS.DELETE_STORE_GOALS) ? () => setDeleteTarget(goal) : null}
                                                >
                                                    {hasPermission(PERMISSIONS.EDIT_STORE_GOALS) && (
                                                        <ActionButtons.Custom
                                                            variant={goal.has_confirmed_sales ? 'success' : 'light'}
                                                            icon={CheckCircleIcon}
                                                            title="Confirmar Vendas"
                                                            onClick={() => openModal('confirmSales', goal)}
                                                        />
                                                    )}
                                                </ActionButtons>
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={7} className="px-6 py-12 text-center text-gray-500">
                                                <p className="text-sm">Nenhuma meta encontrada para o período selecionado.</p>
                                                {hasPermission(PERMISSIONS.CREATE_STORE_GOALS) && (
                                                    <Button variant="primary" size="sm" className="mt-3" onClick={() => openModal('create')}>
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

            {/* Modais */}
            <CreateModal
                show={modals.create}
                onClose={() => closeModal('create')}
                onSuccess={() => { closeModal('create'); router.reload(); }}
                stores={stores}
            />

            <EditModal
                show={modals.edit && selected !== null}
                onClose={() => closeModal('edit')}
                onSuccess={() => { closeModal('edit'); router.reload(); }}
                goal={selected}
            />

            <ViewModal
                show={modals.view}
                onClose={() => closeModal('view')}
                goalId={selected?.id}
            />

            <ImportModal
                show={modals.import}
                onClose={() => closeModal('import')}
                onSuccess={() => { closeModal('import'); router.reload(); }}
            />

            <ConsultantRankingModal
                show={modals.ranking}
                onClose={() => closeModal('ranking')}
                month={currentMonth}
                year={currentYear}
                storeId={filters.store_id}
            />

            <ConfirmSalesModal
                show={modals.confirmSales}
                onClose={() => closeModal('confirmSales')}
                onSuccess={() => { closeModal('confirmSales'); router.reload({ only: ['goals'] }); }}
                storeGoalId={selected?.id}
            />

            {/* Delete Confirm */}
            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleConfirmDelete}
                itemType="meta"
                itemName={`${deleteTarget?.store_name} - ${deleteTarget?.period_label}`}
                warningMessage="As metas individuais dos consultores também serão removidas. Esta ação não pode ser desfeita."
                processing={deleting}
            />
        </>
    );
}
