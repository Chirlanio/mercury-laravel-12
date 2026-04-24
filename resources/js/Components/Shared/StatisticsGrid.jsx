import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/react/24/solid';

const formatValue = (value, format) => {
    if (value === null || value === undefined) return '-';

    switch (format) {
        case 'currency':
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL',
            }).format(value);
        case 'percentage':
            return `${value}%`;
        case 'number':
            return new Intl.NumberFormat('pt-BR').format(value);
        default:
            return String(value);
    }
};

const ICON_COLORS = {
    green: 'text-green-500',
    blue: 'text-blue-500',
    indigo: 'text-indigo-500',
    yellow: 'text-yellow-500',
    red: 'text-red-500',
    purple: 'text-purple-500',
    orange: 'text-orange-500',
    teal: 'text-teal-500',
    gray: 'text-gray-500',
};

function VariationBadge({ value }) {
    if (value === 0 || value === null || value === undefined) {
        return <span className="text-xs text-gray-500">--</span>;
    }

    const isPositive = value > 0;
    return (
        <span
            className={`inline-flex items-center text-xs font-medium ${
                isPositive ? 'text-green-700' : 'text-red-700'
            }`}
        >
            {isPositive ? '+' : ''}
            {typeof value === 'number' ? value.toFixed(1) : value}%
            {isPositive ? (
                <ArrowUpIcon className="w-3 h-3 ml-0.5" />
            ) : (
                <ArrowDownIcon className="w-3 h-3 ml-0.5" />
            )}
        </span>
    );
}

function SkeletonCard() {
    return (
        <div className="bg-white shadow-sm rounded-lg p-4 animate-pulse">
            <div className="h-4 bg-gray-200 rounded w-2/3 mb-3" />
            <div className="h-7 bg-gray-200 rounded w-1/2 mb-2" />
            <div className="h-3 bg-gray-200 rounded w-1/3" />
        </div>
    );
}

/**
 * Grid unificado de cards de estatisticas com loading skeleton.
 *
 * @param {Array} cards - Configuracao dos cards
 * @param {string} cards[].label - Titulo do card
 * @param {number|string} cards[].value - Valor principal
 * @param {string} cards[].format - Formato do valor: 'currency', 'number', 'percentage', ou undefined para raw
 * @param {string} cards[].color - Cor do icone (green, blue, indigo, yellow, red, purple, orange, teal, gray)
 * @param {React.ElementType} cards[].icon - Componente Heroicon (opcional)
 * @param {number} cards[].variation - Percentual de variacao (opcional, exibe badge)
 * @param {string} cards[].sub - Texto complementar abaixo do valor (opcional)
 * @param {Function} cards[].onClick - Handler de clique no card (opcional, torna o card clicavel)
 * @param {boolean} cards[].active - Destaque visual quando selecionado (opcional)
 * @param {boolean} loading - Exibir skeleton de carregamento
 * @param {number} cols - Numero de colunas (default: usa cards.length, max 6)
 * @param {string} className - Classes adicionais no grid wrapper
 *
 * @example
 * <StatisticsGrid
 *   loading={loading}
 *   cards={[
 *     { label: 'Total Vendas', value: 150000, format: 'currency', color: 'green', variation: 12.5 },
 *     { label: 'Qtd Vendas', value: 340, format: 'number', color: 'blue' },
 *     { label: 'Ticket Medio', value: 441.17, format: 'currency', color: 'indigo', sub: '15 lojas ativas' },
 *     { label: 'Meta', value: 85, format: 'percentage', color: 'yellow' },
 *   ]}
 * />
 */

// Layout dos cards conforme número total. Em tablets (sm/md) preferimos
// menos colunas pra evitar squeeze de valores longos (monetários).
const GRID_CLASSES = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
    5: 'grid-cols-2 lg:grid-cols-5',
    6: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
};

export default function StatisticsGrid({
    cards = [],
    loading = false,
    cols,
    className = '',
}) {
    const colCount = cols || Math.min(cards.length, 6) || 4;
    const gridClass = GRID_CLASSES[colCount] || GRID_CLASSES[4];

    if (loading) {
        return (
            <div className={`grid ${gridClass} gap-4 mb-6 ${className}`}>
                {Array.from({ length: colCount }).map((_, i) => (
                    <SkeletonCard key={i} />
                ))}
            </div>
        );
    }

    if (!cards.length) return null;

    return (
        <div className={`grid ${gridClass} gap-4 mb-6 ${className}`}>
            {cards.map((card, index) => {
                const IconComponent = card.icon;
                const iconColor = ICON_COLORS[card.color] || ICON_COLORS.gray;
                const Tag = card.onClick ? 'button' : 'div';
                const formatted = formatValue(card.value, card.format);

                return (
                    <Tag
                        key={index}
                        onClick={card.onClick}
                        className={`bg-white shadow-sm rounded-lg p-4 text-left transition min-w-0 ${
                            card.onClick ? 'cursor-pointer hover:shadow-md' : ''
                        } ${card.active ? 'ring-2 ring-indigo-500 ring-offset-1' : ''}`}
                    >
                        <div className="flex items-center justify-between gap-2">
                            <div className="text-sm font-medium text-gray-500 truncate min-w-0">
                                {card.label}
                            </div>
                            {IconComponent && (
                                <IconComponent
                                    className={`w-5 h-5 shrink-0 ${iconColor}`}
                                />
                            )}
                        </div>
                        <div
                            className="text-lg sm:text-xl xl:text-2xl font-bold text-gray-900 mt-1 tabular-nums truncate"
                            title={typeof formatted === 'string' ? formatted : undefined}
                        >
                            {formatted}
                        </div>
                        <div className="mt-1 flex items-center gap-2 flex-wrap">
                            {card.sub && (
                                <span className="text-xs text-gray-500 truncate">
                                    {card.sub}
                                </span>
                            )}
                            {card.variation !== undefined &&
                                card.variation !== null && (
                                    <VariationBadge value={card.variation} />
                                )}
                        </div>
                    </Tag>
                );
            })}
        </div>
    );
}

export { VariationBadge, SkeletonCard, formatValue };
