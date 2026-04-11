import { useState } from 'react';
import { router } from '@inertiajs/react';

export default function DismissalFollowUpSection({ movementId, followUp, editable = false }) {
    const [data, setData] = useState({ ...followUp });
    const [saving, setSaving] = useState(false);

    if (!followUp) return null;

    const toggle = (field) => {
        if (!editable) return;
        setData(prev => ({ ...prev, [field]: !prev[field] }));
    };

    const handleSave = () => {
        setSaving(true);
        router.put(route('personnel-movements.follow-up.update', movementId), data, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    const items = [
        { field: 'uniform', label: 'Uniforme devolvido' },
        { field: 'phone_chip', label: 'Chip telefônico devolvido' },
        { field: 'original_card', label: 'Cartão original devolvido' },
        { field: 'aso', label: 'ASO realizado' },
        { field: 'aso_resigns', label: 'ASO demissional' },
        { field: 'send_aso_guide', label: 'Guia ASO enviado' },
    ];

    return (
        <div className="space-y-3">
            <h4 className="text-sm font-semibold text-gray-700">Checklist de Follow-up</h4>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                {items.map(({ field, label }) => (
                    <label key={field} className="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={data[field] || false}
                            onChange={() => toggle(field)}
                            disabled={!editable}
                            className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span className="text-sm text-gray-700">{label}</span>
                    </label>
                ))}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                <div>
                    <label className="block text-xs font-medium text-gray-600">Data Assinatura TRCT</label>
                    <div className="text-sm text-gray-900">{followUp.signature_date_trct || '—'}</div>
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-600">Data Rescisão</label>
                    <div className="text-sm text-gray-900">{followUp.termination_date || '—'}</div>
                </div>
            </div>

            {editable && (
                <button
                    onClick={handleSave}
                    disabled={saving}
                    className="mt-2 px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                    {saving ? 'Salvando...' : 'Salvar Checklist'}
                </button>
            )}
        </div>
    );
}
