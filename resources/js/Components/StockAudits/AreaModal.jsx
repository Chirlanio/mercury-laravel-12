import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

export default function AreaModal({ show, onClose, audit, onSuccess }) {
    const [areas, setAreas] = useState([]);
    const [loading, setLoading] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const [name, setName] = useState('');
    const [sortOrder, setSortOrder] = useState(0);

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const fetchAreas = async () => {
        if (!audit) return;
        setLoading(true);

        try {
            const res = await fetch(route('stock-audits.areas', audit.id), {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });
            const json = await res.json();
            setAreas(json.areas || []);
        } catch {
            // Silently handle
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (show && audit) {
            fetchAreas();
        }
    }, [show, audit]);

    const resetForm = () => {
        setName('');
        setSortOrder(0);
        setErrors({});
    };

    const handleAdd = async (e) => {
        e.preventDefault();

        if (!name.trim()) {
            setErrors({ name: 'O nome da area e obrigatorio.' });
            return;
        }

        setProcessing(true);
        setErrors({});

        try {
            const res = await fetch(route('stock-audits.areas', audit.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    action: 'add',
                    name: name.trim(),
                    sort_order: parseInt(sortOrder) || 0,
                }),
            });

            const json = await res.json();

            if (!res.ok) {
                setErrors(json.errors || { general: json.message || 'Erro ao adicionar area.' });
                return;
            }

            resetForm();
            fetchAreas();
            onSuccess?.();
        } catch {
            setErrors({ general: 'Erro de conexao. Tente novamente.' });
        } finally {
            setProcessing(false);
        }
    };

    const handleRemove = async (areaId) => {
        try {
            const res = await fetch(route('stock-audits.areas', audit.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    action: 'remove',
                    area_id: areaId,
                }),
            });

            if (res.ok) {
                fetchAreas();
                onSuccess?.();
            }
        } catch {
            // Silently handle
        }
    };

    const handleClose = () => {
        resetForm();
        setAreas([]);
        onClose();
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="lg">
            <div className="flex flex-col max-h-[80vh]">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 bg-indigo-600 text-white flex-shrink-0 rounded-t-lg">
                    <h2 className="text-lg font-semibold">Areas da Auditoria</h2>
                    <button onClick={handleClose} className="text-white/80 hover:text-white transition">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 overflow-y-auto p-6 space-y-6">
                    {errors.general && (
                        <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                            {errors.general}
                        </div>
                    )}

                    {/* Current Areas */}
                    <section>
                        <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">
                            Areas Cadastradas
                        </h3>

                        {loading && (
                            <div className="flex items-center justify-center py-8">
                                <div className="animate-spin rounded-full h-6 w-6 border-2 border-indigo-600 border-t-transparent"></div>
                                <span className="ml-2 text-sm text-gray-500">Carregando...</span>
                            </div>
                        )}

                        {!loading && areas.length === 0 && (
                            <div className="text-center text-gray-500 py-6 bg-gray-50 rounded-lg">
                                Nenhuma area cadastrada.
                            </div>
                        )}

                        {!loading && areas.length > 0 && (
                            <div className="space-y-2">
                                {areas.map((area) => (
                                    <div
                                        key={area.id}
                                        className="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                                    >
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs text-gray-400 font-mono w-6 text-right">
                                                #{area.sort_order ?? 0}
                                            </span>
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">{area.name}</p>
                                                <p className="text-xs text-gray-500">
                                                    {area.items_count ?? 0} itens
                                                </p>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => handleRemove(area.id)}
                                            className="text-red-500 hover:text-red-700 transition p-1"
                                            title="Remover area"
                                        >
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    {/* Add Area Form */}
                    <section className="border-t border-gray-200 pt-5">
                        <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">
                            Adicionar Area
                        </h3>

                        <form onSubmit={handleAdd} className="space-y-4">
                            <div className="grid grid-cols-3 gap-3">
                                <div className="col-span-2">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Nome da Area <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={name}
                                        onChange={(e) => setName(e.target.value)}
                                        className={`w-full rounded-md border shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${
                                            errors.name ? 'border-red-300' : 'border-gray-300'
                                        }`}
                                        placeholder="Ex: Salao de Vendas, Estoque, Vitrine..."
                                    />
                                    {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Ordem
                                    </label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={sortOrder}
                                        onChange={(e) => setSortOrder(e.target.value)}
                                        className="w-full rounded-md border border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    />
                                    {errors.sort_order && <p className="mt-1 text-sm text-red-600">{errors.sort_order}</p>}
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" variant="primary" size="sm" disabled={processing} loading={processing}>
                                    {processing ? 'Adicionando...' : 'Adicionar Area'}
                                </Button>
                            </div>
                        </form>
                    </section>
                </div>

                {/* Footer */}
                <div className="flex justify-end px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                    <Button variant="secondary" onClick={handleClose}>
                        Fechar
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
