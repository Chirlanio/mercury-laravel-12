import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';

export default function Permissions({ auth, accessLevel, pages, menus, stats }) {
    const [permissions, setPermissions] = useState(() => {
        const perms = {};
        pages.forEach(page => {
            perms[page.id] = {
                has_permission: page.has_permission,
                menu_id: page.menu_id,
                dropdown: page.dropdown,
                lib_menu: page.lib_menu,
                order: page.order,
            };
        });
        return perms;
    });

    const [editingPage, setEditingPage] = useState(null);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editForm, setEditForm] = useState({
        menu_id: null,
        dropdown: false,
        lib_menu: false,
        order: 0,
    });

    // Alternar permissão
    const togglePermission = (pageId) => {
        setPermissions(prev => ({
            ...prev,
            [pageId]: {
                ...prev[pageId],
                has_permission: !prev[pageId].has_permission
            }
        }));
    };

    // Abrir modal de edição
    const openEditModal = (page) => {
        setEditingPage(page);
        setEditForm({
            menu_id: permissions[page.id].menu_id || '',
            dropdown: permissions[page.id].dropdown,
            lib_menu: permissions[page.id].lib_menu,
            order: permissions[page.id].order,
        });
        setShowEditModal(true);
    };

    // Salvar edição
    const saveEdit = () => {
        setPermissions(prev => ({
            ...prev,
            [editingPage.id]: {
                ...prev[editingPage.id],
                menu_id: editForm.menu_id || null,
                dropdown: editForm.dropdown,
                lib_menu: editForm.lib_menu,
                order: parseInt(editForm.order) || 0,
            }
        }));
        setShowEditModal(false);
        setEditingPage(null);
    };

    // Alternar exibição no menu
    const toggleLibMenu = (pageId) => {
        setPermissions(prev => ({
            ...prev,
            [pageId]: {
                ...prev[pageId],
                lib_menu: !prev[pageId].lib_menu
            }
        }));
    };

    // Alterar ordem
    const changeOrder = (pageId, direction) => {
        setPermissions(prev => ({
            ...prev,
            [pageId]: {
                ...prev[pageId],
                order: Math.max(0, (prev[pageId].order || 0) + direction)
            }
        }));
    };

    // Salvar todas as permissões
    const handleSaveAll = () => {
        const formattedPermissions = Object.keys(permissions).map(pageId => ({
            page_id: parseInt(pageId),
            has_permission: permissions[pageId].has_permission,
            menu_id: permissions[pageId].menu_id || null,
            dropdown: permissions[pageId].dropdown,
            lib_menu: permissions[pageId].lib_menu,
            order: permissions[pageId].order || 0,
        }));

        router.post(route('access-levels.permissions.update', accessLevel.id), {
            permissions: formattedPermissions
        }, {
            preserveScroll: true,
            onSuccess: () => {
                console.log('Permissões atualizadas com sucesso!');
            },
            onError: (errors) => {
                console.error('Erro ao atualizar permissões:', errors);
            }
        });
    };

    // Contar permissões ativas
    const countActivePermissions = () => {
        return Object.values(permissions).filter(p => p.has_permission).length;
    };

    const columns = [
        {
            field: 'id',
            label: 'ID',
            sortable: true,
            render: (page) => (
                <span className="text-gray-600 text-sm">{page.id}</span>
            )
        },
        {
            field: 'page_name',
            label: 'Página',
            sortable: true,
            render: (page) => (
                <div>
                    <div className="font-medium text-gray-900">{page.page_name}</div>
                    <div className="text-xs text-gray-500">{page.page_group}</div>
                    <div className="text-xs text-gray-400">{page.controller}@{page.method}</div>
                </div>
            )
        },
        {
            field: 'has_permission',
            label: 'Permissão',
            sortable: true,
            render: (page) => (
                <div className="flex items-center">
                    <input
                        type="checkbox"
                        checked={permissions[page.id]?.has_permission || false}
                        onChange={() => togglePermission(page.id)}
                        className="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                    />
                    <span className="ml-2 text-sm text-gray-600">
                        {permissions[page.id]?.has_permission ? 'Sim' : 'Não'}
                    </span>
                </div>
            )
        },
        {
            field: 'menu_name',
            label: 'Menu',
            sortable: true,
            render: (page) => {
                const menuId = permissions[page.id]?.menu_id;
                const menu = menus.find(m => m.id === menuId);
                const showInMenu = permissions[page.id]?.lib_menu;

                return (
                    <div className="flex flex-col space-y-2">
                        <div className="flex items-center">
                            <input
                                type="checkbox"
                                checked={showInMenu || false}
                                onChange={() => toggleLibMenu(page.id)}
                                className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                            />
                            <span className="ml-2 text-xs text-gray-600">
                                Exibir no menu
                            </span>
                        </div>
                        <div className="text-sm">
                            {menu ? (
                                <span className="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                                    <i className={`${menu.icon} mr-1`}></i>
                                    {menu.name}
                                </span>
                            ) : (
                                <span className="text-gray-400 text-xs">Nenhum menu</span>
                            )}
                        </div>
                    </div>
                );
            }
        },
        {
            field: 'dropdown',
            label: 'Dropdown',
            sortable: true,
            render: (page) => (
                <div className="text-center">
                    {permissions[page.id]?.dropdown ? (
                        <span className="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Sim</span>
                    ) : (
                        <span className="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs">Não</span>
                    )}
                </div>
            )
        },
        {
            field: 'order',
            label: 'Ordem',
            sortable: true,
            render: (page) => (
                <div className="flex items-center space-x-1">
                    <button
                        onClick={() => changeOrder(page.id, -1)}
                        className="p-1 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded"
                        title="Diminuir ordem"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
                        </svg>
                    </button>
                    <span className="px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm font-medium min-w-[30px] text-center">
                        {permissions[page.id]?.order || 0}
                    </span>
                    <button
                        onClick={() => changeOrder(page.id, 1)}
                        className="p-1 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded"
                        title="Aumentar ordem"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>
            )
        },
        {
            field: 'actions',
            label: 'Ações',
            render: (page) => (
                <Button
                    variant="warning"
                    size="sm"
                    onClick={() => openEditModal(page)}
                    icon={({ className }) => (
                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    )}
                />
            )
        }
    ];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={`Permissões - ${accessLevel.name}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center">
                                <button
                                    onClick={() => router.visit(route('access-levels.index'))}
                                    className="mr-4 text-gray-600 hover:text-gray-900"
                                >
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                </button>
                                <div>
                                    <h1 className="text-3xl font-bold text-gray-900">
                                        Gerenciar Permissões
                                    </h1>
                                    <p className="mt-1 text-sm text-gray-600">
                                        Perfil: <span className="font-medium" style={{ color: accessLevel.color }}>{accessLevel.name}</span>
                                    </p>
                                </div>
                            </div>
                            <div className="text-right">
                                <p className="text-sm text-gray-600">
                                    <span className="font-medium text-green-600">{countActivePermissions()}</span> de{' '}
                                    <span className="font-medium">{stats.total_pages}</span> páginas autorizadas
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Tabela de Permissões */}
                    <DataTable
                        data={{ data: pages }}
                        columns={columns}
                        searchPlaceholder="Buscar páginas..."
                        emptyMessage="Nenhuma página encontrada"
                        perPageOptions={[10, 25, 50, 100]}
                    />

                    {/* Botões de ação */}
                    <div className="mt-6 flex justify-end space-x-4">
                        <Button
                            variant="outline"
                            onClick={() => router.visit(route('access-levels.index'))}
                        >
                            Cancelar
                        </Button>
                        <Button
                            variant="primary"
                            onClick={handleSaveAll}
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                            )}
                        >
                            Salvar Todas as Permissões
                        </Button>
                    </div>
                </div>
            </div>

            {/* Modal de Edição */}
            <Modal show={showEditModal} onClose={() => setShowEditModal(false)} maxWidth="2xl">
                {editingPage && (
                    <div className="p-6">
                        <h2 className="text-lg font-medium text-gray-900 mb-4">
                            Editar Configurações da Página
                        </h2>
                        <p className="text-sm text-gray-600 mb-6">
                            Página: <span className="font-medium">{editingPage.page_name}</span>
                        </p>

                        <div className="space-y-4">
                            {/* Exibir no Menu */}
                            <div>
                                <label className="flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={editForm.lib_menu}
                                        onChange={(e) => setEditForm({ ...editForm, lib_menu: e.target.checked })}
                                        className="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span className="ml-2 text-sm font-medium text-gray-700">
                                        Exibir no Menu
                                    </span>
                                </label>
                                <p className="mt-1 ml-7 text-xs text-gray-500">
                                    Marque para que esta página apareça no menu lateral do sistema
                                </p>
                            </div>

                            {/* Menu */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Menu
                                </label>
                                <select
                                    value={editForm.menu_id || ''}
                                    onChange={(e) => setEditForm({ ...editForm, menu_id: e.target.value ? parseInt(e.target.value) : null })}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Nenhum menu</option>
                                    {menus.map(menu => (
                                        <option key={menu.id} value={menu.id}>
                                            {menu.name}
                                        </option>
                                    ))}
                                </select>
                                <p className="mt-1 text-xs text-gray-500">
                                    Selecione em qual menu esta página aparecerá
                                </p>
                            </div>

                            {/* Dropdown */}
                            <div>
                                <label className="flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={editForm.dropdown}
                                        onChange={(e) => setEditForm({ ...editForm, dropdown: e.target.checked })}
                                        className="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span className="ml-2 text-sm font-medium text-gray-700">
                                        Exibir como Dropdown
                                    </span>
                                </label>
                                <p className="mt-1 ml-7 text-xs text-gray-500">
                                    Marque se esta página deve aparecer em um submenu dropdown
                                </p>
                            </div>

                            {/* Ordem */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Ordem
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={editForm.order}
                                    onChange={(e) => setEditForm({ ...editForm, order: parseInt(e.target.value) || 0 })}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p className="mt-1 text-xs text-gray-500">
                                    Define a ordem de exibição no menu (menor = primeiro)
                                </p>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end space-x-3">
                            <Button
                                variant="outline"
                                onClick={() => setShowEditModal(false)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                variant="primary"
                                onClick={saveEdit}
                            >
                                Salvar
                            </Button>
                        </div>
                    </div>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
