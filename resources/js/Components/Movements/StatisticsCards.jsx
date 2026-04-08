import { useState, useEffect } from 'react';
import axios from 'axios';
import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/react/24/solid';

export default function StatisticsCards({ date }) {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);
        axios.get('/movements/statistics', { params: { date } })
            .then(res => setStats(res.data))
            .catch(() => setStats(null))
            .finally(() => setLoading(false));
    }, [date]);

    const fmt = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0);

    const cards = stats ? [
        { label: 'Vendas Líquidas', value: fmt(stats.today_net), variation: stats.variation_yesterday, subLabel: 'vs ontem' },
        { label: 'Vs Semana Passada', value: fmt(stats.today_net), variation: stats.variation_week, subLabel: 'mesmo dia' },
        { label: 'Itens Vendidos', value: new Intl.NumberFormat('pt-BR').format(stats.items_sold || 0) },
        { label: 'Lojas Ativas', value: stats.active_stores || 0 },
        { label: 'Movimentações', value: new Intl.NumberFormat('pt-BR').format(stats.total_movements || 0) },
        { label: 'Última Sync', value: stats.last_sync ? new Date(stats.last_sync).toLocaleString('pt-BR') : 'Nunca', small: true },
    ] : [];

    return (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            {loading ? (
                Array.from({ length: 6 }).map((_, i) => (
                    <div key={i} className="bg-white rounded-lg shadow p-4 animate-pulse">
                        <div className="h-3 bg-gray-200 rounded w-2/3 mb-2"></div>
                        <div className="h-6 bg-gray-200 rounded w-1/2"></div>
                    </div>
                ))
            ) : cards.map((card, i) => (
                <div key={i} className="bg-white rounded-lg shadow p-4">
                    <p className="text-xs text-gray-500 mb-1">{card.label}</p>
                    <p className={`font-bold ${card.small ? 'text-xs' : 'text-lg'} text-gray-900 truncate`}>{card.value}</p>
                    {card.variation !== undefined && card.variation !== null && (
                        <div className={`mt-1 flex items-center text-xs font-medium ${card.variation >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                            {card.variation >= 0 ? (
                                <ArrowUpIcon className="w-3 h-3 mr-0.5" />
                            ) : (
                                <ArrowDownIcon className="w-3 h-3 mr-0.5" />
                            )}
                            {Math.abs(card.variation)}% {card.subLabel}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}
