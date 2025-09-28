export default function SimpleChart({
    data = [],
    title,
    type = 'line',
    color = 'blue',
    height = 200
}) {
    if (!data || data.length === 0) {
        return (
            <div className="bg-white p-6 rounded-lg shadow">
                <h3 className="text-lg font-medium text-gray-900 mb-4">{title}</h3>
                <div className="flex items-center justify-center h-48 text-gray-400">
                    Nenhum dado disponível
                </div>
            </div>
        );
    }

    const maxValue = Math.max(...data.map(item =>
        typeof item === 'object' ? (item.value || item.users || item.activities || item.count || 0) : item
    ));

    const getBarHeight = (value) => {
        if (maxValue === 0) return 0;
        const val = typeof value === 'object' ? (value.value || value.users || value.activities || value.count || 0) : value;
        return Math.max((val / maxValue) * 100, 2); // Mínimo 2% para visualização
    };

    const colorClasses = {
        blue: 'bg-blue-500 hover:bg-blue-600',
        green: 'bg-green-500 hover:bg-green-600',
        yellow: 'bg-yellow-500 hover:bg-yellow-600',
        red: 'bg-red-500 hover:bg-red-600',
        purple: 'bg-purple-500 hover:bg-purple-600',
        indigo: 'bg-indigo-500 hover:bg-indigo-600'
    };

    const chartColor = colorClasses[color] || colorClasses.blue;

    return (
        <div className="bg-white p-6 rounded-lg shadow">
            <h3 className="text-lg font-medium text-gray-900 mb-4">{title}</h3>

            {type === 'bar' ? (
                <div className="flex items-end justify-between space-x-2" style={{ height: `${height}px` }}>
                    {data.map((item, index) => {
                        const value = typeof item === 'object' ? (item.value || item.users || item.activities || item.count || 0) : item;
                        const label = typeof item === 'object' ? (item.label || item.date || item.name || `Item ${index + 1}`) : `Item ${index + 1}`;

                        return (
                            <div key={index} className="flex flex-col items-center flex-1 min-w-0">
                                <div className="w-full flex justify-center mb-2">
                                    <div
                                        className={`w-full max-w-8 rounded-t transition-colors ${chartColor}`}
                                        style={{ height: `${getBarHeight(item)}%` }}
                                        title={`${label}: ${value}`}
                                    />
                                </div>
                                <div className="text-xs text-gray-500 text-center truncate w-full">
                                    {label}
                                </div>
                                <div className="text-xs font-medium text-gray-700">
                                    {value}
                                </div>
                            </div>
                        );
                    })}
                </div>
            ) : (
                // Line chart simplificado usando CSS
                <div className="relative" style={{ height: `${height}px` }}>
                    <svg className="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id={`gradient-${color}`} x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" style={{ stopColor: color === 'blue' ? '#3B82F6' : '#10B981', stopOpacity: 0.3 }} />
                                <stop offset="100%" style={{ stopColor: color === 'blue' ? '#3B82F6' : '#10B981', stopOpacity: 0 }} />
                            </linearGradient>
                        </defs>

                        {/* Área preenchida */}
                        <polygon
                            fill={`url(#gradient-${color})`}
                            points={
                                data.map((item, index) => {
                                    const value = typeof item === 'object' ? (item.value || item.users || item.activities || item.count || 0) : item;
                                    const x = data.length > 1 ? (index / (data.length - 1)) * 100 : 50;
                                    const y = 100 - (value / maxValue) * 100;
                                    return `${x},${y}`;
                                }).join(' ') + ` 100,100 0,100`
                            }
                        />

                        {/* Linha */}
                        <polyline
                            fill="none"
                            stroke={color === 'blue' ? '#3B82F6' : '#10B981'}
                            strokeWidth="2"
                            points={
                                data.map((item, index) => {
                                    const value = typeof item === 'object' ? (item.value || item.users || item.activities || item.count || 0) : item;
                                    const x = data.length > 1 ? (index / (data.length - 1)) * 100 : 50;
                                    const y = maxValue > 0 ? 100 - (value / maxValue) * 100 : 100;
                                    return `${x},${y}`;
                                }).join(' ')
                            }
                        />
                        {data.map((item, index) => {
                            const value = typeof item === 'object' ? (item.value || item.users || item.activities || item.count || 0) : item;
                            const x = (index / (data.length - 1)) * 100;
                            const y = 100 - (value / maxValue) * 100;
                            return (
                                <circle
                                    key={index}
                                    cx={x}
                                    cy={y}
                                    r="2"
                                    fill={color === 'blue' ? '#3B82F6' : '#10B981'}
                                    className="hover:r-3 transition-all"
                                />
                            );
                        })}
                    </svg>

                    {/* Labels */}
                    <div className="absolute bottom-0 left-0 right-0 flex justify-between text-xs text-gray-500 mt-2">
                        {data.map((item, index) => {
                            const label = typeof item === 'object' ? (item.label || item.date || item.name || `${index + 1}`) : `${index + 1}`;
                            return (
                                <span key={index} className="text-center">
                                    {label}
                                </span>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}