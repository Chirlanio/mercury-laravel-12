import { useState, useEffect } from 'react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { TrophyIcon } from '@heroicons/react/24/outline';

const TIER_VARIANT = { hiper: 'warning', super: 'info', goal: 'emerald', below: 'danger' };
const TIER_LABEL = { hiper: 'Hiper', super: 'Super', goal: 'Meta', below: 'Abaixo' };

export default function ConsultantRankingModal({ show, onClose, month, year, storeId }) {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (show) {
            setLoading(true);
            const params = { month, year };
            if (storeId) params.store_id = storeId;

            axios.get('/store-goals/achievement/consultants', { params })
                .then(res => setData(res.data.ranking || []))
                .catch(() => setData([]))
                .finally(() => setLoading(false));
        }
    }, [show, month, year, storeId]);

    const fmt = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Ranking de Consultores"
            headerColor="bg-indigo-700"
            headerIcon={<TrophyIcon className="h-5 w-5" />}
            loading={loading}
            maxWidth="7xl"
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            {data.length > 0 ? (
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Consultor</th>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Loja</th>
                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nível</th>
                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Meta</th>
                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Vendas</th>
                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">%</th>
                                <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Faixa</th>
                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Comissão %</th>
                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {data.map((c, i) => (
                                <tr key={c.employee_id} className="hover:bg-gray-50">
                                    <td className="px-3 py-2 text-sm text-gray-400">{i + 1}</td>
                                    <td className="px-3 py-2 text-sm font-medium text-gray-900">{c.employee_name}</td>
                                    <td className="px-3 py-2 text-sm text-gray-500">{c.store_name}</td>
                                    <td className="px-3 py-2 text-sm text-gray-500">{c.level}</td>
                                    <td className="px-3 py-2 text-sm text-gray-900 text-right">{fmt(c.individual_goal)}</td>
                                    <td className="px-3 py-2 text-sm font-medium text-gray-900 text-right">{fmt(c.sales)}</td>
                                    <td className="px-3 py-2 text-sm font-medium text-right">
                                        <span className={c.achievement_pct >= 100 ? 'text-emerald-600' : 'text-amber-600'}>
                                            {c.achievement_pct}%
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        <StatusBadge variant={TIER_VARIANT[c.tier] || 'danger'} size="sm">
                                            {TIER_LABEL[c.tier] || 'Abaixo'}
                                        </StatusBadge>
                                    </td>
                                    <td className="px-3 py-2 text-sm text-gray-600 text-right">{c.award_pct}%</td>
                                    <td className="px-3 py-2 text-sm font-medium text-emerald-600 text-right">{fmt(c.award_amount)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : !loading && (
                <p className="text-center py-8 text-gray-500 text-sm">Nenhum consultor com meta no período.</p>
            )}
        </StandardModal>
    );
}
