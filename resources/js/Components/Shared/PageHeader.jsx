import { Link } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import {
    PlusIcon, ArrowLeftIcon, ArrowDownTrayIcon, ArrowUpTrayIcon,
    PrinterIcon, ChartBarIcon, ClockIcon, ArrowPathIcon, FunnelIcon,
    Cog6ToothIcon, PencilSquareIcon, TrashIcon, EyeIcon, ChevronDownIcon,
} from '@heroicons/react/24/outline';
import Button, { getButtonClasses, BUTTON_ICON_SIZES } from '@/Components/Button';

// Header padrão de página com título à esquerda e botões de ação à direita.
//
// API declarativa via `actions[]` — cada item:
//   { type, label, icon, variant, compact, onClick|href|download|items, visible, ... }
//
// 1) Type presets aplicam icon/variant/label default automaticamente:
//      { type: 'download', download: route('foo.export') }     → ArrowDownTrayIcon, success-soft, "Exportar"
//      { type: 'create', label: 'Nova OC', onClick: ... }      → PlusIcon, primary, label custom
//      { type: 'reports', items: [...] }                       → ChartBarIcon + dropdown
//
// 2) Compact ('auto'|true|false, default 'auto'):
//      'auto' = icon-only sempre quando variant ≠ primary E tem ícone (label vira tooltip)
//      true   = força icon-only mesmo sendo primary
//      false  = força label sempre
//
// 3) Cores semânticas via variants soft (border + texto coloridos, fundo branco):
//      primary-soft, info-soft, success-soft, warning-soft, danger-soft
//
// 4) Dropdown: quando `items[]` está presente, vira menu (cada item suporta href/download/onClick/divider).

// ───────────────────────────────────────────────────────────────────────
// Type presets — aplicam icon + variant + label default. Override por
// prop explícita na ação (ex: `label` ou `variant` no objeto sobrepõe).
// ───────────────────────────────────────────────────────────────────────
const ACTION_PRESETS = {
    create:    { icon: PlusIcon,           variant: 'primary',       label: 'Novo' },
    back:      { icon: ArrowLeftIcon,      variant: 'outline',       label: 'Voltar' },
    download:  { icon: ArrowDownTrayIcon,  variant: 'success-soft',  label: 'Exportar' },
    print:     { icon: PrinterIcon,        variant: 'info-soft',     label: 'Imprimir' },
    import:    { icon: ArrowUpTrayIcon,    variant: 'info-soft',     label: 'Importar' },
    dashboard: { icon: ChartBarIcon,       variant: 'primary-soft',  label: 'Dashboard' },
    reports:   { icon: ChartBarIcon,       variant: 'primary-soft',  label: 'Relatórios' },
    history:   { icon: ClockIcon,          variant: 'outline',       label: 'Histórico' },
    sync:      { icon: ArrowPathIcon,      variant: 'warning-soft',  label: 'Sincronizar' },
    refresh:   { icon: ArrowPathIcon,      variant: 'warning-soft',  label: 'Atualizar' },
    filter:    { icon: FunnelIcon,         variant: 'outline',       label: 'Filtros' },
    settings:  { icon: Cog6ToothIcon,      variant: 'outline',       label: 'Configurações' },
    edit:      { icon: PencilSquareIcon,   variant: 'warning-soft',  label: 'Editar' },
    delete:    { icon: TrashIcon,          variant: 'danger-soft',   label: 'Excluir' },
    view:      { icon: EyeIcon,            variant: 'info-soft',     label: 'Visualizar' },
};

function resolveAction(action) {
    if (!action.type || !ACTION_PRESETS[action.type]) return action;
    // Preset preenche defaults; props explícitas na action sobrescrevem.
    return { ...ACTION_PRESETS[action.type], ...action };
}

function isCompact(action) {
    if (action.compact === true) return true;
    if (action.compact === false) return false;
    // 'auto' (default): icon-only quando não-primary E tem ícone.
    return Boolean(action.icon) && action.variant !== 'primary';
}

