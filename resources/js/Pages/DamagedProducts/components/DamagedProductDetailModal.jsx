import { useEffect, useState } from 'react';
import {
    InformationCircleIcon,
    PhotoIcon,
    ClockIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import LoadingSpinner from '@/Components/Shared/LoadingSpinner';

const STATUS_VARIANT = {
    open: 'gray',
    matched: 'info',
    transfer_requested: 'warning',
    resolved: 'success',
    cancelled: 'danger',
};

const STATUS_LABEL = {
    open: 'Em aberto',
    matched: 'Match encontrado',
    transfer_requested: 'Transferência solicitada',
    resolved: 'Resolvido',
    cancelled: 'Cancelado',
};

const fmtDate = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('pt-BR');
};

export default function DamagedProductDetailModal({ show, onClose, ulid }) {
    const [item, setItem] = useState(null);
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!show || !ulid) return;
        setLoading(true);
        setError(null);
        window.axios
            .get(route('damaged-products.show', ulid))
            .then((res) => {
                setItem(res.data.item);
                setHistory(res.data.history || []);
            })
            .catch(() => setError('Não foi possível carregar os detalhes.'))
            .finally(() => setLoading(false));
    }, [show, ulid]);

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={item?.product_reference ?? 'Carregando...'}
            subtitle={item ? `${item.store?.code ?? ''} · ${item.store?.name ?? ''}` : null}
            headerColor="bg-gray-700"
            headerIcon={<InformationCircleIcon className="h-5 w-5" />}
            headerBadges={item ? [{
                label: STATUS_LABEL[item.status] ?? item.status,
                variant: STATUS_VARIANT[item.status] ?? 'gray',
            }] : []}
            maxWidth="3xl"
            loading={loading}
            errorMessage={error}
        >
            {item && (
                <>
                    <StandardModal.Section title="Produto">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <StandardModal.Field label="Referência" value={item.product_reference} mono />
                            <StandardModal.Field label="Descrição" value={item.product_name || '—'} />
                            <StandardModal.Field label="Cor" value={item.product_color || '—'} />
                            <StandardModal.Field label="Marca" value={item.brand_cigam_code || '—'} />
                            <StandardModal.Field label="Tamanho" value={item.product_size || '—'} />
                            <StandardModal.Field label="Cadastrado por" value={item.created_by ?? '—'} />
                        </div>
                    </StandardModal.Section>

                    {item.is_mismatched && (
                        <StandardModal.Section title="Par trocado" headerClassName="bg-yellow-50">
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <StandardModal.Field
                                    label="Pé com tamanho trocado"
                                    value={item.mismatched_foot_label || '—'}
                                />
                                <StandardModal.Field
                                    label="Tamanho real"
                                    value={item.mismatched_actual_size}
                                    mono
                                />
                                <StandardModal.Field
                                    label="Tamanho esperado"
                                    value={item.mismatched_expected_size}
                                    mono
                                />
                            </div>
                        </StandardModal.Section>
                    )}

                    {item.is_damaged && (
                        <StandardModal.Section title="Avaria" headerClassName="bg-red-50">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <StandardModal.Field label="Tipo" value={item.damage_type?.name || '—'} />
                                <StandardModal.Field label="Pé(s) avariado(s)" value={item.damaged_foot_label || '—'} />
                                {item.is_repairable && (
                                    <>
                                        <StandardModal.Field
                                            label="Pode ser reparado"
                                            value="Sim"
                                            badge="green"
                                        />
                                        <StandardModal.Field
                                            label="Custo estimado de reparo"
                                            value={item.estimated_repair_cost
                                                ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(item.estimated_repair_cost)
                                                : '—'}
                                        />
                                    </>
                                )}
                                {item.damage_description && (
                                    <div className="sm:col-span-2">
                                        <div className="text-xs font-medium text-gray-500 mb-1">Descrição</div>
                                        <div className="text-sm whitespace-pre-wrap">{item.damage_description}</div>
                                    </div>
                                )}
                            </div>
                        </StandardModal.Section>
                    )}

                    {item.photos?.length > 0 && (
                        <StandardModal.Section title="Fotos" icon={<PhotoIcon className="h-4 w-4" />}>
                            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                                {item.photos.map((photo) => (
                                    <a
                                        key={photo.id}
                                        href={photo.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="block relative group"
                                    >
                                        <img
                                            src={photo.url}
                                            alt={photo.filename}
                                            className="h-32 w-full object-cover rounded-md border"
                                        />
                                        {photo.caption && (
                                            <div className="absolute bottom-0 left-0 right-0 bg-black/60 text-white text-xs p-1 truncate">
                                                {photo.caption}
                                            </div>
                                        )}
                                    </a>
                                ))}
                            </div>
                        </StandardModal.Section>
                    )}

                    {item.notes && (
                        <StandardModal.Section title="Observações">
                            <div className="text-sm whitespace-pre-wrap">{item.notes}</div>
                        </StandardModal.Section>
                    )}

                    {item.cancel_reason && (
                        <StandardModal.Section title="Motivo do cancelamento" headerClassName="bg-red-50">
                            <div className="text-sm whitespace-pre-wrap">{item.cancel_reason}</div>
                            <div className="mt-2 text-xs text-gray-500">Em {fmtDate(item.cancelled_at)}</div>
                        </StandardModal.Section>
                    )}

                    {history.length > 0 && (
                        <StandardModal.Section title="Histórico de status" icon={<ClockIcon className="h-4 w-4" />}>
                            <StandardModal.Timeline
                                items={history.map((h) => ({
                                    id: h.id,
                                    title: `${STATUS_LABEL[h.from_status] ?? h.from_status ?? '—'} → ${STATUS_LABEL[h.to_status] ?? h.to_status}`,
                                    subtitle: `${h.actor} · ${fmtDate(h.created_at)}`,
                                    notes: h.note,
                                    dotColor: h.triggered_by_match_id ? 'bg-purple-500' : 'bg-indigo-500',
                                }))}
                            />
                        </StandardModal.Section>
                    )}
                </>
            )}
        </StandardModal>
    );
}
