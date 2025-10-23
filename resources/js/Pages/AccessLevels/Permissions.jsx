import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import { ChevronUpIcon, ChevronDownIcon } from '@heroicons/react/24/outline';
import { useConfirm } from '@/Hooks/useConfirm';

export default function Permissions({ auth, accessLevel, pages, menus, stats }) {
    const { confirm, ConfirmDialogComponent } = useConfirm();

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

    const [sortField, setSortField] = useState('id');
    const [sortDirection, setSortDirection] = useState('asc');
    const [searchTerm, setSearchTerm] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const [perPage, setPerPage] = useState(50);

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

    // Alternar dropdown
    const toggleDropdown = (pageId) => {
        setPermissions(prev => ({
            ...prev,
            [pageId]: {
                ...prev[pageId],
                dropdown: !prev[pageId].dropdown
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
    const handleSaveAll = async () => {
        const activePermissions = countActivePermissions();
        const totalPages = Object.keys(permissions).length;

        const confirmed = await confirm({
            title: 'Salvar Permissões',
            message: `Você está prestes a salvar ${activePermissions} de ${totalPages} permissões para o perfil "${accessLevel.name}". Esta ação afetará o acesso dos usuários com este nível. Deseja continuar?`,
            confirmText: 'Sim, Salvar',
            cancelText: 'Cancelar',
            type: 'info',
        });

        if (!confirmed) return;

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

    // Ações em lote
    const toggleAllDropdown = (value) => {
        setPermissions(prev => {
            const updated = { ...prev };
            Object.keys(updated).forEach(pageId => {
                if (updated[pageId].has_permission) {
                    updated[pageId] = {
                        ...updated[pageId],
                        dropdown: value
                    };
                }
            });
            return updated;
        });
    };

    const toggleAllLibMenu = (value) => {
        setPermissions(prev => {
            const updated = { ...prev };
            Object.keys(updated).forEach(pageId => {
                if (updated[pageId].has_permission) {
                    updated[pageId] = {
                        ...updated[pageId],
                        lib_menu: value
                    };
                }
            });
            return updated;
        });
    };

    const toggleAllPermissions = (value) => {
        setPermissions(prev => {
            const updated = { ...prev };
            Object.keys(updated).forEach(pageId => {
                updated[pageId] = {
                    ...updated[pageId],
                    has_permission: value
                };
            });
            return updated;
        });
    };

    // Função de ordenação local
    const handleSort = (field) => {
        if (!field) return;

        let direction = 'asc';
        if (sortField === field && sortDirection === 'asc') {
            direction = 'desc';
        }

        setSortField(field);
        setSortDirection(direction);
        setCurrentPage(1); // Reset para primeira página ao ordenar
    };

    // Filtrar e ordenar dados localmente
    const getFilteredAndSortedPages = () => {
        let filtered = [...pages];

        // Aplicar busca
        if (searchTerm) {
            filtered = filtered.filter(page =>
                page.page_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                page.page_group?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                page.controller?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                page.method?.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }

        // Aplicar ordenação
        if (sortField) {
            filtered.sort((a, b) => {
                let aValue, bValue;

                switch (sortField) {
                    case 'id':
                        aValue = a.id;
                        bValue = b.id;
                        break;
                    case 'page_name':
                        aValue = a.page_name || '';
                        bValue = b.page_name || '';
                        break;
                    case 'has_permission':
                        aValue = permissions[a.id]?.has_permission ? 1 : 0;
                        bValue = permissions[b.id]?.has_permission ? 1 : 0;
                        break;
                    case 'menu_name':
                        const menuA = menus.find(m => m.id === permissions[a.id]?.menu_id);
                        const menuB = menus.find(m => m.id === permissions[b.id]?.menu_id);
                        aValue = menuA?.name || '';
                        bValue = menuB?.name || '';
                        break;
                    case 'dropdown':
                        aValue = permissions[a.id]?.dropdown ? 1 : 0;
                        bValue = permissions[b.id]?.dropdown ? 1 : 0;
                        break;
                    case 'order':
                        aValue = permissions[a.id]?.order || 0;
                        bValue = permissions[b.id]?.order || 0;
                        break;
                    default:
                        return 0;
                }

                if (typeof aValue === 'string' && typeof bValue === 'string') {
                    return sortDirection === 'asc'
                        ? aValue.localeCompare(bValue)
                        : bValue.localeCompare(aValue);
                } else {
                    return sortDirection === 'asc'
                        ? aValue - bValue
                        : bValue - aValue;
                }
            });
        }

        return filtered;
    };

    const filteredPages = getFilteredAndSortedPages();

    // Paginação local
    const totalPages = Math.ceil(filteredPages.length / perPage);
    const startIndex = (currentPage - 1) * perPage;
    const endIndex = startIndex + perPage;
    const paginatedPages = filteredPages.slice(startIndex, endIndex);

    // Reset para primeira página quando busca muda
    const handleSearchChange = (value) => {
        setSearchTerm(value);
        setCurrentPage(1);
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
                <div className="flex items-center justify-center">
                    <button
                        onClick={() => toggleDropdown(page.id)}
                        className={`px-3 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer ${
                            permissions[page.id]?.dropdown
                                ? 'bg-green-100 text-green-800 hover:bg-green-200'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                        }`}
                        title="Clique para alternar"
                    >
                        {permissions[page.id]?.dropdown ? 'Sim' : 'Não'}
                    </button>
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

                    {/* Card de Ajuda */}
                    <div className="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div className="flex items-start">
                            <svg className="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div className="text-sm">
                                <p className="font-medium text-blue-900 mb-2">Como funcionam os campos:</p>
                                <ul className="space-y-1 text-blue-800">
                                    <li><strong>Permissão:</strong> Define se o usuário pode acessar esta página</li>
                                    <li><strong>Exibir no Menu:</strong> Se marcado, a página aparece na barra lateral</li>
                                    <li><strong>Menu:</strong> Em qual menu a página será exibida</li>
                                    <li><strong>Dropdown:</strong> Se marcado, a página é exibida como um submenu dropdown (clique para expandir)</li>
                                    <li><strong>Ordem:</strong> Define a posição da página no menu (menor número = primeiro)</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {/* Ações em Lote */}
                    <div className="mb-6 bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <h3 className="text-sm font-medium text-gray-900 mb-3">Ações em Lote</h3>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => toggleAllPermissions(true)}
                            >
                                Permitir Todas
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => toggleAllPermissions(false)}
                            >
                                Bloquear Todas
                            </Button>
                            <span className="border-l border-gray-300 mx-2"></span>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => toggleAllLibMenu(true)}
                            >
                                Exibir Todas no Menu
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => toggleAllLibMenu(false)}
                            >
                                Ocultar Todas do Menu
                            </Button>
                            <span className="border-l border-gray-300 mx-2"></span>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => toggleAllDropdown(true)}
                            >
                                Marcar Todas como Dropdown
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => toggleAllDropdown(false)}
                            >
                                Desmarcar Todas como Dropdown
                            </Button>
                        </div>
                        <p className="mt-3 text-xs text-gray-500">
                            <strong>Nota:</strong> As ações de "Exibir/Ocultar no Menu" e "Dropdown" afetam apenas páginas com permissão ativada.
                        </p>
                    </div>

                    {/* Tabela de Permissões */}
                    <div className="bg-white overflow-hidden shadow-sm rounded-lg">
                        {/* Busca e controles */}
                        <div className="p-6 border-b border-gray-200">
                            <div className="flex items-center justify-between gap-4">
                                <input
                                    type="text"
                                    placeholder="Buscar páginas..."
                                    className="flex-1 max-w-md rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={searchTerm}
                                    onChange={(e) => handleSearchChange(e.target.value)}
                                />
                                <select
                                    value={perPage}
                                    onChange={(e) => {
                                        setPerPage(Number(e.target.value));
                                        setCurrentPage(1);
                                    }}
                                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value={25}>25 por página</option>
                                    <option value={50}>50 por página</option>
                                    <option value={100}>100 por página</option>
                                    <option value={filteredPages.length}>Todos</option>
                                </select>
                            </div>
                        </div>

                        {/* Tabela */}
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        {columns.map((column, index) => (
                                            <th
                                                key={index}
                                                className={`px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider ${
                                                    column.sortable ? 'cursor-pointer hover:bg-gray-100' : ''
                                                }`}
                                                onClick={() => column.sortable && handleSort(column.field)}
                                            >
                                                <div className="flex items-center space-x-1">
                                                    <span>{column.label}</span>
                                                    {column.sortable && (
                                                        <span>
                                                            {sortField === column.field ? (
                                                                sortDirection === 'asc' ? (
                                                                    <ChevronUpIcon className="w-4 h-4 text-gray-600" />
                                                                ) : (
                                                                    <ChevronDownIcon className="w-4 h-4 text-gray-600" />
                                                                )
                                                            ) : (
                                                                <ChevronUpIcon className="w-4 h-4 text-gray-400" />
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {paginatedPages.length > 0 ? (
                                        paginatedPages.map((page) => (
                                            <tr key={page.id} className="hover:bg-gray-50">
                                                {columns.map((column, colIndex) => (
                                                    <td key={colIndex} className="px-6 py-4 whitespace-nowrap">
                                                        {column.render(page)}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={columns.length} className="px-6 py-12 text-center text-gray-500">
                                                Nenhuma página encontrada
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Footer com paginação */}
                        <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-gray-700">
                                    Mostrando <span className="font-medium">{startIndex + 1}</span> a{' '}
                                    <span className="font-medium">{Math.min(endIndex, filteredPages.length)}</span> de{' '}
                                    <span className="font-medium">{filteredPages.length}</span> páginas
                                    {searchTerm && ` (filtradas de ${pages.length} no total)`}
                                </p>

                                {totalPages > 1 && (
                                    <div className="flex items-center space-x-2">
                                        <button
                                            onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
                                            disabled={currentPage === 1}
                                            className="px-3 py-1 rounded-md border border-gray-300 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            Anterior
                                        </button>

                                        <span className="text-sm text-gray-700">
                                            Página {currentPage} de {totalPages}
                                        </span>

                                        <button
                                            onClick={() => setCurrentPage(Math.min(totalPages, currentPage + 1))}
                                            disabled={currentPage === totalPages}
                                            className="px-3 py-1 rounded-md border border-gray-300 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            Próxima
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

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

            {/* Dialog de Confirmação */}
            <ConfirmDialogComponent />
        </AuthenticatedLayout>
    );
}
