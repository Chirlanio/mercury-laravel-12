import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Button from '@/Components/Button';

export default function Index({ accessLevels, categories, groupedAccessLevels, filters, stats, menus = [] }) {
    const { flash } = usePage().props;
    const [showViewModal, setShowViewModal] = useState(false);
    const [selectedAccessLevel, setSelectedAccessLevel] = useState(null);
    const [showMenuModal, setShowMenuModal] = useState(false);
    const [selectedAccessLevelForMenus, setSelectedAccessLevelForMenus] = useState(null);

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
                        <span className="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Gestão</span>
                    )}
                    {accessLevel.is_super_admin && (
                        <span className="px-2 py-1 bg-gray-900 text-white text-xs rounded-full">Super Admin</span>
                    )}
                    {accessLevel.is_level_1 && (
                        <span className="px-2 py-1 bg-indigo-100 text-indigo-800 text-xs rounded-full">Nível 1</span>
                    )}
                </div>
            )
        },
        {
            key: 'pages_access',
            label: 'Acesso às Páginas',
            render: (accessLevel) => (
                <div className="text-sm text-gray-600">
                    <span className="font-medium text-green-600">{accessLevel.authorized_pages_count}</span>
                    <span className="mx-1">/</span>
                    <span className="font-medium text-gray-900">{accessLevel.total_pages_count}</span>
                    <span className="ml-1 text-xs">páginas</span>
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
            label: 'Ações',
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
                        title="Gerenciar permissões"
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
                </div>
            )
        }
    ];

    const handleViewAccessLevel = async (accessLevelId) => {
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
                setShowViewModal(true);
            } else {
                console.error('Erro ao carregar nível de acesso');
            }
        } catch (error) {
            console.error('Erro ao carregar nível de acesso:', error);
        }
    };

    const filterOptions = [
        {
            key: 'category',
            label: 'Categoria',
            type: 'select',
            options: [
                { value: '', label: 'Todas as categorias' },
                { value: 'administrative', label: 'Administrativo' },
                { value: 'operational', label: 'Operacional' },
                { value: 'financial', label: 'Financeiro' },
                { value: 'human_resources', label: 'Recursos Humanos' },
                { value: 'commercial', label: 'Comercial' },
                { value: 'management', label: 'Gestão' }
            ]
        }
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        Níveis de Acesso
                    </h2>
                </div>
            }
        >
            <Head title="Níveis de Acesso" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
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

                    {/* Statistics Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Total</div>
                            <div className="text-2xl font-bold text-gray-900">{stats.total}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Administrativo</div>
                            <div className="text-2xl font-bold text-blue-600">{stats.administrative}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Operacional</div>
                            <div className="text-2xl font-bold text-green-600">{stats.operational}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Financeiro</div>
                            <div className="text-2xl font-bold text-yellow-600">{stats.financial}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">RH</div>
                            <div className="text-2xl font-bold text-purple-600">{stats.human_resources}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Comercial</div>
                            <div className="text-2xl font-bold text-orange-600">{stats.commercial}</div>
                        </div>
                    </div>

                    <div className="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div className="p-6">
                            <DataTable
                                data={accessLevels}
                                columns={columns}
                                filters={filters}
                                filterOptions={filterOptions}
                                searchPlaceholder="Buscar por nome..."
                                onFilterChange={(newFilters) => {
                                    router.get('/access-levels', newFilters, {
                                        preserveState: true,
                                        replace: true
                                    });
                                }}
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* View Modal */}
            {showViewModal && selectedAccessLevel && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={() => setShowViewModal(false)}></div>

                        <div className="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full" style={{ maxHeight: '90vh' }}>
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
                                        onClick={() => setShowViewModal(false)}
                                        className="text-gray-400 hover:text-gray-600"
                                    >
                                        <i className="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            <div className="px-6 pb-6 overflow-y-auto" style={{ maxHeight: 'calc(90vh - 120px)' }}>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    {/* Informações Básicas */}
                                    <div className="space-y-4">
                                        <h4 className="font-medium text-gray-900 text-sm uppercase tracking-wide border-b border-gray-200 pb-2">
                                            Informações Básicas
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

                                    {/* Categorias e Características */}
                                    <div className="space-y-4">
                                        <h4 className="font-medium text-gray-900 text-sm uppercase tracking-wide border-b border-gray-200 pb-2">
                                            Categorias e Características
                                        </h4>

                                        <div className="space-y-3">
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Administrativo</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_administrative ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_administrative ? 'Sim' : 'Não'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Operacional</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_operational ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_operational ? 'Sim' : 'Não'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Financeiro</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_financial ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_financial ? 'Sim' : 'Não'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Recursos Humanos</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_human_resources ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_human_resources ? 'Sim' : 'Não'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Comercial</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_commercial ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_commercial ? 'Sim' : 'Não'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Gestão</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_management ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_management ? 'Sim' : 'Não'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Super Admin</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_super_admin ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_super_admin ? 'Sim' : 'Não'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700">Nível 1</span>
                                                <span className={`px-2 py-1 text-xs rounded-full ${selectedAccessLevel.is_level_1 ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-500'}`}>
                                                    {selectedAccessLevel.is_level_1 ? 'Sim' : 'Não'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Páginas Autorizadas */}
                                {selectedAccessLevel.authorized_pages && selectedAccessLevel.authorized_pages.length > 0 && (
                                    <div className="mt-6">
                                        <h4 className="font-medium text-gray-900 text-sm uppercase tracking-wide border-b border-gray-200 pb-2 mb-4">
                                            Páginas Autorizadas ({selectedAccessLevel.authorized_pages.length})
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

                                {/* Total de Páginas */}
                                {selectedAccessLevel.total_pages && selectedAccessLevel.total_pages.length > 0 && (
                                    <div className="mt-6">
                                        <h4 className="font-medium text-gray-900 text-sm uppercase tracking-wide border-b border-gray-200 pb-2 mb-4">
                                            Todas as Páginas ({selectedAccessLevel.total_pages.length})
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
                        </div>
                    </div>
                </div>
            )}

            {/* Menu Selection Modal */}
            {showMenuModal && selectedAccessLevelForMenus && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={() => setShowMenuModal(false)}></div>

                        <div className="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full dark:bg-gray-800">
                            <div className="bg-white px-6 pt-6 pb-4 dark:bg-gray-800">
                                <div className="flex items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-700">
                                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        Selecione um Menu para Gerenciar
                                    </h3>
                                    <button
                                        onClick={() => setShowMenuModal(false)}
                                        className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div className="px-6 pb-6 dark:bg-gray-800">
                                <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                                    Nível de Acesso: <span className="font-semibold">{selectedAccessLevelForMenus.name}</span>
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
                                            className="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors dark:bg-gray-700 dark:hover:bg-gray-600"
                                        >
                                            <div className="flex items-center">
                                                {menu.icon && (
                                                    <i className={`${menu.icon} mr-3 text-gray-600 dark:text-gray-300`}></i>
                                                )}
                                                <span className="font-medium text-gray-900 dark:text-gray-100">{menu.name}</span>
                                            </div>
                                            <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        Nenhum menu disponível.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}