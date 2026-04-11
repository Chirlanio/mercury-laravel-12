import { useState, useEffect } from 'react';
import StandardModal from '@/Components/StandardModal';
import ActionButtons from '@/Components/ActionButtons';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { formatDate } from '@/Utils/dateHelpers';

const formatCurrency = (value) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

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
                month,
                year,
            });
            const response = await fetch(`/sales/employee-daily?${params}`);
            if (!response.ok) throw new Error('Erro ao carregar dados');
            setData(await response.json());
        } catch {
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

    const headerBadges = [];
    if (data?.store) {
        headerBadges.push({ text: data.store.name, className: 'bg-white/20 text-white' });
    }
    if (month && year) {
        headerBadges.push({ text: `${monthNames[(month || 1) - 1]}/${year}`, className: 'bg-white/20 text-white' });
    }

    return (
        <StandardModal
            show={isOpen}
            onClose={onClose}
            title={data?.employee?.short_name ? `Vendas Diárias — ${data.employee.short_name}` : 'Vendas Diárias'}
            headerColor="bg-gray-700"
            headerBadges={headerBadges}
            loading={loading}
            errorMessage={error}
            footer={data && (
                <StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />
            )}
        >
            {data && (
                <>
                    {/* Tabela de vendas diárias */}
                    <StandardModal.Section title="Detalhamento Diário">
                        <div className="overflow-x-auto -mx-4 -mb-4">
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
                                                <td className="px-4 py-2 text-sm text-gray-900">{formatDate(sale.date_sales)}</td>
                                                <td className="px-4 py-2 text-sm">
                                                    {sale.is_ecommerce ? (
                                                        <StatusBadge variant="purple">E-Commerce</StatusBadge>
                                                    ) : (
                                                        <span className="text-gray-700">{sale.store_name}</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2 text-center text-sm text-gray-700">{sale.qtde_total}</td>
                                                <td className="px-4 py-2 text-right text-sm font-medium text-gray-900">{formatCurrency(sale.total_sales)}</td>
                                                <td className="px-4 py-2 text-center">
                                                    <StatusBadge variant={sale.source === 'cigam' ? 'info' : 'gray'}>
                                                        {sale.source === 'cigam' ? 'CIGAM' : 'Manual'}
                                                    </StatusBadge>
                                                </td>
                                                {(onEditSale || onDeleteSale) && (
                                                    <td className="px-4 py-2 text-center">
                                                        <ActionButtons
                                                            onEdit={onEditSale ? () => handleEdit(sale) : null}
                                                            onDelete={onDeleteSale ? () => onDeleteSale(sale) : null}
                                                        />
                                                    </td>
                                                )}
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </StandardModal.Section>

                    {/* Totais */}
                    <StandardModal.Section title="Resumo">
                        <div className="space-y-1.5">
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
                            <div className="flex justify-between text-sm font-bold pt-1.5 border-t border-gray-200">
                                <span className="text-gray-900">Total:</span>
                                <span className="text-gray-900">
                                    {formatCurrency(data.totals.total)} ({data.totals.total_qtde} peças)
                                </span>
                            </div>
                        </div>
                    </StandardModal.Section>
                </>
            )}
        </StandardModal>
    );
}
