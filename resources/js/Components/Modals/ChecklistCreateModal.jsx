import { useState } from 'react';
import { router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { ClipboardDocumentCheckIcon } from '@heroicons/react/24/outline';

export default function ChecklistCreateModal({ show, onClose, stores = [], onSuccess }) {
    const [storeId, setStoreId] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const handleSubmit = (e) => {
        if (!storeId) {
            setErrors({ store_id: 'Selecione uma loja.' });
            return;
        }

        setProcessing(true);
        setErrors({});

        router.post('/checklists', { store_id: storeId }, {
            onSuccess: () => {
                setStoreId('');
                setProcessing(false);
                onSuccess?.();
            },
            onError: (errs) => {
                setErrors(errs);
                setProcessing(false);
            },
        });
    };

    const handleClose = () => {
        setStoreId('');
        setErrors({});
        onClose();
    };

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Novo Checklist"
            subtitle="Criação de auditoria de qualidade"
            headerColor="bg-indigo-600"
            headerIcon={<ClipboardDocumentCheckIcon className="h-6 w-6" />}
            maxWidth="md"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={handleClose}
                    onSubmit="submit"
                    submitLabel="Criar Checklist"
                    processing={processing}
                />
            }
        >
            <StandardModal.Section title="Seleção de Loja">
                <div className="space-y-4">
                    <div>
                        <InputLabel htmlFor="store_id" value="Loja" required />
                        <select
                            id="store_id"
                            value={storeId}
                            onChange={(e) => setStoreId(e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="">Selecione uma loja...</option>
                            {stores.map((store) => (
                                <option key={store.id} value={store.id}>
                                    {store.code} - {store.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.store_id} className="mt-2" />
                    </div>

                    <div className="bg-blue-50 border-l-4 border-blue-400 p-4">
                        <div className="flex">
                            <div className="ml-3">
                                <p className="text-sm text-blue-700">
                                    Ao criar o checklist, todas as perguntas ativas serão carregadas automaticamente para preenchimento.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
