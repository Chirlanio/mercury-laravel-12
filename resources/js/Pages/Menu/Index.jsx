import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import Button from '@/Components/Button';
import GenericDetailModal from '@/Components/GenericDetailModal';
import GenericFormModal from '@/Components/GenericFormModal';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ auth, menus = { data: [], links: [] }, types = {}, groupedMenus = {}, nextOrder = 1, filters = {} }) {
    const [processing, setProcessing] = useState(false);
    const [showViewModal, setShowViewModal] = useState(false);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [selectedMenuId, setSelectedMenuId] = useState(null);
    const [selectedMenu, setSelectedMenu] = useState(null);

    const handleToggleStatus = async (menuId, currentStatus) => {
        const action = currentStatus ? 'desativar' : 'ativar';
        if (confirm(`Tem certeza que deseja ${action} este menu?`)) {
            setProcessing(true);

            const url = currentStatus ? `/menus/${menuId}/deactivate` : `/menus/${menuId}/activate`;

            router.post(url, {}, {
                onFinish: () => setProcessing(false),
                preserveScroll: true,
            });
        }
    };

    const handleMoveUp = (menuId) => {
        setProcessing(true);
        router.post(`/menus/${menuId}/move-up`, {}, {
            onFinish: () => setProcessing(false),
            preserveScroll: true,
        });
    };

    const handleMoveDown = (menuId) => {
        setProcessing(true);
        router.post(`/menus/${menuId}/move-down`, {}, {
            onFinish: () => setProcessing(false),
            preserveScroll: true,
        });
    };

    const getTypeBadge = (menu) => {
        if (menu.is_main_menu) return { label: 'Principal', color: 'bg-blue-100 text-blue-800' };
        if (menu.is_hr_menu) return { label: 'RH', color: 'bg-purple-100 text-purple-800' };
        if (menu.is_utility_menu) return { label: 'Utilidades', color: 'bg-green-100 text-green-800' };
        if (menu.is_system_menu) return { label: 'Sistema', color: 'bg-gray-100 text-gray-800' };
        return { label: 'Outros', color: 'bg-yellow-100 text-yellow-800' };
    };

    const getStatusBadge = (isActive) => {
        return isActive
            ? { label: 'Ativo', color: 'bg-green-100 text-green-800' }
            : { label: 'Inativo', color: 'bg-red-100 text-red-800' };
    };

    const handleViewMenu = (menuId) => {
        setSelectedMenuId(menuId);
        setShowViewModal(true);
    };

    const handleEditMenu = async (menu) => {
        // Se recebeu objeto menu direto (da tabela), usar ele
        if (menu && typeof menu === 'object') {
            setSelectedMenu(menu);
            setShowEditModal(true);
        } else {
            // Se recebeu apenas ID, buscar dados do menu
            try {
                const response = await fetch(`/menus/${menu}`, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    setSelectedMenu(data);
                    setShowEditModal(true);
                }
            } catch (error) {
                console.error('Erro ao carregar menu:', error);
            }
        }
    };

    // Configuração do GenericDetailModal
    const modalSections = [
        {
            title: 'Informações Básicas',
            fields: [
                { name: 'id', label: 'ID' },
                { name: 'name', label: 'Nome' },
                {
                    name: 'icon',
                    label: 'Ícone',
                    render: (value) => value ? (
                        <div className="flex items-center space-x-2">
                            <i className={value}></i>
                            <span className="font-mono text-xs text-gray-600">{value}</span>
                        </div>
                    ) : 'Não informado',
                },
                {
                    name: 'order',
                    label: 'Ordem',
                    render: (value) => String(value).padStart(2, '0'),
                },
            ],
        },
        {
            title: 'Classificação',
            fields: [
                {
                    name: 'type',
                    label: 'Tipo',
                    type: 'badge',
                    render: (value, data) => {
                        const type = getTypeBadge(data);
                        return (
                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${type.color}`}>
                                {type.label}
                            </span>
                        );
                    },
                },
                {
                    name: 'is_active',
                    label: 'Status',
                    type: 'badge',
                    render: (value, data) => {
                        const status = getStatusBadge(value);
                        return (
                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${status.color}`}>
                                {status.label}
                            </span>
                        );
                    },
                },
                { name: 'is_main_menu', label: 'Menu Principal', type: 'boolean' },
                { name: 'is_hr_menu', label: 'Menu RH', type: 'boolean' },
                { name: 'is_utility_menu', label: 'Menu Utilidades', type: 'boolean' },
                { name: 'is_system_menu', label: 'Menu Sistema', type: 'boolean' },
            ],
        },
        {
            title: 'Hierarquia',
            fields: [
                {
                    name: 'parent_id',
                    label: 'Menu Pai',
                    render: (value, data) => value ? `#${value}` : 'Menu de nível principal',
                },
                {
                    name: 'parent',
                    label: 'Nome do Menu Pai',
                    path: 'parent.name',
                    emptyText: 'Nenhum (menu raiz)',
                },
            ],
        },
        {
            title: 'Informações do Sistema',
            fullWidth: true,
            fields: [
                { name: 'created_at', label: 'Criado em', type: 'datetime' },
                { name: 'updated_at', label: 'Atualizado em', type: 'datetime' },
            ],
        },
    ];

    const modalActions = [
        {
            label: 'Editar Menu',
            variant: 'warning',
            icon: ({ className }) => (
                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            ),
            onClick: (data) => {
                setShowViewModal(false);
                handleEditMenu(data);
            },
        },
        {
            label: 'Mover para Cima',
            variant: 'secondary',
            icon: ({ className }) => (
                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
                </svg>
            ),
            onClick: (data) => {
                setShowViewModal(false);
                handleMoveUp(data.id);
            },
        },
        {
            label: 'Mover para Baixo',
            variant: 'secondary',
            icon: ({ className }) => (
                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            ),
            onClick: (data) => {
                setShowViewModal(false);
                handleMoveDown(data.id);
            },
        },
        {
            label: 'Alternar Status',
            variant: 'primary',
            icon: ({ className }) => (
                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
            ),
            onClick: (data) => {
                setShowViewModal(false);
                handleToggleStatus(data.id, data.is_active);
            },
        },
    ];

    // Sugestões de ícones FontAwesome
    const iconSuggestions = [
        'fas fa-home',
        'fas fa-user',
        'fas fa-users',
        'fas fa-cog',
        'fas fa-cogs',
        'fas fa-chart-bar',
        'fas fa-chart-line',
        'fas fa-shopping-cart',
        'fas fa-truck',
        'fas fa-boxes',
        'fas fa-dollar-sign',
        'fas fa-calculator',
        'fas fa-briefcase',
        'fas fa-building',
        'fas fa-store',
        'fas fa-warehouse',
        'fas fa-clipboard-list',
        'fas fa-tasks',
        'fas fa-file-alt',
        'fas fa-folder',
        'fas fa-book',
        'fas fa-graduation-cap',
        'fas fa-question-circle',
        'fas fa-life-ring',
        'fas fa-headset',
        'fas fa-comments',
        'fas fa-bell',
        'fas fa-envelope',
        'fas fa-calendar',
        'fas fa-clock',
    ];

    // Configuração do formulário de criação e edição
    const formSections = [
        {
            title: 'Informações Básicas',
            columns: 'md:grid-cols-2',
            fields: [
                {
                    name: 'name',
                    label: 'Nome do Menu',
                    type: 'text',
                    required: true,
                    placeholder: 'Ex: Produtos',
                },
                {
                    name: 'order',
                    label: 'Ordem',
                    type: 'number',
                    required: true,
                    defaultValue: nextOrder,
                    helperText: 'Ordem de exibição no menu',
                },
            ],
        },
        {
            title: 'Configuração Visual',
            fields: [
                {
                    name: 'icon',
                    label: 'Ícone (FontAwesome)',
                    type: 'text',
                    placeholder: 'Ex: fas fa-box',
                    helperText: 'Classe do ícone FontAwesome. Clique em uma sugestão abaixo.',
                    fullWidth: true,
                },
                {
                    name: 'icon_preview',
                    type: 'custom',
                    fullWidth: true,
                    render: (data, setData, errors) => (
                        <div className="space-y-3">
                            {data.icon && (
                                <div className="flex items-center space-x-3 p-3 bg-white rounded border border-gray-300">
                                    <i className={`${data.icon} text-2xl text-indigo-600`}></i>
                                    <span className="text-sm text-gray-600">Preview do ícone</span>
                                </div>
                            )}
                            <div>
                                <p className="text-xs text-gray-500 mb-2">Sugestões de ícones:</p>
                                <div className="grid grid-cols-10 gap-2 max-h-40 overflow-y-auto border border-gray-200 p-2 rounded bg-white">
                                    {iconSuggestions.map((iconClass) => (
                                        <button
                                            key={iconClass}
                                            type="button"
                                            onClick={() => setData('icon', iconClass)}
                                            className={`flex items-center justify-center w-10 h-10 border rounded hover:bg-indigo-50 hover:border-indigo-300 transition-colors ${
                                                data.icon === iconClass ? 'bg-indigo-100 border-indigo-500' : 'border-gray-300'
                                            }`}
                                            title={iconClass}
                                        >
                                            <i className={`${iconClass} text-gray-600`}></i>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>
                    ),
                },
            ],
        },
        {
            title: 'Status',
            fields: [
                {
                    name: 'is_active',
                    label: 'Menu Ativo',
                    type: 'checkbox',
                    defaultValue: true,
                },
            ],
        },
    ];

    const columns = [
        {
            label: 'Ordem',
            field: 'order',
            sortable: true,
            render: (menu) => (
                <div className="flex items-center space-x-1">
                    <span className="font-mono text-sm text-gray-600">
                        {String(menu.order).padStart(2, '0')}
                    </span>
                    <div className="flex flex-col">
                        <button
                            onClick={() => handleMoveUp(menu.id)}
                            disabled={processing}
                            className="text-xs text-gray-400 hover:text-gray-600 disabled:opacity-50"
                            title="Mover para cima"
                        >
                            ▲
                        </button>
                        <button
                            onClick={() => handleMoveDown(menu.id)}
                            disabled={processing}
                            className="text-xs text-gray-400 hover:text-gray-600 disabled:opacity-50"
                            title="Mover para baixo"
                        >
                            ▼
                        </button>
                    </div>
                </div>
            )
        },
        {
            label: 'Menu',
            field: 'name',
            sortable: true,
            render: (menu) => (
                <div className="flex items-center">
                    {menu.icon && (
                        <span className="mr-3 text-gray-500 w-5 h-5 flex items-center justify-center">
                            <i className={menu.icon}></i>
                        </span>
                    )}
                    <div>
                        <div className="text-sm font-medium text-gray-900">
                            {menu.name}
                        </div>
                        {menu.icon && (
                            <div className="text-xs text-gray-500">
                                {menu.icon}
                            </div>
                        )}
                    </div>
                </div>
            )
        },
        {
            label: 'Tipo',
            field: 'type',
            sortable: false,
            render: (menu) => {
                const type = getTypeBadge(menu);
                return (
                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${type.color}`}>
                        {type.label}
                    </span>
                );
            }
        },
        {
            label: 'Status',
            field: 'is_active',
            sortable: true,
            render: (menu) => {
                const status = getStatusBadge(menu.is_active);
                return (
                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${status.color}`}>
                        {status.label}
                    </span>
                );
            }
        },
        {
            label: 'Criado em',
            field: 'created_at',
            sortable: true,
            render: (menu) => new Date(menu.created_at).toLocaleDateString('pt-BR')
        },
        {
            label: 'Ações',
            field: 'actions',
            sortable: false,
            render: (menu) => (
                <div className="flex items-center space-x-2">
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            handleViewMenu(menu.id);
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
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            handleEditMenu(menu);
                        }}
                        variant="warning"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        )}
                        title="Editar menu"
                    />
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            handleToggleStatus(menu.id, menu.is_active);
                        }}
                        disabled={processing}
                        variant={menu.is_active ? 'danger' : 'success'}
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            menu.is_active ? (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            ) : (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                            )
                        )}
                        title={menu.is_active ? 'Desativar' : 'Ativar'}
                    />
                </div>
            )
        }
    ];

    const filterOptions = [
        { value: '', label: 'Todos os tipos' },
        ...Object.entries(types).map(([value, label]) => ({
            value,
            label
        }))
    ];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Itens de Menu" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-start">
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900">
                                    Itens de Menu
                                </h2>
                                <p className="mt-1 text-sm text-gray-600">
                                    Lista de todos os itens de menu cadastrados no sistema, organizados por tipo e ordem.
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
                                Novo Menu
                            </Button>
                        </div>
                    </div>

                    {/* Resumo por tipo */}
                    <div className="mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            {Object.entries(groupedMenus).map(([type, items]) => (
                                <div key={type} className="bg-white rounded-lg shadow p-4">
                                    <h3 className="text-sm font-medium text-gray-500 mb-2">
                                        {type}
                                    </h3>
                                    <div className="text-2xl font-bold text-gray-900">
                                        {Object.keys(items).length}
                                    </div>
                                    <div className="text-xs text-gray-500 mt-1">
                                        {Object.keys(items).length === 1 ? 'item' : 'itens'}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* DataTable com filtros */}
                    <DataTable
                        data={menus}
                        columns={columns}
                        searchable={true}
                        searchPlaceholder="Buscar menus..."
                        perPageOptions={[10, 25, 50, 100]}
                        emptyMessage="Nenhum item de menu encontrado"
                        filters={[
                            {
                                field: 'type',
                                label: 'Tipo',
                                type: 'select',
                                options: filterOptions,
                                value: filters.type || ''
                            }
                        ]}
                    />
                </div>
            </div>

            {/* Modal de Visualização */}
            <GenericDetailModal
                show={showViewModal}
                onClose={() => setShowViewModal(false)}
                title="Detalhes do Menu"
                resourceId={selectedMenuId}
                fetchUrl="/menus"
                sections={modalSections}
                actions={modalActions}
                header={{
                    avatar: (data) => (
                        data.icon ? (
                            <div className="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center">
                                <i className={`${data.icon} text-2xl text-indigo-600`}></i>
                            </div>
                        ) : (
                            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
                                <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </div>
                        )
                    ),
                    title: (data) => data.name,
                    subtitle: (data) => `Ordem: ${String(data.order).padStart(2, '0')}`,
                    badges: (data) => {
                        const type = getTypeBadge(data);
                        const status = getStatusBadge(data.is_active);
                        return (
                            <>
                                <span className={`inline-flex px-3 py-1 text-xs font-semibold rounded-full ${type.color}`}>
                                    {type.label}
                                </span>
                                <span className={`inline-flex px-3 py-1 text-xs font-semibold rounded-full ${status.color}`}>
                                    {status.label}
                                </span>
                            </>
                        );
                    },
                }}
            />

            {/* Modal de Criação */}
            <GenericFormModal
                show={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                onSuccess={() => setShowCreateModal(false)}
                title="Criar Novo Menu"
                mode="create"
                sections={formSections}
                submitUrl="/menus"
                submitMethod="post"
                submitButtonText="Criar Menu"
                submitButtonIcon={({ className }) => (
                    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                )}
            />

            {/* Modal de Edição */}
            {selectedMenu && (
                <GenericFormModal
                    show={showEditModal}
                    onClose={() => {
                        setShowEditModal(false);
                        setSelectedMenu(null);
                    }}
                    onSuccess={() => {
                        setShowEditModal(false);
                        setSelectedMenu(null);
                    }}
                    title="Editar Menu"
                    mode="edit"
                    initialData={selectedMenu}
                    sections={formSections}
                    submitUrl={`/menus/${selectedMenu.id}`}
                    submitMethod="put"
                    submitButtonText="Salvar Alterações"
                    submitButtonIcon={({ className }) => (
                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                    )}
                />
            )}
        </AuthenticatedLayout>
    );
}