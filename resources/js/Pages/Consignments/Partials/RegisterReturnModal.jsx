import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowUturnLeftIcon, MinusIcon, PlusIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

/**
 * Registra um evento de retorno parcial/total. Cada item pendente vira
 * um card com stepper +/− mobile-friendly. O backend aplica a regra M1
 * (itens devolvidos ⊆ itens da saída, com quantity ≤ pendente por item).
 *
 * Props:
 *  - consignmentSummary: row da listagem (tem id, outbound_invoice_number, etc.)
 *    Os items detalhados vêm via fetch quando o modal abre.
 */
export default function RegisterReturnModal({ show, onClose, consignmentSummary }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(false);

    const { data, setData, post, processing, errors, reset, setError, clearErrors } = useForm({
        return_invoice_number: '',
        return_date: new Date().toISOString().slice(0, 10),
        return_store_code: '',
        notes: '',
    });

    // Carrega itens pendentes da consignação quando o modal abre
    useEffect(() => {
        if (!show || !consignmentSummary?.id) return;

        let cancelled = false;
        setLoading(true);
        setData('return_store_code', consignmentSummary?.store?.code || '');

        fetch(route('consignments.show', consignmentSummary.id), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then(({ consignment }) => {
                if (cancelled) return;
                const pendingItems = (consignment.items || [])
                    .filter((it) => it.pending_quantity > 0)
                    .map((it) => ({
                        consignment_item_id: it.id,
                        reference: it.reference,
                        size: it.size_label || it.size_cigam_code || '—',
                        description: it.description,
                        pending: it.pending_quantity,
                        quantity: 0,
                        unit_value: it.unit_value,
                    }));
                setItems(pendingItems);
            })
            .catch(() => setItems([]))
            .finally(() => setLoading(false));

        return () => { cancelled = true; };
    }, [show, consignmentSummary?.id]);

    // Reset ao fechar
    useEffect(() => {
        if (!show) {
            reset();
            setItems([]);
            clearErrors();
        }
    }, [show]);

    const updateQty = (idx, delta) => {
        setItems((prev) => prev.map((it, i) => {
            if (i !== idx) return it;
            const next = Math.max(0, Math.min(it.pending, it.quantity + delta));
            return { ...it, quantity: next };
        }));
    };

    const setQtyMax = (idx) => {
        setItems((prev) => prev.map((it, i) => i === idx ? { ...it, quantity: it.pending } : it));
    };

    const totalItemsSelected = items.reduce((sum, it) => sum + it.quantity, 0);
    const totalValue = items.reduce((sum, it) => sum + (it.quantity * Number(it.unit_value || 0)), 0);

    const submit = () => {
        const selected = items.filter((it) => it.quantity > 0);
        if (selected.length === 0) {
            setError('items', 'Selecione ao menos um item.');
            return;
        }

        if (!data.return_date) {
            setError('return_date', 'Informe a data de retorno.');
            return;
        }

        post(route('consignments.returns.store', consignmentSummary.id), {
            data: {
                ...data,
                items: selected.map((it) => ({
                    consignment_item_id: it.consignment_item_id,
                    quantity: it.quantity,
                })),
            },
            preserveScroll: true,
            onSuccess: () => onClose(),
            transform: (d) => ({
                ...d,
                items: selected.map((it) => ({
                    consignment_item_id: it.consignment_item_id,
                    quantity: it.quantity,
                })),
            }),
        });
    };

    if (!consignmentSummary) return null;

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Registrar retorno"
            subtitle={`Consignação #${consignmentSummary.id} — ${consignmentSummary.recipient_name}`}
            headerColor="bg-emerald-600"
            headerIcon={<ArrowUturnLeftIcon className="h-5 w-5" />}
            maxWidth="3xl"
            footer={
                <StandardModal.Footer>
                    <div className="flex-1" />
                    <Button variant="secondary" onClick={onClose} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button
                        variant="success"
                        onClick={submit}
                        disabled={processing || totalItemsSelected === 0}
                    >
                        {processing ? 'Registrando…' : `Registrar ${totalItemsSelected} peça(s)`}
                    </Button>
                </StandardModal.Footer>
            }
        >
            <div className="space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <InputLabel value="Número da NF de retorno" />
                        <TextInput
                            type="text"
                            value={data.return_invoice_number}
                            onChange={(e) => setData('return_invoice_number', e.target.value)}
                            className="mt-1 block w-full"
                            maxLength={20}
                            inputMode="numeric"
                            placeholder="Opcional"
                        />
                    </div>
                    <div>
                        <InputLabel value="Data do retorno *" />
                        <TextInput
                            type="date"
                            value={data.return_date}
                            onChange={(e) => setData('return_date', e.target.value)}
                            className="mt-1 block w-full"
                        />
                        <InputError message={errors.return_date} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Loja do retorno" />
                        <TextInput
                            type="text"
                            value={data.return_store_code}
                            onChange={(e) => setData('return_store_code', e.target.value)}
                            className="mt-1 block w-full"
                            maxLength={10}
                            placeholder="Z421"
                        />
                    </div>
                </div>

                <div>
                    <InputLabel value="Observações" />
                    <textarea
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        rows={2}
                        maxLength={2000}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Anotações sobre o retorno…"
                    />
                </div>

                <div className="border-t pt-4">
                    <h3 className="font-medium text-gray-900 mb-2">
                        Itens pendentes {items.length > 0 && `(${items.length})`}
                    </h3>

                    {loading && (
                        <div className="text-center py-6 text-sm text-gray-500">
                            Carregando itens…
                        </div>
                    )}

                    {!loading && items.length === 0 && (
                        <div className="text-center py-6 text-sm text-gray-500 border-2 border-dashed rounded-md">
                            Não há itens pendentes nesta consignação.
                        </div>
                    )}

                    {errors.items && (
                        <p className="text-sm text-red-600 mb-2">{errors.items}</p>
                    )}

                    {!loading && items.length > 0 && (
                        <div className="space-y-2">
                            {items.map((it, idx) => (
                                <div
                                    key={it.consignment_item_id}
                                    className={`border rounded-md p-3 transition-colors ${
                                        it.quantity > 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-white border-gray-200'
                                    }`}
                                >
                                    <div className="flex items-center justify-between gap-3 flex-wrap">
                                        <div className="flex-1 min-w-0">
                                            <div className="font-medium text-gray-900 truncate">
                                                {it.reference}
                                                <span className="text-xs text-gray-500 font-normal ml-2">
                                                    Tam. {it.size}
                                                </span>
                                            </div>
                                            {it.description && (
                                                <div className="text-xs text-gray-500 truncate">{it.description}</div>
                                            )}
                                            <div className="text-xs text-gray-600 mt-0.5">
                                                Pendente: <span className="font-medium">{it.pending}</span>
                                                {' · '}
                                                R$ {Number(it.unit_value).toFixed(2).replace('.', ',')} cada
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-1.5 shrink-0">
                                            <button
                                                type="button"
                                                onClick={() => updateQty(idx, -1)}
                                                disabled={it.quantity <= 0}
                                                aria-label="Diminuir"
                                                className="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-md border border-gray-300 hover:bg-gray-100 disabled:opacity-50"
                                            >
                                                <MinusIcon className="w-4 h-4" />
                                            </button>
                                            <input
                                                type="number"
                                                min={0}
                                                max={it.pending}
                                                value={it.quantity}
                                                onChange={(e) => {
                                                    const v = Math.max(0, Math.min(it.pending, Number(e.target.value)));
                                                    setItems((prev) => prev.map((i, ix) => ix === idx ? { ...i, quantity: v } : i));
                                                }}
                                                className="w-16 text-center rounded-md border-gray-300 min-h-[44px]"
                                                inputMode="numeric"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => updateQty(idx, 1)}
                                                disabled={it.quantity >= it.pending}
                                                aria-label="Aumentar"
                                                className="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-md border border-gray-300 hover:bg-gray-100 disabled:opacity-50"
                                            >
                                                <PlusIcon className="w-4 h-4" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setQtyMax(idx)}
                                                className="text-xs text-indigo-600 hover:text-indigo-800 px-2 py-1 min-h-[44px]"
                                            >
                                                Todos
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {totalItemsSelected > 0 && (
                        <div className="mt-3 bg-emerald-50 border border-emerald-200 rounded-md p-3 flex justify-between items-center">
                            <div>
                                <div className="text-sm font-medium text-emerald-900">
                                    {totalItemsSelected} peça(s) selecionada(s)
                                </div>
                            </div>
                            <div className="font-bold text-emerald-900">
                                R$ {totalValue.toFixed(2).replace('.', ',')}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </StandardModal>
    );
}
