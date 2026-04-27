import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    LinkIcon,
    ArrowsRightLeftIcon,
    CheckCircleIcon,
    XCircleIcon,
    SparklesIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import LoadingSpinner from '@/Components/Shared/LoadingSpinner';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';

const MATCH_STATUS_VARIANT = {
    pending: 'warning',
    accepted: 'success',
    rejected: 'danger',
    expired: 'gray',
};

const MATCH_TYPE_LABEL = {
    mismatched_pair: 'Par trocado',
    damaged_complement: 'Avaria complementar',
};

export default function MatchesModal({
    show,
    onClose,
    item,
    canApprove = false,
}) {
    const [matches, setMatches] = useState([]);
    const [loading, setLoading] = useState(false);
    const [actionMatchId, setActionMatchId] = useState(null);
    const [action, setAction] = useState(null); // 'accept' | 'reject' | 'resolve'
    const [invoiceNumber, setInvoiceNumber] = useState('');
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const fetchMatches = async () => {
        if (!item?.ulid) return;
        setLoading(true);
        try {
            const res = await window.axios.get(route('damaged-products.matches.load', item.ulid));
            setMatches(res.data.matches || []);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (show) fetchMatches();
        // reset action
        setActionMatchId(null);
        setAction(null);
        setInvoiceNumber('');
        setReason('');
    }, [show, item?.ulid]);

    const startAction = (matchId, kind) => {
        setActionMatchId(matchId);
        setAction(kind);
        setInvoiceNumber('');
        setReason('');
    };

    const cancelAction = () => {
        setActionMatchId(null);
        setAction(null);
    };

    const submitAction = async () => {
        if (!actionMatchId || !action) return;
        setSubmitting(true);
        try {
            const url = route(`damaged-products.matches.${action}`, actionMatchId);
            const payload = {};
            if (action === 'accept') payload.invoice_number = invoiceNumber;
            if (action === 'reject') payload.reason = reason;

            await window.axios.post(url, payload);
            await fetchMatches();
            cancelAction();
            // sinaliza pra página que a lista pode ter mudado (status do produto)
            router.reload({ only: ['items', 'statistics'] });
        } catch (e) {
            const errMsg = e?.response?.data?.errors
                ? Object.values(e.response.data.errors).flat().join(' ')
                : (e?.response?.data?.message || 'Erro ao processar.');
            alert(errMsg);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={`Matches do produto ${item?.product_reference ?? ''}`}
            subtitle={item ? `${item.store?.code ?? ''} · ${item.store?.name ?? ''}` : null}
            headerColor="bg-purple-600"
            headerIcon={<LinkIcon className="h-5 w-5" />}
            maxWidth="3xl"
            loading={loading}
        >
            {!loading && matches.length === 0 && (
                <EmptyState
                    title="Nenhum match encontrado"
                    description="A engine ainda não encontrou produtos complementares em outras lojas para este registro."
                    icon={SparklesIcon}
                />
            )}

            <div className="space-y-3">
                {matches.map((m) => (
                    <div key={m.id} className="rounded-lg border bg-white shadow-sm">
                        {/* Header do match */}
                        <div className="flex items-center justify-between px-4 py-2 bg-gray-50 border-b rounded-t-lg">
                            <div className="flex items-center gap-2">
                                <span className="text-xs font-mono text-gray-500">#{m.id}</span>
                                <StatusBadge variant="purple">{MATCH_TYPE_LABEL[m.match_type] ?? m.match_type}</StatusBadge>
                                <StatusBadge variant={MATCH_STATUS_VARIANT[m.status] ?? 'gray'}>
                                    {m.status_label}
                                </StatusBadge>
                                <span className="text-xs text-gray-500">Score: {Number(m.match_score).toFixed(0)}</span>
                            </div>
                            {m.transfer && (
                                <a
                                    href={route('transfers.index')}
                                    className="text-xs text-indigo-600 hover:underline inline-flex items-center gap-1"
                                >
                                    <ArrowsRightLeftIcon className="h-4 w-4" />
                                    Transferência #{m.transfer.id} ({m.transfer.status})
                                </a>
                            )}
                        </div>

                        {/* Body */}
                        <div className="px-4 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <div className="text-xs font-medium text-gray-500">Produto parceiro</div>
                                <div className="text-sm">
                                    <div className="font-mono font-semibold">{m.partner?.product_reference}</div>
                                    <div className="text-gray-700">{m.partner?.product_name || '—'}</div>
                                    <div className="text-xs text-gray-500 mt-1">
                                        {m.partner?.store?.code} · {m.partner?.store?.name}
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div className="text-xs font-medium text-gray-500">Direção sugerida</div>
                                <div className="text-sm flex items-center gap-2 mt-1">
                                    <span className="px-2 py-1 bg-gray-100 rounded text-xs font-mono">
                                        {m.suggested_origin?.code}
                                    </span>
                                    <ArrowsRightLeftIcon className="h-4 w-4 text-gray-400" />
                                    <span className="px-2 py-1 bg-indigo-100 rounded text-xs font-mono text-indigo-700">
                                        {m.suggested_destination?.code}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Detalhes específicos por tipo */}
                        {m.match_type === 'mismatched_pair' && (
                            <div className="px-4 pb-3">
                                <table className="w-full text-xs border-collapse">
                                    <thead>
                                        <tr className="bg-yellow-50">
                                            <th className="border px-2 py-1 text-left">Produto</th>
                                            <th className="border px-2 py-1">Pé esquerdo</th>
                                            <th className="border px-2 py-1">Pé direito</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td className="border px-2 py-1 font-mono">Este (#{item?.id})</td>
                                            <td className="border px-2 py-1 text-center font-mono">{item?.mismatched_left_size}</td>
                                            <td className="border px-2 py-1 text-center font-mono">{item?.mismatched_right_size}</td>
                                        </tr>
                                        <tr>
                                            <td className="border px-2 py-1 font-mono">Parceiro (#{m.partner?.id})</td>
                                            <td className="border px-2 py-1 text-center font-mono">{m.partner?.mismatched_left_size}</td>
                                            <td className="border px-2 py-1 text-center font-mono">{m.partner?.mismatched_right_size}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p className="mt-2 text-xs text-gray-500">
                                    Combinando o pé esquerdo de um com o direito do outro (e vice-versa) forma 2 pares íntegros.
                                </p>
                            </div>
                        )}

                        {m.match_type === 'damaged_complement' && (
                            <div className="px-4 pb-3 text-xs text-gray-600">
                                Este produto: avaria no <strong>
                                    {item?.damaged_foot === 'left' ? 'pé esquerdo' : item?.damaged_foot === 'right' ? 'pé direito' : '—'}
                                </strong> tamanho <strong className="font-mono">{item?.damaged_size}</strong>
                                {' · '}
                                Parceiro: avaria no <strong>
                                    {m.partner?.damaged_foot === 'left' ? 'pé esquerdo' : m.partner?.damaged_foot === 'right' ? 'pé direito' : '—'}
                                </strong> tamanho <strong className="font-mono">{m.partner?.damaged_size}</strong>
                                . Combinando os pés bons forma 1 par íntegro tamanho <strong className="font-mono">{item?.damaged_size}</strong>.
                            </div>
                        )}

                        {m.reject_reason && (
                            <div className="px-4 pb-3 text-xs text-red-600">
                                <strong>Motivo da rejeição:</strong> {m.reject_reason}
                            </div>
                        )}

                        {/* Ações inline (só para matches pending + canApprove) */}
                        {m.status === 'pending' && canApprove && actionMatchId !== m.id && (
                            <div className="px-4 pb-3 flex gap-2">
                                <Button
                                    type="button"
                                    variant="success"
                                    size="sm"
                                    icon={CheckCircleIcon}
                                    onClick={() => startAction(m.id, 'accept')}
                                >
                                    Aceitar
                                </Button>
                                <Button
                                    type="button"
                                    variant="danger"
                                    size="sm"
                                    icon={XCircleIcon}
                                    onClick={() => startAction(m.id, 'reject')}
                                >
                                    Rejeitar
                                </Button>
                            </div>
                        )}

                        {/* Form inline de ação */}
                        {actionMatchId === m.id && (
                            <div className="px-4 pb-3 border-t bg-gray-50 pt-3">
                                {action === 'accept' && (
                                    <>
                                        <InputLabel htmlFor={`inv-${m.id}`} value="Número da NF (opcional)" />
                                        <TextInput
                                            id={`inv-${m.id}`}
                                            value={invoiceNumber}
                                            onChange={(e) => setInvoiceNumber(e.target.value)}
                                            placeholder="Ex: 12345"
                                            className="w-full mb-3"
                                        />
                                    </>
                                )}
                                {action === 'reject' && (
                                    <>
                                        <InputLabel htmlFor={`reason-${m.id}`} value="Motivo da rejeição *" />
                                        <textarea
                                            id={`reason-${m.id}`}
                                            value={reason}
                                            onChange={(e) => setReason(e.target.value)}
                                            rows={2}
                                            className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm mb-3"
                                            placeholder="Mínimo 5 caracteres..."
                                        />
                                    </>
                                )}
                                <div className="flex gap-2 justify-end">
                                    <Button type="button" variant="light" size="sm" onClick={cancelAction} disabled={submitting}>
                                        Cancelar
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={action === 'accept' ? 'success' : 'danger'}
                                        size="sm"
                                        loading={submitting}
                                        onClick={submitAction}
                                        disabled={action === 'reject' && reason.length < 5}
                                    >
                                        Confirmar {action === 'accept' ? 'aceite' : 'rejeição'}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </StandardModal>
    );
}
