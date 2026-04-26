import { useEffect, useState } from 'react';
import { CheckCircleIcon, ArrowUturnLeftIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';

const COLOR_BG = {
    success: 'bg-green-50 border-green-300 hover:bg-green-100',
    info:    'bg-blue-50 border-blue-300 hover:bg-blue-100',
    warning: 'bg-amber-50 border-amber-300 hover:bg-amber-100',
    danger:  'bg-red-50 border-red-300 hover:bg-red-100',
    purple:  'bg-purple-50 border-purple-300 hover:bg-purple-100',
    gray:    'bg-gray-50 border-gray-300 hover:bg-gray-100',
};

const COLOR_TEXT = {
    success: 'text-green-700',
    info:    'text-blue-700',
    warning: 'text-amber-700',
    danger:  'text-red-700',
    purple:  'text-purple-700',
    gray:    'text-gray-700',
};

export default function SelectOutcomeModal({
    show,
    onClose,
    attendance,
    outcomes = [],
    onConfirm,
}) {
    const [selectedId, setSelectedId] = useState(null);
    const [returnToQueue, setReturnToQueue] = useState(true);
    const [notes, setNotes] = useState('');

    useEffect(() => {
        if (show) {
            setSelectedId(null);
            setReturnToQueue(true);
            setNotes('');
        }
    }, [show]);

    const handleConfirm = () => {
        if (!selectedId) return;
        onConfirm({ outcomeId: selectedId, returnToQueue, notes: notes.trim() || null });
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Finalizar Atendimento"
            subtitle={attendance?.employee_name ? `Consultora: ${attendance.employee_name}` : ''}
            headerColor="bg-blue-600"
            headerIcon={<CheckCircleIcon className="h-6 w-6" />}
            maxWidth="3xl"
            footer={(
                <StandardModal.Footer>
                    <label className="inline-flex items-center text-sm text-gray-700 gap-2">
                        <input
                            type="checkbox"
                            checked={returnToQueue}
                            onChange={(e) => setReturnToQueue(e.target.checked)}
                            className="rounded border-gray-300"
                        />
                        Retornar para fila
                    </label>
                    <div className="flex items-center gap-3 ml-auto">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors min-h-[44px]"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            onClick={handleConfirm}
                            disabled={!selectedId}
                            className="px-6 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg disabled:opacity-40 transition-colors min-h-[44px]"
                        >
                            Confirmar
                        </button>
                    </div>
                </StandardModal.Footer>
            )}
        >
            <StandardModal.Section title="Como foi o atendimento?">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {outcomes.map((o) => {
                        const selected = selectedId === o.id;
                        return (
                            <button
                                type="button"
                                key={o.id}
                                onClick={() => setSelectedId(o.id)}
                                className={`text-left p-3 rounded-lg border-2 transition-all min-h-[72px] ${
                                    selected
                                        ? 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-200'
                                        : (COLOR_BG[o.color] ?? COLOR_BG.gray)
                                }`}
                            >
                                <div className="flex items-start gap-2">
                                    {o.icon && (
                                        <i className={`${o.icon} ${selected ? 'text-indigo-600' : (COLOR_TEXT[o.color] ?? COLOR_TEXT.gray)} mt-0.5`} />
                                    )}
                                    <div className="flex-1 min-w-0">
                                        <div className={`font-semibold text-sm ${selected ? 'text-indigo-900' : 'text-gray-900'}`}>
                                            {o.name}
                                        </div>
                                        {o.description && (
                                            <div className="text-xs text-gray-600 mt-0.5">{o.description}</div>
                                        )}
                                        <div className="flex items-center gap-1 mt-1.5 flex-wrap">
                                            {o.is_conversion && (
                                                <span className="inline-flex items-center gap-1 text-[10px] font-bold uppercase bg-green-100 text-green-800 px-1.5 py-0.5 rounded">
                                                    <CheckCircleIcon className="h-3 w-3" /> Conversão
                                                </span>
                                            )}
                                            {o.restore_queue_position && (
                                                <span className="inline-flex items-center gap-1 text-[10px] font-bold uppercase bg-purple-100 text-purple-800 px-1.5 py-0.5 rounded">
                                                    <ArrowUturnLeftIcon className="h-3 w-3" /> Volta na vez
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </button>
                        );
                    })}
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Observações (opcional)">
                <textarea
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    rows={2}
                    maxLength={500}
                    placeholder="Algo relevante sobre este atendimento..."
                    className="w-full rounded-md border-gray-300 text-sm"
                />
            </StandardModal.Section>
        </StandardModal>
    );
}
