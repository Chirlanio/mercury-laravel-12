/**
 * Card skeleton para estados de carregamento.
 *
 * @param {number} lines - Numero de linhas skeleton (default: 3)
 * @param {boolean} hasHeader - Incluir header skeleton (default: false)
 * @param {string} className - Classes adicionais (opcional)
 *
 * @example
 * // Basico
 * <SkeletonCard />
 *
 * // Com header
 * <SkeletonCard hasHeader lines={4} />
 *
 * // Grid de skeletons
 * <div className="grid grid-cols-4 gap-4">
 *   {[...Array(4)].map((_, i) => <SkeletonCard key={i} />)}
 * </div>
 */
export default function SkeletonCard({
    lines = 3,
    hasHeader = false,
    className = '',
}) {
    const lineWidths = ['w-2/3', 'w-1/2', 'w-1/3', 'w-3/4', 'w-2/5'];

    return (
        <div className={`bg-white shadow-sm rounded-lg p-4 animate-pulse ${className}`}>
            {hasHeader && (
                <div className="h-5 bg-gray-200 rounded w-3/4 mb-4" />
            )}
            {Array.from({ length: lines }).map((_, i) => (
                <div
                    key={i}
                    className={`bg-gray-200 rounded ${lineWidths[i % lineWidths.length]} ${
                        i === 0 ? 'h-4 mb-3' : i === lines - 1 ? 'h-3' : 'h-7 mb-2'
                    }`}
                />
            ))}
        </div>
    );
}
