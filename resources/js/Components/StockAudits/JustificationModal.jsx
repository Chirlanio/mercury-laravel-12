import { useState } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

export default function JustificationModal({ show, onClose, item, auditId, onSuccess }) {
    const [justificationText, setJustificationText] = useState('');
    const [foundQuantity, setFoundQuantity] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!justificationText.trim()) {
            setErrors({ justification_text: 'A justificativa e obrigatoria.' });
            return;
        }

        setProcessing(true);
        setErrors({});

        try {
            const body = {
                item_id: item?.id,
                justification_text: justificationText.trim(),
            };

            if (foundQuantity !== '') {
                body.found_quantity = parseInt(foundQuantity);
            }

            const res = await fetch(route('stock-audits.justify', auditId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(body),
            });

            const json = await res.json();

            if (!res.ok) {
                setErrors(json.errors || { general: json.message || 'Erro ao salvar justificativa.' });
                return;
            }

            setJustificationText('');
            setFoundQuantity('');
            onSuccess?.();
        } catch {
            setErrors({ general: 'Erro de conexao. Tente novamente.' });
        } finally {
            setProcessing(false);
        }
    };

    const handleClose = () => {
        setJustificationText('');
        setFoundQuantity('');
        setErrors({});
        onClose();
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="md">
            <div className="flex flex-col max-h-[80vh]">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 bg-indigo-600 text-white flex-shrink-0 rounded-t-lg">
                    <h2 className="text-lg font-semibold">Justificativa de Divergencia</h2>
                    <button onClick={handleClose} className="text-white/80 hover:text-white transition">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <form onSubmit={handleSubmit} className="flex flex-col flex-1 overflow-hidden">
                    <div className="flex-1 overflow-y-auto p-6 space-y-5">
                        {errors.general && (
                            <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                                {errors.general}
                            </div>
                        )}

                        {/* Item info */}
                        {item && (
                            <div className="bg-gray-50 rounded-lg p-4">
                                <h4 className="text-sm font-medium text-gray-900 mb-2">Dados do Item</h4>
                                <div className="grid grid-cols-2 gap-2 text-sm">
                                    {item.product_name && (
                                        <div>
                                            <span className="text-xs text-gray-500">Produto</span>
                                            <p className="font-medium text-gray-900">{item.product_name}</p>
                                        </div>
                                    )}
                                    {item.ean && (
                                        <div>
                                            <span className="text-xs text-gray-500">EAN</span>
                                            <p className="font-medium text-gray-900">{item.ean}</p>
                                        </div>
                                    )}
                                    {item.expected_quantity != null && (
                                        <div>
                                            <span className="text-xs text-gray-500">Qtd. Esperada</span>
                                            <p className="font-medium text-gray-900">{item.expected_quantity}</p>
                                        </div>
                                    )}
                                    {item.counted_quantity != null && (
                                        <div>
                                            <span className="text-xs text-gray-500">Qtd. Contada</span>
                                            <p className="font-medium text-gray-900">{item.counted_quantity}</p>
                                        </div>
                                    )}
                                    {item.difference != null && (
                                        <div>
                                            <span className="text-xs text-gray-500">Diferenca</span>
                                            <p className={`font-medium ${item.difference > 0 ? 'text-green-600' : item.difference < 0 ? 'text-red-600' : 'text-gray-900'}`}>
                                                {item.difference > 0 ? '+' : ''}{item.difference}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Justification text */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Justificativa <span className="text-red-500">*</span>
                            </label>
                            <textarea
                                value={justificationText}
                                onChange={(e) => setJustificationText(e.target.value)}
                                rows={4}
                                className={`w-full rounded-md border shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${
                                    errors.justification_text ? 'border-red-300' : 'border-gray-300'
                                }`}
                                placeholder="Descreva o motivo da divergencia..."
                            />
                            {errors.justification_text && (
                                <p className="mt-1 text-sm text-red-600">{errors.justification_text}</p>
                            )}
                        </div>

                        {/* Found quantity */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Quantidade Encontrada (opcional)
                            </label>
                            <input
                                type="number"
                                min={0}
                                value={foundQuantity}
                                onChange={(e) => setFoundQuantity(e.target.value)}
                                className={`w-full rounded-md border shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${
                                    errors.found_quantity ? 'border-red-300' : 'border-gray-300'
                                }`}
                                placeholder="Quantidade real localizada"
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Se informada, a quantidade contada sera atualizada.
                            </p>
                            {errors.found_quantity && (
                                <p className="mt-1 text-sm text-red-600">{errors.found_quantity}</p>
                            )}
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                        <Button type="button" variant="secondary" onClick={handleClose} disabled={processing}>
                            Cancelar
                        </Button>
                        <Button type="submit" variant="primary" disabled={processing} loading={processing}>
                            {processing ? 'Salvando...' : 'Salvar Justificativa'}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
