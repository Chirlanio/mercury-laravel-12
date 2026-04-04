import { Fragment, useState } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { router } from '@inertiajs/react';
import { XMarkIcon } from '@heroicons/react/24/outline';

export default function ChecklistCreateModal({ show, onClose, stores = [], onSuccess }) {
    const [storeId, setStoreId] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const handleSubmit = (e) => {
        e.preventDefault();

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
        <Transition appear show={show} as={Fragment}>
            <Dialog as="div" className="relative z-50" onClose={handleClose}>
                <Transition.Child
                    as={Fragment}
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-black bg-opacity-25" />
                </Transition.Child>

                <div className="fixed inset-0 overflow-y-auto">
                    <div className="flex min-h-full items-center justify-center p-4">
                        <Transition.Child
                            as={Fragment}
                            enter="ease-out duration-300"
                            enterFrom="opacity-0 scale-95"
                            enterTo="opacity-100 scale-100"
                            leave="ease-in duration-200"
                            leaveFrom="opacity-100 scale-100"
                            leaveTo="opacity-0 scale-95"
                        >
                            <Dialog.Panel className="w-full max-w-md transform overflow-hidden rounded-lg bg-white p-6 shadow-xl transition-all">
                                <div className="flex items-center justify-between mb-4">
                                    <Dialog.Title className="text-lg font-semibold text-gray-900">
                                        Novo Checklist
                                    </Dialog.Title>
                                    <button onClick={handleClose} className="text-gray-400 hover:text-gray-600">
                                        <XMarkIcon className="h-5 w-5" />
                                    </button>
                                </div>

                                <form onSubmit={handleSubmit}>
                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Loja <span className="text-red-500">*</span>
                                        </label>
                                        <select
                                            value={storeId}
                                            onChange={(e) => setStoreId(e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        >
                                            <option value="">Selecione uma loja...</option>
                                            {stores.map((store) => (
                                                <option key={store.id} value={store.id}>
                                                    {store.code} - {store.name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.store_id && (
                                            <p className="mt-1 text-sm text-red-600">{errors.store_id}</p>
                                        )}
                                    </div>

                                    <p className="text-sm text-gray-500 mb-4">
                                        Ao criar o checklist, todas as perguntas ativas serão carregadas automaticamente para preenchimento.
                                    </p>

                                    <div className="flex justify-end gap-3">
                                        <button
                                            type="button"
                                            onClick={handleClose}
                                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition"
                                        >
                                            Cancelar
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50 transition"
                                        >
                                            {processing ? 'Criando...' : 'Criar Checklist'}
                                        </button>
                                    </div>
                                </form>
                            </Dialog.Panel>
                        </Transition.Child>
                    </div>
                </div>
            </Dialog>
        </Transition>
    );
}
