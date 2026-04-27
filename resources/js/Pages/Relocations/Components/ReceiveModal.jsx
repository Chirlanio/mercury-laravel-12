import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { InboxArrowDownIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';

/**
 * Modal de recebimento. Carrega itens do remanejo (com qty_separated),
 * permite informar qty_received e motivo categorizado por item, e dispara
 * transição para `completed` (se tudo bate) ou `partial` (se há divergência).
 *
 * `receiver_name` é obrigatório — registra quem assinou o recebimento na
 * loja destino. Vai pro Transfer.receiver_name após confirmação.
 *
 * `reasonOptions` vem do backend (RelocationItemReason::labels) — não
 * hardcoded aqui pra manter sincronia.
 */
export default function ReceiveModal({ show, onClose, ulid, reasonOptions = {} }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [errorMsg, setErrorMsg] = useState(null);
    const [processing, setProcessing] = useState(false);
    const [rows, setRows] = useState({});  // {item_id: {qty_received, reason_code, observations}}
    const [globalNote, setGlobalNote] = useState('');
    const [receiverName, setReceiverName] = useState('');
    const [validationErrors, setValidationErrors] = useState({});

    const load = async () => {
        if (!ulid) return;
        setLoading(true);
        setErrorMsg(null);
        try {
            const res = await window.axios.get(route('relocations.show', ulid));
            setData(res.data.relocation);
            // Inicializa rows com qty_received = qty_separated (assume tudo recebido)
            const next = {};
            (res.data.relocation.items || []).forEach((it) => {
                next[it.id] = {
                    qty_received: it.qty_separated || it.qty_requested,
                    reason_code: '',
                    observations: '',
                };
            });
            setRows(next);
        } catch (e) {
            setErrorMsg(e.response?.data?.message || 'Falha ao carregar itens.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (show) {
            load();
            setGlobalNote('');
            setReceiverName('');
            setValidationErrors({});
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, ulid]);

    const updateRow = (id, key, value) => {
        setRows((prev) => ({ ...prev, [id]: { ...prev[id], [key]: value } }));
    };

    // Determina se é completo (todos bateram com solicitado) ou parcial
    const allComplete = data?.items?.every((it) => {
        const r = rows[it.id];
        return r && parseInt(r.qty_received, 10) >= it.qty_requested;
    });

    const submit = () => {
        if (!data) return;
        if (receiverName.trim().length < 3) {
            setValidationErrors({ receiver_name: 'Informe quem está recebendo (mínimo 3 caracteres).' });
            return;
        }
        // Para itens com divergência (qty_received < qty_requested), reason_code é exigido
        const missingReasons = data.items.filter((it) => {
            const r = rows[it.id];
            const qtyRec = parseInt(r?.qty_received ?? 0, 10);
            return qtyRec < it.qty_requested && !r?.reason_code;
        });
        if (missingReasons.length > 0) {
            setValidationErrors({
                received_items: `Informe o motivo da divergência em ${missingReasons.length} item(ns).`,
            });
            return;
        }

        setValidationErrors({});
        setProcessing(true);

        const received_items = Object.entries(rows).map(([id, r]) => ({
            id: parseInt(id, 10),
            qty_received: parseInt(r.qty_received, 10) || 0,
            reason_code: r.reason_code || null,
            observations: r.observations || null,
        }));

        const toStatus = allComplete ? 'completed' : 'partial';

        router.post(route('relocations.transition', data.ulid), {
            to_status: toStatus,
            note: globalNote || null,
            receiver_name: receiverName,
            received_items,
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errs) => {
                setValidationErrors(errs || {});
            },
            onFinish: () => setProcessing(false),
        });
    };

    if (!show) return null;
    const r = data;

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Receber remanejo"
            subtitle={r ? `${r.title || `#${r.id}`} · ${r.origin_store?.code} → ${r.destination_store?.code}` : ''}
            headerColor="bg-green-600"
            headerIcon={<InboxArrowDownIcon className="h-5 w-5" />}
            maxWidth="5xl"
            loading={loading}
            errorMessage={errorMsg}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit={submit}
                    submitLabel={allComplete ? 'Registrar recebimento completo' : 'Registrar recebimento parcial'}
                    submitVariant={allComplete ? 'success' : 'warning'}
                    processing={processing}
                />
            }
        >
            {r && (
                <>
                    <StandardModal.Section title="Recebido por">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Nome de quem recebeu *" />
                                <TextInput
                                    value={receiverName}
                                    onChange={(e) => setReceiverName(e.target.value)}
                                    placeholder="Nome completo da pessoa que assinou"
                                    maxLength={150}
                                    className="w-full"
                                />
                                <InputError message={validationErrors.receiver_name} className="mt-1" />
                            </div>
                            <div>
                                <p className="text-xs text-gray-600 pt-6">
                                    O nome será registrado no Transfer físico vinculado e na timeline do remanejo.
                                </p>
                            </div>
                        </div>
                    </StandardModal.Section>

                    <StandardModal.Section title="Itens enviados">
                        <p className="text-sm text-gray-600 mb-3">
                            Confira a quantidade recebida de cada item. Em caso de divergência,
                            informe o motivo. O remanejo será marcado como{' '}
                            <strong className={allComplete ? 'text-green-700' : 'text-amber-700'}>
                                {allComplete ? 'Concluído' : 'Recebido Parcial'}
                            </strong>.
                        </p>

                        <InputError message={validationErrors.received_items} className="mb-2" />
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50 text-xs uppercase text-gray-600">
                                    <tr>
                                        <th className="px-2 py-2 text-left">Referência</th>
                                        <th className="px-2 py-2 text-left">Produto</th>
                                        <th className="px-2 py-2 text-right">Solicitado</th>
                                        <th className="px-2 py-2 text-right">Separado</th>
                                        <th className="px-2 py-2 text-right">Recebido *</th>
                                        <th className="px-2 py-2 text-left">Motivo (se divergente)</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {(r.items || []).map((it) => {
                                        const row = rows[it.id] || {};
                                        const qtyRec = parseInt(row.qty_received, 10) || 0;
                                        const isShort = qtyRec < it.qty_requested;
                                        return (
                                            <tr key={it.id} className={isShort ? 'bg-amber-50' : ''}>
                                                <td className="px-2 py-2 font-mono text-xs">{it.product_reference}</td>
                                                <td className="px-2 py-2">
                                                    <div>{it.product_name || '—'}</div>
                                                    {(it.product_color || it.size) && (
                                                        <div className="text-xs text-gray-500">
                                                            {[it.product_color, it.size].filter(Boolean).join(' · ')}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-2 py-2 text-right tabular-nums">{it.qty_requested}</td>
                                                <td className="px-2 py-2 text-right tabular-nums">{it.qty_separated}</td>
                                                <td className="px-2 py-2 text-right">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={it.qty_separated || it.qty_requested}
                                                        value={row.qty_received ?? 0}
                                                        onChange={(e) => updateRow(it.id, 'qty_received', e.target.value)}
                                                        className="w-20 rounded-md border-gray-300 text-sm text-right tabular-nums"
                                                    />
                                                </td>
                                                <td className="px-2 py-2">
                                                    {isShort ? (
                                                        <select
                                                            value={row.reason_code ?? ''}
                                                            onChange={(e) => updateRow(it.id, 'reason_code', e.target.value)}
                                                            className="block w-full rounded-md border-gray-300 text-sm"
                                                        >
                                                            <option value="">— Selecione —</option>
                                                            {Object.entries(reasonOptions).map(([code, label]) => (
                                                                <option key={code} value={code}>{label}</option>
                                                            ))}
                                                        </select>
                                                    ) : (
                                                        <span className="text-xs text-gray-400">—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </StandardModal.Section>

                    <StandardModal.Section title="Observação (opcional)">
                        <textarea
                            rows={2}
                            value={globalNote}
                            onChange={(e) => setGlobalNote(e.target.value)}
                            className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                            placeholder="Comentário geral sobre o recebimento (irá no histórico)"
                            maxLength={2000}
                        />
                    </StandardModal.Section>
                </>
            )}
        </StandardModal>
    );
}
