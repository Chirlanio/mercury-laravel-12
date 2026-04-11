import { useState } from 'react';
import { router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';

const STATUS_LABELS = {
    pending: 'Pendente',
    in_progress: 'Em Andamento',
    completed: 'Concluído',
    cancelled: 'Cancelado',
};

const STATUS_VARIANTS = {
    pending: 'warning',
    in_progress: 'info',
    completed: 'success',
    cancelled: 'danger',
};

export default function TransitionModal({ show, onClose, movement }) {
    const [selectedStatus, setSelectedStatus] = useState('');
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');

    if (!movement) return null;

    const transitions = movement.available_transitions || [];

    const handleSubmit = () => {
        if (!selectedStatus) return;
        setProcessing(true);
        setError('');

        fetch(route('personnel-movements.transition', movement.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ new_status: selectedStatus, notes }),
        })
            .then(res => res.json())
            .then(data => {
                setProcessing(false);
                if (data.error) {
                    setError(data.message);
                } else {
                    onClose();
                    router.reload();
                }
            })
            .catch(() => {
                setProcessing(false);
                setError('Erro ao atualizar status.');
            });
    };

    const handleClose = () => {
        setSelectedStatus('');
        setNotes('');
        setError('');
        onClose();
    };

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Alterar Status"
            headerColor="bg-indigo-600"
            maxWidth="md"
        >
            <div className="p-4 space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Status Atual</label>
                    <StatusBadge variant={STATUS_VARIANTS[movement.status] || 'gray'}>
                        {movement.status_label}
                    </StatusBadge>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">Novo Status</label>
                    <div className="space-y-2">
                        {transitions.map(status => (
                            <label key={status} className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="new_status"
                                    value={status}
                                    checked={selectedStatus === status}
                                    onChange={e => setSelectedStatus(e.target.value)}
                                    className="text-indigo-600 focus:ring-indigo-500"
                                />
                                <StatusBadge variant={STATUS_VARIANTS[status] || 'gray'} size="sm">
                                    {STATUS_LABELS[status] || status}
                                </StatusBadge>
                            </label>
                        ))}
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Observações {selectedStatus === 'cancelled' && <span className="text-red-500">*</span>}
                    </label>
                    <textarea
                        value={notes}
                        onChange={e => setNotes(e.target.value)}
                        rows={3}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        placeholder={selectedStatus === 'cancelled' ? 'Motivo do cancelamento (obrigatório)' : 'Observações opcionais...'}
                    />
                </div>

                {error && (
                    <div className="bg-red-50 text-red-700 px-3 py-2 rounded-md text-sm">{error}</div>
                )}
            </div>

            <StandardModal.Footer
                onCancel={handleClose}
                onSubmit={handleSubmit}
                submitLabel="Confirmar"
                processing={processing}
                submitDisabled={!selectedStatus || (selectedStatus === 'cancelled' && !notes)}
            />
        </StandardModal>
    );
}
