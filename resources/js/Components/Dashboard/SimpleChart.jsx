export default function SimpleChart({
    data = [],
    title,
    type = 'line',
    color = 'blue',
    height = 200,
    format = 'number'
}) {
    const getValue = (item) =>
        typeof item === 'object' ? (item.value || item.users || item.activities || item.count || 0) : (item || 0);

    const getLabel = (item, index) =>
        typeof item === 'object' ? (item.label || item.date || item.name || `${index + 1}`) : `${index + 1}`;

    const formatValue = (val) => {
        if (format === 'currency') {
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 }).format(val);
        }
        return new Intl.NumberFormat('pt-BR').format(val);
    };

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

    const values = data.map(getValue);
    const maxValue = Math.max(...values) || 1;
    const total = values.reduce((a, b) => a + b, 0);

    const getBarHeight = (item) => {
        const val = getValue(item);
        return maxValue > 0 ? Math.max((val / maxValue) * 100, val > 0 ? 4 : 1) : 1;
    };

    const colorClasses = {
        blue: { bar: 'bg-blue-300', barMax: 'bg-blue-600', hover: 'hover:bg-blue-400', hoverMax: 'hover:bg-blue-700', text: 'text-blue-600' },
        green: { bar: 'bg-green-300', barMax: 'bg-green-600', hover: 'hover:bg-green-400', hoverMax: 'hover:bg-green-700', text: 'text-green-600' },
        yellow: { bar: 'bg-yellow-300', barMax: 'bg-yellow-600', hover: 'hover:bg-yellow-400', hoverMax: 'hover:bg-yellow-700', text: 'text-yellow-600' },
        red: { bar: 'bg-red-300', barMax: 'bg-red-600', hover: 'hover:bg-red-400', hoverMax: 'hover:bg-red-700', text: 'text-red-600' },
        purple: { bar: 'bg-purple-300', barMax: 'bg-purple-600', hover: 'hover:bg-purple-400', hoverMax: 'hover:bg-purple-700', text: 'text-purple-600' },
        indigo: { bar: 'bg-indigo-300', barMax: 'bg-indigo-600', hover: 'hover:bg-indigo-400', hoverMax: 'hover:bg-indigo-700', text: 'text-indigo-600' },
    };

    const colors = colorClasses[color] || colorClasses.blue;
    const maxIndex = values.indexOf(Math.max(...values));

    return (
        <div className="bg-white p-6 rounded-lg shadow">
            <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-medium text-gray-900">{title}</h3>
                {format === 'currency' && total > 0 && (
                    <span className={`text-sm font-semibold ${colors.text}`}>
                        Total: {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(total)}
                    </span>
                )}
            </div>

            {type === 'bar' ? (
                <div>
                    <div className="flex items-end justify-between gap-1 sm:gap-2" style={{ height: `${height}px` }}>
                        {data.map((item, index) => {
                            const value = getValue(item);
                            const label = getLabel(item, index);
                            const isMax = index === maxIndex && value > 0;

                            return (
                                <div key={index} className="flex flex-col items-center flex-1 min-w-0 h-full justify-end group">
                                    <div className={`text-xs font-medium mb-1 opacity-0 group-hover:opacity-100 transition-opacity truncate max-w-full text-center ${isMax ? colors.text : 'text-gray-600'}`}>
                                        {formatValue(value)}
                                    </div>
                                    <div
                                        className={`w-full max-w-10 rounded-t transition-all cursor-default ${isMax ? `${colors.barMax} ${colors.hoverMax}` : `${colors.bar} ${colors.hover}`}`}
                                        style={{ height: `${getBarHeight(item)}%`, minHeight: '2px' }}
                                        title={`${label}: ${formatValue(value)}`}
                                    />
                                </div>
                            );
                        })}
                    </div>
                    <div className="grid mt-2 border-t pt-2" style={{ gridTemplateColumns: `repeat(${data.length}, 1fr)` }}>
                        {data.map((item, index) => {
                            const isMax = index === maxIndex && getValue(item) > 0;
                            return (
                                <div key={index} className="text-center overflow-visible px-0.5">
                                    <div className="text-xs text-gray-500">{getLabel(item, index)}</div>
                                    <div className={`text-[10px] leading-tight font-semibold ${isMax ? colors.text : 'text-gray-700'}`}>{formatValue(getValue(item))}</div>
                                </div>
                            );
                        })}
                    </div>
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
                                    const val = getValue(item);
                                    const x = data.length > 1 ? (index / (data.length - 1)) * 100 : 50;
                                    const y = 100 - (val / maxValue) * 100;
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
                                    const val = getValue(item);
                                    const x = data.length > 1 ? (index / (data.length - 1)) * 100 : 50;
                                    const y = maxValue > 0 ? 100 - (val / maxValue) * 100 : 100;
                                    return `${x},${y}`;
                                }).join(' ')
                            }
                        />
                        {data.map((item, index) => {
                            const val = getValue(item);
                            const x = (index / (data.length - 1)) * 100;
                            const y = 100 - (val / maxValue) * 100;
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
                        {data.map((item, index) => (
                            <span key={index} className="text-center">
                                {getLabel(item, index)}
                            </span>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}