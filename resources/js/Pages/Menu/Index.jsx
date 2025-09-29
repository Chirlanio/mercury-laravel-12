import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable from '@/Components/DataTable';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ auth, menus = { data: [], links: [] }, types = {}, groupedMenus = {}, filters = {} }) {
    const [processing, setProcessing] = useState(false);

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
                    <button
                        onClick={() => router.get(`/menus/${menu.id}`)}
                        className="text-indigo-600 hover:text-indigo-900 text-sm"
                    >
                        Ver
                    </button>
                    <button
                        onClick={() => handleToggleStatus(menu.id, menu.is_active)}
                        disabled={processing}
                        className={`text-sm ${
                            menu.is_active
                                ? 'text-red-600 hover:text-red-900'
                                : 'text-green-600 hover:text-green-900'
                        }`}
                    >
                        {menu.is_active ? 'Desativar' : 'Ativar'}
                    </button>
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
                        <div>
                            <h2 className="text-2xl font-bold text-gray-900">
                                Itens de Menu
                            </h2>
                            <p className="mt-1 text-sm text-gray-600">
                                Lista de todos os itens de menu cadastrados no sistema, organizados por tipo e ordem.
                            </p>
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
        </AuthenticatedLayout>
    );
}