import DataTable from '@/Components/DataTable';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import GenericFormModal from '@/Components/GenericFormModal';
import GroupAssignModal from '@/Components/GroupAssignModal';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    PlusIcon, Squares2X2Icon, CheckCircleIcon, XCircleIcon, LinkIcon,
    RectangleGroupIcon,
} from '@heroicons/react/24/outline';

export default function Index({
    items = { data: [], links: [] },
    config = {},
    filters = {},
    stats = {},
    additionalData = {},
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.MANAGE_SETTINGS);
    const canEdit = hasPermission(PERMISSIONS.MANAGE_SETTINGS);
    const canDelete = hasPermission(PERMISSIONS.MANAGE_SETTINGS);

    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'edit', 'group']);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [selectedIds, setSelectedIds] = useState([]);

    const supportsGroups = additionalData?.supportsGroups === true;

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(route(config.routeName + '.destroy', deleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onFinish: () => setDeleting(false),
        });
    };

    const closeFormModals = () => {
        closeModal('create');
        closeModal('edit');
    };

    // Stats cards
    const statisticsCards = [
        { label: 'Total', value: stats.total || 0, format: 'number', icon: Squares2X2Icon, color: 'indigo' },
        ...(stats.active !== undefined ? [{ label: 'Ativos', value: stats.active || 0, format: 'number', icon: CheckCircleIcon, color: 'green' }] : []),
        ...(stats.inactive !== undefined ? [{ label: 'Inativos', value: stats.inactive || 0, format: 'number', icon: XCircleIcon, color: 'red' }] : []),
        ...(stats.in_use !== undefined ? [{ label: 'Em Uso', value: stats.in_use || 0, format: 'number', icon: LinkIcon, color: 'blue' }] : []),
    ];

    // Colunas da DataTable
    const buildColumns = () => {
        const cols = (config.columns || []).map((col) => ({
            label: col.label,
            field: col.key,
            sortable: col.sortable !== false,
            render: col.type === 'badge'
                ? (item) => {
                    const value = item[col.key];
                    const isActive = value === true || value === 1 || value === 'true';
                    return (
                        <StatusBadge variant={isActive ? 'success' : 'danger'}>
                            {isActive ? (col.trueLabel || 'Ativo') : (col.falseLabel || 'Inativo')}
                        </StatusBadge>
                    );
                }
                : col.type === 'color'
                ? (item) => {
                    const colorTheme = item.color_theme;
                    if (!colorTheme) return <span className="text-gray-400">-</span>;
                    return (
                        <div className="flex items-center space-x-2">
                            <div className="w-6 h-6 rounded-full border border-gray-200"
                                style={{ backgroundColor: colorTheme.hex_color || '#6B7280' }} />
                            <span className="text-sm text-gray-700">{colorTheme.name}</span>
                        </div>
                    );
                }
                : col.type === 'relation'
                ? (item) => {
                    const relation = item[col.relationKey];
                    if (!relation) return <span className="text-gray-400">-</span>;
                    return <span className="text-sm text-gray-900">{relation[col.relationLabel || 'name']}</span>;
                }
                : col.render || undefined,
        }));

        if (canEdit || canDelete) {
            cols.push({
                label: 'Ações',
                field: 'actions',
                sortable: false,
                render: (item) => (
                    <ActionButtons
                        onEdit={canEdit ? () => openModal('edit', item) : null}
                        onDelete={canDelete ? () => setDeleteTarget(item) : null}
                    />
                ),
            });
        }

        return cols;
    };

    // Seções do formulário
    const buildFormSections = () => {
        const fields = (config.formFields || []).map((field) => {
            const baseField = {
                name: field.name, label: field.label, type: field.type || 'text',
                required: field.required !== false, placeholder: field.placeholder || '',
                defaultValue: field.defaultValue ?? (field.type === 'checkbox' ? false : ''),
                colSpan: field.colSpan || '', fullWidth: field.fullWidth || false,
                mask: field.mask || null,
            };
            if (field.type === 'select' && field.optionsKey) {
                baseField.options = (additionalData[field.optionsKey] || []).map((opt) => ({
                    value: opt.id ?? opt.value, label: opt.name ?? opt.label,
                }));
                baseField.placeholder = field.placeholder || 'Selecione...';
            } else if (field.type === 'select' && field.options) {
                baseField.options = field.options;
                baseField.placeholder = field.placeholder || 'Selecione...';
            }
            return baseField;
        });

        const fieldCount = fields.length;
        const columns = config.formColumns || (fieldCount <= 2 ? 'md:grid-cols-1' : fieldCount <= 4 ? 'md:grid-cols-2' : 'md:grid-cols-2 lg:grid-cols-3');
        return [{ fields, columns }];
    };

    const getModalMaxWidth = () => {
        if (config.modalMaxWidth) return config.modalMaxWidth;
        const fieldCount = (config.formFields || []).length;
        if (fieldCount <= 2) return 'lg';
        if (fieldCount <= 4) return 'xl';
        return '2xl';
    };

    const getItemDisplayName = (item) => {
        if (!item) return '';
        for (const field of ['name', 'description_name', 'sector_name', 'nome']) {
            if (item[field]) return item[field];
        }
        return `#${item.id}`;
    };

    return (
        <>
            <Head title={config.title || 'Configuração'} />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">{config.title || 'Configuração'}</h1>
                                <p className="mt-1 text-sm text-gray-600">{config.description || ''}</p>
                            </div>
                            <div className="flex gap-3">
                                {supportsGroups && canEdit && selectedIds.length >= 1 && (
                                    <Button variant="warning" onClick={() => openModal('group')} icon={RectangleGroupIcon}>
                                        Atribuir Grupo ({selectedIds.length})
                                    </Button>
                                )}
                                {canCreate && (
                                    <Button variant="primary" onClick={() => openModal('create')} icon={PlusIcon}>
                                        Novo
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Estatísticas */}
                    <StatisticsGrid cards={statisticsCards} />

                    {/* DataTable */}
                    <DataTable
                        data={items}
                        columns={buildColumns()}
                        searchPlaceholder="Pesquisar..."
                        emptyMessage="Nenhum registro encontrado"
                        perPageOptions={[10, 15, 25, 50]}
                        selectable={supportsGroups && canEdit}
                        selectedIds={selectedIds}
                        onSelectionChange={setSelectedIds}
                    />
                </div>
            </div>

            {/* Modal de Criação */}
            <GenericFormModal
                show={modals.create}
                onClose={closeFormModals}
                onSuccess={closeFormModals}
                title={'Novo ' + (config.title || 'Registro')}
                mode="create"
                sections={buildFormSections()}
                submitUrl={route(config.routeName + '.store')}
                submitMethod="post"
                submitButtonText="Criar"
                maxWidth={getModalMaxWidth()}
                preserveScroll={true}
            />

            {/* Modal de Edição */}
            <GenericFormModal
                show={modals.edit && selected !== null}
                onClose={closeFormModals}
                onSuccess={closeFormModals}
                title={'Editar ' + (config.title || 'Registro')}
                mode="edit"
                initialData={selected}
                sections={buildFormSections()}
                submitUrl={selected ? route(config.routeName + '.update', selected.id) : ''}
                submitMethod="put"
                submitButtonText="Salvar"
                maxWidth={getModalMaxWidth()}
                preserveScroll={true}
            />

            {/* Delete Confirm */}
            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleConfirmDelete}
                itemType={config.title?.toLowerCase() || 'registro'}
                itemName={getItemDisplayName(deleteTarget)}
                processing={deleting}
            />

            {/* Modal de Atribuição de Grupo */}
            {supportsGroups && (
                <GroupAssignModal
                    show={modals.group}
                    onClose={(assigned) => {
                        closeModal('group');
                        if (assigned) setSelectedIds([]);
                    }}
                    selectedIds={selectedIds}
                    routeName={config.routeName}
                    groups={additionalData.groups || []}
                />
            )}
        </>
    );
}
