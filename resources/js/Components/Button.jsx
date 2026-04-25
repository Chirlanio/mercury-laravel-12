import { forwardRef } from 'react';

// Mapas exportados pra que componentes como PageHeader possam estilizar <a> e outros
// elementos com a mesma aparência de um Button sem aninhar <button> dentro de <a>.
export const BUTTON_VARIANTS = {
    primary: 'text-white bg-indigo-600 hover:bg-indigo-700 border-indigo-600 hover:border-indigo-700 focus:ring-indigo-500',
    secondary: 'text-white bg-gray-600 hover:bg-gray-700 border-gray-600 hover:border-gray-700 focus:ring-gray-500',
    success: 'text-white bg-green-600 hover:bg-green-700 border-green-600 hover:border-green-700 focus:ring-green-500',
    warning: 'text-white bg-yellow-600 hover:bg-yellow-700 border-yellow-600 hover:border-yellow-700 focus:ring-yellow-500',
    danger: 'text-white bg-red-600 hover:bg-red-700 border-red-600 hover:border-red-700 focus:ring-red-500',
    info: 'text-white bg-blue-600 hover:bg-blue-700 border-blue-600 hover:border-blue-700 focus:ring-blue-500',
    light: 'text-gray-700 bg-gray-100 hover:bg-gray-200 border-gray-200 hover:border-gray-300 focus:ring-gray-500',
    dark: 'text-white bg-gray-800 hover:bg-gray-900 border-gray-800 hover:border-gray-900 focus:ring-gray-600',
    outline: 'text-gray-700 bg-transparent hover:bg-gray-50 border-gray-300 hover:border-gray-400 focus:ring-gray-500',
    // Variantes "soft" — fundo branco com borda + texto coloridos. Identificação visual
    // sutil pra ações utilitárias no header sem o peso de variants preenchidas.
    'primary-soft': 'text-indigo-700 bg-white hover:bg-indigo-50 border-indigo-300 hover:border-indigo-400 focus:ring-indigo-500',
    'info-soft': 'text-blue-700 bg-white hover:bg-blue-50 border-blue-300 hover:border-blue-400 focus:ring-blue-500',
    'success-soft': 'text-green-700 bg-white hover:bg-green-50 border-green-300 hover:border-green-400 focus:ring-green-500',
    'warning-soft': 'text-amber-700 bg-white hover:bg-amber-50 border-amber-300 hover:border-amber-400 focus:ring-amber-500',
    'danger-soft': 'text-red-700 bg-white hover:bg-red-50 border-red-300 hover:border-red-400 focus:ring-red-500',
};

export const BUTTON_BASE_CLASSES = 'inline-flex items-center justify-center font-medium rounded-lg border transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed hover:shadow-md hover:scale-105';

export const BUTTON_SIZES = {
    xs: { regular: 'px-2.5 py-1.5 text-xs', iconOnly: 'p-1.5' },
    sm: { regular: 'px-3 py-2 text-sm', iconOnly: 'p-2' },
    md: { regular: 'px-4 py-2 text-sm', iconOnly: 'p-2.5' },
    lg: { regular: 'px-6 py-3 text-base', iconOnly: 'p-3' },
    xl: { regular: 'px-8 py-4 text-lg', iconOnly: 'p-4' },
};

export const BUTTON_ICON_SIZES = {
    xs: 'w-3 h-3',
    sm: 'w-4 h-4',
    md: 'w-4 h-4',
    lg: 'w-5 h-5',
    xl: 'w-6 h-6',
};

export const BUTTON_ICON_ONLY_DIMENSIONS = {
    xs: 'w-6 h-6',
    sm: 'w-8 h-8',
    md: 'w-8 h-8',
    lg: 'w-10 h-10',
    xl: 'w-12 h-12',
};

export function getButtonClasses({
    variant = 'primary',
    size = 'md',
    iconOnly = false,
    disabled = false,
    className = '',
} = {}) {
    const sizeDef = BUTTON_SIZES[size] || BUTTON_SIZES.md;
    return [
        BUTTON_BASE_CLASSES,
        BUTTON_VARIANTS[variant] || BUTTON_VARIANTS.primary,
        iconOnly ? sizeDef.iconOnly : sizeDef.regular,
        iconOnly ? BUTTON_ICON_ONLY_DIMENSIONS[size] : '',
        disabled ? 'opacity-50 cursor-not-allowed' : '',
        className,
    ].filter(Boolean).join(' ');
}

const Button = forwardRef(({
    children,
    type = 'button',
    size = 'md',
    variant = 'primary',
    disabled = false,
    loading = false,
    icon: Icon = null,
    iconOnly = false,
    iconPosition = 'left',
    className = '',
    onClick,
    ...props
}, ref) => {
    const iconSizes = BUTTON_ICON_SIZES;
    const buttonClasses = getButtonClasses({
        variant,
        size,
        iconOnly,
        disabled: disabled || loading,
        className,
    });

    // Determinar espaçamento entre ícone e texto
    const getIconSpacing = () => {
        if (iconOnly || !children) return '';
        return iconPosition === 'right' ? 'ml-2' : 'mr-2';
    };

    // Renderizar ícone
    const renderIcon = () => {
        if (!Icon) return null;

        const iconClass = `${iconSizes[size]} ${getIconSpacing()} transition-colors duration-300`;

        return <Icon className={iconClass} />;
    };

    // Renderizar conteúdo do botão
    const renderContent = () => {
        if (loading) {
            return (
                <>
                    <svg className={`animate-spin ${iconSizes[size]} ${children ? 'mr-2' : ''}`} fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {!iconOnly && children}
                </>
            );
        }

        if (iconOnly) {
            return renderIcon();
        }

        return (
            <>
                {Icon && iconPosition === 'left' && renderIcon()}
                {children}
                {Icon && iconPosition === 'right' && renderIcon()}
            </>
        );
    };

    return (
        <button
            ref={ref}
            type={type}
            className={buttonClasses}
            disabled={disabled || loading}
            onClick={onClick}
            {...props}
        >
            {renderContent()}
        </button>
    );
});

Button.displayName = 'Button';

export default Button;
