import Button from '@/Components/Button';
import { EyeIcon, PencilSquareIcon, TrashIcon } from '@heroicons/react/24/outline';

/**
 * ActionButtons - Botões de ação padronizados para tabelas.
 *
 * Uso básico:
 *   <ActionButtons
 *     onView={() => handleView(item)}
 *     onEdit={canEdit ? () => handleEdit(item) : null}
 *     onDelete={canDelete ? () => handleDelete(item) : null}
 *   />
 *
 * Com ações extras:
 *   <ActionButtons onView={() => ...}>
 *     <ActionButtons.Custom variant="success" icon={ShieldCheckIcon} title="Permissões" onClick={() => ...} />
 *   </ActionButtons>
 */
export default function ActionButtons({ onView, onEdit, onDelete, children, size = 'sm' }) {
    return (
        <div className="flex items-center space-x-2">
            {onView && (
                <Button
                    onClick={(e) => { e.stopPropagation(); onView(); }}
                    variant="secondary"
                    size={size}
                    iconOnly={true}
                    icon={EyeIcon}
                    title="Visualizar"
                />
            )}
            {onEdit && (
                <Button
                    onClick={(e) => { e.stopPropagation(); onEdit(); }}
                    variant="warning"
                    size={size}
                    iconOnly={true}
                    icon={PencilSquareIcon}
                    title="Editar"
                />
            )}
            {onDelete && (
                <Button
                    onClick={(e) => { e.stopPropagation(); onDelete(); }}
                    variant="danger"
                    size={size}
                    iconOnly={true}
                    icon={TrashIcon}
                    title="Excluir"
                />
            )}
            {children}
        </div>
    );
}

// Mapa de cores legadas (color="green"/"red"/...) pra variants do Button.
// Mantém compatibilidade com call sites antigos que usavam essa nomenclatura.
const COLOR_TO_VARIANT = {
    green: 'success',
    red: 'danger',
    amber: 'warning',
    yellow: 'warning',
    blue: 'info',
    purple: 'info',
    indigo: 'primary',
    gray: 'secondary',
};

/**
 * API canônica: <ActionButtons.Custom variant="success" title="Aprovar" icon={...} onClick={...} />
 *
 * Aliases legados aceitos:
 *  - color="green|red|amber|..." → mapeado pra variant
 *  - label="..." → mapeado pra title (tooltip)
 */
ActionButtons.Custom = function CustomAction({
    variant, color,
    title, label,
    icon, onClick, size = 'sm', ...props
}) {
    const resolvedVariant = variant ?? (color ? (COLOR_TO_VARIANT[color] ?? 'primary') : 'primary');
    const resolvedTitle = title ?? label;
    return (
        <Button
            onClick={(e) => { e.stopPropagation(); onClick(); }}
            variant={resolvedVariant}
            size={size}
            iconOnly={true}
            icon={icon}
            title={resolvedTitle}
            {...props}
        />
    );
};
