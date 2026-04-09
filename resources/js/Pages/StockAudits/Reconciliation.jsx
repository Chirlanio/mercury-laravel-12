import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { useState, useCallback } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';

// ============================================================
// Summary card for displaying reconciliation statistics
// ============================================================
function SummaryCard({ label, value, color = 'gray', prefix = '' }) {
    const colorClasses = {
        gray: 'bg-gray-50 border-gray-200 text-gray-700',
        red: 'bg-red-50 border-red-200 text-red-700',
        green: 'bg-green-50 border-green-200 text-green-700',
        blue: 'bg-blue-50 border-blue-200 text-blue-700',
        yellow: 'bg-yellow-50 border-yellow-200 text-yellow-700',
        indigo: 'bg-indigo-50 border-indigo-200 text-indigo-700',
    };

    return (
        <div className={`rounded-lg border p-4 ${colorClasses[color] || colorClasses.gray}`}>
            <p className="text-xs font-medium uppercase tracking-wide opacity-75">{label}</p>
            <p className="mt-1 text-2xl font-bold">
                {prefix}{typeof value === 'number' ? value.toLocaleString('pt-BR') : value}
            </p>
        </div>
    );
}

// ============================================================
// Pagination component (matches project pattern)
// ============================================================
function Pagination({ paginationData }) {
    if (!paginationData?.links || paginationData.last_page <= 1) return null;

    return (
        <div className="px-6 py-3 border-t border-gray-200 flex justify-between items-center">
            <span className="text-sm text-gray-700">
                Mostrando {paginationData.from} a {paginationData.to} de {paginationData.total} registros
            </span>
            <div className="flex space-x-1">
                {paginationData.links.map((link, i) => (
                    <button
                        key={i}
                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
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
    );
}

// ============================================================
// Tab A - Count Reconciliation
// ============================================================
function TabPhaseA({ audit, items, loading, onAutoResolve, onManualResolve }) {
    const [manualValues, setManualValues] = useState({});
    const [resolvingId, setResolvingId] = useState(null);

    const allItems = items.data || [];
    const unresolvedItems = allItems.filter((item) => item.accepted_count === null);
    const resolvedItems = allItems.filter((item) => item.accepted_count !== null);
    const needsManual = unresolvedItems.filter(
        (item) => item.count_1 !== null && item.count_2 !== null && item.count_1 !== item.count_2
    );

    const handleManualResolve = async (item) => {
        const value = manualValues[item.id];
        if (value === undefined || value === '') return;
        setResolvingId(item.id);
        await onManualResolve(item.id, parseFloat(value));
        setResolvingId(null);
        setManualValues((prev) => {
            const next = { ...prev };
            delete next[item.id];
            return next;
        });
    };

    return (
        <div className="space-y-6">
            {/* Stats */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <SummaryCard
                    label="Total de Itens"
                    value={items.total || allItems.length}
                    color="gray"
                />
                <SummaryCard
                    label="Resolvidos Automaticamente"
                    value={resolvedItems.filter((i) => i.resolution_type === 'auto').length}
                    color="green"
                />
                <SummaryCard
                    label="Requer Resolucao Manual"
                    value={needsManual.length}
                    color="yellow"
                />
            </div>

            {/* Auto-resolve button */}
            <div className="flex justify-end">
                <button
                    onClick={onAutoResolve}
                    disabled={loading}
                    className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {loading ? (
                        <>
                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            Processando...
                        </>
                    ) : (
                        'Auto-resolver'
                    )}
                </button>
            </div>

            {/* Table */}
            <div className="bg-white shadow rounded-lg overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ref</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descricao</th>
                                <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cont.1</th>
                                <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cont.2</th>
                                <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cont.3</th>
                                <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aceito</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resolucao</th>
                                <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acoes</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {allItems.length > 0 ? (
                                allItems.map((item) => {
                                    const isUnresolved = item.accepted_count === null;
                                    const hasDivergence = item.count_1 !== null && item.count_2 !== null && item.count_1 !== item.count_2;

                                    return (
                                        <tr
                                            key={item.id}
                                            className={`${isUnresolved && hasDivergence ? 'bg-yellow-50' : ''} hover:bg-gray-50`}
                                        >
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {item.product_reference}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-500 max-w-xs truncate" title={item.product_description}>
                                                {item.product_description || '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-700">
                                                {item.count_1 !== null ? item.count_1 : '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-700">
                                                {item.count_2 !== null ? item.count_2 : '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-700">
                                                {item.count_3 !== null ? item.count_3 : '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-center font-semibold">
                                                {item.accepted_count !== null ? (
                                                    <span className="text-green-700">{item.accepted_count}</span>
                                                ) : (
                                                    <span className="text-gray-400">-</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {item.resolution_type ? (
                                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                                                        item.resolution_type === 'auto'
                                                            ? 'bg-blue-100 text-blue-800'
                                                            : item.resolution_type === 'manual'
                                                            ? 'bg-purple-100 text-purple-800'
                                                            : 'bg-gray-100 text-gray-800'
                                                    }`}>
                                                        {item.resolution_type === 'auto' ? 'Automatico' :
                                                         item.resolution_type === 'manual' ? 'Manual' :
                                                         item.resolution_type}
                                                    </span>
                                                ) : (
                                                    <span className="text-gray-400">-</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                {isUnresolved && hasDivergence && (
                                                    <div className="flex items-center justify-center space-x-2">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            step="1"
                                                            value={manualValues[item.id] ?? ''}
                                                            onChange={(e) =>
                                                                setManualValues((prev) => ({
                                                                    ...prev,
                                                                    [item.id]: e.target.value,
                                                                }))
                                                            }
                                                            placeholder="Qtd"
                                                            className="w-20 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                        <button
                                                            onClick={() => handleManualResolve(item)}
                                                            disabled={resolvingId === item.id || !manualValues[item.id]}
                                                            className="px-2 py-1 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                                        >
                                                            {resolvingId === item.id ? '...' : 'Resolver'}
                                                        </button>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td colSpan="8" className="px-6 py-12 text-center text-gray-500">
                                        Nenhum item encontrado para conciliacao.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination paginationData={items} />
            </div>
        </div>
    );
}

// ============================================================
// Tab B - System Reconciliation
// ============================================================
function TabPhaseB({ audit, items, loading, onCalculate, onJustify }) {
    const [justifyingId, setJustifyingId] = useState(null);
    const [justifyNotes, setJustifyNotes] = useState({});
    const [savingJustify, setSavingJustify] = useState(null);

    const allItems = items.data || [];
    const divergentItems = allItems.filter((item) => item.divergence !== 0);
    const losses = divergentItems.filter((item) => item.divergence < 0);
    const surpluses = divergentItems.filter((item) => item.divergence > 0);
    const totalLoss = losses.reduce((sum, item) => sum + Math.abs(item.divergence_value || 0), 0);
    const totalSurplus = surpluses.reduce((sum, item) => sum + (item.divergence_value || 0), 0);

    const handleJustify = async (item) => {
        const note = justifyNotes[item.id];
        if (!note || !note.trim()) return;

        setSavingJustify(item.id);
        await onJustify(item.id, note.trim());
        setSavingJustify(null);
        setJustifyingId(null);
        setJustifyNotes((prev) => {
            const next = { ...prev };
            delete next[item.id];
            return next;
        });
    };

    return (
        <div className="space-y-6">
            {/* Summary cards */}
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <SummaryCard
                    label="Total de Divergencias"
                    value={divergentItems.length}
                    color="indigo"
                />
                <SummaryCard
                    label="Perda Financeira"
                    value={totalLoss.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    prefix="R$ "
                    color="red"
                />
                <SummaryCard
                    label="Sobra Financeira"
                    value={totalSurplus.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    prefix="R$ "
                    color="green"
                />
                <SummaryCard
                    label="Itens Justificados"
                    value={allItems.filter((i) => i.is_justified).length}
                    color="blue"
                />
            </div>

            {/* Calculate button */}
            <div className="flex justify-end">
                <button
                    onClick={onCalculate}
                    disabled={loading}
                    className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {loading ? (
                        <>
                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            Calculando...
                        </>
                    ) : (
                        'Calcular Divergencias'
                    )}
                </button>
            </div>

            {/* Table */}
            <div className="bg-white shadow rounded-lg overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ref</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descricao</th>
                                <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qtd Sistema</th>
                                <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qtd Aceita</th>
                                <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Divergencia</th>
                                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor (R$)</th>
                                <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Justificado</th>
                                <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acoes</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {allItems.length > 0 ? (
                                allItems.map((item) => {
                                    const isLoss = item.divergence < 0;
                                    const isSurplus = item.divergence > 0;
                                    const rowBg = isLoss
                                        ? 'bg-red-50'
                                        : isSurplus
                                        ? 'bg-green-50'
                                        : '';

                                    return (
                                        <tr key={item.id} className={`${rowBg} hover:bg-gray-50`}>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {item.product_reference}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-500 max-w-xs truncate" title={item.product_description}>
                                                {item.product_description || '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-700">
                                                {item.system_quantity}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-700">
                                                {item.accepted_count !== null ? item.accepted_count : '-'}
                                            </td>
                                            <td className={`px-4 py-3 whitespace-nowrap text-sm text-center font-semibold ${
                                                isLoss ? 'text-red-700' : isSurplus ? 'text-green-700' : 'text-gray-500'
                                            }`}>
                                                {item.divergence > 0 ? '+' : ''}{item.divergence}
                                            </td>
                                            <td className={`px-4 py-3 whitespace-nowrap text-sm text-right font-medium ${
                                                isLoss ? 'text-red-700' : isSurplus ? 'text-green-700' : 'text-gray-500'
                                            }`}>
                                                {item.divergence_value !== 0
                                                    ? `${item.divergence_value < 0 ? '-' : ''}R$ ${Math.abs(item.divergence_value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                    : '-'
                                                }
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                {item.is_justified ? (
                                                    <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Sim
                                                    </span>
                                                ) : item.divergence !== 0 ? (
                                                    <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        Nao
                                                    </span>
                                                ) : (
                                                    <span className="text-gray-400">-</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-center">
                                                {item.divergence !== 0 && !item.is_justified && (
                                                    <>
                                                        {justifyingId === item.id ? (
                                                            <div className="flex flex-col items-center space-y-2">
                                                                <textarea
                                                                    value={justifyNotes[item.id] || ''}
                                                                    onChange={(e) =>
                                                                        setJustifyNotes((prev) => ({
                                                                            ...prev,
                                                                            [item.id]: e.target.value,
                                                                        }))
                                                                    }
                                                                    placeholder="Justificativa..."
                                                                    rows={2}
                                                                    className="w-full min-w-[200px] rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                                                />
                                                                <div className="flex space-x-2">
                                                                    <button
                                                                        onClick={() => handleJustify(item)}
                                                                        disabled={savingJustify === item.id || !justifyNotes[item.id]?.trim()}
                                                                        className="px-2 py-1 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700 disabled:opacity-50"
                                                                    >
                                                                        {savingJustify === item.id ? '...' : 'Salvar'}
                                                                    </button>
                                                                    <button
                                                                        onClick={() => setJustifyingId(null)}
                                                                        className="px-2 py-1 bg-gray-200 text-gray-700 text-xs rounded hover:bg-gray-300"
                                                                    >
                                                                        Cancelar
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <button
                                                                onClick={() => setJustifyingId(item.id)}
                                                                className="px-2 py-1 bg-yellow-500 text-white text-xs rounded hover:bg-yellow-600"
                                                            >
                                                                Justificar
                                                            </button>
                                                        )}
                                                    </>
                                                )}
                                                {item.is_justified && item.justification_note && (
                                                    <span className="text-xs text-gray-500 italic" title={item.justification_note}>
                                                        {item.justification_note.length > 40
                                                            ? item.justification_note.substring(0, 40) + '...'
                                                            : item.justification_note}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td colSpan="8" className="px-6 py-12 text-center text-gray-500">
                                        Nenhum item encontrado. Execute o calculo de divergencias.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination paginationData={items} />
            </div>
        </div>
    );
}

// ============================================================
// Tab C - Store Justifications
// ============================================================
function TabPhaseC({ audit, items, loading, onSubmitJustification, onReviewJustification }) {
    const [submittingId, setSubmittingId] = useState(null);
    const [justificationForms, setJustificationForms] = useState({});
    const [reviewingId, setReviewingId] = useState(null);
    const [reviewForms, setReviewForms] = useState({});
    const [savingSubmit, setSavingSubmit] = useState(null);
    const [savingReview, setSavingReview] = useState(null);

    const allItems = items.data || [];
    const divergentItems = allItems.filter((item) => item.divergence !== 0);

    const updateJustificationForm = (itemId, field, value) => {
        setJustificationForms((prev) => ({
            ...prev,
            [itemId]: { ...(prev[itemId] || {}), [field]: value },
        }));
    };

    const updateReviewForm = (justificationId, field, value) => {
        setReviewForms((prev) => ({
            ...prev,
            [justificationId]: { ...(prev[justificationId] || {}), [field]: value },
        }));
    };

    const handleSubmitJustification = async (item) => {
        const form = justificationForms[item.id];
        if (!form?.text?.trim()) return;

        setSavingSubmit(item.id);
        await onSubmitJustification(item.id, form.text.trim(), form.found_quantity || null);
        setSavingSubmit(null);
        setSubmittingId(null);
        setJustificationForms((prev) => {
            const next = { ...prev };
            delete next[item.id];
            return next;
        });
    };

    const handleReview = async (justificationId, status) => {
        const form = reviewForms[justificationId] || {};
        setSavingReview(justificationId);
        await onReviewJustification(justificationId, status, form.review_note || null);
        setSavingReview(null);
        setReviewingId(null);
        setReviewForms((prev) => {
            const next = { ...prev };
            delete next[justificationId];
            return next;
        });
    };

    const REVIEW_STATUS_COLORS = {
        pending: 'bg-yellow-100 text-yellow-800',
        accepted: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
    };

    const REVIEW_STATUS_LABELS = {
        pending: 'Pendente',
        accepted: 'Aceito',
        rejected: 'Rejeitado',
    };

    return (
        <div className="space-y-6">
            {/* Stats */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <SummaryCard
                    label="Itens Divergentes"
                    value={divergentItems.length}
                    color="indigo"
                />
                <SummaryCard
                    label="Justificativas Enviadas"
                    value={divergentItems.filter((i) => i.store_justifications?.length > 0).length}
                    color="blue"
                />
                <SummaryCard
                    label="Pendentes de Revisao"
                    value={divergentItems.reduce(
                        (count, item) =>
                            count + (item.store_justifications?.filter((j) => j.review_status === 'pending').length || 0),
                        0
                    )}
                    color="yellow"
                />
            </div>

            {/* Items list */}
            <div className="space-y-4">
                {divergentItems.length > 0 ? (
                    divergentItems.map((item) => (
                        <div key={item.id} className="bg-white shadow rounded-lg overflow-hidden">
                            {/* Item header */}
                            <div className={`px-4 py-3 border-b ${
                                item.divergence < 0 ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200'
                            }`}>
                                <div className="flex justify-between items-center">
                                    <div>
                                        <span className="font-medium text-gray-900">{item.product_reference}</span>
                                        <span className="ml-2 text-sm text-gray-500">{item.product_description || ''}</span>
                                    </div>
                                    <div className="flex items-center space-x-4 text-sm">
                                        <span className="text-gray-500">
                                            Sistema: <strong>{item.system_quantity}</strong>
                                        </span>
                                        <span className="text-gray-500">
                                            Aceito: <strong>{item.accepted_count ?? '-'}</strong>
                                        </span>
                                        <span className={`font-semibold ${
                                            item.divergence < 0 ? 'text-red-700' : 'text-green-700'
                                        }`}>
                                            Diverg.: {item.divergence > 0 ? '+' : ''}{item.divergence}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Store justifications */}
                            <div className="px-4 py-3 space-y-3">
                                {item.store_justifications && item.store_justifications.length > 0 ? (
                                    item.store_justifications.map((justification) => (
                                        <div key={justification.id} className="border rounded-md p-3 bg-gray-50">
                                            <div className="flex justify-between items-start">
                                                <div className="flex-1">
                                                    <p className="text-sm text-gray-700">{justification.text}</p>
                                                    <div className="mt-1 flex items-center space-x-3 text-xs text-gray-500">
                                                        {justification.found_quantity !== null && (
                                                            <span>Qtd encontrada: <strong>{justification.found_quantity}</strong></span>
                                                        )}
                                                        <span>Por: {justification.submitted_by || '-'}</span>
                                                        <span>{justification.submitted_at || '-'}</span>
                                                    </div>
                                                </div>
                                                <div className="ml-4 flex items-center space-x-2">
                                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                                                        REVIEW_STATUS_COLORS[justification.review_status] || 'bg-gray-100 text-gray-800'
                                                    }`}>
                                                        {REVIEW_STATUS_LABELS[justification.review_status] || justification.review_status}
                                                    </span>
                                                </div>
                                            </div>

                                            {/* Review controls */}
                                            {justification.review_status === 'pending' && (
                                                <div className="mt-3 border-t pt-3">
                                                    {reviewingId === justification.id ? (
                                                        <div className="space-y-2">
                                                            <textarea
                                                                value={reviewForms[justification.id]?.review_note || ''}
                                                                onChange={(e) =>
                                                                    updateReviewForm(justification.id, 'review_note', e.target.value)
                                                                }
                                                                placeholder="Nota de revisao (opcional)..."
                                                                rows={2}
                                                                className="w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                                            />
                                                            <div className="flex space-x-2">
                                                                <button
                                                                    onClick={() => handleReview(justification.id, 'accepted')}
                                                                    disabled={savingReview === justification.id}
                                                                    className="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 disabled:opacity-50"
                                                                >
                                                                    {savingReview === justification.id ? '...' : 'Aceitar'}
                                                                </button>
                                                                <button
                                                                    onClick={() => handleReview(justification.id, 'rejected')}
                                                                    disabled={savingReview === justification.id}
                                                                    className="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700 disabled:opacity-50"
                                                                >
                                                                    {savingReview === justification.id ? '...' : 'Rejeitar'}
                                                                </button>
                                                                <button
                                                                    onClick={() => setReviewingId(null)}
                                                                    className="px-3 py-1 bg-gray-200 text-gray-700 text-xs rounded hover:bg-gray-300"
                                                                >
                                                                    Cancelar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="flex space-x-2">
                                                            <button
                                                                onClick={() => setReviewingId(justification.id)}
                                                                className="px-3 py-1 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700"
                                                            >
                                                                Revisar
                                                            </button>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-gray-400 italic">Nenhuma justificativa enviada pela loja.</p>
                                )}

                                {/* Submit justification form */}
                                {submittingId === item.id ? (
                                    <div className="border rounded-md p-3 bg-blue-50 space-y-2">
                                        <p className="text-xs font-medium text-gray-700">Nova Justificativa da Loja</p>
                                        <textarea
                                            value={justificationForms[item.id]?.text || ''}
                                            onChange={(e) => updateJustificationForm(item.id, 'text', e.target.value)}
                                            placeholder="Descreva a justificativa..."
                                            rows={3}
                                            className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <div className="flex items-center space-x-3">
                                            <div>
                                                <label className="block text-xs text-gray-600">Qtd Encontrada</label>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    value={justificationForms[item.id]?.found_quantity || ''}
                                                    onChange={(e) =>
                                                        updateJustificationForm(item.id, 'found_quantity', e.target.value)
                                                    }
                                                    placeholder="Opcional"
                                                    className="mt-1 w-24 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </div>
                                        </div>
                                        <div className="flex space-x-2 pt-1">
                                            <button
                                                onClick={() => handleSubmitJustification(item)}
                                                disabled={savingSubmit === item.id || !justificationForms[item.id]?.text?.trim()}
                                                className="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 disabled:opacity-50"
                                            >
                                                {savingSubmit === item.id ? 'Enviando...' : 'Submeter Justificativa'}
                                            </button>
                                            <button
                                                onClick={() => setSubmittingId(null)}
                                                className="px-3 py-1.5 bg-gray-200 text-gray-700 text-sm rounded hover:bg-gray-300"
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <button
                                        onClick={() => setSubmittingId(item.id)}
                                        className="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700"
                                    >
                                        + Submeter Justificativa
                                    </button>
                                )}
                            </div>
                        </div>
                    ))
                ) : (
                    <div className="bg-white shadow rounded-lg p-12 text-center text-gray-500">
                        Nenhum item divergente encontrado.
                    </div>
                )}
            </div>

            <Pagination paginationData={items} />
        </div>
    );
}

// ============================================================
// Main Reconciliation Page
// ============================================================
const TABS = [
    { key: 'a', label: 'Fase A: Contagem' },
    { key: 'b', label: 'Fase B: Sistema' },
    { key: 'c', label: 'Fase C: Justificativas Loja' },
];

export default function Reconciliation({ audit, items, areas = [] }) {
    const { hasPermission } = usePermissions();
    const [activeTab, setActiveTab] = useState('a');
    const [loadingA, setLoadingA] = useState(false);
    const [loadingB, setLoadingB] = useState(false);
    const [loadingC, setLoadingC] = useState(false);
    const [flashMessage, setFlashMessage] = useState(null);

    const showFlash = useCallback((message, type = 'success') => {
        setFlashMessage({ message, type });
        setTimeout(() => setFlashMessage(null), 5000);
    }, []);

    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    const apiPost = useCallback(async (url, body = {}) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || data.error || 'Erro na requisicao.');
        }

        return data;
    }, []);

    const refreshPage = useCallback(() => {
        router.reload({ only: ['items', 'audit'] });
    }, []);

    // Phase A handlers
    const handleAutoResolve = useCallback(async () => {
        setLoadingA(true);
        try {
            const result = await apiPost(route('stock-audits.reconcile-a', audit.id), { action: 'auto' });
            showFlash(
                result.message || `Auto-resolucao concluida. ${result.resolved || 0} item(ns) resolvidos.`,
                'success'
            );
            refreshPage();
        } catch (err) {
            showFlash(err.message || 'Erro ao auto-resolver.', 'error');
        } finally {
            setLoadingA(false);
        }
    }, [audit.id, apiPost, showFlash, refreshPage]);

    const handleManualResolve = useCallback(async (itemId, acceptedCount) => {
        try {
            await apiPost(route('stock-audits.reconcile-a', audit.id), {
                action: 'manual',
                item_id: itemId,
                accepted_count: acceptedCount,
            });
            showFlash('Item resolvido manualmente.', 'success');
            refreshPage();
        } catch (err) {
            showFlash(err.message || 'Erro ao resolver item.', 'error');
        }
    }, [audit.id, apiPost, showFlash, refreshPage]);

    // Phase B handlers
    const handleCalculate = useCallback(async () => {
        setLoadingB(true);
        try {
            const result = await apiPost(route('stock-audits.reconcile-b', audit.id), { action: 'calculate' });
            showFlash(
                result.message || 'Divergencias calculadas com sucesso.',
                'success'
            );
            refreshPage();
        } catch (err) {
            showFlash(err.message || 'Erro ao calcular divergencias.', 'error');
        } finally {
            setLoadingB(false);
        }
    }, [audit.id, apiPost, showFlash, refreshPage]);

    const handleJustify = useCallback(async (itemId, note) => {
        try {
            await apiPost(route('stock-audits.reconcile-b', audit.id), {
                action: 'justify',
                item_id: itemId,
                justification_note: note,
            });
            showFlash('Justificativa registrada.', 'success');
            refreshPage();
        } catch (err) {
            showFlash(err.message || 'Erro ao justificar item.', 'error');
        }
    }, [audit.id, apiPost, showFlash, refreshPage]);

    // Phase C handlers
    const handleSubmitJustification = useCallback(async (itemId, text, foundQuantity) => {
        try {
            await apiPost(route('stock-audits.justify', audit.id), {
                item_id: itemId,
                justification_text: text,
                found_quantity: foundQuantity ? parseFloat(foundQuantity) : null,
            });
            showFlash('Justificativa da loja enviada.', 'success');
            refreshPage();
        } catch (err) {
            showFlash(err.message || 'Erro ao enviar justificativa.', 'error');
        }
    }, [audit.id, apiPost, showFlash, refreshPage]);

    const handleReviewJustification = useCallback(async (justificationId, status, reviewNote) => {
        try {
            await apiPost(route('stock-audits.review-justification', audit.id), {
                justification_id: justificationId,
                review_status: status,
                review_note: reviewNote,
            });
            showFlash(
                status === 'accepted' ? 'Justificativa aceita.' : 'Justificativa rejeitada.',
                'success'
            );
            refreshPage();
        } catch (err) {
            showFlash(err.message || 'Erro ao revisar justificativa.', 'error');
        }
    }, [audit.id, apiPost, showFlash, refreshPage]);

    return (
        <>
            <Head title={`Conciliacao - Auditoria #${audit.id}`} />

            <PageHeader>
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-2 sm:space-y-0">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Conciliacao - Auditoria #{audit.id}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {audit.store_name && (
                                <span className="mr-3">
                                    Loja: <strong>{audit.store_code} - {audit.store_name}</strong>
                                </span>
                            )}
                            <span className="mr-3">
                                Tipo: <strong>{audit.audit_type_label}</strong>
                            </span>
                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${audit.status_color || 'bg-gray-100 text-gray-800'}`}>
                                {audit.status_label}
                            </span>
                        </p>
                    </div>
                    <div className="flex items-center space-x-2 text-sm text-gray-500">
                        {audit.accuracy_percentage !== null && audit.accuracy_percentage !== undefined && (
                            <span className="mr-3">
                                Acuracia: <strong>{parseFloat(audit.accuracy_percentage).toFixed(1)}%</strong>
                            </span>
                        )}
                        <span>Criado em {audit.created_at}</span>
                    </div>
                </div>
            </PageHeader>

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Flash message */}
                    {flashMessage && (
                        <div
                            className={`mb-4 rounded-md p-4 ${
                                flashMessage.type === 'error'
                                    ? 'bg-red-50 text-red-800 border border-red-200'
                                    : flashMessage.type === 'warning'
                                    ? 'bg-yellow-50 text-yellow-800 border border-yellow-200'
                                    : 'bg-green-50 text-green-800 border border-green-200'
                            }`}
                        >
                            <div className="flex justify-between items-center">
                                <p className="text-sm">{flashMessage.message}</p>
                                <button
                                    onClick={() => setFlashMessage(null)}
                                    className="ml-4 text-sm font-medium opacity-60 hover:opacity-100"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Tab Navigation */}
                    <div className="border-b border-gray-200 mb-6">
                        <nav className="-mb-px flex space-x-8" aria-label="Tabs">
                            {TABS.map((tab) => (
                                <button
                                    key={tab.key}
                                    onClick={() => setActiveTab(tab.key)}
                                    className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                                        activeTab === tab.key
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </nav>
                    </div>

                    {/* Tab Content */}
                    {activeTab === 'a' && (
                        <TabPhaseA
                            audit={audit}
                            items={items}
                            loading={loadingA}
                            onAutoResolve={handleAutoResolve}
                            onManualResolve={handleManualResolve}
                        />
                    )}

                    {activeTab === 'b' && (
                        <TabPhaseB
                            audit={audit}
                            items={items}
                            loading={loadingB}
                            onCalculate={handleCalculate}
                            onJustify={handleJustify}
                        />
                    )}

                    {activeTab === 'c' && (
                        <TabPhaseC
                            audit={audit}
                            items={items}
                            loading={loadingC}
                            onSubmitJustification={handleSubmitJustification}
                            onReviewJustification={handleReviewJustification}
                        />
                    )}

                    {/* Bottom Action Buttons */}
                    <div className="mt-8 flex justify-between items-center border-t border-gray-200 pt-6">
                        <button
                            onClick={() => router.visit(route('stock-audits.index'))}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                        >
                            Voltar
                        </button>
                        <button
                            onClick={() => router.reload()}
                            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
                        >
                            Voltar ao Detalhe
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
}
