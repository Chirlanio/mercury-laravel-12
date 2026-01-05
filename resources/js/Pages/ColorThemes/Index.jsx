import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import ColorThemeFormModal from '@/Components/ColorThemeFormModal';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ auth, colorThemes = { data: [], links: [] }, colorPalette = {}, filters = {}, stats = {} }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [selectedTheme, setSelectedTheme] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const { hasPermission } = usePermissions();

    const isLightColor = (hex) => {
        if (!hex) return false;
        const color = hex.replace('#', '');
        const r = parseInt(color.substr(0, 2), 16);
        const g = parseInt(color.substr(2, 2), 16);
        const b = parseInt(color.substr(4, 2), 16);
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return brightness > 155;
    };

    const getTextColor = (hex) => {
        return isLightColor(hex) ? 'text-gray-800' : 'text-white';
    };

    const handleEditClick = (theme) => {
        setSelectedTheme(theme);
        setShowEditModal(true);
    };

    const handleDeleteClick = (theme) => {
        setSelectedTheme(theme);
        setShowDeleteModal(true);
    };

    const handleConfirmDelete = () => {
        if (!selectedTheme) return;

        setDeleting(true);
        router.delete(route('color-themes.destroy', selectedTheme.id), {
            preserveScroll: true,
            onSuccess: () => {
                setShowDeleteModal(false);
                setSelectedTheme(null);
            },
            onFinish: () => {
                setDeleting(false);
            },
        });
    };

    const handleCancelDelete = () => {
        setShowDeleteModal(false);
        setSelectedTheme(null);
    };

    const closeModals = () => {
        setShowCreateModal(false);
        setShowEditModal(false);
        setSelectedTheme(null);
    };

    const columns = [
        {
            label: 'Cor',
            field: 'name',
            sortable: true,
            render: (theme) => (
                <div className="flex items-center space-x-3">
                    <div
                        className="w-12 h-12 rounded-lg shadow-sm flex items-center justify-center border border-gray-200"
                        style={{ backgroundColor: theme.hex_color || '#6B7280' }}
                        title={theme.hex_color}
                    >
                        <span className={`text-xs font-bold ${getTextColor(theme.hex_color)}`}>
                            {theme.name.substring(0, 2).toUpperCase()}
                        </span>
                    </div>
                    <div>
                        <div className="text-sm font-medium text-gray-900">{theme.name}</div>
                        <div className="text-xs text-gray-500 font-mono">{theme.hex_color}</div>
                    </div>
                </div>
            )
        },
        {
            label: 'Classe CSS',
            field: 'color_class',
            sortable: true,
            render: (theme) => (
                <code className="px-2 py-1 bg-gray-100 rounded text-sm text-gray-700 font-mono">
                    {theme.color_class}
                </code>
            )
        },
        {
            label: 'Em Uso',
            field: 'usage_count',
            sortable: false,
            render: (theme) => (
                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                    theme.usage_count > 0
                        ? 'bg-green-100 text-green-800'
                        : 'bg-gray-100 text-gray-600'
                }`}>
                    {theme.usage_count} {theme.usage_count === 1 ? 'nivel' : 'niveis'}
                </span>
            )
        },
        {
            label: 'Acoes',
            field: 'actions',
            sortable: false,
            render: (theme) => (
                <div className="flex items-center space-x-2">
                    {hasPermission(PERMISSIONS.EDIT_USERS) && (
                        <Button
                            onClick={(e) => {
                                e.stopPropagation();
                                handleEditClick(theme);
                            }}
                            variant="warning"
                            size="sm"
                            iconOnly={true}
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            )}
                            title="Editar tema de cor"
                        />
                    )}
                    {hasPermission(PERMISSIONS.DELETE_USERS) && (
                        <Button
                            onClick={(e) => {
                                e.stopPropagation();
                                handleDeleteClick(theme);
                            }}
                            variant="danger"
                            size="sm"
                            iconOnly={true}
                            disabled={theme.usage_count > 0}
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            )}
                            title={theme.usage_count > 0 ? 'Nao pode excluir - cor em uso' : 'Excluir tema de cor'}
                        />
                    )}
                </div>
            )
        }
    ];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Gerenciamento de Cores" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Temas de Cores
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie as cores disponiveis para os niveis de acesso
                                </p>
                            </div>
                            <div className="flex gap-3">
                                {hasPermission(PERMISSIONS.CREATE_USERS) && (
                                    <Button
                                        onClick={() => setShowCreateModal(true)}
                                        variant="primary"
                                        icon={({ className }) => (
                                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                        )}
                                    >
                                        Nova Cor
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
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                                    </svg>
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm font-medium text-gray-500">Total de Cores</p>
                                    <p className="text-2xl font-bold text-gray-900">{stats.total || 0}</p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                            <div className="flex items-center">
                                <div className="p-2 bg-green-100 rounded-lg">
                                    <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm font-medium text-gray-500">Em Uso</p>
                                    <p className="text-2xl font-bold text-gray-900">{stats.in_use || 0}</p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                            <div className="flex items-center">
                                <div className="p-2 bg-gray-100 rounded-lg">
                                    <svg className="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm font-medium text-gray-500">Disponiveis</p>
                                    <p className="text-2xl font-bold text-gray-900">{(stats.total || 0) - (stats.in_use || 0)}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Preview de todas as cores */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <h3 className="text-sm font-medium text-gray-700 mb-3">Paleta de Cores Cadastradas</h3>
                        <div className="flex flex-wrap gap-2">
                            {colorThemes.data && colorThemes.data.map((theme) => (
                                <div
                                    key={theme.id}
                                    className={`px-3 py-1.5 rounded-full text-xs font-medium cursor-pointer transition-transform hover:scale-105 ${getTextColor(theme.hex_color)}`}
                                    style={{ backgroundColor: theme.hex_color }}
                                    title={`${theme.name} (${theme.hex_color})`}
                                    onClick={() => handleEditClick(theme)}
                                >
                                    {theme.name}
                                </div>
                            ))}
                            {(!colorThemes.data || colorThemes.data.length === 0) && (
                                <p className="text-sm text-gray-500">Nenhuma cor cadastrada ainda.</p>
                            )}
                        </div>
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={colorThemes}
                        columns={columns}
                        searchPlaceholder="Pesquisar por nome, classe ou codigo hex..."
                        emptyMessage="Nenhum tema de cor encontrado"
                        perPageOptions={[10, 25, 50]}
                    />
                </div>
            </div>

            {/* Modal de Criacao */}
            <ColorThemeFormModal
                show={showCreateModal}
                onClose={closeModals}
                colorPalette={colorPalette}
            />

            {/* Modal de Edicao */}
            <ColorThemeFormModal
                show={showEditModal}
                onClose={closeModals}
                colorTheme={selectedTheme}
                colorPalette={colorPalette}
            />

            {/* Modal de Confirmacao de Exclusao */}
            <Modal
                show={showDeleteModal}
                onClose={handleCancelDelete}
                title="Confirmar Exclusao"
                maxWidth="md"
            >
                <div className="p-6">
                    <div className="flex items-start">
                        <div className="flex-shrink-0">
                            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                                <svg className="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                        </div>
                        <div className="ml-4">
                            <h3 className="text-lg font-medium text-gray-900">
                                Excluir Tema de Cor
                            </h3>
                            <div className="mt-2">
                                <p className="text-sm text-gray-500">
                                    Tem certeza que deseja excluir o tema de cor <strong>{selectedTheme?.name}</strong>?
                                    Esta acao nao pode ser desfeita.
                                </p>
                                {selectedTheme && (
                                    <div className="mt-3 flex items-center gap-2">
                                        <div
                                            className="w-8 h-8 rounded-lg border"
                                            style={{ backgroundColor: selectedTheme.hex_color }}
                                        />
                                        <code className="text-sm text-gray-600">{selectedTheme.hex_color}</code>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end space-x-3">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={handleCancelDelete}
                            disabled={deleting}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="danger"
                            onClick={handleConfirmDelete}
                            disabled={deleting}
                        >
                            {deleting ? 'Excluindo...' : 'Excluir'}
                        </Button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
