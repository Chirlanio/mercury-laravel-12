import { useState, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import {
    MagnifyingGlassIcon,
    FunnelIcon,
    XMarkIcon,
    CalendarIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import Checkbox from '@/Components/Checkbox';

/**
 * Componente Genérico de Filtros (FilterBar)
 *
 * @param {Array}   fields            - Config dos campos.
 *   Ex.: { name, label, type: 'text'|'select'|'date'|'checkbox', placeholder?, options?, colSpan? (1..4) }
 *   Para select, options aceita [{id, name}] ou [{value, label}].
 * @param {Object}  filters           - Valores vindos do backend (props.filters).
 * @param {String}  routeName         - Nome da rota Ziggy (opcional; default = pathname atual).
 * @param {Boolean} showFilterButton  - Exibe botão "Filtrar" (ignorado se autoApply=true). Default true.
 * @param {Boolean} showClearButton   - Exibe botão "Limpar" (só renderiza se houver filtro ativo). Default true.
 * @param {Number}  gridCols          - Colunas do grid de campos (1..6). Default 4.
 * @param {Boolean} autoApply         - Se true, aplica filtros automaticamente (debounce em text, imediato nos demais). Default false.
 * @param {Number}  debounceMs        - Debounce do modo autoApply para campos text. Default 500ms.
 * @param {Function} onFilter         - Callback opcional disparado após aplicar.
 */

const GRID_CLASSES = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 md:grid-cols-2',
    3: 'grid-cols-1 md:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
    5: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-5',
    6: 'grid-cols-1 sm:grid-cols-3 lg:grid-cols-6',
};

const COL_SPAN_CLASSES = {
    1: 'md:col-span-1',
    2: 'md:col-span-2',
    3: 'md:col-span-3',
    4: 'md:col-span-4',
};

function buildInitialData(fields, filters) {
    const state = {};
    fields.forEach((field) => {
        const raw = filters?.[field.name];
        if (field.type === 'checkbox') {
            state[field.name] = raw === true || raw === 1 || raw === '1' || raw === 'true';
        } else {
            state[field.name] = raw ?? '';
        }
    });
    return state;
}

function isActiveValue(value) {
    return value !== '' && value !== null && value !== undefined && value !== false;
}

export default function FilterBar({
    fields = [],
    filters = {},
    routeName = null,
    showFilterButton = true,
    showClearButton = true,
    gridCols = 4,
    autoApply = false,
    debounceMs = 500,
    onFilter = null,
}) {
    const [data, setData] = useState(() => buildInitialData(fields, filters));
    const lastSyncedRef = useRef(JSON.stringify(filters ?? {}));
    const debounceRef = useRef(null);

    // Sincroniza estado local quando o servidor devolve filtros diferentes.
    // Compara por valor (JSON) para ignorar novas referências de objeto sem mudança real,
    // evitando sobrescrever input em digitação quando o parent re-renderiza.
    useEffect(() => {
        const currentSnapshot = JSON.stringify(filters ?? {});
        if (currentSnapshot !== lastSyncedRef.current) {
            lastSyncedRef.current = currentSnapshot;
            setData(buildInitialData(fields, filters));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters]);

    useEffect(() => () => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
    }, []);

    const applyFilters = (state) => {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
            debounceRef.current = null;
        }

        const path = routeName ? route(routeName) : window.location.pathname;

        const cleanData = Object.keys(state).reduce((acc, key) => {
            const value = state[key];
            if (isActiveValue(value)) acc[key] = value;
            return acc;
        }, {});

        router.get(path, cleanData, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

        if (onFilter) onFilter(cleanData);
    };

    const handleChange = (field, value) => {
        const nextState = { ...data, [field.name]: value };
        setData(nextState);

        if (!autoApply) return;

        if (field.type === 'text') {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(() => applyFilters(nextState), debounceMs);
        } else {
            applyFilters(nextState);
        }
    };

    const handleSubmit = (e) => {
        if (e) e.preventDefault();
        applyFilters(data);
    };

    const handleClear = () => {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
            debounceRef.current = null;
        }

        const cleared = {};
        fields.forEach((f) => {
            cleared[f.name] = f.type === 'checkbox' ? false : '';
        });
        setData(cleared);

        const path = routeName ? route(routeName) : window.location.pathname;
        router.get(path, {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

        if (onFilter) onFilter({});
    };

    const hasActiveFilters = Object.values(data).some(isActiveValue);
    const gridClass = GRID_CLASSES[gridCols] || GRID_CLASSES[4];
    const shouldRenderFilterButton = showFilterButton && !autoApply;
    const shouldRenderClearButton = showClearButton && hasActiveFilters;
    const showFooter = shouldRenderFilterButton || shouldRenderClearButton;

    return (
        <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
            <form onSubmit={handleSubmit}>
                <div className={`grid gap-4 items-end ${gridClass}`}>
                    {fields.map((field) => {
                        const spanClass = field.colSpan ? COL_SPAN_CLASSES[field.colSpan] || '' : '';
                        const cellClass = [
                            spanClass,
                            field.type === 'checkbox' ? 'pb-2' : '',
                        ].filter(Boolean).join(' ');

                        return (
                            <div key={field.name} className={cellClass}>
                                {field.type !== 'checkbox' && (
                                    <InputLabel
                                        htmlFor={`filter-${field.name}`}
                                        value={field.label}
                                        className="mb-1 text-xs font-semibold uppercase text-gray-500"
                                    />
                                )}

                                {field.type === 'text' && (
                                    <div className="relative">
                                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <MagnifyingGlassIcon className="h-4 w-4 text-gray-400" />
                                        </div>
                                        <TextInput
                                            id={`filter-${field.name}`}
                                            type="text"
                                            className="block w-full pl-10 text-sm py-2"
                                            value={data[field.name] ?? ''}
                                            onChange={(e) => handleChange(field, e.target.value)}
                                            placeholder={field.placeholder || 'Filtrar...'}
                                        />
                                    </div>
                                )}

                                {field.type === 'select' && (
                                    <select
                                        id={`filter-${field.name}`}
                                        className="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm py-2"
                                        value={data[field.name] ?? ''}
                                        onChange={(e) => handleChange(field, e.target.value)}
                                    >
                                        <option value="">{field.placeholder || `Todos(as) ${field.label}`}</option>
                                        {field.options?.map((opt) => {
                                            const value = opt.id ?? opt.value;
                                            const label = opt.name ?? opt.label;
                                            return (
                                                <option key={value} value={value}>
                                                    {label}
                                                </option>
                                            );
                                        })}
                                    </select>
                                )}

                                {field.type === 'date' && (
                                    <div className="relative">
                                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <CalendarIcon className="h-4 w-4 text-gray-400" />
                                        </div>
                                        <TextInput
                                            id={`filter-${field.name}`}
                                            type="date"
                                            className="block w-full pl-10 text-sm py-2"
                                            value={data[field.name] ?? ''}
                                            onChange={(e) => handleChange(field, e.target.value)}
                                        />
                                    </div>
                                )}

                                {field.type === 'checkbox' && (
                                    <label className="flex items-center space-x-2 cursor-pointer group">
                                        <Checkbox
                                            id={`filter-${field.name}`}
                                            checked={!!data[field.name]}
                                            onChange={(e) => handleChange(field, e.target.checked)}
                                        />
                                        <span className="text-sm text-gray-600 group-hover:text-gray-900 transition-colors">
                                            {field.label}
                                        </span>
                                    </label>
                                )}
                            </div>
                        );
                    })}
                </div>

                {showFooter && (
                    <div className="flex items-center justify-end gap-2 pt-4 mt-4 border-t border-gray-100">
                        {shouldRenderClearButton && (
                            <Button
                                type="button"
                                variant="light"
                                size="sm"
                                onClick={handleClear}
                                icon={XMarkIcon}
                            >
                                Limpar
                            </Button>
                        )}
                        {shouldRenderFilterButton && (
                            <Button
                                type="submit"
                                size="sm"
                                icon={FunnelIcon}
                            >
                                Filtrar
                            </Button>
                        )}
                    </div>
                )}
            </form>
        </div>
    );
}
