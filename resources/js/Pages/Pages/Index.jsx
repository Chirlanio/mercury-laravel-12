import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ViewModal from '@/Components/Pages/ViewModal';
import CreateModal from '@/Components/Pages/CreateModal';
import EditModal from '@/Components/Pages/EditModal';
import { useConfirm } from '@/Hooks/useConfirm';

export default function Index({
    auth,
    pages = { data: [], links: [] },
    pageGroups = {},
    groupedPages = {},
    crudPages = {},
    controllerMethods = {},
    filters = {},
    stats = {}
}) {
    const { confirm, ConfirmDialogComponent } = useConfirm();
    const [processing, setProcessing] = useState(false);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showViewModal, setShowViewModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [selectedPage, setSelectedPage] = useState(null);

    const { data, setData, post, processing: formProcessing, errors, reset } = useForm({
        page_name: '',
        controller: '',
        method: '',
        menu_controller: '',
        menu_method: '',
        route: '',
        notes: '',
        icon: '',
        page_group_id: '',
        is_public: false,
        is_active: true,
    });

    const { data: editData, setData: setEditData, patch, processing: editProcessing, errors: editErrors, reset: resetEdit } = useForm({
        page_name: '',
        controller: '',
        method: '',
        menu_controller: '',
        menu_method: '',
        route: '',
        notes: '',
        icon: '',
        page_group_id: '',
        is_public: false,
        is_active: true,
    });

    const handleToggleStatus = async (pageId, currentStatus) => {
        const action = currentStatus ? 'desativar' : 'ativar';
        const actionText = currentStatus ? 'Desativar' : 'Ativar';

        const confirmed = await confirm({
            title: `${actionText} Página`,
            message: `Tem certeza que deseja ${action} esta página? ${currentStatus ? 'Usuários não poderão mais acessá-la.' : 'Usuários com permissão poderão acessá-la novamente.'}`,
            confirmText: `Sim, ${actionText}`,
            cancelText: 'Cancelar',
            type: currentStatus ? 'warning' : 'success',
        });

        if (!confirmed) return;

        setProcessing(true);

        const url = currentStatus ? `/pages/${pageId}/deactivate` : `/pages/${pageId}/activate`;

        router.post(url, {}, {
            onFinish: () => setProcessing(false),
            preserveScroll: true,
        });
    };

    const handleTogglePublic = async (pageId, currentPublic) => {
        const action = currentPublic ? 'tornar privada' : 'tornar pública';
        const actionText = currentPublic ? 'Tornar Privada' : 'Tornar Pública';

        const confirmed = await confirm({
            title: `${actionText}`,
            message: `Tem certeza que deseja ${action} esta página? ${currentPublic ? 'Apenas usuários autenticados poderão acessá-la.' : 'Esta página ficará acessível publicamente sem autenticação.'}`,
            confirmText: `Sim, ${actionText}`,
            cancelText: 'Cancelar',
            type: currentPublic ? 'info' : 'warning',
        });

        if (!confirmed) return;

        setProcessing(true);

        const url = currentPublic ? `/pages/${pageId}/make-private` : `/pages/${pageId}/make-public`;

        router.post(url, {}, {
            onFinish: () => setProcessing(false),
            preserveScroll: true,
        });
    };

    const getGroupBadge = (groupName) => {
        const colors = {
            'Listar': 'bg-blue-100 text-blue-800',
            'Cadastrar': 'bg-green-100 text-green-800',
            'Editar': 'bg-yellow-100 text-yellow-800',
            'Apagar': 'bg-red-100 text-red-800',
            'Visualizar': 'bg-purple-100 text-purple-800',
            'Outros': 'bg-gray-100 text-gray-800',
        };
        return colors[groupName] || 'bg-gray-100 text-gray-800';
    };

    const getStatusBadge = (isActive) => {
        return isActive
            ? { label: 'Ativa', color: 'bg-green-100 text-green-800' }
            : { label: 'Inativa', color: 'bg-red-100 text-red-800' };
    };

    const getAccessBadge = (isPublic) => {
        return isPublic
            ? { label: 'Pública', color: 'bg-blue-100 text-blue-800' }
            : { label: 'Privada', color: 'bg-orange-100 text-orange-800' };
    };

    const columns = [
        {
            label: 'Página',
            field: 'page_name',
            sortable: true,
            render: (page) => (
                <div className="flex items-center">
                    {page.icon && (
                        <span className="mr-3 text-gray-500 w-5 h-5 flex items-center justify-center">
                            <i className={page.icon}></i>
                        </span>
                    )}
                    <div>
                        <div className="text-sm font-medium text-gray-900">
                            {page.page_name}
                        </div>
                        <div className="text-xs text-gray-500">
                            {page.controller}@{page.method}
                        </div>
                    </div>
                </div>
            )
        },
        {
            label: 'Grupo',
            field: 'page_group.name',
            sortable: false,
            render: (page) => (
                <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getGroupBadge(page.page_group.name)}`}>
                    {page.page_group.name}
                </span>
            )
        },
        {
            label: 'Rota',
            field: 'route',
            sortable: false,
            render: (page) => (
                <div className="text-sm">
                    <div className="font-mono text-xs text-gray-600">
                        {page.route}
                    </div>
                    {page.menu_route && (
                        <div className="font-mono text-xs text-gray-400">
                            Menu: {page.menu_route}
                        </div>
                    )}
                </div>
            )
        },
        {
            label: 'Status',
            field: 'is_active',
            sortable: true,
            render: (page) => {
                const status = getStatusBadge(page.is_active);
                return (
                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${status.color}`}>
                        {status.label}
                    </span>
                );
            }
        },
        {
            label: 'Acesso',
            field: 'is_public',
            sortable: true,
            render: (page) => {
                const access = getAccessBadge(page.is_public);
                return (
                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${access.color}`}>
                        {access.label}
                    </span>
                );
            }
        },
        {
            label: 'Criado em',
            field: 'created_at',
            sortable: true,
            render: (page) => new Date(page.created_at).toLocaleDateString('pt-BR')
        },
        {
            label: 'Ações',
            field: 'actions',
            sortable: false,
            render: (page) => (
                <div className="flex items-center space-x-2">
                    <Button
                        onClick={() => handleViewPage(page.id)}
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
                    <Button
                        onClick={() => handleEditPage(page.id)}
                        variant="warning"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        )}
                        title="Editar página"
                    />
                    <Button
                        onClick={() => handleToggleStatus(page.id, page.is_active)}
                        disabled={processing}
                        variant={page.is_active ? 'danger' : 'success'}
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            page.is_active ? (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            ) : (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                            )
                        )}
                        title={page.is_active ? 'Desativar' : 'Ativar'}
                    />
                    <Button
                        onClick={() => handleTogglePublic(page.id, page.is_public)}
                        disabled={processing}
                        variant={page.is_public ? 'dark' : 'light'}
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            page.is_public ? (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                            ) : (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            )
                        )}
                        title={page.is_public ? 'Tornar privada' : 'Tornar pública'}
                    />
                </div>
            )
        }
    ];

    const filterOptions = [
        { value: '', label: 'Todos os grupos' },
        ...Object.entries(pageGroups).map(([id, name]) => ({
            value: id,
            label: name
        }))
    ];

    const statusOptions = [
        { value: '', label: 'Todos os status' },
        { value: '1', label: 'Ativas' },
        { value: '0', label: 'Inativas' }
    ];

    const accessOptions = [
        { value: '', label: 'Todos os acessos' },
        { value: '1', label: 'Públicas' },
        { value: '0', label: 'Privadas' }
    ];

    const handleCreateSubmit = (e) => {
        e.preventDefault();
        post(route('pages.store'), {
            onSuccess: () => {
                setShowCreateModal(false);
                reset();
            }
        });
    };

    const handleCancelCreate = () => {
        setShowCreateModal(false);
        reset();
    };

    const handleViewPage = async (pageId) => {
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const response = await fetch(`/pages/${pageId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const pageData = await response.json();
            setSelectedPage(pageData);
            setShowViewModal(true);
        } catch (error) {
            console.error('Erro ao carregar página:', error);
            alert('Erro ao carregar informações da página. Tente novamente.');
        }
    };

    const handleCloseViewModal = () => {
        setShowViewModal(false);
        setSelectedPage(null);
    };

    const handleEditPage = async (pageId) => {
        try {
            const response = await fetch(`/pages/${pageId}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const pageData = await response.json();
            setSelectedPage(pageData);

            // Preencher o formulário de edição com os dados da página
            setEditData({
                page_name: pageData.page_name,
                controller: pageData.controller,
                method: pageData.method,
                menu_controller: pageData.menu_controller || '',
                menu_method: pageData.menu_method || '',
                route: pageData.route || '',
                notes: pageData.notes || '',
                icon: pageData.icon || '',
                page_group_id: pageData.page_group.id,
                is_public: pageData.is_public,
                is_active: pageData.is_active,
            });

            setShowEditModal(true);
        } catch (error) {
            console.error('Erro ao carregar página:', error);
            alert('Erro ao carregar informações da página. Tente novamente.');
        }
    };

    const handleEditSubmit = (e) => {
        e.preventDefault();
        patch(`/pages/${selectedPage.id}`, {
            onSuccess: () => {
                setShowEditModal(false);
                setSelectedPage(null);
                resetEdit();
            }
        });
    };

    const handleCancelEdit = () => {
        setShowEditModal(false);
        setSelectedPage(null);
        resetEdit();
    };

    const iconSuggestions = [
        'fas fa-home',
        'fas fa-user',
        'fas fa-users',
        'fas fa-cogs',
        'fas fa-chart-bar',
        'fas fa-file-alt',
        'fas fa-edit',
        'fas fa-eye',
        'fas fa-trash',
        'fas fa-plus',
        'fas fa-search',
        'fas fa-download',
        'fas fa-upload',
        'fas fa-print',
        'fas fa-save',
        'fas fa-lock',
        'fas fa-unlock',
        'fas fa-key',
        'fas fa-shield-alt',
        'fas fa-database',
        'fas fa-folder',
        'fas fa-star',
        'fas fa-heart',
        'fas fa-bell',
    ];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Páginas" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-start">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Páginas
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie e visualize informações das páginas
                                </p>
                            </div>
                            <Button
                                variant="primary"
                                onClick={() => setShowCreateModal(true)}
                                icon={({ className }) => (
                                    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                )}
                            >
                                Nova Página
                            </Button>
                        </div>
                    </div>

                    {/* Estatísticas */}
                    <div className="mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                            <div className="bg-white rounded-lg shadow p-4">
                                <h3 className="text-sm font-medium text-gray-500 mb-2">
                                    Total de Páginas
                                </h3>
                                <div className="text-2xl font-bold text-gray-900">
                                    {stats.total || 0}
                                </div>
                            </div>
                            <div className="bg-white rounded-lg shadow p-4">
                                <h3 className="text-sm font-medium text-green-600 mb-2">
                                    Páginas Ativas
                                </h3>
                                <div className="text-2xl font-bold text-green-700">
                                    {stats.active || 0}
                                </div>
                            </div>
                            <div className="bg-white rounded-lg shadow p-4">
                                <h3 className="text-sm font-medium text-red-600 mb-2">
                                    Páginas Inativas
                                </h3>
                                <div className="text-2xl font-bold text-red-700">
                                    {stats.inactive || 0}
                                </div>
                            </div>
                            <div className="bg-white rounded-lg shadow p-4">
                                <h3 className="text-sm font-medium text-blue-600 mb-2">
                                    Páginas Públicas
                                </h3>
                                <div className="text-2xl font-bold text-blue-700">
                                    {stats.public || 0}
                                </div>
                            </div>
                            <div className="bg-white rounded-lg shadow p-4">
                                <h3 className="text-sm font-medium text-orange-600 mb-2">
                                    Páginas Privadas
                                </h3>
                                <div className="text-2xl font-bold text-orange-700">
                                    {stats.private || 0}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Resumo por grupo */}
                    <div className="mb-6">
                        <div className="bg-white rounded-lg shadow p-6">
                                <h3 className="text-2xl font-bold text-gray-900">
                                    Páginas por Grupo
                                </h3>                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                {Object.entries(groupedPages).map(([group, pagesList]) => (
                                    <div key={group} className="border rounded-lg p-4">
                                        <h4 className={`font-medium mb-2 ${getGroupBadge(group).replace('bg-', 'text-').replace('-100', '-600')}`}>
                                            {group}
                                        </h4>
                                        <div className="text-sm text-gray-600">
                                            {Object.keys(pagesList).length} {Object.keys(pagesList).length === 1 ? 'página' : 'páginas'}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* DataTable com filtros */}
                    <DataTable
                        data={pages}
                        columns={columns}
                        searchable={true}
                        searchPlaceholder="Buscar páginas..."
                        perPageOptions={[10, 25, 50, 100]}
                        emptyMessage="Nenhuma página encontrada"
                        filters={[
                            {
                                field: 'group_id',
                                label: 'Grupo',
                                type: 'select',
                                options: filterOptions,
                                value: filters.group_id || ''
                            },
                            {
                                field: 'is_active',
                                label: 'Status',
                                type: 'select',
                                options: statusOptions,
                                value: filters.is_active || ''
                            },
                            {
                                field: 'is_public',
                                label: 'Acesso',
                                type: 'select',
                                options: accessOptions,
                                value: filters.is_public || ''
                            }
                        ]}
                    />
                </div>
            </div>

            <CreateModal
                show={showCreateModal}
                onClose={handleCancelCreate}
                onSubmit={handleCreateSubmit}
                onCancel={handleCancelCreate}
                data={data}
                setData={setData}
                errors={errors}
                processing={formProcessing}
                pageGroups={pageGroups}
                iconSuggestions={iconSuggestions}
            />

            <ViewModal
                show={showViewModal}
                onClose={handleCloseViewModal}
                selectedPage={selectedPage}
                getGroupBadge={getGroupBadge}
            />

            <EditModal
                show={showEditModal}
                onClose={handleCancelEdit}
                onSubmit={handleEditSubmit}
                onCancel={handleCancelEdit}
                data={editData}
                setData={setEditData}
                errors={editErrors}
                processing={editProcessing}
                pageGroups={pageGroups}
                iconSuggestions={iconSuggestions}
            />

            {/* Dialog de Confirmação Personalizado */}
            <ConfirmDialogComponent />
        </AuthenticatedLayout>
    );
}