import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { ArrowPathIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

/**
 * Modal genérico de transição. Recebe `config` com:
 *  - expense (com ulid + descrição visível)
 *  - kind: 'expense' | 'accountability'
 *  - toStatus: status alvo
 *  - label: texto do botão de ação ("Aprovar", "Rejeitar"...)
 *  - requiresNote: boolean — força textarea obrigatório
 */
export default function TransitionModal({ show, onClose, config }) {
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (show) {
            setNote('');
            setErrors({});
        }
    }, [show, config]);

    const handleSubmit = (e) => {
        e?.preventDefault?.();
        if (!config?.expense) return;

        if (config.requiresNote && !note.trim()) {
            setErrors({ note: 'Informe o motivo.' });
            return;
        }

        setProcessing(true);
        setErrors({});
        router.post(route('travel-expenses.transition', config.expense.ulid), {
            kind: config.kind,
            to_status: config.toStatus,
            note: note || null,
        }, {
            preserveScroll: true,
            onError: (err) => setErrors(err),
            onSuccess: () => onClose?.(),
            onFinish: () => setProcessing(false),
        });
    };

    if (!config) return null;

    const isDestructive = ['rejected', 'cancelled'].includes(config.toStatus);
    const headerColor = isDestructive ? 'bg-red-600' : (config.toStatus === 'approved' || config.toStatus === 'finalized' ? 'bg-green-600' : 'bg-indigo-600');

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={config.label}
            subtitle={config.expense ? `${config.expense.origin} → ${config.expense.destination}` : ''}
            headerColor={headerColor}
            headerIcon={<ArrowPathIcon className="h-6 w-6" />}
            maxWidth="lg"
            onSubmit={handleSubmit}
            footer={(
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel={config.label}
                    submitColor={isDestructive ? 'danger' : 'primary'}
                    processing={processing}
                />
            )}
        >
            <StandardModal.Section title="Confirmação">
                <p className="text-sm text-gray-700">
                    Você está prestes a <strong>{config.label.toLowerCase()}</strong> esta verba.
                    {' '}
                    {config.kind === 'accountability'
                        ? 'A ação afeta o status da prestação de contas.'
                        : 'A ação afeta o status da solicitação.'}
                </p>

                <div className="mt-4">
                    <InputLabel htmlFor="note" value={config.requiresNote ? 'Motivo *' : 'Observação (opcional)'} />
                    <textarea
                        id="note"
                        rows={3}
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        className="w-full mt-1 rounded-md border-gray-300 text-sm"
                        placeholder={config.requiresNote
                            ? 'Justifique o motivo desta ação...'
                            : 'Opcional — visível no histórico'}
                    />
                    <InputError message={errors.note || errors.status || errors.payment} />
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
