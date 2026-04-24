import { useForm, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    ArrowUturnLeftIcon,
    MagnifyingGlassIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    XCircleIcon,
    InformationCircleIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

/**
 * Registrar retorno de consignação.
 *
 * Fluxo:
 *  1. Usuário preenche NF retorno (número + data + loja) e clica em
 *     "Buscar NF e comparar".
 *  2. Sistema busca a NF no CIGAM (movement_code=21) e compara com a NF
 *     de saída. Divergências aparecem com alertas visuais.
 *  3. Para os itens da saída que NÃO voltaram na NF (missing_in_return),
 *     o usuário marca cada produto como "Vendido" ou "Devolvido (fora da
 *     NF)". A quantidade é implícita (outbound_pending do item).
 *  4. Se "Vendido", o sistema verifica movement_code=2 POR PRODUTO
 *     (barcode/ref_size) pro CPF do cliente nos 7 dias. Sem confirmação
 *     CIGAM, pede justificativa POR ITEM e dispara email pra loja.
 *
 * Obrigatórios: invoice_number, return_date, return_store_code.
 * Loja auto-travada à do user quando hierarquia < SUPPORT.
 */
export default function RegisterReturnModal({
    show,
    onClose,
    consignmentSummary,
    userStoreCode = null,
    canChooseStore = false,
    stores = [],
}) {
    const [comparison, setComparison] = useState(null);
    const [saleCheck, setSaleCheck] = useState(null);
    const [lookupLoading, setLookupLoading] = useState(false);
    const [lookupError, setLookupError] = useState(null);
    const [itemActions, setItemActions] = useState({}); // itemId -> 'sold' | 'returned'
    const [itemJustifications, setItemJustifications] = useState({});
    const [expandedJustifications, setExpandedJustifications] = useState({});

    const { data, setData, processing, errors, reset, setError, clearErrors } = useForm({
        return_invoice_number: '',
        return_date: new Date().toISOString().slice(0, 10),
        return_store_code: '',
        notes: '',
    });

    useEffect(() => {
        if (!show || !consignmentSummary?.id) return;

        const storeCode = canChooseStore
            ? (consignmentSummary?.store?.code || userStoreCode || '')
            : (userStoreCode || '');

        setData('return_store_code', storeCode);
        setComparison(null);
        setSaleCheck(null);
        setLookupError(null);
        setItemActions({});
        setItemJustifications({});
        setExpandedJustifications({});
    }, [show, consignmentSummary?.id]);

    useEffect(() => {
        if (!show) {
            reset();
            setComparison(null);
            setSaleCheck(null);
            setLookupError(null);
            setItemActions({});
            setItemJustifications({});
        setExpandedJustifications({});
            clearErrors();
        }
    }, [show]);

    const canLookup = data.return_invoice_number?.trim()
        && data.return_date
        && data.return_store_code?.trim();

    const handleLookup = async () => {
        if (!canLookup) return;

        setLookupLoading(true);
        setLookupError(null);
        setComparison(null);
        setSaleCheck(null);

        try {
            const url = new URL(
                route('consignments.lookup.return-compare', consignmentSummary.id),
                window.location.origin,
            );
            url.searchParams.set('return_invoice_number', data.return_invoice_number.trim());
            url.searchParams.set('return_date', data.return_date);
            url.searchParams.set('return_store_code', data.return_store_code.trim());

            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                throw new Error(response.status === 422 ? 'Dados inválidos — confira os campos.' : 'Erro ao consultar a NF.');
            }

            const json = await response.json();
            setComparison(json.comparison);
            setSaleCheck(json.customer_sale_check);
            // Nenhum produto pré-marcado como vendido — usuário clica
            // na linha do produto que foi vendido para indicar.
            setItemActions({});
        } catch (e) {
            setLookupError(e.message);
        } finally {
            setLookupLoading(false);
        }
    };

    const perItemSaleMatch = saleCheck?.per_item || {};
    const isItemSaleMatched = (itemId) => !!(perItemSaleMatch[itemId]?.matched);

    const soldItemsNeedingJustification = useMemo(() => (
        Object.entries(itemActions)
            .filter(([itemId, action]) => action === 'sold' && !isItemSaleMatched(Number(itemId)))
            .map(([itemId]) => Number(itemId))
    ), [itemActions, perItemSaleMatch]);

    // Unifica todos os produtos da comparação numa só lista para a UI.
    // Cada entry tem a quantidade correta (qty da NF para items que vieram,
    // outbound_pending para os que não vieram) e um flag `in_return_nf`.
    const unifiedItems = useMemo(() => {
        if (!comparison?.comparison) return [];
        const cmp = comparison.comparison;
        const list = [];

        (cmp.matched || []).forEach((m) => list.push({
            ...m,
            in_return_nf: true,
            returned_qty: m.return_quantity,
            status_label: 'na NF de retorno',
            status_color: 'green',
        }));
        (cmp.quantity_divergent || []).forEach((m) => list.push({
            ...m,
            in_return_nf: true,
            returned_qty: m.return_quantity,
            status_label: 'qtd divergente',
            status_color: 'amber',
        }));
        (cmp.value_divergent || []).forEach((m) => list.push({
            ...m,
            in_return_nf: true,
            returned_qty: m.return_quantity,
            status_label: 'valor divergente',
            status_color: 'amber',
        }));
        (cmp.missing_in_return || []).forEach((m) => list.push({
            ...m,
            in_return_nf: false,
            returned_qty: 0,
            status_label: 'não veio na NF',
            status_color: 'orange',
        }));

        return list;
    }, [comparison]);

    const submit = () => {
        if (!comparison?.found) {
            setError('return_invoice_number', 'Busque e compare a NF antes de registrar.');
            return;
        }

        const items = [];

        unifiedItems.forEach((it) => {
            const isSold = itemActions[it.consignment_item_id] === 'sold';

            if (isSold) {
                // Produto marcado como vendido — sobrescreve classificação
                // da NF (se estava em matched/divergent). Qty = pendente total
                // do item (outbound_pending), não a qty da NF.
                const entry = {
                    consignment_item_id: it.consignment_item_id,
                    quantity: it.outbound_pending,
                    action: 'sold',
                };
                if (!isItemSaleMatched(it.consignment_item_id)) {
                    const j = (itemJustifications[it.consignment_item_id] || '').trim();
                    if (j) entry.sale_justification = j;
                }
                items.push(entry);
                return;
            }

            if (it.in_return_nf) {
                // Item na NF retorno (não marcado como sold) → devolvido normal
                items.push({
                    consignment_item_id: it.consignment_item_id,
                    quantity: it.returned_qty,
                    action: 'returned',
                });
                return;
            }

            // Item não veio na NF e não foi marcado como sold → devolvido fora da NF
            items.push({
                consignment_item_id: it.consignment_item_id,
                quantity: it.outbound_pending,
                action: 'returned',
            });
        });

        if (items.length === 0) {
            setError('items', 'Nenhum item para registrar.');
            return;
        }

        for (const itemId of soldItemsNeedingJustification) {
            const j = (itemJustifications[itemId] || '').trim();
            if (!j) {
                setError('items', 'Informe a justificativa de cada produto marcado como vendido sem confirmação no CIGAM.');
                return;
            }
        }

        router.post(
            route('consignments.returns.store', consignmentSummary.id),
            {
                return_invoice_number: data.return_invoice_number,
                return_date: data.return_date,
                return_store_code: data.return_store_code,
                notes: data.notes || null,
                items,
            },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
            },
        );
    };

    if (!consignmentSummary) return null;

    const cmp = comparison?.comparison;
    const hasMissing = cmp?.missing_in_return?.length > 0;

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Registrar retorno"
            subtitle={`Consignação #${consignmentSummary.id} — ${consignmentSummary.recipient_name}`}
            headerColor="bg-emerald-600"
            headerIcon={<ArrowUturnLeftIcon className="h-5 w-5" />}
            maxWidth="5xl"
            footer={
                <StandardModal.Footer>
                    <div className="flex-1" />
                    <Button variant="secondary" onClick={onClose} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button
                        variant="success"
                        onClick={submit}
                        disabled={processing || !comparison?.found}
                    >
                        {processing ? 'Registrando…' : 'Registrar retorno'}
                    </Button>
                </StandardModal.Footer>
            }
        >
            <div className="space-y-4">
                {/* Identificadores da NF de retorno */}
                <div className="bg-emerald-50 border border-emerald-200 rounded-md p-3">
                    <div className="text-sm font-medium text-emerald-900 mb-2">
                        Identificação da NF de retorno
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <InputLabel value="Número da NF *" />
                            <TextInput
                                type="text"
                                value={data.return_invoice_number}
                                onChange={(e) => setData('return_invoice_number', e.target.value)}
                                className="mt-1 block w-full"
                                maxLength={20}
                                inputMode="numeric"
                                placeholder="Ex: 727"
                            />
                            <InputError message={errors.return_invoice_number} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Data de emissão *" />
                            <TextInput
                                type="date"
                                value={data.return_date}
                                onChange={(e) => setData('return_date', e.target.value)}
                                className="mt-1 block w-full"
                            />
                            <InputError message={errors.return_date} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Loja *" />
                            <select
                                value={data.return_store_code}
                                onChange={(e) => setData('return_store_code', e.target.value)}
                                disabled={!canChooseStore}
                                title={canChooseStore ? '' : 'Loja do usuário — só supervisão pode alterar'}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed text-sm"
                            >
                                <option value="">Selecione a loja…</option>
                                {stores.map((s) => (
                                    <option key={s.id} value={s.code}>
                                        {s.code} — {s.name}
                                    </option>
                                ))}
                            </select>
                            {!canChooseStore && (
                                <p className="mt-1 text-xs text-emerald-700">
                                    Loja travada à do seu usuário.
                                </p>
                            )}
                            <InputError message={errors.return_store_code} className="mt-1" />
                        </div>
                    </div>
                    <div className="mt-3 flex justify-end">
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={handleLookup}
                            disabled={!canLookup || lookupLoading}
                            icon={MagnifyingGlassIcon}
                        >
                            {lookupLoading ? 'Buscando…' : 'Buscar NF e comparar'}
                        </Button>
                    </div>
                    {lookupError && (
                        <div className="mt-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded p-2">
                            {lookupError}
                        </div>
                    )}
                </div>

                {comparison && (
                    <>
                        {!comparison.found && (
                            <div className="bg-amber-50 border border-amber-200 rounded-md p-3 flex items-start gap-2">
                                <ExclamationTriangleIcon className="w-5 h-5 shrink-0 text-amber-600 mt-0.5" />
                                <div className="text-sm text-amber-900">
                                    <div className="font-medium">NF de retorno não encontrada no CIGAM</div>
                                    <div className="text-xs mt-1">
                                        Confira loja + número + data. Marque abaixo os produtos vendidos
                                        ou devolvidos fora da NF.
                                    </div>
                                </div>
                            </div>
                        )}

                        {comparison.found && cmp && (
                            <div className="grid grid-cols-2 sm:grid-cols-5 gap-2 text-xs">
                                <SummaryCell label="Casaram" value={cmp.matched.length} color={cmp.matched.length > 0 ? 'green' : 'gray'} icon={CheckCircleIcon} />
                                <SummaryCell label="Qtd diverg." value={cmp.quantity_divergent.length} color={cmp.quantity_divergent.length > 0 ? 'amber' : 'gray'} />
                                <SummaryCell label="Valor diverg." value={cmp.value_divergent.length} color={cmp.value_divergent.length > 0 ? 'amber' : 'gray'} />
                                <SummaryCell label="Extra na NF" value={cmp.extra_in_return.length} color={cmp.extra_in_return.length > 0 ? 'red' : 'gray'} icon={XCircleIcon} />
                                <SummaryCell label="Não voltou" value={cmp.missing_in_return.length} color={hasMissing ? 'orange' : 'gray'} />
                            </div>
                        )}

                        {cmp?.extra_in_return?.length > 0 && (
                            <IssueGroup title="Itens na NF que NÃO constam na saída" color="red" icon={XCircleIcon} items={cmp.extra_in_return}
                                render={(it) => <ItemRow refText={it.reference || '—'} size={it.size_label} quantity={it.quantity} value={it.unit_value} note={it.reason} />}
                            />
                        )}

                        {unifiedItems.length > 0 && (
                            <div className="bg-indigo-50 border border-indigo-200 rounded-md p-3">
                                <div className="flex items-start gap-2 mb-2">
                                    <InformationCircleIcon className="w-5 h-5 shrink-0 text-indigo-600 mt-0.5" />
                                    <div className="text-sm font-medium text-indigo-900">
                                        Produtos da consignação — clique na linha do produto que foi vendido
                                    </div>
                                </div>

                                <div className="text-xs text-gray-700 bg-white border border-indigo-200 rounded p-2 mb-2">
                                    A verificação no CIGAM é feita <strong>por produto</strong> (ref. + tamanho) para o
                                    CPF {saleCheck?.cpf ?? '—'} na janela de {saleCheck?.window_days ?? 7} dias após {data.return_date}.
                                    Produtos não clicados são considerados <strong>devolvidos</strong> (conforme a NF ou
                                    fora dela).
                                </div>

                                <div className="space-y-2">
                                    {unifiedItems.map((it) => {
                                        const isSold = itemActions[it.consignment_item_id] === 'sold';
                                        const matched = isItemSaleMatched(it.consignment_item_id);
                                        const needsJustification = isSold && !matched;
                                        const movements = perItemSaleMatch[it.consignment_item_id]?.movements || [];

                                        const toggleSold = () => setItemActions((p) => ({
                                            ...p,
                                            [it.consignment_item_id]: isSold ? 'returned' : 'sold',
                                        }));

                                        const statusClass = it.status_color === 'green'
                                            ? 'bg-green-100 text-green-700'
                                            : it.status_color === 'amber'
                                                ? 'bg-amber-100 text-amber-800'
                                                : 'bg-orange-100 text-orange-800';

                                        return (
                                            <div key={it.consignment_item_id}>
                                                <button
                                                    type="button"
                                                    onClick={toggleSold}
                                                    className={`w-full text-left border rounded p-2 transition-colors flex flex-wrap items-center justify-between gap-2 ${
                                                        isSold
                                                            ? 'bg-blue-50 border-blue-400 ring-1 ring-blue-300'
                                                            : 'bg-white border-indigo-200 hover:bg-indigo-100'
                                                    }`}
                                                >
                                                    <div className="flex items-center gap-2 min-w-0 flex-1">
                                                        <span
                                                            className={`inline-flex items-center justify-center w-5 h-5 rounded-full border ${
                                                                isSold
                                                                    ? 'bg-blue-600 border-blue-600 text-white'
                                                                    : 'bg-white border-gray-300 text-transparent'
                                                            }`}
                                                            aria-hidden
                                                        >
                                                            <CheckCircleIcon className="w-4 h-4" />
                                                        </span>
                                                        <div className="text-sm">
                                                            <span className="font-medium">{it.reference}</span>
                                                            {(it.size_label || it.size_cigam_code) && (
                                                                <span className="ml-1 text-xs text-gray-500">
                                                                    Tam. {it.size_label || it.size_cigam_code}
                                                                </span>
                                                            )}
                                                            <span className="ml-2 text-xs text-gray-600">
                                                                {it.outbound_pending} peça(s)
                                                            </span>
                                                            <span className={`ml-2 text-[10px] uppercase font-medium px-1.5 py-0.5 rounded ${statusClass}`}>
                                                                {it.status_label}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <span className={`text-xs font-medium px-2 py-0.5 rounded ${
                                                        isSold
                                                            ? 'bg-blue-600 text-white'
                                                            : it.in_return_nf
                                                                ? 'bg-green-100 text-green-700'
                                                                : 'bg-gray-100 text-gray-600'
                                                    }`}>
                                                        {isSold
                                                            ? 'Vendido'
                                                            : it.in_return_nf
                                                                ? 'Devolvido (NF)'
                                                                : 'Devolvido fora da NF'}
                                                    </span>
                                                </button>

                                                {isSold && matched && (
                                                    <div className="mt-1 text-xs text-green-800 bg-green-50 border border-green-200 rounded p-2 flex items-start gap-1">
                                                        <CheckCircleIcon className="w-4 h-4 shrink-0 mt-0.5" />
                                                        <div>
                                                            Venda deste produto localizada no CIGAM
                                                            {movements[0]?.invoice_number
                                                                ? ` — NF ${movements[0].invoice_number} em ${movements[0].movement_date}`
                                                                : ''}.
                                                        </div>
                                                    </div>
                                                )}

                                                {needsJustification && (() => {
                                                    const expanded = !!expandedJustifications[it.consignment_item_id];
                                                    const hasText = !!(itemJustifications[it.consignment_item_id] || '').trim();
                                                    const sizeDisplay = it.size_label || it.size_cigam_code;

                                                    return (
                                                        <div className="mt-1">
                                                            {!expanded && !hasText ? (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setExpandedJustifications((p) => ({
                                                                        ...p, [it.consignment_item_id]: true,
                                                                    }))}
                                                                    className="inline-flex items-center gap-1 text-xs text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-300 rounded px-2 py-1"
                                                                >
                                                                    <ExclamationTriangleIcon className="w-3.5 h-3.5" />
                                                                    Venda não localizada no CIGAM — adicionar justificativa
                                                                </button>
                                                            ) : (
                                                                <div className="bg-amber-50 border border-amber-300 rounded p-2">
                                                                    <div className="flex items-start justify-between gap-2 mb-1">
                                                                        <div className="flex items-start gap-1 text-xs text-amber-800">
                                                                            <ExclamationTriangleIcon className="w-4 h-4 shrink-0 text-amber-600 mt-0.5" />
                                                                            <span>
                                                                                Venda não localizada no CIGAM — justificativa obrigatória.
                                                                                A loja será notificada por e-mail.
                                                                            </span>
                                                                        </div>
                                                                        {!hasText && (
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => setExpandedJustifications((p) => ({
                                                                                    ...p, [it.consignment_item_id]: false,
                                                                                }))}
                                                                                className="text-xs text-gray-500 hover:text-gray-700"
                                                                            >
                                                                                fechar
                                                                            </button>
                                                                        )}
                                                                    </div>
                                                                    <textarea
                                                                        value={itemJustifications[it.consignment_item_id] || ''}
                                                                        onChange={(e) => setItemJustifications((p) => ({
                                                                            ...p,
                                                                            [it.consignment_item_id]: e.target.value,
                                                                        }))}
                                                                        rows={2}
                                                                        maxLength={2000}
                                                                        autoFocus
                                                                        className="mt-1 block w-full rounded-md border-amber-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                                                                        placeholder={`Explique por que ${it.reference}${sizeDisplay ? ' Tam. '+sizeDisplay : ''} foi marcado como vendido sem registro no CIGAM…`}
                                                                    />
                                                                </div>
                                                            )}
                                                        </div>
                                                    );
                                                })()}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {errors.items && <p className="text-sm text-red-600">{errors.items}</p>}
                    </>
                )}

                <div>
                    <InputLabel value="Observações (opcional)" />
                    <textarea
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        rows={2}
                        maxLength={2000}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Anotações sobre o retorno…"
                    />
                </div>
            </div>
        </StandardModal>
    );
}

function SummaryCell({ label, value, color = 'gray', icon: Icon }) {
    const colors = {
        gray: 'bg-gray-100 text-gray-700 border-gray-200',
        green: 'bg-green-50 text-green-700 border-green-200',
        amber: 'bg-amber-50 text-amber-800 border-amber-200',
        red: 'bg-red-50 text-red-700 border-red-300',
        orange: 'bg-orange-50 text-orange-700 border-orange-300',
    };
    return (
        <div className={`rounded-md p-2 border ${colors[color] || colors.gray} text-center`}>
            <div className="flex items-center justify-center gap-1">
                {Icon && <Icon className="w-4 h-4" />}
                <span className="font-bold text-base">{value}</span>
            </div>
            <div className="text-[10px] uppercase mt-0.5">{label}</div>
        </div>
    );
}

function IssueGroup({ title, color, icon: Icon, items, render }) {
    const colors = {
        green: 'bg-green-50 border-green-200 text-green-800',
        amber: 'bg-amber-50 border-amber-200 text-amber-800',
        red: 'bg-red-50 border-red-300 text-red-800',
    };
    return (
        <div className={`border rounded-md p-3 ${colors[color]}`}>
            <div className={`flex items-center gap-1.5 mb-2 text-sm font-medium`}>
                {Icon && <Icon className="w-5 h-5" />}
                {title} ({items.length})
            </div>
            <div className="space-y-1 text-sm bg-white rounded border border-white/60 p-2">
                {items.map((it, idx) => (
                    <div key={idx} className="flex items-start justify-between gap-2 border-b last:border-0 pb-1 last:pb-0">
                        {render(it)}
                    </div>
                ))}
            </div>
        </div>
    );
}

function ItemRow({ refText, size, quantity, value, note }) {
    return (
        <>
            <div className="min-w-0 flex-1">
                <div className="font-medium text-gray-900 truncate">
                    {refText}
                    {size && <span className="ml-1 text-xs text-gray-500">Tam. {size}</span>}
                </div>
                {note && <div className="text-xs text-gray-600">{note}</div>}
            </div>
            <div className="text-right text-xs shrink-0">
                <div>Qtd: <strong>{quantity}</strong></div>
                <div>R$ {typeof value === 'number' ? value.toFixed(2).replace('.', ',') : value}</div>
            </div>
        </>
    );
}

