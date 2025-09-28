import { router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { ChevronUpIcon, ChevronDownIcon } from '@heroicons/react/24/outline';

export default function DataTable({
    data = { data: [], links: [], from: 0, to: 0, total: 0, per_page: 10 },
    columns = [],
    searchable = true,
    searchPlaceholder = "Pesquisar...",
    perPageOptions = [10, 25, 50, 100],
    onRowClick = null,
    className = "",
    emptyMessage = "Nenhum registro encontrado"
}) {
    const [search, setSearch] = useState('');
    const [sortField, setSortField] = useState('');
    const [sortDirection, setSortDirection] = useState('asc');
    const [perPage, setPerPage] = useState(data.per_page || 10);

    useEffect(() => {
        setPerPage(data.per_page || 10);
    }, [data.per_page]);

    const handleSort = (field) => {
        if (!field) return;

        let direction = 'asc';
        if (sortField === field && sortDirection === 'asc') {
            direction = 'desc';
        }

        setSortField(field);
        setSortDirection(direction);

        // Atualizar URL com parâmetros de ordenação
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('sort', field);
        currentUrl.searchParams.set('direction', direction);

        router.visit(currentUrl.toString(), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePerPageChange = (newPerPage) => {
        setPerPage(newPerPage);

        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('per_page', newPerPage);
        currentUrl.searchParams.delete('page'); // Reset para primeira página

        router.visit(currentUrl.toString(), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSearch = (searchValue) => {
        setSearch(searchValue);

        const currentUrl = new URL(window.location);
        if (searchValue) {
            currentUrl.searchParams.set('search', searchValue);
        } else {
            currentUrl.searchParams.delete('search');
        }
        currentUrl.searchParams.delete('page'); // Reset para primeira página

        router.visit(currentUrl.toString(), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const getSortIcon = (field) => {
        if (sortField !== field) {
            return <ChevronUpIcon className="w-4 h-4 text-gray-400" />;
        }

        return sortDirection === 'asc'
            ? <ChevronUpIcon className="w-4 h-4 text-gray-600" />
            : <ChevronDownIcon className="w-4 h-4 text-gray-600" />;
    };

    return (
        <div className={`bg-white overflow-hidden shadow-sm rounded-lg ${className}`}>
            {/* Header com busca e controles */}
            <div className="p-6 border-b border-gray-200">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    {/* Busca */}
                    {searchable && (
                        <div className="flex-1 max-w-md">
                            <input
                                type="text"
                                placeholder={searchPlaceholder}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={search}
                                onChange={(e) => handleSearch(e.target.value)}
                            />
                        </div>
                    )}

                    {/* Seletor de registros por página */}
                    <div className="flex items-center space-x-2">
                        <span className="text-sm text-gray-700">Mostrar:</span>
                        <select
                            value={perPage}
                            onChange={(e) => handlePerPageChange(Number(e.target.value))}
                            className="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {perPageOptions.map(option => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                        <span className="text-sm text-gray-700">registros</span>
                    </div>
                </div>
            </div>

            {/* Tabela */}
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            {columns.map((column, index) => (
                                <th
                                    key={index}
                                    className={`px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider ${
                                        column.sortable ? 'cursor-pointer hover:bg-gray-100 select-none' : ''
                                    }`}
                                    onClick={() => column.sortable && handleSort(column.field)}
                                >
                                    <div className="flex items-center space-x-1">
                                        <span>{column.label}</span>
                                        {column.sortable && getSortIcon(column.field)}
                                    </div>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {data.data && data.data.length > 0 ? (
                            data.data.map((row, rowIndex) => (
                                <tr
                                    key={rowIndex}
                                    className={onRowClick ? 'hover:bg-gray-50 cursor-pointer' : ''}
                                    onClick={() => onRowClick && onRowClick(row)}
                                >
                                    {columns.map((column, colIndex) => (
                                        <td
                                            key={colIndex}
                                            className="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                        >
                                            {column.render
                                                ? column.render(row, rowIndex)
                                                : row[column.field] || '-'
                                            }
                                        </td>
                                    ))}
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-6 py-12 text-center text-gray-500"
                                >
                                    {emptyMessage}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Footer com paginação e informações */}
            <div className="bg-gray-50 px-6 py-3 border-t border-gray-200">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    {/* Informações dos registros */}
                    <div className="text-sm text-gray-700">
                        Mostrando {data.from || 0} até {data.to || 0} de {data.total || 0} registros
                    </div>

                    {/* Paginação */}
                    {data.links && data.links.length > 0 && (
                        <div className="flex space-x-1">
                            {data.links
                                .filter(link => link.url !== null)
                                .map((link, index) => (
                                    <button
                                        key={index}
                                        onClick={() => router.visit(link.url)}
                                        disabled={!link.url}
                                        className={`px-3 py-2 text-sm rounded-md transition-colors ${
                                            link.active
                                                ? 'bg-indigo-600 text-white'
                                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
                                        } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))
                            }
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}