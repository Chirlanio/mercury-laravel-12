import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

const MODULE_COLORS = {
    Remanejo: 'bg-blue-100 text-blue-800',
    Ajuste: 'bg-orange-100 text-orange-800',
    Transferencia: 'bg-purple-100 text-purple-800',
    Devolucao: 'bg-red-100 text-red-800',
};

export default function PendencyModal({ show, onClose, auditId }) {
    const [pendencies, setPendencies] = useState({});
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    useEffect(() => {
        if (!show || !auditId) {
            setPendencies({});
            return;
        }

        setLoading(true);
        setError('');

        fetch(route('stock-audits.pendencies', auditId), {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        })
            .then((res) => {
                if (!res.ok) throw new Error('Erro ao carregar pendencias.');
                return res.json();
            })
            .then((json) => {
                // Group by module
                const grouped = {};
                const items = json.data || json.pendencies || json || [];

                if (Array.isArray(items)) {
                    items.forEach((item) => {
                        const mod = item.module || 'Outros';
                        if (!grouped[mod]) grouped[mod] = [];
                        grouped[mod].push(item);
                    });
                } else if (typeof items === 'object') {
                    // Already grouped
                    Object.assign(grouped, items);
                }

                setPendencies(grouped);
            })
            .catch((err) => setError(err.message))
            .finally(() => setLoading(false));
    }, [show, auditId]);

    const handleClose = () => {
        setPendencies({});
        setError('');
        onClose();
    };

    const moduleKeys = Object.keys(pendencies);
    const totalPendencies = moduleKeys.reduce(
        (acc, key) => acc + (Array.isArray(pendencies[key]) ? pendencies[key].length : 0),
        0
    );

    return (
        <Modal show={show} onClose={handleClose} maxWidth="2xl">
            <div className="flex flex-col max-h-[80vh]">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 bg-indigo-600 text-white flex-shrink-0 rounded-t-lg">
                    <div className="flex items-center gap-3">
                        <h2 className="text-lg font-semibold">Pendencias da Auditoria</h2>
                        {!loading && totalPendencies > 0 && (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white/20 text-white">
                                {totalPendencies}
                            </span>
                        )}
                    </div>
                    <button onClick={handleClose} className="text-white/80 hover:text-white transition">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 overflow-y-auto p-6">
                    {loading && (
                        <div className="flex items-center justify-center py-16">
                            <div className="animate-spin rounded-full h-8 w-8 border-2 border-indigo-600 border-t-transparent"></div>
                            <span className="ml-3 text-gray-500">Carregando pendencias...</span>
                        </div>
                    )}

                    {error && (
                        <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                            {error}
                        </div>
                    )}

                    {!loading && !error && moduleKeys.length === 0 && (
                        <div className="text-center py-12">
                            <svg className="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p className="mt-3 text-sm text-gray-500">Nenhuma pendencia encontrada.</p>
                            <p className="text-xs text-gray-400 mt-1">Todas as pendencias foram resolvidas.</p>
                        </div>
                    )}

                    {!loading && !error && moduleKeys.length > 0 && (
                        <div className="space-y-6">
                            {moduleKeys.map((moduleName) => (
                                <section key={moduleName}>
                                    <div className="flex items-center gap-2 mb-3">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            MODULE_COLORS[moduleName] || 'bg-gray-100 text-gray-800'
                                        }`}>
                                            {moduleName}
                                        </span>
                                        <span className="text-xs text-gray-400">
                                            ({pendencies[moduleName].length} pendencia{pendencies[moduleName].length !== 1 ? 's' : ''})
                                        </span>
                                    </div>

                                    <div className="space-y-2">
                                        {pendencies[moduleName].map((pendency, idx) => (
                                            <div
                                                key={pendency.id || idx}
                                                className="flex items-start justify-between p-3 bg-gray-50 rounded-lg border border-gray-100"
                                            >
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                                                            MODULE_COLORS[moduleName] || 'bg-gray-100 text-gray-800'
                                                        }`}>
                                                            {moduleName}
                                                        </span>
                                                        {pendency.status && (
                                                            <span className="text-xs text-gray-500">
                                                                {pendency.status}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-gray-900 mt-1">
                                                        {pendency.description || pendency.details || '-'}
                                                    </p>
                                                    {pendency.product_name && (
                                                        <p className="text-xs text-gray-500 mt-0.5">
                                                            Produto: {pendency.product_name}
                                                        </p>
                                                    )}
                                                    {pendency.quantity != null && (
                                                        <p className="text-xs text-gray-500">
                                                            Quantidade: {pendency.quantity}
                                                        </p>
                                                    )}
                                                </div>
                                                {pendency.count != null && (
                                                    <span className="text-sm font-bold text-gray-700 bg-gray-200 px-2 py-0.5 rounded ml-3">
                                                        {pendency.count}
                                                    </span>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            ))}
                        </div>
                    )}
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
