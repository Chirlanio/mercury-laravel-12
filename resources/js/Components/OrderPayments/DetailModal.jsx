import { useState, useEffect } from 'react';
import {
    XMarkIcon, ArrowRightIcon, ArrowLeftIcon, CheckCircleIcon,
    ExclamationTriangleIcon, ClockIcon, BanknotesIcon,
    BuildingOfficeIcon, DocumentTextIcon, UserIcon,
} from '@heroicons/react/24/outline';
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/react/24/solid';
import StandardModal from '@/Components/StandardModal';

const fmtCurrency = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);

const STATUS_CONFIG = {
    backlog: { bg: 'bg-gray-100', text: 'text-gray-800', ring: 'ring-gray-300', header: 'bg-gray-600', dot: 'bg-gray-500' },
    doing:   { bg: 'bg-blue-100', text: 'text-blue-800', ring: 'ring-blue-300', header: 'bg-blue-600', dot: 'bg-blue-500' },
    waiting: { bg: 'bg-yellow-100', text: 'text-yellow-800', ring: 'ring-yellow-300', header: 'bg-yellow-500', dot: 'bg-yellow-500' },
    done:    { bg: 'bg-green-100', text: 'text-green-800', ring: 'ring-green-300', header: 'bg-green-600', dot: 'bg-green-500' },
};

