import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';

export default function MergeModal({
    show = false,
    onClose,
    items = [],
    routeName = '',
    allItems = [],
}) {
    const [targetId, setTargetId] = useState(null);
    const [preview, setPreview] = useState(null);
    const [loading, setLoading] = useState(false);
    const [merging, setMerging] = useState(false);

    // Items to merge = all selected items
    const selectedItems = allItems.filter(item => items.includes(item.id));

    useEffect(() => {
        if (!show) return;
        if (selectedItems.length > 0) {
            setTargetId(selectedItems[0].id);
            setPreview(null);
        }
    }, [show]);

    useEffect(() => {
        if (!show || !targetId || selectedItems.length < 2 || !routeName) return;

        const sourceIds = items.filter(id => id !== targetId);
        if (sourceIds.length === 0) return;

        setLoading(true);
        axios.post(route(routeName + '.merge-preview'), {
            target_id: targetId,
            source_ids: sourceIds,
        })
            .then(res => setPreview(res.data))
            .catch(() => setPreview(null))
            .finally(() => setLoading(false));
    }, [targetId, show]);

    const handleMerge = () => {
        const sourceIds = items.filter(id => id !== targetId);
        setMerging(true);

        router.post(route(routeName + '.merge'), {
            target_id: targetId,
            source_ids: sourceIds,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setMerging(false);
                onClose(true);
            },
            onError: () => setMerging(false),
        });
    };

    const handleClose = () => {
        setPreview(null);
        setTargetId(null);
        onClose(false);
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="2xl" title="Mesclar Registros">
            <div className="space-y-6">
                {/* Explanation */}
                <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <div className="flex">
                        <svg className="w-5 h-5 text-amber-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <div className="text-sm text-amber-800">
                            <p className="font-medium">Como funciona a mesclagem:</p>
                            <ul className="mt-1 list-disc list-inside space-y-1">
                                <li>Selecione o registro <strong>destino</strong> (que será mantido)</li>
                                <li>Os produtos vinculados aos demais registros serão reatribuídos ao destino</li>
                                <li>Os registros de origem serão desativados</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {/* Target selection */}
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Registro destino (será mantido):
                    </label>
                    <div className="space-y-2 max-h-60 overflow-y-auto">
                        {selectedItems.map(item => (
                            <label
                                key={item.id}
                                className={`flex items-center p-3 rounded-lg border cursor-pointer transition-colors ${
                                    targetId === item.id
                                        ? 'border-indigo-500 bg-indigo-50'
                                        : 'border-gray-200 hover:bg-gray-50'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="target"
                                    value={item.id}
                                    checked={targetId === item.id}
                                    onChange={() => setTargetId(item.id)}
                                    className="text-indigo-600 focus:ring-indigo-500"
                                />
                                <div className="ml-3 flex-1">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium text-gray-900">{item.name}</span>
                                        <span className="text-xs text-gray-500">ID: {item.id}</span>
                                    </div>
                                    <span className="text-xs text-gray-500">
                                        CIGAM: {item.cigam_code}
                                    </span>
                                </div>
                                {targetId === item.id && (
                                    <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
                                        Destino
                                    </span>
                                )}
                                {targetId !== item.id && (
                                    <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                        Será desativado
                                    </span>
                                )}
                            </label>
                        ))}
                    </div>
                </div>

                {/* Preview */}
                {loading && (
                    <div className="flex items-center justify-center py-4">
                        <svg className="animate-spin h-5 w-5 text-indigo-600 mr-2" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <span className="text-sm text-gray-600">Calculando impacto...</span>
                    </div>
                )}

                {preview && !loading && (
                    <div className="bg-gray-50 rounded-lg p-4 space-y-3">
                        <h4 className="text-sm font-medium text-gray-700">Resumo da mesclagem:</h4>
                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <div className="bg-white rounded p-3 border">
                                <p className="text-gray-500">Registros a desativar</p>
                                <p className="text-2xl font-bold text-red-600">{preview.sources?.length || 0}</p>
                            </div>
                            <div className="bg-white rounded p-3 border">
                                <p className="text-gray-500">Produtos afetados</p>
                                <p className="text-2xl font-bold text-amber-600">{preview.affected_products || 0}</p>
                            </div>
                        </div>
                        {preview.sources && preview.sources.length > 0 && (
                            <div className="text-xs text-gray-500">
                                Códigos CIGAM que serão reatribuídos: {preview.sources.map(s => s.cigam_code).join(', ')}
                            </div>
                        )}
                    </div>
                )}

                {/* Actions */}
                <div className="flex justify-end space-x-3 pt-4 border-t">
                    <Button variant="secondary" onClick={handleClose} disabled={merging}>
                        Cancelar
                    </Button>
                    <Button
                        variant="primary"
                        onClick={handleMerge}
                        disabled={merging || loading || !targetId || selectedItems.length < 2}
                    >
                        {merging ? 'Mesclando...' : 'Confirmar Mesclagem'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
