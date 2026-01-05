import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Button from '@/Components/Button';
import AccessLevelCreateModal from '@/Components/AccessLevelCreateModal';

export default function Index({ auth, accessLevels, categories, groupedAccessLevels, filters, stats, menus = [], colorThemes = [] }) {
    const { flash } = usePage().props;
    const [showViewModal, setShowViewModal] = useState(false);
    const [selectedAccessLevel, setSelectedAccessLevel] = useState(null);
    const [showMenuModal, setShowMenuModal] = useState(false);
    const [selectedAccessLevelForMenus, setSelectedAccessLevelForMenus] = useState(null);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [accessLevelToDelete, setAccessLevelToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [loadingAccessLevel, setLoadingAccessLevel] = useState(false);

    const handleDeleteClick = (accessLevel) => {
        setAccessLevelToDelete(accessLevel);
        setShowDeleteModal(true);
    };

    const handleConfirmDelete = () => {
        if (!accessLevelToDelete) return;

        setDeleting(true);
        router.delete(route('access-levels.destroy', accessLevelToDelete.id), {
            onSuccess: () => {
                setShowDeleteModal(false);
                setAccessLevelToDelete(null);
                setDeleting(false);
            },
            onError: () => {
                setDeleting(false);
            },
            preserveScroll: true,
        });
    };

    const handleCancelDelete = () => {
        setShowDeleteModal(false);
        setAccessLevelToDelete(null);
    };

    const columns = [
        {
            key: 'name',
            label: 'Nome',
            sortable: true,
            render: (accessLevel) => (
                <div className="flex items-center">
                    <div
                        className={`w-4 h-4 rounded-full mr-3 ${accessLevel.color_class || 'bg-gray-300'}`}
                        style={{ backgroundColor: accessLevel.color || '#6b7280' }}
                    ></div>
                    <span className="font-medium text-gray-900">{accessLevel.name}</span>
                </div>
            )
        },
        {
            key: 'order',
            label: 'Ordem',
            sortable: true,
            render: (accessLevel) => (
                <span className="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full font-medium">
                    {accessLevel.order}
                </span>
            )
        },
        {
            key: 'categories',
            label: 'Categorias',
            render: (accessLevel) => (
                <div className="flex flex-wrap gap-1">
                    {accessLevel.is_administrative && (
                        <span className="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Administrativo</span>
                    )}
                    {accessLevel.is_operational && (
                        <span className="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Operacional</span>
                    )}
                    {accessLevel.is_financial && (
                        <span className="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">Financeiro</span>
                    )}
                    {accessLevel.is_human_resources && (
                        <span className="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">RH</span>
                    )}
                    {accessLevel.is_commercial && (
                        <span className="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full">Comercial</span>
                    )}
                    {accessLevel.is_management && (
                        <span className="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Gestao</span>
                    )}
                    {accessLevel.is_super_admin && (
                        <span className="px-2 py-1 bg-gray-900 text-white text-xs rounded-full">Super Admin</span>
                    )}
                    {accessLevel.is_level_1 && (
                        <span className="px-2 py-1 bg-indigo-100 text-indigo-800 text-xs rounded-full">Nivel 1</span>
                    )}
                </div>
            )
        },
        {
            key: 'pages_access',
            label: 'Acesso as Paginas',
            render: (accessLevel) => (
                <div className="text-sm text-gray-600">
                    <span className="font-medium text-green-600">{accessLevel.authorized_pages_count}</span>
                    <span className="mx-1">/</span>
                    <span className="font-medium text-gray-900">{accessLevel.total_pages_count}</span>
                    <span className="ml-1 text-xs">paginas</span>
                </div>
            )
        },
        {
            key: 'created_at',
            label: 'Criado em',
            sortable: true,
            render: (accessLevel) => new Date(accessLevel.created_at).toLocaleDateString('pt-BR')
        },
        {
            key: 'actions',
            label: 'Acoes',
            render: (accessLevel) => (
                <div className="flex space-x-2">
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(route('access-levels.permissions', accessLevel.id));
                        }}
                        variant="success"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        )}
                        title="Gerenciar permissoes"
                    />
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            setSelectedAccessLevelForMenus(accessLevel);
                            setShowMenuModal(true);
                        }}
                        variant="primary"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        )}
                        title="Gerenciar menus"
                    />
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            handleViewAccessLevel(accessLevel.id);
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
                        title="Visualizar"
                    />
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            handleDeleteClick(accessLevel);
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
                </div>
            )
        }
    ];

    const handleViewAccessLevel = async (accessLevelId) => {
        setLoadingAccessLevel(true);
        setShowViewModal(true);

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(`/access-levels/${accessLevelId}`, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token
                }
            });

            if (response.ok) {
                const accessLevelData = await response.json();
                setSelectedAccessLevel(accessLevelData);
            } else {
                console.error('Erro ao carregar nivel de acesso');
                setShowViewModal(false);
            }
        } catch (error) {
            console.error('Erro ao carregar nivel de acesso:', error);
            setShowViewModal(false);
        } finally {
            setLoadingAccessLevel(false);
        }
    };

    const handleCloseViewModal = () => {
        setShowViewModal(false);
        setSelectedAccessLevel(null);
    };

    return (
        <AuthenticatedLayout user={auth?.user}>
            <Head title="Niveis de Acesso" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Flash Messages */}
                    {flash?.success && (
                        <div className="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                            {flash.success}
                        </div>
                    )}

                    {flash?.error && (
                        <div className="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            {flash.error}
                        </div>
                    )}

                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Niveis de Acesso
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie os niveis de acesso e permissoes do sistema
                                </p>
                            </div>
                            <div className="flex gap-3">
                                <Button
                                    variant="primary"
                                    onClick={() => setShowCreateModal(true)}
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    )}
                                >
                                    Novo Nivel de Acesso
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Statistics Cards */}
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                            <div className="text-xs font-medium text-gray-500 uppercase">Total</div>
                            <div className="text-2xl font-bold text-gray-900">{stats?.total || 0}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                            <div className="text-xs font-medium text-gray-500 uppercase">Administrativo</div>
                            <div className="text-2xl font-bold text-blue-600">{stats?.administrative || 0}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                            <div className="text-xs font-medium text-gray-500 uppercase">Operacional</div>
                            <div className="text-2xl font-bold text-green-600">{stats?.operational || 0}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                            <div className="text-xs font-medium text-gray-500 uppercase">Financeiro</div>
                            <div className="text-2xl font-bold text-yellow-600">{stats?.financial || 0}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                            <div className="text-xs font-medium text-gray-500 uppercase">RH</div>
                            <div className="text-2xl font-bold text-purple-600">{stats?.human_resources || 0}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                            <div className="text-xs font-medium text-gray-500 uppercase">Comercial</div>
                            <div className="text-2xl font-bold text-orange-600">{stats?.commercial || 0}</div>
                        </div>
                    </div>

                    {/* Filters */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            {/* Filtro de Categoria */}
                            <div>
                                <label htmlFor="category-filter" className="block text-sm font-medium text-gray-700 mb-2">
                                    Filtrar por Categoria
                                </label>
                                <select
                                    id="category-filter"
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={filters?.category || ''}
                                    onChange={(e) => {
                                        const currentUrl = new URL(window.location);
                                        if (e.target.value) {
                                            currentUrl.searchParams.set('category', e.target.value);
                                        } else {
                                            currentUrl.searchParams.delete('category');
                                        }
                                        currentUrl.searchParams.delete('page');
                                        router.visit(currentUrl.toString(), {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <option value="">Todas as categorias</option>
                                    <option value="administrative">Administrativo</option>
                                    <option value="operational">Operacional</option>
                                    <option value="financial">Financeiro</option>
                                    <option value="human_resources">Recursos Humanos</option>
                                    <option value="commercial">Comercial</option>
                                    <option value="management">Gestao</option>
                                </select>
                            </div>

                            {/* Spacer */}
                            <div></div>

                            {/* Botao Limpar Filtros */}
                            <div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="h-[42px] w-[150px]"
                                    onClick={() => {
                                        router.visit('/access-levels', {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                    disabled={!filters?.category}
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    )}
                                >
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={accessLevels}
                        columns={columns}
                        searchPlaceholder="Pesquisar niveis de acesso..."
                        emptyMessage="Nenhum nivel de acesso encontrado"
                        onRowClick={(accessLevel) => handleViewAccessLevel(accessLevel.id)}
                        perPageOptions={[15, 25, 50, 100]}
                    />
                </div>
            </div>

            {/* View Modal */}
            {showViewModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={handleCloseViewModal}></div>

                        <div className="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full" style={{ maxHeight: '90vh' }}>
                            {/* Loading State */}
                            {loadingAccessLevel && (
                                <div className="p-12 flex flex-col items-center justify-center">
                                    <svg className="animate-spin h-10 w-10 text-indigo-600 mb-4" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p className="text-gray-600">Carregando dados...</p>
                                </div>
                            )}

                            {/* Content */}
                            {!loadingAccessLevel && selectedAccessLevel && (
                                <>
                                    <div className="bg-white px-6 pt-6 pb-4">
                                        <div className="flex items-center justify-between border-b border-gray-200 pb-4">
                                            <h3 className="text-lg font-medium text-gray-900 flex items-center">
                                                <div
                                                    className={`w-4 h-4 rounded-full mr-3 ${selectedAccessLevel.color_class || 'bg-gray-300'}`}
                                                    style={{ backgroundColor: selectedAccessLevel.color || '#6b7280' }}
                                                ></div>
                                                {selectedAccessLevel.name}
                                            </h3>
                                            <button
                                                onClick={handleCloseViewModal}
                                                className="text-gray-400 hover:text-gray-600"
                                            >
                                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                            <div className="px-6 pb-6 overflow-y-auto" style={{ maxHeight: 'calc(90vh - 120px)' }}>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    {/* Informacoes Basicas */}
                                    <div className="space-y-4">
                                        <h4 className="font-medium text-gray-900 text-sm uppercase tracking-wide border-b border-gray-200 pb-2">
                                            Informacoes Basicas
                                        </h4>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                                            <p className="text-sm text-gray-900 bg-gray-50 px-3 py-2 rounded">{selectedAccessLevel.name}</p>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Ordem</label>
                                            <p className="text-sm text-gray-900 bg-gray-50 px-3 py-2 rounded">{selectedAccessLevel.order}</p>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Tema de Cor</label>
                                            {selectedAccessLevel.color_theme ? (
                                                <div className="flex items-center bg-gray-50 px-3 py-2 rounded">
                                                    <div
                                                        className="w-4 h-4 rounded-full mr-2"
                                                        style={{ backgroundColor: selectedAccessLevel.color_theme.color }}
                                                    ></div>
                                                    <span className="text-sm text-gray-900">{selectedAccessLevel.color_theme.name}</span>
                                                </div>
                                            ) : (
                                                <p className="text-sm text-gray-500 bg-gray-50 px-3 py-2 rounded">Nenhum tema definido</p>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Criado em</label>
                                            <p className="text-sm text-gray-900 bg-gray-50 px-3 py-2 rounded">
                                                {new Date(selectedAccessLevel.created_at).toLocaleString('pt-BR')}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Categorias e Caracteristicas */}
                                    <div className="space-y-4">
                                        <h4 className="font-medium text-gray-900 text-sm uppercase tracking-wide border-b border-gray-200 pb-2">
                                            Categorias e Caracteristicas
                                        </h4>

                                        <div className="space-y-3">
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Administrativo</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_administrative ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_administrative ? 'Sim' : 'Nao'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Operacional</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_operational ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_operational ? 'Sim' : 'Nao'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Financeiro</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_financial ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_financial ? 'Sim' : 'Nao'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Recursos Humanos</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_human_resources ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_human_resources ? 'Sim' : 'Nao'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Comercial</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_commercial ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_commercial ? 'Sim' : 'Nao'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Gestao</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_management ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_management ? 'Sim' : 'Nao'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Super Admin</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_super_admin ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_super_admin ? 'Sim' : 'Nao'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Nivel 1</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_level_1 ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_level_1 ? 'Sim' : 'Nao'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Paginas Autorizadas */}
                                {selectedAccessLevel.authorized_pages && selectedAccessLevel.authorized_pages.length > 0 && (
                                    <div className="mt-6">
                                        <h4 className="font-medium text-gray-900 text-sm uppercase tracking-wide border-b border-gray-200 pb-2 mb-4">
                                            Paginas Autorizadas ({selectedAccessLevel.authorized_pages.length})
                                        </h4>
                                        <div className="bg-gray-50 rounded-lg p-4 max-h-48 overflow-y-auto">
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                {selectedAccessLevel.authorized_pages.map((page) => (
                                                    <div key={page.id} className="bg-white rounded p-3 shadow-sm">
                                                        <div className="flex items-center justify-between">
                                                            <div>
                                                                <h5 className="font-medium text-sm text-gray-900">{page.page_name}</h5>
                                                                <p className="text-xs text-gray-500">{page.controller}@{page.method}</p>
                                                            </div>
                                                            <span className={`px-2 py-1 text-xs rounded-full ${
                                                                page.permission === 'read' ? 'bg-green-100 text-green-800' :
                                                                page.permission === 'write' ? 'bg-blue-100 text-blue-800' :
                                                                page.permission === 'admin' ? 'bg-red-100 text-red-800' :
                                                                'bg-gray-100 text-gray-800'
                                                            }`}>
                                                                {page.permission}
                                                            </span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Total de Paginas */}
                                {selectedAccessLevel.total_pages && selectedAccessLevel.total_pages.length > 0 && (
                                    <div className="mt-6">
                                        <h4 className="font-medium text-gray-900 text-sm uppercase tracking-wide border-b border-gray-200 pb-2 mb-4">
                                            Todas as Paginas ({selectedAccessLevel.total_pages.length})
                                        </h4>
                                        <div className="bg-gray-50 rounded-lg p-4 max-h-48 overflow-y-auto">
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                {selectedAccessLevel.total_pages.map((page) => (
                                                    <div key={page.id} className="bg-white rounded p-3 shadow-sm">
                                                        <div className="flex items-center justify-between">
                                                            <div>
                                                                <h5 className="font-medium text-sm text-gray-900">{page.page_name}</h5>
                                                                <p className="text-xs text-gray-500">{page.controller}@{page.method}</p>
                                                            </div>
                                                            <span className={`px-2 py-1 text-xs rounded-full ${
                                                                page.permission === 'read' ? 'bg-green-100 text-green-800' :
                                                                page.permission === 'write' ? 'bg-blue-100 text-blue-800' :
                                                                page.permission === 'admin' ? 'bg-red-100 text-red-800' :
                                                                'bg-gray-100 text-gray-800'
                                                            }`}>
                                                                {page.permission}
                                                            </span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                )}
                                </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Menu Selection Modal */}
            {showMenuModal && selectedAccessLevelForMenus && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={() => setShowMenuModal(false)}></div>

                        <div className="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                            <div className="bg-white px-6 pt-6 pb-4">
                                <div className="flex items-center justify-between border-b border-gray-200 pb-4">
                                    <h3 className="text-lg font-medium text-gray-900">
                                        Selecione um Menu para Gerenciar
                                    </h3>
                                    <button
                                        onClick={() => setShowMenuModal(false)}
                                        className="text-gray-400 hover:text-gray-600"
                                    >
                                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div className="px-6 pb-6">
                                <p className="mb-4 text-sm text-gray-600">
                                    Nivel de Acesso: <span className="font-semibold">{selectedAccessLevelForMenus.name}</span>
                                </p>

                                {menus && menus.length > 0 ? (
                                    <div className="grid grid-cols-1 gap-3">
                                        {menus.map((menu) => (
                                        <button
                                            key={menu.id}
                                            onClick={() => {
                                                router.visit(route('access-levels.menus.pages.manage', {
                                                    accessLevel: selectedAccessLevelForMenus.id,
                                                    menu: menu.id
                                                }));
                                            }}
                                            className="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
                                        >
                                            <div className="flex items-center">
                                                {menu.icon && (
                                                    <i className={`${menu.icon} mr-3 text-gray-600`}></i>
                                                )}
                                                <span className="font-medium text-gray-900">{menu.name}</span>
                                            </div>
                                            <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-gray-600">
                                        Nenhum menu disponivel.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Create Modal */}
            <AccessLevelCreateModal
                show={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                colorThemes={colorThemes}
            />

            {/* Delete Confirmation Modal */}
            {showDeleteModal && accessLevelToDelete && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={handleCancelDelete}></div>

                        <div className="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div className="sm:flex sm:items-start">
                                    <div className="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                        <svg className="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </div>
                                    <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                        <h3 className="text-lg leading-6 font-medium text-gray-900">
                                            Excluir Nivel de Acesso
                                        </h3>
                                        <div className="mt-2">
                                            <p className="text-sm text-gray-500">
                                                Tem certeza que deseja excluir o nivel de acesso{' '}
                                                <span className="font-semibold text-gray-700">"{accessLevelToDelete.name}"</span>?
                                            </p>
                                            <p className="text-sm text-red-600 mt-2">
                                                Esta acao ira remover todas as permissoes associadas a este nivel e nao pode ser desfeita.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                                <Button
                                    variant="danger"
                                    onClick={handleConfirmDelete}
                                    disabled={deleting}
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    )}
                                >
                                    {deleting ? 'Excluindo...' : 'Excluir'}
                                </Button>
                                <Button
                                    variant="secondary"
                                    onClick={handleCancelDelete}
                                    disabled={deleting}
                                >
                                    Cancelar
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
