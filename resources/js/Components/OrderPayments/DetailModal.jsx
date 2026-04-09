import { useState, useEffect } from 'react';
import {
    XMarkIcon, ArrowRightIcon, ArrowLeftIcon, CheckCircleIcon,
    ExclamationTriangleIcon, ClockIcon, BanknotesIcon,
    BuildingOfficeIcon, DocumentTextIcon, UserIcon,
} from '@heroicons/react/24/outline';
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/react/24/solid';

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

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex min-h-full items-start justify-center p-4 pt-10 sm:pt-16">
                <div className="fixed inset-0 bg-gray-500/75 transition-opacity" onClick={onClose} />
                <div className="relative w-full max-w-4xl bg-white rounded-xl shadow-2xl">
                    {loading ? (
                        <div className="flex justify-center py-24">
                            <div className="animate-spin h-10 w-10 border-4 border-indigo-600 border-t-transparent rounded-full" />
                        </div>
                    ) : !order ? (
                        <div className="p-8 text-center text-gray-500">Erro ao carregar dados.
                            <button onClick={onClose} className="block mx-auto mt-4 text-sm text-indigo-600 hover:underline">Fechar</button>
                        </div>
                    ) : (
                        <>
                            {/* Header */}
                            <div className={`${sc.header} rounded-t-xl px-6 py-4 flex items-center justify-between`}>
                                <div className="flex items-center gap-3">
                                    <h3 className="text-lg font-semibold text-white">
                                        Ordem #{order.id}
                                    </h3>
                                    <span className="bg-white/20 text-white text-xs font-bold px-2.5 py-1 rounded-full">
                                        {order.status_label}
                                    </span>
                                    {order.is_overdue && (
                                        <span className="bg-red-500 text-white text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1">
                                            <ExclamationTriangleIcon className="h-3.5 w-3.5" /> Vencida
                                        </span>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    {canEdit && order.status !== 'done' && (
                                        <button onClick={() => onTransition(order, { backlog: 'doing', doing: 'waiting', waiting: 'done' }[order.status])}
                                            className="inline-flex items-center px-3 py-1.5 text-xs font-medium bg-white/20 text-white rounded-md hover:bg-white/30 transition">
                                            <ArrowRightIcon className="h-3.5 w-3.5 mr-1" /> Avançar
                                        </button>
                                    )}
                                    {canEdit && order.status !== 'backlog' && (
                                        <button onClick={() => onTransition(order, { doing: 'backlog', waiting: 'doing', done: 'waiting' }[order.status])}
                                            className="inline-flex items-center px-3 py-1.5 text-xs font-medium bg-white/20 text-white rounded-md hover:bg-white/30 transition">
                                            <ArrowLeftIcon className="h-3.5 w-3.5 mr-1" /> Retornar
                                        </button>
                                    )}
                                    <button onClick={onClose} className="text-white/70 hover:text-white ml-2">
                                        <XMarkIcon className="h-6 w-6" />
                                    </button>
                                </div>
                            </div>

                            {/* Body */}
                            <div className="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
                                {/* Valor destaque */}
                                <div className="flex items-center justify-between bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl p-5 border border-indigo-100">
                                    <div>
                                        <p className="text-xs font-medium text-indigo-500 uppercase tracking-wide">Valor Total</p>
                                        <p className="text-3xl font-bold text-indigo-700 mt-1">{order.formatted_total}</p>
                                    </div>
                                    <div className="text-right space-y-1">
                                        {order.date_payment && <p className="text-sm text-gray-600"><ClockIcon className="inline h-4 w-4 mr-1 text-gray-400" />Vencimento: <strong>{order.date_payment}</strong></p>}
                                        {order.date_paid && <p className="text-sm text-green-600"><CheckCircleSolid className="inline h-4 w-4 mr-1" />Pago em: <strong>{order.date_paid}</strong></p>}
                                    </div>
                                </div>

                                {/* Info Grid — 2 colunas */}
                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                    {/* Coluna 1: Dados Gerais */}
                                    <Section title="Dados Gerais" icon={<DocumentTextIcon className="h-4 w-4" />}>
                                        <div className="grid grid-cols-2 gap-x-4 gap-y-3">
                                            <Field label="Fornecedor" value={order.supplier_name} />
                                            <Field label="Loja" value={order.store?.name} />
                                            <Field label="Responsável" value={order.manager_name} />
                                            <Field label="Tipo Pagamento" value={order.payment_type} />
                                            <Field label="NF" value={order.number_nf} mono />
                                            <Field label="Lançamento" value={order.launch_number} mono />
                                        </div>
                                        {order.description && (
                                            <div className="mt-3 pt-3 border-t border-gray-100">
                                                <p className="text-xs font-medium text-gray-400 uppercase mb-1">Descrição</p>
                                                <p className="text-sm text-gray-700">{order.description}</p>
                                            </div>
                                        )}
                                    </Section>

                                    {/* Coluna 2: Financeiro */}
                                    <Section title="Financeiro" icon={<BanknotesIcon className="h-4 w-4" />}>
                                        <div className="grid grid-cols-2 gap-x-4 gap-y-3">
                                            <Field label="Adiantamento" value={order.advance ? 'Sim' : 'Não'} badge={order.advance ? 'yellow' : null} />
                                            <Field label="Valor Adiantamento" value={order.advance_amount ? fmtCurrency(order.advance_amount) : '-'} />
                                            <Field label="Comprovante" value={order.proof ? 'Sim' : 'Não'} badge={order.proof ? 'green' : null} />
                                            <Field label="Rateio" value={order.has_allocation ? 'Sim' : 'Não'} badge={order.has_allocation ? 'blue' : null} />
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
                                                        <Field label="Tipo Chave" value={order.pix_key_type} />
                                                        <Field label="Chave PIX" value={order.pix_key} mono />
                                                    </div>
                                                ) : (
                                                    <div className="grid grid-cols-2 gap-x-4 gap-y-2">
                                                        <Field label="Banco" value={order.bank_name} />
                                                        <Field label="Agência" value={order.agency} mono />
                                                        <Field label="Conta" value={order.checking_account} mono />
                                                        <Field label="Tipo Conta" value={order.type_account === '1' ? 'Corrente' : order.type_account === '2' ? 'Poupança' : '-'} />
                                                    </div>
                                                )}
                                                {(order.name_supplier || order.document_number_supplier) && (
                                                    <div className="grid grid-cols-2 gap-x-4 gap-y-2 mt-2">
                                                        <Field label="Titular" value={order.name_supplier} />
                                                        <Field label="CPF/CNPJ" value={order.document_number_supplier} mono />
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </Section>
                                </div>

                                {order.observations && (
                                    <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                        <p className="text-xs font-medium text-amber-600 uppercase mb-1">Observações</p>
                                        <p className="text-sm text-amber-900">{order.observations}</p>
                                    </div>
                                )}

                                {/* Parcelas */}
                                {data.installments?.length > 0 && (
                                    <Section title={`Parcelas (${data.installments.length})`} icon={<ClockIcon className="h-4 w-4" />}>
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
                                    </Section>
                                )}

                                {/* Rateio */}
                                {data.allocations?.length > 0 && (
                                    <Section title="Rateio por Centro de Custo" icon={<BuildingOfficeIcon className="h-4 w-4" />}>
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
                                    </Section>
                                )}

                                {/* Timeline */}
                                {data.status_history?.length > 0 && (
                                    <Section title="Histórico de Status" icon={<ClockIcon className="h-4 w-4" />}>
                                        <div className="relative">
                                            <div className="absolute left-[7px] top-2 bottom-2 w-0.5 bg-gray-200" />
                                            <div className="space-y-4">
                                                {data.status_history.map((h, idx) => {
                                                    const statusKey = h.new_status?.toLowerCase().replace(/[^a-z]/g, '_') || '';
                                                    const dotColor = STATUS_CONFIG[statusKey]?.dot || 'bg-indigo-500';
                                                    return (
                                                        <div key={h.id} className="flex items-start gap-4 relative">
                                                            <div className={`mt-0.5 h-4 w-4 rounded-full ${dotColor} ring-4 ring-white shrink-0 z-10`} />
                                                            <div className="flex-1 bg-gray-50 rounded-lg p-3">
                                                                <div className="flex items-center gap-2 text-sm">
                                                                    {h.old_status && (
                                                                        <>
                                                                            <span className="text-gray-400 text-xs">{h.old_status}</span>
                                                                            <ArrowRightIcon className="h-3 w-3 text-gray-300" />
                                                                        </>
                                                                    )}
                                                                    <span className="font-semibold text-gray-900">{h.new_status}</span>
                                                                </div>
                                                                <div className="flex items-center gap-2 mt-1 text-xs text-gray-500">
                                                                    <UserIcon className="h-3 w-3" />
                                                                    <span>{h.changed_by}</span>
                                                                    <span className="text-gray-300">|</span>
                                                                    <span>{h.created_at}</span>
                                                                </div>
                                                                {h.notes && (
                                                                    <p className="mt-1.5 text-xs text-gray-600 italic bg-white rounded px-2 py-1 border border-gray-100">
                                                                        "{h.notes}"
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    </Section>
                                )}

                                {/* Footer */}
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
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

function Section({ title, icon, children }) {
    return (
        <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div className="bg-gray-50 px-4 py-2.5 border-b border-gray-200">
                <h4 className="text-xs font-semibold text-gray-600 uppercase tracking-wide flex items-center gap-1.5">
                    {icon} {title}
                </h4>
            </div>
            <div className="p-4">{children}</div>
        </div>
    );
}

function Field({ label, value, mono, badge }) {
    const badgeColors = {
        green: 'bg-green-100 text-green-700',
        yellow: 'bg-yellow-100 text-yellow-700',
        blue: 'bg-blue-100 text-blue-700',
        red: 'bg-red-100 text-red-700',
    };
    return (
        <div>
            <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">{label}</p>
            {badge ? (
                <span className={`inline-flex mt-0.5 px-2 py-0.5 rounded text-xs font-medium ${badgeColors[badge]}`}>{value || '-'}</span>
            ) : (
                <p className={`text-sm mt-0.5 text-gray-900 ${mono ? 'font-mono' : ''}`}>{value || '-'}</p>
            )}
        </div>
    );
}
