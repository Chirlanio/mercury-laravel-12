import { useEffect, useRef, useState } from 'react';
import {
    MagnifyingGlassIcon,
    XMarkIcon,
    QrCodeIcon,
    ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

/**
 * ProductLookupInline — autocomplete de produtos (regra M8).
 *
 * Busca em reference/description/barcode/aux_reference via endpoint
 * informado em `lookupUrl` (default: route('consignments.lookup.products')).
 * EAN-13 é detectado automaticamente (13 dígitos). Debounce 300ms para
 * evitar request burst.
 *
 * Após selecionar um produto, mostra as variantes de tamanho disponíveis
 * (chips clicáveis) — ideal para operação mobile com tocar-para-selecionar.
 *
 * Mobile-first: touch-targets ≥44px, dropdown expande full-width em
 * telas pequenas, teclado numérico no campo EAN.
 *
 * Props:
 *  - value: { product_id, product_variant_id, reference, barcode, ... } | null
 *  - onChange: (selection | null) => void
 *  - lookupUrl: string (rota absoluta)
 *  - label: string (default: "Produto")
 *  - error: string opcional
 *  - disabled: boolean
 *  - required: boolean
 *  - autoFocus: boolean
 */
export default function ProductLookupInline({
    value = null,
    onChange,
    lookupUrl,
    label = 'Produto',
    error = null,
    disabled = false,
    required = false,
    autoFocus = false,
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [showDropdown, setShowDropdown] = useState(false);
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [selectedVariantId, setSelectedVariantId] = useState(value?.product_variant_id ?? null);
    const [notFound, setNotFound] = useState(false);
    const debounceRef = useRef(null);
    const inputRef = useRef(null);

    // Hidrata com value inicial (modo edição)
    useEffect(() => {
        if (value && !selectedProduct && value.product_id) {
            setSelectedProduct({
                product_id: value.product_id,
                reference: value.reference,
                description: value.description,
                sale_price: value.unit_value,
                variants: value.product_variant_id
                    ? [{
                        id: value.product_variant_id,
                        barcode: value.barcode,
                        size_cigam_code: value.size_cigam_code,
                    }]
                    : [],
            });
            setSelectedVariantId(value.product_variant_id ?? null);
        }
    }, [value]);

    // Debounce + fetch
    useEffect(() => {
        if (selectedProduct) return; // já tem produto escolhido
        if (query.trim().length < 2) {
            setResults([]);
            setShowDropdown(false);
            setNotFound(false);
            return;
        }

        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(async () => {
            setLoading(true);
            setNotFound(false);
            try {
                const url = new URL(lookupUrl, window.location.origin);
                url.searchParams.set('q', query.trim());
                url.searchParams.set('limit', '20');

                const response = await fetch(url.toString(), {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) throw new Error('Falha na busca');

                const data = await response.json();
                setResults(data.results || []);
                setShowDropdown(true);
                setNotFound((data.results || []).length === 0);
            } catch (e) {
                setResults([]);
                setNotFound(true);
            } finally {
                setLoading(false);
            }
        }, 300);

        return () => clearTimeout(debounceRef.current);
    }, [query, selectedProduct, lookupUrl]);

    const selectProduct = (product) => {
        setSelectedProduct(product);
        setShowDropdown(false);
        setQuery('');

        // Se tem uma única variante, seleciona automaticamente
        if (product.variants.length === 1) {
            const v = product.variants[0];
            setSelectedVariantId(v.id);
            emitChange(product, v);
        } else {
            setSelectedVariantId(null);
            emitChange(product, null);
        }
    };

    const selectVariant = (variant) => {
        setSelectedVariantId(variant.id);
        emitChange(selectedProduct, variant);
    };

    const emitChange = (product, variant) => {
        onChange({
            product_id: product.product_id,
            product_variant_id: variant?.id ?? null,
            reference: product.reference,
            barcode: variant?.barcode ?? null,
            size_cigam_code: variant?.size_cigam_code ?? null,
            size_label: variant?.size_cigam_code
                ? variant.size_cigam_code.replace(/^U/, '')
                : null,
            description: product.description,
            unit_value: product.sale_price,
        });
    };

    const clearSelection = () => {
        setSelectedProduct(null);
        setSelectedVariantId(null);
        setQuery('');
        setResults([]);
        setShowDropdown(false);
        onChange(null);
        inputRef.current?.focus();
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Escape') {
            setShowDropdown(false);
        }
    };

    return (
        <div className="relative">
            <InputLabel value={label + (required ? ' *' : '')} />

            {selectedProduct ? (
                // Produto selecionado — card com detalhes + botão X
                <div className="mt-1 border border-gray-300 rounded-md bg-gray-50 p-3">
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex-1 min-w-0">
                            <div className="font-medium text-gray-900 truncate">
                                {selectedProduct.reference}
                                {selectedProduct.description && (
                                    <span className="text-gray-500 font-normal ml-2">
                                        — {selectedProduct.description}
                                    </span>
                                )}
                            </div>
                            {selectedProduct.sale_price !== null && (
                                <div className="text-xs text-gray-600 mt-0.5">
                                    Preço de tabela: R$ {Number(selectedProduct.sale_price).toFixed(2).replace('.', ',')}
                                </div>
                            )}
                        </div>
                        {!disabled && (
                            <button
                                type="button"
                                onClick={clearSelection}
                                className="shrink-0 text-gray-400 hover:text-red-600 p-2 -m-2"
                                aria-label="Remover produto"
                                title="Remover produto"
                            >
                                <XMarkIcon className="w-5 h-5" />
                            </button>
                        )}
                    </div>

                    {selectedProduct.variants.length > 0 && (
                        <div className="mt-3">
                            <div className="text-xs text-gray-600 mb-1.5">
                                {selectedProduct.variants.length === 1
                                    ? 'Variante:'
                                    : 'Selecione o tamanho:'}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {selectedProduct.variants.map((v) => (
                                    <button
                                        key={v.id}
                                        type="button"
                                        disabled={disabled}
                                        onClick={() => selectVariant(v)}
                                        className={`
                                            min-w-[44px] min-h-[44px] px-3 py-2 text-sm rounded-md border
                                            ${selectedVariantId === v.id
                                                ? 'bg-indigo-600 text-white border-indigo-600'
                                                : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400'}
                                            ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
                                        `}
                                    >
                                        {v.size_cigam_code || '—'}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            ) : (
                // Campo de busca com autocomplete
                <div className="relative mt-1">
                    <div className="relative">
                        <MagnifyingGlassIcon className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                        <TextInput
                            ref={inputRef}
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={handleKeyDown}
                            onFocus={() => {
                                if (results.length > 0) setShowDropdown(true);
                            }}
                            onBlur={() => setTimeout(() => setShowDropdown(false), 200)}
                            placeholder="Digite referência, descrição ou EAN-13..."
                            disabled={disabled}
                            required={required}
                            autoFocus={autoFocus}
                            inputMode="search"
                            className="block w-full pl-10 pr-10"
                        />
                        <QrCodeIcon
                            className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400"
                            title="Compatível com leitor de código de barras — cole ou digite o EAN-13"
                        />
                    </div>

                    {showDropdown && (
                        <div className="absolute z-50 mt-1 w-full bg-white shadow-lg rounded-md border border-gray-200 max-h-72 overflow-y-auto">
                            {loading && (
                                <div className="p-4 text-sm text-gray-500 text-center">
                                    Buscando…
                                </div>
                            )}
                            {!loading && results.length === 0 && notFound && (
                                <div className="p-4 text-sm text-amber-800 bg-amber-50 flex items-start gap-2">
                                    <ExclamationTriangleIcon className="w-5 h-5 shrink-0 mt-0.5" />
                                    <div>
                                        <div className="font-medium">Produto não encontrado</div>
                                        <div className="text-xs mt-1">
                                            Verifique a referência ou EAN. Produtos só podem ser consignados se estiverem no catálogo (regra M8).
                                        </div>
                                    </div>
                                </div>
                            )}
                            {!loading && results.map((r) => (
                                <button
                                    key={r.product_id}
                                    type="button"
                                    onClick={() => selectProduct(r)}
                                    className="w-full text-left px-4 py-3 hover:bg-indigo-50 border-b border-gray-100 last:border-0 min-h-[44px]"
                                >
                                    <div className="font-medium text-gray-900">
                                        {r.reference}
                                    </div>
                                    {r.description && (
                                        <div className="text-xs text-gray-500 mt-0.5 truncate">
                                            {r.description}
                                        </div>
                                    )}
                                    <div className="text-xs text-gray-400 mt-0.5">
                                        {r.variants.length} variante{r.variants.length !== 1 ? 's' : ''}
                                        {r.sale_price !== null && (
                                            <span className="ml-2">
                                                R$ {Number(r.sale_price).toFixed(2).replace('.', ',')}
                                            </span>
                                        )}
                                    </div>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}

            <InputError message={error} className="mt-1" />
        </div>
    );
}