const BREAKPOINT_CONTAINER = {
    sm: 'mb-6 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center',
    lg: 'mb-6 flex flex-col gap-3 lg:flex-row lg:justify-between lg:items-center',
};

const BREAKPOINT_RIGHT_CONTAINER = {
    sm: 'flex flex-wrap gap-2 sm:shrink-0',
    lg: 'flex flex-wrap gap-2 lg:shrink-0',
};

function resolveBreakpoint(explicit, visibleCount) {
    if (explicit === 'sm' || explicit === 'lg') return explicit;
    return visibleCount >= 3 ? 'lg' : 'sm';
}

// ───────────────────────────────────────────────────────────────────────
// Conteúdo do botão: ícone + label (com label oculto quando compact).
// ───────────────────────────────────────────────────────────────────────
function ActionContent({ icon: Icon, label, compact, size = 'md', dropdown = false }) {
    const iconSize = BUTTON_ICON_SIZES[size] || BUTTON_ICON_SIZES.md;

    if (!Icon) {
        return (
            <>
                <span>{label}</span>
                {dropdown && <ChevronDownIcon className={`${iconSize} ml-1.5`} />}
            </>
        );
    }

    if (compact) {
        return <Icon className={iconSize} />;
    }

    return (
        <>
            <Icon className={`${iconSize} mr-2`} />
            <span>{label}</span>
            {dropdown && <ChevronDownIcon className={`${iconSize} ml-1.5`} />}
        </>
    );
}

