import { useState } from 'react';
import StandardModal from '@/Components/StandardModal';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { DocumentTextIcon } from '@heroicons/react/24/outline';

export default function JustificationModal({ show, onClose, item, auditId, onSuccess }) {
    const [justificationText, setJustificationText] = useState('');
    const [foundQuantity, setFoundQuantity] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const handleSubmit = async () => {
        if (!justificationText.trim()) { setErrors({ justification_text: 'A justificativa é obrigatória.' }); return; }
        setProcessing(true); setErrors({});
        try {
            const body = { item_id: item?.id, justification_text: justificationText.trim() };
            if (foundQuantity !== '') body.found_quantity = parseInt(foundQuantity);
            const res = await fetch(route('stock-audits.justify', auditId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                body: JSON.stringify(body),
            });
            const json = await res.json();
            if (!res.ok) { setErrors(json.errors || { general: json.message || 'Erro ao salvar justificativa.' }); return; }
            setJustificationText(''); setFoundQuantity(''); onSuccess?.();
        } catch { setErrors({ general: 'Erro de conexão. Tente novamente.' }); }
        finally { setProcessing(false); }
    };

    const handleClose = () => { setJustificationText(''); setFoundQuantity(''); setErrors({}); onClose(); };

    return (
        <StandardModal show={show} onClose={handleClose} title="Justificativa de Divergência"
            headerColor="bg-indigo-600" headerIcon={<DocumentTextIcon className="h-5 w-5" />}
            maxWidth="md" onSubmit={handleSubmit} errorMessage={errors.general}
            footer={<StandardModal.Footer onCancel={handleClose} onSubmit="submit"
                submitLabel="Salvar Justificativa" processing={processing} />}>

            {/* Dados do Item */}
            {item && (
                <StandardModal.Section title="Dados do Item">
                    <div className="grid grid-cols-2 gap-3">
                        {item.product_name && <StandardModal.Field label="Produto" value={item.product_name} />}
                        {item.ean && <StandardModal.Field label="EAN" value={item.ean} mono />}
                        {item.expected_quantity != null && <StandardModal.Field label="Qtd. Esperada" value={item.expected_quantity} />}
                        {item.counted_quantity != null && <StandardModal.Field label="Qtd. Contada" value={item.counted_quantity} />}
                        {item.difference != null && (
                            <div>
                                <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Diferença</p>
                                <p className={`text-sm mt-0.5 font-medium ${item.difference > 0 ? 'text-green-600' : item.difference < 0 ? 'text-red-600' : 'text-gray-900'}`}>
                                    {item.difference > 0 ? '+' : ''}{item.difference}
                                </p>
                            </div>
                        )}
                    </div>
                </StandardModal.Section>
            )}

            <div>
                <InputLabel value="Justificativa *" />
                <textarea value={justificationText} onChange={(e) => setJustificationText(e.target.value)} rows={4}
                    placeholder="Descreva o motivo da divergência..."
                    className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                <InputError message={errors.justification_text} className="mt-1" />
            </div>

            <div>
                <InputLabel value="Quantidade Encontrada (opcional)" />
                <TextInput type="number" min={0} className="mt-1 w-full" value={foundQuantity}
                    onChange={(e) => setFoundQuantity(e.target.value)} placeholder="Quantidade real localizada" />
                <p className="mt-1 text-xs text-gray-500">Se informada, a quantidade contada será atualizada.</p>
                <InputError message={errors.found_quantity} className="mt-1" />
            </div>
        </StandardModal>
    );
}
