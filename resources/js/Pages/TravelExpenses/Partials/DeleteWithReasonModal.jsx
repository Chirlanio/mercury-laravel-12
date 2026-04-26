import { useEffect, useState } from 'react';
import { TrashIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

/**
 * Modal de exclusão com motivo obrigatório (deleted_reason >= 3 chars).
 * Substitui o DeleteConfirmModal genérico quando o backend exige justificativa.
 */
export default function DeleteWithReasonModal({
    show,
    onClose,
    onConfirm,
    itemName = '',
    details = [],
    processing = false,
}) {
    const [reason, setReason] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        if (show) {
            setReason('');
            setError('');
        }
    }, [show]);

    const handleSubmit = (e) => {
        e?.preventDefault?.();
        const trimmed = reason.trim();
        if (trimmed.length < 3) {
            setError('Informe um motivo com pelo menos 3 caracteres.');
            return;
        }
        onConfirm?.(trimmed);
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Excluir verba de viagem"
            subtitle={itemName}
            headerColor="bg-red-600"
            headerIcon={<TrashIcon className="h-6 w-6" />}
            maxWidth="lg"
            onSubmit={handleSubmit}
            footer={(
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel="Excluir verba"
                    submitColor="danger"
                    processing={processing}
                />
            )}
        >
            <StandardModal.Section title="Confirmação">
                {details.length > 0 && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
                        {details.map((d, i) => (
                            <div key={i} className="text-sm">
                                <div className="text-xs uppercase text-gray-500 tracking-wide">{d.label}</div>
                                <div className="font-medium text-gray-900">{d.value}</div>
                            </div>
                        ))}
                    </div>
                )}

                <div className="rounded-md bg-red-50 border border-red-200 p-3 mb-4">
                    <div className="flex items-start gap-2">
                        <ExclamationTriangleIcon className="h-5 w-5 text-red-600 shrink-0 mt-0.5" />
                        <p className="text-sm text-red-800">
                            Esta ação fará soft-delete da verba — ela não aparecerá nas
                            listagens, mas o registro fica preservado para auditoria.
                            <strong> Não é possível excluir verbas aprovadas/finalizadas
                            ou com itens de prestação lançados.</strong>
                        </p>
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="delete_reason" value="Motivo da exclusão *" />
                    <textarea
                        id="delete_reason"
                        rows={3}
                        value={reason}
                        onChange={(e) => { setReason(e.target.value); setError(''); }}
                        className="w-full mt-1 rounded-md border-gray-300 text-sm"
                        placeholder="Ex: Solicitação duplicada, criada por engano..."
                    />
                    <InputError message={error} />
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
