import { useState } from 'react';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { ArrowPathIcon } from '@heroicons/react/24/outline';

const TRANSITION_LABELS = {
    start: 'Iniciar', plan: 'Planejar', begin_counting: 'Iniciar Contagem',
    finish_counting: 'Finalizar Contagem', begin_recount: 'Iniciar Recontagem',
    finish_recount: 'Finalizar Recontagem', begin_reconciliation: 'Iniciar Conciliação',
    finish_reconciliation: 'Finalizar Conciliação', submit_approval: 'Enviar para Aprovação',
    approve: 'Aprovar', reject: 'Rejeitar', complete: 'Concluir',
    cancel: 'Cancelar Auditoria', reopen: 'Reabrir',
};

const TRANSITION_VARIANT = {
    start: 'info', plan: 'primary', begin_counting: 'warning', finish_counting: 'success',
    begin_recount: 'warning', finish_recount: 'success', begin_reconciliation: 'primary',
    finish_reconciliation: 'success', submit_approval: 'primary', approve: 'success',
    reject: 'danger', complete: 'success', cancel: 'danger', reopen: 'warning',
};

export default function TransitionModal({ show, onClose, data: modalData, statusOptions }) {
    const audit = modalData?.audit;
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [selectedTransition, setSelectedTransition] = useState(null);

    const transitions = audit?.available_transitions || [];
    const requiresReason = (t) => t === 'cancel' || t === 'reject';

    const handleTransition = async (transition) => {
        if (requiresReason(transition) && !reason.trim()) {
            setErrors({ reason: 'Motivo é obrigatório.' });
            setSelectedTransition(transition);
            return;
        }

        setProcessing(true);
        setErrors({});
        try {
            const res = await fetch(route('stock-audits.transition', audit.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                body: JSON.stringify({ transition, reason: reason.trim() || undefined }),
            });
            const json = await res.json();
            if (!res.ok) { setErrors(json.errors || { general: json.message || 'Erro ao realizar transição.' }); return; }
            setReason(''); setSelectedTransition(null); handleClose();
        } catch { setErrors({ general: 'Erro de conexão. Tente novamente.' }); }
        finally { setProcessing(false); }
    };

    const handleClose = () => { setReason(''); setErrors({}); setSelectedTransition(null); onClose(); };

    return (
        <StandardModal show={show} onClose={handleClose} title="Transição de Status"
            subtitle={audit ? `Auditoria #${audit.id}` : undefined}
            headerColor="bg-indigo-600" headerIcon={<ArrowPathIcon className="h-5 w-5" />}
            maxWidth="md" errorMessage={errors.general}
            footer={<StandardModal.Footer onCancel={handleClose} cancelLabel="Fechar" />}>

            {audit && (
                <p className="text-sm text-gray-600">
                    Status atual: <span className="font-medium text-gray-900">{statusOptions?.[audit.status] || audit.status}</span>
                </p>
            )}

            {transitions.length === 0 ? (
                <div className="text-center text-gray-500 py-6">Nenhuma transição disponível para este status.</div>
            ) : !selectedTransition ? (
                <StandardModal.Section title="Transições Disponíveis">
                    <div className="grid grid-cols-1 gap-2 -mx-4 -mb-4 px-4 pb-4">
                        {transitions.map((t) => (
                            <Button key={t} variant={TRANSITION_VARIANT[t] || 'secondary'} className="w-full justify-center"
                                disabled={processing} loading={processing}
                                onClick={() => requiresReason(t) ? setSelectedTransition(t) : handleTransition(t)}>
                                {TRANSITION_LABELS[t] || t}
                            </Button>
                        ))}
                    </div>
                </StandardModal.Section>
            ) : (
                <StandardModal.Section title={`Confirmar: ${TRANSITION_LABELS[selectedTransition]}`}>
                    <div className="space-y-3 -mx-4 -mb-4 px-4 pb-4">
                        <div>
                            <InputLabel value="Motivo *" />
                            <textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={3}
                                placeholder="Informe o motivo..."
                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            <InputError message={errors.reason} className="mt-1" />
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button variant="outline" size="sm" onClick={() => { setSelectedTransition(null); setReason(''); setErrors({}); }}>
                                Voltar
                            </Button>
                            <Button variant="danger" size="sm" loading={processing}
                                onClick={() => handleTransition(selectedTransition)}>
                                Confirmar {TRANSITION_LABELS[selectedTransition]}
                            </Button>
                        </div>
                    </div>
                </StandardModal.Section>
            )}
        </StandardModal>
    );
}