export default function DetailModal({ orderId, onClose, onTransition, canEdit }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (!orderId) return;
        setLoading(true);
        fetch(route('order-payments.show', orderId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then(d => { setData(d); setLoading(false); })
            .catch(() => setLoading(false));
    }, [orderId]);

    if (!orderId) return null;

    const order = data?.order;
    const sc = order ? STATUS_CONFIG[order.status] : {};

    const badges = [];
    if (order) {
        badges.push({ text: order.status_label });
        if (order.is_overdue) badges.push({ text: 'Vencida', className: 'bg-red-500 text-white' });
    }

    const headerActions = order && canEdit ? (
        <>
            {order.status !== 'done' && (
                <button onClick={() => onTransition(order, { backlog: 'doing', doing: 'waiting', waiting: 'done' }[order.status])}
                    className="inline-flex items-center px-3 py-1.5 text-xs font-medium bg-white/20 text-white rounded-md hover:bg-white/30 transition">
                    <ArrowRightIcon className="h-3.5 w-3.5 mr-1" /> Avançar
                </button>
            )}
            {order.status !== 'backlog' && (
                <button onClick={() => onTransition(order, { doing: 'backlog', waiting: 'doing', done: 'waiting' }[order.status])}
                    className="inline-flex items-center px-3 py-1.5 text-xs font-medium bg-white/20 text-white rounded-md hover:bg-white/30 transition">
                    <ArrowLeftIcon className="h-3.5 w-3.5 mr-1" /> Retornar
                </button>
            )}
        </>
    ) : undefined;

    return (
        <StandardModal
            show={!!orderId}
            onClose={onClose}
            title={order ? `Ordem #${order.id}` : 'Ordem'}
            headerColor={sc.header || 'bg-gray-600'}
            headerBadges={badges}
            headerActions={headerActions}
            loading={loading}
            errorMessage={!loading && !order ? 'Erro ao carregar dados.' : undefined}
        >
                                {/* Valor destaque */}
                                <StandardModal.Highlight>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-xs font-medium text-indigo-500 uppercase tracking-wide">Valor Total</p>
                                            <p className="text-3xl font-bold text-indigo-700 mt-1">{order.formatted_total}</p>
                                        </div>
                                        <div className="text-right space-y-1">
                                            {order.date_payment && <p className="text-sm text-gray-600"><ClockIcon className="inline h-4 w-4 mr-1 text-gray-400" />Vencimento: <strong>{order.date_payment}</strong></p>}
                                            {order.date_paid && <p className="text-sm text-green-600"><CheckCircleSolid className="inline h-4 w-4 mr-1" />Pago em: <strong>{order.date_paid}</strong></p>}
                                        </div>
                                    </div>
                                </StandardModal.Highlight>

                                {/* Info Grid — 2 colunas */}
                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                    {/* Coluna 1: Dados Gerais */}
                                    <StandardModal.Section title="Dados Gerais" icon={<DocumentTextIcon className="h-4 w-4" />}>
                                        <div className="grid grid-cols-2 gap-x-4 gap-y-3">
                                            <StandardModal.Field label="Fornecedor" value={order.supplier_name} />
                                            <StandardModal.Field label="Loja" value={order.store?.name} />
                                            <StandardModal.Field label="Responsável" value={order.manager_name} />
                                            <StandardModal.Field label="Tipo Pagamento" value={order.payment_type} />
                                            <StandardModal.Field label="NF" value={order.number_nf} mono />
                                            <StandardModal.Field label="Lançamento" value={order.launch_number} mono />
                                        </div>
                                        {order.description && (
                                            <div className="mt-3 pt-3 border-t border-gray-100">
                                                <p className="text-xs font-medium text-gray-400 uppercase mb-1">Descrição</p>
                                                <p className="text-sm text-gray-700">{order.description}</p>
                                            </div>
                                        )}
                                    </StandardModal.Section>

                                    {/* Coluna 2: Financeiro */}
                                    <StandardModal.Section title="Financeiro" icon={<BanknotesIcon className="h-4 w-4" />}>
                                        <div className="grid grid-cols-2 gap-x-4 gap-y-3">
                                            <StandardModal.Field label="Adiantamento" value={order.advance ? 'Sim' : 'Não'} badge={order.advance ? 'yellow' : null} />
                                            <StandardModal.Field label="Valor Adiantamento" value={order.advance_amount ? fmtCurrency(order.advance_amount) : '-'} />
                                            <StandardModal.Field label="Comprovante" value={order.proof ? 'Sim' : 'Não'} badge={order.proof ? 'green' : null} />
                                            <StandardModal.Field label="Rateio" value={order.has_allocation ? 'Sim' : 'Não'} badge={order.has_allocation ? 'blue' : null} />
                                        </div>
                                        {/* Dados bancarios/PIX */}
                                        {(order.bank_name || order.pix_key) && (
                                            <div className="mt-3 pt-3 border-t border-gray-100">
                                                <p className="text-xs font-medium text-gray-400 uppercase mb-2 flex items-center gap-1">
                                                    <BuildingOfficeIcon className="h-3.5 w-3.5" />
                                                    {order.pix_key ? 'Dados PIX' : 'Dados Bancários'}
                                                </p>
                                                {order.pix_key ? (
                                                    <div className="grid grid-cols-2 gap-x-4 gap-y-2">
                                                        <StandardModal.Field label="Tipo Chave" value={order.pix_key_type} />
                                                        <StandardModal.Field label="Chave PIX" value={order.pix_key} mono />
                                                    </div>
                                                ) : (
                                                    <div className="grid grid-cols-2 gap-x-4 gap-y-2">
                                                        <StandardModal.Field label="Banco" value={order.bank_name} />
                                                        <StandardModal.Field label="Agência" value={order.agency} mono />
                                                        <StandardModal.Field label="Conta" value={order.checking_account} mono />
                                                        <StandardModal.Field label="Tipo Conta" value={order.type_account === '1' ? 'Corrente' : order.type_account === '2' ? 'Poupança' : '-'} />
                                                    </div>
                                                )}
                                                {(order.name_supplier || order.document_number_supplier) && (
                                                    <div className="grid grid-cols-2 gap-x-4 gap-y-2 mt-2">
                                                        <StandardModal.Field label="Titular" value={order.name_supplier} />
                                                        <StandardModal.Field label="CPF/CNPJ" value={order.document_number_supplier} mono />
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </StandardModal.Section>
                                </div>

                                {order.observations && (
                                    <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                        <p className="text-xs font-medium text-amber-600 uppercase mb-1">Observações</p>
                                        <p className="text-sm text-amber-900">{order.observations}</p>
                                    </div>
                                )}

                                {/* Parcelas */}
                                {data.installments?.length > 0 && (
                                    <StandardModal.Section title={`Parcelas (${data.installments.length})`} icon={<ClockIcon className="h-4 w-4" />}>
                                        <div className="border rounded-lg overflow-hidden">
                                            <table className="w-full text-sm">
                                                <thead className="bg-gray-50">
                                                    <tr>
                                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Valor</th>
                                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Vencimento</th>
                                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-100">
                                                    {data.installments.map(inst => (
                                                        <tr key={inst.id} className={inst.is_overdue && !inst.is_paid ? 'bg-red-50' : 'hover:bg-gray-50'}>
                                                            <td className="px-4 py-2.5 text-gray-600 font-medium">{inst.number}</td>
                                                            <td className="px-4 py-2.5 font-semibold text-gray-900">{inst.formatted_value}</td>
                                                            <td className="px-4 py-2.5 text-gray-600">{inst.date_payment}</td>
                                                            <td className="px-4 py-2.5">
                                                                {inst.is_paid ? (
                                                                    <span className="inline-flex items-center gap-1 text-green-700 bg-green-50 px-2.5 py-1 rounded-full text-xs font-medium">
                                                                        <CheckCircleSolid className="h-3.5 w-3.5" /> Paga {inst.date_paid}
                                                                    </span>
                                                                ) : inst.is_overdue ? (
                                                                    <span className="inline-flex items-center gap-1 text-red-700 bg-red-50 px-2.5 py-1 rounded-full text-xs font-medium">
                                                                        <ExclamationTriangleIcon className="h-3.5 w-3.5" /> Vencida
                                                                    </span>
                                                                ) : (
                                                                    <span className="inline-flex items-center gap-1 text-gray-600 bg-gray-100 px-2.5 py-1 rounded-full text-xs font-medium">
                                                                        <ClockIcon className="h-3.5 w-3.5" /> Pendente
                                                                    </span>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </StandardModal.Section>
                                )}

                                {/* Rateio */}
                                {data.allocations?.length > 0 && (
                                    <StandardModal.Section title="Rateio por Centro de Custo" icon={<BuildingOfficeIcon className="h-4 w-4" />}>
                                        <div className="border rounded-lg overflow-hidden">
                                            <table className="w-full text-sm">
                                                <thead className="bg-gray-50">
                                                    <tr>
                                                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Centro de Custo</th>
                                                        <th className="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">%</th>
                                                        <th className="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Valor</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-100">
                                                    {data.allocations.map(a => (
                                                        <tr key={a.id} className="hover:bg-gray-50">
                                                            <td className="px-4 py-2.5 text-gray-700">CC {a.cost_center_id}</td>
                                                            <td className="px-4 py-2.5 text-right font-medium text-gray-900">{a.percentage}%</td>
                                                            <td className="px-4 py-2.5 text-right font-medium text-gray-900">{fmtCurrency(a.value)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </StandardModal.Section>
                                )}

                                {/* Timeline */}
                                {data.status_history?.length > 0 && (
                                    <StandardModal.Section title="Histórico de Status" icon={<ClockIcon className="h-4 w-4" />}>
                                        <StandardModal.Timeline
                                            items={data.status_history.map(h => {
                                                const statusKey = h.new_status?.toLowerCase().replace(/[^a-z]/g, '_') || '';
                                                return {
                                                    id: h.id,
                                                    title: `${h.old_status ? h.old_status + ' → ' : ''}${h.new_status}`,
                                                    subtitle: `${h.changed_by} | ${h.created_at}`,
                                                    notes: h.notes,
                                                    dotColor: STATUS_CONFIG[statusKey]?.dot || 'bg-indigo-500',
                                                };
                                            })}
                                        />
                                    </StandardModal.Section>
                                )}

                                {/* Footer info */}
                                <div className="text-xs text-gray-400 border-t pt-4 flex items-center justify-between">
                                    <span>
                                        Criado por <strong className="text-gray-500">{order.created_by}</strong> em {order.created_at}
                                        {order.updated_by && <> | Atualizado por <strong className="text-gray-500">{order.updated_by}</strong></>}
                                    </span>
                                    {order.is_deleted && (
                                        <span className="text-red-500 font-medium">
                                            Excluida por {order.deleted_by} em {order.deleted_at}
                                        </span>
                                    )}
                                </div>
        </StandardModal>
    );
}
