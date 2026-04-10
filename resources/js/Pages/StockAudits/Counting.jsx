import { Head, router, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { useState, useRef, useEffect, useCallback } from 'react';
import { toast } from 'react-toastify';
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ArrowUpTrayIcon,
    TrashIcon,
    ArrowLeftIcon,
    CheckIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';

const ROUND_LABELS = { 1: '1a Contagem', 2: '2a Contagem', 3: '3a Contagem' };

export default function Counting({ audit, items, areas = [], summary }) {
    const [activeRound, setActiveRound] = useState(() => {
        if (!summary.round_1.finalized) return 1;
        if (summary.round_2.required && !summary.round_2.finalized) return 2;
        if (summary.round_3.required && !summary.round_3.finalized) return 3;
        return 1;
    });
    const [barcode, setBarcode] = useState('');
    const [quantity, setQuantity] = useState(1);
    const [areaId, setAreaId] = useState('');
    const [scanning, setScanning] = useState(false);
    const [lastScan, setLastScan] = useState(null);
    const [lastScanItemId, setLastScanItemId] = useState(null);
    const [showImportModal, setShowImportModal] = useState(false);
    const [importFile, setImportFile] = useState(null);
    const [importing, setImporting] = useState(false);
    const [showClearConfirm, setShowClearConfirm] = useState(false);
    const [clearing, setClearing] = useState(false);
    const [finalizing, setFinalizing] = useState(false);

    const barcodeInputRef = useRef(null);
    const flashTimeoutRef = useRef(null);

    // Auto-focus barcode input on mount and round change
    useEffect(() => {
        focusInput();
    }, [activeRound]);

    // Clear flash highlight after 3 seconds
    useEffect(() => {
        if (lastScanItemId) {
            if (flashTimeoutRef.current) clearTimeout(flashTimeoutRef.current);
            flashTimeoutRef.current = setTimeout(() => {
                setLastScanItemId(null);
            }, 3000);
        }
        return () => {
            if (flashTimeoutRef.current) clearTimeout(flashTimeoutRef.current);
        };
    }, [lastScanItemId]);

    const focusInput = useCallback(() => {
        setTimeout(() => barcodeInputRef.current?.focus(), 50);
    }, []);

    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    const handleScan = async (e) => {
        e.preventDefault();
        const trimmedBarcode = barcode.trim();
        if (!trimmedBarcode || scanning) return;

        setScanning(true);
        setLastScan(null);

        try {
            const response = await fetch(route('stock-audits.count', audit.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    barcode: trimmedBarcode,
                    round: activeRound,
                    quantity: parseFloat(quantity) || 1,
                    area_id: areaId || null,
                }),
            });

            const data = await response.json();

            if (data.error) {
                toast.error(data.message || 'Erro ao registrar contagem.');
                setLastScan({ success: false, message: data.message });
                playErrorSound();
            } else {
                const productName = data.product?.description || data.item?.product_description || trimmedBarcode;
                const productSize = data.product?.size || data.item?.product_size || '';
                const newCount = data.new_count ?? '?';

                const successMsg = `${productName}${productSize ? ` (${productSize})` : ''} - Contagem: ${newCount}`;
                toast.success(successMsg, { autoClose: 2000 });
                setLastScan({
                    success: true,
                    message: successMsg,
                    product: data.product,
                    item: data.item,
                    newCount: newCount,
                });
                setLastScanItemId(data.item?.id);

                // Reload page data to update items table and summary
                router.reload({ only: ['items', 'summary'], preserveScroll: true });
            }
        } catch (err) {
            toast.error('Erro de conexao. Tente novamente.');
            setLastScan({ success: false, message: 'Erro de conexao.' });
            playErrorSound();
        } finally {
            setBarcode('');
            setQuantity(1);
            setScanning(false);
            focusInput();
        }
    };

    const playErrorSound = () => {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = ctx.createOscillator();
            const gain = ctx.createGain();
            oscillator.connect(gain);
            gain.connect(ctx.destination);
            oscillator.type = 'square';
            oscillator.frequency.setValueAtTime(300, ctx.currentTime);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            oscillator.start(ctx.currentTime);
            oscillator.stop(ctx.currentTime + 0.3);
        } catch {
            // Audio not available
        }
    };

    const handleFinalizeRound = async () => {
        if (finalizing) return;
        if (!confirm(`Deseja finalizar a ${ROUND_LABELS[activeRound]}? Esta acao nao pode ser desfeita.`)) return;

        setFinalizing(true);
        try {
            const response = await fetch(route('stock-audits.finalize-round', audit.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ round: activeRound }),
            });

            const data = await response.json();

            if (data.error) {
                toast.error(data.message);
            } else {
                toast.success(data.message || `Rodada ${activeRound} finalizada.`);
                router.reload({ preserveScroll: true });
            }
        } catch {
            toast.error('Erro ao finalizar rodada.');
        } finally {
            setFinalizing(false);
        }
    };

    const handleClearRound = async () => {
        if (clearing) return;
        setClearing(true);

        try {
            const response = await fetch(route('stock-audits.clear-count', audit.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    round: activeRound,
                    area_id: areaId || null,
                }),
            });

            const data = await response.json();

            if (data.error) {
                toast.error(data.message);
            } else {
                toast.success(`Rodada ${activeRound} limpa. ${data.affected ?? 0} itens afetados.`);
                router.reload({ preserveScroll: true });
            }
        } catch {
            toast.error('Erro ao limpar rodada.');
        } finally {
            setClearing(false);
            setShowClearConfirm(false);
        }
    };

    const handleImport = async (e) => {
        e.preventDefault();
        if (!importFile || importing) return;

        setImporting(true);
        const formData = new FormData();
        formData.append('file', importFile);
        formData.append('round', activeRound);
        if (areaId) formData.append('area_id', areaId);

        try {
            const response = await fetch(route('stock-audits.import', audit.id), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const data = await response.json();

            if (data.error) {
                toast.error(data.message || 'Erro na importacao.');
            } else {
                const msg = `Importado: ${data.success_rows ?? 0} itens com sucesso` +
                    (data.error_rows ? `, ${data.error_rows} erros` : '');
                toast.success(msg);
                router.reload({ preserveScroll: true });
            }
        } catch {
            toast.error('Erro ao importar arquivo.');
        } finally {
            setImporting(false);
            setShowImportModal(false);
            setImportFile(null);
        }
    };

    const isRoundFinalized = (round) => {
        const key = `round_${round}`;
        return summary[key]?.finalized;
    };

    const isRoundAvailable = (round) => {
        if (round === 1) return true;
        if (round === 2) return !!summary.round_2?.required;
        if (round === 3) return !!summary.round_3?.required;
        return false;
    };

    const getRoundCounted = (round) => {
        const key = `round_${round}`;
        return summary[key]?.counted ?? 0;
    };

    return (
        <>
            <Head title={`Contagem - Auditoria #${audit.id}`} />

            <PageHeader>
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Contagem - Auditoria #{audit.id}
                        </h2>
                        <p className="text-sm text-gray-500 mt-1">
                            {audit.store_name}{audit.store_code ? ` (${audit.store_code})` : ''} &mdash; {audit.audit_type_label}
                        </p>
                    </div>
                    <Link
                        href={route('stock-audits.index')}
                        className="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                    >
                        <ArrowLeftIcon className="h-4 w-4 mr-1.5" />
                        Voltar
                    </Link>
                </div>
            </PageHeader>

            <div className="py-6">
                <div className="mx-auto max-w-full px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* Summary Cards */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {[1, 2, 3].map((round) => {
                            if (!isRoundAvailable(round)) return null;
                            const counted = getRoundCounted(round);
                            const total = summary.total_items;
                            const finalized = isRoundFinalized(round);

                            return (
                                <div
                                    key={round}
                                    className={`bg-white shadow rounded-lg p-4 border-l-4 ${
                                        finalized
                                            ? 'border-green-500'
                                            : activeRound === round
                                            ? 'border-indigo-500'
                                            : 'border-gray-300'
                                    }`}
                                >
                                    <div className="flex items-center justify-between mb-2">
                                        <h3 className="text-sm font-medium text-gray-700">
                                            {ROUND_LABELS[round]}
                                        </h3>
                                        {finalized && (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <CheckIcon className="h-3 w-3 mr-1" />
                                                Finalizada
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-baseline gap-1">
                                        <span className="text-2xl font-bold text-gray-900">{counted}</span>
                                        <span className="text-sm text-gray-500">/ {total}</span>
                                    </div>
                                    {total > 0 && (
                                        <div className="mt-2">
                                            <div className="w-full bg-gray-200 rounded-full h-2">
                                                <div
                                                    className={`h-2 rounded-full transition-all duration-300 ${
                                                        finalized ? 'bg-green-500' : 'bg-indigo-500'
                                                    }`}
                                                    style={{ width: `${Math.min((counted / total) * 100, 100)}%` }}
                                                />
                                            </div>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {total > 0 ? Math.round((counted / total) * 100) : 0}% contados
                                            </p>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    {/* Barcode Scanner Section */}
                    <div className="bg-white shadow rounded-lg p-6">
                        {/* Round Tabs */}
                        <div className="flex border-b border-gray-200 mb-4">
                            {[1, 2, 3].map((round) => {
                                if (!isRoundAvailable(round)) return null;
                                const finalized = isRoundFinalized(round);

                                return (
                                    <button
                                        key={round}
                                        onClick={() => !finalized && setActiveRound(round)}
                                        disabled={finalized}
                                        className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
                                            activeRound === round
                                                ? 'border-indigo-500 text-indigo-600'
                                                : finalized
                                                ? 'border-transparent text-gray-400 cursor-not-allowed'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                        }`}
                                    >
                                        {ROUND_LABELS[round]}
                                        {finalized && (
                                            <CheckCircleIcon className="inline h-4 w-4 ml-1 text-green-500" />
                                        )}
                                    </button>
                                );
                            })}
                        </div>

                        {/* Scanner Input Row */}
                        <form onSubmit={handleScan} className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                {/* Barcode Input */}
                                <div className="sm:col-span-6">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Codigo de Barras
                                    </label>
                                    <input
                                        ref={barcodeInputRef}
                                        type="text"
                                        value={barcode}
                                        onChange={(e) => setBarcode(e.target.value)}
                                        placeholder="Bipe ou digite o codigo de barras..."
                                        autoFocus
                                        disabled={isRoundFinalized(activeRound)}
                                        className="w-full text-lg px-4 py-3 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    />
                                </div>

                                {/* Quantity */}
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Quantidade
                                    </label>
                                    <input
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={quantity}
                                        onChange={(e) => setQuantity(e.target.value)}
                                        disabled={isRoundFinalized(activeRound)}
                                        className="w-full px-4 py-3 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    />
                                </div>

                                {/* Area Selector */}
                                {areas.length > 0 && (
                                    <div className="sm:col-span-2">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Area
                                        </label>
                                        <select
                                            value={areaId}
                                            onChange={(e) => setAreaId(e.target.value)}
                                            disabled={isRoundFinalized(activeRound)}
                                            className="w-full px-4 py-3 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                        >
                                            <option value="">Todas</option>
                                            {areas.map((area) => (
                                                <option key={area.id} value={area.id}>
                                                    {area.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}

                                {/* Submit Button */}
                                <div className={areas.length > 0 ? 'sm:col-span-2' : 'sm:col-span-4'}>
                                    <button
                                        type="submit"
                                        disabled={scanning || !barcode.trim() || isRoundFinalized(activeRound)}
                                        className="w-full px-4 py-3 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                    >
                                        {scanning ? 'Registrando...' : 'Registrar'}
                                    </button>
                                </div>
                            </div>
                        </form>

                        {/* Last Scan Feedback */}
                        {lastScan && (
                            <div
                                className={`mt-4 p-3 rounded-md flex items-start gap-3 ${
                                    lastScan.success
                                        ? 'bg-green-50 border border-green-200'
                                        : 'bg-red-50 border border-red-200'
                                }`}
                            >
                                {lastScan.success ? (
                                    <CheckCircleIcon className="h-5 w-5 text-green-600 shrink-0 mt-0.5" />
                                ) : (
                                    <ExclamationTriangleIcon className="h-5 w-5 text-red-600 shrink-0 mt-0.5" />
                                )}
                                <div className="text-sm">
                                    <p className={lastScan.success ? 'text-green-800' : 'text-red-800'}>
                                        {lastScan.message}
                                    </p>
                                    {lastScan.success && lastScan.product && (
                                        <p className="text-green-600 mt-1">
                                            Ref: {lastScan.product.reference}
                                            {lastScan.product.size ? ` | Tam: ${lastScan.product.size}` : ''}
                                            {' | '}Nova contagem: {lastScan.newCount}
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}

                        {isRoundFinalized(activeRound) && (
                            <div className="mt-4 p-3 rounded-md bg-yellow-50 border border-yellow-200 text-sm text-yellow-800">
                                <ExclamationTriangleIcon className="h-5 w-5 inline text-yellow-600 mr-2" />
                                Esta rodada ja foi finalizada. Selecione outra rodada para continuar contando.
                            </div>
                        )}
                    </div>

                    {/* Items Table */}
                    <div className="bg-white shadow rounded-lg overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <h3 className="text-sm font-medium text-gray-700">
                                Itens da Auditoria ({summary.total_items} total)
                            </h3>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Ref
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Descricao
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Tam.
                                        </th>
                                        <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                            Sist.
                                        </th>
                                        <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                            Cont.1
                                        </th>
                                        {isRoundAvailable(2) && (
                                            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                                Cont.2
                                            </th>
                                        )}
                                        {isRoundAvailable(3) && (
                                            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                                Cont.3
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {items.data && items.data.length > 0 ? (
                                        items.data.map((item) => (
                                            <tr
                                                key={item.id}
                                                className={`transition-colors duration-500 ${
                                                    lastScanItemId === item.id
                                                        ? 'bg-green-100'
                                                        : 'hover:bg-gray-50'
                                                }`}
                                            >
                                                <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {item.product_reference}
                                                </td>
                                                <td className="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">
                                                    {item.product_description}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                    {item.product_size || '-'}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-right">
                                                    {item.system_quantity}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-sm text-right font-medium">
                                                    <CountCell value={item.count_1} />
                                                </td>
                                                {isRoundAvailable(2) && (
                                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-right font-medium">
                                                        <CountCell value={item.count_2} />
                                                    </td>
                                                )}
                                                {isRoundAvailable(3) && (
                                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-right font-medium">
                                                        <CountCell value={item.count_3} />
                                                    </td>
                                                )}
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={4 + (isRoundAvailable(2) ? 1 : 0) + (isRoundAvailable(3) ? 1 : 0)}
                                                className="px-6 py-12 text-center text-gray-500"
                                            >
                                                Nenhum item registrado ainda. Comece bipando os produtos.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {items.links && items.last_page > 1 && (
                            <div className="px-6 py-3 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-2">
                                <span className="text-sm text-gray-700">
                                    Mostrando {items.from} a {items.to} de {items.total} registros
                                </span>
                                <div className="flex space-x-1">
                                    {items.links.map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                                            disabled={!link.url}
                                            className={`px-3 py-1 text-sm rounded ${
                                                link.active
                                                    ? 'bg-indigo-600 text-white'
                                                    : link.url
                                                    ? 'bg-white text-gray-700 hover:bg-gray-50 border'
                                                    : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Action Buttons */}
                    <div className="flex flex-wrap gap-3">
                        <button
                            onClick={handleFinalizeRound}
                            disabled={finalizing || isRoundFinalized(activeRound) || getRoundCounted(activeRound) === 0}
                            className="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <CheckCircleIcon className="h-4 w-4 mr-2" />
                            {finalizing ? 'Finalizando...' : `Finalizar ${ROUND_LABELS[activeRound]}`}
                        </button>

                        <button
                            onClick={() => setShowImportModal(true)}
                            disabled={isRoundFinalized(activeRound)}
                            className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <ArrowUpTrayIcon className="h-4 w-4 mr-2" />
                            Importar CSV
                        </button>

                        <button
                            onClick={() => setShowClearConfirm(true)}
                            disabled={isRoundFinalized(activeRound) || getRoundCounted(activeRound) === 0}
                            className="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <TrashIcon className="h-4 w-4 mr-2" />
                            Limpar Rodada
                        </button>

                        <Link
                            href={route('stock-audits.index')}
                            className="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                        >
                            <ArrowLeftIcon className="h-4 w-4 mr-2" />
                            Voltar
                        </Link>
                    </div>
                </div>
            </div>

            {/* Import CSV Modal */}
            {showImportModal && (
                <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                        <div className="px-6 py-4 border-b">
                            <h3 className="text-lg font-medium text-gray-900">
                                Importar CSV - {ROUND_LABELS[activeRound]}
                            </h3>
                        </div>
                        <form onSubmit={handleImport}>
                            <div className="p-6 space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Arquivo CSV *
                                    </label>
                                    <input
                                        type="file"
                                        accept=".csv,.txt"
                                        onChange={(e) => setImportFile(e.target.files[0] || null)}
                                        className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                        required
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        Formato esperado: codigo_barras, quantidade (por linha)
                                    </p>
                                </div>
                                {areas.length > 0 && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Area (opcional)
                                        </label>
                                        <select
                                            value={areaId}
                                            onChange={(e) => setAreaId(e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        >
                                            <option value="">Todas</option>
                                            {areas.map((area) => (
                                                <option key={area.id} value={area.id}>{area.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                            </div>
                            <div className="flex justify-end space-x-3 px-6 py-4 border-t bg-gray-50 rounded-b-lg">
                                <button
                                    type="button"
                                    onClick={() => { setShowImportModal(false); setImportFile(null); }}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={!importFile || importing}
                                    className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {importing ? 'Importando...' : 'Importar'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Clear Round Confirmation Modal */}
            {showClearConfirm && (
                <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-xl w-full max-w-sm mx-4">
                        <div className="p-6">
                            <div className="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                                <ExclamationTriangleIcon className="h-6 w-6 text-red-600" />
                            </div>
                            <h3 className="text-lg font-medium text-gray-900 text-center mb-2">
                                Limpar {ROUND_LABELS[activeRound]}?
                            </h3>
                            <p className="text-sm text-gray-500 text-center">
                                Todos os dados de contagem da {ROUND_LABELS[activeRound]}
                                {areaId ? ` na area selecionada` : ''} serao removidos.
                                Esta acao nao pode ser desfeita.
                            </p>
                        </div>
                        <div className="flex justify-end space-x-3 px-6 py-4 border-t bg-gray-50 rounded-b-lg">
                            <button
                                type="button"
                                onClick={() => setShowClearConfirm(false)}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={handleClearRound}
                                disabled={clearing}
                                className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 disabled:opacity-50"
                            >
                                {clearing ? 'Limpando...' : 'Sim, Limpar'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

Counting.layout = (page) => <AuthenticatedLayout>{page}</AuthenticatedLayout>;

/**
 * Small helper component for count cells.
 */
function CountCell({ value }) {
    if (value === null || value === undefined) {
        return <span className="text-gray-300">&mdash;</span>;
    }
    return <span className="text-gray-900">{value}</span>;
}
