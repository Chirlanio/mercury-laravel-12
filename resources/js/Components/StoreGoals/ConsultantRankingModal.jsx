import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import axios from 'axios';

const TIER_CONFIG = {
    hiper: { label: 'Hiper', color: 'bg-yellow-100 text-yellow-800' },
    super: { label: 'Super', color: 'bg-blue-100 text-blue-800' },
    goal: { label: 'Meta', color: 'bg-emerald-100 text-emerald-800' },
    below: { label: 'Abaixo', color: 'bg-red-100 text-red-800' },
};

export default function ConsultantRankingModal({ isOpen, onClose, month, year, storeId }) {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (isOpen) {
            setLoading(true);
            const params = { month, year };
            if (storeId) params.store_id = storeId;

            axios.get('/store-goals/achievement/consultants', { params })
                .then(res => setData(res.data.ranking || []))
                .catch(() => setData([]))
                .finally(() => setLoading(false));
        }
    }, [isOpen, month, year, storeId]);

    const fmt = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="5xl">
            <div className="p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Ranking de Consultores</h2>

                {loading ? (
                    <div className="flex items-center justify-center py-12">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    </div>
                ) : data.length > 0 ? (
                    <div className="overflow-x-auto border rounded-lg">
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
                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Comissão %</th>
                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {data.map((c, i) => {
                                    const tier = TIER_CONFIG[c.tier] || TIER_CONFIG.below;
                                    return (
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
                                                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${tier.color}`}>
                                                    {tier.label}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-sm text-gray-600 text-right">{c.award_pct}%</td>
                                            <td className="px-3 py-2 text-sm font-medium text-emerald-600 text-right">{fmt(c.award_amount)}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <p className="text-center py-8 text-gray-500 text-sm">Nenhum consultor com meta no período.</p>
                )}

                <div className="mt-6 flex justify-end">
                    <Button variant="secondary" onClick={onClose}>Fechar</Button>
                </div>
            </div>
        </Modal>
    );
}
