import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Modal from "@/Components/Modal";
import Button from "@/Components/Button";
import SaleStatisticsCards from "@/Components/SaleStatisticsCards";
import SaleCreateModal from "@/Components/SaleCreateModal";
import SaleEditModal from "@/Components/SaleEditModal";
import SaleSyncModal from "@/Components/SaleSyncModal";
import SaleBulkDeleteModal from "@/Components/SaleBulkDeleteModal";
import SalesHierarchyTable from "@/Components/SalesHierarchyTable";
import EmployeeDailySalesModal from "@/Components/EmployeeDailySalesModal";

export default function Index({ auth, salesByStore, grandTotals, stores, filters, cigamAvailable }) {
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isSyncModalOpen, setIsSyncModalOpen] = useState(false);
    const [isBulkDeleteModalOpen, setIsBulkDeleteModalOpen] = useState(false);
    const [selectedSale, setSelectedSale] = useState(null);
    const [saleToDelete, setSaleToDelete] = useState(null);
    const [deleteError, setDeleteError] = useState(null);

    // Employee daily sales modal state
    const [isDailyModalOpen, setIsDailyModalOpen] = useState(false);
    const [dailyModalParams, setDailyModalParams] = useState({ employeeId: null, storeId: null });

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
        setIsCreateModalOpen(false);
        router.reload();
    };

    const handleEmployeeClick = (employeeId, storeId) => {
        setDailyModalParams({ employeeId, storeId });
        setIsDailyModalOpen(true);
    };

    const handleEditSale = (sale) => {
        setIsDailyModalOpen(false);
        setSelectedSale(sale);
        setIsEditModalOpen(true);
    };

    const handleDeleteSaleFromDaily = (sale) => {
        setIsDailyModalOpen(false);
        setSaleToDelete(sale);
        setDeleteError(null);
        setIsDeleteModalOpen(true);
    };

    const handleUpdated = () => {
        setIsEditModalOpen(false);
        setSelectedSale(null);
        router.reload();
    };

    const deleteSale = () => {
        if (!saleToDelete) return;

        router.delete(`/sales/${saleToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsDeleteModalOpen(false);
                setSaleToDelete(null);
                setDeleteError(null);
            },
            onError: (errors) => {
                setDeleteError(errors.general || 'Erro ao excluir venda.');
            },
        });
    };

    const clearFilters = () => {
        router.get('/sales', {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Vendas" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Vendas
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie registros de vendas por loja e funcionário
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant="info"
                                    onClick={() => setIsSyncModalOpen(true)}
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    )}
                                >
                                    Sincronizar
                                </Button>
                                <Button
                                    variant="danger"
                                    onClick={() => setIsBulkDeleteModalOpen(true)}
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    )}
                                >
                                    Excluir Período
                                </Button>
                                <Button
                                    variant="primary"
                                    onClick={() => setIsCreateModalOpen(true)}
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    )}
                                >
                                    Nova Venda
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Stats Cards */}
                    <SaleStatisticsCards
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
                                    disabled={!filters.store_id && !filters.search}
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

                    {/* Hierarchy Table */}
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
                isOpen={isCreateModalOpen}
                onClose={() => setIsCreateModalOpen(false)}
                onSuccess={handleCreated}
                stores={stores}
            />

            {/* Modal de Vendas Diárias */}
            <EmployeeDailySalesModal
                isOpen={isDailyModalOpen}
                onClose={() => { setIsDailyModalOpen(false); setDailyModalParams({ employeeId: null, storeId: null }); }}
                employeeId={dailyModalParams.employeeId}
                storeId={dailyModalParams.storeId}
                month={currentMonth}
                year={currentYear}
                stores={stores}
                onEditSale={handleEditSale}
                onDeleteSale={handleDeleteSaleFromDaily}
            />

            {/* Modal de Edição */}
            <SaleEditModal
                isOpen={isEditModalOpen && selectedSale !== null}
                onClose={() => { setIsEditModalOpen(false); setSelectedSale(null); }}
                onSuccess={handleUpdated}
                sale={selectedSale}
                stores={stores}
            />

            {/* Modal de Sincronização */}
            <SaleSyncModal
                isOpen={isSyncModalOpen}
                onClose={() => setIsSyncModalOpen(false)}
                stores={stores}
                cigamAvailable={cigamAvailable}
            />

            {/* Modal de Exclusão em Lote */}
            <SaleBulkDeleteModal
                isOpen={isBulkDeleteModalOpen}
                onClose={() => setIsBulkDeleteModalOpen(false)}
                onSuccess={() => { setIsBulkDeleteModalOpen(false); router.reload(); }}
                stores={stores}
            />

            {/* Modal de Confirmação de Exclusão Individual */}
            <Modal show={isDeleteModalOpen} onClose={() => { setIsDeleteModalOpen(false); setSaleToDelete(null); setDeleteError(null); }}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Confirmar Exclusão
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Tem certeza que deseja excluir esta venda de <strong>{saleToDelete?.employee_name || saleToDelete?.store_name}</strong> em <strong>{saleToDelete?.date_sales}</strong>? Esta ação não pode ser desfeita.
                    </p>
                    {deleteError && (
                        <div className="mt-3 p-3 bg-red-50 border border-red-200 rounded-md">
                            <p className="text-sm text-red-600">{deleteError}</p>
                        </div>
                    )}
                    <div className="mt-6 flex justify-end gap-3">
                        <Button
                            type="button"
                            onClick={() => { setIsDeleteModalOpen(false); setSaleToDelete(null); setDeleteError(null); }}
                            className="bg-gray-200 text-gray-800 hover:bg-gray-300"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            onClick={deleteSale}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Excluir
                        </Button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
