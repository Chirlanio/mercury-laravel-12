import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import { usePermissions, PERMISSIONS } from "@/Hooks/usePermissions";
import useModalManager from "@/Hooks/useModalManager";
import Button from "@/Components/Button";
import PageHeader from "@/Components/Shared/PageHeader";
import SaleStatisticsCards from "@/Components/SaleStatisticsCards";
import SaleCreateModal from "@/Components/SaleCreateModal";
import SaleEditModal from "@/Components/SaleEditModal";
import SaleBulkDeleteModal from "@/Components/SaleBulkDeleteModal";
import SalesHierarchyTable from "@/Components/SalesHierarchyTable";
import EmployeeDailySalesModal from "@/Components/EmployeeDailySalesModal";
import DeleteConfirmModal from "@/Components/Shared/DeleteConfirmModal";
import { formatDate } from '@/Utils/dateHelpers';
import { XMarkIcon } from "@heroicons/react/24/outline";

export default function Index({ salesByStore, grandTotals, stores, filters, lastMovementDate }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'edit', 'bulkDelete', 'daily']);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [isRefreshing, setIsRefreshing] = useState(false);

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
    const years = Array.from({ length: thisYear - 2019 }, (_, i) => thisYear - i);

    const handleFilterChange = (key, value) => {
        const params = {
            month: currentMonth,
            year: currentYear,
        };

        if (filters.store_id) params.store_id = filters.store_id;
        if (filters.search) params.search = filters.search;

        if (value) {
            params[key] = value;
        } else {
            delete params[key];
        }

        router.get('/sales', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleCreated = () => {
        closeModal('create');
        router.reload();
    };

    const handleEmployeeClick = (employeeId, storeId) => {
        openModal('daily', { employeeId, storeId });
    };

    const handleEditSale = (sale) => {
        closeModal('daily');
        openModal('edit', sale);
    };

    const handleDeleteSaleFromDaily = (sale) => {
        closeModal('daily');
        setDeleteTarget(sale);
    };

    const handleUpdated = () => {
        closeModal('edit');
        router.reload();
    };

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(`/sales/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    const handleRefreshFromMovements = () => {
        setIsRefreshing(true);
        router.post('/sales/refresh-from-movements', {
            month: currentMonth,
            year: currentYear,
        }, {
            preserveScroll: true,
            onFinish: () => setIsRefreshing(false),
        });
    };

    const clearFilters = () => {
        router.get('/sales', {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Vendas" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Vendas"
                        subtitle="Gerencie registros de vendas por loja e funcionário"
                        scopeBadge={lastMovementDate ? `Movimentações até ${lastMovementDate}` : null}
                        scopeBadgeColor="text-gray-400"
                        actions={[
                            {
                                type: 'refresh',
                                label: isRefreshing ? 'Atualizando...' : 'Atualizar Vendas',
                                onClick: handleRefreshFromMovements,
                                loading: isRefreshing,
                                disabled: isRefreshing,
                                visible: hasPermission(PERMISSIONS.CREATE_SALES),
                                title: 'Recalcula vendas a partir das movimentações CIGAM do período',
                            },
                            {
                                type: 'delete',
                                label: 'Excluir Período',
                                onClick: () => openModal('bulkDelete'),
                                visible: hasPermission(PERMISSIONS.DELETE_SALES),
                                title: 'Excluir todas as vendas do período filtrado',
                            },
                            {
                                type: 'create',
                                label: 'Nova Venda',
                                onClick: () => openModal('create'),
                                visible: hasPermission(PERMISSIONS.CREATE_SALES),
                            },
                        ]}
                    />

                    {/* Estatísticas */}
                    <SaleStatisticsCards
                        month={currentMonth}
                        year={currentYear}
                        storeId={filters.store_id}
                    />

                    {/* Filtros */}
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
                                    disabled={!filters.store_id && !filters.search}
                                    icon={XMarkIcon}
                                >
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Tabela Hierárquica */}
                    <SalesHierarchyTable
                        salesByStore={salesByStore}
                        grandTotals={grandTotals}
                        filters={filters}
                        onEmployeeClick={handleEmployeeClick}
                    />
                </div>
            </div>

            {/* Modal de Cadastro */}
            <SaleCreateModal
                show={modals.create}
                onClose={() => closeModal('create')}
                onSuccess={handleCreated}
                stores={stores}
            />

            {/* Modal de Vendas Diárias */}
            <EmployeeDailySalesModal
                isOpen={modals.daily}
                onClose={() => closeModal('daily')}
                employeeId={selected?.employeeId}
                storeId={selected?.storeId}
                month={currentMonth}
                year={currentYear}
                stores={stores}
                onEditSale={handleEditSale}
                onDeleteSale={handleDeleteSaleFromDaily}
            />

            {/* Modal de Edição */}
            <SaleEditModal
                show={modals.edit && selected !== null}
                onClose={() => closeModal('edit')}
                onSuccess={handleUpdated}
                sale={selected}
                stores={stores}
            />

            {/* Modal de Exclusão em Lote */}
            <SaleBulkDeleteModal
                isOpen={modals.bulkDelete}
                onClose={() => closeModal('bulkDelete')}
                onSuccess={() => { closeModal('bulkDelete'); router.reload(); }}
                stores={stores}
            />

            {/* Confirmação de Exclusão Individual */}
            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleConfirmDelete}
                itemType="venda"
                itemName={deleteTarget?.employee_name || deleteTarget?.store_name}
                details={[
                    { label: 'Data', value: formatDate(deleteTarget?.date_sales) },
                    { label: 'Valor', value: deleteTarget?.total_sales ? `R$ ${deleteTarget.total_sales}` : null },
                ]}
                processing={deleting}
            />
        </>
    );
}
