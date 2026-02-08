import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Modal from "@/Components/Modal";
import DataTable from "@/Components/DataTable";
import Button from "@/Components/Button";
import SaleStatisticsCards from "@/Components/SaleStatisticsCards";
import SaleCreateModal from "@/Components/SaleCreateModal";
import SaleEditModal from "@/Components/SaleEditModal";
import SaleViewModal from "@/Components/SaleViewModal";
import SaleSyncModal from "@/Components/SaleSyncModal";
import SaleBulkDeleteModal from "@/Components/SaleBulkDeleteModal";

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
};

export default function Index({ auth, sales, stores, filters, cigamAvailable }) {
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isSyncModalOpen, setIsSyncModalOpen] = useState(false);
    const [isBulkDeleteModalOpen, setIsBulkDeleteModalOpen] = useState(false);
    const [selectedSale, setSelectedSale] = useState(null);
    const [saleToDelete, setSaleToDelete] = useState(null);
    const [deleteError, setDeleteError] = useState(null);

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
        const currentUrl = new URL(window.location);
        if (value) {
            currentUrl.searchParams.set(key, value);
        } else {
            currentUrl.searchParams.delete(key);
        }
        currentUrl.searchParams.delete('page');
        router.visit(currentUrl.toString(), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleCreated = () => {
        setIsCreateModalOpen(false);
        router.reload();
    };

    const viewSale = async (sale) => {
        try {
            const response = await fetch(`/sales/${sale.id}`);
            const data = await response.json();
            setSelectedSale(data.sale);
            setIsViewModalOpen(true);
        } catch (error) {
            console.error('Erro ao carregar venda:', error);
        }
    };

    const editSale = async (sale) => {
        try {
            const response = await fetch(`/sales/${sale.id}/edit`);
            const data = await response.json();
            setSelectedSale(data.sale);
            setIsEditModalOpen(true);
        } catch (error) {
            console.error('Erro ao carregar venda para edição:', error);
        }
    };

    const handleEditFromView = async (sale) => {
        setIsViewModalOpen(false);
        await editSale(sale);
    };

    const handleUpdated = () => {
        setIsEditModalOpen(false);
        setSelectedSale(null);
        router.reload();
    };

    const openDeleteModal = (sale) => {
        setSaleToDelete(sale);
        setDeleteError(null);
        setIsDeleteModalOpen(true);
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
        router.visit('/sales', {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const columns = [
        {
            field: 'date_sales',
            label: 'Data',
            sortable: true,
            render: (sale) => (
                <span className="text-gray-900 dark:text-gray-100 font-medium">
                    {sale.date_sales}
                </span>
            ),
        },
        {
            field: 'store_name',
            label: 'Loja',
            sortable: false,
            render: (sale) => (
                <span className="text-gray-900 dark:text-gray-100">
                    {sale.store_name}
                </span>
            ),
        },
        {
            field: 'employee_name',
            label: 'Funcionário',
            sortable: false,
            render: (sale) => (
                <span className="text-gray-900 dark:text-gray-100">
                    {sale.employee_name}
                </span>
            ),
        },
        {
            field: 'qtde_total',
            label: 'Qtde',
            sortable: true,
            render: (sale) => (
                <span className="text-gray-900 dark:text-gray-100 font-medium">
                    {sale.qtde_total}
                </span>
            ),
        },
        {
            field: 'total_sales',
            label: 'Valor',
            sortable: true,
            render: (sale) => (
                <span className="text-gray-900 dark:text-gray-100 font-semibold">
                    {sale.formatted_total}
                </span>
            ),
        },
        {
            field: 'source',
            label: 'Origem',
            sortable: true,
            render: (sale) => (
                <span className={`px-2 py-1 text-xs font-semibold rounded-full ${
                    sale.source === 'cigam'
                        ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                }`}>
                    {sale.source === 'cigam' ? 'CIGAM' : 'Manual'}
                </span>
            ),
        },
        {
            field: 'actions',
            label: 'Ações',
            sortable: false,
            render: (sale) => (
                <div className="flex space-x-1">
                    <Button
                        onClick={(e) => { e.stopPropagation(); viewSale(sale); }}
                        variant="secondary"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        )}
                        title="Visualizar"
                    />
                    <Button
                        onClick={(e) => { e.stopPropagation(); editSale(sale); }}
                        variant="warning"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        )}
                        title="Editar"
                    />
                    <Button
                        onClick={(e) => { e.stopPropagation(); openDeleteModal(sale); }}
                        variant="danger"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        )}
                        title="Excluir"
                    />
                </div>
            ),
        },
    ];

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

                    {/* DataTable */}
                    <DataTable
                        data={sales}
                        columns={columns}
                        searchPlaceholder="Buscar por funcionário ou loja..."
                        emptyMessage="Nenhum registro de venda encontrado"
                        perPageOptions={[25, 50, 100]}
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

            {/* Modal de Visualização */}
            <SaleViewModal
                isOpen={isViewModalOpen && selectedSale !== null}
                onClose={() => { setIsViewModalOpen(false); setSelectedSale(null); }}
                sale={selectedSale}
                onEdit={handleEditFromView}
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
                        Tem certeza que deseja excluir esta venda de <strong>{saleToDelete?.employee_name}</strong> em <strong>{saleToDelete?.date_sales}</strong>? Esta ação não pode ser desfeita.
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
