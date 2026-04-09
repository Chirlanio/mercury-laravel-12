import { useState, useEffect, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { XMarkIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

export default function EditModal({ isOpen, onClose, onSuccess, transfer, stores = [], typeOptions = {} }) {
    const [form, setForm] = useState({
        destination_store_id: '',
        invoice_number: '',
        volumes_qty: '',
        products_qty: '',
        transfer_type: 'transfer',
        observations: '',
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

    // Validação reativa: lojas iguais
    const sameStoreError = useMemo(() => {
        if (!transfer) return null;
        if (form.destination_store_id && String(form.destination_store_id) === String(transfer.origin_store_id)) {
            return 'A loja de destino não pode ser a mesma da origem.';
        }
        return null;
    }, [form.destination_store_id, transfer]);

    if (!isOpen || !transfer) return null;

    const originStoreId = transfer.origin_store_id;
    const originStoreName = transfer.origin_store?.name || `Loja #${originStoreId}`;

    const setField = (field, value) => {
        setForm(prev => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors(prev => {
                const next = { ...prev };
                delete next[field];
                return next;
            });
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (sameStoreError) return;

        setProcessing(true);
        router.put(route('transfers.update', transfer.id), {
            ...form,
            origin_store_id: originStoreId,
        }, {
            onSuccess: () => {
                setProcessing(false);
                onSuccess();
            },
            onError: (errs) => {
                setProcessing(false);
                setErrors(errs);
            },
        });
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex min-h-full items-start justify-center p-4 pt-10 sm:pt-16">
                <div className="fixed inset-0 bg-gray-500/75 transition-opacity" onClick={onClose} />
                <div className="relative w-full max-w-lg bg-white rounded-xl shadow-2xl">
                    {/* Header */}
                    <div className="flex items-center justify-between px-6 py-4 border-b bg-indigo-600 rounded-t-xl">
                        <h3 className="text-lg font-semibold text-white">
                            Editar Transferência #{transfer.id}
                        </h3>
                        <button onClick={onClose} className="text-white/70 hover:text-white">
                            <XMarkIcon className="h-6 w-6" />
                        </button>
                    </div>

                    {/* Form */}
                    <form onSubmit={handleSubmit} className="p-6 space-y-4">
                        {/* Origem (read-only) */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Loja Origem</label>
                            <div className="mt-1 px-3 py-2 bg-gray-100 rounded-md text-sm text-gray-700 border border-gray-200">
                                {originStoreName}
                            </div>
                        </div>

                        {/* Destino */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Loja Destino *</label>
                            <select
                                value={form.destination_store_id}
                                onChange={(e) => setField('destination_store_id', e.target.value)}
                                className={`mt-1 w-full rounded-md shadow-sm sm:text-sm ${sameStoreError ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'}`}
                                required
                            >
                                <option value="">Selecione</option>
                                {stores.map((s) => (
                                    <option key={s.id} value={s.id}>{s.code} - {s.name}</option>
                                ))}
                            </select>
                            {errors.destination_store_id && <p className="mt-1 text-sm text-red-600">{errors.destination_store_id}</p>}
                        </div>

                        {/* Feedback visual: lojas iguais */}
                        {sameStoreError && (
                            <div className="p-2.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 flex items-center gap-2">
                                <ExclamationTriangleIcon className="h-4 w-4 text-red-500 shrink-0" />
                                {sameStoreError}
                            </div>
                        )}

                        {/* Tipo */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Tipo *</label>
                            <select
                                value={form.transfer_type}
                                onChange={(e) => setField('transfer_type', e.target.value)}
                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                {Object.entries(typeOptions).map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                            </select>
                            {errors.transfer_type && <p className="mt-1 text-sm text-red-600">{errors.transfer_type}</p>}
                        </div>

                        {/* NF, Volumes, Produtos */}
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Nº NF</label>
                                <input
                                    type="text"
                                    value={form.invoice_number}
                                    onChange={(e) => setField('invoice_number', e.target.value)}
                                    className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                                {errors.invoice_number && <p className="mt-1 text-sm text-red-600">{errors.invoice_number}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Volumes</label>
                                <input
                                    type="number"
                                    min="0"
                                    value={form.volumes_qty}
                                    onChange={(e) => setField('volumes_qty', e.target.value)}
                                    className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                                {errors.volumes_qty && <p className="mt-1 text-sm text-red-600">{errors.volumes_qty}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Produtos</label>
                                <input
                                    type="number"
                                    min="0"
                                    value={form.products_qty}
                                    onChange={(e) => setField('products_qty', e.target.value)}
                                    className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                                {errors.products_qty && <p className="mt-1 text-sm text-red-600">{errors.products_qty}</p>}
                            </div>
                        </div>

                        {/* Observações */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea
                                value={form.observations}
                                onChange={(e) => setField('observations', e.target.value)}
                                rows={3}
                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                            {errors.observations && <p className="mt-1 text-sm text-red-600">{errors.observations}</p>}
                        </div>

                        {/* Buttons */}
                        <div className="flex justify-end space-x-3 pt-4 border-t">
                            <button
                                type="button"
                                onClick={onClose}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={processing || !!sameStoreError}
                                className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {processing ? 'Salvando...' : 'Salvar Alterações'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
