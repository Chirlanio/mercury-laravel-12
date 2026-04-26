import { useEffect, useState } from 'react';
import { PauseIcon, ClockIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';

const COLOR_BG = {
    info:    'bg-blue-50 border-blue-300 hover:bg-blue-100',
    warning: 'bg-amber-50 border-amber-300 hover:bg-amber-100',
    success: 'bg-green-50 border-green-300 hover:bg-green-100',
    danger:  'bg-red-50 border-red-300 hover:bg-red-100',
    purple:  'bg-purple-50 border-purple-300 hover:bg-purple-100',
    gray:    'bg-gray-50 border-gray-300 hover:bg-gray-100',
};

const COLOR_TEXT = {
    info:    'text-blue-700',
    warning: 'text-amber-700',
    success: 'text-green-700',
    danger:  'text-red-700',
    purple:  'text-purple-700',
    gray:    'text-gray-700',
};

export default function SelectBreakTypeModal({
    show,
    onClose,
    breakTypes = [],
    onConfirm,
}) {
    const [selectedId, setSelectedId] = useState(null);

    useEffect(() => {
        if (show) setSelectedId(null);
    }, [show]);

    const handleConfirm = () => {
        if (!selectedId) return;
        onConfirm({ breakTypeId: selectedId });
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Iniciar Pausa"
            subtitle="Escolha o tipo de pausa"
            headerColor="bg-purple-600"
            headerIcon={<PauseIcon className="h-6 w-6" />}
            maxWidth="md"
            footer={(
                <StandardModal.Footer>
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
                            className="px-6 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg disabled:opacity-40 transition-colors min-h-[44px]"
                        >
                            Iniciar
                        </button>
                    </div>
                </StandardModal.Footer>
            )}
        >
            <StandardModal.Section title="Tipos de pausa disponíveis">
                <div className="grid grid-cols-1 gap-2">
                    {breakTypes.map((t) => {
                        const selected = selectedId === t.id;
                        return (
                            <button
                                type="button"
                                key={t.id}
                                onClick={() => setSelectedId(t.id)}
                                className={`text-left p-4 rounded-lg border-2 transition-all min-h-[72px] flex items-center gap-3 ${
                                    selected
                                        ? 'border-purple-600 bg-purple-50 ring-2 ring-purple-200'
                                        : (COLOR_BG[t.color] ?? COLOR_BG.gray)
                                }`}
                            >
                                {t.icon && (
                                    <i className={`${t.icon} text-2xl ${selected ? 'text-purple-600' : (COLOR_TEXT[t.color] ?? COLOR_TEXT.gray)}`} />
                                )}
                                <div className="flex-1">
                                    <div className={`font-semibold text-base ${selected ? 'text-purple-900' : 'text-gray-900'}`}>
                                        {t.name}
                                    </div>
                                    <div className="text-xs text-gray-600 mt-0.5 flex items-center gap-1">
                                        <ClockIcon className="h-3 w-3" />
                                        Tempo máximo: {t.max_duration_minutes} min
                                    </div>
                                </div>
                            </button>
                        );
                    })}
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
