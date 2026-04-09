import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

const STATUS_COLORS = {
    draft: 'bg-gray-100 text-gray-800',
    planned: 'bg-blue-100 text-blue-800',
    in_progress: 'bg-yellow-100 text-yellow-800',
    counting: 'bg-amber-100 text-amber-800',
    recounting: 'bg-orange-100 text-orange-800',
    reconciliation: 'bg-purple-100 text-purple-800',
    pending_approval: 'bg-indigo-100 text-indigo-800',
    approved: 'bg-green-100 text-green-800',
    completed: 'bg-green-200 text-green-900',
    cancelled: 'bg-red-100 text-red-800',
};

const STATUS_LABELS = {
    draft: 'Rascunho',
    planned: 'Planejada',
    in_progress: 'Em Andamento',
    counting: 'Contagem',
    recounting: 'Recontagem',
    reconciliation: 'Conciliacao',
    pending_approval: 'Aguardando Aprovacao',
    approved: 'Aprovada',
    completed: 'Concluida',
    cancelled: 'Cancelada',
};

const TYPE_LABELS = {
    total: 'Total',
    parcial: 'Parcial',
    especifica: 'Especifica',
    aleatoria: 'Aleatoria',
    diaria: 'Diaria',
};

export default function DetailModal({ show, onClose, auditId, onTransition, onEdit }) {
    const [audit, setAudit] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    useEffect(() => {
        if (!show || !auditId) {
            setAudit(null);
            return;
        }

        setLoading(true);
        setError('');

        fetch(route('stock-audits.show', auditId), {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        })
            .then((res) => {
                if (!res.ok) throw new Error('Erro ao carregar dados da auditoria.');
                return res.json();
            })
            .then((json) => setAudit(json.data || json))
            .catch((err) => setError(err.message))
            .finally(() => setLoading(false));
    }, [show, auditId]);

    const handleClose = () => {
        setAudit(null);
        setError('');
        onClose();
    };

    const statusColor = audit ? (STATUS_COLORS[audit.status] || 'bg-gray-100 text-gray-800') : '';
    const statusLabel = audit ? (STATUS_LABELS[audit.status] || audit.status) : '';

    return (
        <Modal show={show} onClose={handleClose} maxWidth="4xl">
            <div className="flex flex-col max-h-[80vh]">
                {/* Header */}
                <div className={`flex items-center justify-between px-6 py-4 flex-shrink-0 rounded-t-lg ${
                    audit ? 'bg-indigo-600 text-white' : 'bg-gray-600 text-white'
                }`}>
                    <div className="flex items-center gap-3">
                        <h2 className="text-lg font-semibold">
                            {audit ? `Auditoria #${audit.id}` : 'Detalhes da Auditoria'}
                        </h2>
                        {audit && (
                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColor}`}>
                                {statusLabel}
                            </span>
                        )}
                    </div>
                    <button onClick={handleClose} className="text-white/80 hover:text-white transition">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 overflow-y-auto p-6">
                    {loading && (
                        <div className="flex items-center justify-center py-16">
                            <div className="animate-spin rounded-full h-8 w-8 border-2 border-indigo-600 border-t-transparent"></div>
                            <span className="ml-3 text-gray-500">Carregando...</span>
                        </div>
                    )}

                    {error && (
                        <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                            {error}
                        </div>
                    )}

                    {audit && !loading && (
                        <div className="space-y-6">
                            {/* Informacoes Gerais */}
                            <section>
                                <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">
                                    Informacoes Gerais
                                </h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 rounded-lg p-4">
                                    <div>
                                        <span className="text-xs text-gray-500">Loja</span>
                                        <p className="text-sm font-medium text-gray-900">
                                            {audit.store?.name || '-'}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-xs text-gray-500">Tipo</span>
                                        <p className="text-sm font-medium text-gray-900">
                                            {TYPE_LABELS[audit.audit_type] || audit.audit_type}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-xs text-gray-500">Data Inicio</span>
                                        <p className="text-sm font-medium text-gray-900">
                                            {audit.started_at
                                                ? new Date(audit.started_at).toLocaleDateString('pt-BR')
                                                : '-'}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-xs text-gray-500">Data Fim</span>
                                        <p className="text-sm font-medium text-gray-900">
                                            {audit.completed_at
                                                ? new Date(audit.completed_at).toLocaleDateString('pt-BR')
                                                : '-'}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-xs text-gray-500">Segunda Contagem</span>
                                        <p className="text-sm font-medium text-gray-900">
                                            {audit.requires_second_count ? 'Sim' : 'Nao'}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-xs text-gray-500">Terceira Contagem</span>
                                        <p className="text-sm font-medium text-gray-900">
                                            {audit.requires_third_count ? 'Sim' : 'Nao'}
                                        </p>
                                    </div>
                                    {audit.vendor && (
                                        <div>
                                            <span className="text-xs text-gray-500">Fornecedor</span>
                                            <p className="text-sm font-medium text-gray-900">{audit.vendor.name}</p>
                                        </div>
                                    )}
                                    {audit.audit_cycle && (
                                        <div>
                                            <span className="text-xs text-gray-500">Ciclo</span>
                                            <p className="text-sm font-medium text-gray-900">{audit.audit_cycle.name}</p>
                                        </div>
                                    )}
                                    {audit.notes && (
                                        <div className="md:col-span-2">
                                            <span className="text-xs text-gray-500">Observacoes</span>
                                            <p className="text-sm text-gray-900 whitespace-pre-wrap">{audit.notes}</p>
                                        </div>
                                    )}
                                </div>
                            </section>

                            {/* Equipe */}
                            <section>
                                <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">
                                    Equipe
                                </h3>
                                <div className="bg-gray-50 rounded-lg p-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <span className="text-xs text-gray-500">Gerente Responsavel</span>
                                            <p className="text-sm font-medium text-gray-900">
                                                {audit.manager_responsible?.name || '-'}
                                            </p>
                                        </div>
                                        <div>
                                            <span className="text-xs text-gray-500">Estoquista</span>
                                            <p className="text-sm font-medium text-gray-900">
                                                {audit.stockist?.name || '-'}
                                            </p>
                                        </div>
                                    </div>
                                    {audit.team_members && audit.team_members.length > 0 && (
                                        <div className="mt-3 pt-3 border-t border-gray-200">
                                            <span className="text-xs text-gray-500 mb-2 block">Membros da Equipe</span>
                                            <div className="space-y-1">
                                                {audit.team_members.map((member, idx) => (
                                                    <div key={idx} className="flex items-center justify-between text-sm">
                                                        <span className="text-gray-900">
                                                            {member.user?.name || member.external_staff_name || '-'}
                                                        </span>
                                                        <span className="text-xs text-gray-500 bg-gray-200 px-2 py-0.5 rounded">
                                                            {member.role}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </section>

                            {/* Areas */}
                            {audit.areas && audit.areas.length > 0 && (
                                <section>
                                    <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">
                                        Areas
                                    </h3>
                                    <div className="bg-gray-50 rounded-lg p-4">
                                        <div className="space-y-2">
                                            {audit.areas.map((area, idx) => (
                                                <div key={idx} className="flex items-center justify-between text-sm">
                                                    <span className="text-gray-900">{area.name}</span>
                                                    <span className="text-xs text-gray-500">
                                                        {area.items_count ?? 0} itens
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </section>
                            )}

                            {/* Resumo Itens */}
                            {audit.items_summary && (
                                <section>
                                    <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">
                                        Resumo dos Itens
                                    </h3>
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        <div className="bg-blue-50 rounded-lg p-3 text-center">
                                            <div className="text-xl font-bold text-blue-600">
                                                {(audit.items_summary.total ?? 0).toLocaleString()}
                                            </div>
                                            <div className="text-xs text-gray-500">Total</div>
                                        </div>
                                        <div className="bg-green-50 rounded-lg p-3 text-center">
                                            <div className="text-xl font-bold text-green-600">
                                                {(audit.items_summary.counted ?? 0).toLocaleString()}
                                            </div>
                                            <div className="text-xs text-gray-500">Contados</div>
                                        </div>
                                        <div className="bg-red-50 rounded-lg p-3 text-center">
                                            <div className="text-xl font-bold text-red-600">
                                                {(audit.items_summary.divergent ?? 0).toLocaleString()}
                                            </div>
                                            <div className="text-xs text-gray-500">Divergentes</div>
                                        </div>
                                        <div className="bg-yellow-50 rounded-lg p-3 text-center">
                                            <div className="text-xl font-bold text-yellow-600">
                                                {(audit.items_summary.pending ?? 0).toLocaleString()}
                                            </div>
                                            <div className="text-xs text-gray-500">Pendentes</div>
                                        </div>
                                    </div>
                                </section>
                            )}

                            {/* Logs */}
                            {audit.logs && audit.logs.length > 0 && (
                                <section>
                                    <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">
                                        Historico
                                    </h3>
                                    <div className="bg-gray-50 rounded-lg p-4 space-y-2 max-h-48 overflow-y-auto">
                                        {audit.logs.map((log, idx) => (
                                            <div key={idx} className="flex items-start gap-3 text-sm border-b border-gray-100 pb-2 last:border-0">
                                                <span className="text-xs text-gray-400 whitespace-nowrap mt-0.5">
                                                    {new Date(log.created_at).toLocaleString('pt-BR')}
                                                </span>
                                                <div>
                                                    <span className="text-gray-900">{log.description}</span>
                                                    {log.user && (
                                                        <span className="text-xs text-gray-500 ml-1">- {log.user.name}</span>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            )}

                            {/* Assinaturas */}
                            {audit.signatures && audit.signatures.length > 0 && (
                                <section>
                                    <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">
                                        Assinaturas
                                    </h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        {audit.signatures.map((sig, idx) => (
                                            <div key={idx} className="bg-gray-50 rounded-lg p-3 text-center">
                                                {sig.signature_data && (
                                                    <img
                                                        src={sig.signature_data}
                                                        alt={`Assinatura ${sig.role}`}
                                                        className="mx-auto h-16 mb-2"
                                                    />
                                                )}
                                                <p className="text-sm font-medium text-gray-900">{sig.signer_name || '-'}</p>
                                                <p className="text-xs text-gray-500">{sig.role}</p>
                                                <p className="text-xs text-gray-400">
                                                    {sig.signed_at ? new Date(sig.signed_at).toLocaleString('pt-BR') : ''}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            )}
                        </div>
                    )}
                </div>

                {/* Footer */}
                {audit && !loading && (
                    <div className="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                        <div className="flex flex-wrap gap-2">
                            {audit.status === 'counting' || audit.status === 'recounting' ? (
                                <Button
                                    variant="info"
                                    size="sm"
                                    onClick={() => window.location.href = route('stock-audits.counting', audit.id)}
                                >
                                    Ir para Contagem
                                </Button>
                            ) : null}

                            {audit.status === 'reconciliation' && (
                                <Button
                                    variant="info"
                                    size="sm"
                                    onClick={() => window.location.href = route('stock-audits.reconciliation', audit.id)}
                                >
                                    Ir para Conciliacao
                                </Button>
                            )}

                            {audit.available_transitions && audit.available_transitions.length > 0 && (
                                <Button
                                    variant="warning"
                                    size="sm"
                                    onClick={() => onTransition?.(audit)}
                                >
                                    Transicao
                                </Button>
                            )}

                            {audit.status === 'draft' && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => onEdit?.(audit)}
                                >
                                    Editar
                                </Button>
                            )}

                            <a
                                href={route('stock-audits.report', audit.id)}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 transition-all duration-300"
                            >
                                Relatorio PDF
                            </a>

                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => window.location.href = route('stock-audits.pendencies', audit.id)}
                            >
                                Pendencias
                            </Button>
                        </div>

                        <Button variant="secondary" size="sm" onClick={handleClose}>
                            Fechar
                        </Button>
                    </div>
                )}
            </div>
        </Modal>
    );
}
