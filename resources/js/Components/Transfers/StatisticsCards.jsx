import { useState, useEffect } from 'react';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
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

    const cards = stats ? [
        {
            label: 'Total',
            value: stats.total_transfers,
            format: 'number',
            icon: ArrowsRightLeftIcon,
            color: 'indigo',
            sub: `${stats.pending} pendentes, ${stats.in_transit} em rota`,
        },
        {
            label: 'Volumes',
            value: stats.total_volumes,
            format: 'number',
            icon: ArchiveBoxIcon,
            color: 'blue',
            sub: 'Total movimentado',
        },
        {
            label: 'Produtos',
            value: stats.total_products,
            format: 'number',
            icon: CubeIcon,
            color: 'green',
            sub: 'Total de itens',
        },
        {
            label: 'Média',
            value: stats.avg_products,
            format: 'number',
            icon: CalculatorIcon,
            color: 'yellow',
            sub: 'Produtos por transferência',
        },
    ] : [];

    return <StatisticsGrid cards={cards} loading={loading} cols={4} />;
}