// ───────────────────────────────────────────────────────────────────────
// Dropdown — renderizado quando action.items existe. Click outside fecha.
// ───────────────────────────────────────────────────────────────────────
function DropdownMenu({ items, onItemClick }) {
    return (
        <div
            className="absolute right-0 mt-1 z-50 min-w-[12rem] origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none animate-in fade-in zoom-in-95 duration-100"
            role="menu"
        >
            <div className="py-1">
                {items.map((item, idx) => {
                    if (item.divider) {
                        return <div key={`d-${idx}`} className="my-1 border-t border-gray-200" />;
                    }

                    const baseClass = 'flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900';
                    const ItemIcon = item.icon;
                    const content = (
                        <>
                            {ItemIcon && <ItemIcon className="w-4 h-4 text-gray-500" />}
                            <span>{item.label}</span>
                        </>
                    );

                    if (item.download) {
                        return (
                            <a key={idx} href={item.download} className={baseClass} onClick={onItemClick}>
                                {content}
                            </a>
                        );
                    }

                    if (item.href) {
                        return (
                            <Link key={idx} href={item.href} className={baseClass} onClick={onItemClick}>
                                {content}
                            </Link>
                        );
                    }

                    return (
                        <button
                            key={idx}
                            type="button"
                            onClick={() => {
                                onItemClick();
                                item.onClick?.();
                            }}
                            disabled={item.disabled}
                            className={`${baseClass} text-left ${item.disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
                        >
                            {content}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

function HeaderActionDropdown({ action }) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef(null);
    const compact = isCompact(action);
    const accessibleTitle = action.title || action.label;
    const buttonExtra = 'min-h-[44px] whitespace-nowrap relative';

    useEffect(() => {
        if (!open) return;
        const handleClickOutside = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setOpen(false);
            }
        };
        const handleEsc = (e) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleEsc);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleEsc);
        };
    }, [open]);

    return (
        <div ref={containerRef} className="relative">
            <Button
                type="button"
                variant={action.variant || 'outline'}
                size={action.size || 'md'}
                onClick={() => setOpen((v) => !v)}
                disabled={action.disabled}
                title={accessibleTitle}
                aria-label={accessibleTitle}
                aria-haspopup="menu"
                aria-expanded={open}
                className={`${buttonExtra} ${action.className || ''}`}
            >
                <ActionContent
                    icon={action.icon}
                    label={action.label}
                    compact={compact}
                    size={action.size || 'md'}
                    dropdown={!compact}
                />
            </Button>
            {open && <DropdownMenu items={action.items} onItemClick={() => setOpen(false)} />}
        </div>
    );
}

// ───────────────────────────────────────────────────────────────────────
// Botão único (sem dropdown).
// ───────────────────────────────────────────────────────────────────────
function HeaderAction({ action }) {
    const {
        label,
        icon,
        variant = 'primary',
        size = 'md',
        loading = false,
        disabled = false,
        onClick,
        href,
        download,
        title,
        className = '',
        ariaLabel,
        type: htmlType = 'button',
    } = action;

    const compact = isCompact(action);
    const buttonExtra = 'min-h-[44px] whitespace-nowrap';
    const content = <ActionContent icon={icon} label={label} compact={compact} size={size} />;
    const accessibleTitle = title || label;
    const accessibleAriaLabel = ariaLabel || label;

    if (download) {
        return (
            <a
                href={download}
                title={accessibleTitle}
                aria-label={accessibleAriaLabel}
                className={getButtonClasses({ variant, size, className: `${buttonExtra} ${className}` })}
            >
                {content}
            </a>
        );
    }

    if (href) {
        return (
            <Link
                href={href}
                title={accessibleTitle}
                aria-label={accessibleAriaLabel}
                className={getButtonClasses({ variant, size, disabled, className: `${buttonExtra} ${className}` })}
            >
                {content}
            </Link>
        );
    }

    return (
        <Button
            type={htmlType}
            variant={variant}
            size={size}
            onClick={onClick}
            loading={loading}
            disabled={disabled}
            title={accessibleTitle}
            aria-label={accessibleAriaLabel}
            className={`${buttonExtra} ${className}`}
        >
            {content}
        </Button>
    );
}

// ───────────────────────────────────────────────────────────────────────
// Componente principal.
// ───────────────────────────────────────────────────────────────────────
export default function PageHeader({
    title,
    subtitle,
    icon: Icon,
    iconColor = 'text-indigo-600',
    scopeBadge,
    scopeBadgeColor = 'text-indigo-600',
    actions,
    breakpoint = 'auto',
    className = '',
    titleSize = 'lg',
    children,
}) {
    const visibleActions = Array.isArray(actions)
        ? actions.filter((a) => a && a.visible !== false).map(resolveAction)
        : [];
    const visibleCount = children
        ? Math.max(3, visibleActions.length)
        : visibleActions.length;
    const bp = resolveBreakpoint(breakpoint, visibleCount);

    const titleClasses = titleSize === 'lg'
        ? 'text-2xl sm:text-3xl font-bold text-gray-900'
        : 'text-xl sm:text-2xl font-bold text-gray-900';

    return (
        <div className={`${BREAKPOINT_CONTAINER[bp]} ${className}`}>
            <div className="min-w-0">
                <h1 className={`${titleClasses} ${Icon ? 'flex items-center gap-2' : ''}`}>
                    {Icon && <Icon className={`h-7 w-7 shrink-0 ${iconColor}`} />}
                    <span className="truncate">{title}</span>
                </h1>
                {subtitle && (
                    <p className="mt-1 text-sm text-gray-600">
                        {subtitle}
                        {scopeBadge && (
                            <span className={`ml-2 text-xs font-medium ${scopeBadgeColor}`}>
                                ({scopeBadge})
                            </span>
                        )}
                    </p>
                )}
            </div>

            {(visibleActions.length > 0 || children) && (
                <div className={BREAKPOINT_RIGHT_CONTAINER[bp]}>
                    {children}
                    {visibleActions.map((action, idx) => {
                        const key = action.key ?? action.label ?? idx;
                        return Array.isArray(action.items) && action.items.length > 0
                            ? <HeaderActionDropdown key={key} action={action} />
                            : <HeaderAction key={key} action={action} />;
                    })}
                </div>
            )}
        </div>
    );
}

export { HeaderAction, ACTION_PRESETS };
