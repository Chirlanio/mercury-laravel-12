import { useState, useEffect } from 'react';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { ExclamationCircleIcon, CheckCircleIcon } from '@heroicons/react/24/outline';

const MODULE_VARIANT = {
    Remanejo: 'info', Ajuste: 'orange', Transferencia: 'purple', Devolucao: 'danger',
};

export default function PendencyModal({ show, onClose, auditId }) {
    const [pendencies, setPendencies] = useState({});
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!show || !auditId) { setPendencies({}); return; }
        setLoading(true); setError('');
        fetch(route('stock-audits.pendencies', auditId), {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        })
            .then(r => { if (!r.ok) throw new Error('Erro ao carregar pendências.'); return r.json(); })
            .then(json => {
                const grouped = {};
                const items = json.data || json.pendencies || json || [];
                if (Array.isArray(items)) {
                    items.forEach(item => { const mod = item.module || 'Outros'; if (!grouped[mod]) grouped[mod] = []; grouped[mod].push(item); });
                } else if (typeof items === 'object') Object.assign(grouped, items);
                setPendencies(grouped);
            })
            .catch(err => setError(err.message))
            .finally(() => setLoading(false));
    }, [show, auditId]);

    const handleClose = () => { setPendencies({}); setError(''); onClose(); };
    const moduleKeys = Object.keys(pendencies);
    const totalPendencies = moduleKeys.reduce((acc, k) => acc + (Array.isArray(pendencies[k]) ? pendencies[k].length : 0), 0);

    const headerBadges = !loading && totalPendencies > 0
        ? [{ text: `${totalPendencies}`, className: 'bg-white/20 text-white' }] : [];

    return (
        <StandardModal show={show} onClose={handleClose} title="Pendências da Auditoria"
            headerColor="bg-indigo-600" headerIcon={<ExclamationCircleIcon className="h-5 w-5" />}
            headerBadges={headerBadges} loading={loading} errorMessage={error} maxWidth="2xl"
            footer={<StandardModal.Footer onCancel={handleClose} cancelLabel="Fechar" />}>

            {!loading && !error && moduleKeys.length === 0 && (
                <div className="text-center py-8">
                    <CheckCircleIcon className="mx-auto h-12 w-12 text-green-400" />
                    <p className="mt-3 text-sm text-gray-500">Nenhuma pendência encontrada.</p>
                    <p className="text-xs text-gray-400 mt-1">Todas as pendências foram resolvidas.</p>
                </div>
            )}

            {moduleKeys.map((moduleName) => (
                <StandardModal.Section key={moduleName} title={`${moduleName} (${pendencies[moduleName].length})`}>
                    <div className="space-y-2 -mx-4 -mb-4 px-4 pb-4">
                        {pendencies[moduleName].map((p, i) => (
                            <div key={p.id || i} className="flex items-start justify-between p-3 bg-white rounded-lg border border-gray-100">
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <StatusBadge variant={MODULE_VARIANT[moduleName] || 'gray'} size="sm">{moduleName}</StatusBadge>
                                        {p.status && <span className="text-xs text-gray-500">{p.status}</span>}
                                    </div>
                                    <p className="text-sm text-gray-900 mt-1">{p.description || p.details || '-'}</p>
                                    {p.product_name && <p className="text-xs text-gray-500 mt-0.5">Produto: {p.product_name}</p>}
                                    {p.quantity != null && <p className="text-xs text-gray-500">Quantidade: {p.quantity}</p>}
                                </div>
                                {p.count != null && (
                                    <span className="text-sm font-bold text-gray-700 bg-gray-200 px-2 py-0.5 rounded ml-3">{p.count}</span>
                                )}
                            </div>
                        ))}
                    </div>
                </StandardModal.Section>
            ))}
        </StandardModal>
    );
}
