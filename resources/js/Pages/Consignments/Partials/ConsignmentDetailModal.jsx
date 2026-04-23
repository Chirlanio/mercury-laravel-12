import { useState } from 'react';
import {
    EyeIcon,
    InformationCircleIcon,
    ArchiveBoxIcon,
    ArrowUturnLeftIcon,
    ClockIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';

const TABS = [
    { key: 'info', label: 'Dados', icon: InformationCircleIcon },
    { key: 'items', label: 'Itens', icon: ArchiveBoxIcon },
    { key: 'returns', label: 'Retornos', icon: ArrowUturnLeftIcon },
    { key: 'history', label: 'Histórico', icon: ClockIcon },
];

/**
 * Modal de detalhes de uma consignação com 4 abas (Dados / Itens /
 * Retornos / Histórico). Mobile-first: abas rolam horizontalmente com
 * flex-nowrap e touch-targets ≥44px; conteúdo abaixo é scrollável.
 */
export default function ConsignmentDetailModal({
    show,
    onClose,
    consignment,
    statusColors = {},
}) {
    const [tab, setTab] = useState('info');

    if (!consignment) return null;

    const c = consignment;
    const totalValue = Number(c.outbound_total_value || 0);
    const returnedValue = Number(c.returned_total_value || 0);
    const soldValue = Number(c.sold_total_value || 0);
    const lostValue = Number(c.lost_total_value || 0);

    const formatCurrency = (v) => `R$ ${Number(v || 0).toFixed(2).replace('.', ',')}`;
    const formatDate = (d) => d ? new Date(d).toLocaleDateString('pt-BR') : '—';
    const formatDateTime = (d) => d ? new Date(d).toLocaleString('pt-BR') : '—';

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={`Consignação #${c.id}`}
            subtitle={c.recipient_name}
            headerColor="bg-gray-700"
            headerIcon={EyeIcon}
            maxWidth="4xl"
            headerBadges={[
                { label: c.type_label, color: 'info' },
                { label: c.status_label, color: statusColors[c.status] || 'gray' },
            ]}
        >
            {/* Abas — horizontal scroll no mobile */}
            <div className="border-b border-gray-200 mb-4 overflow-x-auto">
                <div className="flex gap-1 min-w-max">
                    {TABS.map((t) => {
                        const Icon = t.icon;
                        const active = tab === t.key;
                        return (
                            <button
                                key={t.key}
                                type="button"
                                onClick={() => setTab(t.key)}
                                className={`
                                    flex items-center gap-1.5 px-4 py-3 text-sm font-medium border-b-2 transition-colors min-h-[44px]
                                    ${active
                                        ? 'border-indigo-600 text-indigo-700'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}
                                `}
                            >
                                <Icon className="w-4 h-4" />
                                {t.label}
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Aba: Dados */}
            {tab === 'info' && (
                <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <StandardModal.Field label="Tipo" value={c.type_label} />
                        <StandardModal.Field
                            label="Status"
                            value={<StatusBadge color={statusColors[c.status] || 'gray'}>{c.status_label}</StatusBadge>}
                        />
                        <StandardModal.Field label="Loja" value={c.store ? `${c.store.code} — ${c.store.name}` : '—'} />
                        <StandardModal.Field label="Consultor(a)" value={c.employee?.name || '—'} />
                        <StandardModal.Field label="Destinatário" value={c.recipient_name} />
                        <StandardModal.Field label="Documento" value={c.recipient_document || '—'} />
                        <StandardModal.Field label="Telefone" value={c.recipient_phone || '—'} />
                        <StandardModal.Field label="E-mail" value={c.recipient_email || '—'} />
                        <StandardModal.Field label="NF de saída" value={`${c.outbound_invoice_number} — ${formatDate(c.outbound_invoice_date)}`} />
                        <StandardModal.Field
                            label="Prazo de retorno"
                            value={
                                <span className={c.is_overdue ? 'text-red-600 font-medium' : ''}>
                                    {formatDate(c.expected_return_date)}
                                    {c.is_overdue && ' (atrasada)'}
                                </span>
                            }
                        />
                    </div>

                    <div className="bg-gray-50 rounded-md p-4 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div>
                            <div className="text-xs text-gray-600">Total enviado</div>
                            <div className="font-semibold">{formatCurrency(totalValue)}</div>
                            <div className="text-xs text-gray-500">{c.outbound_items_count} peça(s)</div>
                        </div>
                        <div>
                            <div className="text-xs text-gray-600">Devolvido</div>
                            <div className="font-semibold text-green-700">{formatCurrency(returnedValue)}</div>
                            <div className="text-xs text-gray-500">{c.returned_items_count} peça(s)</div>
                        </div>
                        <div>
                            <div className="text-xs text-gray-600">Vendido</div>
                            <div className="font-semibold text-blue-700">{formatCurrency(soldValue)}</div>
                            <div className="text-xs text-gray-500">{c.sold_items_count} peça(s)</div>
                        </div>
                        <div>
                            <div className="text-xs text-gray-600">Perdido</div>
                            <div className="font-semibold text-red-700">{formatCurrency(lostValue)}</div>
                            <div className="text-xs text-gray-500">{c.lost_items_count} peça(s)</div>
                        </div>
                    </div>

                    {c.notes && (
                        <StandardModal.Section title="Observações">
                            <p className="text-sm text-gray-700 whitespace-pre-wrap">{c.notes}</p>
                        </StandardModal.Section>
                    )}
                </div>
            )}

            {/* Aba: Itens */}
            {tab === 'items' && (
                <div className="overflow-x-auto">
                    {(!c.items || c.items.length === 0) ? (
                        <div className="text-center py-8 text-sm text-gray-500">Sem itens.</div>
                    ) : (
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Referência</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Tam.</th>
                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-600 uppercase">Qtd</th>
                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-600 uppercase">Dev.</th>
                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-600 uppercase">Vend.</th>
                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-600 uppercase hidden md:table-cell">Valor</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {c.items.map((it) => (
                                    <tr key={it.id}>
                                        <td className="px-3 py-2">
                                            <div className="font-medium">{it.reference}</div>
                                            {it.description && (
                                                <div className="text-xs text-gray-500 truncate max-w-xs">{it.description}</div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">{it.size_label || it.size_cigam_code || '—'}</td>
                                        <td className="px-3 py-2 text-right">{it.quantity}</td>
                                        <td className="px-3 py-2 text-right text-green-700">{it.returned_quantity}</td>
                                        <td className="px-3 py-2 text-right text-blue-700">{it.sold_quantity}</td>
                                        <td className="px-3 py-2 text-right hidden md:table-cell">{formatCurrency(it.total_value)}</td>
                                        <td className="px-3 py-2">
                                            <StatusBadge color={it.status_color || 'gray'}>{it.status_label}</StatusBadge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            )}

            {/* Aba: Retornos */}
            {tab === 'returns' && (
                <div>
                    {(!c.returns || c.returns.length === 0) ? (
                        <div className="text-center py-8 text-sm text-gray-500">Nenhum retorno registrado ainda.</div>
                    ) : (
                        <div className="space-y-3">
                            {c.returns.map((r) => (
                                <div key={r.id} className="border border-gray-200 rounded-md p-3">
                                    <div className="flex items-start justify-between gap-2 flex-wrap">
                                        <div>
                                            <div className="font-medium">
                                                NF {r.return_invoice_number || '(sem NF)'} — {formatDate(r.return_date)}
                                            </div>
                                            <div className="text-xs text-gray-500">
                                                Loja {r.return_store_code} · Registrado por {r.registered_by?.name || '—'}
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <div className="font-semibold">{formatCurrency(r.returned_value)}</div>
                                            <div className="text-xs text-gray-500">{r.returned_quantity} peça(s)</div>
                                        </div>
                                    </div>
                                    {r.notes && (
                                        <div className="mt-2 text-xs text-gray-600 bg-gray-50 rounded p-2">{r.notes}</div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {/* Aba: Histórico */}
            {tab === 'history' && (
                <div>
                    {(!c.status_history || c.status_history.length === 0) ? (
                        <div className="text-center py-8 text-sm text-gray-500">Sem histórico.</div>
                    ) : (
                        <div className="space-y-2">
                            {c.status_history.map((h, idx) => (
                                <div key={idx} className="flex gap-3 items-start text-sm border-l-2 border-indigo-200 pl-3 py-1">
                                    <div className="flex-1">
                                        <div className="font-medium text-gray-900">
                                            {h.from_status ? (
                                                <span>
                                                    <span className="text-gray-500">{h.from_status}</span>
                                                    <span className="mx-1">→</span>
                                                    {h.to_status}
                                                </span>
                                            ) : (
                                                <span>Criado como <span className="font-mono">{h.to_status}</span></span>
                                            )}
                                        </div>
                                        {h.note && (
                                            <div className="text-xs text-gray-600 mt-0.5">{h.note}</div>
                                        )}
                                        <div className="text-xs text-gray-400 mt-0.5">
                                            {formatDateTime(h.created_at)}
                                            {h.changed_by && ` · ${h.changed_by.name}`}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </StandardModal>
    );
}
