import { InboxIcon } from '@heroicons/react/24/outline';

/**
 * Componente para estados vazios com icone, titulo e descricao.
 *
 * @param {string} title - Titulo principal (ex: "Nenhum registro encontrado")
 * @param {string} description - Descricao adicional (opcional)
 * @param {React.ElementType} icon - Componente Heroicon (default: InboxIcon)
 * @param {React.ReactNode} action - Botao ou link de acao (opcional)
 * @param {boolean} compact - Versao compacta para uso dentro de tabelas/cards (opcional)
 * @param {string} className - Classes adicionais (opcional)
 *
 * @example
 * // Basico
 * <EmptyState title="Nenhum registro encontrado" />
 *
 * // Com descricao
 * <EmptyState
 *   title="Nenhum funcionario encontrado"
 *   description="Tente ajustar os filtros de busca."
 * />
 *
 * // Com acao
 * <EmptyState
 *   title="Nenhuma venda registrada"
 *   description="Comece adicionando uma nova venda."
 *   action={<Button onClick={openCreate}>Nova Venda</Button>}
 * />
 *
 * // Compacto (dentro de cards/tabelas)
 * <EmptyState title="Nenhum item" compact />
 */
export default function EmptyState({
    title = 'Nenhum registro encontrado',
    description,
    icon: Icon = InboxIcon,
    action,
    compact = false,
    className = '',
}) {
    if (compact) {
        return (
            <div className={`p-4 text-center text-sm text-gray-400 ${className}`}>
                {title}
            </div>
        );
    }

    return (
        <div className={`text-center py-12 ${className}`}>
            <Icon className="w-16 h-16 text-gray-300 mx-auto mb-4" />
            <h3 className="text-lg font-medium text-gray-900 mb-2">{title}</h3>
            {description && (
                <p className="text-sm text-gray-500 mb-4">{description}</p>
            )}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
