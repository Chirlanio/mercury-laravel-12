const SIZE_CLASSES = {
    sm: 'h-5 w-5 border-2',
    md: 'h-8 w-8 border-[3px]',
    lg: 'h-10 w-10 border-4',
    xl: 'h-16 w-16 border-4',
};

const COLOR_CLASSES = {
    indigo: 'border-indigo-600',
    blue: 'border-blue-600',
    green: 'border-green-600',
    red: 'border-red-600',
    gray: 'border-gray-600',
};

/**
 * Spinner de carregamento com tamanho e cor configuraveis.
 *
 * @param {string} size - Tamanho: 'sm', 'md', 'lg', 'xl' (default: 'lg')
 * @param {string} color - Cor: 'indigo', 'blue', 'green', 'red', 'gray' (default: 'indigo')
 * @param {string} label - Texto abaixo do spinner (opcional)
 * @param {boolean} fullPage - Centralizar na pagina inteira (opcional)
 * @param {string} className - Classes adicionais (opcional)
 *
 * @example
 * // Inline
 * <LoadingSpinner size="sm" />
 *
 * // Com mensagem
 * <LoadingSpinner label="Carregando dados..." />
 *
 * // Pagina inteira
 * <LoadingSpinner fullPage label="Carregando..." />
 */
export default function LoadingSpinner({
    size = 'lg',
    color = 'indigo',
    label,
    fullPage = false,
    className = '',
}) {
    const sizeClass = SIZE_CLASSES[size] || SIZE_CLASSES.lg;
    const colorClass = COLOR_CLASSES[color] || COLOR_CLASSES.indigo;

    const spinner = (
        <div className={`flex flex-col items-center justify-center gap-3 ${className}`}>
            <div
                className={`animate-spin rounded-full border-t-transparent ${sizeClass} ${colorClass}`}
            />
            {label && (
                <span className="text-sm text-gray-500">{label}</span>
            )}
        </div>
    );

    if (fullPage) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                {spinner}
            </div>
        );
    }

    return spinner;
}
