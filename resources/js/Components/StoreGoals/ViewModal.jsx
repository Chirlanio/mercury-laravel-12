import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import axios from 'axios';

const TIER_CONFIG = {
    hiper: { label: 'Hiper Meta', color: 'bg-yellow-100 text-yellow-800' },
    super: { label: 'Super Meta', color: 'bg-blue-100 text-blue-800' },
    goal: { label: 'Meta', color: 'bg-emerald-100 text-emerald-800' },
    below: { label: 'Abaixo', color: 'bg-red-100 text-red-800' },
};

export default function ViewModal({ isOpen, onClose, goalId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (isOpen && goalId) {
            setLoading(true);
            axios.get(`/store-goals/${goalId}`)
                .then(res => setData(res.data))
                .catch(() => setData(null))
                .finally(() => setLoading(false));
        } else {
            setData(null);
        }
    }, [isOpen, goalId]);

    const fmt = (value) => {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
    };

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="4xl">
            <div className="p-6">
                {loading ? (
                    <div className="flex items-center justify-center py-12">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    </div>
                ) : data ? (
                    <>
                        {/* Header */}
                        <div className="flex justify-between items-start mb-6">
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">{data.store_name}</h2>
                                <p className="text-sm text-gray-500">{data.period_label}</p>
                            </div>
                            <div className="text-right">
                                <p className="text-xs text-gray-500">Atingimento</p>
                                <p className={`text-2xl font-bold ${data.achievement_pct >= 100 ? 'text-emerald-600' : 'text-amber-600'}`}>
                                    {data.achievement_pct}%
                                </p>
                            </div>
                        </div>

                        {/* Summary Cards */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                            <div className="bg-gray-50 rounded-lg p-3">
                                <p className="text-xs text-gray-500">Meta</p>
                                <p className="text-sm font-semibold text-gray-900">{fmt(data.goal_amount)}</p>
                            </div>
                            <div className="bg-gray-50 rounded-lg p-3">
                                <p className="text-xs text-gray-500">Super Meta</p>
                                <p className="text-sm font-semibold text-gray-900">{fmt(data.super_goal)}</p>
                            </div>
                            <div className="bg-gray-50 rounded-lg p-3">
                                <p className="text-xs text-gray-500">Vendas</p>
                                <p className="text-sm font-semibold text-emerald-600">{fmt(data.total_sales)}</p>
                            </div>
                            <div className="bg-gray-50 rounded-lg p-3">
                                <p className="text-xs text-gray-500">Dias Úteis</p>
                                <p className="text-sm font-semibold text-gray-900">{data.business_days}</p>
                            </div>
                        </div>

                        {/* Consultants Table */}
                        {data.consultants && data.consultants.length > 0 ? (
                            <div>
                                <h3 className="text-sm font-medium text-gray-900 mb-3">
                                    Metas Individuais ({data.consultants.length} consultores)
                                </h3>
                                <div className="overflow-x-auto border rounded-lg">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Consultor</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nível</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Meta</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Super</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Hiper</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Vendas</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">%</th>
                                                <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Faixa</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {data.consultants.map((c) => {
                                                const tier = TIER_CONFIG[c.tier] || TIER_CONFIG.below;
                                                return (
                                                    <tr key={c.id} className="hover:bg-gray-50">
                                                        <td className="px-3 py-2 text-sm text-gray-900">{c.employee_name}</td>
                                                        <td className="px-3 py-2 text-sm text-gray-500">{c.level_snapshot}</td>
                                                        <td className="px-3 py-2 text-sm text-gray-900 text-right">{fmt(c.individual_goal)}</td>
                                                        <td className="px-3 py-2 text-sm text-gray-500 text-right">{fmt(c.super_goal)}</td>
                                                        <td className="px-3 py-2 text-sm text-gray-500 text-right">{fmt(c.hiper_goal)}</td>
                                                        <td className="px-3 py-2 text-sm font-medium text-gray-900 text-right">{fmt(c.actual_sales)}</td>
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
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ) : (
                            <div className="text-center py-8 text-gray-500 bg-gray-50 rounded-lg">
                                <p className="text-sm">Nenhuma meta individual distribuída.</p>
                                <p className="text-xs mt-1">Execute a redistribuição para gerar as metas dos consultores.</p>
                            </div>
                        )}

                        {/* Footer info */}
                        <div className="mt-4 flex justify-between text-xs text-gray-400">
                            <span>Criado por {data.created_by || 'N/A'} em {data.created_at}</span>
                            {data.updated_by && <span>Atualizado por {data.updated_by} em {data.updated_at}</span>}
                        </div>
                    </>
                ) : (
                    <p className="text-center py-8 text-gray-500">Erro ao carregar dados.</p>
                )}

                <div className="mt-6 flex justify-end">
                    <Button variant="secondary" onClick={onClose}>Fechar</Button>
                </div>
            </div>
        </Modal>
    );
}
