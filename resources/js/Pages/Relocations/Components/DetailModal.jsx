import { useEffect, useState } from 'react';
import {
    RectangleStackIcon,
    PaperAirplaneIcon,
    PlayIcon,
    HandThumbUpIcon,
    HandThumbDownIcon,
    InboxArrowDownIcon,
    PrinterIcon,
    TrashIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

const STATUS_VARIANT = {
    draft: 'gray',
    requested: 'warning',
    approved: 'info',
    in_separation: 'purple',
    in_transit: 'indigo',
    completed: 'success',
    partial: 'warning',
    rejected: 'danger',
    cancelled: 'danger',
};

const fmtDateTime = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

const fmtDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

export default function DetailModal({ show, onClose, ulid, permissions = {}, onTransition }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    // Estado para iniciar separação inline (qty_separated por item)
    const [separating, setSeparating] = useState(false);
    const [separatedQty, setSeparatedQty] = useState({});

    // Estado para despachar (NF obrigatória)
    const [dispatchOpen, setDispatchOpen] = useState(false);
    const [dispatchData, setDispatchData] = useState({
        invoice_number: '',
        invoice_date: '',
        volumes_qty: '',
    });

    const load = async () => {
        if (!ulid) return;
        setLoading(true);
        setError(null);
        try {
            const res = await window.axios.get(route('relocations.show', ulid));
            setData(res.data.relocation);
            // Inicializa separatedQty com valores atuais
            const map = {};
            (res.data.relocation.items || []).forEach((it) => {
                map[it.id] = it.qty_separated || 0;
            });
            setSeparatedQty(map);
            setDispatchData({
                invoice_number: res.data.relocation.invoice_number || '',
                invoice_date: '',
                volumes_qty: '',
            });
        } catch (e) {
            setError(e.response?.data?.message || 'Falha ao carregar detalhes.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (show) {
            load();
            setDispatchOpen(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, ulid]);

    if (!show) return null;

    const r = data;

    // Romaneio disponível a partir de approved (loja origem precisa pra separar)
    const canPrintRomaneio = r && permissions.export && [
        'approved', 'in_separation', 'in_transit', 'completed', 'partial',
    ].includes(r.status);

    // Helpers de transição
    const handleTransition = (toStatus, payload = {}, note = null) => {
        if (!r) return;
        onTransition?.({ ulid: r.ulid }, toStatus, payload, note);
        onClose();
    };

    // Confirmar envio (in_separation → in_transit) — exige NF
    const handleDispatch = () => {
        if (!r || dispatchData.invoice_number.trim() === '') return;
        const separatedItems = Object.entries(separatedQty).map(([id, qty]) => ({
            id: parseInt(id, 10),
            qty_separated: parseInt(qty, 10) || 0,
        }));
        handleTransition('in_transit', {
            invoice_number: dispatchData.invoice_number,
            invoice_date: dispatchData.invoice_date || null,
            volumes_qty: dispatchData.volumes_qty || null,
            separated_items: separatedItems,
        });
    };

    // Salvar quantidades separadas sem transitar (ainda em in_separation)
    const handleSaveSeparated = () => {
        // Reusa endpoint de transição mas passa target = mesmo status atual
        // sem mudar — porém o controller só aceita transições reais.
        // Solução: gerar transição "fake" passando in_separation se já estamos
        // em in_separation. Não vai dar (canTransitionTo retorna false).
        // Alternativa simples: usar PUT update com items? Não existe na Fase 2.
        // Pra Fase 2 vamos simplificar: a separação efetiva acontece no momento
        // do dispatch (quantidades enviadas no payload separated_items).
        alert('Confirme as quantidades separadas e clique em "Confirmar envio (NF)" para gravar.');
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={r ? (r.title || `Remanejo #${r.id}`) : 'Carregando...'}
            subtitle={r ? `${r.origin_store?.code} → ${r.destination_store?.code} · ${r.type_name}` : ''}
            headerColor="bg-gray-700"
            headerIcon={<RectangleStackIcon className="h-5 w-5" />}
            headerBadges={r ? [
                { text: r.status_label, className: badgeClass(r.status_color) },
                { text: r.priority_label, className: priorityBadgeClass(r.priority) },
            ] : []}
            maxWidth="5xl"
            loading={loading}
            errorMessage={error}
            headerActions={r && canPrintRomaneio ? (
                <a
                    href={route('relocations.romaneio', r.ulid)}
                    target="_blank"
                    rel="noopener noreferrer"
                    title="Imprimir Romaneio"
                    className="inline-flex items-center gap-1 text-white/90 hover:text-white text-sm font-medium px-2 py-1 rounded hover:bg-white/10 transition-colors"
                >
                    <PrinterIcon className="h-5 w-5" />
                    <span className="hidden sm:inline">Romaneio</span>
                </a>
            ) : null}
        >
            {r && (
                <>
                    <StandardModal.Section title="Dados gerais">
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <StandardModal.Field label="Tipo" value={r.type_name} />
                            <StandardModal.Field label="Prioridade" value={r.priority_label} />
                            <StandardModal.Field label="Prazo (dias)" value={r.deadline_days || '—'} />
                            <StandardModal.Field label="Loja origem" value={r.origin_store ? `${r.origin_store.code} — ${r.origin_store.name}` : '—'} />
                            <StandardModal.Field label="Loja destino" value={r.destination_store ? `${r.destination_store.code} — ${r.destination_store.name}` : '—'} />
                            <StandardModal.Field label="Criado por" value={r.created_by_name || '—'} />
                        </div>
                        {r.observations && (
                            <div className="mt-3">
                                <InputLabel value="Observações" />
                                <p className="text-sm text-gray-700 whitespace-pre-wrap">{r.observations}</p>
                            </div>
                        )}
                    </StandardModal.Section>

                    {/* Métricas */}
                    <StandardModal.Section title="Itens e atendimento">
                        <div className="grid grid-cols-3 gap-3 mb-3">
                            <StandardModal.InfoCard
                                label="Solicitado"
                                value={r.total_requested ?? 0}
                            />
                            <StandardModal.InfoCard
                                label="Recebido"
                                value={r.total_received ?? 0}
                            />
                            <StandardModal.InfoCard
                                label="Atendimento"
                                value={`${r.fulfillment_percentage ?? 0}%`}
                            />
                        </div>

                        {/* Tabela de itens */}
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50 text-xs uppercase text-gray-600">
                                    <tr>
                                        <th className="px-2 py-2 text-left">Referência</th>
                                        <th className="px-2 py-2 text-left">Produto</th>
                                        <th className="px-2 py-2 text-left">Tamanho</th>
                                        <th className="px-2 py-2 text-right">Solicitado</th>
                                        <th className="px-2 py-2 text-right">
                                            {r.status === 'in_separation' || r.status === 'approved'
                                                ? 'Separar'
                                                : 'Separado'}
                                        </th>
                                        <th className="px-2 py-2 text-right">Recebido</th>
                                        <th className="px-2 py-2 text-right" title="Saída registrada no CIGAM">CIGAM ↗</th>
                                        <th className="px-2 py-2 text-right" title="Entrada registrada no CIGAM">CIGAM ↘</th>
                                        <th className="px-2 py-2 text-left">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {(r.items || []).map((it) => (
                                        <tr key={it.id}>
                                            <td className="px-2 py-2 font-mono text-xs">{it.product_reference}</td>
                                            <td className="px-2 py-2">
                                                <div>{it.product_name || '—'}</div>
                                                {it.product_color && <div className="text-xs text-gray-500">{it.product_color}</div>}
                                            </td>
                                            <td className="px-2 py-2">{it.size || '—'}</td>
                                            <td className="px-2 py-2 text-right tabular-nums">{it.qty_requested}</td>
                                            <td className="px-2 py-2 text-right tabular-nums">
                                                {(r.status === 'in_separation' || r.status === 'approved') && permissions.separate ? (
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={it.qty_requested}
                                                        value={separatedQty[it.id] ?? 0}
                                                        onChange={(e) => setSeparatedQty({ ...separatedQty, [it.id]: e.target.value })}
                                                        className="w-20 rounded-md border-gray-300 text-sm text-right tabular-nums"
                                                    />
                                                ) : (
                                                    it.qty_separated
                                                )}
                                            </td>
                                            <td className="px-2 py-2 text-right tabular-nums">{it.qty_received}</td>
                                            <td className="px-2 py-2 text-right tabular-nums text-gray-600">
                                                {it.dispatched_quantity > 0 ? it.dispatched_quantity : <span className="text-gray-300">—</span>}
                                            </td>
                                            <td className="px-2 py-2 text-right tabular-nums text-gray-600">
                                                {it.received_quantity > 0 ? it.received_quantity : <span className="text-gray-300">—</span>}
                                            </td>
                                            <td className="px-2 py-2">
                                                <StatusBadge variant={itemStatusVariant(it.item_status)}>
                                                    {itemStatusLabel(it.item_status)}
                                                </StatusBadge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </StandardModal.Section>

                    {/* Confirmar envio (NF) — visível em in_separation */}
                    {r.status === 'in_separation' && permissions.separate && (
                        <StandardModal.Section title="Confirmar envio (gera transferência)">
                            <p className="text-sm text-gray-600 mb-3">
                                Informe a NF de transferência para registrar o despacho. Uma transferência
                                física será criada automaticamente vinculada a este remanejo.
                            </p>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <InputLabel value="Número da NF *" />
                                    <TextInput
                                        value={dispatchData.invoice_number}
                                        onChange={(e) => setDispatchData({ ...dispatchData, invoice_number: e.target.value })}
                                        placeholder="Ex: 12345"
                                        maxLength={50}
                                        className="w-full"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Data da NF" />
                                    <TextInput
                                        type="date"
                                        value={dispatchData.invoice_date}
                                        onChange={(e) => setDispatchData({ ...dispatchData, invoice_date: e.target.value })}
                                        className="w-full"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Qtd. de volumes" />
                                    <TextInput
                                        type="number"
                                        min="1"
                                        value={dispatchData.volumes_qty}
                                        onChange={(e) => setDispatchData({ ...dispatchData, volumes_qty: e.target.value })}
                                        placeholder="Ex: 2"
                                        className="w-full"
                                    />
                                </div>
                            </div>
                            <div className="mt-3 flex justify-end">
                                <Button
                                    variant="primary"
                                    icon={PaperAirplaneIcon}
                                    onClick={handleDispatch}
                                    disabled={dispatchData.invoice_number.trim() === ''}
                                >
                                    Confirmar envio
                                </Button>
                            </div>
                        </StandardModal.Section>
                    )}

                    {/* Timeline */}
                    <StandardModal.Section title="Histórico de status">
                        <ol className="relative border-l-2 border-gray-200 ml-2 space-y-3">
                            {(r.status_history || []).map((h) => (
                                <li key={h.id} className="ml-4">
                                    <div className="absolute -left-[7px] mt-1.5 h-3 w-3 rounded-full bg-indigo-500 border-2 border-white" />
                                    <div className="text-xs text-gray-500">{fmtDateTime(h.created_at)}</div>
                                    <div className="text-sm">
                                        <span className="text-gray-500">{h.from_status_label || '—'}</span>
                                        {' → '}
                                        <span className="font-semibold">{h.to_status_label}</span>
                                    </div>
                                    {h.note && <div className="text-xs text-gray-700 italic mt-0.5">{h.note}</div>}
                                    {h.changed_by_name && <div className="text-xs text-gray-400">por {h.changed_by_name}</div>}
                                </li>
                            ))}
                        </ol>
                    </StandardModal.Section>

                    {/* Transferência vinculada (após in_transit) */}
                    {r.transfer && (
                        <StandardModal.Section title="Transferência vinculada">
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <StandardModal.Field label="ID Transfer" value={`#${r.transfer.id}`} />
                                <StandardModal.Field label="NF" value={r.transfer.invoice_number || '—'} />
                                <StandardModal.Field label="Status" value={r.transfer.status} />
                                <StandardModal.Field label="Data coleta" value={fmtDate(r.transfer.pickup_date)} />
                            </div>
                        </StandardModal.Section>
                    )}

                    {/* Reconciliação CIGAM em 2 pontas */}
                    {(r.invoice_number) && (
                        <StandardModal.Section title="Reconciliação CIGAM">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div className={`rounded-lg p-3 border ${r.cigam_dispatched_at ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'}`}>
                                    <div className="text-xs uppercase font-semibold text-gray-700">
                                        ↗ Saída na origem
                                    </div>
                                    {r.cigam_dispatched_at ? (
                                        <>
                                            <div className="text-sm text-green-800 font-medium mt-1">
                                                ✓ Despachada em {fmtDateTime(r.cigam_dispatched_at)}
                                            </div>
                                            <div className="text-xs text-gray-600 mt-1">
                                                Aderência: confirmada via CIGAM (movement_code=5+S)
                                            </div>
                                        </>
                                    ) : (
                                        <div className="text-sm text-gray-500 mt-1">
                                            Aguardando registro de saída no CIGAM
                                        </div>
                                    )}
                                </div>

                                <div className={`rounded-lg p-3 border ${r.cigam_received_at ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'}`}>
                                    <div className="text-xs uppercase font-semibold text-gray-700">
                                        ↘ Entrada no destino
                                    </div>
                                    {r.cigam_received_at ? (
                                        <>
                                            <div className="text-sm text-green-800 font-medium mt-1">
                                                ✓ Recebida em {fmtDateTime(r.cigam_received_at)}
                                            </div>
                                            <div className="text-xs text-gray-600 mt-1">
                                                Confirmada via CIGAM (movement_code=5+E)
                                            </div>
                                        </>
                                    ) : (
                                        <div className="text-sm text-gray-500 mt-1">
                                            Aguardando registro de entrada no CIGAM
                                        </div>
                                    )}
                                </div>
                            </div>
                        </StandardModal.Section>
                    )}
                </>
            )}
        </StandardModal>
    );
}

function itemStatusVariant(status) {
    return { pending: 'gray', partial: 'warning', completed: 'success' }[status] ?? 'gray';
}

function itemStatusLabel(status) {
    return { pending: 'Pendente', partial: 'Parcial', completed: 'Completo' }[status] ?? status;
}

function badgeClass(color) {
    const map = {
        gray: 'bg-gray-100 text-gray-800',
        warning: 'bg-amber-100 text-amber-800',
        info: 'bg-blue-100 text-blue-800',
        purple: 'bg-purple-100 text-purple-800',
        indigo: 'bg-indigo-100 text-indigo-800',
        success: 'bg-green-100 text-green-800',
        orange: 'bg-orange-100 text-orange-800',
        danger: 'bg-red-100 text-red-800',
    };
    return map[color] ?? map.gray;
}

function priorityBadgeClass(priority) {
    const map = {
        low: 'bg-gray-100 text-gray-700',
        normal: 'bg-blue-100 text-blue-700',
        high: 'bg-amber-100 text-amber-800',
        urgent: 'bg-red-100 text-red-800',
    };
    return map[priority] ?? map.normal;
}
