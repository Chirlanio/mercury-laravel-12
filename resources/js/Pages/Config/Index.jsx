import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Button from '@/Components/Button';
import GenericFormModal from '@/Components/GenericFormModal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({
    auth,
    items = { data: [], links: [] },
    config = {},
    filters = {},
    stats = {},
    additionalData = {},
}) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [selectedItem, setSelectedItem] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const { hasPermission } = usePermissions();

    const canCreate = hasPermission(PERMISSIONS.MANAGE_SETTINGS);
    const canEdit = hasPermission(PERMISSIONS.MANAGE_SETTINGS);
    const canDelete = hasPermission(PERMISSIONS.MANAGE_SETTINGS);

    const handleEditClick = (item) => {
        setSelectedItem(item);
        setShowEditModal(true);
    };

    const handleDeleteClick = (item) => {
        setSelectedItem(item);
        setShowDeleteDialog(true);
    };

    const handleConfirmDelete = () => {
        if (!selectedItem) return;

        setDeleting(true);
        router.delete(route(config.routeName + '.destroy', selectedItem.id), {
            preserveScroll: true,
            onSuccess: () => {
                setShowDeleteDialog(false);
                setSelectedItem(null);
            },
            onFinish: () => {
                setDeleting(false);
            },
        });
    };

    const closeModals = () => {
        setShowCreateModal(false);
        setShowEditModal(false);
        setSelectedItem(null);
    };

    // Construir colunas da DataTable a partir da config
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
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                            isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }`}>
                            {isActive ? (col.trueLabel || 'Ativo') : (col.falseLabel || 'Inativo')}
                        </span>
                    );
                }
                : col.type === 'color'
                ? (item) => {
                    const colorTheme = item.color_theme;
                    if (!colorTheme) return <span className="text-gray-400">-</span>;
                    return (
                        <div className="flex items-center space-x-2">
                            <div
                                className="w-6 h-6 rounded-full border border-gray-200"
                                style={{ backgroundColor: colorTheme.hex_color || '#6B7280' }}
                            />
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
                : col.render
                ? col.render
                : undefined,
        }));

        // Coluna de acoes
        if (canEdit || canDelete) {
            cols.push({
                label: 'Acoes',
                field: 'actions',
                sortable: false,
                render: (item) => (
                    <div className="flex items-center space-x-2">
                        {canEdit && (
                            <Button
                                onClick={(e) => {
                                    e.stopPropagation();
                                    handleEditClick(item);
                                }}
                                variant="warning"
                                size="sm"
                                iconOnly={true}
                                icon={({ className }) => (
                                    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                )}
                                title="Editar"
                            />
                        )}
                        {canDelete && (
                            <Button
                                onClick={(e) => {
                                    e.stopPropagation();
                                    handleDeleteClick(item);
                                }}
                                variant="danger"
                                size="sm"
                                iconOnly={true}
                                icon={({ className }) => (
                                    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                )}
                                title="Excluir"
                            />
                        )}
                    </div>
                ),
            });
        }

        return cols;
    };

    // Construir secoes do formulario a partir da config
    const buildFormSections = () => {
        const fields = (config.formFields || []).map((field) => {
            const baseField = {
                name: field.name,
                label: field.label,
                type: field.type || 'text',
                required: field.required !== false,
                placeholder: field.placeholder || '',
                defaultValue: field.defaultValue ?? (field.type === 'checkbox' ? false : ''),
            };

            // Adicionar opcoes para selects
            if (field.type === 'select' && field.optionsKey) {
                baseField.options = (additionalData[field.optionsKey] || []).map((opt) => ({
                    value: opt.id ?? opt.value,
                    label: opt.name ?? opt.label,
                }));
                baseField.placeholder = field.placeholder || 'Selecione...';
            } else if (field.type === 'select' && field.options) {
                baseField.options = field.options;
                baseField.placeholder = field.placeholder || 'Selecione...';
            }

            return baseField;
        });

        return [{ fields }];
    };

    // Identificar o campo "nome" principal para exibir no dialog de delete
    const getItemDisplayName = (item) => {
        if (!item) return '';
        const nameFields = ['name', 'description_name', 'sector_name', 'nome'];
        for (const field of nameFields) {
            if (item[field]) return item[field];
        }
        return `#${item.id}`;
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={config.title || 'Configuracao'} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    {config.title || 'Configuracao'}
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    {config.description || ''}
                                </p>
                            </div>
                            <div className="flex gap-3">
                                {canCreate && (
                                    <Button
                                        onClick={() => setShowCreateModal(true)}
                                        variant="primary"
                                        icon={({ className }) => (
                                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                        )}
                                    >
                                        Novo
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Cards de Estatisticas */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div className="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                            <div className="flex items-center">
                                <div className="p-2 bg-indigo-100 rounded-lg">
                                    <svg className="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                    </svg>
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm font-medium text-gray-500">Total</p>
                                    <p className="text-2xl font-bold text-gray-900">{stats.total || 0}</p>
                                </div>
                            </div>
                        </div>

                        {stats.active !== undefined && (
                            <div className="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                                <div className="flex items-center">
                                    <div className="p-2 bg-green-100 rounded-lg">
                                        <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div className="ml-4">
                                        <p className="text-sm font-medium text-gray-500">Ativos</p>
                                        <p className="text-2xl font-bold text-gray-900">{stats.active || 0}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {stats.inactive !== undefined && (
                            <div className="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                                <div className="flex items-center">
                                    <div className="p-2 bg-red-100 rounded-lg">
                                        <svg className="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div className="ml-4">
                                        <p className="text-sm font-medium text-gray-500">Inativos</p>
                                        <p className="text-2xl font-bold text-gray-900">{stats.inactive || 0}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {stats.in_use !== undefined && (
                            <div className="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                                <div className="flex items-center">
                                    <div className="p-2 bg-blue-100 rounded-lg">
                                        <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                        </svg>
                                    </div>
                                    <div className="ml-4">
                                        <p className="text-sm font-medium text-gray-500">Em Uso</p>
                                        <p className="text-2xl font-bold text-gray-900">{stats.in_use || 0}</p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={items}
                        columns={buildColumns()}
                        searchPlaceholder="Pesquisar..."
                        emptyMessage="Nenhum registro encontrado"
                        perPageOptions={[10, 15, 25, 50]}
                    />
                </div>
            </div>

            {/* Modal de Criacao */}
            <GenericFormModal
                show={showCreateModal}
                onClose={closeModals}
                onSuccess={closeModals}
                title={'Novo ' + (config.title || 'Registro')}
                mode="create"
                sections={buildFormSections()}
                submitUrl={route(config.routeName + '.store')}
                submitMethod="post"
                submitButtonText="Criar"
                maxWidth="2xl"
                preserveScroll={true}
            />

            {/* Modal de Edicao */}
            <GenericFormModal
                show={showEditModal}
                onClose={closeModals}
                onSuccess={closeModals}
                title={'Editar ' + (config.title || 'Registro')}
                mode="edit"
                initialData={selectedItem}
                sections={buildFormSections()}
                submitUrl={selectedItem ? route(config.routeName + '.update', selectedItem.id) : ''}
                submitMethod="put"
                submitButtonText="Salvar"
                maxWidth="2xl"
                preserveScroll={true}
            />

            {/* Dialog de Confirmacao de Exclusao */}
            <ConfirmDialog
                show={showDeleteDialog}
                onClose={() => {
                    setShowDeleteDialog(false);
                    setSelectedItem(null);
                }}
                onConfirm={handleConfirmDelete}
                title="Confirmar Exclusao"
                message={`Tem certeza que deseja excluir "${getItemDisplayName(selectedItem)}"? Esta acao nao pode ser desfeita.`}
                confirmText={deleting ? 'Excluindo...' : 'Excluir'}
                cancelText="Cancelar"
                type="danger"
            />
        </AuthenticatedLayout>
    );
}
