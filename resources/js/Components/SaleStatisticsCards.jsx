import { useState, useEffect } from 'react';
import axios from 'axios';

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
};

const VariationBadge = ({ value }) => {
    if (value === 0 || value === null || value === undefined) {
        return <span className="text-xs text-gray-500">--</span>;
    }

    const isPositive = value > 0;
    return (
        <span className={`inline-flex items-center text-xs font-medium ${
            isPositive ? 'text-green-700' : 'text-red-700'
        }`}>
            {isPositive ? '+' : ''}{value.toFixed(1)}%
            <svg className={`w-3 h-3 ml-0.5 ${isPositive ? '' : 'rotate-180'}`} fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clipRule="evenodd" />
            </svg>
        </span>
    );
};

const SkeletonCard = () => (
    <div className="bg-white shadow-sm rounded-lg p-4 animate-pulse">
        <div className="h-4 bg-gray-200 rounded w-2/3 mb-3"></div>
        <div className="h-7 bg-gray-200 rounded w-1/2 mb-2"></div>
        <div className="h-3 bg-gray-200 rounded w-1/3"></div>
    </div>
);

export default function SaleStatisticsCards({ month, year, storeId }) {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);
        const params = { month, year };
        if (storeId) params.store_id = storeId;

        axios.get('/sales/statistics', { params })
            .then(res => {
                setStats(res.data);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, [month, year, storeId]);

    if (loading) {
        return (
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <SkeletonCard /><SkeletonCard /><SkeletonCard /><SkeletonCard />
            </div>
        );
    }

    if (!stats) return null;

    return (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div className="bg-white shadow-sm rounded-lg p-4">
                <div className="flex items-center justify-between">
                    <div className="text-sm font-medium text-gray-500">Total Mês Atual</div>
                    <svg className="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div className="text-2xl font-bold text-gray-900 mt-1">
                    {formatCurrency(stats.current_month_total)}
                </div>
                <div className="mt-1 flex items-center gap-2">
                    <span className="text-xs text-gray-500">vs mês anterior</span>
                    <VariationBadge value={stats.variation} />
                </div>
            </div>

            <div className="bg-white shadow-sm rounded-lg p-4">
                <div className="flex items-center justify-between">
                    <div className="text-sm font-medium text-gray-500">Mesmo Mês Ano Anterior</div>
                    <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <div className="text-2xl font-bold text-gray-900 mt-1">
                    {formatCurrency(stats.same_month_last_year)}
                </div>
                <div className="mt-1 flex items-center gap-2">
                    <span className="text-xs text-gray-500">variação YoY</span>
                    <VariationBadge value={stats.yoy_variation} />
                </div>
            </div>

            <div className="bg-white shadow-sm rounded-lg p-4">
                <div className="flex items-center justify-between">
                    <div className="text-sm font-medium text-gray-500">Lojas / Consultores</div>
                    <svg className="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <div className="text-2xl font-bold text-gray-900 mt-1">
                    {stats.active_stores} / {stats.active_consultants}
                </div>
                <div className="mt-1">
                    <span className="text-xs text-gray-500">{stats.total_records} registros no mês</span>
                </div>
            </div>

            <div className="bg-white shadow-sm rounded-lg p-4">
                <div className="flex items-center justify-between">
                    <div className="text-sm font-medium text-gray-500">Média por Loja / Consultor</div>
                    <svg className="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <div className="text-lg font-bold text-gray-900 mt-1">
                    {formatCurrency(stats.avg_per_store)}
                </div>
                <div className="mt-1">
                    <span className="text-xs text-gray-500">Consultor: {formatCurrency(stats.avg_per_consultant)}</span>
                </div>
            </div>
        </div>
    );
}
