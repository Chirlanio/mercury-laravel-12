const VARIANT_CLASSES = {
    success: 'bg-green-100 text-green-800',
    warning: 'bg-yellow-100 text-yellow-800',
    danger: 'bg-red-100 text-red-800',
    info: 'bg-blue-100 text-blue-800',
    purple: 'bg-purple-100 text-purple-800',
    indigo: 'bg-indigo-100 text-indigo-800',
    teal: 'bg-teal-100 text-teal-800',
    orange: 'bg-orange-100 text-orange-800',
    amber: 'bg-amber-100 text-amber-800',
    pink: 'bg-pink-100 text-pink-800',
    cyan: 'bg-cyan-100 text-cyan-800',
    rose: 'bg-rose-100 text-rose-800',
    emerald: 'bg-emerald-100 text-emerald-800',
    gray: 'bg-gray-100 text-gray-800',
};

const SIZE_CLASSES = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-xs',
    lg: 'px-3 py-1 text-sm',
};

/**
 * Badge de status reutilizavel com variantes de cor.
 *
 * @param {string} children - Texto do badge
 * @param {string} variant - Variante de cor (success, warning, danger, info, purple, indigo, teal, orange, amber, pink, cyan, rose, emerald, gray)
 * @param {string} size - Tamanho (sm, md, lg)
 * @param {React.ElementType} icon - Componente de icone Heroicon (opcional)
 * @param {boolean} dot - Exibir dot indicator (opcional)
 * @param {string} className - Classes adicionais (opcional)
 *
 * @example
 * <StatusBadge variant="success">Ativo</StatusBadge>
 * <StatusBadge variant="danger" icon={XCircleIcon}>Inativo</StatusBadge>
 * <StatusBadge variant="warning" dot>Pendente</StatusBadge>
 *
 * // Com mapeamento de status
 * const STATUS_MAP = { ativo: 'success', inativo: 'danger', pendente: 'warning' };
 * <StatusBadge variant={STATUS_MAP[status]}>{status}</StatusBadge>
 */
export default function StatusBadge({
    children,
    variant = 'gray',
    size = 'md',
    icon: Icon,
    dot = false,
    className = '',
}) {
    const variantClass = VARIANT_CLASSES[variant] || VARIANT_CLASSES.gray;
    const sizeClass = SIZE_CLASSES[size] || SIZE_CLASSES.md;

    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full font-medium ${variantClass} ${sizeClass} ${className}`}
        >
            {dot && (
                <span className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
            )}
            {Icon && <Icon className="h-3 w-3" />}
            {children}
        </span>
    );
}

export { VARIANT_CLASSES };
