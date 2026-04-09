import { useState, useEffect } from 'react';
import {
    ArrowsRightLeftIcon, CubeIcon, ArchiveBoxIcon, CalculatorIcon,
} from '@heroicons/react/24/outline';

export default function StatisticsCards({ filters = {} }) {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);
        const params = new URLSearchParams();
        if (filters.store_id) params.append('store_id', filters.store_id);
        if (filters.status) params.append('status', filters.status);
        if (filters.transfer_type) params.append('transfer_type', filters.transfer_type);

        const url = route('transfers.statistics') + (params.toString() ? `?${params}` : '');

        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then(data => { setStats(data); setLoading(false); })
            .catch(() => setLoading(false));
    }, [filters.store_id, filters.status, filters.transfer_type]);

    if (loading) {
        return (
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                {[...Array(4)].map((_, i) => (
                    <div key={i} className="bg-white rounded-lg shadow p-4 animate-pulse">
                        <div className="h-3 bg-gray-200 rounded w-20 mb-3" />
                        <div className="h-8 bg-gray-200 rounded w-16 mb-2" />
                        <div className="h-2 bg-gray-100 rounded w-28" />
                    </div>
                ))}
            </div>
        );
    }

    if (!stats) return null;

    const cards = [
        {
            label: 'Total',
            value: stats.total_transfers,
            subtitle: `${stats.pending} pendentes, ${stats.in_transit} em rota`,
            icon: ArrowsRightLeftIcon,
            color: 'text-indigo-600',
            bg: 'bg-indigo-50',
        },
        {
            label: 'Volumes',
            value: stats.total_volumes,
            subtitle: 'Total movimentado',
            icon: ArchiveBoxIcon,
            color: 'text-blue-600',
            bg: 'bg-blue-50',
        },
        {
            label: 'Produtos',
            value: stats.total_products,
            subtitle: 'Total de itens',
            icon: CubeIcon,
            color: 'text-emerald-600',
            bg: 'bg-emerald-50',
        },
        {
            label: 'Média',
            value: stats.avg_products,
            subtitle: 'Produtos por transferência',
            icon: CalculatorIcon,
            color: 'text-amber-600',
            bg: 'bg-amber-50',
        },
    ];

    return (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            {cards.map((card) => (
                <div key={card.label} className="bg-white rounded-lg shadow p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">{card.label}</p>
                            <p className={`text-2xl font-bold mt-1 ${card.color}`}>
                                {typeof card.value === 'number' ? card.value.toLocaleString('pt-BR') : card.value}
                            </p>
                            <p className="text-xs text-gray-400 mt-1">{card.subtitle}</p>
                        </div>
                        <div className={`${card.bg} p-2.5 rounded-lg`}>
                            <card.icon className={`h-6 w-6 ${card.color}`} />
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}
