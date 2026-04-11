import { useState, useEffect } from 'react';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import {
    PencilSquareIcon, ArrowPathIcon, DocumentTextIcon,
    ExclamationCircleIcon, ClipboardDocumentCheckIcon,
} from '@heroicons/react/24/outline';

const STATUS_VARIANT = {
    draft: 'gray', planned: 'info', in_progress: 'warning', counting: 'warning',
    recounting: 'orange', reconciliation: 'purple', pending_approval: 'indigo',
    approved: 'success', completed: 'success', cancelled: 'danger',
};

const TYPE_LABELS = {
    total: 'Total', parcial: 'Parcial', especifica: 'Específica',
    aleatoria: 'Aleatória', diaria: 'Diária',
};

export default function DetailModal({ show, onClose, auditId, onTransition, onEdit, canEdit }) {
    const [audit, setAudit] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!show || !auditId) { setAudit(null); return; }
        setLoading(true); setError('');
        fetch(route('stock-audits.show', auditId), {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        })
            .then(r => { if (!r.ok) throw new Error('Erro ao carregar dados.'); return r.json(); })
            .then(json => setAudit(json.data || json))
            .catch(err => setError(err.message))
            .finally(() => setLoading(false));
    }, [show, auditId]);

    const handleClose = () => { setAudit(null); setError(''); onClose(); };

    const headerBadges = audit ? [
        { text: audit.status_label || audit.status, className: 'bg-white/20 text-white' },
    ] : [];

    const hasTransitions = audit?.available_transitions?.length > 0;

    const footerContent = audit && (
        <>
            {(audit.status === 'counting' || audit.status === 'recounting') && (
                <Button variant="info" size="sm" icon={ClipboardDocumentCheckIcon}
                    onClick={() => window.location.href = route('stock-audits.counting', audit.id)}>
                    Ir para Contagem
                </Button>
            )}
            {audit.status === 'reconciliation' && (
                <Button variant="info" size="sm" icon={ClipboardDocumentCheckIcon}
                    onClick={() => window.location.href = route('stock-audits.reconciliation', audit.id)}>
                    Ir para Conciliação
                </Button>
            )}
            {hasTransitions && (
                <Button variant="warning" size="sm" icon={ArrowPathIcon} onClick={() => onTransition?.(audit)}>
                    Transição
                </Button>
            )}
            {canEdit && audit.status === 'draft' && (
                <Button variant="outline" size="sm" icon={PencilSquareIcon} onClick={() => onEdit?.(audit)}>
                    Editar
                </Button>
            )}
            <Button variant="outline" size="sm" icon={DocumentTextIcon}
                onClick={() => window.open(route('stock-audits.report', audit.id), '_blank')}>
                Relatório PDF
            </Button>
            <Button variant="outline" size="sm" icon={ExclamationCircleIcon}
                onClick={() => window.location.href = route('stock-audits.pendencies', audit.id)}>
                Pendências
            </Button>
            <div className="flex-1" />
            <Button variant="outline" onClick={handleClose}>Fechar</Button>
        </>
    );

    return (
        <StandardModal show={show} onClose={handleClose}
            title={audit ? `Auditoria #${audit.id}` : 'Detalhes da Auditoria'}
            headerColor="bg-indigo-600" headerBadges={headerBadges}
            loading={loading} errorMessage={error} maxWidth="4xl"
            footer={footerContent && <StandardModal.Footer>{footerContent}</StandardModal.Footer>}>

            {audit && (
                <>
                    {/* Informações Gerais */}
                    <StandardModal.Section title="Informações Gerais">
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <StandardModal.Field label="Loja" value={audit.store?.name} />
                            <StandardModal.Field label="Tipo" value={TYPE_LABELS[audit.audit_type] || audit.audit_type} />
                            <StandardModal.Field label="Data Início" value={audit.started_at ? new Date(audit.started_at).toLocaleDateString('pt-BR') : null} />
                            <StandardModal.Field label="Data Fim" value={audit.completed_at ? new Date(audit.completed_at).toLocaleDateString('pt-BR') : null} />
                            <StandardModal.Field label="Segunda Contagem" value={audit.requires_second_count ? 'Sim' : 'Não'} />
                            <StandardModal.Field label="Terceira Contagem" value={audit.requires_third_count ? 'Sim' : 'Não'} />
                            {audit.vendor && <StandardModal.Field label="Fornecedor" value={audit.vendor.name} />}
                            {audit.audit_cycle && <StandardModal.Field label="Ciclo" value={audit.audit_cycle.name} />}
                            {audit.notes && (
                                <div className="col-span-full">
                                    <StandardModal.Field label="Observações" value={audit.notes} />
                                </div>
                            )}
                        </div>
                    </StandardModal.Section>

                    {/* Equipe */}
                    <StandardModal.Section title="Equipe">
                        <div className="grid grid-cols-2 gap-4">
                            <StandardModal.Field label="Gerente Responsável" value={audit.manager_responsible?.name} />
                            <StandardModal.Field label="Estoquista" value={audit.stockist?.name} />
                        </div>
                        {audit.team_members?.length > 0 && (
                            <div className="mt-3 pt-3 border-t border-gray-200">
                                <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Membros da Equipe</p>
                                <div className="space-y-1">
                                    {audit.team_members.map((m, i) => (
                                        <div key={i} className="flex items-center justify-between text-sm">
                                            <span className="text-gray-900">{m.user?.name || m.external_staff_name || '-'}</span>
                                            <StatusBadge variant="gray" size="sm">{m.role}</StatusBadge>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </StandardModal.Section>

                    {/* Áreas */}
                    {audit.areas?.length > 0 && (
                        <StandardModal.Section title="Áreas">
                            <div className="space-y-2">
                                {audit.areas.map((a, i) => (
                                    <div key={i} className="flex items-center justify-between text-sm">
                                        <span className="text-gray-900">{a.name}</span>
                                        <StatusBadge variant="indigo" size="sm">{a.items_count ?? 0} itens</StatusBadge>
                                    </div>
                                ))}
                            </div>
                        </StandardModal.Section>
                    )}

                    {/* Resumo dos Itens */}
                    {audit.items_summary && (
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <StandardModal.InfoCard label="Total" value={(audit.items_summary.total ?? 0).toLocaleString()} colorClass="bg-blue-50" />
                            <StandardModal.InfoCard label="Contados" value={(audit.items_summary.counted ?? 0).toLocaleString()} colorClass="bg-green-50" />
                            <StandardModal.InfoCard label="Divergentes" value={(audit.items_summary.divergent ?? 0).toLocaleString()} colorClass="bg-red-50" />
                            <StandardModal.InfoCard label="Pendentes" value={(audit.items_summary.pending ?? 0).toLocaleString()} colorClass="bg-yellow-50" />
                        </div>
                    )}

                    {/* Histórico */}
                    {audit.logs?.length > 0 && (
                        <StandardModal.Section title="Histórico">
                            <div className="space-y-2 max-h-48 overflow-y-auto -mx-4 -mb-4 px-4 pb-4">
                                {audit.logs.map((log, i) => (
                                    <div key={i} className="flex items-start gap-3 text-sm border-b border-gray-100 pb-2 last:border-0">
                                        <span className="text-xs text-gray-400 whitespace-nowrap mt-0.5">
                                            {new Date(log.created_at).toLocaleString('pt-BR')}
                                        </span>
                                        <div>
                                            <span className="text-gray-900">{log.description}</span>
                                            {log.user && <span className="text-xs text-gray-500 ml-1">- {log.user.name}</span>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </StandardModal.Section>
                    )}

                    {/* Assinaturas */}
                    {audit.signatures?.length > 0 && (
                        <StandardModal.Section title="Assinaturas">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 -mx-4 -mb-4 px-4 pb-4">
                                {audit.signatures.map((sig, i) => (
                                    <div key={i} className="bg-gray-50 rounded-lg p-3 text-center">
                                        {sig.signature_data && (
                                            <img src={sig.signature_data} alt={`Assinatura ${sig.role}`} className="mx-auto h-16 mb-2" />
                                        )}
                                        <p className="text-sm font-medium text-gray-900">{sig.signer_name || '-'}</p>
                                        <p className="text-xs text-gray-500">{sig.role}</p>
                                        {sig.signed_at && <p className="text-xs text-gray-400">{new Date(sig.signed_at).toLocaleString('pt-BR')}</p>}
                                    </div>
                                ))}
                            </div>
                        </StandardModal.Section>
                    )}
                </>
            )}
        </StandardModal>
    );
}
