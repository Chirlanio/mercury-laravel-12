import { router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { ChevronUpIcon, ChevronDownIcon } from '@heroicons/react/24/outline';

function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (isNaN(date.getTime())) return value;
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

function isDateTimeField(field) {
    return field && (field.endsWith('_at') || field.endsWith('_date') || field === 'date');
}

export default function DataTable({
    data = { data: [], links: [], from: 0, to: 0, total: 0, per_page: 10 },
    columns = [],
    searchable = true,
    searchPlaceholder = "Pesquisar...",
    perPageOptions = [10, 25, 50, 100],
    onRowClick = null,
    className = "",
    emptyMessage = "Nenhum registro encontrado",
    selectable = false,
    selectedIds = [],
    onSelectionChange = null,
    baseUrl = null,
    onNavigate = null,
}) {
    const [search, setSearch] = useState('');
    const [sortField, setSortField] = useState('');
    const [sortDirection, setSortDirection] = useState('asc');
    const [perPage, setPerPage] = useState(data.per_page || 10);

    const allPageIds = (data.data || []).map(row => row.id);
    const allPageSelected = allPageIds.length > 0 && allPageIds.every(id => selectedIds.includes(id));

    const toggleSelectAll = () => {
        if (!onSelectionChange) return;
        if (allPageSelected) {
            onSelectionChange(selectedIds.filter(id => !allPageIds.includes(id)));
        } else {
            const merged = [...new Set([...selectedIds, ...allPageIds])];
            onSelectionChange(merged);
        }
    };

    const toggleSelectRow = (id) => {
        if (!onSelectionChange) return;
        if (selectedIds.includes(id)) {
            onSelectionChange(selectedIds.filter(sid => sid !== id));
        } else {
            onSelectionChange([...selectedIds, id]);
        }
    };

    useEffect(() => {
        setPerPage(data.per_page || 10);
    }, [data.per_page]);

    const getBaseUrl = () => baseUrl || window.location.href;

    const navigate = (url) => {
        if (onNavigate) {
            onNavigate(url);
        } else {
            router.visit(url, { preserveState: true, preserveScroll: true });
        }
    };

    const handleSort = (field) => {
        if (!field) return;

        let direction = 'asc';
        if (sortField === field && sortDirection === 'asc') {
            direction = 'desc';
        }

        setSortField(field);
        setSortDirection(direction);

        const currentUrl = new URL(getBaseUrl());
        currentUrl.searchParams.set('sort', field);
        currentUrl.searchParams.set('direction', direction);

        navigate(currentUrl.toString());
    };

    const handlePerPageChange = (newPerPage) => {
        setPerPage(newPerPage);

        const currentUrl = new URL(getBaseUrl());
        currentUrl.searchParams.set('per_page', newPerPage);
        currentUrl.searchParams.delete('page');

        navigate(currentUrl.toString());
    };

    const handleSearch = (searchValue) => {
        setSearch(searchValue);

        const currentUrl = new URL(getBaseUrl());
        if (searchValue) {
            currentUrl.searchParams.set('search', searchValue);
        } else {
            currentUrl.searchParams.delete('search');
        }
        currentUrl.searchParams.delete('page');

        navigate(currentUrl.toString());
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
                            {selectable && (
                                <th className="px-4 py-3 w-10">
                                    <input
                                        type="checkbox"
                                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        checked={allPageSelected}
                                        onChange={toggleSelectAll}
                                    />
                                </th>
                            )}
                            {columns.map((column, index) => (
                                <th
                                    key={index}
                                    className={`px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider ${
                                        column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left'
                                    } ${
                                        column.sortable ? 'cursor-pointer hover:bg-gray-100 select-none' : ''
                                    } ${column.className || ''}`}
                                    onClick={() => column.sortable && handleSort(column.field ?? column.key)}
                                    title={column.headerTitle}
                                >
                                    <div className={`flex items-center space-x-1 ${
                                        column.align === 'right' ? 'justify-end' : column.align === 'center' ? 'justify-center' : ''
                                    }`}>
                                        <span>{column.label}</span>
                                        {column.sortable && getSortIcon(column.field ?? column.key)}
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
                                    className={`${onRowClick ? 'hover:bg-gray-50 cursor-pointer' : ''} ${selectable && selectedIds.includes(row.id) ? 'bg-indigo-50' : ''}`}
                                    onClick={() => onRowClick && onRowClick(row)}
                                >
                                    {selectable && (
                                        <td className="px-4 py-4 w-10">
                                            <input
                                                type="checkbox"
                                                className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                checked={selectedIds.includes(row.id)}
                                                onChange={() => toggleSelectRow(row.id)}
                                                onClick={(e) => e.stopPropagation()}
                                            />
                                        </td>
                                    )}
                                    {columns.map((column, colIndex) => (
                                        <td
                                            key={colIndex}
                                            className={`px-6 py-4 text-sm text-gray-900 ${
                                                column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : ''
                                            } ${column.nowrap === false ? '' : 'whitespace-nowrap'} ${column.className || ''}`}
                                        >
                                            {column.render
                                                ? column.render(row, rowIndex)
                                                : isDateTimeField(column.field ?? column.key)
                                                    ? formatDateTime(row[column.field ?? column.key])
                                                    : row[column.field ?? column.key] || '-'
                                            }
                                        </td>
                                    ))}
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td
                                    colSpan={columns.length + (selectable ? 1 : 0)}
                                    className={typeof emptyMessage === 'string'
                                        ? 'px-6 py-12 text-center text-gray-500'
                                        : 'p-0'
                                    }
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
                                .map((link, index) => {
                                    // Manter todos os parâmetros da URL atual ao navegar entre páginas
                                    let url = link.url;
                                    if (url) {
                                        const linkUrl = new URL(url, window.location.origin);
                                        const currentBase = new URL(getBaseUrl());

                                        // Preservar TODOS os parâmetros da URL base que não estão no link
                                        currentBase.searchParams.forEach((value, param) => {
                                            if (!linkUrl.searchParams.has(param)) {
                                                linkUrl.searchParams.set(param, value);
                                            }
                                        });

                                        url = linkUrl.toString();
                                    }

                                    return (
                                        <button
                                            key={index}
                                            onClick={() => navigate(url)}
                                            disabled={!link.url}
                                            className={`px-3 py-2 text-sm rounded-md transition-colors ${
                                                link.active
                                                    ? 'bg-indigo-600 text-white'
                                                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
                                            } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    );
                                })
                            }
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
