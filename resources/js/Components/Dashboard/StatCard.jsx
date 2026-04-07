import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/react/24/solid';

export default function StatCard({
    title,
    value,
    previousValue = null,
    icon: Icon,
    color = 'blue',
    format = 'number',
    loading = false
}) {
    const formatValue = (val) => {
        if (loading) return '...';
        if (val === null || val === undefined) return '-';

        switch (format) {
            case 'percentage':
                return `${val}%`;
            case 'currency':
                return new Intl.NumberFormat('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                }).format(val);
            case 'number':
            default:
                return new Intl.NumberFormat('pt-BR').format(val);
        }
    };

    const getTrend = () => {
        if (!previousValue || previousValue === value) return null;

        const isPositive = value > previousValue;
        const difference = Math.abs(value - previousValue);
        const percentage = previousValue > 0 ? (difference / previousValue) * 100 : 0;

        return {
            isPositive,
            percentage: Math.round(percentage),
            value: difference
        };
    };

    const trend = getTrend();

    const colorClasses = {
        blue: {
            bg: 'bg-blue-500',
            light: 'bg-blue-50',
            text: 'text-blue-600',
            icon: 'text-blue-500'
        },
        green: {
            bg: 'bg-green-500',
            light: 'bg-green-50',
            text: 'text-green-600',
            icon: 'text-green-500'
        },
        yellow: {
            bg: 'bg-yellow-500',
            light: 'bg-yellow-50',
            text: 'text-yellow-600',
            icon: 'text-yellow-500'
        },
        red: {
            bg: 'bg-red-500',
            light: 'bg-red-50',
            text: 'text-red-600',
            icon: 'text-red-500'
        },
        purple: {
            bg: 'bg-purple-500',
            light: 'bg-purple-50',
            text: 'text-purple-600',
            icon: 'text-purple-500'
        },
        indigo: {
            bg: 'bg-indigo-500',
            light: 'bg-indigo-50',
            text: 'text-indigo-600',
            icon: 'text-indigo-500'
        }
    };

    const colors = colorClasses[color] || colorClasses.blue;

    return (
        <div className="bg-white overflow-hidden shadow rounded-lg flex flex-col">
            <div className="p-5 flex-1">
                <div className="flex items-center">
                    <div className="flex-shrink-0">
                        <div className={`w-8 h-8 ${colors.light} rounded-md flex items-center justify-center`}>
                            {Icon && <Icon className={`h-5 w-5 ${colors.icon}`} />}
                        </div>
                    </div>
                    <div className="ml-5 min-w-0 flex-1">
                        <dl>
                            <dt className="text-sm font-medium text-gray-500 truncate">
                                {title}
                            </dt>
                            <dd>
                                <div className={`text-2xl font-semibold ${colors.text} ${loading ? 'animate-pulse' : ''} truncate`}>
                                    {formatValue(value)}
                                </div>
                                {trend && (
                                    <div className={`mt-1 flex items-center text-sm font-semibold ${
                                        trend.isPositive ? 'text-green-600' : 'text-red-600'
                                    }`}>
                                        {trend.isPositive ? (
                                            <ArrowUpIcon className="flex-shrink-0 h-4 w-4 text-green-500 mr-0.5" />
                                        ) : (
                                            <ArrowDownIcon className="flex-shrink-0 h-4 w-4 text-red-500 mr-0.5" />
                                        )}
                                        {trend.percentage}%
                                    </div>
                                )}
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div className="bg-gray-50 px-5 py-3">
                <div className="text-sm text-gray-500">
                    {trend ? (
                        <>
                            {trend.isPositive ? 'Aumento' : 'Diminuição'} de{' '}
                            <span className={`font-medium ${trend.isPositive ? 'text-green-600' : 'text-red-600'}`}>
                                {formatValue(trend.value)}
                            </span>
                            {' '}vs mês anterior
                        </>
                    ) : (
                        <span className="text-gray-400">Sem comparativo</span>
                    )}
                </div>
            </div>
        </div>
    );
}