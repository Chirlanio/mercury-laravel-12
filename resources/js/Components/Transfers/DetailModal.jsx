import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { useConfirm } from '@/Hooks/useConfirm';
import {
    TruckIcon, CheckCircleIcon, XCircleIcon,
    ArrowsRightLeftIcon, DocumentTextIcon, CalendarDaysIcon,
    CubeIcon, PencilSquareIcon, ClockIcon,
} from '@heroicons/react/24/outline';
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/react/24/solid';

const STATUS_HEADER = {
    pending: 'bg-yellow-500', in_transit: 'bg-blue-600', delivered: 'bg-indigo-600',
    confirmed: 'bg-green-600', cancelled: 'bg-red-600',
};
const STATUS_VARIANT = {
    pending: 'warning', in_transit: 'info', delivered: 'indigo', confirmed: 'success', cancelled: 'danger',
};
const STATUS_MESSAGE = {
    pending: 'Aguardando coleta na loja de origem.',
    in_transit: 'Em rota para a loja destino.',
    delivered: 'Entregue. Aguardando confirmação de recebimento.',
    confirmed: 'Transferência finalizada com sucesso.',
    cancelled: 'Transferência cancelada.',
};

export default function DetailModal({ transferId, onClose, onRefresh, onEdit }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showDeliveryInput, setShowDeliveryInput] = useState(false);
    const [receiverName, setReceiverName] = useState('');
    const [actionLoading, setActionLoading] = useState(false);
    const { confirm, ConfirmDialogComponent } = useConfirm();

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
    const sc = transfer?.status || 'pending';

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

    const handleCancel = async () => {
        const confirmed = await confirm({
            title: 'Cancelar Transferência',
            message: 'Tem certeza que deseja cancelar esta transferência?',
            confirmText: 'Sim, Cancelar',
            type: 'danger',
        });
        if (confirmed) handleAction('transfers.cancel');
    };

    const headerBadges = transfer ? [
        { text: transfer.status_label, className: 'bg-white/20 text-white' },
    ] : [];

    const hasActions = data?.can_confirm_pickup || data?.can_confirm_delivery || data?.can_confirm_receipt || data?.can_cancel;

    const footerContent = transfer && hasActions && (
        <>
            {data.can_confirm_pickup && (
                <Button variant="info" icon={TruckIcon} onClick={() => handleAction('transfers.confirm-pickup')} loading={actionLoading}>
                    Confirmar Coleta
                </Button>
            )}
            {data.can_confirm_delivery && !showDeliveryInput && (
                <Button variant="warning" icon={CheckCircleIcon} onClick={() => setShowDeliveryInput(true)} disabled={actionLoading}>
                    Confirmar Entrega
                </Button>
            )}
            {data.can_confirm_delivery && showDeliveryInput && (
                <div className="flex items-center gap-2 flex-1">
                    <input type="text" value={receiverName} onChange={(e) => setReceiverName(e.target.value)}
                        placeholder="Nome do recebedor *" autoFocus onKeyDown={(e) => e.key === 'Enter' && handleDelivery()}
                        className="flex-1 rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" />
                    <Button variant="warning" onClick={handleDelivery} disabled={actionLoading || !receiverName.trim()}>Confirmar</Button>
                    <Button variant="outline" onClick={() => { setShowDeliveryInput(false); setReceiverName(''); }}>Cancelar</Button>
                </div>
            )}
            {data.can_confirm_receipt && (
                <Button variant="success" icon={CheckCircleSolid} onClick={() => handleAction('transfers.confirm-receipt')} loading={actionLoading}>
                    Confirmar Recebimento
                </Button>
            )}
            {data.can_cancel && !showDeliveryInput && (
                <>
                    <div className="flex-1" />
                    <Button variant="danger" icon={XCircleIcon} onClick={handleCancel} disabled={actionLoading}>
                        Cancelar Transferência
                    </Button>
                </>
            )}
        </>
    );

    return (
        <>
            <StandardModal
                show={!!transferId}
                onClose={onClose}
                title={transfer ? `Transferência #${transfer.id}` : 'Detalhes da Transferência'}
                headerColor={STATUS_HEADER[sc] || 'bg-gray-700'}
                headerIcon={<ArrowsRightLeftIcon className="h-5 w-5" />}
                headerBadges={headerBadges}
                headerActions={data?.can_edit && onEdit && (
                    <Button variant="outline" size="xs" onClick={() => { onClose(); onEdit(); }}
                        icon={PencilSquareIcon} className="text-white border-white/30 hover:bg-white/10">
                        Editar
                    </Button>
                )}
                loading={loading}
                errorMessage={!loading && !transfer ? 'Erro ao carregar dados.' : null}
                maxWidth="4xl"
                footer={footerContent ? <StandardModal.Footer>{footerContent}</StandardModal.Footer> : (
                    transfer && <StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />
                )}
            >
                {transfer && (
                    <>
                        {/* Status message */}
                        <div className={`flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm`}>
                            <ClockIcon className="h-4 w-4 shrink-0" />
                            <StatusBadge variant={STATUS_VARIANT[transfer.status] || 'gray'}>
                                {STATUS_MESSAGE[transfer.status]}
                            </StatusBadge>
                        </div>

                        {/* Info Grid */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <StandardModal.Section title="Informações da Transferência" icon={<DocumentTextIcon className="h-4 w-4" />}>
                                <div className="grid grid-cols-2 gap-x-4 gap-y-3">
                                    <StandardModal.Field label="Loja Origem" value={transfer.origin_store?.name} />
                                    <StandardModal.Field label="Loja Destino" value={transfer.destination_store?.name} />
                                    <StandardModal.Field label="Tipo" value={transfer.type_label} />
                                    <StandardModal.Field label="Nota Fiscal" value={transfer.invoice_number} mono />
                                    <StandardModal.Field label="Criado por" value={transfer.created_by} />
                                    <StandardModal.Field label="Data Criação" value={transfer.created_at} />
                                </div>
                            </StandardModal.Section>

                            <StandardModal.Section title="Resumo da Transferência" icon={<CubeIcon className="h-4 w-4" />}>
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
                            </StandardModal.Section>
                        </div>

                        {/* Datas e Horários */}
                        <StandardModal.Section title="Datas e Horários" icon={<CalendarDaysIcon className="h-4 w-4" />}>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <TimelineStep title="Coleta" icon={<TruckIcon className="h-4 w-4" />} color="text-blue-600"
                                    date={transfer.pickup_date} time={transfer.pickup_time}
                                    person={transfer.driver_name} personLabel="Motorista" pending="Aguardando coleta" />
                                <TimelineStep title="Entrega" icon={<CheckCircleIcon className="h-4 w-4" />} color="text-amber-600"
                                    date={transfer.delivery_date} time={transfer.delivery_time}
                                    person={transfer.receiver_name} personLabel="Recebido por" pending="Aguardando entrega" />
                                <TimelineStep title="Recebimento Final" icon={<CheckCircleSolid className="h-4 w-4" />} color="text-green-600"
                                    date={transfer.confirmed_at} person={transfer.confirmed_by}
                                    personLabel="Confirmado por" pending="Aguardando recebimento" />
                            </div>
                        </StandardModal.Section>

                        {/* Observações */}
                        {transfer.observations && (
                            <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                <p className="text-xs font-medium text-amber-600 uppercase mb-1">Observações</p>
                                <p className="text-sm text-amber-900 whitespace-pre-line">{transfer.observations}</p>
                            </div>
                        )}

                        {/* Metadata */}
                        <div className="text-xs text-gray-400 border-t pt-3">
                            Criado por <strong className="text-gray-500">{transfer.created_by}</strong> em {transfer.created_at}
                            {transfer.updated_at !== transfer.created_at && <> | Atualizado em {transfer.updated_at}</>}
                        </div>
                    </>
                )}
            </StandardModal>
            <ConfirmDialogComponent />
        </>
    );
}

function TimelineStep({ title, icon, color, date, time, person, personLabel, pending }) {
    return (
        <div>
            <div className={`flex items-center gap-1.5 mb-2 font-semibold text-sm ${color}`}>{icon} {title}</div>
            {date ? (
                <div className="space-y-1">
                    <p className="text-sm text-gray-700">{date}{time ? ` ${time}` : ''}</p>
                    {person && <p className="text-xs text-gray-500">{personLabel}: <strong className="text-gray-700">{person}</strong></p>}
                </div>
            ) : (
                <p className="text-sm text-gray-400 italic">{pending}</p>
            )}
        </div>
    );
}
