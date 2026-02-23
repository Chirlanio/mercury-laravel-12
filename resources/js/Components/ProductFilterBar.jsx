import { useState, useEffect } from 'react';
import { MagnifyingGlassIcon, FunnelIcon, XMarkIcon } from '@heroicons/react/24/outline';

export default function ProductFilterBar({ filters, onFilterChange }) {
    const [options, setOptions] = useState(null);
    const [showFilters, setShowFilters] = useState(false);

    useEffect(() => {
        fetch('/products/filter-options')
            .then(res => res.json())
            .then(data => setOptions(data))
            .catch(() => setOptions({}));
    }, []);

    const hasActiveFilters = filters.brand || filters.collection || filters.category ||
        filters.color || filters.material || filters.supplier ||
        filters.is_active !== undefined && filters.is_active !== '' ||
        filters.sync_locked !== undefined && filters.sync_locked !== '';

    const clearFilters = () => {
        ['brand', 'collection', 'category', 'color', 'material', 'supplier', 'is_active', 'sync_locked'].forEach(key => {
            onFilterChange(key, '');
        });
    };

    return (
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-4">
            <div className="flex items-center gap-3">
                <div className="relative flex-1">
                    <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Buscar por referência ou descrição..."
                        defaultValue={filters.search || ''}
                        onChange={(e) => {
                            clearTimeout(window._productSearchTimeout);
                            window._productSearchTimeout = setTimeout(() => {
                                onFilterChange('search', e.target.value);
                            }, 500);
                        }}
                        className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>

                <button
                    onClick={() => setShowFilters(!showFilters)}
                    className={`flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition-colors ${
                        showFilters || hasActiveFilters
                            ? 'bg-indigo-50 border-indigo-300 text-indigo-700'
                            : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                    }`}
                >
                    <FunnelIcon className="h-4 w-4" />
                    Filtros
                    {hasActiveFilters && (
                        <span className="bg-indigo-600 text-white text-xs rounded-full px-1.5 py-0.5">!</span>
                    )}
                </button>

                {hasActiveFilters && (
                    <button
                        onClick={clearFilters}
                        className="flex items-center gap-1 px-3 py-2 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors"
                    >
                        <XMarkIcon className="h-4 w-4" />
                        Limpar
                    </button>
                )}
            </div>

            {showFilters && options && (
                <div className="mt-4 pt-4 border-t border-gray-200 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <FilterSelect
                        label="Marca"
                        value={filters.brand || ''}
                        onChange={(v) => onFilterChange('brand', v)}
                        options={options.brands?.map(b => ({ value: b.cigam_code, label: b.name })) || []}
                    />
                    <FilterSelect
                        label="Coleção"
                        value={filters.collection || ''}
                        onChange={(v) => onFilterChange('collection', v)}
                        options={options.collections?.map(c => ({ value: c.cigam_code, label: c.name })) || []}
                    />
                    <FilterSelect
                        label="Categoria"
                        value={filters.category || ''}
                        onChange={(v) => onFilterChange('category', v)}
                        options={options.categories?.map(c => ({ value: c.cigam_code, label: c.name })) || []}
                    />
                    <FilterSelect
                        label="Cor"
                        value={filters.color || ''}
                        onChange={(v) => onFilterChange('color', v)}
                        options={options.colors?.map(c => ({ value: c.cigam_code, label: c.name })) || []}
                    />
                    <FilterSelect
                        label="Material"
                        value={filters.material || ''}
                        onChange={(v) => onFilterChange('material', v)}
                        options={options.materials?.map(m => ({ value: m.cigam_code, label: m.name })) || []}
                    />
                    <FilterSelect
                        label="Fornecedor"
                        value={filters.supplier || ''}
                        onChange={(v) => onFilterChange('supplier', v)}
                        options={options.suppliers?.map(s => ({ value: s.codigo_for, label: s.nome_fantasia || s.razao_social })) || []}
                    />
                    <FilterSelect
                        label="Status"
                        value={filters.is_active ?? ''}
                        onChange={(v) => onFilterChange('is_active', v)}
                        options={[
                            { value: '1', label: 'Ativo' },
                            { value: '0', label: 'Inativo' },
                        ]}
                    />
                    <FilterSelect
                        label="Sync Lock"
                        value={filters.sync_locked ?? ''}
                        onChange={(v) => onFilterChange('sync_locked', v)}
                        options={[
                            { value: '1', label: 'Bloqueado' },
                            { value: '0', label: 'Desbloqueado' },
                        ]}
                    />
                </div>
            )}
        </div>
    );
}

function FilterSelect({ label, value, onChange, options }) {
    return (
        <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">{label}</label>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-full border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500"
            >
                <option value="">Todos</option>
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
            </select>
        </div>
    );
}
