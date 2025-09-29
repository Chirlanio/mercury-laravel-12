import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

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
        notes: '',
        icon: '',
        page_group_id: '',
        is_public: false,
        is_active: true,
    });

    const handleToggleStatus = async (pageId, currentStatus) => {
        const action = currentStatus ? 'desativar' : 'ativar';
        if (confirm(`Tem certeza que deseja ${action} esta página?`)) {
            setProcessing(true);

            const url = currentStatus ? `/pages/${pageId}/deactivate` : `/pages/${pageId}/activate`;

            router.post(url, {}, {
                onFinish: () => setProcessing(false),
                preserveScroll: true,
            });
        }
    };

    const handleTogglePublic = async (pageId, currentPublic) => {
        const action = currentPublic ? 'tornar privada' : 'tornar pública';
        if (confirm(`Tem certeza que deseja ${action} esta página?`)) {
            setProcessing(true);

            const url = currentPublic ? `/pages/${pageId}/make-private` : `/pages/${pageId}/make-public`;

            router.post(url, {}, {
                onFinish: () => setProcessing(false),
                preserveScroll: true,
            });
        }
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
                    <button
                        onClick={() => handleViewPage(page.id)}
                        className="text-indigo-600 hover:text-indigo-900 text-sm"
                        title="Ver detalhes"
                    >
                        Ver
                    </button>
                    <button
                        onClick={() => handleEditPage(page.id)}
                        className="text-blue-600 hover:text-blue-900 text-sm"
                        title="Editar página"
                    >
                        Editar
                    </button>
                    <button
                        onClick={() => handleToggleStatus(page.id, page.is_active)}
                        disabled={processing}
                        className={`text-sm ${
                            page.is_active
                                ? 'text-red-600 hover:text-red-900'
                                : 'text-green-600 hover:text-green-900'
                        }`}
                        title={page.is_active ? 'Desativar' : 'Ativar'}
                    >
                        {page.is_active ? 'Desativar' : 'Ativar'}
                    </button>
                    <button
                        onClick={() => handleTogglePublic(page.id, page.is_public)}
                        disabled={processing}
                        className={`text-sm ${
                            page.is_public
                                ? 'text-orange-600 hover:text-orange-900'
                                : 'text-blue-600 hover:text-blue-900'
                        }`}
                        title={page.is_public ? 'Tornar privada' : 'Tornar pública'}
                    >
                        {page.is_public ? 'Tornar privada' : 'Tornar pública'}
                    </button>
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
            <Head title="Páginas do Sistema" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-start">
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900">
                                    Páginas do Sistema
                                </h2>
                                <p className="mt-1 text-sm text-gray-600">
                                    Lista de todas as páginas cadastradas no sistema, organizadas por grupo e funcionalidade.
                                </p>
                            </div>
                            <button
                                onClick={() => setShowCreateModal(true)}
                                className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
                            >
                                <svg className="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Nova Página
                            </button>
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
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Páginas por Grupo
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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

            {/* Modal de Criação */}
            {showCreateModal && (
                <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" onClick={handleCancelCreate}>
                    <div className="relative top-10 mx-auto p-6 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 max-h-screen shadow-lg rounded-md bg-white" onClick={(e) => e.stopPropagation()}>
                        {/* Header do Modal */}
                        <div className="flex justify-between items-center pb-4 border-b border-gray-200">
                            <h3 className="text-lg font-semibold text-gray-900">
                                Cadastrar Nova Página
                            </h3>
                            <button
                                onClick={handleCancelCreate}
                                className="text-gray-400 hover:text-gray-600"
                            >
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {/* Conteúdo do Modal */}
                        <div className="mt-4 max-h-[calc(100vh-200px)] overflow-y-auto">
                            <form onSubmit={handleCreateSubmit}>
                            {/* Informações Básicas */}
                            <div className="mb-6">
                                <h4 className="text-md font-medium text-gray-900 mb-3">
                                    Informações Básicas
                                </h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label htmlFor="page_name" className="block text-sm font-medium text-gray-700">
                                            Nome da Página *
                                        </label>
                                        <input
                                            id="page_name"
                                            type="text"
                                            value={data.page_name}
                                            onChange={(e) => setData('page_name', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Ex: Listar Usuários"
                                        />
                                        {errors.page_name && (
                                            <p className="mt-1 text-sm text-red-600">{errors.page_name}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label htmlFor="page_group_id" className="block text-sm font-medium text-gray-700">
                                            Grupo da Página *
                                        </label>
                                        <select
                                            id="page_group_id"
                                            value={data.page_group_id}
                                            onChange={(e) => setData('page_group_id', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Selecione um grupo</option>
                                            {Object.entries(pageGroups).map(([id, name]) => (
                                                <option key={id} value={id}>
                                                    {name}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.page_group_id && (
                                            <p className="mt-1 text-sm text-red-600">{errors.page_group_id}</p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Configurações de Rota */}
                            <div className="mb-6">
                                <h4 className="text-md font-medium text-gray-900 mb-3">
                                    Configurações de Rota
                                </h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label htmlFor="controller" className="block text-sm font-medium text-gray-700">
                                            Controller *
                                        </label>
                                        <input
                                            id="controller"
                                            type="text"
                                            value={data.controller}
                                            onChange={(e) => setData('controller', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Ex: UserController"
                                        />
                                        {errors.controller && (
                                            <p className="mt-1 text-sm text-red-600">{errors.controller}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label htmlFor="method" className="block text-sm font-medium text-gray-700">
                                            Método *
                                        </label>
                                        <input
                                            id="method"
                                            type="text"
                                            value={data.method}
                                            onChange={(e) => setData('method', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Ex: index"
                                        />
                                        {errors.method && (
                                            <p className="mt-1 text-sm text-red-600">{errors.method}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label htmlFor="menu_controller" className="block text-sm font-medium text-gray-700">
                                            Menu Controller
                                        </label>
                                        <input
                                            id="menu_controller"
                                            type="text"
                                            value={data.menu_controller}
                                            onChange={(e) => setData('menu_controller', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Ex: usuarios"
                                        />
                                        {errors.menu_controller && (
                                            <p className="mt-1 text-sm text-red-600">{errors.menu_controller}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label htmlFor="menu_method" className="block text-sm font-medium text-gray-700">
                                            Menu Método
                                        </label>
                                        <input
                                            id="menu_method"
                                            type="text"
                                            value={data.menu_method}
                                            onChange={(e) => setData('menu_method', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Ex: listar"
                                        />
                                        {errors.menu_method && (
                                            <p className="mt-1 text-sm text-red-600">{errors.menu_method}</p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Configurações Visuais */}
                            <div className="mb-6">
                                <h4 className="text-md font-medium text-gray-900 mb-3">
                                    Configurações Visuais
                                </h4>
                                <div className="space-y-4">
                                    <div>
                                        <label htmlFor="icon" className="block text-sm font-medium text-gray-700">
                                            Ícone
                                        </label>
                                        <input
                                            id="icon"
                                            type="text"
                                            value={data.icon}
                                            onChange={(e) => setData('icon', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Ex: fas fa-users"
                                        />
                                        {data.icon && (
                                            <div className="mt-2 flex items-center">
                                                <i className={data.icon}></i>
                                                <span className="ml-2 text-sm text-gray-600">Preview do ícone</span>
                                            </div>
                                        )}
                                        {errors.icon && (
                                            <p className="mt-1 text-sm text-red-600">{errors.icon}</p>
                                        )}

                                        {/* Sugestões de ícones */}
                                        <div className="mt-2">
                                            <p className="text-xs text-gray-500 mb-2">Sugestões:</p>
                                            <div className="grid grid-cols-8 gap-2 max-h-32 overflow-y-auto border border-gray-200 p-2 rounded">
                                                {iconSuggestions.map((iconClass) => (
                                                    <button
                                                        key={iconClass}
                                                        type="button"
                                                        onClick={() => setData('icon', iconClass)}
                                                        className="flex items-center justify-center w-8 h-8 border border-gray-300 rounded hover:bg-gray-50 hover:border-indigo-300 transition-colors"
                                                        title={iconClass}
                                                    >
                                                        <i className={`${iconClass} text-gray-600`}></i>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label htmlFor="notes" className="block text-sm font-medium text-gray-700">
                                            Observações
                                        </label>
                                        <textarea
                                            id="notes"
                                            rows={3}
                                            value={data.notes}
                                            onChange={(e) => setData('notes', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Descrição da funcionalidade da página..."
                                        />
                                        {errors.notes && (
                                            <p className="mt-1 text-sm text-red-600">{errors.notes}</p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Configurações de Acesso */}
                            <div className="mb-6">
                                <h4 className="text-md font-medium text-gray-900 mb-3">
                                    Configurações de Acesso
                                </h4>
                                <div className="space-y-3">
                                    <div className="flex items-center">
                                        <input
                                            id="is_public"
                                            type="checkbox"
                                            checked={data.is_public}
                                            onChange={(e) => setData('is_public', e.target.checked)}
                                            className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        />
                                        <label htmlFor="is_public" className="ml-2 block text-sm text-gray-700">
                                            Página Pública (acessível sem autenticação)
                                        </label>
                                    </div>

                                    <div className="flex items-center">
                                        <input
                                            id="is_active"
                                            type="checkbox"
                                            checked={data.is_active}
                                            onChange={(e) => setData('is_active', e.target.checked)}
                                            className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        />
                                        <label htmlFor="is_active" className="ml-2 block text-sm text-gray-700">
                                            Página Ativa
                                        </label>
                                    </div>
                                </div>
                            </div>

                                {/* Botões do Modal */}
                                <div className="flex justify-end space-x-3 pt-4 border-t border-gray-200 mt-6">
                                    <button
                                        type="button"
                                        onClick={handleCancelCreate}
                                        className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={formProcessing}
                                        className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        {formProcessing ? 'Salvando...' : 'Criar Página'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal de Visualização */}
            {showViewModal && selectedPage && (
                <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" onClick={handleCloseViewModal}>
                    <div className="relative top-10 mx-auto p-6 border w-11/12 md:w-4/5 lg:w-3/4 xl:w-2/3 max-h-screen shadow-lg rounded-md bg-white" onClick={(e) => e.stopPropagation()}>
                        {/* Header do Modal */}
                        <div className="flex justify-between items-center pb-3 border-b border-gray-200">
                            <h3 className="text-lg font-semibold text-gray-900">
                                {selectedPage.page_name}
                            </h3>
                            <button
                                onClick={handleCloseViewModal}
                                className="text-gray-400 hover:text-gray-600"
                            >
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {/* Conteúdo do Modal */}
                        <div className="mt-4 max-h-[calc(100vh-200px)] overflow-y-auto">
                            <div className="space-y-6">
                                {/* Informações Básicas */}
                                <div>
                                    <h4 className="text-lg font-medium text-gray-900 mb-4">
                                        Informações Básicas
                                    </h4>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Nome da Página *
                                            </label>
                                            <div className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                                {selectedPage.page_name}
                                            </div>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Grupo da Página *
                                            </label>
                                            <div className="mt-1">
                                                <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getGroupBadge(selectedPage.page_group.name)}`}>
                                                    {selectedPage.page_group.name}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Configurações de Rota */}
                                <div>
                                    <h4 className="text-lg font-medium text-gray-900 mb-4">
                                        Configurações de Rota
                                    </h4>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Controller *
                                            </label>
                                            <div className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2 text-sm font-mono text-gray-900">
                                                {selectedPage.controller}
                                            </div>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Método *
                                            </label>
                                            <div className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2 text-sm font-mono text-gray-900">
                                                {selectedPage.method}
                                            </div>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Menu Controller
                                            </label>
                                            <div className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2 text-sm font-mono text-gray-900">
                                                {selectedPage.menu_controller || 'N/A'}
                                            </div>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Menu Método
                                            </label>
                                            <div className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2 text-sm font-mono text-gray-900">
                                                {selectedPage.menu_method || 'N/A'}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Configurações Visuais */}
                                <div>
                                    <h4 className="text-lg font-medium text-gray-900 mb-4">
                                        Configurações Visuais
                                    </h4>
                                    <div className="space-y-4">
                                        {selectedPage.icon && (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">
                                                    Ícone
                                                </label>
                                                <div className="mt-1 flex items-center space-x-3 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2">
                                                    <i className={selectedPage.icon}></i>
                                                    <span className="text-sm font-mono text-gray-600">{selectedPage.icon}</span>
                                                </div>
                                            </div>
                                        )}

                                        {selectedPage.notes && (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">
                                                    Observações
                                                </label>
                                                <div className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2 min-h-[100px]">
                                                    <div
                                                        className="text-sm text-gray-700 prose prose-sm max-w-none"
                                                        dangerouslySetInnerHTML={{ __html: selectedPage.notes }}
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Configurações de Acesso */}
                                <div>
                                    <h4 className="text-lg font-medium text-gray-900 mb-4">
                                        Configurações de Acesso
                                    </h4>
                                    <div className="space-y-4">
                                        <div className="flex items-center space-x-3">
                                            <div className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded flex items-center justify-center">
                                                {selectedPage.is_public && <i className="fas fa-check text-xs text-indigo-600"></i>}
                                            </div>
                                            <label className="text-sm text-gray-700">
                                                Página Pública (acessível sem autenticação)
                                            </label>
                                            <span className={`ml-auto px-2 py-1 text-xs font-semibold rounded-full ${
                                                selectedPage.is_public
                                                    ? 'bg-blue-100 text-blue-800'
                                                    : 'bg-orange-100 text-orange-800'
                                            }`}>
                                                {selectedPage.is_public ? 'Sim' : 'Não'}
                                            </span>
                                        </div>

                                        <div className="flex items-center space-x-3">
                                            <div className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded flex items-center justify-center">
                                                {selectedPage.is_active && <i className="fas fa-check text-xs text-indigo-600"></i>}
                                            </div>
                                            <label className="text-sm text-gray-700">
                                                Página Ativa
                                            </label>
                                            <span className={`ml-auto px-2 py-1 text-xs font-semibold rounded-full ${
                                                selectedPage.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                            }`}>
                                                {selectedPage.is_active ? 'Sim' : 'Não'}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {/* Níveis de Acesso */}
                                {selectedPage.access_levels && selectedPage.access_levels.length > 0 && (
                                    <div>
                                        <h4 className="text-lg font-medium text-gray-900 mb-4">
                                            Níveis de Acesso Autorizados
                                        </h4>
                                        <div className="space-y-3">
                                            {selectedPage.access_levels.map((accessLevel, index) => (
                                                <div key={index} className="bg-gray-50 rounded-md border border-gray-300 p-4">
                                                    <div className="flex items-center justify-between">
                                                        <div>
                                                            <h5 className="text-sm font-medium text-gray-900">{accessLevel.name}</h5>
                                                            <div className="mt-1 flex space-x-4 text-xs text-gray-500">
                                                                <span>Ordem: {accessLevel.order}</span>
                                                                {accessLevel.dropdown && <span>Dropdown</span>}
                                                                {accessLevel.lib_menu && <span>Menu Liberado</span>}
                                                            </div>
                                                        </div>
                                                        <span className={`px-2 py-1 text-xs font-semibold rounded-full ${
                                                            accessLevel.permission
                                                                ? 'bg-green-100 text-green-800'
                                                                : 'bg-red-100 text-red-800'
                                                        }`}>
                                                            {accessLevel.permission ? 'Permitido' : 'Negado'}
                                                        </span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Informações do Sistema */}
                                <div>
                                    <h4 className="text-lg font-medium text-gray-900 mb-4">
                                        Informações do Sistema
                                    </h4>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                ID
                                            </label>
                                            <div className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2 text-sm font-mono text-gray-900">
                                                #{selectedPage.id}
                                            </div>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Níveis com Acesso
                                            </label>
                                            <div className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                                {selectedPage.access_level_count || 0}
                                            </div>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Rota
                                            </label>
                                            <div className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2 text-sm font-mono text-gray-900 break-all">
                                                {selectedPage.route}
                                            </div>
                                        </div>

                                        {selectedPage.menu_route && (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">
                                                    Rota do Menu
                                                </label>
                                                <div className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2 text-sm font-mono text-gray-900 break-all">
                                                    {selectedPage.menu_route}
                                                </div>
                                            </div>
                                        )}

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Criado em
                                            </label>
                                            <div className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                                {new Date(selectedPage.created_at).toLocaleString('pt-BR')}
                                            </div>
                                        </div>

                                        {selectedPage.updated_at && (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">
                                                    Atualizado em
                                                </label>
                                                <div className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                                    {new Date(selectedPage.updated_at).toLocaleString('pt-BR')}
                                                </div>
                                            </div>
                                        )}

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Acessível a Todos
                                            </label>
                                            <div className="mt-1">
                                                <span className={`px-2 py-1 text-xs font-semibold rounded-full ${
                                                    selectedPage.is_accessible_to_all
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-red-100 text-red-800'
                                                }`}>
                                                    {selectedPage.is_accessible_to_all ? 'Sim' : 'Não'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal de Edição */}
            {showEditModal && selectedPage && (
                <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" onClick={handleCancelEdit}>
                    <div className="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-lg rounded-md bg-white" onClick={(e) => e.stopPropagation()}>
                        {/* Header do Modal */}
                        <div className="flex justify-between items-center pb-3 border-b border-gray-200">
                            <h3 className="text-lg font-semibold text-gray-900">
                                Editar Página
                            </h3>
                            <button
                                onClick={handleCancelEdit}
                                className="text-gray-400 hover:text-gray-600"
                            >
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {/* Conteúdo do Modal */}
                        <div className="mt-4 max-h-[calc(100vh-200px)] overflow-y-auto">
                            <form onSubmit={handleEditSubmit}>
                                {/* Informações Básicas */}
                                <div className="mb-6">
                                    <h4 className="text-md font-medium text-gray-900 mb-3">
                                        Informações Básicas
                                    </h4>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label htmlFor="edit_page_name" className="block text-sm font-medium text-gray-700">
                                                Nome da Página *
                                            </label>
                                            <input
                                                id="edit_page_name"
                                                type="text"
                                                value={editData.page_name}
                                                onChange={(e) => setEditData('page_name', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Ex: Listar Usuários"
                                            />
                                            {editErrors.page_name && (
                                                <p className="mt-1 text-sm text-red-600">{editErrors.page_name}</p>
                                            )}
                                        </div>

                                        <div>
                                            <label htmlFor="edit_page_group_id" className="block text-sm font-medium text-gray-700">
                                                Grupo da Página *
                                            </label>
                                            <select
                                                id="edit_page_group_id"
                                                value={editData.page_group_id}
                                                onChange={(e) => setEditData('page_group_id', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="">Selecione um grupo</option>
                                                {Object.entries(pageGroups).map(([id, name]) => (
                                                    <option key={id} value={id}>
                                                        {name}
                                                    </option>
                                                ))}
                                            </select>
                                            {editErrors.page_group_id && (
                                                <p className="mt-1 text-sm text-red-600">{editErrors.page_group_id}</p>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {/* Configurações de Rota */}
                                <div className="mb-6">
                                    <h4 className="text-md font-medium text-gray-900 mb-3">
                                        Configurações de Rota
                                    </h4>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label htmlFor="edit_controller" className="block text-sm font-medium text-gray-700">
                                                Controller *
                                            </label>
                                            <input
                                                id="edit_controller"
                                                type="text"
                                                value={editData.controller}
                                                onChange={(e) => setEditData('controller', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Ex: UserController"
                                            />
                                            {editErrors.controller && (
                                                <p className="mt-1 text-sm text-red-600">{editErrors.controller}</p>
                                            )}
                                        </div>

                                        <div>
                                            <label htmlFor="edit_method" className="block text-sm font-medium text-gray-700">
                                                Método *
                                            </label>
                                            <input
                                                id="edit_method"
                                                type="text"
                                                value={editData.method}
                                                onChange={(e) => setEditData('method', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Ex: index"
                                            />
                                            {editErrors.method && (
                                                <p className="mt-1 text-sm text-red-600">{editErrors.method}</p>
                                            )}
                                        </div>

                                        <div>
                                            <label htmlFor="edit_menu_controller" className="block text-sm font-medium text-gray-700">
                                                Menu Controller
                                            </label>
                                            <input
                                                id="edit_menu_controller"
                                                type="text"
                                                value={editData.menu_controller}
                                                onChange={(e) => setEditData('menu_controller', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Ex: usuarios"
                                            />
                                            {editErrors.menu_controller && (
                                                <p className="mt-1 text-sm text-red-600">{editErrors.menu_controller}</p>
                                            )}
                                        </div>

                                        <div>
                                            <label htmlFor="edit_menu_method" className="block text-sm font-medium text-gray-700">
                                                Menu Método
                                            </label>
                                            <input
                                                id="edit_menu_method"
                                                type="text"
                                                value={editData.menu_method}
                                                onChange={(e) => setEditData('menu_method', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Ex: listar"
                                            />
                                            {editErrors.menu_method && (
                                                <p className="mt-1 text-sm text-red-600">{editErrors.menu_method}</p>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {/* Configurações Visuais */}
                                <div className="mb-6">
                                    <h4 className="text-md font-medium text-gray-900 mb-3">
                                        Configurações Visuais
                                    </h4>
                                    <div className="space-y-4">
                                        <div>
                                            <label htmlFor="edit_icon" className="block text-sm font-medium text-gray-700">
                                                Ícone
                                            </label>
                                            <input
                                                id="edit_icon"
                                                type="text"
                                                value={editData.icon}
                                                onChange={(e) => setEditData('icon', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Ex: fas fa-users"
                                            />
                                            {editData.icon && (
                                                <div className="mt-2 flex items-center">
                                                    <i className={editData.icon}></i>
                                                    <span className="ml-2 text-sm text-gray-600">Preview do ícone</span>
                                                </div>
                                            )}
                                            {editErrors.icon && (
                                                <p className="mt-1 text-sm text-red-600">{editErrors.icon}</p>
                                            )}

                                            {/* Sugestões de ícones */}
                                            <div className="mt-2">
                                                <p className="text-xs text-gray-500 mb-2">Sugestões:</p>
                                                <div className="grid grid-cols-8 gap-2 max-h-32 overflow-y-auto border border-gray-200 p-2 rounded">
                                                    {iconSuggestions.map((iconClass) => (
                                                        <button
                                                            key={iconClass}
                                                            type="button"
                                                            onClick={() => setEditData('icon', iconClass)}
                                                            className="flex items-center justify-center w-8 h-8 border border-gray-300 rounded hover:bg-gray-50 hover:border-indigo-300 transition-colors"
                                                            title={iconClass}
                                                        >
                                                            <i className={`${iconClass} text-gray-600`}></i>
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <label htmlFor="edit_notes" className="block text-sm font-medium text-gray-700">
                                                Observações
                                            </label>
                                            <textarea
                                                id="edit_notes"
                                                rows={3}
                                                value={editData.notes}
                                                onChange={(e) => setEditData('notes', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="Descrição da funcionalidade da página..."
                                            />
                                            {editErrors.notes && (
                                                <p className="mt-1 text-sm text-red-600">{editErrors.notes}</p>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {/* Configurações de Acesso */}
                                <div className="mb-6">
                                    <h4 className="text-md font-medium text-gray-900 mb-3">
                                        Configurações de Acesso
                                    </h4>
                                    <div className="space-y-3">
                                        <div className="flex items-center">
                                            <input
                                                id="edit_is_public"
                                                type="checkbox"
                                                checked={editData.is_public}
                                                onChange={(e) => setEditData('is_public', e.target.checked)}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <label htmlFor="edit_is_public" className="ml-2 block text-sm text-gray-700">
                                                Página Pública (acessível sem autenticação)
                                            </label>
                                        </div>

                                        <div className="flex items-center">
                                            <input
                                                id="edit_is_active"
                                                type="checkbox"
                                                checked={editData.is_active}
                                                onChange={(e) => setEditData('is_active', e.target.checked)}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <label htmlFor="edit_is_active" className="ml-2 block text-sm text-gray-700">
                                                Página Ativa
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                {/* Botões do Modal */}
                                <div className="flex justify-end space-x-3 pt-4 border-t border-gray-200 mt-6">
                                    <button
                                        type="button"
                                        onClick={handleCancelEdit}
                                        className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={editProcessing}
                                        className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        {editProcessing ? 'Salvando...' : 'Salvar Alterações'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}