import { useEffect, useRef, useState } from 'react';
import { MagnifyingGlassIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/react/24/outline';

/**
 * Input de NF/cupom com lookup automático em /reversals/lookup-invoice.
 * Busca com debounce de 500ms e dispara onResolved com o payload quando
 * encontra. O preview da venda é responsabilidade do pai (ver
 * InvoiceSalePreview) para poder ocupar largura total fora da grid.
 *
 * IMPORTANTE: o número de NF/cupom não é único entre lojas nem entre
 * anos — por isso passamos `storeCode` e opcionalmente `movementDate`
 * (quando o usuário escolhe uma data específica entre várias).
 *
 * @param {string} value Número da NF digitado
 * @param {Function} onChange Handler de mudança do input
 * @param {string} storeCode Código da loja da venda (obrigatório)
 * @param {string} movementDate Data específica da venda (opcional — desempata)
 * @param {Function} onResolved (payload|null) => void
 * @param {string} error Erro inline a exibir
 * @param {boolean} disabled
 */
export default function InvoiceLookupSection({
    value,
    onChange,
    storeCode,
    movementDate,
    onResolved,
    error,
    disabled,
}) {
    const [lookupError, setLookupError] = useState(null);
    const [searching, setSearching] = useState(false);
    const [resolved, setResolved] = useState(false);
    const timer = useRef(null);
    const lastQuery = useRef(null);

    useEffect(() => {
        if (timer.current) clearTimeout(timer.current);

        const query = (value || '').trim();
        if (!query || query.length < 3) {
            setLookupError(null);
            setResolved(false);
            lastQuery.current = null;
            onResolved?.(null);
            return;
        }

        if (!storeCode) {
            setLookupError('Selecione a loja antes de digitar a NF/cupom.');
            setResolved(false);
            lastQuery.current = null;
            onResolved?.(null);
            return;
        }

        const cacheKey = `${storeCode}|${query}|${movementDate || ''}`;
        if (lastQuery.current === cacheKey && resolved) return;

        timer.current = setTimeout(() => {
            setSearching(true);
            setLookupError(null);
            lastQuery.current = cacheKey;

            const params = new URLSearchParams({
                invoice_number: query,
                store_code: storeCode,
            });
            if (movementDate) params.set('movement_date', movementDate);

            fetch(`${route('reversals.lookup-invoice')}?${params}`, {
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then((data) => {
                    if (!data.found) {
                        setResolved(false);
                        setLookupError(
                            data.error ||
                                'NF/cupom não encontrado nas movimentações desta loja. Verifique o número.'
                        );
                        onResolved?.(null);
                    } else {
                        setResolved(true);
                        setLookupError(null);
                        onResolved?.(data);
                    }
                })
                .catch(() => {
                    setResolved(false);
                    setLookupError('Erro ao buscar NF. Tente novamente.');
                })
                .finally(() => setSearching(false));
        }, 500);

        return () => timer.current && clearTimeout(timer.current);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value, storeCode, movementDate]);

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                NF / Cupom Fiscal *
            </label>
            <div className="relative">
                <input
                    type="text"
                    value={value || ''}
                    onChange={(e) => onChange(e.target.value)}
                    disabled={disabled}
                    placeholder="Digite o número da NF ou cupom fiscal"
                    className="w-full rounded-md border-gray-300 shadow-sm pr-10 focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100"
                />
                <div className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                    {searching ? (
                        <MagnifyingGlassIcon className="h-5 w-5 text-indigo-500 animate-pulse" />
                    ) : resolved ? (
                        <CheckCircleIcon className="h-5 w-5 text-green-600" />
                    ) : lookupError ? (
                        <XCircleIcon className="h-5 w-5 text-red-500" />
                    ) : (
                        <MagnifyingGlassIcon className="h-5 w-5 text-gray-400" />
                    )}
                </div>
            </div>

            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
            {lookupError && !error && (
                <p className="mt-1 text-xs text-red-600">{lookupError}</p>
            )}
        </div>
    );
}
