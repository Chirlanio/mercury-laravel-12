import { useEffect, useState } from 'react';
import {
    InformationCircleIcon,
    PhotoIcon,
    ClockIcon,
    CubeIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';

const STATUS_LABEL = {
    open: 'Em aberto',
    matched: 'Match encontrado',
    transfer_requested: 'Transferência solicitada',
    resolved: 'Resolvido',
    cancelled: 'Cancelado',
};

// Cores do badge no header escuro do modal — fundo claro pra contrastar
const STATUS_BADGE_CLASS = {
    open: 'bg-gray-200 text-gray-800',
    matched: 'bg-blue-100 text-blue-800',
    transfer_requested: 'bg-amber-100 text-amber-800',
    resolved: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
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
                text: STATUS_LABEL[item.status] ?? item.status,
                className: STATUS_BADGE_CLASS[item.status] ?? 'bg-gray-200 text-gray-800',
            }] : []}
            maxWidth="5xl"
            loading={loading}
            errorMessage={error}
        >
            {item && (
                <>
                    <StandardModal.Section title="Produto" icon={<CubeIcon className="h-4 w-4" />}>
                        <div className="flex flex-col sm:flex-row gap-4">
                            {/* Foto do produto (catálogo) */}
                            <div className="shrink-0 w-full sm:w-40">
                                {item.product_image_url ? (
                                    <a
                                        href={item.product_image_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="block"
                                    >
                                        <img
                                            src={item.product_image_url}
                                            alt={item.product_reference}
                                            className="w-full sm:w-40 h-40 object-cover rounded-lg border bg-gray-50"
                                        />
                                    </a>
                                ) : (
                                    <div className="w-full sm:w-40 h-40 rounded-lg border bg-gray-50 flex items-center justify-center text-gray-400">
                                        <PhotoIcon className="h-12 w-12" />
                                    </div>
                                )}
                            </div>

                            {/* Dados do produto */}
                            <div className="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <StandardModal.Field label="Referência" value={item.product_reference} mono />
                                <StandardModal.Field label="Marca" value={item.brand_name || item.brand_cigam_code || '—'} />
                                <StandardModal.Field label="Descrição" value={item.product_name || '—'} />
                                <StandardModal.Field label="Cor" value={item.product_color || '—'} />
                                <StandardModal.Field label="Cadastrado por" value={item.created_by ?? '—'} />
                                <StandardModal.Field label="Cadastrado em" value={fmtDate(item.created_at)} />
                            </div>
                        </div>
                    </StandardModal.Section>

                    {item.is_mismatched && (
                        <StandardModal.Section title="Par trocado" headerClassName="bg-yellow-50">
                            <div className="space-y-3">
                                <SizeRowReadonly
                                    label="Esquerdo"
                                    sizes={item.product_sizes || []}
                                    selected={item.mismatched_left_size}
                                    color="yellow"
                                />
                                <SizeRowReadonly
                                    label="Direito"
                                    sizes={item.product_sizes || []}
                                    selected={item.mismatched_right_size}
                                    color="yellow"
                                />
                            </div>
                        </StandardModal.Section>
                    )}

                    {item.is_damaged && (
                        <StandardModal.Section title="Avaria" headerClassName="bg-red-50">
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
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
                            </div>

                            {/* Tamanho avariado como grid clicável (readonly) */}
                            {item.damaged_size && item.damaged_foot !== 'na' && (
                                <SizeRowReadonly
                                    label="Tamanho"
                                    sizes={item.product_sizes || []}
                                    selected={item.damaged_size}
                                    color="red"
                                />
                            )}

                            {item.damage_description && (
                                <div className="mt-3">
                                    <div className="text-xs font-medium text-gray-500 mb-1">Descrição</div>
                                    <div className="text-sm whitespace-pre-wrap rounded-md bg-red-50 border border-red-200 p-3">
                                        {item.damage_description}
                                    </div>
                                </div>
                            )}
                        </StandardModal.Section>
                    )}

                    {item.photos?.length > 0 && (
                        <StandardModal.Section title={`Fotos do dano (${item.photos.length})`} icon={<PhotoIcon className="h-4 w-4" />}>
                            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
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

/**
 * Versão readonly do SizeRow do FormModal — mesmo layout (botões 44×44px),
 * mas todos disabled e apenas o selecionado fica destacado em cor cheia.
 * Tamanhos não disponíveis no catálogo do produto aparecem como "—".
 */
function SizeRowReadonly({ label, sizes, selected, color = 'indigo' }) {
    const colorMap = {
        indigo: 'bg-indigo-600 border-indigo-600 text-white',
        yellow: 'bg-yellow-500 border-yellow-500 text-white',
        red:    'bg-red-600 border-red-600 text-white',
    };
    const selectedClass = colorMap[color] ?? colorMap.indigo;

    if (sizes.length === 0) {
        return (
            <div className="flex items-center gap-3">
                <div className="w-20 shrink-0 text-sm font-medium text-gray-700">{label}</div>
                <div className="text-sm text-gray-500 italic">
                    {selected ? <span className="font-mono">{selected}</span> : '—'}
                </div>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-3">
            <div className="w-20 shrink-0 text-sm font-medium text-gray-700">{label}</div>
            <div className="flex flex-wrap gap-1.5">
                {sizes.map((s) => {
                    const isSelected = String(selected) === String(s.cigam_code);
                    return (
                        <span
                            key={s.cigam_code}
                            className={`
                                min-w-[44px] min-h-[44px] px-3 py-2 rounded-md border text-sm font-medium inline-flex items-center justify-center
                                ${isSelected
                                    ? `${selectedClass} shadow-sm`
                                    : 'bg-white border-gray-200 text-gray-400'
                                }
                            `}
                        >
                            {s.name}
                        </span>
                    );
                })}
            </div>
        </div>
    );
}
