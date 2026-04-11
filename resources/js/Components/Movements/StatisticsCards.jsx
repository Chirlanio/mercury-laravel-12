import { useState, useEffect } from 'react';
import axios from 'axios';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import {
    CurrencyDollarIcon, ArrowTrendingUpIcon, ShoppingCartIcon,
    BuildingStorefrontIcon, ArrowsRightLeftIcon, ClockIcon,
} from '@heroicons/react/24/outline';

const fmt = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0);

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

    const cards = stats ? [
        {
            label: 'Vendas Líquidas',
            value: stats.today_net,
            format: 'currency',
            icon: CurrencyDollarIcon,
            color: 'green',
            variation: stats.variation_yesterday,
            sub: 'vs ontem',
        },
        {
            label: 'Vs Semana Passada',
            value: stats.today_net,
            format: 'currency',
            icon: ArrowTrendingUpIcon,
            color: 'blue',
            variation: stats.variation_week,
            sub: 'mesmo dia',
        },
        {
            label: 'Itens Vendidos',
            value: stats.items_sold,
            format: 'number',
            icon: ShoppingCartIcon,
            color: 'indigo',
        },
        {
            label: 'Lojas Ativas',
            value: stats.active_stores,
            format: 'number',
            icon: BuildingStorefrontIcon,
            color: 'purple',
        },
        {
            label: 'Movimentações',
            value: stats.total_movements,
            format: 'number',
            icon: ArrowsRightLeftIcon,
            color: 'orange',
        },
        {
            label: 'Última Sync',
            value: stats.last_sync ? new Date(stats.last_sync).toLocaleString('pt-BR') : 'Nunca',
            icon: ClockIcon,
            color: 'gray',
        },
    ] : [];

    return <StatisticsGrid cards={cards} loading={loading} cols={6} />;
}
