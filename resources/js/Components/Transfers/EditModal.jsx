import { useState, useEffect, useMemo } from 'react';
import { router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { PencilSquareIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

export default function EditModal({ isOpen, onClose, onSuccess, transfer, stores = [], typeOptions = {} }) {
    const [form, setForm] = useState({
        destination_store_id: '', invoice_number: '', volumes_qty: '',
        products_qty: '', transfer_type: 'transfer', observations: '',
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (isOpen && transfer) {
            setForm({
                destination_store_id: transfer.destination_store_id || '',
                invoice_number: transfer.invoice_number || '',
                volumes_qty: transfer.volumes_qty ?? '',
                products_qty: transfer.products_qty ?? '',
                transfer_type: transfer.transfer_type || 'transfer',
                observations: transfer.observations || '',
            });
            setErrors({});
        }
    }, [isOpen, transfer]);

    const sameStoreError = useMemo(() => {
        if (!transfer) return null;
        if (form.destination_store_id && String(form.destination_store_id) === String(transfer.origin_store_id)) {
            return 'A loja de destino não pode ser a mesma da origem.';
        }
        return null;
    }, [form.destination_store_id, transfer]);

    if (!isOpen || !transfer) return null;

    const originStoreName = transfer.origin_store?.name || `Loja #${transfer.origin_store_id}`;

    const setField = (field, value) => {
        setForm(prev => ({ ...prev, [field]: value }));
        if (errors[field]) setErrors(prev => { const n = { ...prev }; delete n[field]; return n; });
    };

    const handleSubmit = () => {
        if (sameStoreError) return;
        setProcessing(true);
        router.put(route('transfers.update', transfer.id), {
            ...form, origin_store_id: transfer.origin_store_id,
        }, {
            onSuccess: () => { setProcessing(false); onSuccess(); },
            onError: (errs) => { setProcessing(false); setErrors(errs); },
        });
    };

    return (
        <StandardModal
            show={isOpen}
            onClose={onClose}
            title={`Editar Transferência #${transfer.id}`}
            headerColor="bg-yellow-600"
            headerIcon={<PencilSquareIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer onCancel={onClose} onSubmit="submit"
                    submitLabel="Salvar Alterações" submitColor="bg-yellow-600 hover:bg-yellow-700"
                    processing={processing} disabled={!!sameStoreError} />
            }
        >
            <FormSection title="Lojas" cols={2}>
                <div>
                    <InputLabel value="Loja Origem" />
                    <TextInput className="mt-1 w-full bg-gray-100 text-gray-500" value={originStoreName} disabled />
                </div>
                <div>
                    <InputLabel value="Loja Destino *" />
                    <select value={form.destination_store_id} onChange={(e) => setField('destination_store_id', e.target.value)}
                        className={`mt-1 w-full rounded-md shadow-sm sm:text-sm ${sameStoreError ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'}`}
                        required>
                        <option value="">Selecione</option>
                        {stores.map((s) => <option key={s.id} value={s.id}>{s.code} - {s.name}</option>)}
                    </select>
                    <InputError message={errors.destination_store_id} className="mt-1" />
                </div>
                {sameStoreError && (
                    <div className="col-span-full flex items-center gap-2 p-2.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                        <ExclamationTriangleIcon className="h-4 w-4 text-red-500 shrink-0" />
                        {sameStoreError}
                    </div>
                )}
            </FormSection>

            <FormSection title="Detalhes da Transferência" cols={3}>
                <div className="col-span-full">
                    <InputLabel value="Tipo *" />
                    <select value={form.transfer_type} onChange={(e) => setField('transfer_type', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        {Object.entries(typeOptions).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                    </select>
                    <InputError message={errors.transfer_type} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Nº Nota Fiscal" />
                    <TextInput className="mt-1 w-full" value={form.invoice_number}
                        onChange={(e) => setField('invoice_number', e.target.value)} />
                    <InputError message={errors.invoice_number} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Volumes" />
                    <TextInput type="number" min="0" className="mt-1 w-full" value={form.volumes_qty}
                        onChange={(e) => setField('volumes_qty', e.target.value)} />
                    <InputError message={errors.volumes_qty} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Produtos" />
                    <TextInput type="number" min="0" className="mt-1 w-full" value={form.products_qty}
                        onChange={(e) => setField('products_qty', e.target.value)} />
                    <InputError message={errors.products_qty} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Observações" cols={1}>
                <div>
                    <textarea value={form.observations} onChange={(e) => setField('observations', e.target.value)}
                        rows={3} className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                    <InputError message={errors.observations} className="mt-1" />
                </div>
            </FormSection>
        </StandardModal>
    );
}
