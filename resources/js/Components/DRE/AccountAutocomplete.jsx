import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/react/24/outline';

/**
 * Autocomplete de conta contábil analítica (grupos 3/4/5).
 *
 * Busca via AJAX em `/dre/mappings/search-accounts?q=...` conforme digitação.
 * Debounce de 250ms. Cache simples do último resultado pra re-render rápido.
 *
 * Props:
 *   - value: {id, code, name} | null — conta já selecionada
 *   - onChange: (account | null) => void
 *   - disabled: boolean
 */
export default function AccountAutocomplete({
    value = null,
    onChange,
    disabled = false,
    placeholder = 'Buscar conta por código ou nome…',
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const debounceRef = useRef(null);

    useEffect(() => {
        if (query.length < 2) {
            setResults([]);
            return;
        }

        if (debounceRef.current) clearTimeout(debounceRef.current);

        debounceRef.current = setTimeout(async () => {
            setLoading(true);
            try {
                const { data } = await axios.get(route('dre.mappings.search-accounts'), {
                    params: { q: query, limit: 20 },
                });
                setResults(data.results || []);
            } finally {
                setLoading(false);
            }
        }, 250);

        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [query]);

    const pick = (account) => {
        onChange({
            id: account.id,
            code: account.code,
            name: account.name,
            reduced_code: account.reduced_code,
        });
        setQuery('');
        setOpen(false);
    };

    const clear = () => {
        onChange(null);
        setQuery('');
    };

    if (value && !open) {
        return (
            <div className="flex items-center gap-2 border border-gray-300 rounded-md px-3 py-2 bg-gray-50">
                <span className="font-mono text-sm text-gray-600">{value.code}</span>
                <span className="text-sm text-gray-900 truncate flex-1">{value.name}</span>
                {!disabled && (
                    <button
                        type="button"
                        onClick={clear}
                        className="text-gray-400 hover:text-gray-600"
                        title="Limpar seleção"
                    >
                        <XMarkIcon className="h-4 w-4" />
                    </button>
                )}
            </div>
        );
    }

    return (
        <div className="relative">
            <div className="relative">
                <MagnifyingGlassIcon className="absolute top-2.5 left-3 h-4 w-4 text-gray-400" />
                <input
                    type="text"
                    value={query}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                    placeholder={placeholder}
                    disabled={disabled}
                    className="pl-9 pr-3 py-2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full disabled:bg-gray-50 disabled:text-gray-400"
                />
            </div>

            {open && query.length >= 2 && (
                <div className="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-64 overflow-y-auto">
                    {loading && (
                        <div className="px-3 py-2 text-sm text-gray-500">Buscando…</div>
                    )}
                    {!loading && results.length === 0 && (
                        <div className="px-3 py-2 text-sm text-gray-500">
                            Nenhuma conta encontrada.
                        </div>
                    )}
                    {results.map((r) => (
                        <button
                            type="button"
                            key={r.id}
                            onClick={() => pick(r)}
                            className="w-full text-left px-3 py-2 hover:bg-indigo-50 focus:bg-indigo-50 focus:outline-none"
                        >
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-xs text-gray-500">
                                    {r.code}
                                </span>
                                <span className="text-sm text-gray-900 truncate">
                                    {r.name}
                                </span>
                            </div>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
