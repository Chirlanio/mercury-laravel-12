import { Dialog, DialogPanel, Transition, TransitionChild } from '@headlessui/react';
import { XMarkIcon } from '@heroicons/react/24/outline';

/**
 * StandardModal — Componente modal padronizado para todo o projeto.
 *
 * Baseado nos padrões visuais dos módulos de Férias e Ordens de Pagamento:
 * - Header colorido com título, badges e ações
 * - Body scrollável com seções em cards
 * - Footer fixo com botões de ação
 * - Loading/error states integrados
 *
 * @example
 * <StandardModal show={open} onClose={close} title="Novo Item" headerColor="bg-indigo-600"
 *     footer={<StandardModal.Footer onCancel={close} onSubmit={save} submitLabel="Salvar" />}>
 *     <StandardModal.Section title="Dados" icon={<Icon />}>
 *         ...campos...
 *     </StandardModal.Section>
 * </StandardModal>
 */
export default function StandardModal({
    show = false,
    onClose = () => {},
    closeable = true,
    title,
    subtitle,
    headerColor = 'bg-indigo-600',
    headerIcon,
    headerBadges = [],
    headerActions,
    maxWidth = '7xl',
    loading = false,
    errorMessage,
    footer,
    children,
    onSubmit,
}) {
    const close = () => {
        if (closeable) onClose();
    };

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
        '3xl': 'sm:max-w-3xl',
        '4xl': 'sm:max-w-4xl',
        '5xl': 'sm:max-w-5xl',
        '6xl': 'sm:max-w-6xl',
    }[maxWidth] || 'sm:max-w-7xl';

    const isForm = typeof onSubmit === 'function';
    const Wrapper = isForm ? 'form' : 'div';
    const wrapperProps = isForm
        ? { onSubmit: (e) => { e.preventDefault(); onSubmit(e); }, className: 'flex flex-col flex-1 min-h-0' }
        : { className: 'flex flex-col flex-1 min-h-0' };

    return (
        <Transition show={show} leave="duration-200">
            <Dialog as="div" className="fixed inset-0 z-50 overflow-y-auto" onClose={close}>
                {/* Backdrop */}
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-gray-500/75 transition-opacity" />
                </TransitionChild>

                {/* Panel */}
                <div className="flex min-h-full items-start justify-center p-4 pt-8 sm:pt-12">
                    <TransitionChild
                        enter="ease-out duration-300"
                        enterFrom="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enterTo="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-200"
                        leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                        leaveTo="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <DialogPanel className={`relative w-full ${maxWidthClass} max-h-[90vh] flex flex-col bg-white rounded-xl shadow-2xl`}>
                            {loading ? (
                                <div className="flex justify-center py-24">
                                    <div className="animate-spin h-10 w-10 border-4 border-indigo-600 border-t-transparent rounded-full" />
                                </div>
                            ) : errorMessage && !children ? (
                                <div className="p-8 text-center text-gray-500">
                                    {errorMessage}
                                    <button onClick={close} className="block mx-auto mt-4 text-sm text-indigo-600 hover:underline">
                                        Fechar
                                    </button>
                                </div>
                            ) : (
                                <>
                                    {/* Header */}
                                    <div className={`${headerColor} rounded-t-xl px-6 py-4 flex items-center justify-between shrink-0`}>
                                        <div className="flex items-center gap-3 min-w-0">
                                            {headerIcon && <span className="text-white shrink-0">{headerIcon}</span>}
                                            <div className="min-w-0">
                                                <h3 className="text-lg font-semibold text-white truncate">{title}</h3>
                                                {subtitle && <p className="text-sm text-white/70 mt-0.5 truncate">{subtitle}</p>}
                                            </div>
                                            {headerBadges.map((badge, i) => (
                                                <span key={i} className={`text-xs font-bold px-2.5 py-1 rounded-full shrink-0 ${badge.className || 'bg-white/20 text-white'}`}>
                                                    {badge.text}
                                                </span>
                                            ))}
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            {headerActions}
                                            {closeable && (
                                                <button type="button" onClick={close} className="text-white/70 hover:text-white ml-2 transition-colors">
                                                    <XMarkIcon className="h-6 w-6" />
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    {/* Body + Footer */}
                                    <Wrapper {...wrapperProps}>
                                        <ModalBody errorMessage={errorMessage}>{children}</ModalBody>
                                        {footer}
                                    </Wrapper>
                                </>
                            )}
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}

function ModalBody({ errorMessage, children }) {
    return (
        <div className="p-6 space-y-5 overflow-y-auto flex-1">
            {errorMessage && (
                <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700 flex items-start gap-2">
                    <svg className="h-5 w-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <span>{errorMessage}</span>
                </div>
            )}
            {children}
        </div>
    );
}

// ============================================================
// Sub-componentes
// ============================================================

/**
 * Section — Card com header e corpo para organizar conteúdo dentro do modal.
 *
 * @example
 * <StandardModal.Section title="Dados Gerais" icon={<DocumentTextIcon className="h-4 w-4" />}>
 *     <div className="grid grid-cols-2 gap-4">...</div>
 * </StandardModal.Section>
 */
function Section({ title, icon, children }) {
    return (
        <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div className="bg-gray-50 px-4 py-2.5 border-b border-gray-200">
                <h4 className="text-xs font-semibold text-gray-600 uppercase tracking-wide flex items-center gap-1.5">
                    {icon} {title}
                </h4>
            </div>
            <div className="p-4">{children}</div>
        </div>
    );
}

/**
 * Field — Exibição de um campo label + valor (para modais de detalhe).
 *
 * @example
 * <StandardModal.Field label="Fornecedor" value={order.supplier_name} />
 * <StandardModal.Field label="NF" value={order.nf} mono />
 * <StandardModal.Field label="Status" value="Ativo" badge="green" />
 */
function Field({ label, value, mono, badge }) {
    const badgeColors = {
        green: 'bg-green-100 text-green-700',
        yellow: 'bg-yellow-100 text-yellow-700',
        blue: 'bg-blue-100 text-blue-700',
        red: 'bg-red-100 text-red-700',
        orange: 'bg-orange-100 text-orange-700',
        indigo: 'bg-indigo-100 text-indigo-700',
        purple: 'bg-purple-100 text-purple-700',
        gray: 'bg-gray-100 text-gray-700',
    };
    return (
        <div>
            <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">{label}</p>
            {badge ? (
                <span className={`inline-flex mt-0.5 px-2 py-0.5 rounded text-xs font-medium ${badgeColors[badge] || badgeColors.gray}`}>
                    {value || '-'}
                </span>
            ) : (
                <p className={`text-sm mt-0.5 text-gray-900 ${mono ? 'font-mono' : ''}`}>{value || '-'}</p>
            )}
        </div>
    );
}

/**
 * InfoCard — Card de resumo com label e valor em destaque (para exibir métricas/datas).
 *
 * @example
 * <StandardModal.InfoCard label="Início" value="01/01/2026" icon={<CalendarIcon className="h-4 w-4" />} />
 * <StandardModal.InfoCard label="Dias" value="30" highlight />
 */
function InfoCard({ label, value, icon, highlight, colorClass }) {
    return (
        <div className={`rounded-lg p-3 text-center ${colorClass || 'bg-gray-50'}`}>
            <p className="text-[10px] font-semibold text-gray-400 uppercase flex items-center justify-center gap-1">
                {icon}{label}
            </p>
            <p className={`text-lg font-bold mt-0.5 ${highlight ? 'text-indigo-700' : 'text-gray-900'}`}>{value}</p>
        </div>
    );
}

/**
 * MiniField — Campo compacto para informações secundárias.
 *
 * @example
 * <StandardModal.MiniField label="Parcela" value="1ª" />
 */
function MiniField({ label, value }) {
    return (
        <div className="bg-gray-50 rounded p-2">
            <p className="text-[10px] font-semibold text-gray-400 uppercase">{label}</p>
            <p className="text-sm text-gray-900 mt-0.5">{value || '-'}</p>
        </div>
    );
}

/**
 * Footer — Rodapé fixo padronizado com botões de ação.
 *
 * @example
 * // Footer simples
 * <StandardModal.Footer onCancel={close} onSubmit={save} submitLabel="Salvar" />
 *
 * // Footer com cor customizada
 * <StandardModal.Footer onCancel={close} onSubmit={save} submitLabel="Confirmar"
 *     submitColor="bg-green-600 hover:bg-green-700" />
 *
 * // Footer com conteúdo customizado
 * <StandardModal.Footer>
 *     <button>Ação Custom</button>
 * </StandardModal.Footer>
 */
function Footer({ children, onCancel, onSubmit, submitLabel = 'Salvar', cancelLabel = 'Cancelar', submitColor, processing, disabled }) {
    if (children) {
        return (
            <div className="flex flex-wrap items-center gap-2 px-6 py-4 border-t bg-gray-50 rounded-b-xl shrink-0">
                {children}
            </div>
        );
    }

    return (
        <div className="flex justify-end space-x-3 px-6 py-4 border-t bg-gray-50 rounded-b-xl shrink-0">
            {onCancel && (
                <button type="button" onClick={onCancel}
                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    {cancelLabel}
                </button>
            )}
            {onSubmit && (
                <button type={typeof onSubmit === 'string' ? 'submit' : 'button'}
                    onClick={typeof onSubmit === 'function' ? onSubmit : undefined}
                    disabled={processing || disabled}
                    className={`px-6 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-50 transition-colors ${submitColor || 'bg-indigo-600 hover:bg-indigo-700'}`}>
                    {processing ? 'Processando...' : submitLabel}
                </button>
            )}
        </div>
    );
}

/**
 * Highlight — Bloco de destaque para valores importantes (ex: valor total).
 *
 * @example
 * <StandardModal.Highlight>
 *     <div>
 *         <p className="text-xs font-medium text-indigo-500 uppercase">Valor Total</p>
 *         <p className="text-3xl font-bold text-indigo-700">R$ 1.500,00</p>
 *     </div>
 * </StandardModal.Highlight>
 */
function Highlight({ children, className }) {
    return (
        <div className={`bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl p-5 border border-indigo-100 ${className || ''}`}>
            {children}
        </div>
    );
}

/**
 * Timeline — Componente de timeline para histórico de status.
 *
 * @example
 * <StandardModal.Timeline items={[{ id: 1, title: 'Aprovado', subtitle: 'João - 01/01', notes: 'OK', dotColor: 'bg-green-500' }]} />
 */
function Timeline({ items = [] }) {
    if (!items.length) return null;
    return (
        <div className="relative">
            <div className="absolute left-[7px] top-2 bottom-2 w-0.5 bg-gray-200" />
            <div className="space-y-4">
                {items.map((item) => (
                    <div key={item.id} className="flex items-start gap-4 relative">
                        <div className={`mt-0.5 h-4 w-4 rounded-full ${item.dotColor || 'bg-indigo-500'} ring-4 ring-white shrink-0 z-10`} />
                        <div className="flex-1 bg-gray-50 rounded-lg p-3">
                            <div className="text-sm font-semibold text-gray-900">{item.title}</div>
                            {item.subtitle && (
                                <div className="text-xs text-gray-500 mt-0.5">{item.subtitle}</div>
                            )}
                            {item.notes && (
                                <p className="mt-1.5 text-xs text-gray-600 italic bg-white rounded px-2 py-1 border border-gray-100">
                                    "{item.notes}"
                                </p>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// Attach sub-components
StandardModal.Section = Section;
StandardModal.Field = Field;
StandardModal.InfoCard = InfoCard;
StandardModal.MiniField = MiniField;
StandardModal.Footer = Footer;
StandardModal.Highlight = Highlight;
StandardModal.Timeline = Timeline;
