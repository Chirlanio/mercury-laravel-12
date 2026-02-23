import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
};

const monthNames = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];

export default function EmployeeDailySalesModal({
    isOpen,
    onClose,
    employeeId,
    storeId,
    month,
    year,
    stores = [],
    onEditSale,
    onDeleteSale,
}) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (isOpen && employeeId && storeId && month && year) {
            fetchData();
        }
        if (!isOpen) {
            setData(null);
            setError(null);
        }
    }, [isOpen, employeeId, storeId, month, year]);

    const fetchData = async () => {
        setLoading(true);
        setError(null);
        try {
            const params = new URLSearchParams({
                employee_id: employeeId,
                store_id: storeId,
                month: month,
                year: year,
            });
            const response = await fetch(`/sales/employee-daily?${params}`);
            if (!response.ok) throw new Error('Erro ao carregar dados');
            const json = await response.json();
            setData(json);
        } catch (err) {
            setError('Erro ao carregar vendas diárias. Tente novamente.');
        } finally {
            setLoading(false);
        }
    };

    const handleEdit = async (sale) => {
        if (!onEditSale) return;
        try {
            const response = await fetch(`/sales/${sale.id}/edit`);
            const json = await response.json();
            onEditSale(json.sale);
        } catch (err) {
            console.error('Erro ao carregar venda para edição:', err);
        }
    };

    const handleDelete = (sale) => {
        if (!onDeleteSale) return;
        onDeleteSale(sale);
    };

    const title = data
        ? `Vendas Diárias — ${data.employee.short_name}`
        : 'Vendas Diárias';

    return (
        <Modal show={isOpen} onClose={onClose} title={title} maxWidth="4xl">
            <div className="p-6">
                {loading && <LoadingSkeleton />}

                {error && (
                    <div className="p-4 bg-red-50 border border-red-200 rounded-md">
                        <p className="text-sm text-red-600">{error}</p>
                    </div>
                )}

                {data && !loading && (
                    <>
                        {/* Header info */}
                        <div className="flex items-center gap-4 mb-4 text-sm text-gray-600">
                            <span>Loja: <strong className="text-gray-900">{data.store.name}</strong></span>
                            <span className="text-gray-300">|</span>
                            <span>{monthNames[(month || 1) - 1]}/{year}</span>
                        </div>

                        {/* Daily sales table */}
                        <div className="overflow-x-auto border rounded-lg">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Local</th>
                                        <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Qtde</th>
                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                                        <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Origem</th>
                                        {(onEditSale || onDeleteSale) && (
                                            <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {data.daily_sales.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="px-4 py-8 text-center text-gray-500">
                                                Nenhuma venda encontrada neste período.
                                            </td>
                                        </tr>
                                    ) : (
                                        data.daily_sales.map((sale) => (
                                            <tr key={sale.id} className="hover:bg-gray-50">
                                                <td className="px-4 py-2 text-sm text-gray-900">{sale.date_sales}</td>
                                                <td className="px-4 py-2 text-sm">
                                                    {sale.is_ecommerce ? (
                                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                            E-Commerce
                                                        </span>
                                                    ) : (
                                                        <span className="text-gray-700">{sale.store_name}</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2 text-center text-sm text-gray-700">{sale.qtde_total}</td>
                                                <td className="px-4 py-2 text-right text-sm font-medium text-gray-900">{formatCurrency(sale.total_sales)}</td>
                                                <td className="px-4 py-2 text-center">
                                                    <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${
                                                        sale.source === 'cigam'
                                                            ? 'bg-blue-100 text-blue-800'
                                                            : 'bg-gray-100 text-gray-800'
                                                    }`}>
                                                        {sale.source === 'cigam' ? 'CIGAM' : 'Manual'}
                                                    </span>
                                                </td>
                                                {(onEditSale || onDeleteSale) && (
                                                    <td className="px-4 py-2 text-center">
                                                        <div className="flex justify-center gap-1">
                                                            {onEditSale && (
                                                                <button
                                                                    onClick={() => handleEdit(sale)}
                                                                    className="p-1 text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50 rounded"
                                                                    title="Editar"
                                                                >
                                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                    </svg>
                                                                </button>
                                                            )}
                                                            {onDeleteSale && (
                                                                <button
                                                                    onClick={() => handleDelete(sale)}
                                                                    className="p-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded"
                                                                    title="Excluir"
                                                                >
                                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Totals */}
                        <div className="mt-4 bg-gray-50 rounded-lg p-4 space-y-1">
                            {data.totals.store_total > 0 && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">Loja Física:</span>
                                    <span className="font-medium text-gray-900">
                                        {formatCurrency(data.totals.store_total)} ({data.totals.store_qtde} peças)
                                    </span>
                                </div>
                            )}
                            {data.totals.ecommerce_total > 0 && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-purple-600">E-Commerce:</span>
                                    <span className="font-medium text-purple-900">
                                        {formatCurrency(data.totals.ecommerce_total)} ({data.totals.ecommerce_qtde} peças)
                                    </span>
                                </div>
                            )}
                            <div className="flex justify-between text-sm font-bold pt-1 border-t border-gray-200">
                                <span className="text-gray-900">Total:</span>
                                <span className="text-gray-900">
                                    {formatCurrency(data.totals.total)} ({data.totals.total_qtde} peças)
                                </span>
                            </div>
                        </div>
                    </>
                )}

                <div className="flex justify-end mt-4 pt-4 border-t">
                    <Button variant="secondary" onClick={onClose}>
                        Fechar
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

function LoadingSkeleton() {
    return (
        <div className="animate-pulse space-y-4">
            <div className="flex gap-4">
                <div className="h-4 bg-gray-200 rounded w-1/4"></div>
                <div className="h-4 bg-gray-200 rounded w-1/6"></div>
            </div>
            <div className="border rounded-lg overflow-hidden">
                <div className="bg-gray-100 h-10"></div>
                {[...Array(5)].map((_, i) => (
                    <div key={i} className="flex gap-4 p-3 border-b">
                        <div className="h-4 bg-gray-200 rounded w-1/6"></div>
                        <div className="h-4 bg-gray-200 rounded w-1/5"></div>
                        <div className="h-4 bg-gray-200 rounded w-1/12"></div>
                        <div className="h-4 bg-gray-200 rounded w-1/6"></div>
                        <div className="h-4 bg-gray-200 rounded w-1/12"></div>
                    </div>
                ))}
            </div>
            <div className="bg-gray-100 rounded-lg h-20"></div>
        </div>
    );
}
