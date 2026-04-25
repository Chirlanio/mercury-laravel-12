import { useMemo } from 'react';
import { PaperAirplaneIcon, PaperClipIcon, ArrowDownTrayIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import LoadingSpinner from '@/Components/Shared/LoadingSpinner';
import Button from '@/Components/Button';

const COLOR_MAP = {
    success: 'success',
    warning: 'warning',
    info: 'info',
    danger: 'danger',
    purple: 'purple',
    gray: 'gray',
    orange: 'orange',
    teal: 'teal',
};

const DOT_BY_COLOR = {
    success: 'bg-green-500',
    warning: 'bg-amber-500',
    info: 'bg-blue-500',
    danger: 'bg-red-500',
    purple: 'bg-purple-500',
    gray: 'bg-gray-400',
};

const KIND_LABEL = { expense: 'Solicitação', accountability: 'Prestação' };

const STATUS_DOT = {
    draft: 'bg-gray-400',
    submitted: 'bg-amber-500',
    approved: 'bg-blue-500',
    rejected: 'bg-red-500',
    finalized: 'bg-green-500',
    cancelled: 'bg-red-500',
    pending: 'bg-gray-400',
    in_progress: 'bg-blue-500',
};

export default function TravelExpenseDetailModal({
    show,
    onClose,
    expense,
    loading = false,
    onOpenAccountability,
    canExport = false,
}) {
    const timelineItems = useMemo(() => {
        if (!expense?.history) return [];
        return [...expense.history].reverse().map((h) => ({
            id: h.id,
            title: `[${KIND_LABEL[h.kind] ?? h.kind}] ${labelOf(h.from_status)} → ${labelOf(h.to_status)}`,
            subtitle: `${h.changed_by?.name ?? '—'} · ${formatDateTime(h.created_at)}`,
            notes: h.note,
            dotColor: STATUS_DOT[h.to_status] ?? 'bg-indigo-500',
        }));
    }, [expense]);

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={expense ? `${expense.origin} → ${expense.destination}` : 'Detalhes'}
            subtitle={expense ? `Beneficiado: ${expense.employee?.name ?? '—'}` : ''}
            headerColor="bg-gray-700"
            headerIcon={PaperAirplaneIcon}
            headerBadges={expense ? [
                {
                    label: expense.status_label,
                    variant: COLOR_MAP[expense.status_color] ?? 'gray',
                },
                {
                    label: `Prestação: ${expense.accountability_status_label}`,
                    variant: COLOR_MAP[expense.accountability_status_color] ?? 'gray',
                },
            ] : []}
            headerActions={canExport && expense ? (
                <a
                    href={route('travel-expenses.pdf', expense.ulid)}
                    className="inline-flex items-center gap-1.5 rounded-md bg-white/10 hover:bg-white/20 text-white text-xs font-medium px-2.5 py-1.5 transition-colors"
                    title="Baixar comprovante em PDF"
                >
                    <ArrowDownTrayIcon className="h-4 w-4" />
                    PDF
                </a>
            ) : null}
            maxWidth="4xl"
            loading={loading}
        >
            {!expense && !loading ? (
                <div className="text-center py-12 text-gray-500">Nenhum dado.</div>
            ) : !expense ? (
                <div className="flex justify-center py-12"><LoadingSpinner /></div>
            ) : (
                <>
                    <StandardModal.Section title="Resumo Financeiro">
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <StandardModal.InfoCard
                                label="Valor da verba"
                                value={formatCurrency(expense.value)}
                                highlight
                            />
                            <StandardModal.InfoCard
                                label="Diária"
                                value={`${formatCurrency(expense.daily_rate)} × ${expense.days_count}`}
                            />
                            <StandardModal.InfoCard
                                label="Total prestado"
                                value={formatCurrency(expense.accounted_value)}
                                colorClass={expense.accounted_value > expense.value ? 'text-amber-700' : 'text-gray-900'}
                            />
                            <StandardModal.InfoCard
                                label="Saldo"
                                value={formatCurrency(expense.balance)}
                                colorClass={expense.balance < 0 ? 'text-red-700' : 'text-green-700'}
                            />
                        </div>
                    </StandardModal.Section>

                    <StandardModal.Section title="Dados da Viagem">
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <StandardModal.Field label="Loja" value={expense.store ? `${expense.store.code} — ${expense.store.name}` : expense.store_code} />
                            <StandardModal.Field label="Solicitante" value={expense.created_by?.name ?? '—'} />
                            <StandardModal.Field label="Beneficiado" value={expense.employee?.name ?? '—'} />
                            <StandardModal.Field label="Trecho" value={`${expense.origin} → ${expense.destination}`} />
                            <StandardModal.Field label="Saída" value={formatDate(expense.initial_date)} />
                            <StandardModal.Field label="Retorno" value={formatDate(expense.end_date)} />
                            {expense.client_name && (
                                <StandardModal.Field label="Cliente / contato" value={expense.client_name} />
                            )}
                        </div>
                        {expense.description && (
                            <div className="mt-3">
                                <div className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Justificativa</div>
                                <p className="text-sm text-gray-700 bg-gray-50 rounded p-3 border border-gray-200">{expense.description}</p>
                            </div>
                        )}
                    </StandardModal.Section>

                    <StandardModal.Section title="Pagamento">
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                            {expense.masked_cpf && (
                                <StandardModal.Field label="CPF" value={expense.masked_cpf} mono />
                            )}
                            {expense.bank && (
                                <>
                                    <StandardModal.Field label="Banco" value={expense.bank.cod_bank ? `${expense.bank.cod_bank} — ${expense.bank.bank_name}` : expense.bank.bank_name} />
                                    <StandardModal.Field label="Agência" value={expense.bank_branch ?? '—'} mono />
                                    <StandardModal.Field label="Conta" value={expense.bank_account ?? '—'} mono />
                                </>
                            )}
                            {expense.pix_type && (
                                <>
                                    <StandardModal.Field label="Tipo de chave PIX" value={expense.pix_type.name} />
                                    <StandardModal.Field label="Chave PIX" value={expense.pix_key ?? '—'} mono />
                                </>
                            )}
                            {!expense.bank && !expense.pix_type && (
                                <div className="col-span-full text-sm text-gray-500 italic">Nenhuma forma de pagamento informada.</div>
                            )}
                        </div>
                    </StandardModal.Section>

                    <StandardModal.Section title="Prestação de Contas">
                        <div className="flex items-center justify-between mb-3">
                            <div>
                                <StatusBadge
                                    label={expense.accountability_status_label}
                                    variant={COLOR_MAP[expense.accountability_status_color] ?? 'gray'}
                                />
                                <span className="ml-3 text-sm text-gray-500">
                                    {(expense.items?.length ?? 0)} {(expense.items?.length === 1 ? 'item' : 'itens')}
                                </span>
                            </div>
                            {onOpenAccountability && expense.status === 'approved' && (
                                <Button
                                    variant="primary-soft"
                                    size="sm"
                                    icon={PaperClipIcon}
                                    onClick={onOpenAccountability}
                                >
                                    Gerenciar itens
                                </Button>
                            )}
                        </div>
                        {expense.items?.length > 0 && (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-gray-50 text-gray-600 uppercase text-xs">
                                        <tr>
                                            <th className="px-3 py-2 text-left">Data</th>
                                            <th className="px-3 py-2 text-left">Tipo</th>
                                            <th className="px-3 py-2 text-left">Descrição</th>
                                            <th className="px-3 py-2 text-left">NF</th>
                                            <th className="px-3 py-2 text-right">Valor</th>
                                            <th className="px-3 py-2 text-center">Comp.</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {expense.items.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-3 py-2 whitespace-nowrap">{formatDate(item.expense_date)}</td>
                                                <td className="px-3 py-2 whitespace-nowrap">{item.type_expense?.name ?? '—'}</td>
                                                <td className="px-3 py-2">{item.description}</td>
                                                <td className="px-3 py-2">{item.invoice_number ?? '—'}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{formatCurrency(item.value)}</td>
                                                <td className="px-3 py-2 text-center">
                                                    {item.has_attachment ? (
                                                        <a
                                                            href={route('travel-expenses.items.download', [expense.ulid, item.id])}
                                                            className="text-indigo-600 hover:underline inline-flex items-center"
                                                            title={item.attachment_original_name}
                                                        >
                                                            <PaperClipIcon className="h-4 w-4" />
                                                        </a>
                                                    ) : (
                                                        <span className="text-gray-400">—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {expense.accountability_rejection_reason && (
                            <div className="mt-3 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">
                                <strong>Prestação rejeitada:</strong> {expense.accountability_rejection_reason}
                            </div>
                        )}
                    </StandardModal.Section>

                    {(expense.rejection_reason || expense.cancelled_reason || expense.internal_notes) && (
                        <StandardModal.Section title="Observações">
                            {expense.rejection_reason && (
                                <div className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800 mb-2">
                                    <strong>Verba rejeitada:</strong> {expense.rejection_reason}
                                </div>
                            )}
                            {expense.cancelled_reason && (
                                <div className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800 mb-2">
                                    <strong>Cancelada:</strong> {expense.cancelled_reason}
                                </div>
                            )}
                            {expense.internal_notes && (
                                <div className="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                                    <strong>Notas internas:</strong> {expense.internal_notes}
                                </div>
                            )}
                        </StandardModal.Section>
                    )}

                    <StandardModal.Section title="Histórico">
                        <StandardModal.Timeline items={timelineItems} />
                    </StandardModal.Section>
                </>
            )}
        </StandardModal>
    );
}

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(`${iso}T00:00:00`);
    return d.toLocaleDateString('pt-BR');
}

function formatDateTime(iso) {
    if (!iso) return '—';
    return new Date(iso.replace(' ', 'T')).toLocaleString('pt-BR');
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
}

const STATUS_LABELS = {
    draft: 'Rascunho',
    submitted: 'Solicitada',
    approved: 'Aprovada',
    rejected: 'Rejeitada',
    finalized: 'Finalizada',
    cancelled: 'Cancelada',
    pending: 'Aguardando Lançamento',
    in_progress: 'Em Andamento',
};

function labelOf(s) {
    if (!s) return 'Início';
    return STATUS_LABELS[s] ?? s;
}
