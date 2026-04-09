import { useState } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

const TRANSITION_COLORS = {
    start: 'bg-blue-600 hover:bg-blue-700 text-white',
    plan: 'bg-indigo-600 hover:bg-indigo-700 text-white',
    begin_counting: 'bg-amber-600 hover:bg-amber-700 text-white',
    finish_counting: 'bg-green-600 hover:bg-green-700 text-white',
    begin_recount: 'bg-orange-600 hover:bg-orange-700 text-white',
    finish_recount: 'bg-green-600 hover:bg-green-700 text-white',
    begin_reconciliation: 'bg-purple-600 hover:bg-purple-700 text-white',
    finish_reconciliation: 'bg-green-600 hover:bg-green-700 text-white',
    submit_approval: 'bg-indigo-600 hover:bg-indigo-700 text-white',
    approve: 'bg-green-600 hover:bg-green-700 text-white',
    reject: 'bg-red-600 hover:bg-red-700 text-white',
    complete: 'bg-green-700 hover:bg-green-800 text-white',
    cancel: 'bg-red-600 hover:bg-red-700 text-white',
    reopen: 'bg-yellow-600 hover:bg-yellow-700 text-white',
};

const TRANSITION_LABELS = {
    start: 'Iniciar',
    plan: 'Planejar',
    begin_counting: 'Iniciar Contagem',
    finish_counting: 'Finalizar Contagem',
    begin_recount: 'Iniciar Recontagem',
    finish_recount: 'Finalizar Recontagem',
    begin_reconciliation: 'Iniciar Conciliacao',
    finish_reconciliation: 'Finalizar Conciliacao',
    submit_approval: 'Enviar para Aprovacao',
    approve: 'Aprovar',
    reject: 'Rejeitar',
    complete: 'Concluir',
    cancel: 'Cancelar Auditoria',
    reopen: 'Reabrir',
};

export default function TransitionModal({ show, onClose, audit, onSuccess }) {
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [selectedTransition, setSelectedTransition] = useState(null);

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const transitions = audit?.available_transitions || [];

    const handleTransition = async (transition) => {
        if (transition === 'cancel' && !reason.trim()) {
            setErrors({ reason: 'Motivo e obrigatorio para cancelamento.' });
            setSelectedTransition('cancel');
            return;
        }

        setProcessing(true);
        setErrors({});

        try {
            const res = await fetch(route('stock-audits.transition', audit.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    transition,
                    reason: reason.trim() || undefined,
                }),
            });

            const json = await res.json();

            if (!res.ok) {
                setErrors(json.errors || { general: json.message || 'Erro ao realizar transicao.' });
                return;
            }

            setReason('');
            setSelectedTransition(null);
            onSuccess?.();
        } catch {
            setErrors({ general: 'Erro de conexao. Tente novamente.' });
        } finally {
            setProcessing(false);
        }
    };

    const handleClose = () => {
        setReason('');
        setErrors({});
        setSelectedTransition(null);
        onClose();
    };

    const requiresReason = (transition) => transition === 'cancel' || transition === 'reject';

    return (
        <Modal show={show} onClose={handleClose} maxWidth="md">
            <div className="flex flex-col max-h-[80vh]">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 bg-indigo-600 text-white flex-shrink-0 rounded-t-lg">
                    <h2 className="text-lg font-semibold">Transicao de Status</h2>
                    <button onClick={handleClose} className="text-white/80 hover:text-white transition">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 overflow-y-auto p-6 space-y-5">
                    {audit && (
                        <div className="text-sm text-gray-600">
                            Auditoria <strong>#{audit.id}</strong> - Status atual:{' '}
                            <span className="font-medium text-gray-900">{audit.status}</span>
                        </div>
                    )}

                    {errors.general && (
                        <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                            {errors.general}
                        </div>
                    )}

                    {transitions.length === 0 && (
                        <div className="text-center text-gray-500 py-6">
                            Nenhuma transicao disponivel para este status.
                        </div>
                    )}

                    {transitions.length > 0 && (
                        <div className="space-y-3">
                            <p className="text-sm text-gray-600 font-medium">Transicoes disponiveis:</p>
                            <div className="grid grid-cols-1 gap-2">
                                {transitions.map((transition) => (
                                    <button
                                        key={transition}
                                        type="button"
                                        disabled={processing}
                                        onClick={() => {
                                            if (requiresReason(transition)) {
                                                setSelectedTransition(transition);
                                            } else {
                                                handleTransition(transition);
                                            }
                                        }}
                                        className={`w-full px-4 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed ${
                                            TRANSITION_COLORS[transition] || 'bg-gray-600 hover:bg-gray-700 text-white'
                                        }`}
                                    >
                                        {processing ? 'Processando...' : (TRANSITION_LABELS[transition] || transition)}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Reason field (shown for cancel/reject) */}
                    {selectedTransition && requiresReason(selectedTransition) && (
                        <div className="space-y-3 border-t border-gray-200 pt-4">
                            <label className="block text-sm font-medium text-gray-700">
                                Motivo <span className="text-red-500">*</span>
                            </label>
                            <textarea
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                rows={3}
                                className={`w-full rounded-md border shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${
                                    errors.reason ? 'border-red-300' : 'border-gray-300'
                                }`}
                                placeholder="Informe o motivo..."
                            />
                            {errors.reason && <p className="mt-1 text-sm text-red-600">{errors.reason}</p>}

                            <div className="flex justify-end gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => {
                                        setSelectedTransition(null);
                                        setReason('');
                                        setErrors({});
                                    }}
                                >
                                    Voltar
                                </Button>
                                <Button
                                    variant="danger"
                                    size="sm"
                                    disabled={processing}
                                    loading={processing}
                                    onClick={() => handleTransition(selectedTransition)}
                                >
                                    Confirmar {TRANSITION_LABELS[selectedTransition] || selectedTransition}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex justify-end px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                    <Button variant="secondary" onClick={handleClose}>
                        Fechar
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
