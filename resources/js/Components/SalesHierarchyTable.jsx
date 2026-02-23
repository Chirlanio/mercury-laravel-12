import { useState } from 'react';
import { router } from '@inertiajs/react';
import { ChevronRightIcon, ChevronDownIcon } from '@heroicons/react/24/solid';

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
};

export default function SalesHierarchyTable({ salesByStore = [], grandTotals = {}, filters = {}, onEmployeeClick }) {
    const [expandedStores, setExpandedStores] = useState({});
    const [searchValue, setSearchValue] = useState(filters.search || '');

    const toggleStore = (storeId) => {
        setExpandedStores(prev => ({
            ...prev,
            [storeId]: !prev[storeId],
        }));
    };

    const handleSearch = (e) => {
        e.preventDefault();
        const params = {
            month: filters.month,
            year: filters.year,
        };
        if (filters.store_id) params.store_id = filters.store_id;
        if (searchValue.trim()) params.search = searchValue.trim();

        router.get('/sales', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearSearch = () => {
        setSearchValue('');
        const params = {
            month: filters.month,
            year: filters.year,
        };
        if (filters.store_id) params.store_id = filters.store_id;

        router.get('/sales', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <div className="bg-white shadow-sm rounded-lg overflow-hidden">
            {/* Search bar */}
            <div className="p-4 border-b border-gray-200">
                <form onSubmit={handleSearch} className="flex gap-2">
                    <div className="relative flex-1">
                        <input
                            type="text"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            placeholder="Buscar por nome da consultora..."
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pl-10"
                        />
                        <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <button
                        type="submit"
                        className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium"
                    >
                        Buscar
                    </button>
                    {filters.search && (
                        <button
                            type="button"
                            onClick={clearSearch}
                            className="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm font-medium"
                        >
                            Limpar
                        </button>
                    )}
                </form>
            </div>

            {/* Table */}
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-8"></th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loja / Consultora</th>
                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Consultoras</th>
                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Qtde</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {salesByStore.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-6 py-12 text-center text-gray-500">
                                    Nenhum registro de venda encontrado
                                </td>
                            </tr>
                        ) : (
                            salesByStore.map((storeData) => {
                                const isExpanded = expandedStores[storeData.store_id];
                                return (
                                    <StoreRow
                                        key={storeData.store_id}
                                        storeData={storeData}
                                        isExpanded={isExpanded}
                                        onToggle={() => toggleStore(storeData.store_id)}
                                        onEmployeeClick={onEmployeeClick}
                                        filters={filters}
                                    />
                                );
                            })
                        )}
                    </tbody>
                    {salesByStore.length > 0 && (
                        <tfoot className="bg-gray-100">
                            <tr className="font-bold">
                                <td className="px-6 py-3"></td>
                                <td className="px-6 py-3 text-sm text-gray-900">TOTAL</td>
                                <td className="px-6 py-3 text-center text-sm text-gray-900">{grandTotals.total_employees || 0}</td>
                                <td className="px-6 py-3 text-center text-sm text-gray-900">{grandTotals.qtde_total || 0}</td>
                                <td className="px-6 py-3 text-right text-sm text-gray-900">{formatCurrency(grandTotals.total_sales || 0)}</td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </div>
    );
}

function StoreRow({ storeData, isExpanded, onToggle, onEmployeeClick, filters }) {
    return (
        <>
            {/* Store row */}
            <tr
                className="bg-indigo-50 hover:bg-indigo-100 cursor-pointer transition-colors"
                onClick={onToggle}
            >
                <td className="px-6 py-3">
                    {isExpanded ? (
                        <ChevronDownIcon className="w-4 h-4 text-indigo-600" />
                    ) : (
                        <ChevronRightIcon className="w-4 h-4 text-indigo-600" />
                    )}
                </td>
                <td className="px-6 py-3 text-sm font-semibold text-gray-900">
                    {storeData.store_name}
                </td>
                <td className="px-6 py-3 text-center text-sm font-medium text-gray-700">
                    {storeData.employees.length}
                </td>
                <td className="px-6 py-3 text-center text-sm font-medium text-gray-700">
                    {storeData.qtde_total}
                </td>
                <td className="px-6 py-3 text-right text-sm font-semibold text-gray-900">
                    {formatCurrency(storeData.total_sales)}
                </td>
            </tr>

            {/* Employee rows */}
            {isExpanded && storeData.employees.map((emp) => (
                <tr
                    key={`${storeData.store_id}-${emp.employee_id}`}
                    className="hover:bg-gray-50 cursor-pointer transition-colors"
                    onClick={() => onEmployeeClick && onEmployeeClick(emp.employee_id, storeData.store_id, emp.employee_name)}
                >
                    <td className="px-6 py-2"></td>
                    <td className="px-6 py-2 text-sm text-gray-700 pl-12">
                        <span className="inline-flex items-center gap-2">
                            <span className="w-2 h-2 rounded-full bg-indigo-400 flex-shrink-0"></span>
                            {emp.employee_name}
                        </span>
                    </td>
                    <td className="px-6 py-2 text-center text-sm text-gray-500"></td>
                    <td className="px-6 py-2 text-center text-sm text-gray-700">{emp.qtde_total}</td>
                    <td className="px-6 py-2 text-right text-sm font-medium text-gray-900">{formatCurrency(emp.total_sales)}</td>
                </tr>
            ))}
        </>
    );
}
