import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import {
    XMarkIcon, TruckIcon, CheckCircleIcon, XCircleIcon,
    ArrowsRightLeftIcon, DocumentTextIcon, CalendarDaysIcon,
    CubeIcon, PencilSquareIcon, ClockIcon,
} from '@heroicons/react/24/outline';
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/react/24/solid';

const STATUS_CONFIG = {
    pending:    { bg: 'bg-yellow-100', text: 'text-yellow-800', header: 'bg-yellow-500', message: 'Aguardando coleta na loja de origem.' },
    in_transit: { bg: 'bg-blue-100', text: 'text-blue-800', header: 'bg-blue-600', message: 'Em rota para a loja destino.' },
    delivered:  { bg: 'bg-indigo-100', text: 'text-indigo-800', header: 'bg-indigo-600', message: 'Entregue. Aguardando confirmação de recebimento.' },
    confirmed:  { bg: 'bg-green-100', text: 'text-green-800', header: 'bg-green-600', message: 'Transferência finalizada com sucesso.' },
    cancelled:  { bg: 'bg-red-100', text: 'text-red-800', header: 'bg-red-600', message: 'Transferência cancelada.' },
};

export default function DetailModal({ transferId, onClose, onRefresh, onEdit }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showDeliveryInput, setShowDeliveryInput] = useState(false);
    const [receiverName, setReceiverName] = useState('');
    const [actionLoading, setActionLoading] = useState(false);

    useEffect(() => {
        if (!transferId) return;
        setLoading(true);
        setShowDeliveryInput(false);
        setReceiverName('');
        fetch(route('transfers.show', transferId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then(d => { setData(d); setLoading(false); })
            .catch(() => setLoading(false));
    }, [transferId]);

    if (!transferId) return null;

    const transfer = data?.transfer;
    const sc = transfer ? STATUS_CONFIG[transfer.status] || {} : {};

    const handleAction = (routeName, postData = {}) => {
        setActionLoading(true);
        router.post(route(routeName, transferId), postData, {
            onSuccess: () => { setActionLoading(false); onRefresh(); },
            onError: () => setActionLoading(false),
        });
    };

    const handleDelivery = () => {
        if (!receiverName.trim()) return;
        handleAction('transfers.confirm-delivery', { receiver_name: receiverName.trim() });
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex min-h-full items-start justify-center p-4 pt-10 sm:pt-16">
                <div className="fixed inset-0 bg-gray-500/75 transition-opacity" onClick={onClose} />
                <div className="relative w-full max-w-4xl bg-white rounded-xl shadow-2xl">
                    {loading ? (
                        <div className="flex justify-center py-24">
                            <div className="animate-spin h-10 w-10 border-4 border-indigo-600 border-t-transparent rounded-full" />
                        </div>
                    ) : !transfer ? (
                        <div className="p-8 text-center text-gray-500">
                            Erro ao carregar dados.
                            <button onClick={onClose} className="block mx-auto mt-4 text-sm text-indigo-600 hover:underline">Fechar</button>
                        </div>
                    ) : (
                        <>
                            {/* Header */}
                            <div className={`${sc.header} rounded-t-xl px-6 py-4 flex items-center justify-between`}>
                                <div className="flex items-center gap-3">
                                    <ArrowsRightLeftIcon className="h-5 w-5 text-white/70" />
                                    <h3 className="text-lg font-semibold text-white">
                                        Transferência #{transfer.id}
                                    </h3>
                                    <span className="bg-white/20 text-white text-xs font-bold px-2.5 py-1 rounded-full">
                                        {transfer.status_label}
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    {data.can_edit && onEdit && (
                                        <button
                                            onClick={() => { onClose(); onEdit(); }}
                                            className="inline-flex items-center px-3 py-1.5 text-xs font-medium bg-white/20 text-white rounded-md hover:bg-white/30 transition"
                                        >
                                            <PencilSquareIcon className="h-3.5 w-3.5 mr-1" /> Editar
                                        </button>
                                    )}
                                    <button onClick={onClose} className="text-white/70 hover:text-white ml-2">
                                        <XMarkIcon className="h-6 w-6" />
                                    </button>
                                </div>
                            </div>

                            {/* Status message */}
                            <div className={`${sc.bg} px-6 py-2.5 border-b ${sc.text} text-sm flex items-center gap-2`}>
                                <ClockIcon className="h-4 w-4" />
                                {sc.message}
                            </div>

                            {/* Body */}
                            <div className="p-6 space-y-6 max-h-[65vh] overflow-y-auto">
                                {/* Info Grid */}
                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                                    {/* Informações */}
                                    <Section title="Informações da Transferência" icon={<DocumentTextIcon className="h-4 w-4" />}>
                                        <div className="grid grid-cols-2 gap-x-4 gap-y-3">
                                            <Field label="Loja Origem" value={transfer.origin_store?.name} />
                                            <Field label="Loja Destino" value={transfer.destination_store?.name} />
                                            <Field label="Tipo" value={transfer.type_label} />
                                            <Field label="Nota Fiscal" value={transfer.invoice_number} mono />
                                            <Field label="Criado por" value={transfer.created_by} />
                                            <Field label="Data Criação" value={transfer.created_at} />
                                        </div>
                                    </Section>

                                    {/* Resumo */}
                                    <Section title="Resumo da Transferência" icon={<CubeIcon className="h-4 w-4" />}>
                                        <div className="flex items-center justify-around py-4">
                                            <div className="text-center">
                                                <p className="text-3xl font-bold text-blue-600">{transfer.volumes_qty ?? 0}</p>
                                                <p className="text-xs text-gray-500 mt-1 uppercase tracking-wide">Volumes</p>
                                            </div>
                                            <div className="h-12 w-px bg-gray-200" />
                                            <div className="text-center">
                                                <p className="text-3xl font-bold text-emerald-600">{transfer.products_qty ?? 0}</p>
                                                <p className="text-xs text-gray-500 mt-1 uppercase tracking-wide">Produtos</p>
                                            </div>
                                        </div>
                                    </Section>
                                </div>

                                {/* Datas e Horários */}
                                <Section title="Datas e Horários" icon={<CalendarDaysIcon className="h-4 w-4" />}>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <TimelineStep
                                            title="Coleta"
                                            icon={<TruckIcon className="h-4 w-4" />}
                                            color="text-blue-600"
                                            date={transfer.pickup_date}
                                            time={transfer.pickup_time}
                                            person={transfer.driver_name}
                                            personLabel="Motorista"
                                            pending="Aguardando coleta"
                                        />
                                        <TimelineStep
                                            title="Entrega"
                                            icon={<CheckCircleIcon className="h-4 w-4" />}
                                            color="text-amber-600"
                                            date={transfer.delivery_date}
                                            time={transfer.delivery_time}
                                            person={transfer.receiver_name}
                                            personLabel="Recebido por"
                                            pending="Aguardando entrega"
                                        />
                                        <TimelineStep
                                            title="Recebimento Final"
                                            icon={<CheckCircleSolid className="h-4 w-4" />}
                                            color="text-green-600"
                                            date={transfer.confirmed_at}
                                            person={transfer.confirmed_by}
                                            personLabel="Confirmado por"
                                            pending="Aguardando recebimento"
                                        />
                                    </div>
                                </Section>

                                {/* Observações */}
                                {transfer.observations && (
                                    <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                        <p className="text-xs font-medium text-amber-600 uppercase mb-1">Observações</p>
                                        <p className="text-sm text-amber-900 whitespace-pre-line">{transfer.observations}</p>
                                    </div>
                                )}

                                {/* Footer metadata */}
                                <div className="text-xs text-gray-400 border-t pt-4">
                                    Criado por <strong className="text-gray-500">{transfer.created_by}</strong> em {transfer.created_at}
                                    {transfer.updated_at !== transfer.created_at && (
                                        <> | Atualizado em {transfer.updated_at}</>
                                    )}
                                </div>
                            </div>

                            {/* Ações do Workflow - footer fixo */}
                            {(data.can_confirm_pickup || data.can_confirm_delivery || data.can_confirm_receipt || data.can_cancel) && (
                                <div className="px-6 py-4 border-t bg-gray-50 rounded-b-xl shrink-0">
                                    <div className="flex flex-wrap gap-3">
                                        {data.can_confirm_pickup && (
                                            <button
                                                onClick={() => handleAction('transfers.confirm-pickup')}
                                                disabled={actionLoading}
                                                className="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
                                            >
                                                <TruckIcon className="h-4 w-4 mr-2" />
                                                Confirmar Coleta
                                            </button>
                                        )}

                                        {data.can_confirm_delivery && !showDeliveryInput && (
                                            <button
                                                onClick={() => setShowDeliveryInput(true)}
                                                disabled={actionLoading}
                                                className="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-amber-500 rounded-md hover:bg-amber-600 disabled:opacity-50"
                                            >
                                                <CheckCircleIcon className="h-4 w-4 mr-2" />
                                                Confirmar Entrega
                                            </button>
                                        )}

                                        {data.can_confirm_delivery && showDeliveryInput && (
                                            <div className="flex items-center gap-2 w-full">
                                                <input
                                                    type="text"
                                                    value={receiverName}
                                                    onChange={(e) => setReceiverName(e.target.value)}
                                                    placeholder="Nome do recebedor *"
                                                    className="flex-1 rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                                                    onKeyDown={(e) => e.key === 'Enter' && handleDelivery()}
                                                    autoFocus
                                                />
                                                <button
                                                    onClick={handleDelivery}
                                                    disabled={actionLoading || !receiverName.trim()}
                                                    className="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-amber-500 rounded-md hover:bg-amber-600 disabled:opacity-50"
                                                >
                                                    Confirmar
                                                </button>
                                                <button
                                                    onClick={() => { setShowDeliveryInput(false); setReceiverName(''); }}
                                                    className="px-3 py-2 text-sm text-gray-600 hover:text-gray-800"
                                                >
                                                    Cancelar
                                                </button>
                                            </div>
                                        )}

                                        {data.can_confirm_receipt && (
                                            <button
                                                onClick={() => handleAction('transfers.confirm-receipt')}
                                                disabled={actionLoading}
                                                className="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50"
                                            >
                                                <CheckCircleSolid className="h-4 w-4 mr-2" />
                                                Confirmar Recebimento
                                            </button>
                                        )}

                                        {data.can_cancel && (
                                            <button
                                                onClick={() => {
                                                    if (confirm('Tem certeza que deseja cancelar esta transferência?')) {
                                                        handleAction('transfers.cancel');
                                                    }
                                                }}
                                                disabled={actionLoading}
                                                className="ml-auto inline-flex items-center px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 disabled:opacity-50"
                                            >
                                                <XCircleIcon className="h-4 w-4 mr-2" />
                                                Cancelar Transferência
                                            </button>
                                        )}
                                    </div>
                                </div>
                            )}
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

function Field({ label, value, mono }) {
    return (
        <div>
            <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">{label}</p>
            <p className={`text-sm mt-0.5 text-gray-900 ${mono ? 'font-mono' : ''}`}>{value || '-'}</p>
        </div>
    );
}

function TimelineStep({ title, icon, color, date, time, person, personLabel, pending }) {
    const hasData = !!date;
    return (
        <div>
            <div className={`flex items-center gap-1.5 mb-2 font-semibold text-sm ${color}`}>
                {icon} {title}
            </div>
            {hasData ? (
                <div className="space-y-1">
                    <p className="text-sm text-gray-700">
                        {date}{time ? ` ${time}` : ''}
                    </p>
                    {person && (
                        <p className="text-xs text-gray-500">
                            {personLabel}: <strong className="text-gray-700">{person}</strong>
                        </p>
                    )}
                </div>
            ) : (
                <p className="text-sm text-gray-400 italic">{pending}</p>
            )}
        </div>
    );
}
