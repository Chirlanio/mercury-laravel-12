import { useState, useEffect } from 'react';
import axios from 'axios';

export default function StatisticsCards({ month, year, storeId }) {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showRanking, setShowRanking] = useState(false);

    useEffect(() => {
        setLoading(true);
        const params = { month, year };
        if (storeId) params.store_id = storeId;

        axios.get('/store-goals/statistics', { params })
            .then(res => setStats(res.data))
            .catch(() => setStats(null))
            .finally(() => setLoading(false));
    }, [month, year, storeId]);

    const formatCurrency = (value) => {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
    };

    if (loading) {
        return (
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                {[...Array(4)].map((_, i) => (
                    <div key={i} className="bg-white rounded-lg shadow-sm border border-gray-200 p-4 animate-pulse">
                        <div className="h-3 bg-gray-200 rounded w-20 mb-3"></div>
                        <div className="h-6 bg-gray-200 rounded w-28"></div>
                    </div>
                ))}
            </div>
        );
    }

    if (!stats) return null;

    const cards = [
        {
            label: 'Meta Total',
            value: formatCurrency(stats.total_goal_amount),
            sub: stats.goal_variation !== null ? (
                <span className={stats.goal_variation >= 0 ? 'text-emerald-600' : 'text-red-600'}>
                    {stats.goal_variation >= 0 ? '▲' : '▼'} {Math.abs(stats.goal_variation)}% vs mês anterior
                </span>
            ) : null,
            color: 'text-indigo-600',
        },
        {
            label: 'Vendas Realizadas',
            value: formatCurrency(stats.total_sales),
            color: 'text-emerald-600',
        },
        {
            label: 'Atingimento Geral',
            value: `${stats.achievement_pct}%`,
            sub: `${stats.stores_above_goal} lojas atingiram a meta`,
            color: stats.achievement_pct >= 100 ? 'text-emerald-600' : stats.achievement_pct >= 80 ? 'text-amber-600' : 'text-red-600',
        },
        {
            label: 'Cobertura',
            value: `${stats.stores_with_goals}/${stats.active_stores}`,
            sub: `${stats.coverage_pct}% das lojas com meta`,
            color: 'text-blue-600',
        },
    ];

    return (
        <div className="mb-6">
            {/* Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                {cards.map((card, i) => (
                    <div key={i} className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">{card.label}</p>
                        <p className={`mt-1 text-xl font-bold ${card.color}`}>{card.value}</p>
                        {card.sub && <p className="text-xs text-gray-500 mt-0.5">{card.sub}</p>}
                    </div>
                ))}
            </div>

            {/* Store Ranking (collapsible) */}
            {stats.store_ranking && stats.store_ranking.length > 0 && (
                <div className="bg-white rounded-lg shadow-sm border border-gray-200">
                    <button
                        onClick={() => setShowRanking(!showRanking)}
                        className="w-full px-4 py-3 flex items-center justify-between text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        <span>Ranking por Loja ({stats.store_ranking.length} lojas)</span>
                        <svg className={`w-4 h-4 text-gray-400 transition-transform ${showRanking ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    {showRanking && (
                        <div className="border-t overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Loja</th>
                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Meta</th>
                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Vendas</th>
                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Atingimento</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Progresso</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {stats.store_ranking.map((s, i) => (
                                        <tr key={s.store_id} className="hover:bg-gray-50">
                                            <td className="px-4 py-2 text-sm text-gray-400">{i + 1}</td>
                                            <td className="px-4 py-2 text-sm font-medium text-gray-900">{s.store_name}</td>
                                            <td className="px-4 py-2 text-sm text-gray-600 text-right">{formatCurrency(s.goal_amount)}</td>
                                            <td className="px-4 py-2 text-sm font-medium text-gray-900 text-right">{formatCurrency(s.sales)}</td>
                                            <td className="px-4 py-2 text-right">
                                                <span className={`text-sm font-bold ${s.achievement_pct >= 100 ? 'text-emerald-600' : s.achievement_pct >= 80 ? 'text-amber-600' : 'text-red-600'}`}>
                                                    {s.achievement_pct}%
                                                </span>
                                            </td>
                                            <td className="px-4 py-2">
                                                <div className="w-32 bg-gray-200 rounded-full h-2">
                                                    <div
                                                        className={`h-2 rounded-full ${s.achievement_pct >= 100 ? 'bg-emerald-500' : s.achievement_pct >= 80 ? 'bg-amber-500' : 'bg-red-500'}`}
                                                        style={{ width: `${Math.min(100, s.achievement_pct)}%` }}
                                                    ></div>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
