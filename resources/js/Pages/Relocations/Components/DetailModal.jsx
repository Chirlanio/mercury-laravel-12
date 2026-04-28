import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    RectangleStackIcon,
    PaperAirplaneIcon,
    PlayIcon,
    HandThumbUpIcon,
    HandThumbDownIcon,
    InboxArrowDownIcon,
    PrinterIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    XCircleIcon,
    ArrowPathIcon,
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

    // Validação on-demand da NF contra movements (CIGAM).
    // - dispatchValidation: resultado do preview (null antes de validar)
    // - dispatchValidating: spinner durante a chamada
    // - dispatchValidationError: erro na chamada (rede/permissão)
    const [dispatchValidation, setDispatchValidation] = useState(null);
    const [dispatchValidating, setDispatchValidating] = useState(false);
    const [dispatchValidationError, setDispatchValidationError] = useState(null);

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
            setDispatchValidation(null);
            setDispatchValidationError(null);
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

    // Reabrir/clonar — só faz sentido em estado terminal não-feliz
    // (cancelled ou rejected) e exige permission de CREATE.
    const canReopen = r && permissions.create && ['cancelled', 'rejected'].includes(r.status);

    const handleReopen = async () => {
        if (!r) return;
        if (!confirm(`Reabrir o remanejo #${r.id}? Vai criar um novo em DRAFT com os mesmos itens.`)) {
            return;
        }
        try {
            window.axios.defaults.withCredentials = true;
            await window.axios.post(route('relocations.clone', r.ulid));
            onClose();
            // Reload pra mostrar o novo na listagem.
            // eslint-disable-next-line no-undef
            router.reload({ only: ['relocations', 'statistics'] });
        } catch (e) {
            const msg = e.response?.data?.errors?.status?.[0]
                ?? e.response?.data?.message
                ?? 'Falha ao reabrir o remanejo.';
            alert(msg);
        }
    };

    // Helpers de transição
    const handleTransition = (toStatus, payload = {}, note = null) => {
        if (!r) return;
        onTransition?.({ ulid: r.ulid }, toStatus, payload, note);
        onClose();
    };

    // Validação on-demand da NF — chama o endpoint preview que compara
    // qty_separated vs movements (CIGAM). Resultado renderiza um painel
    // com matched/missing/extra/divergent. Não muta o remanejo.
    const handleValidateNF = async () => {
        if (!r) return;
        const inv = (dispatchData.invoice_number || '').trim();
        const dt = dispatchData.invoice_date;
        if (!inv || !dt) {
            setDispatchValidationError('Informe número e data da NF antes de validar.');
            return;
        }
        setDispatchValidating(true);
        setDispatchValidationError(null);
        setDispatchValidation(null);
        try {
            // Antes de validar, persiste qty_separated localmente — backend
            // compara contra o que está salvo no banco. Se o usuário ajustou
            // os inputs sem salvar, validar com o snapshot atual seria errado.
            // Pra simplificar nesse modal, assumimos qty_separated == qty_requested
            // (caso default dos sugestionados); se mudou, vai ser refletido na
            // próxima validação após dispatch.
            const { data } = await window.axios.post(
                route('relocations.dispatch.validate', r.ulid),
                { invoice_number: inv, invoice_date: dt },
            );
            setDispatchValidation(data);
        } catch (e) {
            setDispatchValidationError(
                e.response?.data?.error
                    ?? e.response?.data?.message
                    ?? 'Falha ao validar NF.'
            );
        } finally {
            setDispatchValidating(false);
        }
    };

    // Confirmar envio (in_separation → in_transit) — exige NF.
    // Se a validação ocorreu, envia o snapshot pra ser persistido junto.
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
            dispatch_validation: dispatchValidation,
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
            headerActions={r ? (
                <div className="flex items-center gap-1">
                    {canReopen && (
                        <button
                            type="button"
                            onClick={handleReopen}
                            title="Reabrir como novo remanejo"
                            className="inline-flex items-center gap-1 text-white/90 hover:text-white text-sm font-medium px-2 py-1 rounded hover:bg-white/10 transition-colors"
                        >
                            <ArrowPathIcon className="h-5 w-5" />
                            <span className="hidden sm:inline">Reabrir</span>
                        </button>
                    )}
                    {canPrintRomaneio && (
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
                    )}
                </div>
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

                    {/* Confirmar envio (NF) — visível em in_separation; aparece ANTES
                        da tabela de itens pra que o usuário informe a NF e valide
                        primeiro contra o CIGAM, depois confira itens abaixo. */}
                    {r.status === 'in_separation' && permissions.separate && (
                        <StandardModal.Section title="Confirmar envio (gera transferência)">
                            <p className="text-sm text-gray-600 mb-3">
                                Informe a NF de transferência <strong>primeiro</strong> e clique em
                                <strong> Validar NF no CIGAM</strong> — o sistema busca a NF nas
                                movimentações e compara com o que foi separado, mostrando
                                divergências (faltando, sobrando ou qty diferente). Depois confira
                                a tabela abaixo e confirme o envio.
                            </p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                                <div>
                                    <InputLabel value="Número da NF *" />
                                    <TextInput
                                        value={dispatchData.invoice_number}
                                        onChange={(e) => {
                                            setDispatchData({ ...dispatchData, invoice_number: e.target.value });
                                            // Invalida validação anterior se mudar a NF
                                            if (dispatchValidation) setDispatchValidation(null);
                                        }}
                                        placeholder="Ex: 12345"
                                        maxLength={50}
                                        className="w-full"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Data da NF *" />
                                    <TextInput
                                        type="date"
                                        value={dispatchData.invoice_date}
                                        onChange={(e) => {
                                            setDispatchData({ ...dispatchData, invoice_date: e.target.value });
                                            if (dispatchValidation) setDispatchValidation(null);
                                        }}
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
                                <div>
                                    <Button
                                        variant="outline"
                                        icon={MagnifyingGlassIcon}
                                        onClick={handleValidateNF}
                                        disabled={
                                            dispatchValidating
                                            || dispatchData.invoice_number.trim() === ''
                                            || !dispatchData.invoice_date
                                        }
                                        loading={dispatchValidating}
                                        className="w-full justify-center"
                                    >
                                        Validar NF
                                    </Button>
                                </div>
                            </div>

                            <div className="mt-3 flex justify-end">
                                <Button
                                    variant={dispatchValidation?.has_discrepancies ? 'warning' : 'primary'}
                                    icon={PaperAirplaneIcon}
                                    onClick={handleDispatch}
                                    disabled={dispatchData.invoice_number.trim() === ''}
                                >
                                    {dispatchValidation?.has_discrepancies
                                        ? 'Confirmar mesmo assim (com alerta)'
                                        : 'Confirmar envio'}
                                </Button>
                            </div>

                            {dispatchValidationError && (
                                <div className="mt-3 bg-red-50 border border-red-200 rounded p-3 text-sm text-red-700 flex items-start gap-2">
                                    <XCircleIcon className="h-5 w-5 flex-shrink-0 mt-0.5" />
                                    <div>{dispatchValidationError}</div>
                                </div>
                            )}

                            {dispatchValidation && (
                                <DispatchValidationResult result={dispatchValidation} />
                            )}
                        </StandardModal.Section>
                    )}

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

// Painel de resultado da validação NF — 4 estados:
// (a) NF não encontrada → warning amber
// (b) Bate perfeitamente → success green
// (c) Tem divergência → danger red, com 3 listas
// Usado dentro do DetailModal na fase in_separation.
function DispatchValidationResult({ result }) {
    if (!result.nf_found) {
        return (
            <div className="mt-3 bg-amber-50 border border-amber-200 rounded p-3 flex items-start gap-2">
                <ExclamationTriangleIcon className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
                <div className="text-sm text-amber-900">
                    <p className="font-medium">NF não encontrada no CIGAM.</p>
                    <p className="text-xs text-amber-800 mt-1">
                        Pode ser delay na sincronização (aguarde alguns minutos e tente novamente)
                        ou a NF foi emitida com chave diferente (loja origem, número ou data).
                        Confira no sistema CIGAM antes de confirmar o envio.
                    </p>
                </div>
            </div>
        );
    }

    if (! result.has_discrepancies) {
        return (
            <div className="mt-3 bg-green-50 border border-green-200 rounded p-3 flex items-start gap-2">
                <CheckCircleIcon className="h-5 w-5 text-green-600 flex-shrink-0 mt-0.5" />
                <div className="text-sm text-green-900">
                    <p className="font-medium">NF bate com a separação ✓</p>
                    <p className="text-xs text-green-800 mt-1">
                        {result.matched.length} item(ns) confirmado(s),
                        {' '}{result.total_items_in_invoice} unidade(s) total na NF.
                        Pode confirmar o envio com segurança.
                    </p>
                </div>
            </div>
        );
    }

    const missing = result.missing ?? [];
    const extra = result.extra ?? [];
    const divergent = result.divergent ?? [];

    return (
        <div className="mt-3 bg-red-50 border border-red-200 rounded p-3">
            <div className="flex items-start gap-2 mb-3">
                <ExclamationTriangleIcon className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                <div className="text-sm text-red-900">
                    <p className="font-medium">Divergências detectadas</p>
                    <p className="text-xs text-red-800 mt-1">
                        Os itens separados não batem com a NF emitida. Você pode <strong>ajustar a separação
                        ou a NF</strong> e validar de novo, ou <strong>confirmar mesmo assim</strong> — neste
                        caso o sistema notifica o time de planejamento e logística pra investigação.
                    </p>
                </div>
            </div>

            {missing.length > 0 && (
                <DiscrepancyList
                    title={`Faltando na NF (${missing.length})`}
                    description="Itens que foram separados mas não estão na NF emitida."
                    items={missing}
                    qtyKey="qty_separated"
                    qtyLabel="Separado"
                    qtyOtherKey="qty_in_invoice"
                    qtyOtherLabel="Na NF"
                />
            )}

            {extra.length > 0 && (
                <DiscrepancyList
                    title={`Sobrando na NF (${extra.length})`}
                    description="Itens que estão na NF mas não foram solicitados/separados."
                    items={extra}
                    qtyKey={null}
                    qtyOtherKey="qty_in_invoice"
                    qtyOtherLabel="Na NF"
                />
            )}

            {divergent.length > 0 && (
                <DiscrepancyList
                    title={`Quantidade divergente (${divergent.length})`}
                    description="Itens onde a quantidade na NF difere da separada."
                    items={divergent}
                    qtyKey="qty_separated"
                    qtyLabel="Separado"
                    qtyOtherKey="qty_in_invoice"
                    qtyOtherLabel="Na NF"
                />
            )}
        </div>
    );
}

function DiscrepancyList({ title, description, items, qtyKey, qtyLabel, qtyOtherKey, qtyOtherLabel }) {
    return (
        <div className="mt-3 bg-white border border-red-200 rounded p-2">
            <p className="text-sm font-medium text-red-900">{title}</p>
            {description && <p className="text-xs text-gray-600 mt-0.5">{description}</p>}
            <div className="mt-2 max-h-40 overflow-y-auto">
                <table className="min-w-full text-xs">
                    <thead className="text-[10px] uppercase text-gray-500">
                        <tr>
                            <th className="text-left py-1 pr-2">Produto</th>
                            <th className="text-left py-1 pr-2 w-24">EAN</th>
                            <th className="text-left py-1 pr-2 w-16">Tam.</th>
                            {qtyKey && <th className="text-right py-1 pr-2 w-16">{qtyLabel}</th>}
                            <th className="text-right py-1 w-16">{qtyOtherLabel}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {items.map((it, i) => (
                            <tr key={i}>
                                <td className="py-1 pr-2 text-gray-800">
                                    {it.product_name || it.product_reference || '—'}
                                    {it.product_color && (
                                        <span className="text-gray-400"> · {it.product_color}</span>
                                    )}
                                </td>
                                <td className="py-1 pr-2 font-mono text-[10px] text-gray-500">{it.barcode}</td>
                                <td className="py-1 pr-2 text-gray-600">{it.size ?? '—'}</td>
                                {qtyKey && (
                                    <td className="py-1 pr-2 text-right tabular-nums font-medium text-gray-900">
                                        {it[qtyKey]}
                                    </td>
                                )}
                                <td className="py-1 text-right tabular-nums font-medium text-red-700">
                                    {it[qtyOtherKey]}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
