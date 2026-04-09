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

ActionButtons.Custom = function CustomAction({ variant, icon, title, onClick, size = 'sm', ...props }) {
    return (
        <Button
            onClick={(e) => { e.stopPropagation(); onClick(); }}
            variant={variant}
            size={size}
            iconOnly={true}
            icon={icon}
            title={title}
            {...props}
        />
    );
};
