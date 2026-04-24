import { useForm, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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
 *  1. User preenche NF retorno (número + data + loja).
 *  2. Sistema busca a NF no CIGAM (movement_code=21) e compara com a
 *     NF de saída já salva. Divergências são exibidas com alertas
 *     visuais (qty, valor, produto a mais/a menos, não voltou).
 *  3. Para itens da saída que não voltaram (missing_in_return), user
 *     marca "Vendido" ou "Devolvido (fora da NF)". Se "Vendido", o
 *     sistema verifica movement_code=2 pro CPF do cliente nos 7 dias.
 *     Se NÃO achar, exige justificativa + dispara email para a loja.
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
}) {
    const [comparison, setComparison] = useState(null);
    const [saleCheck, setSaleCheck] = useState(null);
    const [lookupLoading, setLookupLoading] = useState(false);
    const [lookupError, setLookupError] = useState(null);
    const [itemActions, setItemActions] = useState({});

    const { data, setData, processing, errors, reset, setError, clearErrors } = useForm({
        return_invoice_number: '',
        return_date: new Date().toISOString().slice(0, 10),
        return_store_code: '',
        notes: '',
        sale_justification: '',
    });

    // Ao abrir, pré-popula loja
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
    }, [show, consignmentSummary?.id]);

    // Reset completo ao fechar
    useEffect(() => {
        if (!show) {
            reset();
            setComparison(null);
            setSaleCheck(null);
            setLookupError(null);
            setItemActions({});
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

            // Pré-marca default "sold" para itens que não voltaram
            const defaults = {};
            (json.comparison?.comparison?.missing_in_return || []).forEach((it) => {
                defaults[it.consignment_item_id] = 'sold';
            });
            setItemActions(defaults);
        } catch (e) {
            setLookupError(e.message);
        } finally {
            setLookupLoading(false);
        }
    };

    const soldItemsWithoutSaleConfirmed = (() => {
        if (!comparison?.comparison?.missing_in_return?.length) return 0;
        if (saleCheck?.found_in_cigam) return 0;
        return Object.values(itemActions).filter((a) => a === 'sold').length;
    })();

    const submit = () => {
        if (!comparison?.found) {
            setError('return_invoice_number', 'Busque e compare a NF antes de registrar.');
            return;
        }

        const cmp = comparison.comparison;
        const items = [];

        cmp.matched.forEach((m) => items.push({
            consignment_item_id: m.consignment_item_id,
            quantity: m.return_quantity,
            action: 'returned',
        }));
        cmp.quantity_divergent.forEach((m) => items.push({
            consignment_item_id: m.consignment_item_id,
            quantity: m.return_quantity,
            action: 'returned',
        }));
        cmp.value_divergent.forEach((m) => items.push({
            consignment_item_id: m.consignment_item_id,
            quantity: m.return_quantity,
            action: 'returned',
        }));
        (cmp.missing_in_return || []).forEach((m) => {
            const action = itemActions[m.consignment_item_id];
            if (!action) return;
            items.push({
                consignment_item_id: m.consignment_item_id,
                quantity: m.outbound_pending,
                action,
            });
        });

        if (items.length === 0) {
            setError('items', 'Nenhum item para registrar.');
            return;
        }

        if (soldItemsWithoutSaleConfirmed > 0 && !data.sale_justification?.trim()) {
            setError('sale_justification', 'Justificativa obrigatória — venda alegada sem confirmação no CIGAM.');
            return;
        }

        router.post(
            route('consignments.returns.store', consignmentSummary.id),
            {
                return_invoice_number: data.return_invoice_number,
                return_date: data.return_date,
                return_store_code: data.return_store_code,
                notes: data.notes || null,
                sale_justification: data.sale_justification || null,
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
                        Identificação da NF de retorno (movement_code=21)
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
                            <TextInput
                                type="text"
                                value={data.return_store_code}
                                onChange={(e) => setData('return_store_code', e.target.value.toUpperCase())}
                                className="mt-1 block w-full"
                                maxLength={10}
                                placeholder="Z421"
                                disabled={!canChooseStore}
                                title={canChooseStore ? '' : 'Loja do usuário — só supervisão pode alterar'}
                            />
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
                                        Confira loja + número + data. Aguarde o próximo sync ou marque
                                        manualmente os itens pendentes como vendidos/devolvidos abaixo.
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

                        {cmp?.matched?.length > 0 && (
                            <IssueGroup title="Itens confirmados no retorno" color="green" icon={CheckCircleIcon} items={cmp.matched}
                                render={(it) => <ItemRow refText={it.reference} size={it.size_label} quantity={it.return_quantity} value={it.return_unit_value} />}
                            />
                        )}
                        {cmp?.quantity_divergent?.length > 0 && (
                            <IssueGroup title="Quantidade diferente do esperado" color="amber" icon={ExclamationTriangleIcon} items={cmp.quantity_divergent}
                                render={(it) => <ItemRow refText={it.reference} size={it.size_label} quantity={`${it.return_quantity} / ${it.outbound_pending}`} value={it.return_unit_value} note={it.reason} />}
                            />
                        )}
                        {cmp?.value_divergent?.length > 0 && (
                            <IssueGroup title="Valor unitário divergente" color="amber" icon={ExclamationTriangleIcon} items={cmp.value_divergent}
                                render={(it) => <ItemRow refText={it.reference} size={it.size_label} quantity={it.return_quantity} value={`${Number(it.return_unit_value).toFixed(2)} / ${Number(it.outbound_unit_value).toFixed(2)}`} note={it.reason} />}
                            />
                        )}
                        {cmp?.extra_in_return?.length > 0 && (
                            <IssueGroup title="Itens no retorno que NÃO constam na NF de saída" color="red" icon={XCircleIcon} items={cmp.extra_in_return}
                                render={(it) => <ItemRow refText={it.reference || '—'} size={it.size_label} quantity={it.quantity} value={it.unit_value} note={it.reason} />}
                            />
                        )}

                        {hasMissing && (
                            <div className="bg-orange-50 border border-orange-200 rounded-md p-3">
                                <div className="flex items-start gap-2 mb-2">
                                    <InformationCircleIcon className="w-5 h-5 shrink-0 text-orange-600 mt-0.5" />
                                    <div className="text-sm font-medium text-orange-900">
                                        Itens que não voltaram — indique o destino:
                                    </div>
                                </div>

                                {saleCheck && !saleCheck.found_in_cigam && (
                                    <div className="text-xs text-amber-800 bg-amber-100 border border-amber-200 rounded p-2 mb-2 flex items-start gap-1">
                                        <ExclamationTriangleIcon className="w-4 h-4 shrink-0 mt-0.5" />
                                        <div>
                                            <strong>Atenção:</strong> não foi encontrada venda no CIGAM (movement_code=2)
                                            para o CPF {saleCheck.cpf ?? '—'} nos próximos {saleCheck.window_days} dias após {data.return_date}.
                                            Se marcar algum item como "Vendido", será necessário justificar e a
                                            loja será notificada por e-mail.
                                        </div>
                                    </div>
                                )}

                                {saleCheck?.found_in_cigam && (
                                    <div className="text-xs text-green-800 bg-green-100 border border-green-200 rounded p-2 mb-2 flex items-start gap-1">
                                        <CheckCircleIcon className="w-4 h-4 shrink-0 mt-0.5" />
                                        <div>
                                            {saleCheck.movements.length} venda(s) localizada(s) no CIGAM para o CPF nos 7 dias seguintes.
                                        </div>
                                    </div>
                                )}

                                <div className="space-y-2">
                                    {cmp.missing_in_return.map((it) => (
                                        <div
                                            key={it.consignment_item_id}
                                            className="bg-white border border-orange-200 rounded p-2 flex flex-wrap items-center justify-between gap-2"
                                        >
                                            <div className="text-sm">
                                                <span className="font-medium">{it.reference}</span>
                                                {it.size_cigam_code && (
                                                    <span className="ml-1 text-xs text-gray-500">Tam. {it.size_cigam_code}</span>
                                                )}
                                                <span className="ml-2 text-xs text-gray-600">{it.outbound_pending} peça(s)</span>
                                            </div>
                                            <div className="flex gap-1 text-xs">
                                                <ActionRadio
                                                    checked={itemActions[it.consignment_item_id] === 'sold'}
                                                    onChange={() => setItemActions((p) => ({ ...p, [it.consignment_item_id]: 'sold' }))}
                                                    label="Vendido"
                                                    color="blue"
                                                />
                                                <ActionRadio
                                                    checked={itemActions[it.consignment_item_id] === 'returned'}
                                                    onChange={() => setItemActions((p) => ({ ...p, [it.consignment_item_id]: 'returned' }))}
                                                    label="Devolvido (fora da NF)"
                                                    color="green"
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {soldItemsWithoutSaleConfirmed > 0 && (
                            <div className="bg-red-50 border border-red-300 rounded-md p-3">
                                <InputLabel value="Justificativa obrigatória — venda alegada sem confirmação CIGAM *" className="!text-red-800" />
                                <textarea
                                    value={data.sale_justification}
                                    onChange={(e) => setData('sale_justification', e.target.value)}
                                    rows={3}
                                    maxLength={2000}
                                    className="mt-1 block w-full rounded-md border-red-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                                    placeholder="Explique por que o item foi marcado como vendido sem registro no CIGAM…"
                                />
                                <InputError message={errors.sale_justification} className="mt-1" />
                                <p className="mt-1 text-xs text-red-700">
                                    A loja será notificada por e-mail com esta justificativa.
                                </p>
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

function ActionRadio({ checked, onChange, label, color }) {
    const colors = {
        blue: checked ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-blue-700 border-blue-300 hover:bg-blue-50',
        green: checked ? 'bg-green-600 text-white border-green-600' : 'bg-white text-green-700 border-green-300 hover:bg-green-50',
    };
    return (
        <button
            type="button"
            onClick={onChange}
            className={`px-3 py-1.5 rounded-md border font-medium transition-colors ${colors[color]}`}
        >
            {label}
        </button>
    );
}
