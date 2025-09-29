import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({ auth, menu }) {
    const [processing, setProcessing] = useState(false);

    const handleToggleStatus = async () => {
        const action = menu.is_active ? 'desativar' : 'ativar';
        if (confirm(`Tem certeza que deseja ${action} este menu?`)) {
            setProcessing(true);

            const url = menu.is_active ? `/menus/${menu.id}/deactivate` : `/menus/${menu.id}/activate`;

            router.post(url, {}, {
                onFinish: () => setProcessing(false),
                onSuccess: () => router.get(`/menus/${menu.id}`),
            });
        }
    };

    const handleMoveUp = () => {
        setProcessing(true);
        router.post(`/menus/${menu.id}/move-up`, {}, {
            onFinish: () => setProcessing(false),
            onSuccess: () => router.get(`/menus/${menu.id}`),
        });
    };

    const handleMoveDown = () => {
        setProcessing(true);
        router.post(`/menus/${menu.id}/move-down`, {}, {
            onFinish: () => setProcessing(false),
            onSuccess: () => router.get(`/menus/${menu.id}`),
        });
    };

    const getMenuType = () => {
        if (menu.is_main_menu) return { label: 'Menu Principal', color: 'bg-blue-100 text-blue-800' };
        if (menu.is_hr_menu) return { label: 'Recursos Humanos', color: 'bg-purple-100 text-purple-800' };
        if (menu.is_utility_menu) return { label: 'Utilidades', color: 'bg-green-100 text-green-800' };
        if (menu.is_system_menu) return { label: 'Sistema', color: 'bg-gray-100 text-gray-800' };
        return { label: 'Outros', color: 'bg-yellow-100 text-yellow-800' };
    };

    const menuType = getMenuType();

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={`Menu: ${menu.name}`} />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex justify-between items-start">
                        <div>
                            <div className="flex items-center mb-2">
                                <button
                                    onClick={() => router.get('/menus')}
                                    className="text-indigo-600 hover:text-indigo-900 text-sm mr-2"
                                >
                                    ← Voltar para lista
                                </button>
                            </div>
                            <h2 className="text-2xl font-bold text-gray-900 flex items-center">
                                {menu.icon && (
                                    <span className="mr-3 text-gray-500">
                                        <i className={menu.icon}></i>
                                    </span>
                                )}
                                {menu.name}
                            </h2>
                            <p className="mt-1 text-sm text-gray-600">
                                Detalhes do item de menu
                            </p>
                        </div>

                        <div className="flex items-center space-x-3">
                            <button
                                onClick={handleMoveUp}
                                disabled={processing}
                                className="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-md text-sm font-medium disabled:opacity-50"
                                title="Mover para cima"
                            >
                                ↑ Subir
                            </button>
                            <button
                                onClick={handleMoveDown}
                                disabled={processing}
                                className="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-md text-sm font-medium disabled:opacity-50"
                                title="Mover para baixo"
                            >
                                ↓ Descer
                            </button>
                            <button
                                onClick={handleToggleStatus}
                                disabled={processing}
                                className={`px-4 py-2 rounded-md text-sm font-medium ${
                                    menu.is_active
                                        ? 'bg-red-600 hover:bg-red-700 text-white'
                                        : 'bg-green-600 hover:bg-green-700 text-white'
                                } disabled:opacity-50`}
                            >
                                {menu.is_active ? 'Desativar' : 'Ativar'}
                            </button>
                        </div>
                    </div>

                    {/* Conteúdo principal */}
                    <div className="bg-white shadow rounded-lg">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <h3 className="text-lg font-medium text-gray-900">
                                Informações do Menu
                            </h3>
                        </div>

                        <div className="px-6 py-4">
                            <dl className="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                {/* Nome */}
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Nome</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{menu.name}</dd>
                                </div>

                                {/* Ordem */}
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Ordem</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        <span className="font-mono bg-gray-100 px-2 py-1 rounded">
                                            {String(menu.order).padStart(2, '0')}
                                        </span>
                                    </dd>
                                </div>

                                {/* Ícone */}
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Ícone</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {menu.icon ? (
                                            <div className="flex items-center">
                                                <span className="mr-2 text-lg">
                                                    <i className={menu.icon}></i>
                                                </span>
                                                <code className="text-xs bg-gray-100 px-2 py-1 rounded">
                                                    {menu.icon}
                                                </code>
                                            </div>
                                        ) : (
                                            <span className="text-gray-400 italic">Nenhum ícone definido</span>
                                        )}
                                    </dd>
                                </div>

                                {/* Tipo */}
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Tipo</dt>
                                    <dd className="mt-1">
                                        <span className={`inline-flex px-3 py-1 text-xs font-semibold rounded-full ${menuType.color}`}>
                                            {menuType.label}
                                        </span>
                                    </dd>
                                </div>

                                {/* Status */}
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Status</dt>
                                    <dd className="mt-1">
                                        <span className={`inline-flex px-3 py-1 text-xs font-semibold rounded-full ${
                                            menu.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800'
                                        }`}>
                                            {menu.is_active ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </dd>
                                </div>

                                {/* Data de criação */}
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Criado em</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {new Date(menu.created_at).toLocaleString('pt-BR')}
                                    </dd>
                                </div>

                                {/* Última atualização */}
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Última atualização</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {new Date(menu.updated_at).toLocaleString('pt-BR')}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    {/* Seção de categorização */}
                    <div className="mt-6 bg-white shadow rounded-lg">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <h3 className="text-lg font-medium text-gray-900">
                                Categorização
                            </h3>
                        </div>

                        <div className="px-6 py-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div className={`p-3 rounded-lg border-2 ${
                                    menu.is_main_menu ? 'border-blue-200 bg-blue-50' : 'border-gray-200 bg-gray-50'
                                }`}>
                                    <div className="flex items-center">
                                        <div className={`w-3 h-3 rounded-full mr-2 ${
                                            menu.is_main_menu ? 'bg-blue-500' : 'bg-gray-300'
                                        }`}></div>
                                        <span className="text-sm font-medium">Menu Principal</span>
                                    </div>
                                </div>

                                <div className={`p-3 rounded-lg border-2 ${
                                    menu.is_hr_menu ? 'border-purple-200 bg-purple-50' : 'border-gray-200 bg-gray-50'
                                }`}>
                                    <div className="flex items-center">
                                        <div className={`w-3 h-3 rounded-full mr-2 ${
                                            menu.is_hr_menu ? 'bg-purple-500' : 'bg-gray-300'
                                        }`}></div>
                                        <span className="text-sm font-medium">Recursos Humanos</span>
                                    </div>
                                </div>

                                <div className={`p-3 rounded-lg border-2 ${
                                    menu.is_utility_menu ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50'
                                }`}>
                                    <div className="flex items-center">
                                        <div className={`w-3 h-3 rounded-full mr-2 ${
                                            menu.is_utility_menu ? 'bg-green-500' : 'bg-gray-300'
                                        }`}></div>
                                        <span className="text-sm font-medium">Utilidades</span>
                                    </div>
                                </div>

                                <div className={`p-3 rounded-lg border-2 ${
                                    menu.is_system_menu ? 'border-gray-400 bg-gray-100' : 'border-gray-200 bg-gray-50'
                                }`}>
                                    <div className="flex items-center">
                                        <div className={`w-3 h-3 rounded-full mr-2 ${
                                            menu.is_system_menu ? 'bg-gray-600' : 'bg-gray-300'
                                        }`}></div>
                                        <span className="text-sm font-medium">Sistema</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}