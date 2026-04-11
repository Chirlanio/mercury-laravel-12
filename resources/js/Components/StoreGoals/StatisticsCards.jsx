import { useState, useEffect } from 'react';
import axios from 'axios';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import {
    BanknotesIcon, CurrencyDollarIcon, ChartBarSquareIcon, BuildingStorefrontIcon,
    ChevronDownIcon,
} from '@heroicons/react/24/outline';

const formatCurrency = (value) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);

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

    const cards = stats ? [
        {
            label: 'Meta Total',
            value: stats.total_goal_amount,
            format: 'currency',
            icon: BanknotesIcon,
            color: 'indigo',
            variation: stats.goal_variation,
        },
        {
            label: 'Vendas Realizadas',
            value: stats.total_sales,
            format: 'currency',
            icon: CurrencyDollarIcon,
            color: 'green',
        },
        {
            label: 'Atingimento Geral',
            value: stats.achievement_pct,
            format: 'percentage',
            icon: ChartBarSquareIcon,
            color: stats.achievement_pct >= 100 ? 'green' : stats.achievement_pct >= 80 ? 'yellow' : 'red',
            sub: `${stats.stores_above_goal} lojas atingiram a meta`,
        },
        {
            label: 'Cobertura',
            value: `${stats.stores_with_goals}/${stats.active_stores}`,
            icon: BuildingStorefrontIcon,
            color: 'blue',
            sub: `${stats.coverage_pct}% das lojas com meta`,
        },
    ] : [];

    return (
        <div className="mb-6">
            <StatisticsGrid cards={cards} loading={loading} cols={4} />

            {/* Store Ranking (collapsible) */}
            {stats?.store_ranking?.length > 0 && (
                <div className="bg-white rounded-lg shadow-sm border border-gray-200">
                    <button
                        onClick={() => setShowRanking(!showRanking)}
                        className="w-full px-4 py-3 flex items-center justify-between text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        <span>Ranking por Loja ({stats.store_ranking.length} lojas)</span>
                        <ChevronDownIcon className={`w-4 h-4 text-gray-400 transition-transform ${showRanking ? 'rotate-180' : ''}`} />
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
                                                    />
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
