import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import GenericDetailModal from '@/Components/GenericDetailModal';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ auth, pageGroups = { data: [], links: [] }, stats = {}, filters = {} }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [showViewModal, setShowViewModal] = useState(false);
    const [selectedGroup, setSelectedGroup] = useState(null);
    const [selectedGroupId, setSelectedGroupId] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const { hasPermission } = usePermissions();

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
    });

    const handleCreateClick = () => {
        reset();
        setShowCreateModal(true);
    };

    const handleEditClick = (group) => {
        setSelectedGroup(group);
        setData('name', group.name);
        setShowEditModal(true);
    };

    const handleViewClick = (groupId) => {
        setSelectedGroupId(groupId);
        setShowViewModal(true);
    };

    const handleDeleteClick = (group) => {
        setSelectedGroup(group);
        setShowDeleteModal(true);
    };

    const handleSubmitCreate = (e) => {
        e.preventDefault();
        post(route('page-groups.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setShowCreateModal(false);
            },
        });
    };

    const handleSubmitEdit = (e) => {
        e.preventDefault();
        put(route('page-groups.update', selectedGroup.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setShowEditModal(false);
                setSelectedGroup(null);
            },
        });
    };

    const handleConfirmDelete = () => {
        if (!selectedGroup) return;

        setDeleting(true);
        router.delete(route('page-groups.destroy', selectedGroup.id), {
            preserveScroll: true,
            onSuccess: () => {
                setShowDeleteModal(false);
                setSelectedGroup(null);
            },
            onFinish: () => {
                setDeleting(false);
            },
        });
    };

    const handleCancelDelete = () => {
        setShowDeleteModal(false);
        setSelectedGroup(null);
    };

    const closeModals = () => {
        reset();
        setShowCreateModal(false);
        setShowEditModal(false);
        setSelectedGroup(null);
    };

    const modalSections = [
        {
            title: 'Informacoes do Grupo',
            fields: [
                { name: 'id', label: 'ID' },
                { name: 'name', label: 'Nome' },
                { name: 'pages_count', label: 'Paginas Vinculadas' },
            ],
        },
        {
            title: 'Datas',
            fields: [
                { name: 'created_at', label: 'Criado em', type: 'datetime' },
                { name: 'updated_at', label: 'Atualizado em', type: 'datetime' },
            ],
        },
    ];

    const columns = [
        {
            label: 'Nome',
            field: 'name',
            sortable: true,
            render: (group) => (
                <div className="flex items-center space-x-3">
                    <div className="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <svg className="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <span className="text-sm font-medium text-gray-900">{group.name}</span>
                </div>
            )
        },
        {
            label: 'Paginas',
            field: 'pages_count',
            sortable: true,
            render: (group) => (
                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                    group.pages_count > 0
                        ? 'bg-green-100 text-green-800'
                        : 'bg-gray-100 text-gray-600'
                }`}>
                    {group.pages_count} {group.pages_count === 1 ? 'pagina' : 'paginas'}
                </span>
            )
        },
        {
            label: 'Criado em',
            field: 'created_at',
            sortable: true,
            render: (group) => new Date(group.created_at).toLocaleDateString('pt-BR')
        },
        {
            label: 'Acoes',
            field: 'actions',
            sortable: false,
            render: (group) => (
                <div className="flex items-center space-x-2">
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            handleViewClick(group.id);
                        }}
                        variant="secondary"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        )}
                        title="Ver detalhes"
                    />
                    {hasPermission(PERMISSIONS.EDIT_USERS) && (
                        <Button
                            onClick={(e) => {
                                e.stopPropagation();
                                handleEditClick(group);
                            }}
                            variant="warning"
                            size="sm"
                            iconOnly={true}
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            )}
                            title="Editar grupo"
                        />
                    )}
                    {hasPermission(PERMISSIONS.DELETE_USERS) && (
                        <Button
                            onClick={(e) => {
                                e.stopPropagation();
                                handleDeleteClick(group);
                            }}
                            variant="danger"
                            size="sm"
                            iconOnly={true}
                            disabled={!group.can_delete}
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            )}
                            title={group.can_delete ? 'Excluir grupo' : 'Nao pode excluir - grupo em uso'}
                        />
                    )}
                </div>
            )
        }
    ];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Grupos de Paginas" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Grupos de Paginas
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie os grupos de categorizacao das paginas do sistema
                                </p>
                            </div>
                            <div className="flex gap-3">
                                {hasPermission(PERMISSIONS.CREATE_USERS) && (
                                    <Button
                                        onClick={handleCreateClick}
                                        variant="primary"
                                        icon={({ className }) => (
                                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                        )}
                                    >
                                        Novo Grupo
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
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm font-medium text-gray-500">Total de Grupos</p>
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
                                    <p className="text-sm font-medium text-gray-500">Com Paginas</p>
                                    <p className="text-2xl font-bold text-gray-900">{stats.with_pages || 0}</p>
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
                                    <p className="text-sm font-medium text-gray-500">Vazios</p>
                                    <p className="text-2xl font-bold text-gray-900">{stats.empty || 0}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={pageGroups}
                        columns={columns}
                        searchPlaceholder="Pesquisar por nome..."
                        emptyMessage="Nenhum grupo de paginas encontrado"
                        perPageOptions={[10, 25, 50]}
                    />
                </div>
            </div>

            {/* Modal de Criacao */}
            <Modal
                show={showCreateModal}
                onClose={closeModals}
                title="Criar Novo Grupo"
                maxWidth="md"
            >
                <form onSubmit={handleSubmitCreate} className="p-6">
                    <div className="mb-4">
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                            Nome do Grupo *
                        </label>
                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                errors.name ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                            }`}
                            placeholder="Ex: Relatorios"
                            autoFocus
                        />
                        {errors.name && (
                            <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                        )}
                    </div>

                    <div className="flex justify-end space-x-3">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={closeModals}
                            disabled={processing}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={processing || !data.name}
                        >
                            {processing ? 'Criando...' : 'Criar Grupo'}
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Modal de Edicao */}
            <Modal
                show={showEditModal}
                onClose={closeModals}
                title="Editar Grupo"
                maxWidth="md"
            >
                <form onSubmit={handleSubmitEdit} className="p-6">
                    <div className="mb-4">
                        <label htmlFor="edit_name" className="block text-sm font-medium text-gray-700 mb-1">
                            Nome do Grupo *
                        </label>
                        <input
                            id="edit_name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                errors.name ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                            }`}
                            placeholder="Ex: Relatorios"
                            autoFocus
                        />
                        {errors.name && (
                            <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                        )}
                    </div>

                    <div className="flex justify-end space-x-3">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={closeModals}
                            disabled={processing}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={processing || !data.name}
                        >
                            {processing ? 'Salvando...' : 'Salvar Alteracoes'}
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Modal de Visualizacao */}
            <GenericDetailModal
                show={showViewModal}
                onClose={() => setShowViewModal(false)}
                title="Detalhes do Grupo"
                resourceId={selectedGroupId}
                fetchUrl="/page-groups"
                sections={modalSections}
                header={{
                    avatar: () => (
                        <div className="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center">
                            <svg className="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                    ),
                    title: (data) => data.name,
                    subtitle: (data) => `${data.pages_count} pagina(s) vinculada(s)`,
                }}
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
                                Excluir Grupo de Paginas
                            </h3>
                            <div className="mt-2">
                                <p className="text-sm text-gray-500">
                                    Tem certeza que deseja excluir o grupo <strong>{selectedGroup?.name}</strong>?
                                    Esta acao nao pode ser desfeita.
                                </p>
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
