import { useState, useEffect } from 'react';
import axios from 'axios';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import {
    CurrencyDollarIcon,
    ArrowTrendingUpIcon,
    BuildingStorefrontIcon,
    ChartBarIcon,
} from '@heroicons/react/24/outline';

const formatCurrency = (value) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

export default function SaleStatisticsCards({ month, year, storeId }) {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);
        const params = { month, year };
        if (storeId) params.store_id = storeId;

        axios.get('/sales/statistics', { params })
            .then(res => { setStats(res.data); setLoading(false); })
            .catch(() => setLoading(false));
    }, [month, year, storeId]);

    const cards = stats ? [
        {
            label: 'Total Mês Atual',
            value: stats.current_month_total,
            format: 'currency',
            icon: CurrencyDollarIcon,
            color: 'green',
            variation: stats.variation,
        },
        {
            label: 'Mesmo Mês Ano Anterior',
            value: stats.same_month_last_year,
            format: 'currency',
            icon: ArrowTrendingUpIcon,
            color: 'blue',
            variation: stats.yoy_variation,
        },
        {
            label: 'Lojas / Consultores',
            value: `${stats.active_stores} / ${stats.active_consultants}`,
            icon: BuildingStorefrontIcon,
            color: 'indigo',
            sub: `${stats.total_records} registros no mês`,
        },
        {
            label: 'Média por Loja',
            value: stats.avg_per_store,
            format: 'currency',
            icon: ChartBarIcon,
            color: 'yellow',
            sub: `Consultor: ${formatCurrency(stats.avg_per_consultant)}`,
        },
    ] : [];

    return <StatisticsGrid cards={cards} loading={loading} cols={4} />;
}
